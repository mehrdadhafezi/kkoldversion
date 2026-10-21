<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * v0.8.64.0 — «تنظیمات نارضایتی» admin page (Phase 2 configuration foundation).
 * Tabs: complaint subjects · complaint outcomes · inquiry units. Each tab/handler is gated by
 * its own dedicated capability (manage_dissatisfaction_subjects / manage_dissatisfaction_outcomes /
 * manage_inquiry_units). Same admin design system as the rest of the plugin (wrap.bcrm, bcrm-card,
 * WordPress nav-tabs / widefat tables).
 */
class Biawin_CRM_After_Sales_Config_Admin
{
    public const PAGE = 'biawin-crm-complaint-config';

    public static function init(): void
    {
        add_action('admin_post_bcrm_cfg_catalog_save', [__CLASS__, 'handle_catalog_save']);
        add_action('admin_post_bcrm_cfg_catalog_toggle', [__CLASS__, 'handle_catalog_toggle']);
        add_action('admin_post_bcrm_cfg_catalog_move', [__CLASS__, 'handle_catalog_move']);
        add_action('admin_post_bcrm_cfg_units_save', [__CLASS__, 'handle_units_save']);
    }

    /** tab => [label, capability] */
    public static function tabs(): array
    {
        $cat = Biawin_CRM_After_Sales_Config::catalogs();
        return [
            'subjects' => [$cat[Biawin_CRM_After_Sales_Config::CATALOG_SUBJECT]['label'], 'manage_dissatisfaction_subjects'],
            'outcomes' => [$cat[Biawin_CRM_After_Sales_Config::CATALOG_OUTCOME]['label'], 'manage_dissatisfaction_outcomes'],
            'units' => ['واحدهای قابل استعلام', 'manage_inquiry_units'],
        ];
    }

    /** The capability used to register the menu: the first config capability the user holds. */
    public static function menu_capability(): string
    {
        foreach (self::tabs() as [, $cap]) if (current_user_can($cap)) return $cap;
        return 'manage_inquiry_units';
    }

    private static function tab_catalog(string $tab): string
    {
        return $tab === 'outcomes' ? Biawin_CRM_After_Sales_Config::CATALOG_OUTCOME : Biawin_CRM_After_Sales_Config::CATALOG_SUBJECT;
    }

    private static function url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::PAGE], $args), admin_url('admin.php'));
    }

    private static function notice(array $result): void
    {
        set_transient('bcrm_cfg_notice_' . get_current_user_id(), ['ok' => !empty($result['ok']), 'message' => (string)($result['message'] ?? '')], 120);
    }

    private static function back(string $tab, array $result, array $extra = []): void
    {
        self::notice($result);
        wp_safe_redirect(self::url(array_merge(['tab' => $tab], $extra)));
        exit;
    }

    private static function guard(string $tab, string $nonce): void
    {
        $tabs = self::tabs();
        if (!isset($tabs[$tab]) || !current_user_can($tabs[$tab][1]) || !check_admin_referer($nonce)) wp_die('دسترسی غیرمجاز است.');
    }

    // ------------------------------------------------------------------ handlers

    public static function handle_catalog_save(): void
    {
        $tab = sanitize_key(wp_unslash($_POST['tab'] ?? ''));
        if (!in_array($tab, ['subjects', 'outcomes'], true)) wp_die('دسترسی غیرمجاز است.');
        self::guard($tab, 'bcrm_cfg_catalog_save');
        $id = absint($_POST['id'] ?? 0);
        $data = [
            'label' => wp_unslash($_POST['label'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'is_active' => !empty($_POST['is_active']),
        ];
        if (isset($_POST['sort_order']) && $_POST['sort_order'] !== '') $data['sort_order'] = (int)$_POST['sort_order'];
        if (!$id) $data['item_key'] = wp_unslash($_POST['item_key'] ?? '');
        $result = Biawin_CRM_After_Sales_Config::save_item(self::tab_catalog($tab), $data, $id);
        self::back($tab, $result, (!$result['ok'] && $id) ? ['edit' => $id] : []);
    }

    public static function handle_catalog_toggle(): void
    {
        $tab = sanitize_key(wp_unslash($_POST['tab'] ?? ''));
        if (!in_array($tab, ['subjects', 'outcomes'], true)) wp_die('دسترسی غیرمجاز است.');
        self::guard($tab, 'bcrm_cfg_catalog_toggle');
        $id = absint($_POST['id'] ?? 0);
        $item = Biawin_CRM_After_Sales_Config::item($id);
        if (!$item || (string)$item->catalog !== self::tab_catalog($tab)) self::back($tab, ['ok' => false, 'message' => 'مورد پیدا نشد.']);
        self::back($tab, Biawin_CRM_After_Sales_Config::set_active($id, !empty($_POST['activate'])));
    }

    public static function handle_catalog_move(): void
    {
        $tab = sanitize_key(wp_unslash($_POST['tab'] ?? ''));
        if (!in_array($tab, ['subjects', 'outcomes'], true)) wp_die('دسترسی غیرمجاز است.');
        self::guard($tab, 'bcrm_cfg_catalog_move');
        $id = absint($_POST['id'] ?? 0);
        $item = Biawin_CRM_After_Sales_Config::item($id);
        if (!$item || (string)$item->catalog !== self::tab_catalog($tab)) self::back($tab, ['ok' => false, 'message' => 'مورد پیدا نشد.']);
        self::back($tab, Biawin_CRM_After_Sales_Config::move($id, sanitize_key(wp_unslash($_POST['direction'] ?? '')) === 'up' ? 'up' : 'down'));
    }

    public static function handle_units_save(): void
    {
        self::guard('units', 'bcrm_cfg_units_save');
        $rows = (array)($_POST['units'] ?? []);
        $saved = 0; $errors = [];
        foreach ($rows as $unit_id => $row) {
            $row = is_array($row) ? wp_unslash($row) : [];
            if (empty($row['present'])) continue; // only rows rendered in the (possibly filtered) form
            // v0.8.64.1 — forward ONLY the inquiry fields; any other posted key (is_active, unit_name,
            // parent, supervisor, display_order, …) is ignored, never saved.
            $inquiry = [];
            foreach (array_keys(Biawin_CRM_After_Sales_Config::inquiry_columns()) as $field) $inquiry[$field] = $row[$field] ?? null;
            $result = Biawin_CRM_After_Sales_Config::save_unit_inquiry_config(absint($unit_id), $inquiry);
            if (empty($result['ok'])) $errors[] = $result['message'];
            elseif (!empty($result['changed'])) $saved++;
        }
        $q = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
        self::back('units', $errors ? ['ok' => false, 'message' => implode(' ', array_unique($errors))] : ['ok' => true, 'message' => $saved ? sprintf('تنظیمات استعلام %s واحد ذخیره شد.', number_format_i18n($saved)) : 'تغییری ثبت نشد.'], $q !== '' ? ['q' => $q] : []);
    }

    // ------------------------------------------------------------------ page

    public static function page(): void
    {
        $tabs = array_filter(self::tabs(), static fn($t) => current_user_can($t[1]));
        if (!$tabs) wp_die('دسترسی غیرمجاز است.');
        $tab = sanitize_key(wp_unslash($_GET['tab'] ?? ''));
        if (!isset($tabs[$tab])) $tab = (string)array_key_first($tabs);

        echo '<div class="wrap bcrm bcrm-cfg" dir="rtl"><h1>تنظیمات نارضایتی</h1>';
        echo '<p class="description">پیکربندی داده‌محور بخش نارضایتی‌ها: موضوعات، نتیجه‌ها/تصمیمات و واحدهای قابل استعلام. هیچ موردی حذف فیزیکی نمی‌شود؛ غیرفعال‌سازی فقط آن را از انتخاب‌های جدید برمی‌دارد و سوابق قبلی همان عنوان را نشان می‌دهند.</p>';
        $notice = get_transient('bcrm_cfg_notice_' . get_current_user_id());
        if (is_array($notice) && $notice['message'] !== '') {
            delete_transient('bcrm_cfg_notice_' . get_current_user_id());
            echo '<div class="notice ' . ($notice['ok'] ? 'notice-success' : 'notice-error') . ' inline"><p>' . esc_html($notice['message']) . '</p></div>';
        }
        if (!Biawin_CRM_After_Sales_Config::table_ready('catalog_items')) {
            echo '<div class="notice notice-warning inline"><p>ساختار دیتابیس این بخش هنوز ساخته نشده است؛ صفحه را دوباره باز کنید یا از «تنظیمات ← بررسی و ترمیم ساختار دیتابیس» استفاده کنید.</p></div></div>';
            return;
        }
        echo '<nav class="nav-tab-wrapper" style="margin-bottom:14px">';
        foreach ($tabs as $key => [$label]) echo '<a class="nav-tab' . ($key === $tab ? ' nav-tab-active' : '') . '" href="' . esc_url(self::url(['tab' => $key])) . '">' . esc_html($label) . '</a>';
        echo '</nav>';
        if ($tab === 'units') self::render_units();
        else self::render_catalog($tab);
        echo '</div>';
    }

    private static function render_catalog(string $tab): void
    {
        global $wpdb;
        $catalog = self::tab_catalog($tab);
        $meta = Biawin_CRM_After_Sales_Config::catalogs()[$catalog];
        $q = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $status = sanitize_key(wp_unslash($_GET['status'] ?? 'all'));
        $items = Biawin_CRM_After_Sales_Config::items($catalog, false, $q);
        if ($status === 'active') $items = array_values(array_filter($items, static fn($i) => (int)$i->is_active === 1));
        if ($status === 'inactive') $items = array_values(array_filter($items, static fn($i) => (int)$i->is_active === 0));
        $all_ids = array_map(static fn($i) => (int)$i->id, Biawin_CRM_After_Sales_Config::items($catalog));
        $edit = Biawin_CRM_After_Sales_Config::item(absint($_GET['edit'] ?? 0));
        if ($edit && (string)$edit->catalog !== $catalog) $edit = null;

        // Usage counts (one grouped query): how many complaint records reference each item.
        $usage = [];
        $complaints = Biawin_CRM_DB::table('complaints');
        if ($catalog === Biawin_CRM_After_Sales_Config::CATALOG_SUBJECT) {
            foreach ((array)$wpdb->get_results("SELECT subject_id k, COUNT(*) n FROM $complaints WHERE subject_id IS NOT NULL GROUP BY subject_id") as $r) $usage['id:' . (int)$r->k] = (int)$r->n;
        } else {
            foreach ((array)$wpdb->get_results("SELECT resolution_outcome k, COUNT(*) n FROM $complaints WHERE resolution_outcome IS NOT NULL AND resolution_outcome<>'' GROUP BY resolution_outcome") as $r) $usage['key:' . (string)$r->k] = (int)$r->n;
        }

        echo '<div class="bcrm-card" style="max-width:1100px"><h2>' . ($edit ? 'ویرایش ' . esc_html($meta['singular']) . ' «' . esc_html($edit->label) . '»' : 'افزودن ' . esc_html($meta['singular'])) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_cfg_catalog_save');
        echo '<input type="hidden" name="action" value="bcrm_cfg_catalog_save"><input type="hidden" name="tab" value="' . esc_attr($tab) . '"><input type="hidden" name="id" value="' . (int)($edit->id ?? 0) . '"><table class="form-table">';
        echo '<tr><th>عنوان نمایشی</th><td><input name="label" required maxlength="190" class="regular-text" value="' . esc_attr($edit->label ?? '') . '"></td></tr>';
        echo '<tr><th>کلید داخلی</th><td><input name="item_key" class="regular-text code" maxlength="80" value="' . esc_attr($edit->item_key ?? '') . '"' . ($edit ? ' readonly' : ' placeholder="اختیاری — مثلاً delay"') . '><p class="description">' . ($edit ? 'کلید دائمی است و تغییر نمی‌کند (سوابق به آن ارجاع دارند).' : 'حروف کوچک لاتین، عدد، _ و -. خالی بماند، خودکار ساخته می‌شود.') . '</p></td></tr>';
        echo '<tr><th>توضیح</th><td><textarea name="description" class="large-text" rows="2">' . esc_textarea($edit->description ?? '') . '</textarea></td></tr>';
        echo '<tr><th>ترتیب</th><td><input type="number" name="sort_order" style="width:110px" value="' . esc_attr($edit ? (string)(int)$edit->sort_order : '') . '"' . ($edit ? '' : ' placeholder="انتهای فهرست"') . '></td></tr>';
        echo '<tr><th>وضعیت</th><td><label><input type="checkbox" name="is_active" value="1"' . checked(!$edit || (int)$edit->is_active === 1, true, false) . '> فعال (در فرم‌های جدید قابل انتخاب)</label></td></tr>';
        if ($edit && $catalog === Biawin_CRM_After_Sales_Config::CATALOG_OUTCOME) {
            $m = json_decode((string)$edit->meta_json, true);
            $handler = is_array($m) ? (string)($m['legacy_handler'] ?? '') : '';
            echo '<tr><th>اقدام‌های خودکار</th><td><p class="description">' . ($handler !== '' ? 'نتیجه سیستمی موجود: هنگام ثبت در فرم فعلی پرونده، اقدام فعلی «' . esc_html($handler) . '» اجرا می‌شود. ' : '') . 'اتصال اقدام‌ها (گردش عودت، درخواست خزانه، استعلام مالی، تگ، فیلدهای الزامی) در فاز بعد فعال می‌شود؛ ساختار داده آن آماده است.</p></td></tr>';
        }
        echo '</table><button class="button button-primary">' . ($edit ? 'ذخیره تغییرات' : 'افزودن') . '</button>';
        if ($edit) echo ' <a class="button" href="' . esc_url(self::url(['tab' => $tab])) . '">انصراف</a>';
        echo '</form></div>';

        echo '<form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:16px 0 8px"><input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        echo '<label>جستجو<br><input type="search" name="q" value="' . esc_attr($q) . '" placeholder="عنوان، کلید یا توضیح"></label><label>وضعیت<br><select name="status">';
        foreach (['all' => 'همه', 'active' => 'فعال', 'inactive' => 'غیرفعال'] as $k => $l) echo '<option value="' . esc_attr($k) . '"' . selected($status, $k, false) . '>' . esc_html($l) . '</option>';
        echo '</select></label><button class="button">اعمال</button>' . ($q !== '' || $status !== 'all' ? ' <a class="button-link" href="' . esc_url(self::url(['tab' => $tab])) . '">حذف فیلتر</a>' : '') . '</form>';

        echo '<table class="widefat striped bcrm-cfg-table"><thead><tr><th style="width:70px">ترتیب</th><th>عنوان</th><th>کلید</th><th>وضعیت</th><th>پرونده‌های مرتبط</th><th style="width:300px">عملیات</th></tr></thead><tbody>';
        if (!$items) echo '<tr><td colspan="6">' . ($q !== '' || $status !== 'all' ? 'موردی با این فیلتر پیدا نشد.' : 'هنوز موردی تعریف نشده است.') . '</td></tr>';
        foreach ($items as $item) {
            $pos = array_search((int)$item->id, $all_ids, true);
            $used = $catalog === Biawin_CRM_After_Sales_Config::CATALOG_SUBJECT ? ($usage['id:' . (int)$item->id] ?? 0) : ($usage['key:' . (string)$item->item_key] ?? 0);
            echo '<tr' . ((int)$item->is_active ? '' : ' class="bcrm-cfg-inactive"') . '><td>' . number_format_i18n((int)$item->sort_order) . '</td><td><strong>' . esc_html($item->label) . '</strong>' . ((int)$item->is_system ? ' <span class="bcrm-cfg-pill is-system" title="کلید موجود در سوابق و گردش فعلی">سیستمی</span>' : '') . ($item->description ? '<br><span class="description">' . esc_html($item->description) . '</span>' : '') . '</td><td><code>' . esc_html($item->item_key) . '</code></td>';
            echo '<td>' . ((int)$item->is_active ? '<span class="bcrm-cfg-pill is-on">فعال</span>' : '<span class="bcrm-cfg-pill is-off">غیرفعال</span>') . '</td><td>' . number_format_i18n($used) . '</td><td style="display:flex;gap:4px;flex-wrap:wrap">';
            echo '<a class="button button-small" href="' . esc_url(self::url(['tab' => $tab, 'edit' => (int)$item->id])) . '">ویرایش</a>';
            self::post_button('bcrm_cfg_catalog_toggle', $tab, (int)$item->id, ['activate' => (int)$item->is_active ? '' : '1'], (int)$item->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی');
            if ($pos !== false && $pos > 0) self::post_button('bcrm_cfg_catalog_move', $tab, (int)$item->id, ['direction' => 'up'], '↑', 'انتقال به بالا');
            if ($pos !== false && $pos < count($all_ids) - 1) self::post_button('bcrm_cfg_catalog_move', $tab, (int)$item->id, ['direction' => 'down'], '↓', 'انتقال به پایین');
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function post_button(string $action, string $tab, int $id, array $fields, string $label, string $title = ''): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
        wp_nonce_field($action);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '"><input type="hidden" name="id" value="' . $id . '">';
        foreach ($fields as $k => $v) echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
        echo '<button class="button button-small"' . ($title !== '' ? ' title="' . esc_attr($title) . '" aria-label="' . esc_attr($title) . '"' : '') . '>' . esc_html($label) . '</button></form>';
    }

    private static function render_units(): void
    {
        global $wpdb;
        $q = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $units_table = Biawin_CRM_DB::table('service_units');
        $where = '1=1';
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where = $wpdb->prepare('(unit_name LIKE %s OR unit_key LIKE %s)', $like, $like);
        }
        $units = (array)$wpdb->get_results("SELECT * FROM $units_table WHERE $where ORDER BY can_receive_inquiry DESC, display_order ASC, unit_name ASC, id ASC");
        $counts = [];
        foreach ((array)$wpdb->get_results('SELECT unit_id, member_role, COUNT(*) n FROM ' . Biawin_CRM_DB::table('service_unit_members') . ' GROUP BY unit_id, member_role') as $r) $counts[(int)$r->unit_id][(string)$r->member_role] = (int)$r->n;
        $types = Biawin_CRM_Admin::unit_type_labels();
        $scopes = Biawin_CRM_After_Sales_Config::responder_scopes();

        echo '<p>این فهرست همان واحدهای صفحه «<a href="' . esc_url(admin_url('admin.php?page=biawin-crm-service-units')) . '">واحدها و اعضا</a>» است. اینجا <strong>فقط تنظیمات استعلام</strong> ذخیره می‌شود؛ وضعیت فعال/غیرفعال واحد، نام، والد، سرپرست، اعضا و ترتیب فقط در «واحدها و اعضا» تغییر می‌کنند.</p>';
        echo '<form method="get" style="display:flex;gap:8px;align-items:end;margin:8px 0"><input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '"><input type="hidden" name="tab" value="units"><label>جستجو<br><input type="search" name="q" value="' . esc_attr($q) . '" placeholder="نام یا کلید واحد"></label><button class="button">اعمال</button>' . ($q !== '' ? ' <a class="button-link" href="' . esc_url(self::url(['tab' => 'units'])) . '">حذف فیلتر</a>' : '') . '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_cfg_units_save');
        echo '<input type="hidden" name="action" value="bcrm_cfg_units_save"><input type="hidden" name="q" value="' . esc_attr($q) . '">';
        echo '<table class="widefat striped bcrm-cfg-table"><thead>'
            . '<tr><th rowspan="2">واحد</th><th rowspan="2">وضعیت واحد<br><span class="description">(فقط نمایش)</span></th><th colspan="3" class="bcrm-cfg-group">تنظیمات استعلام</th><th rowspan="2">اعضا</th><th rowspan="2">نتیجه</th></tr>'
            . '<tr><th>استعلام‌پذیر</th><th>پاسخ از طریق پرتال لازم است</th><th>پاسخ‌دهندگان</th></tr></thead><tbody>';
        if (!$units) echo '<tr><td colspan="7">واحدی پیدا نشد.</td></tr>';
        foreach ($units as $u) {
            $id = (int)$u->id;
            $n = 'units[' . $id . ']';
            $c = $counts[$id] ?? [];
            $sup = (int)($c['supervisor'] ?? 0) ?: ((int)($u->supervisor_user_id ?? 0) ? 1 : 0);
            $active = (int)$u->is_active === 1;
            $enabled = (int)$u->can_receive_inquiry === 1;
            $mode = Biawin_CRM_After_Sales_Config::normalize_responders($u->inquiry_responders ?? null);
            echo '<tr><td><input type="hidden" name="' . $n . '[present]" value="1"><strong>' . esc_html($u->unit_name) . '</strong><br><code>' . esc_html($u->unit_key) . '</code> · ' . esc_html($types[(string)($u->unit_type ?? 'service')] ?? (string)$u->unit_type) . '<br><a href="' . esc_url(add_query_arg(['page' => 'biawin-crm-service-units', 'edit_unit' => $u->unit_key], admin_url('admin.php'))) . '">ویرایش واحد، اعضا و وضعیت</a></td>';
            echo '<td>' . ($active ? '<span class="bcrm-cfg-pill is-on">فعال</span>' : '<span class="bcrm-cfg-pill is-off">غیرفعال</span>') . '</td>';
            // Hidden 0 + checkbox 1 → the value is always explicit (unchecked = 0), validated server-side.
            echo '<td><input type="hidden" name="' . $n . '[inquiry_enabled]" value="0"><label><input type="checkbox" name="' . $n . '[inquiry_enabled]" value="1"' . checked($enabled, true, false) . '> فعال</label></td>';
            echo '<td><input type="hidden" name="' . $n . '[requires_portal_response]" value="0"><label><input type="checkbox" name="' . $n . '[requires_portal_response]" value="1"' . checked((int)$u->requires_portal_response, 1, false) . '> فعال</label></td>';
            echo '<td><fieldset class="bcrm-cfg-radios"><legend class="screen-reader-text">پاسخ‌دهندگان ' . esc_html($u->unit_name) . '</legend>';
            foreach ($scopes as $k => $l) echo '<label><input type="radio" name="' . $n . '[inquiry_responders]" value="' . esc_attr($k) . '"' . checked($mode, $k, false) . '> ' . esc_html($l) . '</label>';
            echo '</fieldset></td><td>' . sprintf('سرپرست: %s · کارشناس: %s', number_format_i18n($sup), number_format_i18n((int)($c['agent'] ?? 0))) . '</td>';
            if (!$enabled) $state = '<span class="bcrm-cfg-pill is-off">مستثنی</span>';
            elseif (!$active) $state = '<span class="bcrm-cfg-pill is-off" title="استعلام‌پذیر است ولی خود واحد غیرفعال است">غیرفعال (واحد)</span>';
            else $state = '<span class="bcrm-cfg-pill is-on">' . ((int)$u->requires_portal_response ? 'استعلام پرتالی + دستی' : 'فقط استعلام دستی') . '</span>';
            echo '<td>' . $state . '</td></tr>';
        }
        echo '</tbody></table><p class="description">«استعلام‌پذیر» فقط تعیین می‌کند این واحد استعلام دریافت کند یا نه و هرگز خود واحد را فعال/غیرفعال نمی‌کند. واحد غیرفعال حتی اگر استعلام‌پذیر باشد استعلام دریافت نمی‌کند. «استعلام پرتالی»: کارشناس نارضایتی از واحد سؤال می‌کند و پاسخ در پرتال همان واحد ثبت می‌شود. «استعلام دستی»: کارشناس قبلاً با واحد تماس گرفته و فقط نتیجه را ثبت می‌کند.</p><p><button class="button button-primary">ذخیره تنظیمات استعلام</button></p></form>';
    }
}
