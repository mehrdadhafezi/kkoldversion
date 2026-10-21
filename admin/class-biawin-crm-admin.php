<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_bcrm_import_dry_run', [__CLASS__, 'handle_dry_run']);
        add_action('admin_post_bcrm_import_commit', [__CLASS__, 'handle_commit']);
        add_action('admin_post_bcrm_save_interaction', [__CLASS__, 'handle_interaction']);
        add_action('admin_post_bcrm_assign_customers', [__CLASS__, 'handle_assignment']);
        add_action('admin_post_bcrm_assign_customers_excel', [__CLASS__, 'handle_assign_customers_excel']); // v0.8.62.16
        add_action('admin_post_bcrm_save_sales_manager_mapping', [__CLASS__, 'handle_save_sales_manager_mapping']);
        add_action('admin_post_bcrm_return_assignments', [__CLASS__, 'handle_return_assignments']);
        add_action('admin_post_bcrm_deficiency_action', [__CLASS__, 'handle_deficiency_action']);
        add_action('admin_post_bcrm_deficiency_assign', [__CLASS__, 'handle_deficiency_assign']);
        add_action('admin_post_bcrm_deficiency_delete_receipt', [__CLASS__, 'handle_deficiency_delete_receipt']); // v0.8.65.12
        add_action('admin_post_bcrm_deficiency_assign_upload', [__CLASS__, 'handle_deficiency_assign_upload']); // v0.8.62.15
        add_action('admin_post_bcrm_deficiency_export', [__CLASS__, 'handle_deficiency_export']);
        add_action('admin_post_bcrm_deficiency_export_excel', [__CLASS__, 'handle_deficiency_export_excel']); // v0.8.62.17
        add_action('admin_post_bcrm_save_settings', [__CLASS__, 'handle_settings']);
        add_action('admin_post_bcrm_save_service', [__CLASS__, 'handle_save_service']);
        add_action('admin_post_bcrm_update_service', [__CLASS__, 'handle_update_service']);
        add_action('admin_post_bcrm_assign_service_case', [__CLASS__, 'handle_assign_service_case']);
        add_action('admin_post_bcrm_refund_register_upload', [__CLASS__, 'handle_refund_register_upload']);
        add_action('admin_post_bcrm_refund_used_upload', [__CLASS__, 'handle_refund_used_upload']);
        add_action('admin_post_bcrm_refund_purge', [__CLASS__, 'handle_refund_purge']);
        add_action('admin_post_bcrm_financial_case', [__CLASS__, 'handle_financial_case']);
        add_action('admin_post_bcrm_refund_case', [__CLASS__, 'handle_refund_case']);
        add_action('admin_post_bcrm_save_portal_user', [__CLASS__, 'handle_save_portal_user']);
        add_action('admin_post_bcrm_toggle_portal_user', [__CLASS__, 'handle_toggle_portal_user']);
        add_action('admin_post_bcrm_save_user_caps', [__CLASS__, 'handle_save_user_caps']);
        add_action('admin_post_bcrm_delete_portal_user', [__CLASS__, 'handle_delete_portal_user']);
        add_action('admin_post_bcrm_reset_portal_password', [__CLASS__, 'handle_reset_portal_password']);
        add_action('admin_post_bcrm_save_service_unit', [__CLASS__, 'handle_save_service_unit']);
        add_action('admin_post_bcrm_export_customers_csv', [__CLASS__, 'handle_export_customers_csv']);
        add_action('admin_post_bcrm_export_customers_excel', [__CLASS__, 'handle_export_customers_excel']);
        add_action('admin_post_bcrm_data_audit_json', [__CLASS__, 'handle_data_audit_json']);
        add_action('admin_post_bcrm_unapproved_invoice_cleanup', [__CLASS__, 'handle_unapproved_invoice_cleanup']);
        add_action('admin_post_bcrm_create_backup', [__CLASS__, 'handle_create_backup']);
        add_action('admin_post_bcrm_restore_backup', [__CLASS__, 'handle_restore_backup']);
        add_action('admin_post_bcrm_upload_restore_backup', [__CLASS__, 'handle_upload_restore_backup']);
        add_action('admin_post_bcrm_download_backup', [__CLASS__, 'handle_download_backup']);
        add_action('admin_post_bcrm_repair_database', [__CLASS__, 'handle_repair_database']);
        add_action('admin_post_bcrm_invoice_ownership_preview', [__CLASS__, 'handle_invoice_ownership_preview']);
        add_action('admin_post_bcrm_invoice_ownership_execute', [__CLASS__, 'handle_invoice_ownership_execute']);
        add_action('admin_post_bcrm_run_historical_identity_audit', ['Biawin_CRM_Historical_Audit', 'handle']);
        add_action('admin_post_bcrm_run_forward_reconstruction_planner', ['Biawin_CRM_Forward_Reconstruction_Planner', 'handle']);
        add_action('admin_post_bcrm_run_replacement_manifest', ['Biawin_CRM_Replacement_Manifest', 'handle']);
        add_action('admin_post_bcrm_export_replacement_manifest_csv', ['Biawin_CRM_Replacement_Manifest', 'export_csv']);
        add_action('admin_post_bcrm_run_commit_safety_preflight', ['Biawin_CRM_Commit_Safety_Preflight', 'handle']);
        add_action('admin_post_bcrm_run_expert_intent_merge_audit', ['Biawin_CRM_Expert_Intent_Merge_Audit', 'handle']);
        add_action('admin_post_bcrm_run_final_safe_set', ['Biawin_CRM_Final_Safe_Set', 'handle']);
        add_action('admin_post_bcrm_export_final_safe_set_csv', ['Biawin_CRM_Final_Safe_Set', 'export_csv']);
        add_action('admin_post_bcrm_preview_controlled_reconstruction', ['Biawin_CRM_Controlled_Reconstruction', 'handle_preview']);
        add_action('admin_post_bcrm_revalidate_remaining_controlled_reconstruction', ['Biawin_CRM_Controlled_Reconstruction', 'handle_remaining_revalidation']);
        add_action('admin_post_bcrm_final_read_only_audit', ['Biawin_CRM_Controlled_Reconstruction', 'handle_final_read_only_audit']);
        add_action('admin_post_bcrm_commit_controlled_reconstruction', ['Biawin_CRM_Controlled_Reconstruction', 'handle_commit']);
        add_action('admin_post_bcrm_recover_controlled_reconstruction_state', ['Biawin_CRM_Controlled_Reconstruction', 'handle_recover_state']);
        add_action('admin_post_bcrm_rollback_controlled_reconstruction', ['Biawin_CRM_Controlled_Reconstruction', 'handle_rollback']);
        add_action('admin_post_bcrm_export_reconstruction_journal_csv', ['Biawin_CRM_Controlled_Reconstruction', 'export_journal_csv']);
        add_action('admin_post_bcrm_run_job1000_planner', ['Biawin_CRM_Controlled_Job_1000_Planner', 'handle']);
        add_action('admin_post_bcrm_export_job1000_plan_json', ['Biawin_CRM_Controlled_Job_1000_Planner', 'export_json']);
        add_action('admin_post_bcrm_export_job1000_plan_csv', ['Biawin_CRM_Controlled_Job_1000_Planner', 'export_csv']);
        add_action('admin_post_bcrm_export_expert_intent_identity_csv', ['Biawin_CRM_Expert_Intent_Merge_Audit', 'export_identity_csv']);
        add_action('admin_post_bcrm_export_merge_reconciliation_csv', ['Biawin_CRM_Expert_Intent_Merge_Audit', 'export_merge_csv']);
        add_action('admin_post_bcrm_export_record_plan_csv', ['Biawin_CRM_Commit_Safety_Preflight', 'export_record_plan_csv']);
        add_action('admin_post_bcrm_export_expert_ledger_csv', ['Biawin_CRM_Commit_Safety_Preflight', 'export_expert_ledger_csv']);
        add_action('wp_ajax_bcrm_inspect_import_headers', [__CLASS__, 'ajax_inspect_import_headers']);
        add_action('wp_ajax_bcrm_import_start_stage', [__CLASS__, 'ajax_import_start_stage']);
        add_action('wp_ajax_bcrm_import_stage_batch', [__CLASS__, 'ajax_import_stage_batch']);
        add_action('wp_ajax_bcrm_import_start_commit', [__CLASS__, 'ajax_import_start_commit']);
        add_action('wp_ajax_bcrm_import_commit_batch', [__CLASS__, 'ajax_import_commit_batch']);
        add_action('wp_ajax_bcrm_import_cancel', [__CLASS__, 'ajax_import_cancel']);
    }

    public static function assets(string $hook): void
    {
        if (strpos($hook, 'biawin-crm') !== false) {
            $admin_css_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/admin.css');
            $admin_js_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/admin.js');
            $fix_css_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/portal-fixes.css');
            $fix_js_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/portal-fixes.js');
            wp_enqueue_style('biawin-crm-admin', BIAWIN_CRM_URL . 'assets/admin.css', [], $admin_css_ver);
            wp_enqueue_style('biawin-crm-portal-fixes-admin', BIAWIN_CRM_URL . 'assets/portal-fixes.css', ['biawin-crm-admin'], $fix_css_ver);
            if (class_exists('Biawin_CRM_Frontend')) {
                wp_add_inline_style('biawin-crm-portal-fixes-admin', '.bcrm{--bcrm-font-family:' . Biawin_CRM_Frontend::portal_font_stack() . ';font-family:var(--bcrm-font-family)!important}.bcrm input,.bcrm select,.bcrm textarea,.bcrm button{font-family:var(--bcrm-font-family)!important}');
            }
            wp_enqueue_script('biawin-crm-admin', BIAWIN_CRM_URL . 'assets/admin.js', [], $admin_js_ver, true);
            wp_enqueue_script('biawin-crm-portal-fixes-admin', BIAWIN_CRM_URL . 'assets/portal-fixes.js', ['biawin-crm-admin'], $fix_js_ver, true);
            wp_localize_script('biawin-crm-admin', 'BCRM_ADMIN', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'importNonce' => wp_create_nonce('bcrm_inspect_import_headers'),
                'batchNonce' => wp_create_nonce('bcrm_batch_import'),
                'stageNonce' => wp_create_nonce(Biawin_CRM_Receipt_Staging::NONCE_ACTION),
                'stagingToken' => Biawin_CRM_Receipt_Staging::fixed_token(),
                'finReviewNonce' => wp_create_nonce('bcrm_fin_review'),
                'maxFileUploads' => class_exists('Biawin_CRM_Upload_Normalizer') ? Biawin_CRM_Upload_Normalizer::max_uploads_ini() : 0,
            ]);
        }
    }

    public static function menu(): void
    {
        add_menu_page('CRM بیاوین', 'CRM بیاوین', 'bcrm_work_cases', 'biawin-crm', [__CLASS__, 'dashboard'], 'dashicons-groups', 26);
        add_submenu_page('biawin-crm', 'ایمپورت اطلاعات', 'ایمپورت اطلاعات', 'bcrm_import', 'biawin-crm-import', [__CLASS__, 'import_page']);
        add_submenu_page('biawin-crm', 'ایمپورت اصلاحی', 'ایمپورت اصلاحی', 'bcrm_import', 'biawin-crm-correction-import', [__CLASS__, 'import_page']);
        add_submenu_page('biawin-crm', 'مشتریان', 'مشتریان', 'bcrm_work_cases', 'biawin-crm-customers', [__CLASS__, 'customers_page']);
        add_submenu_page('biawin-crm', 'تخصیص پرونده', 'تخصیص پرونده', 'bcrm_manage_cases', 'biawin-crm-assignments', [__CLASS__, 'assignments_page']);
        add_submenu_page('biawin-crm', 'رفع ناقصی', 'رفع ناقصی', 'view_deficiency_cases', 'biawin-crm-deficiency', [__CLASS__, 'deficiency_page']);
        add_submenu_page('biawin-crm', 'تأیید مالی', 'تأیید مالی', 'bcrm_financial_verify', 'biawin-crm-financial', [__CLASS__, 'financial_page']);
        add_submenu_page('biawin-crm', 'گزارش تأیید مالی', 'گزارش تأیید مالی', Biawin_CRM_Finance_Report::menu_capability(), Biawin_CRM_Finance_Report::page_slug(), ['Biawin_CRM_Finance_Report', 'page']);
        add_submenu_page('biawin-crm', 'نارضایتی‌ها', 'نارضایتی‌ها', 'bcrm_complaints', 'biawin-crm-complaints', [__CLASS__, 'complaints_page']);
        add_submenu_page('biawin-crm', 'کارتابل خدمات', 'کارتابل خدمات', 'bcrm_services', 'biawin-crm-service-cases', [__CLASS__, 'service_cases_page']);
        add_submenu_page('biawin-crm', 'کاتالوگ خدمات', 'کاتالوگ خدمات', 'bcrm_manage_cases', 'biawin-crm-services', [__CLASS__, 'services_page']);
        add_submenu_page('biawin-crm', 'واحدها و اعضا', 'واحدها و اعضا', 'bcrm_manage_cases', 'biawin-crm-service-units', [__CLASS__, 'service_units_page']);
        // v0.8.64.0 — complaint subjects / outcomes / inquiry units; each tab has its own dedicated capability.
        add_submenu_page('biawin-crm', 'تنظیمات نارضایتی', 'تنظیمات نارضایتی', Biawin_CRM_After_Sales_Config_Admin::menu_capability(), Biawin_CRM_After_Sales_Config_Admin::PAGE, ['Biawin_CRM_After_Sales_Config_Admin', 'page']);
        add_submenu_page('biawin-crm', 'عودت وجه', 'عودت وجه', 'bcrm_refunds', 'biawin-crm-refunds', [__CLASS__, 'refunds_page']);
        add_submenu_page('biawin-crm', 'ثبت عودت‌ها', 'ثبت عودت‌ها', 'bcrm_refund_register', 'biawin-crm-refund-register', [__CLASS__, 'refund_register_page']);
        add_submenu_page('biawin-crm', 'ثبت استفاده‌شده‌ها', 'ثبت استفاده‌شده‌ها', 'bcrm_refund_register', 'biawin-crm-refund-used', [__CLASS__, 'refund_used_page']);
        add_submenu_page('biawin-crm', 'گزارش‌ها', 'گزارش‌ها', 'bcrm_reports', 'biawin-crm-reports', [__CLASS__, 'reports_page']);
        add_submenu_page('biawin-crm', 'Data Audit', 'Data Audit', 'bcrm_reports', 'biawin-crm-data-audit', [__CLASS__, 'data_audit_page']);
        add_submenu_page('biawin-crm', 'Reporting Source-of-Truth Validation', 'Reporting SOT', 'bcrm_reports', 'biawin-crm-reporting-sot', [__CLASS__, 'reporting_source_of_truth_page']);
        add_submenu_page('biawin-crm', 'ممیزی تاریخی هویت', 'ممیزی تاریخی هویت', 'bcrm_reports', 'biawin-crm-historical-audit', ['Biawin_CRM_Historical_Audit', 'page']);
        add_submenu_page('biawin-crm', 'بک‌آپ و بازگردانی CRM', 'بک‌آپ و بازگردانی', 'bcrm_manage_cases', 'biawin-crm-backups', [__CLASS__, 'backups_page']);
        add_submenu_page('biawin-crm', 'اصلاح مالکیت فاکتورها', 'اصلاح مالکیت فاکتورها', 'bcrm_import', 'biawin-crm-invoice-ownership', [__CLASS__, 'invoice_ownership_page']);
        add_submenu_page('biawin-crm', 'پاکسازی فاکتورهای بدون تایید مالی', 'پاکسازی فاکتورهای بدون تایید', 'bcrm_import', 'biawin-crm-unapproved-cleanup', [__CLASS__, 'unapproved_invoice_cleanup_page']);
        add_submenu_page('biawin-crm', 'کاربران پرتال', 'کاربران پرتال', 'bcrm_manage_cases', 'biawin-crm-users', [__CLASS__, 'users_page']);
        add_submenu_page('biawin-crm', 'لاگ ممیزی', 'لاگ ممیزی', 'bcrm_manage_cases', 'biawin-crm-audit', [__CLASS__, 'audit_page']);
        add_submenu_page('biawin-crm', 'تنظیمات', 'تنظیمات', 'bcrm_manage_cases', 'biawin-crm-settings', [__CLASS__, 'settings_page']);
    }

    public static function dashboard(): void
    {
        $scope = self::main_dashboard_reporting_scope();
        $normal = Biawin_CRM_Reporting_Consumer_Adapter::customer_population_count(Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $scope);
        $guarded = Biawin_CRM_Reporting_Consumer_Adapter::customer_population_count(Biawin_CRM_Reporting_Population_Service::GUARDED, $scope);
        $blacklisted = Biawin_CRM_Reporting_Consumer_Adapter::customer_population_count(Biawin_CRM_Reporting_Population_Service::BLACKLISTED_LIVE, $scope);
        $unresolved = Biawin_CRM_Reporting_Consumer_Adapter::customer_population_count(Biawin_CRM_Reporting_Population_Service::UNRESOLVED, $scope);
        $actual_paid = Biawin_CRM_Reporting_Consumer_Adapter::management_actual_paid_total($scope);
        $workable = Biawin_CRM_Reporting_Consumer_Adapter::workable_active_workload($scope);
        $unassigned = Biawin_CRM_Reporting_Consumer_Adapter::unassigned_normal_canonical($scope);
        $complaints = Biawin_CRM_Reporting_Consumer_Adapter::case_population_summary('complaints', $scope);
        $deficiency = Biawin_CRM_Reporting_Consumer_Adapter::case_population_summary('deficiency_cases', $scope);
        $financial_cases = Biawin_CRM_Reporting_Consumer_Adapter::case_population_summary('financial_cases', $scope);
        $service_cases = Biawin_CRM_Reporting_Consumer_Adapter::case_population_summary('service_cases', $scope);
        $refunds = Biawin_CRM_Reporting_Consumer_Adapter::case_population_summary('refunds', $scope);

        echo '<div class="wrap bcrm" dir="rtl"><h1>CRM بیاوین</h1><div class="bcrm-grid">';
        self::metric('پروفایل‌های عادی', self::adapter_metric_value($normal), self::adapter_metric_note($normal, 'NORMAL_CANONICAL'));
        self::metric('پروفایل‌های Guard', self::adapter_metric_value($guarded), self::adapter_metric_note($guarded, 'GUARDED'));
        self::metric('پروفایل‌های Blacklist', self::adapter_metric_value($blacklisted), self::adapter_metric_note($blacklisted, 'BLACKLISTED'));
        self::metric('پروفایل‌های نامشخص', self::adapter_metric_value($unresolved), self::adapter_metric_note($unresolved, 'UNRESOLVED'));
        self::metric('پرداخت واقعی', self::adapter_metric_value($actual_paid, true), self::adapter_metric_note($actual_paid, 'ACTUAL_PAID_LIVE'));
        self::metric('تخصیص فعال قابل کار', self::adapter_metric_value($workable), self::adapter_metric_note($workable, 'NEWEST_ACTIVE_NORMAL'));
        self::metric('بدون تخصیص عادی', self::adapter_metric_value($unassigned), self::adapter_metric_note($unassigned, 'UNASSIGNED_NORMAL_CANONICAL'));
        self::metric('پرونده نارضایتی', self::adapter_metric_value($complaints), self::adapter_metric_note($complaints, 'CASE_COMPLAINTS'));
        self::metric('پرونده رفع ناقصی', self::adapter_metric_value($deficiency), self::adapter_metric_note($deficiency, 'CASE_DEFICIENCY'));
        self::metric('پرونده مالی', self::adapter_metric_value($financial_cases), self::adapter_metric_note($financial_cases, 'CASE_FINANCIAL'));
        self::metric('پرونده خدمات', self::adapter_metric_value($service_cases), self::adapter_metric_note($service_cases, 'CASE_SERVICE'));
        self::metric('پرونده عودت وجه', self::adapter_metric_value($refunds), self::adapter_metric_note($refunds, 'CASE_REFUNDS'));
        echo '</div><p class="description">فضای کاری اولیه برای یکپارچه‌سازی مشتریان، پیگیری تماس، شکایت، گریدبندی و تخصیص پرونده.</p></div>';
    }

    private static function metric(string $label, $value, string $note = ''): void
    {
        echo '<div class="bcrm-card"><strong>' . esc_html((string) $value) . '</strong><span>' . esc_html($label) . '</span>';
        if ($note !== '') echo '<small style="display:block;margin-top:8px;color:#64748b">' . esc_html($note) . '</small>';
        echo '</div>';
    }

    private static function main_dashboard_reporting_scope(): array
    {
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        if (current_user_can('manage_options')) {
            return ['role' => Biawin_CRM_Reporting_Consumer_Adapter::ROLE_ADMIN, 'requesting_user_id' => $user_id, 'scope_proven' => true, 'permitted_department_keys' => [], 'permitted_organization_keys' => []];
        }
        if (current_user_can('bcrm_reports') || current_user_can('bcrm_manage_cases')) {
            return ['role' => Biawin_CRM_Reporting_Consumer_Adapter::ROLE_MANAGER, 'requesting_user_id' => $user_id, 'manager_user_id' => $user_id, 'scope_proven' => true, 'permitted_department_keys' => [], 'permitted_organization_keys' => []];
        }
        return ['role' => Biawin_CRM_Reporting_Consumer_Adapter::ROLE_AGENT, 'requesting_user_id' => $user_id, 'agent_user_id' => $user_id, 'scope_proven' => true];
    }

    private static function adapter_metric_value(array $result, bool $money = false): string
    {
        if (empty($result['trusted']) || $result['value'] === null) return 'نامطمئن';
        $value = (float)$result['value'];
        return $money ? number_format_i18n($value, 0) : number_format_i18n((int)$value);
    }

    private static function adapter_metric_note(array $result, string $semantic): string
    {
        $status = (string)($result['status'] ?? 'UNKNOWN');
        $source = (string)($result['source'] ?? 'UNKNOWN_SOURCE');
        $suffix = empty($result['trusted']) ? ' / ' . self::adapter_safe_status_message($status) : '';
        return $semantic . ' / ' . $source . ' / ' . $status . $suffix;
    }

    private static function adapter_safe_status_message(string $status): string
    {
        switch ($status) {
            case Biawin_CRM_Reporting_Consumer_Adapter::DEGRADED:
                return 'داده این شاخص کامل نیست و نیازمند بررسی است';
            case Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED:
                return 'داده این شاخص در حال حاضر قابل تأیید نیست';
            case Biawin_CRM_Reporting_Consumer_Adapter::UNAVAILABLE:
                return 'داده این شاخص در حال حاضر در دسترس نیست';
            case Biawin_CRM_Reporting_Consumer_Adapter::SEMANTICALLY_UNRESOLVED:
                return 'تعریف کسب‌وکاری این شاخص هنوز نهایی نشده است';
            default:
                return 'وضعیت این شاخص در حال حاضر قابل تأیید نیست';
        }
    }

    private static function report_metric_card(array $row): void
    {
        $trusted = !array_key_exists('trusted', $row) || !empty($row['trusted']);
        $count = $trusted && $row['count'] !== null ? number_format_i18n((int)$row['count']) : 'نامطمئن';
        $complete = $trusted && $row['complete_amount'] !== null ? number_format_i18n((float)$row['complete_amount']) : 'نامطمئن';
        $incomplete = $trusted && $row['incomplete_amount'] !== null ? number_format_i18n((float)$row['incomplete_amount']) : 'نامطمئن';
        $note = !$trusted ? ' / ' . self::adapter_safe_status_message((string)($row['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)) : '';
        echo '<div class="bcrm-card"><strong>' . esc_html($count) . '</strong><span>' . esc_html($row['label']) . '</span>';
        echo '<small style="display:block;margin-top:10px;color:#15803d">مبلغ کامل‌ها: ' . esc_html($complete) . '</small>';
        echo '<small style="display:block;margin-top:4px;color:#b91c1c">مبلغ ناقص‌ها: ' . esc_html($incomplete) . '</small>';
        if ($note !== '') echo '<small style="display:block;margin-top:4px;color:#64748b">' . esc_html($note) . '</small>';
        echo '</div>';
    }

    public static function import_page(): void
    {
        // Legacy monolithic dry-run transients are intentionally discarded in v0.6.4.
        delete_transient('bcrm_last_dry_run_' . get_current_user_id());
        $is_correction_page = isset($_GET['page']) && sanitize_key((string)$_GET['page']) === 'biawin-crm-correction-import';
        $fields = Biawin_CRM_Import_Service::field_labels();
        echo '<div class="wrap bcrm" dir="rtl"><h1>' . esc_html($is_correction_page ? 'ایمپورت اصلاحی پروفایل‌ها' : 'ایمپورت Excel/CSV') . '</h1>';
        echo '<p class="description">' . esc_html($is_correction_page ? 'برای اصلاح پروفایل‌هایی که به خاطر شماره‌های مشترک اشتباهی ادغام شده‌اند، فایل اصلاحی را اینجا بارگذاری کنید. در این حالت فقط CustomerID معیار مالکیت پروفایل و فاکتور است؛ شماره تماس ۱ و ۲ برای پیدا کردن/ادغام پروفایل استفاده نمی‌شوند.' : 'فایل را انتخاب کنید. سیستم سرستون‌های واقعی فایل را می‌خواند و سپس برای هر ستون مشخص می‌کنید معادل کدام فیلد در CRM است.') . '</p>';
        echo '<form id="bcrm-import-form" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_import_dry_run');
        echo '<input type="hidden" name="action" value="bcrm_import_dry_run">';
        echo '<div class="bcrm-import-upload-card">';
        echo '<label class="bcrm-import-file-label"><strong>فایل مشتریان</strong><span>CSV یا XLSX</span></label>';
        echo '<input id="bcrm-import-file" type="file" name="bcrm_file" accept=".csv,.xlsx" required>';
        echo '<input id="bcrm-upload-token" type="hidden" name="upload_token" value="">';
        echo '<button id="bcrm-read-headers" type="button" class="button">خواندن سرستون‌ها</button>';
        echo '<span id="bcrm-import-inspect-status" class="bcrm-import-status" aria-live="polite"></span>';
        echo '</div>';

        echo '<div id="bcrm-mapping-panel" class="bcrm-mapping-panel" hidden>';
        echo '<div class="bcrm-mapping-head"><div><h2>تطبیق سرستون‌های فایل</h2><p>برای هر سرستون فایل، فیلد معادل در سیستم را انتخاب کنید. ستون‌های استاندارد را به فیلدهای CRM وصل کنید. هر ستون اضافه را هم می‌توانید با گزینه «ذخیره با همین سرستون» نگه دارید.</p></div>';
        echo '<button type="button" class="button" id="bcrm-reset-mapping">پاک کردن انتخاب‌ها</button></div>';
        echo '<div class="bcrm-mapping-notice"><strong>کد فاکتور الزامی است.</strong> فیلدهای استاندارد سیستم قابل انتخاب‌اند و ستون‌های اضافه می‌توانند با نام اصلی خود ذخیره شوند.</div>';
        echo '<label style="display:block;margin:10px 0"><input type="checkbox" id="bcrm-fill-customerid-gaps" name="fill_customer_id_gaps" value="1" ' . checked(!$is_correction_page, true, false) . '> اگر CustomerID عددی و ترتیبی است، شناسه‌های مفقود بین ۱ تا بزرگ‌ترین CustomerID را به‌صورت «پروفایل منبع بدون فاکتور» بساز تا تعداد پروفایل‌های منبع با بازه CustomerID برابر شود.</label>';
        echo '<label style="display:block;margin:10px 0"><input type="checkbox" id="bcrm-correction-mode" name="correction_mode" value="1" ' . checked($is_correction_page, true, false) . '> حالت اصلاحی: فقط CustomerID معیار اتصال/اصلاح باشد و هیچ شماره‌ای برای ادغام استفاده نشود.</label>';
        echo '<label style="display:block;margin:10px 0"><input type="checkbox" id="bcrm-backup-before-import" value="1" checked> قبل از ثبت نهایی ایمپورت، بک‌آپ کامل قابل بازگردانی بساز.</label>';
        echo '<div class="bcrm-mapping-table-wrap"><table class="widefat striped bcrm-mapping-table"><thead><tr><th>سرستون فایل</th><th>نمونه اطلاعات</th><th>معادل در سیستم</th></tr></thead><tbody id="bcrm-mapping-rows"></tbody></table></div>';
        echo '<div class="bcrm-mapping-actions"><span id="bcrm-mapping-summary"></span><button id="bcrm-import-preview-submit" class="button button-primary" disabled>پیش‌نمایش ایمپورت</button></div>';
        echo '<div id="bcrm-batch-progress" class="bcrm-batch-progress" hidden><div class="bcrm-progress-head"><strong id="bcrm-progress-title">در حال آماده‌سازی…</strong><span id="bcrm-progress-percent">۰٪</span></div><div class="bcrm-progress-track"><span id="bcrm-progress-bar"></span></div><p id="bcrm-progress-text" class="description"></p><button type="button" id="bcrm-cancel-import" class="button">لغو پردازش</button></div>';
        echo '<div id="bcrm-batch-preview" class="bcrm-batch-preview" hidden></div>';
        echo '</div>';
        echo '<noscript><div class="notice notice-error inline"><p>برای تطبیق سرستون‌های فایل، JavaScript مرورگر باید فعال باشد.</p></div></noscript>';
        echo '</form>';

        // Field labels are exposed to the page without coupling the UI to internal keys.
        echo '<script type="application/json" id="bcrm-import-field-labels">' . wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';

        $preview = get_transient('bcrm_last_dry_run_' . get_current_user_id());
        if ($preview) {
            echo '<hr><h2>آخرین پیش‌نمایش</h2><div class="bcrm-grid">';
            self::metric('ردیف‌ها', (int) $preview['row_count']);
            self::metric('فاکتورهای یونیک', (int) $preview['invoice_count']);
            self::metric('پروفایل‌های اولیه', (int) $preview['profile_count']);
            self::metric('گروه‌های مشکوک', (int) $preview['suspicious_group_count']);
            self::metric('فاکتورهای دارای مغایرت', (int) ($preview['conflicted_invoice_count'] ?? 0));
            self::metric('شماره‌های پرتکرار', count($preview['high_risk_phones'] ?? []));
            echo '</div>';
            if (!empty($preview['errors'])) {
                echo '<div class="notice notice-error inline"><ul>';
                foreach ($preview['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul></div>';
            }
            if (!empty($preview['missing_invoice_code_rows'])) {
                echo '<div class="notice notice-warning inline"><p>' . esc_html((string) $preview['missing_invoice_code_rows']) . ' ردیف بدون کد فاکتور تشخیص داده شد.</p></div>';
            }
            if (!empty($preview['headers'])) {
                echo '<details class="bcrm-details"><summary>ستون‌های فایل استفاده‌شده در این پیش‌نمایش</summary><p>' . esc_html(implode('، ', $preview['headers'])) . '</p></details>';
            }
            if (!empty($preview['mapping'])) {
                echo '<details class="bcrm-details"><summary>تطبیق ستون‌های این پیش‌نمایش</summary><table class="widefat striped"><thead><tr><th>فیلد سیستم</th><th>ستون فایل</th></tr></thead><tbody>';
                foreach ($preview['mapping'] as $target => $header) {
                    if ($header === '') continue;
                    echo '<tr><td>' . esc_html($fields[$target] ?? $target) . '</td><td>' . esc_html($header) . '</td></tr>';
                }
                echo '</tbody></table></details>';
            }
            if (!empty($preview['high_risk_phones'])) {
                echo '<details class="bcrm-details"><summary>شماره‌های پرتکرار که از ادغام خودکار حذف شدند</summary><ul>';
                foreach ($preview['high_risk_phones'] as $phone => $count) {
                    echo '<li>' . esc_html($phone) . ' - ' . esc_html((string) $count) . ' فاکتور</li>';
                }
                echo '</ul></details>';
            }
            if ((int) ($preview['invoice_count'] ?? 0) > 0 && empty($preview['errors'])) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('bcrm_import_commit');
                echo '<input type="hidden" name="action" value="bcrm_import_commit"><button class="button button-primary">ثبت نهایی ایمپورت</button></form>';
            } else {
                echo '<p><strong>ثبت نهایی فعال نیست، چون پیش‌نمایش دارای خطا است یا هیچ فاکتور معتبری پیدا نشده است.</strong></p>';
            }
        }
        echo '</div>';
    }

    public static function ajax_inspect_import_headers(): void
    {
        if (!current_user_can('bcrm_import')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز است.'], 403);
        }
        check_ajax_referer('bcrm_inspect_import_headers', 'nonce');

        $file = $_FILES['file'] ?? null;
        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => 'فایل معتبری دریافت نشد.'], 400);
        }
        if (!empty($file['error'])) {
            wp_send_json_error(['message' => 'آپلود فایل با خطا مواجه شد. کد خطا: ' . (int) $file['error']], 400);
        }
        $name = sanitize_file_name((string) ($file['name'] ?? ''));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            wp_send_json_error(['message' => 'فقط فایل CSV یا XLSX قابل استفاده است.'], 400);
        }
        if ((int) ($file['size'] ?? 0) > 25 * 1024 * 1024) {
            wp_send_json_error(['message' => 'حجم فایل برای مرحله بررسی سرستون‌ها بیش از حد مجاز است.'], 400);
        }

        [$upload_token, $stored_path, $store_error] = Biawin_CRM_Import_Service::persist_uploaded_file($file);
        if ($store_error) {
            wp_send_json_error(['message' => $store_error], 400);
        }
        [$headers, $samples, $row_count, $errors] = Biawin_CRM_Import_Service::inspect_file($stored_path, $name, 5);
        if ($errors) {
            @unlink($stored_path);
            delete_user_meta(get_current_user_id(), 'bcrm_upload_' . $upload_token);
            wp_send_json_error(['message' => implode(' ', $errors)], 400);
        }
        if (!$headers) {
            wp_send_json_error(['message' => 'هیچ سرستونی در فایل پیدا نشد.'], 400);
        }

        $suggestions = Biawin_CRM_Import_Service::suggest_header_mapping($headers);
        $last_mapping = get_user_meta(get_current_user_id(), 'bcrm_last_import_mapping', true);
        if (is_array($last_mapping)) {
            foreach ($last_mapping as $target => $header) {
                if ($target === '__custom' && is_array($header)) {
                    foreach ($header as $custom_header) if (in_array($custom_header, $headers, true)) $suggestions[$custom_header] = '__custom__';
                    continue;
                }
                if ($header !== '' && in_array($header, $headers, true) && isset(Biawin_CRM_Import_Service::field_labels()[$target])) $suggestions[$header] = $target;
            }
        }

        wp_send_json_success([
            'headers' => array_values($headers),
            'samples' => $samples,
            'suggestions' => $suggestions,
            'rowCount' => $row_count,
            'fields' => Biawin_CRM_Import_Service::field_labels(),
            'uploadToken' => $upload_token,
        ]);
    }


    private static function batch_guard(): void
    {
        if (!current_user_can('bcrm_import')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        check_ajax_referer('bcrm_batch_import','nonce');
    }

    public static function ajax_import_start_stage(): void
    {
        self::batch_guard();
        $token=sanitize_text_field(wp_unslash($_POST['upload_token']??''));
        $upload=Biawin_CRM_Import_Service::resolve_uploaded_file($token);
        // If the persisted upload reference disappeared between the header-inspection
        // request and this request, transparently accept the browser's still-selected
        // file again. The operator does not need to reselect anything.
        if(!$upload && !empty($_FILES['file']['tmp_name'])) {
            $file = $_FILES['file'];
            if (!empty($file['error'])) wp_send_json_error(['message'=>'ارسال مجدد فایل با خطا مواجه شد. کد خطا: '.(int)$file['error']],400);
            [$token2,$stored_path,$store_error]=Biawin_CRM_Import_Service::persist_uploaded_file($file);
            if($store_error) wp_send_json_error(['message'=>$store_error],400);
            $token=$token2;
            $upload=Biawin_CRM_Import_Service::resolve_uploaded_file($token);
        }
        if(!$upload) wp_send_json_error(['message'=>'فایل موقت در دسترس نیست و ارسال مجدد خودکار هم انجام نشد. فایل را یک‌بار دوباره انتخاب کنید.'],400);
        [$headers,,,$errors]=Biawin_CRM_Import_Service::inspect_file($upload['path'],$upload['name'],1);
        if($errors) wp_send_json_error(['message'=>implode(' ',$errors)],400);
        [$mapping,$mapping_errors]=self::mapping_from_request($headers);
        if($mapping_errors) wp_send_json_error(['message'=>implode(' ',$mapping_errors)],400);
        update_user_meta(get_current_user_id(),'bcrm_last_import_mapping',$mapping);
        [$job,$err]=Biawin_CRM_Import_Service::create_job($token,$mapping,$headers);
        if($err) wp_send_json_error(['message'=>$err],500);
        wp_send_json_success($job);
    }

    public static function ajax_import_stage_batch(): void
    {
        self::batch_guard();
        $job=sanitize_text_field(wp_unslash($_POST['job_token']??''));
        $result=Biawin_CRM_Import_Service::stage_batch($job,2000);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'پردازش Batch ناموفق بود.'],500);
        wp_send_json_success($result);
    }

    public static function ajax_import_start_commit(): void
    {
        self::batch_guard();
        $job=sanitize_text_field(wp_unslash($_POST['job_token']??''));
        if (!empty($_POST['create_backup'])) self::create_crm_backup('before-import');
        $result=Biawin_CRM_Import_Service::start_commit($job);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'شروع ثبت نهایی ناموفق بود.'],500);
        wp_send_json_success($result);
    }

    public static function ajax_import_commit_batch(): void
    {
        self::batch_guard();
        $job=sanitize_text_field(wp_unslash($_POST['job_token']??''));
        $result=Biawin_CRM_Import_Service::commit_batch($job,400);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'ثبت Batch ناموفق بود.'],500);
        wp_send_json_success($result);
    }

    public static function ajax_import_cancel(): void
    {
        self::batch_guard();
        $job=sanitize_text_field(wp_unslash($_POST['job_token']??''));
        if(!$job || !Biawin_CRM_Import_Service::cancel_job($job)) wp_send_json_error(['message'=>'لغو پردازش ممکن نشد.'],400);
        wp_send_json_success(['message'=>'پردازش لغو شد.']);
    }

    private static function mapping_from_request(array $headers): array
    {
        $fields = Biawin_CRM_Import_Service::field_labels();
        $mapping = array_fill_keys(array_keys($fields), '');
        $mapping['__custom'] = [];
        $errors = [];

        $posted_headers = array_values(array_map('sanitize_text_field', wp_unslash($_POST['map_header'] ?? [])));
        $posted_targets = array_values(array_map('sanitize_key', wp_unslash($_POST['map_target'] ?? [])));

        // Backward compatibility for previous versions that submitted mapping[field] = header.
        if (!$posted_headers && !empty($_POST['mapping']) && is_array($_POST['mapping'])) {
            $legacy = array_map('sanitize_text_field', wp_unslash($_POST['mapping']));
            foreach ($legacy as $target => $header) {
                if (isset($fields[$target]) && in_array($header, $headers, true)) {
                    $mapping[$target] = $header;
                }
            }
        } else {
            foreach ($posted_headers as $idx => $header) {
                $target = $posted_targets[$idx] ?? '';
                if ($target === '') continue;
                if ($target === '__custom__') { if (!in_array($header, $headers, true)) $errors[]='ستون «'.$header.'» در فایل فعلی وجود ندارد.'; else $mapping['__custom'][]=$header; continue; }
                if (!isset($fields[$target])) {
                    $errors[] = 'یک فیلد ناشناخته در تطبیق ستون‌ها ارسال شده است.';
                    continue;
                }
                if (!in_array($header, $headers, true)) {
                    $errors[] = 'ستون «' . $header . '» در فایل فعلی وجود ندارد.';
                    continue;
                }
                if ($mapping[$target] !== '' && $mapping[$target] !== $header) {
                    $errors[] = 'فیلد «' . $fields[$target] . '» به بیش از یک ستون متصل شده است.';
                    continue;
                }
                $mapping[$target] = $header;
            }
        }

        if ($mapping['invoice_code'] === '') {
            $errors[] = 'ستون معادل «کد فاکتور» الزامی است.';
        }
        $correction_mode = !empty($_POST['correction_mode']) ? 1 : 0;
        if ($correction_mode && $mapping['customer_external_id'] === '') {
            $errors[] = 'در حالت اصلاحی، ستون CustomerID الزامی است.';
        }

        $mapping['__custom'] = array_values(array_unique(array_filter($mapping['__custom'])));
        $mapping['__fill_customer_id_gaps'] = !empty($_POST['fill_customer_id_gaps']) ? 1 : 0;
        $mapping['__correction_mode'] = $correction_mode;
        return [$mapping, array_values(array_unique($errors))];
    }

    public static function handle_dry_run(): void
    {
        if (!current_user_can('bcrm_import') || !check_admin_referer('bcrm_import_dry_run')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        $file = $_FILES['bcrm_file'] ?? null;
        if (!$file || empty($file['tmp_name'])) {
            wp_die('فایلی بارگذاری نشده است.');
        }
        [$headers, $rows, $errors] = Biawin_CRM_Import_Service::parse_file($file['tmp_name'], $file['name']);
        [$mapping, $mapping_errors] = self::mapping_from_request($headers);
        $errors = array_merge($errors, $mapping_errors);
        $analysis = Biawin_CRM_Import_Service::analyze($rows, $mapping);
        $analysis['mapping'] = $mapping;
        $analysis['file_name'] = sanitize_file_name($file['name']);
        $analysis['headers'] = $headers;
        $analysis['errors'] = $errors;
        set_transient('bcrm_last_dry_run_' . get_current_user_id(), $analysis, HOUR_IN_SECONDS);
        if (empty($analysis['errors'])) {
            update_user_meta(get_current_user_id(), 'bcrm_last_import_mapping', $mapping);
        }
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-import'));
        exit;
    }

    public static function handle_commit(): void
    {
        if (!current_user_can('bcrm_import') || !check_admin_referer('bcrm_import_commit')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        $analysis = get_transient('bcrm_last_dry_run_' . get_current_user_id());
        if (!$analysis) {
            wp_die('پیش‌نمایش منقضی شده است.');
        }
        if (!empty($analysis['errors']) || (int) ($analysis['invoice_count'] ?? 0) === 0) {
            wp_die('ثبت نهایی ممکن نیست، چون هیچ فاکتور معتبری در پیش‌نمایش پیدا نشده است.');
        }
        Biawin_CRM_Import_Service::commit($analysis, $analysis['mapping'], $analysis['file_name']);
        delete_transient('bcrm_last_dry_run_' . get_current_user_id());
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-customers'));
        exit;
    }

    public static function customers_page(): void
    {
        global $wpdb;
        $id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
        echo '<div class="wrap bcrm" dir="rtl"><h1>مشتریان</h1>';
        if ($id) {
            self::profile_page($id);
            echo '</div>';
            return;
        }
        $rows = $wpdb->get_results("SELECT * FROM " . Biawin_CRM_DB::table('customers') . " ORDER BY id DESC LIMIT 100");
        echo '<table class="widefat striped"><thead><tr><th>شناسه</th><th>کد مشتری</th><th>نام</th><th>وضعیت</th><th>گرید</th><th>مجموع واریزی</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $url = self::portal_customer_url((int) $row->id);
            echo '<tr><td>' . (int) $row->id . '</td><td>' . esc_html($row->customer_code ?: '-') . '</td><td>' . esc_html($row->display_name) . '</td><td>' . esc_html(self::status_label($row->status)) . '</td><td>' . esc_html($row->grade ?: '-') . '</td><td>' . esc_html($row->total_paid) . '</td><td><a class="button" href="' . esc_url($url) . '">نمای ۳۶۰ درجه</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function profile_page(int $customer_id): void
    {
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('customers') . " WHERE id = %d", $customer_id));
        if (!$customer) {
            echo '<p>مشتری پیدا نشد.</p>';
            return;
        }
        $invoices = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('invoices') . " WHERE customer_id = %d", $customer_id));
        $phones = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('phones') . " WHERE customer_id = %d", $customer_id));
        echo '<h2>' . esc_html($customer->display_name ?: 'پروفایل بدون نام') . '</h2><div class="bcrm-grid">';
        self::metric('شناسه داخلی', (int) $customer->id);
        self::metric('فاکتورها', count($invoices));
        self::metric('شماره‌ها', count($phones));
        self::metric('مغایرت', (int) $customer->discrepancy_amount);
        self::metric('گرید', $customer->grade ?: '-');
        echo '</div><h3>شماره‌های تماس</h3><ul>';
        foreach ($phones as $phone) {
            echo '<li>' . esc_html($phone->normalized_phone) . '</li>';
        }
        echo '</ul><h3>فاکتورها</h3><table class="widefat striped"><tr><th>کد</th><th>نام ثبت‌شده</th><th>مبلغ</th><th>وضعیت</th></tr>';
        foreach ($invoices as $invoice) {
            $tag = !empty($invoice->prepayment_unapproved) ? ' <span style="color:#b91c1c;font-weight:700">[پیش‌واریزی تأیید نشده]</span>' : '';
            $tag .= !empty($invoice->exclude_from_total) ? ' <span style="color:#b45309;font-weight:700">[خارج از مجموع]</span>' : '';
            echo '<tr><td>' . esc_html($invoice->invoice_code) . '</td><td>' . esc_html($invoice->invoice_name) . '</td><td>' . esc_html($invoice->amount_paid) . '</td><td>' . esc_html($invoice->invoice_status) . $tag . '</td></tr>';
        }
        echo '</table>';
        echo '<h3>اقدام کارشناس</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_save_interaction');
        echo '<input type="hidden" name="action" value="bcrm_save_interaction"><input type="hidden" name="customer_id" value="' . (int) $customer_id . '">';
        echo '<p><label>نتیجه تماس <select name="outcome_key"><option value="">انتخاب کنید</option>'; foreach(Biawin_CRM_Workflow_Service::contact_outcomes(true) as $o) echo '<option value="'.esc_attr($o['key']).'">'.esc_html($o['label']).'</option>'; echo '</select></label></p>';
        echo '<p><label>رضایت <input name="satisfaction_score" type="number" min="1" max="10"></label></p>';
        echo '<p><label>مبلغ اعلامی مشتری <input name="declared_paid"></label></p>';
        echo '<p><label>شکایت<br><textarea name="complaint_reason" rows="3" class="large-text"></textarea></label></p>';
        echo '<p><label>یادداشت<br><textarea name="notes" rows="4" class="large-text"></textarea></label></p>';
        echo '<p><button class="button button-primary">ثبت اقدام معتبر و صدور کد مشتری</button></p></form>';
    }

    public static function handle_interaction(): void
    {
        if (!current_user_can('bcrm_work_cases') || !check_admin_referer('bcrm_save_interaction')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        $request = new WP_REST_Request('POST', '/biawin-crm/v1/customers/' . (int) $_POST['customer_id'] . '/interaction');
        $request->set_param('id', (int) $_POST['customer_id']);
        $request->set_body_params(wp_unslash($_POST));
        Biawin_CRM_REST::create_interaction($request);
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-customers&customer_id=' . (int) $_POST['customer_id']));
        exit;
    }

    public static function assignments_page(): void
    {
        global $wpdb;
        $agents = array_values(array_filter(get_users(['role__in' => ['bcrm_agent']]), fn($u) => !(bool)get_user_meta($u->ID,'bcrm_disabled',true) && $u->has_cap('bcrm_work_cases')));
        $min = isset($_GET['min_paid']) ? Biawin_CRM_Normalizer::money(wp_unslash($_GET['min_paid'])) : 0;
        $max = isset($_GET['max_paid']) && $_GET['max_paid'] !== '' ? Biawin_CRM_Normalizer::money(wp_unslash($_GET['max_paid'])) : 0;
        $min_invoices = isset($_GET['min_invoices']) && $_GET['min_invoices'] !== '' ? max(0, absint($_GET['min_invoices'])) : 0;
        $max_invoices = isset($_GET['max_invoices']) && $_GET['max_invoices'] !== '' ? max(0, absint($_GET['max_invoices'])) : 0;
        $phone_query_raw = isset($_GET['phone_query']) ? sanitize_text_field(wp_unslash($_GET['phone_query'])) : '';
        $phone_query = $phone_query_raw !== '' ? Biawin_CRM_Normalizer::phone($phone_query_raw) : '';
        $cohort = sanitize_key($_GET['cohort'] ?? 'normal');
        if (!in_array($cohort, ['normal','1404','all'], true)) $cohort = 'normal';
        $customers = Biawin_CRM_DB::table('customers');
        $phones = Biawin_CRM_DB::table('phones');
        $invoices = Biawin_CRM_DB::table('invoices');
        $interactions = Biawin_CRM_DB::table('interactions');
        $mapping_table = Biawin_CRM_DB::table('sales_manager_mapping');
        $assign_contact = isset($_GET['contact_outcome']) ? sanitize_key((string)wp_unslash($_GET['contact_outcome'])) : '__none__';
        $valid_assign_contacts = array_merge(['', '__none__'], array_map(static fn($o) => (string)$o['key'], Biawin_CRM_Workflow_Service::contact_outcomes(true)));
        if (!in_array($assign_contact, $valid_assign_contacts, true)) $assign_contact = '__none__';
        $refund_tag = Biawin_CRM_Refund_Register::sanitize_tag_filter(wp_unslash($_GET['refund_tag'] ?? 'any'));
        $assignment_scope = self::main_dashboard_reporting_scope();
        $workload_cards_result = Biawin_CRM_Reporting_Consumer_Adapter::management_workload_cards($assignment_scope);
        $workload_cards = [];
        if (!empty($workload_cards_result['trusted']) && isset($workload_cards_result['data']['rows']) && is_array($workload_cards_result['data']['rows'])) {
            foreach ($workload_cards_result['data']['rows'] as $card_row) $workload_cards[(string)$card_row['key']] = $card_row;
        }
        $normal_population = Biawin_CRM_Reporting_Consumer_Adapter::customer_population_count(Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $assignment_scope);
        $normal_count = !empty($normal_population['trusted']) && $normal_population['value'] !== null ? (int)$normal_population['value'] : null;
        $y1404_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $customers WHERE assignment_cohort='1404'");
        $assigned_count = $workload_cards['workable_active']['value'] ?? null;
        $remaining_count = $workload_cards['unassigned_normal']['value'] ?? null;
        $review_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $customers WHERE status IN ('needs_review','in_review')");
        $where=['1=1']; $params=[];
        if ($cohort==='1404') $where[]="c.assignment_cohort='1404'";
        elseif ($cohort==='normal') $where[]="(c.assignment_cohort<>'1404' OR c.assignment_cohort IS NULL)";
        if($min>0){$where[]='c.total_paid >= %f';$params[]=$min;}
        if($max>0){$where[]='c.total_paid <= %f';$params[]=$max;}
        if($min_invoices>0){$where[]="(SELECT COUNT(*) FROM $invoices fi WHERE fi.customer_id=c.id) >= %d";$params[]=$min_invoices;}
        if($max_invoices>0){$where[]="(SELECT COUNT(*) FROM $invoices fi WHERE fi.customer_id=c.id) <= %d";$params[]=$max_invoices;}
        if ($assign_contact === '__none__') {
            $where[] = "NOT EXISTS (SELECT 1 FROM $interactions ai WHERE ai.customer_id=c.id)";
        } elseif ($assign_contact !== '') {
            $assign_contact_label = Biawin_CRM_Workflow_Service::contact_outcome_label($assign_contact, $assign_contact);
            $latest_outcome_key = "(SELECT NULLIF(ai.outcome_key,'') FROM $interactions ai WHERE ai.customer_id=c.id ORDER BY ai.id DESC LIMIT 1)";
            $latest_outcome_label = "(SELECT NULLIF(ai.outcome,'') FROM $interactions ai WHERE ai.customer_id=c.id ORDER BY ai.id DESC LIMIT 1)";
            $where[] = "(COALESCE($latest_outcome_key,'')=%s OR (COALESCE($latest_outcome_key,'')='' AND COALESCE($latest_outcome_label,'')=%s))";
            $params[] = $assign_contact;
            $params[] = $assign_contact_label;
        }
        $assignment_filters = [
            'cohort' => $cohort,
            'min_paid' => $min,
            'max_paid' => $max,
            'min_invoices' => $min_invoices,
            'max_invoices' => $max_invoices,
            'contact_outcome' => $assign_contact,
            'contact_outcome_label' => $assign_contact !== '' && $assign_contact !== '__none__' ? Biawin_CRM_Workflow_Service::contact_outcome_label($assign_contact, $assign_contact) : '',
            'refund_tag' => $refund_tag,
        ];
        $filter_summary = Biawin_CRM_Reporting_Consumer_Adapter::management_assignment_filter_summary($assignment_filters, $assignment_scope);
        $candidate_rows = Biawin_CRM_Reporting_Consumer_Adapter::management_assignment_candidate_rows($assignment_filters, $assignment_scope, 500, 0);
        $filter_total = !empty($filter_summary['trusted']) ? (int)($filter_summary['data']['total_count'] ?? 0) : null;
        $filter_unassigned = !empty($filter_summary['trusted']) ? (int)($filter_summary['data']['unassigned_count'] ?? 0) : null;
        $filter_assigned = !empty($filter_summary['trusted']) ? (int)($filter_summary['data']['workable_count'] ?? 0) : null;
        $rows = !empty($candidate_rows['trusted']) && isset($candidate_rows['data']['rows']) && is_array($candidate_rows['data']['rows']) ? array_map(static fn($row)=>(object)$row, $candidate_rows['data']['rows']) : [];

        $phone_matches=[];
        if($phone_query_raw !== ''){
            if($phone_query !== ''){
                $phone_matches=$wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT c.*, p.normalized_phone phone, (SELECT COUNT(*) FROM $invoices ic WHERE ic.customer_id=c.id) invoice_count, (SELECT u.display_name FROM ".Biawin_CRM_DB::table('assignments')." a LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id WHERE a.customer_id=c.id AND " . Biawin_CRM_Reporting_Assignment_Service::newest_active_assignment_condition('a') . " LIMIT 1) agent_name FROM $customers c INNER JOIN $phones p ON p.customer_id=c.id WHERE p.normalized_phone=%s ORDER BY c.id ASC",
                    $phone_query
                ));
            }
        }

        echo '<div class="wrap bcrm bcrm-assignment-page" dir="rtl"><div class="bcrm-assign-hero"><div><span>داشبورد / تخصیص شماره موبایل</span><h1>تخصیص شماره موبایل</h1><p>شماره‌های مشتریان را به کارشناسان منتخب تخصیص دهید و وضعیت صف را مدیریت کنید.</p></div><div class="bcrm-assign-hero-icon">☎</div></div>';
        if(isset($_GET['assigned'])) echo '<div class="notice notice-success inline"><p>'.number_format_i18n(absint($_GET['assigned'])).' پروفایل تخصیص داده شد.</p></div>';
        $last_assignment_result = get_transient('bcrm_assignment_result_' . get_current_user_id());
        if (isset($_GET['assigned']) && is_array($last_assignment_result) && !empty($last_assignment_result['rows'])) {
            echo '<div class="notice notice-info inline"><p><strong>جزئیات آخرین تخصیص:</strong>' . (!empty($last_assignment_result['summary']) ? ' ' . esc_html((string)$last_assignment_result['summary']) : '') . '</p><ul style="margin:0 18px 8px 0">';
            foreach (array_slice((array)$last_assignment_result['rows'], 0, 12) as $assignment_row) {
                $contact_note = !empty($assignment_row['latest_contact']) ? ' | آخرین تماس: ' . $assignment_row['latest_contact'] . (!empty($assignment_row['latest_contact_agent']) ? ' توسط ' . $assignment_row['latest_contact_agent'] : '') : ' | بدون تماس ثبت‌شده';
                echo '<li>' . esc_html(($assignment_row['customer'] ?? ('Customer #' . (int)($assignment_row['customer_id'] ?? 0))) . ' ← ' . ($assignment_row['agent'] ?? ('User #' . (int)($assignment_row['agent_user_id'] ?? 0))) . $contact_note) . '</li>';
            }
            if ((int)($last_assignment_result['count'] ?? 0) > 12) echo '<li>و موارد بیشتر...</li>';
            echo '</ul></div>';
        }
        if(isset($_GET['returned'])) echo '<div class="notice notice-success inline"><p>'.number_format_i18n(absint($_GET['returned'])).' تخصیص برگشت داده شد و پروفایل‌ها به صف تخصیص‌نیافته منتقل شدند.</p></div>';
        $assign_error = get_transient('bcrm_assignment_error_' . get_current_user_id());
        if (is_array($assign_error) && !empty($assign_error['message'])) {
            delete_transient('bcrm_assignment_error_' . get_current_user_id());
            echo '<div class="notice notice-warning inline"><p>'.esc_html((string)$assign_error['message']).'</p></div>';
        }
        $base=admin_url('admin.php?page=biawin-crm-assignments');
        echo '<div class="bcrm-assign-kpis"><div><span>تخصیص عادی</span><strong>'.esc_html(self::report_safe_number($normal_count, $normal_count !== null)).'</strong><small>شماره</small></div><div><span>تخصیص شده</span><strong>'.esc_html(self::report_safe_number($assigned_count, $assigned_count !== null)).'</strong><small>شماره</small></div><div><span>باقی مانده</span><strong>'.esc_html(self::report_safe_number($remaining_count, $remaining_count !== null)).'</strong><small>شماره</small></div><div><span>در انتظار بررسی</span><strong>'.number_format_i18n($review_count).'</strong><small>شماره</small></div></div>';
        // v0.8.53 supervisor request: live workload count per agent. Counts are based on the canonical newest active assignment.
        echo '<div class="bcrm-card bcrm-assign-card"><div class="bcrm-assign-card-head"><h2>تعداد داده موجود در کارتابل کارشناسان</h2><span class="description">لحظه‌ای؛ Quarantineهای Readiness جداگانه نمایش داده می‌شوند.</span></div><table class="widefat striped"><thead><tr><th>کارشناس</th><th>کارتابل فعال</th><th>Readiness Quarantine</th><th>قابل کار اکنون</th></tr></thead><tbody>';
        $agent_rows_result = Biawin_CRM_Reporting_Consumer_Adapter::management_agent_workload_rows($assignment_scope);
        $agent_rows = [];
        if (!empty($agent_rows_result['trusted']) && isset($agent_rows_result['data']['rows']) && is_array($agent_rows_result['data']['rows'])) {
            foreach ($agent_rows_result['data']['rows'] as $agent_row) $agent_rows[(int)$agent_row['agent_user_id']] = $agent_row;
        }
        foreach ($agents as $agent) {
            $agent_row = $agent_rows[(int)$agent->ID] ?? ['active_customers'=>0, 'guarded_customers'=>0, 'workable_customers'=>0, 'trusted'=>!empty($agent_rows_result['trusted'])];
            echo '<tr><td>'.esc_html($agent->display_name ?: $agent->user_login).'</td><td><strong>'.esc_html(self::report_safe_number($agent_row['active_customers'] ?? null, $agent_row['trusted'] ?? false)).'</strong></td><td>'.esc_html(self::report_safe_number($agent_row['guarded_customers'] ?? null, $agent_row['trusted'] ?? false)).'</td><td><strong>'.esc_html(self::report_safe_number($agent_row['workable_customers'] ?? null, $agent_row['trusted'] ?? false)).'</strong></td></tr>';
        }
        if(!$agents) echo '<tr><td colspan="4">کارشناس فعالی پیدا نشد.</td></tr>';
        echo '</tbody></table></div>';

        echo '<nav class="nav-tab-wrapper" style="margin-bottom:14px"><a class="nav-tab '.($cohort==='normal'?'nav-tab-active':'').'" href="'.esc_url(add_query_arg('cohort','normal',$base)).'">تخصیص عادی <span class="count">('.esc_html(self::report_safe_number($normal_count, $normal_count !== null)).')</span></a><a class="nav-tab '.($cohort==='1404'?'nav-tab-active':'').'" href="'.esc_url(add_query_arg('cohort','1404',$base)).'">فقط ۱۴۰۴ <span class="count">('.number_format_i18n($y1404_count).')</span></a><a class="nav-tab '.($cohort==='all'?'nav-tab-active':'').'" href="'.esc_url(add_query_arg('cohort','all',$base)).'">همه</a></nav>';

        echo '<div class="bcrm-card bcrm-assign-card"><div class="bcrm-assign-card-head"><h2>جستجو و تخصیص مستقیم</h2></div><form method="get" class="bcrm-assign-search"><input type="hidden" name="page" value="biawin-crm-assignments"><input type="hidden" name="cohort" value="'.esc_attr($cohort).'"><label>شماره موبایل مشتری<br><input name="phone_query" inputmode="tel" value="'.esc_attr($phone_query_raw).'" placeholder="شماره موبایل، مشتری یا یادداشت..."></label><button class="button button-primary">جستجو</button></form>';
        if($phone_query_raw !== ''){
            if($phone_query === '') echo '<div class="notice notice-warning inline"><p>شماره واردشده معتبر نیست.</p></div>';
            elseif(!$phone_matches) echo '<div class="notice notice-warning inline"><p>پروفایلی با این شماره پیدا نشد.</p></div>';
            else {
                echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>نام</th><th>شماره</th><th>CustomerID</th><th>تعداد فاکتور</th><th>مجموع واریزی</th><th>کارشناس فعلی</th><th>تخصیص مستقیم</th></tr></thead><tbody>';
                foreach($phone_matches as $m){
                    echo '<tr><td>'.esc_html($m->display_name?:'پروفایل بدون نام').'</td><td>'.esc_html($m->phone).'</td><td>'.esc_html($m->source_customer_id?:'-').'</td><td>'.number_format_i18n((int)$m->invoice_count).'</td><td>'.esc_html(number_format_i18n((float)$m->total_paid)).'</td><td>'.esc_html($m->agent_name?:'تخصیص‌نیافته').'</td><td>';
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:6px;align-items:center">'; wp_nonce_field('bcrm_assign_customers');
                    echo '<input type="hidden" name="cohort" value="all"><input type="hidden" name="profile_limit" value="1"><input type="hidden" name="customer_ids[]" value="'.(int)$m->id.'"><select name="agent_user_ids[]"><option value="">انتخاب کارشناس…</option>';
                    foreach($agents as $agent) echo '<option value="'.(int)$agent->ID.'">'.esc_html($agent->display_name).'</option>';
                    echo '</select><button class="button button-primary" type="submit" name="action" value="bcrm_assign_customers">تخصیص</button>'.($m->agent_name?'<button class="button" type="submit" name="action" value="bcrm_return_assignments" onclick="return confirm(\'این تخصیص به صف تخصیص‌نیافته برگردد؟\')">برگشت تخصیص</button>':'').'</form></td></tr>';
                }
                echo '</tbody></table>';
            }
        }
        echo '</div>';

        // v0.8.62.16: تخصیص دسته‌ای پروفایل‌ها از فایل اکسل Customer ID به «یک» کارشناس واحد.
        echo '<div class="bcrm-card bcrm-assign-card"><div class="bcrm-assign-card-head"><h2>تخصیص از فایل اکسل (Customer ID)</h2></div><p class="description">فایل اکسل/CSV شامل ستون <strong>CustomerID</strong> را آپلود کنید؛ پروفایل هر مشتری به کارشناس انتخابی تخصیص می‌یابد (اگر پروفایل قبلاً تخصیص خورده باشد، تخصیص فعالش به کارشناس جدید منتقل می‌شود). شناسه‌های تکراری فقط یک‌بار پردازش می‌شوند. فرمت‌های مجاز: XLSX و CSV.</p>';
        echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'پروفایل‌های همه Customer ID های فایل به کارشناس انتخابی تخصیص داده شود؟\')">';
        wp_nonce_field('bcrm_assign_customers_excel');
        echo '<input type="hidden" name="action" value="bcrm_assign_customers_excel"><label>فایل اکسل Customer ID ها<br><input type="file" name="assign_customers_file" accept=".xlsx,.csv" required></label> <label>کارشناس مقصد<br><select name="agent_user_id" required><option value="">انتخاب کارشناس…</option>';
        foreach ($agents as $agent) echo '<option value="'.(int)$agent->ID.'">'.esc_html($agent->display_name.' ('.$agent->user_login.')').'</option>';
        echo '</select></label> <button class="button button-primary">تخصیص از فایل اکسل</button></form></div>';

        $sales_rules = $wpdb->get_results("SELECT m.*, u.display_name agent_name FROM $mapping_table m LEFT JOIN {$wpdb->users} u ON u.ID=m.agent_user_id ORDER BY m.sales_manager ASC LIMIT 300");
        $sales_managers = $wpdb->get_col("SELECT sales_manager FROM $customers WHERE sales_manager IS NOT NULL AND sales_manager<>'' UNION SELECT sales_manager FROM $invoices WHERE sales_manager IS NOT NULL AND sales_manager<>'' ORDER BY sales_manager ASC LIMIT 1000");
        echo '<div class="bcrm-card bcrm-assign-card"><div class="bcrm-assign-card-head"><h2>Rule تخصیص بر اساس مدیر فروش</h2></div><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="bcrm-assign-rule">';
        wp_nonce_field('bcrm_save_sales_manager_mapping');
        echo '<input type="hidden" name="action" value="bcrm_save_sales_manager_mapping"><label>مدیر فروش<br><select name="sales_manager" required><option value="">انتخاب مدیر فروش…</option>';
        foreach($sales_managers as $sales_manager_option) echo '<option value="'.esc_attr((string)$sales_manager_option).'">'.esc_html((string)$sales_manager_option).'</option>';
        echo '</select></label><label>تخصیص به کارشناس<br><select name="agent_user_id" required><option value="">انتخاب کارشناس…</option>';
        foreach($agents as $agent) echo '<option value="'.(int)$agent->ID.'">'.esc_html($agent->display_name.' ('.$agent->user_login.')').'</option>';
        echo '</select></label><button class="button button-primary">ذخیره Rule</button></form>';
        if(!$sales_managers) echo '<p class="description">هنوز مدیر فروشی از اکسل/فاکتورهای واردشده پیدا نشده است. بعد از import ستون «مدیر فروش»، گزینه‌ها اینجا نمایش داده می‌شوند.</p>';
        if($sales_rules){ echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>مدیر فروش</th><th>کارشناس</th></tr></thead><tbody>'; foreach($sales_rules as $rule) echo '<tr><td>'.esc_html($rule->sales_manager).'</td><td>'.esc_html($rule->agent_name ?: ('User #'.(int)$rule->agent_user_id)).'</td></tr>'; echo '</tbody></table>'; }
        echo '</div>';

        echo '<div class="bcrm-card bcrm-assign-card"><div class="bcrm-assign-card-head"><h2>برگشت تخصیص بر اساس کارشناس</h2></div><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="bcrm-assign-rule bcrm-return-rule">';
        wp_nonce_field('bcrm_assign_customers');
        echo '<input type="hidden" name="action" value="bcrm_return_assignments"><input type="hidden" name="cohort" value="'.esc_attr($cohort).'"><label>کارشناس<br><select name="return_agent_user_ids[]" multiple size="6"><option value="all">همه کارشناسان فعال</option>';
        foreach($agents as $agent) echo '<option value="'.(int)$agent->ID.'">'.esc_html($agent->display_name.' ('.$agent->user_login.')').'</option>';
        echo '</select><small style="display:block">برای چند کارشناس Ctrl/Cmd را نگه دارید؛ برای همه، گزینه اول را انتخاب کنید.</small></label><label>وضعیت عملیاتی<br><select name="return_customer_status"><option value="">همه وضعیت‌ها</option><option value="assigned">تخصیص‌یافته</option><option value="followup_needed">نیازمند پیگیری</option><option value="in_review">در حال بررسی</option><option value="needs_review">نیازمند بررسی</option><option value="verified">بررسی‌شده</option><option value="complaint_open">نارضایتی ثبت‌شده</option><option value="closed">بسته‌شده</option></select></label>';
        echo '<label>وضعیت تماس<br><select name="return_contact_outcome"><option value="">همه وضعیت‌های تماس</option><option value="__none__">تماس گرفته نشده</option>';
        foreach(Biawin_CRM_Workflow_Service::contact_outcomes(true) as $outcome) echo '<option value="'.esc_attr($outcome['key']).'">'.esc_html($outcome['label']).'</option>';
        echo '</select></label>';
        echo '<label>وضعیت پروفایل<br><select name="return_profile_completeness"><option value="">همه</option><option value="complete">کامل</option><option value="incomplete">ناقص</option><option value="pending">در انتظار بررسی</option></select></label><label>مسیر مشتری<br><select name="return_journey_state"><option value="">همه مسیرها</option><option value="no_request">بدون درخواست</option><option value="complaint">نارضایتی</option><option value="service_request">درخواست خدمات</option><option value="refund">عودت وجه</option></select></label>';
        echo '<label>حداقل مجموع واریزی<br><input type="text" inputmode="numeric" name="return_min_paid" placeholder="مثلاً 100,000,000"></label><label>حداکثر مجموع واریزی<br><input type="text" inputmode="numeric" name="return_max_paid" placeholder="مثلاً 8,000,000,000"></label><label>تعداد برگشت<br><select name="return_limit_mode"><option value="all">همه موارد مطابق فیلتر</option><option value="latest">n تای آخر تخصیص داده‌شده</option><option value="oldest">n تای قدیمی‌تر</option></select></label><label>n<br><input type="number" min="1" max="10000" name="return_limit" placeholder="مثلاً 50"></label><button class="button" onclick="return confirm(&quot;تخصیص‌های مطابق این فیلتر برگشت داده شود؟&quot;)">برگشت تخصیص‌های فیلترشده</button></form></div>';

        echo '<form method="get" action="'.esc_url(admin_url('admin.php?page=biawin-crm-assignments')).'" class="bcrm-assign-filters"><input type="hidden" name="page" value="biawin-crm-assignments"><input type="hidden" name="cohort" value="'.esc_attr($cohort).'"><label>حداقل مجموع واریزی<br><input name="min_paid" inputmode="numeric" value="'.esc_attr($min?:'').'" placeholder="مثلاً 100,000,000"></label><label>حداکثر مجموع واریزی<br><input name="max_paid" inputmode="numeric" value="'.esc_attr($max?:'').'" placeholder="مثلاً 8,000,000,000"></label><label>حداقل تعداد فاکتور<br><input type="number" min="0" name="min_invoices" value="'.esc_attr($min_invoices?:'').'" placeholder="مثلاً 0 عدد"></label><label>حداکثر تعداد فاکتور<br><input type="number" min="0" name="max_invoices" value="'.esc_attr($max_invoices?:'').'" placeholder="مثلاً 0 عدد"></label><label>وضعیت تماس<br><select name="contact_outcome"><option value="" '.selected($assign_contact,'',false).'>همه وضعیت‌های تماس</option><option value="__none__" '.selected($assign_contact,'__none__',false).'>تماس گرفته نشده</option>';
        foreach(Biawin_CRM_Workflow_Service::contact_outcomes(true) as $outcome) echo '<option value="'.esc_attr($outcome['key']).'" '.selected($assign_contact,(string)$outcome['key'],false).'>'.esc_html($outcome['label']).'</option>';
        echo '</select></label>';
        echo '<label>وضعیت عودت<br><select name="refund_tag"><option value="any" '.selected($refund_tag,'any',false).'>همه مشتریان</option><option value="REFUND_CONFIRMED" '.selected($refund_tag,'REFUND_CONFIRMED',false).'>فقط عودت قطعی (اکسل)</option><option value="REFUND_REPORTED" '.selected($refund_tag,'REFUND_REPORTED',false).'>فقط عودت اعلامی (کارشناس)</option><option value="BOTH" '.selected($refund_tag,'BOTH',false).'>هر تگ عودت</option></select></label>';
        echo '<button class="button">اعمال فیلتر</button></form>';
        echo '<div class="bcrm-assign-kpis" style="margin-top:12px"><div><span>مطابق فیلتر فعلی</span><strong>'.esc_html(self::report_safe_number($filter_total, $filter_total !== null)).'</strong><small>پروفایل</small></div><div><span>قابل تخصیص با گزینه فعلی</span><strong>'.esc_html(self::report_safe_number($filter_unassigned, $filter_unassigned !== null)).'</strong><small>تخصیص‌نیافته</small></div><div><span>قبلاً تخصیص‌خورده</span><strong>'.esc_html(self::report_safe_number($filter_assigned, $filter_assigned !== null)).'</strong><small>پروفایل</small></div><div><span>نمایش در جدول</span><strong>'.esc_html(self::report_safe_number(count((array)$rows), !empty($candidate_rows['trusted']))).'</strong><small>از ۵۰۰ مورد اول</small></div></div>';
        if ($filter_unassigned === 0) {
            echo '<div class="notice notice-warning inline"><p>با فیلترهای فعلی هیچ پروفایل تخصیص‌نیافته‌ای باقی نمانده است. برای بررسی، وضعیت تماس را روی «همه وضعیت‌های تماس» بگذارید، تعداد فاکتور را بازتر کنید، یا گزینه «فقط پروفایل‌های تخصیص‌نیافته» را هنگام تخصیص مجدد بردارید.</p></div>';
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_assign_customers');
        echo '<input type="hidden" name="cohort" value="'.esc_attr($cohort).'"><input type="hidden" name="min_paid" value="'.esc_attr($min ?: '').'"><input type="hidden" name="max_paid" value="'.esc_attr($max ?: '').'"><input type="hidden" name="min_invoices" value="'.esc_attr($min_invoices ?: '').'"><input type="hidden" name="max_invoices" value="'.esc_attr($max_invoices ?: '').'"><input type="hidden" name="contact_outcome" value="'.esc_attr($assign_contact).'"><input type="hidden" name="refund_tag" value="'.esc_attr($refund_tag).'"><div class="bcrm-card bcrm-assign-card bcrm-assign-execute"><div><strong>کارشناسان</strong><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:10px;max-height:260px;overflow:auto;border:1px solid #dbe3ef;border-radius:10px;padding:10px;background:#fff">';
        foreach($agents as $agent) echo '<label style="display:flex;align-items:center;gap:8px;margin:0"><input type="checkbox" name="agent_user_ids[]" value="'.(int)$agent->ID.'"> <span>'.esc_html($agent->display_name.' ('.$agent->user_login.')').'</span></label>';
        echo '</div><small style="display:block;margin-top:6px">برای تخصیص دستی، یک یا چند کارشناس را تیک بزنید. اگر «توزیع بین همه» را فعال کنید، انتخاب‌های دستی نادیده گرفته می‌شود.</small></div><label>تعداد پروفایل برای تخصیص<br><input type="number" name="profile_limit" min="1" max="10000" value="100" required></label><div class="bcrm-assign-switches"><label><input type="checkbox" name="only_unassigned" value="1" checked> فقط پروفایل‌های تخصیص‌نیافته</label><label><input type="checkbox" name="use_all_agents" value="1"> توزیع بین همه کارشناسان تماس فعال</label><label><input type="checkbox" name="use_sales_manager_mapping" value="1"> اولویت با Rule مدیر فروش</label></div><div class="bcrm-assign-actions"><button class="button button-primary" type="submit" name="action" value="bcrm_assign_customers">اجرای تخصیص</button><button class="button" type="submit" name="action" value="bcrm_return_assignments" onclick="return confirm(&quot;تخصیص پروفایل‌های انتخاب‌شده برگشت داده شود؟&quot;)">برگشت انتخاب‌شده‌ها</button></div></div>';
        echo '<p class="description">فیلتر تعداد فاکتور بر اساس تعداد فاکتورهای متصل به کل پروفایل محاسبه می‌شود. تخصیص خودکار فقط از تب فعلی انجام می‌شود؛ یعنی از تب ۱۴۰۴ هیچ پروفایل عادی وارد تخصیص نمی‌شود و برعکس.</p>';
        echo '<table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll(\'.bcrm-assign-check\').forEach(x=>x.checked=this.checked)"></td><th>CustomerID</th><th>کد مشتری</th><th>نام</th><th>موبایل</th><th>تعداد فاکتور</th><th>مجموع واریزی</th><th>آخرین وضعیت تماس</th><th>ثبت‌کننده تماس</th><th>تگ تخصیص</th><th>وضعیت عودت</th><th>پروفایل</th><th>مدیر فروش</th><th>کارشناس فعلی</th></tr></thead><tbody>';
        if(!$rows) echo '<tr><td colspan="14">پروفایلی در این بخش وجود ندارد.</td></tr>';
        foreach($rows as $r) {
            $r_tags = class_exists('Biawin_CRM_Refund_Register') ? Biawin_CRM_Refund_Register::tags_for((int)$r->id) : [];
            $refund_badge = '—';
            if (in_array('REFUND_CONFIRMED', $r_tags, true) && in_array('REFUND_REPORTED', $r_tags, true)) $refund_badge = '<span class="bcrm-badge is-danger">قطعی</span> <span class="bcrm-badge is-warning">اعلامی</span>';
            elseif (in_array('REFUND_CONFIRMED', $r_tags, true)) $refund_badge = '<span class="bcrm-badge is-danger">قطعی</span>';
            elseif (in_array('REFUND_REPORTED', $r_tags, true)) $refund_badge = '<span class="bcrm-badge is-warning">اعلامی</span>';
            echo '<tr><th class="check-column"><input class="bcrm-assign-check" type="checkbox" name="customer_ids[]" value="'.(int)$r->id.'"></th><td>'.esc_html($r->source_customer_id?:'-').'</td><td>'.esc_html($r->customer_code?:'-').'</td><td>'.esc_html($r->display_name?:'پروفایل بدون نام').'</td><td>'.esc_html($r->phone?:'-').'</td><td>'.number_format_i18n((int)$r->invoice_count).'</td><td>'.esc_html(number_format_i18n((float)$r->total_paid)).'</td><td>'.esc_html($r->latest_contact_outcome ?: 'تماس گرفته نشده').'</td><td>'.esc_html($r->latest_contact_agent ?: '-').'</td><td>'.(($r->assignment_cohort??'')==='1404'?'<span class="bcrm-badge is-warning">۱۴۰۴</span>':'—').'</td><td>'.$refund_badge.'</td><td>'.esc_html($r->profile_completeness).'</td><td>'.esc_html($r->sales_manager?:'-').'</td><td>'.esc_html($r->agent_name?:'تخصیص‌نیافته').'</td></tr>';
        }
        echo '</tbody></table></form></div>';

        $recent_result = Biawin_CRM_Reporting_Consumer_Adapter::management_recent_assignment_rows(30, $assignment_scope);
        $recent_assignments = !empty($recent_result['trusted']) && isset($recent_result['data']['rows']) && is_array($recent_result['data']['rows']) ? array_map(static fn($row)=>(object)$row, $recent_result['data']['rows']) : [];
        echo '<div class="bcrm-card bcrm-assign-card"><div class="bcrm-assign-card-head"><h2>آخرین تخصیص‌های فعال</h2></div><table class="widefat striped"><thead><tr><th>زمان</th><th>مشتری</th><th>کد/CustomerID</th><th>کارشناس فعلی</th><th>تخصیص‌دهنده</th><th>آخرین تماس</th><th>ثبت‌کننده تماس</th></tr></thead><tbody>';
        if(!$recent_assignments) echo '<tr><td colspan="7">هنوز تخصیص فعالی ثبت نشده است.</td></tr>';
        foreach($recent_assignments as $ra) echo '<tr><td>'.esc_html($ra->assigned_at ?: '-').'</td><td>'.esc_html($ra->display_name ?: ('Customer #'.(int)$ra->customer_id)).'</td><td>'.esc_html(($ra->source_customer_id ?: $ra->customer_code) ?: '-').'</td><td>'.esc_html($ra->agent_name ?: ('User #'.(int)$ra->agent_user_id)).'</td><td>'.esc_html($ra->assigned_by_name ?: ('User #'.(int)$ra->assigned_by)).'</td><td>'.esc_html($ra->latest_contact_outcome ?: 'تماس گرفته نشده').'</td><td>'.esc_html($ra->latest_contact_agent ?: '-').'</td></tr>';
        echo '</tbody></table><p class="description">اگر قبلاً پیام موفقیت گرفته‌اید، مقصد آن تخصیص‌ها را از این جدول ببینید. برای تخصیص مجدد پروفایل‌های قبلاً تخصیص‌خورده، گزینه «فقط پروفایل‌های تخصیص‌نیافته» را بردارید یا ابتدا از بخش برگشت تخصیص استفاده کنید.</p></div>';
    }

    public static function handle_save_sales_manager_mapping(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_save_sales_manager_mapping')) wp_die('دسترسی غیرمجاز است.');
        $sales_manager = sanitize_text_field(wp_unslash($_POST['sales_manager'] ?? ''));
        $agent_id = absint($_POST['agent_user_id'] ?? 0);
        $agent = $agent_id ? get_user_by('id', $agent_id) : false;
        if ($sales_manager === '' || !$agent || !in_array('bcrm_agent', (array)$agent->roles, true) || !$agent->has_cap('bcrm_work_cases') || (bool)get_user_meta($agent_id,'bcrm_disabled',true)) {
            wp_die('مدیر فروش یا کارشناس انتخاب‌شده معتبر نیست.');
        }
        global $wpdb;
        $table = Biawin_CRM_DB::table('sales_manager_mapping');
        $now = Biawin_CRM_DB::now();
        $ok = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (sales_manager,agent_user_id,created_by,created_at,updated_at) VALUES (%s,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE agent_user_id=VALUES(agent_user_id), updated_at=VALUES(updated_at)",
            $sales_manager,
            $agent_id,
            get_current_user_id(),
            $now,
            $now
        ));
        if ($ok === false) wp_die('ذخیره Rule مدیر فروش انجام نشد: '.esc_html($wpdb->last_error ?: 'خطای دیتابیس'));
        Biawin_CRM_DB::log('assignment_rule', null, 'sales_manager_mapping_saved', null, ['sales_manager'=>$sales_manager,'agent_user_id'=>$agent_id]);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-assignments','mapping_saved'=>'1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_assignment(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_assign_customers')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $cohort=sanitize_key($_POST['cohort']??'normal'); if(!in_array($cohort,['normal','1404','all'],true))$cohort='normal';
        $raw=$_POST['customer_ids']??[]; if(!is_array($raw))$raw=explode(',',sanitize_text_field(wp_unslash($raw)));
        $ids=array_values(array_unique(array_filter(array_map('absint',$raw))));
        $limit=max(1,min(10000,absint($_POST['profile_limit']??100)));
        $min=Biawin_CRM_Normalizer::money($_POST['min_paid']??0); $max=Biawin_CRM_Normalizer::money($_POST['max_paid']??0);
        $min_invoices=isset($_POST['min_invoices']) && $_POST['min_invoices']!=='' ? max(0,absint($_POST['min_invoices'])) : 0;
        $max_invoices=isset($_POST['max_invoices']) && $_POST['max_invoices']!=='' ? max(0,absint($_POST['max_invoices'])) : 0;
        $assign_contact=isset($_POST['contact_outcome']) ? sanitize_key((string)$_POST['contact_outcome']) : '';
        $valid_assign_contacts = array_merge(['', '__none__'], array_map(static fn($o) => (string)$o['key'], Biawin_CRM_Workflow_Service::contact_outcomes(true)));
        if(!in_array($assign_contact,$valid_assign_contacts,true)) $assign_contact='__none__';
        $assign_refund_tag=Biawin_CRM_Refund_Register::sanitize_tag_filter($_POST['refund_tag'] ?? 'any');
        $c=Biawin_CRM_DB::table('customers');
        $invoice_table=Biawin_CRM_DB::table('invoices');
        $interactions_table=Biawin_CRM_DB::table('interactions');
        $refund_tag_refund_ok=static function(int $cid) use ($wpdb, $assign_refund_tag): bool {
            if($assign_refund_tag==='any') return true;
            $tags_table=Biawin_CRM_DB::table('customer_tags');
            if($assign_refund_tag==='BOTH') $sql="SELECT id FROM $tags_table WHERE customer_id=%d AND tag IN ('REFUND_REPORTED','REFUND_CONFIRMED') LIMIT 1";
            else $sql=$wpdb->prepare("SELECT id FROM $tags_table WHERE customer_id=%d AND tag=%s LIMIT 1",$cid,$assign_refund_tag);
            return (bool)$wpdb->get_var($sql);
        };
        if(!$ids){
            $where=['c.total_paid >= %f']; $params=[$min];
            if(class_exists('Biawin_CRM_Production_Readiness') && Biawin_CRM_Production_Readiness::guard_enabled()) $where[]=Biawin_CRM_Production_Readiness::sql_not_quarantined('c');
            if($cohort==='1404')$where[]="c.assignment_cohort='1404'"; elseif($cohort==='normal')$where[]="(c.assignment_cohort<>'1404' OR c.assignment_cohort IS NULL)";
            if($max>0){$where[]='c.total_paid <= %f';$params[]=$max;}
            if($min_invoices>0){$where[]="(SELECT COUNT(*) FROM $invoice_table fi WHERE fi.customer_id=c.id) >= %d";$params[]=$min_invoices;}
            if($max_invoices>0){$where[]="(SELECT COUNT(*) FROM $invoice_table fi WHERE fi.customer_id=c.id) <= %d";$params[]=$max_invoices;}
            if(!empty($_POST['only_unassigned'])) $where[]='NOT EXISTS (SELECT 1 FROM '.Biawin_CRM_DB::table('assignments')." a WHERE a.customer_id=c.id AND a.status='assigned')";
            if($assign_refund_tag==='BOTH') $where[]="EXISTS (SELECT 1 FROM ".Biawin_CRM_DB::table('customer_tags')." rt WHERE rt.customer_id=c.id AND rt.tag IN ('REFUND_REPORTED','REFUND_CONFIRMED'))";
            elseif($assign_refund_tag!=='any') $where[]=$wpdb->prepare("EXISTS (SELECT 1 FROM ".Biawin_CRM_DB::table('customer_tags')." rt WHERE rt.customer_id=c.id AND rt.tag=%s)",$assign_refund_tag);
            if($assign_contact==='__none__'){
                $where[]="NOT EXISTS (SELECT 1 FROM $interactions_table ai WHERE ai.customer_id=c.id)";
            } elseif($assign_contact!==''){
                $assign_contact_label=Biawin_CRM_Workflow_Service::contact_outcome_label($assign_contact,$assign_contact);
                $latest_outcome_key="(SELECT NULLIF(ai.outcome_key,'') FROM $interactions_table ai WHERE ai.customer_id=c.id ORDER BY ai.id DESC LIMIT 1)";
                $latest_outcome_label="(SELECT NULLIF(ai.outcome,'') FROM $interactions_table ai WHERE ai.customer_id=c.id ORDER BY ai.id DESC LIMIT 1)";
                $where[]="(COALESCE($latest_outcome_key,'')=%s OR (COALESCE($latest_outcome_key,'')='' AND COALESCE($latest_outcome_label,'')=%s))";
                $params[]=$assign_contact;
                $params[]=$assign_contact_label;
            }
            $sql="SELECT c.id FROM $c c WHERE ".implode(' AND ',$where)." ORDER BY c.total_paid DESC,c.id ASC LIMIT %d"; $params[]=$limit;
            $ids=array_map('intval',$wpdb->get_col($wpdb->prepare($sql,...$params)));
        } else {
            $ids=array_slice($ids,0,$limit);
            $allowed=[];
            foreach($ids as $id){
                if(class_exists('Biawin_CRM_Production_Readiness') && Biawin_CRM_Production_Readiness::guard_enabled() && Biawin_CRM_Production_Readiness::is_quarantined_customer((int)$id)) continue;
                $row=$wpdb->get_row($wpdb->prepare("SELECT c.assignment_cohort,c.total_paid,(SELECT COUNT(*) FROM $invoice_table fi WHERE fi.customer_id=c.id) invoice_count,(SELECT COALESCE(NULLIF(li.outcome_key,''),NULLIF(li.outcome,''),'') FROM $interactions_table li WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact_value,(SELECT COUNT(*) FROM $interactions_table li WHERE li.customer_id=c.id) interaction_count FROM $c c WHERE c.id=%d",$id));
                if(!$row) continue;
                if($cohort==='1404' && $row->assignment_cohort!=='1404') continue;
                if($cohort==='normal' && $row->assignment_cohort==='1404') continue;
                if($min>0 && (float)$row->total_paid<$min) continue;
                if($max>0 && (float)$row->total_paid>$max) continue;
                if($min_invoices>0 && (int)$row->invoice_count<$min_invoices) continue;
                if($max_invoices>0 && (int)$row->invoice_count>$max_invoices) continue;
                if(!$refund_tag_refund_ok((int)$id)) continue;
                if($assign_contact==='__none__') {
                    if((int)$row->interaction_count>0) continue;
                } elseif($assign_contact!==''){
                    $assign_contact_label=Biawin_CRM_Workflow_Service::contact_outcome_label($assign_contact,$assign_contact);
                    if((string)$row->latest_contact_value!==$assign_contact && (string)$row->latest_contact_value!==$assign_contact_label) continue;
                }
                $allowed[]=$id;
            }
            $ids=$allowed;
        }
        if(!$ids) {
            set_transient('bcrm_assignment_error_' . get_current_user_id(), [
                'message' => 'هیچ پروفایل قابل تخصیصی با شرایط انتخاب‌شده پیدا نشد. شمارنده‌های فیلتر را ببینید؛ اگر عدد قابل تخصیص صفر است، وضعیت تماس/تعداد فاکتور/فقط تخصیص‌نیافته را تغییر دهید.',
            ], 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect(add_query_arg([
                'page'=>'biawin-crm-assignments',
                'cohort'=>$cohort,
                'min_paid'=>sanitize_text_field((string)wp_unslash($_POST['min_paid'] ?? '')),
                'max_paid'=>sanitize_text_field((string)wp_unslash($_POST['max_paid'] ?? '')),
                'min_invoices'=>sanitize_text_field((string)wp_unslash($_POST['min_invoices'] ?? '')),
                'max_invoices'=>sanitize_text_field((string)wp_unslash($_POST['max_invoices'] ?? '')),
                'contact_outcome'=>$assign_contact,
            ], admin_url('admin.php')));
            exit;
        }

        $manual_agent_ids = array_values(array_unique(array_filter(array_map('absint',(array)($_POST['agent_user_ids']??[])))));
        $use_all_agents = !empty($_POST['use_all_agents']);
        $use_sales_manager_mapping = !empty($_POST['use_sales_manager_mapping']) && ($use_all_agents || !$manual_agent_ids);
        if(!empty($_POST['use_sales_manager_mapping']) && $manual_agent_ids && !$use_all_agents) {
            Biawin_CRM_DB::log('assignment_rule', null, 'sales_manager_mapping_skipped_for_manual_agents', null, ['manual_agent_user_ids'=>$manual_agent_ids]);
        }
        if($use_all_agents) {
            $agents=array_values(array_filter(get_users(['role__in'=>['bcrm_agent']]),fn($u)=>!(bool)get_user_meta($u->ID,'bcrm_disabled',true)&&$u->has_cap('bcrm_work_cases')));
        } else {
            $agents=[];
            foreach($manual_agent_ids as $aid){$u=get_user_by('id',$aid);if($u&&in_array('bcrm_agent',(array)$u->roles,true)&&$u->has_cap('bcrm_work_cases')&&!(bool)get_user_meta($aid,'bcrm_disabled',true))$agents[]=$u;}
        }
        if(!$agents && !$use_sales_manager_mapping) wp_die('حداقل یک کارشناس فعال انتخاب کنید یا گزینه توزیع بین همه کارشناسان را بزنید.');
        $assigned=0;
        $assigned_details=[];
        $fallback_idx = 0;
        foreach($ids as $id){$agent=$use_sales_manager_mapping ? self::agent_for_sales_manager_rule($id) : null;
            $used_rule = (bool)$agent;
            if(!$agent && $agents){$agent=$agents[$fallback_idx%count($agents)];$fallback_idx++;}
            if(!$agent) continue;
            $wpdb->query('START TRANSACTION');
            // Serialize assignment changes per customer. Without this row lock two concurrent supervisor
            // requests can both insert an active assignment and make the same customer visible to two agents.
            $locked_customer=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $c WHERE id=%d FOR UPDATE",$id));
            if(!$locked_customer){$wpdb->query('ROLLBACK');continue;}
            $assignments_table=Biawin_CRM_DB::table('assignments');
            $active_rows=$wpdb->get_results($wpdb->prepare("SELECT id,agent_user_id FROM $assignments_table WHERE customer_id=%d AND status='assigned' FOR UPDATE",$id));
            if(count((array)$active_rows)>1){
                $keep_id=(int)$active_rows[0]->id;
                $close_ids=array_values(array_filter(array_map(static fn($row)=>(int)$row->id,(array)$active_rows), static fn($row_id)=>$row_id!==$keep_id));
                if($close_ids){
                    $placeholders=implode(',',array_fill(0,count($close_ids),'%d'));
                    $close_args=array_merge([Biawin_CRM_DB::now()],$close_ids);
                    $closed=$wpdb->query($wpdb->prepare("UPDATE $assignments_table SET status='duplicate_closed',completed_at=%s WHERE id IN ($placeholders) AND status='assigned'",...$close_args));
                    if($closed===false){$wpdb->query('ROLLBACK');wp_die('اصلاح تخصیص تکراری انجام نشد: '.esc_html($wpdb->last_error?:'خطای دیتابیس'));}
                }
                $active_rows=$wpdb->get_results($wpdb->prepare("SELECT id,agent_user_id FROM $assignments_table WHERE customer_id=%d AND status='assigned' FOR UPDATE",$id));
            }
            $now=Biawin_CRM_DB::now();
            if($active_rows){
                $primary=(int)$active_rows[0]->id;
                $new=$wpdb->update($assignments_table,['agent_user_id'=>(int)$agent->ID,'assigned_by'=>get_current_user_id(),'assigned_at'=>$now,'completed_at'=>null],['id'=>$primary,'status'=>'assigned']);
            } else {
                $new=$wpdb->insert($assignments_table,['customer_id'=>$id,'agent_user_id'=>(int)$agent->ID,'status'=>'assigned','assigned_by'=>get_current_user_id(),'assigned_at'=>$now]);
            }
            $upd=$wpdb->update($c,['status'=>'assigned','updated_at'=>Biawin_CRM_DB::now()],['id'=>$id]);
            if($new===false||$upd===false){$wpdb->query('ROLLBACK');wp_die('ذخیره تخصیص انجام نشد: '.esc_html($wpdb->last_error?:'خطای دیتابیس'));}
            $wpdb->query('COMMIT');$assigned++; $assigned_details[]=['customer_id'=>(int)$id,'agent_user_id'=>(int)$agent->ID,'agent'=>$agent->display_name ?: $agent->user_login]; Biawin_CRM_DB::log('customer',$id,'assigned_to_agent',null,['agent_user_id'=>(int)$agent->ID,'batch_count'=>count($ids),'assignment_cohort'=>$cohort,'min_invoices'=>$min_invoices,'max_invoices'=>$max_invoices,'sales_manager_rule'=>$used_rule]);
            Biawin_CRM_Workflow_Service::activity($id,'customer_assigned','پرونده به کارشناس تخصیص داده شد.','customer',$id,['agent_user_id'=>(int)$agent->ID,'assignment_cohort'=>$cohort,'sales_manager_rule'=>$used_rule]);
        }
        if($assigned_details){
            $detail_ids=array_column($assigned_details,'customer_id');
            $placeholders=implode(',',array_fill(0,count($detail_ids),'%d'));
            $customer_names=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.display_name,c.source_customer_id,c.customer_code,(SELECT COALESCE(NULLIF(li.outcome,''),NULLIF(li.outcome_key,''),'') FROM $interactions_table li WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact,(SELECT u.display_name FROM $interactions_table li LEFT JOIN {$wpdb->users} u ON u.ID=li.agent_user_id WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1) latest_contact_agent FROM $c c WHERE c.id IN ($placeholders)",...$detail_ids),OBJECT_K);
            foreach($assigned_details as &$detail){
                $customer=$customer_names[$detail['customer_id']]??null;
                $label=$customer ? (($customer->display_name ?: 'پروفایل بدون نام') . ' (' . (($customer->source_customer_id ?: $customer->customer_code) ?: ('#'.$detail['customer_id'])) . ')') : ('Customer #'.$detail['customer_id']);
                $detail['customer']=$label;
                $detail['latest_contact']=$customer ? (string)$customer->latest_contact : '';
                $detail['latest_contact_agent']=$customer ? (string)$customer->latest_contact_agent : '';
            }
            unset($detail);
        }
        set_transient('bcrm_assignment_result_' . get_current_user_id(), ['count'=>$assigned,'rows'=>$assigned_details,'created_at'=>Biawin_CRM_DB::now()], 15 * MINUTE_IN_SECONDS);
        update_option('bcrm_dashboard_cache_buster', (string) microtime(true), false);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-assignments','cohort'=>$cohort,'assigned'=>$assigned],admin_url('admin.php')));exit;
    }

    /**
     * v0.8.62.16: تخصیص دسته‌ای پروفایل‌ها از فایل اکسل/CSV شامل Customer ID به «یک» کارشناس واحد.
     * قواعد و قفل‌گذاری عیناً مثل handle_assignment: قفل ردیف مشتری (FOR UPDATE)، اصلاح تخصیص فعال تکراری،
     * reassign یا insert تخصیص فعال، تغییر status مشتری، لاگ assigned_to_agent + activity customer_assigned.
     * @return array{ok:bool,message:string,assigned:int,duplicate_ids:int,not_found:array,errors:array}
     */
    public static function assign_profiles_from_customer_file(string $path, string $file_name, int $agent_id): array
    {
        global $wpdb;
        if (!class_exists('Biawin_CRM_Refund_Register')) {
            require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-refund-register.php';
        }
        $ext = strtolower(pathinfo(sanitize_file_name($file_name), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            return ['ok'=>false,'message'=>'فقط فایل XLSX یا CSV پذیرفته می‌شود. فایل XLS قدیمی را اول به XLSX تبدیل کنید.','assigned'=>0,'duplicate_ids'=>0,'not_found'=>[],'errors'=>[]];
        }
        if (!is_file($path)) {
            return ['ok'=>false,'message'=>'فایل آپلودشده پیدا نشد.','assigned'=>0,'duplicate_ids'=>0,'not_found'=>[],'errors'=>[]];
        }
        $agent_id = absint($agent_id);
        $agent = $agent_id ? get_user_by('id', $agent_id) : false;
        if (!$agent || !in_array('bcrm_agent', (array)$agent->roles, true) || !$agent->has_cap('bcrm_work_cases') || (bool)get_user_meta($agent_id,'bcrm_disabled',true)) {
            return ['ok'=>false,'message'=>'کارشناس مقصد معتبر یا فعال نیست (کارشناس تماس با دسترسی bcrm_work_cases لازم است).','assigned'=>0,'duplicate_ids'=>0,'not_found'=>[],'errors'=>[]];
        }
        $parsed = Biawin_CRM_Refund_Register::parse_upload($path, $file_name);
        if (!empty($parsed['errors'])) {
            return ['ok'=>false,'message'=>'خواندن فایل انجام نشد: ' . implode(' | ', array_map('strval', $parsed['errors'])),'assigned'=>0,'duplicate_ids'=>0,'not_found'=>[],'errors'=>(array)$parsed['errors']];
        }
        $ids = [];
        $dup = 0;
        foreach ((array)$parsed['rows'] as $row) {
            $raw = '';
            foreach ((array)$row as $header => $value) {
                if (Biawin_CRM_Refund_Register::canonical_header((string)$header) === 'customer id') {
                    $raw = trim((string)$value);
                    break;
                }
            }
            if ($raw === '') {
                foreach ((array)$row as $value) {
                    if (trim((string)$value) !== '') { $raw = trim((string)$value); break; }
                }
            }
            if ($raw === '') continue;
            $key = strtolower($raw);
            if (isset($ids[$key])) { $dup++; continue; }
            $ids[$key] = $raw;
        }
        if (!$ids) {
            return ['ok'=>false,'message'=>'هیچ Customer ID معتبری در فایل پیدا نشد (ستون CustomerID یا اولین ستون پر).','assigned'=>0,'duplicate_ids'=>0,'not_found'=>[],'errors'=>[]];
        }
        if (count($ids) > 10000) $ids = array_slice($ids, 0, 10000, true);

        $customers = Biawin_CRM_DB::table('customers');
        $assignments_table = Biawin_CRM_DB::table('assignments');
        $assigned = 0;
        $not_found = [];
        $details = [];
        $line = 1; // سطر ۱ اکسل = سرستون
        foreach ($ids as $key => $raw) {
            $line++;
            $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE source_customer_id=%s", $raw));
            if (!$id && ctype_digit($raw)) $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE id=%d", (int)$raw));
            if (!$id && ctype_digit($raw)) $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE customer_code=%s", $raw));
            if (!$id) { $not_found[] = ['row'=>$line,'key'=>$raw]; continue; }
            $wpdb->query('START TRANSACTION');
            // همان قفل‌گذاری handle_assignment — دو درخواست هم‌زمان نباید یک مشتری را دوبار تخصیص فعال بگیرند.
            $locked = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE id=%d FOR UPDATE", $id));
            if (!$locked) { $wpdb->query('ROLLBACK'); $not_found[] = ['row'=>$line,'key'=>$raw]; continue; }
            $active_rows = $wpdb->get_results($wpdb->prepare("SELECT id,agent_user_id FROM $assignments_table WHERE customer_id=%d AND status='assigned' FOR UPDATE", $id));
            if (count((array)$active_rows) > 1) {
                $keep_id = (int)$active_rows[0]->id;
                $close_ids = array_values(array_filter(array_map(static fn($row)=>(int)$row->id,(array)$active_rows), static fn($row_id)=>$row_id!==$keep_id));
                if ($close_ids) {
                    $placeholders = implode(',', array_fill(0, count($close_ids), '%d'));
                    $close_args = array_merge([Biawin_CRM_DB::now()], $close_ids);
                    $closed = $wpdb->query($wpdb->prepare("UPDATE $assignments_table SET status='duplicate_closed',completed_at=%s WHERE id IN ($placeholders) AND status='assigned'", ...$close_args));
                    if ($closed === false) { $wpdb->query('ROLLBACK'); $errors[] = ['row'=>$line,'key'=>$raw,'reason'=>'duplicate repair failed']; continue; }
                }
                $active_rows = $wpdb->get_results($wpdb->prepare("SELECT id,agent_user_id FROM $assignments_table WHERE customer_id=%d AND status='assigned' FOR UPDATE", $id));
            }
            $now = Biawin_CRM_DB::now();
            if ($active_rows) {
                $primary = (int)$active_rows[0]->id;
                $new = $wpdb->update($assignments_table, ['agent_user_id'=>$agent_id,'assigned_by'=>get_current_user_id(),'assigned_at'=>$now,'completed_at'=>null], ['id'=>$primary,'status'=>'assigned']);
            } else {
                $new = $wpdb->insert($assignments_table, ['customer_id'=>$id,'agent_user_id'=>$agent_id,'status'=>'assigned','assigned_by'=>get_current_user_id(),'assigned_at'=>$now]);
            }
            $upd = $wpdb->update($customers, ['status'=>'assigned','updated_at'=>$now], ['id'=>$id]);
            if ($new === false || $upd === false) { $wpdb->query('ROLLBACK'); $errors[] = ['row'=>$line,'key'=>$raw,'reason'=>'db']; continue; }
            $wpdb->query('COMMIT');
            $assigned++;
            $details[] = ['customer_id'=>$id,'agent_user_id'=>$agent_id,'agent'=>$agent->display_name ?: $agent->user_login];
            Biawin_CRM_DB::log('customer', $id, 'assigned_to_agent', null, ['agent_user_id'=>$agent_id,'source'=>'excel','excel_row'=>$line,'customer_key'=>$raw]);
            Biawin_CRM_Workflow_Service::activity($id, 'customer_assigned', 'پروفایل با فایل اکسل به کارشناس تخصیص داده شد.', 'customer', $id, ['agent_user_id'=>$agent_id,'source'=>'excel']);
        }

        $not_found_count = count($not_found);
        $message = sprintf('%s پروفایل به کارشناس «%s» تخصیص داده شد.', number_format_i18n($assigned), $agent->display_name);
        if ($not_found_count) $message .= ' ' . sprintf('%s شناسه در سیستم پیدا نشد.', number_format_i18n($not_found_count));
        if ($dup) $message .= ' ' . sprintf('%s شناسه تکراری نادیده گرفته شد.', number_format_i18n($dup));
        Biawin_CRM_DB::log('customer', null, 'assignment_excel_batch', null, [
            'file_name'=>$file_name,'agent_user_id'=>$agent_id,'unique_ids'=>count($ids),
            'duplicates'=>$dup,'assigned'=>$assigned,'not_found'=>$not_found_count,
        ]);
        return ['ok'=>true,'message'=>$message,'assigned'=>$assigned,'duplicate_ids'=>$dup,'not_found'=>$not_found,'errors'=>isset($errors)?$errors:[]];
    }

    /**
     * v0.8.62.16: هندلر آپلود اکسل Customer ID ها در صفحه «تخصیص شماره موبایل».
     */
    public static function handle_assign_customers_excel(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_assign_customers_excel')) wp_die('دسترسی غیرمجاز است.');
        $agent_id = absint($_POST['agent_user_id'] ?? 0);
        if (!$agent_id) wp_die('کارشناس مقصد انتخاب نشده است.');
        $file = $_FILES['assign_customers_file'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            wp_die('فایل اکسل انتخاب نشده یا آپلود آن کامل نشد.');
        }
        $name = sanitize_file_name((string)($file['name'] ?? 'assign-customers.xlsx'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) wp_die('فقط فایل XLSX یا CSV پذیرفته می‌شود. فایل XLS قدیمی را اول به XLSX تبدیل کنید.');
        $result = self::assign_profiles_from_customer_file((string)$file['tmp_name'], $name, $agent_id);
        if (empty($result['ok'])) {
            set_transient('bcrm_assignment_error_' . get_current_user_id(), ['message' => $result['message'] ?? 'تخصیص از فایل اکسل انجام نشد.'], 5 * MINUTE_IN_SECONDS);
            wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-assignments'], admin_url('admin.php')));
            exit;
        }
        // جزئیات per-ID (شناسه ناشناخته‌ها) را با همان سازوکار جزئیات آخرین تخصیص نمایش می‌دهیم.
        $details = [];
        foreach ((array)($result['not_found'] ?? []) as $nf) {
            $details[] = ['customer_id'=>0,'agent_user_id'=>$agent_id,'agent'=>'',
                'customer'=>'شناسه ناشناخته: ' . (string)($nf['key'] ?? '') . ' (سطر ' . (int)($nf['row'] ?? 0) . ')',
                'latest_contact'=>'','latest_contact_agent'=>''];
        }
        set_transient('bcrm_assignment_result_' . get_current_user_id(), [
            'count'=>$result['assigned'],'rows'=>$details,'summary'=>$result['message'],'created_at'=>Biawin_CRM_DB::now(),
        ], 15 * MINUTE_IN_SECONDS);
        update_option('bcrm_dashboard_cache_buster', (string) microtime(true), false);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-assignments','assigned'=>$result['assigned']], admin_url('admin.php')));
        exit;
    }

    public static function deficiency_page(): void
    {
        Biawin_CRM_Deficiency_Workflow::render_admin_page('deficiency');
    }

    public static function handle_deficiency_action(): void
    {
        $case_id = absint($_POST['case_id'] ?? 0);
        if (!$case_id || !check_admin_referer('bcrm_deficiency_action_' . $case_id)) wp_die('درخواست معتبر نیست.');
        $result = Biawin_CRM_Deficiency_Workflow::process_action(wp_unslash($_POST));
        $case = self::deficiency_case_for_redirect($case_id);
        $page = (!empty($case) && (string)$case->current_department === 'finance') ? 'biawin-crm-financial' : 'biawin-crm-deficiency';
        $args = ['page'=>$page, 'case_id'=>$case_id];
        if (!empty($result['ok'])) $args['bcrm_message'] = $result['message'] ?? 'عملیات ثبت شد.';
        else $args['bcrm_error'] = $result['message'] ?? 'عملیات انجام نشد.';
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_deficiency_assign(): void
    {
        $department = sanitize_key($_POST['department'] ?? 'deficiency');
        $required = $department === 'finance' ? 'assign_finance_cases' : 'assign_deficiency_cases';
        if (!current_user_can($required) && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        if (!check_admin_referer('bcrm_deficiency_assign')) wp_die('درخواست معتبر نیست.');
        $case_ids = (array)($_POST['case_ids'] ?? []);
        if (!empty($_POST['require_selection']) && !$case_ids) {
            $return_tab = sanitize_key($_POST['return_tab'] ?? 'new');
            wp_safe_redirect(add_query_arg(['page'=>$department === 'finance' ? 'biawin-crm-financial' : 'biawin-crm-deficiency','tab'=>$return_tab,'bcrm_error'=>'حداقل یک پرونده را از همین تب انتخاب کنید.'], admin_url('admin.php')));
            exit;
        }
        if (!empty($_POST['return_assignment'])) {
            $result = Biawin_CRM_Deficiency_Workflow::return_assignments($case_ids, $department, absint($_POST['return_agent_id'] ?? 0));
        } else {
            $agent_ids = (array)($_POST['agent_user_ids'] ?? []);
            if (!$agent_ids && !empty($_POST['agent_user_id'])) $agent_ids = [absint($_POST['agent_user_id'])];
            $result = Biawin_CRM_Deficiency_Workflow::assign_cases_multi($case_ids, $agent_ids, $department, absint($_POST['limit'] ?? 100));
        }
        $page = $department === 'finance' ? 'biawin-crm-financial' : 'biawin-crm-deficiency';
        $return_tab = sanitize_key($_POST['return_tab'] ?? 'assign');
        $args = ['page'=>$page, 'tab'=>$return_tab ?: 'assign'];
        if (!empty($result['ok'])) $args['bcrm_message'] = isset($result['returned']) ? number_format_i18n((int)$result['returned']) . ' تخصیص برگشت داده شد.' : number_format_i18n((int)($result['assigned'] ?? 0)) . ' پرونده تخصیص داده شد.';
        else $args['bcrm_error'] = $result['message'] ?? 'تخصیص انجام نشد.';
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * v0.8.62.15: آپلود اکسل Customer ID ها و تخصیص همه پرونده‌های بازِ آن‌ها به یک کارشناس واحد.
     */
    public static function handle_deficiency_assign_upload(): void
    {
        $department = sanitize_key($_POST['department'] ?? 'deficiency');
        $required = $department === 'finance' ? 'assign_finance_cases' : 'assign_deficiency_cases';
        if (!current_user_can($required) && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_deficiency_assign_upload');
        $agent_id = absint($_POST['agent_user_id'] ?? 0);
        if (!$agent_id) wp_die('کارشناس مقصد انتخاب نشده است.');
        $file = $_FILES['assign_customer_file'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            wp_die('فایل اکسل انتخاب نشده یا آپلود آن کامل نشد.');
        }
        $name = sanitize_file_name((string)($file['name'] ?? 'assign.xlsx'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) wp_die('فقط فایل XLSX یا CSV پذیرفته می‌شود. فایل XLS قدیمی را اول به XLSX تبدیل کنید.');
        $result = Biawin_CRM_Deficiency_Workflow::assign_from_customer_file((string)$file['tmp_name'], $name, $agent_id, $department);
        $page = $department === 'finance' ? 'biawin-crm-financial' : 'biawin-crm-deficiency';
        $args = ['page' => $page, 'tab' => 'assign'];
        if (!empty($result['ok'])) {
            $args['bcrm_message'] = $result['message'];
            set_transient('bcrm_assign_upload_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
        } else {
            $args['bcrm_error'] = $result['message'] ?? 'تخصیص از فایل اکسل انجام نشد.';
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_deficiency_delete_receipt(): void
    {
        $case_id = absint($_GET['case_id'] ?? 0);
        $attachment_id = absint($_GET['attachment_id'] ?? 0);
        if (!$case_id || !$attachment_id || !check_admin_referer('bcrm_deficiency_delete_receipt_' . $case_id . '_' . $attachment_id)) wp_die('درخواست معتبر نیست.');
        $result = Biawin_CRM_Deficiency_Workflow::delete_receipt_from_deficiency($case_id, $attachment_id);
        $args = ['page'=>'biawin-crm-deficiency','case_id'=>$case_id];
        if (!empty($result['ok'])) $args['bcrm_message'] = $result['message'] ?? 'فیش حذف شد.';
        else $args['bcrm_error'] = $result['message'] ?? 'حذف فیش انجام نشد.';
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handle_deficiency_export(): void
    {
        if (!current_user_can('view_deficiency_reports') && !current_user_can('view_finance_reports') && !current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_deficiency_export');
        Biawin_CRM_Deficiency_Workflow::export_csv();
    }

    /** v0.8.62.17: خروجی اکسل چندشیتی گزارش Workflow (پرونده‌ها + اقدام‌ها و ریز وضعیت‌ها + خلاصه). */
    public static function handle_deficiency_export_excel(): void
    {
        if (!current_user_can('view_deficiency_reports') && !current_user_can('view_finance_reports') && !current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_deficiency_export');
        Biawin_CRM_Deficiency_Workflow::export_excel();
    }

    private static function deficiency_case_for_redirect(int $case_id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT current_department FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' WHERE id=%d', $case_id));
    }

    private static function agent_for_sales_manager_rule(int $customer_id): ?WP_User
    {
        global $wpdb;
        $sales_manager = trim((string)$wpdb->get_var($wpdb->prepare(
            'SELECT sales_manager FROM '.Biawin_CRM_DB::table('customers').' WHERE id=%d',
            $customer_id
        )));
        if ($sales_manager === '') return null;
        $agent_id = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT agent_user_id FROM '.Biawin_CRM_DB::table('sales_manager_mapping').' WHERE sales_manager=%s LIMIT 1',
            $sales_manager
        ));
        if (!$agent_id) return null;
        $agent = get_user_by('id', $agent_id);
        if (!$agent || !in_array('bcrm_agent', (array)$agent->roles, true) || !$agent->has_cap('bcrm_work_cases') || (bool)get_user_meta($agent_id,'bcrm_disabled',true)) return null;
        return $agent;
    }

    public static function handle_return_assignments(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_assign_customers')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $raw = $_POST['customer_ids'] ?? [];
        if (!is_array($raw)) $raw = explode(',', sanitize_text_field(wp_unslash($raw)));
        $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
        $return_agent_values = (array)($_POST['return_agent_user_ids'] ?? []);
        if (!$return_agent_values && isset($_POST['return_agent_user_id'])) $return_agent_values = [$_POST['return_agent_user_id']];
        $return_all_agents = in_array('all', array_map('strval', $return_agent_values), true);
        $return_agent_ids = $return_all_agents ? [] : array_values(array_unique(array_filter(array_map('absint', $return_agent_values))));
        $assignments = Biawin_CRM_DB::table('assignments');
        $customers = Biawin_CRM_DB::table('customers');
        $interactions = Biawin_CRM_DB::table('interactions');
        $return_status = sanitize_key((string)($_POST['return_customer_status'] ?? ''));
        $return_profile = sanitize_key((string)($_POST['return_profile_completeness'] ?? ''));
        $return_journey = sanitize_key((string)($_POST['return_journey_state'] ?? ''));
        $return_contact = sanitize_key((string)($_POST['return_contact_outcome'] ?? ''));
        $return_min_paid = isset($_POST['return_min_paid']) ? Biawin_CRM_Normalizer::money(wp_unslash($_POST['return_min_paid'])) : 0;
        $return_max_paid = isset($_POST['return_max_paid']) && $_POST['return_max_paid'] !== '' ? Biawin_CRM_Normalizer::money(wp_unslash($_POST['return_max_paid'])) : 0;
        $return_limit_mode = sanitize_key((string)($_POST['return_limit_mode'] ?? 'all'));
        if (!in_array($return_limit_mode, ['all','latest','oldest'], true)) $return_limit_mode = 'all';
        $return_limit = max(0, min(10000, absint($_POST['return_limit'] ?? 0)));
        if (!$ids && ($return_all_agents || $return_agent_ids)) {
            $where = ["a.status='assigned'"];
            $params = [];
            if (!$return_all_agents) {
                $agent_placeholders = implode(',', array_fill(0, count($return_agent_ids), '%d'));
                $where[] = "a.agent_user_id IN ($agent_placeholders)";
                $params = array_merge($params, $return_agent_ids);
            }
            if ($return_status !== '') { $where[] = 'c.status=%s'; $params[] = $return_status; }
            if ($return_profile !== '') { $where[] = 'c.profile_completeness=%s'; $params[] = $return_profile; }
            if ($return_journey !== '') { $where[] = 'c.journey_state=%s'; $params[] = $return_journey; }
            if ($return_min_paid > 0) { $where[] = 'c.total_paid >= %f'; $params[] = $return_min_paid; }
            if ($return_max_paid > 0) { $where[] = 'c.total_paid <= %f'; $params[] = $return_max_paid; }
            if ($return_contact === '__none__') {
                $where[] = "NOT EXISTS (SELECT 1 FROM $interactions li WHERE li.customer_id=c.id)";
            } elseif ($return_contact !== '') {
                $return_contact_label = Biawin_CRM_Workflow_Service::contact_outcome_label($return_contact, $return_contact);
                $latest_outcome_key = "(SELECT NULLIF(li.outcome_key,'') FROM $interactions li WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1)";
                $latest_outcome_label = "(SELECT NULLIF(li.outcome,'') FROM $interactions li WHERE li.customer_id=c.id ORDER BY li.id DESC LIMIT 1)";
                $where[] = "(COALESCE($latest_outcome_key,'')=%s OR (COALESCE($latest_outcome_key,'')='' AND COALESCE($latest_outcome_label,'')=%s))";
                $params[] = $return_contact;
                $params[] = $return_contact_label;
            }
            $order = $return_limit_mode === 'oldest' ? 'a.assigned_at ASC,a.id ASC' : 'a.assigned_at DESC,a.id DESC';
            $sql = "SELECT DISTINCT a.customer_id FROM $assignments a INNER JOIN $customers c ON c.id=a.customer_id WHERE " . implode(' AND ', $where) . " ORDER BY $order";
            if ($return_limit_mode !== 'all' && $return_limit > 0) {
                $sql .= ' LIMIT %d';
                $params[] = $return_limit;
            }
            $ids = array_map('intval', $wpdb->get_col($wpdb->prepare($sql, ...$params)));
        } elseif ($ids) {
            $filtered = [];
            foreach ($ids as $id) {
                $where = ['id=%d'];
                $params = [$id];
                if ($return_status !== '') { $where[] = 'status=%s'; $params[] = $return_status; }
                if ($return_profile !== '') { $where[] = 'profile_completeness=%s'; $params[] = $return_profile; }
                if ($return_journey !== '') { $where[] = 'journey_state=%s'; $params[] = $return_journey; }
                if ($return_min_paid > 0) { $where[] = 'total_paid >= %f'; $params[] = $return_min_paid; }
                if ($return_max_paid > 0) { $where[] = 'total_paid <= %f'; $params[] = $return_max_paid; }
                if ($return_contact === '__none__') {
                    $where[] = "NOT EXISTS (SELECT 1 FROM $interactions li WHERE li.customer_id=$customers.id)";
                } elseif ($return_contact !== '') {
                    $return_contact_label = Biawin_CRM_Workflow_Service::contact_outcome_label($return_contact, $return_contact);
                    $latest_outcome_key = "(SELECT NULLIF(li.outcome_key,'') FROM $interactions li WHERE li.customer_id=$customers.id ORDER BY li.id DESC LIMIT 1)";
                    $latest_outcome_label = "(SELECT NULLIF(li.outcome,'') FROM $interactions li WHERE li.customer_id=$customers.id ORDER BY li.id DESC LIMIT 1)";
                    $where[] = "(COALESCE($latest_outcome_key,'')=%s OR (COALESCE($latest_outcome_key,'')='' AND COALESCE($latest_outcome_label,'')=%s))";
                    $params[] = $return_contact;
                    $params[] = $return_contact_label;
                }
                if ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $customers WHERE ".implode(' AND ', $where), ...$params)) > 0) $filtered[] = $id;
            }
            $ids = $filtered;
            if ($return_limit_mode !== 'all' && $return_limit > 0) {
                $id_sql = implode(',', array_fill(0, count($ids), '%d'));
                if ($id_sql !== '') {
                    $order = $return_limit_mode === 'oldest' ? 'a.assigned_at ASC,a.id ASC' : 'a.assigned_at DESC,a.id DESC';
                    $agent_clause = '';
                    $params = $ids;
                    if (!$return_all_agents && $return_agent_ids) {
                        $agent_placeholders = implode(',', array_fill(0, count($return_agent_ids), '%d'));
                        $agent_clause = "a.agent_user_id IN ($agent_placeholders) AND ";
                        $params = array_merge($return_agent_ids, $params);
                    }
                    $params[] = $return_limit;
                    $ids = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT DISTINCT a.customer_id FROM $assignments a WHERE $agent_clause a.customer_id IN ($id_sql) AND a.status='assigned' ORDER BY $order LIMIT %d", ...$params)));
                }
            }
        }
        if (!$ids) wp_die('هیچ تخصیصی با فیلترهای انتخاب‌شده پیدا نشد.');
        $returned = 0;
        foreach ($ids as $id) {
            $wpdb->query('START TRANSACTION');
            if (!$return_all_agents && $return_agent_ids) {
                $agent_placeholders = implode(',', array_fill(0, count($return_agent_ids), '%d'));
                $active = $wpdb->get_results($wpdb->prepare("SELECT id,agent_user_id FROM $assignments WHERE customer_id=%d AND agent_user_id IN ($agent_placeholders) AND status='assigned' FOR UPDATE", $id, ...$return_agent_ids));
            } else {
                $active = $wpdb->get_results($wpdb->prepare("SELECT id,agent_user_id FROM $assignments WHERE customer_id=%d AND status='assigned' FOR UPDATE", $id));
            }
            if (!$active) { $wpdb->query('ROLLBACK'); continue; }
            if (!$return_all_agents && $return_agent_ids) {
                $agent_placeholders = implode(',', array_fill(0, count($return_agent_ids), '%d'));
                $updated = $wpdb->query($wpdb->prepare("UPDATE $assignments SET status='returned', completed_at=%s WHERE customer_id=%d AND agent_user_id IN ($agent_placeholders) AND status='assigned'", Biawin_CRM_DB::now(), $id, ...$return_agent_ids));
            } else {
                $updated = $wpdb->query($wpdb->prepare("UPDATE $assignments SET status='returned', completed_at=%s WHERE customer_id=%d AND status='assigned'", Biawin_CRM_DB::now(), $id));
            }
            $remaining_active = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id=%d AND status='assigned'", $id));
            $new_status_sql = $remaining_active ? "status=status" : "status=CASE WHEN status='assigned' THEN 'new' ELSE status END";
            $customer_updated = $wpdb->query($wpdb->prepare("UPDATE $customers SET $new_status_sql, updated_at=%s WHERE id=%d", Biawin_CRM_DB::now(), $id));
            if ($updated === false || $customer_updated === false) {
                $wpdb->query('ROLLBACK');
                wp_die('برگشت تخصیص ذخیره نشد: ' . esc_html($wpdb->last_error ?: 'خطای دیتابیس'));
            }
            $wpdb->query('COMMIT');
            $returned++;
            $previous_agents = array_values(array_unique(array_map('intval', wp_list_pluck($active, 'agent_user_id'))));
            Biawin_CRM_DB::log('customer', $id, 'assignment_returned', null, ['previous_agent_user_ids'=>$previous_agents,'returned_by'=>get_current_user_id()]);
            Biawin_CRM_Workflow_Service::activity($id, 'assignment_returned', 'تخصیص پرونده برگشت داده شد و پروفایل به صف تخصیص‌نیافته منتقل شد.', 'customer', $id, ['previous_agent_user_ids'=>$previous_agents,'returned_by'=>get_current_user_id()]);
        }
        update_option('bcrm_dashboard_cache_buster', (string) microtime(true), false);
        $cohort = sanitize_key($_POST['cohort'] ?? 'normal');
        if (!in_array($cohort, ['normal','1404','all'], true)) $cohort = 'normal';
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-assignments','cohort'=>$cohort,'returned'=>$returned], admin_url('admin.php')));
        exit;
    }

    public static function data_audit_page(): void
    {
        if (!current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        $report = self::data_audit_report();
        $download_url = wp_nonce_url(admin_url('admin-post.php?action=bcrm_data_audit_json'), 'bcrm_data_audit_json');
        echo '<div class="wrap bcrm" dir="rtl"><h1>Data Audit فقط‌خواندنی</h1>';
        echo '<p>این صفحه هیچ تغییری در دیتا ایجاد نمی‌کند و فقط عددهای اصلی، مغایرت‌ها و موارد مشکوک را مستقیم از دیتابیس محاسبه می‌کند.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url($download_url).'">دانلود خروجی JSON کامل</a></p>';
        echo '<div class="bcrm-grid">';
        foreach (($report['kpis'] ?? []) as $label => $value) self::metric(self::audit_label((string)$label), is_numeric($value) ? number_format_i18n((float)$value) : (string)$value);
        echo '</div><h2>سناریوهای تخصیص</h2>';
        self::audit_table($report['assignment_scenarios'] ?? []);
        echo '<h2>وضعیت تماس‌ها</h2>';
        self::audit_table($report['contact_status_counts'] ?? []);
        echo '<h2>تخصیص بر اساس کارشناس</h2>';
        self::audit_table($report['assignment_by_agent'] ?? []);
        echo '<h2>موارد مشکوک و مغایرت‌ها</h2>';
        self::audit_table($report['integrity_summary'] ?? []);
        echo '<h2>نمونه رکوردهای نیازمند بررسی</h2>';
        foreach (($report['samples'] ?? []) as $title => $rows) {
            echo '<h3>'.esc_html(self::audit_label((string)$title)).'</h3>';
            self::audit_table((array)$rows);
        }
        echo '<details style="margin-top:18px"><summary>نمای JSON خلاصه</summary><pre style="direction:ltr;text-align:left;white-space:pre-wrap;max-height:520px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px">'.esc_html(wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre></details>';
        echo '</div>';
    }

    public static function handle_data_audit_json(): void
    {
        if (!current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_data_audit_json');
        $report = self::data_audit_report();
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="biawin-crm-data-audit-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function reporting_source_of_truth_page(): void
    {
        if (!current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        $report = Biawin_CRM_Reporting_Validation_Service::run();
        $overall = (string)($report['meta']['overall_reporting_sot_status'] ?? 'BLOCKED');
        $core = (string)($report['meta']['core_source_of_truth_status'] ?? 'BLOCKED');
        $validation = (string)($report['meta']['validation_execution_status'] ?? 'BLOCKED');
        echo '<div class="wrap bcrm" dir="rtl"><h1>Reporting Source-of-Truth Validation</h1>';
        echo '<div class="notice notice-info inline"><p><strong>READ-ONLY</strong> — NO DATA REPAIR — NO MIGRATION — NO RUN #8 — NO GUARD MUTATION</p></div>';
        $notice_class = $overall === 'HEALTHY' ? 'notice-success' : ($overall === 'DEGRADED' ? 'notice-warning' : 'notice-error');
        echo '<div class="notice ' . esc_attr($notice_class) . ' inline"><p><strong>CORE SOURCE-OF-TRUTH: ' . esc_html($core) . '</strong></p><p><strong>VALIDATION EXECUTION: ' . esc_html($validation) . '</strong></p>';
        if ($overall !== 'HEALTHY') {
            echo '<p>Guard Source: ' . esc_html((string)($report['meta']['guard_source_status'] ?? 'UNKNOWN')) . ' — Blacklist Source: ' . esc_html((string)($report['meta']['blacklist_source_status'] ?? 'UNKNOWN')) . ' — Canonical Population: ' . esc_html((string)($report['meta']['canonical_population_status'] ?? 'BLOCKED')) . '</p>';
        }
        echo '</div>';
        echo '<p class="description">این صفحه فقط Foundation گزارش‌گیری نسخه 0.8.55.2 را از روی وضعیت زنده دیتابیس می‌خواند و هیچ دکمه ترمیم یا بازسازی ندارد.</p>';
        self::reporting_sot_cards('Source Status', $report['meta'] ?? []);
        self::reporting_sot_cards('Population Summary', $report['population']['counts'] ?? []);
        self::reporting_sot_cards('Guard / Blacklist Divergence', $report['population']['divergence'] ?? []);
        self::reporting_sot_cards('Financial Freshness', $report['financial_freshness'] ?? []);
        self::reporting_sot_cards('Assignment Integrity', $report['assignment_integrity'] ?? []);
        echo '<h2>Case Ownership Integrity</h2>';
        foreach (($report['case_ownership_integrity'] ?? []) as $domain => $row) {
            echo '<h3>' . esc_html((string)$domain) . '</h3>';
            self::audit_table([(array)$row]);
        }
        echo '<h2>Deficiency Orphan Diagnostics</h2>';
        self::reporting_sot_cards('Deficiency Orphan Diagnostic Status', array_diff_key((array)($report['deficiency_orphan_diagnostics'] ?? []), ['rows'=>true]));
        self::audit_table((array)($report['deficiency_orphan_diagnostics']['rows'] ?? []));
        self::reporting_sot_cards('Migration State', $report['migration_state'] ?? []);
        echo '<h2>Population Metadata</h2>';
        self::audit_table(array_values((array)($report['population']['definitions'] ?? [])));
        echo '<h2>Financial Semantics</h2>';
        $semantics = [];
        foreach ((array)($report['financial_semantics'] ?? []) as $key => $value) $semantics[] = ['key'=>$key, 'meaning'=>$value];
        self::audit_table($semantics);
        echo '<h2>Event Double-Count Risk</h2>';
        self::audit_table([(array)($report['event_double_count_risk'] ?? [])]);
        echo '<details style="margin-top:18px"><summary>نمای JSON کامل</summary><pre style="direction:ltr;text-align:left;white-space:pre-wrap;max-height:520px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px">'.esc_html(wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre></details>';
        echo '</div>';
    }

    private static function reporting_sot_cards(string $title, array $rows): void
    {
        echo '<h2>' . esc_html($title) . '</h2><div class="bcrm-grid">';
        foreach ($rows as $key => $value) {
            if (is_array($value)) {
                $count = array_key_exists('count', $value) ? $value['count'] : null;
                $status = (string)($value['status'] ?? ($value['source_status'] ?? ''));
                $trusted = array_key_exists('trusted', $value) ? ((bool)$value['trusted'] ? 'trusted' : 'untrusted') : '';
                $display = $count === null ? 'UNAVAILABLE' : (is_numeric($count) ? number_format_i18n((float)$count) : (string)$count);
                $suffix = trim($status . ($trusted !== '' ? ' / ' . $trusted : ''));
                self::metric((string)$key, $suffix !== '' ? $display . ' — ' . $suffix : $display);
                continue;
            }
            if ($value === null) {
                self::metric((string)$key, 'UNAVAILABLE');
                continue;
            }
            self::metric((string)$key, is_bool($value) ? ($value ? 'YES' : 'NO') : (is_numeric($value) ? number_format_i18n((float)$value) : (string)$value));
        }
        echo '</div>';
    }

    private static function data_audit_report(): array
    {
        global $wpdb;
        $names = ['customers','phones','invoices','assignments','interactions','audit_log','sales_manager_mapping','service_cases','complaints','refunds','deficiency_cases','financial_cases','financial_documents'];
        $t = []; $exists = []; $row_counts = [];
        foreach ($names as $name) { $t[$name] = Biawin_CRM_DB::table($name); $exists[$name] = self::audit_table_exists($t[$name]); $row_counts[$name] = $exists[$name] ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t[$name]}") : null; }
        $c=$t['customers']; $a=$t['assignments']; $i=$t['invoices']; $p=$t['phones']; $it=$t['interactions'];
        $kpis = [
            'customers_total'=>$exists['customers']?(int)$wpdb->get_var("SELECT COUNT(*) FROM $c"):0,
            'profiles_complete'=>$exists['customers']?(int)$wpdb->get_var("SELECT COUNT(*) FROM $c WHERE profile_completeness='complete'"):0,
            'profiles_incomplete'=>$exists['customers']?(int)$wpdb->get_var("SELECT COUNT(*) FROM $c WHERE profile_completeness='incomplete'"):0,
            'profiles_pending'=>$exists['customers']?(int)$wpdb->get_var("SELECT COUNT(*) FROM $c WHERE profile_completeness='pending'"):0,
            'invoices_total'=>$exists['invoices']?(int)$wpdb->get_var("SELECT COUNT(*) FROM $i"):0,
            'phones_total'=>$exists['phones']?(int)$wpdb->get_var("SELECT COUNT(*) FROM $p"):0,
            'active_assigned_customers'=>$exists['assignments']?(int)$wpdb->get_var("SELECT COUNT(DISTINCT customer_id) FROM $a WHERE status='assigned'"):0,
            'unassigned_customers'=>($exists['customers']&&$exists['assignments'])?(int)$wpdb->get_var("SELECT COUNT(*) FROM $c cc WHERE NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned')"):0,
            'customers_with_any_contact'=>$exists['interactions']?(int)$wpdb->get_var("SELECT COUNT(DISTINCT customer_id) FROM $it"):0,
            'customers_without_contact'=>($exists['customers']&&$exists['interactions'])?(int)$wpdb->get_var("SELECT COUNT(*) FROM $c cc WHERE NOT EXISTS (SELECT 1 FROM $it ii WHERE ii.customer_id=cc.id)"):0,
            'duplicate_active_assignment_customers'=>$exists['assignments']?(int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT customer_id FROM $a WHERE status='assigned' GROUP BY customer_id HAVING COUNT(*)>1) x"):0,
        ];
        $kpis['profile_complete_plus_incomplete_plus_pending']=(int)$kpis['profiles_complete']+(int)$kpis['profiles_incomplete']+(int)$kpis['profiles_pending'];
        $contact_counts = $exists['interactions'] ? $wpdb->get_results("SELECT COALESCE(NULLIF(outcome_key,''),NULLIF(outcome,''),'بدون وضعیت') status,COUNT(*) interactions,COUNT(DISTINCT customer_id) customers FROM $it GROUP BY COALESCE(NULLIF(outcome_key,''),NULLIF(outcome,''),'بدون وضعیت') ORDER BY interactions DESC", ARRAY_A) : [];
        $assignment_by_agent = $exists['assignments'] ? $wpdb->get_results("SELECT a.agent_user_id,COALESCE(u.display_name,CONCAT('User #',a.agent_user_id)) agent,COUNT(DISTINCT a.customer_id) assigned_customers FROM $a a LEFT JOIN {$wpdb->users} u ON u.ID=a.agent_user_id WHERE a.status='assigned' GROUP BY a.agent_user_id,u.display_name ORDER BY assigned_customers DESC", ARRAY_A) : [];
        $assignment_scenarios = [];
        if ($exists['customers'] && $exists['assignments'] && $exists['invoices'] && $exists['interactions']) {
            $assignment_scenarios[] = self::audit_assignment_scenario('default_not_contacted_one_invoice_min_20m', "c.total_paid>=20000000 AND (SELECT COUNT(*) FROM $i fi WHERE fi.customer_id=c.id)=1 AND NOT EXISTS (SELECT 1 FROM $it ii WHERE ii.customer_id=c.id)");
            $assignment_scenarios[] = self::audit_assignment_scenario('all_contacts_one_invoice_min_20m', "c.total_paid>=20000000 AND (SELECT COUNT(*) FROM $i fi WHERE fi.customer_id=c.id)=1");
            $assignment_scenarios[] = self::audit_assignment_scenario('not_contacted_any_invoice_min_20m', "c.total_paid>=20000000 AND NOT EXISTS (SELECT 1 FROM $it ii WHERE ii.customer_id=c.id)");
            $assignment_scenarios[] = self::audit_assignment_scenario('all_customers', '1=1');
        }
        $integrity = []; $samples = [];
        if ($exists['customers'] && $exists['assignments']) {
            $integrity[]=['check'=>'active_assignment_missing_customer','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $a aa LEFT JOIN $c cc ON cc.id=aa.customer_id WHERE aa.status='assigned' AND cc.id IS NULL")];
            $integrity[]=['check'=>'assigned_status_without_active_assignment','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $c cc WHERE cc.status='assigned' AND NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned')")];
            $integrity[]=['check'=>'active_assignment_customer_status_not_assigned','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $c cc WHERE cc.status<>'assigned' AND EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned')")];
            $samples['assigned_status_without_active_assignment']=$wpdb->get_results("SELECT cc.id,cc.display_name,cc.source_customer_id,cc.status,cc.updated_at FROM $c cc WHERE cc.status='assigned' AND NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned') ORDER BY cc.updated_at DESC LIMIT 50", ARRAY_A);
            $samples['active_assignment_customer_status_not_assigned']=$wpdb->get_results("SELECT cc.id,cc.display_name,cc.source_customer_id,cc.status,(SELECT MAX(aa.assigned_at) FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned') assigned_at FROM $c cc WHERE cc.status<>'assigned' AND EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned') ORDER BY assigned_at DESC LIMIT 50", ARRAY_A);
            $samples['duplicate_active_assignments']=$wpdb->get_results("SELECT aa.customer_id,cc.display_name,cc.source_customer_id,COUNT(*) active_rows,GROUP_CONCAT(aa.agent_user_id ORDER BY aa.id DESC) agent_user_ids FROM $a aa LEFT JOIN $c cc ON cc.id=aa.customer_id WHERE aa.status='assigned' GROUP BY aa.customer_id,cc.display_name,cc.source_customer_id HAVING COUNT(*)>1 ORDER BY active_rows DESC LIMIT 50", ARRAY_A);
        }
        if ($exists['customers'] && $exists['phones']) {
            $integrity[]=['check'=>'customers_without_phone','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $c cc WHERE NOT EXISTS (SELECT 1 FROM $p pp WHERE pp.customer_id=cc.id)")];
            $integrity[]=['check'=>'duplicate_phone_across_customers','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT normalized_phone FROM $p WHERE normalized_phone<>'' GROUP BY normalized_phone HAVING COUNT(DISTINCT customer_id)>1) x")];
            $samples['duplicate_phone_across_customers']=$wpdb->get_results("SELECT normalized_phone,COUNT(DISTINCT customer_id) customers,GROUP_CONCAT(DISTINCT customer_id ORDER BY customer_id) customer_ids FROM $p WHERE normalized_phone<>'' GROUP BY normalized_phone HAVING COUNT(DISTINCT customer_id)>1 ORDER BY customers DESC LIMIT 50", ARRAY_A);
        }
        if ($exists['customers'] && $exists['invoices']) {
            $integrity[]=['check'=>'invoices_without_customer','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $i ii LEFT JOIN $c cc ON cc.id=ii.customer_id WHERE ii.customer_id IS NULL OR cc.id IS NULL")];
            $integrity[]=['check'=>'customer_total_paid_mismatch_invoice_sum_gt_1000','count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT cc.id FROM $c cc LEFT JOIN $i ii ON ii.customer_id=cc.id GROUP BY cc.id,cc.total_paid HAVING ABS(COALESCE(SUM(CASE WHEN COALESCE(ii.exclude_from_total,0)=1 THEN 0 ELSE ii.amount_paid END),0)-cc.total_paid)>1000) x")];
            $samples['customer_total_paid_mismatch_invoice_sum_gt_1000']=$wpdb->get_results("SELECT cc.id,cc.display_name,cc.source_customer_id,cc.total_paid,COALESCE(SUM(CASE WHEN COALESCE(ii.exclude_from_total,0)=1 THEN 0 ELSE ii.amount_paid END),0) invoice_sum,(COALESCE(SUM(CASE WHEN COALESCE(ii.exclude_from_total,0)=1 THEN 0 ELSE ii.amount_paid END),0)-cc.total_paid) diff FROM $c cc LEFT JOIN $i ii ON ii.customer_id=cc.id GROUP BY cc.id,cc.display_name,cc.source_customer_id,cc.total_paid HAVING ABS(COALESCE(SUM(CASE WHEN COALESCE(ii.exclude_from_total,0)=1 THEN 0 ELSE ii.amount_paid END),0)-cc.total_paid)>1000 ORDER BY ABS(COALESCE(SUM(CASE WHEN COALESCE(ii.exclude_from_total,0)=1 THEN 0 ELSE ii.amount_paid END),0)-cc.total_paid) DESC LIMIT 50", ARRAY_A);
        }
        if ($exists['customers'] && $exists['interactions'] && $exists['assignments']) {
            $samples['recent_contacted_unassigned']=$wpdb->get_results("SELECT cc.id,cc.display_name,cc.source_customer_id,cc.status,(SELECT COALESCE(NULLIF(ii.outcome,''),NULLIF(ii.outcome_key,''),'') FROM $it ii WHERE ii.customer_id=cc.id ORDER BY ii.id DESC LIMIT 1) latest_contact,(SELECT ii.created_at FROM $it ii WHERE ii.customer_id=cc.id ORDER BY ii.id DESC LIMIT 1) latest_contact_at FROM $c cc WHERE NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=cc.id AND aa.status='assigned') AND EXISTS (SELECT 1 FROM $it ii WHERE ii.customer_id=cc.id) ORDER BY latest_contact_at DESC LIMIT 50", ARRAY_A);
        }
        return ['meta'=>['generated_at'=>current_time('mysql'),'plugin_version'=>defined('BIAWIN_CRM_VERSION')?BIAWIN_CRM_VERSION:'','site_url'=>site_url(),'wp_prefix'=>$wpdb->prefix,'read_only'=>true],'tables'=>['exists'=>$exists,'row_counts'=>$row_counts],'kpis'=>$kpis,'assignment_scenarios'=>$assignment_scenarios,'contact_status_counts'=>$contact_counts,'assignment_by_agent'=>$assignment_by_agent,'integrity_summary'=>$integrity,'samples'=>$samples];
    }

    private static function audit_assignment_scenario(string $name, string $condition): array
    {
        global $wpdb; $c=Biawin_CRM_DB::table('customers'); $a=Biawin_CRM_DB::table('assignments');
        $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $c c WHERE $condition");
        $unassigned=(int)$wpdb->get_var("SELECT COUNT(*) FROM $c c WHERE $condition AND NOT EXISTS (SELECT 1 FROM $a aa WHERE aa.customer_id=c.id AND aa.status='assigned')");
        return ['scenario'=>$name,'matching_total'=>$total,'assignable_unassigned'=>$unassigned,'already_assigned'=>max(0,$total-$unassigned)];
    }

    private static function audit_table_exists(string $table): bool
    {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function audit_table(array $rows): void
    {
        if (!$rows) { echo '<p class="description">موردی وجود ندارد.</p>'; return; }
        $first=(array)reset($rows); echo '<table class="widefat striped"><thead><tr>';
        foreach(array_keys($first) as $key) echo '<th>'.esc_html(self::audit_label((string)$key)).'</th>';
        echo '</tr></thead><tbody>';
        foreach($rows as $row){ $row=(array)$row; echo '<tr>'; foreach(array_keys($first) as $key){ $value=$row[$key]??''; echo '<td>'.esc_html(is_scalar($value)||$value===null?(string)$value:wp_json_encode($value,JSON_UNESCAPED_UNICODE)).'</td>'; } echo '</tr>'; }
        echo '</tbody></table>';
    }

    private static function audit_label(string $key): string
    {
        $labels=['customers_total'=>'کل مشتریان','profiles_complete'=>'پروفایل کامل','profiles_incomplete'=>'پروفایل ناقص','profiles_pending'=>'در انتظار بررسی','invoices_total'=>'کل فاکتورها','phones_total'=>'کل شماره‌ها','active_assigned_customers'=>'مشتری تخصیص‌یافته فعال','unassigned_customers'=>'مشتری تخصیص‌نیافته','customers_with_any_contact'=>'مشتری دارای تماس','customers_without_contact'=>'مشتری بدون تماس','duplicate_active_assignment_customers'=>'مشتری با تخصیص فعال تکراری','profile_complete_plus_incomplete_plus_pending'=>'جمع کامل/ناقص/در انتظار','default_not_contacted_one_invoice_min_20m'=>'بدون تماس، ۱ فاکتور، حداقل ۲۰ میلیون','all_contacts_one_invoice_min_20m'=>'همه تماس‌ها، ۱ فاکتور، حداقل ۲۰ میلیون','not_contacted_any_invoice_min_20m'=>'بدون تماس، هر تعداد فاکتور، حداقل ۲۰ میلیون','all_customers'=>'همه مشتریان','matching_total'=>'مطابق فیلتر','assignable_unassigned'=>'قابل تخصیص','already_assigned'=>'قبلاً تخصیص‌خورده','status'=>'وضعیت','interactions'=>'تعداد تماس','customers'=>'مشتری‌ها','assigned_customers'=>'تخصیص‌یافته','agent'=>'کارشناس','check'=>'بررسی','count'=>'تعداد'];
        return $labels[$key] ?? $key;
    }

    public static function reports_page(): void
    {
        $scope = self::main_dashboard_reporting_scope();
        $metrics = self::report_kpi_rows($scope);
        $report_page=max(1,absint($_GET['report_paged']??1));
        $report_per_page=50;
        $total_result = Biawin_CRM_Reporting_Consumer_Adapter::customer_population_count(Biawin_CRM_Reporting_Population_Service::NORMAL_CANONICAL, $scope);
        $total = !empty($total_result['trusted']) && $total_result['value'] !== null ? (int)$total_result['value'] : 0;
        $pages=max(1,(int)ceil($total/$report_per_page));
        if($report_page>$pages)$report_page=$pages;
        $offset=($report_page-1)*$report_per_page;
        $rows=self::customer_report_preview_rows($report_per_page,$offset,$scope);

        echo '<div class="wrap bcrm" dir="rtl"><h1>گزارش‌های عملیاتی</h1><div class="bcrm-report-actions">';
        echo '<a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bcrm_export_customers_excel'),'bcrm_export_customers_excel')).'">دانلود گزارش جامع Excel</a> ';
        echo '<a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bcrm_export_customers_csv'),'bcrm_export_customers_csv')).'">CSV UTF-8</a></div><div class="bcrm-grid">';
        foreach ($metrics as $metric_row) self::report_metric_card($metric_row);
        echo '</div><h2>گزارش جامع مشتریان</h2><p class="description">در این گزارش وضعیت پروفایل، مسیر مشتری، اولین تخصیص، کارشناس فعلی و آخرین اقدام ثبت‌شده قابل مشاهده است.</p>';
        echo '<div class="bcrm-table-wrap"><table class="widefat striped"><thead><tr><th>CustomerID</th><th>کد مشتری</th><th>نام</th><th>موبایل</th><th>وضعیت پروفایل</th><th>مسیر</th><th>فاکتور</th><th>مجموع واریزی</th><th>اولین تخصیص</th><th>اولین کارشناس</th><th>کارشناس فعلی</th><th>آخرین اقدام</th><th>زمان آخرین اقدام</th></tr></thead><tbody>';
        foreach($rows as $r){
            echo '<tr><td>'.esc_html($r['source_customer_id']?:'-').'</td><td>'.esc_html($r['customer_code']?:'-').'</td><td>'.esc_html($r['display_name']?:'پروفایل بدون نام').'</td><td>'.esc_html($r['phone']?:'-').'</td><td>'.esc_html(self::report_profile_label($r['profile_completeness'])).'</td><td>'.esc_html(self::report_journey_label($r['journey_state'])).'</td><td>'.esc_html(self::report_safe_number($r['invoice_count'] ?? null, $r['trusted'] ?? true)).'</td><td>'.esc_html(self::report_safe_number($r['total_paid'] ?? null, $r['trusted'] ?? true, true)).'</td><td>'.esc_html(self::report_datetime($r['first_assigned_at']??'')).
                '</td><td>'.esc_html($r['first_agent_name']?:'-').'</td><td>'.esc_html($r['current_agent_name']?:'-').'</td><td>'.esc_html($r['last_action_title']?:'-').'</td><td>'.esc_html(self::report_datetime($r['last_action_at']??'')).'</td></tr>';
        }
        echo '</tbody></table></div>';
        if($pages>1){
            echo '<div class="tablenav"><div class="tablenav-pages">';
            if($report_page>1) echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'biawin-crm-reports','report_paged'=>$report_page-1],admin_url('admin.php'))).'">قبلی</a> ';
            echo '<span>صفحه '.number_format_i18n($report_page).' از '.number_format_i18n($pages).'</span> ';
            if($report_page<$pages) echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'biawin-crm-reports','report_paged'=>$report_page+1],admin_url('admin.php'))).'">بعدی</a>';
            echo '</div></div>';
        }
        $satisfaction = self::report_satisfaction_summary($scope);
        echo '<h2>میانگین رضایت کلی مشتریان</h2><table class="widefat striped"><thead><tr><th>تعداد مشتری با رضایت ثبت‌شده</th><th>میانگین رضایت کلی</th></tr></thead><tbody><tr><td>'.esc_html(self::report_safe_number($satisfaction['customers'], $satisfaction['trusted'])).'</td><td>'.esc_html($satisfaction['average']===''?'-':self::report_safe_number($satisfaction['average'], $satisfaction['trusted'], true, 2)).'</td></tr></tbody></table>';
        echo '<h2>گزارش وضعیت‌های تماس</h2><table class="widefat striped"><thead><tr><th>وضعیت تماس</th><th>تعداد ثبت</th><th>تعداد مشتری یکتا</th><th>آخرین زمان ثبت</th></tr></thead><tbody>';
        foreach(self::report_contact_status_rows($scope) as $r) echo '<tr><td>'.esc_html($r['label']).'</td><td>'.esc_html(self::report_safe_number($r['total'] ?? null, $r['trusted'] ?? true)).'</td><td>'.esc_html(self::report_safe_number($r['customers'] ?? null, $r['trusted'] ?? true)).'</td><td>'.esc_html(self::report_datetime($r['last_at']??'')).'</td></tr>';
        echo '</tbody></table>';
        echo '<h2>گزارش مسیرها - کلی</h2><table class="widefat striped"><thead><tr><th>مسیر</th><th>تعداد مشتری</th><th>مبلغ کل</th><th>تعداد کامل</th><th>مبلغ کامل</th><th>تعداد ناقص</th><th>مبلغ ناقص</th></tr></thead><tbody>';
        foreach(self::report_route_rows($scope) as $r) echo '<tr><td>'.esc_html(self::report_journey_label($r['journey_state'])).'</td><td>'.esc_html(self::report_safe_number($r['customers'] ?? null, $r['trusted'] ?? true)).'</td><td>'.esc_html(self::report_safe_number($r['amount'] ?? null, $r['trusted'] ?? true, true)).'</td><td>'.esc_html(self::report_safe_number($r['complete_count'] ?? null, $r['trusted'] ?? true)).'</td><td>'.esc_html(self::report_safe_number($r['complete_amount'] ?? null, $r['trusted'] ?? true, true)).'</td><td>'.esc_html(self::report_safe_number($r['incomplete_count'] ?? null, $r['trusted'] ?? true)).'</td><td>'.esc_html(self::report_safe_number($r['incomplete_amount'] ?? null, $r['trusted'] ?? true, true)).'</td></tr>';
        echo '</tbody></table>';
        echo '<h2>گزارش مسیرها - ریز مشتری</h2><div class="bcrm-table-wrap"><table class="widefat striped"><thead><tr><th>CustomerID</th><th>کد مشتری</th><th>نام</th><th>مسیر</th><th>وضعیت پروفایل</th><th>مبلغ</th></tr></thead><tbody>';
        foreach(self::report_route_detail_rows(200, $scope) as $r) echo '<tr><td>'.esc_html($r['source_customer_id']?:'-').'</td><td>'.esc_html($r['customer_code']?:'-').'</td><td>'.esc_html($r['display_name']?:'پروفایل بدون نام').'</td><td>'.esc_html(self::report_journey_label($r['journey_state'])).'</td><td>'.esc_html(self::report_profile_label($r['profile_completeness'])).'</td><td>'.esc_html(self::report_safe_number($r['total_paid'] ?? null, $r['trusted'] ?? true, true)).'</td></tr>';
        echo '</tbody></table></div><p class="description">برای نسخه کامل ریز مشتریان، گزارش جامع Excel را دریافت کنید.</p>';
        echo '<h2>عملکرد خدمات و پروموشن‌ها</h2><table class="widefat striped"><thead><tr><th>نام خدمت/پروموشن</th><th>تعداد انتخاب‌شده</th><th>تخصیص‌نیافته</th><th>در حال انجام</th><th>تکمیل‌شده</th><th>اعتبار مصرف/رزرو</th></tr></thead><tbody>';
        foreach(self::report_service_case_rows($scope) as $r) echo '<tr><td>'.esc_html($r->name ?? '-').'</td><td>'.esc_html(self::report_safe_number($r->total ?? null, $r->trusted ?? true)).'</td><td>'.esc_html(self::report_safe_number($r->unassigned ?? null, $r->trusted ?? true)).'</td><td>'.esc_html(self::report_safe_number($r->active ?? null, $r->trusted ?? true)).'</td><td>'.esc_html(self::report_safe_number($r->completed ?? null, $r->trusted ?? true)).'</td><td>'.esc_html(self::report_safe_number($r->credit ?? null, $r->trusted ?? true, true)).'</td></tr>';
        echo '</tbody></table>';
        echo '<h2>خدمات واردشده از اکسل</h2><table class="widefat striped"><thead><tr><th>نام خدمت</th><th>تعداد انتخاب‌شده</th><th>تعداد مشتری یکتا</th></tr></thead><tbody>';
        foreach(self::report_source_service_rows($scope) as $r) echo '<tr><td>'.esc_html($r->service_name ?? '-').'</td><td>'.esc_html(self::report_safe_number($r->total ?? null, $r->trusted ?? true)).'</td><td>'.esc_html(self::report_safe_number($r->customers ?? null, $r->trusted ?? true)).'</td></tr>';
        echo '</tbody></table></div>';
    }

    private static function report_kpi_rows(array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_report_kpi_rows($scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return array_map(static fn($label) => ['label' => $label, 'count' => null, 'complete_amount' => null, 'incomplete_amount' => null, 'trusted' => false, 'status' => $result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED], ['کل مشتریان','پروفایل کامل','پروفایل ناقص','بدون درخواست','دارای نارضایتی','درخواست خدمات','عودت وجه']);
    }

    private static function report_contact_status_rows(array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_contact_status_rows($scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return [['label' => self::adapter_safe_status_message((string)($result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)), 'total' => null, 'customers' => null, 'last_at' => '', 'trusted' => false]];
    }

    private static function report_satisfaction_summary(array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_satisfaction_summary($scope);
        if (!empty($result['trusted'])) return ['customers'=>(int)($result['data']['customers'] ?? 0), 'average'=>$result['data']['average'] ?? '', 'trusted'=>true];
        return ['customers'=>null, 'average'=>'', 'trusted'=>false];
    }

    private static function report_route_rows(array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_route_summary_rows($scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return [['journey_state'=>self::adapter_safe_status_message((string)($result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)), 'customers'=>null, 'amount'=>null, 'complete_count'=>null, 'complete_amount'=>null, 'incomplete_count'=>null, 'incomplete_amount'=>null, 'trusted'=>false]];
    }

    private static function report_route_detail_rows(int $limit, array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_route_detail_rows($limit, $scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return [['source_customer_id'=>'-', 'customer_code'=>'-', 'display_name'=>self::adapter_safe_status_message((string)($result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)), 'journey_state'=>'', 'profile_completeness'=>'', 'total_paid'=>null, 'trusted'=>false]];
    }

    private static function report_service_case_rows(array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_service_case_summary_rows($scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return [(object)['name'=>self::adapter_safe_status_message((string)($result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)), 'total'=>null, 'unassigned'=>null, 'active'=>null, 'completed'=>null, 'credit'=>null, 'trusted'=>false]];
    }

    private static function report_source_service_rows(array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_source_service_summary_rows($scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return [(object)['service_name'=>self::adapter_safe_status_message((string)($result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)), 'total'=>null, 'customers'=>null, 'trusted'=>false]];
    }

    private static function report_safe_number($value, bool $trusted = true, bool $money = false, int $decimals = 0): string
    {
        if (!$trusted || $value === null || $value === '') return 'نامطمئن';
        return number_format_i18n($money ? (float)$value : (int)$value, $decimals);
    }

    private static function report_profile_label(?string $value): string
    {
        return ['complete'=>'کامل','incomplete'=>'ناقص','pending'=>'در انتظار بررسی'][$value?:''] ?? ($value?:'-');
    }

    private static function report_journey_label(?string $value): string
    {
        return ['no_request'=>'بدون درخواست','complaint'=>'نارضایتی','service_request'=>'درخواست خدمات','refund'=>'عودت وجه','بدون مسیر'=>'بدون مسیر'][$value?:''] ?? ($value?:'-');
    }

    private static function report_datetime($date): string
    {
        if (!$date) return '-';
        try {
            $source_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Tehran');
            $dt = new DateTimeImmutable((string)$date, $source_tz);
            $dt = $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
            [$jy, $jm, $jd] = self::gregorian_to_jalali((int)$dt->format('Y'), (int)$dt->format('m'), (int)$dt->format('d'));
            return sprintf('%04d/%02d/%02d %02d:%02d', $jy, $jm, $jd, (int)$dt->format('H'), (int)$dt->format('i'));
        } catch (Exception $e) {
            return (string)$date;
        }
    }

    private static function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    private static function customer_report_preview_rows(int $limit, int $offset, array $scope): array
    {
        $result = Biawin_CRM_Reporting_Consumer_Adapter::management_customer_preview_rows($limit, $offset, $scope);
        if (!empty($result['trusted']) && isset($result['data']['rows']) && is_array($result['data']['rows'])) return $result['data']['rows'];
        return [[
            'source_customer_id' => '-',
            'customer_code' => '-',
            'display_name' => self::adapter_safe_status_message((string)($result['status'] ?? Biawin_CRM_Reporting_Consumer_Adapter::BLOCKED)),
            'phone' => '-',
            'profile_completeness' => '',
            'journey_state' => '',
            'invoice_count' => null,
            'total_paid' => null,
            'first_assigned_at' => '',
            'first_agent_name' => '-',
            'current_agent_name' => '-',
            'last_action_title' => '-',
            'last_action_at' => '',
            'trusted' => false,
        ]];
    }

    public static function settings_page(): void
    {
        $services = implode("\n", self::other_services());
        echo '<div class="wrap bcrm" dir="rtl"><h1>تنظیمات CRM</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_save_settings');
        echo '<input type="hidden" name="action" value="bcrm_save_settings">';
        $wf = Biawin_CRM_Workflow_Service::settings();
        $font_key = class_exists('Biawin_CRM_Frontend') ? Biawin_CRM_Frontend::selected_portal_font_key() : sanitize_key((string)get_option('biawin_crm_portal_font', 'system'));
        echo '<table class="form-table"><tr><th>فونت پیش‌فرض پرتال</th><td><select name="portal_font">';
        if (class_exists('Biawin_CRM_Frontend')) {
            foreach (Biawin_CRM_Frontend::font_choices() as $key => $font) echo '<option value="' . esc_attr($key) . '"' . selected($font_key, $key, false) . '>' . esc_html($font['label']) . '</option>';
        }
        echo '</select><p class="description">کاربرها می‌توانند از هدر پرتال فونت را برای مرورگر خودشان هم تغییر دهند.</p></td></tr><tr><th>ضریب اعتبار خدمات</th><td><input name="credit_multiplier" type="number" step="0.1" value="' . esc_attr((string)$wf['credit_multiplier']) . '"><p class="description">فرمول پیش‌فرض: مجموع فاکتورهای تأییدشده × این ضریب</p></td></tr><tr><th>کف اعتبار (تومان)</th><td><input name="credit_floor" class="regular-text" value="' . esc_attr((string)$wf['credit_floor']) . '"></td></tr><tr><th>سقف اعتبار (تومان)</th><td><input name="credit_cap" class="regular-text" value="' . esc_attr((string)$wf['credit_cap']) . '"></td></tr><tr><th>تلرانس مغایرت مالی</th><td><input name="reconciliation_tolerance" class="regular-text" value="' . esc_attr((string)$wf['reconciliation_tolerance']) . '"><p class="description">پیش‌فرض صفر؛ فقط اختلاف داخل این محدوده پروفایل را کامل محسوب می‌کند.</p></td></tr><tr><th>خدمات متنی قدیمی فرم</th><td><textarea name="other_services" rows="5" class="large-text" placeholder="هر خدمت در یک خط">' . esc_textarea($services) . '</textarea></td></tr></table>';
        echo '<hr><h2>مدیریت نتیجه‌های تماس</h2><p class="description">این لیست مستقیماً در فرم کارشناس و تب‌های «نتیجه آخرین تماس» استفاده می‌شود. حذف یک وضعیت فقط آن را از انتخاب‌های جدید برمی‌دارد و تاریخچه تماس‌های قبلی پاک نمی‌شود.</p>';
        echo '<div class="bcrm-contact-outcomes"><table class="widefat striped" id="bcrm-contact-outcomes-table"><thead><tr><th>عنوان</th><th>کلید</th><th>ترتیب</th><th>فعال</th><th>حذف</th></tr></thead><tbody>';
        foreach (Biawin_CRM_Workflow_Service::contact_outcomes(false) as $item) {
            echo '<tr><td><input name="contact_outcome_label[]" class="regular-text" value="'.esc_attr($item['label']).'"></td><td><input name="contact_outcome_key[]" class="regular-text code" value="'.esc_attr($item['key']).'" readonly></td><td><input name="contact_outcome_sort[]" type="number" value="'.(int)$item['sort'].'" style="width:90px"></td><td><input type="hidden" name="contact_outcome_active[]" value="0"><label><input type="checkbox" name="contact_outcome_active_checked[]" value="'.esc_attr($item['key']).'" '.checked(!empty($item['active']),true,false).'> فعال</label></td><td><label><input type="checkbox" name="contact_outcome_delete[]" value="'.esc_attr($item['key']).'"> حذف</label></td></tr>';
        }
        echo '</tbody></table><div style="display:flex;gap:8px;align-items:end;margin-top:12px;flex-wrap:wrap"><label><span style="display:block;margin-bottom:4px">وضعیت جدید</span><input name="new_contact_outcome_label" class="regular-text" placeholder="مثلاً تماس در زمان نامناسب"></label><label><span style="display:block;margin-bottom:4px">کلید اختیاری</span><input name="new_contact_outcome_key" class="regular-text code" placeholder="مثلاً bad_time"></label></div></div>';
        echo '<hr><h2>تنظیمات مالی</h2><p class="description">گزینه‌های بررسی هر فیش از اینجا مدیریت می‌شوند. عنوان، ترتیب، فعال/غیرفعال بودن، حذف و افزودن گزینه برای هر سه گروه قابل مدیریت است.</p>';
        if (class_exists('Biawin_CRM_Finance_Review')) {
            foreach (['approve_types'=>'زیرگزینه‌های تأیید','reject_reasons'=>'دلایل عدم تأیید','refund_types'=>'زیرگزینه‌های عودت شده'] as $group=>$title) {
                echo '<h3>' . esc_html($title) . '</h3><table class="widefat striped"><thead><tr><th>عنوان</th><th>کلید</th><th>ترتیب</th><th>فعال</th><th>حذف</th></tr></thead><tbody>';
                foreach (Biawin_CRM_Finance_Review::financial_options($group, false) as $item) {
                    echo '<tr><td><input name="fin_' . esc_attr($group) . '_label[]" class="regular-text" value="' . esc_attr($item['label']) . '"></td><td><input name="fin_' . esc_attr($group) . '_key[]" class="regular-text code" value="' . esc_attr($item['key']) . '" readonly></td><td><input name="fin_' . esc_attr($group) . '_sort[]" type="number" value="' . (int)$item['sort'] . '" style="width:90px"></td><td><label><input type="checkbox" name="fin_' . esc_attr($group) . '_active[]" value="' . esc_attr($item['key']) . '" ' . checked(!empty($item['active']), true, false) . '> فعال</label></td><td>' . '<label><input type="checkbox" name="fin_' . esc_attr($group) . '_delete[]" value="' . esc_attr($item['key']) . '"> حذف</label>' . '</td></tr>';
                }
                echo '</tbody></table>';
                echo '<p><label>گزینه جدید <input name="fin_' . esc_attr($group) . '_new_label" class="regular-text" placeholder="عنوان گزینه جدید"></label> <label>کلید اختیاری <input name="fin_' . esc_attr($group) . '_new_key" class="regular-text code"></label></p>';
            }
        }
        echo '<p><button class="button button-primary">ذخیره تنظیمات</button></p></form>';
        global $wpdb;
        $interaction_table=Biawin_CRM_DB::table('interactions');
        $contact_col=$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$interaction_table` LIKE %s",'contact_phone'));
        echo '<hr><h2>سلامت دیتابیس افزونه</h2><p>نسخه دیتابیس ثبت‌شده: <code>'.esc_html((string)get_option('biawin_crm_db_version','-')).'</code> · ستون ذخیره شماره تماس: '.($contact_col?'<strong style="color:#15803d">سالم</strong>':'<strong style="color:#b91c1c">نیازمند ترمیم</strong>').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_repair_database'); echo '<input type="hidden" name="action" value="bcrm_repair_database"><button class="button">بررسی و ترمیم ساختار دیتابیس</button></form></div>';
    }

    public static function handle_settings(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_save_settings')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        $lines = preg_split('/\R/u', (string) wp_unslash($_POST['other_services'] ?? ''));
        $services = array_values(array_unique(array_filter(array_map('sanitize_text_field', array_map('trim', $lines)))));
        update_option('biawin_crm_other_services', $services);
        update_option('biawin_crm_credit_multiplier', max(0, (float) ($_POST['credit_multiplier'] ?? 2.5)));
        update_option('biawin_crm_credit_floor', max(0, Biawin_CRM_Normalizer::money($_POST['credit_floor'] ?? 200000000)));
        update_option('biawin_crm_credit_cap', max(0, Biawin_CRM_Normalizer::money($_POST['credit_cap'] ?? 1500000000)));
        update_option('biawin_crm_reconciliation_tolerance', max(0, Biawin_CRM_Normalizer::money($_POST['reconciliation_tolerance'] ?? 0)));
        $portal_font = sanitize_key((string)($_POST['portal_font'] ?? 'system'));
        $font_choices = class_exists('Biawin_CRM_Frontend') ? Biawin_CRM_Frontend::font_choices() : ['system'=>true];
        update_option('biawin_crm_portal_font', isset($font_choices[$portal_font]) ? $portal_font : 'system');

        $labels = array_values((array)($_POST['contact_outcome_label'] ?? []));
        $keys = array_values((array)($_POST['contact_outcome_key'] ?? []));
        $sorts = array_values((array)($_POST['contact_outcome_sort'] ?? []));
        $active_keys = array_map('sanitize_key', (array)($_POST['contact_outcome_active_checked'] ?? []));
        $delete_keys = array_map('sanitize_key', (array)($_POST['contact_outcome_delete'] ?? []));
        $existing = [];
        foreach (Biawin_CRM_Workflow_Service::contact_outcomes(false) as $item) $existing[$item['key']] = $item;
        $outcomes = [];
        $used = [];
        $count = max(count($labels), count($keys));
        for ($i=0; $i<$count; $i++) {
            $key = sanitize_key((string)($keys[$i] ?? ''));
            $label = sanitize_text_field((string)($labels[$i] ?? ''));
            if ($key === '' || $label === '' || isset($used[$key]) || in_array($key, $delete_keys, true)) continue;
            $used[$key] = true;
            $old = $existing[$key] ?? null;
            $outcomes[] = [
                'key' => $key,
                'label' => $label,
                'operational_status' => $old['operational_status'] ?? substr('contact_' . $key, 0, 40),
                'active' => in_array($key, $active_keys, true) ? 1 : 0,
                'sort' => (int)($sorts[$i] ?? (($i+1)*10)),
            ];
        }
        $new_label = sanitize_text_field((string)($_POST['new_contact_outcome_label'] ?? ''));
        if ($new_label !== '') {
            $new_key = sanitize_key((string)($_POST['new_contact_outcome_key'] ?? ''));
            if ($new_key === '') $new_key = sanitize_key('status_' . substr(md5($new_label . microtime(true)), 0, 8));
            if (!isset($used[$new_key])) {
                $outcomes[] = ['key'=>$new_key,'label'=>$new_label,'operational_status'=>substr('contact_'.$new_key,0,40),'active'=>1,'sort'=>(count($outcomes)+1)*10];
            }
        }
        if (!$outcomes) $outcomes = Biawin_CRM_Workflow_Service::default_contact_outcomes();
        $has_active = false; foreach ($outcomes as $item) if (!empty($item['active'])) { $has_active = true; break; }
        if (!$has_active && isset($outcomes[0])) $outcomes[0]['active'] = 1;
        update_option('biawin_crm_contact_outcomes', $outcomes);
        if (class_exists('Biawin_CRM_Finance_Review')) {
            foreach (['approve_types','reject_reasons','refund_types'] as $group) {
                $labels=array_values((array)($_POST['fin_'.$group.'_label']??[])); $keys=array_values((array)($_POST['fin_'.$group.'_key']??[])); $sorts=array_values((array)($_POST['fin_'.$group.'_sort']??[]));
                $active=array_map('sanitize_key',(array)($_POST['fin_'.$group.'_active']??[])); $delete=array_map('sanitize_key',(array)($_POST['fin_'.$group.'_delete']??[])); $items=[]; $used=[];
                for($i=0;$i<max(count($labels),count($keys));$i++){ $key=sanitize_key((string)($keys[$i]??'')); $label=sanitize_text_field((string)($labels[$i]??'')); if($key===''||$label===''||isset($used[$key])||in_array($key,$delete,true)) continue; $used[$key]=1; $items[]=['key'=>$key,'label'=>$label,'active'=>in_array($key,$active,true)?1:0,'sort'=>(int)($sorts[$i]??(($i+1)*10))]; }
                $nl=sanitize_text_field((string)($_POST['fin_'.$group.'_new_label']??'')); if($nl!==''){ $nk=sanitize_key((string)($_POST['fin_'.$group.'_new_key']??'')); if($nk==='') $nk=substr($group,0,12).'_'.substr(md5($nl.microtime(true)),0,8); if(!isset($used[$nk])) $items[]=['key'=>$nk,'label'=>$nl,'active'=>1,'sort'=>(count($items)+1)*10]; }
                if(!$items) $items=Biawin_CRM_Finance_Review::financial_option_groups()[$group];
                update_option('biawin_crm_finance_'.$group,$items);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-settings'));
        exit;
    }

    public static function financial_page(): void
    {
        if (empty($_GET['legacy']) && (isset($_GET['case_id']) || empty($_GET['tab']) || in_array(sanitize_key($_GET['tab'] ?? ''), ['dashboard','new','assigned','review','approved','rejected','returned','assign','reports'], true))) {
            Biawin_CRM_Deficiency_Workflow::render_admin_page('finance');
            return;
        }
        global $wpdb;
        $uid=get_current_user_id(); $roles=(array)wp_get_current_user()->roles;
        $fin_where="f.status NOT IN ('closed','cancelled')";
        if(!current_user_can('bcrm_manage_cases') && !in_array('bcrm_financial_supervisor',$roles,true)) $fin_where.=$wpdb->prepare(' AND (f.assigned_user_id IS NULL OR f.assigned_user_id=%d)',$uid);
        $rows=$wpdb->get_results("SELECT f.*,c.display_name,c.customer_code FROM ".Biawin_CRM_DB::table('financial_cases')." f LEFT JOIN ".Biawin_CRM_DB::table('customers')." c ON c.id=f.customer_id WHERE $fin_where ORDER BY FIELD(f.status,'new','in_progress','need_evidence','resolved'),f.id DESC LIMIT 200");
        echo '<div class="wrap bcrm" dir="rtl"><h1>کارتابل مالی قدیمی</h1><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=biawin-crm-financial')).'">بازگشت به Workflow جدید تأیید مالی</a></p><p>پروفایل‌های ناقص از اینجا پیگیری می‌شوند. تکمیل واقعی فقط وقتی رخ می‌دهد که مبلغ اعلامی با مجموع فاکتورهای تأییدشده برابر شود.</p><table class="widefat striped"><thead><tr><th>کد پرونده</th><th>مشتری</th><th>مبلغ اعلامی</th><th>سیستم</th><th>اختلاف</th><th>وضعیت</th><th>اقدام</th></tr></thead><tbody>';
        if(!$rows) echo '<tr><td colspan="7">پرونده مالی بازی وجود ندارد.</td></tr>';
        foreach($rows as $r){
            echo '<tr><td>'.esc_html($r->case_code).'</td><td>'.esc_html($r->display_name ?: '-').'</td><td>'.esc_html($r->declared_amount===null?'-':number_format_i18n((float)$r->declared_amount)).'</td><td>'.esc_html(number_format_i18n((float)$r->system_amount)).'</td><td>'.esc_html(number_format_i18n(abs((float)$r->difference_amount))).'</td><td>'.esc_html($r->status).'</td><td><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="button" href="'.esc_url(self::portal_customer_url((int)$r->customer_id)).'">باز کردن پروفایل</a>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:5px;align-items:center">'; wp_nonce_field('bcrm_financial_case_'.$r->id); echo '<input type="hidden" name="action" value="bcrm_financial_case"><input type="hidden" name="case_id" value="'.(int)$r->id.'"><select name="status"><option value="in_progress">در حال بررسی</option><option value="need_evidence">نیازمند فیش/مدرک</option><option value="resolved">بررسی انجام شد</option><option value="cancelled">لغو</option></select><input name="note" placeholder="یادداشت مالی"><button class="button">ثبت</button></form></div></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function complaints_page(): void
    {
        global $wpdb;
        $uid=get_current_user_id(); $roles=(array)wp_get_current_user()->roles;
        $comp_where='1=1';
        if(!current_user_can('bcrm_manage_cases') && !in_array('bcrm_complaint_supervisor',$roles,true)) $comp_where.=$wpdb->prepare(' AND (x.owner_user_id IS NULL OR x.owner_user_id=%d)',$uid);
        $rows=$wpdb->get_results("SELECT x.*,c.display_name,c.customer_code FROM ".Biawin_CRM_DB::table('complaints')." x LEFT JOIN ".Biawin_CRM_DB::table('customers')." c ON c.id=x.customer_id WHERE $comp_where ORDER BY x.status='open' DESC,x.id DESC LIMIT 200");
        echo '<div class="wrap bcrm" dir="rtl"><h1>کارتابل نارضایتی و پیگیری</h1><table class="widefat striped"><thead><tr><th>شناسه</th><th>مشتری</th><th>موضوع</th><th>اولویت</th><th>وضعیت</th><th>خروجی</th><th></th></tr></thead><tbody>';
        foreach($rows as $r) echo '<tr><td>#'.(int)$r->id.'</td><td>'.esc_html($r->display_name ?: '-').'</td><td>'.esc_html($r->subject ?: '-').'</td><td>'.esc_html($r->priority).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->resolution_outcome ?: '-').'</td><td><a class="button" href="'.esc_url(self::portal_customer_url((int)$r->customer_id)).'">پروفایل</a></td></tr>';
        echo '</tbody></table></div>';
    }

    public static function service_cases_page(): void
    {
        global $wpdb;
        $uid=get_current_user_id();
        $supervised_units=Biawin_CRM_Workflow_Service::supervised_unit_keys($uid);
        if(current_user_can('bcrm_manage_cases')) {
            $where='1=1';
        } elseif($supervised_units) {
            $ph=implode(',',array_fill(0,count($supervised_units),'%s'));
            $where=$wpdb->prepare("(sc.unit_key IN ($ph) AND sc.status NOT IN ('completed','rejected','cancelled'))",$supervised_units);
        } else {
            $where=$wpdb->prepare('(sc.assigned_user_id=%d)', $uid);
        }
        $rows=$wpdb->get_results("SELECT sc.*,s.name service_name,c.display_name,u.display_name agent_name FROM ".Biawin_CRM_DB::table('service_cases')." sc LEFT JOIN ".Biawin_CRM_DB::table('service_catalog')." s ON s.id=sc.service_id LEFT JOIN ".Biawin_CRM_DB::table('customers')." c ON c.id=sc.customer_id LEFT JOIN {$wpdb->users} u ON u.ID=sc.assigned_user_id WHERE $where ORDER BY sc.status='unassigned' DESC,sc.id DESC LIMIT 250");
        echo '<div class="wrap bcrm" dir="rtl"><h1>کارتابل خدمات تخصصی</h1><table class="widefat striped"><thead><tr><th>کد</th><th>خدمت</th><th>مشتری</th><th>اعتبار</th><th>واحد</th><th>کارشناس</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';
        foreach($rows as $r){echo '<tr><td>'.esc_html($r->case_code).'</td><td>'.esc_html($r->service_name ?: '-').'</td><td><a href="'.esc_url(self::portal_customer_url((int)$r->customer_id)).'">'.esc_html($r->display_name ?: '-').'</a></td><td>'.esc_html(number_format_i18n((float)$r->credit_amount)).'</td><td>'.esc_html($r->unit_key ?: '-').'</td><td>'.esc_html($r->agent_name ?: 'تخصیص‌نیافته').'</td><td>'.esc_html($r->status).'</td><td>';
            if(current_user_can('bcrm_manage_cases') || Biawin_CRM_Workflow_Service::can_supervise_unit((string)$r->unit_key)){
                $case_agents=Biawin_CRM_Workflow_Service::unit_agents((string)$r->unit_key);
                if($case_agents){echo '<form style="display:flex;gap:5px" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('bcrm_assign_service_case');echo '<input type="hidden" name="action" value="bcrm_assign_service_case"><input type="hidden" name="case_id" value="'.(int)$r->id.'"><select name="agent_user_id">';foreach($case_agents as $a)echo '<option value="'.(int)$a->ID.'">'.esc_html($a->display_name).'</option>';echo '</select><button class="button">تخصیص</button></form>';}
            }
            echo '</td></tr>';}
        echo '</tbody></table></div>';
    }

    public static function services_page(): void
    {
        global $wpdb;
        $rows=$wpdb->get_results("SELECT * FROM ".Biawin_CRM_DB::table('service_catalog')." ORDER BY sort_order,id");
        echo '<div class="wrap bcrm" dir="rtl"><h1>کاتالوگ خدمات و پروموشن‌ها</h1><div class="bcrm-card" style="max-width:1000px"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('bcrm_save_service');echo '<input type="hidden" name="action" value="bcrm_save_service"><table class="form-table"><tr><th>نام خدمت</th><td><input required name="name" class="regular-text"></td></tr><tr><th>کد</th><td><input required name="service_code" class="regular-text" placeholder="مثلاً furniture"></td></tr><tr><th>ارزش اعتباری</th><td><input required name="credit_cost" class="regular-text"></td></tr><tr><th>واحد مقصد</th><td><input name="unit_key" class="regular-text"></td></tr><tr><th>توضیح</th><td><textarea name="description" class="large-text"></textarea></td></tr></table><button class="button button-primary">افزودن خدمت</button></form></div><h2>خدمات موجود</h2><table class="widefat striped"><thead><tr><th>کد</th><th>نام</th><th>اعتبار</th><th>واحد</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';
        foreach($rows as $r){
            $fid='bcrm-service-form-'.(int)$r->id;
            echo '<tr><td><code>'.esc_html($r->service_code).'</code></td><td><input form="'.esc_attr($fid).'" name="name" value="'.esc_attr($r->name).'" required></td><td><input form="'.esc_attr($fid).'" name="credit_cost" value="'.esc_attr((string)(float)$r->credit_cost).'" required></td><td><input form="'.esc_attr($fid).'" name="unit_key" value="'.esc_attr($r->unit_key ?: '').'"></td><td><select form="'.esc_attr($fid).'" name="is_active"><option value="1" '.selected((int)$r->is_active,1,false).'>فعال</option><option value="0" '.selected((int)$r->is_active,0,false).'>غیرفعال</option></select></td><td><form id="'.esc_attr($fid).'" method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_update_service_'.$r->id); echo '<input type="hidden" name="action" value="bcrm_update_service"><input type="hidden" name="service_id" value="'.(int)$r->id.'"><button class="button">ذخیره</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function refunds_page(): void
    {
        global $wpdb;
        $uid=get_current_user_id(); $roles=(array)wp_get_current_user()->roles;
        $refund_where='1=1';
        if(!current_user_can('bcrm_manage_cases') && !in_array('bcrm_refund_supervisor',$roles,true)) $refund_where.=$wpdb->prepare(' AND (r.assigned_user_id IS NULL OR r.assigned_user_id=%d)',$uid);
        $rows=$wpdb->get_results("SELECT r.*,c.display_name FROM ".Biawin_CRM_DB::table('refunds')." r LEFT JOIN ".Biawin_CRM_DB::table('customers')." c ON c.id=r.customer_id WHERE $refund_where ORDER BY FIELD(r.status,'new','review','approved','ready_payment','paid','rejected','cancelled','closed'),r.id DESC LIMIT 200");
        echo '<div class="wrap bcrm" dir="rtl"><h1>کارتابل عودت وجه</h1><table class="widefat striped"><thead><tr><th>کد</th><th>مشتری</th><th>درخواستی</th><th>تأییدشده</th><th>دلیل</th><th>وضعیت</th><th>پرداخت</th><th>عملیات</th></tr></thead><tbody>';
        if(!$rows) echo '<tr><td colspan="8">پرونده عودتی وجود ندارد.</td></tr>';
        foreach($rows as $r){
            echo '<tr><td>'.esc_html($r->refund_code).'</td><td><a href="'.esc_url(self::portal_customer_url((int)$r->customer_id)).'">'.esc_html($r->display_name ?: '-').'</a></td><td>'.esc_html($r->requested_amount===null?'-':number_format_i18n((float)$r->requested_amount)).'</td><td>'.esc_html($r->approved_amount===null?'-':number_format_i18n((float)$r->approved_amount)).'</td><td>'.esc_html($r->reason ?: '-').'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->payment_reference ?: '-').'</td><td>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:grid;gap:4px;min-width:250px">'; wp_nonce_field('bcrm_refund_case_'.$r->id); echo '<input type="hidden" name="action" value="bcrm_refund_case"><input type="hidden" name="refund_id" value="'.(int)$r->id.'"><select name="status"><option value="review">در حال بررسی</option><option value="approved">تأیید</option><option value="ready_payment">آماده پرداخت</option><option value="paid">پرداخت‌شده</option><option value="rejected">رد</option><option value="cancelled">لغو</option><option value="closed">بسته‌شده</option></select><input name="approved_amount" placeholder="مبلغ تأییدشده" value="'.esc_attr($r->approved_amount===null?'':(string)(float)$r->approved_amount).'"><input name="payment_reference" placeholder="شناسه/پیگیری پرداخت" value="'.esc_attr($r->payment_reference ?: '').'"><button class="button">ثبت وضعیت</button></form>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /** v0.8.62.0 — صفحه «ثبت عودت‌ها»: آپلود اکسل عودت‌ها با سرتیترهای customer id / وضعیت عودت / مبلغ عودت. */
    public static function refund_register_page(): void
    {
        if (!current_user_can('bcrm_refund_register') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $result = get_transient('bcrm_refund_register_result_' . get_current_user_id());
        if (is_array($result)) delete_transient('bcrm_refund_register_result_' . get_current_user_id());
        $recent = $wpdb->get_results("SELECT rr.*, c.display_name, c.source_customer_id, u.display_name actor_name FROM " . Biawin_CRM_DB::table('refund_registers') . " rr LEFT JOIN " . Biawin_CRM_DB::table('customers') . " c ON c.id=rr.customer_id LEFT JOIN {$wpdb->users} u ON u.ID=rr.created_by WHERE rr.source_type IN ('excel','used') ORDER BY rr.id DESC LIMIT 100");
        echo '<div class="wrap bcrm" dir="rtl"><h1>ثبت عودت‌ها از اکسل</h1>';
        if (is_array($result)) {
            echo '<div class="notice notice-' . ($result['ok_count'] > 0 ? 'success' : 'warning') . ' inline"><p><strong>نتیجه آپلود (Batch: ' . esc_html($result['batch_code']) . '):</strong> ' . esc_html($result['message']) . '</p>';
            if (!empty($result['not_found'])) {
                echo '<p style="margin:6px 18px 2px 0"><strong>ردیف‌های با مشتری پیدا نشده:</strong></p><ul style="margin:0 18px 8px 0">';
                foreach (array_slice($result['not_found'], 0, 30) as $nf) echo '<li>ردیف ' . number_format_i18n((int)$nf['row']) . ' → CustomerID <code>' . esc_html((string)$nf['customer_id']) . '</code></li>';
                if (count($result['not_found']) > 30) echo '<li>و ' . number_format_i18n(count($result['not_found']) - 30) . ' مورد دیگر…</li>';
                echo '</ul>';
            }
            if (!empty($result['invalid'])) {
                echo '<p style="margin:6px 18px 2px 0"><strong>ردیف‌های نامعتبر:</strong></p><ul style="margin:0 18px 8px 0">';
                foreach (array_slice($result['invalid'], 0, 30) as $inv) echo '<li>ردیف ' . number_format_i18n((int)$inv['row']) . (!empty($inv['customer_id']) ? ' (CustomerID <code>' . esc_html((string)$inv['customer_id']) . '</code>)' : '') . ' → ' . esc_html((string)$inv['reason']) . '</li>';
                if (count($result['invalid']) > 30) echo '<li>و ' . number_format_i18n(count($result['invalid']) - 30) . ' مورد دیگر…</li>';
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '<div class="bcrm-card" style="max-width:860px"><h2>آپلود فایل اکسل</h2><p class="description">سرتیترهای فایل: <code>کد فاکتور</code> (اولویت تطبیق — یونیک) — <code>customer id</code> (در نبود کد فاکتور؛ ممکن است تکراری باشد) — <code>وضعیت عودت</code> (۰ یا ۱) — <code>مبلغ عودت</code>. حداقل یکی از دو کلید (کد فاکتور یا Customer ID) باید باشد؛ اگر هر دو باشند کد فاکتور ملاک است. هر مشتری با وضعیت ۱، تگ «عودت قطعی» می‌گیرد؛ آپلود مجدد همان کلید، مبلغ را جایگزین می‌کند و رکورد قبلی در تاریخچه می‌ماند. فرمول مانده: مجموع فاکتورها − آخرین عودت قطعی − آخرین «استفاده شده» (آپلود جداگانه در منوی «ثبت استفاده‌شده‌ها»).</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('bcrm_refund_register_upload');
        echo '<input type="hidden" name="action" value="bcrm_refund_register_upload"><p><input type="file" name="refund_register_file" accept=".xlsx,.csv" required></p><p><button class="button button-primary">آپلود و ثبت عودت‌ها</button></p></form></div>';
        self::render_refund_purge_tool('excel');
        echo '<h2 style="margin-top:18px">۱۰۰ ثبت اخیر</h2><table class="widefat striped"><thead><tr><th>زمان</th><th>مشتری</th><th>CustomerID</th><th>کد فاکتور</th><th>مبلغ</th><th>منبع</th><th>Batch</th><th>ثبت‌کننده</th><th>ردیف اکسل</th></tr></thead><tbody>';
        if (!$recent) echo '<tr><td colspan="9">هنوز عودتی ثبت نشده است.</td></tr>';
        foreach ((array)$recent as $r) {
            $src = $r->source_type === 'excel' ? 'اکسل (قطعی)' : ($r->source_type === 'used' ? 'اکسل (استفاده‌شده)' : 'نتیجه تماس (اعلامی)');
            echo '<tr><td>' . esc_html($r->created_at) . '</td><td><a href="' . esc_url(self::portal_customer_url((int)$r->customer_id)) . '">' . esc_html($r->display_name ?: ('Customer #' . (int)$r->customer_id)) . '</a></td><td>' . esc_html($r->source_customer_id ?: '-') . '</td><td>' . esc_html($r->invoice_code ?: '-') . '</td><td>' . ($r->refund_amount > 0 ? esc_html(number_format_i18n((float)$r->refund_amount)) : '—') . '</td><td>' . esc_html($src) . '</td><td>' . esc_html($r->batch_code ?: '-') . '</td><td>' . esc_html(($r->actor_name ?? '-') ?: '-') . '</td><td>' . ($r->source_row ? number_format_i18n((int)$r->source_row) : '-') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /** v0.8.62.11: صفحه جداگانه آپلود اکسل «استفاده شده» (تطبیق با کد فاکتور یا Customer ID).
     *  رکوردها source_type='used' می‌گیرند؛ هیچ تگی نمی‌گیرند و فقط در فرمول مانده لحاظ می‌شوند. */
    public static function refund_used_page(): void
    {
        if (!current_user_can('bcrm_refund_register') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $result = get_transient('bcrm_refund_used_result_' . get_current_user_id());
        if (is_array($result)) delete_transient('bcrm_refund_used_result_' . get_current_user_id());
        $recent = $wpdb->get_results("SELECT rr.*, c.display_name, c.source_customer_id, u.display_name actor_name FROM " . Biawin_CRM_DB::table('refund_registers') . " rr LEFT JOIN " . Biawin_CRM_DB::table('customers') . " c ON c.id=rr.customer_id LEFT JOIN {$wpdb->users} u ON u.ID=rr.created_by WHERE rr.source_type='used' ORDER BY rr.id DESC LIMIT 100");
        echo '<div class="wrap bcrm" dir="rtl"><h1>ثبت «استفاده شده» از اکسل</h1>';
        if (is_array($result)) {
            echo '<div class="notice notice-' . ($result['ok_count'] > 0 ? 'success' : 'warning') . ' inline"><p><strong>نتیجه آپلود (Batch: ' . esc_html($result['batch_code']) . '):</strong> ' . esc_html($result['message']) . '</p>';
            if (!empty($result['not_found'])) {
                echo '<p style="margin:6px 18px 2px 0"><strong>ردیف‌هایی که مشتری/فاکتورشان پیدا نشد:</strong></p><ul style="margin:0 18px 8px 0">';
                foreach (array_slice($result['not_found'], 0, 30) as $nf) echo '<li>ردیف ' . number_format_i18n((int)$nf['row']) . ' → <code>' . esc_html((string)($nf['key'] ?? '')) . '</code>' . (($nf['kind'] ?? '') === 'invoice_code' ? ' (کد فاکتور)' : ' (CustomerID)') . '</li>';
                if (count($result['not_found']) > 30) echo '<li>و ' . number_format_i18n(count($result['not_found']) - 30) . ' مورد دیگر…</li>';
                echo '</ul>';
            }
            if (!empty($result['invalid'])) {
                echo '<p style="margin:6px 18px 2px 0"><strong>ردیف‌های نامعتبر:</strong></p><ul style="margin:0 18px 8px 0">';
                foreach (array_slice($result['invalid'], 0, 30) as $inv) echo '<li>ردیف ' . number_format_i18n((int)$inv['row']) . (!empty($inv['key']) ? ' (<code>' . esc_html((string)$inv['key']) . '</code>)' : '') . ' → ' . esc_html((string)$inv['reason']) . '</li>';
                if (count($result['invalid']) > 30) echo '<li>و ' . number_format_i18n(count($result['invalid']) - 30) . ' مورد دیگر…</li>';
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '<div class="bcrm-card" style="max-width:860px"><h2>آپلود فایل اکسل «استفاده شده»</h2><p class="description">سرتیترهای فایل: <code>کد فاکتور</code> (اولویت تطبیق — یونیک) — <code>customer id</code> (در نبود کد فاکتور) — <code>مبلغ عودت</code>. ستون «وضعیت عودت» اختیاری است (خالی = معتبر). هر ردیف بر اساس کد فاکتور یا Customer ID تطبیق داده می‌شود؛ آپلود مجدد همان کلید، مبلغ را جایگزین می‌کند. رکوردهای «استفاده شده» هیچ تگی نمی‌گیرند و فقط در فرمول مانده (مجموع فاکتورها − عودت قطعی − استفاده شده) لحاظ می‌شوند.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('bcrm_refund_used_upload');
        echo '<input type="hidden" name="action" value="bcrm_refund_used_upload"><p><input type="file" name="refund_register_file" accept=".xlsx,.csv" required></p><p><button class="button button-primary">آپلود و ثبت «استفاده شده»ها</button></p></form></div>';
        self::render_refund_purge_tool('used');
        echo '<h2 style="margin-top:18px">۱۰۰ ثبت اخیر «استفاده شده»</h2><table class="widefat striped"><thead><tr><th>زمان</th><th>مشتری</th><th>CustomerID</th><th>کد فاکتور</th><th>مبلغ استفاده</th><th>Batch</th><th>ثبت‌کننده</th><th>ردیف اکسل</th></tr></thead><tbody>';
        if (!$recent) echo '<tr><td colspan="8">هنوز «استفاده شده»ای ثبت نشده است.</td></tr>';
        foreach ((array)$recent as $r) {
            echo '<tr><td>' . esc_html($r->created_at) . '</td><td><a href="' . esc_url(self::portal_customer_url((int)$r->customer_id)) . '">' . esc_html($r->display_name ?: ('Customer #' . (int)$r->customer_id)) . '</a></td><td>' . esc_html($r->source_customer_id ?: '-') . '</td><td>' . esc_html($r->invoice_code ?: '-') . '</td><td>' . esc_html(number_format_i18n((float)$r->refund_amount)) . '</td><td>' . esc_html($r->batch_code ?: '-') . '</td><td>' . esc_html(($r->actor_name ?? '-') ?: '-') . '</td><td>' . ($r->source_row ? number_format_i18n((int)$r->source_row) : '-') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /** v0.8.62.13: ابزار حذف رکوردهای اکسل عودت/استفاده‌شده + پاک‌سازی تگ‌های یتیم. */
    public static function handle_refund_purge(): void
    {
        if (!current_user_can('bcrm_refund_register') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_refund_purge');
        $source_type = ($_POST['purge_source'] ?? '') === 'used' ? 'used' : 'excel';
        $only_no_invoice = !empty($_POST['purge_only_no_invoice']);
        $page = $source_type === 'used' ? 'biawin-crm-refund-used' : 'biawin-crm-refund-register';
        if (!empty($_POST['purge_confirm'])) {
            if (!class_exists('Biawin_CRM_Refund_Register')) require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-refund-register.php';
            $res = Biawin_CRM_Refund_Register::purge_excel_rows($source_type, $only_no_invoice, sanitize_text_field($_POST['purge_batch'] ?? ''));
            set_transient('bcrm_refund_purge_result_' . get_current_user_id(), [
                'ok' => true, 'deleted' => (int)$res['deleted'], 'tags_removed' => (int)$res['tags_removed'],
                'source_type' => $source_type, 'only_no_invoice' => $only_no_invoice,
            ], 120);
        }
        wp_safe_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }

    /** v0.8.62.13: نوار ابزار حذف + نمایش نتیجه — مشترک بین دو صفحه عودت/استفاده‌شده. */
    private static function render_refund_purge_tool(string $source_type): void
    {
        global $wpdb;
        $res = get_transient('bcrm_refund_purge_result_' . get_current_user_id());
        if (is_array($res)) {
            delete_transient('bcrm_refund_purge_result_' . get_current_user_id());
            $label = $res['source_type'] === 'used' ? '«استفاده شده»' : 'عودت قطعی (اکسل)';
            echo '<div class="notice notice-success inline"><p><strong>حذف انجام شد:</strong> ' . number_format_i18n((int)$res['deleted']) . ' رکورد ' . esc_html($label) . ' حذف شد' . ((int)$res['tags_removed'] ? ' و ' . number_format_i18n((int)$res['tags_removed']) . ' تگ قطعی یتیم هم پاک شد' : '') . '.</p></div>';
        }
        $registers = Biawin_CRM_DB::table('refund_registers');
        $st = $source_type === 'used' ? 'used' : 'excel';
        $total_all = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $registers WHERE source_type=%s", $st));
        $total_no_inv = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $registers WHERE source_type=%s AND invoice_code=''", $st));
        $page_slug = $source_type === 'used' ? 'biawin-crm-refund-used' : 'biawin-crm-refund-register';
        echo '<details class="bcrm-card" style="max-width:860px;margin-top:12px"><summary style="cursor:pointer"><strong>🗑 حذف رکوردهای قبلی (' . number_format_i18n($total_all) . ' رکورد در این بخش)</strong></summary>';
        echo '<p class="description">برای شروع دیتای تازه، رکوردهای قبلی همین بخش را حذف کنید. رکوردهای «اعلامی کارشناس» (نتیجه تماس) هرگز حذف نمی‌شوند. اگر بعد از حذف مشتری دیگر هیچ رکورد عودت اکسلی نداشته باشد، تگ «عودت قطعی» او هم خودکار پاک می‌شود. همه حذف‌ها در لاگ ممیزی ثبت می‌شود. این عمل قابل بازگشت نیست.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'مطمئن هستید؟ رکوردهای انتخاب‌شده برای همیشه حذف می‌شوند.\')">';
        wp_nonce_field('bcrm_refund_purge');
        echo '<input type="hidden" name="action" value="bcrm_refund_purge"><input type="hidden" name="purge_source" value="' . esc_attr($st) . '">';
        echo '<p><label><input type="checkbox" name="purge_only_no_invoice" value="1"> فقط رکوردهای قدیمی «بدون کد فاکتور» (' . number_format_i18n($total_no_inv) . ' رکورد — آپلودهای Customer-ID محور قبلی)</label></p>';
        echo '<p><label>فقط یک Batch خاص (کد Batch مثل <code>RR-xxxx</code>؛ خالی = همه): <input type="text" name="purge_batch" style="width:220px"></label></p>';
        echo '<p><label><input type="checkbox" name="purge_confirm" value="1" required> تأیید می‌کنم رکوردهای انتخاب‌شده حذف شوند</label></p>';
        echo '<p><button class="button button-danger" style="color:#b32d2e;border-color:#b32d2e">حذف رکوردها</button></p></form></details>';
    }

    public static function handle_refund_register_upload(): void
    {
        if (!current_user_can('bcrm_refund_register') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_refund_register_upload');
        if (empty($_FILES['refund_register_file']) || !is_array($_FILES['refund_register_file']) || ($_FILES['refund_register_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_die('فایل اکسل انتخاب نشده یا آپلود آن کامل نشد.');
        }
        $name = sanitize_file_name((string)$_FILES['refund_register_file']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) wp_die('فقط XLSX یا CSV پذیرفته می‌شود. فایل XLS قدیمی را اول به XLSX تبدیل کنید.');
        if (!class_exists('Biawin_CRM_Refund_Register')) {
            require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-refund-register.php';
        }
        $parsed = Biawin_CRM_Refund_Register::parse_upload((string)$_FILES['refund_register_file']['tmp_name'], $name);
        if (!empty($parsed['errors'])) wp_die('خواندن فایل انجام نشد: ' . esc_html(implode(' | ', array_map('strval', $parsed['errors']))));
        if (!$parsed['rows']) wp_die('هیچ ردیفی در فایل پیدا نشد.');
        $canonical_rows = [];
        foreach ($parsed['rows'] as $row) {
            $mapped = [];
            foreach ((array)$row as $header => $value) {
                $canonical = Biawin_CRM_Refund_Register::canonical_header((string)$header);
                if ($canonical !== '' && !isset($mapped[$canonical])) $mapped[$canonical] = $value;
            }
            $canonical_rows[] = $mapped;
        }
        $result = Biawin_CRM_Refund_Register::import_batch($canonical_rows, get_current_user_id(), Biawin_CRM_Refund_Register::SOURCE_EXCEL_REFUND);
        set_transient('bcrm_refund_register_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-refund-register'));
        exit;
    }

    /** v0.8.62.11: هندلر آپلود اکسل «استفاده شده» — همان موتور تجزیه/ثبت با source_type='used'. */
    public static function handle_refund_used_upload(): void
    {
        if (!current_user_can('bcrm_refund_register') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_refund_used_upload');
        if (empty($_FILES['refund_register_file']) || !is_array($_FILES['refund_register_file']) || ($_FILES['refund_register_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_die('فایل اکسل انتخاب نشده یا آپلود آن کامل نشد.');
        }
        $name = sanitize_file_name((string)$_FILES['refund_register_file']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) wp_die('فقط XLSX یا CSV پذیرفته می‌شود. فایل XLS قدیمی را اول به XLSX تبدیل کنید.');
        if (!class_exists('Biawin_CRM_Refund_Register')) {
            require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-refund-register.php';
        }
        $parsed = Biawin_CRM_Refund_Register::parse_upload((string)$_FILES['refund_register_file']['tmp_name'], $name);
        if (!empty($parsed['errors'])) wp_die('خواندن فایل انجام نشد: ' . esc_html(implode(' | ', array_map('strval', $parsed['errors']))));
        if (!$parsed['rows']) wp_die('هیچ ردیفی در فایل پیدا نشد.');
        $canonical_rows = [];
        foreach ($parsed['rows'] as $row) {
            $mapped = [];
            foreach ((array)$row as $header => $value) {
                $canonical = Biawin_CRM_Refund_Register::canonical_header((string)$header);
                if ($canonical !== '' && !isset($mapped[$canonical])) $mapped[$canonical] = $value;
            }
            $canonical_rows[] = $mapped;
        }
        $result = Biawin_CRM_Refund_Register::import_batch($canonical_rows, get_current_user_id(), Biawin_CRM_Refund_Register::SOURCE_EXCEL_USED);
        set_transient('bcrm_refund_used_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-refund-used'));
        exit;
    }

    public static function handle_save_service(): void
    {
        if(!current_user_can('bcrm_manage_cases')||!check_admin_referer('bcrm_save_service')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $code=sanitize_key($_POST['service_code']??'');
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        $cost=Biawin_CRM_Normalizer::money($_POST['credit_cost']??0);
        if($code===''||$name===''||$cost<0) wp_die('اطلاعات خدمت نامعتبر است.');
        $ok=$wpdb->insert(Biawin_CRM_DB::table('service_catalog'),['service_code'=>$code,'name'=>$name,'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??'')),'credit_cost'=>$cost,'unit_key'=>sanitize_key($_POST['unit_key']??''),'is_active'=>1,'sort_order'=>0,'created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()]);
        if($ok===false) wp_die('ذخیره خدمت انجام نشد: '.esc_html($wpdb->last_error ?: 'خطای دیتابیس'));
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-services'));exit;
    }

    public static function handle_update_service(): void
    {
        if(!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $id=absint($_POST['service_id']??0); check_admin_referer('bcrm_update_service_'.$id);
        global $wpdb; $name=sanitize_text_field(wp_unslash($_POST['name']??'')); $cost=Biawin_CRM_Normalizer::money($_POST['credit_cost']??0);
        if(!$id || $name==='' || $cost<0) wp_die('اطلاعات خدمت نامعتبر است.');
        $ok=$wpdb->update(Biawin_CRM_DB::table('service_catalog'),['name'=>$name,'credit_cost'=>$cost,'unit_key'=>sanitize_key($_POST['unit_key']??''),'is_active'=>!empty($_POST['is_active'])?1:0,'updated_at'=>Biawin_CRM_DB::now()],['id'=>$id]);
        if($ok===false) wp_die('ذخیره تغییرات خدمت انجام نشد: '.esc_html($wpdb->last_error ?: 'خطای دیتابیس'));
        Biawin_CRM_DB::log('service',$id,'service_updated',null,['name'=>$name,'credit_cost'=>$cost,'is_active'=>!empty($_POST['is_active'])?1:0]);
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-services')); exit;
    }

    public static function handle_assign_service_case(): void
    {
        if(!(current_user_can('bcrm_manage_cases')||current_user_can('bcrm_services'))||!check_admin_referer('bcrm_assign_service_case'))wp_die('دسترسی غیرمجاز است.');
        $result=Biawin_CRM_Workflow_Service::assign_service_case(absint($_POST['case_id']??0),absint($_POST['agent_user_id']??0));
        if(empty($result['ok'])) wp_die(esc_html($result['message']??'تخصیص انجام نشد.'));
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-service-cases'));exit;
    }

    public static function handle_financial_case(): void
    {
        if (!current_user_can('bcrm_financial_verify') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb; $id=absint($_POST['case_id']??0); check_admin_referer('bcrm_financial_case_'.$id);
        $case=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('financial_cases').' WHERE id=%d',$id)); if(!$case) wp_die('پرونده پیدا نشد.');
        $roles=(array)wp_get_current_user()->roles;
        if(!current_user_can('bcrm_manage_cases') && !in_array('bcrm_financial_supervisor',$roles,true) && $case->assigned_user_id !== null && (int)$case->assigned_user_id!==get_current_user_id()) wp_die('این پرونده مالی به شما تخصیص داده نشده است.');
        $status=sanitize_key($_POST['status']??'in_progress'); $allowed=['in_progress','need_evidence','resolved','cancelled']; if(!in_array($status,$allowed,true)) wp_die('وضعیت نامعتبر است.');
        Biawin_CRM_Customer_Service::recalculate_customer((int)$case->customer_id);
        $profile=$wpdb->get_row($wpdb->prepare('SELECT profile_completeness FROM '.Biawin_CRM_DB::table('customers').' WHERE id=%d',(int)$case->customer_id));
        if($status==='resolved' && (!$profile || $profile->profile_completeness!=='complete')) $status='need_evidence';
        $data=['status'=>$status,'note'=>sanitize_textarea_field(wp_unslash($_POST['note']??'')),'assigned_user_id'=>get_current_user_id(),'updated_at'=>Biawin_CRM_DB::now()];
        if($status==='resolved'){ $data['resolved_by']=get_current_user_id(); $data['resolved_at']=Biawin_CRM_DB::now(); }
        $ok=$wpdb->update(Biawin_CRM_DB::table('financial_cases'),$data,['id'=>$id]);
        if($ok===false) wp_die('ذخیره وضعیت مالی انجام نشد: '.esc_html($wpdb->last_error ?: 'خطای دیتابیس'));
        Biawin_CRM_Workflow_Service::activity((int)$case->customer_id,'financial_case_status','وضعیت پرونده مالی تغییر کرد.','financial_case',$id,['status'=>$status,'note'=>$data['note']]);
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-financial')); exit;
    }

    public static function handle_refund_case(): void
    {
        if (!current_user_can('bcrm_refunds') && !current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb; $id=absint($_POST['refund_id']??0); check_admin_referer('bcrm_refund_case_'.$id);
        $refund=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('refunds').' WHERE id=%d',$id)); if(!$refund) wp_die('پرونده عودت پیدا نشد.');
        $roles=(array)wp_get_current_user()->roles;
        if(!current_user_can('bcrm_manage_cases') && !in_array('bcrm_refund_supervisor',$roles,true) && $refund->assigned_user_id !== null && (int)$refund->assigned_user_id!==get_current_user_id()) wp_die('این پرونده عودت به شما تخصیص داده نشده است.');
        $status=sanitize_key($_POST['status']??'review'); $allowed=['review','approved','ready_payment','paid','rejected','cancelled','closed']; if(!in_array($status,$allowed,true)) wp_die('وضعیت نامعتبر است.');
        $approved_raw=trim((string)($_POST['approved_amount']??'')); $approved=$approved_raw===''?null:Biawin_CRM_Normalizer::money($approved_raw);
        $ref=sanitize_text_field(wp_unslash($_POST['payment_reference']??''));
        if($status==='paid' && ($approved===null || $approved<=0 || $ref==='')) wp_die('برای ثبت پرداخت‌شده، مبلغ تأییدشده و شناسه پرداخت الزامی است.');
        $data=['status'=>$status,'approved_amount'=>$approved,'payment_reference'=>$ref,'assigned_user_id'=>get_current_user_id(),'updated_at'=>Biawin_CRM_DB::now()];
        if($status==='paid') $data['paid_at']=Biawin_CRM_DB::now();
        if(in_array($status,['paid','rejected','cancelled','closed'],true)) $data['closed_at']=Biawin_CRM_DB::now();
        $ok=$wpdb->update(Biawin_CRM_DB::table('refunds'),$data,['id'=>$id]);
        if($ok===false) wp_die('ذخیره وضعیت عودت انجام نشد: '.esc_html($wpdb->last_error ?: 'خطای دیتابیس'));
        Biawin_CRM_Workflow_Service::activity((int)$refund->customer_id,'refund_status','وضعیت پرونده عودت تغییر کرد.','refund',$id,['status'=>$status,'approved_amount'=>$approved,'payment_reference'=>$ref]);
        wp_safe_redirect(admin_url('admin.php?page=biawin-crm-refunds')); exit;
    }

    private static function portal_customer_url(int $customer_id): string
    {
        $page_id = (int) get_option('biawin_crm_portal_page_id', 0);
        $base = $page_id ? get_permalink($page_id) : home_url('/biawin-crm-portal/');
        return add_query_arg('bcrm_customer', $customer_id, $base);
    }

    /** v0.8.63.0 — unit types of the (existing) service_units architecture. */
    public static function unit_type_labels(): array
    {
        return [
            'service' => 'واحد خدمت (کارتابل خدمات)',
            'service_group' => 'گروه خدمات',
            'after_sales' => 'خدمات پس از فروش',
            'dissatisfaction' => 'نارضایتی‌ها',
            'finance' => 'تأیید مالی',
            'loan' => 'وام',
            'treasury' => 'خزانه',
            'other' => 'سایر',
        ];
    }

    /** Roles whose users can be members of a unit (CRM portal roles only — never all site users). */
    private static function unit_member_roles(): array
    {
        return array_keys(Biawin_CRM_Auth::role_labels());
    }

    public static function service_units_page(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $units_table = Biawin_CRM_DB::table('service_units');
        $members_table = Biawin_CRM_DB::table('service_unit_members');
        $units = $wpdb->get_results("SELECT * FROM $units_table ORDER BY display_order ASC, unit_name ASC, id ASC");
        $by_id = [];
        foreach ($units as $u) $by_id[(int)$u->id] = $u;
        // One query each for members and open-case counts (was N+1 per unit/member).
        $member_rows = $wpdb->get_results("SELECT m.unit_id, m.user_id, m.member_role, u.display_name FROM $members_table m LEFT JOIN {$wpdb->users} u ON u.ID=m.user_id ORDER BY u.display_name");
        $members = [];
        foreach ($member_rows as $m) $members[(int)$m->unit_id][(string)$m->member_role][(int)$m->user_id] = (string)($m->display_name ?: ('#' . (int)$m->user_id));
        $open_counts = [];
        foreach ($wpdb->get_results("SELECT unit_key, COUNT(*) total FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE status NOT IN ('completed','rejected','cancelled') GROUP BY unit_key") as $r) $open_counts[(string)$r->unit_key] = (int)$r->total;
        $people = get_users(['role__in' => self::unit_member_roles(), 'orderby' => 'display_name', 'fields' => ['ID', 'display_name', 'user_login']]);
        $types = self::unit_type_labels();

        $edit_key = sanitize_key(wp_unslash($_GET['edit_unit'] ?? ''));
        $edit = null;
        foreach ($units as $u) if ((string)$u->unit_key === $edit_key) $edit = $u;
        $edit_members = $edit ? ($members[(int)$edit->id] ?? []) : [];

        echo '<div class="wrap bcrm" dir="rtl"><h1>واحدها و اعضا</h1><p>ساختار واحدها (از جمله خدمات پس از فروش، نارضایتی‌ها، تأیید مالی، وام و خزانه) و سرپرست/کارشناسان هر واحد از اینجا مدیریت می‌شود. هر کاربر می‌تواند عضو چند واحد باشد. کلید واحدهای خدمت باید با «واحد مقصد» خدمت در کاتالوگ یکی باشد.</p>';
        if (!empty($_GET['updated'])) echo '<div class="notice notice-success inline"><p>واحد ذخیره شد.</p></div>';
        echo '<div class="bcrm-card" style="max-width:1100px"><h2>' . ($edit ? 'ویرایش واحد «' . esc_html($edit->unit_name) . '»' : 'ساخت واحد جدید') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_save_service_unit');
        echo '<input type="hidden" name="action" value="bcrm_save_service_unit"><input type="hidden" name="unit_form_v2" value="1"><table class="form-table">';
        echo '<tr><th>نام واحد</th><td><input name="unit_name" required class="regular-text" value="' . esc_attr($edit->unit_name ?? '') . '" placeholder="مثلاً واحد مبل"></td></tr>';
        echo '<tr><th>کلید واحد</th><td><input name="unit_key" required class="regular-text" value="' . esc_attr($edit->unit_key ?? '') . '"' . ($edit ? ' readonly' : '') . ' placeholder="furniture"><p class="description">شناسه ثابت ماشینی؛ پس از ساخت تغییر نمی‌کند.</p></td></tr>';
        echo '<tr><th>نوع واحد</th><td><select name="unit_type">';
        foreach ($types as $k => $label) echo '<option value="' . esc_attr($k) . '"' . selected((string)($edit->unit_type ?? 'service'), $k, false) . '>' . esc_html($label) . '</option>';
        echo '</select></td></tr><tr><th>واحد والد</th><td><select name="parent_unit_id"><option value="0">— بدون والد (سطح اول) —</option>';
        foreach ($units as $u) {
            if ($edit && (int)$u->id === (int)$edit->id) continue;
            echo '<option value="' . (int)$u->id . '"' . selected((int)($edit->parent_unit_id ?? 0), (int)$u->id, false) . '>' . esc_html($u->unit_name . ' (' . $u->unit_key . ')') . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>تنظیمات</th><td style="display:grid;gap:6px">'
            . '<label><input type="checkbox" name="is_active" value="1"' . checked(!$edit || (int)$edit->is_active === 1, true, false) . '> فعال</label>'
            . '<label><input type="checkbox" name="can_receive_inquiry" value="1"' . checked((int)($edit->can_receive_inquiry ?? 0), 1, false) . '> قابل استعلام از نارضایتی‌ها</label>'
            . '<label><input type="checkbox" name="requires_portal_response" value="1"' . checked((int)($edit->requires_portal_response ?? 0), 1, false) . '> پاسخ استعلام در پرتال لازم است</label>'
            . '<label>ترتیب نمایش <input type="number" name="display_order" value="' . esc_attr((string)(int)($edit->display_order ?? 0)) . '" style="width:90px"></label></td></tr>';
        $current_sup = $edit ? (int)($edit->supervisor_user_id ?? 0) : 0;
        echo '<tr><th>سرپرست</th><td><select name="supervisor_user_id"><option value="0">— انتخاب کنید —</option>';
        foreach ($people as $u) echo '<option value="' . (int)$u->ID . '"' . selected($current_sup, (int)$u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
        echo '</select></td></tr><tr><th>کارشناسان عضو</th><td style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px">';
        foreach ($people as $u) echo '<label><input type="checkbox" name="agent_user_ids[]" value="' . (int)$u->ID . '"' . checked(isset($edit_members['agent'][(int)$u->ID]), true, false) . '> ' . esc_html($u->display_name) . '</label>';
        echo '</td></tr></table><button class="button button-primary">ذخیره واحد</button>' . ($edit ? ' <a class="button" href="' . esc_url(add_query_arg('page', 'biawin-crm-service-units', admin_url('admin.php'))) . '">انصراف</a>' : '') . '</form></div>';

        echo '<h2>ساختار واحدها</h2><table class="widefat striped"><thead><tr><th>واحد</th><th>کلید</th><th>نوع</th><th>سرپرست</th><th>کارشناسان</th><th>استعلام</th><th>وضعیت</th><th>پرونده‌های خدمت باز</th><th></th></tr></thead><tbody>';
        if (!$units) echo '<tr><td colspan="9">هنوز واحدی ساخته نشده است.</td></tr>';
        // Tree order: roots first, then children depth-first (no recursion into cycles).
        $children = [];
        foreach ($units as $u) $children[(int)($u->parent_unit_id ?? 0)][] = $u;
        $ordered = [];
        $walk = static function (int $parent, int $depth) use (&$walk, &$ordered, $children) {
            foreach ($children[$parent] ?? [] as $u) {
                if (isset($ordered[(int)$u->id]) || $depth > 8) continue;
                $ordered[(int)$u->id] = [$u, $depth];
                $walk((int)$u->id, $depth + 1);
            }
        };
        $walk(0, 0);
        foreach ($units as $u) if (!isset($ordered[(int)$u->id])) $ordered[(int)$u->id] = [$u, 0]; // orphans (parent deleted)
        foreach ($ordered as [$u, $depth]) {
            $m = $members[(int)$u->id] ?? [];
            $sup_names = $m['supervisor'] ?? [];
            if (!$sup_names && $u->supervisor_user_id) { $su = get_user_by('id', (int)$u->supervisor_user_id); if ($su) $sup_names = [(int)$su->ID => $su->display_name]; }
            $inquiry = (int)($u->can_receive_inquiry ?? 0) ? ('بله' . ((int)($u->requires_portal_response ?? 0) ? ' · پاسخ پرتالی' : '')) : '—';
            echo '<tr><td>' . str_repeat('— ', $depth) . '<strong>' . esc_html($u->unit_name) . '</strong></td><td><code>' . esc_html($u->unit_key) . '</code></td><td>' . esc_html($types[(string)($u->unit_type ?? 'service')] ?? (string)$u->unit_type) . '</td><td>' . esc_html($sup_names ? implode('، ', $sup_names) : 'تعیین نشده') . '</td><td>' . esc_html(!empty($m['agent']) ? implode('، ', $m['agent']) : '—') . '</td><td>' . esc_html($inquiry) . '</td><td>' . ((int)$u->is_active ? 'فعال' : '<span style="color:#b91c1c">غیرفعال</span>') . '</td><td>' . number_format_i18n($open_counts[(string)$u->unit_key] ?? 0) . '</td><td><a class="button button-small" href="' . esc_url(add_query_arg(['page' => 'biawin-crm-service-units', 'edit_unit' => $u->unit_key], admin_url('admin.php'))) . '">ویرایش</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function handle_save_service_unit(): void
    {
        if(!current_user_can('bcrm_manage_cases')||!check_admin_referer('bcrm_save_service_unit')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb;
        $key=sanitize_key($_POST['unit_key']??''); $name=sanitize_text_field(wp_unslash($_POST['unit_name']??'')); $supervisor=absint($_POST['supervisor_user_id']??0);
        $agents=array_values(array_unique(array_filter(array_map('absint',(array)($_POST['agent_user_ids']??[])))));
        if($key===''||$name==='') wp_die('نام و کلید واحد الزامی است.');
        $table=Biawin_CRM_DB::table('service_units'); $now=Biawin_CRM_DB::now();
        // v0.8.63.0 — hierarchy/inquiry fields. Legacy posts (no unit_form_v2) keep the old behaviour.
        $v2 = !empty($_POST['unit_form_v2']);
        $extra = [];
        if ($v2) {
            $type = sanitize_key(wp_unslash($_POST['unit_type'] ?? 'service'));
            if (!isset(self::unit_type_labels()[$type])) $type = 'other';
            $parent = absint($_POST['parent_unit_id'] ?? 0);
            $extra = [
                'unit_type' => $type,
                'can_receive_inquiry' => !empty($_POST['can_receive_inquiry']) ? 1 : 0,
                'requires_portal_response' => !empty($_POST['requires_portal_response']) ? 1 : 0,
                'display_order' => (int)($_POST['display_order'] ?? 0),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                'parent_unit_id' => $parent ?: null,
            ];
        }
        // Only CRM portal users may become members/supervisors.
        $allowed_roles = self::unit_member_roles();
        foreach (array_merge($supervisor ? [$supervisor] : [], $agents) as $uid) {
            $u = get_user_by('id', $uid);
            if (!$u || !array_intersect((array)$u->roles, $allowed_roles)) wp_die('کاربر انتخاب‌شده کاربر پرتال CRM نیست.');
        }
        $wpdb->query('START TRANSACTION');
        try {
            $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE unit_key=%s",$key));
            if ($v2 && !empty($extra['parent_unit_id'])) {
                // Parent must exist and must not be the unit itself or one of its descendants (no cycles).
                $cursor = (int)$extra['parent_unit_id']; $hops = 0;
                while ($cursor && $hops++ < 20) {
                    if ($existing && $cursor === $existing) throw new RuntimeException('cycle');
                    $cursor = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(parent_unit_id,0) FROM $table WHERE id=%d", $cursor));
                }
                if (!(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id=%d", (int)$extra['parent_unit_id']))) throw new RuntimeException('parent');
            }
            if($existing){
                $saved=$wpdb->update($table,array_merge(['unit_name'=>$name,'supervisor_user_id'=>$supervisor?:null,'is_active'=>1,'updated_at'=>$now],$extra),['id'=>$existing]);
                if($saved===false) throw new RuntimeException('unit update failed');
                $unit_id=$existing;
            } else {
                $saved=$wpdb->insert($table,array_merge(['unit_key'=>$key,'unit_name'=>$name,'supervisor_user_id'=>$supervisor?:null,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now],$extra));
                $unit_id=(int)$wpdb->insert_id;
                if($saved===false||!$unit_id) throw new RuntimeException('unit insert failed');
            }
            $members=Biawin_CRM_DB::table('service_unit_members');
            if($wpdb->delete($members,['unit_id'=>$unit_id])===false) throw new RuntimeException('member clear failed');
            if($supervisor && $wpdb->insert($members,['unit_id'=>$unit_id,'user_id'=>$supervisor,'member_role'=>'supervisor','created_at'=>$now])===false) throw new RuntimeException('supervisor insert failed');
            foreach($agents as $uid){
                if($uid===$supervisor) continue;
                if($wpdb->insert($members,['unit_id'=>$unit_id,'user_id'=>$uid,'member_role'=>'agent','created_at'=>$now])===false) throw new RuntimeException('agent insert failed');
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            $msg = $e->getMessage() === 'cycle' ? 'واحد والد نمی‌تواند خود واحد یا یکی از زیرمجموعه‌هایش باشد.' : ($e->getMessage() === 'parent' ? 'واحد والد پیدا نشد.' : ('ذخیره واحد انجام نشد: ' . ($wpdb->last_error ?: 'خطای دیتابیس')));
            wp_die(esc_html($msg));
        }
        Biawin_CRM_DB::log('service_unit', $unit_id, 'service_unit_saved', null, ['unit_key'=>$key,'supervisor_user_id'=>$supervisor,'agent_user_ids'=>$agents] + $extra);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-service-units','updated'=>'1'],admin_url('admin.php'))); exit;
    }

    /** v0.8.62.6: per-user capability grants, manageable from «کاربران پرتال». */
    public static function grantable_caps(): array
    {
        return [
            'bcrm_view_refunded' => 'مشاهده تب «عودتی‌ها»',
            'bcrm_refund_register' => 'ثبت عودت (دسترسی صفحه ثبت عودت‌ها)',
            'bcrm_refunds' => 'عودت وجه',
            'bcrm_reports' => 'گزارش‌ها',
            'bcrm_import' => 'ورود اطلاعات (ایمپورت)',
            'bcrm_manage_cases' => 'مدیریت پرونده‌ها',
        ] + Biawin_CRM_Activator::after_sales_capabilities(); // v0.8.63.0 — after-sales foundation caps
    }

    public static function users_page(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $roles = Biawin_CRM_Auth::role_labels();
        $users = get_users(['role__in' => array_keys($roles), 'orderby' => 'ID', 'order' => 'DESC']);
        $portal = Biawin_CRM_Auth::portal_url();
        echo '<div class="wrap bcrm" dir="rtl"><h1>کاربران پرتال</h1><p>برای کارشناسان نام کاربری و رمز عبور مستقل تعیین کنید. ورود آن‌ها از <a target="_blank" href="'.esc_url($portal).'">صفحه پرتال CRM</a> انجام می‌شود و نیازی به ورود به wp-admin ندارند.</p>';
        if (!empty($_GET['bcrm_user_message'])) echo '<div class="notice notice-success inline"><p>'.esc_html(sanitize_text_field(wp_unslash($_GET['bcrm_user_message']))).'</p></div>';
        echo '<div class="bcrm-card" style="max-width:1000px"><h2>ساخت کاربر جدید</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('bcrm_save_portal_user');
        echo '<input type="hidden" name="action" value="bcrm_save_portal_user"><table class="form-table"><tr><th>نام و نام خانوادگی</th><td><input class="regular-text" name="display_name" required></td></tr><tr><th>نام کاربری</th><td><input class="regular-text" name="username" autocomplete="off" required><p class="description">نام کاربری پس از ساخت قابل تغییر نیست.</p></td></tr><tr><th>رمز عبور</th><td><input class="regular-text" type="password" name="password" autocomplete="new-password" minlength="8" required></td></tr><tr><th>ایمیل (اختیاری)</th><td><input class="regular-text" type="email" name="email"></td></tr><tr><th>نقش</th><td><select name="role" required>';
        foreach ($roles as $key=>$label) echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';
        echo '</select></td></tr></table><button class="button button-primary">ساخت کاربر و فعال‌سازی ورود</button></form></div>';
        echo '<h2>کاربران موجود</h2><table class="widefat striped"><thead><tr><th>نام</th><th>نام کاربری</th><th>نقش</th><th>دسترسی‌های اضافی</th><th>وضعیت</th><th>آخرین ورود</th><th>عملیات</th></tr></thead><tbody>';
        if (!$users) echo '<tr><td colspan="7">هنوز کاربری ساخته نشده است.</td></tr>';
        $grantable = self::grantable_caps();
        foreach ($users as $u) {
            $role = $u->roles[0] ?? '';
            $disabled = (bool)get_user_meta($u->ID,'bcrm_disabled',true);
            $last = get_user_meta($u->ID,'bcrm_last_portal_login',true);
            echo '<tr><td>'.esc_html($u->display_name).'</td><td><code>'.esc_html($u->user_login).'</code></td><td>'.esc_html($roles[$role] ?? $role).'</td><td>';
            $extra = [];
            foreach (array_keys($grantable) as $gc) if ($u->has_cap($gc)) $extra[] = $grantable[$gc];
            echo $extra ? esc_html(implode('، ', $extra)) : '<span style="color:#637083">—</span>';
            echo '</td><td>'.($disabled?'<span style="color:#b91c1c">غیرفعال</span>':'<span style="color:#15803d">فعال</span>').'</td><td>'.esc_html($last ?: '-').'</td><td style="display:flex;gap:6px;flex-wrap:wrap">';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_toggle_portal_user_'.$u->ID); echo '<input type="hidden" name="action" value="bcrm_toggle_portal_user"><input type="hidden" name="user_id" value="'.(int)$u->ID.'"><button class="button">'.($disabled?'فعال‌سازی':'غیرفعال‌سازی').'</button></form>';
            echo '<button type="button" class="button" onclick="var f=document.getElementById(\'bcrm-caps-'.(int)$u->ID.'\'); f.style.display = f.style.display===\'none\' ? \'block\' : \'none\';">دسترسی‌ها</button>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:4px">'; wp_nonce_field('bcrm_reset_portal_password_'.$u->ID); echo '<input type="hidden" name="action" value="bcrm_reset_portal_password"><input type="hidden" name="user_id" value="'.(int)$u->ID.'"><input type="password" name="new_password" minlength="8" placeholder="رمز جدید" required><button class="button">تغییر رمز</button></form>';
            if ((int)$u->ID !== get_current_user_id()) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_delete_portal_user_'.$u->ID); echo '<input type="hidden" name="action" value="bcrm_delete_portal_user"><input type="hidden" name="user_id" value="'.(int)$u->ID.'"><button class="button" style="border-color:#fecaca;color:#b91c1c" onclick="return confirm(\'این کاربر پرتال حذف شود؟ این عملیات قابل برگشت نیست.\')">حذف</button></form>';
            }
            echo '</td></tr>';
            // v0.8.62.6: per-user capability panel (collapsible row under each user).
            echo '<tr id="bcrm-caps-'.(int)$u->ID.'" style="display:none"><td colspan="7" style="background:#f8fafc"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('bcrm_save_user_caps_'.$u->ID);
            echo '<input type="hidden" name="action" value="bcrm_save_user_caps"><input type="hidden" name="user_id" value="'.(int)$u->ID.'"><strong style="display:block;margin-bottom:6px">دسترسی‌های اضافی '.esc_html($u->display_name).'</strong><div style="display:flex;gap:16px;flex-wrap:wrap">';
            foreach ($grantable as $cap => $cap_label) {
                $checked = $u->has_cap($cap) ? ' checked' : '';
                echo '<label style="display:inline-flex;align-items:center;gap:5px;font-size:12px"><input type="checkbox" name="bcrm_caps[]" value="'.esc_attr($cap).'"'.$checked.'> '.esc_html($cap_label).' <code style="font-size:10px">'.esc_html($cap).'</code></label>';
            }
            echo '</div><p style="margin:8px 0 0"><button class="button button-primary">ذخیره دسترسی‌ها</button> <span class="description">دسترسی‌های نقش اصلی دست‌نخورده می‌مانند؛ این‌ها فقط اضافه/حذف می‌شوند.</span></p></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function handle_save_user_caps(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $id = absint($_POST['user_id'] ?? 0);
        check_admin_referer('bcrm_save_user_caps_' . $id);
        $target = $id ? get_user_by('id', $id) : false;
        if (!$target || !$target->has_cap('bcrm_work_cases') || $target->has_cap('manage_options')) wp_die('دسترسی‌ها فقط برای کاربران پرتال قابل تغییر است.');
        $grantable = self::grantable_caps();
        $requested = (array)($_POST['bcrm_caps'] ?? []);
        $applied = [];
        foreach ($grantable as $cap => $label) {
            if (in_array($cap, $requested, true)) { $target->add_cap($cap); $applied[] = $cap; }
            else { $target->remove_cap($cap); } // only grantable caps are ever removed — role caps untouched
        }
        Biawin_CRM_DB::log('user', $id, 'portal_user_caps_updated', ['caps' => $applied, 'username' => $target->user_login], null);
        wp_safe_redirect(add_query_arg(['page' => 'biawin-crm-users', 'bcrm_user_message' => 'دسترسی‌های کاربر ذخیره شد.'], admin_url('admin.php')));
        exit;
    }

    public static function handle_save_portal_user(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_save_portal_user')) wp_die('دسترسی غیرمجاز است.');
        Biawin_CRM_Activator::ensure_roles_and_caps();
        $roles = Biawin_CRM_Auth::role_labels();
        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''), true);
        $password = (string)wp_unslash($_POST['password'] ?? '');
        $display = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $role = sanitize_key($_POST['role'] ?? 'bcrm_agent');
        if ($username === '' || strlen($password) < 8 || $display === '' || !isset($roles[$role])) wp_die('اطلاعات کاربر ناقص یا نامعتبر است.');
        if (username_exists($username)) wp_die('این نام کاربری قبلاً وجود دارد.');
        if ($email && email_exists($email)) wp_die('این ایمیل قبلاً استفاده شده است.');
        $user_id = wp_insert_user(['user_login'=>$username,'user_pass'=>$password,'user_email'=>$email,'display_name'=>$display,'role'=>$role]);
        if (is_wp_error($user_id)) wp_die(esc_html($user_id->get_error_message()));
        $user = get_user_by('id', (int)$user_id);
        if ($user) $user->set_role($role);
        update_user_meta($user_id,'bcrm_disabled',0);
        Biawin_CRM_DB::log('user',(int)$user_id,'portal_user_created',null,['role'=>$role,'username'=>$username]);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-users','bcrm_user_message'=>'کاربر با موفقیت ساخته شد.'],admin_url('admin.php'))); exit;
    }

    public static function handle_toggle_portal_user(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $id=absint($_POST['user_id']??0); check_admin_referer('bcrm_toggle_portal_user_'.$id);
        $target=$id?get_user_by('id',$id):false;
        if (!$target || !$target->has_cap('bcrm_work_cases') || $id===get_current_user_id()) wp_die('این کاربر قابل غیرفعال‌سازی نیست.');
        $disabled=(bool)get_user_meta($id,'bcrm_disabled',true); update_user_meta($id,'bcrm_disabled',$disabled?0:1);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-users','bcrm_user_message'=>$disabled?'کاربر فعال شد.':'کاربر غیرفعال شد.'],admin_url('admin.php'))); exit;
    }

    public static function handle_delete_portal_user(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $id = absint($_POST['user_id'] ?? 0);
        check_admin_referer('bcrm_delete_portal_user_' . $id);
        if (!$id || $id === get_current_user_id()) wp_die('این کاربر قابل حذف نیست.');
        $target = get_user_by('id', $id);
        if (!$target || !$target->has_cap('bcrm_work_cases') || $target->has_cap('manage_options')) wp_die('فقط کاربران پرتال قابل حذف هستند.');
        $username = $target->user_login;
        $roles = (array)$target->roles;
        require_once ABSPATH . 'wp-admin/includes/user.php';
        Biawin_CRM_DB::log('user', $id, 'portal_user_deleted', ['roles'=>$roles,'username'=>$username], null);
        if (!wp_delete_user($id)) wp_die('حذف کاربر انجام نشد.');
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-users','bcrm_user_message'=>'کاربر پرتال حذف شد.'], admin_url('admin.php')));
        exit;
    }

    public static function handle_reset_portal_password(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $id=absint($_POST['user_id']??0); check_admin_referer('bcrm_reset_portal_password_'.$id);
        $target=$id?get_user_by('id',$id):false;
        if(!$target || !$target->has_cap('bcrm_work_cases')) wp_die('کاربر پرتال پیدا نشد.');
        $password=(string)wp_unslash($_POST['new_password']??''); if(strlen($password)<8) wp_die('رمز عبور باید حداقل ۸ کاراکتر باشد.');
        wp_set_password($password,$id); Biawin_CRM_DB::log('user',$id,'portal_password_reset',null,['by'=>get_current_user_id()]);
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-users','bcrm_user_message'=>'رمز عبور تغییر کرد.'],admin_url('admin.php'))); exit;
    }

    public static function audit_page(): void
    {
        if(!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        global $wpdb; $rows=$wpdb->get_results("SELECT a.*,u.display_name FROM ".Biawin_CRM_DB::table('audit_log')." a LEFT JOIN {$wpdb->users} u ON u.ID=a.actor_user_id ORDER BY a.id DESC LIMIT 500");
        echo '<div class="wrap bcrm" dir="rtl"><h1>لاگ ممیزی</h1><p>۵۰۰ رویداد اخیر عملیات حساس سیستم.</p><table class="widefat striped"><thead><tr><th>زمان</th><th>کاربر</th><th>موجودیت</th><th>شناسه</th><th>عملیات</th><th>بعد از تغییر</th></tr></thead><tbody>';
        foreach($rows as $r) echo '<tr><td>'.esc_html($r->created_at).'</td><td>'.esc_html($r->display_name?:'سیستم').'</td><td>'.esc_html($r->object_type).'</td><td>'.esc_html((string)$r->object_id).'</td><td>'.esc_html($r->action).'</td><td><code style="white-space:pre-wrap">'.esc_html(mb_strimwidth((string)$r->after_json,0,300,'…')).'</code></td></tr>';
        echo '</tbody></table></div>';
    }

    public static function handle_repair_database(): void
    {
        if(!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_repair_database')) wp_die('دسترسی غیرمجاز است.');
        Biawin_CRM_Activator::activate();
        wp_safe_redirect(add_query_arg(['page'=>'biawin-crm-settings','bcrm_repaired'=>'1'],admin_url('admin.php')));
        exit;
    }

    public static function handle_export_customers_csv(): void
    {
        if(!current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_export_customers_csv');
        Biawin_CRM_File_Safety::quiet_output(); // v0.8.63.2: no PHP notices inside the download
        $out=@fopen('php://output','w');
        if(!$out) wp_die('ساخت خروجی گزارش مشتریان ممکن نشد.', 'خروجی گزارش', ['response'=>500]);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="biawin-customers-'.gmdate('Ymd-His').'.csv"');
        fwrite($out,"\xEF\xBB\xBF");
        $headers=self::customer_report_headers(); fputcsv($out,$headers);
        self::stream_customer_report_rows(function(array $row) use($out){ fputcsv($out,self::customer_report_export_values($row)); });
        fclose($out); exit;
    }

    public static function handle_export_customers_excel(): void
    {
        if(!current_user_can('bcrm_reports')) wp_die('دسترسی غیرمجاز است.');
        check_admin_referer('bcrm_export_customers_excel');
        /*
         * v0.8.63.2 — production hardening (supersedes 0.8.63.1's preflight, which called
         * "@disk_free_space()": on hosts that list it in disable_functions the function is
         * UNDEFINED in PHP 8 and "@" does not prevent the fatal).
         * - disk space is probed only through Biawin_CRM_File_Safety (availability-checked);
         *   "unknown" never blocks the export;
         * - the XLSX is built in a private temp dir (WordPress temp dir, never uploads) with
         *   every write checked; any failure (ENOSPC, unwritable temp, ZIP error) removes the
         *   partial files and falls back to the streamed CSV (no temp file at all);
         * - temp files are removed on success, on failure and on shutdown; stale leftovers swept.
         * Export data (headers, rows, values) is unchanged.
         */
        Biawin_CRM_File_Safety::quiet_output();
        Biawin_CRM_File_Safety::sweep_stale();
        @set_time_limit(0);
        if (!class_exists('ZipArchive')) { self::stream_customer_report_csv('zip_unavailable'); exit; }
        global $wpdb;
        $customers = (int)$wpdb->get_var('SELECT COUNT(*) FROM '.Biawin_CRM_DB::table('customers'));
        // sheet XML + ZIP copy ≈ 2 × (≈4 KB/customer), never less than 64 MB of headroom.
        $needed = max(64 * 1048576, $customers * 4096 * 2);
        $free = Biawin_CRM_File_Safety::disk_free_bytes(Biawin_CRM_File_Safety::temp_base());
        if ($free !== null && $free < $needed) { self::stream_customer_report_csv('low_disk', ['free'=>$free,'needed'=>$needed]); exit; }

        $tmp_dir = Biawin_CRM_File_Safety::make_temp_dir('report');
        if ($tmp_dir === '') { self::stream_customer_report_csv('temp_unavailable'); exit; }
        $sheet = $tmp_dir.'/sheet1.xml';
        $fh = @fopen($sheet,'wb');
        $ok = (bool)$fh && Biawin_CRM_File_Safety::write($fh,'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
        $row_no = 1;
        if ($ok) $ok = self::xlsx_write_row($fh,$row_no++,self::customer_report_headers());
        if ($ok) {
            self::stream_customer_report_rows(function(array $row) use($fh,&$row_no,&$ok){
                if (!$ok) return; // stop writing after the first failure (no notice flood)
                $ok = self::xlsx_write_row($fh,$row_no++,self::customer_report_export_values($row));
            });
        }
        if ($ok) $ok = Biawin_CRM_File_Safety::write($fh,'</sheetData></worksheet>');
        if ($fh) $ok = @fclose($fh) && $ok;
        $xlsx = $ok ? Biawin_CRM_File_Safety::build_zip($tmp_dir, 'biawin-customers.xlsx', [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="مشتریان" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
        ], ['xl/worksheets/sheet1.xml' => $sheet]) : '';
        if ($xlsx === '') {
            Biawin_CRM_File_Safety::remove($tmp_dir);
            self::stream_customer_report_csv('xlsx_write_failed', ['rows_written' => max(0, $row_no - 2)]);
            exit;
        }
        Biawin_CRM_File_Safety::send_file($xlsx, 'biawin-customers-'.gmdate('Ymd-His').'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        Biawin_CRM_File_Safety::remove($tmp_dir);
        exit;
    }

    /**
     * v0.8.63.2 — Excel-compatible UTF-8 (BOM) CSV streamed straight to the browser: no temp
     * file, so it works even on a full disk. Same headers/values as the XLSX.
     */
    private static function stream_customer_report_csv(string $reason, array $context = []): void
    {
        Biawin_CRM_DB::log('export', null, 'customer_export_csv_fallback', null, ['reason' => $reason] + $context);
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $out = @fopen('php://output', 'wb');
        if (!$out) wp_die('ساخت خروجی گزارش مشتریان ممکن نشد؛ لطفاً بعداً دوباره تلاش کنید.', 'خروجی گزارش', ['response' => 500]);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="biawin-customers-'.gmdate('Ymd-His').'.csv"');
        @fwrite($out,"\xEF\xBB\xBF");
        @fputcsv($out,self::customer_report_headers());
        self::stream_customer_report_rows(function(array $row) use($out){ @fputcsv($out,self::customer_report_export_values($row)); });
        @fclose($out);
    }

    private static function customer_report_headers(): array
    {
        return ['شناسه داخلی','CustomerID','کد مشتری','نام مشتری','مدیر فروش','شماره اصلی','همه شماره‌ها','وضعیت عملیاتی','وضعیت پروفایل','مسیر مشتری','وضعیت رضایت','گرید','گروه تخصیص','تعداد فاکتور','مجموع واریزی','کد فاکتور','عنوان فاکتور','مبلغ فاکتور','فروشنده فاکتور','کال‌سنتر فاکتور','مدیر فروش فاکتور','وضعیت فاکتور','تاریخ فاکتور','وضعیت تایید پیش‌واریزی','فاکتور خارج از مجموع','مبلغ اعلامی','مغایرت','رضایت میانگین','تعداد تماس/اقدام','آخرین نتیجه تماس','آخرین شماره تماس','آخرین مبلغ اعلامی','آخرین رضایت کلی','رضایت فروش','رضایت پیگیری','رضایت کیفیت خدمات','رضایت زمان خدمات','دریافت همه خدمات وعده‌داده‌شده','تمایل خرید مجدد','تمایل معرفی','پیگیری بعدی','یادداشت آخرین تماس','اولین تاریخ تخصیص','اولین کارشناس','کارشناس فعلی','آخرین اقدام','انجام‌دهنده آخرین اقدام','تاریخ آخرین اقدام','خلاصه فاکتورها','محصولات فاکتورها','خدمات واردشده از اکسل','پرونده‌های خدمات/پروموشن','پرونده‌های مالی','نارضایتی‌ها','عودت وجه‌ها','تعداد فایل پیوست','تاریخ ایجاد','آخرین بروزرسانی'];
    }

    /** v0.8.63.2: returns false on a failed/partial write (e.g. errno=28) instead of emitting notices. */
    private static function xlsx_write_row($fh,int $row_no,array $values): bool
    {
        $xml='<row r="'.$row_no.'">';
        foreach(array_values($values) as $idx=>$value){
            $col=''; $n=$idx+1; while($n>0){$n--; $col=chr(65+($n%26)).$col; $n=intdiv($n,26);}
            $safe=htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
            $xml.='<c r="'.$col.$row_no.'" t="inlineStr"><is><t xml:space="preserve">'.$safe.'</t></is></c>';
        }
        return Biawin_CRM_File_Safety::write($fh,$xml.'</row>');
    }

    private static function customer_report_export_values(array $r): array
    {
        return [
            $r['id'],$r['source_customer_id'],$r['customer_code'],$r['display_name'],$r['sales_manager'],$r['phone'],$r['phones_summary'],self::status_label($r['status']),self::report_profile_label($r['profile_completeness']),self::report_journey_label($r['journey_state']),$r['satisfaction_state'],$r['grade'],$r['assignment_cohort'],$r['invoice_count'],$r['total_paid'],$r['export_invoice_code'],$r['export_invoice_name'],$r['export_invoice_amount'],$r['export_invoice_seller'],$r['export_invoice_callcenter'],$r['export_invoice_sales_manager'],$r['export_invoice_status'],self::report_datetime($r['export_invoice_date'] ?? ''),$r['export_invoice_prepayment_status'],self::bool_label($r['export_invoice_exclude_from_total'] ?? null),$r['declared_paid'],$r['discrepancy_amount'],$r['satisfaction_avg'],
            $r['interaction_count'],$r['latest_outcome_label'],$r['latest_contact_phone'],$r['latest_declared_paid'],$r['latest_satisfaction_score'],$r['sales_satisfaction'],$r['followup_satisfaction'],$r['service_quality_satisfaction'],$r['service_timing_satisfaction'],self::bool_label($r['promised_services_received']),self::bool_label($r['repurchase_intent']),self::bool_label($r['referral_intent']),self::report_datetime($r['next_followup_at']),$r['latest_notes'],
            self::report_datetime($r['first_assigned_at']),$r['first_agent_name'],$r['current_agent_name'],$r['last_action_title'],$r['last_actor_name'],self::report_datetime($r['last_action_at']),$r['invoices_summary'],$r['invoice_items_summary'],$r['source_services_summary'],$r['service_cases_summary'],$r['financial_cases_summary'],$r['complaints_summary'],$r['refunds_summary'],$r['attachments_count'],self::report_datetime($r['created_at']),self::report_datetime($r['updated_at'])
        ];
    }

    private static function report_group_rows(array $rows, string $key, callable $formatter): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $id = (int)($row[$key] ?? 0);
            if (!$id) continue;
            $text = trim((string)$formatter($row));
            if ($text === '') continue;
            $grouped[$id][] = $text;
        }
        foreach ($grouped as $id => $items) $grouped[$id] = implode(' ; ', array_values(array_unique($items)));
        return $grouped;
    }

    private static function bool_label($value): string
    {
        if ($value === null || $value === '') return '-';
        return (int)$value ? 'بله' : 'خیر';
    }

    private static function stream_customer_report_rows(callable $consumer): void
    {
        global $wpdb;
        $c=Biawin_CRM_DB::table('customers'); $p=Biawin_CRM_DB::table('phones'); $i=Biawin_CRM_DB::table('invoices');
        $a=Biawin_CRM_DB::table('assignments'); $act=Biawin_CRM_DB::table('activities'); $inter=Biawin_CRM_DB::table('interactions');
        $items=Biawin_CRM_DB::table('invoice_items'); $source_services=Biawin_CRM_DB::table('services'); $service_cases=Biawin_CRM_DB::table('service_cases'); $catalog=Biawin_CRM_DB::table('service_catalog');
        $financial=Biawin_CRM_DB::table('financial_cases'); $complaints=Biawin_CRM_DB::table('complaints'); $refunds=Biawin_CRM_DB::table('refunds'); $attachments=Biawin_CRM_DB::table('attachments');
        $last_id=0; $limit=500;
        do{
            $base=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.source_customer_id,c.customer_code,c.display_name,c.sales_manager,c.status,c.profile_completeness,c.journey_state,c.satisfaction_state,c.grade,c.assignment_cohort,c.total_paid,c.declared_paid,c.discrepancy_amount,c.satisfaction_avg,c.created_at,c.updated_at,(SELECT normalized_phone FROM $p pp WHERE pp.customer_id=c.id ORDER BY pp.is_primary DESC,pp.id LIMIT 1) phone,(SELECT COUNT(*) FROM $i ii WHERE ii.customer_id=c.id) invoice_count FROM $c c WHERE c.id>%d ORDER BY c.id ASC LIMIT %d",$last_id,$limit),ARRAY_A);
            if(!$base) break;
            $ids=array_map('intval',array_column($base,'id')); $id_sql=implode(',',array_fill(0,count($ids),'%d'));
            $assign_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,agent_user_id,status,assigned_at,id FROM $a WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id ASC",...$ids),ARRAY_A);
            $activity_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,actor_user_id,title,created_at,id FROM $act WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id DESC",...$ids),ARRAY_A);
            $interaction_rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $inter WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id DESC",...$ids),ARRAY_A);
            $phone_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,normalized_phone,phone_status,relation_type,source,is_primary,created_at,verified_at FROM $p WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,is_primary DESC,id ASC",...$ids),ARRAY_A);
            $invoice_rows=$wpdb->get_results($wpdb->prepare("SELECT id,customer_id,invoice_code,invoice_name,amount_paid,seller,callcenter,sales_manager,invoice_status,seller_action_date,prepayment_approval_status,prepayment_unapproved,exclude_from_total,created_at FROM $i WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id ASC",...$ids),ARRAY_A);
            $item_rows=$wpdb->get_results($wpdb->prepare("SELECT inv.customer_id,it.product_name,it.quantity FROM $items it INNER JOIN $i inv ON inv.id=it.invoice_id WHERE inv.customer_id IN ($id_sql) ORDER BY inv.customer_id ASC,it.product_name ASC",...$ids),ARRAY_A);
            $src_service_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,service_name,service_status,created_at FROM $source_services WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id ASC",...$ids),ARRAY_A);
            $case_rows=$wpdb->get_results($wpdb->prepare("SELECT sc.customer_id,sc.case_code,cat.name service_name,sc.status,sc.credit_amount,sc.unit_key,sc.created_at,sc.closed_at FROM $service_cases sc LEFT JOIN $catalog cat ON cat.id=sc.service_id WHERE sc.customer_id IN ($id_sql) ORDER BY sc.customer_id ASC,sc.id ASC",...$ids),ARRAY_A);
            $financial_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,case_code,declared_amount,system_amount,difference_amount,reason,status,created_at,resolved_at FROM $financial WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id ASC",...$ids),ARRAY_A);
            $complaint_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,subject,priority,status,created_at,closed_at FROM $complaints WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id ASC",...$ids),ARRAY_A);
            $refund_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,refund_code,requested_amount,approved_amount,status,paid_at,created_at FROM $refunds WHERE customer_id IN ($id_sql) ORDER BY customer_id ASC,id ASC",...$ids),ARRAY_A);
            $attachment_rows=$wpdb->get_results($wpdb->prepare("SELECT customer_id,COUNT(*) attachment_count FROM $attachments WHERE customer_id IN ($id_sql) GROUP BY customer_id",...$ids),ARRAY_A);
            $first=[];$current=[];$last=[];$latest_interaction=[];$interaction_count=[];$user_ids=[];
            foreach($assign_rows as $ar){$cid=(int)$ar['customer_id']; if(!isset($first[$cid]))$first[$cid]=$ar; if($ar['status']==='assigned')$current[$cid]=$ar; $user_ids[]=(int)$ar['agent_user_id'];}
            foreach($activity_rows as $ac){$cid=(int)$ac['customer_id']; if(!isset($last[$cid])){$last[$cid]=$ac;$user_ids[]=(int)$ac['actor_user_id'];}}
            foreach($interaction_rows as $ir){$cid=(int)$ir['customer_id']; if(!isset($latest_interaction[$cid])){$latest_interaction[$cid]=$ir;$user_ids[]=(int)$ir['agent_user_id'];} $interaction_count[$cid]=($interaction_count[$cid]??0)+1;}
            $phones=self::report_group_rows($phone_rows,'customer_id',static fn($x)=>$x['normalized_phone'].' ['.$x['phone_status'].($x['is_primary']?'، اصلی':'').']');
            $invoices=self::report_group_rows($invoice_rows,'customer_id',static fn($x)=>$x['invoice_code'].' | '.$x['invoice_name'].' | مبلغ '.$x['amount_paid'].' | فروشنده '.$x['seller'].' | کال‌سنتر '.$x['callcenter'].' | مدیر فروش '.$x['sales_manager'].' | وضعیت '.$x['invoice_status'].(!empty($x['prepayment_unapproved'])?' | پیش‌واریزی تایید نشده':'').(!empty($x['exclude_from_total'])?' | خارج از مجموع':''));
            $invoice_items=self::report_group_rows($item_rows,'customer_id',static fn($x)=>$x['product_name'].' x '.$x['quantity']);
            $src_services=self::report_group_rows($src_service_rows,'customer_id',static fn($x)=>$x['service_name'].' ['.($x['service_status']?:'بدون وضعیت').']');
            $cases=self::report_group_rows($case_rows,'customer_id',static fn($x)=>$x['case_code'].' | '.$x['service_name'].' | '.$x['status'].' | اعتبار '.$x['credit_amount'].' | ایجاد '.self::report_datetime($x['created_at']).($x['closed_at']?' | پایان '.self::report_datetime($x['closed_at']):''));
            $financial_cases=self::report_group_rows($financial_rows,'customer_id',static fn($x)=>$x['case_code'].' | '.$x['status'].' | اعلامی '.$x['declared_amount'].' | سیستم '.$x['system_amount'].' | اختلاف '.$x['difference_amount'].' | '.($x['reason']?:'بدون دلیل').' | '.self::report_datetime($x['created_at']));
            $complaints_map=self::report_group_rows($complaint_rows,'customer_id',static fn($x)=>($x['subject']?:'نارضایتی').' | '.$x['priority'].' | '.$x['status'].' | '.self::report_datetime($x['created_at']));
            $refunds_map=self::report_group_rows($refund_rows,'customer_id',static fn($x)=>$x['refund_code'].' | '.$x['status'].' | درخواست '.$x['requested_amount'].' | تایید '.$x['approved_amount'].' | '.self::report_datetime($x['created_at']));
            $invoice_by_customer=[];
            foreach($invoice_rows as $inv_row)$invoice_by_customer[(int)$inv_row['customer_id']][]=$inv_row;
            $attachment_counts=[]; foreach($attachment_rows as $ar)$attachment_counts[(int)$ar['customer_id']]=(int)$ar['attachment_count'];
            $user_ids=array_values(array_unique(array_filter($user_ids))); $users=[];
            if($user_ids){foreach(get_users(['include'=>$user_ids]) as $u)$users[(int)$u->ID]=$u->display_name;}
            foreach($base as $r){$cid=(int)$r['id'];$fa=$first[$cid]??[];$ca=$current[$cid]??[];$la=$last[$cid]??[];
                $li=$latest_interaction[$cid]??[];
                $outcome_key=sanitize_key((string)($li['outcome_key']??'')); $outcome_label=$outcome_key?Biawin_CRM_Workflow_Service::contact_outcome_label($outcome_key,(string)($li['outcome']??'')):((string)($li['outcome']??''));
                $r['first_assigned_at']=$fa['assigned_at']??'';$r['first_agent_name']=$users[(int)($fa['agent_user_id']??0)]??'';$r['current_agent_name']=$users[(int)($ca['agent_user_id']??0)]??'';$r['last_action_title']=$la['title']??'';$r['last_action_at']=$la['created_at']??'';$r['last_actor_name']=$users[(int)($la['actor_user_id']??0)]??'';
                $r['phones_summary']=$phones[$cid]??''; $r['invoices_summary']=$invoices[$cid]??''; $r['invoice_items_summary']=$invoice_items[$cid]??''; $r['source_services_summary']=$src_services[$cid]??''; $r['service_cases_summary']=$cases[$cid]??''; $r['financial_cases_summary']=$financial_cases[$cid]??''; $r['complaints_summary']=$complaints_map[$cid]??''; $r['refunds_summary']=$refunds_map[$cid]??''; $r['attachments_count']=$attachment_counts[$cid]??0;
                $r['interaction_count']=$interaction_count[$cid]??0; $r['latest_outcome_label']=$outcome_label; $r['latest_contact_phone']=$li['contact_phone']??''; $r['latest_declared_paid']=$li['declared_paid']??''; $r['latest_satisfaction_score']=$li['satisfaction_score']??''; $r['sales_satisfaction']=$li['sales_satisfaction']??''; $r['followup_satisfaction']=$li['followup_satisfaction']??''; $r['service_quality_satisfaction']=$li['service_quality_satisfaction']??''; $r['service_timing_satisfaction']=$li['service_timing_satisfaction']??''; $r['promised_services_received']=$li['promised_services_received']??null; $r['repurchase_intent']=$li['repurchase_intent']??null; $r['referral_intent']=$li['referral_intent']??null; $r['next_followup_at']=$li['next_followup_at']??''; $r['latest_notes']=$li['notes']??'';
                $customer_invoices=$invoice_by_customer[$cid]??[];
                if(!$customer_invoices)$customer_invoices=[[]];
                foreach($customer_invoices as $invoice_row){
                    $export_row=$r;
                    $export_row['export_invoice_code']=$invoice_row['invoice_code']??'';
                    $export_row['export_invoice_name']=$invoice_row['invoice_name']??'';
                    $export_row['export_invoice_amount']=$invoice_row['amount_paid']??'';
                    $export_row['export_invoice_seller']=$invoice_row['seller']??'';
                    $export_row['export_invoice_callcenter']=$invoice_row['callcenter']??'';
                    $export_row['export_invoice_sales_manager']=$invoice_row['sales_manager']??'';
                    $export_row['export_invoice_status']=$invoice_row['invoice_status']??'';
                    $export_row['export_invoice_date']=$invoice_row['seller_action_date']??($invoice_row['created_at']??'');
                    $export_row['export_invoice_prepayment_status']=!empty($invoice_row['prepayment_unapproved'])?'تایید نشده':($invoice_row['prepayment_approval_status']??'');
                    $export_row['export_invoice_exclude_from_total']=$invoice_row['exclude_from_total']??null;
                    $consumer($export_row);
                }
                $last_id=max($last_id,$cid);
            }
            if(function_exists('gc_collect_cycles')) gc_collect_cycles();
        }while(count($base)===$limit);
    }

    public static function other_services(): array
    {
        $services = get_option('biawin_crm_other_services');
        if (is_array($services) && $services) {
            return array_values(array_filter(array_map('sanitize_text_field', $services)));
        }
        return ['افتتاح حساب', 'اعتبارسنجی مرآت', 'تخصیص اعتبار', 'پیگیری خدمات', 'مشاوره فروش', 'سایر خدمات'];
    }

    public static function backups_page(): void
    {
        if (!current_user_can('bcrm_manage_cases')) wp_die('دسترسی غیرمجاز است.');
        $notice = get_transient('bcrm_backup_notice_' . get_current_user_id());
        if ($notice) delete_transient('bcrm_backup_notice_' . get_current_user_id());
        echo '<div class="wrap bcrm" dir="rtl"><h1>بک‌آپ و بازگردانی CRM</h1>';
        echo '<p class="description">قبل از ایمپورت اصلاحی یا پاکسازی فاکتورها، از کل جداول CRM بک‌آپ بگیرید. بک‌آپ‌ها داخل همین پرتال قابل بازگردانی هستند.</p>';
        // v0.8.63.2 — location + retention are visible so backups cannot silently fill the disk again.
        $bdir = self::backup_dir();
        $bfree = $bdir ? Biawin_CRM_File_Safety::disk_free_bytes($bdir) : null;
        echo '<div class="notice notice-info inline"><p><strong>محل ذخیره:</strong> <code>' . esc_html($bdir ?: '—') . '</code>' . (defined('BCRM_BACKUP_DIR') ? ' (دیسک اختصاصی — BCRM_BACKUP_DIR)' : ' (داخل uploads؛ برای انتقال به دیسک بک‌آپ، <code>BCRM_BACKUP_DIR</code> را در wp-config.php تعریف کنید)') . ' · <strong>نگهداری:</strong> حداکثر ' . number_format_i18n(self::backup_keep()) . ' بک‌آپ؛ قدیمی‌ترها قبل از ساخت بک‌آپ جدید حذف می‌شوند (<code>BCRM_BACKUP_KEEP</code>) · <strong>فضای خالی:</strong> ' . esc_html($bfree === null ? 'نامشخص (این هاست اندازه‌گیری را مجاز نمی‌کند)' : size_format($bfree)) . '</p></div>';
        if (is_array($notice)) echo '<div class="notice notice-' . esc_attr($notice['type'] ?? 'info') . ' inline"><p>' . esc_html($notice['message'] ?? '') . '</p></div>';
        echo '<div class="bcrm-card"><h2>ساخت بک‌آپ کامل</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_create_backup');
        echo '<input type="hidden" name="action" value="bcrm_create_backup"><p class="description">تمام جداول با پیشوند bcrm ذخیره می‌شوند؛ شامل مشتری‌ها، فاکتورها، شماره‌ها، تخصیص‌ها، تماس‌ها، کارتابل‌ها، لاگ‌ها و تنظیمات عملیاتی داخل دیتابیس افزونه.</p>';
        echo '<p><button class="button button-primary button-hero">ساخت بک‌آپ الان</button></p></form></div>';
        echo '<div class="bcrm-card"><h2>آپلود و بازگردانی بک‌آپ</h2><p class="description">برای تست روی لوکال، فایل NDJSON بک‌آپی که از سایت اصلی دانلود کرده‌اید را اینجا آپلود و بازگردانی کنید. دیتای فعلی CRM لوکال با این بک‌آپ جایگزین می‌شود.</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'بازگردانی فایل آپلودی، دیتای فعلی CRM را جایگزین می‌کند. ادامه می‌دهید؟\')">';
        wp_nonce_field('bcrm_upload_restore_backup');
        echo '<input type="hidden" name="action" value="bcrm_upload_restore_backup"><p><input type="file" name="backup_upload" accept=".ndjson,.json"></p>';
        echo '<p class="description">حداکثر حجم آپلود فعلی PHP: ' . esc_html(size_format(wp_max_upload_size())) . '. اگر فایل بزرگ‌تر است، در لوکال مسیر کامل فایل را در کادر زیر وارد کنید.</p>';
        echo '<p><label>مسیر فایل روی لوکال/سرور<br><input type="text" name="backup_local_path" style="width:100%;max-width:760px" placeholder="مثلاً C:\\Users\\Mehrdad\\Downloads\\bcrm-backup-....ndjson"></label></p>';
        echo '<p><label><input type="checkbox" name="pre_restore_backup" value="1" checked> قبل از ریستور، از دیتای فعلی لوکال بک‌آپ بگیر</label></p>';
        echo '<p><button class="button button-primary">آپلود و بازگردانی</button></p></form></div>';
        $files = self::backup_files();
        echo '<h2>بک‌آپ‌های موجود</h2><table class="widefat striped"><thead><tr><th>فایل</th><th>زمان</th><th>حجم</th><th>عملیات</th></tr></thead><tbody>';
        if (!$files) echo '<tr><td colspan="4">هنوز بک‌آپی ساخته نشده است.</td></tr>';
        foreach ($files as $file) {
            $base = basename($file);
            echo '<tr><td><code>' . esc_html($base) . '</code></td><td>' . esc_html(date_i18n('Y/m/d H:i', filemtime($file))) . '</td><td>' . esc_html(size_format(filesize($file))) . '</td><td style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('bcrm_download_backup'); echo '<input type="hidden" name="action" value="bcrm_download_backup"><input type="hidden" name="backup_file" value="' . esc_attr($base) . '"><button class="button">دانلود</button></form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'بازگردانی، دیتای فعلی CRM را با این بک‌آپ جایگزین می‌کند. ادامه می‌دهید؟\')">'; wp_nonce_field('bcrm_restore_backup'); echo '<input type="hidden" name="action" value="bcrm_restore_backup"><input type="hidden" name="backup_file" value="' . esc_attr($base) . '"><label><input type="checkbox" name="pre_restore_backup" value="1" checked> قبل از ریستور بک‌آپ جدید بگیر</label> <button class="button button-primary">بازگردانی</button></form>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function handle_create_backup(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_create_backup')) wp_die('دسترسی غیرمجاز است.');
        $result = self::create_crm_backup('manual');
        self::backup_notice(empty($result['ok']) ? 'error' : 'success', $result['message'] ?? 'عملیات انجام شد.');
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
        exit;
    }

    public static function handle_download_backup(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_download_backup')) wp_die('دسترسی غیرمجاز است.');
        $file = self::backup_path_from_request();
        if (!$file || !is_readable($file)) wp_die('فایل بک‌آپ پیدا نشد.');
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public static function handle_restore_backup(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_restore_backup')) wp_die('دسترسی غیرمجاز است.');
        $file = self::backup_path_from_request();
        if (!$file || !is_readable($file)) {
            self::backup_notice('error', 'فایل بک‌آپ پیدا نشد.');
            wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
            exit;
        }
        if (!empty($_POST['pre_restore_backup'])) self::create_crm_backup('before-restore');
        $result = self::restore_crm_backup($file);
        self::backup_notice(empty($result['ok']) ? 'error' : 'success', $result['message'] ?? 'بازگردانی انجام شد.');
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
        exit;
    }

    public static function handle_upload_restore_backup(): void
    {
        if (!current_user_can('bcrm_manage_cases') || !check_admin_referer('bcrm_upload_restore_backup')) wp_die('دسترسی غیرمجاز است.');
        $upload = $_FILES['backup_upload'] ?? null;
        $dir = self::backup_dir();
        if (!$dir) {
            self::backup_notice('error', 'پوشه بک‌آپ قابل ساخت نیست.');
            wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
            exit;
        }
        $dest = trailingslashit($dir) . 'bcrm-backup-' . gmdate('Ymd-His') . '-uploaded.ndjson';
        $local_path = trim((string)wp_unslash($_POST['backup_local_path'] ?? ''));
        $has_upload = is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($has_upload) {
            if (!empty($upload['error'])) {
                self::backup_notice('error', self::backup_upload_error_message((int)$upload['error']));
                wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
                exit;
            }
            if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
                self::backup_notice('error', 'فایل بک‌آپ معتبری دریافت نشد. اگر فایل بزرگ است، از کادر مسیر فایل لوکال استفاده کنید.');
                wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
                exit;
            }
            $original = sanitize_file_name((string)($upload['name'] ?? 'uploaded-backup.ndjson'));
            if (!self::is_supported_backup_file_name($original)) {
                self::backup_notice('error', 'فقط فایل بک‌آپ NDJSON قابل بازگردانی است.');
                wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
                exit;
            }
            if (!@move_uploaded_file((string)$upload['tmp_name'], $dest)) {
                self::backup_notice('error', 'ذخیره فایل بک‌آپ آپلودی ممکن نشد.');
                wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
                exit;
            }
        } elseif ($local_path !== '') {
            if (!self::is_supported_backup_file_name($local_path) || !is_file($local_path) || !is_readable($local_path)) {
                self::backup_notice('error', 'مسیر فایل بک‌آپ روی لوکال معتبر یا قابل خواندن نیست.');
                wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
                exit;
            }
            if (!@copy($local_path, $dest)) {
                self::backup_notice('error', 'کپی فایل بک‌آپ از مسیر لوکال ممکن نشد.');
                wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
                exit;
            }
        } else {
            self::backup_notice('error', 'فایل بک‌آپ انتخاب نشده است. فایل را آپلود کنید یا مسیر کامل فایل روی لوکال را وارد کنید.');
            wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
            exit;
        }
        if (!empty($_POST['pre_restore_backup'])) self::create_crm_backup('before-upload-restore');
        $result = self::restore_crm_backup($dest);
        self::backup_notice(empty($result['ok']) ? 'error' : 'success', $result['message'] ?? 'بازگردانی انجام شد.');
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-backups', admin_url('admin.php')));
        exit;
    }

    private static function is_supported_backup_file_name(string $name): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($ext, ['ndjson','json'], true);
    }

    private static function backup_upload_error_message(int $code): string
    {
        if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'حجم فایل بک‌آپ از سقف آپلود PHP بیشتر است. سقف فعلی: ' . size_format(wp_max_upload_size()) . '. در لوکال مسیر کامل فایل را در کادر «مسیر فایل روی لوکال/سرور» وارد کنید.';
        }
        $messages = [
            UPLOAD_ERR_PARTIAL => 'فایل بک‌آپ ناقص آپلود شد. دوباره تلاش کنید یا مسیر فایل لوکال را وارد کنید.',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت آپلود PHP در دسترس نیست.',
            UPLOAD_ERR_CANT_WRITE => 'نوشتن فایل آپلودی روی دیسک ممکن نشد.',
            UPLOAD_ERR_EXTENSION => 'یک افزونه PHP آپلود فایل را متوقف کرد.',
        ];
        return $messages[$code] ?? ('آپلود بک‌آپ با خطا مواجه شد. کد خطا: ' . $code);
    }

    /**
     * v0.8.63.2 — bounded, failure-safe full CRM backup (same NDJSON format; restore unchanged).
     * Production incident: ~15 GB of backups accumulated in uploads/biawin-crm-backups and filled
     * the root filesystem (fwrite errno=28). Now:
     *  - retention: at most backup_keep() files (default 3) — the oldest are pruned BEFORE a new
     *    backup is written, so peak disk use stays bounded;
     *  - free-space preflight when the host allows measuring it (estimated from table sizes);
     *  - every write is checked; a failed backup deletes its partial file (no orphans);
     *  - location can be moved to a dedicated disk with BCRM_BACKUP_DIR (wp-config.php);
     *  - the folder is web-denied and file names carry a random part (not guessable).
     */
    private static function create_crm_backup(string $reason = 'manual'): array
    {
        global $wpdb;
        @set_time_limit(0);
        $dir = self::backup_dir();
        if (!$dir) return ['ok'=>false,'message'=>'ساخت پوشه بک‌آپ ممکن نشد.'];
        $tables = self::crm_tables();
        $estimate = self::backup_size_estimate($tables);
        self::prune_backups(max(0, self::backup_keep() - 1)); // make room first: keep N-1 + the new one
        $free = Biawin_CRM_File_Safety::disk_free_bytes($dir);
        $reserve = 256 * 1048576;
        if ($free !== null && $free < $estimate + $reserve) {
            Biawin_CRM_DB::log('backup', null, 'crm_backup_skipped_low_disk', null, ['free'=>$free,'estimate'=>$estimate,'reason'=>$reason]);
            return ['ok'=>false,'message'=>'فضای خالی دیسک برای بک‌آپ کافی نیست (آزاد: ' . size_format($free) . '، لازم حدود ' . size_format($estimate + $reserve) . '). بک‌آپ ساخته نشد.'];
        }
        $file = trailingslashit($dir) . 'bcrm-backup-' . gmdate('Ymd-His') . '-' . sanitize_key($reason) . '-' . bin2hex(random_bytes(4)) . '.ndjson';
        $fh = @fopen($file, 'wb');
        if (!$fh) return ['ok'=>false,'message'=>'ساخت فایل بک‌آپ ممکن نشد.'];
        $fail = static function (string $why) use (&$fh, $file, $reason): array {
            if (is_resource($fh)) @fclose($fh);
            @unlink($file); // never leave a partial backup behind
            Biawin_CRM_DB::log('backup', null, 'crm_backup_failed', null, ['reason'=>$reason,'error'=>$why]);
            return ['ok'=>false,'message'=>'ساخت بک‌آپ ناموفق بود (' . $why . ')؛ فایل ناقص حذف شد.'];
        };
        if (!Biawin_CRM_File_Safety::write($fh, wp_json_encode(['type'=>'meta','version'=>BIAWIN_CRM_VERSION,'site'=>site_url(),'created_at'=>current_time('mysql'),'tables'=>$tables], JSON_UNESCAPED_UNICODE) . "\n")) return $fail('write failed / disk full');
        $total = 0;
        foreach ($tables as $table) {
            $last = 0;
            $has_id = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'id'));
            $columns = self::table_insertable_columns($table);
            if (!$columns) continue;
            $select = implode(',', array_map(static fn($c)=>'`' . str_replace('`', '``', $c) . '`', $columns));
            do {
                if ($has_id) {
                    $rows = $wpdb->get_results($wpdb->prepare("SELECT $select FROM `$table` WHERE id>%d ORDER BY id ASC LIMIT 1000", $last), ARRAY_A);
                } else {
                    $rows = $wpdb->get_results("SELECT $select FROM `$table` LIMIT 1000", ARRAY_A);
                }
                $buffer = '';
                foreach ($rows as $row) {
                    $line = wp_json_encode(['type'=>'row','table'=>$table,'row'=>$row], JSON_UNESCAPED_UNICODE);
                    if ($line === false) return $fail('json encode failed in ' . $table);
                    $buffer .= $line . "\n";
                    $total++;
                    if ($has_id && isset($row['id'])) $last = max($last, (int)$row['id']);
                }
                if ($buffer !== '' && !Biawin_CRM_File_Safety::write($fh, $buffer)) return $fail('write failed / disk full');
                if (!$has_id) break;
            } while (count($rows) === 1000);
        }
        if (!@fclose($fh)) { $fh = null; return $fail('close failed / disk full'); }
        Biawin_CRM_DB::log('backup', null, 'crm_backup_created', null, ['file'=>basename($file),'rows'=>$total,'reason'=>$reason,'bytes'=>(int)@filesize($file)]);
        return ['ok'=>true,'message'=>'بک‌آپ کامل ساخته شد: ' . basename($file) . ' (' . number_format_i18n($total) . ' ردیف)'];
    }

    /** v0.8.63.2 — how many backup files to keep (BCRM_BACKUP_KEEP in wp-config.php, 1…20; default 3). */
    private static function backup_keep(): int
    {
        $keep = defined('BCRM_BACKUP_KEEP') ? (int)BCRM_BACKUP_KEEP : 3;
        return max(1, min(20, $keep));
    }

    /** Delete the oldest plugin backups so that at most $keep remain (only bcrm-backup-*.ndjson in our dir). */
    private static function prune_backups(int $keep): int
    {
        $files = self::backup_files(); // newest first
        $removed = 0;
        foreach (array_slice($files, $keep) as $old) {
            if (preg_match('/^bcrm-backup-[A-Za-z0-9\-]+\.ndjson$/', basename($old)) && @unlink($old)) {
                $removed++;
                Biawin_CRM_DB::log('backup', null, 'crm_backup_pruned', null, ['file'=>basename($old),'keep'=>$keep]);
            }
        }
        return $removed;
    }

    /** Rough NDJSON size of the CRM tables (data_length × 1.3), used only for the free-space preflight. */
    private static function backup_size_estimate(array $tables): float
    {
        global $wpdb;
        if (!$tables) return 0.0;
        $ph = implode(',', array_fill(0, count($tables), '%s'));
        $bytes = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(DATA_LENGTH),0) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($ph)", $tables));
        return $bytes * 1.3;
    }

    private static function table_insertable_columns(string $table): array
    {
        global $wpdb;
        $cols = $wpdb->get_results("SHOW FULL COLUMNS FROM `$table`", ARRAY_A);
        $out = [];
        foreach ($cols as $col) {
            $extra = strtolower((string)($col['Extra'] ?? ''));
            if (strpos($extra, 'generated') !== false) continue;
            $field = (string)($col['Field'] ?? '');
            if ($field !== '') $out[] = $field;
        }
        return $out;
    }

    private static function restore_crm_backup(string $file): array
    {
        global $wpdb;
        @set_time_limit(0);
        $fh = @fopen($file, 'rb');
        if (!$fh) return ['ok'=>false,'message'=>'خواندن فایل بک‌آپ ممکن نشد.'];
        $meta_line = fgets($fh);
        $meta = json_decode((string)$meta_line, true);
        if (($meta['type'] ?? '') !== 'meta' || empty($meta['tables']) || !is_array($meta['tables'])) {
            fclose($fh);
            return ['ok'=>false,'message'=>'ساختار فایل بک‌آپ معتبر نیست.'];
        }
        $tables = array_values(array_filter(array_map('strval', $meta['tables']), static fn($t)=>strpos($t, $wpdb->prefix . 'bcrm_') === 0));
        $wpdb->query('START TRANSACTION');
        foreach (array_reverse($tables) as $table) $wpdb->query("DELETE FROM `$table`");
        $count = 0;
        while (($line = fgets($fh)) !== false) {
            $item = json_decode($line, true);
            if (($item['type'] ?? '') !== 'row') continue;
            $table = (string)($item['table'] ?? '');
            $row = is_array($item['row'] ?? null) ? $item['row'] : [];
            if (!in_array($table, $tables, true) || !$row) continue;
            $ok = $wpdb->insert($table, $row);
            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                fclose($fh);
                return ['ok'=>false,'message'=>'بازگردانی در جدول ' . $table . ' ناموفق بود.'];
            }
            $count++;
        }
        fclose($fh);
        $wpdb->query('COMMIT');
        Biawin_CRM_DB::log('backup', null, 'crm_backup_restored', null, ['file'=>basename($file),'rows'=>$count]);
        return ['ok'=>true,'message'=>'بک‌آپ با موفقیت بازگردانی شد: ' . basename($file) . ' (' . number_format_i18n($count) . ' ردیف)'];
    }

    private static function crm_tables(): array
    {
        global $wpdb;
        $like = $wpdb->esc_like($wpdb->prefix . 'bcrm_') . '%';
        return array_values(array_filter($wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like)), 'is_string'));
    }

    /**
     * v0.8.63.2 — backup location. Define BCRM_BACKUP_DIR (absolute path, e.g. the dedicated
     * backup disk) in wp-config.php to keep backups out of wp-content/uploads entirely; otherwise
     * the historical uploads/biawin-crm-backups is used, web-denied and bounded by retention.
     */
    private static function backup_dir(): string
    {
        if (defined('BCRM_BACKUP_DIR') && is_string(BCRM_BACKUP_DIR) && trim(BCRM_BACKUP_DIR) !== '') {
            $dir = untrailingslashit(wp_normalize_path(BCRM_BACKUP_DIR));
        } else {
            $upload = wp_upload_dir();
            if (!empty($upload['error'])) return '';
            $dir = trailingslashit($upload['basedir']) . 'biawin-crm-backups';
        }
        if (!wp_mkdir_p($dir) || !is_writable($dir)) return '';
        $guards = [
            'index.html' => '',
            'index.php' => "<?php // Silence is golden.\n",
            '.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
            'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>',
        ];
        foreach ($guards as $name => $content) {
            $path = trailingslashit($dir) . $name;
            if (!file_exists($path)) @file_put_contents($path, $content);
        }
        return $dir;
    }

    private static function backup_files(): array
    {
        $dir = self::backup_dir();
        if (!$dir) return [];
        $files = glob(trailingslashit($dir) . 'bcrm-backup-*.ndjson') ?: [];
        usort($files, static fn($a,$b)=>filemtime($b) <=> filemtime($a));
        return $files;
    }

    private static function backup_path_from_request(): string
    {
        $base = basename(sanitize_file_name((string)($_POST['backup_file'] ?? '')));
        if ($base === '' || !preg_match('/^bcrm-backup-[A-Za-z0-9\\-]+\\.ndjson$/', $base)) return '';
        $path = trailingslashit(self::backup_dir()) . $base;
        return is_file($path) ? $path : '';
    }

    private static function backup_notice(string $type, string $message): void
    {
        set_transient('bcrm_backup_notice_' . get_current_user_id(), ['type'=>$type,'message'=>$message], 10 * MINUTE_IN_SECONDS);
    }

    public static function invoice_ownership_page(): void
    {
        $last = get_transient('bcrm_invoice_ownership_' . get_current_user_id());
        if (is_array($last)) delete_transient('bcrm_invoice_ownership_' . get_current_user_id());
        echo '<div class="wrap bcrm" dir="rtl"><h1>اصلاح مالکیت فاکتورها</h1>';
        if (Biawin_CRM_Historical_Audit::maintenance_lock_enabled()) echo '<div class="notice notice-warning inline"><p><strong>قفل ایمنی v0.8.40 فعال است:</strong> پیش‌نمایش مجاز است ولی اجرای واقعی تا پایان ممیزی تاریخی مسدود شده است.</p></div>';
        echo '<p class="description">این ابزار برای تست و اجرای اصلاح فایل نهایی است. معیار فقط CustomerID و کد فاکتور است؛ اگر مقصد وجود داشته باشد فاکتور به همان پروفایل اضافه می‌شود و اگر وجود نداشته باشد پروفایل جدید ساخته می‌شود. شماره‌ها، تخصیص‌ها و تماس‌ها برای تصمیم مالکیت استفاده یا جابه‌جا نمی‌شوند و پروفایل‌های قبلی خودکار حذف نمی‌شوند.</p>';
        echo '<div class="bcrm-card"><h2>۱. خروجی کامل از سایت اصلی</h2><p class="description">قبل از هر اصلاح، یک بک‌آپ کامل قابل بازگردانی از همه جداول CRM بگیرید و همان را روی لوکال خام restore کنید.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('bcrm_create_backup');
        echo '<input type="hidden" name="action" value="bcrm_create_backup"><button class="button button-primary">ساخت خروجی/بک‌آپ کامل CRM</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=biawin-crm-backups')) . '">مشاهده و دانلود بک‌آپ‌ها</a></form></div>';

        echo '<div class="bcrm-card"><h2>۲. پیش‌نمایش فایل اصلاح مالکیت</h2>';
        echo '<p class="description">فایل XLSX/CSV باید ستون CustomerID و ستون کد فاکتور داشته باشد. هیچ تغییری تا زمان اجرای واقعی انجام نمی‌شود.</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_invoice_ownership_preview');
        echo '<input type="hidden" name="action" value="bcrm_invoice_ownership_preview">';
        echo '<p><label><strong>فایل اصلاحی</strong><br><input type="file" name="ownership_file" accept=".csv,.xlsx" required></label></p>';
        echo '<p><button class="button button-primary">پیش‌نمایش اصلاح مالکیت</button></p></form></div>';
        if ($last) self::render_invoice_ownership_result($last);
        echo '</div>';
    }

    public static function handle_invoice_ownership_preview(): void
    {
        if (!current_user_can('bcrm_import') || !check_admin_referer('bcrm_invoice_ownership_preview')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0);
        $result = self::empty_invoice_ownership_result(false);
        $result['token'] = wp_generate_password(20, false, false);
        $file = $_FILES['ownership_file'] ?? null;
        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['errors'][] = 'فایل معتبری دریافت نشد.';
            self::store_invoice_ownership_result($result);
        }
        if (!empty($file['error'])) {
            $result['errors'][] = 'آپلود فایل با خطا مواجه شد. کد خطا: ' . (int)$file['error'];
            self::store_invoice_ownership_result($result);
        }
        $name = sanitize_file_name((string)($file['name'] ?? 'ownership.xlsx'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xlsx'], true)) {
            $result['errors'][] = 'فقط فایل CSV یا XLSX قابل استفاده است.';
            self::store_invoice_ownership_result($result);
        }
        [$headers, $rows, $parse_errors] = Biawin_CRM_Import_Service::parse_file((string)$file['tmp_name'], $name);
        if ($parse_errors) {
            $result['errors'] = array_merge($result['errors'], $parse_errors);
            self::store_invoice_ownership_result($result);
        }
        $plans = self::invoice_ownership_plans_from_rows($headers, $rows, $result);
        if (!$plans) {
            $result['errors'][] = 'هیچ ردیف معتبر دارای CustomerID و کد فاکتور پیدا نشد.';
            self::store_invoice_ownership_result($result);
        }
        self::analyze_invoice_ownership_plans($plans, $result, false);
        set_transient('bcrm_invoice_ownership_job_' . get_current_user_id() . '_' . $result['token'], $plans, HOUR_IN_SECONDS);
        self::store_invoice_ownership_result($result);
    }

    public static function handle_invoice_ownership_execute(): void
    {
        if (!current_user_can('bcrm_import') || !check_admin_referer('bcrm_invoice_ownership_execute')) wp_die('دسترسی غیرمجاز است.');
        if (Biawin_CRM_Historical_Audit::maintenance_lock_enabled()) wp_die(esc_html(Biawin_CRM_Historical_Audit::maintenance_lock_message()));
        @set_time_limit(0);
        $token = sanitize_text_field((string)($_POST['ownership_token'] ?? ''));
        $plans = self::get_invoice_ownership_job($token);
        $result = self::empty_invoice_ownership_result(true);
        $result['token'] = $token;
        if (!$plans) {
            $result['errors'][] = 'پیش‌نمایش منقضی شده یا پیدا نشد. فایل اصلاحی را دوباره پیش‌نمایش بگیرید.';
            self::store_invoice_ownership_result($result);
        }
        if (empty($_POST['confirm_execute'])) {
            $result['errors'][] = 'برای اجرای واقعی باید تیک تایید اجرای اصلاح مالکیت را بزنید.';
            self::store_invoice_ownership_result($result);
        }
        if (!empty($_POST['create_backup'])) self::create_crm_backup('before-invoice-ownership');
        $batch_size = max(100, min(1000, absint($_POST['batch_size'] ?? 1000)));
        self::analyze_invoice_ownership_plans($plans, $result, true, $batch_size);
        foreach (($result['processed_invoice_codes'] ?? []) as $code) unset($plans[$code]);
        $result['remaining_count'] = count($plans);
        if ($plans) set_transient('bcrm_invoice_ownership_job_' . get_current_user_id() . '_' . $token, $plans, HOUR_IN_SECONDS);
        else delete_transient('bcrm_invoice_ownership_job_' . get_current_user_id() . '_' . $token);
        self::store_invoice_ownership_result($result);
    }

    private static function empty_invoice_ownership_result(bool $execute): array
    {
        return ['execute'=>$execute,'token'=>'','errors'=>[],'counts'=>[
            'input_rows'=>0,'valid_rows'=>0,'duplicate_invoice_rows'=>0,'invoice_not_found'=>0,'target_found'=>0,'target_missing'=>0,'target_will_create'=>0,'target_created'=>0,'already_correct'=>0,'will_move'=>0,'moved'=>0,'blocked'=>0,'failed'=>0,
        ],'samples'=>[],'processed_invoice_codes'=>[],'remaining_count'=>0];
    }

    private static function invoice_ownership_plans_from_rows(array $headers, array $rows, array &$result): array
    {
        $customer_key = self::find_invoice_ownership_header($headers, ['customerid','customer_id','شناسهcustomer','شناسهcrm','شناسه','کدمشتری']);
        $invoice_key = self::find_invoice_ownership_header($headers, ['کدفاکتور','شمارهفاکتور','فاکتور','invoicecode','invoice_code','invoiceid','invoice_id']);
        if ($customer_key === '' || $invoice_key === '') {
            $result['errors'][] = 'ستون CustomerID یا کد فاکتور در فایل پیدا نشد.';
            return [];
        }
        $plans = [];
        $result['counts']['input_rows'] = count($rows);
        foreach ($rows as $row) {
            $customer_id = self::normalize_source_customer_id((string)($row[$customer_key] ?? ''));
            $invoice_code = trim((string)($row[$invoice_key] ?? ''));
            if ($customer_id === '' || $invoice_code === '') continue;
            if (isset($plans[$invoice_code]) && $plans[$invoice_code] !== $customer_id) $result['counts']['duplicate_invoice_rows']++;
            $plans[$invoice_code] = $customer_id;
        }
        $result['counts']['valid_rows'] = count($plans);
        return $plans;
    }

    private static function find_invoice_ownership_header(array $headers, array $aliases): string
    {
        foreach ($headers as $header) {
            $normalized = preg_replace('/\s+/u', '', mb_strtolower((string)$header));
            $normalized = str_replace(['ي','ك'], ['ی','ک'], $normalized);
            if (in_array($normalized, $aliases, true)) return (string)$header;
        }
        return '';
    }

    private static function normalize_source_customer_id(string $raw): string
    {
        $raw = strtoupper(preg_replace('/\s+/u', '', trim($raw)));
        if ($raw === '') return '';
        if (preg_match('/^C?\d+$/', $raw)) {
            $num = (int)preg_replace('/\D+/', '', $raw);
            return 'C' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
        }
        return preg_match('/^C\d{1,12}$/', $raw) ? $raw : '';
    }

    private static function analyze_invoice_ownership_plans(array $plans, array &$result, bool $execute, int $limit = 0): void
    {
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        $services = Biawin_CRM_DB::table('services');
        $source_ids = [];
        $changed_customer_ids = [];
        $processed = 0;
        foreach ($plans as $invoice_code => $target_source_id) {
            if ($execute && $limit > 0 && $processed >= $limit) break;
            $processed++;
            if ($execute) $result['processed_invoice_codes'][] = (string)$invoice_code;
            $invoice = $wpdb->get_row($wpdb->prepare("SELECT i.id,i.customer_id,i.invoice_code,i.amount_paid,c.source_customer_id current_source_id,c.display_name current_name FROM $invoices i LEFT JOIN $customers c ON c.id=i.customer_id WHERE i.invoice_code=%s LIMIT 1", $invoice_code));
            if (!$invoice) {
                $result['counts']['invoice_not_found']++;
                self::invoice_ownership_sample($result, 'فاکتور پیدا نشد', $invoice_code, '-', $target_source_id, 'کد فاکتور در CRM وجود ندارد.');
                continue;
            }
            $target = $wpdb->get_row($wpdb->prepare("SELECT id,source_customer_id,display_name FROM $customers WHERE source_customer_id=%s LIMIT 1", $target_source_id));
            if (!$target) {
                $result['counts']['target_missing']++;
                if (!$execute) {
                    $result['counts']['target_will_create']++;
                    $result['counts']['will_move']++;
                    self::invoice_ownership_sample($result, 'آماده ساخت پروفایل و انتقال', $invoice_code, (string)($invoice->current_source_id ?: ('#'.$invoice->customer_id)), $target_source_id, 'CustomerID مقصد وجود ندارد؛ در اجرای واقعی پروفایل جدید ساخته می‌شود و فاکتور به آن منتقل می‌شود.');
                    continue;
                }
                $inserted = $wpdb->insert($customers, [
                    'internal_uuid'=>wp_generate_uuid4(),
                    'source_customer_id'=>$target_source_id,
                    'display_name'=>null,
                    'normalized_name'=>null,
                    'status'=>'auto_linked',
                    'total_paid'=>0,
                    'created_at'=>Biawin_CRM_DB::now(),
                    'updated_at'=>Biawin_CRM_DB::now(),
                ]);
                if ($inserted === false) {
                    $result['counts']['failed']++;
                    self::invoice_ownership_sample($result, 'ناموفق: ساخت مقصد', $invoice_code, (string)($invoice->current_source_id ?: ('#'.$invoice->customer_id)), $target_source_id, $wpdb->last_error ?: 'ساخت پروفایل مقصد ممکن نشد.');
                    continue;
                }
                $target = (object)['id'=>(int)$wpdb->insert_id,'source_customer_id'=>$target_source_id,'display_name'=>''];
                $result['counts']['target_created']++;
            }
            $result['counts']['target_found']++;
            if ((int)$invoice->customer_id === (int)$target->id) {
                $result['counts']['already_correct']++;
                if ($execute) $changed_customer_ids[(int)$target->id] = (int)$target->id;
                self::invoice_ownership_sample($result, 'درست است', $invoice_code, (string)($invoice->current_source_id ?: ('#'.$invoice->customer_id)), $target_source_id, 'مالک فعلی با فایل اصلاحی یکی است.');
                continue;
            }
            if (!$execute) {
                $result['counts']['will_move']++;
                self::invoice_ownership_sample($result, 'آماده انتقال', $invoice_code, (string)($invoice->current_source_id ?: ('#'.$invoice->customer_id)), $target_source_id, 'در اجرای واقعی فقط مالکیت فاکتور و خدمات همان فاکتور تغییر می‌کند؛ پروفایل مبدا حذف نمی‌شود.');
                continue;
            }
            $old_customer_id = (int)$invoice->customer_id;
            $updated = $wpdb->update($invoices, ['customer_id'=>(int)$target->id,'updated_at'=>Biawin_CRM_DB::now()], ['id'=>(int)$invoice->id]);
            $updated_services = $wpdb->update($services, ['customer_id'=>(int)$target->id], ['invoice_id'=>(int)$invoice->id]);
            if ($updated === false || $updated_services === false) {
                $result['counts']['failed']++;
                self::invoice_ownership_sample($result, 'ناموفق', $invoice_code, (string)($invoice->current_source_id ?: ('#'.$invoice->customer_id)), $target_source_id, $wpdb->last_error ?: 'خطای دیتابیس');
                continue;
            }
            $result['counts']['moved']++;
            $changed_customer_ids[$old_customer_id] = $old_customer_id;
            $changed_customer_ids[(int)$target->id] = (int)$target->id;
            Biawin_CRM_DB::log('invoice', (int)$invoice->id, 'invoice_ownership_corrected', ['customer_id'=>$old_customer_id], ['customer_id'=>(int)$target->id,'target_source_customer_id'=>$target_source_id,'invoice_code'=>$invoice_code]);
            self::invoice_ownership_sample($result, 'منتقل شد', $invoice_code, (string)($invoice->current_source_id ?: ('#'.$invoice->customer_id)), $target_source_id, 'مالکیت فاکتور اصلاح شد.');
        }
        if ($execute && $changed_customer_ids) {
            foreach ($changed_customer_ids as $customer_id) {
                self::repair_customer_identity_from_owned_invoices((int)$customer_id);
                Biawin_CRM_Customer_Service::recalculate_customer((int)$customer_id);
            }
        }
    }

    private static function repair_customer_identity_from_owned_invoices(int $customer_id): void
    {
        if ($customer_id <= 0) return;
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        $invoice_phones = Biawin_CRM_DB::table('invoice_phones');
        $phones = Biawin_CRM_DB::table('phones');
        $now = Biawin_CRM_DB::now();

        $invoice_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,invoice_name,sales_manager FROM $invoices WHERE customer_id=%d ORDER BY id ASC",
            $customer_id
        ), ARRAY_A);
        if (!$invoice_rows) return;

        $name_counts = [];
        $sales_manager_counts = [];
        $invoice_ids = [];
        foreach ($invoice_rows as $invoice) {
            $invoice_ids[] = (int)$invoice['id'];
            $name = trim((string)($invoice['invoice_name'] ?? ''));
            if ($name !== '' && $name !== '_' && $name !== '-') $name_counts[$name] = ($name_counts[$name] ?? 0) + 1;
            $sales_manager = trim((string)($invoice['sales_manager'] ?? ''));
            if ($sales_manager !== '' && $sales_manager !== '_' && $sales_manager !== '-') $sales_manager_counts[$sales_manager] = ($sales_manager_counts[$sales_manager] ?? 0) + 1;
        }
        arsort($name_counts);
        arsort($sales_manager_counts);
        $display_name = $name_counts ? (string)array_key_first($name_counts) : null;
        $sales_manager = $sales_manager_counts ? (string)array_key_first($sales_manager_counts) : null;

        $update = ['updated_at'=>$now];
        if ($display_name !== null) {
            $update['display_name'] = $display_name;
            $update['normalized_name'] = Biawin_CRM_Normalizer::name($display_name);
        }
        if ($sales_manager !== null) $update['sales_manager'] = $sales_manager;
        if (count($update) > 1) $wpdb->update($customers, $update, ['id'=>$customer_id]);

        if (!$invoice_ids) return;
        $placeholders = implode(',', array_fill(0, count($invoice_ids), '%d'));
        $desired_phones = $wpdb->get_col($wpdb->prepare(
            "SELECT normalized_phone FROM $invoice_phones WHERE invoice_id IN ($placeholders) AND normalized_phone<>'' GROUP BY normalized_phone ORDER BY MIN(phone_position),MIN(id)",
            ...$invoice_ids
        ));
        $desired_phones = array_values(array_filter(array_map([Biawin_CRM_Normalizer::class, 'phone'], $desired_phones)));
        $desired_phones = array_values(array_unique($desired_phones));

        // For profiles touched by ownership correction, invoice_phones is the authoritative
        // source for imported/repair-generated phone ownership. Preserve manual phones, but
        // detach stale imported phones so phone search cannot keep resolving to the old profile.
        if ($desired_phones) {
            $desired_placeholders = implode(',', array_fill(0, count($desired_phones), '%s'));
            $wpdb->query($wpdb->prepare(
                "UPDATE $phones SET customer_id=NULL,is_primary=0,auto_match_blocked=1 WHERE customer_id=%d AND source NOT IN ('manual','manual_invoice_bundle') AND normalized_phone NOT IN ($desired_placeholders)",
                $customer_id,
                ...$desired_phones
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $phones SET customer_id=NULL,is_primary=0,auto_match_blocked=1 WHERE customer_id=%d AND source NOT IN ('manual','manual_invoice_bundle')",
                $customer_id
            ));
        }

        foreach ($desired_phones as $index => $phone) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $phones WHERE normalized_phone=%s LIMIT 1", $phone));
            $is_primary = $index === 0 ? 1 : 0;
            if ($existing) {
                $wpdb->update($phones, [
                    'customer_id'=>$customer_id,
                    'raw_phone'=>$phone,
                    'phone_status'=>($existing->phone_status ?: 'unverified'),
                    'relation_type'=>($existing->relation_type ?: 'unknown'),
                    'source'=>in_array((string)$existing->source, ['manual','manual_invoice_bundle'], true) ? (string)$existing->source : 'ownership_repair',
                    'is_primary'=>$is_primary,
                    'auto_match_blocked'=>0,
                ], ['id'=>(int)$existing->id]);
            } else {
                $wpdb->insert($phones, [
                    'customer_id'=>$customer_id,
                    'raw_phone'=>$phone,
                    'normalized_phone'=>$phone,
                    'phone_status'=>'unverified',
                    'relation_type'=>'unknown',
                    'source'=>'ownership_repair',
                    'is_primary'=>$is_primary,
                    'auto_match_blocked'=>0,
                    'created_at'=>$now,
                ]);
            }
        }
        Biawin_CRM_Customer_Service::ensure_single_primary_phone($customer_id);
        Biawin_CRM_DB::log('customer', $customer_id, 'customer_identity_repaired_from_invoices', null, [
            'display_name'=>$display_name,
            'sales_manager'=>$sales_manager,
            'phones'=>$desired_phones,
        ]);
    }

    private static function invoice_ownership_sample(array &$result, string $action, string $invoice_code, string $current_customer_id, string $target_customer_id, string $note): void
    {
        if (count($result['samples']) >= 120) return;
        $result['samples'][] = ['action'=>$action,'invoice_code'=>$invoice_code,'current_customer_id'=>$current_customer_id,'target_customer_id'=>$target_customer_id,'note'=>$note];
    }

    private static function get_invoice_ownership_job(string $token): array
    {
        if ($token === '' || !preg_match('/^[A-Za-z0-9]+$/', $token)) return [];
        $plans = get_transient('bcrm_invoice_ownership_job_' . get_current_user_id() . '_' . $token);
        if (!is_array($plans)) return [];
        $out = [];
        foreach ($plans as $invoice_code => $target_customer_id) {
            $invoice_code = trim((string)$invoice_code);
            $target_customer_id = self::normalize_source_customer_id((string)$target_customer_id);
            if ($invoice_code !== '' && $target_customer_id !== '') $out[$invoice_code] = $target_customer_id;
        }
        return $out;
    }

    private static function store_invoice_ownership_result(array $result): void
    {
        set_transient('bcrm_invoice_ownership_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-invoice-ownership', admin_url('admin.php')));
        exit;
    }

    private static function render_invoice_ownership_result(array $result): void
    {
        if (!empty($result['errors'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html(implode(' ', $result['errors'])) . '</p></div>';
            return;
        }
        if (!empty($result['execute'])) {
            echo '<div class="notice notice-success inline"><p><strong>Batch اصلاح مالکیت انجام شد.</strong> ' . esc_html(number_format_i18n((int)($result['counts']['moved'] ?? 0))) . ' فاکتور در این batch منتقل شد.';
            if ((int)($result['remaining_count'] ?? 0) > 0) echo '<br>' . esc_html(number_format_i18n((int)$result['remaining_count'])) . ' مورد هنوز برای ادامه اجرا باقی مانده است.';
            else echo '<br>همه موارد این Job تمام شد.';
            echo '</p></div>';
        }
        echo '<h2>' . (!empty($result['execute']) ? 'گزارش اجرای واقعی' : 'گزارش پیش‌نمایش اصلاح مالکیت') . '</h2><div class="bcrm-grid">';
        $labels = ['input_rows'=>'ردیف فایل','valid_rows'=>'فاکتور یکتا در فایل','duplicate_invoice_rows'=>'فاکتور تکراری با CustomerID متفاوت','invoice_not_found'=>'فاکتور پیدا نشد','target_found'=>'مقصد موجود','target_missing'=>'مقصد ناموجود','target_will_create'=>'پروفایل جدید در اجرا','target_created'=>'پروفایل جدید ساخته‌شده','already_correct'=>'از قبل درست','will_move'=>'آماده انتقال/ساخت','moved'=>'منتقل‌شده','blocked'=>'مسدود','failed'=>'ناموفق'];
        foreach ($labels as $key=>$label) self::metric($label, (int)($result['counts'][$key] ?? 0));
        echo '</div>';
        $repairable_count = (int)($result['counts']['will_move'] ?? 0) + (int)($result['counts']['already_correct'] ?? 0);
        if (empty($result['execute']) && !empty($result['token']) && $repairable_count > 0) {
            echo '<div class="bcrm-card" style="margin-top:16px"><h2>اجرای واقعی انتقال + تعمیر هویت</h2><p class="description">این اجرا علاوه بر انتقال فاکتورهای نیازمند جابه‌جایی، پروفایل‌های «از قبل درست» را هم از روی فاکتورهای متعلق به خودشان تعمیر می‌کند؛ نام، مدیر فروش و شماره‌های import/ownership-repair با invoice_phones همگام می‌شوند. شماره‌های دستی حذف یا جابه‌جا نمی‌شوند.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('bcrm_invoice_ownership_execute');
            echo '<input type="hidden" name="action" value="bcrm_invoice_ownership_execute"><input type="hidden" name="ownership_token" value="' . esc_attr((string)$result['token']) . '">';
            echo '<p><label><input type="checkbox" name="create_backup" value="1" checked> قبل از اجرا بک‌آپ کامل قابل بازگردانی بساز</label></p>';
            echo '<p><label><input type="checkbox" name="confirm_execute" value="1" required> تایید می‌کنم انتقال فاکتورها و تعمیر هویت پروفایل‌های قابل تعمیر اجرا شود.</label></p>';
            echo '<p><input type="number" name="batch_size" value="1000" min="100" max="1000"> تعداد پردازش در هر batch</p>';
            echo '<p><button class="button button-primary button-hero" onclick="return confirm(\'انتقال/تعمیر مالکیت و هویت اجرا شود؟\')">اجرای اصلاح مالکیت</button></p></form></div>';
        } elseif (!empty($result['execute']) && !empty($result['token']) && (int)($result['remaining_count'] ?? 0) > 0) {
            echo '<div class="bcrm-card" style="margin-top:16px"><h2>ادامه اجرای اصلاح مالکیت</h2><p class="description">برای جلوگیری از خطای 504، اجرا در batchهای کوتاه انجام می‌شود. این دکمه batch بعدی را اجرا می‌کند.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('bcrm_invoice_ownership_execute');
            echo '<input type="hidden" name="action" value="bcrm_invoice_ownership_execute"><input type="hidden" name="ownership_token" value="' . esc_attr((string)$result['token']) . '"><input type="hidden" name="confirm_execute" value="1">';
            echo '<p><input type="number" name="batch_size" value="1000" min="100" max="1000"> تعداد پردازش در batch بعدی</p>';
            echo '<p><button class="button button-primary button-hero">ادامه اجرای batch بعدی</button></p></form></div>';
        }
        if (!empty($result['samples'])) {
            echo '<table class="widefat striped" style="margin-top:16px"><thead><tr><th>اقدام</th><th>کد فاکتور</th><th>مالک فعلی</th><th>CustomerID اصلاحی</th><th>توضیح</th></tr></thead><tbody>';
            foreach ($result['samples'] as $row) echo '<tr><td>'.esc_html($row['action']).'</td><td>'.esc_html($row['invoice_code']).'</td><td>'.esc_html($row['current_customer_id']).'</td><td>'.esc_html($row['target_customer_id']).'</td><td>'.esc_html($row['note']).'</td></tr>';
            echo '</tbody></table>';
        }
    }

    public static function unapproved_invoice_cleanup_page(): void
    {
        $last = get_transient('bcrm_unapproved_invoice_cleanup_' . get_current_user_id());
        if (is_array($last)) delete_transient('bcrm_unapproved_invoice_cleanup_' . get_current_user_id());
        echo '<div class="wrap bcrm" dir="rtl"><h1>پاکسازی فاکتورهای بدون تایید مالی</h1>';
        if (Biawin_CRM_Historical_Audit::maintenance_lock_enabled()) echo '<div class="notice notice-warning inline"><p><strong>قفل ایمنی v0.8.40 فعال است:</strong> پیش‌نمایش مجاز است ولی اجرای واقعی پاکسازی مسدود شده است.</p></div>';
        echo '<p class="description">فایل XLSX/CSV شامل ستون CustomerID را بارگذاری کنید. پیش‌فرض فقط پیش‌نمایش است؛ اجرای واقعی فقط با تیک تایید انجام می‌شود.</p>';
        echo '<div class="bcrm-card"><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_unapproved_invoice_cleanup');
        echo '<input type="hidden" name="action" value="bcrm_unapproved_invoice_cleanup"><input type="hidden" name="cleanup_mode" value="preview">';
        echo '<p><label><strong>فایل CustomerID ها</strong><br><input id="bcrm-cleanup-file" type="file" name="cleanup_file" accept=".csv,.xlsx" required></label></p>';
        echo '<p class="description">بعد از انتخاب فایل روی «بررسی فایل» بزنید. پیش‌نمایش و اجرای واقعی هر دو مرحله‌ای انجام می‌شوند و نیازی به آپلود دوباره نیست.</p>';
        echo '<p class="description">قانون اجرا: پروفایل تک‌فاکتوری بدون تایید مالی حذف می‌شود؛ فاکتور بدون تایید مالی در پروفایل چندفاکتوری حذف نمی‌شود و فقط از مجموع مبلغ فاکتورها خارج می‌شود.</p>';
        echo '<p><button class="button button-primary">بررسی فایل</button></p></form></div>';
        if ($last) self::render_unapproved_invoice_cleanup_result($last);
        echo '</div>';
    }

    public static function handle_unapproved_invoice_cleanup(): void
    {
        if (!current_user_can('bcrm_import') || !check_admin_referer('bcrm_unapproved_invoice_cleanup')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0);
        $mode = sanitize_key((string)($_POST['cleanup_mode'] ?? 'preview'));
        if ($mode === 'execute') {
            if (Biawin_CRM_Historical_Audit::maintenance_lock_enabled()) wp_die(esc_html(Biawin_CRM_Historical_Audit::maintenance_lock_message()));
            self::handle_unapproved_invoice_cleanup_execute();
            return;
        }
        if ($mode === 'preview_continue') {
            self::handle_unapproved_invoice_cleanup_preview_continue();
            return;
        }
        $result = self::empty_cleanup_result(false);
        $result['mode'] = 'preview';
        $result['execute'] = false;
        $result['token'] = wp_generate_password(20, false, false);
        $result['selected_actions'] = [];
        $result['errors'] = [];
        $result['counts'] = array_merge($result['counts'], [
            'input_rows'=>0,'unique_customer_ids'=>0,'matched_customers'=>0,'single_deleted'=>0,'multi_excluded'=>0,'skipped_not_found'=>0,'skipped_no_unapproved_invoice'=>0,'skipped_already_excluded'=>0,'skipped_multiple_unapproved'=>0,'skipped_customer_has_activity'=>0,'failed'=>0,
        ]);
        $file = $_FILES['cleanup_file'] ?? null;
        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['errors'][] = 'فایل معتبری دریافت نشد.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        if (!empty($file['error'])) {
            $result['errors'][] = 'آپلود فایل با خطا مواجه شد. کد خطا: ' . (int)$file['error'];
            self::store_unapproved_invoice_cleanup_result($result);
        }
        $name = sanitize_file_name((string)($file['name'] ?? 'cleanup.xlsx'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xlsx'], true)) {
            $result['errors'][] = 'فقط فایل CSV یا XLSX قابل استفاده است.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        [$headers, $rows, $parse_errors] = Biawin_CRM_Import_Service::parse_file((string)$file['tmp_name'], $name);
        if ($parse_errors) {
            $result['errors'] = array_merge($result['errors'], $parse_errors);
            self::store_unapproved_invoice_cleanup_result($result);
        }
        $customer_ids = self::cleanup_customer_ids_from_rows($headers, $rows);
        $result['counts']['input_rows'] = count($rows);
        $result['counts']['unique_customer_ids'] = count($customer_ids);
        if (!$customer_ids) {
            $result['errors'][] = 'هیچ CustomerID معتبری در فایل پیدا نشد.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        $batch_size = max(100, min(1000, absint($_POST['batch_size'] ?? 1000)));
        self::run_unapproved_invoice_cleanup($customer_ids, $result, false, [], $batch_size);
        $processed = array_flip(array_map('strval', $result['processed_customer_ids'] ?? []));
        $preview_ids = array_values(array_filter($customer_ids, static fn($id)=>!isset($processed[(string)$id])));
        $result['remaining_count'] = count($preview_ids);
        self::store_cleanup_job((string)$result['token'], $customer_ids, $result['counts'], $preview_ids);
        self::store_unapproved_invoice_cleanup_result($result);
    }

    private static function handle_unapproved_invoice_cleanup_preview_continue(): void
    {
        $token = sanitize_text_field((string)($_POST['cleanup_token'] ?? ''));
        $job = self::get_cleanup_job($token);
        $preview_ids = $job['preview_ids'];
        $result = self::empty_cleanup_result(false);
        $result['mode'] = 'preview';
        $result['token'] = $token;
        $result['counts'] = array_merge($result['counts'], (array)($job['counts'] ?? []));
        if (!$job['customer_ids']) {
            $result['errors'][] = 'پیش‌نمایش منقضی شده یا پیدا نشد. لطفاً فایل را دوباره بررسی کنید.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        if (!$preview_ids) {
            $result['remaining_count'] = 0;
            self::store_cleanup_job((string)$token, $job['customer_ids'], $result['counts'], []);
            self::store_unapproved_invoice_cleanup_result($result);
        }
        $batch_size = max(100, min(1000, absint($_POST['batch_size'] ?? 1000)));
        self::run_unapproved_invoice_cleanup($preview_ids, $result, false, [], $batch_size);
        $processed = array_flip(array_map('strval', $result['processed_customer_ids'] ?? []));
        $preview_ids = array_values(array_filter($preview_ids, static fn($id)=>!isset($processed[(string)$id])));
        $result['remaining_count'] = count($preview_ids);
        self::store_cleanup_job((string)$token, $job['customer_ids'], $result['counts'], $preview_ids);
        self::store_unapproved_invoice_cleanup_result($result);
    }

    private static function handle_unapproved_invoice_cleanup_execute(): void
    {
        $token = sanitize_text_field((string)($_POST['cleanup_token'] ?? ''));
        $job = self::get_cleanup_job($token);
        $customer_ids = $job['customer_ids'];
        $result = self::empty_cleanup_result(true);
        $result['mode'] = 'execute';
        $result['token'] = $token;
        $result['counts']['input_rows'] = (int)($job['counts']['input_rows'] ?? count($customer_ids));
        $result['counts']['unique_customer_ids'] = (int)($job['counts']['unique_customer_ids'] ?? count($customer_ids));
        if (!$customer_ids) {
            $result['errors'][] = 'پیش‌نمایش منقضی شده یا پیدا نشد. لطفاً فایل را دوباره بررسی کنید.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        $actions = array_values(array_intersect(['single_delete','multi_exclude','no_unapproved'], array_map('sanitize_key', (array)($_POST['cleanup_actions'] ?? []))));
        $result['selected_actions'] = $actions;
        if (!$actions) {
            $result['errors'][] = 'هیچ دسته‌ای برای اجرای واقعی انتخاب نشده است.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        if (!array_intersect($actions, ['single_delete','multi_exclude'])) {
            $result['errors'][] = 'دسته انتخاب‌شده اقدام دیتابیسی ندارد. برای اجرای واقعی باید «تک‌فاکتوری‌های آماده حذف» یا «چندفاکتوری‌های آماده خروج از مجموع» را انتخاب کنید.';
            self::store_unapproved_invoice_cleanup_result($result);
        }
        if (!empty($_POST['create_backup'])) self::create_crm_backup('before-cleanup');
        $batch_size = max(100, min(1000, absint($_POST['batch_size'] ?? 1000)));
        self::run_unapproved_invoice_cleanup($customer_ids, $result, true, $actions, $batch_size);
        $processed = array_flip(array_map('strval', $result['processed_customer_ids'] ?? []));
        $customer_ids = array_values(array_filter($customer_ids, static fn($id)=>!isset($processed[(string)$id])));
        $result['remaining_count'] = count($customer_ids);
        if ($customer_ids) self::store_cleanup_job((string)$token, $customer_ids, $result['counts'], (array)($job['preview_ids'] ?? []));
        else delete_transient('bcrm_cleanup_job_' . get_current_user_id() . '_' . $token);
        self::store_unapproved_invoice_cleanup_result($result);
    }

    private static function empty_cleanup_result(bool $execute): array
    {
        return ['execute'=>$execute,'errors'=>[],'counts'=>[
            'input_rows'=>0,'unique_customer_ids'=>0,'matched_customers'=>0,'single_deleted'=>0,'multi_excluded'=>0,'skipped_not_found'=>0,'skipped_no_unapproved_invoice'=>0,'skipped_already_excluded'=>0,'skipped_multiple_unapproved'=>0,'skipped_customer_has_activity'=>0,'skipped_not_selected'=>0,'failed'=>0,
        ],'samples'=>[],'processed_customer_ids'=>[],'remaining_count'=>0];
    }

    private static function cleanup_action_labels(array $actions): array
    {
        $labels = [
            'single_delete' => 'حذف پروفایل‌های تک‌فاکتوری بدون تایید مالی',
            'multi_exclude' => 'خروج فاکتور بدون تایید از مجموع در پروفایل چندفاکتوری',
            'no_unapproved' => 'ثبت موارد بدون فاکتور بدون تایید فقط در گزارش',
        ];
        $out = [];
        foreach ($actions as $action) if (isset($labels[$action])) $out[] = $labels[$action];
        return $out;
    }

    private static function run_unapproved_invoice_cleanup(array $customer_ids, array &$result, bool $execute, array $actions, int $limit = 0): void
    {
        global $wpdb;
        if (empty($result['counts']['unique_customer_ids'])) $result['counts']['unique_customer_ids'] = count($customer_ids);
        $customers = Biawin_CRM_DB::table('customers');
        $invoices = Biawin_CRM_DB::table('invoices');
        $processed = 0;
        foreach ($customer_ids as $source_customer_id) {
            if ($limit > 0 && $processed >= $limit) break;
            $processed++;
            $result['processed_customer_ids'][] = (string)$source_customer_id;
            $customer = $wpdb->get_row($wpdb->prepare("SELECT id,source_customer_id,customer_code,display_name,total_paid FROM $customers WHERE source_customer_id=%s LIMIT 1", $source_customer_id));
            if (!$customer) {
                $result['counts']['skipped_not_found']++;
                self::cleanup_sample($result, 'پیدا نشد', $source_customer_id, '-', '-', 0, 'CustomerID در CRM پیدا نشد.');
                continue;
            }
            $result['counts']['matched_customers']++;
            $all_invoices = $wpdb->get_results($wpdb->prepare("SELECT * FROM $invoices WHERE customer_id=%d ORDER BY id ASC", (int)$customer->id));
            $unapproved = array_values(array_filter($all_invoices, [__CLASS__, 'invoice_is_financially_unapproved']));
            if (!$unapproved) {
                $result['counts']['skipped_no_unapproved_invoice']++;
                $action_label = $execute && in_array('no_unapproved', $actions, true) ? 'بدون اقدام' : 'رد شد';
                self::cleanup_sample($result, $action_label, $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', '-', 0, 'فاکتور بدون تایید مالی ندارد؛ چیزی برای حذف/خروج از مجموع وجود ندارد.');
                continue;
            }
            if (count($unapproved) > 1) {
                $result['counts']['skipped_multiple_unapproved']++;
                self::cleanup_sample($result, 'نیازمند بررسی دستی', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', implode(',', array_map(static fn($i)=>(string)$i->invoice_code, $unapproved)), 0, 'بیش از یک فاکتور بدون تایید مالی دارد.');
                continue;
            }
            $invoice = $unapproved[0];
            if (count($all_invoices) <= 1) {
                $activity = self::cleanup_customer_activity_count((int)$customer->id);
                if ($activity > 0) {
                    $result['counts']['skipped_customer_has_activity']++;
                    self::cleanup_sample($result, 'رد شد', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', $invoice->invoice_code, (float)$invoice->amount_paid, 'پروفایل تک‌فاکتوری است ولی سابقه عملیاتی دارد؛ برای جلوگیری از حذف تاریخچه، حذف نشد.');
                    continue;
                }
                if ($execute && !in_array('single_delete', $actions, true)) {
                    $result['counts']['skipped_not_selected']++;
                    self::cleanup_sample($result, 'انتخاب نشده', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', $invoice->invoice_code, (float)$invoice->amount_paid, 'دسته تک‌فاکتوری برای اجرای واقعی انتخاب نشده بود.');
                    continue;
                }
                if ($execute) {
                    $ok = self::delete_single_unapproved_invoice_profile((int)$customer->id, (int)$invoice->id, $source_customer_id, (float)$invoice->amount_paid);
                    if (!$ok) { $result['counts']['failed']++; continue; }
                }
                $result['counts']['single_deleted']++;
                self::cleanup_sample($result, $execute ? 'حذف شد' : 'آماده حذف', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', $invoice->invoice_code, (float)$invoice->amount_paid, 'پروفایل تک‌فاکتوری بدون سابقه عملیاتی.');
                continue;
            }
            if ((int)($invoice->exclude_from_total ?? 0) === 1) {
                $result['counts']['skipped_already_excluded']++;
                self::cleanup_sample($result, 'قبلاً خارج شده', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', $invoice->invoice_code, (float)$invoice->amount_paid, 'این فاکتور قبلاً از مجموع خارج شده است.');
                continue;
            }
            if ($execute && !in_array('multi_exclude', $actions, true)) {
                $result['counts']['skipped_not_selected']++;
                self::cleanup_sample($result, 'انتخاب نشده', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', $invoice->invoice_code, (float)$invoice->amount_paid, 'دسته چندفاکتوری برای اجرای واقعی انتخاب نشده بود.');
                continue;
            }
            if ($execute) {
                $updated = $wpdb->update($invoices, [
                    'exclude_from_total'=>1,
                    'excluded_amount'=>(float)$invoice->amount_paid,
                    'excluded_reason'=>'bulk_unapproved_invoice_cleanup',
                    'excluded_at'=>Biawin_CRM_DB::now(),
                    'excluded_by'=>get_current_user_id(),
                    'updated_at'=>Biawin_CRM_DB::now(),
                ], ['id'=>(int)$invoice->id]);
                if ($updated === false || !Biawin_CRM_Customer_Service::recalculate_customer((int)$customer->id)) { $result['counts']['failed']++; continue; }
                Biawin_CRM_DB::log('invoice', (int)$invoice->id, 'unapproved_invoice_excluded_from_total', null, ['customer_id'=>(int)$customer->id,'source_customer_id'=>$source_customer_id,'amount'=>(float)$invoice->amount_paid]);
                Biawin_CRM_Workflow_Service::activity((int)$customer->id, 'unapproved_invoice_excluded_from_total', 'فاکتور بدون تایید مالی از مجموع مبلغ پروفایل خارج شد.', 'invoice', (int)$invoice->id, ['invoice_code'=>$invoice->invoice_code,'amount'=>(float)$invoice->amount_paid]);
            }
            $result['counts']['multi_excluded']++;
            self::cleanup_sample($result, $execute ? 'از مجموع خارج شد' : 'آماده خروج از مجموع', $source_customer_id, $customer->display_name ?: 'پروفایل بدون نام', $invoice->invoice_code, (float)$invoice->amount_paid, 'پروفایل چندفاکتوری است؛ فاکتور حذف نمی‌شود.');
        }
    }

    private static function cleanup_customer_ids_from_rows(array $headers, array $rows): array
    {
        $key = $headers[0] ?? '';
        foreach ($headers as $header) {
            $n = preg_replace('/\s+/u', '', mb_strtolower((string)$header));
            if (in_array($n, ['customerid','customer_id','شناسهcustomer','شناسهcrm','شناسه'], true)) { $key = $header; break; }
        }
        $ids = [];
        foreach ($rows as $row) {
            $raw = trim((string)($row[$key] ?? reset($row)));
            if ($raw === '') continue;
            $raw = strtoupper(preg_replace('/\s+/u', '', $raw));
            if (preg_match('/^C?\d+$/', $raw)) {
                $num = (int)preg_replace('/\D+/', '', $raw);
                $raw = 'C' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
            }
            if (preg_match('/^C\d{1,12}$/', $raw)) $ids[$raw] = $raw;
        }
        return array_values($ids);
    }

    private static function store_cleanup_job(string $token, array $customer_ids, array $counts, ?array $preview_ids = null): void
    {
        set_transient('bcrm_cleanup_job_' . get_current_user_id() . '_' . $token, [
            'customer_ids' => array_values($customer_ids),
            'preview_ids' => array_values($preview_ids ?? $customer_ids),
            'counts' => array_merge([
                'input_rows' => count($customer_ids),
                'unique_customer_ids' => count($customer_ids),
            ], $counts),
        ], HOUR_IN_SECONDS);
    }

    private static function get_cleanup_job(string $token): array
    {
        if ($token === '' || !preg_match('/^[A-Za-z0-9]+$/', $token)) return ['customer_ids'=>[], 'counts'=>[]];
        $job = get_transient('bcrm_cleanup_job_' . get_current_user_id() . '_' . $token);
        if (is_array($job) && isset($job['customer_ids'])) {
            $customer_ids = array_values(array_filter(array_map('strval', (array)$job['customer_ids'])));
            return [
                'customer_ids' => $customer_ids,
                'preview_ids' => array_values(array_filter(array_map('strval', (array)($job['preview_ids'] ?? $customer_ids)))),
                'counts' => is_array($job['counts'] ?? null) ? $job['counts'] : [],
            ];
        }
        $customer_ids = is_array($job) ? array_values(array_filter(array_map('strval', $job))) : [];
        return ['customer_ids'=>$customer_ids, 'preview_ids'=>$customer_ids, 'counts'=>[]];
    }

    private static function invoice_is_financially_unapproved($invoice): bool
    {
        if ((int)($invoice->prepayment_unapproved ?? 0) === 1) return true;
        $text = self::cleanup_normalize_text((string)($invoice->prepayment_approval_status ?? '') . ' ' . (string)($invoice->payment_info ?? ''));
        return strpos($text, 'تاییدنشد') !== false || strpos($text, 'تاييدنشد') !== false || strpos($text, 'unapproved') !== false || strpos($text, 'notapproved') !== false;
    }

    private static function cleanup_normalize_text(string $value): string
    {
        $value = str_replace(['ي', 'ك', 'ۀ', 'ة', 'أ', 'إ'], ['ی', 'ک', 'ه', 'ه', 'ا', 'ا'], trim($value));
        $value = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $value);
        $value = preg_replace('/[\s\-_‌]+/u', '', $value);
        return function_exists('mb_strtolower') ? mb_strtolower((string)$value, 'UTF-8') : strtolower((string)$value);
    }

    private static function cleanup_customer_activity_count(int $customer_id): int
    {
        global $wpdb;
        $tables = ['assignments','interactions','activities','service_cases','complaints','refunds','financial_cases','deficiency_cases','attachments','customer_tags'];
        $count = 0;
        foreach ($tables as $name) {
            $table = Biawin_CRM_DB::table($name);
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists) $count += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE customer_id=%d", $customer_id));
        }
        return $count;
    }

    private static function delete_single_unapproved_invoice_profile(int $customer_id, int $invoice_id, string $source_customer_id, float $amount): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $invoice_items = Biawin_CRM_DB::table('invoice_items');
        $invoice_phones = Biawin_CRM_DB::table('invoice_phones');
        $payments = Biawin_CRM_DB::table('payments');
        $invoices = Biawin_CRM_DB::table('invoices');
        $phones = Biawin_CRM_DB::table('phones');
        $services = Biawin_CRM_DB::table('services');
        $customers = Biawin_CRM_DB::table('customers');
        $ok = $wpdb->delete($invoice_items, ['invoice_id'=>$invoice_id]) !== false
            && $wpdb->delete($invoice_phones, ['invoice_id'=>$invoice_id]) !== false
            && $wpdb->delete($payments, ['invoice_id'=>$invoice_id]) !== false
            && $wpdb->delete($invoices, ['id'=>$invoice_id]) !== false
            && $wpdb->delete($phones, ['customer_id'=>$customer_id]) !== false
            && $wpdb->delete($services, ['customer_id'=>$customer_id]) !== false
            && $wpdb->delete($customers, ['id'=>$customer_id]) !== false;
        if (!$ok) { $wpdb->query('ROLLBACK'); return false; }
        $wpdb->query('COMMIT');
        Biawin_CRM_DB::log('customer', $customer_id, 'single_unapproved_invoice_profile_deleted', null, ['source_customer_id'=>$source_customer_id,'invoice_id'=>$invoice_id,'amount'=>$amount,'deleted_by'=>get_current_user_id()]);
        return true;
    }

    private static function cleanup_sample(array &$result, string $action, string $customer_id, ?string $name, ?string $invoice_code, float $amount, string $note): void
    {
        if (count($result['samples']) >= 80) return;
        $result['samples'][] = ['action'=>$action,'customer_id'=>$customer_id,'name'=>$name ?: 'پروفایل بدون نام','invoice_code'=>$invoice_code ?: '-','amount'=>$amount,'note'=>$note];
    }

    private static function store_unapproved_invoice_cleanup_result(array $result): void
    {
        set_transient('bcrm_unapproved_invoice_cleanup_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-unapproved-cleanup', admin_url('admin.php')));
        exit;
    }

    private static function render_unapproved_invoice_cleanup_result(array $result): void
    {
        if (!empty($result['errors'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html(implode(' ', $result['errors'])) . '</p></div>';
            return;
        }
        if (!empty($result['execute'])) {
            $changed = (int)($result['counts']['single_deleted'] ?? 0) + (int)($result['counts']['multi_excluded'] ?? 0);
            $selected = self::cleanup_action_labels((array)($result['selected_actions'] ?? []));
            echo '<div class="notice notice-success inline"><p><strong>Batch پاکسازی انجام شد.</strong> ';
            echo $changed > 0 ? esc_html(number_format_i18n($changed) . ' مورد در این batch در دیتابیس تغییر کرد.') : 'در این batch مورد قابل تغییری برای دسته‌های انتخاب‌شده نبود.';
            if ((int)($result['remaining_count'] ?? 0) > 0) echo '<br>' . esc_html(number_format_i18n((int)$result['remaining_count'])) . ' CustomerID هنوز برای ادامه اجرا باقی مانده است.';
            else echo '<br>همه موارد این Job تمام شد.';
            if ($selected) echo '<br>دسته‌های انتخاب‌شده: ' . esc_html(implode('، ', $selected));
            echo '</p></div>';
        }
        echo '<h2>' . (!empty($result['execute']) ? 'گزارش اجرای واقعی' : 'گزارش پیش‌نمایش') . '</h2><div class="bcrm-grid">';
        $labels = ['input_rows'=>'ردیف فایل','unique_customer_ids'=>'CustomerID یکتا','matched_customers'=>'پیداشده در CRM','single_deleted'=>'تک‌فاکتوری حذف/آماده حذف','multi_excluded'=>'چندفاکتوری خارج/آماده خروج از مجموع','skipped_no_unapproved_invoice'=>'بدون فاکتور بدون تایید','skipped_multiple_unapproved'=>'چند فاکتور بدون تایید','skipped_customer_has_activity'=>'تک‌فاکتوری دارای سابقه','skipped_not_selected'=>'انتخاب نشده در اجرا','failed'=>'ناموفق'];
        foreach ($labels as $key=>$label) self::metric($label, (int)($result['counts'][$key] ?? 0));
        echo '</div>';
        if (empty($result['execute']) && !empty($result['token']) && empty($result['errors']) && (int)($result['remaining_count'] ?? 0) > 0) {
            echo '<div class="notice notice-info inline"><p><strong>پیش‌نمایش هنوز کامل نشده است.</strong><br>' . esc_html(number_format_i18n((int)$result['remaining_count'])) . ' CustomerID برای تحلیل باقی مانده است. برای جلوگیری از 504، مرحله بعدی را اجرا کنید تا عدد باقی‌مانده صفر شود.</p></div>';
            echo '<div class="bcrm-card" style="margin-top:16px"><h2>ادامه محاسبه پیش‌نمایش</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('bcrm_unapproved_invoice_cleanup');
            echo '<input type="hidden" name="action" value="bcrm_unapproved_invoice_cleanup"><input type="hidden" name="cleanup_mode" value="preview_continue"><input type="hidden" name="cleanup_token" value="' . esc_attr((string)$result['token']) . '">';
            echo '<p><input type="number" name="batch_size" value="1000" min="100" max="1000"> تعداد تحلیل در batch بعدی</p>';
            echo '<p><button class="button button-primary button-hero">ادامه محاسبه پیش‌نمایش</button></p></form></div>';
        } elseif (empty($result['execute']) && !empty($result['token']) && empty($result['errors'])) {
            $single = (int)($result['counts']['single_deleted'] ?? 0);
            $multi = (int)($result['counts']['multi_excluded'] ?? 0);
            $none = (int)($result['counts']['skipped_no_unapproved_invoice'] ?? 0);
            echo '<div class="bcrm-card" style="margin-top:16px"><h2>اجرای واقعی روی همین فایل</h2>';
            echo '<p class="description">دسته‌هایی را که می‌خواهید واقعاً اعمال شوند انتخاب کنید. فایل دوباره آپلود نمی‌شود و همین پیش‌نمایش اجرا خواهد شد.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('bcrm_unapproved_invoice_cleanup');
            echo '<input type="hidden" name="action" value="bcrm_unapproved_invoice_cleanup"><input type="hidden" name="cleanup_mode" value="execute"><input type="hidden" name="cleanup_token" value="' . esc_attr((string)$result['token']) . '">';
            echo '<p><label><input type="checkbox" name="cleanup_actions[]" value="single_delete" ' . disabled($single <= 0, true, false) . '> تک‌فاکتوری‌های بدون تایید مالی حذف شوند <strong>(' . esc_html(number_format_i18n($single)) . ')</strong></label></p>';
            echo '<p><label><input type="checkbox" name="cleanup_actions[]" value="multi_exclude" ' . disabled($multi <= 0, true, false) . '> فاکتورهای بدون تایید در پروفایل چندفاکتوری از مجموع خارج شوند <strong>(' . esc_html(number_format_i18n($multi)) . ')</strong></label></p>';
            echo '<p><label><input type="checkbox" disabled> بدون فاکتور بدون تایید فقط اطلاع‌رسانی است و اجرای دیتابیسی ندارد <strong>(' . esc_html(number_format_i18n($none)) . ')</strong></label></p>';
            echo '<p><label><input type="checkbox" name="create_backup" value="1" checked> قبل از اجرای واقعی، بک‌آپ کامل قابل بازگردانی بساز</label></p>';
            echo '<p><input type="number" name="batch_size" value="1000" min="100" max="1000"> تعداد پردازش در هر batch</p>';
            echo '<p><button class="button button-primary button-hero" onclick="return confirm(\'اجرای واقعی روی دسته‌های انتخاب‌شده انجام شود؟\')">اجرای واقعی دسته‌های انتخاب‌شده</button></p>';
            echo '</form></div>';
        } elseif (!empty($result['execute']) && !empty($result['token']) && (int)($result['remaining_count'] ?? 0) > 0) {
            echo '<div class="bcrm-card" style="margin-top:16px"><h2>ادامه پاکسازی بدون تایید مالی</h2><p class="description">برای جلوگیری از خطای 504، پاکسازی در batchهای کوتاه انجام می‌شود. این دکمه batch بعدی را اجرا می‌کند.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('bcrm_unapproved_invoice_cleanup');
            echo '<input type="hidden" name="action" value="bcrm_unapproved_invoice_cleanup"><input type="hidden" name="cleanup_mode" value="execute"><input type="hidden" name="cleanup_token" value="' . esc_attr((string)$result['token']) . '">';
            foreach ((array)($result['selected_actions'] ?? []) as $action) echo '<input type="hidden" name="cleanup_actions[]" value="' . esc_attr((string)$action) . '">';
            echo '<p><input type="number" name="batch_size" value="1000" min="100" max="1000"> تعداد پردازش در batch بعدی</p>';
            echo '<p><button class="button button-primary button-hero">ادامه اجرای batch بعدی</button></p></form></div>';
        }
        if (!empty($result['samples'])) {
            echo '<table class="widefat striped" style="margin-top:16px"><thead><tr><th>اقدام</th><th>CustomerID</th><th>نام</th><th>فاکتور</th><th>مبلغ</th><th>توضیح</th></tr></thead><tbody>';
            foreach ($result['samples'] as $row) echo '<tr><td>'.esc_html($row['action']).'</td><td>'.esc_html($row['customer_id']).'</td><td>'.esc_html($row['name']).'</td><td>'.esc_html($row['invoice_code']).'</td><td>'.esc_html(number_format_i18n((float)$row['amount'])).'</td><td>'.esc_html($row['note']).'</td></tr>';
            echo '</tbody></table>';
        }
    }

    private static function status_label(?string $status): string
    {
        $labels = [
            'new' => 'جدید',
            'auto_linked' => 'ارتباط قطعی خودکار',
            'needs_review' => 'نیازمند بررسی',
            'assigned' => 'تخصیص‌یافته',
            'in_review' => 'در حال بررسی',
            'verified' => 'بررسی‌شده و دارای کد مشتری',
            'followup_needed' => 'نیازمند پیگیری',
            'complaint_open' => 'نارضایتی ثبت‌شده',
            'closed' => 'بسته‌شده',
        ];
        return $labels[$status ?: ''] ?? (string) $status;
    }
}
