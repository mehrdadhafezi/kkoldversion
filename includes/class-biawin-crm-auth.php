<?php

if (!defined('ABSPATH')) { exit; }

class Biawin_CRM_Auth
{
    public static function init(): void
    {
        add_action('admin_init', [__CLASS__, 'block_agent_admin']);
        add_filter('show_admin_bar', [__CLASS__, 'filter_admin_bar'], 99);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);
        add_filter('wp_authenticate_user', [__CLASS__, 'reject_disabled_user'], 20, 2);
        add_action('admin_post_nopriv_bcrm_portal_login', [__CLASS__, 'handle_login']);
        add_action('admin_post_bcrm_portal_logout', [__CLASS__, 'handle_logout']);
    }


    public static function filter_admin_bar($show): bool
    {
        if (!is_user_logged_in()) {
            return (bool) $show;
        }

        $page_id = (int) get_option('biawin_crm_portal_page_id', 0);
        if ($page_id > 0 && function_exists('get_queried_object_id') && (int) get_queried_object_id() === $page_id) {
            return false;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && strpos($uri, '/biawin-crm-portal') !== false) {
            return false;
        }

        return (bool) $show;
    }

    public static function portal_url(array $args = []): string
    {
        $page_id = (int) get_option('biawin_crm_portal_page_id', 0);
        $url = $page_id ? get_permalink($page_id) : home_url('/biawin-crm-portal/');
        return $args ? add_query_arg($args, $url) : $url;
    }

    public static function handle_login(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bcrm_portal_login')) {
            wp_safe_redirect(self::portal_url(['bcrm_login_error' => 'security'])); exit;
        }
        $username_raw = (string) wp_unslash($_POST['username'] ?? '');
        $username = self::normalize_portal_username($username_raw);
        $password = (string) wp_unslash($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            wp_safe_redirect(self::portal_url(['bcrm_login_error' => 'required'])); exit;
        }
        $user = wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => !empty($_POST['remember']),
        ], is_ssl());
        if (is_wp_error($user) || !$user->has_cap('bcrm_work_cases') || (bool)get_user_meta($user->ID, 'bcrm_disabled', true)) {
            if (!is_wp_error($user)) { wp_logout(); }
            wp_safe_redirect(self::portal_url(['bcrm_login_error' => 'invalid'])); exit;
        }
        update_user_meta($user->ID, 'bcrm_last_portal_login', current_time('mysql'));
        wp_safe_redirect(self::portal_url()); exit;
    }

    private static function normalize_portal_username(string $username): string
    {
        $username = trim($username);
        if ($username === '') return '';

        $username = strtr($username, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
        $digits = preg_replace('/\D+/', '', $username);
        if (is_string($digits)) {
            if (preg_match('/^09\d{9}$/', $digits)) return $digits;
            if (preg_match('/^9\d{9}$/', $digits)) return '0' . $digits;
            if (preg_match('/^98(9\d{9})$/', $digits, $m)) return '0' . $m[1];
            if (preg_match('/^0098(9\d{9})$/', $digits, $m)) return '0' . $m[1];
        }
        return sanitize_user($username);
    }

    public static function handle_logout(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bcrm_portal_logout')) {
            wp_die('درخواست خروج نامعتبر است.');
        }
        wp_logout();
        wp_safe_redirect(self::portal_url(['logged_out' => 1])); exit;
    }

    public static function block_agent_admin(): void
    {
        $script = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (!is_user_logged_in() || wp_doing_ajax() || $script === 'admin-post.php') { return; }
        if (current_user_can('manage_options')) { return; }
        if (current_user_can('bcrm_work_cases')) {
            wp_safe_redirect(self::portal_url()); exit;
        }
    }

    public static function reject_disabled_user($user, $password)
    {
        if ($user instanceof WP_User && $user->has_cap('bcrm_work_cases') && (bool)get_user_meta($user->ID, 'bcrm_disabled', true)) {
            return new WP_Error('bcrm_disabled', 'حساب کاربری پرتال غیرفعال است.');
        }
        return $user;
    }

    public static function login_redirect($redirect_to, $requested, $user)
    {
        if ($user instanceof WP_User && $user->has_cap('bcrm_work_cases') && !$user->has_cap('manage_options')) {
            return self::portal_url();
        }
        return $redirect_to;
    }

    public static function role_labels(): array
    {
        return [
            'bcrm_portal_admin' => 'مدیر پرتال CRM',
            'bcrm_agent' => 'کارشناس تماس',
            'bcrm_supervisor' => 'سرپرست CRM',
            'bcrm_deficiency_supervisor' => 'سرپرست رفع ناقصی',
            'bcrm_deficiency_agent' => 'کارشناس رفع ناقصی',
            'bcrm_financial_supervisor' => 'سرپرست تأیید مالی',
            'bcrm_financial_agent' => 'کارشناس تأیید مالی',
            'bcrm_complaint_supervisor' => 'سرپرست نارضایتی‌ها',
            'bcrm_complaint_agent' => 'کارشناس نارضایتی‌ها',
            'bcrm_service_supervisor' => 'سرپرست واحد خدمات',
            'bcrm_service_agent' => 'کارشناس خدمات',
            'bcrm_refund_supervisor' => 'سرپرست عودت وجه',
            'bcrm_refund_agent' => 'کارشناس عودت وجه',
            'bcrm_refund_register' => 'ثبت‌کننده عودت اکسلی',
            // v0.8.63.0 — after-sales foundation
            'bcrm_after_sales_manager' => 'مدیر خدمات پس از فروش',
            'bcrm_loan_supervisor' => 'سرپرست وام',
            'bcrm_loan_agent' => 'کارشناس وام',
            'bcrm_treasury_supervisor' => 'سرپرست خزانه',
            'bcrm_treasury_agent' => 'کارشناس خزانه',
        ];
    }
}
