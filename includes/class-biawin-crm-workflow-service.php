<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Workflow_Service
{
    public const PROFILE_PENDING = 'pending';
    public const PROFILE_INCOMPLETE = 'incomplete';
    public const PROFILE_COMPLETE = 'complete';

    public static function default_contact_outcomes(): array
    {
        return [
            ['key'=>'success','label'=>'تماس موفق','operational_status'=>'contact_success','active'=>1,'sort'=>10],
            ['key'=>'no_answer','label'=>'عدم پاسخ','operational_status'=>'no_answer','active'=>1,'sort'=>20],
            ['key'=>'powered_off','label'=>'خاموش','operational_status'=>'powered_off','active'=>1,'sort'=>30],
            ['key'=>'unavailable','label'=>'در دسترس نیست','operational_status'=>'unavailable','active'=>1,'sort'=>40],
            ['key'=>'callback','label'=>'درخواست تماس مجدد','operational_status'=>'callback_requested','active'=>1,'sort'=>50],
            ['key'=>'followup','label'=>'نیازمند پیگیری','operational_status'=>'followup_needed','active'=>1,'sort'=>60],
            ['key'=>'wrong_number','label'=>'شماره اشتباه','operational_status'=>'wrong_number','active'=>1,'sort'=>70],
            ['key'=>'other_person','label'=>'شماره متعلق به شخص دیگر','operational_status'=>'other_person_number','active'=>1,'sort'=>80],
            ['key'=>'refused','label'=>'امتناع از پاسخ','operational_status'=>'refused','active'=>1,'sort'=>90],
            ['key'=>'non_cooperation','label'=>'عدم همکاری','operational_status'=>'non_cooperation','active'=>1,'sort'=>91],
            ['key'=>'no_receipt','label'=>'فیش ندارد','operational_status'=>'no_receipt','active'=>1,'sort'=>92],
            ['key'=>'no_company_payment','label'=>'واریزی نداشته به شرکت','operational_status'=>'no_company_payment','active'=>1,'sort'=>93],
            ['key'=>'refund_reported','label'=>'عودت شده است','operational_status'=>'refund_reported','active'=>1,'sort'=>95],
        ];
    }

    public static function contact_outcomes(bool $active_only = false): array
    {
        $stored = get_option('biawin_crm_contact_outcomes', null);
        $items = is_array($stored) && $stored ? $stored : self::default_contact_outcomes();
        $clean = [];
        $seen = [];
        foreach ($items as $idx => $item) {
            if (!is_array($item)) continue;
            $key = sanitize_key((string)($item['key'] ?? ''));
            $label = sanitize_text_field((string)($item['label'] ?? ''));
            if ($key === '' || $label === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $status = sanitize_key((string)($item['operational_status'] ?? ''));
            if ($status === '') $status = substr('contact_' . $key, 0, 40);
            $active = !empty($item['active']) ? 1 : 0;
            $sort = isset($item['sort']) ? (int)$item['sort'] : (($idx + 1) * 10);
            $clean[] = ['key'=>$key,'label'=>$label,'operational_status'=>$status,'active'=>$active,'sort'=>$sort];
        }
        $clean = array_values(array_filter($clean, static fn($i) => $i['key'] !== 'answered'));
        $present = []; foreach ($clean as $item) $present[$item['key']] = true;
        foreach ([
            ['key'=>'non_cooperation','label'=>'عدم همکاری','operational_status'=>'non_cooperation','active'=>1,'sort'=>91],
            ['key'=>'no_receipt','label'=>'فیش ندارد','operational_status'=>'no_receipt','active'=>1,'sort'=>92],
            ['key'=>'no_company_payment','label'=>'واریزی نداشته به شرکت','operational_status'=>'no_company_payment','active'=>1,'sort'=>93],
        ] as $required) if (!isset($present[$required['key']])) $clean[] = $required;
        usort($clean, static function($a,$b){ return ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'],$b['label']); });
        if ($active_only) $clean = array_values(array_filter($clean, static fn($i)=>!empty($i['active'])));
        return $clean;
    }

    public static function contact_outcome_by_key(string $key): ?array
    {
        $key = sanitize_key($key);
        foreach (self::contact_outcomes(false) as $item) if ($item['key'] === $key) return $item;
        return null;
    }

    public static function contact_outcome_key_from_label(string $label): string
    {
        $label = trim($label);
        foreach (self::contact_outcomes(false) as $item) if ($item['label'] === $label) return $item['key'];
        foreach (self::default_contact_outcomes() as $item) if ($item['label'] === $label) return $item['key'];
        return '';
    }

    public static function contact_outcome_label(string $key, string $fallback = ''): string
    {
        $item = self::contact_outcome_by_key($key);
        return $item ? $item['label'] : $fallback;
    }

    public static function settings(): array
    {
        return [
            'credit_multiplier' => (float) get_option('biawin_crm_credit_multiplier', 2.5),
            'credit_floor' => (float) get_option('biawin_crm_credit_floor', 200000000),
            'credit_cap' => (float) get_option('biawin_crm_credit_cap', 1500000000),
            'reconciliation_tolerance' => max(500000, (float) get_option('biawin_crm_reconciliation_tolerance', 500000)),
        ];
    }

    public static function calculate_credit(float $verified_total): float
    {
        if ($verified_total <= 0) return 0.0;
        $s = self::settings();
        $raw = $verified_total * max(0, $s['credit_multiplier']);
        return min(max($raw, max(0, $s['credit_floor'])), max($s['credit_floor'], $s['credit_cap']));
    }

    public static function sync_customer(int $customer_id): bool
    {
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id = %d',
            $customer_id
        ));
        if (!$customer) {
            return false;
        }

        $total = (float) $customer->total_paid;
        $declared = $customer->declared_paid === null ? null : (float) $customer->declared_paid;
        $tolerance = self::settings()['reconciliation_tolerance'];
        $profile = self::PROFILE_PENDING;
        if ($declared !== null) {
            $profile = abs($declared - $total) <= $tolerance ? self::PROFILE_COMPLETE : self::PROFILE_INCOMPLETE;
        }

        $credit_total = self::calculate_credit($total);
        $reserved = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credit_amount),0) FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND status IN ('unassigned','assigned','in_progress','waiting')",
            $customer_id
        ));
        $consumed = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credit_amount),0) FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND status='completed'",
            $customer_id
        ));
        $remaining = $credit_total - $reserved - $consumed;

        $updated = $wpdb->update(Biawin_CRM_DB::table('customers'), [
            'profile_completeness' => $profile,
            'service_credit_total' => $credit_total,
            'service_credit_reserved' => $reserved,
            'service_credit_consumed' => $consumed,
            'service_credit_remaining' => $remaining,
            'updated_at' => Biawin_CRM_DB::now(),
        ], ['id' => $customer_id]);
        if ($updated === false) return false;

        if ($profile === self::PROFILE_INCOMPLETE) {
            if (!self::ensure_financial_case($customer_id, $declared, $total)) return false;
            if (class_exists('Biawin_CRM_Deficiency_Workflow') && !Biawin_CRM_Deficiency_Workflow::ensure_case_for_customer($customer_id, $declared, $total)) return false;
        } elseif ($profile === self::PROFILE_COMPLETE) {
            if (!self::close_open_financial_cases($customer_id)) return false;
        }
        return true;
    }

    public static function ensure_financial_case(int $customer_id, ?float $declared = null, ?float $system_total = null): int
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('financial_cases');
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE customer_id=%d AND status NOT IN ('resolved','closed','cancelled') ORDER BY id DESC LIMIT 1",
            $customer_id
        ));
        if ($existing) {
            $updated = $wpdb->update($table, [
                'declared_amount' => $declared,
                'system_amount' => $system_total,
                'difference_amount' => ($declared === null || $system_total === null) ? null : $declared - $system_total,
                'updated_at' => Biawin_CRM_DB::now(),
            ], ['id' => $existing]);
            return $updated === false ? 0 : $existing;
        }
        $id = self::insert_case_with_retry($table, 'case_code', 'FIN', [
            'customer_id' => $customer_id,
            'declared_amount' => $declared,
            'system_amount' => $system_total,
            'difference_amount' => ($declared === null || $system_total === null) ? null : $declared - $system_total,
            'status' => 'new',
            'created_by' => get_current_user_id(),
            'created_at' => Biawin_CRM_DB::now(),
            'updated_at' => Biawin_CRM_DB::now(),
        ]);
        if (!$id) return 0;
        self::activity($customer_id, 'financial_case_created', 'پرونده تکمیل مالی ایجاد شد.', 'financial_case', $id);
        return $id;
    }

    private static function close_open_financial_cases(int $customer_id): bool
    {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE " . Biawin_CRM_DB::table('financial_cases') . " SET status='resolved', resolved_by=%d, resolved_at=%s, updated_at=%s WHERE customer_id=%d AND status NOT IN ('resolved','closed','cancelled')",
            get_current_user_id() ?: 0,
            Biawin_CRM_DB::now(),
            Biawin_CRM_DB::now(),
            $customer_id
        ));
        return $result !== false;
    }

    public static function set_customer_journey(int $customer_id, string $journey, string $satisfaction = '', string $note = ''): array
    {
        global $wpdb;
        $allowed = ['no_request', 'complaint', 'service_request'];
        if (!in_array($journey, $allowed, true)) {
            return ['ok' => false, 'message' => 'مسیر انتخاب‌شده معتبر نیست.'];
        }
        if (!self::sync_customer($customer_id)) return ['ok'=>false,'message'=>'بروزرسانی وضعیت پروفایل انجام نشد. دوباره تلاش کنید.'];
        $customer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $customer_id));
        if (!$customer) {
            return ['ok' => false, 'message' => 'پروفایل مشتری پیدا نشد.'];
        }
        // Business rule: financial mismatch must NOT block choosing the customer's journey.
        // Once declared amount is saved, an incomplete profile may keep its selected journey while
        // simultaneously entering financial verification. Service fulfillment still remains gated
        // until finance resolves the mismatch and the profile becomes complete.
        if ($customer->profile_completeness === self::PROFILE_PENDING) {
            return ['ok' => false, 'message' => 'ابتدا مبلغ اعلامی مشتری را وارد و ثبت کنید.'];
        }
        if ($customer->profile_completeness === self::PROFILE_INCOMPLETE) {
            $financial_case_id = self::ensure_financial_case($customer_id, (float)$customer->declared_paid, (float)$customer->total_paid);
            if (!$financial_case_id) {
                return ['ok' => false, 'message' => 'مسیر انتخاب شدنی است اما ایجاد پرونده تأیید مالی ناموفق بود؛ دوباره تلاش کنید.'];
            }
        }
        $updated = $wpdb->update(Biawin_CRM_DB::table('customers'), [
            'journey_state' => $journey,
            'satisfaction_state' => sanitize_key($satisfaction),
            'updated_at' => Biawin_CRM_DB::now(),
        ], ['id' => $customer_id]);
        if ($updated === false) {
            return ['ok' => false, 'message' => 'ذخیره مسیر مشتری در دیتابیس انجام نشد.'];
        }
        self::activity($customer_id, 'journey_changed', $note ?: 'مسیر عملیاتی مشتری تعیین شد.', 'customer', $customer_id, ['journey' => $journey, 'satisfaction' => $satisfaction, 'profile_completeness' => (string)$customer->profile_completeness]);
        return [
            'ok' => true,
            'profile_completeness' => (string)$customer->profile_completeness,
            'message' => $customer->profile_completeness === self::PROFILE_INCOMPLETE
                ? 'مسیر مشتری ثبت شد؛ پروفایل با وضعیت ناقص برای تأیید مالی ارسال شده است.'
                : 'مسیر مشتری ثبت شد.',
        ];
    }

    public static function create_complaint(int $customer_id, array $data): array
    {
        global $wpdb;
        if (!self::sync_customer($customer_id)) return ['ok'=>false,'message'=>'بروزرسانی وضعیت پروفایل انجام نشد. دوباره تلاش کنید.'];
        $customer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $customer_id));
        if (!$customer) {
            return ['ok' => false, 'message' => 'پروفایل مشتری پیدا نشد.'];
        }
        // v0.8.64.0 — once admins configure complaint subjects, the subject must come from that
        // catalog (dropdown). The label is also stored as a snapshot in `subject`, so the record
        // keeps showing its original subject even if the item is later renamed or deactivated.
        // With no active subjects configured, the legacy free-text subject keeps working.
        $subject = sanitize_text_field($data['subject'] ?? 'شکایت مشتری');
        $subject_id = absint($data['subject_id'] ?? 0);
        if (class_exists('Biawin_CRM_After_Sales_Config')) {
            $subject_cat = Biawin_CRM_After_Sales_Config::CATALOG_SUBJECT;
            if ($subject_id) {
                $item = Biawin_CRM_After_Sales_Config::item($subject_id);
                if (!$item || (string)$item->catalog !== $subject_cat || !(int)$item->is_active) return ['ok' => false, 'message' => 'موضوع نارضایتی انتخاب‌شده معتبر یا فعال نیست.'];
                $subject = (string)$item->label;
            } elseif (Biawin_CRM_After_Sales_Config::items($subject_cat, true)) {
                return ['ok' => false, 'message' => 'موضوع نارضایتی را از فهرست انتخاب کنید.'];
            }
        }
        $row = [
            'customer_id' => $customer_id,
            'subject' => $subject,
            'related_unit' => sanitize_text_field($data['related_unit'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'priority' => sanitize_key($data['priority'] ?? 'normal'),
            'owner_user_id' => isset($data['owner_user_id']) ? absint($data['owner_user_id']) : null,
            'status' => 'open',
            'created_at' => Biawin_CRM_DB::now(),
        ];
        if ($subject_id) $row['subject_id'] = $subject_id;
        $inserted = $wpdb->insert(Biawin_CRM_DB::table('complaints'), $row);
        $id = (int) $wpdb->insert_id;
        if ($inserted === false || !$id) {
            return ['ok' => false, 'message' => 'ثبت شکایت در دیتابیس انجام نشد.'];
        }
        $route = self::set_customer_journey($customer_id, 'complaint', '', 'شکایت مشتری ثبت شد.');
        if (empty($route['ok'])) {
            $wpdb->delete(Biawin_CRM_DB::table('complaints'), ['id' => $id]);
            return $route;
        }
        self::activity($customer_id, 'complaint_created', 'شکایت جدید ثبت شد.', 'complaint', $id);
        return ['ok' => true, 'id' => $id];
    }


    public static function can_manage_complaint(int $complaint_id, int $user_id = 0): bool
    {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) return false;
        if (current_user_can('bcrm_manage_cases')) return true;
        $user = get_user_by('id', $user_id);
        if (!$user || !$user->has_cap('bcrm_complaints')) return false;
        $complaint = $wpdb->get_row($wpdb->prepare(
            'SELECT owner_user_id,status FROM ' . Biawin_CRM_DB::table('complaints') . ' WHERE id=%d',
            $complaint_id
        ));
        if (!$complaint || $complaint->status !== 'open') return false;
        if (in_array('bcrm_complaint_supervisor', (array)$user->roles, true)) return true;
        return $complaint->owner_user_id === null || (int)$complaint->owner_user_id === $user_id;
    }

    /**
     * Outcome keys this flow can execute today (each has built-in behaviour below). Labels, order
     * and active state come from the outcome catalog; outcomes an admin adds get their actions in a
     * later phase (catalog meta_json), so the current form only offers handler-backed outcomes.
     */
    public static function complaint_outcome_handlers(): array
    {
        return ['satisfied', 'refund', 'service_request'];
    }

    /** [key => label] for the existing resolve form: active catalog outcomes that have a handler. */
    public static function complaint_resolution_options(): array
    {
        $fallback = ['satisfied' => 'مشتری راضی شد', 'service_request' => 'تبدیل به درخواست خدمت', 'refund' => 'درخواست عودت وجه'];
        if (!class_exists('Biawin_CRM_After_Sales_Config') || !Biawin_CRM_After_Sales_Config::table_ready('catalog_items')) return $fallback;
        $items = Biawin_CRM_After_Sales_Config::items(Biawin_CRM_After_Sales_Config::CATALOG_OUTCOME);
        if (!$items) return $fallback;
        $options = [];
        foreach ($items as $item) {
            if ((int)$item->is_active && in_array((string)$item->item_key, self::complaint_outcome_handlers(), true)) $options[(string)$item->item_key] = (string)$item->label;
        }
        return $options;
    }

    public static function resolve_complaint(int $complaint_id, string $outcome, string $note = ''): array
    {
        global $wpdb;
        $allowed = self::complaint_outcome_handlers();
        if (!in_array($outcome, $allowed, true)) return ['ok' => false, 'message' => 'نتیجه شکایت معتبر نیست.'];
        // v0.8.64.0 — an outcome the admin deactivated in «تنظیمات نارضایتی» is not accepted for new decisions.
        if (class_exists('Biawin_CRM_After_Sales_Config')) {
            $configured = Biawin_CRM_After_Sales_Config::item_by_key(Biawin_CRM_After_Sales_Config::CATALOG_OUTCOME, $outcome);
            if ($configured && !(int)$configured->is_active) return ['ok' => false, 'message' => 'این نتیجه غیرفعال شده است.'];
        }

        $wpdb->query('START TRANSACTION');
        try {
            $complaint = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('complaints') . ' WHERE id=%d FOR UPDATE', $complaint_id));
            if (!$complaint) throw new RuntimeException('شکایت پیدا نشد.');
            if ((string)$complaint->status !== 'open') throw new RuntimeException('این پرونده نارضایتی قبلاً تعیین تکلیف شده است.');
            if ($outcome === 'refund') {
                $next = self::create_refund((int)$complaint->customer_id, ['complaint_id'=>$complaint_id,'reason'=>$note]);
            } elseif ($outcome === 'service_request') {
                $next = self::set_customer_journey((int)$complaint->customer_id, 'service_request', '', 'شکایت به درخواست خدمت تبدیل شد.');
            } else {
                $next = self::set_customer_journey((int)$complaint->customer_id, 'no_request', 'satisfied', 'مشتری در واحد شکایت راضی شد.');
            }
            if (empty($next['ok'])) throw new RuntimeException($next['message'] ?? 'ثبت نتیجه شکایت انجام نشد.');

            $updated = $wpdb->update(Biawin_CRM_DB::table('complaints'), [
                'resolution' => sanitize_textarea_field($note),
                'resolution_outcome' => $outcome,
                'status' => 'closed',
                'closed_at' => Biawin_CRM_DB::now(),
            ], ['id' => $complaint_id]);
            if ($updated === false) throw new RuntimeException('ذخیره نتیجه شکایت در دیتابیس انجام نشد.');

            self::activity((int)$complaint->customer_id, 'complaint_resolved', 'شکایت تعیین تکلیف شد.', 'complaint', $complaint_id, ['outcome'=>$outcome]);
            $wpdb->query('COMMIT');
            return ['ok'=>true];
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return ['ok'=>false,'message'=>$e->getMessage() ?: 'ثبت نتیجه شکایت انجام نشد.'];
        }
    }

    public static function create_refund(int $customer_id, array $data): array
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('refunds');
        $id = self::insert_case_with_retry($table, 'refund_code', 'REF', [
            'customer_id' => $customer_id,
            'complaint_id' => isset($data['complaint_id']) ? absint($data['complaint_id']) : null,
            'requested_amount' => isset($data['requested_amount']) ? Biawin_CRM_Normalizer::money($data['requested_amount']) : null,
            'reason' => sanitize_textarea_field($data['reason'] ?? ''),
            'status' => 'new',
            'created_by' => get_current_user_id(),
            'created_at' => Biawin_CRM_DB::now(),
            'updated_at' => Biawin_CRM_DB::now(),
        ]);
        if (!$id) return ['ok'=>false,'message'=>'ثبت پرونده عودت در دیتابیس انجام نشد.'];
        $updated = $wpdb->update(Biawin_CRM_DB::table('customers'), ['journey_state' => 'refund', 'updated_at' => Biawin_CRM_DB::now()], ['id' => $customer_id]);
        if ($updated === false) {
            $wpdb->delete($table, ['id'=>$id]);
            return ['ok'=>false,'message'=>'مسیر عودت روی پروفایل مشتری ذخیره نشد.'];
        }
        self::activity($customer_id, 'refund_created', 'پرونده عودت وجه ایجاد شد.', 'refund', $id);
        return ['ok' => true, 'id' => $id];
    }

    public static function active_services(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . Biawin_CRM_DB::table('service_catalog') . " WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
    }

    public static function select_services(int $customer_id, array $service_ids, string $note = ''): array
    {
        global $wpdb;
        $service_ids = array_values(array_unique(array_filter(array_map('absint', $service_ids))));
        if (!$service_ids) return ['ok'=>false,'message'=>'حداقل یک خدمت انتخاب کنید.'];

        $wpdb->query('START TRANSACTION');
        try {
            if (!self::sync_customer($customer_id)) throw new RuntimeException('customer sync failed');
            $customer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d FOR UPDATE', $customer_id));
            if (!$customer) {
                $wpdb->query('ROLLBACK'); return ['ok'=>false,'message'=>'پروفایل مشتری پیدا نشد.'];
            }
            $placeholders = implode(',', array_fill(0, count($service_ids), '%d'));
            $services = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('service_catalog') . " WHERE id IN ($placeholders) AND is_active=1", $service_ids));
            if (count($services) !== count($service_ids)) {
                $wpdb->query('ROLLBACK'); return ['ok'=>false,'message'=>'یک یا چند خدمت معتبر یا فعال نیست.'];
            }
            $existing_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT service_id FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND service_id IN ($placeholders) AND status NOT IN ('rejected','cancelled')",
                array_merge([$customer_id], $service_ids)
            ));
            if ($existing_ids) {
                $existing_ids = array_values(array_unique(array_map('intval', $existing_ids)));
                $missing_ids = array_values(array_diff(array_map('intval', $service_ids), $existing_ids));
                if (!$missing_ids) {
                    $case_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM " . Biawin_CRM_DB::table('service_cases') . " WHERE customer_id=%d AND service_id IN ($placeholders) AND status NOT IN ('rejected','cancelled') ORDER BY id",
                        array_merge([$customer_id], $service_ids)
                    ));
                    $wpdb->query('COMMIT');
                    return ['ok'=>true,'case_ids'=>array_map('intval',(array)$case_ids),'message'=>'درخواست خدمات قبلاً ثبت شده بود؛ همان پرونده‌های موجود نمایش داده می‌شوند.','already_exists'=>true];
                }
                $wpdb->query('ROLLBACK'); return ['ok'=>false,'message'=>'بخشی از خدمات انتخابی قبلاً پرونده فعال دارند. موارد تکراری را از انتخاب خارج کنید.'];
            }
            $reserved = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(credit_amount),0) FROM ".Biawin_CRM_DB::table('service_cases')." WHERE customer_id=%d AND status IN ('unassigned','assigned','in_progress','waiting')",$customer_id));
            $consumed = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(credit_amount),0) FROM ".Biawin_CRM_DB::table('service_cases')." WHERE customer_id=%d AND status='completed'",$customer_id));
            $available=(float)$customer->service_credit_total-$reserved-$consumed;
            $sum=array_sum(array_map(fn($x)=>(float)$x->credit_cost,$services));
            // Over-allocation is allowed by business rule. Remaining credit may become negative; UI shows a warning only.
            $created=[];
            foreach($services as $service){
                $table=Biawin_CRM_DB::table('service_cases');
                $case_id=self::insert_case_with_retry($table,'case_code','SRV',[
                    'customer_id'=>$customer_id,'service_id'=>(int)$service->id,
                    'credit_amount'=>(float)$service->credit_cost,'status'=>'unassigned','unit_key'=>$service->unit_key,
                    'note'=>sanitize_textarea_field($note),'created_by'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()
                ]);
                if(!$case_id) throw new RuntimeException('service case insert failed');
                $created[]=$case_id;
                self::activity($customer_id,'service_case_created','درخواست خدمت «'.$service->name.'» ایجاد شد.','service_case',$case_id,['service_id'=>(int)$service->id]);
            }
            $journey_updated = $wpdb->update(Biawin_CRM_DB::table('customers'),['journey_state'=>'service_request','updated_at'=>Biawin_CRM_DB::now()],['id'=>$customer_id]);
            if ($journey_updated === false) throw new RuntimeException('customer journey update failed');
            $wpdb->query('COMMIT');
            self::sync_customer($customer_id);
            self::activity($customer_id,'journey_changed','خدمات مشتری انتخاب و پرونده‌های تخصصی ساخته شد.','customer',$customer_id,['journey'=>'service_request']);
            return ['ok'=>true,'case_ids'=>$created];
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return ['ok'=>false,'message'=>'ایجاد پرونده‌های خدمات انجام نشد.'];
        }
    }

    public static function supervised_unit_keys(int $user_id = 0): array
    {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) return [];
        return array_values(array_unique(array_filter($wpdb->get_col($wpdb->prepare(
            'SELECT u.unit_key FROM '.Biawin_CRM_DB::table('service_unit_members').' m INNER JOIN '.Biawin_CRM_DB::table('service_units').' u ON u.id=m.unit_id WHERE m.user_id=%d AND m.member_role=%s AND u.is_active=1',
            $user_id, 'supervisor'
        )))));
    }

    public static function can_supervise_unit(string $unit_key, int $user_id = 0): bool
    {
        if (current_user_can('bcrm_manage_cases')) return true;
        return in_array($unit_key, self::supervised_unit_keys($user_id), true);
    }

    public static function unit_agents(string $unit_key): array
    {
        global $wpdb;
        $unit_id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.Biawin_CRM_DB::table('service_units').' WHERE unit_key=%s AND is_active=1',$unit_key));
        if(!$unit_id)return [];
        $ids=$wpdb->get_col($wpdb->prepare("SELECT user_id FROM ".Biawin_CRM_DB::table('service_unit_members')." WHERE unit_id=%d AND member_role='agent'",$unit_id));
        $out=[]; foreach($ids as $id){$u=get_user_by('id',(int)$id);if($u && !(bool)get_user_meta($u->ID,'bcrm_disabled',true))$out[]=$u;}
        return $out;
    }

    public static function assign_service_case(int $case_id, int $agent_user_id): array
    {
        global $wpdb;
        $case = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('service_cases') . ' WHERE id=%d', $case_id));
        if (!$case) {
            return ['ok' => false, 'message' => 'پرونده خدمت پیدا نشد.'];
        }
        if (!self::can_supervise_unit((string)$case->unit_key)) {
            return ['ok'=>false,'message'=>'شما سرپرست این واحد خدمات نیستید.'];
        }
        $agent = get_user_by('id', $agent_user_id);
        $allowed_ids=array_map(fn($u)=>(int)$u->ID,self::unit_agents((string)$case->unit_key));
        if (!$agent || !$agent->has_cap('bcrm_services') || (bool)get_user_meta($agent_user_id, 'bcrm_disabled', true) || !in_array($agent_user_id,$allowed_ids,true)) {
            return ['ok' => false, 'message' => 'کارشناس انتخاب‌شده عضو فعال این واحد خدمات نیست.'];
        }
        $updated = $wpdb->update(Biawin_CRM_DB::table('service_cases'), [
            'assigned_user_id' => $agent_user_id,
            'assigned_by' => get_current_user_id(),
            'assigned_at' => Biawin_CRM_DB::now(),
            'status' => 'assigned',
            'updated_at' => Biawin_CRM_DB::now(),
        ], ['id' => $case_id]);
        if ($updated === false) return ['ok' => false, 'message' => 'تخصیص پرونده در دیتابیس ذخیره نشد.'];
        self::activity((int) $case->customer_id, 'service_case_assigned', 'پرونده خدمت به کارشناس تخصیص داده شد.', 'service_case', $case_id, ['agent_user_id' => $agent_user_id]);
        return ['ok' => true];
    }

    public static function update_service_case(int $case_id, string $status, string $note = ''): array
    {
        global $wpdb;
        $allowed = ['unassigned','assigned','in_progress','waiting','completed','rejected','cancelled'];
        if (!in_array($status, $allowed, true)) {
            return ['ok' => false, 'message' => 'وضعیت خدمت معتبر نیست.'];
        }
        $case = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('service_cases') . ' WHERE id=%d', $case_id));
        if (!$case) {
            return ['ok' => false, 'message' => 'پرونده خدمت پیدا نشد.'];
        }
        if (!current_user_can('bcrm_manage_cases') && (int)$case->assigned_user_id !== get_current_user_id()) {
            return ['ok' => false, 'message' => 'این پرونده خدمت به شما تخصیص داده نشده است.'];
        }
        $data = ['status' => $status, 'note' => sanitize_textarea_field($note), 'updated_at' => Biawin_CRM_DB::now()];
        $data['closed_at'] = in_array($status, ['completed','rejected','cancelled'], true) ? Biawin_CRM_DB::now() : null;
        $updated = $wpdb->update(Biawin_CRM_DB::table('service_cases'), $data, ['id' => $case_id]);
        if ($updated === false) return ['ok' => false, 'message' => 'وضعیت خدمت در دیتابیس ذخیره نشد.'];
        self::activity((int) $case->customer_id, 'service_case_status', 'وضعیت پرونده خدمت تغییر کرد.', 'service_case', $case_id, ['status' => $status, 'note' => $note]);
        self::sync_customer((int) $case->customer_id);
        return ['ok' => true];
    }

    public static function customer_timeline(int $customer_id, int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT a.*, u.display_name AS actor_name FROM ' . Biawin_CRM_DB::table('activities') . ' a LEFT JOIN ' . $wpdb->users . ' u ON u.ID=a.actor_user_id WHERE a.customer_id=%d ORDER BY a.id DESC LIMIT %d',
            $customer_id,
            $limit
        ));
    }

    public static function activity(int $customer_id, string $type, string $title, string $object_type = 'customer', ?int $object_id = null, array $meta = []): void
    {
        global $wpdb;
        $wpdb->insert(Biawin_CRM_DB::table('activities'), [
            'customer_id' => $customer_id,
            'actor_user_id' => get_current_user_id() ?: null,
            'activity_type' => sanitize_key($type),
            'object_type' => sanitize_key($object_type),
            'object_id' => $object_id,
            'title' => sanitize_text_field($title),
            'meta_json' => $meta ? wp_json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => Biawin_CRM_DB::now(),
        ]);
    }

    private static function insert_case_with_retry(string $table, string $code_field, string $prefix, array $data): int
    {
        global $wpdb;
        for ($attempt=0; $attempt<6; $attempt++) {
            $data[$code_field] = self::next_case_code($prefix, $table);
            $ok = $wpdb->insert($table, $data);
            if ($ok !== false && (int)$wpdb->insert_id > 0) return (int)$wpdb->insert_id;
            usleep(10000 + ($attempt * 5000));
        }
        return 0;
    }

    private static function next_case_code(string $prefix, string $table): string
    {
        global $wpdb;
        $id = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0)+1 FROM $table");
        return $prefix . '-' . date_i18n('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
