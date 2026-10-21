<?php
if (!defined('ABSPATH')) { exit; }

/** Internal persistence; public application commands live in Workflow/OTP/Transfer. */
class Biawin_CRM_Ownership_Repository
{
    private static $depth = 0;
    public static function table(string $name): string { return Biawin_CRM_DB::table($name); }
    public static function rows(string $sql): array {
        global $wpdb;
        $wpdb->last_error = '';
        $rows = $wpdb->get_results($sql);
        if ($wpdb->last_error !== '' || !is_array($rows)) throw new RuntimeException('Ownership read failed: ' . $wpdb->last_error);
        return $rows;
    }
    public static function row(string $table, int $id, bool $lock = false): object {
        global $wpdb;
        $rows = self::rows($wpdb->prepare('SELECT * FROM ' . self::table($table) . ' WHERE id=%d' . ($lock ? ' FOR UPDATE' : ''), $id));
        if (!$rows) throw new RuntimeException('Ownership record not found: ' . $table);
        return $rows[0];
    }
    public static function assets(int $case_id): array {
        global $wpdb;
        return self::rows($wpdb->prepare('SELECT * FROM ' . self::table('ownership_case_assets') . ' WHERE case_id=%d ORDER BY id', $case_id));
    }
    public static function insert(string $table, array $data): int {
        global $wpdb;
        if ($wpdb->insert(self::table($table), $data) === false) throw new RuntimeException('Ownership insert failed: ' . $wpdb->last_error);
        return (int)$wpdb->insert_id;
    }
    public static function update(string $table, array $data, array $where): void {
        global $wpdb;
        if ($wpdb->update(self::table($table), $data, $where) === false) throw new RuntimeException('Ownership update failed: ' . $wpdb->last_error);
    }
    public static function sql(string $sql): void {
        global $wpdb;
        if ($wpdb->query($sql) === false) throw new RuntimeException('Ownership SQL failed: ' . $wpdb->last_error);
    }
    public static function json($value): string { return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
    public static function decode(?string $value): array { return $value ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : []; }
    private static $legacy_open = false;
    private static $legacy_gate = false;
    private static $atomic_open = false;
    public static function in_transaction(): bool { return self::$legacy_open || self::$atomic_open; }
    private static function lock_name(): string {
        global $wpdb;
        return 'bcrm_ownership_' . md5($wpdb->dbname . '|' . $wpdb->prefix);
    }
    private static function enter_gate(): void {
        global $wpdb;
        if (self::$depth) { self::$depth++; return; }
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', self::lock_name()));
        if ((string)$locked !== '1') throw new RuntimeException('Ownership safety lock unavailable; retry.');
        self::$depth = 1;
    }
    private static function leave_gate(): void {
        global $wpdb;
        self::$depth--;
        if (self::$depth) return;
        $saved = [];
        foreach (['insert_id','rows_affected','last_error','last_query','last_result','num_rows'] as $property) if (property_exists($wpdb,$property)) $saved[$property] = $wpdb->$property;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name()));
        foreach ($saved as $property=>$value) $wpdb->$property = $value;
    }
    public static function gate(callable $work) {
        self::enter_gate();
        try { return $work(); } finally { self::leave_gate(); }
    }
    /** Only transactions that reach a protected mutation retain the ownership gate. */
    public static function guarded_write(callable $work) {
        if (self::$legacy_open) {
            if (!self::$legacy_gate) { self::enter_gate(); self::$legacy_gate = true; }
            return $work();
        }
        return self::gate($work);
    }
    /** Preserve caller transaction SQL, without serializing unrelated transactions. */
    public static function legacy_transaction(string $sql) {
        global $wpdb;
        $begin = preg_match('/^\s*(START TRANSACTION|BEGIN)\s*;?\s*$/i',$sql);
        if ($begin) {
            if (!self::$legacy_open) {
                if (self::$depth) throw new RuntimeException('Legacy transaction cannot commit an ownership transaction.');
            }
            $result = $wpdb->query($sql);
            self::$legacy_open = $result !== false;
            if ($result === false && self::$legacy_gate) { self::$legacy_gate = false; self::leave_gate(); }
            return $result;
        }
        if (self::$atomic_open && !self::$legacy_open) throw new RuntimeException('Legacy SQL cannot end an ownership transaction.');
        try { return $wpdb->query($sql); }
        finally {
            self::$legacy_open = false;
            if (self::$legacy_gate) { self::$legacy_gate = false; self::leave_gate(); }
        }
    }
    public static function atomic(callable $work) {
        return self::gate(static function () use ($work) {
            global $wpdb;
            if (self::in_transaction()) throw new RuntimeException('Ownership command cannot nest inside an existing transaction.');
            if ((string)$wpdb->get_var('SELECT @@autocommit') !== '1') throw new RuntimeException('Ownership commands require an autocommit connection.');
            // Cross-domain atomicity is mandatory. Never silently use nontransactional tables.
            $names = ['ownership_cases','ownership_case_assets','ownership_case_amendments','ownership_events','ownership_locks','customer_holds','ownership_otp_challenges','customers','invoices','phones','invoice_phones','services','attachments','activities','financial_cases','deficiency_cases','case_actions','customer_tags','service_cases','interactions'];
            $quoted = implode(',', array_map(static function ($n) use ($wpdb) { return $wpdb->prepare('%s', self::table($n)); }, $names));
            $tables = self::rows('SHOW TABLE STATUS WHERE Name IN (' . $quoted . ')');
            if (count($tables) !== count($names)) throw new RuntimeException('Ownership transactional tables are missing.');
            foreach ($tables as $table) if (strcasecmp($table->Engine, 'InnoDB') !== 0) throw new RuntimeException('Ownership requires InnoDB: ' . $table->Name);
            // Unlike MariaDB's @@in_transaction, SET TRANSACTION is supported by
            // MySQL too. Without SESSION it fails inside an existing transaction,
            // preventing START TRANSACTION from silently committing a caller's work.
            $previous = $wpdb->suppress_errors(true);
            try { $ready = $wpdb->query('SET TRANSACTION READ WRITE'); }
            finally { $wpdb->suppress_errors($previous); }
            if ($ready === false) throw new RuntimeException('Ownership cannot start inside an existing or unsupported transaction.');
            self::sql('START TRANSACTION');
            self::$atomic_open = true;
            try { $result = $work(); self::sql('COMMIT'); return $result; }
            catch (Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
            finally { self::$atomic_open = false; }
        });
    }
    public static function case_command(int $id, int $version, callable $work) {
        return self::atomic(static function () use ($id, $version, $work) {
            $case = self::row('ownership_cases', $id, true);
            if ((int)$case->lock_version !== $version) throw new RuntimeException('Stale ownership case version.');
            $result = $work($case);
            self::update('ownership_cases', ['lock_version'=>$version+1, 'updated_at'=>Biawin_CRM_DB::now()], ['id'=>$id]);
            return $result;
        });
    }
    public static function conflicting(int $case_id, string $type, int $asset_id): bool {
        global $wpdb;
        $rows = self::rows($wpdb->prepare('SELECT a.id FROM ' . self::table('ownership_case_assets') . ' a JOIN ' . self::table('ownership_cases') . " c ON c.id=a.case_id WHERE a.asset_type=%s AND a.asset_id=%d AND c.id<>%d AND c.status NOT IN ('DRAFT','CLOSED','CANCELLED') LIMIT 1", $type, $asset_id, $case_id));
        return (bool)$rows;
    }
}
