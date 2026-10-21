<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Frontend
{
    public static function init(): void
    {
        add_shortcode('biawin_crm_portal', [__CLASS__, 'portal']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        // v0.8.62.14: دانلود خروجی اکسل باید قبل از هر خروجی HTML (المنتور/قالب) رهگیری شود؛
        // وگرنه «headers already sent» و باینری XLSX وسط صفحه چاپ می‌شود.
        add_action('template_redirect', [__CLASS__, 'maybe_intercept_refund_export'], 1);
        add_filter('wp_redirect', [__CLASS__, 'frontend_redirect_filter'], 20, 2);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('request', [__CLASS__, 'normalize_portal_request']);

        foreach ([
            'bcrm_front_interaction' => 'handle_interaction',
            'bcrm_front_attach_invoice' => 'handle_attach_invoice',
            'bcrm_front_add_phone' => 'handle_add_phone',
            'bcrm_front_journey' => 'handle_journey',
            'bcrm_front_complaint' => 'handle_complaint',
            'bcrm_front_services' => 'handle_services',
            'bcrm_front_complaint_resolution' => 'handle_complaint_resolution',
            'bcrm_front_service_case' => 'handle_service_case',
            'bcrm_front_manual_invoice' => 'handle_manual_invoice',
            'bcrm_front_invoice_phone_bundle' => 'handle_invoice_phone_bundle',
            'bcrm_front_financial_evidence' => 'handle_financial_evidence',
        ] as $action => $method) {
            add_action('admin_post_' . $action, [__CLASS__, $method]);
            add_action('admin_post_nopriv_' . $action, [__CLASS__, 'deny_guest']);
        }

        add_action('wp_ajax_bcrm_search_related', [__CLASS__, 'ajax_search_related']);
        add_action('wp_ajax_bcrm_attach_invoice', [__CLASS__, 'ajax_attach_invoice']);
        add_action('wp_ajax_bcrm_add_phone', [__CLASS__, 'ajax_add_phone']);
        add_action('wp_ajax_bcrm_add_invoice_phone', [__CLASS__, 'ajax_add_invoice_phone']);
        add_action('wp_ajax_bcrm_attach_bundle', [__CLASS__, 'ajax_attach_bundle']);
        add_action('wp_ajax_bcrm_preview_reconciliation', [__CLASS__, 'ajax_preview_reconciliation']);
        add_action('wp_ajax_bcrm_save_interaction', [__CLASS__, 'ajax_save_interaction']);
        add_action('wp_ajax_bcrm_set_journey', [__CLASS__, 'ajax_set_journey']);
        add_action('wp_ajax_bcrm_select_services', [__CLASS__, 'ajax_select_services']);
        add_action('wp_ajax_bcrm_create_complaint', [__CLASS__, 'ajax_create_complaint']);
        add_action('wp_ajax_bcrm_update_service_case', [__CLASS__, 'ajax_update_service_case']);
        add_action('wp_ajax_bcrm_manual_bundle', [__CLASS__, 'ajax_manual_bundle']);
        add_action('wp_ajax_bcrm_phone_verification', [__CLASS__, 'ajax_phone_verification']);
        add_action('wp_ajax_bcrm_edit_manual_phone', [__CLASS__, 'ajax_edit_manual_phone']);
        add_action('wp_ajax_bcrm_detach_customer_phone', [__CLASS__, 'ajax_detach_customer_phone']);
        add_action('wp_ajax_bcrm_edit_manual_invoice', [__CLASS__, 'ajax_edit_manual_invoice']);
        add_action('wp_ajax_bcrm_assign_service_case_front', [__CLASS__, 'ajax_assign_service_case_front']);
    }

    public static function query_vars(array $vars): array
    {
        foreach (['bcrm_view','bcrm_customer','bcrm_filter','bcrm_q','bcrm_s','bcrm_paged','bcrm_per_page','bcrm_contact','tab','case_id','bcrm_message','bcrm_error'] as $var) {
            if (!in_array($var, $vars, true)) $vars[] = $var;
        }
        return $vars;
    }

    /** v0.8.62.14: رهگیری دانلود خروجی عودت‌ها روی template_redirect — قبل از هر خروجی HTML.
     *  شرط‌ها: پرتال (نه ادمین)، view=refunded، پارامتر bcrm_rf_export=1 و nonce معتبر.
     *  اگر شرط برقرار نباشد هیچ کاری نمی‌کند و جریان عادی ادامه پیدا می‌کند. */
    public static function maybe_intercept_refund_export(): void
    {
        if (is_admin()) return;
        if (empty($_GET['bcrm_rf_export']) || (string)$_GET['bcrm_rf_export'] !== '1') return;
        $view = sanitize_key((string)($_GET['bcrm_view'] ?? ''));
        if ($view === '' && isset($_GET['page'])) $view = self::view_from_admin_slug(sanitize_key((string) wp_unslash($_GET['page'])));
        if ($view !== 'refunded') return;
        // در این نقطه هنوز هیچ HTML ای چاپ نشده — خروجی تمیز است.
        self::export_refunded_excel();
    }

    public static function normalize_portal_request(array $query_vars): array
    {
        if (!is_admin() && isset($_GET['bcrm_view']) && isset($query_vars['s'])) {
            $query_vars['bcrm_s'] = $query_vars['s'];
            unset($query_vars['s']);
        }
        return $query_vars;
    }

    public static function assets(): void
    {
        if (is_admin()) {
            return;
        }
        $frontend_css_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/frontend.css');
        $admin_css_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/admin.css');
        $fix_css_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/portal-fixes.css');
        $frontend_js_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/frontend.js');
        $admin_js_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/admin.js');
        $fix_js_ver = BIAWIN_CRM_VERSION . '.' . (string) @filemtime(BIAWIN_CRM_DIR . 'assets/portal-fixes.js');
        wp_enqueue_style('biawin-crm-frontend', BIAWIN_CRM_URL . 'assets/frontend.css', [], $frontend_css_ver);
        wp_enqueue_style('biawin-crm-admin-frontend', BIAWIN_CRM_URL . 'assets/admin.css', [], $admin_css_ver);
        wp_enqueue_style('biawin-crm-portal-fixes', BIAWIN_CRM_URL . 'assets/portal-fixes.css', ['biawin-crm-frontend', 'biawin-crm-admin-frontend'], $fix_css_ver);
        wp_add_inline_style('biawin-crm-portal-fixes', '.bcrm-portal-shell,.bcrm-auth-screen{--bcrm-font-family:' . self::portal_font_stack() . ';font-family:var(--bcrm-font-family)!important}');
        wp_enqueue_script('biawin-crm-frontend', BIAWIN_CRM_URL . 'assets/frontend.js', [], $frontend_js_ver, true);
        wp_enqueue_script('biawin-crm-admin-frontend', BIAWIN_CRM_URL . 'assets/admin.js', [], $admin_js_ver, true);
        wp_enqueue_script('biawin-crm-portal-fixes', BIAWIN_CRM_URL . 'assets/portal-fixes.js', ['biawin-crm-frontend'], $fix_js_ver, true);
        wp_localize_script('biawin-crm-frontend', 'BiawinCRM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bcrm_front_ajax'),
            'stageNonce' => wp_create_nonce(Biawin_CRM_Receipt_Staging::NONCE_ACTION),
            'stagingToken' => Biawin_CRM_Receipt_Staging::fixed_token(),
            'finReviewNonce' => wp_create_nonce('bcrm_fin_review'),
            'currency' => 'تومان',
            'portalUrl' => Biawin_CRM_Auth::portal_url(),
            'canManageCustomerPhones' => current_user_can('bcrm_manage_customer_phones') ? 1 : 0,
            'maxFileUploads' => Biawin_CRM_Upload_Normalizer::max_uploads_ini(),
        ]);
        wp_localize_script('biawin-crm-admin-frontend', 'BCRM_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'importNonce' => wp_create_nonce('bcrm_inspect_import_headers'),
            'batchNonce' => wp_create_nonce('bcrm_batch_import'),
            'stageNonce' => wp_create_nonce(Biawin_CRM_Receipt_Staging::NONCE_ACTION),
            'stagingToken' => Biawin_CRM_Receipt_Staging::fixed_token(),
            'finReviewNonce' => wp_create_nonce('bcrm_fin_review'),
            'portalMode' => true,
            'maxFileUploads' => Biawin_CRM_Upload_Normalizer::max_uploads_ini(),
        ]);
    }

    public static function font_choices(): array
    {
        return [
            'system' => ['label'=>'پیش‌فرض سیستم', 'stack'=>'Tahoma, Arial, sans-serif'],
            'vazirmatn' => ['label'=>'Vazirmatn', 'stack'=>'Vazirmatn, Vazir, Tahoma, Arial, sans-serif'],
            'iransans' => ['label'=>'IRANSans', 'stack'=>'IRANSans, IranSans, Tahoma, Arial, sans-serif'],
            'dana' => ['label'=>'Dana', 'stack'=>'Dana, Tahoma, Arial, sans-serif'],
            'shabnam' => ['label'=>'Shabnam', 'stack'=>'Shabnam, Tahoma, Arial, sans-serif'],
            'sahel' => ['label'=>'Sahel', 'stack'=>'Sahel, Tahoma, Arial, sans-serif'],
            'yekan' => ['label'=>'Yekan', 'stack'=>'Yekan, BYekan, Tahoma, Arial, sans-serif'],
        ];
    }

    public static function selected_portal_font_key(): string
    {
        $key = sanitize_key((string) get_option('biawin_crm_portal_font', 'system'));
        return isset(self::font_choices()[$key]) ? $key : 'system';
    }

    public static function portal_font_stack(?string $key = null): string
    {
        $choices = self::font_choices();
        $key = $key !== null ? sanitize_key($key) : self::selected_portal_font_key();
        return $choices[$key]['stack'] ?? $choices['system']['stack'];
    }

    public static function portal(): string
    {
        if (!is_user_logged_in()) {
            $error = sanitize_key($_GET['bcrm_login_error'] ?? '');
            $messages = ['required'=>'نام کاربری و رمز عبور را وارد کنید.','invalid'=>'نام کاربری یا رمز عبور اشتباه است یا دسترسی پرتال ندارید.','security'=>'درخواست ورود معتبر نیست.'];
            ob_start();
            echo '<div class="bcrm-portal bcrm-auth-screen" dir="rtl"><div class="bcrm-auth-layout"><section class="bcrm-auth-visual"><div class="bcrm-auth-logo">B</div><div><span class="bcrm-auth-eyebrow">BIAWIN CRM</span><h1>مرکز یکپارچه مدیریت مشتریان</h1><p>پروفایل ۳۶۰ درجه، تخصیص هوشمند، رضایت‌سنجی، شکایات، مالی و خدمات در یک محیط واحد.</p></div><div class="bcrm-auth-points"><span>پروفایل یکپارچه مشتری</span><span>کارتابل‌های تخصصی</span><span>گزارش و پیگیری لحظه‌ای</span></div></section><section class="bcrm-auth-card"><div class="bcrm-auth-brand"><strong>CRM بیاوین</strong><span>ورود به پرتال سازمانی</span></div><h2>خوش آمدید</h2><p class="bcrm-auth-subtitle">برای ادامه وارد حساب کاربری خود شوید.</p>';
            if ($error && isset($messages[$error])) echo '<div class="bcrm-alert is-danger">'.esc_html($messages[$error]).'</div>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="bcrm-login-form">';
            wp_nonce_field('bcrm_portal_login');
            echo '<input type="hidden" name="action" value="bcrm_portal_login"><label><span>نام کاربری</span><input name="username" autocomplete="username" placeholder="نام کاربری" required></label><label><span>رمز عبور</span><input type="password" name="password" autocomplete="current-password" placeholder="رمز عبور" required></label><label class="bcrm-remember"><input type="checkbox" name="remember" value="1"><span>مرا به خاطر بسپار</span></label><button class="bcrm-primary-button bcrm-login-submit" type="submit">ورود به پرتال</button></form></section></div></div>';
            return (string)ob_get_clean();
        }
        if (!current_user_can('bcrm_work_cases') || (bool)get_user_meta(get_current_user_id(), 'bcrm_disabled', true)) {
            return '<div class="bcrm-portal" dir="rtl"><div class="bcrm-alert is-danger">حساب شما برای پرتال CRM فعال نیست یا دسترسی لازم را ندارد.</div></div>';
        }

        $customer_id = isset($_GET['bcrm_customer']) ? absint($_GET['bcrm_customer']) : 0;
        $view_raw = isset($_GET['bcrm_view']) ? (string) wp_unslash($_GET['bcrm_view']) : '';
        if ($view_raw === '' && isset($_GET['page'])) {
            $view_raw = self::view_from_admin_slug(sanitize_key((string) wp_unslash($_GET['page'])));
        }
        if (isset($_GET['bcrm_s']) && !isset($_GET['s'])) {
            $_GET['s'] = wp_unslash($_GET['bcrm_s']);
        }
        $view = sanitize_key($view_raw !== '' ? $view_raw : ($customer_id ? 'customers' : 'dashboard'));
        if ($customer_id) {
            $content = self::render_profile($customer_id);
            return self::render_shell($content, 'customers');
        }

        $views = self::portal_views();
        if (!isset($views[$view]) || !self::can_view($views[$view])) {
            $view = current_user_can('bcrm_manage_cases') ? 'dashboard' : 'customers';
        }
        // v0.8.62.9/v0.8.62.14: خروجی اکسل عودت‌ها حالا در template_redirect رهگیری می‌شود
        // (قبل از هر خروجی HTML)؛ اینجا فقط fallback محافظتی است اگر هوک رد شده باشد.
        if ($view === 'refunded' && isset($_GET['bcrm_rf_export']) && $_GET['bcrm_rf_export'] === '1') {
            self::export_refunded_excel();
        }
        $content = self::render_portal_view($view);
        return self::render_shell($content, $view);
    }

    private static function portal_views(): array
    {
        return [
            'dashboard' => ['label'=>'داشبورد','cap'=>'bcrm_work_cases','icon'=>'🏠'],
            'customers' => ['label'=>'مشتریان','cap'=>'bcrm_work_cases','icon'=>'👥'],
            'assignments' => ['label'=>'تخصیص پروفایل','cap'=>'bcrm_manage_cases','icon'=>'↔'],
            'import' => ['label'=>'ایمپورت اطلاعات','cap'=>'bcrm_import','icon'=>'⬇'],
            'deficiency' => ['label'=>'رفع ناقصی','cap'=>'view_deficiency_cases','icon'=>'◈'],
            'financial' => ['label'=>'تأیید مالی','cap'=>'view_finance_cases','icon'=>'✓'],
            'complaints' => ['label'=>'نارضایتی‌ها','cap'=>'bcrm_complaints','icon'=>'!'],
            'service-cases' => ['label'=>'کارتابل خدمات','cap'=>'bcrm_services','icon'=>'▦'],
            'services' => ['label'=>'کاتالوگ خدمات','cap'=>'bcrm_manage_cases','icon'=>'◆'],
            'service-units' => ['label'=>'واحدها و اعضا','cap'=>'bcrm_manage_cases','icon'=>'▤'],
            'refunds' => ['label'=>'عودت وجه','cap'=>'bcrm_refunds','icon'=>'↶'],
            'refunded' => ['label'=>'عودتی‌ها','cap'=>'bcrm_refund_register','icon'=>'⚑'],
            'reports' => ['label'=>'گزارش‌ها','cap'=>'bcrm_reports','icon'=>'▥'],
            'data-audit' => ['label'=>'Data Audit','cap'=>'bcrm_reports','icon'=>'▧'],
            'users' => ['label'=>'کاربران و دسترسی‌ها','cap'=>'bcrm_manage_cases','icon'=>'♟'],
            'audit' => ['label'=>'لاگ ممیزی','cap'=>'bcrm_manage_cases','icon'=>'≡'],
            'settings' => ['label'=>'تنظیمات','cap'=>'bcrm_manage_cases','icon'=>'⚙'],
        ];
    }

    private static function can_view(array $cfg): bool
    {
        // v0.8.62.6: the «عودتی‌ها» tab opens for EITHER the register cap (upload-capable,
        // e.g. سرپرست عودت وجه) OR the lighter view-only cap (bcrm_view_refunded).
        if (($cfg['cap'] ?? '') === 'bcrm_refund_register') {
            return current_user_can('bcrm_refund_register') || current_user_can('bcrm_view_refunded');
        }
        return current_user_can((string)$cfg['cap']);
    }

    /** v0.8.62.8 — تب «عودت‌ها» (بازطراحی حرفه‌ای): فیلترهای اکسلی سرور-ساید + KPI کلیک‌پذیر.
     *  منطق کسب‌وکار (Last-Refund balance، تگ‌ها، ایمپورت اکسل) دست‌نخورده مانده است. */
    private static function render_refunded_list(): string
    {
        global $wpdb;
        if (!class_exists('Biawin_CRM_Refund_Register')) return '<div class="bcrm-portal bcrm-app" dir="rtl"></div>';
        $service = 'Biawin_CRM_Refund_Register';
        $refund_tag_op_active = false; // legacy name kept for harness compatibility

        /* ---------- (1) پارامترهای HTTP — allowlist، بدون warning، نام‌های ASCII پایدار ---------- */
        $q = isset($_GET['r_q']) && is_string($_GET['r_q']) ? trim(wp_unslash($_GET['r_q'])) : '';
        $q = trim((string)preg_replace('/[\x{200c}]+/u', ' ', $q));
        $q = trim((string)preg_replace('/\s+/u', ' ', $q));
        if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);
        $sort = in_array((string)($_GET['r_sort'] ?? ''), ['name', 'code', 'phone', 'invoices', 'refund', 'balance', 'tags'], true) ? (string)$_GET['r_sort'] : '';
        $dir_raw = strtolower((string)($_GET['r_dir'] ?? ''));
        $dir = $dir_raw === 'asc' ? 'asc' : ($dir_raw === 'desc' ? 'desc' : '');
        $tag_mode = $service::sanitize_tag_mode(isset($_GET['tag_mode']) && is_string($_GET['tag_mode']) ? wp_unslash($_GET['tag_mode']) : '');
        $legacy_tag = $service::sanitize_tag_filter(isset($_GET['refund_tag']) && is_string($_GET['refund_tag']) ? wp_unslash($_GET['refund_tag']) : 'any');
        if ($tag_mode === '' && $legacy_tag !== 'any') {
            $legacy_map = ['REFUND_CONFIRMED' => $service::TAG_MODE_CONFIRMED, 'REFUND_REPORTED' => $service::TAG_MODE_REPORTED, 'BOTH' => $service::TAG_MODE_BOTH];
            $tag_mode = $legacy_map[$legacy_tag] ?? '';
        }
        // اعداد فارسی/عربی، جداکننده هزارگان و فاصله → float (الگوی Biawin_CRM_Normalizer::money).
        $num = static function (string $key): ?float {
            if (!isset($_GET[$key]) || !is_string($_GET[$key])) return null;
            $raw = str_replace([',', '٬', '،', ' '], '', wp_unslash($_GET[$key]));
            $raw = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $raw);
            $raw = trim($raw);
            if ($raw === '' || !is_numeric($raw)) return null;
            return (float)$raw;
        };
        $inv_op = (string)($_GET['inv_op'] ?? '');
        $inv_a = $num('inv_a');
        $inv_b = $num('inv_b');
        if (!in_array($inv_op, ['eq', 'gt', 'gte', 'lt', 'lte', 'between'], true) || $inv_a === null || ($inv_op === 'between' && $inv_b === null)) $inv_op = '';
        if ($inv_op !== '' && $inv_op !== 'between') $inv_b = null;
        $r_op = (string)($_GET['r_op'] ?? '');
        $r_a = $num('r_a');
        $r_b = $num('r_b');
        if (!in_array($r_op, ['eq', 'gt', 'gte', 'lt', 'lte', 'between'], true) || $r_a === null || ($r_op === 'between' && $r_b === null)) $r_op = '';
        if ($r_op !== '' && $r_op !== 'between') $r_b = null;
        $b_op = (string)($_GET['b_op'] ?? '');
        $b_a = $num('b_a');
        $b_b = $num('b_b');
        if (!in_array($b_op, ['eq', 'gt', 'lt', 'between'], true) || $b_a === null || ($b_op === 'between' && $b_b === null)) $b_op = '';
        if ($b_op !== '' && $b_op !== 'between') $b_b = null;
        $b_sign = (string)($_GET['b_sign'] ?? '');
        if (!in_array($b_sign, ['neg', 'pos', 'zero'], true)) $b_sign = '';
        $u_sign = (string)($_GET['u_sign'] ?? '');
        if (!in_array($u_sign, ['yes', 'no'], true)) $u_sign = '';
        // v0.8.62.6 FIX حفظ شد: خواندن r_pp با isset قبل از allowlist (بدون warning/division-by-zero).
        $pp_raw = isset($_GET['r_pp']) ? (int)$_GET['r_pp'] : 25;
        $per_page = in_array($pp_raw, [25, 50, 100], true) ? $pp_raw : 25;
        $page = max(1, (int)($_GET['r_p'] ?? 1));

        /* ---------- (2) WHERE مشترک (تولید لیست + KPI) — همه فیلترها در SQL ---------- */
        /* ---------- (2) تحلیل پارامتر (درج شده در لایه مشترک؛ نسخه‌های محلی زیر برای چسب‌های فعال) ---------- */
        // $q, $sort, $dir, $tag_mode, $inv_op/r_op/b_op/b_sign, $per_page, $page بالاتر با الگوی isset خوانده شده‌اند.
        // (2b) WHERE مشترک: از refunded_query_parts() — پایه جمعیت + همه فیلترها با alias های c/ph و ساب‌کوئری آخرین عودت.

        /* ---------- (3) KPI — یک سطر به ازای هر مشتری، بدون fan-out ----------
         * v0.8.62.9 FIX: تگ‌ها/مبالغ با ساب‌کوئری‌های LAST-ID در SELECT می‌آیند تا هر مشتری
         * دقیقاً یک سطر باشد و COUNT(*) = COUNT(DISTINCT customer) و SUM ها دوبرابر نشوند.
         *  • همه عودت‌ها  = COUNT(DISTINCT مشتریِ دارای تگ قطعی OR اعلامی)
         *  • هر دو تگ     = منطق AND (هر دو تگ با هم — نه OR)
         *  • مانده منفی   = COUNT مشتریان با مانده < 0 + SUM مانده‌های منفی همان‌ها
         *  • مبالغ هر کارت از جمعیتِ همان کارت (CASE داخل SUM) محاسبه می‌شود.
         * همه کارت‌ها با یک کوئری تجمیعی SQL روی همان WHERE فیلترشده محاسبه می‌شوند. */
        $customers = Biawin_CRM_DB::table('customers');
        $tags_table = Biawin_CRM_DB::table('customer_tags');
        $phones = Biawin_CRM_DB::table('phones');
        $registers = Biawin_CRM_DB::table('refund_registers');
        [$where_sql, $args, $sort, $dir] = self::refunded_query_parts($service);
        $T = $service::TAG_CONFIRMED;
        $R = $service::TAG_REPORTED;
        $kpi_ctag = "(SELECT COUNT(*) FROM $tags_table cx WHERE cx.customer_id=c.id AND cx.tag IN ('$T','$R'))";
        [$kpi_last_refund, $kpi_used_refund, $balance_sql] = self::refunded_amount_expressions();
        $kpi_select = "SELECT COUNT(*) total,
                COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM $tags_table fx WHERE fx.customer_id=c.id AND fx.tag='$T') THEN 1 ELSE 0 END),0) confirmed,
                COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM $tags_table fx WHERE fx.customer_id=c.id AND fx.tag='$R') THEN 1 ELSE 0 END),0) reported,
                COALESCE(SUM(CASE WHEN $kpi_ctag = 2 THEN 1 ELSE 0 END),0) both_tags,
                COALESCE(SUM(CASE WHEN $balance_sql < 0 THEN 1 ELSE 0 END),0) negative,
                COALESCE(SUM(CASE WHEN $balance_sql < 0 THEN $balance_sql ELSE 0 END),0) negative_amount,
                COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM $tags_table fx WHERE fx.customer_id=c.id AND fx.tag='$T') THEN COALESCE($kpi_last_refund,0) ELSE 0 END),0) confirmed_amount,
                COALESCE(SUM(CASE WHEN $kpi_ctag = 2 THEN COALESCE($kpi_last_refund,0) ELSE 0 END),0) both_amount,
                COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM $tags_table fx WHERE fx.customer_id=c.id AND fx.tag IN ('$T','$R')) THEN COALESCE($kpi_last_refund,0) ELSE 0 END),0) total_amount,
                COALESCE(SUM($balance_sql),0) sum_balance,
                COALESCE(SUM($kpi_used_refund),0) sum_used,
                COALESCE(SUM(c.total_paid),0) sum_invoices,
                COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM $tags_table fx WHERE fx.customer_id=c.id AND fx.tag='$T') THEN COALESCE($kpi_last_refund,0) ELSE 0 END),0) sum_refunds";
        $kpi_from = "$customers c"; // بدون JOIN تگ/عودت در سطح ردیف — همه با ساب‌کوئری LAST-ID (تک‌سطر)
        $kpi_where_sql = $where_sql;
        // فیلتر موبایل به JOIN تلفن نیاز دارد؛ آن را فقط وقتی لازم است اضافه کن.
        $kpi_from .= " LEFT JOIN $phones ph ON ph.id=(SELECT MAX(px.id) FROM $phones px WHERE px.customer_id=c.id ORDER BY px.is_primary DESC, px.id DESC)";
        $kpi_row = $args
            ? $wpdb->get_row($wpdb->prepare("$kpi_select FROM $kpi_from WHERE $kpi_where_sql", ...$args))
            : $wpdb->get_row("$kpi_select FROM $kpi_from WHERE $kpi_where_sql");
        $kpis = [
            'total' => $kpi_row ? (int)$kpi_row->total : 0,
            'confirmed' => $kpi_row ? (int)$kpi_row->confirmed : 0,
            'reported' => $kpi_row ? (int)$kpi_row->reported : 0,
            'both' => $kpi_row ? (int)$kpi_row->both_tags : 0,
            'negative' => $kpi_row ? (int)$kpi_row->negative : 0,
            'negative_amount' => $kpi_row ? (float)$kpi_row->negative_amount : 0.0,
            'confirmed_amount' => $kpi_row ? (float)$kpi_row->confirmed_amount : 0.0,
            'both_amount' => $kpi_row ? (float)$kpi_row->both_amount : 0.0,
            'total_amount' => $kpi_row ? (float)$kpi_row->total_amount : 0.0,
            'reported_amount' => 0.0, // عودت اعلامی کارشناس مبلغ قطعی ندارد — اختراع نمی‌شود
            'sum_balance' => $kpi_row ? (float)$kpi_row->sum_balance : 0.0,
            'sum_used' => $kpi_row ? (float)$kpi_row->sum_used : 0.0,
            'sum_invoices' => $kpi_row ? (float)$kpi_row->sum_invoices : 0.0,
            'sum_refunds' => $kpi_row ? (float)$kpi_row->sum_refunds : 0.0,
        ];

        /* ---------- (4) کوئری صفحه — از لایه مشترک (یک سطر به ازای هر مشتری) ----------
         * تگ نمایش‌داده‌شده = قطعی اگر هر دو تگ موجود باشد (اولویت قطعی)؛ بدون ردیف تکراری. */
        $rows = self::refunded_fetch_rows([$where_sql, $args], $sort, $dir, $per_page, ($page - 1) * $per_page);
        $total_rows = $rows ? (int)$wpdb->get_var('SELECT FOUND_ROWS()') : 0;
        $pages = max(1, (int)ceil($total_rows / $per_page));
        $page = min($pages, $page);

        // ترمیم خودکار تگ‌های گمشده (فقط افزودن تگ؛ هرگز حذف نمی‌کند) + re-query همان صفحه.
        if ($rows) {
            $needs_heal = 0;
            foreach ($rows as $r) {
                if (($r->refund_tag !== 'REFUND_CONFIRMED') && (float)$r->refund_amount > 0) $needs_heal++;
            }
            if ($needs_heal > 0) {
                $service::refresh_tag_rows($rows);
                $rows = self::refunded_fetch_rows([$where_sql, $args], $sort, $dir, $per_page, ($page - 1) * $per_page);
            }
        }

        /* ---------- (5) لینک/وضعیت کارت‌های KPI + چیپ فیلترهای فعال ---------- */
        $reset_params = ['r_q', 'r_sort', 'r_dir', 'tag_mode', 'refund_tag', 'inv_op', 'inv_a', 'inv_b', 'r_op', 'r_a', 'r_b', 'b_op', 'b_a', 'b_b', 'b_sign', 'r_p', 'r_pp', 'bcrm_customer'];
        $FILTER_PARAMS = ['r_q', 'r_sort', 'r_dir', 'tag_mode', 'refund_tag', 'inv_op', 'inv_a', 'inv_b', 'r_op', 'r_a', 'r_b', 'b_op', 'b_a', 'b_b', 'b_sign', 'u_sign'];
        $clear_all_url = remove_query_arg($reset_params);
        $kpi_links = [
            'total' => remove_query_arg(['tag_mode', 'refund_tag', 'r_p']),
            'confirmed' => add_query_arg(['tag_mode' => $service::TAG_MODE_CONFIRMED, 'r_p' => 1]),
            'reported' => add_query_arg(['tag_mode' => $service::TAG_MODE_REPORTED, 'r_p' => 1]),
            'both' => add_query_arg(['tag_mode' => $service::TAG_MODE_BOTH, 'r_p' => 1]),
            'negative' => add_query_arg(['b_sign' => 'neg', 'r_p' => 1]),
        ];
        $kpi_active = [
            'total' => ($tag_mode === ''),
            'confirmed' => ($tag_mode === $service::TAG_MODE_CONFIRMED),
            'reported' => ($tag_mode === $service::TAG_MODE_REPORTED),
            'both' => ($tag_mode === $service::TAG_MODE_BOTH),
            'negative' => ($b_sign === 'neg'),
        ];
        $kpi_hints = [
            'total' => 'تعداد مشتریانِ واقعاً دارای عودت (تگ قطعی یا اعلامی، یا رکورد عودت قطعی اکسل) — مطابق فیلترهای فعال. مبلغ = جمع آخرین عودت قطعی همین جمعیت.',
            'confirmed' => 'تعداد مشتریانی که برای آن‌ها عودت قطعی از طریق Excel ثبت شده است. مبلغ = جمع آخرین مبلغ عودت قطعی اکسل همین مشتریان.',
            'reported' => 'تعداد مشتریانی که کارشناس در نتیجه تماس، عودت را اعلام کرده است. مبلغ قطعی ثبت نمی‌شود و صفر نمایش داده می‌شود.',
            'both' => 'تعداد مشتریانی که هم‌زمان هر دو تگ عودت قطعی (اکسل) و اعلامی (کارشناس) را دارند. مبلغ = جمع آخرین عودت قطعی همین مشتریان.',
            'negative' => 'تعداد مشتریانی که برآیند عودت قطعی و «استفاده شده» از مجموع فاکتورهایشان بیشتر است (مانده = مجموع فاکتورها − آخرین عودت قطعی اکسل − آخرین «استفاده شده»). مبلغ = جمع مانده‌های منفی همین مشتریان.',
            'sum_balance' => 'جمع مانده (مجموع فاکتورها − آخرین عودت قطعی اکسل − آخرین «استفاده شده») برای همه مشتریانِ مطابق فیلترهای فعال.',
            'sum_invoices' => 'جمع مبالغ فاکتور مشتریانِ مطابق فیلترهای فعال.',
            'sum_refunds' => 'جمع آخرین مبالغ عودت قطعی (اکسل) مشتریانِ مطابق فیلترهای فعال.',
            'sum_used' => 'جمع آخرین مبلغ «استفاده شده» (از اکسل استفاده‌شده) مشتریانِ مطابق فیلترهای فعال — در مانده هم لحاظ می‌شود.',
        ];
        $kpi_kinds = ['total' => '', 'confirmed' => 'is-danger', 'reported' => 'is-warning', 'both' => 'is-info', 'negative' => 'is-danger', 'sum_balance' => 'is-info', 'sum_invoices' => '', 'sum_refunds' => '', 'sum_used' => ''];
        $active_filters = [];
        if ($q !== '') $active_filters[] = 'جست‌وجو: ' . $q;
        $tag_mode_labels = [$service::TAG_MODE_CONFIRMED => 'قطعی (اکسل)', $service::TAG_MODE_REPORTED => 'اعلامی (کارشناس)', $service::TAG_MODE_BOTH => 'هر دو تگ', $service::TAG_MODE_NONE => 'بدون تگ'];
        if ($tag_mode !== '') $active_filters[] = 'تگ: ' . $tag_mode_labels[$tag_mode];
        $op_labels = ['eq' => 'برابر', 'gt' => 'بزرگ‌تر از', 'gte' => 'بزرگ‌تر یا مساوی', 'lt' => 'کوچک‌تر از', 'lte' => 'کوچک‌تر یا مساوی', 'between' => 'بین'];
        $fmt_money = static function (float $v): string { return self::money($v); };
        if ($inv_op !== '') $active_filters[] = 'فاکتور ' . $op_labels[$inv_op] . ' ' . $fmt_money($inv_a) . ($inv_op === 'between' ? ' و ' . $fmt_money((float)$inv_b) : '');
        if ($r_op !== '') $active_filters[] = 'عودت ' . $op_labels[$r_op] . ' ' . $fmt_money($r_a) . ($r_op === 'between' ? ' و ' . $fmt_money((float)$r_b) : '');
        if ($b_op !== '') $active_filters[] = 'مانده ' . $op_labels[$b_op] . ' ' . $fmt_money($b_a) . ($b_op === 'between' ? ' و ' . $fmt_money((float)$b_b) : '');
        $sign_labels = ['neg' => 'منفی', 'pos' => 'مثبت', 'zero' => 'صفر'];
        if ($b_sign !== '') $active_filters[] = 'مانده ' . $sign_labels[$b_sign];
        if ($u_sign !== '') $active_filters[] = $u_sign === 'yes' ? 'دارای «استفاده شده»' : 'بدون «استفاده شده»';
        $filter_count = ($q !== '' ? 1 : 0) + ($tag_mode !== '' ? 1 : 0) + ($inv_op !== '' ? 1 : 0) + ($r_op !== '' ? 1 : 0) + ($b_op !== '' ? 1 : 0) + ($b_sign !== '' ? 1 : 0) + ($u_sign !== '' ? 1 : 0);

        ob_start();
        echo '<div class="bcrm-portal bcrm-app bcrm-rf-page" dir="rtl">';
        self::app_topbar('عودت‌ها', 'مدیریت، بررسی و فیلتر مشتریان دارای عودت', remove_query_arg($reset_params));
        echo '<div class="bcrm-rf-header"><span class="bcrm-eyebrow">مدیریت عودت‌ها</span><h2>عودت‌ها</h2><p>مدیریت، بررسی و فیلتر مشتریان دارای عودت</p></div>';
        // KPI cards — clickable shortcuts (toggle via URL params) + count + amount + ؟ tooltip.
        echo '<div class="bcrm-rf-kpis">';
        $kpi_cards = [
            'total' => ['همه عودت‌ها', number_format_i18n($kpis['total']), self::money($kpis['total_amount'])],
            'confirmed' => ['قطعی (اکسل)', number_format_i18n($kpis['confirmed']), self::money($kpis['confirmed_amount'])],
            'reported' => ['اعلامی (کارشناس)', number_format_i18n($kpis['reported']), ''],
            'both' => ['هر دو تگ', number_format_i18n($kpis['both']), self::money($kpis['both_amount'])],
            'negative' => ['مانده منفی', number_format_i18n($kpis['negative']), self::money($kpis['negative_amount'])],
            'sum_balance' => ['جمع مانده', '', self::money($kpis['sum_balance'])],
            'sum_invoices' => ['جمع فاکتورها', '', self::money($kpis['sum_invoices'])],
            'sum_refunds' => ['جمع عودت قطعی', '', self::money($kpis['sum_refunds'])],
            'sum_used' => ['جمع استفاده‌شده', '', self::money($kpis['sum_used'])],
        ];
        foreach ($kpi_cards as $key => [$label, $count, $amount]) {
            $hint = $kpi_hints[$key] ?? '';
            $kind = $kpi_kinds[$key] ?? '';
            $active = !empty($kpi_active[$key]);
            $open_tag = isset($kpi_links[$key])
                ? '<a class="bcrm-rf-kpi' . ($kind !== '' ? ' ' . $kind : '') . ($active ? ' is-active' : '') . '" href="' . esc_url($kpi_links[$key]) . '">'
                : '<div class="bcrm-rf-kpi' . ($kind !== '' ? ' ' . $kind : '') . '">';
            $close_tag = isset($kpi_links[$key]) ? '</a>' : '</div>';
            echo $open_tag . '<span>' . esc_html($label) . '<i class="bcrm-rf-qmark" tabindex="0" role="button" title="' . esc_attr($hint) . '">؟</i></span>';
            if ($count !== '') echo '<strong>' . $count . '</strong>';
            if ($amount !== '') echo '<em class="bcrm-rf-amount">' . esc_html($amount) . ' تومان</em>';
            echo $close_tag;
        }
        echo '</div>';
        echo '<div class="bcrm-panel">';
        // Toolbar: global search + active-filter chips + count + clear-all.
        echo '<div class="bcrm-rf-toolbar">';
        echo '<form method="get" class="bcrm-rf-search">';
        echo '<input type="hidden" name="' . esc_attr('bcrm_view') . '" value="refunded">';
        foreach ($FILTER_PARAMS as $keep) {
            if ($keep === 'r_q') continue;
            $v = isset($_GET[$keep]) && is_string($_GET[$keep]) ? (string)$_GET[$keep] : '';
            if ($v !== '') echo '<input type="hidden" name="' . esc_attr($keep) . '" value="' . esc_attr($v) . '">';
        }
        echo '<input type="search" name="r_q" value="' . esc_attr($q) . '" placeholder="جست‌وجو: نام، CustomerID، موبایل یا مبلغ…"><button class="bcrm-primary-button is-small" type="submit">جست‌وجو</button>';
        echo '</form>';
        echo '<div class="bcrm-rf-toolbar-info">';
        if ($filter_count > 0) {
            echo '<span class="bcrm-rf-count" title="' . esc_attr(implode(' • ', $active_filters)) . '"><b>فیلتر فعال: ' . number_format_i18n($filter_count) . '</b> — ' . esc_html(implode(' • ', $active_filters)) . '</span>';
            echo '<a class="bcrm-rf-clearall" href="' . esc_url($clear_all_url) . '">پاک کردن همه فیلترها ✕</a>';
        }
        $export_url = add_query_arg(['bcrm_rf_export' => '1', '_wpnonce' => wp_create_nonce('bcrm_rf_export')]);
        echo '<a class="bcrm-rf-export" href="' . esc_url($export_url) . '" title="دانلود XLSX نتایج فیلترشده فعلی">⤓ خروجی اکسل</a>';
        echo '<span class="bcrm-rf-total">' . number_format_i18n($total_rows) . ' مشتری</span>';
        echo '</div></div>';
        echo '<div class="bcrm-table-wrap"><table class="bcrm-table bcrm-rf-table"><thead><tr>';
        // Excel-style headers: عنوان قابل sort (۳ حالته) + دکمه فیلتر مستقل هر ستون.
        $cols = [
            'name' => ['مشتری', 'text'],
            'code' => ['CustomerID', 'text'],
            'phone' => ['موبایل', 'text'],
            'invoices' => ['مجموع فاکتورها', 'number'],
            'refund' => ['مبلغ عودت', 'number'],
            'used' => ['استفاده شده', 'number'],
            'balance' => ['مانده', 'number'],
            'tags' => ['تگ‌ها', 'categorical'],
        ];
        foreach ($cols as $key => [$label, $kind]) {
            $is_sorted = ($sort === $key && $dir !== '');
            $next_dir = !$is_sorted ? 'asc' : ($dir === 'asc' ? 'desc' : '');
            $sort_href = add_query_arg(['r_sort' => $key, 'r_dir' => $next_dir, 'r_p' => 1]);
            $arrow = $is_sorted ? ($dir === 'asc' ? '↑' : '↓') : '';
            $col_state = '';
            if ($key === 'tags') $col_state = $tag_mode;
            if ($key === 'balance' && ($b_op !== '' || $b_sign !== '')) $col_state = 'set';
            if ($key === 'invoices' && $inv_op !== '') $col_state = 'set';
            if ($key === 'refund' && $r_op !== '') $col_state = 'set';
            if ($key === 'used' && $u_sign !== '') $col_state = 'set';
            echo '<th class="bcrm-rf-col bcrm-rf-col-' . esc_attr($kind) . ($is_sorted ? ' is-sorted' : '') . ($col_state !== '' ? ' is-filtered' : '') . '">';
            echo '<a class="bcrm-rf-sort" href="' . esc_url($sort_href) . '"><span>' . esc_html($label) . '</span>' . ($arrow !== '' ? '<i class="bcrm-rf-arrow">' . $arrow . '</i>' : '') . '</a>';
            echo '<button type="button" class="bcrm-rf-fbtn' . ($col_state !== '' ? ' is-set' : '') . '" data-col="' . esc_attr($key) . '" data-kind="' . esc_attr($kind) . '" aria-haspopup="true" aria-expanded="false" title="فیلتر ' . esc_attr($label) . '">▾</button>';
            echo '</th>';
        }
        echo '</tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="8">مشتری‌ای مطابق فیلترهای فعال پیدا نشد. <a href="' . esc_url($clear_all_url) . '">پاک کردن همه فیلترها</a></td></tr>';
        foreach ($rows as $r) {
            $badges = '';
            if ($r->refund_tag === 'REFUND_CONFIRMED') $badges .= '<span class="bcrm-badge is-danger">قطعی</span> ';
            if ($r->refund_tag === 'REFUND_REPORTED') $badges .= '<span class="bcrm-badge is-warning">اعلامی</span>';
            echo '<tr>'
                . '<td><a href="' . esc_url(add_query_arg('bcrm_customer', (int)$r->id, Biawin_CRM_Auth::portal_url())) . '">' . esc_html($r->display_name ?: 'پروفایل بدون نام') . '</a></td>'
                . '<td>' . esc_html($r->source_customer_id ?: '-') . '</td>'
                . '<td class="bcrm-ltr-cell">' . esc_html($r->phone ?: '-') . '</td>'
                . '<td>' . esc_html(self::money($r->total_paid)) . '</td>'
                . '<td>' . ((float)$r->refund_amount > 0 ? esc_html(self::money((float)$r->refund_amount)) : '<span class="bcrm-muted">—</span>') . '</td>'
                . '<td>' . ((float)($r->used_amount ?? 0) > 0 ? esc_html(self::money((float)($r->used_amount ?? 0))) : '<span class="bcrm-muted">—</span>') . '</td>'
                . '<td style="' . ((float)$r->balance < 0 ? 'color:#b91c1c;font-weight:800' : '') . '">' . esc_html(self::money($r->balance)) . '</td>'
                . '<td>' . ($badges !== '' ? $badges : '<span class="bcrm-muted">—</span>') . '</td>'
                . '</tr>';
        }
        echo '</tbody></table></div>';
        // Pagination bar.
        if ($pages > 1) {
            $mk = static function ($p) { return add_query_arg(['r_p' => $p]); };
            $range = [];
            for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) $range[] = $p;
            if ($range[0] > 1) array_unshift($range, 1, '…');
            if ($range[count($range) - 1] < $pages) array_push($range, '…', $pages);
            echo '<div class="bcrm-rf-pagination">';
            echo '<span class="bcrm-rf-pageinfo">' . sprintf('نمایش %s تا %s از %s مشتری', number_format_i18n(($page - 1) * $per_page + 1), number_format_i18n(min($total_rows, $page * $per_page)), number_format_i18n($total_rows)) . '</span>';
            echo '<nav class="bcrm-rf-pages">';
            if ($page > 1) echo '<a href="' . esc_url($mk($page - 1)) . '">‹ قبلی</a>';
            foreach ($range as $p) {
                if ($p === '…') { echo '<span class="bcrm-rf-gap">…</span>'; continue; }
                echo '<a href="' . esc_url($mk((int)$p)) . '" class="' . ((int)$p === $page ? 'is-current' : '') . '">' . number_format_i18n((int)$p) . '</a>';
            }
            if ($page < $pages) echo '<a href="' . esc_url($mk($page + 1)) . '">بعدی ›</a>';
            echo '</nav>';
            // Per-page selector (25/50/100) — همه فیلترها در صفحه‌بندی حفظ می‌شوند.
            echo '<form method="get" class="bcrm-rf-pp">';
            foreach ($FILTER_PARAMS as $keep) {
                $v = isset($_GET[$keep]) && is_string($_GET[$keep]) ? (string)$_GET[$keep] : '';
                if ($v !== '') echo '<input type="hidden" name="' . esc_attr($keep) . '" value="' . esc_attr($v) . '">';
            }
            echo '<input type="hidden" name="' . esc_attr('bcrm_view') . '" value="refunded"><select name="r_pp" onchange="this.form.submit()">';
            foreach ([25, 50, 100] as $n) echo '<option value="' . $n . '"' . ($per_page === $n ? ' selected' : '') . '>' . number_format_i18n($n) . ' در صفحه</option>';
            echo '</select></form></div>';
        }
        echo '</div></div>';
        return (string)ob_get_clean();
    }

    /**
     * v0.8.62.9: لایه مرجع مشترک لیست عودت‌ها — تحلیل پارامتر + ساخت WHERE + ساخت سطر.
     * لیست صفحه، KPI و خروجی اکسل همگی از همین متدها استفاده می‌کنند تا جمعیت همیشه یکی باشد.
     * WHERE از alias مشتری c، تلفن ph و expression مشترک آخرین عودت استفاده می‌کند؛
     * هیچ JOIN تکراری لازم نیست و هر مشتری دقیقاً یک سطر دارد.
     */
    private static function refunded_amount_expressions(): array
    {
        $registers = Biawin_CRM_DB::table('refund_registers');
        // v0.8.62.13: عودت قطعی = جمع «آخرین رکورد هر کد فاکتور» (هر کد فاکتور یک گروه مستقل؛
        // آپلود مجدد همان کد = جایگزینی همان گروه، هرگز SUM تکراری). رکوردهای قدیمی بدون کد
        // فاکتور (invoice_code='') یک گروه مشترک برای کل مشتری می‌سازند (سازگار با داده قدیم).
        $last_refund = "(SELECT COALESCE(SUM(rr.refund_amount),0) FROM $registers rr WHERE rr.customer_id=c.id AND rr.source_type='excel' AND rr.id IN (SELECT MAX(rm.id) FROM $registers rm WHERE rm.customer_id=c.id AND rm.source_type='excel' GROUP BY rm.invoice_code))";
        // «استفاده شده» = آخرین رکورد (Last-Record) — طبق تصمیم فعلی تغییری نمی‌کند.
        $used_refund = "(SELECT uu.refund_amount FROM $registers uu WHERE uu.customer_id=c.id AND uu.source_type='used' ORDER BY uu.id DESC LIMIT 1)";
        // مانده = مجموع فاکتورها − جمع عودت قطعی هر کد فاکتور − آخرین «استفاده شده».
        return [$last_refund, $used_refund, "(c.total_paid - COALESCE($last_refund,0) - COALESCE($used_refund,0))"];
    }

    private static function refunded_query_parts(string $service): array
    {
        global $wpdb;
        $q = isset($_GET['r_q']) && is_string($_GET['r_q']) ? trim(wp_unslash($_GET['r_q'])) : '';
        $q = trim((string)preg_replace('/[\x{200c}]+/u', ' ', $q));
        $q = trim((string)preg_replace('/\s+/u', ' ', $q));
        if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);
        $sort = in_array((string)($_GET['r_sort'] ?? ''), ['name', 'code', 'phone', 'invoices', 'refund', 'balance', 'tags'], true) ? (string)$_GET['r_sort'] : '';
        $dir_raw = strtolower((string)($_GET['r_dir'] ?? ''));
        $dir = $dir_raw === 'asc' ? 'asc' : ($dir_raw === 'desc' ? 'desc' : '');
        $tag_mode = $service::sanitize_tag_mode(isset($_GET['tag_mode']) && is_string($_GET['tag_mode']) ? wp_unslash($_GET['tag_mode']) : '');
        $legacy_tag = $service::sanitize_tag_filter(isset($_GET['refund_tag']) && is_string($_GET['refund_tag']) ? wp_unslash($_GET['refund_tag']) : 'any');
        if ($tag_mode === '' && $legacy_tag !== 'any') {
            $legacy_map = ['REFUND_CONFIRMED' => $service::TAG_MODE_CONFIRMED, 'REFUND_REPORTED' => $service::TAG_MODE_REPORTED, 'BOTH' => $service::TAG_MODE_BOTH];
            $tag_mode = $legacy_map[$legacy_tag] ?? '';
        }
        $num = static function (string $key): ?float {
            if (!isset($_GET[$key]) || !is_string($_GET[$key])) return null;
            $raw = str_replace([',', '٬', '،', ' '], '', wp_unslash($_GET[$key]));
            $raw = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $raw);
            $raw = trim($raw);
            if ($raw === '' || !is_numeric($raw)) return null;
            return (float)$raw;
        };
        $inv_op = (string)($_GET['inv_op'] ?? '');
        $inv_a = $num('inv_a');
        $inv_b = $num('inv_b');
        if (!in_array($inv_op, ['eq', 'gt', 'gte', 'lt', 'lte', 'between'], true) || $inv_a === null || ($inv_op === 'between' && $inv_b === null)) $inv_op = '';
        if ($inv_op !== '' && $inv_op !== 'between') $inv_b = null;
        $r_op = (string)($_GET['r_op'] ?? '');
        $r_a = $num('r_a');
        $r_b = $num('r_b');
        if (!in_array($r_op, ['eq', 'gt', 'gte', 'lt', 'lte', 'between'], true) || $r_a === null || ($r_op === 'between' && $r_b === null)) $r_op = '';
        if ($r_op !== '' && $r_op !== 'between') $r_b = null;
        $b_op = (string)($_GET['b_op'] ?? '');
        $b_a = $num('b_a');
        $b_b = $num('b_b');
        if (!in_array($b_op, ['eq', 'gt', 'lt', 'between'], true) || $b_a === null || ($b_op === 'between' && $b_b === null)) $b_op = '';
        if ($b_op !== '' && $b_op !== 'between') $b_b = null;
        $b_sign = (string)($_GET['b_sign'] ?? '');
        if (!in_array($b_sign, ['neg', 'pos', 'zero'], true)) $b_sign = '';

        $customers = Biawin_CRM_DB::table('customers');
        $tags_table = Biawin_CRM_DB::table('customer_tags');
        $phones = Biawin_CRM_DB::table('phones');
        $registers = Biawin_CRM_DB::table('refund_registers');
        // پایه جمعیت: فقط مشتریان واقعاً دارای عودت (تگ قطعی/اعلامی یا رکورد عودت قطعی اکسل).
        $Tc = $service::TAG_CONFIRMED;
        $Tr = $service::TAG_REPORTED;
        $where = [
            "(EXISTS (SELECT 1 FROM $tags_table bx WHERE bx.customer_id=c.id AND bx.tag IN ('$Tc','$Tr'))"
            . " OR EXISTS (SELECT 1 FROM $registers br WHERE br.customer_id=c.id AND br.source_type='excel'))",
        ];
        [$last_refund_sql, , $balance_sql] = self::refunded_amount_expressions();
        $args = [];
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(c.display_name LIKE %s OR c.source_customer_id LIKE %s OR c.customer_code LIKE %s OR ph.normalized_phone LIKE %s'
                . (is_numeric($q) ? " OR c.total_paid LIKE %s OR $last_refund_sql LIKE %s" : '') . ')';
            array_push($args, $like, $like, $like, $like);
            if (is_numeric($q)) array_push($args, $like, $like);
        }
        // Correlated predicates preserve one customer row without a tag JOIN.
        $has_confirmed = "EXISTS (SELECT 1 FROM $tags_table ft WHERE ft.customer_id=c.id AND ft.tag='$Tc')";
        $has_reported = "EXISTS (SELECT 1 FROM $tags_table ft WHERE ft.customer_id=c.id AND ft.tag='$Tr')";
        $tag_predicates = [
            $service::TAG_MODE_CONFIRMED => $has_confirmed,
            $service::TAG_MODE_REPORTED => $has_reported,
            $service::TAG_MODE_BOTH => "($has_confirmed AND $has_reported)",
            $service::TAG_MODE_NONE => "(NOT $has_confirmed AND NOT $has_reported)",
        ];
        if (isset($tag_predicates[$tag_mode])) $where[] = $tag_predicates[$tag_mode];
        $op_sql = [
            'eq' => ['= %f', 1], 'gt' => ['> %f', 1], 'gte' => ['>= %f', 1],
            'lt' => ['< %f', 1], 'lte' => ['<= %f', 1], 'between' => ['BETWEEN %f AND %f', 2],
        ];
        if ($inv_op !== '') {
            $where[] = 'c.total_paid ' . $op_sql[$inv_op][0];
            $args[] = $inv_a;
            if ($op_sql[$inv_op][1] === 2) $args[] = $inv_b;
        }
        if ($r_op !== '') {
            $where[] = $last_refund_sql . ' ' . $op_sql[$r_op][0];
            $args[] = $r_a;
            if ($op_sql[$r_op][1] === 2) $args[] = $r_b;
        }
        if ($b_op !== '') {
            $where[] = $balance_sql . ' ' . $op_sql[$b_op][0];
            $args[] = $b_a;
            if ($op_sql[$b_op][1] === 2) $args[] = $b_b;
        }
        // v0.8.62.11: منفی/مثبت/صفر دقیقاً مطابق فرمول مانده (مجموع فاکتورها − عودت قطعی − استفاده شده) — بدون استثنا.
        if ($b_sign === 'neg') $where[] = "$balance_sql < 0";
        if ($b_sign === 'pos') $where[] = "$balance_sql >= 0";
        if ($b_sign === 'zero') $where[] = "$balance_sql = 0";
        // v0.8.62.11: فیلتر ستون «استفاده شده» — has / has-not (ساب‌کوئری EXISTS روی alias c).
        $u_sign = (string)($_GET['u_sign'] ?? '');
        if (!in_array($u_sign, ['yes', 'no'], true)) $u_sign = '';
        if ($u_sign === 'yes') $where[] = "EXISTS (SELECT 1 FROM $registers ru WHERE ru.customer_id=c.id AND ru.source_type='used')";
        if ($u_sign === 'no') $where[] = "NOT EXISTS (SELECT 1 FROM $registers ru WHERE ru.customer_id=c.id AND ru.source_type='used')";
        return [implode(' AND ', $where), $args, $sort, $dir];
    }

    /** v0.8.62.9: سطرهای لیست (یک سطر به ازای هر مشتری) با alias های c/ph و ساب‌کوئری آخرین عودت — بدون fan-out. */
    private static function refunded_fetch_rows(array $where_args, string $sort, string $dir, int $limit = 25, int $offset = 0): array
    {
        global $wpdb;
        [$where_sql, $args] = $where_args;
        $customers = Biawin_CRM_DB::table('customers');
        $tags_table = Biawin_CRM_DB::table('customer_tags');
        $phones = Biawin_CRM_DB::table('phones');
        $registers = Biawin_CRM_DB::table('refund_registers');
        $row_tag = "(SELECT tx.tag FROM $tags_table tx WHERE tx.customer_id=c.id AND tx.tag IN ('" . Biawin_CRM_Refund_Register::TAG_CONFIRMED . "','" . Biawin_CRM_Refund_Register::TAG_REPORTED . "') ORDER BY tx.tag LIMIT 1)";
        [$row_last_refund, $row_used_refund, $balance_sql] = self::refunded_amount_expressions();
        $order_map = [
            'name' => 'c.display_name', 'code' => 'c.source_customer_id', 'phone' => 'ph.normalized_phone',
            'invoices' => 'c.total_paid', 'refund' => 'refund_amount', 'balance' => 'balance_calc', 'tags' => 'refund_tag',
        ];
        $order_by = ($sort !== '' && $dir !== '' && isset($order_map[$sort])) ? ($order_map[$sort] . ' ' . strtoupper($dir) . ', c.id ASC') : 'c.id ASC';
        // SQL_CALC_FOUND_ROWS + LIMIT/OFFSET: دیتابیس فقط صفحه درخواستی را برمی‌گرداند؛ جمع کل با FOUND_ROWS.
        $sql = "SELECT SQL_CALC_FOUND_ROWS c.id, c.display_name, c.source_customer_id, c.customer_code, c.total_paid,
                ph.normalized_phone phone, $row_tag refund_tag, $row_last_refund refund_amount, $row_used_refund used_amount,
                $balance_sql AS balance_calc, $balance_sql balance
             FROM $customers c
             LEFT JOIN $phones ph ON ph.id=(SELECT MAX(px.id) FROM $phones px WHERE px.customer_id=c.id ORDER BY px.is_primary DESC, px.id DESC)
             WHERE $where_sql
             ORDER BY $order_by
             LIMIT %d OFFSET %d";
        return (array)$wpdb->get_results($wpdb->prepare($sql, array_merge($args, [$limit, $offset])));
    }

    /** v0.8.62.9 — خروجی اکسل (XLSX) نتایج فیلترشده «عودت‌ها».
     *  همان پارامترها/WHERE/ساخت سطر لیست (پایه جمعیت + فیلترها + sort)؛ بدون صفحه‌بندی، سقف 50,000.
     *  فقط وقتی کاربر قبلاً can_view('refunded') را گذرانده + nonce معتبر است فراخوانی می‌شود. */
    private static function export_refunded_excel(): void
    {
        check_ajax_referer('bcrm_rf_export', '_wpnonce');
        if (!current_user_can('bcrm_refund_register') && !current_user_can('bcrm_view_refunded')) {
            wp_die('دسترسی ندارید.');
        }
        global $wpdb;
        $service = 'Biawin_CRM_Refund_Register';
        $parts = self::refunded_query_parts($service);
        $rows = self::refunded_fetch_rows([$parts[0], $parts[1]], $parts[2], $parts[3], 50000, 0);
        $headers = ['مشتری', 'CustomerID', 'موبایل', 'مجموع فاکتورها', 'مبلغ عودت (آخرین قطعی)', 'استفاده شده', 'مانده', 'تگ‌ها'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (string)$r->display_name,
                (string)($r->source_customer_id ?: ''),
                (string)($r->phone ?: ''),
                (float)$r->total_paid,
                (float)$r->refund_amount,
                (float)($r->used_amount ?? 0),
                (float)$r->balance,
                $r->refund_tag === 'REFUND_CONFIRMED' ? 'قطعی' : ($r->refund_tag === 'REFUND_REPORTED' ? 'اعلامی' : ''),
            ];
        }
        $file_name = 'biawin-refunds-' . gmdate('Ymd-His') . '.xlsx';
        if (class_exists('ZipArchive')) {
            self::xlsx_write($file_name, $headers, $data);
        } else {
            // Fallback CSV (UTF-8 BOM تا اکسل فارسی را درست نشان دهد) — بدون وابستگی خارجی.
            $file_name = 'biawin-refunds-' . gmdate('Ymd-His') . '.csv';
            self::csv_write($file_name, $headers, $data);
        }
        exit;
    }

    /** v0.8.62.9: XLSX نویس مینیمال (inline strings) — بدون PhpSpreadsheet؛ همان موتور ZipArchive ایمپورت. */
    private static function xlsx_write(string $file_name, array $headers, array $data): void
    {
        $esc = static function ($v): string {
            return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        };
        $col_name = static function (int $i): string {
            $s = '';
            for (++$i; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
            return $s;
        };
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $row_xml = static function (int $rn, array $cells) use ($esc, $col_name): string {
            $x = '<row r="' . $rn . '">';
            foreach ($cells as $i => $v) {
                $ref = $col_name($i) . $rn;
                if (is_int($v) || is_float($v)) {
                    $x .= '<c r="' . $ref . '"><v>' . $esc($v) . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $esc($v) . '</t></is></c>';
                }
            }
            return $x . '</row>';
        };
        $sheet .= $row_xml(1, $headers);
        $rn = 1;
        foreach ($data as $row) $sheet .= $row_xml(++$rn, array_values($row));
        $sheet .= '</sheetData></worksheet>';
        $content_types = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $wb = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Refunds" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $wb_rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
        // v0.8.62.14: پاک‌سازی هر خروجی قبلی (المنتور/قالب) تا باینری XLSX تمیز دانلود شود.
        // v0.8.63.2: فایل اول کامل در پوشه موقت خصوصی ساخته می‌شود (نوشتن کنترل‌شده + پاک‌سازی تضمینی)؛
        // اگر ساخت ممکن نبود (دیسک پر/موقت در دسترس نیست) همان خروجی CSV بدون فایل موقت ارسال می‌شود.
        Biawin_CRM_File_Safety::quiet_output();
        Biawin_CRM_File_Safety::sweep_stale();
        $tmp_dir = Biawin_CRM_File_Safety::make_temp_dir('refund-xlsx');
        $xlsx = $tmp_dir !== '' ? Biawin_CRM_File_Safety::build_zip($tmp_dir, 'refunds.xlsx', [
            '[Content_Types].xml' => $content_types,
            '_rels/.rels' => $rels,
            'xl/workbook.xml' => $wb,
            'xl/_rels/workbook.xml.rels' => $wb_rels,
            'xl/worksheets/sheet1.xml' => $sheet,
        ]) : '';
        if ($xlsx === '') {
            if ($tmp_dir !== '') Biawin_CRM_File_Safety::remove($tmp_dir);
            self::csv_write(preg_replace('/\.xlsx$/', '.csv', $file_name), $headers, $data);
            return;
        }
        Biawin_CRM_File_Safety::send_file($xlsx, $file_name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        Biawin_CRM_File_Safety::remove($tmp_dir);
    }

    /** v0.8.62.9: CSV fallback با UTF-8 BOM. */
    private static function csv_write(string $file_name, array $headers, array $data): void
    {
        // v0.8.62.14: پاک‌سازی بافرهای خروجی قبلی قبل از استریم CSV.
        while (ob_get_level() > 0) { @ob_end_clean(); }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $file_name);
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($data as $row) fputcsv($out, array_map('strval', $row));
        fclose($out);
    }

    private static function render_portal_view(string $view): string
    {
        if ($view === 'dashboard') return self::render_front_dashboard();
        if ($view === 'customers') return self::render_case_list();
        if ($view === 'refunded') return self::render_refunded_list();
        $map = [
            'assignments'=>'assignments_page','import'=>'import_page','deficiency'=>'deficiency_page','financial'=>'financial_page',
            'complaints'=>'complaints_page','service-cases'=>'service_cases_page','services'=>'services_page',
            'service-units'=>'service_units_page','refunds'=>'refunds_page','reports'=>'reports_page',
            'data-audit'=>'data_audit_page',
            'users'=>'users_page','audit'=>'audit_page','settings'=>'settings_page',
        ];
        if (!isset($map[$view])) return self::render_case_list();
        ob_start();
        Biawin_CRM_Admin::{$map[$view]}();
        $html = (string)ob_get_clean();
        return self::translate_admin_html_to_portal($html);
    }

    private static function customer_access_scope(string $alias = 'c'): array
    {
        if (current_user_can('bcrm_manage_cases')) {
            return ['1=1', []];
        }
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        $assignments = Biawin_CRM_DB::table('assignments');
        // Legacy data may contain more than one active assignment. Treat the newest active row as the
        // canonical owner so a customer cannot appear in two ordinary agents' queues at the same time.
        $access = ["EXISTS (SELECT 1 FROM $assignments a WHERE a.id=(SELECT MAX(ax.id) FROM $assignments ax WHERE ax.customer_id={$alias}.id AND ax.status='assigned') AND a.agent_user_id=%d)"];
        $args = [$user_id];
        // Specialist roles keep access to the queues that belong to their role.
        if (current_user_can('bcrm_services')) {
            $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('service_cases') . " sc WHERE sc.customer_id={$alias}.id AND sc.assigned_user_id=%d AND sc.status NOT IN ('completed','rejected','cancelled'))";
            $args[] = $user_id;
        }
        $supervised_units = Biawin_CRM_Workflow_Service::supervised_unit_keys($user_id);
        if ($supervised_units) {
            $quoted = implode(',', array_fill(0, count($supervised_units), '%s'));
            $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('service_cases') . " scu WHERE scu.customer_id={$alias}.id AND scu.unit_key IN ($quoted) AND scu.status NOT IN ('completed','rejected','cancelled'))";
            foreach ($supervised_units as $unit_key) $args[] = $unit_key;
        }
        $roles = (array) wp_get_current_user()->roles;
        if (current_user_can('view_deficiency_cases')) {
            if (in_array('bcrm_deficiency_supervisor', $roles, true)) {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('deficiency_cases') . " dc WHERE dc.customer_id={$alias}.id AND dc.status NOT IN ('COMPLETED','ARCHIVED'))";
            } else {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('deficiency_cases') . " dc WHERE dc.customer_id={$alias}.id AND dc.status NOT IN ('COMPLETED','ARCHIVED') AND dc.deficiency_agent_id=%d)";
                $args[] = $user_id;
            }
        }
        if (current_user_can('view_finance_cases')) {
            if (in_array('bcrm_financial_supervisor', $roles, true)) {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('deficiency_cases') . " df WHERE df.customer_id={$alias}.id AND df.current_department='finance' AND df.status NOT IN ('COMPLETED','ARCHIVED'))";
            } else {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('deficiency_cases') . " df WHERE df.customer_id={$alias}.id AND df.current_department='finance' AND df.status NOT IN ('COMPLETED','ARCHIVED') AND df.finance_agent_id=%d)";
                $args[] = $user_id;
            }
        }
        if (current_user_can('bcrm_financial_verify')) {
            if (in_array('bcrm_financial_supervisor', $roles, true)) {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('financial_cases') . " fc WHERE fc.customer_id={$alias}.id AND fc.status NOT IN ('resolved','closed','cancelled'))";
            } else {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('financial_cases') . " fc WHERE fc.customer_id={$alias}.id AND fc.status NOT IN ('resolved','closed','cancelled') AND (fc.assigned_user_id IS NULL OR fc.assigned_user_id=%d))";
                $args[] = $user_id;
            }
        }
        if (current_user_can('bcrm_complaints')) {
            if (in_array('bcrm_complaint_supervisor', $roles, true)) {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('complaints') . " co WHERE co.customer_id={$alias}.id AND co.status='open')";
            } else {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('complaints') . " co WHERE co.customer_id={$alias}.id AND co.status='open' AND (co.owner_user_id IS NULL OR co.owner_user_id=%d))";
                $args[] = $user_id;
            }
        }
        if (current_user_can('bcrm_refunds')) {
            if (in_array('bcrm_refund_supervisor', $roles, true)) {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('refunds') . " r WHERE r.customer_id={$alias}.id AND r.status NOT IN ('closed','cancelled'))";
            } else {
                $access[] = "EXISTS (SELECT 1 FROM " . Biawin_CRM_DB::table('refunds') . " r WHERE r.customer_id={$alias}.id AND r.status NOT IN ('closed','cancelled') AND (r.assigned_user_id IS NULL OR r.assigned_user_id=%d))";
                $args[] = $user_id;
            }
        }
        $scope = '(' . implode(' OR ', $access) . ')';
        if (class_exists('Biawin_CRM_Production_Readiness') && Biawin_CRM_Production_Readiness::guard_enabled()) {
            $scope .= ' AND ' . Biawin_CRM_Production_Readiness::sql_not_quarantined($alias);
        }
        return [$scope, $args];
    }

    private static function scoped_scalar(string $sql, array $args = []): int
    {
        global $wpdb;
        return (int)($args ? $wpdb->get_var($wpdb->prepare($sql, $args)) : $wpdb->get_var($sql));
    }

    private static function render_front_dashboard(): string
    {
        global $wpdb;
        $c=Biawin_CRM_DB::table('customers'); $i=Biawin_CRM_DB::table('invoices');
        $sc=Biawin_CRM_DB::table('service_cases'); $co=Biawin_CRM_DB::table('complaints');
        $rf=Biawin_CRM_DB::table('refunds');
        [$scope_sql,$scope_args]=self::customer_access_scope('c');
        $is_manager=current_user_can('bcrm_manage_cases');
        $dashboard_scope = self::front_reporting_scope();
        $summary_sql="SELECT COUNT(*) total, SUM(c.profile_completeness='incomplete') incomplete, SUM(c.profile_completeness='complete') complete, SUM(c.journey_state='service_request') service_request FROM $c c WHERE $scope_sql";
        $summary=$scope_args ? $wpdb->get_row($wpdb->prepare($summary_sql,$scope_args)) : $wpdb->get_row($summary_sql);
        $data=[
            'total'=>(int)($summary->total??0),'incomplete'=>(int)($summary->incomplete??0),'complete'=>(int)($summary->complete??0),
            'service_request'=>(int)($summary->service_request??0),'unassigned'=>0,
            'complaints'=>self::scoped_scalar("SELECT COUNT(*) FROM $co co INNER JOIN $c c ON c.id=co.customer_id WHERE co.status='open' AND $scope_sql",$scope_args),
            'services'=>self::scoped_scalar("SELECT COUNT(*) FROM $sc sc INNER JOIN $c c ON c.id=sc.customer_id WHERE sc.status NOT IN ('completed','rejected','cancelled') AND $scope_sql",$scope_args),
            'refunds'=>self::scoped_scalar("SELECT COUNT(*) FROM $rf rf INNER JOIN $c c ON c.id=rf.customer_id WHERE rf.status NOT IN ('closed','cancelled') AND $scope_sql",$scope_args),
            'invoices'=>self::scoped_scalar("SELECT COUNT(*) FROM $i i INNER JOIN $c c ON c.id=i.customer_id WHERE $scope_sql",$scope_args),
        ];
        if ($is_manager) {
            $workload = Biawin_CRM_Reporting_Consumer_Adapter::management_workload_cards($dashboard_scope);
            if (!empty($workload['trusted']) && !empty($workload['data']['rows'])) {
                foreach ($workload['data']['rows'] as $row) {
                    if (($row['key'] ?? '') === 'unassigned_normal') $data['unassigned'] = (int)$row['value'];
                }
            } else {
                $data['unassigned'] = 'نامطمئن';
            }
        } else {
            $agent_summary = Biawin_CRM_Reporting_Consumer_Adapter::agent_dashboard_assignment_summary(get_current_user_id(), $dashboard_scope);
            if (!empty($agent_summary['trusted']) && is_array($agent_summary['data'])) {
                $data['total'] = (int)$agent_summary['data']['total'];
                $data['incomplete'] = (int)$agent_summary['data']['incomplete'];
                $data['complete'] = (int)$agent_summary['data']['complete'];
                $data['service_request'] = (int)$agent_summary['data']['service_request'];
            } else {
                $data['total'] = $data['incomplete'] = $data['complete'] = $data['service_request'] = 'نامطمئن';
            }
        }
        $metrics=[
            [$is_manager?'کل پروفایل‌ها':'پرونده‌های من',$data['total'],'users'],
            ['پروفایل ناقص',$data['incomplete'],'alert'],
            [$is_manager?'تخصیص‌نیافته':'پروفایل کامل',$is_manager?$data['unassigned']:$data['complete'],$is_manager?'service':'check'],
            ['نارضایتی باز',$data['complaints'],'complaint'],
            ['خدمات باز',$data['services'],'service'],
            ['عودت فعال',$data['refunds'],'refund'],
        ];
        ob_start();
        echo '<div class="bcrm-portal bcrm-app bcrm-dashboard-page" dir="rtl"><section class="bcrm-dashboard-hero"><div><span>مرکز عملیات CRM</span><h1>'.($is_manager?'داشبورد مدیریتی':'داشبورد من').'</h1><p>'.($is_manager?'نمای زنده از وضعیت مشتریان، صف‌های کاری و عملیات تیم.':'فقط پرونده‌ها و فعالیت‌هایی که به شما تخصیص داده شده‌اند.').'</p></div>';
        if($is_manager) echo '<a class="bcrm-primary-button" href="'.esc_url(Biawin_CRM_Auth::portal_url(['bcrm_view'=>'assignments'])).'">مدیریت تخصیص‌ها</a>';
        echo '</section><section class="bcrm-dashboard-grid">';
        foreach($metrics as $m) self::metric_card($m[0],$m[1],$m[2]);
        echo '</section><section class="bcrm-dashboard-columns"><div class="bcrm-panel"><div class="bcrm-panel-title"><div><h3>دسترسی سریع</h3><p>'.($is_manager?'عملیات پرتکرار سرپرست و ادمین':'پرونده‌های کاری شما').'</p></div></div><div class="bcrm-quick-actions">';
        foreach(self::portal_views() as $key=>$cfg){ if($key==='dashboard'||!self::can_view($cfg))continue; echo '<a href="'.esc_url(Biawin_CRM_Auth::portal_url(['bcrm_view'=>$key])).'"><span>'.$cfg['icon'].'</span><strong>'.esc_html($cfg['label']).'</strong></a>'; }
        echo '</div></div><div class="bcrm-panel"><div class="bcrm-panel-title"><div><h3>وضعیت داده</h3><p>'.($is_manager?'خلاصه پایگاه مشتریان':'خلاصه پرونده‌های تخصیص‌یافته').'</p></div></div><div class="bcrm-data-summary"><div><span>فاکتورها</span><strong>'.esc_html(self::front_metric_number($data['invoices'])).'</strong></div><div><span>پروفایل کامل</span><strong>'.esc_html(self::front_metric_number($data['complete'])).'</strong></div><div><span>درخواست خدمات</span><strong>'.esc_html(self::front_metric_number($data['service_request'])).'</strong></div></div></div></section>';
        if($is_manager && current_user_can('bcrm_reports')){
            $excel=wp_nonce_url(admin_url('admin-post.php?action=bcrm_export_customers_excel'),'bcrm_export_customers_excel');
            echo '<section class="bcrm-panel bcrm-dashboard-report"><div class="bcrm-panel-title"><div><h3>گزارش جامع مشتریان</h3><p>وضعیت همه مشتریان، اولین تخصیص، کارشناس فعلی و آخرین اقدام را مشاهده یا به Excel دریافت کنید.</p></div><div class="bcrm-header-actions"><a class="bcrm-secondary-button" href="'.esc_url(Biawin_CRM_Auth::portal_url(['bcrm_view'=>'reports'])).'">مشاهده گزارش</a><a class="bcrm-primary-button" href="'.esc_url($excel).'">دانلود Excel</a></div></div></section>';
        }
        echo '</div>';
        return (string)ob_get_clean();
    }

    private static function front_reporting_scope(): array
    {
        $user_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            return ['role' => Biawin_CRM_Reporting_Consumer_Adapter::ROLE_ADMIN, 'requesting_user_id' => $user_id, 'scope_proven' => true, 'permitted_department_keys' => [], 'permitted_organization_keys' => []];
        }
        if (current_user_can('bcrm_manage_cases')) {
            return ['role' => Biawin_CRM_Reporting_Consumer_Adapter::ROLE_MANAGER, 'requesting_user_id' => $user_id, 'manager_user_id' => $user_id, 'scope_proven' => true, 'permitted_department_keys' => [], 'permitted_organization_keys' => []];
        }
        return ['role' => Biawin_CRM_Reporting_Consumer_Adapter::ROLE_AGENT, 'requesting_user_id' => $user_id, 'agent_user_id' => $user_id, 'scope_proven' => true];
    }

    private static function front_metric_number($value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) return 'نامطمئن';
        return number_format_i18n((int)$value);
    }

    private static function render_shell(string $content, string $active): string
    {
        $user=wp_get_current_user(); $views=self::portal_views(); $font_key=self::selected_portal_font_key();
        ob_start();
        echo '<div class="bcrm-portal-shell bcrm-font-'.esc_attr($font_key).'" dir="rtl"><div class="bcrm-sidebar-overlay" data-bcrm-menu-close></div><aside class="bcrm-shell-sidebar"><div class="bcrm-shell-brand"><div class="bcrm-shell-mark">B</div><div class="bcrm-brand-copy"><strong>Biawin CRM</strong><span>Customer Operations</span></div><button type="button" class="bcrm-sidebar-collapse" data-bcrm-sidebar-collapse aria-label="جمع کردن منو">☰</button></div><nav class="bcrm-shell-nav">';
        foreach($views as $key=>$cfg){ if(!self::can_view($cfg))continue; $url=Biawin_CRM_Auth::portal_url(['bcrm_view'=>$key]); echo '<a data-bcrm-view="'.esc_attr($key).'" data-tooltip="'.esc_attr($cfg['label']).'" title="'.esc_attr($cfg['label']).'" aria-label="'.esc_attr($cfg['label']).'" class="'.($active===$key?'is-active':'').'" href="'.esc_url($url).'"><i aria-hidden="true">'.$cfg['icon'].'</i><span>'.esc_html($cfg['label']).'</span></a>'; }
        echo '</nav><div class="bcrm-shell-sidebar-foot"><span class="bcrm-user-avatar">'.esc_html(mb_substr($user->display_name?:'ک',0,1)).'</span><div><strong>'.esc_html($user->display_name).'</strong><small>'.esc_html(self::current_role_label($user)).'</small></div></div></aside><main class="bcrm-shell-main"><header class="bcrm-shell-header"><button type="button" class="bcrm-mobile-menu" data-bcrm-menu-toggle aria-label="باز کردن منو">☰</button><div><span class="bcrm-shell-kicker">پرتال یکپارچه مشتریان</span><strong>'.esc_html($views[$active]['label']??'CRM بیاوین').'</strong></div><div class="bcrm-shell-actions"><form method="get" action="'.esc_url(Biawin_CRM_Auth::portal_url()).'" class="bcrm-shell-search"><input type="hidden" name="bcrm_view" value="customers"><input type="search" name="bcrm_q" placeholder="جست‌وجوی مشتری، موبایل یا فاکتور"><button>⌕</button></form><label class="bcrm-font-picker"><span>فونت</span><select data-bcrm-font-switch aria-label="انتخاب فونت">';
        foreach(self::font_choices() as $key=>$font){ echo '<option value="'.esc_attr($key).'" data-font-stack="'.esc_attr($font['stack']).'"'.selected($font_key,$key,false).'>'.esc_html($font['label']).'</option>'; }
        echo '</select></label><button type="button" class="bcrm-theme-toggle" data-bcrm-theme-toggle aria-label="تغییر حالت روشن/تاریک" title="دارک مد">☾</button><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('bcrm_portal_logout','_wpnonce',true,false).'<input type="hidden" name="action" value="bcrm_portal_logout"><button class="bcrm-logout-button" type="submit">خروج</button></form></div></header><div class="bcrm-shell-content">'.$content.'</div></main></div>';
        return (string)ob_get_clean();
    }

    private static function current_role_label(WP_User $user): string
    {
        $labels=Biawin_CRM_Auth::role_labels();
        foreach((array)$user->roles as $role){ if(isset($labels[$role])) return $labels[$role]; }
        return current_user_can('manage_options') ? 'مدیر سیستم' : 'کاربر پرتال';
    }

    private static function admin_view_map(): array
    {
        return [
            'biawin-crm'=>'dashboard',
            'biawin-crm-import'=>'import',
            'biawin-crm-customers'=>'customers',
            'biawin-crm-assignments'=>'assignments',
            'biawin-crm-deficiency'=>'deficiency',
            'biawin-crm-financial'=>'financial',
            'biawin-crm-complaints'=>'complaints',
            'biawin-crm-service-cases'=>'service-cases',
            'biawin-crm-services'=>'services',
            'biawin-crm-service-units'=>'service-units',
            'biawin-crm-refunds'=>'refunds',
            'biawin-crm-reports'=>'reports',
            'biawin-crm-data-audit'=>'data-audit',
            'biawin-crm-users'=>'users',
            'biawin-crm-audit'=>'audit',
            'biawin-crm-settings'=>'settings',
        ];
    }

    private static function view_from_admin_slug(string $slug): string
    {
        $map = self::admin_view_map();
        return $map[$slug] ?? '';
    }

    private static function translate_admin_html_to_portal(string $html): string
    {
        $view_map = self::admin_view_map();
        uksort($view_map, static fn($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($view_map as $slug => $view) {
            $html = str_replace(admin_url('admin.php?page=' . $slug), Biawin_CRM_Auth::portal_url(['bcrm_view' => $view]), $html);
            $html = str_replace('name="page" value="' . $slug . '"', 'name="bcrm_view" value="' . $view . '"', $html);
            $html = str_replace("name='page' value='" . $slug . "'", "name='bcrm_view' value='" . $view . "'", $html);
        }

        // Mark POST forms rendered from wp-admin pages so their redirects can be
        // translated back into the front portal even when Referrer is stripped.
        $context = '<input type="hidden" name="bcrm_portal_context" value="1">';
        $html = preg_replace('/(<form\b[^>]*method=(["\'])post\2[^>]*>)/i', '$1' . $context, $html) ?: $html;

        // wp-admin screen classes create layout leaks on the front end.
        $html = str_replace('class="wrap bcrm"', 'class="wrap bcrm bcrm-front-admin-page"', $html);
        return $html;
    }

    public static function frontend_redirect_filter(string $location, int $status): string
    {
        if (!is_user_logged_in() || !current_user_can('bcrm_work_cases')) {
            return $location;
        }

        $parts = wp_parse_url($location);
        if (empty($parts['query'])) {
            return $location;
        }
        parse_str($parts['query'], $q);
        $slug = sanitize_key((string) ($q['page'] ?? ''));
        $view = self::view_from_admin_slug($slug);
        if ($view === '') {
            return $location;
        }

        $ref = (string) wp_get_referer();
        $portal = Biawin_CRM_Auth::portal_url();
        $from_portal = ($ref !== '' && strpos($ref, $portal) !== false)
            || !empty($_POST['bcrm_portal_context'])
            || !empty($_GET['bcrm_portal_context']);

        if (!$from_portal) {
            return $location;
        }

        unset($q['page'], $q['bcrm_portal_context']);
        $q['bcrm_view'] = $view;
        return Biawin_CRM_Auth::portal_url($q);
    }

    private static function render_case_list(): string
    {
        global $wpdb;
        $user_id = get_current_user_id();
        $customers_table = Biawin_CRM_DB::table('customers');
        $assignments_table = Biawin_CRM_DB::table('assignments');
        $search = isset($_GET['bcrm_q']) ? sanitize_text_field(wp_unslash($_GET['bcrm_q'])) : '';
        $filter = isset($_GET['bcrm_filter']) ? sanitize_key($_GET['bcrm_filter']) : 'all';
        $contact_filter = isset($_GET['bcrm_contact']) ? sanitize_key($_GET['bcrm_contact']) : 'all';
        $contact_outcomes = Biawin_CRM_Workflow_Service::contact_outcomes(true);
        $contact_outcome_map = [];
        foreach ($contact_outcomes as $item) $contact_outcome_map[$item['key']] = $item;
        $allowed_contact_filters = array_merge(['all', 'none'], array_keys($contact_outcome_map));
        if (!in_array($contact_filter, $allowed_contact_filters, true)) $contact_filter = 'all';
        $allowed_per_page = [25, 50, 100, 200];
        $per_page = isset($_GET['bcrm_per_page']) ? absint($_GET['bcrm_per_page']) : 50;
        if (!in_array($per_page, $allowed_per_page, true)) $per_page = 50;
        $paged = isset($_GET['bcrm_paged']) ? max(1, absint($_GET['bcrm_paged'])) : 1;

        $where = ['1=1'];
        $where_args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $normalized_phone = Biawin_CRM_Normalizer::phone($search);
            if ($normalized_phone !== '') {
                $where[] = '(c.display_name LIKE %s OR c.customer_code LIKE %s OR c.source_customer_id LIKE %s OR EXISTS (SELECT 1 FROM ' . Biawin_CRM_DB::table('phones') . ' p WHERE p.customer_id=c.id AND p.normalized_phone=%s) OR EXISTS (SELECT 1 FROM ' . Biawin_CRM_DB::table('invoices') . ' i WHERE i.customer_id=c.id AND i.invoice_code LIKE %s))';
                array_push($where_args, $like, $like, $like, $normalized_phone, $like);
            } else {
                $where[] = '(c.display_name LIKE %s OR c.customer_code LIKE %s OR c.source_customer_id LIKE %s OR EXISTS (SELECT 1 FROM ' . Biawin_CRM_DB::table('invoices') . ' i WHERE i.customer_id=c.id AND i.invoice_code LIKE %s))';
                array_push($where_args, $like, $like, $like, $like);
            }
        }
        if ($filter === 'incomplete') {
            $where[] = "c.profile_completeness='incomplete'";
        } elseif ($filter === 'complete') {
            $where[] = "c.profile_completeness='complete'";
        } elseif ($filter === 'complaint') {
            $where[] = "c.journey_state='complaint'";
        } elseif ($filter === 'service_request') {
            $where[] = "c.journey_state='service_request'";
        } elseif ($filter === 'refund') {
            $where[] = "c.journey_state='refund'";
        }
        if ($contact_filter === 'none') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM ' . Biawin_CRM_DB::table('interactions') . ' ii WHERE ii.customer_id=c.id)';
        } elseif ($contact_filter !== 'all') {
            $legacy_label = (string)($contact_outcome_map[$contact_filter]['label'] ?? '');
            $where[] = "(COALESCE((SELECT NULLIF(ii.outcome_key,'') FROM " . Biawin_CRM_DB::table('interactions') . " ii WHERE ii.customer_id=c.id ORDER BY ii.id DESC LIMIT 1),'')=%s OR (COALESCE((SELECT NULLIF(ii.outcome_key,'') FROM " . Biawin_CRM_DB::table('interactions') . " ii WHERE ii.customer_id=c.id ORDER BY ii.id DESC LIMIT 1),'')='' AND COALESCE((SELECT ii.outcome FROM " . Biawin_CRM_DB::table('interactions') . " ii WHERE ii.customer_id=c.id ORDER BY ii.id DESC LIMIT 1),'')=%s))";
            $where_args[] = $contact_filter;
            $where_args[] = $legacy_label;
        }


        [$scope_sql, $access_args] = self::customer_access_scope('c');
        $access_sql = $scope_sql . ' AND ';

        $all_args = array_merge($access_args, $where_args);
        $base_sql = " FROM $customers_table c WHERE " . $access_sql . implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(DISTINCT c.id)' . $base_sql;
        $total_items = (int) ($all_args ? $wpdb->get_var($wpdb->prepare($count_sql, $all_args)) : $wpdb->get_var($count_sql));
        $total_pages = max(1, (int) ceil($total_items / $per_page));
        if ($paged > $total_pages) $paged = $total_pages;
        $offset = ($paged - 1) * $per_page;

        $list_sql = 'SELECT DISTINCT c.*, (SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' pp WHERE pp.customer_id=c.id ORDER BY pp.is_primary DESC,pp.id ASC LIMIT 1) AS primary_phone, (SELECT ii.outcome FROM ' . Biawin_CRM_DB::table('interactions') . ' ii WHERE ii.customer_id=c.id ORDER BY ii.id DESC LIMIT 1) AS latest_outcome, (SELECT ii.outcome_key FROM ' . Biawin_CRM_DB::table('interactions') . ' ii WHERE ii.customer_id=c.id ORDER BY ii.id DESC LIMIT 1) AS latest_outcome_key, (SELECT ii.next_followup_at FROM ' . Biawin_CRM_DB::table('interactions') . ' ii WHERE ii.customer_id=c.id ORDER BY ii.id DESC LIMIT 1) AS latest_followup_at' . $base_sql . ' ORDER BY c.updated_at DESC, c.id DESC LIMIT %d OFFSET %d';
        $list_args = array_merge($all_args, [$per_page, $offset]);
        $customers = $wpdb->get_results($wpdb->prepare($list_sql, $list_args));

        $metric_sql = "SELECT COUNT(*) all_count, SUM(c.profile_completeness='complete') complete_count, SUM(c.profile_completeness='incomplete') incomplete_count, SUM(c.journey_state='complaint') complaint_count, SUM(c.journey_state='service_request') service_request_count, SUM(c.journey_state='refund') refund_count FROM $customers_table c WHERE $scope_sql";
        $metric_row = $access_args ? $wpdb->get_row($wpdb->prepare($metric_sql, $access_args)) : $wpdb->get_row($metric_sql);
        $metrics = [
            'all' => (int)($metric_row->all_count ?? 0),
            'complete' => (int)($metric_row->complete_count ?? 0),
            'incomplete' => (int)($metric_row->incomplete_count ?? 0),
            'complaint' => (int)($metric_row->complaint_count ?? 0),
            'service_request' => (int)($metric_row->service_request_count ?? 0),
            'refund' => (int)($metric_row->refund_count ?? 0),
        ];

        $base_params = [
            'bcrm_view' => 'customers',
            'bcrm_filter' => $filter,
            'bcrm_q' => $search,
            'bcrm_per_page' => $per_page,
            'bcrm_contact' => $contact_filter,
        ];

        ob_start();
        echo '<div class="bcrm-portal bcrm-app" dir="rtl">';
        self::app_topbar('مشتریان', 'مدیریت پرونده‌ها، پروفایل‌سازی و مسیر رضایت مشتری');
        echo '<section class="bcrm-dashboard-grid">';
        self::metric_card('کل پروفایل‌ها', $metrics['all'], 'users');
        self::metric_card('پروفایل کامل', $metrics['complete'], 'check');
        self::metric_card('پروفایل ناقص', $metrics['incomplete'], 'alert');
        self::metric_card('نارضایتی فعال', $metrics['complaint'], 'complaint');
        self::metric_card('با درخواست خدمت', $metrics['service_request'], 'service');
        self::metric_card('عودت وجه', $metrics['refund'], 'refund');
        echo '</section>';

        echo '<section class="bcrm-panel bcrm-list-panel"><div class="bcrm-toolbar"><form method="get" action="' . esc_url(Biawin_CRM_Auth::portal_url()) . '" class="bcrm-search-form">';
        echo '<input type="hidden" name="bcrm_view" value="customers"><input type="hidden" name="bcrm_filter" value="' . esc_attr($filter) . '"><input type="hidden" name="bcrm_per_page" value="' . esc_attr((string)$per_page) . '"><input type="hidden" name="bcrm_contact" value="' . esc_attr($contact_filter) . '">';
        echo '<input type="search" name="bcrm_q" value="' . esc_attr($search) . '" placeholder="جست‌وجو با نام، موبایل، CustomerID، کد مشتری یا کد فاکتور"><button>جست‌وجو</button></form>';
        echo '<div class="bcrm-list-controls"><form method="get" action="' . esc_url(Biawin_CRM_Auth::portal_url()) . '" class="bcrm-per-page-form"><input type="hidden" name="bcrm_view" value="customers"><input type="hidden" name="bcrm_filter" value="' . esc_attr($filter) . '"><input type="hidden" name="bcrm_q" value="' . esc_attr($search) . '"><input type="hidden" name="bcrm_contact" value="' . esc_attr($contact_filter) . '"><label>نمایش در هر صفحه <select name="bcrm_per_page" onchange="this.form.submit()">';
        foreach ($allowed_per_page as $size) echo '<option value="' . esc_attr((string)$size) . '"' . selected($per_page, $size, false) . '>' . esc_html((string)$size) . '</option>';
        echo '</select></label></form><div class="bcrm-result-count">' . number_format_i18n($total_items) . ' نتیجه</div></div><div class="bcrm-filter-tabs">';
        foreach (['all'=>'همه','complete'=>'کامل','incomplete'=>'ناقص','complaint'=>'نارضایتی','service_request'=>'درخواست خدمات','refund'=>'عودت وجه'] as $key=>$label) {
            $url = Biawin_CRM_Auth::portal_url(['bcrm_view'=>'customers','bcrm_filter'=>$key,'bcrm_contact'=>$contact_filter,'bcrm_q'=>$search,'bcrm_per_page'=>$per_page]);
            echo '<a class="' . ($filter === $key ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</div><div class="bcrm-filter-tabs bcrm-contact-tabs"><span class="bcrm-tabs-label">نتیجه آخرین تماس:</span>';
        $contact_tabs = ['all'=>'همه', 'none'=>'بدون وضعیت'];
        foreach ($contact_outcomes as $item) $contact_tabs[$item['key']] = $item['label'];
        foreach ($contact_tabs as $key=>$label) {
            $url = Biawin_CRM_Auth::portal_url(['bcrm_view'=>'customers','bcrm_filter'=>$filter,'bcrm_contact'=>$key,'bcrm_q'=>$search,'bcrm_per_page'=>$per_page]);
            echo '<a class="' . ($contact_filter === $key ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</div></div>';

        if (!$customers) {
            echo '<div class="bcrm-empty-state"><strong>پرونده‌ای پیدا نشد</strong><span>فیلتر یا عبارت جست‌وجو را تغییر دهید.</span></div>';
        } else {
            echo '<div class="bcrm-case-table">';
            foreach ($customers as $customer) {
                $url = Biawin_CRM_Auth::portal_url(['bcrm_view'=>'customers','bcrm_customer'=>(int)$customer->id]);
                $phone = (string)($customer->primary_phone ?? '');
                $outcome = (string)($customer->latest_outcome ?? '');
                $outcome_key = sanitize_key((string)($customer->latest_outcome_key ?? ''));
                if ($outcome_key === '') $outcome_key = Biawin_CRM_Workflow_Service::contact_outcome_key_from_label($outcome);
                $outcome_class = $outcome_key === 'success' ? 'is-success' : (in_array($outcome_key, ['followup','callback'], true) ? 'is-warning' : (in_array($outcome_key, ['wrong_number','other_person','refused'], true) ? 'is-danger' : 'is-neutral'));
                $current_outcome = $outcome_key !== '' ? Biawin_CRM_Workflow_Service::contact_outcome_by_key($outcome_key) : null;
                $outcome_label = $current_outcome ? (string)$current_outcome['label'] : ($outcome !== '' ? $outcome : 'بدون تماس');
                $followup_text = !empty($customer->latest_followup_at) ? ' · پیگیری: ' . self::date_label($customer->latest_followup_at) : '';
                echo '<article class="bcrm-case-row"><div class="bcrm-avatar">' . esc_html(mb_substr($customer->display_name ?: 'م', 0, 1)) . '</div><div class="bcrm-case-identity"><strong>' . esc_html($customer->display_name ?: 'پروفایل بدون نام') . '</strong><span>' . esc_html($phone ?: 'بدون شماره') . ' · ' . esc_html($customer->customer_code ?: 'کد مشتری صادر نشده') . '</span></div><div class="bcrm-case-money"><span>مجموع فاکتور</span><strong>' . self::money($customer->total_paid) . '</strong></div><div>' . self::profile_badge($customer->profile_completeness) . '</div><div>' . self::journey_badge($customer->journey_state) . '</div><div class="bcrm-contact-state"><span class="bcrm-badge ' . esc_attr($outcome_class) . '">' . esc_html($outcome_label) . '</span><small>' . esc_html($followup_text) . '</small></div><a class="bcrm-primary-button is-small" href="' . esc_url($url) . '">باز کردن پروفایل</a></article>';
            }
            echo '</div>';
        }

        if ($total_pages > 1) {
            $window_start = max(1, $paged - 2);
            $window_end = min($total_pages, $paged + 2);
            echo '<nav class="bcrm-pagination" aria-label="صفحه‌بندی مشتریان"><span class="bcrm-page-summary">صفحه ' . number_format_i18n($paged) . ' از ' . number_format_i18n($total_pages) . '</span><div class="bcrm-page-links">';
            if ($paged > 1) {
                echo '<a href="' . esc_url(Biawin_CRM_Auth::portal_url(array_merge($base_params, ['bcrm_paged'=>$paged-1]))) . '">قبلی</a>';
            }
            if ($window_start > 1) {
                echo '<a href="' . esc_url(Biawin_CRM_Auth::portal_url(array_merge($base_params, ['bcrm_paged'=>1]))) . '">1</a>';
                if ($window_start > 2) echo '<span>…</span>';
            }
            for ($n=$window_start; $n<=$window_end; $n++) {
                if ($n === $paged) echo '<span class="is-current">' . number_format_i18n($n) . '</span>';
                else echo '<a href="' . esc_url(Biawin_CRM_Auth::portal_url(array_merge($base_params, ['bcrm_paged'=>$n]))) . '">' . number_format_i18n($n) . '</a>';
            }
            if ($window_end < $total_pages) {
                if ($window_end < $total_pages - 1) echo '<span>…</span>';
                echo '<a href="' . esc_url(Biawin_CRM_Auth::portal_url(array_merge($base_params, ['bcrm_paged'=>$total_pages]))) . '">' . number_format_i18n($total_pages) . '</a>';
            }
            if ($paged < $total_pages) {
                echo '<a href="' . esc_url(Biawin_CRM_Auth::portal_url(array_merge($base_params, ['bcrm_paged'=>$paged+1]))) . '">بعدی</a>';
            }
            echo '</div></nav>';
        }
        echo '</section></div>';
        return (string) ob_get_clean();
    }

    private static function render_profile(int $customer_id): string
    {
        global $wpdb;
        Biawin_CRM_DB::read_refresh($customer_id, static function () use ($customer_id) { Biawin_CRM_Customer_Service::recalculate_customer($customer_id); });
        $customer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $customer_id));
        if (!$customer) {
            return '<div class="bcrm-portal"><div class="bcrm-alert is-danger">پرونده پیدا نشد.</div></div>';
        }
        if (class_exists('Biawin_CRM_Production_Readiness') && !Biawin_CRM_Production_Readiness::ordinary_agent_may_work($customer_id)) {
            return '<div class="bcrm-portal"><div class="bcrm-alert is-warning"><strong>این پرونده موقتاً در Readiness Quarantine است.</strong><span>CustomerID این هویت در Blacklist رسمی قرار دارد. برای جلوگیری از ثبت تاریخچه جدید روی هویت نادرست، تا پایان Reconciliation فقط سرپرست/مدیر می‌تواند آن را بررسی کند.</span></div></div>';
        }
        if (!self::can_access_customer($customer_id)) {
            return '<div class="bcrm-portal"><div class="bcrm-alert is-danger">این پرونده به شما تخصیص داده نشده است.</div></div>';
        }

        Biawin_CRM_DB::read_refresh($customer_id, static function () use ($customer_id) { Biawin_CRM_Customer_Service::ensure_single_primary_phone($customer_id); });
        $phones = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d ORDER BY is_primary DESC,id ASC', $customer_id));
        $invoices = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d ORDER BY id DESC', $customer_id));
        $item_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(ii.quantity),0) FROM ' . Biawin_CRM_DB::table('invoice_items') . ' ii INNER JOIN ' . Biawin_CRM_DB::table('invoices') . ' i ON i.id=ii.invoice_id WHERE i.customer_id=%d', $customer_id));
        $source_services = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('services') . ' WHERE customer_id=%d ORDER BY id DESC', $customer_id));
        $service_cases = $wpdb->get_results($wpdb->prepare('SELECT sc.*, s.name AS service_name FROM ' . Biawin_CRM_DB::table('service_cases') . ' sc LEFT JOIN ' . Biawin_CRM_DB::table('service_catalog') . ' s ON s.id=sc.service_id WHERE sc.customer_id=%d ORDER BY sc.id DESC', $customer_id));
        $complaints = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('complaints') . ' WHERE customer_id=%d ORDER BY id DESC', $customer_id));
        $refunds = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('refunds') . ' WHERE customer_id=%d ORDER BY id DESC', $customer_id));
        $financial_cases = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('financial_cases') . ' WHERE customer_id=%d ORDER BY id DESC', $customer_id));
        $attachments = $wpdb->get_results($wpdb->prepare('SELECT a.*,p.post_title FROM ' . Biawin_CRM_DB::table('attachments') . ' a LEFT JOIN ' . $wpdb->posts . ' p ON p.ID=a.wp_attachment_id WHERE a.customer_id=%d ORDER BY a.id DESC', $customer_id));
        $finance_receipt_totals = self::finance_receipt_totals_for_profile($customer_id, (array)$attachments);
        $interactions = $wpdb->get_results($wpdb->prepare('SELECT i.*,u.display_name agent_name FROM ' . Biawin_CRM_DB::table('interactions') . ' i LEFT JOIN ' . $wpdb->users . ' u ON u.ID=i.agent_user_id WHERE i.customer_id=%d ORDER BY i.id DESC LIMIT 50', $customer_id));
        $timeline = Biawin_CRM_Workflow_Service::customer_timeline($customer_id, 100);
        $catalog = Biawin_CRM_Workflow_Service::active_services();
        $message = isset($_GET['bcrm_message']) ? sanitize_text_field(wp_unslash($_GET['bcrm_message'])) : '';

        ob_start();
        echo '<div class="bcrm-portal bcrm-app bcrm-profile-page" dir="rtl" data-bcrm-profile-customer-id="' . (int)$customer_id . '">';
        self::app_topbar('پروفایل مشتری', 'نمای ۳۶۰ درجه و مدیریت همه اقدامات مشتری', remove_query_arg('bcrm_customer'));
        if ($message !== '') {
            echo '<div class="bcrm-alert is-success">' . esc_html($message) . '</div>';
        }

        echo '<section class="bcrm-customer-header"><div class="bcrm-customer-main"><div class="bcrm-avatar is-large">' . esc_html(mb_substr($customer->display_name ?: 'م', 0, 1)) . '</div><div><div class="bcrm-title-line"><h2>' . esc_html($customer->display_name ?: 'پروفایل بدون نام') . '</h2>' . self::profile_badge($customer->profile_completeness) . self::journey_badge($customer->journey_state) . (($customer->assignment_cohort ?? '') === '1404' ? '<span class="bcrm-badge is-warning">۱۴۰۴</span>' : '') . '</div><div class="bcrm-meta-line"><span>کد مشتری: <b>' . esc_html($customer->customer_code ?: 'هنوز صادر نشده') . '</b></span><span>CustomerID: <b dir="ltr">' . esc_html($customer->source_customer_id ?: 'ثبت نشده') . '</b></span><span class="bcrm-grade-meta" title="گرید فعلی از مجموع امتیاز مالی و میانگین رضایت محاسبه می‌شود؛ وجود شکایت باز یا مغایرت مالی، گرید را Disputed می‌کند.">گرید: <b>' . esc_html($customer->grade ?: '-') . '</b> <small>ⓘ</small></span><span>آخرین بروزرسانی: <b>' . esc_html(self::date_label($customer->updated_at)) . '</b></span></div></div></div><div class="bcrm-header-actions"><a class="bcrm-secondary-button" href="#bcrm-interaction">ثبت تماس</a><button type="button" class="bcrm-secondary-button" data-bcrm-toggle-related>افزودن شماره و فاکتور</button></div></section>';

        echo '<section class="bcrm-summary bcrm-summary-9">';
        self::summary_box('مجموع فاکتورها', self::money($customer->total_paid), '', 'total_paid');
        self::summary_box('مبلغ اعلامی', $customer->declared_paid === null ? 'ثبت نشده' : self::money($customer->declared_paid), '', 'declared_paid');
        self::summary_box('مغایرت', self::money(abs((float) $customer->discrepancy_amount)), abs((float)$customer->discrepancy_amount) > 0 ? 'danger' : 'success', 'difference');
        self::summary_box('مجموع تاییدها', self::money((float)$finance_receipt_totals['approved']), 'success', 'finance_approved_receipts');
        self::summary_box('مجموع عدم تاییدها', self::money((float)$finance_receipt_totals['rejected']), 'danger', 'finance_rejected_receipts');
        $refund_balance = class_exists('Biawin_CRM_Refund_Register') ? Biawin_CRM_Refund_Register::balance_for($customer_id) : (float)$customer->total_paid;
        $refund_total = class_exists('Biawin_CRM_Refund_Register') ? Biawin_CRM_Refund_Register::confirmed_total($customer_id) : 0.0;
        $refund_used = class_exists('Biawin_CRM_Refund_Register') ? Biawin_CRM_Refund_Register::used_total($customer_id) : 0.0;
        self::summary_box('مانده', self::money($refund_balance), $refund_balance < 0 ? 'danger' : ($refund_total > 0 ? 'success' : ''), 'refund_balance');
        self::summary_box('استفاده شده', self::money($refund_used), '', 'refund_used');
        self::summary_box('تعداد فاکتور', (string) count($invoices));
        self::summary_box('تعداد محصول', (string) $item_count);
        self::summary_box('اعتبار خدمات', self::money($customer->service_credit_total), 'primary', 'service_credit_total');
        self::summary_box('اعتبار مصرف/رزرو', self::money((float)$customer->service_credit_consumed + (float)$customer->service_credit_reserved), '', 'service_credit_used');
        self::summary_box('اعتبار باقی‌مانده', self::money($customer->service_credit_remaining), 'success', 'service_credit_remaining');
        echo '</section>';

        if ($customer->profile_completeness === 'incomplete') {
            echo '<div class="bcrm-alert is-warning"><div><strong>پروفایل ناقص است</strong><span>مبلغ اعلامی مشتری با مجموع فاکتورهای ثبت‌شده تطبیق ندارد. این پرونده باید در کارتابل تأیید مالی تکمیل شود.</span></div><span class="bcrm-diff">اختلاف: ' . self::money(abs((float)$customer->discrepancy_amount)) . '</span></div>';
        } elseif ($customer->profile_completeness === 'complete') {
            echo '<div class="bcrm-alert is-success"><div><strong>پروفایل کامل است</strong><span>مبلغ اعلامی و مجموع فاکتورهای مشتری با هم تطبیق دارند و مشتری می‌تواند وارد مسیر رضایت، نارضایتی یا خدمات شود.</span></div></div>';
        } else {
            echo '<div class="bcrm-alert is-info"><div><strong>پروفایل هنوز تعیین تکلیف نشده</strong><span>برای تشخیص کامل یا ناقص بودن، مبلغ اعلامی مشتری را در فرم تماس ثبت کنید.</span></div></div>';
        }

        $refund_tags = class_exists('Biawin_CRM_Refund_Register') ? Biawin_CRM_Refund_Register::tags_for($customer_id) : [];
        if ($refund_tags) {
            $tag_line = [];
            if (in_array('REFUND_CONFIRMED', $refund_tags, true)) $tag_line[] = '<span class="bcrm-badge is-danger">عودت قطعی (اکسل)</span>';
            if (in_array('REFUND_REPORTED', $refund_tags, true)) $tag_line[] = '<span class="bcrm-badge is-warning">عودت اعلامی (کارشناس)</span>';
            echo '<section class="bcrm-panel" id="bcrm-refunds" style="margin-top:14px"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">عودت وجه</span><h3>عودت‌های مشتری</h3><p>' . implode(' ', $tag_line) . '</p></div></div><div class="bcrm-table-wrap"><table class="bcrm-table"><thead><tr><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>منبع</th><th>ثبت‌کننده</th><th>Batch / یادداشت</th></tr></thead><tbody>';
            $refund_history = Biawin_CRM_Refund_Register::history_for($customer_id);
            foreach ($refund_history as $rr) {
                $is_excel = $rr->source_type === 'excel';
                $kind = $is_excel ? 'قطعی' : ($rr->source_type === 'used' ? 'استفاده شده' : 'اعلامی');
                $source = $is_excel ? 'آپلود اکسل (عودت)' : ($rr->source_type === 'used' ? 'آپلود اکسل (استفاده‌شده)' : 'اعلام‌شده توسط کارشناس');
                echo '<tr><td>' . esc_html(self::date_label($rr->created_at)) . '</td><td>' . esc_html($kind) . '</td><td>' . ($rr->refund_amount > 0 ? esc_html(self::money($rr->refund_amount)) : '—') . '</td><td>' . esc_html($source) . '</td><td>' . esc_html(($rr->actor_name ?? '-') ?: '-') . '</td><td>' . esc_html(trim(($rr->batch_code ?: '') . ($rr->source_row ? ' · ردیف ' . number_format_i18n((int)$rr->source_row) : '')) ?: '-') . '</td></tr>';
            }
            if (!$refund_history) echo '<tr><td colspan="6">هنوز عودتی ثبت نشده است.</td></tr>';
            echo '</tbody></table></div></section>';
        }

        echo '<nav class="bcrm-profile-tabs"><a href="#bcrm-overview" class="is-active">نمای کلی</a><a href="#bcrm-invoices">فاکتورها</a><a href="#bcrm-interaction">فرم کارشناس</a><a href="#bcrm-services">خدمات مشتری</a><a href="#bcrm-complaints">نارضایتی‌ها</a>' . ($refund_tags ? '<a href="#bcrm-refunds">عودت‌ها</a>' : '') . '<a href="#bcrm-history">تاریخچه</a></nav>';

        echo '<div class="bcrm-profile-grid" id="bcrm-overview"><div class="bcrm-main-column">';
        self::render_invoice_panel($customer_id, $invoices);
        self::render_search_and_attach($customer_id);
        self::render_interaction_panel($customer, $phones, $interactions[0] ?? null);
        self::render_journey_panel($customer);
        self::render_service_panel($customer, $catalog, $service_cases);
        self::render_complaint_panel($customer, $complaints, $refunds);
        self::render_timeline_panel($timeline, $interactions);
        echo '</div><aside class="bcrm-side-column">';
        self::render_phone_card($customer_id, $phones);
        self::render_profile_status_card($customer, $financial_cases, $attachments);
        self::render_source_services_card($source_services);
        echo '</aside></div></div>';
        return (string) ob_get_clean();
    }

    private static function render_invoice_panel(int $customer_id, array $invoices): void
    {
        global $wpdb;
        $invoice_total = 0.0; foreach ($invoices as $iv) if (!(int)($iv->exclude_from_total ?? 0)) $invoice_total += (float)($iv->amount_paid ?? 0);
        echo '<section class="bcrm-panel" id="bcrm-invoices"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">اطلاعات مالی</span><h3>فاکتورهای مشتری</h3><p>همه فاکتورهای متصل به این پروفایل · <strong>مجموع: ' . self::money($invoice_total) . '</strong></p></div><button type="button" class="bcrm-secondary-button" data-bcrm-toggle-related>افزودن شماره و فاکتور</button></div><div class="bcrm-table-wrap"><table class="bcrm-table"><thead><tr><th>کد فاکتور</th><th>شماره/نام ثبت‌شده</th><th>محصولات</th><th>مبلغ فاکتور</th><th>فروشنده</th><th>کال‌سنتر</th><th>مدیر فروش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="bcrm-invoice-rows">';
        if (!$invoices) {
            echo '<tr><td colspan="9" class="bcrm-table-empty">فاکتوری به این پروفایل متصل نیست.</td></tr>';
        }
        foreach ($invoices as $invoice) {
            $items = $wpdb->get_col($wpdb->prepare('SELECT product_name FROM ' . Biawin_CRM_DB::table('invoice_items') . ' WHERE invoice_id=%d', (int)$invoice->id));
            $invoice_phones = $wpdb->get_col($wpdb->prepare('SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('invoice_phones') . ' WHERE invoice_id=%d ORDER BY phone_position,id', (int)$invoice->id));
            $identity = trim(implode('، ', $invoice_phones) . ($invoice->invoice_name ? ' · ' . $invoice->invoice_name : ''), ' ·');
            $extra_html = '';
            $extra = !empty($invoice->extra_json) ? json_decode((string)$invoice->extra_json, true) : [];
            if (is_array($extra) && $extra) {
                $parts = [];
                foreach ($extra as $label => $value) {
                    $text = is_array($value) ? implode('، ', array_map('strval', $value)) : (string)$value;
                    if ($text !== '') $parts[] = '<span><b>' . esc_html((string)$label) . ':</b> ' . esc_html($text) . '</span>';
                }
                if ($parts) $extra_html = '<details class="bcrm-invoice-extra"><summary>اطلاعات تکمیلی اکسل</summary>' . implode('', $parts) . '</details>';
            }
            $status_badges = '';
            if ((int)($invoice->prepayment_unapproved ?? 0) === 1) $status_badges .= '<span class="bcrm-badge is-danger">پیش‌واریزی تأیید نشده</span> ';
            if ((int)($invoice->exclude_from_total ?? 0) === 1) $status_badges .= '<span class="bcrm-badge is-warning">خارج از مجموع</span> ';
            if ((int)$invoice->has_input_conflict) $status_badges .= '<span class="bcrm-badge is-warning">مغایرت ورودی</span> ';
            $status_badges .= '<span class="bcrm-badge is-neutral">' . esc_html($invoice->invoice_status ?: 'بدون وضعیت') . '</span>';
            $manual_invoice = $invoice->source_import_id === null;
            $add_phone_action = '<button type="button" class="bcrm-link-button" data-bcrm-add-invoice-phone data-invoice-id="' . (int)$invoice->id . '" data-invoice-code="' . esc_attr($invoice->invoice_code) . '">افزودن شماره</button>';
            $invoice_action = $manual_invoice ? '<button type="button" class="bcrm-link-button" data-bcrm-edit-manual-invoice data-invoice-id="' . (int)$invoice->id . '" data-invoice-code="' . esc_attr($invoice->invoice_code) . '" data-invoice-amount="' . esc_attr((string)(float)$invoice->amount_paid) . '" data-invoice-name="' . esc_attr((string)$invoice->invoice_name) . '" data-payment-info="' . esc_attr((string)$invoice->payment_info) . '">ویرایش</button>' : '<span class="bcrm-muted">مرجع</span>';
            $invoice_action .= ' ' . $add_phone_action;
            echo '<tr data-invoice-code="' . esc_attr($invoice->invoice_code) . '" data-invoice-id="' . (int)$invoice->id . '"><td><b>' . esc_html($invoice->invoice_code) . '</b></td><td>' . esc_html($identity ?: '-') . $extra_html . '</td><td>' . esc_html(implode('، ', $items) ?: '-') . '</td><td data-bcrm-invoice-amount>' . self::money($invoice->amount_paid) . '</td><td>' . esc_html($invoice->seller ?: '-') . '</td><td>' . esc_html($invoice->callcenter ?: '-') . '</td><td>' . esc_html($invoice->sales_manager ?: '-') . '</td><td>' . $status_badges . '</td><td>' . $invoice_action . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function render_interaction_panel(object $customer, array $phones, ?object $last = null): void
    {
        $v = static function(string $key, $fallback = '') use ($last) { return $last && isset($last->{$key}) ? $last->{$key} : $fallback; };
        echo '<section class="bcrm-panel bcrm-action-panel" id="bcrm-interaction"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">اقدام اصلی کارشناس</span><h3>تماس، مبلغ اعلامی و رضایت‌سنجی</h3><p>اطلاعات آخرین تماس دوباره داخل فرم بارگذاری می‌شود و مبلغ اعلامی همان لحظه بدون ذخیره بررسی می‌شود.</p></div><span class="bcrm-save-state" data-bcrm-save-state>' . ($last ? 'آخرین اطلاعات بارگذاری شد' : 'آماده ثبت') . '</span></div>';
        $editable_interaction_id = ($last && (int)$last->agent_user_id === get_current_user_id()) ? (int)$last->id : 0;
        echo '<form class="bcrm-form" data-bcrm-interaction-form autocomplete="off"><input type="hidden" name="customer_id" value="' . (int)$customer->id . '"><input type="hidden" name="interaction_id" value="' . $editable_interaction_id . '">';
        echo '<div class="bcrm-form-grid"><label><span>شماره تماس استفاده‌شده</span><select name="contact_phone" required><option value="">انتخاب کنید</option>';
        foreach ($phones as $phone) echo '<option value="' . esc_attr($phone->normalized_phone) . '" ' . selected((string)$v('contact_phone'), (string)$phone->normalized_phone, false) . '>' . esc_html($phone->normalized_phone) . '</option>';
        echo '</select></label><label><span>نتیجه تماس</span><select name="outcome_key">';
        $active_outcomes = Biawin_CRM_Workflow_Service::contact_outcomes(true);
        $saved_outcome_key = $last && isset($last->outcome_key) ? sanitize_key((string)$last->outcome_key) : '';
        if ($saved_outcome_key === '' && $last && isset($last->outcome)) $saved_outcome_key = Biawin_CRM_Workflow_Service::contact_outcome_key_from_label((string)$last->outcome);
        echo '<option value="" ' . selected($saved_outcome_key, '', false) . '>انتخاب کنید</option>';
        foreach ($active_outcomes as $outcome) echo '<option value="' . esc_attr($outcome['key']) . '" ' . selected($saved_outcome_key, (string)$outcome['key'], false) . '>' . esc_html($outcome['label']) . '</option>';
        echo '</select></label><label class="bcrm-declared-field"><span>مبلغ اعلامی مشتری</span><input name="declared_paid" data-bcrm-declared-paid inputmode="numeric" placeholder="مثلاً 300,000,000" value="' . esc_attr($customer->declared_paid === null ? '' : number_format((float)$customer->declared_paid, 0, '.', ',')) . '"><small>بررسی خودکار و لحظه‌ای</small></label>';
        foreach ([['satisfaction_score','رضایت کلی (۱ تا ۱۰)'],['sales_satisfaction','رضایت از فروش (۱ تا ۱۰)'],['followup_satisfaction','رضایت از پیگیری (۱ تا ۱۰)'],['service_quality_satisfaction','رضایت از کیفیت خدمات (۱ تا ۱۰)'],['service_timing_satisfaction','رضایت از زمان ارائه خدمات (۱ تا ۱۰)']] as $field) {
            $cur = (string) $v($field[0], '');
            echo '<label><span>' . esc_html($field[1]) . '</span><select name="' . esc_attr($field[0]) . '"><option value="" ' . selected($cur, '', false) . '>انتخاب کنید</option>';
            for ($score = 1; $score <= 10; $score++) echo '<option value="' . $score . '" ' . selected($cur, (string)$score, false) . '>' . $score . '</option>';
            echo '</select></label>';
        }
        foreach ([['promised_services_received','همه خدمات وعده‌داده‌شده دریافت شده؟'],['repurchase_intent','تمایل به خرید مجدد'],['referral_intent','تمایل به معرفی شرکت']] as $field) {
            $cur = $v($field[0], '');
            echo '<label><span>' . esc_html($field[1]) . '</span><select name="' . esc_attr($field[0]) . '"><option value="" ' . selected((string)$cur,'',false) . '>نامشخص</option><option value="1" ' . selected((string)$cur,'1',false) . '>بله</option><option value="0" ' . selected((string)$cur,'0',false) . '>خیر</option></select></label>';
        }
        $next = $v('next_followup_at','');
        $next_value = $next ? self::jalali_datetime_value((string)$next) : '';
        echo '<label class="bcrm-date-field"><span>پیگیری بعدی</span><div class="bcrm-date-picker-wrap"><input name="next_followup_at" data-bcrm-jalali-datetime type="text" readonly placeholder="انتخاب تاریخ و ساعت" value="' . esc_attr($next_value) . '"><button type="button" class="bcrm-date-picker-button" data-bcrm-open-jalali aria-label="باز کردن تقویم">📅</button></div></label><div class="bcrm-form-hint"><strong>شماره دیگری اعلام شد؟</strong><span>«افزودن شماره و فاکتور» را بزنید؛ با انتخاب فاکتور، شماره مرتبط همان لحظه به پروفایل اضافه می‌شود.</span></div></div>';
        echo '<div class="bcrm-reconciliation-preview" data-bcrm-reconciliation-preview><span>مبلغ اعلامی را وارد کنید تا سیستم بدون ذخیره، مغایرت را بررسی کند.</span></div>';
        $interaction_button_label = $editable_interaction_id ? 'ذخیره تغییرات' : 'ثبت تماس';
        $interaction_help = $editable_interaction_id ? 'آخرین تماس ثبت‌شده توسط شما قابل ویرایش است و با ذخیره تغییرات همان رکورد بروزرسانی می‌شود.' : 'برای انتخاب مسیر یا خدمت، سیستم در صورت نیاز همین فرم را خودکار ذخیره می‌کند.';
        echo '<label class="bcrm-full"><span>یادداشت کارشناس</span><textarea name="notes" rows="3" placeholder="خلاصه مکالمه و اطلاعات مهم مشتری">' . esc_textarea((string)$v('notes')) . '</textarea></label><div class="bcrm-form-actions"><button type="submit" class="bcrm-primary-button" data-bcrm-save-interaction>' . esc_html($interaction_button_label) . '</button><small>' . esc_html($interaction_help) . '</small></div></form></section>';
    }

    private static function render_journey_panel(object $customer): void
    {
        $profile_pending = $customer->profile_completeness === 'pending';
        $profile_incomplete = $customer->profile_completeness === 'incomplete';
        $journey_hint = $profile_pending
            ? 'مبلغ اعلامی را ثبت کنید؛ پس از آن مسیر حتی در صورت مغایرت قابل انتخاب است.'
            : ($profile_incomplete ? 'مغایرت مالی وجود دارد؛ مسیر را انتخاب کنید. پروفایل هم‌زمان در تأیید مالی می‌ماند.' : 'آماده انتخاب مسیر');
        $has_declared = $customer->declared_paid !== null;
        echo '<section class="bcrm-panel bcrm-next-step' . ($has_declared ? '' : ' is-blocked') . '" id="bcrm-journey" data-bcrm-journey-panel data-bcrm-customer-id="' . (int)$customer->id . '" data-has-declared="' . ($has_declared ? '1' : '0') . '" data-profile-complete="' . ($customer->profile_completeness === 'complete' ? '1' : '0') . '"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">تعیین مسیر مشتری</span><h3>مشتری در کدام مسیر قرار می‌گیرد؟</h3><p>پس از ثبت مبلغ اعلامی، یکی از سه مسیر را انتخاب کنید. مغایرت مالی مانع تعیین مسیر نیست و جداگانه در تأیید مالی پیگیری می‌شود.</p></div><span class="bcrm-gate-label" data-bcrm-gate-label>' . esc_html($journey_hint) . '</span></div><div class="bcrm-journey-grid">';
        foreach ([
            ['no_request','بدون درخواست','مشتری درخواست خدمت یا نارضایتی ندارد؛ سطح رضایت او ثبت می‌شود.'],
            ['complaint','نارضایتی','مشتری ناراضی بوده و به خدمات پس از فروش ارجاع داده میشود'],
            ['service_request','درخواست خدمات','مشتری از اعتبار خود برای انتخاب یک یا چند خدمت استفاده می‌کند.'],
        ] as $route) {
            echo '<div class="bcrm-journey-card ' . ($customer->journey_state === $route[0] ? 'is-selected' : '') . '" data-bcrm-journey-card="' . esc_attr($route[0]) . '"><strong>' . esc_html($route[1]) . '</strong><span>' . esc_html($route[2]) . '</span>';
            if ($route[0] === 'no_request') echo '<select data-bcrm-satisfaction><option value="satisfied">کاملاً راضی</option><option value="relative">رضایت نسبی</option><option value="neutral">خنثی</option><option value="dissatisfied">ناراضی</option></select>';
            echo '<button type="button" class="bcrm-secondary-button" data-bcrm-select-journey="' . esc_attr($route[0]) . '" ' . disabled(!$has_declared, true, false) . '>انتخاب این مسیر</button></div>';
        }
        echo '</div><div class="bcrm-inline-feedback" data-bcrm-journey-feedback></div></section>';
    }

    private static function render_service_panel(object $customer, array $catalog, array $service_cases): void
    {
        $blocked = $customer->declared_paid === null;
        echo '<section class="bcrm-panel bcrm-service-panel' . ($blocked ? ' is-blocked' : '') . '" id="bcrm-services" data-bcrm-services-panel data-has-declared="' . ($blocked ? '0' : '1') . '" data-profile-complete="' . ($customer->profile_completeness === 'complete' ? '1' : '0') . '"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">خدمات و پروموشن‌ها</span><h3>انتخاب خدمات مشتری</h3><p>انتخاب خدمات حتی در صورت مغایرت مالی مجاز است. پروفایل ناقص هم‌زمان در کارتابل تأیید مالی باقی می‌ماند تا مغایرت برطرف شود.</p></div><div class="bcrm-credit-chip">سقف اعتبار / مانده فعلی: <b data-bcrm-credit-remaining>' . self::money($customer->service_credit_remaining) . '</b></div></div>';
        $pct = (float)$customer->service_credit_total > 0 ? min(100, (((float)$customer->service_credit_reserved + (float)$customer->service_credit_consumed) / (float)$customer->service_credit_total) * 100) : 0;
        echo '<div class="bcrm-credit-progress"><span data-bcrm-credit-progress style="width:' . esc_attr((string)$pct) . '%"></span></div>';
        echo '<form class="bcrm-service-selector" data-bcrm-service-selector data-customer-id="' . (int)$customer->id . '" data-credit-remaining="' . esc_attr((string)(float)$customer->service_credit_remaining) . '"><div class="bcrm-service-grid">';
        foreach ($catalog as $service) {
            $disabled = $blocked;
            echo '<label class="bcrm-service-option ' . ($disabled ? 'is-disabled' : '') . '"><input type="checkbox" name="service_ids[]" value="' . (int)$service->id . '" data-cost="' . esc_attr((string)(float)$service->credit_cost) . '" ' . disabled($disabled, true, false) . '><span class="bcrm-service-check"></span><div><strong>' . esc_html($service->name) . '</strong><span>' . esc_html($service->description ?: ('کارتابل مقصد: ' . ($service->unit_key ?: 'تعیین نشده'))) . '</span><b>' . self::money($service->credit_cost) . '</b></div></label>';
        }
        echo '</div><label class="bcrm-service-note"><span>توضیح انتخاب مشتری</span><textarea name="note" rows="2" placeholder="در صورت نیاز توضیح کوتاه"></textarea></label><div class="bcrm-selection-footer"><div><span>جمع انتخاب فعلی</span><strong data-bcrm-selection-total>۰ تومان</strong><small data-bcrm-selection-remaining>مانده پس از انتخاب: ' . self::money($customer->service_credit_remaining) . '</small></div><button type="submit" class="bcrm-primary-button" ' . disabled($blocked, true, false) . '>ثبت خدمات انتخابی</button></div><div class="bcrm-inline-feedback" data-bcrm-service-feedback></div></form>';
        echo '<h4 class="bcrm-subtitle">پرونده‌های خدمات مشتری</h4><div class="bcrm-service-cases" data-bcrm-service-cases>';
        if (!$service_cases) echo '<div class="bcrm-empty-state is-small"><span>هنوز خدمتی برای این مشتری انتخاب نشده است.</span></div>';
        foreach ($service_cases as $case) {
            echo '<article data-service-case-id="' . (int)$case->id . '"><div><strong>' . esc_html($case->service_name ?: 'خدمت') . '</strong><span>' . esc_html($case->case_code) . ' · ' . self::money($case->credit_amount) . '</span></div><div class="bcrm-case-actions"><span class="bcrm-badge is-primary" data-case-status-label>' . esc_html(self::service_case_status_label($case->status)) . '</span>';
            if (Biawin_CRM_Workflow_Service::can_supervise_unit((string)$case->unit_key)) {
                $agents=Biawin_CRM_Workflow_Service::unit_agents((string)$case->unit_key);
                if($agents){
                    echo '<form class="bcrm-mini-status" data-bcrm-service-assign-form><input type="hidden" name="case_id" value="'.(int)$case->id.'"><select name="agent_user_id"><option value="">تخصیص کارشناس…</option>';
                    foreach($agents as $agent) echo '<option value="'.(int)$agent->ID.'" '.selected((int)$case->assigned_user_id,(int)$agent->ID,false).'>'.esc_html($agent->display_name).'</option>';
                    echo '</select><button type="submit" class="bcrm-secondary-button">تخصیص</button></form>';
                }
            }
            if (current_user_can('bcrm_manage_cases') || (int)$case->assigned_user_id === get_current_user_id()) {
                echo '<form class="bcrm-mini-status" data-bcrm-service-status-form><input type="hidden" name="case_id" value="' . (int)$case->id . '"><select name="status"><option value="assigned" ' . selected($case->status,'assigned',false) . '>تخصیص‌یافته</option><option value="in_progress" ' . selected($case->status,'in_progress',false) . '>در حال انجام</option><option value="waiting" ' . selected($case->status,'waiting',false) . '>در انتظار</option><option value="completed" ' . selected($case->status,'completed',false) . '>تکمیل‌شده</option><option value="rejected" ' . selected($case->status,'rejected',false) . '>ردشده</option><option value="cancelled" ' . selected($case->status,'cancelled',false) . '>لغوشده</option></select><button type="submit" class="bcrm-secondary-button">ثبت</button></form>';
            }
            echo '</div></article>';
        }
        echo '</div></section>';
    }

    /**
     * v0.8.64.0 — complaint subject field: a dropdown of the ACTIVE admin-configured subjects
     * («تنظیمات نارضایتی»); legacy free text only while no subject is configured.
     */
    private static function complaint_subject_field(): string
    {
        $items = class_exists('Biawin_CRM_After_Sales_Config') ? Biawin_CRM_After_Sales_Config::items(Biawin_CRM_After_Sales_Config::CATALOG_SUBJECT, true) : [];
        if (!$items) return '<input name="subject" required>';
        $html = '<select name="subject_id" required><option value="">انتخاب موضوع</option>';
        foreach ($items as $item) $html .= '<option value="' . (int)$item->id . '">' . esc_html($item->label) . '</option>';
        return $html . '</select>';
    }

    /** v0.8.64.0 — outcome options (labels/order/active) from the outcome catalog. */
    private static function complaint_outcome_select(): string
    {
        $options = Biawin_CRM_Workflow_Service::complaint_resolution_options();
        if (!$options) return '<select name="outcome" disabled><option value="">نتیجه فعالی تعریف نشده است</option></select>';
        $html = '<select name="outcome">';
        foreach ($options as $key => $label) $html .= '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        return $html . '</select>';
    }

    private static function render_complaint_panel(object $customer, array $complaints, array $refunds): void
    {
        echo '<section class="bcrm-panel" id="bcrm-complaints"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">خدمات پس از فروش</span><h3>نارضایتی و عودت وجه</h3><p>نارضایتی می‌تواند به رضایت، درخواست خدمت یا عودت وجه ختم شود.</p></div></div>';
        echo '<details class="bcrm-details-card"><summary>ثبت نارضایتی جدید</summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bcrm-form">';
        wp_nonce_field('bcrm_front_complaint');
        echo '<input type="hidden" name="action" value="bcrm_front_complaint"><input type="hidden" name="customer_id" value="' . (int)$customer->id . '"><div class="bcrm-form-grid"><label><span>موضوع</span>' . self::complaint_subject_field() . '</label><label><span>واحد مرتبط</span><input name="related_unit"></label><label><span>اولویت</span><select name="priority"><option value="normal">عادی</option><option value="high">بالا</option><option value="critical">بحرانی</option></select></label></div><label class="bcrm-full"><span>شرح نارضایتی</span><textarea name="description" rows="4" required></textarea></label><button class="bcrm-primary-button">ثبت و ارجاع به خدمات پس از فروش</button></form></details>';

        if ($complaints) {
            echo '<div class="bcrm-complaint-list">';
            foreach ($complaints as $c) {
                echo '<article><div class="bcrm-complaint-head"><div><strong>' . esc_html($c->subject ?: 'نارضایتی مشتری') . '</strong><span>#' . (int)$c->id . ' · ' . esc_html(self::date_label($c->created_at)) . '</span></div><span class="bcrm-badge ' . ($c->status === 'open' ? 'is-danger' : 'is-success') . '">' . esc_html($c->status === 'open' ? 'باز' : 'بسته') . '</span></div><p>' . esc_html($c->description ?: '-') . '</p>';
                if ($c->status === 'open' && (current_user_can('bcrm_complaints') || current_user_can('bcrm_manage_cases'))) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bcrm-inline-resolution">';
                    wp_nonce_field('bcrm_front_complaint_resolution');
                    echo '<input type="hidden" name="action" value="bcrm_front_complaint_resolution"><input type="hidden" name="customer_id" value="' . (int)$customer->id . '"><input type="hidden" name="complaint_id" value="' . (int)$c->id . '">' . self::complaint_outcome_select() . '<input name="note" placeholder="توضیح نتیجه"><button class="bcrm-secondary-button">ثبت نتیجه</button></form>';
                }
                echo '</article>';
            }
            echo '</div>';
        }
        if ($refunds) {
            echo '<h4 class="bcrm-subtitle">پرونده‌های عودت</h4><div class="bcrm-service-cases">';
            foreach ($refunds as $r) echo '<article><div><strong>' . esc_html($r->refund_code) . '</strong><span>' . esc_html($r->reason ?: 'عودت وجه') . '</span></div><span class="bcrm-badge is-warning">' . esc_html($r->status) . '</span></article>';
            echo '</div>';
        }
        echo '</section>';
    }

    private static function render_timeline_panel(array $timeline, array $interactions): void
    {
        echo '<section class="bcrm-panel" id="bcrm-history"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">تاریخچه یکپارچه</span><h3>Timeline مشتری</h3><p>همه واحدها باید سوابق قبلی مشتری را قبل از تماس مطالعه کنند.</p></div></div><div class="bcrm-timeline">';
        if (!$timeline && !$interactions) echo '<div class="bcrm-empty-state"><span>هنوز فعالیتی ثبت نشده است.</span></div>';
        foreach ($timeline as $item) {
            echo '<article><span class="bcrm-timeline-dot"></span><div><strong>' . esc_html($item->title) . '</strong><span>' . esc_html(($item->actor_name ?: 'سیستم') . ' · ' . self::date_label($item->created_at)) . '</span></div></article>';
        }
        foreach ($interactions as $i) {
            echo '<article><span class="bcrm-timeline-dot"></span><div><strong>تماس / اقدام کارشناس: ' . esc_html($i->outcome ?: 'ثبت اقدام') . '</strong><span>' . esc_html(($i->agent_name ?: 'کارشناس') . ' · ' . self::date_label($i->created_at)) . '</span>' . ($i->notes ? '<p>' . esc_html($i->notes) . '</p>' : '') . '</div></article>';
        }
        echo '</div></section>';
    }

    private static function render_phone_card(int $customer_id, array $phones): void
    {
        echo '<section class="bcrm-panel bcrm-side-card"><div class="bcrm-section-head"><div><h3>شماره‌های تماس</h3><p>شماره‌های واردشده از اکسل ابتدا «تأییدنشده» هستند. کارشناس پس از تأیید شفاهی مالکیت/ارتباط شماره آن را تأیید می‌کند. «اصلی» بودن مستقل از تأیید است.</p></div></div><ul class="bcrm-phone-list" id="bcrm-phone-list">';
        if (!$phones) echo '<li><div><strong>شماره‌ای ثبت نشده</strong><span>از «افزودن شماره و فاکتور» استفاده کنید.</span></div></li>';
        foreach ($phones as $p) {
            $manual_phone = in_array((string)($p->source ?? ''), ['agent','manual_invoice_bundle'], true);
            echo '<li data-phone="' . esc_attr($p->normalized_phone) . '" data-phone-id="' . (int)$p->id . '" data-phone-source="' . esc_attr((string)($p->source ?? '')) . '"><div><strong data-bcrm-phone-value>' . esc_html($p->normalized_phone) . '</strong><span data-phone-status-label>' . esc_html(self::phone_status_label($p->phone_status)) . ($manual_phone ? ' · ثبت دستی' : '') . '</span></div><div class="bcrm-phone-actions">' . ($manual_phone ? '<button type="button" class="bcrm-link-button" data-bcrm-edit-manual-phone>ویرایش</button>' : '') . (current_user_can('bcrm_manage_customer_phones') ? '<button type="button" class="bcrm-link-button is-danger" data-bcrm-detach-phone>جدا کردن شماره از پروفایل</button>' : '') . ((int)$p->is_primary ? '<span class="bcrm-badge is-primary">اصلی</span>' : '<button type="button" class="bcrm-link-button" data-bcrm-phone-primary>اصلی کن</button>') . (in_array($p->phone_status, ['verified','verified_primary'], true) ? '<button type="button" class="bcrm-link-button" data-bcrm-phone-unverify>لغو تأیید</button>' : '<button type="button" class="bcrm-link-button" data-bcrm-phone-verify>تأیید شماره</button>') . '</div></li>';
        }
        echo '</ul><button type="button" class="bcrm-secondary-button bcrm-full-button" data-bcrm-toggle-related>افزودن شماره و فاکتور</button></section>';
    }

    private static function render_profile_status_card(object $customer, array $financial_cases, array $attachments): void
    {
        global $wpdb;
        $workflow_case = class_exists('Biawin_CRM_Deficiency_Workflow') ? $wpdb->get_row($wpdb->prepare(
            'SELECT d.*, a.title last_action_title, a.created_at last_action_at FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' d LEFT JOIN ' . Biawin_CRM_DB::table('activities') . ' a ON a.id=(SELECT MAX(ax.id) FROM ' . Biawin_CRM_DB::table('activities') . ' ax WHERE ax.customer_id=d.customer_id AND ax.object_type=%s AND ax.object_id=d.id) WHERE d.customer_id=%d ORDER BY d.id DESC LIMIT 1',
            'deficiency_case',
            (int)$customer->id
        )) : null;
        if ($workflow_case) {
            $labels = Biawin_CRM_Deficiency_Workflow::status_labels();
            $agent_id = (string)$workflow_case->current_department === 'finance' ? (int)$workflow_case->finance_agent_id : (int)$workflow_case->deficiency_agent_id;
            $agent = $agent_id ? get_user_by('id', $agent_id) : null;
            $dept = (string)$workflow_case->current_department === 'finance' ? 'تأیید مالی' : 'رفع ناقصی';
            echo '<section class="bcrm-panel bcrm-side-card bcrm-workflow-status"><div class="bcrm-section-head"><div><h3>وضعیت رفع ناقصی / مالی</h3></div></div><div class="bcrm-status-stack">';
            echo '<div><span>وضعیت فعلی</span><strong>' . esc_html($labels[$workflow_case->status] ?? $workflow_case->status) . '</strong></div>';
            echo '<div><span>واحد فعلی</span><strong>' . esc_html($dept) . '</strong></div>';
            echo '<div><span>کارشناس فعلی</span><strong>' . esc_html($agent ? $agent->display_name : 'تخصیص‌نیافته') . '</strong></div>';
            echo '<div><span>مبلغ اختلاف</span><strong>' . self::money(abs((float)$workflow_case->difference_amount)) . '</strong></div>';
            echo '<div><span>آخرین اقدام</span><strong>' . esc_html($workflow_case->last_action_title ?: '-') . '</strong></div>';
            echo '<div><span>تاریخ آخرین اقدام</span><strong>' . esc_html(self::date_label($workflow_case->last_action_at ?: $workflow_case->updated_at)) . '</strong></div>';
            echo '</div><a class="bcrm-secondary-button bcrm-full-button" href="' . esc_url(Biawin_CRM_Auth::portal_url(['bcrm_view'=>(string)$workflow_case->current_department === 'finance' ? 'financial' : 'deficiency', 'case_id'=>(int)$workflow_case->id])) . '">باز کردن پرونده Workflow</a></section>';
        }
        echo '<section class="bcrm-panel bcrm-side-card"><div class="bcrm-section-head"><div><h3>وضعیت پروفایل</h3></div></div><div class="bcrm-status-stack">' . self::profile_badge($customer->profile_completeness) . '<div><span>مبلغ فاکتورها</span><strong>' . self::money($customer->total_paid) . '</strong></div><div><span>مبلغ اعلامی</span><strong>' . ($customer->declared_paid === null ? 'ثبت نشده' : self::money($customer->declared_paid)) . '</strong></div><div><span>اختلاف</span><strong>' . self::money(abs((float)$customer->discrepancy_amount)) . '</strong></div></div>';
        if ($financial_cases) {
            $case = $financial_cases[0];
            echo '<div class="bcrm-financial-case"><small>آخرین پرونده مالی</small><strong>' . esc_html($case->case_code) . '</strong><span>' . esc_html(self::financial_status_label($case->status)) . '</span></div>';
        }
        if ($customer->profile_completeness === 'incomplete' && (current_user_can('bcrm_financial_verify') || current_user_can('bcrm_manage_cases'))) {
            echo '<details class="bcrm-financial-add"><summary>ثبت شماره + فاکتور / پرداخت پیدا شده</summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="bcrm-compact-form">';
            wp_nonce_field('bcrm_front_invoice_phone_bundle');
            echo '<input type="hidden" name="action" value="bcrm_front_invoice_phone_bundle"><input type="hidden" name="customer_id" value="' . (int)$customer->id . '"><input name="phone" placeholder="شماره موبایل مرتبط با فاکتور" required><input name="invoice_code" placeholder="کد فاکتور" required><input name="amount" placeholder="مبلغ واریزی (فقط برای فاکتور جدید)"><textarea name="payment_info" rows="2" placeholder="اطلاعات واریز / شماره پیگیری"></textarea><button class="bcrm-primary-button">ثبت و محاسبه مجدد</button></form></details>';
            echo '<details class="bcrm-financial-add"><summary>الحاق فیش / مدرک واریزی</summary><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" class="bcrm-compact-form">';
            wp_nonce_field('bcrm_front_financial_evidence');
            echo '<input type="hidden" name="action" value="bcrm_front_financial_evidence"><input type="hidden" name="customer_id" value="' . (int)$customer->id . '"><input type="file" name="evidence_file" accept="image/*,.pdf" required><input name="note" placeholder="توضیح مدرک / شماره پیگیری"><button class="bcrm-secondary-button">آپلود مدرک</button></form></details>';
        }
        if ($attachments) {
            echo '<div class="bcrm-attachment-list"><small>مدارک مالی</small>';
            foreach ($attachments as $a) {
                $url=wp_get_attachment_url((int)$a->wp_attachment_id);
                if ($url) echo '<a target="_blank" rel="noopener" href="'.esc_url($url).'">'.esc_html($a->note ?: ($a->post_title ?: 'مدرک واریزی')).'</a>';
            }
            echo '</div>';
        }
        echo '</section>';
    }

    private static function render_source_services_card(array $services): void
    {
        echo '<section class="bcrm-panel bcrm-side-card"><div class="bcrm-section-head"><div><h3>خدمات ثبت‌شده در CRM</h3><p>خدمات واردشده از فایل مرجع</p></div></div>';
        if (!$services) echo '<div class="bcrm-empty-state is-small"><span>خدمتی ثبت نشده است.</span></div>';
        else echo '<ul class="bcrm-source-services">' . implode('', array_map(fn($s) => '<li><strong>' . esc_html($s->service_name) . '</strong><span>' . esc_html($s->service_status ?: 'بدون وضعیت') . '</span></li>', $services)) . '</ul>';
        echo '</section>';
    }

    private static function render_search_and_attach(int $customer_id): void
    {
        echo '<section class="bcrm-related-panel" data-bcrm-related-panel hidden><div class="bcrm-related-dialog"><div class="bcrm-section-head"><div><span class="bcrm-eyebrow">تکمیل هویت و مبلغ</span><h3>افزودن شماره / فاکتور</h3><p>شماره‌ای که مشتری اعلام می‌کند را جست‌وجو کنید. اگر فاکتور مرتبط وجود داشت آن را متصل کنید؛ اگر شماره در پرونده‌ها نبود، می‌توانید شماره را بدون کد فاکتور و مبلغ ثبت کنید.</p></div><button type="button" class="bcrm-icon-button" data-bcrm-close-related>×</button></div><div class="bcrm-related-search"><input data-bcrm-related-query placeholder="شماره موبایل، کد فاکتور یا نام"><button type="button" class="bcrm-primary-button" data-bcrm-related-search>جست‌وجو</button></div><input type="hidden" data-bcrm-customer-id value="' . (int)$customer_id . '"><div class="bcrm-related-results" data-bcrm-related-results></div><div class="bcrm-result-message"></div><details class="bcrm-details-card bcrm-manual-bundle"><summary>شماره در پرونده‌ها نیست؟ ثبت شماره جدید</summary><form data-bcrm-manual-bundle-form class="bcrm-form"><input type="hidden" name="customer_id" value="' . (int)$customer_id . '"><div class="bcrm-form-grid"><label><span>شماره موبایل</span><input name="phone" required></label><label><span>کد فاکتور <small>(اختیاری)</small></span><input name="invoice_code" placeholder="در صورت وجود"></label><label><span>مبلغ واریزی <small>(اختیاری)</small></span><input name="amount" inputmode="numeric" placeholder="در صورت اطلاع"></label></div><label class="bcrm-full"><span>اطلاعات واریز <small>(اختیاری)</small></span><textarea name="payment_info" rows="2"></textarea></label><button type="submit" class="bcrm-primary-button">ثبت شماره</button><div class="bcrm-inline-feedback" data-bcrm-manual-feedback></div></form></details></div></section>';
    }

    public static function handle_interaction(): void
    {
        self::require_action('bcrm_front_interaction', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        if (!self::can_access_customer($id)) wp_die('شما به این پرونده دسترسی ندارید.');
        $request = new WP_REST_Request('POST', '/biawin-crm/v1/customers/' . $id . '/interaction');
        $request->set_url_params(['id' => $id]);
        $request->set_body_params(array_merge(wp_unslash($_POST), ['customer_id'=>$id]));
        $response = Biawin_CRM_REST::create_interaction($request);
        $data = $response->get_data();
        self::redirect_customer($id, !empty($data['ok']) ? 'اطلاعات تماس ثبت و پروفایل بروزرسانی شد.' : ($data['message'] ?? 'ثبت انجام نشد.'));
    }

    public static function handle_attach_invoice(): void
    {
        self::require_action('bcrm_front_attach_invoice', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        if (!self::can_access_customer($id)) wp_die('شما به این پرونده دسترسی ندارید.');
        $invoice_code = sanitize_text_field(wp_unslash($_POST['invoice_code'] ?? ''));
        $result = Biawin_CRM_Customer_Service::attach_invoice($id, $invoice_code, 'اتصال فقط فاکتور توسط کارشناس');
        self::redirect_customer($id, $result['message'] ?? 'عملیات انجام شد.');
    }

    public static function handle_add_phone(): void
    {
        wp_die('افزودن شماره به‌تنهایی غیرفعال است. شماره و فاکتور باید هم‌زمان ثبت شوند.');
    }

    public static function handle_invoice_phone_bundle(): void
    {
        self::require_action('bcrm_front_invoice_phone_bundle', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        if (!self::can_access_customer($id)) wp_die('شما به این پرونده دسترسی ندارید.');
        $result = Biawin_CRM_Customer_Service::create_manual_invoice_with_phone(
            $id,
            sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['invoice_code'] ?? '')),
            Biawin_CRM_Normalizer::money($_POST['amount'] ?? 0),
            sanitize_textarea_field(wp_unslash($_POST['payment_info'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['name'] ?? ''))
        );
        self::redirect_customer($id, $result['message'] ?? 'عملیات انجام شد.');
    }

    public static function handle_financial_evidence(): void
    {
        self::require_action('bcrm_front_financial_evidence', 'bcrm_work_cases');
        $id=absint($_POST['customer_id']??0);
        if (!self::can_access_customer($id) || (!current_user_can('bcrm_financial_verify') && !current_user_can('bcrm_manage_cases'))) wp_die('دسترسی غیرمجاز است.');
        if (empty($_FILES['evidence_file']['name'])) self::redirect_customer($id,'فایلی انتخاب نشده است.');
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload('evidence_file', 0, [], [
            'test_form' => false,
            'mimes' => ['jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf']
        ]);
        if (is_wp_error($attachment_id)) self::redirect_customer($id,'آپلود مدرک ناموفق بود: '.$attachment_id->get_error_message());
        global $wpdb;
        $case_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM ".Biawin_CRM_DB::table('financial_cases')." WHERE customer_id=%d AND status NOT IN ('resolved','closed','cancelled') ORDER BY id DESC LIMIT 1",$id));
        $saved = Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('attachments'),[
            'customer_id'=>$id,'object_type'=>'financial_case','object_id'=>$case_id?:null,'wp_attachment_id'=>(int)$attachment_id,
            'file_kind'=>'payment_evidence','note'=>sanitize_text_field(wp_unslash($_POST['note']??'')),'uploaded_by'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now()
        ]);
        if ($saved === false || !(int)$wpdb->insert_id) {
            wp_delete_attachment((int)$attachment_id, true);
            self::redirect_customer($id,'فایل آپلود شد اما ثبت آن در پرونده مالی ناموفق بود. دوباره تلاش کنید.');
        }
        Biawin_CRM_Workflow_Service::activity($id,'financial_evidence_uploaded','مدرک واریزی مشتری بارگذاری شد.','attachment',(int)$wpdb->insert_id,['wp_attachment_id'=>(int)$attachment_id]);
        self::redirect_customer($id,'مدرک واریزی با موفقیت بارگذاری شد.');
    }

    public static function handle_journey(): void
    {
        self::require_action('bcrm_front_journey', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        if (!self::can_access_customer($id)) wp_die('شما به این پرونده دسترسی ندارید.');
        $result = Biawin_CRM_Workflow_Service::set_customer_journey($id, sanitize_key($_POST['journey'] ?? ''), sanitize_key($_POST['satisfaction'] ?? ''), 'مسیر توسط کارشناس انتخاب شد.');
        self::redirect_customer($id, $result['ok'] ? 'مسیر مشتری ثبت شد.' : $result['message']);
    }

    public static function handle_complaint(): void
    {
        self::require_action('bcrm_front_complaint', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        if (!self::can_access_customer($id)) wp_die('شما به این پرونده دسترسی ندارید.');
        $result = Biawin_CRM_Workflow_Service::create_complaint($id, wp_unslash($_POST));
        self::redirect_customer($id, $result['ok'] ? 'نارضایتی ثبت و به خدمات پس از فروش ارجاع شد.' : $result['message']);
    }

    public static function handle_services(): void
    {
        self::require_action('bcrm_front_services', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        if (!self::can_access_customer($id)) wp_die('شما به این پرونده دسترسی ندارید.');
        $result = Biawin_CRM_Workflow_Service::select_services($id, $_POST['service_ids'] ?? [], sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));
        self::redirect_customer($id, $result['ok'] ? 'پرونده خدمات انتخابی ایجاد شد.' : $result['message']);
    }

    public static function handle_complaint_resolution(): void
    {
        if (!is_user_logged_in() || !(current_user_can('bcrm_complaints') || current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_front_complaint_resolution')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        $id = absint($_POST['customer_id'] ?? 0);
        $complaint_id = absint($_POST['complaint_id'] ?? 0);
        if (!Biawin_CRM_Workflow_Service::can_manage_complaint($complaint_id)) wp_die('این پرونده نارضایتی در دسترس شما نیست.');
        $result = Biawin_CRM_Workflow_Service::resolve_complaint($complaint_id, sanitize_key($_POST['outcome'] ?? ''), sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));
        self::redirect_customer($id, $result['ok'] ? 'نارضایتی تعیین تکلیف شد.' : $result['message']);
    }

    public static function handle_service_case(): void
    {
        self::require_action('bcrm_front_service_case', 'bcrm_work_cases');
        $id = absint($_POST['customer_id'] ?? 0);
        $result = Biawin_CRM_Workflow_Service::update_service_case(absint($_POST['case_id'] ?? 0), sanitize_key($_POST['status'] ?? ''), sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')));
        self::redirect_customer($id, $result['ok'] ? 'وضعیت خدمت بروزرسانی شد.' : $result['message']);
    }

    public static function handle_manual_invoice(): void
    {
        wp_die('ثبت فاکتور به‌تنهایی غیرفعال است. شماره و فاکتور باید هم‌زمان ثبت شوند.');
    }

    public static function ajax_search_related(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $query = sanitize_text_field(wp_unslash($_POST['query'] ?? ''));
        if (mb_strlen($query) < 2) wp_send_json_error(['message'=>'حداقل دو کاراکتر وارد کنید.']);
        $results = Biawin_CRM_Customer_Service::search_related($query);
        wp_send_json_success(['results'=>$results]);
    }

    public static function ajax_attach_invoice(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $customer_id = self::ajax_customer_id();
        $invoice_code = sanitize_text_field(wp_unslash($_POST['invoice_code'] ?? ''));
        if ($invoice_code === '') wp_send_json_error(['message'=>'کد فاکتور الزامی است.']);
        $result = Biawin_CRM_Customer_Service::attach_invoice($customer_id, $invoice_code, 'اتصال فقط فاکتور توسط کارشناس');
        if (empty($result['ok'])) wp_send_json_error(['message'=>$result['message'] ?? 'اتصال فاکتور انجام نشد.']);
        wp_send_json_success(['message'=>$result['message'],'invoice'=>Biawin_CRM_Customer_Service::invoice_payload($invoice_code),'profile_deleted'=>!empty($result['profile_deleted']),'profile_count'=>$result['profile_count']??null,'snapshot'=>self::profile_snapshot($customer_id)]);
    }

    public static function ajax_add_phone(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases') || !current_user_can('bcrm_manage_customer_phones')) {
            wp_send_json_error(['message'=>'شما دسترسی افزودن شماره به پروفایل را ندارید.'],403);
        }
        $customer_id = self::ajax_customer_id();
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $result = Biawin_CRM_Customer_Service::add_phone($customer_id, $phone, 'unverified', 'self');
        if (empty($result['ok'])) wp_send_json_error(['message'=>$result['message'] ?? 'ثبت شماره ناموفق بود.']);
        wp_send_json_success($result);
    }

    public static function ajax_add_invoice_phone(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $customer_id = self::ajax_customer_id();
        $invoice_id = absint($_POST['invoice_id'] ?? 0);
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $result = Biawin_CRM_Customer_Service::add_phone_to_invoice($customer_id, $invoice_id, $phone);
        if (empty($result['ok'])) wp_send_json_error(['message'=>$result['message'] ?? 'ثبت شماره روی فاکتور ناموفق بود.']);
        wp_send_json_success($result + ['snapshot'=>self::profile_snapshot($customer_id)]);
    }

    public static function ajax_attach_bundle(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $customer_id = self::ajax_customer_id();
        $invoice_code = sanitize_text_field(wp_unslash($_POST['invoice_code'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $searched = sanitize_text_field(wp_unslash($_POST['searched_query'] ?? ''));
        $extras = [];
        if ($phone !== '') $extras[] = $phone;
        $searched_phone = Biawin_CRM_Normalizer::phone($searched);
        if ($searched_phone !== '') $extras[] = $searched_phone;
        $result = Biawin_CRM_Customer_Service::attach_invoice_and_phones($customer_id, $invoice_code, array_values(array_unique($extras)));
        if (empty($result['ok'])) wp_send_json_error(['message'=>$result['message'] ?? 'اتصال انجام نشد.']);
        wp_send_json_success(['message'=>$result['message'],'invoice'=>Biawin_CRM_Customer_Service::invoice_payload($invoice_code),'phones'=>$result['phones'] ?? [],'profile_deleted'=>!empty($result['profile_deleted']),'profile_count'=>$result['profile_count']??null,'snapshot'=>self::profile_snapshot($customer_id)]);
    }

    public static function ajax_preview_reconciliation(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $customer_id = self::ajax_customer_id();
        global $wpdb;
        $declared = Biawin_CRM_Normalizer::money($_POST['declared_paid'] ?? '');
        $has_value = trim((string)($_POST['declared_paid'] ?? '')) !== '';
        if (!$has_value) wp_send_json_success(['has_value'=>false]);
        $total = (float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(CASE WHEN COALESCE(exclude_from_total,0)=1 THEN 0 ELSE amount_paid END),0) FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d', $customer_id));
        $tolerance = (float)Biawin_CRM_Workflow_Service::settings()['reconciliation_tolerance'];
        $difference = $declared - $total;
        $complete = abs($difference) <= $tolerance;
        // Financial mismatch does not block service selection. Credit is always based on current invoice total.
        $credit = Biawin_CRM_Workflow_Service::calculate_credit($total);
        $reserved = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(credit_amount),0) FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND status IN ('unassigned','assigned','in_progress','waiting')", $customer_id));
        $consumed = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(credit_amount),0) FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND status='completed'", $customer_id));
        $remaining = $credit - $reserved - $consumed;
        wp_send_json_success([
            'has_value'=>true,'complete'=>$complete,'profile_completeness'=>$complete?'complete':'incomplete',
            'declared'=>$declared,'total'=>$total,'difference'=>$difference,'abs_difference'=>abs($difference),
            'credit'=>$credit,'reserved'=>$reserved,'consumed'=>$consumed,'remaining'=>$remaining,
            'message'=>$complete?'مبلغ اعلامی با مجموع فاکتورها برابر است؛ پروفایل قابل تکمیل است.':'مغایرت مالی وجود دارد؛ پس از ثبت تماس، پرونده به تأیید مالی می‌رود.'
        ]);
    }

    public static function ajax_save_interaction(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id = self::ajax_customer_id();
        $request = new WP_REST_Request('POST', '/biawin-crm/v1/customers/' . $id . '/interaction');
        $request->set_url_params(['id' => $id]);
        $request->set_body_params(array_merge(wp_unslash($_POST), ['customer_id'=>$id]));
        $response = Biawin_CRM_REST::create_interaction($request);
        $data = $response->get_data();
        if (empty($data['ok'])) wp_send_json_error(['message'=>$data['message'] ?? 'ثبت تماس انجام نشد.']);
        wp_send_json_success(['message'=>'اطلاعات تماس ذخیره شد.','interaction_id'=>$data['interaction_id'] ?? 0,'snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_set_journey(): void
    {
        check_ajax_referer('bcrm_front_ajax', 'nonce');
        if (!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $journey=sanitize_key($_POST['journey']??'');
        $result=Biawin_CRM_Workflow_Service::set_customer_journey($id,$journey,sanitize_key($_POST['satisfaction']??''),'مسیر توسط کارشناس در پنل سریع انتخاب شد.');
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'ثبت مسیر انجام نشد.']);
        wp_send_json_success(['message'=>$result['message'] ?? 'مسیر مشتری ثبت شد.','journey'=>$journey,'profile_completeness'=>$result['profile_completeness'] ?? '', 'snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_select_services(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $ids=$_POST['service_ids']??[];
        if(!is_array($ids)) $ids=preg_split('/[,،]+/',(string)$ids);
        $route=Biawin_CRM_Workflow_Service::set_customer_journey($id,'service_request','','درخواست خدمات توسط کارشناس ثبت شد.');
        if(empty($route['ok'])) wp_send_json_error(['message'=>$route['message']??'مسیر خدمات قابل ثبت نیست.']);
        $result=Biawin_CRM_Workflow_Service::select_services($id,$ids,sanitize_textarea_field(wp_unslash($_POST['note']??'')));
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'خدمات ثبت نشد.']);
        global $wpdb;
        $cases=$wpdb->get_results($wpdb->prepare('SELECT sc.*,s.name service_name FROM '.Biawin_CRM_DB::table('service_cases').' sc LEFT JOIN '.Biawin_CRM_DB::table('service_catalog').' s ON s.id=sc.service_id WHERE sc.customer_id=%d ORDER BY sc.id DESC',$id));
        wp_send_json_success(['message'=>'خدمات انتخاب و پرونده‌های تخصصی ایجاد شدند.','case_ids'=>$result['case_ids']??[],'cases'=>array_map([__CLASS__,'service_case_payload'],$cases),'snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_create_complaint(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $result=Biawin_CRM_Workflow_Service::create_complaint($id,wp_unslash($_POST));
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'ثبت نارضایتی انجام نشد.']);
        wp_send_json_success(['message'=>'نارضایتی ثبت و به کارتابل خدمات پس از فروش ارجاع شد.','complaint_id'=>$result['id']??0,'snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_update_service_case(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!(current_user_can('bcrm_services')||current_user_can('bcrm_manage_cases'))) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $case_id=absint($_POST['case_id']??0);
        $result=Biawin_CRM_Workflow_Service::update_service_case($case_id,sanitize_key($_POST['status']??''),sanitize_textarea_field(wp_unslash($_POST['note']??'')));
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'بروزرسانی انجام نشد.']);
        global $wpdb;
        $case=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('service_cases').' WHERE id=%d',$case_id));
        wp_send_json_success(['message'=>'وضعیت خدمت بروزرسانی شد.','status'=>$case->status??'','status_label'=>self::service_case_status_label($case->status??''),'snapshot'=>$case?self::profile_snapshot((int)$case->customer_id):null]);
    }

    public static function ajax_assign_service_case_front(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_services') && !current_user_can('bcrm_manage_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $case_id=absint($_POST['case_id']??0); $agent_id=absint($_POST['agent_user_id']??0);
        if(!$case_id||!$agent_id) wp_send_json_error(['message'=>'پرونده و کارشناس را انتخاب کنید.']);
        $result=Biawin_CRM_Workflow_Service::assign_service_case($case_id,$agent_id);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'تخصیص انجام نشد.']);
        wp_send_json_success(['message'=>'پرونده خدمت تخصیص داده شد.']);
    }

    public static function ajax_phone_verification(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $phone_id=absint($_POST['phone_id']??0);
        $status=sanitize_key($_POST['status']??'unverified');
        $make_primary=!empty($_POST['make_primary']);
        $result=Biawin_CRM_Customer_Service::set_phone_verification($id,$phone_id,$status,$make_primary);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'وضعیت شماره ذخیره نشد.']);
        wp_send_json_success(['message'=>$result['message'],'snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_edit_manual_phone(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $phone_id=absint($_POST['phone_id']??0);
        $phone=sanitize_text_field(wp_unslash($_POST['phone']??''));
        $result=Biawin_CRM_Customer_Service::update_manual_phone($id,$phone_id,$phone);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'ویرایش شماره انجام نشد.']);
        wp_send_json_success(['message'=>$result['message'],'phone'=>$result['phone']??'','snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_detach_customer_phone(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases') || !current_user_can('bcrm_manage_customer_phones')) wp_send_json_error(['message'=>'شما دسترسی جدا کردن شماره از پروفایل را ندارید.'],403);
        $id=self::ajax_customer_id();
        $phone_id=absint($_POST['phone_id']??0);
        $result=Biawin_CRM_Customer_Service::detach_phone_to_new_customer($id,$phone_id);
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'جدا کردن شماره انجام نشد.']);
        wp_send_json_success(['message'=>$result['message'],'phone'=>$result['phone']??'','new_customer_id'=>$result['new_customer_id']??0,'snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_edit_manual_invoice(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $invoice_id=absint($_POST['invoice_id']??0);
        $result=Biawin_CRM_Customer_Service::update_manual_invoice(
            $id,
            $invoice_id,
            sanitize_text_field(wp_unslash($_POST['invoice_code']??'')),
            Biawin_CRM_Normalizer::money($_POST['amount']??0),
            sanitize_textarea_field(wp_unslash($_POST['payment_info']??'')),
            sanitize_text_field(wp_unslash($_POST['name']??''))
        );
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'ویرایش فاکتور انجام نشد.']);
        wp_send_json_success(['message'=>$result['message'],'invoice_code'=>$result['invoice_code']??'','snapshot'=>self::profile_snapshot($id)]);
    }

    public static function ajax_manual_bundle(): void
    {
        check_ajax_referer('bcrm_front_ajax','nonce');
        if(!current_user_can('bcrm_work_cases')) wp_send_json_error(['message'=>'دسترسی غیرمجاز است.'],403);
        $id=self::ajax_customer_id();
        $result=Biawin_CRM_Customer_Service::create_manual_invoice_with_phone($id,sanitize_text_field(wp_unslash($_POST['phone']??'')),sanitize_text_field(wp_unslash($_POST['invoice_code']??'')),Biawin_CRM_Normalizer::money($_POST['amount']??0),sanitize_textarea_field(wp_unslash($_POST['payment_info']??'')),sanitize_text_field(wp_unslash($_POST['name']??'')));
        if(empty($result['ok'])) wp_send_json_error(['message'=>$result['message']??'ثبت انجام نشد.']);
        $code=sanitize_text_field(wp_unslash($_POST['invoice_code']??''));
        wp_send_json_success(['message'=>$result['message']??'اطلاعات ثبت شد.','invoice'=>$code !== '' ? Biawin_CRM_Customer_Service::invoice_payload($code) : null,'phone_only'=>!empty($result['phone_only']),'snapshot'=>self::profile_snapshot($id)]);
    }

    private static function profile_snapshot(int $customer_id): array
    {
        global $wpdb;
        Biawin_CRM_DB::read_refresh($customer_id, static function () use ($customer_id) { Biawin_CRM_Customer_Service::recalculate_customer($customer_id); });
        $c=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('customers').' WHERE id=%d',$customer_id));
        $phones=$wpdb->get_col($wpdb->prepare('SELECT normalized_phone FROM '.Biawin_CRM_DB::table('phones').' WHERE customer_id=%d ORDER BY is_primary DESC,id ASC',$customer_id));
        return [
            'customer_id'=>$customer_id,'profile_completeness'=>$c->profile_completeness??'pending','journey_state'=>$c->journey_state??'',
            'total_paid'=>(float)($c->total_paid??0),'declared_paid'=>$c&&$c->declared_paid!==null?(float)$c->declared_paid:null,
            'difference'=>(float)($c->discrepancy_amount??0),'service_credit_total'=>(float)($c->service_credit_total??0),
            'service_credit_reserved'=>(float)($c->service_credit_reserved??0),'service_credit_consumed'=>(float)($c->service_credit_consumed??0),
            'service_credit_remaining'=>(float)($c->service_credit_remaining??0),'customer_code'=>$c->customer_code??'','phones'=>$phones,
        ];
    }

    public static function service_case_payload(object $case): array
    {
        return ['id'=>(int)$case->id,'case_code'=>$case->case_code,'service_name'=>$case->service_name??'خدمت','credit_amount'=>(float)$case->credit_amount,'status'=>$case->status,'status_label'=>self::service_case_status_label($case->status)];
    }

    private static function ajax_customer_id(): int
    {
        global $wpdb;
        $customer_id = absint($_POST['customer_id'] ?? 0);
        if (!$customer_id) {
            wp_send_json_error(['message'=>'شناسه مشتری در درخواست ارسال نشده است. صفحه پروفایل را یک بار تازه‌سازی کنید.','code'=>'missing_customer_id'], 400);
        }
        $exists = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $customer_id));
        if (!$exists) wp_send_json_error(['message'=>'پروفایل مشتری با این شناسه در دیتابیس وجود ندارد.','code'=>'customer_not_found'],404);
        if (!self::can_access_customer($customer_id)) wp_send_json_error(['message'=>'شما به این پرونده دسترسی ندارید.','code'=>'customer_access_denied'],403);
        if (class_exists('Biawin_CRM_Production_Readiness') && !Biawin_CRM_Production_Readiness::ordinary_agent_may_work($customer_id)) wp_send_json_error(['message'=>'این پرونده در Readiness Quarantine است و تا پایان Reconciliation قابل ویرایش توسط کارشناس نیست.','code'=>'readiness_quarantine'],423);
        return $customer_id;
    }

    private static function is_assigned_to_current_user(int $customer_id): bool
    {
        global $wpdb;
        $assignments = Biawin_CRM_DB::table('assignments');
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $assignments WHERE id=(SELECT MAX(ax.id) FROM $assignments ax WHERE ax.customer_id=%d AND ax.status='assigned') AND agent_user_id=%d LIMIT 1",
            $customer_id, get_current_user_id()
        ));
    }

    public static function can_access_customer(int $customer_id): bool
    {
        global $wpdb;
        if (current_user_can('bcrm_manage_cases')) {
            return true;
        }
        if (self::is_assigned_to_current_user($customer_id)) {
            return true;
        }
        $uid = get_current_user_id();
        if (current_user_can('bcrm_services') && $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('service_cases') . ' WHERE customer_id=%d AND assigned_user_id=%d LIMIT 1', $customer_id, $uid))) {
            return true;
        }
        $supervised_units = Biawin_CRM_Workflow_Service::supervised_unit_keys($uid);
        if ($supervised_units) {
            $ph = implode(',', array_fill(0,count($supervised_units),'%s'));
            $sql = 'SELECT id FROM ' . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND unit_key IN ($ph) AND status NOT IN ('completed','rejected','cancelled') LIMIT 1";
            $args = array_merge([$customer_id], $supervised_units);
            if ($wpdb->get_var($wpdb->prepare($sql, $args))) return true;
        }
        $roles = (array) wp_get_current_user()->roles;
        $is_deficiency_supervisor = in_array('bcrm_deficiency_supervisor', $roles, true);
        if (current_user_can('view_deficiency_cases')) {
            $deficiency_sql = 'SELECT id FROM ' . Biawin_CRM_DB::table('deficiency_cases') . " WHERE customer_id=%d AND status NOT IN ('COMPLETED','ARCHIVED')";
            if (!$is_deficiency_supervisor) $deficiency_sql .= ' AND deficiency_agent_id=%d';
            $deficiency_sql .= ' LIMIT 1';
            $deficiency_access = $is_deficiency_supervisor
                ? $wpdb->get_var($wpdb->prepare($deficiency_sql, $customer_id))
                : $wpdb->get_var($wpdb->prepare($deficiency_sql, $customer_id, $uid));
            if ($deficiency_access) return true;
        }
        if (current_user_can('view_finance_cases') && Biawin_CRM_Deficiency_Workflow::user_can_see_customer($customer_id, $uid)) {
            return true;
        }
        $is_financial_supervisor = in_array('bcrm_financial_supervisor', $roles, true);
        $is_complaint_supervisor = in_array('bcrm_complaint_supervisor', $roles, true);
        $is_refund_supervisor = in_array('bcrm_refund_supervisor', $roles, true);
        if (current_user_can('bcrm_financial_verify')) {
            $financial_sql = 'SELECT id FROM ' . Biawin_CRM_DB::table('financial_cases') . " WHERE customer_id=%d AND status NOT IN ('resolved','closed','cancelled')";
            if (!$is_financial_supervisor) $financial_sql .= ' AND (assigned_user_id IS NULL OR assigned_user_id=%d)';
            $financial_sql .= ' ORDER BY id DESC LIMIT 1';
            $financial_access = $is_financial_supervisor
                ? $wpdb->get_var($wpdb->prepare($financial_sql, $customer_id))
                : $wpdb->get_var($wpdb->prepare($financial_sql, $customer_id, $uid));
            if ($financial_access) return true;
        }
        if (current_user_can('bcrm_complaints')) {
            $complaint_sql = 'SELECT id FROM ' . Biawin_CRM_DB::table('complaints') . " WHERE customer_id=%d AND status='open'";
            if (!$is_complaint_supervisor) $complaint_sql .= ' AND (owner_user_id IS NULL OR owner_user_id=%d)';
            $complaint_sql .= ' LIMIT 1';
            $complaint_access = $is_complaint_supervisor
                ? $wpdb->get_var($wpdb->prepare($complaint_sql, $customer_id))
                : $wpdb->get_var($wpdb->prepare($complaint_sql, $customer_id, $uid));
            if ($complaint_access) return true;
        }
        if (current_user_can('bcrm_refunds')) {
            $refund_sql = 'SELECT id FROM ' . Biawin_CRM_DB::table('refunds') . " WHERE customer_id=%d AND status NOT IN ('closed','cancelled')";
            if (!$is_refund_supervisor) $refund_sql .= ' AND (assigned_user_id IS NULL OR assigned_user_id=%d)';
            $refund_sql .= ' LIMIT 1';
            $refund_access = $is_refund_supervisor
                ? $wpdb->get_var($wpdb->prepare($refund_sql, $customer_id))
                : $wpdb->get_var($wpdb->prepare($refund_sql, $customer_id, $uid));
            if ($refund_access) return true;
        }
        // v0.8.62.0: دارندگان دسترسی ثبت عودت به پروفایل‌های دارای تگ عودت (اعلامی/قطعی) دسترسی دارند.
        // v0.8.62.6: دسترسی سبک مشاهده (bcrm_view_refunded) هم به پروفایل‌های عودتی‌دار دسترسی می‌دهد.
        if (class_exists('Biawin_CRM_Refund_Register') && (current_user_can('bcrm_refund_register') || current_user_can('bcrm_view_refunded'))) {
            $tags = Biawin_CRM_DB::table('customer_tags');
            $tagged = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $tags WHERE customer_id=%d AND tag IN (%s,%s) LIMIT 1",
                $customer_id, Biawin_CRM_Refund_Register::TAG_REPORTED, Biawin_CRM_Refund_Register::TAG_CONFIRMED
            ));
            if ($tagged) return true;
        }
        return false;
    }

    private static function require_action(string $nonce, string $cap): void
    {
        if (!is_user_logged_in() || !current_user_can($cap) || !check_admin_referer($nonce)) wp_die('دسترسی غیرمجاز است.');
    }

    private static function redirect_customer(int $id, string $message): void
    {
        wp_safe_redirect(add_query_arg(['bcrm_customer'=>$id,'bcrm_message'=>$message], wp_get_referer() ?: home_url('/')));
        exit;
    }

    public static function deny_guest(): void
    {
        wp_die('ابتدا وارد حساب کاربری شوید.');
    }

    private static function app_topbar(string $title, string $subtitle, string $back_url = ''): void
    {
        $user = wp_get_current_user();
        echo '<header class="bcrm-app-topbar"><div class="bcrm-topbar-title">' . ($back_url ? '<a class="bcrm-back" href="' . esc_url($back_url) . '">← بازگشت</a>' : '') . '<div><h1>' . esc_html($title) . '</h1><p>' . esc_html($subtitle) . '</p></div></div><div class="bcrm-user-box"><span class="bcrm-user-avatar">' . esc_html(mb_substr($user->display_name ?: 'ک',0,1)) . '</span><div><strong>' . esc_html($user->display_name) . '</strong><span>' . esc_html($user->user_login) . '</span></div><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('bcrm_portal_logout','_wpnonce',true,false).'<input type="hidden" name="action" value="bcrm_portal_logout"><button class="bcrm-link-button" type="submit">خروج</button></form></div></header>';
    }

    private static function metric_card(string $label, $value, string $type): void
    {
        $count_attr = $label === 'کل پروفایل‌ها' ? ' data-bcrm-profile-count' : '';
        echo '<div class="bcrm-metric-card is-' . esc_attr($type) . '"><span class="bcrm-metric-icon"></span><div><strong' . $count_attr . '>' . esc_html((string)$value) . '</strong><span>' . esc_html($label) . '</span></div></div>';
    }

    private static function finance_receipt_totals_for_profile(int $customer_id, array $attachments): array
    {
        global $wpdb;
        $receipt_ids = [];
        $receipt_amounts = [];
        foreach ($attachments as $att) {
            if ((string)($att->file_kind ?? '') !== 'bank_receipt') continue;
            $id = (int)($att->id ?? 0);
            if (!$id) continue;
            $receipt_ids[] = $id;
            $amount = 0.0;
            if (preg_match('/مبلغ فیش:\s*([\d\x{06F0}-\x{06F9}]+(?:[.,،][\d\x{06F0}-\x{06F9}]+)*)/u', (string)($att->note ?? ''), $m)) {
                $amount = (float)Biawin_CRM_Normalizer::money($m[1]);
            }
            $receipt_amounts[$id] = $amount;
        }
        if (!$receipt_ids) return ['approved'=>0.0,'rejected'=>0.0];
        $placeholders = implode(',', array_fill(0, count($receipt_ids), '%d'));
        $reviews = $wpdb->get_results($wpdb->prepare(
            'SELECT attachment_id,review_status,approved_amount FROM ' . Biawin_CRM_DB::table('receipt_reviews') . " WHERE attachment_id IN ($placeholders)",
            ...$receipt_ids
        ));
        $approved = 0.0;
        $rejected = 0.0;
        foreach ((array)$reviews as $review) {
            $id = (int)$review->attachment_id;
            if ((string)$review->review_status === 'approved') {
                $approved += $review->approved_amount !== null ? (float)$review->approved_amount : (float)($receipt_amounts[$id] ?? 0.0);
            } elseif ((string)$review->review_status === 'rejected') {
                $rejected += (float)($receipt_amounts[$id] ?? 0.0);
            }
        }
        return ['approved'=>$approved,'rejected'=>$rejected];
    }

    private static function summary_box(string $label, string $value, string $tone = '', string $key = ''): void
    {
        echo '<div class="' . ($tone ? 'is-' . esc_attr($tone) : '') . '"><b>' . esc_html($label) . '</b><span' . ($key ? ' data-bcrm-summary="' . esc_attr($key) . '"' : '') . '>' . esc_html($value) . '</span></div>';
    }

    private static function money($amount): string
    {
        return number_format_i18n((float)$amount, 0) . ' تومان';
    }

    private static function jalali_datetime_value(string $date): string
    {
        $ts = strtotime($date);
        if (!$ts) return '';
        $gy = (int) wp_date('Y', $ts); $gm = (int) wp_date('n', $ts); $gd = (int) wp_date('j', $ts);
        [$jy, $jm, $jd] = self::gregorian_to_jalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, wp_date('H:i', $ts));
    }

    private static function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053)); $days %= 12053;
        $jy += 4 * intdiv($days, 1461); $days %= 1461;
        if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
        if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + ($days % 31); }
        else { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + (($days - 186) % 30); }
        return [$jy, $jm, $jd];
    }

    private static function date_label($date): string
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

    private static function profile_badge(?string $status): string
    {
        $map = ['complete'=>['کامل','is-success'],'incomplete'=>['ناقص','is-warning'],'pending'=>['در انتظار بررسی','is-neutral']];
        [$label,$class] = $map[$status ?: 'pending'] ?? [$status ?: 'در انتظار بررسی','is-neutral'];
        return '<span class="bcrm-badge ' . $class . '">' . esc_html($label) . '</span>';
    }

    private static function journey_badge(?string $state): string
    {
        if (!$state) return '';
        $map = ['no_request'=>'بدون درخواست','complaint'=>'نارضایتی','service_request'=>'درخواست خدمات','refund'=>'عودت وجه'];
        return '<span class="bcrm-badge is-primary">' . esc_html($map[$state] ?? $state) . '</span>';
    }

    private static function phone_status_label(?string $status): string
    {
        return ['unverified'=>'تأییدنشده','verified'=>'تأییدشده','verified_primary'=>'تأییدشده','wrong'=>'شماره اشتباه','other_person'=>'متعلق به شخص دیگر','blocked'=>'مسدود'][($status ?: '')] ?? ($status ?: 'تأییدنشده');
    }

    private static function financial_status_label(?string $status): string
    {
        return ['new'=>'جدید','assigned'=>'تخصیص‌یافته','in_review'=>'در حال بررسی','in_progress'=>'در حال بررسی','need_evidence'=>'نیازمند فیش/مدرک','needs_document'=>'نیازمند مدرک','resolved'=>'تکمیل‌شده','closed'=>'بسته‌شده','cancelled'=>'لغوشده'][($status ?: '')] ?? ($status ?: '-');
    }

    private static function service_case_status_label(?string $status): string
    {
        return ['unassigned'=>'تخصیص‌نیافته','assigned'=>'تخصیص‌یافته','in_progress'=>'در حال انجام','waiting'=>'در انتظار','completed'=>'تکمیل‌شده','rejected'=>'ردشده','cancelled'=>'لغوشده'][($status ?: '')] ?? ($status ?: '-');
    }
}
