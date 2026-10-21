<?php
/**
 * Plugin Name: Biawin CRM Customer Integration
 * Description: افزونه یکپارچه‌سازی مشتریان، تجمیع فاکتورها، رضایت‌سنجی، شکایات، گریدبندی و تخصیص پرونده به کارشناسان.
 * Version: 0.8.65.19
 * Author: Biawin
 * Text Domain: biawin-crm
 * Requires PHP: 7.4
 * Update URI: false
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BIAWIN_CRM_VERSION', '0.8.65.19');
define('BIAWIN_CRM_FILE', __FILE__);
define('BIAWIN_CRM_DIR', plugin_dir_path(__FILE__));
define('BIAWIN_CRM_URL', plugin_dir_url(__FILE__));

require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-activator.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-db.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-schema.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-authorization.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-migration-audit.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-repository.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-audit.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-customer-hold-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-lock-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-invoice-phone-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-risk-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-workflow.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-otp.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-transfer-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-write-guard.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-presenter.php'; // C3 UI: read-only view models
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-ajax.php'; // C3 UI: thin command adapters
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-ui.php'; // C3 UI: workspaces
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-ownership-legacy-bridge.php'; // C3.8: legacy Attach ↔ ownership bridge
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-portal-ui.php'; // C3.11: portal design system / enhancements
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-file-safety.php'; // v0.8.63.2
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-upload-normalizer.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-receipt-staging.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-finance-review.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-finance-report.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-auth.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-normalizer.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-customer-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-workflow-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-after-sales-config.php'; // v0.8.64.0
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-after-sales-config-admin.php'; // v0.8.64.0
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-refund-register.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-deficiency-workflow.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-import-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-historical-audit.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-forward-reconstruction-planner.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-replacement-manifest.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-commit-safety-preflight.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-expert-intent-merge-audit.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-final-safe-set.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-controlled-reconstruction.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-controlled-job-1000-planner.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-production-readiness.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-canonical-reconciliation-plan.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-blacklist-final-commit-engine.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-post-run7-reconciliation.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-reporting-population-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-reporting-financial-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-reporting-assignment-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-reporting-validation-service.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-reporting-consumer-adapter.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-rest.php';
require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-frontend.php';
require_once BIAWIN_CRM_DIR . 'admin/class-biawin-crm-admin.php';

register_activation_hook(__FILE__, ['Biawin_CRM_Activator', 'activate']);

add_action('plugins_loaded', function () {
    Biawin_CRM_Activator::maybe_upgrade();
    add_filter('pre_delete_attachment', ['Biawin_CRM_Ownership_Write_Guard', 'protect_media'], 10, 3);
    Biawin_CRM_Auth::init();
    Biawin_CRM_Admin::init();
    Biawin_CRM_After_Sales_Config_Admin::init(); // v0.8.64.0: «تنظیمات نارضایتی»
    Biawin_CRM_Production_Readiness::init();
    Biawin_CRM_Canonical_Reconciliation_Plan::init();
    Biawin_CRM_Blacklist_Final_Commit_Engine::init();
    Biawin_CRM_Post_Run7_Reconciliation::init();
    Biawin_CRM_Frontend::init();
    Biawin_CRM_Receipt_Staging::init();
    Biawin_CRM_Finance_Review::init(); // v0.8.62.28: بررسی فیش و تأیید/رد مالی در همان پنجره نمایش فیش
    Biawin_CRM_Finance_Report::register_hooks(); // v0.8.65.11: گزارش فقط‌خواندنی تأیید مالی + Excel
    Biawin_CRM_REST::init();
    Biawin_CRM_Ownership_UI::init(); // C3: «تطبیق مالکیت» workspaces (portal + admin)
    Biawin_CRM_Portal_UI::init(); // C3.11: additive portal design system
});

// v0.8.62.3: safety net for EXISTING installations — before any admin screen (or the
// menu page itself) resolves, re-sync roles/caps once. Covers installs whose
// administrator role predates bcrm_refund_register and who never re-activated the
// plugin (WordPress ZIP updates do NOT run register_activation_hook). Idempotent.
add_action('admin_init', ['Biawin_CRM_Activator', 'ensure_roles_and_caps']);
