<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * v0.8.63.2 — production file-system safety for exports/backups.
 *
 * Real production failures this prevents:
 *  - "Call to undefined function disk_free_space()": hosts list it in disable_functions; in
 *    PHP 8 a disabled function is UNDEFINED and the "@" operator does NOT stop the fatal.
 *  - "fwrite(): Write ... failed with errno=28 No space left on device": every write is checked,
 *    partial files are removed, and nothing is left behind.
 *
 * Temp files live in a controlled location (WordPress temp dir — get_temp_dir(), honours
 * WP_TEMP_DIR — never wp-content/uploads), always under a "bcrm-tmp-" prefix, are removed
 * on success, on failure AND on shutdown (fatal/abort/exit), and stale leftovers older than
 * two hours are swept.
 */
class Biawin_CRM_File_Safety
{
    public const TMP_PREFIX = 'bcrm-tmp-';
    private static $registered = [];
    private static $shutdown_hooked = false;

    /** True when a PHP function exists and is not listed in disable_functions. */
    public static function function_available(string $name): bool
    {
        if (!function_exists($name) || !is_callable($name)) return false;
        $disabled = array_map('trim', explode(',', strtolower((string)@ini_get('disable_functions'))));
        return !in_array(strtolower($name), $disabled, true);
    }

    /**
     * Free bytes on the filesystem holding $path, or NULL when it cannot be determined
     * (function disabled, error, open_basedir…). Never throws, never fatals.
     * Filter 'bcrm_disk_free_bytes' lets a host/operator (or QA) supply/override the value.
     */
    public static function disk_free_bytes(string $path): ?float
    {
        $value = null;
        if (self::function_available('disk_free_space')) {
            try {
                $probe = is_dir($path) ? $path : dirname($path);
                $raw = @disk_free_space($probe);
                if ($raw !== false && is_numeric($raw)) $value = (float)$raw;
            } catch (Throwable $e) {
                $value = null;
            }
        }
        if (function_exists('apply_filters')) $value = apply_filters('bcrm_disk_free_bytes', $value, $path);
        return ($value === null || !is_numeric($value)) ? null : (float)$value;
    }

    /** Base directory for temporary export files (WordPress temp dir, not uploads). */
    public static function temp_base(): string
    {
        $base = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        return rtrim(str_replace('\\', '/', (string)$base), '/');
    }

    /** Create a private temp directory for one operation; '' on failure. Auto-cleaned on shutdown. */
    public static function make_temp_dir(string $purpose): string
    {
        $base = self::temp_base();
        if ($base === '' || !@is_dir($base) || !@is_writable($base)) return '';
        $purpose = preg_replace('/[^a-z0-9\-]/', '', strtolower($purpose)) ?: 'job';
        for ($i = 0; $i < 3; $i++) {
            $dir = $base . '/' . self::TMP_PREFIX . $purpose . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(5));
            if (@mkdir($dir, 0700)) {
                self::register_cleanup($dir);
                return $dir;
            }
        }
        return '';
    }

    /** Remember a path for guaranteed removal at the end of the request (even after fatal/exit). */
    public static function register_cleanup(string $path): void
    {
        self::$registered[$path] = true;
        if (!self::$shutdown_hooked) {
            self::$shutdown_hooked = true;
            register_shutdown_function([__CLASS__, 'cleanup_registered']);
        }
    }

    public static function cleanup_registered(): void
    {
        foreach (array_keys(self::$registered) as $path) self::remove($path);
        self::$registered = [];
    }

    /** Remove a temp file/dir created by this class (prefix-guarded; never touches anything else). */
    public static function remove(string $path): void
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || strpos(basename($path), self::TMP_PREFIX) !== 0) return;
        if (is_dir($path) && !is_link($path)) {
            foreach ((array)@scandir($path) as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === false) continue;
                $child = $path . '/' . $entry;
                if (is_dir($child) && !is_link($child)) {
                    foreach ((array)@scandir($child) as $e2) { if ($e2 !== '.' && $e2 !== '..' && $e2 !== false) @unlink($child . '/' . $e2); }
                    @rmdir($child);
                } else {
                    @unlink($child);
                }
            }
            @rmdir($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
        unset(self::$registered[$path]);
    }

    /** Delete orphaned "bcrm-tmp-*" leftovers older than $max_age seconds (from killed processes). */
    public static function sweep_stale(int $max_age = 7200): int
    {
        $base = self::temp_base();
        if ($base === '' || !@is_dir($base)) return 0;
        $removed = 0;
        foreach ((array)@glob($base . '/' . self::TMP_PREFIX . '*', GLOB_NOSORT) as $path) {
            if (!is_string($path)) continue;
            $mtime = @filemtime($path);
            if ($mtime && (time() - $mtime) > $max_age) { self::remove($path); $removed++; }
        }
        return $removed;
    }

    /** Downloads must never carry PHP notices/warnings into the file or the browser. */
    public static function quiet_output(): void
    {
        @ini_set('display_errors', '0');
        @ini_set('html_errors', '0');
    }

    /** fwrite that reports failure (incl. ENOSPC partial writes) instead of emitting notices. */
    public static function write($fh, string $data): bool
    {
        if (!is_resource($fh)) return false;
        $len = strlen($data);
        $written = @fwrite($fh, $data);
        return $written !== false && $written === $len;
    }

    /**
     * Build an XLSX package (ZipArchive) in a private temp dir. $parts = [zip path => string]
     * plus optional $files = [zip path => local file]. Returns the .xlsx path or '' on any
     * failure (temp dir removed). Nothing is sent to the browser here.
     */
    public static function build_zip(string $temp_dir, string $name, array $parts, array $files = []): string
    {
        if ($temp_dir === '' || !class_exists('ZipArchive')) return '';
        $target = $temp_dir . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return '';
        foreach ($parts as $path => $content) {
            if (!$zip->addFromString((string)$path, (string)$content)) { @$zip->close(); @unlink($target); return ''; }
        }
        foreach ($files as $path => $local) {
            if (!is_file($local) || !$zip->addFile($local, (string)$path)) { @$zip->close(); @unlink($target); return ''; }
        }
        if (!@$zip->close()) { @unlink($target); return ''; } // close() is where ENOSPC surfaces for ZipArchive
        clearstatcache(true, $target);
        if (!is_file($target) || (int)@filesize($target) <= 0) { @unlink($target); return ''; }
        return $target;
    }

    /** Send a finished file as a download and remove it. */
    public static function send_file(string $file, string $download_name, string $content_type): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (function_exists('nocache_headers')) nocache_headers();
        if (!headers_sent()) {
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"');
            header('Content-Length: ' . (string)(int)@filesize($file));
        }
        @readfile($file);
    }
}
