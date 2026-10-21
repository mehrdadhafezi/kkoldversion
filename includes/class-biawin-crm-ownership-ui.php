<?php
if (!defined('ABSPATH')) { exit; }

/**
 * C3 Ownership Reconciliation workspaces (تطبیق مالکیت).
 *
 * Rendering only. Reads come from Biawin_CRM_Ownership_Presenter (which starts
 * from Workflow::get()/list_cases()); every mutation is posted to
 * Biawin_CRM_Ownership_Ajax, which calls one approved backend command. Hidden
 * or disabled controls are always explained; the backend stays authoritative.
 *
 * The portal shell and admin menu files are frozen by the C2 regression suite,
 * so the workspace is attached through WordPress shortcode/menu hooks instead
 * of editing them.
 */
class Biawin_CRM_Ownership_UI
{
    const VIEW = 'ownership';
    const ADMIN_SLUG = 'biawin-crm-ownership';
    const TITLE = 'تطبیق مالکیت';
    const UNIT = 'واحد تطبیق هویت و مالکیت';
    const NOT_TRANSFER = 'ثبت درخواست به معنی انتقال مالکیت نیست.';
    const HOLD_TEXT = 'این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است.';
    const LOCK_TEXT = 'تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است.';
    const PHONE_LOCK_TEXT = 'تغییر مالکیت این شماره فقط از مسیر تطبیق مالکیت مجاز است.';

    public static function init(): void
    {
        add_filter('pre_do_shortcode_tag', [__CLASS__, 'portal_intercept'], 10, 2);
        add_filter('do_shortcode_tag', [__CLASS__, 'portal_nav'], 10, 2);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'front_assets'], 30);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets'], 30);
        Biawin_CRM_Ownership_Ajax::init();
        Biawin_CRM_Ownership_Legacy_Bridge::init();
    }

    // ------------------------------------------------------------------ wiring

    public static function url(array $args = []): string
    {
        $args = array_filter($args, static function ($v) { return $v !== null && $v !== ''; });
        if (is_admin()) return add_query_arg($args, admin_url('admin.php?page=' . self::ADMIN_SLUG));
        return Biawin_CRM_Auth::portal_url(array_merge(['bcrm_view' => self::VIEW], $args));
    }

    private static function portal_user_ok(): bool
    {
        return is_user_logged_in() && current_user_can('bcrm_work_cases') && !(bool)get_user_meta(get_current_user_id(), 'bcrm_disabled', true);
    }

    /** Render bcrm_view=ownership inside the existing portal shell. Other views are untouched. */
    public static function portal_intercept($return, $tag)
    {
        if ($tag !== 'biawin_crm_portal' || is_admin() || sanitize_key((string)($_GET['bcrm_view'] ?? '')) !== self::VIEW) return $return;
        if (!self::portal_user_ok() || !empty($_GET['bcrm_customer'])) return $return; // the portal handles login/denial
        $content = self::render();
        $html = '';
        if (class_exists('Biawin_CRM_Frontend') && method_exists('Biawin_CRM_Frontend', 'render_shell')) {
            try {
                $shell = new ReflectionMethod('Biawin_CRM_Frontend', 'render_shell');
                $shell->setAccessible(true);
                $html = (string)$shell->invoke(null, $content, self::VIEW);
            } catch (Throwable $e) {
                $html = '';
            }
        }
        if ($html === '') return '<div class="bcrm-portal bcrm-app" dir="rtl">' . $content . '</div>';
        $html = preg_replace('~(<span class="bcrm-shell-kicker">[^<]*</span><strong>)[^<]*(</strong>)~u', '$1' . esc_html(self::TITLE) . '$2', $html, 1);
        return self::inject_nav($html, true);
    }

    /** Add the ownership entry to the portal sidebar for users with an ownership workspace. */
    public static function portal_nav($output, $tag)
    {
        if ($tag !== 'biawin_crm_portal' || !is_string($output) || is_admin() || !self::portal_user_ok()) return $output;
        return self::inject_nav($output, false);
    }

    private static function inject_nav(string $html, bool $active): string
    {
        if (!Biawin_CRM_Ownership_Presenter::can_enter() || strpos($html, 'data-bcrm-view="' . self::VIEW . '"') !== false) return $html;
        $start = strpos($html, '<nav class="bcrm-shell-nav">');
        if ($start === false) return $html;
        $end = strpos($html, '</nav>', $start);
        if ($end === false) return $html;
        $link = '<a data-bcrm-view="' . self::VIEW . '" data-tooltip="' . esc_attr(self::TITLE) . '" title="' . esc_attr(self::TITLE) . '" aria-label="' . esc_attr(self::TITLE) . '" class="' . ($active ? 'is-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . ' href="' . esc_url(Biawin_CRM_Auth::portal_url(['bcrm_view' => self::VIEW])) . '"><i aria-hidden="true">⇄</i><span>' . esc_html(self::TITLE) . '</span></a>';
        return substr($html, 0, $end) . $link . substr($html, $end);
    }

    public static function menu_capability(): string
    {
        foreach (['view_ownership_cases', 'view_own_ownership_claims', 'submit_ownership_claim'] as $cap) if (current_user_can($cap)) return $cap;
        return 'do_not_allow';
    }

    public static function admin_menu(): void
    {
        add_submenu_page('biawin-crm', self::TITLE, self::TITLE, self::menu_capability(), self::ADMIN_SLUG, [__CLASS__, 'admin_page']);
    }

    public static function admin_page(): void
    {
        echo '<div class="wrap bcrm" dir="rtl">' . self::render() . '</div>';
    }

    private static function enqueue(): void
    {
        $css = BIAWIN_CRM_VERSION . '.' . (string)@filemtime(BIAWIN_CRM_DIR . 'ownership-ui/ownership.css');
        $js = BIAWIN_CRM_VERSION . '.' . (string)@filemtime(BIAWIN_CRM_DIR . 'ownership-ui/ownership.js');
        wp_enqueue_style('biawin-crm-ownership', BIAWIN_CRM_URL . 'ownership-ui/ownership.css', [], $css);
        wp_enqueue_script('biawin-crm-ownership', BIAWIN_CRM_URL . 'ownership-ui/ownership.js', [], $js, true);
        wp_localize_script('biawin-crm-ownership', 'BCRM_OWN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => Biawin_CRM_Ownership_Ajax::ACTION,
            'nonce' => wp_create_nonce(Biawin_CRM_Ownership_Ajax::NONCE),
            'caseUrl' => self::url(['own_case' => '__ID__']),
            'listUrl' => self::url(['own_tab' => 'mine']),
            'phoneModes' => Biawin_CRM_Ownership_Presenter::PHONE_MODES,
            'phoneModeHelp' => Biawin_CRM_Ownership_Presenter::PHONE_MODE_HELP,
            'phoneModeOwnership' => Biawin_CRM_Ownership_Presenter::PHONE_MODE_OWNERSHIP,
            'actions' => Biawin_CRM_Ownership_Presenter::ACTIONS,
            'notTransfer' => self::NOT_TRANSFER,
            'lockText' => self::LOCK_TEXT,
            'phoneLockText' => self::PHONE_LOCK_TEXT,
            'holdText' => self::HOLD_TEXT,
        ]);
    }

    public static function front_assets(): void
    {
        if (is_admin() || !is_user_logged_in() || !Biawin_CRM_Ownership_Presenter::can_enter()) return;
        self::enqueue();
    }

    public static function admin_assets(string $hook): void
    {
        if (strpos($hook, self::ADMIN_SLUG) !== false) self::enqueue();
    }

    // ------------------------------------------------------------------ helpers

    private static function e($value): string { return esc_html((string)$value); }

    private static function badge(string $text, string $tone, string $icon = ''): string
    {
        return '<span class="bcrm-badge bcrm-own-badge is-' . esc_attr($tone) . '">' . ($icon !== '' ? '<span aria-hidden="true">' . self::e($icon) . '</span> ' : '') . self::e($text) . '</span>';
    }

    public static function status_badge(string $status): string
    {
        return self::badge(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::STATUSES, $status), Biawin_CRM_Ownership_Presenter::STATUS_TONES[$status] ?? 'neutral');
    }

    public static function risk_badge(string $risk): string
    {
        if ($risk === 'HIGH_RISK') return self::badge(Biawin_CRM_Ownership_Presenter::RISK['HIGH_RISK'], 'danger', '⚠');
        return self::badge(Biawin_CRM_Ownership_Presenter::RISK['NORMAL'], 'success', '●');
    }

    public static function execution_badge(string $state): string
    {
        $tone = ['NOT_STARTED' => 'neutral', 'COMMITTED' => 'warning', 'FINALIZATION_FAILED' => 'danger', 'FINALIZED' => 'success'][$state] ?? 'neutral';
        return self::badge(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::EXECUTION, $state), $tone, $state === 'FINALIZATION_FAILED' ? '!' : '');
    }

    public static function date(?string $value, bool $utc = false): string
    {
        if (!$value) return '—';
        if ($utc && function_exists('get_date_from_gmt')) $value = get_date_from_gmt($value);
        if (class_exists('Biawin_CRM_Deficiency_Workflow') && method_exists('Biawin_CRM_Deficiency_Workflow', 'jalali_datetime')) return Biawin_CRM_Deficiency_Workflow::jalali_datetime($value);
        return $value;
    }

    private static function money($value): string
    {
        return number_format_i18n((float)$value) . ' تومان';
    }

    private static function age(string $since): string
    {
        $ts = $since ? strtotime($since) : false;
        if (!$ts) return '—';
        $now = function_exists('current_time') ? strtotime(current_time('mysql')) : time();
        $hours = max(0, (int)floor(($now - $ts) / 3600));
        if ($hours < 24) return number_format_i18n($hours) . ' ساعت';
        return number_format_i18n((int)floor($hours / 24)) . ' روز';
    }

    private static function profile_label(array $p): string
    {
        return '<span class="bcrm-own-name" title="' . esc_attr($p['name']) . '">' . self::e($p['name']) . '</span>' . ($p['code'] !== '' ? ' <small class="bcrm-own-code">' . self::e($p['code']) . '</small>' : '');
    }

    private static function alert(string $tone, string $html, string $role = 'note'): string
    {
        return '<div class="bcrm-own-alert is-' . esc_attr($tone) . '" role="' . esc_attr($role) . '">' . $html . '</div>';
    }

    private static function denied(string $message): string
    {
        return '<section class="bcrm-own-empty is-denied" role="alert"><strong>دسترسی مجاز نیست</strong><p>' . self::e($message) . '</p></section>';
    }

    // ------------------------------------------------------------------ page

    public static function render(): string
    {
        $tabs = Biawin_CRM_Ownership_Presenter::workspaces();
        $case_id = absint($_GET['own_case'] ?? 0);
        $tab = sanitize_key((string)($_GET['own_tab'] ?? ''));
        if (!$case_id && !isset($tabs[$tab])) $tab = isset($tabs['queue']) ? 'queue' : (string)array_key_first($tabs ?: ['' => '']);
        ob_start();
        echo '<div class="bcrm-own" dir="rtl" data-bcrm-own>';
        echo '<header class="bcrm-own-hero' . ($case_id ? ' is-compact' : '') . '"><div><span class="bcrm-own-kicker">' . self::e(self::UNIT) . '</span><h1>' . self::e(self::TITLE) . '</h1><p>ثبت، بررسی و تصمیم‌گیری پرونده‌های تطبیق مالکیت فاکتور و شماره. انتقال مالکیت فقط پس از تأیید و اجرای رسمی پرونده انجام می‌شود.</p></div></header>';
        if (!$tabs) {
            echo self::denied('حساب شما به هیچ‌یک از فضاهای کاری تطبیق مالکیت دسترسی ندارد.') . '</div>';
            return (string)ob_get_clean();
        }
        echo '<nav class="bcrm-own-tabs" aria-label="فضاهای کاری تطبیق مالکیت">';
        foreach ($tabs as $key => $label) {
            $current = !$case_id && $key === $tab;
            echo '<a href="' . esc_url(self::url(['own_tab' => $key])) . '" class="' . ($current ? 'is-active' : '') . '"' . ($current ? ' aria-current="page"' : '') . '>' . self::e($label) . '</a>';
        }
        echo '</nav><div class="bcrm-own-flash" data-own-flash role="status" aria-live="polite"></div>';
        try {
            if ($case_id) echo self::render_case($case_id);
            elseif ($tab === 'request') echo self::render_request();
            elseif ($tab === 'mine') echo self::render_mine();
            elseif ($tab === 'queue') echo self::render_queue();
            elseif ($tab === 'supervisor') echo self::render_supervisor();
            elseif ($tab === 'history') echo self::render_history();
        } catch (Throwable $e) {
            $error = Biawin_CRM_Ownership_Presenter::error($e);
            echo $error['code'] === 'permission' ? self::denied($error['message']) : self::alert('danger', '<strong>نمایش ممکن نشد.</strong> ' . self::e($error['message']), 'alert');
        }
        echo self::dialog_template() . '</div>';
        return (string)ob_get_clean();
    }

    private static function dialog_template(): string
    {
        return '<dialog class="bcrm-own-dialog" data-own-dialog aria-labelledby="bcrm-own-dialog-title" aria-describedby="bcrm-own-dialog-body"><form method="dialog"><h2 id="bcrm-own-dialog-title" data-own-dialog-title></h2><div id="bcrm-own-dialog-body" data-own-dialog-body></div><div class="bcrm-own-dialog-actions"><button type="submit" value="confirm" class="bcrm-primary-button" data-own-dialog-confirm>تأیید و ادامه</button><button type="submit" value="cancel" class="bcrm-secondary-button" data-own-dialog-cancel>انصراف</button></div></form></dialog>';
    }

    // ------------------------------------------------------------------ lists

    private static function rows(): array
    {
        return Biawin_CRM_Ownership_Presenter::list_rows(Biawin_CRM_Ownership_Presenter::scoped_cases());
    }

    /** Filter bar + table that collapses to cards on narrow screens (see ownership.css). */
    /** @param string|array $empty [heading, hint] for a specific empty state */
    private static function table(array $rows, array $columns, array $filters, $empty, string $id, string $default = 'all'): string
    {
        $html = '<div class="bcrm-own-list" data-own-list="' . esc_attr($id) . '" data-own-default="' . esc_attr($default) . '">';
        $html .= '<div class="bcrm-own-toolbar"><div class="bcrm-own-filters" role="group" aria-label="فیلتر پرونده‌ها">';
        foreach ($filters as $key => [$label, $count]) {
            $html .= '<button type="button" class="bcrm-own-chip" data-own-filter="' . esc_attr($key) . '" aria-pressed="' . ($key === $default ? 'true' : 'false') . '">' . self::e($label) . ' <span class="bcrm-own-count">' . self::e(number_format_i18n($count)) . '</span></button>';
        }
        $html .= '</div><label class="bcrm-own-search"><span class="screen-reader-text bcrm-own-sr">جست‌وجو در فهرست</span><input type="search" data-own-search placeholder="جست‌وجوی کد پرونده یا نام پروفایل"></label></div>';
        $html .= '<div class="bcrm-table-wrap bcrm-own-table-wrap"><table class="bcrm-table bcrm-own-table"><thead><tr>';
        foreach ($columns as $label) $html .= '<th scope="col">' . self::e($label) . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr data-own-href="' . esc_url(self::url(['own_case' => $row['id']])) . '" data-own-groups="' . esc_attr(implode(' ', $row['_groups'])) . '" data-own-text="' . esc_attr($row['code'] . ' ' . $row['source']['name'] . ' ' . $row['source']['code'] . ' ' . $row['claimant']['name'] . ' ' . $row['claimant']['code']) . '">';
            foreach ($columns as $key => $label) $html .= '<td data-label="' . esc_attr($label) . '">' . $row['_cells'][$key] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div><div class="bcrm-own-empty" data-own-empty' . ($rows ? ' hidden' : '') . ' role="status"><strong>' . self::e(is_array($empty) ? $empty[0] : 'پرونده‌ای برای نمایش نیست.') . '</strong><p>' . self::e(is_array($empty) ? $empty[1] : $empty) . '</p></div></div>';
        return $html;
    }

    private static function code_cell(array $row): string
    {
        return '<a class="bcrm-own-case-link" href="' . esc_url(self::url(['own_case' => $row['id']])) . '"><bdi>' . self::e($row['code']) . '</bdi></a>';
    }

    private static function reasons_html(array $reasons): string
    {
        if (!$reasons) return '';
        $out = '<ul class="bcrm-own-reasons">';
        foreach ($reasons as $reason) $out .= '<li>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::RISK_REASONS, $reason)) . '</li>';
        return $out . '</ul>';
    }

    private static function risk_cell(array $row): string
    {
        return self::risk_badge($row['risk']) . ($row['reviewer'] ? self::reasons_html($row['reasons']) : '');
    }

    private static function status_cell(array $row): string
    {
        $html = self::status_badge($row['status']);
        if ($row['execution'] !== 'NOT_STARTED' && $row['execution'] !== 'FINALIZED') $html .= ' ' . self::execution_badge($row['execution']);
        return $html;
    }

    private static function render_mine(): string
    {
        $me = get_current_user_id();
        $rows = array_values(array_filter(self::rows(), static function ($r) use ($me) { return $r['requested_by'] === $me; }));
        $groups = ['all' => 'همه', 'active' => 'فعال', 'draft' => 'پیش‌نویس', 'evidence' => 'در انتظار مدرک', 'customer' => 'در انتظار تأیید مشتری', 'decided' => 'تصمیم‌گیری‌شده', 'closed' => 'بسته‌شده', 'cancelled' => 'لغوشده'];
        $counts = array_fill_keys(array_keys($groups), 0);
        foreach ($rows as &$row) {
            $g = ['all'];
            if (!in_array($row['status'], ['DRAFT', 'CLOSED', 'CANCELLED'], true)) $g[] = 'active';
            $g[] = ['DRAFT' => 'draft', 'AWAITING_EVIDENCE' => 'evidence', 'AWAITING_CUSTOMER_CONFIRMATION' => 'customer', 'DECIDED' => 'decided', 'CLOSED' => 'closed', 'CANCELLED' => 'cancelled'][$row['status']] ?? '';
            $row['_groups'] = array_values(array_filter($g));
            foreach ($row['_groups'] as $key) $counts[$key]++;
            $row['_cells'] = [
                'code' => self::code_cell($row), 'source' => self::profile_label($row['source']), 'claimant' => self::profile_label($row['claimant']),
                'status' => self::status_cell($row), 'risk' => self::risk_cell($row), 'created' => self::e(self::date($row['created_at'])),
                'updated' => self::e(self::date($row['updated_at'])), 'specialist' => self::e($row['assignee_id'] ? $row['assignee'] : 'تخصیص نیافته'),
                'assets' => self::e(number_format_i18n($row['invoices']) . ' فاکتور / ' . number_format_i18n($row['phones']) . ' شماره'),
                'evidence' => self::e($row['evidence'] ? number_format_i18n($row['evidence']) . ' مدرک' : 'بدون مدرک'),
                'next' => self::e($row['next']),
            ];
        }
        unset($row);
        $filters = [];
        foreach ($groups as $key => $label) $filters[$key] = [$label, $counts[$key]];
        $note = current_user_can('view_ownership_cases') && !current_user_can('assign_ownership_cases')
            ? self::alert('info', 'فهرست بر اساس محدوده دسترسی سیستم نمایش داده می‌شود؛ درخواست‌های ثبت‌شده توسط شما که به کارشناس دیگری تخصیص یافته‌اند در این فهرست نیستند.')
            : '';
        $cta = current_user_can('submit_ownership_claim') ? '<a class="bcrm-primary-button" href="' . esc_url(self::url(['own_tab' => 'request'])) . '">ثبت درخواست تطبیق جدید</a>' : '';
        return '<section class="bcrm-own-section"><div class="bcrm-own-section-head"><div><h2>پرونده‌های من</h2><p>درخواست‌هایی که شما ثبت کرده‌اید.</p></div>' . $cta . '</div>' . $note
            . self::table($rows, ['code' => 'کد پرونده', 'source' => 'مالک فعلی (مبدأ)', 'claimant' => 'مدعی (مقصد)', 'status' => 'وضعیت', 'risk' => 'ریسک', 'created' => 'ایجاد', 'updated' => 'آخرین تغییر', 'specialist' => 'کارشناس', 'assets' => 'دارایی‌ها', 'evidence' => 'مدارک', 'next' => 'اقدام بعدی'], $filters, ['هنوز درخواستی ثبت نکرده‌اید.', 'برای شروع، «ثبت درخواست تطبیق جدید» را بزنید. پرونده‌های بسته در «بسته / لغوشده» هستند.'], 'mine', 'active')
            . '</section>';
    }

    private static function queue_cells(array $row): array
    {
        $flags = [];
        if ($row['holds']) $flags[] = self::badge('توقف پروفایل فعال', 'warning', '⏸');
        if ($row['conflicting']) $flags[] = self::badge('مدرک متعارض', 'danger', '≠');
        if ($row['amendments']) $flags[] = self::badge(number_format_i18n($row['amendments']) . ' اصلاحیه', 'primary', '✎');
        if ($row['otp'] !== '') $flags[] = self::badge('OTP: ' . $row['otp'], 'neutral');
        return [
            'code' => self::code_cell($row), 'source' => self::profile_label($row['source']), 'claimant' => self::profile_label($row['claimant']),
            'assets' => '<span>' . self::e(number_format_i18n($row['assets'])) . ' دارایی</span><small class="bcrm-own-muted">' . self::e(number_format_i18n($row['invoices']) . ' فاکتور، ' . number_format_i18n($row['phones']) . ' شماره') . '</small>',
            'status' => self::status_cell($row), 'risk' => self::risk_cell($row),
            'assignee' => self::e($row['assignee_id'] ? $row['assignee'] : 'تخصیص نیافته'),
            'age' => self::e(self::age($row['submitted_at'] ?: $row['created_at'])),
            'flags' => $flags ? implode(' ', $flags) : '<span class="bcrm-own-muted">—</span>',
            'supervisor' => self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::SUPERVISOR, $row['supervisor'])),
        ];
    }

    private static function render_queue(): string
    {
        $statuses = ['SUBMITTED', 'ASSIGNED', 'UNDER_REVIEW', 'AWAITING_EVIDENCE', 'AWAITING_CUSTOMER_CONFIRMATION', 'AWAITING_SUPERVISOR_APPROVAL', 'DECIDED', 'CLOSED'];
        $rows = array_values(array_filter(self::rows(), static function ($r) { return $r['status'] !== 'DRAFT'; }));
        $counts = ['active' => 0, 'finfail' => 0] + array_fill_keys($statuses, 0);
        foreach ($rows as &$row) {
            $g = [$row['status']];
            if (!in_array($row['status'], ['CLOSED', 'CANCELLED'], true)) $g[] = 'active';
            if ($row['execution'] === 'FINALIZATION_FAILED') $g[] = 'finfail';
            $row['_groups'] = $g;
            foreach ($g as $key) if (isset($counts[$key])) $counts[$key]++;
            $row['_cells'] = self::queue_cells($row);
        }
        unset($row);
        $filters = ['active' => ['همه فعال', $counts['active']]];
        foreach ($statuses as $status) $filters[$status] = [Biawin_CRM_Ownership_Presenter::STATUSES[$status], $counts[$status]];
        $filters['finfail'] = ['نهایی‌سازی ناتمام', $counts['finfail']];
        $scope = current_user_can('assign_ownership_cases') ? 'همه پرونده‌های تطبیق مالکیت (محدوده سرپرست).' : 'فقط پرونده‌های تخصیص‌یافته به شما.';
        $alert = $counts['finfail'] ? self::alert('danger', '<strong>' . self::e(number_format_i18n($counts['finfail'])) . ' پرونده با نهایی‌سازی ناتمام.</strong> انتقال مالکیت این پرونده‌ها ثبت شده ولی نهایی‌سازی کامل نشده است؛ «ادامه نهایی‌سازی» را از صفحه پرونده اجرا کنید.', 'alert') : '';
        return '<section class="bcrm-own-section"><div class="bcrm-own-section-head"><div><h2>صف کارشناس تطبیق مالکیت</h2><p>' . self::e($scope) . '</p></div></div>' . $alert
            . self::table($rows, ['code' => 'کد پرونده', 'source' => 'مالک فعلی', 'claimant' => 'مدعی', 'assets' => 'دارایی‌ها', 'status' => 'وضعیت', 'risk' => 'ریسک و دلایل', 'assignee' => 'کارشناس', 'age' => 'سن پرونده', 'flags' => 'نشانگرها', 'supervisor' => 'وضعیت سرپرست'], $filters, ['پرونده‌ای در این وضعیت در انتظار شما نیست.', 'با ثبت یا تخصیص پرونده جدید، این فهرست به‌روزرسانی می‌شود. فیلتر دیگری را هم امتحان کنید.'], 'queue', 'active')
            . '</section>';
    }

    private static function render_supervisor(): string
    {
        $me = get_current_user_id();
        $rows = array_values(array_filter(self::rows(), static function ($r) { return $r['status'] === 'AWAITING_SUPERVISOR_APPROVAL'; }));
        $counts = ['all' => count($rows), 'actionable' => 0, 'self' => 0];
        foreach ($rows as &$row) {
            $self = $row['decided_by'] === $me;
            $row['_groups'] = ['all', $self ? 'self' : 'actionable'];
            $counts[$self ? 'self' : 'actionable']++;
            $row['_cells'] = self::queue_cells($row) + [
                'decider' => self::e(Biawin_CRM_Ownership_Presenter::user_name($row['decided_by'])),
                'sod' => $self ? self::badge('شما تصمیم‌گیرنده‌اید — تأیید توسط شما مجاز نیست', 'danger', '⛔') : self::badge('تفکیک وظایف رعایت می‌شود', 'success', '✓'),
                'open' => '<a class="bcrm-secondary-button" href="' . esc_url(self::url(['own_case' => $row['id']])) . '">' . ($self ? 'مشاهده پرونده' : 'بررسی و تصمیم سرپرست') . '</a>',
            ];
        }
        unset($row);
        return '<section class="bcrm-own-section"><div class="bcrm-own-section-head"><div><h2>صف سرپرست / High Risk</h2><p>پرونده‌های پرریسکی که کارشناس تأیید انتقال پیشنهاد کرده و منتظر تأیید مستقل سرپرست هستند.</p></div></div>'
            . self::table($rows, ['code' => 'کد پرونده', 'source' => 'مالک فعلی', 'claimant' => 'مدعی', 'assets' => 'دارایی‌ها', 'risk' => 'ریسک و دلایل', 'decider' => 'کارشناس تصمیم‌گیرنده', 'sod' => 'تفکیک وظایف', 'flags' => 'نشانگرها', 'age' => 'سن پرونده', 'open' => 'اقدام'],
                ['all' => ['همه', $counts['all']], 'actionable' => ['قابل تصمیم توسط شما', $counts['actionable']], 'self' => ['تصمیم خودتان (فقط مشاهده)', $counts['self']]], ['پرونده پرریسکی در انتظار تأیید سرپرست نیست.', 'پرونده‌های پرریسک پس از تأیید کارشناس در این صف قرار می‌گیرند.'], 'supervisor', 'all')
            . '</section>';
    }

    private static function render_history(): string
    {
        $me = get_current_user_id();
        $reviewer = current_user_can('view_ownership_cases');
        $rows = array_values(array_filter(self::rows(), static function ($r) use ($me, $reviewer) { return in_array($r['status'], ['CLOSED', 'CANCELLED'], true) && ($reviewer || $r['requested_by'] === $me); }));
        $counts = ['all' => count($rows), 'closed' => 0, 'cancelled' => 0];
        foreach ($rows as &$row) {
            $key = $row['status'] === 'CLOSED' ? 'closed' : 'cancelled';
            $row['_groups'] = ['all', $key];
            $counts[$key]++;
            $row['_cells'] = [
                'code' => self::code_cell($row), 'source' => self::profile_label($row['source']), 'claimant' => self::profile_label($row['claimant']),
                'status' => self::status_badge($row['status']), 'decision' => self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::DECISIONS, $row['decision'])),
                'execution' => self::execution_badge($row['execution']), 'risk' => self::risk_badge($row['risk']), 'updated' => self::e(self::date($row['updated_at'])),
            ];
        }
        unset($row);
        return '<section class="bcrm-own-section"><div class="bcrm-own-section-head"><div><h2>پرونده‌های بسته / لغوشده</h2><p>سوابق فقط‌خواندنی؛ هیچ اقدامی روی این پرونده‌ها مجاز نیست.</p></div></div>'
            . self::table($rows, ['code' => 'کد پرونده', 'source' => 'مالک فعلی', 'claimant' => 'مدعی', 'status' => 'وضعیت', 'decision' => 'تصمیم', 'execution' => 'اجرا', 'risk' => 'ریسک', 'updated' => 'آخرین تغییر'],
                ['all' => ['همه', $counts['all']], 'closed' => ['بسته‌شده', $counts['closed']], 'cancelled' => ['لغوشده', $counts['cancelled']]], ['پرونده بسته یا لغوشده‌ای وجود ندارد.', 'پرونده‌ها پس از اجرا، رد، انصراف یا لغو در این بخش بایگانی می‌شوند.'], 'history', 'all')
            . '</section>';
    }

    // ------------------------------------------------------------------ request

    private static function render_request(): string
    {
        if (!current_user_can('submit_ownership_claim')) return self::denied('ثبت درخواست تطبیق مالکیت برای حساب شما مجاز نیست.');
        // Query parameters are hints only; Ajax::prefill() re-reads and re-authorizes every value.
        $prefill = Biawin_CRM_Ownership_Ajax::prefill([
            'claimant' => $_GET['claimant'] ?? 0, 'source' => $_GET['source'] ?? 0,
            'asset_type' => $_GET['asset_type'] ?? '', 'asset_id' => $_GET['asset_id'] ?? 0,
        ]);
        $has_prefill = $prefill['claimant'] || $prefill['source'] || $prefill['asset'] || $prefill['notices'];
        $steps = [1 => 'پروفایل مبدأ', 2 => 'پروفایل مقصد', 3 => 'انتخاب دارایی', 4 => 'روش انتقال شماره', 5 => 'دلیل و توضیحات', 6 => 'مرور نهایی'];
        $html = '<section class="bcrm-own-section bcrm-own-wizard" data-own-wizard' . ($has_prefill ? ' data-own-prefill="' . esc_attr((string)wp_json_encode($prefill)) . '"' : '') . '><div class="bcrm-own-section-head"><div><h2>درخواست تطبیق مالکیت</h2><p>ادعای مالکیت فاکتور یا شماره‌ای که در پروفایل دیگری ثبت شده است.</p></div></div>';
        $html .= self::alert('warning', '<strong>' . self::e(self::NOT_TRANSFER) . '</strong> درخواست پس از بررسی کارشناس تطبیق مالکیت و در صورت نیاز تأیید سرپرست اجرا می‌شود.');
        if ($has_prefill) {
            $notes = '';
            foreach ($prefill['notices'] as $notice) $notes .= '<li>' . self::e($notice) . '</li>';
            $html .= self::alert('info', '<strong>پیشنهاد از اتصال ناموفق پروفایل.</strong> اطلاعات زیر از وضعیت فعلی سیستم بازخوانی و برای بازبینی شما پیشنهاد شده است. هیچ پرونده‌ای ایجاد یا ارسال نشده است؛ دارایی، روش شماره، دلیل و ارسال را باید خودتان صریحاً انتخاب و تأیید کنید.' . ($notes !== '' ? '<ul>' . $notes . '</ul>' : ''), 'status');
        }
        $html .= '<ol class="bcrm-own-steps" aria-label="مراحل درخواست">';
        foreach ($steps as $n => $label) $html .= '<li data-own-step-nav="' . $n . '"' . ($n === 1 ? ' aria-current="step"' : '') . '><span>' . self::e(number_format_i18n($n)) . '</span>' . self::e($label) . '</li>';
        $html .= '</ol>';
        $html .= '<div class="bcrm-own-step" data-own-step="1" role="group" aria-labelledby="own-step-1"><h3 id="own-step-1">۱. پروفایل مبدأ (مالک فعلی)</h3><p class="bcrm-own-muted">کد فاکتور یا شماره موبایل مورد ادعا را جست‌وجو کنید. پروفایلی که دارایی اکنون در آن ثبت است مبدأ پرونده است.</p>'
            . '<div class="bcrm-own-searchbar"><label for="own-source-q">کد فاکتور، شماره موبایل یا نام</label><div><input id="own-source-q" type="search" data-own-lookup-q autocomplete="off"><button type="button" class="bcrm-primary-button" data-own-lookup>جست‌وجو</button></div></div>'
            . '<div data-own-lookup-results aria-live="polite" aria-busy="false"></div><div data-own-source-card></div>'
            . '<span class="bcrm-own-field-error" data-own-err role="alert"></span><div class="bcrm-own-step-actions"><button type="button" class="bcrm-primary-button" data-own-next disabled>ادامه: پروفایل مقصد</button></div></div>';
        $html .= '<div class="bcrm-own-step" data-own-step="2" role="group" aria-labelledby="own-step-2" hidden><h3 id="own-step-2">۲. پروفایل مقصد (مدعی)</h3><p class="bcrm-own-muted">فقط پروفایل‌هایی که به آن‌ها دسترسی دارید قابل انتخاب‌اند. مبدأ و مقصد باید متفاوت باشند.</p>'
            . '<div class="bcrm-own-searchbar"><label for="own-claimant-q">نام، کد مشتری یا شماره موبایل مدعی</label><div><input id="own-claimant-q" type="search" data-own-claimant-q autocomplete="off"><button type="button" class="bcrm-primary-button" data-own-claimant-search>جست‌وجو</button></div></div>'
            . '<div data-own-claimant-results aria-live="polite"></div><div data-own-claimant-card></div>'
            . '<span class="bcrm-own-field-error" data-own-err role="alert"></span><div class="bcrm-own-step-actions"><button type="button" class="bcrm-secondary-button" data-own-prev>بازگشت</button><button type="button" class="bcrm-primary-button" data-own-next disabled>ادامه: انتخاب دارایی‌ها</button></div></div>';
        $html .= '<div class="bcrm-own-step" data-own-step="3" role="group" aria-labelledby="own-step-3" hidden><h3 id="own-step-3">۳. انتخاب دارایی‌ها</h3>'
            . self::alert('info', 'هر فاکتور باید جداگانه و صریح انتخاب شود. مشترک بودن شماره به معنی انتقال فاکتورهای دیگر نیست و هیچ فاکتوری خودکار انتخاب نمی‌شود.')
            . '<p class="bcrm-own-muted">🔒 «قفل مالکیت» مانع ثبت درخواست نیست؛ این مسیر رسمی برای همین دارایی‌هاست. فقط دارایی‌ای که در پرونده فعال دیگری است قابل انتخاب نیست.</p>'
            . '<div data-own-picker></div>'
            . '<span class="bcrm-own-field-error" data-own-err role="alert"></span><div class="bcrm-own-step-actions"><button type="button" class="bcrm-secondary-button" data-own-prev>بازگشت</button><button type="button" class="bcrm-primary-button" data-own-next>ادامه: روش انتقال شماره</button></div></div>';
        $html .= '<div class="bcrm-own-step" data-own-step="4" role="group" aria-labelledby="own-step-4" hidden><h3 id="own-step-4">۴. روش انتقال شماره</h3>'
            . self::alert('info', 'برای هر فاکتور یکی از چهار روش را صریحاً انتخاب کنید. هیچ روشی پیش‌فرض انتخاب نمی‌شود. انتقال فاکتور به معنی انتقال مالکیت شماره نیست؛ مالکیت شماره فقط با «انتقال مالکیت شماره فعلی» و انتخاب جداگانه خود شماره منتقل می‌شود.')
            . '<div data-own-modes></div>'
            . '<span class="bcrm-own-field-error" data-own-err role="alert"></span><div class="bcrm-own-step-actions"><button type="button" class="bcrm-secondary-button" data-own-prev>بازگشت</button><button type="button" class="bcrm-primary-button" data-own-next>ادامه: دلیل و توضیحات</button></div></div>';
        $html .= '<div class="bcrm-own-step" data-own-step="5" role="group" aria-labelledby="own-step-5" hidden><h3 id="own-step-5">۵. دلیل و توضیحات</h3><div class="bcrm-own-form-grid">'
            . '<label for="own-reason">دلیل درخواست <small>(حداکثر ۱۲۰ نویسه)</small></label><input id="own-reason" maxlength="120" list="own-reason-list" data-own-reason required aria-describedby="own-reason-err"><datalist id="own-reason-list"><option value="پرداخت توسط مدعی انجام شده است"><option value="ثبت اشتباه شماره هنگام فروش"><option value="خرید برای شخص دیگر"><option value="ادغام نادرست پروفایل"></datalist><span class="bcrm-own-field-error" id="own-reason-err" data-own-err></span>'
            . '<label for="own-note">توضیحات تکمیلی</label><textarea id="own-note" rows="4" data-own-note></textarea></div>'
            . '<p class="bcrm-own-muted">مدارک (رسید پرداخت، رضایت‌نامه، مدرک مالکیت) پس از ذخیره پیش‌نویس در صفحه پرونده بارگذاری می‌شوند.</p>'
            . '<div class="bcrm-own-step-actions"><button type="button" class="bcrm-secondary-button" data-own-prev>بازگشت</button><button type="button" class="bcrm-primary-button" data-own-next>ادامه: مرور نهایی</button></div></div>';
        $html .= '<div class="bcrm-own-step" data-own-step="6" role="group" aria-labelledby="own-step-6" hidden><h3 id="own-step-6">۶. مرور نهایی</h3>'
            . self::alert('warning', '<strong>' . self::e(self::NOT_TRANSFER) . '</strong> با ارسال برای بررسی، هر دو پروفایل تا تعیین تکلیف پرونده فقط خواندنی می‌شوند.')
            . '<div data-own-review></div><div class="bcrm-own-form-error" data-own-wizard-error role="alert"></div>'
            . '<div class="bcrm-own-step-actions"><button type="button" class="bcrm-secondary-button" data-own-prev>بازگشت</button><button type="button" class="bcrm-secondary-button" data-own-save-draft>ذخیره پیش‌نویس</button><button type="button" class="bcrm-primary-button" data-own-save-submit>ثبت درخواست تطبیق و ارسال برای بررسی</button></div></div>';
        return $html . '</section>';
    }

    // ------------------------------------------------------------------ case detail

    private static function render_case(int $id): string
    {
        $view = Biawin_CRM_Ownership_Presenter::case_view($id);
        $case = $view['case'];
        $actions = Biawin_CRM_Ownership_Presenter::actions($case);
        $source = $view['labels'][(int)$case->current_owner_customer_id] ?? ['id' => (int)$case->current_owner_customer_id, 'name' => '#' . (int)$case->current_owner_customer_id, 'code' => ''];
        $claimant = $view['labels'][(int)$case->claimant_customer_id] ?? ['id' => (int)$case->claimant_customer_id, 'name' => '#' . (int)$case->claimant_customer_id, 'code' => ''];
        $view['source'] = $source;
        $view['claimant'] = $claimant;
        $readonly = in_array($case->status, ['CLOSED', 'CANCELLED'], true);
        $asset_labels = [];
        foreach ($view['assets'] as $item) $asset_labels[] = $item['row']->asset_type === 'INVOICE' ? 'فاکتور ' . ($item['live']->invoice_code ?? ('#' . $item['row']->asset_id)) : 'شماره ' . ($item['live']->normalized_phone ?? ('#' . $item['row']->asset_id));
        $context = ['code' => (string)$case->case_code, 'source' => $source['name'], 'claimant' => $claimant['name'], 'assets' => $asset_labels, 'risk' => (string)$case->risk_level];
        $html = '<article class="bcrm-own-case" data-own-context="' . esc_attr((string)wp_json_encode($context, JSON_UNESCAPED_UNICODE)) . '" data-own-case="' . (int)$case->id . '" data-own-version="' . (int)$case->lock_version . '" data-own-status="' . esc_attr($case->status) . '" data-own-execution="' . esc_attr((string)$case->execution_state) . '" data-own-risk="' . esc_attr((string)$case->risk_level) . '" data-own-source="' . (int)$case->current_owner_customer_id . '" data-own-claimant="' . (int)$case->claimant_customer_id . '">';
        $html .= '<a class="bcrm-own-back" href="' . esc_url(self::url(['own_tab' => $view['reviewer'] ? 'queue' : 'mine'])) . '">→ بازگشت به فهرست</a>';
        [$who, $next] = $readonly ? ['other', $case->status === 'CANCELLED' ? 'پرونده لغو شده و فقط‌خواندنی است.' : 'پرونده بسته شده و فقط‌خواندنی است.'] : self::next_step($view, $actions);
        $html .= '<div class="bcrm-own-sticky" data-own-sticky><div class="bcrm-own-sticky-summary"><bdi class="bcrm-own-sticky-code">' . self::e($case->case_code) . '</bdi>' . self::status_badge((string)$case->status) . self::risk_badge((string)$case->risk_level)
            . ($case->execution_state !== 'NOT_STARTED' ? self::execution_badge((string)$case->execution_state) : '')
            . '<span class="bcrm-own-sticky-next is-' . $who . '"><span class="bcrm-own-kicker">گام بعدی</span>' . self::e($next) . '</span>'
            . ($who === 'you' ? '<a class="bcrm-primary-button bcrm-own-sticky-go" href="#own-actions">رفتن به اقدام</a>' : '') . '</div>' . '<nav class="bcrm-own-anchors" aria-label="بخش‌های پرونده"><a href="#own-profiles">مالک فعلی و مدعی</a><a href="#own-assets">دارایی‌ها</a><a href="#own-risk">ریسک</a><a href="#own-holds">توقف و قفل</a><a href="#own-evidence">مدارک</a>' . ($view['reviewer'] && self::has_phone_asset($view) ? '<a href="#own-otp">OTP</a>' : '') . '<a href="#own-decision">تصمیم</a><a href="#own-timeline">تاریخچه</a><a href="#own-execution">اجرا و بازیابی</a></nav>' . '</div>';
        $html .= self::case_header($view);
        $html .= '<div class="bcrm-own-stale" data-own-stale hidden role="alert"><strong>این پرونده تغییر کرده است.</strong> اطلاعات نمایش‌داده‌شده قدیمی است؛ پیش از هر اقدام صفحه را تازه‌سازی کنید. <button type="button" class="bcrm-secondary-button" data-own-reload>تازه‌سازی پرونده</button></div>';
        $html .= self::case_banners($view, $actions);
        $html .= '<div class="bcrm-own-case-layout"><div class="bcrm-own-case-main">';
        $html .= self::section_profiles($view) . self::section_assets($view, $actions) . self::section_risk($view) . self::section_holds($view)
            . self::section_evidence($view, $actions) . self::section_otp($view, $actions) . self::section_decision($view) . self::section_timeline($view) . self::section_execution($view);
        $html .= '</div><aside class="bcrm-own-case-actions" id="own-actions" tabindex="-1" aria-label="اقدامات پرونده">' . ($readonly ? self::readonly_panel($case) : self::action_panel($view, $actions)) . '</aside></div></article>';
        return $html;
    }

    private static function has_phone_asset(array $view): bool
    {
        foreach ($view['assets'] as $asset) if ($asset['row']->asset_type === 'PHONE') return true;
        return false;
    }

    private static function case_header(array $view): string
    {
        $case = $view['case'];
        $times = [
            'ایجاد' => $case->created_at ?? '', 'ارسال' => $case->submitted_at ?? '', 'تخصیص' => $case->assigned_at ?? '', 'شروع بررسی' => $case->review_started_at ?? '',
            'تصمیم کارشناس' => $case->decided_at ?? '', 'تصمیم سرپرست' => $case->supervisor_decided_at ?? '', 'اجرای انتقال' => $case->executed_at ?? '',
            'بستن' => $case->closed_at ?? '', 'لغو' => $case->cancelled_at ?? '', 'آخرین تغییر' => $case->updated_at ?? '',
        ];
        $html = '<header class="bcrm-own-case-head"><div class="bcrm-own-case-title"><span class="bcrm-own-kicker">پرونده تطبیق مالکیت</span><h2><bdi>' . self::e($case->case_code) . '</bdi></h2><div class="bcrm-own-badges">'
            . self::status_badge((string)$case->status) . self::risk_badge((string)$case->risk_level) . self::execution_badge((string)$case->execution_state) . '</div></div>';
        $html .= self::stepper($case);
        $html .= '<dl class="bcrm-own-facts"><div><dt>مبدأ (مالک فعلی)</dt><dd>' . self::profile_label($view['source']) . '</dd></div><div><dt>مقصد (مدعی)</dt><dd>' . self::profile_label($view['claimant']) . '</dd></div>'
            . '<div><dt>درخواست‌دهنده</dt><dd>' . self::e(Biawin_CRM_Ownership_Presenter::user_name($case->requested_by_user_id)) . '</dd></div>'
            . '<div><dt>کارشناس تطبیق مالکیت</dt><dd>' . self::e($case->assigned_to_user_id ? Biawin_CRM_Ownership_Presenter::user_name($case->assigned_to_user_id) : 'تخصیص نیافته') . '</dd></div>'
            . '<div><dt>تأیید سرپرست</dt><dd>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::SUPERVISOR, $case->supervisor_approval_status)) . ($case->supervisor_user_id ? ' — ' . self::e(Biawin_CRM_Ownership_Presenter::user_name($case->supervisor_user_id)) : '') . '</dd></div>'
            . '<div><dt>دلیل درخواست</dt><dd>' . self::e($case->claim_reason ?: '—') . '</dd></div></dl>';
        $html .= '<details class="bcrm-own-times"><summary>زمان‌های مهم</summary><dl>';
        foreach ($times as $label => $value) if ($value) $html .= '<div><dt>' . self::e($label) . '</dt><dd>' . self::e(self::date($value)) . '</dd></div>';
        $html .= '</dl></details>';
        if (!empty($case->request_note)) {
            $note = '<p>' . nl2br(self::e($case->request_note)) . '</p>';
            $long = (function_exists('mb_strlen') ? mb_strlen((string)$case->request_note) : strlen((string)$case->request_note)) > 220;
            $html .= $long ? '<details class="bcrm-own-note bcrm-own-collapsible"><summary>توضیحات درخواست (نمایش متن کامل)</summary>' . $note . '</details>' : '<div class="bcrm-own-note"><strong>توضیحات درخواست:</strong>' . $note . '</div>';
        }
        return $html . '</header>';
    }

    /** Workflow position (presentation of the backend status; no frontend-only state). */
    private static function stepper(object $case): string
    {
        $status = (string)$case->status;
        if ($status === 'CANCELLED') return '<p class="bcrm-own-stepper-cancelled">' . self::badge('لغوشده — پرونده بدون انتقال مالکیت متوقف شد', 'neutral') . '</p>';
        $high = $case->risk_level === 'HIGH_RISK' || $case->supervisor_approval_status !== 'NOT_REQUIRED';
        $steps = ['DRAFT' => 'پیش‌نویس', 'SUBMITTED' => 'ارسال', 'ASSIGNED' => 'تخصیص', 'UNDER_REVIEW' => 'بررسی کارشناس'];
        if ($high) $steps['AWAITING_SUPERVISOR_APPROVAL'] = 'تأیید سرپرست';
        $steps['DECIDED'] = 'تصمیم / اجرا';
        $steps['CLOSED'] = 'بسته';
        $map = ['AWAITING_EVIDENCE' => 'UNDER_REVIEW', 'AWAITING_CUSTOMER_CONFIRMATION' => 'UNDER_REVIEW'];
        $current = $map[$status] ?? $status;
        $keys = array_keys($steps);
        $pos = array_search($current, $keys, true);
        $html = '<ol class="bcrm-own-stepper" aria-label="مسیر پرونده">';
        foreach ($keys as $i => $key) {
            $cls = $pos === false ? '' : ($i < $pos || ($key === 'CLOSED' && $status === 'CLOSED') ? 'is-done' : ($i === $pos ? 'is-current' : ''));
            $sub = $i === $pos && isset($map[$status]) ? '<small>' . self::e(Biawin_CRM_Ownership_Presenter::STATUSES[$status]) . '</small>' : '';
            $html .= '<li class="' . $cls . '"' . ($cls === 'is-current' ? ' aria-current="step"' : '') . '><span>' . self::e($steps[$key]) . '</span>' . $sub . '</li>';
        }
        return $html . '</ol>';
    }

    private static function case_banners(array $view, array $actions): string
    {
        $case = $view['case'];
        $html = '';
        if (!in_array($case->status, ['CLOSED', 'CANCELLED'], true) && $case->execution_state === 'NOT_STARTED') {
            $html .= self::alert('warning', '<strong>' . self::e(self::NOT_TRANSFER) . '</strong> مالکیت فقط پس از تصمیم نهایی و اجرای رسمی پرونده تغییر می‌کند.');
        }
        $active = array_filter($view['holds'], static function ($h) { return $h->status === 'ACTIVE'; });
        if ($active) $html .= self::alert('hold', '<strong>توقف موقت پروفایل (Customer Hold):</strong> ' . self::e(self::HOLD_TEXT) . ' ویرایش، اتصال فاکتور/شماره، ایمپورت، رفع ناقصی و تأیید مالی این دو پروفایل تا پایان پرونده مسدود است.');
        if ($case->execution_state === 'COMMITTED') {
            $html .= self::alert('warning', '<strong>انتقال مالکیت ثبت شده است.</strong> نهایی‌سازی (بازمحاسبه پروفایل‌ها و آزادسازی توقف) هنوز کامل نشده است. اگر این وضعیت باقی ماند «ادامه نهایی‌سازی» را اجرا کنید.', 'status');
        }
        if ($case->execution_state === 'FINALIZATION_FAILED') {
            $error = $view['reviewer'] && !empty($case->execution_error) ? '<details><summary>جزئیات خطا</summary><pre>' . self::e($case->execution_error) . '</pre></details>' : '';
            $html .= self::alert('danger', '<strong>انتقال مالکیت انجام و ثبت شده، اما نهایی‌سازی پس از انتقال کامل نشد.</strong> مالکیت فاکتور/شماره قبلاً منتقل شده و نباید دوباره اجرا شود. فقط «ادامه نهایی‌سازی» مجاز است؛ این اقدام انتقال را تکرار نمی‌کند و فقط بازمحاسبه پروفایل‌ها و بستن پرونده را کامل می‌کند.' . $error, 'alert');
        }
        if ($actions['supervisor_self']) {
            $html .= self::alert('info', '<strong>تفکیک وظایف:</strong> شما تأیید انتقال این پرونده را پیشنهاد داده‌اید؛ تأیید سرپرست باید توسط سرپرست دیگری انجام شود.');
        }
        if ($case->status === 'CANCELLED') $html .= self::alert('neutral', '<strong>این پرونده لغو شده است.</strong> هیچ تغییری در مالکیت انجام نشده و پرونده فقط‌خواندنی است.');
        if ($case->status === 'CLOSED') {
            $text = $case->decision === 'TRANSFER_APPROVED' ? 'انتقال تأییدشده اجرا و پرونده بسته شده است.' : 'پرونده بدون انتقال مالکیت بسته شده است (' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::DECISIONS, $case->decision) . ').';
            $html .= self::alert('neutral', '<strong>پرونده بسته است.</strong> ' . self::e($text) . ' پرونده فقط‌خواندنی است.');
        }
        return $html;
    }

    private static function section(string $id, string $title, string $body, string $intro = ''): string
    {
        return '<section class="bcrm-panel bcrm-own-panel" id="' . esc_attr($id) . '" aria-labelledby="' . esc_attr($id) . '-h"><h3 id="' . esc_attr($id) . '-h">' . self::e($title) . '</h3>' . ($intro !== '' ? '<p class="bcrm-own-muted">' . $intro . '</p>' : '') . $body . '</section>';
    }

    private static function section_profiles(array $view): string
    {
        $case = $view['case'];
        $snapshot = $view['reviewer'] ? Biawin_CRM_Ownership_Repository::decode($case->snapshot_json ?? null) : [];
        $current = [];
        foreach ($view['data']['current_profiles'] ?? [] as $p) if ($p) $current[(int)$p->id] = $p;
        $holds = [];
        foreach ($view['holds'] as $h) if ($h->status === 'ACTIVE') $holds[(int)$h->customer_id] = true;
        $other = [];
        foreach ($view['other_holds'] as $h) $other[(int)$h->customer_id][] = (int)$h->case_id;
        $cards = '';
        foreach (['source' => (int)$case->current_owner_customer_id, 'claimant' => (int)$case->claimant_customer_id] as $role => $cid) {
            $label = $view[$role];
            $cards .= '<div class="bcrm-own-profile is-' . $role . '"><span class="bcrm-own-kicker">' . ($role === 'source' ? 'مبدأ — مالک فعلی' : 'مقصد — مدعی') . '</span><h4>' . self::profile_label($label) . '</h4>';
            if (!empty($holds[$cid])) $cards .= self::badge('فقط خواندنی تا پایان پرونده', 'warning', '⏸');
            if (!empty($other[$cid])) $cards .= ' ' . self::badge('توقف فعال از ' . number_format_i18n(count($other[$cid])) . ' پرونده دیگر', 'warning');
            if (!$view['reviewer']) {
                $cards .= '<p class="bcrm-own-muted">جزئیات مالی و شماره‌های پروفایل برای کارشناس تطبیق مالکیت نمایش داده می‌شود.</p></div>';
                continue;
            }
            $now = $current[$cid] ?? null;
            $then = $snapshot['profiles'][(string)$cid] ?? null;
            $fields = ['profile_completeness' => 'وضعیت تکمیل پروفایل', 'total_paid' => 'جمع پرداختی ثبت‌شده', 'declared_paid' => 'مبلغ اعلامی', 'discrepancy_amount' => 'مغایرت'];
            $cards .= '<table class="bcrm-own-kv"><thead><tr><th scope="col">مورد</th><th scope="col">اکنون</th><th scope="col">هنگام ثبت</th></tr></thead><tbody>';
            foreach ($fields as $field => $flabel) {
                $a = $now->$field ?? null; $b = $then[$field] ?? null;
                $fmt = static function ($v) use ($field) { if ($v === null || $v === '') return '—'; return $field === 'profile_completeness' ? (['complete' => 'کامل', 'incomplete' => 'ناقص', 'pending' => 'در انتظار'][$v] ?? $v) : self::money($v); };
                $changed = $then !== null && (string)$a !== (string)$b;
                $cards .= '<tr' . ($changed ? ' class="is-changed"' : '') . '><th scope="row">' . self::e($flabel) . '</th><td>' . self::e($fmt($a)) . '</td><td>' . self::e($then !== null ? $fmt($b) : '—') . ($changed ? ' <span class="bcrm-own-sr">(تغییر کرده)</span><span aria-hidden="true"> ●</span>' : '') . '</td></tr>';
            }
            $cards .= '</tbody></table><h5>شماره‌ها</h5><ul class="bcrm-own-phones">';
            foreach ($view['profile_phones'][$cid] ?? [] as $phone) $cards .= '<li><bdi dir="ltr">' . self::e($phone->normalized_phone) . '</bdi> ' . self::badge(self::phone_status($phone->phone_status), 'neutral') . '</li>';
            if (empty($view['profile_phones'][$cid])) $cards .= '<li class="bcrm-own-muted">بدون شماره</li>';
            $cards .= '</ul></div>';
        }
        return self::section('own-profiles', 'مقایسه پروفایل‌ها', '<div class="bcrm-own-compare">' . $cards . '</div>');
    }

    public static function phone_status(string $status): string
    {
        return ['verified' => 'تأییدشده', 'unverified' => 'تأییدنشده', 'invalid' => 'نامعتبر', 'wrong_number' => 'شماره اشتباه'][$status] ?? ($status ?: 'نامشخص');
    }

    private static function effective(object $asset, string $field)
    {
        $final = 'final_' . $field;
        $requested = $field === 'action' ? 'requested_action' : 'requested_' . $field;
        return $asset->$final ?: $asset->$requested;
    }

    private static function section_assets(array $view, array $actions): string
    {
        $case = $view['case'];
        $body = '';
        foreach ($view['assets'] as $item) {
            $asset = $item['row']; $live = $item['live']; $snap = $item['snapshot'];
            $active_lock = null;
            foreach ($item['locks'] as $lock) if (!$lock->released_at) $active_lock = $lock;
            $action = (string)self::effective($asset, 'action');
            $moving = $action === 'TRANSFER';
            $body .= '<article class="bcrm-own-asset is-' . strtolower($asset->asset_type) . '" aria-label="دارایی ' . ($asset->asset_type === 'INVOICE' ? 'فاکتور' : 'شماره') . '">';
            if ($asset->asset_type === 'INVOICE') {
                $body .= '<header><span class="bcrm-own-kicker">فاکتور</span><h4><bdi>' . self::e($live->invoice_code ?? ('#' . $asset->asset_id)) . '</bdi></h4>' . self::badge($moving ? 'انتقال' : 'فقط بررسی', $moving ? 'primary' : 'neutral') . '</header>';
                $body .= '<dl class="bcrm-own-facts is-compact"><div><dt>مبلغ</dt><dd>' . self::e(isset($live->amount_paid) ? self::money($live->amount_paid) : '—') . '</dd></div>'
                    . '<div><dt>مالک فعلی</dt><dd>' . self::e(isset($live->customer_id) ? self::owner_name($view, (int)$live->customer_id) : '—') . '</dd></div>'
                    . '<div><dt>مقصد</dt><dd>' . self::profile_label($view['claimant']) . '</dd></div>'
                    . '<div><dt>شماره رسمی (Canonical)</dt><dd>' . (!empty($live->canonical_phone_normalized) ? '<bdi dir="ltr">' . self::e($live->canonical_phone_normalized) . '</bdi>' : 'ثبت نشده') . '</dd></div>';
                if ($view['reviewer'] && $snap) {
                    $relations = array_map(static function ($r) { return is_array($r) ? ($r['normalized_phone'] ?? '') : ''; }, $snap['phone_relations'] ?? []);
                    $body .= '<div><dt>شماره فاکتور هنگام ثبت</dt><dd><bdi dir="ltr">' . self::e($snap['phone'] ?? '—') . '</bdi></dd></div><div><dt>شماره‌های سوابق قدیمی</dt><dd>' . ($relations ? '<bdi dir="ltr">' . self::e(implode('، ', $relations)) . '</bdi>' : '—') . '</dd></div>';
                    $services = array_map(static function ($s) { return is_array($s) ? ($s['service_name'] ?? '') : ''; }, $snap['services'] ?? []);
                    $body .= '<div><dt>خدمات وابسته (همراه فاکتور منتقل می‌شوند)</dt><dd>' . self::e($services ? implode('، ', $services) : 'بدون خدمت') . '</dd></div>';
                }
                $body .= '</dl>';
                $body .= '<div class="bcrm-own-plan"><div><span class="bcrm-own-kicker">برنامه درخواستی</span><strong>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::ACTIONS, $asset->requested_action)) . ' — ' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::PHONE_MODES, $asset->requested_phone_resolution)) . '</strong>' . self::plan_phone($view, $asset->requested_phone_resolution, $asset->requested_phone_id, $asset->requested_phone_normalized, $snap['plan']['number'] ?? null) . '</div>';
                if ($asset->final_phone_resolution || $asset->final_action) {
                    $body .= '<div class="is-final"><span class="bcrm-own-kicker">برنامه نهایی کارشناس</span><strong>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::ACTIONS, $asset->final_action)) . ' — ' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::PHONE_MODES, $asset->final_phone_resolution)) . '</strong>' . self::plan_phone($view, $asset->final_phone_resolution, $asset->final_phone_id, $asset->final_phone_normalized, null);
                    if (!empty($asset->specialist_override_reason)) $body .= '<p><strong>دلیل تغییر:</strong> ' . self::e($asset->specialist_override_reason) . '</p>';
                    $body .= '</div>';
                } else {
                    $body .= '<div class="is-final is-empty"><span class="bcrm-own-kicker">برنامه نهایی کارشناس</span><span>هنوز تعیین نشده — برنامه درخواستی ملاک است.</span></div>';
                }
                $body .= '</div>';
                $mode = (string)self::effective($asset, 'phone_resolution');
                $body .= '<div class="bcrm-own-beforeafter" aria-label="پیش و پس از اجرا"><div><span class="bcrm-own-kicker">پیش از اجرا</span><p>مالک: ' . self::profile_label($view['source']) . '</p></div><span class="bcrm-own-arrow" aria-hidden="true">←</span><div><span class="bcrm-own-kicker">پس از اجرای تأییدشده</span><p>مالک: ' . ($moving ? self::profile_label($view['claimant']) : self::profile_label($view['source']) . ' (بدون تغییر)') . '</p><p>' . self::e($moving ? self::phone_outcome($mode) : 'شماره فاکتور تغییر نمی‌کند.') . '</p></div></div>';
                if ($actions['final_plan'] && $case->status !== 'DRAFT') $body .= self::final_plan_form($view, $asset);
            } else {
                $body .= '<header><span class="bcrm-own-kicker">شماره</span><h4><bdi dir="ltr">' . self::e($live->normalized_phone ?? ('#' . $asset->asset_id)) . '</bdi></h4>' . self::badge($moving ? 'انتقال مالکیت شماره' : 'فقط بررسی', $moving ? 'danger' : 'neutral') . '</header>';
                $body .= '<dl class="bcrm-own-facts is-compact"><div><dt>مالک فعلی</dt><dd>' . self::e(isset($live->customer_id) ? self::owner_name($view, (int)$live->customer_id) : '—') . '</dd></div><div><dt>وضعیت تأیید</dt><dd>' . self::e(self::phone_status((string)($live->phone_status ?? ''))) . '</dd></div>'
                    . '<div><dt>اقدام درخواستی</dt><dd>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::ACTIONS, $asset->requested_action)) . '</dd></div><div><dt>اقدام نهایی</dt><dd>' . self::e($asset->final_action ? Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::ACTIONS, $asset->final_action) : 'مانند درخواست') . '</dd></div></dl>';
                $body .= '<p class="bcrm-own-muted">' . ($moving ? 'با اجرای تأییدشده، مالکیت خود این شماره به پروفایل مقصد منتقل می‌شود.' : 'مالکیت این شماره منتقل نمی‌شود.') . '</p>';
            }
            if ($active_lock) $body .= self::alert('lock', '<strong>قفل مالکیت دائمی (Ownership Lock):</strong> ' . self::e($asset->asset_type === 'INVOICE' ? self::LOCK_TEXT : self::PHONE_LOCK_TEXT) . ' <small>علت: ' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::LOCK_REASONS, $active_lock->lock_reason)) . ' — از ' . self::e(self::date($active_lock->locked_at)) . '</small>');
            if ($item['locks']) $body .= self::lock_history($item['locks'], $view);
            if ($case->status === 'DRAFT' && $actions['update_draft']) {
                $body .= '<form class="bcrm-own-action is-inline" data-own-op="remove_draft_asset" data-own-confirm="این دارایی از پیش‌نویس حذف شود؟"><input type="hidden" name="asset_row" value="' . (int)$asset->id . '"><button type="submit" class="bcrm-secondary-button">حذف از پیش‌نویس</button><div class="bcrm-own-form-error" data-own-form-error role="alert"></div></form>';
            }
            $body .= '</article>';
        }
        if (!$view['assets']) $body .= '<div class="bcrm-own-empty"><strong>هنوز دارایی انتخاب نشده است.</strong><p>برای ارسال پرونده حداقل یک فاکتور یا شماره لازم است.</p></div>';
        $existing = []; $existing_phones = [];
        foreach ($view['assets'] as $item) {
            $existing[] = $item['row']->asset_type . ':' . (int)$item['row']->asset_id;
            if ($item['row']->asset_type === 'PHONE' && self::effective($item['row'], 'action') === 'TRANSFER' && !empty($item['live']->normalized_phone)) $existing_phones[] = $item['live']->normalized_phone;
        }
        $picker_attrs = ' data-own-existing="' . esc_attr(implode(',', $existing)) . '" data-own-existing-phones="' . esc_attr(implode(',', $existing_phones)) . '"';
        if ($case->status === 'DRAFT' && $actions['update_draft']) {
            $body .= '<div class="bcrm-own-subpanel"><h4>افزودن دارایی به پیش‌نویس</h4><div data-own-picker data-own-picker-mode="draft"' . $picker_attrs . '></div></div>';
        }
        if ($actions['amend']) {
            $body .= '<div class="bcrm-own-subpanel"><h4>اصلاحیه رسمی — افزودن دارایی</h4><p class="bcrm-own-muted">افزودن دارایی پس از ثبت فقط با اصلاحیه رسمی و دلیل الزامی مجاز است و دامنه پرونده را گسترش می‌دهد.</p><div data-own-picker data-own-picker-mode="amend"' . $picker_attrs . '></div></div>';
        }
        return self::section('own-assets', 'دارایی‌های پرونده', $body);
    }

    private static function owner_name(array $view, int $customer): string
    {
        foreach (['source', 'claimant'] as $role) if ($view[$role]['id'] === $customer) return $view[$role]['name'];
        return '#' . $customer;
    }

    private static function plan_phone(array $view, $mode, $phone_id, $phone, $snap_number): string
    {
        $mode = (string)$mode;
        if ($mode === 'USE_TARGET_PROFILE_PHONE') {
            $number = '';
            foreach ($view['profile_phones'][(int)$view['case']->claimant_customer_id] ?? [] as $p) if ((int)$p->id === (int)$phone_id) $number = $p->normalized_phone;
            return '<p>شماره مقصد: ' . ($number !== '' ? '<bdi dir="ltr">' . self::e($number) . '</bdi>' : 'شناسه ' . self::e((int)$phone_id)) . '</p>';
        }
        if ($mode === 'PROPOSE_NEW_PHONE') return '<p>شماره پیشنهادی: <bdi dir="ltr">' . self::e($phone ?: '—') . '</bdi></p>';
        if ($snap_number) return '<p>شماره: <bdi dir="ltr">' . self::e($snap_number) . '</bdi></p>';
        return '';
    }

    private static function phone_outcome(string $mode): string
    {
        switch ($mode) {
            case 'KEEP_CURRENT_PHONE': return 'شماره فاکتور همان شماره فعلی می‌ماند؛ مالکیت خود شماره در مبدأ باقی می‌ماند.';
            case 'USE_TARGET_PROFILE_PHONE': return 'شماره رسمی فاکتور یکی از شماره‌های فعلی پروفایل مقصد می‌شود.';
            case 'CLAIM_CURRENT_PHONE': return 'شماره فعلی فاکتور و مالکیت خود شماره (به‌عنوان دارایی جداگانه) به مقصد منتقل می‌شود.';
            case 'PROPOSE_NEW_PHONE': return 'شماره جدید پس از اجرا در پروفایل مقصد ثبت و شماره رسمی فاکتور می‌شود.';
        }
        return 'روش شماره تعیین نشده است.';
    }

    private static function lock_history(array $locks, array $view): string
    {
        $html = '<details class="bcrm-own-locks"><summary>سابقه قفل مالکیت (' . self::e(number_format_i18n(count($locks))) . ')</summary><table class="bcrm-own-kv"><thead><tr><th scope="col">مالک</th><th scope="col">علت</th><th scope="col">ایجاد</th><th scope="col">وضعیت</th></tr></thead><tbody>';
        foreach ($locks as $lock) {
            $html .= '<tr><td>' . self::e(self::owner_name($view, (int)$lock->customer_id)) . '</td><td>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::LOCK_REASONS, $lock->lock_reason)) . '</td><td>' . self::e(self::date($lock->locked_at)) . '</td><td>'
                . ($lock->released_at ? 'آزادشده ' . self::e(self::date($lock->released_at)) . ' — ' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::LOCK_REASONS, $lock->release_reason)) . ($lock->released_by_case_id ? ' (پرونده #' . (int)$lock->released_by_case_id . ')' : '') : self::badge('فعال', 'warning'))
                . '</td></tr>';
        }
        return $html . '</tbody></table></details>';
    }

    private static function phone_mode_fields(string $prefix, array $view, string $selected, $phone_id, $phone): string
    {
        $html = '<fieldset class="bcrm-own-modes"><legend>روش تعیین شماره فاکتور</legend>';
        foreach (Biawin_CRM_Ownership_Presenter::PHONE_MODES as $mode => $label) {
            $html .= '<label class="bcrm-own-mode"><input type="radio" name="phone_resolution" value="' . esc_attr($mode) . '"' . checked($selected, $mode, false) . ' required><span><strong>' . self::e($label) . '</strong><small>' . self::e(Biawin_CRM_Ownership_Presenter::PHONE_MODE_HELP[$mode]) . '</small></span></label>';
        }
        // Mode-specific inputs: only the selected mode's field is shown, enabled and serialized (ownership.js).
        // Values are prefilled only when they belong to the selected mode, so a stale value can never be posted.
        $html .= '</fieldset><div class="bcrm-own-form-grid">';
        $html .= '<div class="bcrm-own-mode-field" data-own-mode-field="USE_TARGET_PROFILE_PHONE"' . ($selected === 'USE_TARGET_PROFILE_PHONE' ? '' : ' hidden') . '><label for="' . $prefix . '-pid">شماره پروفایل مقصد <small>(الزامی)</small></label><select id="' . $prefix . '-pid" name="phone_id"' . ($selected === 'USE_TARGET_PROFILE_PHONE' ? ' required' : ' disabled') . '><option value="">— انتخاب —</option>';
        foreach ($view['profile_phones'][(int)$view['case']->claimant_customer_id] ?? [] as $p) $html .= '<option value="' . (int)$p->id . '"' . ($selected === 'USE_TARGET_PROFILE_PHONE' ? selected((int)$phone_id, (int)$p->id, false) : '') . '>' . self::e($p->normalized_phone) . '</option>';
        $html .= '</select></div><div class="bcrm-own-mode-field" data-own-mode-field="PROPOSE_NEW_PHONE"' . ($selected === 'PROPOSE_NEW_PHONE' ? '' : ' hidden') . '><label for="' . $prefix . '-ph">شماره جدید <small>(الزامی)</small></label><input id="' . $prefix . '-ph" name="phone" dir="ltr" inputmode="tel" autocomplete="off" value="' . ($selected === 'PROPOSE_NEW_PHONE' ? esc_attr((string)$phone) : '') . '"' . ($selected === 'PROPOSE_NEW_PHONE' ? ' required' : ' disabled') . '></div></div>';
        return $html;
    }

    private static function final_plan_form(array $view, object $asset): string
    {
        $prefix = 'own-fp-' . (int)$asset->id;
        $action = (string)($asset->final_action ?: $asset->requested_action);
        $html = '<details class="bcrm-own-subform"><summary>تعیین برنامه نهایی این فاکتور</summary><form class="bcrm-own-action" data-own-op="set_final_plan" data-own-plan autocomplete="off" data-req-action="' . esc_attr($asset->requested_action) . '" data-req-mode="' . esc_attr((string)$asset->requested_phone_resolution) . '" data-req-pid="' . ($asset->requested_phone_resolution === 'USE_TARGET_PROFILE_PHONE' ? (int)$asset->requested_phone_id : 0) . '" data-req-phone="' . esc_attr($asset->requested_phone_resolution === 'PROPOSE_NEW_PHONE' ? (string)$asset->requested_phone_normalized : '') . '" data-own-confirm="برنامه نهایی این فاکتور ثبت شود؟ تغییر نسبت به درخواست به‌عنوان تغییر برنامه توسط کارشناس ثبت می‌شود و ممکن است سطح ریسک پرونده را تغییر دهد.">';
        $html .= '<input type="hidden" name="asset_row" value="' . (int)$asset->id . '"><div class="bcrm-own-form-grid"><label for="' . $prefix . '-a">اقدام نهایی</label><select id="' . $prefix . '-a" name="final_action">';
        foreach (Biawin_CRM_Ownership_Presenter::ACTIONS as $key => $label) $html .= '<option value="' . esc_attr($key) . '"' . selected($action, $key, false) . '>' . self::e($label) . '</option>';
        $html .= '</select></div>' . self::phone_mode_fields($prefix, $view, (string)($asset->final_phone_resolution ?: $asset->requested_phone_resolution), $asset->final_phone_id ?: $asset->requested_phone_id, $asset->final_phone_normalized ?: $asset->requested_phone_normalized);
        $html .= '<label for="' . $prefix . '-r">دلیل تغییر برنامه <small data-own-plan-hint>(در صورت تغییر نسبت به درخواست الزامی است)</small></label><textarea id="' . $prefix . '-r" name="reason" rows="2"></textarea>';
        $html .= '<button type="submit" class="bcrm-primary-button">تأیید برنامه انتقال</button><div class="bcrm-own-form-error" data-own-form-error role="alert"></div></form></details>';
        return $html;
    }

    private static function section_risk(array $view): string
    {
        $case = $view['case'];
        $body = '<div class="bcrm-own-risk is-' . strtolower((string)$case->risk_level) . '">' . self::risk_badge((string)$case->risk_level) . '</div>';
        if ($view['reviewer']) {
            $reasons = Biawin_CRM_Ownership_Repository::decode($case->risk_reasons_json ?? null);
            $body .= $reasons ? '<h4>چرا پرونده پرریسک شده؟</h4>' . self::reasons_html($reasons) : '<p>سیستم دلیل پرریسکی ثبت نکرده است.</p>';
            $body .= '<p class="bcrm-own-muted">آخرین ارزیابی: ' . self::e(self::date($case->risk_evaluated_at ?? '')) . '. سطح ریسک فقط توسط سیستم تعیین می‌شود و کاهش آن ممکن نیست.</p>';
        } else {
            $body .= '<p class="bcrm-own-muted">دلایل ریسک برای کارشناس تطبیق مالکیت نمایش داده می‌شود.</p>';
        }
        if ($case->risk_level === 'HIGH_RISK') {
            $who = $case->supervisor_user_id
                ? 'تأیید سرپرست: ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::SUPERVISOR, $case->supervisor_approval_status) . ' — ' . Biawin_CRM_Ownership_Presenter::user_name($case->supervisor_user_id)
                : 'پس از تأیید کارشناس، یک سرپرست تطبیق مالکیت که خودش تصمیم انتقال را ثبت نکرده است باید پرونده را تأیید کند.';
            $body .= '<h4>چه کسی باید تأیید کند؟</h4><p>' . self::e($who) . '</p>';
        }
        return self::section('own-risk', 'ریسک پرونده', $body);
    }

    private static function section_holds(array $view): string
    {
        $body = '<div class="bcrm-own-legend">'
            . '<div class="is-hold"><strong>⏸ توقف موقت پروفایل (Customer Hold)</strong><p>با ارسال پرونده روی هر دو پروفایل اعمال می‌شود و پروفایل را تا پایان همین پرونده فقط‌خواندنی می‌کند. با بستن یا لغو پرونده آزاد می‌شود.</p></div>'
            . '<div class="is-lock"><strong>🔒 قفل مالکیت دائمی (Ownership Lock)</strong><p>روی دارایی پروفایل‌های تکمیل‌شده است و مستقل از پرونده باقی می‌ماند. مانع ثبت درخواست تطبیق نیست؛ فقط تغییر عادی مالکیت را مسدود می‌کند.</p></div>'
            . '<div class="is-case"><strong>⚑ پرونده فعال (Active Ownership Case)</strong><p>دارایی‌ای که در پرونده ارسال‌شده و باز دیگری است، در پرونده دوم قابل ثبت نیست.</p></div>'
            . '</div>';
        if (!$view['holds']) {
            $body .= '<p>هنوز توقفی برای این پرونده ثبت نشده است' . ($view['case']->status === 'DRAFT' ? ' (پیش‌نویس روی پروفایل‌ها اثری ندارد).' : '.') . '</p>';
        } else {
            $body .= '<div class="bcrm-table-wrap"><table class="bcrm-own-kv"><thead><tr><th scope="col">پروفایل</th><th scope="col">وضعیت</th><th scope="col">ایجاد</th><th scope="col">آزادسازی</th><th scope="col">علت آزادسازی</th></tr></thead><tbody>';
            foreach ($view['holds'] as $hold) {
                $body .= '<tr><td>' . self::e(self::owner_name($view, (int)$hold->customer_id)) . '</td><td>' . ($hold->status === 'ACTIVE' ? self::badge('فعال — فقط خواندنی', 'warning', '⏸') : self::badge('آزادشده', 'neutral')) . '</td><td>' . self::e(self::date($hold->created_at)) . '</td><td>' . self::e(self::date($hold->released_at)) . '</td><td>' . self::e($hold->release_reason ? Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::HOLD_REASONS, $hold->release_reason) : '—') . '</td></tr>';
            }
            $body .= '</tbody></table></div>';
        }
        if ($view['other_holds']) {
            $body .= self::alert('info', 'این پروفایل‌ها توقف فعال از ' . self::e(number_format_i18n(count($view['other_holds']))) . ' پرونده تطبیق دیگر نیز دارند؛ پایان این پرونده آن توقف‌ها را آزاد نمی‌کند.');
        }
        return self::section('own-holds', 'توقف پروفایل و قفل مالکیت', $body);
    }

    private static function section_evidence(array $view, array $actions): string
    {
        $conflicting = [];
        foreach ($view['data']['events'] ?? [] as $event) if ($event->event_type === 'EVIDENCE_ADDED') {
            $meta = Biawin_CRM_Ownership_Repository::decode($event->meta_json);
            if (!empty($meta['conflicting'])) $conflicting[(int)($meta['attachment_id'] ?? 0)] = true;
        }
        $body = '';
        if (!$view['reviewer']) $body .= '<p class="bcrm-own-muted">فقط مدارکی که خودتان بارگذاری کرده‌اید نمایش داده می‌شود.</p>';
        $body .= '<ul class="bcrm-own-evidence">';
        foreach ($view['evidence'] as $item) {
            $e = $item['row'];
            $preview = '';
            if ($item['url'] !== '' && strpos($item['mime'], 'image/') === 0) $preview = '<a href="' . esc_url($item['url']) . '" target="_blank" rel="noopener"><img src="' . esc_url($item['url']) . '" alt="پیش‌نمایش ' . esc_attr($item['filename']) . '" loading="lazy"></a>';
            elseif ($item['url'] !== '' && strpos($item['mime'], 'audio/') === 0) $preview = '<audio controls preload="none" src="' . esc_url($item['url']) . '" aria-label="پخش ' . esc_attr($item['filename']) . '"></audio>';
            elseif ($item['url'] !== '') $preview = '<a class="bcrm-own-file" href="' . esc_url($item['url']) . '" target="_blank" rel="noopener">مشاهده فایل</a>';
            $body .= '<li class="bcrm-own-evidence-item">' . $preview . '<div><strong>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::EVIDENCE, $e->file_kind)) . '</strong>'
                . (!empty($conflicting[(int)$e->id]) ? ' ' . self::badge('مدرک متعارض', 'danger', '≠') : '')
                . '<p class="bcrm-own-muted"><bdi>' . self::e($item['filename'] ?: '—') . '</bdi> · ' . self::e($item['uploader']) . ' · ' . self::e(self::date($e->created_at)) . '</p>' . ($e->note ? '<p>' . self::e($e->note) . '</p>' : '') . '</div></li>';
        }
        $body .= '</ul>';
        if (!$view['evidence']) $body .= '<div class="bcrm-own-empty"><strong>مدرکی ثبت نشده است.</strong><p>تأیید انتقال بدون مدرک پشتیبان ممکن نیست.</p></div>';
        if ($actions['add_evidence']) {
            $body .= '<details class="bcrm-own-subform"' . ($view['evidence'] ? '' : ' open') . '><summary>بارگذاری مدرک</summary><form class="bcrm-own-action" data-own-op="add_evidence" data-own-upload enctype="multipart/form-data"><div class="bcrm-own-form-grid">'
                . '<label for="own-ev-kind">نوع مدرک</label><select id="own-ev-kind" name="kind" required><option value="">— انتخاب —</option>';
            foreach (Biawin_CRM_Ownership_Presenter::EVIDENCE as $key => $label) $body .= '<option value="' . esc_attr($key) . '">' . self::e($label) . '</option>';
            $body .= '</select><label for="own-ev-file">فایل <small>(JPG، PNG، WEBP، PDF، MP3، WAV)</small></label><input id="own-ev-file" type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.pdf,.mp3,.wav,image/jpeg,image/png,image/webp,application/pdf,audio/mpeg,audio/wav">'
                . '<label for="own-ev-note">توضیح <small>(حداکثر ۲۵۵ نویسه)</small></label><input id="own-ev-note" name="note" maxlength="255"></div>';
            if ($actions['conflicting_evidence']) $body .= '<label class="bcrm-own-check"><input type="checkbox" name="conflicting" value="1"> این مدرک با ادعا متعارض است <small>(سطح ریسک پرونده توسط سیستم بازارزیابی می‌شود)</small></label>';
            $body .= '<p class="bcrm-own-muted">مدرک پس از ثبت قابل ویرایش یا حذف نیست.</p><button type="submit" class="bcrm-primary-button">بارگذاری و ثبت مدرک</button><div class="bcrm-own-progress" data-own-progress hidden>در حال بارگذاری…</div><div class="bcrm-own-form-error" data-own-form-error role="alert"></div></form></details>';
        } elseif (!in_array($view['case']->status, ['CLOSED', 'CANCELLED'], true)) {
            $body .= '<p class="bcrm-own-muted">ثبت مدرک در وضعیت فعلی پرونده یا با دسترسی شما ممکن نیست' . (in_array($view['case']->status, ['AWAITING_SUPERVISOR_APPROVAL', 'DECIDED'], true) ? ' (پس از تصمیم کارشناس، مدارک قابل تغییر نیستند).' : '.') . '</p>';
        }
        return self::section('own-evidence', 'مدارک', $body);
    }

    private static function section_otp(array $view, array $actions): string
    {
        if (!$view['reviewer'] || !self::has_phone_asset($view)) return '';
        $body = self::alert('info', 'کد یکبارمصرف فقط برای شماره‌ای که صریحاً مورد ادعاست ارسال می‌شود. تأیید کد فقط مدرک کنترل شماره است و به‌تنهایی مالکیت هیچ دارایی را منتقل نمی‌کند. کد هرگز در سیستم نمایش یا ذخیره نمی‌شود.');
        $phones = [];
        foreach ($view['assets'] as $item) if ($item['row']->asset_type === 'PHONE') $phones[(int)$item['row']->asset_id] = $item['live']->normalized_phone ?? ('#' . $item['row']->asset_id);
        if ($view['otp']) {
            $body .= '<div class="bcrm-table-wrap"><table class="bcrm-own-kv"><thead><tr><th scope="col">شماره</th><th scope="col">هدف</th><th scope="col">ارسال</th><th scope="col">انقضا</th><th scope="col">تلاش‌ها</th><th scope="col">وضعیت</th></tr></thead><tbody>';
            foreach ($view['otp'] as $otp) {
                $body .= '<tr><td><bdi dir="ltr">' . self::e($otp->phone_normalized) . '</bdi></td><td>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::OTP_PURPOSES, $otp->purpose)) . '</td><td>' . self::e(self::date($otp->sent_at, true)) . '</td><td>' . self::e(self::date($otp->expires_at, true)) . '</td><td>' . self::e(number_format_i18n((int)$otp->attempt_count) . ' از ' . number_format_i18n((int)$otp->max_attempts)) . '</td><td>' . self::e(Biawin_CRM_Ownership_Presenter::otp_state($otp)) . '</td></tr>';
            }
            $body .= '</tbody></table></div>';
        } else {
            $body .= '<p>هنوز کدی ارسال نشده است.</p>';
        }
        if ($actions['otp_issue']) {
            $body .= '<form class="bcrm-own-action is-inline" data-own-op="otp_issue" data-own-confirm="کد تأیید به شماره مورد اختلاف ارسال شود؟"><div class="bcrm-own-form-grid"><label for="own-otp-phone">شماره مورد اختلاف</label><select id="own-otp-phone" name="phone_asset" required>';
            foreach ($phones as $pid => $num) $body .= '<option value="' . (int)$pid . '">' . self::e($num) . '</option>';
            $body .= '</select><label for="own-otp-purpose">هدف</label><select id="own-otp-purpose" name="purpose">';
            foreach (Biawin_CRM_Ownership_Presenter::OTP_PURPOSES as $key => $label) $body .= '<option value="' . esc_attr($key) . '">' . self::e($label) . '</option>';
            $body .= '</select></div><button type="submit" class="bcrm-secondary-button">ارسال کد تأیید</button><div class="bcrm-own-form-error" data-own-form-error role="alert"></div></form>';
        }
        $open = array_filter($view['otp'], static function ($o) { return !$o->verified_at && !$o->consumed_at && (Biawin_CRM_Ownership_Repository::decode($o->provider_meta_json)['accepted'] ?? false) === true; });
        if ($actions['otp_verify'] && $open) {
            $body .= '<form class="bcrm-own-action is-inline" data-own-op="otp_verify"><div class="bcrm-own-form-grid"><label for="own-otp-ch">کد ارسال‌شده</label><select id="own-otp-ch" name="challenge" required data-own-otp-challenge>';
            foreach ($open as $o) $body .= '<option value="' . (int)$o->id . '" data-phone-asset="' . (int)$o->phone_asset_id . '" data-purpose="' . esc_attr($o->purpose) . '">' . self::e($o->phone_normalized . ' — ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::OTP_PURPOSES, $o->purpose) . ' — انقضا ' . self::date($o->expires_at, true)) . '</option>';
            $body .= '</select><input type="hidden" name="phone_asset"><input type="hidden" name="purpose"><label for="own-otp-code">کد ۶ رقمی اعلام‌شده توسط مشتری</label><input id="own-otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹]{6}" maxlength="6" dir="ltr" required></div><button type="submit" class="bcrm-primary-button">ثبت و بررسی کد</button><div class="bcrm-own-form-error" data-own-form-error role="alert"></div></form>';
        }
        return self::section('own-otp', 'تأیید شماره با کد یکبارمصرف (OTP)', $body);
    }

    /** Decision summary: what an approved execution would move, read from case data only. */
    private static function decision_summary(array $view): string
    {
        $case = $view['case'];
        $invoices = []; $phones = []; $resolutions = []; $services = [];
        foreach ($view['assets'] as $item) {
            $a = $item['row'];
            if (self::effective($a, 'action') !== 'TRANSFER') continue;
            if ($a->asset_type === 'INVOICE') {
                $code = $item['live']->invoice_code ?? ('#' . $a->asset_id);
                $invoices[] = $code;
                $resolutions[] = $code . ': ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::PHONE_MODES, self::effective($a, 'phone_resolution'));
                foreach ($item['snapshot']['services'] ?? [] as $s) if (is_array($s)) $services[] = $code . ': ' . ($s['service_name'] ?? '');
            } else {
                $phones[] = $item['live']->normalized_phone ?? ('#' . $a->asset_id);
            }
        }
        $rows = [
            'مبدأ' => self::profile_label($view['source']),
            'مقصد' => self::profile_label($view['claimant']),
            'فاکتورهای منتقل‌شونده' => $invoices ? '<bdi>' . self::e(implode('، ', $invoices)) . '</bdi>' : 'هیچ',
            'شماره‌های منتقل‌شونده' => $phones ? '<bdi dir="ltr">' . self::e(implode('، ', $phones)) . '</bdi>' : 'هیچ — مالکیت هیچ شماره‌ای منتقل نمی‌شود',
            'روش شماره هر فاکتور' => $resolutions ? self::e(implode(' | ', $resolutions)) : '—',
            'خدمات همراه فاکتورها' => $view['reviewer'] ? ($services ? self::e(implode('، ', $services)) : 'بدون خدمت') : 'برای کارشناس نمایش داده می‌شود',
            'ریسک' => self::risk_badge((string)$case->risk_level),
            'تصمیم کارشناس' => $case->decision ? self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::DECISIONS, $case->decision) . ' — ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::METHODS, $case->decision_method) . ' — ' . Biawin_CRM_Ownership_Presenter::user_name($case->decided_by_user_id)) : 'هنوز تصمیم ثبت نشده',
            'تأیید سرپرست' => self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::SUPERVISOR, $case->supervisor_approval_status) . ($case->supervisor_user_id ? ' — ' . Biawin_CRM_Ownership_Presenter::user_name($case->supervisor_user_id) : '')),
        ];
        $html = '<dl class="bcrm-own-summary">';
        foreach ($rows as $label => $value) $html .= '<div><dt>' . self::e($label) . '</dt><dd>' . $value . '</dd></div>';
        return $html . '</dl>';
    }

    private static function section_decision(array $view): string
    {
        $case = $view['case'];
        $body = self::decision_summary($view);
        if (!empty($case->decision_note)) $body .= '<div class="bcrm-own-note"><strong>یادداشت تصمیم کارشناس:</strong><p>' . nl2br(self::e($case->decision_note)) . '</p></div>';
        if ($view['reviewer'] && !empty($case->supervisor_note)) $body .= '<div class="bcrm-own-note"><strong>یادداشت سرپرست:</strong><p>' . nl2br(self::e($case->supervisor_note)) . '</p></div>';
        return self::section('own-decision', 'تصمیم', $body);
    }

    /** J. Execution / recovery: the four backend execution states as a progress track. */
    private static function section_execution(array $view): string
    {
        $case = $view['case'];
        $state = (string)$case->execution_state;
        $progress = $view['reviewer'] ? Biawin_CRM_Ownership_Repository::decode($case->finalization_json ?? null) : [];
        $stages = [
            ['NOT_STARTED', 'اجرا نشده', 'هنوز هیچ مالکیتی منتقل نشده است.'],
            ['COMMITTED', 'ثبت قطعی انتقال', 'مالکیت دارایی‌ها در یک تراکنش منتقل شد.'],
            ['FINALIZED', 'نهایی‌سازی', 'بازمحاسبه پروفایل‌ها، آزادسازی توقف و بستن پرونده.'],
        ];
        $classes = [
            'NOT_STARTED' => ['is-current', '', ''],
            'COMMITTED' => ['is-done', 'is-done', 'is-current'],
            'FINALIZATION_FAILED' => ['is-done', 'is-done', 'is-failed'],
            'FINALIZED' => ['is-done', 'is-done', 'is-done'],
        ][$state] ?? ['', '', ''];
        $sr = ['is-done' => '(انجام شده)', 'is-current' => '(مرحله فعلی)', 'is-failed' => '(ناتمام)', '' => ''];
        $body = '<p>' . self::execution_badge($state) . ($case->executed_at ? ' — ثبت انتقال: ' . self::e(self::date($case->executed_at)) : '') . '</p><ol class="bcrm-own-track" aria-label="مراحل اجرا">';
        foreach ($stages as $i => [$key, $label, $help]) {
            $cls = $classes[$i];
            $body .= '<li class="' . $cls . '"' . ($cls === 'is-current' ? ' aria-current="step"' : '') . '><strong>' . self::e($label) . '</strong><span>' . self::e($help) . '</span>' . ($sr[$cls] !== '' ? '<span class="bcrm-own-sr">' . $sr[$cls] . '</span>' : '') . '</li>';
        }
        $body .= '</ol>';
        if ($state === 'NOT_STARTED') {
            $intro = '<p>' . self::execution_badge($state) . ' هنوز هیچ مالکیتی منتقل نشده است.</p>';
            $body = $intro . '<details class="bcrm-own-collapsible"><summary>نمایش مراحل اجرا</summary>' . substr($body, strpos($body, '<ol class="bcrm-own-track"')) . '</details>';
        }
        if ($state === 'FINALIZATION_FAILED') {
            $body .= self::alert('danger', '<strong>انتقال مالکیت قبلاً به‌صورت قطعی ثبت شده است؛ نهایی‌سازی ناتمام مانده است.</strong> اجرای دوباره انتقال مجاز نیست و انجام نمی‌شود. تنها اقدام مجاز «ادامه نهایی‌سازی» است که بازمحاسبه پروفایل‌ها و بستن پرونده را کامل می‌کند.', 'alert');
        }
        if ($view['reviewer'] && $state !== 'NOT_STARTED') {
            $body .= '<ul class="bcrm-own-checklist"><li class="' . (!empty($progress['source']) ? 'is-done' : 'is-todo') . '">' . (!empty($progress['source']) ? '✓ بازمحاسبه پروفایل مبدأ انجام شد' : '○ بازمحاسبه پروفایل مبدأ انجام نشده') . '</li><li class="' . (!empty($progress['target']) ? 'is-done' : 'is-todo') . '">' . (!empty($progress['target']) ? '✓ بازمحاسبه پروفایل مقصد انجام شد' : '○ بازمحاسبه پروفایل مقصد انجام نشده') . '</li></ul>';
        }
        return self::section('own-execution', 'اجرا و بازیابی', $body);
    }

    private static function event_summary(object $event, array $view): string
    {
        $meta = Biawin_CRM_Ownership_Repository::decode($event->meta_json);
        $parts = [];
        if (isset($meta['from'], $meta['to'])) $parts[] = Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::STATUSES, $meta['from']) . ' ← ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::STATUSES, $meta['to']);
        foreach (['note' => 'یادداشت', 'reason' => 'دلیل'] as $key => $label) if (!empty($meta[$key]) && is_string($meta[$key])) $parts[] = $label . ': ' . $meta[$key];
        if (!empty($meta['method'])) $parts[] = 'روش: ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::METHODS, $meta['method']);
        if (!empty($meta['result'])) $parts[] = 'نتیجه: ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::CONTACT_RESULTS, $meta['result']);
        if (!empty($meta['kind'])) $parts[] = 'نوع: ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::EVIDENCE, $meta['kind']) . (!empty($meta['conflicting']) ? ' (متعارض)' : '');
        if (!empty($meta['reasons']) && is_array($meta['reasons'])) $parts[] = 'دلایل: ' . implode('، ', array_map(static function ($r) { return Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::RISK_REASONS, $r); }, $meta['reasons']));
        if (!empty($meta['resolution'])) $parts[] = 'روش شماره: ' . Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::PHONE_MODES, $meta['resolution']);
        if (in_array($event->event_type, ['INVOICE_OWNER_CHANGED', 'PHONE_OWNER_CHANGED', 'SERVICE_OWNER_ALIGNED'], true) && isset($meta['before'], $meta['after'])) $parts[] = self::owner_name($view, (int)$meta['before']) . ' ← ' . self::owner_name($view, (int)$meta['after']);
        if ($event->event_type === 'INVOICE_PHONE_CHANGED') $parts[] = 'شماره: ' . ($meta['before'] ?: '—') . ' ← ' . ($meta['after'] ?? '—');
        if ($event->event_type === 'CASE_ASSIGNED' && isset($meta['after'])) $parts[] = 'کارشناس: ' . Biawin_CRM_Ownership_Presenter::user_name($meta['after']);
        if (!empty($meta['step'])) $parts[] = $meta['step'] === 'source' ? 'پروفایل مبدأ' : 'پروفایل مقصد';
        if (!empty($meta['error'])) $parts[] = 'خطا: ' . $meta['error'];
        if (!empty($meta['attempt'])) $parts[] = 'تلاش ' . number_format_i18n((int)$meta['attempt']);
        if ($event->customer_id) $parts[] = 'پروفایل: ' . self::owner_name($view, (int)$event->customer_id);
        if ($event->asset_type) $parts[] = ($event->asset_type === 'INVOICE' ? 'فاکتور' : 'شماره') . ' #' . (int)$event->asset_id;
        return implode(' · ', $parts);
    }

    private static function section_timeline(array $view): string
    {
        $case = $view['case'];
        if (isset($view['data']['events'])) {
            $body = '<ol class="bcrm-own-timeline">';
            foreach ($view['data']['events'] as $event) {
                $summary = self::event_summary($event, $view);
                $critical = in_array($event->event_type, ['CASE_EXECUTION_FAILED', 'RISK_ESCALATED', 'MANUAL_RISK_ESCALATION', 'SUPERVISOR_REJECTED', 'OTP_FAILED', 'OTP_EXPIRED', 'OTP_DELIVERY_FAILED', 'OTP_REUSE_REJECTED'], true);
                $body .= '<li class="' . ($critical ? 'is-critical' : '') . '"><time>' . self::e(self::date($event->created_at)) . '</time><div><strong>' . self::e(Biawin_CRM_Ownership_Presenter::label(Biawin_CRM_Ownership_Presenter::EVENTS, $event->event_type)) . '</strong> <small class="bcrm-own-code">' . self::e($event->event_type) . '</small><p class="bcrm-own-muted">' . self::e(Biawin_CRM_Ownership_Presenter::user_name($event->actor_user_id)) . '</p>' . ($summary !== '' ? '<p>' . self::e($summary) . '</p>' : '')
                    . (($event->meta_json ?? '') !== '' && $event->meta_json !== '[]' && $event->meta_json !== '{}' ? '<details class="bcrm-own-tech"><summary>جزئیات فنی</summary><pre dir="ltr">' . self::e((string)wp_json_encode(Biawin_CRM_Ownership_Repository::decode($event->meta_json), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></details>' : '')
                    . '</div></li>';
            }
            if (!$view['data']['events']) $body .= '<li>رویدادی ثبت نشده است.</li>';
            $body .= '</ol>';
            if ($view['amendments']) {
                $body .= '<h4>اصلاحیه‌های رسمی</h4><ul class="bcrm-own-list-plain">';
                foreach ($view['amendments'] as $am) $body .= '<li>' . self::e(self::date($am->created_at)) . ' — ' . self::e(Biawin_CRM_Ownership_Presenter::user_name($am->created_by_user_id)) . ': ' . self::e($am->reason) . '</li>';
                $body .= '</ul>';
            }
            return self::section('own-timeline', 'تاریخچه رویدادها (غیرقابل تغییر)', $body);
        }
        $points = ['ایجاد پیش‌نویس' => $case->created_at ?? '', 'ارسال برای بررسی' => $case->submitted_at ?? '', 'تخصیص به کارشناس' => $case->assigned_at ?? '', 'شروع بررسی' => $case->review_started_at ?? '', 'تصمیم کارشناس' => $case->decided_at ?? '', 'تصمیم سرپرست' => $case->supervisor_decided_at ?? '', 'ثبت انتقال' => $case->executed_at ?? '', 'بستن پرونده' => $case->closed_at ?? '', 'لغو پرونده' => $case->cancelled_at ?? ''];
        $body = '<ol class="bcrm-own-timeline">';
        foreach ($points as $label => $value) if ($value) $body .= '<li><time>' . self::e(self::date($value)) . '</time><div><strong>' . self::e($label) . '</strong></div></li>';
        $body .= '</ol><p class="bcrm-own-muted">تاریخچه کامل رویدادها برای کارشناس تطبیق مالکیت نمایش داده می‌شود.</p>';
        return self::section('own-timeline', 'خلاصه زمانی پرونده', $body);
    }

    // ------------------------------------------------------------------ action panel

    /** Primary (expected next) actions stay open; secondary and destructive ones collapse into an accordion. */
    private static function form(string $op, string $title, string $fields, string $button, string $confirm = '', string $tone = 'primary', string $intro = ''): string
    {
        $irreversible = in_array($op, ['submit', 'cancel', 'decide', 'supervisor_decision', 'execute', 'evaluate_risk'], true);
        $form = self::form_markup($op, $title, $fields, $button, $confirm, $tone, $intro, $irreversible);
        if ($tone === 'primary') return $form;
        return '<details class="bcrm-own-action-toggle is-' . esc_attr($tone) . '"><summary>' . self::e($title) . '</summary>' . $form . '</details>';
    }

    private static function form_markup(string $op, string $title, string $fields, string $button, string $confirm, string $tone, string $intro, bool $irreversible): string
    {
        return '<form class="bcrm-own-action" autocomplete="off" data-own-op="' . esc_attr($op) . '" data-own-irreversible="' . ($irreversible ? '1' : '0') . '"' . ($confirm !== '' ? ' data-own-confirm="' . esc_attr($confirm) . '"' : '') . '><h5>' . self::e($title) . '</h5>' . ($intro !== '' ? '<p class="bcrm-own-muted">' . $intro . '</p>' : '') . $fields
            . '<button type="submit" class="' . ($tone === 'danger' ? 'bcrm-secondary-button is-danger' : ($tone === 'secondary' ? 'bcrm-secondary-button' : 'bcrm-primary-button')) . '">' . self::e($button) . '</button><div class="bcrm-own-form-error" data-own-form-error role="alert"></div></form>';
    }

    private static function textarea(string $id, string $label, bool $required = true, string $name = 'note'): string
    {
        return '<label for="' . esc_attr($id) . '">' . self::e($label) . ($required ? ' <small>(الزامی)</small>' : '') . '</label><textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="3"' . ($required ? ' required' : '') . '></textarea>';
    }

    private static function method_select(string $id): string
    {
        $html = '<label for="' . esc_attr($id) . '">روش احراز</label><select id="' . esc_attr($id) . '" name="method" required><option value="">— انتخاب —</option>';
        foreach (Biawin_CRM_Ownership_Presenter::METHODS as $key => $label) $html .= '<option value="' . esc_attr($key) . '">' . self::e($label) . '</option>';
        return $html . '</select>';
    }

    private static function readonly_panel(object $case): string
    {
        $text = $case->status === 'CANCELLED' ? 'این پرونده لغو شده است.' : 'این پرونده بسته شده است.';
        return '<div class="bcrm-own-actions-card"><h3>اقدامات</h3><p>' . self::e($text) . ' پرونده فقط‌خواندنی است و هیچ اقدامی روی آن مجاز نیست.</p></div>';
    }

    private static function group(string $role, string $title, string $intro, array $items): string
    {
        $items = array_values(array_filter($items, static function ($x) { return $x !== ''; }));
        if (!$items) return '';
        return '<section class="bcrm-own-group is-' . esc_attr($role) . '" aria-label="' . esc_attr($title) . '"><h4 class="bcrm-own-group-title">' . self::e($title) . '</h4>' . ($intro !== '' ? '<p class="bcrm-own-muted">' . $intro . '</p>' : '') . implode('', $items) . '</section>';
    }

    /** One plain-language "what happens next" line for the current viewer. */
    private static function next_step(array $view, array $actions): array
    {
        $case = $view['case'];
        $high = $case->risk_level === 'HIGH_RISK';
        if ($actions['submit']) return ['you', 'دارایی‌ها و مدارک را بررسی کنید و سپس «ارسال برای بررسی» را بزنید.'];
        if ($actions['resume']) return ['you', 'انتقال قبلاً ثبت شده است؛ فقط «ادامه نهایی‌سازی» را بزنید.'];
        if ($actions['execute']) return ['you', 'تصمیم نهایی ثبت شده است؛ خلاصه را بازبینی و «اجرای انتقال مالکیت» را بزنید.'];
        if ($actions['supervisor']) return ['you', 'خلاصه، دلایل ریسک، مدارک و تاریخچه را بررسی کنید و «تأیید سرپرست» یا «بازگرداندن به بررسی» را انتخاب کنید.'];
        if ($actions['assign'] && $case->status === 'SUBMITTED') return ['you', 'پرونده را به یک کارشناس تطبیق مالکیت تخصیص دهید.'];
        if ($actions['start_review'] && $case->status === 'ASSIGNED') return ['you', '«شروع بررسی» را بزنید.'];
        if ($actions['start_review']) return ['you', 'پس از دریافت مدرک یا تأیید مشتری، «بازگشت به بررسی» را بزنید.'];
        if ($actions['approve']) return ['you', 'مدارک و تماس‌ها را تکمیل کنید، برنامه نهایی هر فاکتور را تأیید کنید و سپس تصمیم بگیرید.' . ($high ? ' این پرونده پرریسک است: پس از تأیید شما، تأیید مستقل سرپرست لازم است.' : '')];
        if ($actions['add_evidence']) return ['other', 'پرونده در جریان بررسی است. اگر مدرک خواسته شده، از بخش «مدارک» بارگذاری کنید.'];
        return ['other', self::idle_reason($view)];
    }

    /** Read-only review checklist from case data; it guides, it does not gate (the backend gates). */
    private static function review_checklist(array $view): string
    {
        $case = $view['case'];
        $events = $view['data']['events'] ?? [];
        $has = static function (string $type) use ($events) { foreach ($events as $e) if ($e->event_type === $type) return true; return false; };
        $invoices = array_filter($view['assets'], static function ($a) { return $a['row']->asset_type === 'INVOICE'; });
        $planned = array_filter($invoices, static function ($a) { return (string)$a['row']->final_phone_resolution !== ''; });
        $items = [
            [count($view['evidence']) > 0, 'مدرک پشتیبان ثبت شده است'],
            [$has('CONTACT_RECORDED'), 'تماس با طرفین ثبت شده است'],
            [count($invoices) === 0 || count($planned) === count($invoices), 'برنامه نهایی همه فاکتورها تأیید شده است (' . number_format_i18n(count($planned)) . ' از ' . number_format_i18n(count($invoices)) . ')'],
            [!empty($case->risk_evaluated_at), 'ریسک توسط سیستم ارزیابی شده است'],
        ];
        $html = '<div class="bcrm-own-checklist-card"><h4>چک‌لیست بررسی</h4><ul class="bcrm-own-checklist">';
        foreach ($items as [$done, $label]) $html .= '<li class="' . ($done ? 'is-done' : 'is-todo') . '"><span aria-hidden="true">' . ($done ? '✓' : '○') . '</span> ' . self::e($label) . ' <span class="bcrm-own-sr">' . ($done ? '(انجام شده)' : '(انجام نشده)') . '</span></li>';
        return $html . '</ul><p class="bcrm-own-muted">این فهرست راهنماست؛ شرایط تأیید را سیستم هنگام ثبت تصمیم بررسی می‌کند.</p></div>';
    }

    private static function action_panel(array $view, array $actions): string
    {
        $case = $view['case'];
        $status = (string)$case->status;
        $high = $case->risk_level === 'HIGH_RISK';
        [$who, $next] = self::next_step($view, $actions);
        $html = '<div class="bcrm-own-actions-card"><div class="bcrm-own-next is-' . $who . '" role="status"><span class="bcrm-own-kicker">گام بعدی</span><strong>' . self::e($next) . '</strong></div>';
        if ($view['reviewer'] && in_array($status, Biawin_CRM_Ownership_Workflow::REVIEW_STATES, true)) $html .= self::review_checklist($view);
        if ($high && !in_array($status, ['CLOSED', 'CANCELLED'], true) && $view['reviewer']) {
            $html .= self::alert('danger', '<strong>⚠ پرونده پرریسک:</strong> تأیید کارشناس به‌تنهایی کافی نیست؛ پس از تأیید کارشناس، تأیید مستقل سرپرستی غیر از تصمیم‌گیرنده لازم است.');
        }

        $requester = [];
        if ($actions['update_draft']) {
            $requester[] = self::form('update_draft', 'ویرایش پیش‌نویس', '<label for="own-d-reason">دلیل درخواست</label><input id="own-d-reason" name="reason" maxlength="120" value="' . esc_attr((string)$case->claim_reason) . '"><label for="own-d-note">توضیحات</label><textarea id="own-d-note" name="note" rows="3">' . esc_textarea((string)$case->request_note) . '</textarea>', 'ذخیره تغییرات پیش‌نویس', '', 'secondary');
            $requester[] = self::form('submit', 'ارسال برای بررسی', '', 'ارسال برای بررسی', self::NOT_TRANSFER . ' با ارسال، هر دو پروفایل تا تعیین تکلیف پرونده فقط خواندنی می‌شوند و پیش‌نویس دیگر قابل ویرایش نیست. ادامه می‌دهید؟', 'primary', self::e(self::NOT_TRANSFER));
            $requester[] = self::form('cancel', 'لغو پیش‌نویس', self::textarea('own-cd-reason', 'دلیل لغو', true, 'reason'), 'لغو پیش‌نویس', 'پیش‌نویس لغو شود؟ این اقدام برگشت‌پذیر نیست.', 'danger');
        }

        $specialist = [];
        if ($actions['start_review']) {
            $specialist[] = self::form('start_review', $status === 'ASSIGNED' ? 'شروع بررسی' : 'ادامه بررسی', '', $status === 'ASSIGNED' ? 'شروع بررسی' : 'بازگشت به بررسی', '', 'primary', $status === 'ASSIGNED' ? '' : 'پس از دریافت مدرک یا تأیید مشتری، پرونده را به وضعیت «در حال بررسی» برگردانید.');
        }
        if ($actions['request_evidence']) {
            $specialist[] = self::form('request_evidence', 'درخواست مدرک / تأیید مشتری', '<fieldset class="bcrm-own-radios"><legend>نوع درخواست</legend><label><input type="radio" name="customer_confirmation" value="0" checked> درخواست مدرک</label><label><input type="radio" name="customer_confirmation" value="1"> درخواست تأیید مشتری</label></fieldset>' . self::textarea('own-re-note', 'شرح درخواست'), 'ثبت درخواست مدرک', '', 'secondary');
        }
        if ($actions['record_contact']) {
            $opts = '';
            foreach (Biawin_CRM_Ownership_Presenter::CONTACT_RESULTS as $key => $label) $opts .= '<option value="' . esc_attr($key) . '">' . self::e($label) . '</option>';
            $specialist[] = self::form('record_contact', 'ثبت تماس', '<label for="own-ct-r">نتیجه تماس</label><select id="own-ct-r" name="result" required>' . $opts . '</select>' . self::textarea('own-ct-note', 'شرح تماس'), 'ثبت تماس', '', 'secondary', 'برای روش «رضایت مالک فعلی»، تماس با نتیجه «رضایت مالک فعلی» لازم است.');
        }
        if ($actions['escalate']) {
            $specialist[] = self::form('evaluate_risk', 'ارزیابی / ارجاع ریسک', self::textarea('own-esc', 'دلیل ارجاع دستی به پرریسک (برای فقط بازارزیابی خالی بگذارید)', false, 'escalation_reason'), 'ثبت ارزیابی ریسک', 'ارزیابی ریسک ثبت شود؟ در صورت درج دلیل، پرونده پرریسک می‌شود و این سطح قابل کاهش نیست.', 'secondary');
        }
        if ($actions['approve'] || $actions['reject'] || $actions['withdraw']) {
            $specialist[] = '<div class="bcrm-own-decision-box"><h5>خلاصه پیش از تصمیم کارشناس</h5>' . self::decision_summary($view) . '</div>';
            if ($actions['approve']) {
                $specialist[] = self::form('decide', 'تأیید انتقال', '<input type="hidden" name="decision" value="TRANSFER_APPROVED">' . self::method_select('own-ap-m') . self::textarea('own-ap-note', 'یادداشت تصمیم'), $high ? 'تأیید انتقال و ارسال برای تأیید سرپرست' : 'تأیید انتقال',
                    $high ? 'پرونده پرریسک است: تأیید شما برای تأیید مستقل به صف سرپرست می‌رود و هنوز مالکیتی منتقل نمی‌شود. ادامه می‌دهید؟' : 'تأیید انتقال ثبت شود؟ پس از تأیید، اجرای انتقال به‌صورت جداگانه انجام می‌شود.',
                    'primary', $high ? 'این پرونده پرریسک است و پس از تأیید شما به تأیید مستقل سرپرست نیاز دارد.' : 'تأیید فقط با مدرک پشتیبان ثبت‌شده ممکن است.');
            }
            if ($actions['reject']) $specialist[] = self::form('decide', 'رد درخواست', '<input type="hidden" name="decision" value="CLAIM_REJECTED">' . self::method_select('own-rj-m') . self::textarea('own-rj-note', 'دلیل رد'), 'رد درخواست', 'درخواست رد و پرونده بسته شود؟ توقف هر دو پروفایل آزاد می‌شود و این اقدام برگشت‌پذیر نیست.', 'danger');
            if ($actions['withdraw']) $specialist[] = self::form('decide', 'ثبت انصراف مدعی', '<input type="hidden" name="decision" value="CLAIM_WITHDRAWN">' . self::method_select('own-wd-m') . self::textarea('own-wd-note', 'شرح انصراف'), 'ثبت انصراف', 'انصراف ثبت و پرونده بسته شود؟ این اقدام برگشت‌پذیر نیست.', 'danger');
        } elseif ($view['reviewer'] && in_array($status, ['AWAITING_EVIDENCE', 'AWAITING_CUSTOMER_CONFIRMATION'], true)) {
            $specialist[] = '<p class="bcrm-own-explain">تصمیم (تأیید/رد/انصراف) فقط در وضعیت «در حال بررسی» ممکن است؛ ابتدا «بازگشت به بررسی» را انجام دهید.</p>';
        }

        $supervisor = [];
        if ($actions['assign']) {
            $options = '<option value="">— انتخاب کارشناس —</option>';
            foreach ($view['specialists'] as $uid => $name) $options .= '<option value="' . (int)$uid . '"' . selected((int)$case->assigned_to_user_id, (int)$uid, false) . '>' . self::e($name) . '</option>';
            $supervisor[] = self::form('assign', $case->assigned_to_user_id ? 'تغییر کارشناس' : 'تخصیص به کارشناس', '<label for="own-as">کارشناس تطبیق مالکیت</label><select id="own-as" name="specialist" required>' . $options . '</select>', $case->assigned_to_user_id ? 'ثبت تغییر کارشناس' : 'تخصیص پرونده', '', $status === 'SUBMITTED' ? 'primary' : 'secondary');
        }
        if ($actions['supervisor']) {
            $supervisor[] = '<div class="bcrm-own-decision-box is-supervisor"><h5>خلاصه برای تصمیم سرپرست (High Risk)</h5>' . self::decision_summary($view) . '</div>';
            $supervisor[] = self::form('supervisor_decision', 'تأیید سرپرست', '<input type="hidden" name="approve" value="1">' . self::textarea('own-sv-a', 'یادداشت تأیید'), 'تأیید سرپرست', 'تأیید مستقل سرپرست ثبت شود؟ پس از آن انتقال آماده اجراست.');
            $supervisor[] = self::form('supervisor_decision', 'بازگرداندن به بررسی', '<input type="hidden" name="approve" value="0">' . self::textarea('own-sv-r', 'دلیل بازگرداندن'), 'بازگرداندن به بررسی', 'پرونده با این دلیل به کارشناس برگردانده شود؟', 'danger');
        } elseif ($actions['supervisor_self']) {
            $supervisor[] = '<p class="bcrm-own-explain">تأیید سرپرست برای شما در دسترس نیست زیرا خودتان تصمیم انتقال را ثبت کرده‌اید (تفکیک وظایف).</p>';
        } elseif ($status === 'AWAITING_SUPERVISOR_APPROVAL') {
            $supervisor[] = '<p class="bcrm-own-explain">پرونده منتظر تأیید مستقل سرپرست تطبیق مالکیت است.</p>';
        }
        if ($actions['cancel_case']) {
            $supervisor[] = self::form('cancel', 'لغو پرونده (سرپرست)', self::textarea('own-cc-reason', 'دلیل لغو', true, 'reason'), 'لغو پرونده', 'پرونده لغو و توقف هر دو پروفایل آزاد شود؟ این اقدام برگشت‌پذیر نیست.', 'danger');
        }

        $execution = [];
        if ($actions['execute']) {
            $execution[] = '<div class="bcrm-own-decision-box is-execute"><h5>آنچه با اجرا منتقل می‌شود</h5>' . self::decision_summary($view) . '</div>';
            $execution[] = self::form('execute', 'اجرای انتقال مالکیت', '', 'اجرای انتقال مالکیت', 'مالکیت دارایی‌های مشخص‌شده به‌صورت قطعی به پروفایل مقصد منتقل می‌شود. این اقدام برگشت‌پذیر نیست. ادامه می‌دهید؟', 'primary', 'انتقال یک‌بار و به‌صورت تراکنشی ثبت می‌شود و سپس پروفایل‌ها بازمحاسبه می‌شوند.');
        }
        if ($actions['resume']) {
            $execution[] = self::form('resume', 'ادامه نهایی‌سازی', '', 'ادامه نهایی‌سازی', 'نهایی‌سازی ادامه یابد؟ انتقال مالکیت تکرار نمی‌شود؛ فقط بازمحاسبه پروفایل‌ها و بستن پرونده تکمیل می‌شود.', 'primary', 'انتقال مالکیت قبلاً ثبت شده است و تکرار نمی‌شود.');
        } elseif ($status === 'DECIDED' && in_array($case->execution_state, ['COMMITTED', 'FINALIZATION_FAILED'], true)) {
            $execution[] = '<p class="bcrm-own-explain">ادامه نهایی‌سازی فقط توسط کارشناس تخصیص‌یافته یا سرپرست تطبیق مالکیت ممکن است.</p>';
        } elseif ($status === 'DECIDED' && !$actions['execute']) {
            $execution[] = '<p class="bcrm-own-explain">اجرای انتقال تأییدشده توسط کارشناس تخصیص‌یافته یا سرپرست تطبیق مالکیت انجام می‌شود.</p>';
        }

        $groups = self::group('requester', 'اقدامات درخواست‌دهنده', '', $requester)
            . self::group('specialist', 'اقدامات کارشناس تطبیق مالکیت', '', $specialist)
            . self::group('supervisor', 'اقدامات سرپرست تطبیق مالکیت', '', $supervisor)
            . self::group('execution', 'اجرا و بازیابی', '', $execution);
        $html .= $groups !== '' ? $groups : '<p class="bcrm-own-explain">' . self::e(self::idle_reason($view)) . '</p>';
        $html .= '<p class="bcrm-own-muted bcrm-own-authority">نمایش یک اقدام به معنی مجاز بودن قطعی آن نیست؛ سیستم هنگام اجرا دسترسی، وضعیت و نسخه پرونده را دوباره بررسی می‌کند.</p>';
        return $html . '</div>';
    }

    private static function idle_reason(array $view): string
    {
        $case = $view['case'];
        $own = (int)$case->requested_by_user_id === get_current_user_id();
        switch ($case->status) {
            case 'DRAFT': return $own ? 'دسترسی ثبت درخواست برای حساب شما فعال نیست.' : 'پیش‌نویس فقط توسط درخواست‌دهنده قابل ویرایش است.';
            case 'SUBMITTED': return 'پرونده منتظر تخصیص توسط سرپرست تطبیق مالکیت است.';
            case 'ASSIGNED': case 'UNDER_REVIEW': case 'AWAITING_EVIDENCE': case 'AWAITING_CUSTOMER_CONFIRMATION':
                return $own && !$view['reviewer'] ? 'پرونده در دست بررسی کارشناس تطبیق مالکیت است. در صورت درخواست مدرک، از بخش «مدارک» بارگذاری کنید.' : 'این پرونده به شما تخصیص داده نشده است.';
            case 'AWAITING_SUPERVISOR_APPROVAL': return 'پرونده منتظر تأیید مستقل سرپرست است.';
            case 'DECIDED': return 'تصمیم ثبت شده و اجرای آن توسط کاربر مجاز انجام می‌شود.';
        }
        return 'اقدامی برای شما در دسترس نیست.';
    }
}
