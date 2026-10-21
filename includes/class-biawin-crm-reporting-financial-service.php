<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.55.2 financial source-of-truth reporting helpers.
 *
 * Live actual paid means live invoices.amount_paid excluding excluded invoices.
 * Cached customer aggregates are compared, never repaired here.
 */
class Biawin_CRM_Reporting_Financial_Service
{
    public const ACTUAL_PAID_LIVE = 'ACTUAL_PAID_LIVE';
    public const DECLARED_PAID_LATEST_CUSTOMER_CLAIM = 'DECLARED_PAID_LATEST_CUSTOMER_CLAIM';
    public const APPROVED_PAID = 'UNKNOWN_NOT_DEFINED';

    public static function actual_paid_expression(string $invoice_alias = 'i'): string
    {
        return "COALESCE(SUM(CASE WHEN COALESCE($invoice_alias.exclude_from_total,0)=1 THEN 0 ELSE $invoice_alias.amount_paid END),0)";
    }

    public static function live_actual_paid_for_customer(int $customer_id): float
    {
        global $wpdb;
        $i = Biawin_CRM_DB::table('invoices');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($i)) return 0.0;
        self::clear_last_error();
        return (float)$wpdb->get_var($wpdb->prepare(
            'SELECT ' . self::actual_paid_expression('i') . " FROM $i i WHERE i.customer_id=%d",
            $customer_id
        ));
    }

    public static function latest_declared_paid_for_customer(int $customer_id): ?float
    {
        global $wpdb;
        $it = Biawin_CRM_DB::table('interactions');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($it)) return null;
        self::clear_last_error();
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT declared_paid FROM $it WHERE customer_id=%d AND declared_paid IS NOT NULL ORDER BY id DESC LIMIT 1",
            $customer_id
        ));
        return $value === null ? null : (float)$value;
    }

    public static function live_discrepancy_for_customer(int $customer_id): float
    {
        $declared = self::latest_declared_paid_for_customer($customer_id);
        return $declared === null ? 0.0 : $declared - self::live_actual_paid_for_customer($customer_id);
    }

    public static function freshness_summary(float $tolerance = 1000.0): array
    {
        global $wpdb;
        $c = Biawin_CRM_DB::table('customers');
        $i = Biawin_CRM_DB::table('invoices');
        $it = Biawin_CRM_DB::table('interactions');
        if (!Biawin_CRM_Reporting_Population_Service::table_exists($c)) {
            return self::untrusted_summary('TABLE_UNAVAILABLE', 'customers table unavailable');
        }

        $invoice_join = Biawin_CRM_Reporting_Population_Service::table_exists($i)
            ? "LEFT JOIN (SELECT customer_id," . self::actual_paid_expression('ii') . " live_total_paid FROM $i ii GROUP BY customer_id) inv ON inv.customer_id=c.id"
            : 'LEFT JOIN (SELECT NULL customer_id, 0 live_total_paid) inv ON 1=0';

        $latest_declared_join = Biawin_CRM_Reporting_Population_Service::table_exists($it)
            ? "LEFT JOIN (SELECT x.customer_id,x.declared_paid AS latest_declared_paid FROM $it x INNER JOIN (SELECT customer_id,MAX(id) AS max_id FROM $it WHERE declared_paid IS NOT NULL GROUP BY customer_id) m ON m.max_id=x.id) decl ON decl.customer_id=c.id"
            : 'LEFT JOIN (SELECT NULL AS customer_id, NULL AS latest_declared_paid) decl ON 1=0';

        $sql = "SELECT
            COUNT(*) AS customers_checked,
            SUM(CASE WHEN ABS(COALESCE(c.total_paid,0)-COALESCE(inv.live_total_paid,0))<=%f THEN 1 ELSE 0 END) AS total_paid_matched,
            SUM(CASE WHEN ABS(COALESCE(c.total_paid,0)-COALESCE(inv.live_total_paid,0))>%f THEN 1 ELSE 0 END) AS total_paid_mismatched,
            SUM(CASE WHEN ((c.declared_paid IS NULL AND decl.latest_declared_paid IS NULL) OR ABS(COALESCE(c.declared_paid,0)-COALESCE(decl.latest_declared_paid,0))<=%f) THEN 1 ELSE 0 END) AS declared_paid_matched,
            SUM(CASE WHEN NOT ((c.declared_paid IS NULL AND decl.latest_declared_paid IS NULL) OR ABS(COALESCE(c.declared_paid,0)-COALESCE(decl.latest_declared_paid,0))<=%f) THEN 1 ELSE 0 END) AS declared_paid_mismatched,
            SUM(CASE WHEN ABS(COALESCE(c.discrepancy_amount,0)-CASE WHEN decl.latest_declared_paid IS NULL THEN 0 ELSE COALESCE(decl.latest_declared_paid,0)-COALESCE(inv.live_total_paid,0) END)<=%f THEN 1 ELSE 0 END) AS discrepancy_matched,
            SUM(CASE WHEN ABS(COALESCE(c.discrepancy_amount,0)-CASE WHEN decl.latest_declared_paid IS NULL THEN 0 ELSE COALESCE(decl.latest_declared_paid,0)-COALESCE(inv.live_total_paid,0) END)>%f THEN 1 ELSE 0 END) AS discrepancy_mismatched
            FROM $c c $invoice_join $latest_declared_join";
        self::clear_last_error();
        $row = $wpdb->get_row($wpdb->prepare($sql, $tolerance, $tolerance, $tolerance, $tolerance, $tolerance, $tolerance), ARRAY_A);
        if (self::last_query_failed() || !is_array($row)) {
            return self::untrusted_summary('QUERY_FAILED', self::last_error());
        }

        return [
            'status' => 'AVAILABLE',
            'trusted' => true,
            'error' => null,
            'customers_checked' => (int)($row['customers_checked'] ?? 0),
            'total_paid_matched' => (int)($row['total_paid_matched'] ?? 0),
            'total_paid_mismatched' => (int)($row['total_paid_mismatched'] ?? 0),
            'declared_paid_matched' => (int)($row['declared_paid_matched'] ?? 0),
            'declared_paid_mismatched' => (int)($row['declared_paid_mismatched'] ?? 0),
            'discrepancy_matched' => (int)($row['discrepancy_matched'] ?? 0),
            'discrepancy_mismatched' => (int)($row['discrepancy_mismatched'] ?? 0),
        ];
    }

    public static function untrusted_summary(string $status, string $error): array
    {
        return [
            'status' => $status,
            'trusted' => false,
            'error' => self::sanitize_error($error),
            'customers_checked' => null,
            'total_paid_matched' => null,
            'total_paid_mismatched' => null,
            'declared_paid_matched' => null,
            'declared_paid_mismatched' => null,
            'discrepancy_matched' => null,
            'discrepancy_mismatched' => null,
        ];
    }

    public static function semantics(): array
    {
        return [
            self::ACTUAL_PAID_LIVE => 'SUM live invoices.amount_paid excluding exclude_from_total=1',
            self::DECLARED_PAID_LATEST_CUSTOMER_CLAIM => 'latest non-null interactions.declared_paid by interaction id',
            self::APPROVED_PAID => 'No authoritative approved-paid source is defined in v0.8.55.2',
            'cached_total_paid' => 'customers.total_paid remains denormalized/cache only',
            'cached_discrepancy_amount' => 'customers.discrepancy_amount remains denormalized/cache only',
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
