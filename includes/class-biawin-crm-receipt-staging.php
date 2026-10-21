<?php
/**
 * Receipt staging (v0.8.62.27 rewrite): unlimited receipts, any format, race-free.
 *
 * PHP truncates multi-file requests silently at max_file_uploads and whole requests
 * die at post_max_size — both before plugin code runs. So the browser uploads every
 * receipt as its own request the moment it is selected, and the final form submit
 * carries only staged ids + per-row amounts.
 *
 * Why this was rewritten:
 *  Up to v0.8.62.26 the whole queue lived in ONE site-wide wp_option that every upload
 *  request read → modified → wrote back. Parallel uploads overwrote each other's rows
 *  (the add_option "mutex" is not atomic: WordPress' add_option() is a SELECT followed by
 *  INSERT … ON DUPLICATE KEY UPDATE, so two processes could both "acquire" it). Result:
 *  the browser held an id the server had lost → finalize saw an "unknown staged id" and
 *  rolled back the WHOLE batch — not a single receipt got saved. The queue was also shared
 *  by every user and every case, so one agent's successful submit wiped another agent's
 *  in-flight uploads.
 *
 * Now:
 *  - Every staged file is ONE row in {prefix}bcrm_receipt_stage (atomic INSERT, no lock,
 *    no read-modify-write). Rows are owned by (user_id, case_id).
 *  - Content dedupe is a UNIQUE (user_id, case_id, content_hash) key, so a duplicate
 *    parallel upload of the same file resolves to the same id instead of racing.
 *  - Files are kept in a private, web-denied directory with a neutral .bin name, so any
 *    format can be staged safely; the real media import happens at finalize with a
 *    sanitized filename (server-executable extensions are neutralized).
 *  - consume() removes only the ids a finalize actually persisted.
 */
class Biawin_CRM_Receipt_Staging
{
    public const OPTION_PREFIX    = 'bcrm_receipt_stage_';
    public const NONCE_ACTION     = 'bcrm_receipt_stage';
    public const TTL_SECONDS      = 24 * 3600;  // abandoned staged files are garbage-collected after 24h
    public const MAX_STAGED_FILES = 1000;       // per user+case DoS guard only — real batches never reach it
    public const SCHEMA_VERSION   = '1';
    public const SCHEMA_OPTION    = 'bcrm_receipt_queue_schema';
    public const STORAGE_DIR      = 'bcrm-receipt-staging';

    private static $table_ready = false;
    private static $gc_done = false;

    /** Register the AJAX endpoints. The portal login gate covers guests; nopriv handlers answer with actionable JSON instead of "0". */
    public static function init(): void
    {
        add_action('wp_ajax_bcrm_stage_receipt', [__CLASS__, 'ajax_stage']);
        add_action('wp_ajax_bcrm_stage_receipt_remove', [__CLASS__, 'ajax_remove']);
        add_action('wp_ajax_bcrm_stage_list', [__CLASS__, 'ajax_list']);
        add_action('wp_ajax_bcrm_stage_config', [__CLASS__, 'ajax_config']);
        add_action('wp_ajax_nopriv_bcrm_stage_config', [__CLASS__, 'ajax_config']);
    }

    public static function table(): string
    {
        return Biawin_CRM_DB::table('receipt_stage');
    }

    /**
     * Create the staging table on first use (independent of the activation hook, which
     * does not run when a ZIP is uploaded over an existing install). Also retires the
     * pre-v0.8.62.27 shared option queue and its files.
     */
    public static function ensure_table(): bool
    {
        if (self::$table_ready) return true;
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
            self::$table_ready = true;
            return true;
        }
        global $wpdb;
        $table = self::table();
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $wpdb->last_error = '';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            staged_id VARCHAR(40) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            case_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            content_hash CHAR(64) NOT NULL,
            file_path TEXT NOT NULL,
            file_name VARCHAR(255) NOT NULL DEFAULT '',
            mime_type VARCHAR(191) NOT NULL DEFAULT '',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            touched_at INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY staged_id (staged_id),
            UNIQUE KEY owner_hash (user_id, case_id, content_hash),
            KEY touched_at (touched_at)
        ) $charset");
        if ((string)$wpdb->last_error !== '') {
            Biawin_CRM_Upload_Normalizer::log('stage_table_create_failed', ['error' => (string)$wpdb->last_error]);
            return false;
        }
        self::retire_legacy_pool();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        self::$table_ready = true;
        return true;
    }

    /** Remove the pre-v0.8.62.27 shared option queue (+ its files and lock). */
    private static function retire_legacy_pool(): void
    {
        $token = get_option(self::OPTION_PREFIX . 'site_token');
        if (is_string($token) && $token !== '') {
            $name = self::OPTION_PREFIX . md5($token);
            $legacy = get_option($name);
            if (is_array($legacy) && isset($legacy['files']) && is_array($legacy['files'])) {
                foreach ($legacy['files'] as $file) {
                    if (!empty($file['path']) && is_string($file['path'])) @unlink($file['path']);
                }
            }
            delete_option($name);
        }
        delete_option(self::OPTION_PREFIX . 'lock');
    }

    /** Fresh upload config (new nonce + token + limits), with no-cache headers for page-cache-hostile hosting. */
    public static function ajax_config(): void
    {
        nocache_headers();
        wp_send_json([
            'ok'           => true,
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'token'        => self::fixed_token(),
            'loggedIn'     => is_user_logged_in() ? 1 : 0,
            'maxUploads'   => Biawin_CRM_Upload_Normalizer::max_uploads_ini(),
            'maxFileBytes' => Biawin_CRM_Upload_Normalizer::effective_max_file_bytes(),
        ]);
    }

    /**
     * Layered auth: a valid logged-in session plus either a valid nonce or the matching
     * site token (cached portal pages outlive the 12–24h nonce). No session → refused.
     */
    private static function authorize(): ?array
    {
        $nonce_ok = isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION);
        $logged_in = is_user_logged_in();
        if ($nonce_ok && $logged_in) {
            return null;
        }
        $raw_token = isset($_POST['staging_token']) ? sanitize_text_field(wp_unslash($_POST['staging_token'])) : '';
        if ($logged_in && $raw_token !== '' && hash_equals(self::fixed_token(), $raw_token)) {
            Biawin_CRM_Upload_Normalizer::log('stage_auth_token_fallback', ['stale_nonce' => $nonce_ok ? 0 : 1]);
            return null;
        }
        Biawin_CRM_Upload_Normalizer::log('stage_auth_rejected', [
            'logged_in'    => $logged_in ? 1 : 0,
            'nonce_ok'     => $nonce_ok ? 1 : 0,
            'token_ok'     => ($raw_token !== '' && hash_equals(self::fixed_token(), $raw_token)) ? 1 : 0,
            'content_type' => (string)($_SERVER['CONTENT_TYPE'] ?? ''),
        ]);
        if (!$logged_in) {
            return ['status' => 401, 'message' => 'نشست شما منقضی شده است؛ لطفاً دوباره وارد پرتال شوید و فیش‌ها را مجدد انتخاب کنید.'];
        }
        return ['status' => 403, 'message' => 'اعتبارسنجی آپلود ناموفق بود؛ صفحه را دوباره بارگذاری کنید و تلاش را از سر بگیرید.'];
    }

    /** Fixed per-site token so localized scripts can carry it in every workflow form. */
    public static function fixed_token(): string
    {
        $token = get_option(self::OPTION_PREFIX . 'site_token');
        if (!is_string($token) || $token === '') {
            $token = wp_generate_password(24, false, false);
            update_option(self::OPTION_PREFIX . 'site_token', $token, false);
        }
        return $token;
    }

    /** AJAX: stage one uploaded file. Response: {ok, id, hash, name, size}. */
    public static function ajax_stage(): void
    {
        @set_time_limit(0);
        Biawin_CRM_Upload_Normalizer::log('stage_request_received', ['content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0)]);
        // post_max_size overflow empties $_POST/$_FILES entirely — say so instead of "auth failed".
        $post_limit = Biawin_CRM_Upload_Normalizer::ini_bytes((string)ini_get('post_max_size'));
        if ($post_limit > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $post_limit && empty($_POST)) {
            Biawin_CRM_Upload_Normalizer::log('stage_post_max_exceeded', ['content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0), 'post_max_size' => (string)ini_get('post_max_size')]);
            wp_send_json(['ok' => false, 'message' => 'حجم این فایل از سقف post_max_size سرور (' . (string)ini_get('post_max_size') . ') بیشتر است.', 'code' => 'too_large'], 413);
        }
        $denied = self::authorize();
        if ($denied !== null) {
            wp_send_json(['ok' => false, 'message' => $denied['message'], 'code' => 'auth'], $denied['status']);
        }
        if (!current_user_can('process_deficiency_case') && !current_user_can('manage_deficiency_cases') && !current_user_can('bcrm_manage_cases')) {
            wp_send_json(['ok' => false, 'message' => 'شما دسترسی بارگذاری فیش برای پرونده رفع ناقصی را ندارید.', 'code' => 'receipt_upload_forbidden'], 403);
        }
        $raw_token = isset($_POST['staging_token']) ? sanitize_text_field(wp_unslash($_POST['staging_token'])) : '';
        if ($raw_token === '' || strlen($raw_token) > 128) {
            wp_send_json(['ok' => false, 'message' => 'نشانه آپلود نامعتبر است؛ صفحه را دوباره بارگذاری کنید.', 'code' => 'token'], 400);
        }
        $field = isset($_POST['field']) ? sanitize_key(wp_unslash($_POST['field'])) : 'case_documents';
        if (!in_array($field, ['case_documents', 'case_document'], true)) {
            wp_send_json(['ok' => false, 'message' => 'فیلد فایل نامعتبر است.'], 400);
        }
        $case_id = isset($_POST['case_id']) ? absint(wp_unslash($_POST['case_id'])) : 0;
        $entries = Biawin_CRM_Upload_Normalizer::normalize_field($field);
        if (!$entries) {
            Biawin_CRM_Upload_Normalizer::log('stage_no_payload', ['content_type' => (string)($_SERVER['CONTENT_TYPE'] ?? ''), 'files_keys' => implode(',', array_keys($_FILES ?? []))]);
            wp_send_json(['ok' => false, 'message' => 'فایل به سرور نرسید؛ اگر تکرار شد، پشتیبانی هاست را برای بررسی mod_security درگیر کنید.', 'code' => 'no_payload'], 400);
        }
        $entry = reset($entries); // exactly one file per request by contract
        $result = self::stage_entry(is_array($entry) ? $entry : [], $raw_token, $case_id);
        if (empty($result['ok'])) {
            wp_send_json(['ok' => false, 'message' => (string)($result['message'] ?? 'ذخیره فیش در صف آپلود ناموفق بود.'), 'code' => (string)($result['code'] ?? 'stage')], 400);
        }
        wp_send_json($result);
    }

    /**
     * Core staging step (shared by the AJAX handler and tests): validate + store one
     * uploaded file as its own queue row. No lock is needed — each file is an atomic INSERT.
     *
     * @return array{ok:bool,id?:string,hash?:string,name?:string,size?:int,duplicate?:bool,message?:string,code?:string}
     */
    public static function stage_entry(array $entry, string $token = '', int $case_id = 0): array
    {
        global $wpdb;
        if (!self::ensure_table()) {
            return ['ok' => false, 'code' => 'table', 'message' => 'جدول صف آپلود فیش در دیتابیس ساخته نشد؛ دسترسی CREATE TABLE کاربر دیتابیس را بررسی کنید.'];
        }
        self::gc();
        $php_error = (int)($entry['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($php_error !== UPLOAD_ERR_OK) {
            Biawin_CRM_Upload_Normalizer::log('upload_php_error', ['php_error' => $php_error, 'name' => (string)($entry['name'] ?? '')]);
            return ['ok' => false, 'code' => 'php_error', 'message' => Biawin_CRM_Upload_Normalizer::php_error_message($php_error)];
        }
        $tmp = (string)($entry['tmp_name'] ?? '');
        $hash = Biawin_CRM_Upload_Normalizer::content_hash($entry);
        if ($hash === '') {
            return ['ok' => false, 'code' => 'unreadable', 'message' => 'محتوای فایل خوانده نشد؛ دوباره تلاش کنید.'];
        }
        $size = (int)@filesize($tmp);
        if ($size <= 0) {
            return ['ok' => false, 'code' => 'empty', 'message' => 'فایل خالی است (۰ بایت).'];
        }
        $max = Biawin_CRM_Upload_Normalizer::MAX_FILE_BYTES;
        if ($size > $max) {
            Biawin_CRM_Upload_Normalizer::log('stage_rejected_size', ['size' => $size, 'max' => $max]);
            return ['ok' => false, 'code' => 'too_large', 'message' => 'حجم فایل نباید بیشتر از ' . (int)round($max / 1048576) . ' مگابایت باشد.'];
        }
        $user_id = (int)get_current_user_id();
        $table = self::table();

        $existing = self::find_by_hash($user_id, $case_id, $hash);
        if ($existing) {
            if (is_file((string)$existing->file_path)) {
                $wpdb->update($table, ['touched_at' => time()], ['id' => (int)$existing->id]);
                Biawin_CRM_Upload_Normalizer::log('receipt_staged_duplicate', ['staged_id' => (string)$existing->staged_id, 'case_id' => $case_id]);
                return ['ok' => true, 'id' => (string)$existing->staged_id, 'hash' => $hash, 'name' => (string)$existing->file_name, 'size' => (int)$existing->file_size, 'duplicate' => true];
            }
            $wpdb->delete($table, ['id' => (int)$existing->id]); // stale row whose file vanished: re-stage below
        }
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND case_id=%d", $user_id, $case_id));
        if ($count >= self::MAX_STAGED_FILES) {
            return ['ok' => false, 'code' => 'too_many', 'message' => 'تعداد فایل‌های در صف آپلود بیش از حد مجاز است.'];
        }
        $dir = self::storage_dir();
        if ($dir === '') {
            Biawin_CRM_Upload_Normalizer::log('stage_storage_unavailable', []);
            return ['ok' => false, 'code' => 'storage', 'message' => 'پوشه ذخیره موقت فیش‌ها روی سرور قابل نوشتن نیست؛ دسترسی پوشه uploads را بررسی کنید.'];
        }
        $id = 'r' . substr($hash, 0, 12) . bin2hex(random_bytes(4));
        $dest = $dir . '/' . $id . '.bin';
        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : @copy($tmp, $dest);
        if (!$moved || !is_file($dest)) {
            Biawin_CRM_Upload_Normalizer::log('stage_move_failed', ['name' => (string)($entry['name'] ?? '')]);
            return ['ok' => false, 'code' => 'storage', 'message' => 'ذخیره فیش روی دیسک سرور ناموفق بود (فضای دیسک یا دسترسی پوشه uploads را بررسی کنید).'];
        }
        $name = Biawin_CRM_Upload_Normalizer::safe_filename((string)($entry['name'] ?? ''), (string)($entry['type'] ?? ''));
        $mime = Biawin_CRM_Upload_Normalizer::detect_mime($dest, $name, (string)($entry['type'] ?? ''));
        // A lost UNIQUE race is expected and handled below; never let wpdb print an HTML
        // error into this JSON response (WP_DEBUG sites show DB errors inline).
        $suppressed = method_exists($wpdb, 'suppress_errors') ? $wpdb->suppress_errors(true) : null;
        $inserted = $wpdb->insert($table, [
            'staged_id'    => $id,
            'user_id'      => $user_id,
            'case_id'      => $case_id,
            'content_hash' => $hash,
            'file_path'    => $dest,
            'file_name'    => $name,
            'mime_type'    => $mime,
            'file_size'    => $size,
            'touched_at'   => time(),
            'created_at'   => Biawin_CRM_DB::now(),
        ]);
        if ($suppressed !== null) $wpdb->suppress_errors($suppressed);
        if (!$inserted) {
            @unlink($dest);
            // A parallel request with the same content won the UNIQUE key: reuse its id.
            $winner = self::find_by_hash($user_id, $case_id, $hash);
            if ($winner) {
                Biawin_CRM_Upload_Normalizer::log('receipt_staged_duplicate', ['staged_id' => (string)$winner->staged_id, 'case_id' => $case_id, 'race' => 1]);
                return ['ok' => true, 'id' => (string)$winner->staged_id, 'hash' => $hash, 'name' => (string)$winner->file_name, 'size' => (int)$winner->file_size, 'duplicate' => true];
            }
            Biawin_CRM_Upload_Normalizer::log('stage_db_insert_failed', ['error' => (string)$wpdb->last_error]);
            return ['ok' => false, 'code' => 'db', 'message' => 'ثبت فیش در صف دیتابیس ناموفق بود؛ دوباره تلاش کنید.'];
        }
        Biawin_CRM_Upload_Normalizer::log('receipt_staged', ['staged_id' => $id, 'size' => $size, 'case_id' => $case_id, 'mime' => $mime]);
        return ['ok' => true, 'id' => $id, 'hash' => $hash, 'name' => $name, 'size' => $size];
    }

    private static function find_by_hash(int $user_id, int $case_id, string $hash): ?object
    {
        global $wpdb;
        $table = self::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d AND case_id=%d AND content_hash=%s", $user_id, $case_id, $hash));
        return $row ?: null;
    }

    /** Private staging directory under uploads, denied to the web. '' when not writable. */
    public static function storage_dir(): string
    {
        $base = '';
        if (function_exists('wp_upload_dir')) {
            $up = wp_upload_dir(null, false);
            if (is_array($up) && !empty($up['basedir'])) $base = (string)$up['basedir'];
        }
        if ($base === '') $base = rtrim(function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir(), '/\\');
        $dir = rtrim(str_replace('\\', '/', $base), '/') . '/' . self::STORAGE_DIR;
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) wp_mkdir_p($dir); else @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) return '';
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        if (!is_file($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        return $dir;
    }

    /** Delete expired rows + files (all users), at most once per request. */
    private static function gc(): void
    {
        if (self::$gc_done) return;
        self::$gc_done = true;
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, file_path FROM $table WHERE touched_at < %d LIMIT 200", time() - self::TTL_SECONDS));
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!empty($row->file_path)) @unlink((string)$row->file_path);
            $wpdb->delete($table, ['id' => (int)$row->id]);
        }
    }

    /** AJAX: drop one staged file (row removed / file replaced before submit). */
    public static function ajax_remove(): void
    {
        $denied = self::authorize();
        if ($denied !== null) {
            wp_send_json(['ok' => false, 'message' => $denied['message'], 'code' => 'auth'], $denied['status']);
        }
        $id = isset($_POST['staged_id']) ? sanitize_text_field(wp_unslash($_POST['staged_id'])) : '';
        if ($id === '') {
            wp_send_json(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 400);
        }
        self::remove_entry('', $id);
        wp_send_json(['ok' => true]);
    }

    /** AJAX: this user's pending staged files for a case (restores rows after a reload / failed submit). */
    public static function ajax_list(): void
    {
        nocache_headers();
        $denied = self::authorize();
        if ($denied !== null) {
            wp_send_json(['ok' => false, 'message' => $denied['message'], 'code' => 'auth'], $denied['status']);
        }
        $case_id = isset($_POST['case_id']) ? absint(wp_unslash($_POST['case_id'])) : 0;
        wp_send_json(['ok' => true, 'items' => self::list_for_case($case_id)]);
    }

    /** @return array<int,array{id:string,name:string,size:int,mime:string}> oldest first */
    public static function list_for_case(int $case_id): array
    {
        if (!self::ensure_table()) return [];
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT staged_id, file_name, file_size, mime_type, file_path FROM $table WHERE user_id=%d AND case_id=%d ORDER BY id ASC", (int)get_current_user_id(), $case_id));
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_file((string)$row->file_path)) continue;
            $out[] = ['id' => (string)$row->staged_id, 'name' => (string)$row->file_name, 'size' => (int)$row->file_size, 'mime' => (string)$row->mime_type];
        }
        return $out;
    }

    /** Core removal (shared by the AJAX handler and tests). Only the owner's rows. */
    public static function remove_entry(string $token, string $id): bool
    {
        if (!self::ensure_table()) return false;
        global $wpdb;
        $table = self::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, file_path FROM $table WHERE staged_id=%s AND user_id=%d", $id, (int)get_current_user_id()));
        if (!$row) return false;
        @unlink((string)$row->file_path);
        $wpdb->delete($table, ['id' => (int)$row->id]);
        return true;
    }

    /**
     * Read-only view of the current user's queue (all cases), in the legacy shape
     * ['files' => [id => {path,name,type,size,hash,case_id,staged_at}], 'by_hash' => [hash => id]].
     */
    public static function state(string $token = ''): array
    {
        $out = ['files' => [], 'by_hash' => []];
        if (!self::ensure_table()) return $out;
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY id ASC", (int)get_current_user_id()));
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out['files'][(string)$row->staged_id] = self::row_to_file($row);
            $out['by_hash'][(string)$row->content_hash] = (string)$row->staged_id;
        }
        return $out;
    }

    private static function row_to_file(object $row): array
    {
        return [
            'path'      => (string)$row->file_path,
            'name'      => (string)$row->file_name,
            'type'      => (string)$row->mime_type,
            'size'      => (int)$row->file_size,
            'hash'      => (string)$row->content_hash,
            'case_id'   => (int)$row->case_id,
            'staged_at' => (int)$row->touched_at,
        ];
    }

    /**
     * FINALIZE (called from send_to_finance): materialize staged ids into real attachment
     * rows in submitted (DOM) order, each with its own amount. Any failure rolls back
     * every attachment created by THIS call; the staged files stay queued for a retry.
     *
     * @return array{ok:bool,message:string,documents?:array,accounting?:array,missing_ids?:array}
     */
    public static function finalize_for_case(object $case, array $ids, array $amounts = [], string $description = ''): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true); // a proxy timeout must not kill PHP mid-batch
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('image');
        $accounting_base = [
            'received_count'  => count($ids),
            'valid_count'     => 0,
            'uploaded_count'  => 0,
            'persisted_count' => 0,
            'duplicate_count' => 0,
            'failed_count'    => 0,
        ];
        if (!$ids) {
            return ['ok' => false, 'message' => 'هیچ فیش آپلودشده‌ای برای ثبت وجود ندارد.', 'accounting' => $accounting_base];
        }
        if ($amounts && count($amounts) !== count($ids)) {
            Biawin_CRM_Upload_Normalizer::log('finalize_id_amount_count_mismatch', ['case_id' => (int)$case->id, 'ids' => count($ids), 'amounts' => count($amounts)]);
            return ['ok' => false, 'message' => 'تعداد مبالغ فیش‌ها با تعداد فایل‌های ارسالی هم‌خوانی ندارد؛ صفحه را دوباره بارگذاری کنید.', 'accounting' => $accounting_base];
        }
        if (!self::ensure_table()) {
            return ['ok' => false, 'message' => 'جدول صف آپلود فیش در دیتابیس در دسترس نیست.', 'accounting' => array_merge($accounting_base, ['failed_count' => count($ids)])];
        }
        global $wpdb;
        $table = self::table();
        $unique = array_values(array_unique(array_map('strval', $ids)));
        $placeholders = implode(',', array_fill(0, count($unique), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d AND staged_id IN ($placeholders)", array_merge([(int)get_current_user_id()], $unique)));
        $staged = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_file((string)$row->file_path)) $staged[(string)$row->staged_id] = self::row_to_file($row);
        }
        $missing = array_values(array_diff($unique, array_keys($staged)));
        if ($missing) {
            Biawin_CRM_Upload_Normalizer::log('finalize_unknown_staged_id', ['case_id' => (int)$case->id, 'staged_id' => (string)$missing[0], 'missing_count' => count($missing), 'ids' => count($ids), 'staged_count' => count($staged)]);
            return ['ok' => false, 'missing_ids' => $missing, 'message' => sprintf('%d فیش از %d فیش ارسالی در صف سرور پیدا نشد (احتمالاً بیش از ۲۴ ساعت از آپلودشان گذشته یا حذف شده‌اند). هیچ فیشی ثبت نشد؛ فقط همان فیش‌ها را دوباره انتخاب و ثبت نهایی کنید.', count($missing), count($ids)), 'accounting' => array_merge($accounting_base, ['failed_count' => count($missing)])];
        }

        // Receipts need no thumbnail zoo: generating 5+ sub-sizes per phone photo made a
        // 30-receipt finalize run for minutes and hit proxy/PHP limits.
        $no_sizes = static function () { return []; };
        $no_threshold = static function () { return false; };
        $filters = function_exists('add_filter') && function_exists('remove_filter');
        if ($filters) {
            add_filter('intermediate_image_sizes_advanced', $no_sizes, 999);
            add_filter('fallback_intermediate_image_sizes', $no_sizes, 999);
            add_filter('big_image_size_threshold', $no_threshold, 999);
        }
        try {
            $documents = [];
            $duplicate_count = 0;
            $rolled_back = static function (array $docs): void {
                global $wpdb;
                foreach ($docs as $doc) {
                    $att_id = (int)($doc['attachment_id'] ?? 0);
                    if ($att_id > 0) $wpdb->delete(Biawin_CRM_DB::table('attachments'), ['id' => $att_id]);
                    $wp_att = (int)($doc['wp_attachment_id'] ?? 0);
                    if ($wp_att > 0) wp_delete_attachment($wp_att, true);
                }
            };
            $seen_ids = [];
            $row_position = -1;
            foreach ($ids as $id) {
                $row_position++;
                $id = (string)$id;
                // The same physical file may legitimately sit on several rows, each with its
                // own amount: every submitted row is persisted; repeats are only counted.
                if (isset($seen_ids[$id])) {
                    $duplicate_count++;
                    Biawin_CRM_Upload_Normalizer::log('finalize_repeated_content_row', ['case_id' => (int)$case->id, 'staged_id' => $id, 'row_position' => $row_position]);
                } else {
                    $seen_ids[$id] = true;
                }
                $file = $staged[$id];
                // Per-row temp copy: the media import moves its source, and a repeated id
                // must still find a pristine file for its next row.
                $row_source = wp_tempnam('bcrm-row-');
                if (!$row_source || !@copy($file['path'], $row_source)) {
                    if ($row_source && is_string($row_source)) @unlink($row_source);
                    $rolled_back($documents);
                    Biawin_CRM_Upload_Normalizer::log('finalize_row_source_copy_failed', ['case_id' => (int)$case->id, 'staged_id' => $id, 'row_position' => $row_position]);
                    return ['ok' => false, 'message' => 'آماده‌سازی یکی از فیش‌ها ناموفق بود (فضای دیسک موقت سرور)؛ دوباره تلاش کنید.', 'accounting' => array_merge($accounting_base, ['valid_count' => count($documents), 'uploaded_count' => count($documents), 'duplicate_count' => $duplicate_count, 'failed_count' => 1])];
                }
                $upload_field = Biawin_CRM_Upload_Normalizer::UPLOAD_FIELD;
                $had_field = array_key_exists($upload_field, $_FILES);
                $prev_field = $had_field ? $_FILES[$upload_field] : null;
                $_FILES[$upload_field] = [
                    'name'     => $file['name'],
                    'type'     => $file['type'],
                    'tmp_name' => $row_source,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => (int)$file['size'],
                ];
                $row_raw = ['document_type' => 'bank_receipt', 'description' => $description];
                if ($amounts) $row_raw['receipt_amount'] = $amounts[$row_position] ?? '';
                $document = Biawin_CRM_Deficiency_Workflow::save_uploaded_document_for_staging($case, $row_raw);
                if ($had_field) $_FILES[$upload_field] = $prev_field;
                else unset($_FILES[$upload_field]);
                if (is_file($row_source)) @unlink($row_source);
                if (empty($document['ok'])) {
                    $rolled_back($documents);
                    Biawin_CRM_Upload_Normalizer::log('finalize_row_failed', ['case_id' => (int)$case->id, 'staged_id' => $id, 'row_position' => $row_position, 'rolled_back' => count($documents), 'reason' => (string)($document['message'] ?? '')]);
                    return ['ok' => false, 'message' => 'ثبت فیش ردیف ' . ($row_position + 1) . ' («' . $file['name'] . '») ناموفق بود: ' . (string)($document['message'] ?? '') . ' — هیچ فیشی ثبت نشد و فایل‌ها برای تلاش دوباره در صف مانده‌اند.', 'accounting' => array_merge($accounting_base, ['valid_count' => count($documents), 'uploaded_count' => count($documents), 'duplicate_count' => $duplicate_count, 'failed_count' => 1])];
                }
                $documents[] = $document + ['staged_id' => $id, 'content_hash' => $file['hash']];
            }
            return ['ok' => true, 'message' => '', 'documents' => $documents, 'accounting' => array_merge($accounting_base, ['valid_count' => count($documents), 'uploaded_count' => count($documents), 'persisted_count' => count($documents), 'duplicate_count' => $duplicate_count])];
        } finally {
            if ($filters) {
                remove_filter('intermediate_image_sizes_advanced', $no_sizes, 999);
                remove_filter('fallback_intermediate_image_sizes', $no_sizes, 999);
                remove_filter('big_image_size_threshold', $no_threshold, 999);
            }
        }
    }

    /** Number of the current user's staged files (one case, or all cases when $case_id is null); null when the table is unavailable. */
    public static function pool_count(?int $case_id = null): ?int
    {
        if (!self::ensure_table()) return null;
        global $wpdb;
        $table = self::table();
        if ($case_id === null) {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d", (int)get_current_user_id()));
        }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND case_id=%d", (int)get_current_user_id(), $case_id));
    }

    /** Is the staging queue usable right now (table present)? */
    public static function pool_available(): bool
    {
        return self::ensure_table();
    }

    /** Drop every staged file of the current user. */
    public static function reset_pool(): void
    {
        if (!self::ensure_table()) return;
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, file_path FROM $table WHERE user_id=%d", (int)get_current_user_id()));
        foreach (is_array($rows) ? $rows : [] as $row) {
            @unlink((string)$row->file_path);
            $wpdb->delete($table, ['id' => (int)$row->id]);
        }
    }

    /**
     * Consume staged files after a fully successful finalize: only the given ids
     * (other rows — other cases, other users, still-uploading files — are untouched).
     * Without ids, the current user's whole queue is cleared.
     */
    public static function consume(?array $ids = null): void
    {
        if ($ids === null) {
            self::reset_pool();
            return;
        }
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            self::remove_entry('', $id);
        }
    }
}
