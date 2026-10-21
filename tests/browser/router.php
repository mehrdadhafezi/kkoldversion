<?php
/**
 * Router for `php -S 127.0.0.1:PORT -t <repo> tests/browser/router.php` (browser acceptance harness only).
 * /portal  → real portal shell + Ownership workspace (Biawin_CRM_Ownership_UI::portal_intercept)
 * /legacy  → real frozen legacy related-search/Attach panel (Biawin_CRM_Frontend markup + assets/frontend.js)
 * /ajax    → admin-ajax emulation: bridge at priority 1, then the real legacy handler / C3 adapter
 * /__test  → read-only fixture inspection and fault injection for the test runner
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(dirname(__DIR__, 2) . $path)) return false; // static assets from the repository

require __DIR__ . '/bootstrap.php';

function page_head(string $title): string {
    $css = '';
    foreach (['assets/frontend.css', 'assets/admin.css', 'assets/portal-fixes.css'] as $file) $css .= '<link rel="stylesheet" href="/' . $file . '">';
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title>' . $css;
}
function enqueued_tags(): string {
    global $enqueued;
    $html = '';
    foreach ($enqueued['styles'] as $src) $html .= '<link rel="stylesheet" href="' . esc_url($src) . '">';
    $scripts = '';
    foreach ($enqueued['data'] as $name => $data) $scripts .= '<script>window.' . $name . '=' . json_encode($data, JSON_UNESCAPED_UNICODE) . ';</script>';
    foreach ($enqueued['scripts'] as $src) $scripts .= '<script src="' . esc_url($src) . '"></script>';
    return $html . '</head><body style="margin:0;background:#f4f6fa">' . '%BODY%' . $scripts . '<script src="/assets/portal-fixes.js"></script>';
}

switch ($path) {
    case '/portal':
        if (!is_user_logged_in()) { echo page_head('ورود') . '</head><body><p data-login>برای ورود، کاربر آزمون را انتخاب کنید.</p></body></html>'; exit; }
        Biawin_CRM_Ownership_UI::front_assets();
        $_GET['bcrm_view'] = $_GET['bcrm_view'] ?? 'ownership';
        $html = Biawin_CRM_Ownership_UI::portal_intercept(false, 'biawin_crm_portal');
        if ($html === false) $html = '<div class="bcrm-portal" dir="rtl"><p>این نما در محیط آزمون پشتیبانی نمی‌شود.</p></div>';
        if (!wp_script_is_ownership()) $html .= '<script>window.BCRM_OWN=null;</script>';
        echo page_head('تطبیق مالکیت') . str_replace('%BODY%', $html, enqueued_tags()) . '</body></html>';
        exit;
    case '/legacy':
        // Legacy profile Attach panel exactly as the frozen frontend renders it, driven by the frozen assets/frontend.js.
        $customer = absint($_GET['customer'] ?? 0);
        Biawin_CRM_Ownership_UI::front_assets();
        $enqueued['data']['BiawinCRM'] = ['ajaxUrl' => '/ajax', 'nonce' => wp_create_nonce('bcrm_front_ajax'), 'currency' => 'تومان', 'portalUrl' => '/portal', 'canManageCustomerPhones' => 0, 'maxFileUploads' => 20];
        $enqueued['scripts'] = ['biawin-crm-frontend' => '/assets/frontend.js'] + $enqueued['scripts'];
        $panel = new ReflectionMethod('Biawin_CRM_Frontend', 'render_search_and_attach');
        $panel->setAccessible(true);
        ob_start(); $panel->invoke(null, $customer); $markup = (string)ob_get_clean();
        $body = '<div class="bcrm-portal-shell" dir="rtl"><main class="bcrm-shell-main"><div class="bcrm-shell-content"><div class="bcrm-portal bcrm-app" dir="rtl"><h1>پروفایل مشتری #' . $customer . '</h1><button type="button" class="bcrm-primary-button" data-bcrm-toggle-related>افزودن شماره / فاکتور</button>' . $markup . '</div></div></main></div>';
        echo page_head('پروفایل') . str_replace('%BODY%', $body, enqueued_tags()) . '</body></html>';
        exit;
    case '/ajax':
        $action = sanitize_key((string)($_POST['action'] ?? ''));
        if ($action === Biawin_CRM_Ownership_Ajax::ACTION) { Biawin_CRM_Ownership_Ajax::handle(); exit; }
        $legacy = ['bcrm_attach_invoice' => 'ajax_attach_invoice', 'bcrm_attach_bundle' => 'ajax_attach_bundle', 'bcrm_search_related' => 'ajax_search_related'];
        if (!isset($legacy[$action])) { http_response_code(400); echo '0'; exit; }
        // admin-ajax: do_action('wp_ajax_' . $action) runs priority 1 (C3.8 bridge) before priority 10 (frozen handler).
        $current_hook = 'wp_ajax_' . $action;
        if (in_array($action, Biawin_CRM_Ownership_Legacy_Bridge::AJAX_ACTIONS, true)) Biawin_CRM_Ownership_Legacy_Bridge::guard_ajax();
        call_user_func(['Biawin_CRM_Frontend', $legacy[$action]]);
        exit;
    case '/__test/fail':
        file_put_contents(BCRM_RUNTIME . '/fail-customer', (string)absint($_GET['customer'] ?? 0));
        wp_send_json(['ok' => true]);
    case '/__test/otp':
        wp_send_json(is_file(BCRM_RUNTIME . '/last-otp.json') ? json_decode((string)file_get_contents(BCRM_RUNTIME . '/last-otp.json'), true) : null);
    case '/__test/sql':
        $q = (string)($_GET['q'] ?? '');
        if (!preg_match('/^\s*SELECT\b/i', $q)) { http_response_code(400); wp_send_json(['error' => 'SELECT only']); }
        wp_send_json($wpdb->get_results($q, 'ARRAY_A'));
    case '/__test/set-execution':
        // Fixture simulation only: a process crash after a REAL commit, before the failure was recorded,
        // leaves COMMITTED behind. Allowed only on a case whose transfer was genuinely committed.
        $done = $wpdb->query("UPDATE test_bcrm_ownership_cases SET execution_state='COMMITTED' WHERE id=" . absint($_GET['case'] ?? 0) . " AND execution_state='FINALIZATION_FAILED'");
        wp_send_json(['ok' => $done === 1]);
    case '/__test/fixture':
        wp_send_json(json_decode((string)file_get_contents(BCRM_RUNTIME . '/fixture.json'), true));
}
if (preg_match('~^/media/(\d+)$~', $path, $m) && ($media = bcrm_media((int)$m[1]))) { header('Content-Type: ' . $media['mime']); readfile($media['path']); exit; }
http_response_code(404);
echo 'not found';

function wp_script_is_ownership(): bool { global $enqueued; return isset($enqueued['data']['BCRM_OWN']); }
