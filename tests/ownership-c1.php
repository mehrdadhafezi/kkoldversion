<?php
/** Standalone C1 tests with explicit WordPress/wpdb doubles; not live DB acceptance. */
define('ABSPATH', __DIR__ . '/');
define('BIAWIN_CRM_VERSION', '0.8.65.18');
require_once __DIR__ . '/../includes/class-biawin-crm-db.php';
require_once __DIR__ . '/../includes/class-biawin-crm-normalizer.php';
require_once __DIR__ . '/../includes/class-biawin-crm-ownership-schema.php';
require_once __DIR__ . '/../includes/class-biawin-crm-ownership-authorization.php';
require_once __DIR__ . '/../includes/class-biawin-crm-ownership-migration-audit.php';
require_once __DIR__ . '/../includes/class-biawin-crm-activator.php';

$options = [];
$roles = [];
$checks = 0;
function check($condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $autoload = null) { global $options; $options[$key] = $value; return true; }
function delete_option($key) { global $options; unset($options[$key]); }
function wp_cache_delete($key, $group) {}
function current_time($format) { return '2026-10-21 12:00:00'; }
function get_current_user_id() { return 0; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
class RoleDouble {
    public $capabilities;
    function __construct(array $caps) { $this->capabilities = $caps; }
    function add_cap($cap) { $this->capabilities[$cap] = true; }
    function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
function get_role($name) { global $roles; return $roles[$name] ?? null; }
function add_role($name, $label, $caps) { global $roles; return $roles[$name] = new RoleDouble($caps); }
class DBFixture {
    public $prefix = 'custom_';
    public $dbname = 'fixture';
    public $last_error = '';
    public $suppressed = false;
    public $schema = [];
    public $columns = [];
    public $queries = [];
    public $results = [];
    public $fail = false;
    public $omit_column = false;
    function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    function suppress_errors($value) { $old = $this->suppressed; $this->suppressed = $value; return $old; }
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace('/%[ds]/', is_int($arg) ? (string)$arg : "'" . str_replace("'", "''", $arg) . "'", $sql, 1);
        return $sql;
    }
    function get_var($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'GET_LOCK') !== false) return '1';
        if (preg_match("/Field = '([^']+)'/", $sql, $m)) return isset($this->columns[$m[1]]) ? $m[1] : null;
        throw new RuntimeException('Unexpected read: ' . $sql);
    }
    function get_col($sql, $offset = 0) {
        preg_match('/`([^`]+)`/', $sql, $m);
        $statement = $this->schema[$m[1]] ?? '';
        if ($offset === 2) {
            preg_match_all('/(?:UNIQUE )?KEY (\w+) \(/', $statement, $matches);
            return array_merge(['PRIMARY'], $matches[1]);
        }
        preg_match_all('/^\s+(\w+) (?:BIGINT|SMALLINT|VARCHAR|LONGTEXT|DATETIME)\b/m', $statement, $matches);
        return $this->omit_column ? [] : $matches[1];
    }
    function query($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'RELEASE_LOCK') !== false) return 1;
        if (preg_match('/^ALTER TABLE .* ADD COLUMN `([^`]+)`/', $sql, $m)) {
            if ($this->fail) { $this->last_error = 'injected DDL failure'; return false; }
            $this->columns[$m[1]] = true;
            return 1;
        }
        throw new RuntimeException('Unexpected mutation: ' . $sql);
    }
    function get_results($sql) {
        check(strpos(ltrim($sql), 'SELECT ') === 0, 'audit is SELECT-only');
        $this->queries[] = $sql;
        if (!$this->results) throw new RuntimeException('Unexpected query: ' . $sql);
        $result = array_shift($this->results);
        if ($result === false) { $this->last_error = 'injected read failure'; return null; }
        return $result;
    }
    function insert(...$args) { return 1; } // upgrade error log only
}
function dbDelta($statement) {
    global $wpdb;
    preg_match('/CREATE TABLE (\S+)/', $statement, $m);
    $wpdb->schema[$m[1]] = $statement;
}
$wpdb = new DBFixture();
Biawin_CRM_Ownership_Schema::ensure();
$first = serialize([$wpdb->schema, $wpdb->columns]);
Biawin_CRM_Ownership_Schema::ensure();
check($first === serialize([$wpdb->schema, $wpdb->columns]), 'repeat schema is stable');
check(count($wpdb->schema) === 7 && count($wpdb->columns) === 4, 'seven tables and four columns');
check(count(array_filter($wpdb->queries, static function ($q) { return strpos($q, 'ALTER TABLE') === 0; })) === 4, 'no duplicate ALTER');
foreach (Biawin_CRM_Ownership_Schema::invoice_columns() as $definition) check(substr($definition, -4) === 'NULL', 'nullable invoice column');
$wpdb->omit_column = true;
try { Biawin_CRM_Ownership_Schema::ensure(); check(false, 'incomplete DDL must fail'); }
catch (RuntimeException $e) { check(strpos($e->getMessage(), 'Incomplete') !== false, 'incomplete DDL detected'); }
check(!$wpdb->suppressed, 'error suppression restored');
$wpdb = new DBFixture();
$options = ['biawin_crm_db_version' => '0.8.65.17', Biawin_CRM_Activator::FINANCE_RETURN_FOLLOWUP_CAP_OPTION => 1];
$roles['administrator'] = new RoleDouble(['manage_options' => true]);
$wpdb->fail = true;
Biawin_CRM_Activator::maybe_upgrade();
check(get_option('biawin_crm_db_version') === '0.8.65.17', 'failed migration does not advance version');
check(!$wpdb->suppressed, 'failure restores suppression');
$wpdb->fail = false;
Biawin_CRM_Activator::maybe_upgrade();
check(get_option('biawin_crm_db_version') === BIAWIN_CRM_VERSION, 'migration retries successfully without activation data operations');
$before = count($wpdb->queries);
Biawin_CRM_Activator::maybe_upgrade();
check(count($wpdb->queries) === $before, 'up-to-date load performs no schema/audit queries');
// Older upgrades retain activation. An unavailable activation dependency must
// not leave the current release stamped by the successful C1 migration alone.
$options['biawin_crm_db_version'] = '0.8.65.16';
set_error_handler(static function () { return true; });
Biawin_CRM_Activator::maybe_upgrade();
restore_error_handler();
check(get_option('biawin_crm_db_version') === '0.8.65.16', 'older upgrade activation failure remains retryable');
$options['biawin_crm_db_version'] = BIAWIN_CRM_VERSION;
check(get_role('bcrm_ownership_specialist')->has_cap('approve_normal_ownership_transfer'), 'specialist normal approval');
check(!get_role('bcrm_ownership_specialist')->has_cap('approve_high_risk_ownership_transfer'), 'specialist cannot approve high risk');
check(get_role('bcrm_ownership_supervisor')->has_cap('approve_high_risk_ownership_transfer'), 'supervisor high-risk approval');
check(get_role('bcrm_portal_admin')->has_cap('manage_ownership_policy'), 'CRM admin policy');
check(!get_role('bcrm_supervisor')->has_cap('view_ownership_cases'), 'unrelated CRM role unchanged');
$requester_roles = ['bcrm_agent', 'bcrm_supervisor', 'bcrm_deficiency_agent', 'bcrm_deficiency_supervisor'];
$requester_caps = ['submit_ownership_claim', 'view_own_ownership_claims'];
$ownership_map = Biawin_CRM_Ownership_Authorization::role_capabilities();
$restricted_caps = array_diff($ownership_map['bcrm_ownership_supervisor'], $requester_caps);
foreach ($requester_roles as $name) {
    check($ownership_map[$name] === $requester_caps, $name . ' receives exactly request-side caps');
    foreach ($requester_caps as $cap) check(get_role($name)->has_cap($cap), $name . ' can ' . $cap);
    foreach ($restricted_caps as $cap) check(!get_role($name)->has_cap($cap), $name . ' cannot ' . $cap);
    // Simulate an installation that already ran the original C1 marker 8.
    foreach ($requester_caps as $cap) {
        unset($roles[$name]->capabilities[$cap], $options[Biawin_CRM_Activator::CAPS_LEDGER_OPTION]['caps'][$name][$cap]);
    }
}
$options[Biawin_CRM_Activator::CAPS_MARKER_OPTION] = 8;
$before = count($wpdb->queries);
Biawin_CRM_Activator::maybe_upgrade();
check(count($wpdb->queries) === $before, 'requester upgrade does not rerun schema or audit');
check(get_option(Biawin_CRM_Activator::CAPS_MARKER_OPTION) === 9, 'requester marker advances from existing C1');
foreach ($requester_roles as $name) {
    foreach ($requester_caps as $cap) check(get_role($name)->has_cap($cap), 'existing C1 repaired: ' . $name . '/' . $cap);
}
foreach ($ownership_map['bcrm_ownership_specialist'] as $cap) check(get_role('bcrm_ownership_specialist')->has_cap($cap), 'specialist retains ' . $cap);
foreach ($ownership_map['bcrm_ownership_supervisor'] as $cap) check(get_role('bcrm_ownership_supervisor')->has_cap($cap), 'supervisor retains ' . $cap);
$state = serialize([$roles, $options]);
Biawin_CRM_Activator::sync_roles_and_caps();
check($state === serialize([$roles, $options]), 'role registration idempotent');
unset($roles['bcrm_ownership_specialist']->capabilities['review_ownership_cases']);
$roles['bcrm_ownership_supervisor']->capabilities['approve_high_risk_ownership_transfer'] = false;
unset($roles['bcrm_ownership_specialist']);
Biawin_CRM_Activator::sync_roles_and_caps();
check(!get_role('bcrm_ownership_specialist'), 'deleted role not recreated');
check(!get_role('bcrm_ownership_supervisor')->has_cap('approve_high_risk_ownership_transfer'), 'denial preserved');
// Legacy ledger absent: new operational caps still get provisioned, pre-existing denials survive.
$options = [];
$roles = ['administrator' => new RoleDouble(['manage_ownership_policy' => false]),
    'bcrm_ownership_specialist' => new RoleDouble(['review_ownership_cases' => false])];
Biawin_CRM_Activator::sync_roles_and_caps();
check(get_role('administrator')->has_cap('approve_high_risk_ownership_transfer'), 'ledger bootstrap provisions new admin caps');
check(!get_role('administrator')->has_cap('manage_ownership_policy'), 'ledger bootstrap preserves deny');
check(!get_role('bcrm_ownership_specialist')->has_cap('review_ownership_cases'), 'existing ownership role deny preserved');
unset($roles['bcrm_ownership_supervisor']->capabilities['review_ownership_cases']);
Biawin_CRM_Activator::sync_roles_and_caps();
check(!get_role('bcrm_ownership_supervisor')->has_cap('review_ownership_cases'), 'removed capability stays removed');
unset($roles['bcrm_agent']->capabilities['submit_ownership_claim']);
$roles['bcrm_deficiency_agent']->capabilities['view_own_ownership_claims'] = false;
Biawin_CRM_Activator::sync_roles_and_caps();
check(!get_role('bcrm_agent')->has_cap('submit_ownership_claim'), 'removed requester capability stays removed');
check(!get_role('bcrm_deficiency_agent')->has_cap('view_own_ownership_claims'), 'requester explicit deny preserved');
unset($roles['bcrm_deficiency_agent']);
Biawin_CRM_Activator::sync_roles_and_caps();
check(!get_role('bcrm_deficiency_agent'), 'deleted requester role not recreated');
$audit = 'Biawin_CRM_Ownership_Migration_Audit';
foreach ([
    [[], 'AMBIGUOUS_REVIEW'],
    [['09123456789'], 'CLEAN'],
    [['+989123456789'], 'DETERMINISTIC_REPAIR'],
    [['09123456789', '+989123456789'], 'DETERMINISTIC_REPAIR'],
    [['09123456789', '09987654321'], 'AMBIGUOUS_REVIEW'],
    [['bad'], 'AMBIGUOUS_REVIEW'],
] as $fixture) check($audit::classify($fixture[0])['classification'] === $fixture[1], 'legacy classification ' . json_encode($fixture[0]));
check($audit::classify(['09123456789'], '09987654321')['classification'] === $audit::AMBIGUOUS, 'canonical conflict');
$wpdb = new DBFixture();
$invoice = static function ($id) { return (object)['id'=>$id, 'customer_id'=>10, 'owner_exists'=>10, 'canonical_phone_normalized'=>null, 'profile_completeness'=>'complete', 'has_active_lock'=>0]; };
$wpdb->results = [[$invoice(1), $invoice(2), $invoice(3), $invoice(4)],
    [(object)['invoice_id'=>1,'normalized_phone'=>'09123456789'], (object)['invoice_id'=>3,'normalized_phone'=>'09123456789'], (object)['invoice_id'=>3,'normalized_phone'=>'09987654321']],
    [(object)['id'=>1,'normalized_phone'=>'09123456789','customer_id'=>10], (object)['id'=>2,'normalized_phone'=>'09987654321','customer_id'=>11]],
    [(object)['id'=>99,'invoice_id'=>3]]];
$options_before = serialize($options);
$report = $audit::audit_batch(0, 3);
check($report['counts'] === ['CLEAN'=>1,'DETERMINISTIC_REPAIR'=>0,'AMBIGUOUS_REVIEW'=>2], 'batch counts');
check($report['relation_counts'] === ['zero'=>1,'one'=>1,'multiple'=>1], 'relation counts');
check($report['has_more'] && $report['next_after_id'] === 3, 'keyset page boundary');
check($report['records'][0]['needs_initial_lock'], 'initial lock inventory only');
check($report['records'][2]['service_mismatch_ids'] === [99], 'service mismatch identifiers');
check(in_array('phone_owner_mismatch', $report['records'][2]['issues'], true), 'phone mismatch inventory');
check($options_before === serialize($options), 'audit writes no options');
$wpdb->results = [[$invoice(5)],
    [(object)['invoice_id'=>5,'normalized_phone'=>'+989123456789']],
    [(object)['id'=>1,'normalized_phone'=>'09123456789','customer_id'=>10]], []];
$normalized_report = $audit::audit_batch(3);
check($normalized_report['counts']['DETERMINISTIC_REPAIR'] === 1, 'batch deterministic normalization');
$wpdb->results = [[$invoice(6)],
    [(object)['invoice_id'=>6,'normalized_phone'=>'+989123456789']],
    [(object)['id'=>1,'normalized_phone'=>'09123456789','customer_id'=>10], (object)['id'=>2,'normalized_phone'=>'+989123456789','customer_id'=>11]], []];
$duplicate_report = $audit::audit_batch(5);
check(in_array('duplicate_phone_identity', $duplicate_report['records'][0]['issues'], true), 'normalization collision requires review');
$wpdb->results = [[$invoice(7)], [(object)['invoice_id'=>7,'normalized_phone'=>'09123456789']], [], []];
$missing_report = $audit::audit_batch(6);
check(in_array('missing_phone_identity', $missing_report['records'][0]['issues'], true), 'missing phone identity requires review');
$wpdb->results = [[]];
check(!$audit::audit_batch(3)['has_more'], 'empty last page');
$wpdb->results = [false];
try { $audit::audit_batch(); check(false, 'audit must not hide query failure'); }
catch (RuntimeException $e) { check(strpos($e->getMessage(), 'read failed') !== false, 'audit query failure surfaced'); }
echo "PASS: $checks C1 checks (WordPress/wpdb doubles; no live database).\n";
