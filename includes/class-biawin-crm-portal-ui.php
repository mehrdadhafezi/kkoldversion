<?php
if (!defined('ABSPATH')) { exit; }

/**
 * C3.11 portal presentation layer.
 *
 * The portal shell and every legacy view are rendered by frozen files
 * (includes/class-biawin-crm-frontend.php, admin/class-biawin-crm-admin.php,
 * assets/**). This class only enqueues an additive design-system stylesheet
 * and a progressive-enhancement script after the frozen assets. Nothing here
 * changes markup on the server, routes, permissions or business behaviour; if
 * the script does not run, every page works exactly as before.
 */
class Biawin_CRM_Portal_UI
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'front_assets'], 40);
    }

    public static function front_assets(): void
    {
        if (is_admin() || !is_user_logged_in() || !current_user_can('bcrm_work_cases')) return;
        $css = BIAWIN_CRM_VERSION . '.' . (string)@filemtime(BIAWIN_CRM_DIR . 'portal-ui/portal.css');
        $js = BIAWIN_CRM_VERSION . '.' . (string)@filemtime(BIAWIN_CRM_DIR . 'portal-ui/portal.js');
        wp_enqueue_style('biawin-crm-portal-ui', BIAWIN_CRM_URL . 'portal-ui/portal.css', ['biawin-crm-portal-fixes'], $css);
        wp_enqueue_script('biawin-crm-portal-ui', BIAWIN_CRM_URL . 'portal-ui/portal.js', [], $js, true);
    }
}
