<?php
/** Builds a fresh file-backed fixture for the browser acceptance run (schema from the real activator/ownership schema). */
$runtime = getenv('BCRM_BROWSER_RUNTIME') ?: sys_get_temp_dir() . '/bcrm-browser';
@mkdir($runtime . '/media', 0777, true);
foreach (glob($runtime . '/{*.sqlite,*.json,fail-customer,media/*}', GLOB_BRACE) as $f) @unlink($f);
require __DIR__ . '/bootstrap.php';

function seed_schema(): void {
    global $wpdb;
    $source = file_get_contents(BIAWIN_CRM_DIR . 'includes/class-biawin-crm-activator.php');
    preg_match_all('/CREATE TABLE \{\$p\}(\w+) \((.*?)\n        \) \$charset/s', $source, $matches, PREG_SET_ORDER);
    $statements = [];
    foreach ($matches as $m) $statements[] = 'CREATE TABLE test_bcrm_' . $m[1] . ' (' . $m[2] . "\n)";
    $statements = array_merge($statements, Biawin_CRM_Ownership_Schema::tables('test_bcrm_', ''));
    foreach ($statements as $sql) {
        $sql = preg_replace('/\) ENGINE=InnoDB.*$/s', ')', $sql);
        $sql = preg_replace('/id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT/', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/,?\s*PRIMARY KEY\s*\(id\)/', '', $sql);
        $sql = preg_replace('/,\s*KEY \w+ \([^\n]*\)/', '', $sql);
        $sql = preg_replace('/UNIQUE KEY \w+ /', 'UNIQUE ', $sql);
        $sql = preg_replace('/(product_name)\(120\)/', '$1', $sql);
        if ($wpdb->query($sql) === false) throw new RuntimeException($wpdb->last_error . ' Schema: ' . $sql);
    }
    foreach (['profile_completeness' => "TEXT DEFAULT 'pending'", 'completion_source' => "TEXT DEFAULT 'NORMAL'", 'journey_state' => 'TEXT', 'satisfaction_state' => 'TEXT', 'service_credit_total' => 'REAL DEFAULT 0', 'service_credit_reserved' => 'REAL DEFAULT 0', 'service_credit_consumed' => 'REAL DEFAULT 0', 'service_credit_remaining' => 'REAL DEFAULT 0'] as $column => $type) {
        if ($wpdb->query('ALTER TABLE test_bcrm_customers ADD COLUMN ' . $column . ' ' . $type) === false && strpos($wpdb->last_error, 'duplicate') === false) throw new RuntimeException($wpdb->last_error);
    }
    foreach (Biawin_CRM_Ownership_Schema::invoice_columns() as $column => $type) $wpdb->query('ALTER TABLE test_bcrm_invoices ADD COLUMN ' . $column . ' ' . $type);
}
function add_row(string $table, array $data): int { return Biawin_CRM_Ownership_Repository::insert($table, $data); }
function customer(string $name, string $code, bool $complete, float $paid = 0): int {
    $now = Biawin_CRM_DB::now();
    $id = add_row('customers', ['internal_uuid' => bin2hex(random_bytes(12)), 'display_name' => $name, 'customer_code' => $code, 'profile_completeness' => $complete ? 'complete' : 'incomplete', 'total_paid' => $paid, 'declared_paid' => $paid, 'created_at' => $now, 'updated_at' => $now]);
    add_row('interactions', ['agent_user_id' => 1, 'type' => 'call', 'customer_id' => $id, 'declared_paid' => $paid, 'created_at' => $now]);
    // The requester works the claimant profiles through the ordinary assignment model.
    add_row('assignments', ['customer_id' => $id, 'agent_user_id' => 1, 'status' => 'assigned', 'assigned_by' => 7, 'assigned_at' => $now]);
    return $id;
}
function phone(int $customer, string $number, bool $primary = true): int {
    return add_row('phones', ['customer_id' => $customer, 'raw_phone' => $number, 'normalized_phone' => $number, 'phone_status' => 'verified', 'is_primary' => $primary ? 1 : 0, 'created_at' => Biawin_CRM_DB::now()]);
}
function invoice(int $customer, string $code, float $amount, string $phone, string $service): int {
    $now = Biawin_CRM_DB::now();
    $id = add_row('invoices', ['customer_id' => $customer, 'invoice_code' => $code, 'amount_paid' => $amount, 'created_at' => $now, 'updated_at' => $now]);
    add_row('invoice_phones', ['invoice_id' => $id, 'normalized_phone' => $phone, 'created_at' => $now]);
    add_row('services', ['customer_id' => $customer, 'invoice_id' => $id, 'service_name' => $service, 'created_at' => $now]);
    add_row('payments', ['invoice_id' => $id, 'amount' => $amount, 'created_at' => $now]);
    return $id;
}
function lock(int $invoice, int $owner): void { Biawin_CRM_Ownership_Repository::atomic(static function () use ($invoice, $owner) { Biawin_CRM_Ownership_Lock_Service::create('INVOICE', $invoice, $owner); }); }

seed_schema();
$wpdb->query('CREATE TABLE test_users (ID INTEGER PRIMARY KEY, user_login TEXT, user_email TEXT, display_name TEXT, user_registered TEXT)');
$wpdb->query('CREATE TABLE test_posts (ID INTEGER PRIMARY KEY, post_title TEXT, post_type TEXT, post_mime_type TEXT, post_author INTEGER, guid TEXT)');
$wpdb->query('CREATE TABLE test_postmeta (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT)');
$wpdb->query('CREATE TABLE test_usermeta (umeta_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, meta_key TEXT, meta_value TEXT)');
foreach ($USERS as $id => $name) $wpdb->insert('test_users', ['ID' => $id, 'user_login' => 'user' . $id, 'user_email' => 'user' . $id . '@example.test', 'display_name' => $name, 'user_registered' => gmdate('Y-m-d H:i:s')]);
$uid = 0;
$fixture = [];
$pairs = [
    'A' => ['LOCK-INV-A', true], 'C' => ['HR-INV-C1', false], 'D' => ['SR-INV-D1', false], 'E' => ['EV-INV-E1', false],
    'F' => ['FIN-INV-F1', false], 'G' => ['CAN-INV-G1', false], 'H' => ['LEG-INV-H1', true], 'K' => ['KOM-INV-K1', false], 'S' => ['STALE-INV-S1', false],
];
$n = 0;
foreach ($pairs as $key => [$code, $locked]) {
    $n++;
    $source = customer('مالک فعلی ' . $key . ' — حساب پرداخت‌کننده اصلی با نام طولانی برای آزمون شکستن خط', 'C-' . (1000 + $n), true, 2000000);
    $claimant = customer('مدعی ' . $key, 'C-' . (2000 + $n), false, 1000000);
    $phone = '0912' . str_pad((string)(1000000 + $n * 11), 7, '0', STR_PAD_LEFT);
    $pid = phone($source, $phone);
    phone($claimant, '0935' . str_pad((string)(2000000 + $n * 13), 7, '0', STR_PAD_LEFT));
    $inv = invoice($source, $code, 1500000, $phone, 'خدمت پس از فروش ' . $key);
    $sibling = invoice($source, $code . '-SIB', 500000, $phone, 'خدمت دوم ' . $key);
    if ($locked) lock($inv, $source);
    $fixture[$key] = ['source' => $source, 'claimant' => $claimant, 'invoice' => $inv, 'invoice_code' => $code, 'sibling' => $sibling, 'sibling_code' => $code . '-SIB', 'phone' => $pid, 'phone_number' => $phone];
}
file_put_contents(BCRM_RUNTIME . '/fixture.json', json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode($fixture), "\n";
