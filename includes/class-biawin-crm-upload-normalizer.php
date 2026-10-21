<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical $_FILES normalizer for the deficiency/finance receipt workflow.
 *
 * Guarantees:
 * - Every uploaded entry keeps its ORIGINAL request key/index (0,1,3,7,... or string keys).
 * - name/type/tmp_name/error/size always come from the SAME request key; no positional fallback.
 * - Duplicate content is detected by SHA-256 of the tmp file contents, not by filename or row index.
 * - Two different files with the same filename are both accepted.
 * - Malformed entries are rejected explicitly instead of being silently remapped.
 */
class Biawin_CRM_Upload_Normalizer
{
    /** v0.8.62.27: 50MB per receipt (was 8MB — modern phone photos routinely exceed it). */
    public const MAX_FILE_BYTES = 50 * 1024 * 1024;
    /** Private scalar $_FILES field used for both inline and staged uploads. */
    public const UPLOAD_FIELD = 'bcrm_case_document_upload';
    /**
     * v0.8.62.27: receipts accept ANY format. These extensions would be executed or rendered
     * as active content by the web server if they landed in the public uploads dir, so they
     * are neutralized with an extra ".txt" suffix instead of being rejected.
     */
    public const ACTIVE_EXTENSIONS = ['php','php3','php4','php5','php7','php8','pht','phtml','phar','phps','inc','cgi','pl','py','rb','sh','bash','asp','aspx','ashx','asmx','jsp','jspx','cfm','shtml','shtm','html','htm','xhtml','svg','svgz','xml','js','mjs','htaccess','htpasswd','ini','user'];
    /** Common receipt formats (image + PDF). Kept for reference/tests — no longer a restriction. */
    public const RECEIPT_MIMES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'bmp' => 'image/bmp',
        'tif|tiff' => 'image/tiff',
        'ico' => 'image/x-icon',
        'pdf' => 'application/pdf',
    ];

    /**
     * @return array<int|string, array{name:string,type:string,tmp_name:string,error:int,size:int,key:int|string}>
     */
    public static function normalize_field(string $field): array
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return [];
        }
        $entry = $_FILES[$field];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $part) {
            if (!array_key_exists($part, $entry)) {
                return [];
            }
        }
        $out = [];
        if (is_array($entry['name'])) {
            // Multiple files under one field (case_documents[]): every part must be keyed identically.
            foreach (['type', 'tmp_name', 'error', 'size'] as $part) {
                if (!is_array($entry[$part])) {
                    return [];
                }
            }
            $keys = array_unique(array_merge(
                array_keys($entry['name']),
                array_keys($entry['tmp_name'])
            ));
            foreach ($keys as $key) {
                $name = $entry['name'][$key] ?? null;
                $tmp = $entry['tmp_name'][$key] ?? null;
                // A key that exists for tmp_name but not name (or vice versa) is a malformed entry.
                if (!array_key_exists($key, $entry['name']) || !array_key_exists($key, $entry['tmp_name'])) {
                    self::log('malformed_multipart_entry', ['field'=>$field,'key'=>(string)$key]);
                    continue;
                }
                if (!is_string($name) && !is_null($name)) {
                    self::log('malformed_multipart_entry', ['field'=>$field,'key'=>(string)$key]);
                    continue;
                }
                if (!is_string($tmp) || $tmp === '') {
                    continue; // Browser-declared but no uploaded payload: treat as absent, not as another row's file.
                }
                $out[$key] = [
                    'name' => (string)$name,
                    'type' => is_string($entry['type'][$key] ?? null) ? (string)$entry['type'][$key] : '',
                    'tmp_name' => $tmp,
                    'error' => is_scalar($entry['error'][$key] ?? null) ? (int)$entry['error'][$key] : UPLOAD_ERR_NO_FILE,
                    'size' => is_scalar($entry['size'][$key] ?? null) ? (int)$entry['size'][$key] : 0,
                    'key' => $key,
                ];
            }
            return $out;
        }
        // Single scalar entry (case_document single-file inputs and REST uploads).
        if (!is_string($entry['name']) || !is_string($entry['tmp_name']) || $entry['name'] === '' || $entry['tmp_name'] === '') {
            return [];
        }
        $out[0] = [
            'name' => (string)$entry['name'],
            'type' => is_string($entry['type'] ?? null) ? (string)$entry['type'] : '',
            'tmp_name' => (string)$entry['tmp_name'],
            'error' => is_scalar($entry['error'] ?? null) ? (int)$entry['error'] : UPLOAD_ERR_NO_FILE,
            'size' => is_scalar($entry['size'] ?? null) ? (int)$entry['size'] : 0,
            'key' => 0,
        ];
        return $out;
    }

    /** True when at least one real uploaded payload exists for the field. */
    public static function has_uploaded_file(string $field): bool
    {
        return (bool) self::normalize_field($field);
    }

    public static function uploaded_keys(string $field): array
    {
        return array_keys(self::normalize_field($field));
    }

    /** SHA-256 of the actual tmp file contents; empty string when content cannot be read. */
    public static function content_hash(array $file): string
    {
        $path = (string)($file['tmp_name'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) return '';
        $hash = hash_file('sha256', $path);
        return is_string($hash) ? $hash : '';
    }

    /** The per-file ceiling that actually applies: plugin limit, upload_max_filesize and post_max_size. */
    public static function effective_max_file_bytes(): int
    {
        $limits = [self::MAX_FILE_BYTES];
        $upload = self::ini_bytes((string)ini_get('upload_max_filesize'));
        if ($upload > 0) $limits[] = $upload;
        $post = self::ini_bytes((string)ini_get('post_max_size'));
        if ($post > 65536) $limits[] = $post - 65536; // multipart envelope + other fields
        return (int)min($limits);
    }

    /** Human message for a PHP upload error code. */
    public static function php_error_message(int $code): string
    {
        $known = [
            UPLOAD_ERR_INI_SIZE   => 'حجم فایل از سقف upload_max_filesize سرور (' . (string)ini_get('upload_max_filesize') . ') بیشتر است.',
            UPLOAD_ERR_FORM_SIZE  => 'حجم فایل از سقف تعیین‌شده در فرم بیشتر است.',
            UPLOAD_ERR_PARTIAL    => 'فایل ناقص منتقل شد (قطع اینترنت)؛ دوباره تلاش کنید.',
            UPLOAD_ERR_NO_FILE    => 'فایلی آپلود نشد.',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت PHP روی سرور وجود ندارد (upload_tmp_dir)؛ به پشتیبانی هاست اطلاع دهید.',
            UPLOAD_ERR_CANT_WRITE => 'سرور نتوانست فایل را روی دیسک بنویسد (فضای دیسک پر یا دسترسی)؛ به پشتیبانی هاست اطلاع دهید.',
            UPLOAD_ERR_EXTENSION  => 'یک افزونه PHP روی سرور آپلود را متوقف کرد.',
        ];
        return $known[$code] ?? ('آپلود فایل با خطای PHP متوقف شد (کد ' . $code . ').');
    }

    /**
     * Safe stored filename for ANY format: basename only, control/reserved chars stripped,
     * a missing extension derived from the MIME type, server-active extensions neutralized.
     */
    public static function safe_filename(string $name, string $mime = ''): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string)preg_replace('/[\x00-\x1F\x7F<>:"|?*]+/u', '', $name);
        $name = trim($name, " .\t");
        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $stem = $ext !== '' ? substr($name, 0, -(strlen($ext) + 1)) : $name;
        $stem = trim((string)$stem, ' .');
        if ($stem === '') $stem = 'receipt-' . gmdate('Ymd-His');
        if ($ext === '' || !preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
            $ext = self::extension_for_mime($mime) ?: 'bin';
        }
        $stem = function_exists('mb_substr') ? mb_substr($stem, 0, 120) : substr($stem, 0, 120);
        $safe = $stem . '.' . $ext;
        if (in_array($ext, self::ACTIVE_EXTENSIONS, true)) $safe .= '.txt';
        return $safe; // inner dotted parts ("x.php.jpg") are handled by WordPress' sanitize_file_name()
    }

    public static function extension_for_mime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/avif' => 'avif', 'image/heic' => 'heic', 'image/heif' => 'heif',
            'image/heic-sequence' => 'heic', 'image/heif-sequence' => 'heif', 'image/bmp' => 'bmp', 'image/tiff' => 'tif',
            'application/pdf' => 'pdf', 'text/plain' => 'txt', 'application/zip' => 'zip', 'application/x-rar-compressed' => 'rar',
            'application/vnd.rar' => 'rar', 'application/x-7z-compressed' => '7z', 'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/csv' => 'csv', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/3gpp' => '3gp', 'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a', 'audio/ogg' => 'ogg', 'audio/amr' => 'amr',
        ];
        return $map[strtolower(trim($mime))] ?? '';
    }

    /** Best-effort MIME of a stored file: content sniffing, then extension, then the browser's claim. */
    public static function detect_mime(string $path, string $name, string $browser_type = ''): string
    {
        $mime = '';
        if (function_exists('finfo_open') && is_file($path)) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string)@finfo_file($fi, $path);
                @finfo_close($fi);
            }
        }
        if (($mime === '' || $mime === 'application/octet-stream') && function_exists('wp_check_filetype')) {
            $check = wp_check_filetype($name);
            if (!empty($check['type']) && $check['type'] !== 'application/octet-stream') $mime = (string)$check['type'];
        }
        if (($mime === '' || $mime === 'application/octet-stream') && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $browser_type)) {
            $mime = strtolower($browser_type);
        }
        // Never let an active-content type become the attachment's served MIME.
        if (preg_match('#(html|xml|svg|javascript|x-php|x-httpd)#i', $mime)) $mime = 'text/plain';
        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    /** Detected PHP max-file-uploads ini (0 = unlimited/unknown). Diagnostic only — never a business limit. */
    public static function max_uploads_ini(): int
    {
        return (int) ini_get('max_file_uploads');
    }

    /**
     * Exact-cap signature: PHP with max_file_uploads=20 silently drops file slots beyond
     * the cap while keeping every other multipart part intact, so a request that arrived
     * with exactly N files where N == ini_get('max_file_uploads') while the user declared
     * more rows is almost certainly this truncation. $received_count > $max_uploads can
     * never be true on a truncated request (PHP itself enforces the cap), so this is the
     * only observable signature of the cap from PHP.
     */
    public static function looks_like_max_uploads_cap(int $received_count, int $declared_count): bool
    {
        $max_uploads = self::max_uploads_ini();
        return $max_uploads > 0 && $declared_count > $max_uploads && $received_count === $max_uploads;
    }

    /** Structured, actionable guidance for raising the server limits (shown on the error screen). */
    public static function server_limit_notice(string $context = ''): string
    {
        $max_uploads = self::max_uploads_ini();
        $post_max = trim((string) ini_get('post_max_size'));
        $lines = [
            'راهنمای رفع محدودیت سرور —' . ($context !== '' ? ' ' . $context : ''),
            '۱) در کنترل‌پنل هاست (مثلاً LiteSpeed → PHP Configuration یا MultiPHP INI Editor در cPanel) مقدار max_file_uploads را به 100 و upload_max_filesize و post_max_size را به حدود 128M برسانید.',
            '۲) اگر گزینه بالا در پنل هاست نیست، فایل .user.ini در ریشه وردپرس با محتوای زیر بسازید (نیاز به restart PHP ندارد):',
            '   max_file_uploads=100',
            '   upload_max_filesize=64M',
            '   post_max_size=128M',
            '۳) پس از ذخیره، با PHP Info بررسی کنید که مقادیر اعمال شده‌اند و دوباره تلاش کنید.',
            'مقدار فعلی سرور: max_file_uploads=' . $max_uploads . ($post_max !== '' ? ' , post_max_size=' . $post_max : ''),
        ];
        return implode("\n", $lines);
    }

    /**
     * Server limit diagnostics. Returns ['ok'=>bool,'message'=>string,'truncated'=>bool].
     * Detects a truncated request instead of silently continuing with fewer files than selected.
     */
    public static function check_server_limits(int $received_count, int $declared_count = 0): array
    {
        $max_uploads = self::max_uploads_ini();
        if ($max_uploads > 0 && $received_count > $max_uploads) {
            return ['ok'=>false,'truncated'=>true,'message'=>sprintf('سرور فقط اجازه دریافت %d فایل همزمان را می‌دهد؛ %d فایل دریافت شد. تنظیم max_file_uploads سرور را افزایش دهید.', $max_uploads, $received_count)];
        }
        if ($declared_count > 0 && $received_count < $declared_count) {
            $message = sprintf('%d ردیف فیش انتخاب شد اما فقط %d فایل به سرور رسید؛ درخواست ناقص است و پردازش متوقف شد.', $declared_count, $received_count);
            if (self::looks_like_max_uploads_cap($received_count, $declared_count)) {
                // PHP drops file slots beyond the cap SILENTLY: name slots disappear while the
                // amount rows survive. Name the cause and the exact fix instead of a generic
                // "incomplete request" so support can act on one screenshot.
                $message = sprintf('سرور هاستینگ شما در هر درخواست حداکثر %d فایل می‌پذیرد (max_file_uploads). %d فیش انتخاب شد اما فقط %d فایل اول به سرور رسید؛ بقیه فایل‌ها پیش از رسیدن به افزونه در سطح PHP حذف شده‌اند. هیچ داده‌ای ذخیره نشده است؛ ابتدا سقف سرور را افزایش دهید.', $max_uploads, $declared_count, $received_count);
            }
            return ['ok'=>false,'truncated'=>true,'message'=>$message];
        }
        $post_max = trim((string) ini_get('post_max_size'));
        $post_bytes = self::ini_bytes($post_max);
        $declared_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($post_bytes > 0 && $declared_length > $post_bytes) {
            return ['ok'=>false,'truncated'=>true,'message'=>'حجم درخواست از سقف post_max_size سرور بیشتر شد؛ بخشی یا همه داده‌ها دریافت نشد. سقف سرور را افزایش دهید یا فایل‌های کمتری ارسال کنید.'];
        }
        return ['ok'=>true,'truncated'=>false,'message'=>''];
    }

    public static function ini_bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        $unit = strtolower(substr($value, -1));
        $num = (float)$value;
        switch ($unit) {
            case 'g': return (int)($num * 1024 * 1024 * 1024);
            case 'm': return (int)($num * 1024 * 1024);
            case 'k': return (int)($num * 1024);
        }
        return (int)$num;
    }

    /** Safe diagnostic log: counts and indexes only, never file contents or tmp paths. */
    public static function log(string $event, array $context = []): void
    {
        $safe = [];
        foreach ($context as $k => $v) {
            $safe[(string)$k] = is_scalar($v) || $v === null ? (is_bool($v) ? (int)$v : $v) : wp_json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $line = '[biawin-crm upload] ' . $event . ' ' . wp_json_encode($safe, JSON_UNESCAPED_UNICODE);
        if (function_exists('error_log')) {
            @error_log($line);
        } else {
            // v0.8.61.5 — no error_log() in this environment (harness shimmed it out):
            // still emit via output_buffering-safe error_reporting path so shims that
            // capture set_error_handler style sinks can see the line.
            trigger_error($line, E_USER_NOTICE);
        }
        if (class_exists('Biawin_CRM_DB') && method_exists('Biawin_CRM_DB', 'log')) {
            try {
                Biawin_CRM_DB::log('upload', null, 'receipt_' . $event, null, $safe);
            } catch (Throwable $e) {
                // Never let diagnostics break the upload workflow.
            }
        }
    }
}
