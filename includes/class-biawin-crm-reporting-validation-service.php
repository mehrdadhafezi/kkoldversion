<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.55.2 read-only Reporting Source-of-Truth validation.
 */
class Biawin_CRM_Reporting_Validation_Service
{
    public static function run(): array
    {
        Biawin_CRM_Reporting_Population_Service::reset_request_memo();
        $population = Biawin_CRM_Reporting_Population_Service::summary();
        $canonical_status = (string)($population['metadata']['canonical_population_status'] ?? 'BLOCKED');
        $financial = Biawin_CRM_Reporting_Financial_Service::freshness_summary();
        $event_risk = self::event_double_count_risk();
        $validation_status = self::validation_status($canonical_status, $financial, $event_risk);

        return [
            'meta' => [
                'plugin_version' => defined('BIAWIN_CRM_VERSION') ? BIAWIN_CRM_VERSION : '',
                'generated_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
                'read_only' => true,
                'core_source_of_truth_status' => $canonical_status === 'HEALTHY' ? 'HEALTHY' : 'BLOCKED',
                'validation_execution_status' => $validation_status,
                'overall_reporting_sot_status' => $validation_status,
                'guard_source_status' => $population['sources']['guard']['status'] ?? 'UNKNOWN',
                'blacklist_source_status' => $population['sources']['blacklist']['status'] ?? 'UNKNOWN',
                'canonical_population_status' => $canonical_status,
                'run_7_mutation' => 'NO',
                'run_7_continuation' => 'NO',
                'run_8_implemented' => 'NO',
                'run_8_executable' => 'NO',
                'guard_mutation' => 'NO',
                'guard_sync' => 'NO',
                'customer_migration' => 'NO',
                'customerid_mutation' => 'NO',
                'customer_mutation' => 'NO',
                'invoice_mutation' => 'NO',
                'phone_mutation' => 'NO',
                'assignment_mutation' => 'NO',
                'case_mutation' => 'NO',
                'history_mutation' => 'NO',
                'database_domain_mutation' => 'NO',
                'database_schema_mutation' => 'NO',
                'frozen_plan_business_dependency' => 'NO',
            ],
            'population' => $population,
            'financial_freshness' => $financial,
            'financial_semantics' => Biawin_CRM_Reporting_Financial_Service::semantics(),
            'assignment_integrity' => Biawin_CRM_Reporting_Assignment_Service::summary($population),
            'case_ownership_integrity' => self::case_ownership_integrity($population),
            'deficiency_orphan_diagnostics' => self::deficiency_orphan_diagnostics(),
            'event_double_count_risk' => $event_risk,
            'migration_state' => self::migration_state(),
        ];
    }

    public static function case_ownership_integrity(?array $population = null): array
    {
        $population = $population ?: Biawin_CRM_Reporting_Population_Service::summary();
        $domains = [
            'complaints' => 'complaints',
            'deficiency_cases' => 'deficiency_cases',
            'financial_cases' => 'financial_cases',
            'service_cases' => 'service_cases',
            'refunds' => 'refunds',
        ];
        $rows = [];
        foreach ($domains as $key => $logical) {
            $rows[$key] = self::case_domain_counts($logical, $population);
        }
        return $rows;
    }

    private static function case_domain_counts(string $logical, array $population): array
    {
        global $wpdb;
        $t = Biawin_CRM_DB::table($logical);
        $c = Biawin_CRM_DB::table('customers');
        $blacklist = Biawin_CRM_Reporting_Population_Service::blacklist_live_source();
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($t)) {
            return ['total_rows'=>0,'valid_customer_refs'=>0,'orphan_refs'=>0,'guarded_customer_refs'=>null,'blacklisted_customer_refs'=>null,'table_exists'=>false,'source_status'=>'TABLE_UNAVAILABLE'];
        }
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
        $valid = Biawin_CRM_Reporting_Population_Service::table_exists($c)
            ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $t x INNER JOIN $c c ON c.id=x.customer_id")
            : 0;
        $orphan = Biawin_CRM_Reporting_Population_Service::table_exists($c)
            ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $t x LEFT JOIN $c c ON c.id=x.customer_id WHERE x.customer_id IS NULL OR c.id IS NULL")
            : $total;
        $guarded = null;
        if (($population['sources']['guard']['trusted'] ?? false) && class_exists('Biawin_CRM_Production_Readiness')) {
            $q = Biawin_CRM_Production_Readiness::table();
            if (Biawin_CRM_Reporting_Population_Service::table_exists($q)) {
                $guarded = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t x INNER JOIN $q q ON q.customer_id=x.customer_id");
            }
        }
        $blacklisted = $blacklist['available'] ? self::count_rows_for_customer_ids($t, $blacklist['ids']) : null;
        return [
            'total_rows' => $total,
            'valid_customer_refs' => $valid,
            'orphan_refs' => $orphan,
            'guarded_customer_refs' => $guarded,
            'blacklisted_customer_refs' => $blacklisted,
            'table_exists' => true,
            'source_status' => (($population['sources']['guard']['trusted'] ?? false) && $blacklist['available']) ? 'AVAILABLE' : 'PARTIAL_SOURCE_BLOCKED',
        ];
    }

    private static function count_rows_for_customer_ids(string $table, array $ids): int
    {
        global $wpdb;
        if (!$ids) return 0;
        $total = 0;
        foreach (array_chunk(array_map('intval', $ids), 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $total += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE customer_id IN ($ph)", ...$chunk));
        }
        return $total;
    }

    public static function deficiency_orphan_diagnostics(int $limit = 100): array
    {
        global $wpdb;
        $d = Biawin_CRM_DB::table('deficiency_cases');
        $c = Biawin_CRM_DB::table('customers');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($d) || !Biawin_CRM_Reporting_Population_Service::table_exists($c)) {
            return ['status'=>'TABLE_UNAVAILABLE','trusted'=>false,'rows'=>[]];
        }
        $limit = max(1, min(500, $limit));
        self::clear_last_error();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id deficiency_case_id,d.customer_id,d.status,d.created_at,d.updated_at FROM $d d LEFT JOIN $c c ON c.id=d.customer_id WHERE d.customer_id IS NULL OR c.id IS NULL ORDER BY d.id ASC LIMIT %d",
            $limit
        ), ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) {
            return ['status'=>'QUERY_FAILED','trusted'=>false,'error'=>self::sanitize_error(self::last_error()),'rows'=>[]];
        }
        return ['status'=>'AVAILABLE','trusted'=>true,'rows'=>$rows];
    }

    public static function event_double_count_risk(): array
    {
        global $wpdb;
        $activities = Biawin_CRM_DB::table('activities');
        $audit = Biawin_CRM_DB::table('audit_log');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($activities) || !Biawin_CRM_Reporting_Population_Service::table_exists($audit)) {
            return self::event_untrusted('TABLE_UNAVAILABLE', 'activities or audit_log table unavailable');
        }
        $activity_column = self::activity_event_column($activities);
        if ($activity_column === null) {
            return self::event_untrusted('COLUMN_UNAVAILABLE', 'activities event column unavailable');
        }
        $sql = "SELECT COUNT(*) FROM $activities ac INNER JOIN $audit au
            ON au.object_type='customer'
            AND au.object_id=ac.customer_id
            AND au.action=ac.$activity_column
            AND ABS(TIMESTAMPDIFF(SECOND,au.created_at,ac.created_at))<=5
            WHERE ac.$activity_column='assignment_returned'";
        self::clear_last_error();
        $count = $wpdb->get_var($sql);
        if (self::last_query_failed()) {
            return self::event_untrusted('QUERY_FAILED', self::last_error());
        }
        return [
            'status' => 'AVAILABLE',
            'trusted' => true,
            'risk_indicator' => 'ACTIVITIES_AND_AUDIT_LOG_ARE_SEPARATE_EVIDENCE_STREAMS',
            'activity_event_column' => $activity_column,
            'potential_duplicate_assignment_return_events' => (int)$count,
            'policy' => 'audit_log must not be automatically added to operational event totals',
        ];
    }

    public static function activity_event_column(string $activities_table): ?string
    {
        global $wpdb;
        foreach (['activity_type', 'type'] as $column) {
            self::clear_last_error();
            $found = $wpdb->get_var("SHOW COLUMNS FROM $activities_table LIKE '$column'");
            if (!self::last_query_failed() && (string)$found === $column) return $column;
        }
        return null;
    }

    public static function migration_state(): array
    {
        global $wpdb;
        $run8_count = 0;
        $run7_terminal = 'UNKNOWN';
        $engine = Biawin_CRM_DB::table('blacklist_final_runs');
        if (Biawin_CRM_Reporting_Population_Service::table_exists($engine)) {
            $run8_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $engine WHERE mode LIKE '%run8%'");
            $run7 = $wpdb->get_row("SELECT status FROM $engine WHERE run_uuid='39c9c292-31e1-43df-8305-201bdec445ca' ORDER BY id DESC LIMIT 1", ARRAY_A);
            if ($run7) $run7_terminal = in_array((string)$run7['status'], ['completed','complete','terminal','done'], true) ? 'YES' : 'CHECK_STATUS_' . (string)$run7['status'];
        }
        return [
            'guard_enabled' => class_exists('Biawin_CRM_Production_Readiness') && Biawin_CRM_Production_Readiness::guard_enabled(),
            'run_7_terminal' => $run7_terminal,
            'run_8_implemented_or_executable' => 'NO',
            'run_8_rows_detected' => $run8_count,
            'frozen_plan_business_dependency' => 'NO',
        ];
    }

    public static function validation_status(string $canonical_status, array $financial, array $event_risk): string
    {
        if ($canonical_status !== 'HEALTHY') return 'BLOCKED';
        if (empty($financial['trusted']) || empty($event_risk['trusted'])) return 'DEGRADED';
        return 'HEALTHY';
    }

    private static function event_untrusted(string $status, string $error): array
    {
        return [
            'status' => $status,
            'trusted' => false,
            'error' => self::sanitize_error($error),
            'risk_indicator' => 'UNAVAILABLE',
            'activity_event_column' => null,
            'potential_duplicate_assignment_return_events' => null,
            'policy' => 'audit_log must not be automatically added to operational event totals',
        ];
    }

    private static function clear_last_error(): void
    {
        global $wpdb;
        if (isset($wpdb)) $wpdb->last_error = '';
    }

    private static function last_query_failed(): bool
    {
        global $wpdb;
        return isset($wpdb->last_error) && (string)$wpdb->last_error !== '';
    }

    private static function last_error(): string
    {
        global $wpdb;
        return isset($wpdb->last_error) && (string)$wpdb->last_error !== '' ? (string)$wpdb->last_error : 'database query failed';
    }

    private static function sanitize_error(string $error): string
    {
        $error = preg_replace('/\s+/', ' ', strip_tags($error));
        return substr(trim((string)$error), 0, 240);
    }
}
