<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.55.1 assignment reporting source-of-truth.
 *
 * Current workload is the newest active assignment per customer.
 */
class Biawin_CRM_Reporting_Assignment_Service
{
    public static function newest_active_assignment_condition(string $alias = 'a'): string
    {
        $a = Biawin_CRM_DB::table('assignments');
        return "$alias.status='assigned' AND $alias.id=(SELECT MAX(ax.id) FROM $a ax WHERE ax.customer_id=$alias.customer_id AND ax.status='assigned')";
    }

    public static function summary(?array $population = null): array
    {
        global $wpdb;
        $population = $population ?: Biawin_CRM_Reporting_Population_Service::summary();
        $a = Biawin_CRM_DB::table('assignments');
        $c = Biawin_CRM_DB::table('customers');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($a)) {
            return self::empty_summary('TABLE_UNAVAILABLE');
        }

        $canonical_active = (int)$wpdb->get_var("SELECT COUNT(*) FROM $a a WHERE " . self::newest_active_assignment_condition('a'));
        $duplicates = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT customer_id FROM $a WHERE status='assigned' GROUP BY customer_id HAVING COUNT(*)>1) x");
        $guarded = self::guarded_active_workload($population);
        $workable = $guarded['trusted'] ? max(0, $canonical_active - (int)$guarded['count']) : null;
        $unassigned_all = Biawin_CRM_Reporting_Population_Service::table_exists($c)
            ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $c c WHERE NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=c.id AND aa.status='assigned')")
            : null;
        $unassigned_canonical = self::unassigned_normal_canonical_customers($population);

        return [
            'canonical_active_customers' => $canonical_active,
            'duplicate_active_assignment_customers' => $duplicates,
            'guarded_active_workload' => $guarded,
            'guarded_active_assignments' => $guarded['count'],
            'workable_active_workload' => [
                'count' => $workable,
                'trusted' => $guarded['trusted'],
                'status' => $guarded['trusted'] ? 'AVAILABLE' : 'BLOCKED',
                'description' => 'newest active assignments minus live Guard workload',
            ],
            'workable_active_assignments' => $workable,
            'unassigned_all_live_customers' => $unassigned_all,
            'unassigned_normal_canonical_customers' => $unassigned_canonical,
            'semantics' => 'NEWEST ACTIVE ASSIGNMENT PER CUSTOMER',
        ];
    }

    public static function unassigned_normal_canonical_customers(?array $population = null): array
    {
        global $wpdb;
        $population = $population ?: Biawin_CRM_Reporting_Population_Service::summary();
        $metric = $population['counts'][Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL] ?? null;
        if (!is_array($metric) || empty($metric['trusted'])) {
            return [
                'count' => null,
                'trusted' => false,
                'status' => 'BLOCKED',
                'dependency_errors' => is_array($metric) ? (array)($metric['dependency_errors'] ?? []) : ['NORMAL_CANONICAL_UNAVAILABLE'],
                'description' => 'blocked because NORMAL_CANONICAL is not trusted',
            ];
        }
        $source = Biawin_CRM_Reporting_Population_Service::normal_canonical_source();
        if (!$source['available']) {
            return [
                'count' => null,
                'trusted' => false,
                'status' => 'BLOCKED',
                'dependency_errors' => [$source['status']],
                'description' => 'blocked because NORMAL_CANONICAL source IDs are unavailable',
            ];
        }
        $a = Biawin_CRM_DB::table('assignments');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($a)) {
            return [
                'count' => null,
                'trusted' => false,
                'status' => 'TABLE_UNAVAILABLE',
                'dependency_errors' => ['ASSIGNMENTS_TABLE_UNAVAILABLE'],
                'description' => 'assignment table unavailable',
            ];
        }
        $ids = array_map('intval', $source['ids']);
        if (!$ids) {
            return [
                'count' => 0,
                'trusted' => true,
                'status' => 'AVAILABLE',
                'dependency_errors' => [],
                'description' => 'trusted empty NORMAL_CANONICAL population',
            ];
        }
        $total = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $customers = Biawin_CRM_DB::table('customers');
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM (SELECT x.id FROM $customers x WHERE x.id IN ($ph) AND NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=x.id AND aa.status='assigned')) u", ...$chunk);
            $total += (int)$wpdb->get_var($sql);
        }
        return [
            'count' => $total,
            'trusted' => true,
            'status' => 'AVAILABLE',
            'dependency_errors' => [],
            'description' => 'unassigned customers inside trusted NORMAL_CANONICAL only',
        ];
    }

    public static function workload_by_agent(int $limit = 200): array
    {
        global $wpdb;
        $a = Biawin_CRM_DB::table('assignments');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($a)) return [];
        $sql = "SELECT a.agent_user_id,COALESCE(u.display_name,CONCAT('User #',a.agent_user_id)) agent_name,COUNT(*) active_customers
            FROM $a a LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id
            WHERE " . self::newest_active_assignment_condition('a') . "
            GROUP BY a.agent_user_id,u.display_name
            ORDER BY active_customers DESC LIMIT %d";
        return (array)$wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);
    }

    private static function guarded_active_workload(array $population): array
    {
        global $wpdb;
        $guard = $population['sources']['guard'] ?? null;
        if (!is_array($guard) || empty($guard['trusted'])) {
            return [
                'count' => null,
                'trusted' => false,
                'status' => 'BLOCKED',
                'description' => 'Guard source unavailable',
            ];
        }
        if (class_exists('Biawin_CRM_Production_Readiness')) {
            $a = Biawin_CRM_DB::table('assignments');
            $q = Biawin_CRM_Production_Readiness::table();
            if (Biawin_CRM_Reporting_Population_Service::table_exists($q)) {
                return [
                    'count' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $a a INNER JOIN $q q ON q.customer_id=a.customer_id WHERE " . self::newest_active_assignment_condition('a')),
                    'trusted' => true,
                    'status' => 'AVAILABLE',
                    'description' => 'newest active assignments currently in Guard',
                ];
            }
        }
        return [
            'count' => null,
            'trusted' => false,
            'status' => 'BLOCKED',
            'description' => 'Guard source unavailable',
        ];
    }

    private static function empty_summary(string $status): array
    {
        return [
            'canonical_active_customers' => null,
            'duplicate_active_assignment_customers' => null,
            'guarded_active_workload' => ['count'=>null,'trusted'=>false,'status'=>$status],
            'guarded_active_assignments' => null,
            'workable_active_workload' => ['count'=>null,'trusted'=>false,'status'=>$status],
            'workable_active_assignments' => null,
            'unassigned_all_live_customers' => null,
            'unassigned_normal_canonical_customers' => ['count'=>null,'trusted'=>false,'status'=>$status],
            'semantics' => 'NEWEST ACTIVE ASSIGNMENT PER CUSTOMER',
        ];
    }
}
