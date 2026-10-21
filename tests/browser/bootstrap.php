<?php
/**
 * C3.9 browser-acceptance harness bootstrap.
 *
 * Boots the real plugin classes (including the frozen Biawin_CRM_Frontend) on a
 * file-backed SQLite copy of the C2 fixture model behind minimal WordPress
 * doubles, so headless Chromium can drive the real Ownership UI and the real
 * legacy Attach handlers over HTTP. MySQL/WordPress runtime are NOT exercised.
 */
define('ABSPATH', __DIR__ . '/');
define('BIAWIN_CRM_VERSION', '0.8.65.19');
define('BIAWIN_CRM_DIR', dirname(__DIR__, 2) . '/');
define('BIAWIN_CRM_URL', '/');
$runtime = getenv('BCRM_BROWSER_RUNTIME') ?: sys_get_temp_dir() . '/bcrm-browser';
if (!is_dir($runtime . '/media')) mkdir($runtime . '/media', 0777, true);
define('BCRM_RUNTIME', $runtime);

// ------------------------------------------------------------------ users and capabilities
$uid = (int)($_COOKIE['bcrm_uid'] ?? 0);
$current_hook = '';
$enqueued = ['styles' => [], 'scripts' => [], 'data' => []];
$options = ['biawin_crm_db_version' => BIAWIN_CRM_VERSION];
$USERS = [1 => 'درخواست‌دهنده (کارشناس پروفایل)', 2 => 'کارشناس تطبیق الف', 3 => 'سرپرست تطبیق الف', 4 => 'کارشناس تطبیق ب', 5 => 'سرپرست تطبیق ب', 6 => 'کاربر پرتال بدون دسترسی تطبیق', 7 => 'مدیر سیستم'];

// ------------------------------------------------------------------ WordPress doubles
function get_current_user_id() { global $uid; return $uid; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function current_user_can($cap, ...$args) { global $uid; return user_can($uid, $cap); }
function user_can($id, $cap) { $caps = bcrm_caps(); return in_array($cap, $caps[(int)$id] ?? [], true); }
function bcrm_caps(): array {
    static $caps = null;
    if ($caps === null) {
        $map = Biawin_CRM_Ownership_Authorization::role_capabilities();
        $caps = [
            1 => array_merge($map['bcrm_agent'], ['bcrm_work_cases']),
            2 => array_merge($map['bcrm_ownership_specialist'], ['bcrm_work_cases']),
            3 => array_merge($map['bcrm_ownership_supervisor'], ['bcrm_work_cases']),
            4 => array_merge($map['bcrm_ownership_specialist'], ['bcrm_work_cases']),
            5 => array_merge($map['bcrm_ownership_supervisor'], ['bcrm_work_cases']),
            6 => ['bcrm_work_cases'],
            7 => array_merge($map['administrator'], ['bcrm_work_cases', 'bcrm_manage_cases', 'manage_options']),
        ];
    }
    return $caps;
}
function get_userdata($id) { global $USERS; return isset($USERS[(int)$id]) ? (object)['ID' => (int)$id, 'display_name' => $USERS[(int)$id]] : false; }
function wp_get_current_user() { global $uid, $USERS; return new WP_User($uid, $USERS[$uid] ?? ''); }
class WP_User { public $ID, $display_name, $roles = []; function __construct($id, $name) { $this->ID = $id; $this->display_name = $name; } function has_cap($cap) { return user_can($this->ID, $cap); } }
function get_user_by($field, $id) { global $USERS; return isset($USERS[(int)$id]) ? new WP_User((int)$id, $USERS[(int)$id]) : false; }
function get_users($args = []) { global $USERS; $out = []; foreach ($USERS as $id => $name) $out[] = (object)['ID' => $id, 'display_name' => $name]; return $out; }
function get_user_meta($id, $key, $single = false) { return false; }
function get_option($name, $default = false) { global $options; return $options[$name] ?? $default; }
function current_time($type) { return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); }
function date_i18n($format) { return gmdate($format); }
function wp_date($format, $ts = null) { return gmdate($format, $ts ?? time()); }
function get_date_from_gmt($v) { return $v; }
function wp_rand($min = 0, $max = 2147483647) { return random_int($min, $max); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function sanitize_textarea_field($s) { return trim(strip_tags((string)$s)); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$s))); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$s)); }
function absint($v) { return abs((int)$v); }
function wp_unslash($v) { return $v; }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s) { return esc_html($s); }
function __($s) { return $s; }
function add_query_arg($args, $url = '') { $parts = parse_url($url); parse_str($parts['query'] ?? '', $q); $q = array_merge($q, $args); return ($parts['path'] ?? '/') . '?' . http_build_query($q); }
function admin_url($p = '') { return $p === 'admin-ajax.php' ? '/ajax' : '/wp-admin/' . $p; }
function home_url($p = '') { return $p; }
function is_admin() { return false; }
function checked($a, $b, $echo = true) { return (string)$a === (string)$b ? ' checked="checked"' : ''; }
function selected($a, $b, $echo = true) { return (string)$a === (string)$b ? ' selected="selected"' : ''; }
function number_format_i18n($n, $d = 0) { return number_format((float)$n, $d); }
function wp_create_nonce($action) { return 'nonce-' . $action; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'nonce-' . $action ? 1 : false; }
function check_ajax_referer($action, $arg = false, $die = true) { $ok = ($_POST[$arg ?: '_wpnonce'] ?? '') === 'nonce-' . $action; if (!$ok && $die) { http_response_code(403); echo '-1'; exit; } return $ok; }
function wp_nonce_field($action, $name = '_wpnonce', $referer = true, $echo = true) { $h = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; if ($echo) echo $h; return $h; }
function wp_send_json($data, $status = null) { if ($status) http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function wp_send_json_success($data = null, $status = null) { wp_send_json(['success' => true, 'data' => $data], $status); }
function wp_send_json_error($data = null, $status = null) { wp_send_json(['success' => false, 'data' => $data], $status); }
function wp_die($html = '', $title = '', $args = []) { http_response_code(is_array($args) ? ($args['response'] ?? 500) : 500); echo '<!doctype html><meta charset="utf-8"><title>' . esc_html($title) . '</title><body data-wp-die>' . $html . '</body>'; exit; }
function current_action() { global $current_hook; return $current_hook; }
function apply_filters($name, $value, ...$args) {
    // Test OTP adapter: "deliver" by recording the code where only the test harness can read it.
    if ($name === 'biawin_crm_ownership_send_otp') { file_put_contents(BCRM_RUNTIME . '/last-otp.json', json_encode(['phone' => $args[0], 'code' => $args[1], 'case' => $args[2]])); return true; }
    return $value;
}
function do_action(...$a) {}
function add_action(...$a) {}
function add_filter(...$a) {}
function wp_enqueue_style($h, $src = '', $deps = [], $ver = false) { global $enqueued; $enqueued['styles'][$h] = $src; }
function wp_enqueue_script($h, $src = '', $deps = [], $ver = false, $footer = false) { global $enqueued; $enqueued['scripts'][$h] = $src; }
function wp_localize_script($h, $name, $data) { global $enqueued; $enqueued['data'][$name] = $data; }
function wp_add_inline_style(...$a) {}
function get_post($id) { $m = bcrm_media((int)$id); return $m ? (object)['ID' => (int)$id, 'post_type' => 'attachment', 'post_author' => $m['author']] : null; }
function get_attached_file($id) { $m = bcrm_media((int)$id); return $m ? $m['path'] : ''; }
function get_post_mime_type($id) { $m = bcrm_media((int)$id); return $m ? $m['mime'] : ''; }
function wp_get_attachment_url($id) { return '/media/' . (int)$id; }
function current_user_can_edit($id) { return false; }
function is_wp_error($v) { return $v instanceof WP_Error; }
class WP_Error { private $m; function __construct($c = '', $m = '') { $this->m = $m; } function get_error_message() { return $this->m; } }
function bcrm_media_registry(?array $set = null): array {
    $file = BCRM_RUNTIME . '/media.json';
    if ($set !== null) { file_put_contents($file, json_encode($set)); return $set; }
    return is_file($file) ? (array)json_decode((string)file_get_contents($file), true) : [];
}
function bcrm_media(int $id): ?array { return bcrm_media_registry()[(string)$id] ?? null; }
function media_handle_upload($field, $post, $data = [], $overrides = []) {
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_uploaded_file($f['tmp_name'])) return new WP_Error('upload', 'فایلی دریافت نشد.');
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime = null;
    foreach ($overrides['mimes'] ?? [] as $exts => $m) if (in_array($ext, explode('|', $exts), true)) $mime = $m;
    if (!$mime) return new WP_Error('type', 'نوع فایل مجاز نیست.');
    $registry = bcrm_media_registry();
    $id = 5000 + count($registry) + 1;
    $path = BCRM_RUNTIME . '/media/' . $id . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$f['name']));
    move_uploaded_file($f['tmp_name'], $path);
    $registry[(string)$id] = ['path' => $path, 'mime' => $mime, 'author' => get_current_user_id()];
    bcrm_media_registry($registry);
    return $id;
}
function wp_delete_attachment($id, $force = false) { $r = bcrm_media_registry(); unset($r[(string)$id]); bcrm_media_registry($r); return true; }

// ------------------------------------------------------------------ file-backed SQL double (same SQL translation as tests/ownership-c2.php)
class BrowserSQL {
    public $prefix = 'test_', $dbname = 'browser_double', $last_error = '', $insert_id = 0, $rows_affected = 0, $num_rows = 0, $last_query = '', $last_result = [];
    public $pdo;
    private $suppressed = false;
    function __construct(string $file) { $this->pdo = new PDO('sqlite:' . $file); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $this->pdo->exec('PRAGMA busy_timeout=5000'); }
    function suppress_errors($value = true) { $old = $this->suppressed; $this->suppressed = $value; return $old; }
    function esc_like($text) { return addcslashes($text, '_%\\'); }
    function get_charset_collate() { return ''; }
    function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $i = 0; return preg_replace_callback('/%[dsf]/', function ($m) use (&$i, $args) { $v = $args[$i++] ?? ''; return $m[0] === '%d' ? (string)(int)$v : ($m[0] === '%f' ? (string)(float)$v : $this->pdo->quote((string)$v)); }, $sql);
    }
    private function fail_customer(string $sql): void {
        $flag = BCRM_RUNTIME . '/fail-customer';
        if (!is_file($flag)) return;
        $id = trim((string)file_get_contents($flag));
        if ($id !== '' && preg_match('/^UPDATE `?test_bcrm_customers`? /', $sql) && strpos($sql, "`id` = '" . $id . "'") !== false) { unlink($flag); throw new RuntimeException('injected recalculation failure'); }
    }
    function query($sql) {
        $this->last_query = $sql; $this->last_error = '';
        try {
            if (strpos($sql, 'RELEASE_LOCK') !== false) return 1;
            if ($sql === 'SET TRANSACTION READ WRITE') { if ($this->pdo->inTransaction()) throw new RuntimeException('transaction already active'); return 0; }
            if (preg_match('/^\s*(START TRANSACTION|BEGIN)\s*;?\s*$/i', $sql)) { $this->pdo->beginTransaction(); return 1; }
            if (preg_match('/^\s*COMMIT\s*;?\s*$/i', $sql)) { if ($this->pdo->inTransaction()) $this->pdo->commit(); return 1; }
            if (preg_match('/^\s*ROLLBACK\s*;?\s*$/i', $sql)) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); return 1; }
            $this->fail_customer($sql);
            if (preg_match('/^\s*(SELECT|SHOW)/i', $sql)) { $r = $this->get_results($sql); return $r === null ? false : count($r); }
            $this->rows_affected = $this->pdo->exec(str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql));
            if (preg_match('/^\s*INSERT/i', $sql)) $this->insert_id = (int)$this->pdo->lastInsertId();
            return $this->rows_affected;
        } catch (Throwable $e) { $this->last_error = $e->getMessage(); return false; }
    }
    function get_results($sql, $output = 'OBJECT') {
        $this->last_query = $sql; $this->last_error = '';
        if (strpos($sql, 'SHOW TABLE STATUS') === 0) { preg_match_all("/'([^']+)'/", $sql, $m); return array_map(static function ($n) { return (object)['Name' => $n, 'Engine' => 'InnoDB']; }, $m[1]); }
        try {
            $sql = preg_replace('/ FOR UPDATE\b/i', '', $sql); $sql = str_replace('<=>', ' IS ', $sql);
            $rows = $this->pdo->query($sql)->fetchAll($output === 'ARRAY_A' ? PDO::FETCH_ASSOC : PDO::FETCH_OBJ);
            $this->num_rows = count($rows); return $rows;
        } catch (Throwable $e) { $this->last_error = $e->getMessage() . ' SQL: ' . $sql; return null; }
    }
    function get_var($sql, $x = 0, $y = 0) { if (strpos($sql, 'GET_LOCK') !== false) return '1'; if ($sql === 'SELECT @@autocommit') return 1; $r = $this->get_results($sql); return $r ? (array_values((array)$r[$y])[$x] ?? null) : null; }
    function get_row($sql, $output = 'OBJECT', $y = 0) { $r = $this->get_results($sql, $output); return $r[$y] ?? null; }
    function get_col($sql, $column = 0) { return array_map(static function ($row) use ($column) { return array_values((array)$row)[$column]; }, $this->get_results($sql) ?? []); }
    function insert($table, $data, $format = null) { $c = implode(',', array_map(static function ($k) { return '`' . $k . '`'; }, array_keys($data))); $v = implode(',', array_map(function ($x) { return $x === null ? 'NULL' : $this->pdo->quote((string)$x); }, array_values($data))); return $this->query('INSERT INTO ' . $table . ' (' . $c . ') VALUES (' . $v . ')'); }
    function replace($table, $data, $format = null) { return $this->insert($table, $data); }
    function update($table, $data, $where, $f = null, $wf = null) { $set = []; $parts = []; foreach ($data as $k => $v) $set[] = '`' . $k . '` = ' . ($v === null ? 'NULL' : $this->pdo->quote((string)$v)); foreach ($where as $k => $v) $parts[] = '`' . $k . '` = ' . $this->pdo->quote((string)$v); return $this->query('UPDATE ' . $table . ' SET ' . implode(',', $set) . ' WHERE ' . implode(' AND ', $parts)); }
    function delete($table, $where, $format = null) { $parts = []; foreach ($where as $k => $v) $parts[] = '`' . $k . '` = ' . $this->pdo->quote((string)$v); return $this->query('DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $parts)); }
}
$wpdb = new BrowserSQL(BCRM_RUNTIME . '/fixture.sqlite');

class Biawin_CRM_Auth {
    public static function portal_url(array $args = []): string { return $args ? add_query_arg($args, '/portal') : '/portal'; }
    public static function role_labels(): array { return []; }
}
foreach (['db', 'normalizer', 'ownership-schema', 'ownership-authorization', 'ownership-migration-audit', 'ownership-repository', 'ownership-audit', 'customer-hold-service',
          'ownership-lock-service', 'invoice-phone-service', 'ownership-risk-service', 'ownership-workflow', 'ownership-otp', 'ownership-transfer-service', 'ownership-write-guard',
          'customer-service', 'workflow-service', 'deficiency-workflow', 'frontend', 'ownership-presenter', 'ownership-ajax', 'ownership-ui', 'ownership-legacy-bridge'] as $name) {
    require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-' . $name . '.php';
}
