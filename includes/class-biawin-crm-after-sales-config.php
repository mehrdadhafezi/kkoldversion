<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * v0.8.64.0 — After-Sales Phase 2: data-driven configuration layer.
 *
 * - Complaint subjects / complaint outcomes live in one generic catalog table (catalog_items),
 *   managed by admins; nothing is hard-coded for future UIs. Items are never hard-deleted by
 *   the UI — deactivation only hides them from new selections; historical rows keep their
 *   reference (subject_id / resolution_outcome key) AND a label snapshot.
 * - Inquiry providers are the EXISTING service_units: is_active (unit status) and, independently,
 *   can_receive_inquiry (= inquiry enabled) / requires_portal_response / inquiry_responders
 *   (members|supervisor|both) — no parallel "inquiry units" system.
 * - complaint_inquiries holds N inquiries per complaint; mode 'portal' vs 'manual' stays distinct.
 *
 * Phase 2 provides the configuration + data primitives only; the full complaints workflow UI
 * (requesting/answering inquiries in the portal, outcome actions) is Phase 3+.
 */
class Biawin_CRM_After_Sales_Config
{
    public const CATALOG_SUBJECT = 'complaint_subject';
    public const CATALOG_OUTCOME = 'complaint_outcome';
    public const MODE_PORTAL = 'portal';
    public const MODE_MANUAL = 'manual';
    public const STATUS_PENDING = 'pending';     // portal inquiry waiting for the unit
    public const STATUS_RECORDED = 'recorded';   // manual inquiry (result already known)

    private static $table_cache = [];

    public static function catalogs(): array
    {
        return [
            self::CATALOG_SUBJECT => ['label' => 'موضوعات نارضایتی', 'singular' => 'موضوع', 'cap' => 'manage_dissatisfaction_subjects', 'key_prefix' => 'subject'],
            self::CATALOG_OUTCOME => ['label' => 'نتایج / تصمیمات نارضایتی', 'singular' => 'نتیجه', 'cap' => 'manage_dissatisfaction_outcomes', 'key_prefix' => 'outcome'],
        ];
    }

    /**
     * v0.8.64.1 — explicit inquiry responder modes (stored in service_units.inquiry_responders):
     * members = the unit's member agents · supervisor = the unit supervisor · both = either.
     */
    public static function responder_scopes(): array
    {
        return [
            'members' => 'کارشناسان واحد',
            'supervisor' => 'سرپرست واحد',
            'both' => 'کارشناسان + سرپرست',
        ];
    }

    /** NULL/unknown stored value → safe default 'members' (read-time only; stored data is not rewritten). */
    public static function normalize_responders($value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return isset(self::responder_scopes()[$value]) ? $value : 'members';
    }

    /** True once migration 0.8.64.0 created the table (callers fall back to legacy behaviour before that). */
    public static function table_ready(string $name): bool
    {
        if (isset(self::$table_cache[$name])) return self::$table_cache[$name];
        global $wpdb;
        $table = Biawin_CRM_DB::table($name);
        return self::$table_cache[$name] = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    }

    public static function reset_cache(): void
    {
        self::$table_cache = [];
    }

    // ------------------------------------------------------------------ catalogs

    public static function items(string $catalog, bool $active_only = false, string $search = ''): array
    {
        if (!isset(self::catalogs()[$catalog]) || !self::table_ready('catalog_items')) return [];
        global $wpdb;
        $table = Biawin_CRM_DB::table('catalog_items');
        $where = $wpdb->prepare('catalog=%s', $catalog);
        if ($active_only) $where .= ' AND is_active=1';
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(' AND (label LIKE %s OR item_key LIKE %s OR description LIKE %s)', $like, $like, $like);
        }
        return (array)$wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id ASC");
    }

    public static function item(int $id): ?object
    {
        if (!$id || !self::table_ready('catalog_items')) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('catalog_items') . ' WHERE id=%d', $id));
        return $row ?: null;
    }

    public static function item_by_key(string $catalog, string $key): ?object
    {
        if ($key === '' || !self::table_ready('catalog_items')) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('catalog_items') . ' WHERE catalog=%s AND item_key=%s', $catalog, $key));
        return $row ?: null;
    }

    /**
     * Add (id=0) or edit an item. The key is stable: set once (given or generated), never changed
     * by an edit, because historical records reference it. Returns ['ok', 'id', 'message'].
     */
    public static function save_item(string $catalog, array $data, int $id = 0): array
    {
        if (!isset(self::catalogs()[$catalog])) return ['ok' => false, 'message' => 'کاتالوگ نامعتبر است.'];
        if (!self::table_ready('catalog_items')) return ['ok' => false, 'message' => 'ساختار دیتابیس هنوز به‌روزرسانی نشده است.'];
        global $wpdb;
        $table = Biawin_CRM_DB::table('catalog_items');
        $label = trim(sanitize_text_field((string)($data['label'] ?? '')));
        if ($label === '') return ['ok' => false, 'message' => 'عنوان نمایشی الزامی است.'];
        if (function_exists('mb_strlen') ? mb_strlen($label) > 190 : strlen($label) > 190) return ['ok' => false, 'message' => 'عنوان نمایشی حداکثر ۱۹۰ کاراکتر است.'];
        $description = sanitize_textarea_field((string)($data['description'] ?? ''));
        $now = Biawin_CRM_DB::now();
        $actor = get_current_user_id() ?: null;

        if ($id) {
            $old = self::item($id);
            if (!$old || (string)$old->catalog !== $catalog) return ['ok' => false, 'message' => 'مورد پیدا نشد.'];
            $dup = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE catalog=%s AND label=%s AND id<>%d", $catalog, $label, $id));
            if ($dup) return ['ok' => false, 'message' => 'موردی با همین عنوان وجود دارد.'];
            $fields = ['label' => $label, 'description' => $description, 'updated_by' => $actor, 'updated_at' => $now];
            if (array_key_exists('sort_order', $data)) $fields['sort_order'] = (int)$data['sort_order'];
            if (array_key_exists('is_active', $data)) $fields['is_active'] = !empty($data['is_active']) ? 1 : 0;
            if ($wpdb->update($table, $fields, ['id' => $id]) === false) return ['ok' => false, 'message' => 'ذخیره انجام نشد: ' . ($wpdb->last_error ?: 'خطای دیتابیس')];
            Biawin_CRM_DB::log('catalog_item', $id, 'catalog_item_updated', ['label' => $old->label, 'sort_order' => (int)$old->sort_order, 'is_active' => (int)$old->is_active], ['catalog' => $catalog] + $fields);
            return ['ok' => true, 'id' => $id, 'message' => 'ذخیره شد.'];
        }

        $key = sanitize_key((string)($data['item_key'] ?? ''));
        if ($key === '') $key = self::catalogs()[$catalog]['key_prefix'] . '_' . substr(md5($label . microtime(true) . wp_rand()), 0, 8);
        if (strlen($key) > 80) return ['ok' => false, 'message' => 'کلید حداکثر ۸۰ کاراکتر است.'];
        if (self::item_by_key($catalog, $key)) return ['ok' => false, 'message' => 'این کلید قبلاً استفاده شده است (کلیدها یکتا و دائمی‌اند).'];
        $dup = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE catalog=%s AND label=%s", $catalog, $label));
        if ($dup) return ['ok' => false, 'message' => 'موردی با همین عنوان وجود دارد.'];
        $sort = array_key_exists('sort_order', $data) && $data['sort_order'] !== ''
            ? (int)$data['sort_order']
            : ((int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(sort_order),0) FROM $table WHERE catalog=%s", $catalog)) + 10);
        $ok = $wpdb->insert($table, [
            'catalog' => $catalog, 'item_key' => $key, 'label' => $label, 'description' => $description,
            'is_active' => array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
            'sort_order' => $sort, 'is_system' => 0, 'meta_json' => wp_json_encode(['actions' => []]),
            'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $new_id = (int)$wpdb->insert_id;
        if ($ok === false || !$new_id) return ['ok' => false, 'message' => 'ثبت انجام نشد: ' . ($wpdb->last_error ?: 'خطای دیتابیس')];
        Biawin_CRM_DB::log('catalog_item', $new_id, 'catalog_item_created', null, ['catalog' => $catalog, 'item_key' => $key, 'label' => $label, 'sort_order' => $sort]);
        return ['ok' => true, 'id' => $new_id, 'message' => 'افزوده شد.'];
    }

    public static function set_active(int $id, bool $active): array
    {
        $item = self::item($id);
        if (!$item) return ['ok' => false, 'message' => 'مورد پیدا نشد.'];
        global $wpdb;
        if ((int)$item->is_active === ($active ? 1 : 0)) return ['ok' => true, 'id' => $id, 'message' => 'بدون تغییر.'];
        $ok = $wpdb->update(Biawin_CRM_DB::table('catalog_items'), ['is_active' => $active ? 1 : 0, 'updated_by' => get_current_user_id() ?: null, 'updated_at' => Biawin_CRM_DB::now()], ['id' => $id]);
        if ($ok === false) return ['ok' => false, 'message' => 'ذخیره انجام نشد.'];
        Biawin_CRM_DB::log('catalog_item', $id, $active ? 'catalog_item_activated' : 'catalog_item_deactivated', ['is_active' => (int)$item->is_active], ['catalog' => $item->catalog, 'item_key' => $item->item_key, 'is_active' => $active ? 1 : 0]);
        return ['ok' => true, 'id' => $id, 'message' => $active ? 'فعال شد.' : 'غیرفعال شد (سوابق قبلی حفظ می‌شوند).'];
    }

    /** Move one item up/down: re-sequences the catalog in steps of 10 (stable, gap-free order). */
    public static function move(int $id, string $direction): array
    {
        $item = self::item($id);
        if (!$item) return ['ok' => false, 'message' => 'مورد پیدا نشد.'];
        $ids = array_map(static fn($r) => (int)$r->id, self::items((string)$item->catalog));
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($pos === false || $swap < 0 || $swap >= count($ids)) return ['ok' => true, 'id' => $id, 'message' => 'بدون تغییر.'];
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        return self::reorder((string)$item->catalog, $ids);
    }

    /** Persist a full order (ids not listed keep their relative order after the listed ones). */
    public static function reorder(string $catalog, array $ordered_ids): array
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('catalog_items');
        $existing = array_map(static fn($r) => (int)$r->id, self::items($catalog));
        $ordered = array_values(array_intersect(array_map('intval', $ordered_ids), $existing));
        foreach ($existing as $eid) if (!in_array($eid, $ordered, true)) $ordered[] = $eid;
        $wpdb->query('START TRANSACTION');
        foreach ($ordered as $i => $eid) {
            if ($wpdb->update($table, ['sort_order' => ($i + 1) * 10], ['id' => $eid, 'catalog' => $catalog]) === false) {
                $wpdb->query('ROLLBACK');
                return ['ok' => false, 'message' => 'ذخیره ترتیب انجام نشد.'];
            }
        }
        $wpdb->query('COMMIT');
        Biawin_CRM_DB::log('catalog_item', null, 'catalog_reordered', null, ['catalog' => $catalog, 'order' => $ordered]);
        return ['ok' => true, 'message' => 'ترتیب ذخیره شد.'];
    }

    /** Display label for a stored reference: catalog label (even if inactive) → snapshot → key. */
    public static function label_for(string $catalog, string $key, string $snapshot = ''): string
    {
        $item = self::item_by_key($catalog, $key);
        if ($item) return (string)$item->label;
        return $snapshot !== '' ? $snapshot : $key;
    }

    // ------------------------------------------------------------------ inquiry units (service_units)

    /**
     * v0.8.64.1 — THE single eligibility rule for receiving inquiries. Two independent settings:
     * the unit itself must be active (is_active, managed on «واحدها و اعضا») AND inquiry must be
     * enabled for it (can_receive_inquiry, managed on «واحدهای قابل استعلام»). An inactive unit is
     * never operational, whatever its inquiry setting.
     */
    public static function unit_accepts_inquiries($unit): bool
    {
        return is_object($unit) && (int)($unit->is_active ?? 0) === 1 && (int)($unit->can_receive_inquiry ?? 0) === 1;
    }

    /**
     * Inquiry providers. $operational_only=true → units that accept inquiries now (active AND inquiry
     * enabled); false → every unit with inquiry enabled, including currently inactive units.
     */
    public static function inquiry_units(bool $operational_only = true): array
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('service_units');
        $where = 'can_receive_inquiry=1' . ($operational_only ? ' AND is_active=1' : '');
        return (array)$wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY display_order ASC, unit_name ASC, id ASC");
    }

    /** Strict boolean input: missing/''/'0'/0/false → 0, '1'/1/true → 1, anything else → null (invalid). */
    private static function bool_input($value): ?int
    {
        if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0') return 0;
        if ($value === true || $value === 1 || $value === '1') return 1;
        return null;
    }

    /** Field name in the inquiry form → service_units column. These three are the ONLY columns written. */
    public static function inquiry_columns(): array
    {
        return ['inquiry_enabled' => 'can_receive_inquiry', 'requires_portal_response' => 'requires_portal_response', 'inquiry_responders' => 'inquiry_responders'];
    }

    /**
     * Save the inquiry configuration of an existing unit. The UPDATE is scoped to exactly
     * can_receive_inquiry / requires_portal_response / inquiry_responders — never is_active, name, key,
     * parent, supervisor, members, display_order or updated_at. Writes nothing if nothing changed.
     * $data keys: inquiry_enabled, requires_portal_response (booleans), inquiry_responders (mode).
     */
    public static function save_unit_inquiry_config(int $unit_id, array $data): array
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('service_units');
        $unit = $unit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $unit_id)) : null;
        if (!$unit) return ['ok' => false, 'message' => 'واحد پیدا نشد.'];
        $enabled = self::bool_input($data['inquiry_enabled'] ?? null);
        $portal = self::bool_input($data['requires_portal_response'] ?? null);
        if ($enabled === null || $portal === null) return ['ok' => false, 'message' => 'مقدار تنظیمات استعلام نامعتبر است.'];
        $mode = is_string($data['inquiry_responders'] ?? null) ? strtolower(trim($data['inquiry_responders'])) : '';
        if (!isset(self::responder_scopes()[$mode])) return ['ok' => false, 'message' => 'پاسخ‌دهنده نامعتبر است (فقط کارشناسان، سرپرست یا هر دو).'];
        $fields = ['can_receive_inquiry' => $enabled, 'requires_portal_response' => $portal, 'inquiry_responders' => $mode];
        $changed = [];
        foreach ($fields as $column => $value) if ((string)($unit->$column ?? '') !== (string)$value) $changed[$column] = $value;
        if (!$changed) return ['ok' => true, 'id' => $unit_id, 'changed' => false, 'message' => 'بدون تغییر.'];
        if ($wpdb->update($table, $changed, ['id' => $unit_id], array_map(static fn($v) => is_int($v) ? '%d' : '%s', $changed), ['%d']) === false) {
            return ['ok' => false, 'message' => 'ذخیره انجام نشد: ' . ($wpdb->last_error ?: 'خطای دیتابیس')];
        }
        $before = [];
        foreach (array_keys($changed) as $column) $before[$column] = $unit->$column ?? null;
        Biawin_CRM_DB::log('service_unit', $unit_id, 'unit_inquiry_config_saved', $before, ['unit_key' => $unit->unit_key] + $changed);
        return ['ok' => true, 'id' => $unit_id, 'changed' => true, 'message' => 'تنظیمات استعلام واحد ذخیره شد.'];
    }

    /**
     * v0.8.64.1 — the ONE responder resolver (portal answering, admin, REST and future UI must use it).
     * Returns user ids per the unit's mode, from the existing membership/supervisor data:
     *   members    → member rows with member_role 'agent'
     *   supervisor → service_units.supervisor_user_id + member rows with member_role 'supervisor'
     *   both       → union of the two
     * Only existing, non-disabled users are returned. Read-only: never creates users or changes
     * memberships/roles. Whether the unit may receive inquiries at all is unit_accepts_inquiries().
     */
    public static function get_inquiry_responders(int $unit_id): array
    {
        global $wpdb;
        $unit = $unit_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('service_units') . ' WHERE id=%d', $unit_id)) : null;
        if (!$unit) return [];
        $mode = self::normalize_responders($unit->inquiry_responders ?? null);
        $rows = (array)$wpdb->get_results($wpdb->prepare('SELECT user_id, member_role FROM ' . Biawin_CRM_DB::table('service_unit_members') . ' WHERE unit_id=%d ORDER BY id', $unit_id));
        $members = []; $supervisors = [];
        foreach ($rows as $r) {
            if ((string)$r->member_role === 'supervisor') $supervisors[] = (int)$r->user_id;
            elseif ((string)$r->member_role === 'agent') $members[] = (int)$r->user_id;
        }
        if ((int)($unit->supervisor_user_id ?? 0)) array_unshift($supervisors, (int)$unit->supervisor_user_id);
        $ids = $mode === 'members' ? $members : ($mode === 'supervisor' ? $supervisors : array_merge($supervisors, $members));
        $out = [];
        foreach (array_unique(array_filter($ids)) as $uid) {
            if (!get_userdata($uid) || get_user_meta($uid, 'bcrm_disabled', true)) continue; // eligible = existing, enabled users
            $out[] = $uid;
        }
        return $out;
    }

    // ------------------------------------------------------------------ inquiries (data layer only)

    /**
     * MODE A — portal inquiry: the complaint agent asks a unit; it stays 'pending' for the unit.
     * Only to an active unit with can_receive_inquiry AND requires_portal_response.
     */
    public static function request_portal_inquiry(int $complaint_id, int $unit_id, string $note = '', int $actor = 0): array
    {
        return self::insert_inquiry($complaint_id, $unit_id, self::MODE_PORTAL, [
            'status' => self::STATUS_PENDING,
            'request_note' => sanitize_textarea_field($note),
            'requested_by' => $actor ?: (get_current_user_id() ?: null),
            'requested_at' => Biawin_CRM_DB::now(),
        ]);
    }

    /** MODE B — manual inquiry: the agent already contacted the unit and records the result. */
    public static function record_manual_inquiry(int $complaint_id, int $unit_id, string $result, string $notes = '', string $contacted_at = '', int $actor = 0, string $result_key = ''): array
    {
        $result = sanitize_textarea_field($result);
        if (trim($result) === '') return ['ok' => false, 'message' => 'نتیجه استعلام الزامی است.'];
        $when = $contacted_at !== '' && strtotime($contacted_at) ? gmdate('Y-m-d H:i:s', strtotime($contacted_at)) : Biawin_CRM_DB::now();
        $actor = $actor ?: (get_current_user_id() ?: null);
        return self::insert_inquiry($complaint_id, $unit_id, self::MODE_MANUAL, [
            'status' => self::STATUS_RECORDED,
            'result_key' => sanitize_key($result_key) ?: null,
            'result_text' => $result,
            'notes' => sanitize_textarea_field($notes),
            'contacted_at' => $when,
            'responded_by' => $actor,
            'responded_at' => Biawin_CRM_DB::now(),
        ]);
    }

    public static function complaint_inquiries(int $complaint_id): array
    {
        if (!self::table_ready('complaint_inquiries')) return [];
        global $wpdb;
        return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('complaint_inquiries') . ' WHERE complaint_id=%d ORDER BY id ASC', $complaint_id));
    }

    private static function insert_inquiry(int $complaint_id, int $unit_id, string $mode, array $fields): array
    {
        if (!self::table_ready('complaint_inquiries')) return ['ok' => false, 'message' => 'ساختار دیتابیس هنوز به‌روزرسانی نشده است.'];
        global $wpdb;
        $complaint = $wpdb->get_row($wpdb->prepare('SELECT id, customer_id FROM ' . Biawin_CRM_DB::table('complaints') . ' WHERE id=%d', $complaint_id));
        if (!$complaint) return ['ok' => false, 'message' => 'پرونده نارضایتی پیدا نشد.'];
        $unit = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('service_units') . ' WHERE id=%d', $unit_id));
        if (!$unit || !self::unit_accepts_inquiries($unit)) return ['ok' => false, 'message' => $unit && !(int)$unit->is_active ? 'این واحد غیرفعال است.' : 'این واحد قابل استعلام نیست.'];
        if ($mode === self::MODE_PORTAL && !(int)$unit->requires_portal_response) return ['ok' => false, 'message' => 'این واحد استعلام پرتالی دریافت نمی‌کند؛ استعلام دستی ثبت کنید.'];
        $now = Biawin_CRM_DB::now();
        $row = array_merge([
            'complaint_id' => $complaint_id, 'unit_id' => $unit_id, 'unit_name_snapshot' => (string)$unit->unit_name,
            'mode' => $mode, 'created_by' => get_current_user_id() ?: null, 'created_at' => $now, 'updated_at' => $now,
        ], $fields);
        if ($wpdb->insert(Biawin_CRM_DB::table('complaint_inquiries'), $row) === false) return ['ok' => false, 'message' => 'ثبت استعلام انجام نشد: ' . ($wpdb->last_error ?: 'خطای دیتابیس')];
        $id = (int)$wpdb->insert_id;
        Biawin_CRM_DB::log('complaint', $complaint_id, 'complaint_inquiry_' . $mode, null, ['inquiry_id' => $id, 'unit_id' => $unit_id, 'unit_key' => $unit->unit_key, 'status' => $row['status']]);
        return ['ok' => true, 'id' => $id];
    }
}
