<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.56.1 shared reporting consumer adapter.
 *
 * Read-only facade for future Release B consumers. It composes existing SOT
 * services and returns structured trust metadata; it does not migrate legacy UI,
 * exports, REST endpoints, dashboards, Guard, blacklist, or domain data.
 */
class Biawin_CRM_Reporting_Consumer_Adapter
{
    public const AVAILABLE = 'AVAILABLE';
    public const DEGRADED = 'DEGRADED';
    public const BLOCKED = 'BLOCKED';
    public const UNAVAILABLE = 'UNAVAILABLE';
    public const SEMANTICALLY_UNRESOLVED = 'SEMANTICALLY_UNRESOLVED';
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_AGENT = 'agent';
    public const ROLE_FINANCE = 'finance';
    public const ROLE_DEFICIENCY = 'deficiency';
    public const ROLE_SERVICE = 'service';
    public const ROLE_AUDITOR = 'auditor';

    private const CHUNK_SIZE = 500;
    private static $memo = [];

    public static function reset_request_memo(): void
    {
        self::$memo = [];
        if (class_exists('Biawin_CRM_Reporting_Population_Service')) {
            Biawin_CRM_Reporting_Population_Service::reset_request_memo();
        }
    }

    public static function public_methods(): array
    {
        return [
            'population_summary',
            'customer_population_ids',
            'customer_population_count',
            'customer_population_membership',
            'customer_financial_snapshot',
            'financial_snapshot_for_customers',
            'financial_total_for_population',
            'management_actual_paid_total',
            'management_report_kpi_rows',
            'management_satisfaction_summary',
            'management_contact_status_rows',
            'management_route_summary_rows',
            'management_route_detail_rows',
            'management_service_case_summary_rows',
            'management_source_service_summary_rows',
            'management_customer_preview_rows',
            'approved_paid_total',
            'assignment_workload_summary',
            'newest_active_assignments_for_customers',
            'workable_active_workload',
            'guarded_active_workload',
            'unassigned_normal_canonical',
            'management_workload_cards',
            'management_agent_workload_rows',
            'management_recent_assignment_rows',
            'management_guarded_assignment_rows',
            'management_assignment_filter_summary',
            'management_assignment_candidate_rows',
            'agent_dashboard_assignment_rows',
            'agent_dashboard_assignment_summary',
            'agent_workload',
            'event_report',
            'case_population_summary',
            'case_customer_validity',
            'case_rows',
            'case_orphan_summary',
            'case_guard_summary',
            'normalize_export_row',
            'sanitize_export_cell',
            'normalize_filters',
            'assert_scope',
        ];
    }

    public static function population_summary(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'population_summary');
        if (!$scope_check['trusted']) return $scope_check;
        $key = 'population_summary';
        if (isset(self::$memo[$key])) return self::$memo[$key];
        $summary = Biawin_CRM_Reporting_Population_Service::summary();
        return self::$memo[$key] = self::structured(
            !empty($summary['metadata']['canonical_population_status']) && $summary['metadata']['canonical_population_status'] === 'HEALTHY' ? self::AVAILABLE : self::BLOCKED,
            !empty($summary['metadata']['canonical_population_status']) && $summary['metadata']['canonical_population_status'] === 'HEALTHY',
            Biawin_CRM_Reporting_Population_Service::NORMAL_PLUS_VISIBLE_QUARANTINE,
            $summary,
            'reporting_population_service',
            null,
            ['freshness' => 'live_read', 'value' => $summary['counts'][Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL]['count'] ?? null]
        );
    }

    public static function customer_population_ids(string $population, array $scope = [], int $limit = 0, int $offset = 0): array
    {
        $scope_check = self::assert_scope($scope, 'customer_population_ids');
        if (!$scope_check['trusted']) return $scope_check;
        $source = self::population_source($population, $limit, $offset);
        $data = [
            'ids' => $source['ids'],
            'count' => $source['count'],
        ];
        if ($population === Biawin_CRM_Reporting_Population_Service::NORMAL_PLUS_VISIBLE_QUARANTINE) {
            $data['segments'] = $source['metadata']['segments'] ?? null;
            $data['composite_population_only'] = true;
            $data['business_count'] = $source['metadata']['segments']['normal_canonical']['count'] ?? null;
            $data['aggregate_visible_count'] = $source['count'];
        }
        return self::structured(
            $source['available'] ? self::AVAILABLE : self::BLOCKED,
            !empty($source['trusted']),
            $population,
            $data,
            'reporting_population_service',
            $source['error'] ?? null,
            ['value' => $source['count'], 'metadata' => $source['metadata'] ?? []]
        );
    }

    public static function customer_population_count(string $population, array $scope = []): array
    {
        $ids = self::customer_population_ids($population, $scope);
        return self::structured(
            $ids['status'],
            $ids['trusted'],
            $population,
            $population === Biawin_CRM_Reporting_Population_Service::NORMAL_PLUS_VISIBLE_QUARANTINE ? [
                'segments' => $ids['data']['segments'] ?? null,
                'composite_population_only' => true,
                'business_count' => $ids['data']['business_count'] ?? null,
                'aggregate_visible_count' => $ids['data']['aggregate_visible_count'] ?? null,
            ] : null,
            $ids['source'],
            $ids['error'],
            ['value' => $ids['trusted'] ? (int)($ids['data']['count'] ?? 0) : null, 'dependency_status' => $ids['dependency_status']]
        );
    }

    public static function customer_population_membership(int $customer_id, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'customer_population_membership', ['customer_id' => $customer_id]);
        if (!$scope_check['trusted']) return $scope_check;
        $segments = [];
        foreach ([
            Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL,
            Biawin_CRM_Reporting_Population_Service::GUARDED,
            Biawin_CRM_Reporting_Population_Service::BLACKLISTED_LIVE,
            Biawin_CRM_Reporting_Population_Service::UNRESOLVED,
        ] as $population) {
            $source = self::population_source($population);
            if (!$source['available']) {
                return self::structured(self::BLOCKED, false, $population, null, 'reporting_population_service', $source['error'] ?: $source['status'], ['value' => null]);
            }
            $segments[$population] = in_array($customer_id, array_map('intval', $source['ids']), true);
        }
        return self::structured(self::AVAILABLE, true, 'SEGMENTED', ['customer_id' => $customer_id, 'segments' => $segments], 'reporting_population_service');
    }

    public static function customer_financial_snapshot(int $customer_id, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'customer_financial_snapshot', ['customer_id' => $customer_id]);
        if (!$scope_check['trusted']) return $scope_check;
        $batch = self::financial_snapshot_for_customers([$customer_id], $scope);
        if (!$batch['trusted']) return $batch;
        return self::structured(self::AVAILABLE, true, 'CUSTOMER', $batch['data']['rows'][$customer_id] ?? null, 'reporting_financial_service', null, ['value' => $batch['data']['rows'][$customer_id]['actual_paid_live'] ?? null]);
    }

    public static function financial_snapshot_for_customers(array $customer_ids, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'financial_snapshot_for_customers');
        if (!$scope_check['trusted']) return $scope_check;
        $ids = self::normalize_ids($customer_ids);
        if (!$ids) return self::structured(self::AVAILABLE, true, 'CUSTOMER_SET', ['rows' => []], 'reporting_financial_service', null, ['value' => 0]);
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        $interactions = Biawin_CRM_DB::table('interactions');
        foreach ([$customers, $invoices, $interactions] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, 'CUSTOMER_SET', null, 'reporting_financial_service', 'FINANCIAL_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }
        $rows = [];
        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare(
                "SELECT c.id customer_id,
                    COALESCE(inv.actual_paid_live,0) actual_paid_live,
                    decl.declared_paid_latest,
                    CASE WHEN decl.declared_paid_latest IS NULL THEN NULL ELSE decl.declared_paid_latest-COALESCE(inv.actual_paid_live,0) END discrepancy_live,
                    c.total_paid cached_total_paid,
                    c.declared_paid cached_declared_paid,
                    c.discrepancy_amount cached_discrepancy
                FROM $customers c
                LEFT JOIN (SELECT customer_id," . Biawin_CRM_Reporting_Financial_Service::actual_paid_expression('i') . " actual_paid_live FROM $invoices i WHERE i.customer_id IN ($ph) GROUP BY customer_id) inv ON inv.customer_id=c.id
                LEFT JOIN (SELECT x.customer_id,x.declared_paid declared_paid_latest FROM $interactions x INNER JOIN (SELECT customer_id,MAX(id) max_id FROM $interactions WHERE declared_paid IS NOT NULL AND customer_id IN ($ph) GROUP BY customer_id) m ON m.max_id=x.id) decl ON decl.customer_id=c.id
                WHERE c.id IN ($ph)",
                ...array_merge($chunk, $chunk, $chunk)
            );
            self::clear_last_error();
            $chunk_rows = $wpdb->get_results($sql, ARRAY_A);
            if (self::last_query_failed() || !is_array($chunk_rows)) {
                return self::structured(self::BLOCKED, false, 'CUSTOMER_SET', null, 'reporting_financial_service', 'FINANCIAL_QUERY_FAILED', ['value' => null]);
            }
            foreach ($chunk_rows as $row) {
                $id = (int)$row['customer_id'];
                $rows[$id] = [
                    'customer_id' => $id,
                    'actual_paid_live' => (float)$row['actual_paid_live'],
                    'declared_paid_latest' => $row['declared_paid_latest'] === null ? null : (float)$row['declared_paid_latest'],
                    'discrepancy_live' => $row['discrepancy_live'] === null ? null : (float)$row['discrepancy_live'],
                    'cached_total_paid' => $row['cached_total_paid'] === null ? null : (float)$row['cached_total_paid'],
                    'cached_declared_paid' => $row['cached_declared_paid'] === null ? null : (float)$row['cached_declared_paid'],
                    'cached_discrepancy' => $row['cached_discrepancy'] === null ? null : (float)$row['cached_discrepancy'],
                    'cache_is_authoritative' => false,
                ];
            }
        }
        if (count($rows) !== count($ids)) {
            return self::structured(self::DEGRADED, false, 'CUSTOMER_SET', ['rows' => $rows], 'reporting_financial_service', 'FINANCIAL_PARTIAL_RESULT', ['value' => null]);
        }
        ksort($rows);
        return self::structured(self::AVAILABLE, true, 'CUSTOMER_SET', ['rows' => $rows], 'reporting_financial_service', null, ['value' => array_sum(array_column($rows, 'actual_paid_live'))]);
    }

    public static function financial_total_for_population(string $population, array $filters = [], array $scope = []): array
    {
        $normalized = self::normalize_filters($filters, ['timestamp_semantic' => ['invoice_created_at']]);
        if (!$normalized['trusted']) return $normalized;
        $normalized_filters = (array)($normalized['data']['filters'] ?? $filters);
        $business_filters = array_diff_key($normalized_filters, ['timestamp_semantic' => true]);
        if ($population === Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL && !$business_filters) {
            return self::management_actual_paid_total($scope);
        }
        $ids = self::customer_population_ids($population, $scope);
        if (!$ids['trusted']) return $ids;
        $snapshots = self::financial_snapshot_for_customers($ids['data']['ids'], $scope);
        if (!$snapshots['trusted']) return $snapshots;
        return self::structured(self::AVAILABLE, true, $population, ['amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE], 'reporting_financial_service', null, ['value' => (float)$snapshots['value']]);
    }

    public static function management_actual_paid_total(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_actual_paid_total');
        if (!$scope_check['trusted']) return $scope_check;

        $key = 'management_actual_paid_total';
        if (isset(self::$memo[$key])) return self::$memo[$key];

        $all = Biawin_CRM_Reporting_Population_Service::all_live_source();
        $guard = Biawin_CRM_Reporting_Population_Service::guard_source();
        $blacklist = Biawin_CRM_Reporting_Population_Service::blacklist_live_source();
        $canonical = Biawin_CRM_Reporting_Population_Service::canonical_count_result($all, $guard, $blacklist);
        if (empty($canonical['trusted'])) {
            return self::$memo[$key] = self::structured(
                self::BLOCKED,
                false,
                Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL,
                ['amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE],
                'reporting_financial_service',
                'CANONICAL_POPULATION_UNTRUSTED',
                ['value' => null, 'dependency_status' => $canonical['dependency_errors']]
            );
        }

        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        foreach ([$customers, $invoices] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::$memo[$key] = self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_financial_service', 'FINANCIAL_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        $excluded = self::normalize_ids(array_merge($guard['ids'], $blacklist['ids']));
        $where = '';
        $args = [];
        if ($excluded) {
            $where = ' WHERE c.id NOT IN (' . implode(',', array_fill(0, count($excluded), '%d')) . ')';
            $args = $excluded;
        }
        $sql = "SELECT " . Biawin_CRM_Reporting_Financial_Service::actual_paid_expression('i') . " actual_paid_live
            FROM $customers c
            LEFT JOIN $invoices i ON i.customer_id=c.id" . $where;
        if ($args) $sql = $wpdb->prepare($sql, ...$args);

        self::clear_last_error();
        $value = $wpdb->get_var($sql);
        if (self::last_query_failed()) {
            return self::$memo[$key] = self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_financial_service', 'FINANCIAL_QUERY_FAILED', ['value' => null]);
        }
        return self::$memo[$key] = self::structured(
            self::AVAILABLE,
            true,
            Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL,
            [
                'amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE,
                'algorithm' => 'database_side_canonical_aggregate',
                'canonical_population_count' => (int)$canonical['count'],
                'excluded_union_count' => (int)$canonical['excluded_union_count'],
                'population_materialization' => 'excluded_ids_only',
            ],
            'reporting_financial_service',
            null,
            ['value' => (float)$value]
        );
    }

    public static function approved_paid_total(array $filters = [], array $scope = []): array
    {
        return self::structured(self::SEMANTICALLY_UNRESOLVED, false, 'UNDEFINED', null, 'business_contract', 'APPROVED_PAID_SOURCE_UNDEFINED', ['value' => null]);
    }

    public static function management_report_kpi_rows(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_report_kpi_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_report_kpi_rows');
        if (!$ctx['trusted']) return $ctx;

        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        foreach ([$customers, $invoices] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'REPORT_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        $defs = [
            ['کل مشتریان', '1=1'],
            ['پروفایل کامل', "c.profile_completeness='complete'"],
            ['پروفایل ناقص', "c.profile_completeness='incomplete'"],
            ['بدون درخواست', "c.journey_state='no_request'"],
            ['دارای نارضایتی', "c.journey_state='complaint'"],
            ['درخواست خدمات', "c.journey_state='service_request'"],
            ['عودت وجه', "c.journey_state='refund'"],
        ];
        $rows = [];
        foreach ($defs as [$label, $where]) {
            [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
            $sql = "SELECT COUNT(DISTINCT c.id) count_all,
                COALESCE(SUM(CASE WHEN c.profile_completeness='complete' THEN CASE WHEN COALESCE(i.exclude_from_total,0)=1 THEN 0 ELSE i.amount_paid END ELSE 0 END),0) complete_amount,
                COALESCE(SUM(CASE WHEN c.profile_completeness='incomplete' THEN CASE WHEN COALESCE(i.exclude_from_total,0)=1 THEN 0 ELSE i.amount_paid END ELSE 0 END),0) incomplete_amount
                FROM $customers c LEFT JOIN $invoices i ON i.customer_id=c.id
                WHERE $where$exclude_sql";
            if ($args) $sql = $wpdb->prepare($sql, ...$args);
            self::clear_last_error();
            $row = $wpdb->get_row($sql, ARRAY_A);
            if (self::last_query_failed() || !is_array($row)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'REPORT_QUERY_FAILED', ['value' => null]);
            }
            $rows[] = [
                'label' => $label,
                'count' => (int)($row['count_all'] ?? 0),
                'complete_amount' => (float)($row['complete_amount'] ?? 0),
                'incomplete_amount' => (float)($row['incomplete_amount'] ?? 0),
                'trusted' => true,
                'status' => self::AVAILABLE,
                'population' => Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL,
                'amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE,
            ];
        }
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'canonical_context' => $ctx['data']], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function management_satisfaction_summary(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_satisfaction_summary');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_satisfaction_summary');
        if (!$ctx['trusted']) return $ctx;
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($customers)) {
            return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'CUSTOMERS_TABLE_UNAVAILABLE', ['value' => null]);
        }
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $sql = "SELECT COUNT(DISTINCT c.id) customers, AVG(c.satisfaction_avg) average_score FROM $customers c WHERE c.satisfaction_avg IS NOT NULL$exclude_sql";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        self::clear_last_error();
        $row = $wpdb->get_row($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($row)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'SATISFACTION_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['customers' => (int)($row['customers'] ?? 0), 'average' => $row['average_score'] ?? ''], 'reporting_consumer_adapter', null, ['value' => $row['average_score'] === null ? null : (float)$row['average_score']]);
    }

    public static function management_contact_status_rows(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_contact_status_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_contact_status_rows');
        if (!$ctx['trusted']) return $ctx;
        global $wpdb;
        $interactions = Biawin_CRM_DB::table('interactions');
        $customers = Biawin_CRM_DB::table('customers');
        foreach ([$interactions, $customers] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'CONTACT_TABLE_UNAVAILABLE', ['value' => null]);
        }
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $sql = "SELECT COALESCE(NULLIF(i.outcome_key,''),'__legacy__') outcome_key, i.outcome, COUNT(*) total, COUNT(DISTINCT i.customer_id) customers, MAX(i.created_at) last_at
            FROM $interactions i INNER JOIN $customers c ON c.id=i.customer_id
            WHERE 1=1$exclude_sql
            GROUP BY COALESCE(NULLIF(i.outcome_key,''),'__legacy__'), i.outcome
            ORDER BY total DESC";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'CONTACT_QUERY_FAILED', ['value' => null]);
        foreach ($rows as &$row) {
            $key = (string)($row['outcome_key'] ?? '');
            $row['label'] = $key !== '__legacy__' ? Biawin_CRM_Workflow_Service::contact_outcome_label($key, $key) : ((string)($row['outcome'] ?? '') ?: 'بدون وضعیت');
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function management_route_summary_rows(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_route_summary_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_route_summary_rows');
        if (!$ctx['trusted']) return $ctx;
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        foreach ([$customers, $invoices] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'ROUTE_TABLE_UNAVAILABLE', ['value' => null]);
        }
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'WHERE');
        $sql = "SELECT COALESCE(NULLIF(c.journey_state,''),'بدون مسیر') journey_state,
            COUNT(DISTINCT c.id) customers,
            COALESCE(SUM(CASE WHEN COALESCE(i.exclude_from_total,0)=1 THEN 0 ELSE i.amount_paid END),0) amount,
            COUNT(DISTINCT CASE WHEN c.profile_completeness='complete' THEN c.id END) complete_count,
            COALESCE(SUM(CASE WHEN c.profile_completeness='complete' THEN CASE WHEN COALESCE(i.exclude_from_total,0)=1 THEN 0 ELSE i.amount_paid END ELSE 0 END),0) complete_amount,
            COUNT(DISTINCT CASE WHEN c.profile_completeness='incomplete' THEN c.id END) incomplete_count,
            COALESCE(SUM(CASE WHEN c.profile_completeness='incomplete' THEN CASE WHEN COALESCE(i.exclude_from_total,0)=1 THEN 0 ELSE i.amount_paid END ELSE 0 END),0) incomplete_amount
            FROM $customers c LEFT JOIN $invoices i ON i.customer_id=c.id
            $exclude_sql
            GROUP BY COALESCE(NULLIF(c.journey_state,''),'بدون مسیر') ORDER BY customers DESC";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'ROUTE_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function management_route_detail_rows(int $limit, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_route_detail_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_route_detail_rows');
        if (!$ctx['trusted']) return $ctx;
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        foreach ([$customers, $invoices] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'ROUTE_DETAIL_TABLE_UNAVAILABLE', ['value' => null]);
        }
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'WHERE');
        $sql = "SELECT c.source_customer_id,c.customer_code,c.display_name,c.journey_state,c.profile_completeness,
            " . Biawin_CRM_Reporting_Financial_Service::actual_paid_expression('i') . " total_paid
            FROM $customers c LEFT JOIN $invoices i ON i.customer_id=c.id
            $exclude_sql
            GROUP BY c.id,c.source_customer_id,c.customer_code,c.display_name,c.journey_state,c.profile_completeness
            ORDER BY c.journey_state ASC,total_paid DESC,c.id DESC LIMIT %d";
        $args[] = max(1, min(500, $limit));
        $sql = $wpdb->prepare($sql, ...$args);
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'ROUTE_DETAIL_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function management_service_case_summary_rows(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_service_case_summary_rows');
        if (!$scope_check['trusted']) return $scope_check;
        global $wpdb;
        $catalog = Biawin_CRM_DB::table('service_catalog');
        $cases = Biawin_CRM_DB::table('service_cases');
        foreach ([$catalog, $cases] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, 'CASE_DOMAIN', null, 'reporting_consumer_adapter', 'SERVICE_CASE_TABLE_UNAVAILABLE', ['value' => null]);
        }
        self::clear_last_error();
        $rows = $wpdb->get_results("SELECT s.name,COUNT(sc.id) total,SUM(sc.status='unassigned') unassigned,SUM(sc.status IN ('assigned','in_progress','waiting')) active,SUM(sc.status='completed') completed,COALESCE(SUM(sc.credit_amount),0) credit FROM $catalog s LEFT JOIN $cases sc ON sc.service_id=s.id GROUP BY s.id,s.name ORDER BY s.sort_order,s.id");
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, 'CASE_DOMAIN', null, 'reporting_consumer_adapter', 'SERVICE_CASE_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, 'CASE_DOMAIN', ['rows' => $rows, 'case_semantic' => 'SERVICE_CASE_DOMAIN_NOT_REVENUE'], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function management_source_service_summary_rows(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_source_service_summary_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_source_service_summary_rows');
        if (!$ctx['trusted']) return $ctx;
        global $wpdb;
        $services = Biawin_CRM_DB::table('services');
        $customers = Biawin_CRM_DB::table('customers');
        foreach ([$services, $customers] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'SOURCE_SERVICE_TABLE_UNAVAILABLE', ['value' => null]);
        }
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $sql = "SELECT s.service_name,COUNT(*) total,COUNT(DISTINCT s.customer_id) customers
            FROM $services s INNER JOIN $customers c ON c.id=s.customer_id
            WHERE s.service_name<>''$exclude_sql
            GROUP BY s.service_name ORDER BY total DESC,s.service_name ASC LIMIT 500";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        self::clear_last_error();
        $rows = $wpdb->get_results($sql);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'SOURCE_SERVICE_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function management_customer_preview_rows(int $limit, int $offset, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_customer_preview_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_customer_preview_rows');
        if (!$ctx['trusted']) return $ctx;
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $phones = Biawin_CRM_DB::table('phones');
        $invoices = Biawin_CRM_DB::table('invoices');
        $assignments = Biawin_CRM_DB::table('assignments');
        $activities = Biawin_CRM_DB::table('activities');
        foreach ([$customers, $phones, $invoices, $assignments, $activities] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'CUSTOMER_PREVIEW_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'WHERE');
        $sql = $wpdb->prepare(
            "SELECT c.id,c.source_customer_id,c.customer_code,c.display_name,c.profile_completeness,c.journey_state,
                (SELECT normalized_phone FROM $phones pp WHERE pp.customer_id=c.id ORDER BY pp.is_primary DESC,pp.id LIMIT 1) phone,
                (SELECT COUNT(*) FROM $invoices ii WHERE ii.customer_id=c.id) invoice_count,
                " . Biawin_CRM_Reporting_Financial_Service::actual_paid_expression('i') . " total_paid,
                (SELECT aa.assigned_at FROM $assignments aa WHERE aa.customer_id=c.id ORDER BY aa.id ASC LIMIT 1) first_assigned_at,
                (SELECT u.display_name FROM $assignments aa LEFT JOIN {$wpdb->users} u ON u.ID=aa.agent_user_id WHERE aa.customer_id=c.id ORDER BY aa.id ASC LIMIT 1) first_agent_name,
                (SELECT u.display_name FROM $assignments aa LEFT JOIN {$wpdb->users} u ON u.ID=aa.agent_user_id WHERE aa.customer_id=c.id AND " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('aa') . " LIMIT 1) current_agent_name,
                (SELECT ac.title FROM $activities ac WHERE ac.customer_id=c.id ORDER BY ac.id DESC LIMIT 1) last_action_title,
                (SELECT ac.created_at FROM $activities ac WHERE ac.customer_id=c.id ORDER BY ac.id DESC LIMIT 1) last_action_at
            FROM $customers c LEFT JOIN $invoices i ON i.customer_id=c.id
            $exclude_sql
            GROUP BY c.id,c.source_customer_id,c.customer_code,c.display_name,c.profile_completeness,c.journey_state
            ORDER BY c.id DESC LIMIT %d OFFSET %d",
            ...array_merge($args, [$limit, $offset])
        );
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_consumer_adapter', 'CUSTOMER_PREVIEW_QUERY_FAILED', ['value' => null]);
        foreach ($rows as &$row) {
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
            $row['population'] = Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL;
            $row['amount_semantic'] = Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE;
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'amount_semantic' => Biawin_CRM_Reporting_Financial_Service::ACTUAL_PAID_LIVE], 'reporting_consumer_adapter', null, ['value' => count($rows)]);
    }

    public static function assignment_workload_summary(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'assignment_workload_summary');
        if (!$scope_check['trusted']) return $scope_check;
        $summary = Biawin_CRM_Reporting_Assignment_Service::summary();
        $trusted = !empty($summary['workable_active_workload']['trusted']);
        return self::structured($trusted ? self::AVAILABLE : self::BLOCKED, $trusted, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $summary, 'reporting_assignment_service', $trusted ? null : 'ASSIGNMENT_QUERY_FAILED', ['value' => $trusted ? $summary['workable_active_workload']['count'] : null]);
    }

    public static function newest_active_assignments_for_customers(array $customer_ids, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'newest_active_assignments_for_customers');
        if (!$scope_check['trusted']) return $scope_check;
        $ids = self::normalize_ids($customer_ids);
        if (!$ids) return self::structured(self::AVAILABLE, true, 'CUSTOMER_SET', ['rows' => []], 'reporting_assignment_service', null, ['value' => 0]);
        global $wpdb;
        $a = Biawin_CRM_DB::table('assignments');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($a)) return self::structured(self::BLOCKED, false, 'CUSTOMER_SET', null, 'reporting_assignment_service', 'ASSIGNMENTS_TABLE_UNAVAILABLE', ['value' => null]);
        $rows = [];
        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT a.* FROM $a a WHERE a.customer_id IN ($ph) AND " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a'), ...$chunk);
            self::clear_last_error();
            $result = $wpdb->get_results($sql, ARRAY_A);
            if (self::last_query_failed() || !is_array($result)) {
                return self::structured(self::BLOCKED, false, 'CUSTOMER_SET', null, 'reporting_assignment_service', 'ASSIGNMENT_QUERY_FAILED', ['value' => null]);
            }
            foreach ($result as $row) $rows[(int)$row['customer_id']] = $row;
        }
        return self::structured(self::AVAILABLE, true, 'CUSTOMER_SET', ['rows' => $rows, 'semantics' => 'NEWEST ACTIVE ASSIGNMENT PER CUSTOMER'], 'reporting_assignment_service', null, ['value' => count($rows)]);
    }

    public static function workable_active_workload(array $scope = []): array
    {
        $summary = self::assignment_workload_summary($scope);
        return self::structured($summary['status'], $summary['trusted'], Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $summary['data']['workable_active_workload'] ?? null, 'reporting_assignment_service', $summary['error'], ['value' => $summary['data']['workable_active_workload']['count'] ?? null]);
    }

    public static function guarded_active_workload(array $scope = []): array
    {
        $summary = self::assignment_workload_summary($scope);
        return self::structured($summary['status'], $summary['trusted'], Biawin_CRM_Reporting_Population_Service::GUARDED, $summary['data']['guarded_active_workload'] ?? null, 'reporting_assignment_service', $summary['error'], ['value' => $summary['data']['guarded_active_workload']['count'] ?? null]);
    }

    public static function unassigned_normal_canonical(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'unassigned_normal_canonical');
        if (!$scope_check['trusted']) return $scope_check;
        $result = Biawin_CRM_Reporting_Assignment_Service::unassigned_normal_canonical_customers();
        return self::structured(!empty($result['trusted']) ? self::AVAILABLE : self::BLOCKED, !empty($result['trusted']), Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $result, 'reporting_assignment_service', $result['trusted'] ? null : ($result['status'] ?? 'ASSIGNMENT_QUERY_FAILED'), ['value' => $result['trusted'] ? $result['count'] : null]);
    }

    public static function management_workload_cards(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_workload_cards');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_workload_cards');
        if (!$ctx['trusted']) return $ctx;

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        foreach ([$assignments, $customers] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'WORKLOAD_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $active_sql = "SELECT COUNT(DISTINCT c.id)
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . $exclude_sql;
        if ($args) $active_sql = $wpdb->prepare($active_sql, ...$args);
        self::clear_last_error();
        $workable = $wpdb->get_var($active_sql);
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'WORKLOAD_QUERY_FAILED', ['value' => null]);

        $unassigned = self::unassigned_normal_canonical($scope);
        if (empty($unassigned['trusted'])) return $unassigned;
        $guarded = self::guarded_active_workload($scope);
        $duplicates = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT customer_id FROM $assignments WHERE status='assigned' GROUP BY customer_id HAVING COUNT(*)>1) x");
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'WORKLOAD_DUPLICATE_QUERY_FAILED', ['value' => null]);

        $rows = [
            ['key' => 'workable_active', 'label' => 'تخصیص فعال قابل کار', 'value' => (int)$workable, 'population' => Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, 'semantic' => 'NEWEST_ACTIVE_NORMAL_CANONICAL'],
            ['key' => 'unassigned_normal', 'label' => 'بدون تخصیص عادی', 'value' => (int)$unassigned['value'], 'population' => Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, 'semantic' => 'UNASSIGNED_NORMAL_CANONICAL'],
            ['key' => 'guarded_active', 'label' => 'تخصیص Guard', 'value' => $guarded['trusted'] ? (int)$guarded['value'] : null, 'population' => Biawin_CRM_Reporting_Population_Service::GUARDED, 'semantic' => 'GUARDED_NEWEST_ACTIVE'],
            ['key' => 'duplicate_active_rows', 'label' => 'تخصیص فعال تکراری', 'value' => $duplicates, 'population' => 'ASSIGNMENT_DIAGNOSTIC', 'semantic' => 'DUPLICATE_ACTIVE_ROWS_DIAGNOSTIC'],
        ];
        foreach ($rows as &$row) {
            $row['trusted'] = $row['value'] !== null;
            $row['status'] = $row['trusted'] ? self::AVAILABLE : self::BLOCKED;
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'canonical_context' => $ctx['data']], 'reporting_assignment_service', null, ['value' => (int)$workable]);
    }

    public static function management_agent_workload_rows(array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_agent_workload_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_agent_workload_rows');
        if (!$ctx['trusted']) return $ctx;

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        foreach ([$assignments, $customers] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_WORKLOAD_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $sql = "SELECT a.agent_user_id,COALESCE(u.display_name,CONCAT('User #',a.agent_user_id)) agent_name,COUNT(DISTINCT c.id) active_customers
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . $exclude_sql . "
            GROUP BY a.agent_user_id,u.display_name
            ORDER BY active_customers DESC,a.agent_user_id ASC LIMIT 500";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_WORKLOAD_QUERY_FAILED', ['value' => null]);

        $guarded_by_agent = [];
        if (class_exists('Biawin_CRM_Production_Readiness')) {
            $guard_table = Biawin_CRM_Production_Readiness::table();
            if (Biawin_CRM_Reporting_Population_Service::table_exists($guard_table)) {
                $guard_sql = "SELECT a.agent_user_id,COUNT(DISTINCT a.customer_id) guarded_customers
                    FROM $assignments a INNER JOIN $guard_table q ON q.customer_id=a.customer_id
                    WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . "
                    GROUP BY a.agent_user_id";
                self::clear_last_error();
                $guard_rows = $wpdb->get_results($guard_sql, ARRAY_A);
                if (self::last_query_failed() || !is_array($guard_rows)) {
                    return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_GUARDED_WORKLOAD_QUERY_FAILED', ['value' => null]);
                }
                foreach ($guard_rows as $guard_row) {
                    $guarded_by_agent[(int)($guard_row['agent_user_id'] ?? 0)] = (int)($guard_row['guarded_customers'] ?? 0);
                }
            }
        }

        foreach ($rows as &$row) {
            $agent_user_id = (int)($row['agent_user_id'] ?? 0);
            $row['active_customers'] = (int)($row['active_customers'] ?? 0);
            $row['guarded_customers'] = array_key_exists($agent_user_id, $guarded_by_agent) ? $guarded_by_agent[$agent_user_id] : 0;
            $row['workable_customers'] = $row['active_customers'];
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
            $row['semantic'] = 'NEWEST_ACTIVE_NORMAL_CANONICAL';
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows], 'reporting_assignment_service', null, ['value' => count($rows)]);
    }

    public static function management_recent_assignment_rows(int $limit = 30, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_recent_assignment_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_recent_assignment_rows');
        if (!$ctx['trusted']) return $ctx;
        $limit = max(1, min(100, $limit));

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        $interactions = Biawin_CRM_DB::table('interactions');
        foreach ([$assignments, $customers, $interactions] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'RECENT_ASSIGNMENT_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $total_sql = "SELECT COUNT(DISTINCT c.id)
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . $exclude_sql;
        if ($args) $total_sql = $wpdb->prepare($total_sql, ...$args);
        self::clear_last_error();
        $total_count = $wpdb->get_var($total_sql);
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'RECENT_ASSIGNMENT_TOTAL_QUERY_FAILED', ['value' => null]);
        $sql = $wpdb->prepare(
            "SELECT a.id,a.customer_id,a.agent_user_id,a.assigned_by,a.assigned_at,c.display_name,c.source_customer_id,c.customer_code,u.display_name agent_name,byu.display_name assigned_by_name,
                (SELECT COALESCE(NULLIF(li.outcome,''),NULLIF(li.outcome_key,''),'') FROM $interactions li WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact_outcome,
                (SELECT cu.display_name FROM $interactions li LEFT JOIN {$wpdb->users} cu ON cu.ID=li.agent_user_id WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact_agent
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id LEFT JOIN {$wpdb->users} byu ON byu.ID=a.assigned_by
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . $exclude_sql . "
            ORDER BY a.assigned_at DESC,a.id DESC LIMIT %d",
            ...array_merge($args, [$limit])
        );
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'RECENT_ASSIGNMENT_QUERY_FAILED', ['value' => null]);
        foreach ($rows as &$row) {
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
            $row['semantic'] = 'NEWEST_ACTIVE_NORMAL_CANONICAL';
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'total_count' => (int)$total_count, 'returned_count' => count($rows)], 'reporting_assignment_service', null, ['value' => (int)$total_count]);
    }

    public static function management_guarded_assignment_rows(int $limit = 30, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_guarded_assignment_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $limit = max(1, min(100, $limit));

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        if (!class_exists('Biawin_CRM_Production_Readiness')) {
            return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::GUARDED, null, 'reporting_assignment_service', 'GUARD_CLASS_UNAVAILABLE', ['value' => null]);
        }
        $guard_table = Biawin_CRM_Production_Readiness::table();
        foreach ([$assignments, $customers, $guard_table] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::GUARDED, null, 'reporting_assignment_service', 'GUARDED_ASSIGNMENT_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        $total_sql = "SELECT COUNT(DISTINCT c.id)
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id INNER JOIN $guard_table q ON q.customer_id=c.id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a');
        self::clear_last_error();
        $total_count = $wpdb->get_var($total_sql);
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::GUARDED, null, 'reporting_assignment_service', 'GUARDED_ASSIGNMENT_TOTAL_QUERY_FAILED', ['value' => null]);

        $sql = $wpdb->prepare(
            "SELECT a.id,a.customer_id,a.agent_user_id,a.assigned_by,a.assigned_at,c.display_name,c.source_customer_id,c.customer_code,u.display_name agent_name
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id INNER JOIN $guard_table q ON q.customer_id=c.id
            LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . "
            ORDER BY a.assigned_at DESC,a.id DESC LIMIT %d",
            $limit
        );
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::GUARDED, null, 'reporting_assignment_service', 'GUARDED_ASSIGNMENT_QUERY_FAILED', ['value' => null]);
        foreach ($rows as &$row) {
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
            $row['semantic'] = 'GUARDED_NEWEST_ACTIVE';
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::GUARDED, ['rows' => $rows, 'total_count' => (int)$total_count, 'returned_count' => count($rows)], 'reporting_assignment_service', null, ['value' => (int)$total_count]);
    }

    public static function management_assignment_filter_summary(array $filters = [], array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'management_assignment_filter_summary');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_assignment_filter_summary');
        if (!$ctx['trusted']) return $ctx;
        $normalized = self::normalize_assignment_filters($filters);
        if (!$normalized['trusted']) return $normalized;

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        $interactions = Biawin_CRM_DB::table('interactions');
        foreach ([$assignments, $customers, $invoices, $interactions] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'FILTER_SUMMARY_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        [$filter_sql, $args] = self::assignment_filter_sql($normalized['data']['filters'], $ctx, 'c');
        $total_sql = "SELECT COUNT(DISTINCT c.id) FROM $customers c WHERE 1=1$filter_sql";
        $active_sql = "SELECT COUNT(DISTINCT c.id)
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . $filter_sql;
        $unassigned_sql = "SELECT COUNT(DISTINCT c.id)
            FROM $customers c
            WHERE 1=1$filter_sql AND NOT EXISTS (SELECT 1 FROM $assignments aa WHERE aa.customer_id=c.id AND " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('aa') . ")";
        self::clear_last_error();
        $total = $wpdb->get_var($args ? $wpdb->prepare($total_sql, ...$args) : $total_sql);
        $active = $wpdb->get_var($args ? $wpdb->prepare($active_sql, ...$args) : $active_sql);
        $unassigned = $wpdb->get_var($args ? $wpdb->prepare($unassigned_sql, ...$args) : $unassigned_sql);
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'FILTER_SUMMARY_QUERY_FAILED', ['value' => null]);

        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, [
            'total_count' => (int)$total,
            'workable_count' => (int)$active,
            'unassigned_count' => (int)$unassigned,
            'filters' => $normalized['data']['filters'],
            'semantic' => 'FILTERED_NEWEST_ACTIVE_NORMAL_CANONICAL',
        ], 'reporting_assignment_service', null, ['value' => (int)$total]);
    }

    public static function management_assignment_candidate_rows(array $filters = [], array $scope = [], int $limit = 500, int $offset = 0): array
    {
        $scope_check = self::assert_scope($scope, 'management_assignment_candidate_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('management_assignment_candidate_rows');
        if (!$ctx['trusted']) return $ctx;
        $summary = self::management_assignment_filter_summary($filters, $scope);
        if (!$summary['trusted']) return $summary;
        $normalized = self::normalize_assignment_filters($filters);
        if (!$normalized['trusted']) return $normalized;
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        $phones = Biawin_CRM_DB::table('phones');
        $invoices = Biawin_CRM_DB::table('invoices');
        $interactions = Biawin_CRM_DB::table('interactions');
        foreach ([$assignments, $customers, $phones, $invoices, $interactions] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'FILTER_ROWS_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }

        [$filter_sql, $args] = self::assignment_filter_sql($normalized['data']['filters'], $ctx, 'c');
        $sql = $wpdb->prepare(
            "SELECT c.*,
                (SELECT normalized_phone FROM $phones p WHERE p.customer_id=c.id ORDER BY p.is_primary DESC,p.id LIMIT 1) phone,
                (SELECT COUNT(*) FROM $invoices ic WHERE ic.customer_id=c.id) invoice_count,
                (SELECT u.display_name FROM $assignments a LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id WHERE a.customer_id=c.id AND " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . " LIMIT 1) agent_name,
                (SELECT COALESCE(NULLIF(li.outcome,''),NULLIF(li.outcome_key,''),'') FROM $interactions li WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact_outcome,
                (SELECT u.display_name FROM $interactions li LEFT JOIN {$wpdb->users} u ON u.ID=li.agent_user_id WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact_agent
            FROM $customers c
            WHERE 1=1$filter_sql
            ORDER BY c.total_paid DESC,c.id ASC LIMIT %d OFFSET %d",
            ...array_merge($args, [$limit, $offset])
        );
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'FILTER_ROWS_QUERY_FAILED', ['value' => null]);
        foreach ($rows as &$row) {
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
            $row['semantic'] = 'FILTERED_NORMAL_CANONICAL_CANDIDATE_ROW';
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, [
            'rows' => $rows,
            'total_count' => (int)$summary['data']['total_count'],
            'workable_count' => (int)$summary['data']['workable_count'],
            'unassigned_count' => (int)$summary['data']['unassigned_count'],
            'returned_count' => count($rows),
        ], 'reporting_assignment_service', null, ['value' => (int)$summary['data']['total_count']]);
    }

    public static function agent_dashboard_assignment_summary(int $agent_user_id, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'agent_dashboard_assignment_summary', ['agent_user_id' => $agent_user_id]);
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('agent_dashboard_assignment_summary');
        if (!$ctx['trusted']) return $ctx;

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        foreach ([$assignments, $customers] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_DASHBOARD_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }
        [$exclude_sql, $exclude_args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT c.id) total,
                COALESCE(SUM(c.profile_completeness='complete'),0) complete,
                COALESCE(SUM(c.profile_completeness='incomplete'),0) incomplete,
                COALESCE(SUM(c.journey_state='service_request'),0) service_request
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . " AND a.agent_user_id=%d$exclude_sql",
            ...array_merge([$agent_user_id], $exclude_args)
        );
        self::clear_last_error();
        $row = $wpdb->get_row($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($row)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_DASHBOARD_QUERY_FAILED', ['value' => null]);
        $data = [
            'total' => (int)($row['total'] ?? 0),
            'complete' => (int)($row['complete'] ?? 0),
            'incomplete' => (int)($row['incomplete'] ?? 0),
            'service_request' => (int)($row['service_request'] ?? 0),
            'semantic' => 'NEWEST_ACTIVE_NORMAL_CANONICAL',
        ];
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $data, 'reporting_assignment_service', null, ['value' => $data['total']]);
    }

    public static function agent_dashboard_assignment_rows(int $agent_user_id, array $scope = [], int $limit = 100, int $offset = 0): array
    {
        $scope_check = self::assert_scope($scope, 'agent_dashboard_assignment_rows', ['agent_user_id' => $agent_user_id]);
        if (!$scope_check['trusted']) return $scope_check;
        $ctx = self::normal_canonical_context('agent_dashboard_assignment_rows');
        if (!$ctx['trusted']) return $ctx;
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        foreach ([$assignments, $customers] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_DASHBOARD_ROWS_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }
        [$exclude_sql, $exclude_args] = self::excluded_where_clause($ctx['excluded_ids'], 'c', 'AND');
        $total_sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT c.id) FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . " AND a.agent_user_id=%d$exclude_sql",
            ...array_merge([$agent_user_id], $exclude_args)
        );
        self::clear_last_error();
        $total_count = $wpdb->get_var($total_sql);
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_DASHBOARD_ROWS_TOTAL_QUERY_FAILED', ['value' => null]);
        $sql = $wpdb->prepare(
            "SELECT c.id,c.source_customer_id,c.customer_code,c.display_name,c.profile_completeness,c.journey_state,a.id assignment_id,a.assigned_at
            FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id
            WHERE " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . " AND a.agent_user_id=%d$exclude_sql
            ORDER BY a.assigned_at DESC,a.id DESC LIMIT %d OFFSET %d",
            ...array_merge([$agent_user_id], $exclude_args, [$limit, $offset])
        );
        self::clear_last_error();
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, null, 'reporting_assignment_service', 'AGENT_DASHBOARD_ROWS_QUERY_FAILED', ['value' => null]);
        foreach ($rows as &$row) {
            $row['trusted'] = true;
            $row['status'] = self::AVAILABLE;
            $row['semantic'] = 'AGENT_NEWEST_ACTIVE_NORMAL_CANONICAL';
        }
        unset($row);
        return self::structured(self::AVAILABLE, true, Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, ['rows' => $rows, 'total_count' => (int)$total_count, 'returned_count' => count($rows)], 'reporting_assignment_service', null, ['value' => (int)$total_count]);
    }

    public static function agent_workload(int $agent_user_id, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'agent_workload', ['agent_user_id' => $agent_user_id]);
        if (!$scope_check['trusted']) return $scope_check;
        $rows = array_values(array_filter(Biawin_CRM_Reporting_Assignment_Service::workload_by_agent(500), function ($row) use ($agent_user_id) {
            return (int)($row['agent_user_id'] ?? 0) === $agent_user_id;
        }));
        return self::structured(self::AVAILABLE, true, 'NORMAL_CANONICAL_WORKLOAD_OWNER', ['rows' => $rows, 'actor_warning' => 'assignment owner is not interaction/activity/audit actor'], 'reporting_assignment_service', null, ['value' => $rows[0]['active_customers'] ?? 0]);
    }

    public static function event_report(array $filters = [], array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'event_report');
        if (!$scope_check['trusted']) return $scope_check;
        $normalized = self::normalize_filters($filters, ['timestamp_semantic' => ['interaction_created_at', 'activity_created_at', 'audit_created_at']]);
        if (!$normalized['trusted']) return $normalized;
        global $wpdb;
        $interactions = Biawin_CRM_DB::table('interactions');
        $activities = Biawin_CRM_DB::table('activities');
        $audit = Biawin_CRM_DB::table('audit_log');
        foreach ([$interactions, $activities, $audit] as $table) {
            if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) {
                return self::structured(self::BLOCKED, false, 'EVENTS', null, 'operational_events_plus_audit_evidence', 'EVENT_TABLE_UNAVAILABLE', ['value' => null]);
            }
        }
        $operational_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $interactions") + (int)$wpdb->get_var("SELECT COUNT(*) FROM $activities");
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, 'EVENTS', null, 'operational_events_plus_audit_evidence', 'EVENT_QUERY_FAILED', ['value' => null]);
        $audit_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $audit");
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, 'EVENTS', null, 'operational_events_plus_audit_evidence', 'EVENT_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, 'EVENTS', [
            'operational_events' => [
                'sources' => ['interactions', 'activities'],
                'required_fields' => ['event_source', 'event_id', 'event_type', 'customer_id', 'actor_user_id', 'event_timestamp'],
            ],
            'audit_evidence' => [
                'source' => 'audit_log',
                'required_fields' => ['audit_id', 'action', 'customer_reference', 'actor_user_id', 'timestamp'],
            ],
            'operational_count' => $operational_count,
            'audit_evidence_count' => $audit_count,
            'combined_event_count_exposed' => false,
            'dedupe_metadata' => [
                'assignment_returned_policy' => 'activity is operational event; matching audit_log is diagnostic evidence only',
                'heuristic_is_non_destructive' => true,
            ],
        ], 'operational_events_plus_audit_evidence', null, ['value' => $operational_count]);
    }

    public static function case_population_summary(string $case_type, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'case_population_summary');
        if (!$scope_check['trusted']) return $scope_check;
        $case = self::case_contract($case_type);
        if (!$case) return self::structured(self::UNAVAILABLE, false, 'CASE', null, 'case_reporting_context', 'CASE_TYPE_UNSUPPORTED', ['value' => null]);
        global $wpdb;
        $table = Biawin_CRM_DB::table($case['table']);
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_TABLE_UNAVAILABLE', ['value' => null]);
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
        $orphan = self::case_orphan_summary($case_type, $scope);
        return self::structured($orphan['trusted'] ? self::AVAILABLE : self::DEGRADED, $orphan['trusted'], strtoupper($case_type), ['total' => $total, 'orphan' => $orphan['data'], 'amount_semantics' => $case['amounts']], 'case_reporting_context', $orphan['error'], ['value' => $orphan['trusted'] ? $total - (int)$orphan['value'] : null]);
    }

    public static function case_customer_validity(string $case_type, int $customer_id, array $scope = []): array
    {
        $scope_check = self::assert_scope($scope, 'case_customer_validity', ['customer_id' => $customer_id]);
        if (!$scope_check['trusted']) return $scope_check;
        $membership = self::customer_population_membership($customer_id, [self::ROLE_ADMIN]);
        if (!$membership['trusted']) {
            return self::structured(self::BLOCKED, false, strtoupper($case_type), ['classification' => 'UNKNOWN_DEPENDENCY_STATE'], 'case_reporting_context', $membership['error'], ['value' => null]);
        }
        $segments = $membership['data']['segments'];
        $class = 'ORPHAN_CUSTOMER_REFERENCE';
        if (!empty($segments[Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL])) $class = 'NORMAL_CANONICAL_CUSTOMER';
        elseif (!empty($segments[Biawin_CRM_Reporting_Population_Service::GUARDED])) $class = 'GUARDED_CUSTOMER';
        elseif (!empty($segments[Biawin_CRM_Reporting_Population_Service::BLACKLISTED_LIVE])) $class = 'BLACKLISTED_LIVE_CUSTOMER';
        return self::structured(self::AVAILABLE, true, strtoupper($case_type), ['customer_id' => $customer_id, 'classification' => $class], 'case_reporting_context', null, ['value' => $class]);
    }

    public static function case_rows(string $case_type, array $filters = [], array $scope = [], int $limit = 100, int $offset = 0): array
    {
        $scope_check = self::assert_scope($scope, 'case_rows');
        if (!$scope_check['trusted']) return $scope_check;
        $case = self::case_contract($case_type);
        if (!$case) return self::structured(self::UNAVAILABLE, false, 'CASE', null, 'case_reporting_context', 'CASE_TYPE_UNSUPPORTED', ['value' => null]);
        $normalized = self::normalize_filters($filters, ['timestamp_semantic' => ['case_created_at', 'case_completed_at']]);
        if (!$normalized['trusted']) return $normalized;
        global $wpdb;
        $table = Biawin_CRM_DB::table($case['table']);
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_TABLE_UNAVAILABLE', ['value' => null]);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", max(1, min(500, $limit)), max(0, $offset)), ARRAY_A);
        if (self::last_query_failed() || !is_array($rows)) return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, strtoupper($case_type), ['rows' => $rows], 'case_reporting_context', null, ['value' => count($rows)]);
    }

    public static function case_orphan_summary(string $case_type, array $scope = []): array
    {
        $case = self::case_contract($case_type);
        if (!$case) return self::structured(self::UNAVAILABLE, false, 'CASE', null, 'case_reporting_context', 'CASE_TYPE_UNSUPPORTED', ['value' => null]);
        global $wpdb;
        $table = Biawin_CRM_DB::table($case['table']);
        $customers = Biawin_CRM_DB::table('customers');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($table) || !Biawin_CRM_Reporting_Population_Service::table_exists($customers)) {
            return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_DEPENDENCY_UNAVAILABLE', ['value' => null]);
        }
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table x LEFT JOIN $customers c ON c.id=x.customer_id WHERE c.id IS NULL");
        if (self::last_query_failed()) return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_QUERY_FAILED', ['value' => null]);
        return self::structured(self::AVAILABLE, true, strtoupper($case_type), ['orphan_refs' => $count, 'canonical_customer_case_metrics_exclude_orphans' => true], 'case_reporting_context', null, ['value' => $count]);
    }

    public static function case_guard_summary(string $case_type, array $scope = []): array
    {
        $case = self::case_contract($case_type);
        if (!$case) return self::structured(self::UNAVAILABLE, false, 'CASE', null, 'case_reporting_context', 'CASE_TYPE_UNSUPPORTED', ['value' => null]);
        $guarded = self::customer_population_ids(Biawin_CRM_Reporting_Population_Service::GUARDED, $scope);
        if (!$guarded['trusted']) return $guarded;
        global $wpdb;
        $table = Biawin_CRM_DB::table($case['table']);
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($table)) return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_TABLE_UNAVAILABLE', ['value' => null]);
        $ids = self::normalize_ids($guarded['data']['ids']);
        if (!$ids) return self::structured(self::AVAILABLE, true, strtoupper($case_type), ['guarded_case_refs' => 0], 'case_reporting_context', null, ['value' => 0]);
        $total = 0;
        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $total += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE customer_id IN ($ph)", ...$chunk));
            if (self::last_query_failed()) return self::structured(self::BLOCKED, false, strtoupper($case_type), null, 'case_reporting_context', 'CASE_QUERY_FAILED', ['value' => null]);
        }
        return self::structured(self::AVAILABLE, true, strtoupper($case_type), ['guarded_case_refs' => $total], 'case_reporting_context', null, ['value' => $total]);
    }

    public static function normalize_export_row(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) $out[$key] = self::sanitize_export_cell($value);
        return $out;
    }

    public static function sanitize_export_cell($value)
    {
        if (!is_string($value) || $value === '') return $value;
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }

    public static function normalize_filters(array $filters, array $contract = []): array
    {
        $allowed = ['date_from', 'date_to', 'timestamp_semantic', 'agent_user_id', 'manager_user_id', 'status', 'case_type', 'journey_state', 'customer_id', 'service_id', 'project_id'];
        foreach ($filters as $key => $value) {
            if (!in_array((string)$key, $allowed, true)) {
                return self::structured(self::BLOCKED, false, 'FILTERS', null, 'filter_contract', 'UNKNOWN_FILTER_' . strtoupper((string)$key), ['value' => null]);
            }
        }
        if ((isset($filters['date_from']) || isset($filters['date_to'])) && empty($filters['timestamp_semantic'])) {
            return self::structured(self::SEMANTICALLY_UNRESOLVED, false, 'FILTERS', null, 'filter_contract', 'TIMESTAMP_SEMANTIC_REQUIRED', ['value' => null]);
        }
        if (!empty($filters['timestamp_semantic']) && !empty($contract['timestamp_semantic']) && !in_array($filters['timestamp_semantic'], $contract['timestamp_semantic'], true)) {
            return self::structured(self::SEMANTICALLY_UNRESOLVED, false, 'FILTERS', null, 'filter_contract', 'TIMESTAMP_SEMANTIC_UNSUPPORTED', ['value' => null]);
        }
        return self::structured(self::AVAILABLE, true, 'FILTERS', ['filters' => $filters], 'filter_contract');
    }

    public static function assert_scope(array $scope, string $operation, array $object = []): array
    {
        $role = self::scope_role($scope);
        $broad = [
            'population_summary',
            'customer_population_ids',
            'customer_population_count',
            'financial_snapshot_for_customers',
            'financial_total_for_population',
            'management_actual_paid_total',
            'management_report_kpi_rows',
            'management_satisfaction_summary',
            'management_contact_status_rows',
            'management_route_summary_rows',
            'management_route_detail_rows',
            'management_service_case_summary_rows',
            'management_source_service_summary_rows',
            'management_customer_preview_rows',
            'assignment_workload_summary',
            'newest_active_assignments_for_customers',
            'workable_active_workload',
            'guarded_active_workload',
            'unassigned_normal_canonical',
            'management_workload_cards',
            'management_agent_workload_rows',
            'management_recent_assignment_rows',
            'management_guarded_assignment_rows',
            'management_assignment_filter_summary',
            'management_assignment_candidate_rows',
            'event_report',
            'case_population_summary',
            'case_rows',
        ];
        $domain_allowed = [
            self::ROLE_ADMIN => true,
            self::ROLE_AUDITOR => true,
            self::ROLE_MANAGER => true,
            self::ROLE_FINANCE => in_array($operation, ['customer_financial_snapshot', 'financial_snapshot_for_customers', 'financial_total_for_population', 'management_actual_paid_total', 'approved_paid_total'], true),
            self::ROLE_DEFICIENCY => in_array($operation, ['case_population_summary', 'case_customer_validity', 'case_rows', 'case_orphan_summary', 'case_guard_summary'], true),
            self::ROLE_SERVICE => in_array($operation, ['case_population_summary', 'case_customer_validity', 'case_rows', 'case_orphan_summary', 'case_guard_summary'], true),
        ];
        if ($role === self::ROLE_AGENT) {
            if (in_array($operation, $broad, true)) {
                return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_FORBIDS_MANAGER_WIDE_DATA', ['value' => null]);
            }
            if (isset($object['agent_user_id']) && (int)($scope['agent_user_id'] ?? 0) !== (int)$object['agent_user_id']) {
                return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_FORBIDS_AGENT', ['value' => null]);
            }
            if (isset($object['customer_id']) && !self::agent_can_access_customer($scope, (int)$object['customer_id'])) {
                return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_FORBIDS_CUSTOMER', ['value' => null]);
            }
        } elseif (empty($domain_allowed[$role])) {
            return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_FORBIDS_OPERATION', ['value' => null]);
        }
        $manager_scope_required = [
            'management_workload_cards',
            'management_agent_workload_rows',
            'management_recent_assignment_rows',
            'management_guarded_assignment_rows',
            'management_assignment_filter_summary',
            'management_assignment_candidate_rows',
        ];
        if ($role === self::ROLE_MANAGER && in_array($operation, $manager_scope_required, true)) {
            if (empty($scope['scope_proven']) || empty($scope['requesting_user_id'])) {
                return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_UNPROVEN_MANAGER_AGGREGATE', ['value' => null]);
            }
            $requested_department = isset($scope['requested_department_key']) ? (string)$scope['requested_department_key'] : '';
            if ($requested_department !== '') {
                $allowed_departments = array_map('strval', (array)($scope['permitted_department_keys'] ?? []));
                if (!$allowed_departments || !in_array($requested_department, $allowed_departments, true)) {
                    return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_FORBIDS_DEPARTMENT', ['value' => null]);
                }
            }
            $requested_organization = isset($scope['requested_organization_key']) ? (string)$scope['requested_organization_key'] : '';
            if ($requested_organization !== '') {
                $allowed_organizations = array_map('strval', (array)($scope['permitted_organization_keys'] ?? []));
                if (!$allowed_organizations || !in_array($requested_organization, $allowed_organizations, true)) {
                    return self::structured(self::BLOCKED, false, 'SCOPE', null, 'existing_capability_scope', 'SCOPE_FORBIDS_ORGANIZATION', ['value' => null]);
                }
            }
        }
        return self::structured(self::AVAILABLE, true, 'SCOPE', ['role' => $role, 'operation' => $operation], 'existing_capability_scope');
    }

    private static function population_source(string $population, int $limit = 0, int $offset = 0): array
    {
        switch ($population) {
            case Biawin_CRM_Reporting_Population_Service::ALL_LIVE:
                return Biawin_CRM_Reporting_Population_Service::all_live_source();
            case Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL:
                return Biawin_CRM_Reporting_Population_Service::normal_canonical_source($limit, $offset);
            case Biawin_CRM_Reporting_Population_Service::GUARDED:
            case Biawin_CRM_Reporting_Population_Service::UNRESOLVED:
                return Biawin_CRM_Reporting_Population_Service::guard_source();
            case Biawin_CRM_Reporting_Population_Service::BLACKLISTED_LIVE:
                return Biawin_CRM_Reporting_Population_Service::blacklist_live_source();
            case Biawin_CRM_Reporting_Population_Service::NORMAL_PLUS_VISIBLE_QUARANTINE:
                $normal = Biawin_CRM_Reporting_Population_Service::normal_canonical_source($limit, $offset);
                $guard = Biawin_CRM_Reporting_Population_Service::guard_source();
                $blacklist = Biawin_CRM_Reporting_Population_Service::blacklist_live_source();
                if (!$normal['available'] || !$guard['available'] || !$blacklist['available']) {
                    return Biawin_CRM_Reporting_Population_Service::source_result(false, 'BLOCKED', [], 'SEGMENT_DEPENDENCY_UNAVAILABLE');
                }
                $unresolved = $guard;
                $visible_ids = array_values(array_unique(array_merge($normal['ids'], $guard['ids'], $blacklist['ids'])));
                sort($visible_ids, SORT_NUMERIC);
                return Biawin_CRM_Reporting_Population_Service::source_result(true, 'AVAILABLE', $visible_ids, null, count($visible_ids), [
                    'composite_population_only' => true,
                    'aggregate_visible_count_deduped' => count($visible_ids),
                    'overlap_policy' => 'segment membership may overlap; aggregate visible ids are deduplicated and canonical exclusion occurs once',
                    'segments' => [
                        'normal_canonical' => ['count' => $normal['count'], 'ids' => $normal['ids']],
                        'guarded' => ['count' => $guard['count'], 'ids' => $guard['ids']],
                        'blacklisted_live' => ['count' => $blacklist['count'], 'ids' => $blacklist['ids']],
                        'unresolved' => ['count' => $unresolved['count'], 'ids' => $unresolved['ids']],
                    ],
                ]);
        }
        return Biawin_CRM_Reporting_Population_Service::source_result(false, 'UNAVAILABLE', [], 'POPULATION_UNSUPPORTED');
    }

    private static function normalize_assignment_filters(array $filters): array
    {
        $allowed = ['cohort', 'min_paid', 'max_paid', 'min_invoices', 'max_invoices', 'contact_outcome', 'contact_outcome_label', 'refund_tag'];
        foreach ($filters as $key => $value) {
            if (!in_array((string)$key, $allowed, true)) {
                return self::structured(self::BLOCKED, false, 'FILTERS', null, 'filter_contract', 'UNKNOWN_ASSIGNMENT_FILTER_' . strtoupper((string)$key), ['value' => null]);
            }
        }
        $cohort = sanitize_key((string)($filters['cohort'] ?? 'normal'));
        if (!in_array($cohort, ['normal', '1404', 'all'], true)) $cohort = 'normal';
        $normalized = [
            'cohort' => $cohort,
            'min_paid' => max(0, (float)($filters['min_paid'] ?? 0)),
            'max_paid' => max(0, (float)($filters['max_paid'] ?? 0)),
            'min_invoices' => max(0, (int)($filters['min_invoices'] ?? 0)),
            'max_invoices' => max(0, (int)($filters['max_invoices'] ?? 0)),
            'contact_outcome' => sanitize_key((string)($filters['contact_outcome'] ?? '')),
            'contact_outcome_label' => sanitize_text_field((string)($filters['contact_outcome_label'] ?? '')),
            'refund_tag' => in_array(($filters['refund_tag'] ?? ''), ['any', 'REFUND_REPORTED', 'REFUND_CONFIRMED', 'BOTH'], true) ? (string)$filters['refund_tag'] : 'any',
        ];
        return self::structured(self::AVAILABLE, true, 'FILTERS', ['filters' => $normalized], 'filter_contract');
    }

    private static function assignment_filter_sql(array $filters, array $ctx, string $alias = 'c'): array
    {
        global $wpdb;
        $invoices = Biawin_CRM_DB::table('invoices');
        $interactions = Biawin_CRM_DB::table('interactions');
        [$exclude_sql, $args] = self::excluded_where_clause($ctx['excluded_ids'], $alias, 'AND');
        $where = $exclude_sql;
        if (($filters['cohort'] ?? 'normal') === '1404') {
            $where .= " AND $alias.assignment_cohort='1404'";
        } elseif (($filters['cohort'] ?? 'normal') === 'normal') {
            $where .= " AND ($alias.assignment_cohort<>'1404' OR $alias.assignment_cohort IS NULL)";
        }
        if ((float)($filters['min_paid'] ?? 0) > 0) {
            $where .= " AND $alias.total_paid >= %f";
            $args[] = (float)$filters['min_paid'];
        }
        if ((float)($filters['max_paid'] ?? 0) > 0) {
            $where .= " AND $alias.total_paid <= %f";
            $args[] = (float)$filters['max_paid'];
        }
        if ((int)($filters['min_invoices'] ?? 0) > 0) {
            $where .= " AND (SELECT COUNT(*) FROM $invoices fi WHERE fi.customer_id=$alias.id) >= %d";
            $args[] = (int)$filters['min_invoices'];
        }
        if ((int)($filters['max_invoices'] ?? 0) > 0) {
            $where .= " AND (SELECT COUNT(*) FROM $invoices fi WHERE fi.customer_id=$alias.id) <= %d";
            $args[] = (int)$filters['max_invoices'];
        }
        $contact = (string)($filters['contact_outcome'] ?? '');
        if ($contact === '__none__') {
            $where .= " AND NOT EXISTS (SELECT 1 FROM $interactions ai WHERE ai.customer_id=$alias.id)";
        } elseif ($contact !== '') {
            $label = (string)($filters['contact_outcome_label'] ?? $contact);
            $where .= " AND (COALESCE((SELECT NULLIF(ai.outcome_key,'') FROM $interactions ai WHERE ai.customer_id=$alias.id ORDER BY ai.id DESC LIMIT 1),'')=%s OR (COALESCE((SELECT NULLIF(ai.outcome_key,'') FROM $interactions ai WHERE ai.customer_id=$alias.id ORDER BY ai.id DESC LIMIT 1),'')='' AND COALESCE((SELECT NULLIF(ai.outcome,'') FROM $interactions ai WHERE ai.customer_id=$alias.id ORDER BY ai.id DESC LIMIT 1),'')=%s))";
            $args[] = $contact;
            $args[] = $label;
        }
        // v0.8.62.0: فیلتر «فقط عودتی‌ها» بر اساس تگ‌های عودت اعلامی/قطعی.
        $refund_tag = (string)($filters['refund_tag'] ?? 'any');
        if (in_array($refund_tag, ['REFUND_REPORTED', 'REFUND_CONFIRMED'], true)) {
            $where .= " AND EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('customer_tags') . " rt WHERE rt.customer_id=$alias.id AND rt.tag=%s)";
            $args[] = $refund_tag;
        } elseif ($refund_tag === 'BOTH') {
            $where .= " AND EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('customer_tags') . " rt WHERE rt.customer_id=$alias.id AND rt.tag IN (%s,%s))";
            $args[] = 'REFUND_REPORTED';
            $args[] = 'REFUND_CONFIRMED';
        }
        return [$where, $args];
    }

    private static function normal_canonical_context(string $operation): array
    {
        $key = 'normal_canonical_context_' . $operation;
        if (isset(self::$memo[$key])) return self::$memo[$key];
        $all = Biawin_CRM_Reporting_Population_Service::all_live_source();
        $guard = Biawin_CRM_Reporting_Population_Service::guard_source();
        $blacklist = Biawin_CRM_Reporting_Population_Service::blacklist_live_source();
        $canonical = Biawin_CRM_Reporting_Population_Service::canonical_count_result($all, $guard, $blacklist);
        if (empty($canonical['trusted'])) {
            return self::$memo[$key] = self::structured(
                self::BLOCKED,
                false,
                Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL,
                ['operation' => $operation],
                'reporting_population_service',
                'CANONICAL_POPULATION_UNTRUSTED',
                ['value' => null, 'dependency_status' => $canonical['dependency_errors']]
            );
        }
        $excluded = self::normalize_ids(array_merge($guard['ids'], $blacklist['ids']));
        return self::$memo[$key] = [
            'status' => self::AVAILABLE,
            'trusted' => true,
            'population' => Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL,
            'source' => 'reporting_population_service',
            'error' => null,
            'excluded_ids' => $excluded,
            'data' => [
                'canonical_population_count' => (int)$canonical['count'],
                'excluded_union_count' => (int)$canonical['excluded_union_count'],
                'guarded_count' => (int)$guard['count'],
                'blacklisted_live_count' => (int)$blacklist['count'],
                'population_materialization' => 'excluded_ids_only',
            ],
            'value' => (int)$canonical['count'],
        ];
    }

    private static function excluded_where_clause(array $excluded_ids, string $alias = 'c', string $prefix = 'WHERE'): array
    {
        $ids = self::normalize_ids($excluded_ids);
        if (!$ids) return ['', []];
        $clause = ' ' . $prefix . ' ' . $alias . '.id NOT IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
        return [$clause, $ids];
    }

    private static function case_contract(string $case_type): ?array
    {
        $map = [
            'complaints' => ['table' => 'complaints', 'amounts' => []],
            'deficiency_cases' => ['table' => 'deficiency_cases', 'amounts' => ['reported_amount', 'registered_invoice_amount', 'difference_amount', 'final_approved_amount']],
            'financial_cases' => ['table' => 'financial_cases', 'amounts' => ['declared_amount', 'system_amount', 'difference_amount']],
            'service_cases' => ['table' => 'service_cases', 'amounts' => ['credit_amount_is_service_domain_not_revenue']],
            'refunds' => ['table' => 'refunds', 'amounts' => ['requested_amount', 'approved_amount_case_local']],
        ];
        return $map[$case_type] ?? null;
    }

    private static function structured(string $status, bool $trusted, string $population, $data, string $source, ?string $error = null, array $extra = []): array
    {
        return array_merge([
            'status' => $status,
            'trusted' => $trusted,
            'population' => $population,
            'freshness' => $extra['freshness'] ?? 'live_read',
            'source' => $source,
            'generated_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'data' => $data,
            'value' => array_key_exists('value', $extra) ? $extra['value'] : null,
            'error' => $error,
            'dependency_status' => $extra['dependency_status'] ?? null,
        ], array_diff_key($extra, ['value' => true, 'freshness' => true, 'dependency_status' => true]));
    }

    private static function scope_role(array $scope): string
    {
        if (isset($scope[0]) && is_string($scope[0])) return $scope[0];
        if (!empty($scope['role'])) return (string)$scope['role'];
        if (function_exists('current_user_can')) {
            if (current_user_can('manage_options')) return self::ROLE_ADMIN;
            if (current_user_can('bcrm_reports')) return self::ROLE_MANAGER;
        }
        return self::ROLE_ADMIN;
    }

    private static function agent_can_access_customer(array $scope, int $customer_id): bool
    {
        $allowed = $scope['allowed_customer_ids'] ?? [];
        return in_array($customer_id, self::normalize_ids((array)$allowed), true);
    }

    private static function normalize_ids(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) $out[$id] = $id;
        }
        ksort($out);
        return array_values($out);
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
}
