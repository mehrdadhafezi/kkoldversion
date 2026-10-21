<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Customer_Service
{
    public static function ensure_customer_code(int $customer_id): string
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $table = Biawin_CRM_DB::table('customers');
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT customer_code FROM $table WHERE id = %d", $customer_id));
            if ($existing && preg_match('/^\d+$/', (string) $existing)) return (string)$existing;

            $max_code = (int) $wpdb->get_var("SELECT MAX(CAST(customer_code AS UNSIGNED)) FROM $table WHERE customer_code REGEXP '^[0-9]+$'");
            $code = (string) max(1000, $max_code + 1);
            $updated = Biawin_CRM_DB::write('update', $table, ['customer_code' => $code, 'status' => 'verified', 'updated_at' => Biawin_CRM_DB::now()], ['id' => $customer_id]);
            if ($updated !== false) {
                Biawin_CRM_DB::log('customer', $customer_id, 'customer_code_generated', null, ['customer_code' => $code]);
                return $code;
            }
            // Concurrent calls can race on the unique customer_code index. Re-read and retry with the next value.
            usleep(10000 + ($attempt * 5000));
        }
        return '';
    }

    public static function recalculate_customer(int $customer_id): bool
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN COALESCE(exclude_from_total,0)=1 THEN 0 ELSE amount_paid END), 0) FROM " . Biawin_CRM_DB::table('invoices') . " WHERE customer_id = %d",
            $customer_id
        ));
        $declared = $wpdb->get_var($wpdb->prepare(
            "SELECT declared_paid FROM " . Biawin_CRM_DB::table('interactions') . " WHERE customer_id = %d AND declared_paid IS NOT NULL ORDER BY id DESC LIMIT 1",
            $customer_id
        ));
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(satisfaction_score) FROM " . Biawin_CRM_DB::table('interactions') . " WHERE customer_id = %d AND satisfaction_score IS NOT NULL",
            $customer_id
        ));
        $open_complaints = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . Biawin_CRM_DB::table('complaints') . " WHERE customer_id = %d AND status = 'open'",
            $customer_id
        ));

        $declared_value = $declared === null ? null : (float) $declared;
        $discrepancy = $declared_value === null ? 0 : $declared_value - $total;
        $grade = self::grade($customer_id, $total, $avg === null ? null : (float) $avg, $open_complaints, $discrepancy);

        $updated = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('customers'), [
            'total_paid' => $total,
            'declared_paid' => $declared_value,
            'discrepancy_amount' => $discrepancy,
            'satisfaction_avg' => $avg === null ? null : round((float) $avg, 2),
            'financial_score' => self::financial_score($total),
            'grade' => $grade,
            'updated_at' => Biawin_CRM_DB::now(),
        ], ['id' => $customer_id]);
        if ($updated === false) return false;

        if (class_exists('Biawin_CRM_Workflow_Service')) {
            return Biawin_CRM_Workflow_Service::sync_customer($customer_id);
        }
        return true;
    }


    public static function ensure_single_primary_phone(int $customer_id): void
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $table = Biawin_CRM_DB::table('phones');
        $primary_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE customer_id=%d AND is_primary=1 ORDER BY id ASC", $customer_id));
        if (count($primary_ids) > 1) {
            $keep = (int)$primary_ids[0];
            Biawin_CRM_DB::write('query', $wpdb->prepare("UPDATE $table SET is_primary=0 WHERE customer_id=%d AND id<>%d", $customer_id, $keep));
        } elseif (!$primary_ids) {
            $first = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE customer_id=%d ORDER BY id ASC LIMIT 1", $customer_id));
            if ($first) Biawin_CRM_DB::write('update', $table, ['is_primary'=>1], ['id'=>$first]);
        }
    }

    public static function collapse_empty_profile(int $source_customer_id, int $target_customer_id, string $reason = ''): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($target_customer_id);
        Biawin_CRM_DB::assert_customer_mutable($source_customer_id);
        if (!$source_customer_id || !$target_customer_id || $source_customer_id === $target_customer_id) {
            return ['deleted'=>false];
        }
        $invoice_count = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d',
            $source_customer_id
        ));
        if ($invoice_count > 0) return ['deleted'=>false,'reason'=>'source_has_invoices'];

        // v0.8.5 identity-safety guard: a source profile is NOT "empty" while it still owns phones.
        // Deleting such a profile used to detach many unrelated numbers and could indirectly mix identities.
        // The agent must explicitly move the intended phone(s); only a truly phone-less profile may collapse.
        $phones = Biawin_CRM_DB::table('phones');
        $remaining_phone_count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $phones WHERE customer_id=%d",
            $source_customer_id
        ));
        if ($remaining_phone_count > 0) {
            return ['deleted'=>false,'reason'=>'source_has_remaining_phones','remaining_phone_count'=>$remaining_phone_count];
        }

        Biawin_CRM_DB::write('query', 'START TRANSACTION');
        try {
            $simple_tables = ['interactions','complaints','financial_cases','refunds','service_cases','activities','attachments','services'];
            foreach ($simple_tables as $name) {
                $table = Biawin_CRM_DB::table($name);
                $updated = Biawin_CRM_DB::write('update', $table, ['customer_id'=>$target_customer_id], ['customer_id'=>$source_customer_id]);
                if ($updated === false) throw new RuntimeException('merge '.$name.' failed');
            }

            // Assignment ownership must never jump to another agent merely because two profiles are merged.
            // Close any active assignment on the disappearing source profile; do NOT move it to target.
            // The target keeps its own current assignment unchanged.
            $assignments = Biawin_CRM_DB::table('assignments');
            $source_active_agents = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT agent_user_id FROM $assignments WHERE customer_id=%d AND status='assigned'",
                $source_customer_id
            )));
            $closed_assignments = Biawin_CRM_DB::write('query', $wpdb->prepare(
                "UPDATE $assignments SET status='merged_closed', completed_at=%s WHERE customer_id=%d AND status='assigned'",
                Biawin_CRM_DB::now(), $source_customer_id
            ));
            if ($closed_assignments === false) throw new RuntimeException('close source assignments failed');
            self::ensure_single_primary_phone($target_customer_id);

            $source = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('customers').' WHERE id=%d', $source_customer_id));
            $deleted = Biawin_CRM_DB::write('delete', Biawin_CRM_DB::table('customers'), ['id'=>$source_customer_id]);
            if ($deleted === false) throw new RuntimeException('customer delete failed');

            Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('merge_events'), [
                'primary_customer_id'=>$target_customer_id,
                'merged_customer_ids'=>wp_json_encode([$source_customer_id]),
                'merge_type'=>'manual',
                'action_type'=>'collapse_empty_profile',
                'confidence'=>100,
                'reason'=>$reason ?: 'پروفایل بدون فاکتور پس از ادغام حذف شد.',
                'before_json'=>$source ? wp_json_encode($source, JSON_UNESCAPED_UNICODE) : null,
                'after_json'=>wp_json_encode(['customer_id'=>$target_customer_id], JSON_UNESCAPED_UNICODE),
                'performed_by'=>get_current_user_id() ?: null,
                'created_at'=>Biawin_CRM_DB::now(),
            ]);
            if (!empty($source_active_agents) && class_exists('Biawin_CRM_Workflow_Service')) {
                Biawin_CRM_Workflow_Service::activity(
                    $target_customer_id,
                    'source_assignment_closed_on_merge',
                    'تخصیص فعال پروفایل مبدا هنگام ادغام بسته شد و تخصیص پروفایل مقصد تغییر نکرد.',
                    'customer',
                    $target_customer_id,
                    ['source_customer_id'=>$source_customer_id,'closed_agent_user_ids'=>$source_active_agents]
                );
            }
            Biawin_CRM_DB::write('query', 'COMMIT');
            self::recalculate_customer($target_customer_id);
            return ['deleted'=>true,'deleted_customer_id'=>$source_customer_id,'profile_count'=>(int)$wpdb->get_var('SELECT COUNT(*) FROM '.Biawin_CRM_DB::table('customers'))];
        } catch (Throwable $e) {
            Biawin_CRM_DB::write('query', 'ROLLBACK');
            return ['deleted'=>false,'error'=>$e->getMessage()];
        }
    }

    public static function set_phone_verification(int $customer_id, int $phone_id, string $status, bool $make_primary = false): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $allowed = ['unverified','verified','verified_primary','wrong','other_person','blocked'];
        $phone = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('phones').' WHERE id=%d AND customer_id=%d', $phone_id, $customer_id));
        if (!$phone) return ['ok'=>false,'message'=>'شماره در این پروفایل پیدا نشد.'];
        // Empty status means "keep the current verification state"; this is used when only changing the primary phone.
        if ($status === '') $status = (string)($phone->phone_status ?: 'unverified');
        if (!in_array($status, $allowed, true)) return ['ok'=>false,'message'=>'وضعیت شماره معتبر نیست.'];
        if ($make_primary) {
            $cleared = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('phones'), ['is_primary'=>0], ['customer_id'=>$customer_id]);
            if ($cleared === false) return ['ok'=>false,'message'=>'تغییر شماره اصلی در دیتابیس انجام نشد.'];
        }
        $data = [
            'phone_status'=>$status,
            'verified_at'=>in_array($status,['verified','verified_primary'],true) ? ($phone->verified_at ?: Biawin_CRM_DB::now()) : null,
            'auto_match_blocked'=>in_array($status,['wrong','other_person','blocked'],true)?1:0,
        ];
        if ($make_primary) $data['is_primary']=1;
        $ok=Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('phones'),$data,['id'=>$phone_id]);
        if ($ok===false) return ['ok'=>false,'message'=>'وضعیت شماره ذخیره نشد.'];
        self::ensure_single_primary_phone($customer_id);
        Biawin_CRM_DB::log('customer',$customer_id,'phone_verification_changed',null,['phone_id'=>$phone_id,'status'=>$status,'primary'=>$make_primary]);
        return ['ok'=>true,'message'=>'وضعیت شماره بروزرسانی شد.'];
    }

    public static function search_related(string $query): array
    {
        global $wpdb;
        $normalized_phone = Biawin_CRM_Normalizer::phone($query);
        $like = '%' . $wpdb->esc_like(trim($query)) . '%';
        $results = ['phones' => [], 'invoices' => []];

        if ($normalized_phone !== '') {
            // Phone search must be exact. Never expand from the owner profile to all of that profile's invoices;
            // a shared/generic phone can otherwise surface many unrelated invoices and cause accidental merges.
            $results['phones'] = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, c.display_name, c.customer_code FROM " . Biawin_CRM_DB::table('phones') . " p
                 LEFT JOIN " . Biawin_CRM_DB::table('customers') . " c ON c.id = p.customer_id
                 WHERE p.normalized_phone = %s
                 LIMIT 20",
                $normalized_phone
            ));
            $results['phone_usage_count'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT invoice_id) FROM " . Biawin_CRM_DB::table('invoice_phones') . " WHERE normalized_phone=%s",
                $normalized_phone
            ));
            $phone_invoices = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT i.*, c.display_name, c.customer_code FROM " . Biawin_CRM_DB::table('invoice_phones') . " ip
                 INNER JOIN " . Biawin_CRM_DB::table('invoices') . " i ON i.id=ip.invoice_id
                 LEFT JOIN " . Biawin_CRM_DB::table('customers') . " c ON c.id=i.customer_id
                 WHERE ip.normalized_phone=%s
                 ORDER BY i.id DESC
                 LIMIT 50",
                $normalized_phone
            ));
            $results['invoices'] = array_merge($results['invoices'], $phone_invoices);
        }

        $direct_invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, c.display_name, c.customer_code FROM " . Biawin_CRM_DB::table('invoices') . " i
             LEFT JOIN " . Biawin_CRM_DB::table('customers') . " c ON c.id = i.customer_id
             WHERE i.invoice_code LIKE %s OR i.invoice_name LIKE %s OR c.display_name LIKE %s
             LIMIT 20",
            $like,
            $like,
            $like
        ));
        $merged = [];
        foreach (array_merge($results['invoices'], $direct_invoices) as $invoice) {
            $merged[$invoice->invoice_code] = $invoice;
        }
        $results['invoices'] = array_values($merged);
        foreach ($results['invoices'] as $invoice) {
            $invoice->phones = $wpdb->get_col($wpdb->prepare("SELECT normalized_phone FROM " . Biawin_CRM_DB::table('invoice_phones') . " WHERE invoice_id=%d ORDER BY phone_position,id", (int)$invoice->id));
        }

        return $results;
    }

    public static function invoice_payload(string $invoice_code): ?array
    {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('invoices') . " WHERE invoice_code = %s", $invoice_code));
        if (!$invoice) {
            return null;
        }
        $items = $wpdb->get_col($wpdb->prepare("SELECT product_name FROM " . Biawin_CRM_DB::table('invoice_items') . " WHERE invoice_id = %d", (int) $invoice->id));
        $phones = $wpdb->get_col($wpdb->prepare("SELECT normalized_phone FROM " . Biawin_CRM_DB::table('invoice_phones') . " WHERE invoice_id = %d ORDER BY phone_position,id", (int) $invoice->id));
        return [
            'invoice_code' => $invoice->invoice_code,
            'invoice_name' => $invoice->invoice_name,
            'items' => implode('، ', $items),
            'phones' => $phones,
            'amount_paid' => number_format_i18n((float) $invoice->amount_paid),
            'seller' => $invoice->seller ?: '-',
            'callcenter' => $invoice->callcenter ?: '-',
            'invoice_status' => $invoice->invoice_status ?: '-',
            'has_input_conflict' => (int) $invoice->has_input_conflict,
        ];
    }

    public static function attach_invoice(int $target_customer_id, string $invoice_code, string $reason, bool $collapse_source = true, bool $allow_coded_merge = false): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($target_customer_id);
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . Biawin_CRM_DB::table('invoices') . " WHERE invoice_code = %s",
            $invoice_code
        ));
        if (!$invoice) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }

        $source_customer_id = (int) $invoice->customer_id;
        if ($source_customer_id === $target_customer_id) {
            return ['ok' => true, 'message' => 'این فاکتور از قبل در همین پروفایل قرار دارد.'];
        }

        $target_code = $wpdb->get_var($wpdb->prepare("SELECT customer_code FROM " . Biawin_CRM_DB::table('customers') . " WHERE id = %d", $target_customer_id));
        $source_code = $source_customer_id ? $wpdb->get_var($wpdb->prepare("SELECT customer_code FROM " . Biawin_CRM_DB::table('customers') . " WHERE id = %d", $source_customer_id)) : null;
        if ($target_code && $source_code && !$allow_coded_merge && !current_user_can('bcrm_sensitive_merge')) {
            return ['ok' => false, 'message' => 'اتصال دو پروفایل دارای کد مشتری فقط با دسترسی سرپرست/مدیر مجاز است.'];
        }

        $before = ['invoice_id' => (int) $invoice->id, 'from_customer_id' => $source_customer_id, 'to_customer_id' => $target_customer_id];
        $updated_invoice = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('invoices'), ['customer_id' => $target_customer_id, 'updated_at' => Biawin_CRM_DB::now()], ['id' => (int) $invoice->id]);
        if ($updated_invoice === false) {
            return ['ok'=>false,'message'=>'اتصال فاکتور در دیتابیس انجام نشد.'];
        }
        $updated_services = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('services'), ['customer_id' => $target_customer_id], ['invoice_id' => (int) $invoice->id]);
        if ($updated_services === false) {
            return ['ok'=>false,'message'=>'اتصال خدمات فاکتور به پروفایل انجام نشد.'];
        }
        self::recalculate_customer($target_customer_id);
        $collapse = ['deleted'=>false];
        if ($source_customer_id) {
            self::recalculate_customer($source_customer_id);
            if ($collapse_source && $source_customer_id !== $target_customer_id) {
                $collapse = self::collapse_empty_profile($source_customer_id, $target_customer_id, $reason);
            }
        }

        Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('merge_events'), [
            'primary_customer_id' => $target_customer_id,
            'merged_customer_ids' => wp_json_encode(array_filter([$source_customer_id]), JSON_UNESCAPED_UNICODE),
            'merge_type' => 'manual',
            'action_type' => 'attach_invoice',
            'confidence' => 100,
            'reason' => $reason,
            'before_json' => wp_json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => wp_json_encode(['invoice_id' => (int) $invoice->id, 'customer_id' => $target_customer_id], JSON_UNESCAPED_UNICODE),
            'performed_by' => get_current_user_id(),
            'created_at' => Biawin_CRM_DB::now(),
        ]);
        Biawin_CRM_DB::log('invoice', (int) $invoice->id, 'invoice_attached_to_customer', $before, ['customer_id' => $target_customer_id]);
        self::ensure_customer_code($target_customer_id);
        return ['ok' => true, 'message' => 'فاکتور به پروفایل متصل شد.', 'profile_deleted'=>!empty($collapse['deleted']), 'profile_count'=>$collapse['profile_count'] ?? null];
    }

    public static function attach_invoice_and_phones(int $target_customer_id, string $invoice_code, array $extra_phones = [], string $reason = 'اتصال شماره و فاکتور توسط کارشناس'): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($target_customer_id);
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('invoices') . " WHERE invoice_code=%s", trim($invoice_code)));
        if (!$invoice) {
            return ['ok' => false, 'message' => 'فاکتور پیدا نشد.'];
        }

        $invoice_phones_all = $wpdb->get_col($wpdb->prepare(
            "SELECT normalized_phone FROM " . Biawin_CRM_DB::table('invoice_phones') . " WHERE invoice_id=%d ORDER BY phone_position,id",
            (int)$invoice->id
        ));

        // v0.8.65.2: attach_invoice_and_phones is EXPLICIT only. Never infer/fallback to an invoice phone.
        // The UI offers two distinct operations: invoice-only, or invoice + a specifically selected phone.
        $phones_to_attach = [];
        foreach ($extra_phones as $raw) {
            $n = Biawin_CRM_Normalizer::phone($raw);
            if ($n !== '') $phones_to_attach[] = $n;
        }
        $phones_to_attach = array_values(array_unique(array_filter($phones_to_attach)));
        if (!$phones_to_attach) {
            return ['ok'=>false,'message'=>'برای «اتصال فاکتور + شماره» باید شماره را صریحاً انتخاب کنید. برای انتقال بدون شماره از «اتصال فقط فاکتور» استفاده کنید.'];
        }

        // Guard against shared/generic phones. This is about a phone repeated across many invoices,
        // not about one invoice having several legitimate phone numbers.
        foreach ($phones_to_attach as $phone) {
            $usage = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT invoice_id) FROM " . Biawin_CRM_DB::table('invoice_phones') . " WHERE normalized_phone=%s",
                $phone
            ));
            if ($usage >= 50 && !current_user_can('bcrm_sensitive_merge')) return ['ok'=>false,'message'=>'این شماره در ' . $usage . ' فاکتور مختلف تکرار شده و احتمال شماره عمومی/مشترک بسیار بالاست. اتصال آن فقط با بررسی سرپرست مجاز است.'];
        }

        $source_customer_id = (int)$invoice->customer_id;
        $target_code = $wpdb->get_var($wpdb->prepare("SELECT customer_code FROM " . Biawin_CRM_DB::table('customers') . " WHERE id=%d", $target_customer_id));
        $source_code = $source_customer_id ? $wpdb->get_var($wpdb->prepare("SELECT customer_code FROM " . Biawin_CRM_DB::table('customers') . " WHERE id=%d", $source_customer_id)) : null;
        $invoice_phone_map = array_flip(array_map('strval', $invoice_phones_all));
        $has_invoice_phone_bridge = (bool) array_intersect($phones_to_attach, array_keys($invoice_phone_map));
        if ($source_customer_id && $source_customer_id !== $target_customer_id && $target_code && $source_code && !$has_invoice_phone_bridge && !current_user_can('bcrm_sensitive_merge')) return ['ok' => false, 'message' => 'این اتصال باعث ادغام دو پروفایل دارای کد مشتری می‌شود و فقط سرپرست/مدیر مجاز است.'];

        foreach ($phones_to_attach as $phone) {
            $owner = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('phones') . " WHERE normalized_phone=%s", $phone));
            if ($owner && (int)$owner->customer_id !== $target_customer_id && (int)$owner->customer_id !== $source_customer_id && !current_user_can('bcrm_manage_cases')) {
                return ['ok' => false, 'message' => 'شماره ' . $phone . ' به پروفایل دیگری متصل است و نیاز به تأیید سرپرست دارد.'];
            }
        }

        Biawin_CRM_DB::write('query', 'START TRANSACTION');
        try {
            $res = self::attach_invoice($target_customer_id, $invoice_code, $reason, false, $has_invoice_phone_bridge);
            if (empty($res['ok'])) {
                Biawin_CRM_DB::write('query', 'ROLLBACK');
                return $res;
            }
            foreach ($phones_to_attach as $idx => $phone) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('phones') . " WHERE normalized_phone=%s", $phone));
                if ($existing) {
                    $phone_updated = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('phones'), [
                        'customer_id' => $target_customer_id,
                        'source' => 'invoice_attach_explicit',
                        'is_primary' => $idx === 0 ? 1 : (int)$existing->is_primary,
                    ], ['id' => (int)$existing->id]);
                    if ($phone_updated === false) throw new RuntimeException('phone update failed');
                } else {
                    $phone_inserted = Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('phones'), [
                        'customer_id' => $target_customer_id,
                        'raw_phone' => $phone,
                        'normalized_phone' => $phone,
                        'phone_status' => 'unverified',
                        'relation_type' => 'self',
                        'source' => 'invoice_attach_explicit',
                        'is_primary' => $idx === 0 ? 1 : 0,
                        'auto_match_blocked' => 0,
                        'created_at' => Biawin_CRM_DB::now(),
                    ]);
                    if ($phone_inserted === false) throw new RuntimeException('phone insert failed');
                }
            }
            self::recalculate_customer($target_customer_id);
            if ($source_customer_id && $source_customer_id !== $target_customer_id) self::recalculate_customer($source_customer_id);
            self::ensure_single_primary_phone($target_customer_id);

            $log_after = [
                'invoice_code'=>$invoice_code,
                'phones_attached'=>$phones_to_attach,
                'invoice_phones_snapshot'=>array_values(array_unique(array_filter($invoice_phones_all))),
                'from_customer_id'=>$source_customer_id,
                'to_customer_id'=>$target_customer_id,
                'request_actor'=>get_current_user_id(),
            ];
            Biawin_CRM_DB::log('customer', $target_customer_id, 'invoice_and_explicit_phone_attached', null, $log_after);
            if (class_exists('Biawin_CRM_Workflow_Service')) {
                Biawin_CRM_Workflow_Service::activity(
                    $target_customer_id,
                    'invoice_and_phones_attached',
                    'شماره انتخاب‌شده و فاکتور به پروفایل متصل شدند.',
                    'invoice',
                    (int)$invoice->id,
                    $log_after
                );
            }
            Biawin_CRM_DB::write('query', 'COMMIT');
            $collapse = ['deleted'=>false];
            if ($source_customer_id && $source_customer_id !== $target_customer_id) {
                $collapse = self::collapse_empty_profile($source_customer_id, $target_customer_id, $reason);
            }
            return [
                'ok'=>true,
                'message'=>'شماره انتخاب‌شده و فاکتور با موفقیت به پروفایل متصل شدند.',
                'phones'=>$phones_to_attach,
                'invoice_id'=>(int)$invoice->id,
                'profile_deleted'=>!empty($collapse['deleted']),
                'profile_count'=>$collapse['profile_count']??null,
            ];
        } catch (Throwable $e) {
            Biawin_CRM_DB::write('query', 'ROLLBACK');
            return ['ok'=>false,'message'=>'اتصال شماره و فاکتور انجام نشد.' . (current_user_can('manage_options') && ($wpdb->last_error || $e->getMessage()) ? ' DB: ' . ($wpdb->last_error ?: $e->getMessage()) : '')];
        }
    }

    public static function create_manual_invoice_with_phone(int $customer_id, string $phone, string $invoice_code, float $amount, string $payment_info = '', string $name = ''): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $normalized = Biawin_CRM_Normalizer::phone($phone);
        $invoice_code = trim($invoice_code);
        if ($normalized === '') return ['ok'=>false,'message'=>'شماره موبایل معتبر (با فرمت 09xxxxxxxxx) الزامی است.'];

        $customer = $wpdb->get_row($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $customer_id));
        if (!$customer) return ['ok'=>false,'message'=>'پروفایل مشتری پیدا نشد.'];

        // A newly reported phone may legitimately have no invoice in the CRM.
        // In that case save the phone alone and do not force invoice code or amount.
        if ($invoice_code === '') {
            $added = self::add_phone($customer_id, $phone, 'unverified', 'self');
            if (empty($added['ok'])) return $added;
            self::ensure_customer_code($customer_id);
            if (class_exists('Biawin_CRM_Workflow_Service')) {
                Biawin_CRM_Workflow_Service::activity($customer_id, 'phone_added_without_invoice', 'شماره جدید بدون فاکتور به پروفایل اضافه شد.', 'customer', $customer_id, ['phone'=>$normalized]);
            }
            return ['ok'=>true,'message'=>'شماره جدید به پروفایل اضافه شد.','invoice_id'=>0,'phones'=>[$normalized],'phone_only'=>true];
        }

        $existing_invoice = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE invoice_code=%s', $invoice_code));
        if ($existing_invoice) {
            return self::attach_invoice_and_phones($customer_id, $invoice_code, [$normalized], 'اتصال شماره و فاکتور در تکمیل مالی');
        }
        // Amount is optional for a newly reported invoice; unknown amount is stored as zero and
        // can be completed later by finance without blocking identity linkage.
        if ($amount < 0) $amount = 0;

        // شماره و فاکتور جدید یک واحد اتمیک هستند: اگر اتصال شماره نامعتبر باشد، هیچ فاکتوری ساخته نمی‌شود.
        $phone_owner = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE normalized_phone=%s', $normalized));
        if ($phone_owner && (int)$phone_owner->customer_id !== $customer_id) {
            return ['ok'=>false,'message'=>'این شماره از قبل روی پروفایل دیگری است. ثبت فاکتور دستی نباید مالک شماره را خودکار تغییر دهد؛ ابتدا اتصال هویت را از مسیر جست‌وجو/اتصال بررسی کنید.'];
        }

        Biawin_CRM_DB::write('query', 'START TRANSACTION');
        try {
            $now = Biawin_CRM_DB::now();
            $invoice_table = Biawin_CRM_DB::table('invoices');
            $ok = Biawin_CRM_DB::write('insert', $invoice_table, [
                'customer_id' => $customer_id,
                'invoice_code' => sanitize_text_field($invoice_code),
                'invoice_name' => sanitize_text_field($name),
                'amount_paid' => $amount,
                'invoice_status' => 'ثبت تکمیلی مالی',
                'payment_info' => sanitize_textarea_field($payment_info),
                'has_input_conflict' => 0,
                'source_import_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$ok || !$wpdb->insert_id) throw new RuntimeException('invoice insert failed');
            $invoice_id = (int)$wpdb->insert_id;

            if (!Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('payments'), [
                'invoice_id' => $invoice_id,
                'amount' => $amount,
                'payment_info' => sanitize_textarea_field($payment_info),
                'source_import_id' => null,
                'created_at' => $now,
            ])) throw new RuntimeException('payment insert failed');

            if ($phone_owner) {
                $phone_updated = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('phones'), [
                    'customer_id' => $customer_id,
                    'source' => 'manual_invoice_bundle',
                    'is_primary' => (int)$phone_owner->is_primary ?: 1,
                ], ['id' => (int)$phone_owner->id]);
                if ($phone_updated === false) throw new RuntimeException('phone update failed');
            } else {
                if (!Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('phones'), [
                    'customer_id' => $customer_id,
                    'raw_phone' => $phone,
                    'normalized_phone' => $normalized,
                    'phone_status' => 'unverified',
                    'relation_type' => 'self',
                    'source' => 'manual_invoice_bundle',
                    'is_primary' => 1,
                    'auto_match_blocked' => 0,
                    'created_at' => $now,
                ])) throw new RuntimeException('phone insert failed');
            }

            $invoice_phone_sql = $wpdb->prepare(
                'INSERT INTO ' . Biawin_CRM_DB::table('invoice_phones') . ' (invoice_id,normalized_phone,phone_position,created_at) VALUES (%d,%s,1,%s)',
                $invoice_id, $normalized, $now
            );
            if (Biawin_CRM_DB::write('query', $invoice_phone_sql) === false) throw new RuntimeException('invoice phone insert failed');

            self::recalculate_customer($customer_id);
            self::ensure_customer_code($customer_id);
            Biawin_CRM_DB::log('invoice', $invoice_id, 'manual_invoice_phone_bundle_created', null, [
                'customer_id'=>$customer_id,'invoice_code'=>$invoice_code,'phone'=>$normalized,'amount'=>$amount
            ]);
            if (class_exists('Biawin_CRM_Workflow_Service')) {
                Biawin_CRM_Workflow_Service::activity($customer_id, 'manual_invoice_phone_bundle_created', 'شماره و فاکتور تکمیلی به‌صورت هم‌زمان ثبت شدند.', 'invoice', $invoice_id, ['invoice_code'=>$invoice_code,'phone'=>$normalized,'amount'=>$amount]);
            }
            Biawin_CRM_DB::write('query', 'COMMIT');
            return ['ok'=>true,'message'=>'شماره و فاکتور ثبت شدند و پروفایل دوباره محاسبه شد.','invoice_id'=>$invoice_id,'phones'=>[$normalized]];
        } catch (Throwable $e) {
            Biawin_CRM_DB::write('query', 'ROLLBACK');
            return ['ok'=>false,'message'=>'ثبت هم‌زمان شماره و فاکتور انجام نشد؛ هیچ داده ناقصی ذخیره نشد.' . (current_user_can('manage_options') && $wpdb->last_error ? ' DB: ' . $wpdb->last_error : '')];
        }
    }

    public static function add_phone(int $customer_id, string $raw_phone, string $status, string $relation): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $phone = Biawin_CRM_Normalizer::phone($raw_phone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'شماره معتبر نیست.'];
        }
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Biawin_CRM_DB::table('phones') . " WHERE normalized_phone = %s", $phone));
        if ($existing && (int) $existing->customer_id !== $customer_id) {
            return ['ok' => false, 'message' => 'این شماره از قبل روی پروفایل دیگری ثبت شده است. برای جلوگیری از ادغام اشتباه، شماره از این فرم جابه‌جا نمی‌شود؛ از مسیر «جست‌وجو و اتصال شماره/فاکتور» و تأیید صریح استفاده کنید.'];
        }
        $saved = Biawin_CRM_DB::write('query', $wpdb->prepare(
            "INSERT INTO " . Biawin_CRM_DB::table('phones') . " (customer_id, raw_phone, normalized_phone, phone_status, relation_type, source, is_primary, auto_match_blocked, verified_at, created_at)
             VALUES (%d, %s, %s, %s, %s, 'agent', 0, %d, %s, %s)
             ON DUPLICATE KEY UPDATE phone_status = VALUES(phone_status), relation_type = VALUES(relation_type), auto_match_blocked = VALUES(auto_match_blocked)",
            $customer_id,
            $raw_phone,
            $phone,
            sanitize_key($status),
            sanitize_key($relation),
            in_array($status, ['wrong', 'other_person', 'blocked'], true) ? 1 : 0,
            $status === 'verified_primary' ? Biawin_CRM_DB::now() : null,
            Biawin_CRM_DB::now()
        ));
        if ($saved === false) return ['ok'=>false,'message'=>'شماره در دیتابیس ذخیره نشد.'];
        Biawin_CRM_DB::log('customer', $customer_id, 'phone_added_or_updated', null, ['phone' => $phone, 'status' => $status, 'relation' => $relation]);
        self::ensure_customer_code($customer_id);
        return ['ok' => true, 'message' => 'شماره ثبت شد.'];
    }

    public static function add_phone_to_invoice(int $customer_id, int $invoice_id, string $raw_phone): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $invoice_table = Biawin_CRM_DB::table('invoices');
        $invoice_phones = Biawin_CRM_DB::table('invoice_phones');
        $phones = Biawin_CRM_DB::table('phones');
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoice_table WHERE id=%d AND customer_id=%d", $invoice_id, $customer_id));
        if (!$invoice) return ['ok'=>false,'message'=>'این فاکتور در پروفایل فعلی پیدا نشد.'];
        $normalized = Biawin_CRM_Normalizer::phone($raw_phone);
        if ($normalized === '') return ['ok'=>false,'message'=>'شماره موبایل معتبر وارد کنید.'];

        $other_owner = $wpdb->get_row($wpdb->prepare("SELECT * FROM $phones WHERE normalized_phone=%s LIMIT 1", $normalized));
        if ($other_owner && (int)$other_owner->customer_id !== $customer_id) {
            return ['ok'=>false,'message'=>'این شماره از قبل روی پروفایل دیگری ثبت شده است. برای جلوگیری از جابه‌جایی هویت، از این مسیر منتقل نمی‌شود و نیاز به بررسی سرپرست دارد.'];
        }
        $before = $wpdb->get_col($wpdb->prepare("SELECT normalized_phone FROM $invoice_phones WHERE invoice_id=%d ORDER BY phone_position,id", $invoice_id));
        if (in_array($normalized, array_map('strval',$before), true)) {
            return ['ok'=>true,'message'=>'این شماره از قبل روی فاکتور ثبت شده است.','phone'=>$normalized,'invoice_id'=>$invoice_id,'invoice_code'=>(string)$invoice->invoice_code,'phones'=>$before];
        }

        Biawin_CRM_DB::write('query', 'START TRANSACTION');
        try {
            $position = 1 + (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(phone_position),0) FROM $invoice_phones WHERE invoice_id=%d", $invoice_id));
            if (Biawin_CRM_DB::write('insert', $invoice_phones, [
                'invoice_id'=>$invoice_id,
                'normalized_phone'=>$normalized,
                'phone_position'=>$position,
                'created_at'=>Biawin_CRM_DB::now(),
            ]) === false) throw new RuntimeException('invoice phone insert failed');

            if ($other_owner) {
                $ok=Biawin_CRM_DB::write('update', $phones,[
                    'raw_phone'=>$raw_phone,
                    'source'=>'manual_invoice_phone',
                ],['id'=>(int)$other_owner->id,'customer_id'=>$customer_id]);
                if($ok===false) throw new RuntimeException('phone update failed');
            } else {
                if (Biawin_CRM_DB::write('insert', $phones,[
                    'customer_id'=>$customer_id,
                    'raw_phone'=>$raw_phone,
                    'normalized_phone'=>$normalized,
                    'phone_status'=>'unverified',
                    'relation_type'=>'self',
                    'source'=>'manual_invoice_phone',
                    'is_primary'=>0,
                    'auto_match_blocked'=>0,
                    'created_at'=>Biawin_CRM_DB::now(),
                ])===false) throw new RuntimeException('phone insert failed');
            }
            self::ensure_single_primary_phone($customer_id);
            $after=$wpdb->get_col($wpdb->prepare("SELECT normalized_phone FROM $invoice_phones WHERE invoice_id=%d ORDER BY phone_position,id",$invoice_id));
            $audit=[
                'actor_user_id'=>get_current_user_id(),
                'customer_id'=>$customer_id,
                'invoice_id'=>$invoice_id,
                'invoice_code'=>(string)$invoice->invoice_code,
                'before_invoice_phones'=>array_values(array_map('strval',$before)),
                'added_phone'=>$normalized,
                'after_invoice_phones'=>array_values(array_map('strval',$after)),
                'source'=>'manual_invoice_phone',
                'occurred_at'=>Biawin_CRM_DB::now(),
            ];
            Biawin_CRM_DB::log('invoice',$invoice_id,'invoice_phone_added_by_agent',$before,$audit);
            if(class_exists('Biawin_CRM_Workflow_Service')) Biawin_CRM_Workflow_Service::activity($customer_id,'invoice_phone_added_by_agent','شماره تماس توسط کارشناس به فاکتور اضافه شد.','invoice',$invoice_id,$audit);
            Biawin_CRM_DB::write('query', 'COMMIT');
            return ['ok'=>true,'message'=>'شماره با ثبت کامل سابقه به فاکتور اضافه شد.','phone'=>$normalized,'invoice_id'=>$invoice_id,'invoice_code'=>(string)$invoice->invoice_code,'phones'=>$after];
        } catch(Throwable $e) {
            Biawin_CRM_DB::write('query', 'ROLLBACK');
            return ['ok'=>false,'message'=>'ثبت شماره روی فاکتور انجام نشد؛ هیچ تغییر ناقصی نگه داشته نشد.'];
        }
    }

    public static function update_manual_phone(int $customer_id, int $phone_id, string $raw_phone): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $table = Biawin_CRM_DB::table('phones');
        $phone = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND customer_id=%d", $phone_id, $customer_id));
        if (!$phone) return ['ok'=>false,'message'=>'شماره در این پروفایل پیدا نشد.'];
        if (!in_array((string)$phone->source, ['agent','manual_invoice_bundle'], true)) {
            return ['ok'=>false,'message'=>'شماره‌های مرجع/ایمپورت قابل ویرایش نیستند؛ فقط شماره‌ای که کارشناس دستی ثبت کرده قابل ویرایش است.'];
        }
        $normalized = Biawin_CRM_Normalizer::phone($raw_phone);
        if ($normalized === '') return ['ok'=>false,'message'=>'شماره موبایل معتبر وارد کنید.'];
        $duplicate = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE normalized_phone=%s AND id<>%d LIMIT 1", $normalized, $phone_id));
        if ($duplicate) return ['ok'=>false,'message'=>'این شماره از قبل در CRM وجود دارد. برای اتصال آن از جست‌وجوی شماره و فاکتور استفاده کنید.'];
        $before = ['phone_id'=>$phone_id,'raw_phone'=>$phone->raw_phone,'normalized_phone'=>$phone->normalized_phone,'source'=>$phone->source];
        $ok = Biawin_CRM_DB::write('update', $table, ['raw_phone'=>$raw_phone,'normalized_phone'=>$normalized], ['id'=>$phone_id,'customer_id'=>$customer_id]);
        if ($ok === false) return ['ok'=>false,'message'=>'ویرایش شماره در دیتابیس انجام نشد.'];
        // Keep invoice-phone linkage in sync only for a manually-created bundle that explicitly carried this phone.
        if ((string)$phone->source === 'manual_invoice_bundle' && $phone->normalized_phone !== $normalized) {
            $invoice_phones = Biawin_CRM_DB::table('invoice_phones');
            $invoices = Biawin_CRM_DB::table('invoices');
            $sync = Biawin_CRM_DB::write('query', $wpdb->prepare(
                "UPDATE $invoice_phones ip INNER JOIN $invoices i ON i.id=ip.invoice_id
                 SET ip.normalized_phone=%s
                 WHERE ip.normalized_phone=%s AND i.customer_id=%d AND i.source_import_id IS NULL",
                $normalized, $phone->normalized_phone, $customer_id
            ));
            if ($sync === false) return ['ok'=>false,'message'=>'شماره ویرایش شد اما همگام‌سازی فاکتور دستی ناموفق بود.'];
        }
        Biawin_CRM_DB::log('customer', $customer_id, 'manual_phone_updated', $before, ['phone_id'=>$phone_id,'raw_phone'=>$raw_phone,'normalized_phone'=>$normalized,'source'=>$phone->source]);
        if (class_exists('Biawin_CRM_Workflow_Service')) {
            Biawin_CRM_Workflow_Service::activity($customer_id, 'manual_phone_updated', 'شماره ثبت‌شده توسط کارشناس ویرایش شد.', 'customer', $customer_id, ['phone_id'=>$phone_id,'before'=>$phone->normalized_phone,'after'=>$normalized]);
        }
        return ['ok'=>true,'message'=>'شماره تماس ویرایش شد.','phone'=>$normalized];
    }

    public static function detach_phone_to_new_customer(int $customer_id, int $phone_id): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        if ($customer_id <= 0 || $phone_id <= 0) return ['ok'=>false,'message'=>'شناسه پروفایل یا شماره معتبر نیست.'];

        $phones = Biawin_CRM_DB::table('phones');
        $customers = Biawin_CRM_DB::table('customers');
        $now = Biawin_CRM_DB::now();

        Biawin_CRM_DB::write('query', 'START TRANSACTION');
        try {
            $old_customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE id=%d FOR UPDATE", $customer_id));
            if (!$old_customer) throw new RuntimeException('پروفایل فعلی پیدا نشد.');

            $phone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $phones WHERE id=%d AND customer_id=%d FOR UPDATE", $phone_id, $customer_id));
            if (!$phone) throw new RuntimeException('شماره در این پروفایل پیدا نشد.');

            $duplicate = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $phones WHERE normalized_phone=%s AND id<>%d",
                $phone->normalized_phone,
                $phone_id
            ));
            if ($duplicate > 0) throw new RuntimeException('این شماره قبلاً در رکورد دیگری وجود دارد و قابل جدا کردن نیست.');

            $inserted = Biawin_CRM_DB::write('insert', $customers, [
                'internal_uuid' => wp_generate_uuid4(),
                'display_name' => null,
                'normalized_name' => null,
                'status' => 'phone_detached',
                'total_paid' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$inserted || !$wpdb->insert_id) throw new RuntimeException('ساخت پروفایل مستقل برای شماره انجام نشد.');
            $new_customer_id = (int)$wpdb->insert_id;

            $updated_phone = Biawin_CRM_DB::write('update', $phones, [
                'customer_id' => $new_customer_id,
                'is_primary' => 1,
                'source' => 'detached',
                'auto_match_blocked' => 1,
            ], ['id'=>$phone_id, 'customer_id'=>$customer_id]);
            if ($updated_phone === false) throw new RuntimeException('انتقال شماره به پروفایل جدید ذخیره نشد.');

            Biawin_CRM_DB::write('query', 'COMMIT');
        } catch (Throwable $e) {
            Biawin_CRM_DB::write('query', 'ROLLBACK');
            return ['ok'=>false,'message'=>$e->getMessage() ?: 'جدا کردن شماره انجام نشد.'];
        }

        self::ensure_single_primary_phone($customer_id);
        self::ensure_single_primary_phone($new_customer_id);
        self::recalculate_customer($customer_id);
        self::recalculate_customer($new_customer_id);

        $event = [
            'old_customer_id' => $customer_id,
            'new_customer_id' => $new_customer_id,
            'phone' => (string)$phone->normalized_phone,
            'user_id' => get_current_user_id() ?: 0,
            'created_at' => $now,
        ];
        Biawin_CRM_DB::log('customer', $customer_id, 'PHONE_DETACHED_FROM_CUSTOMER', null, $event);
        if (class_exists('Biawin_CRM_Workflow_Service')) {
            Biawin_CRM_Workflow_Service::activity($customer_id, 'PHONE_DETACHED_FROM_CUSTOMER', 'شماره اشتباه از این پروفایل جدا شد.', 'customer', $new_customer_id, $event);
            Biawin_CRM_Workflow_Service::activity($new_customer_id, 'PHONE_DETACHED_FROM_CUSTOMER', 'پروفایل مستقل از شماره جداشده ساخته شد.', 'customer', $customer_id, $event);
        }

        return ['ok'=>true,'message'=>'شماره از پروفایل فعلی جدا شد و به عنوان مشتری مستقل ثبت شد.','old_customer_id'=>$customer_id,'new_customer_id'=>$new_customer_id,'phone'=>(string)$phone->normalized_phone];
    }

    public static function update_manual_invoice(int $customer_id, int $invoice_id, string $invoice_code, float $amount, string $payment_info = '', string $name = ''): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $invoices = Biawin_CRM_DB::table('invoices');
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$invoices} WHERE id=%d AND customer_id=%d", $invoice_id, $customer_id));
        if (!$invoice) return ['ok'=>false,'message'=>'فاکتور در این پروفایل پیدا نشد.'];
        if ($invoice->source_import_id !== null) return ['ok'=>false,'message'=>'فاکتورهای ایمپورت‌شده از فایل مرجع قابل ویرایش نیستند.'];
        $invoice_code = trim($invoice_code);
        if ($invoice_code === '') return ['ok'=>false,'message'=>'کد فاکتور برای ویرایش فاکتور دستی الزامی است.'];
        if ($amount < 0) $amount = 0;
        $dup=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$invoices} WHERE invoice_code=%s AND id<>%d LIMIT 1",$invoice_code,$invoice_id));
        if($dup) return ['ok'=>false,'message'=>'این کد فاکتور از قبل وجود دارد.'];
        $before=['invoice_id'=>$invoice_id,'invoice_code'=>$invoice->invoice_code,'amount_paid'=>$invoice->amount_paid,'payment_info'=>$invoice->payment_info,'invoice_name'=>$invoice->invoice_name];
        Biawin_CRM_DB::write('query', 'START TRANSACTION');
        try {
            $ok=Biawin_CRM_DB::write('update', $invoices,[
                'invoice_code'=>sanitize_text_field($invoice_code),
                'invoice_name'=>sanitize_text_field($name),
                'amount_paid'=>$amount,
                'payment_info'=>sanitize_textarea_field($payment_info),
                'updated_at'=>Biawin_CRM_DB::now(),
            ],['id'=>$invoice_id,'customer_id'=>$customer_id]);
            if($ok===false) throw new RuntimeException('invoice update failed');
            $payments=Biawin_CRM_DB::table('payments');
            $payment_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$payments} WHERE invoice_id=%d ORDER BY id ASC LIMIT 1",$invoice_id));
            if($payment_id){
                if(Biawin_CRM_DB::write('update', $payments,['amount'=>$amount,'payment_info'=>sanitize_textarea_field($payment_info)],['id'=>$payment_id])===false) throw new RuntimeException('payment update failed');
            } else {
                if(!Biawin_CRM_DB::write('insert', $payments,['invoice_id'=>$invoice_id,'amount'=>$amount,'payment_info'=>sanitize_textarea_field($payment_info),'source_import_id'=>null,'created_at'=>Biawin_CRM_DB::now()])) throw new RuntimeException('payment insert failed');
            }
            Biawin_CRM_DB::write('query', 'COMMIT');
        } catch(Throwable $e){
            Biawin_CRM_DB::write('query', 'ROLLBACK');
            return ['ok'=>false,'message'=>'ویرایش فاکتور ذخیره نشد.'];
        }
        self::recalculate_customer($customer_id);
        Biawin_CRM_DB::log('invoice',$invoice_id,'manual_invoice_updated',$before,['invoice_code'=>$invoice_code,'amount'=>$amount,'payment_info'=>$payment_info,'invoice_name'=>$name,'customer_id'=>$customer_id]);
        if(class_exists('Biawin_CRM_Workflow_Service')) Biawin_CRM_Workflow_Service::activity($customer_id,'manual_invoice_updated','فاکتور ثبت‌شده توسط کارشناس ویرایش شد.','invoice',$invoice_id,['before_code'=>$invoice->invoice_code,'after_code'=>$invoice_code,'amount'=>$amount]);
        return ['ok'=>true,'message'=>'فاکتور دستی ویرایش شد.','invoice_id'=>$invoice_id,'invoice_code'=>$invoice_code];
    }

    public static function create_manual_invoice(int $customer_id, string $invoice_code, float $amount, string $payment_info = '', string $name = ''): array
    {
        global $wpdb;
        Biawin_CRM_DB::assert_customer_mutable($customer_id);
        $invoice_code = trim($invoice_code);
        if ($invoice_code === '' || $amount <= 0) {
            return ['ok' => false, 'message' => 'کد فاکتور و مبلغ معتبر الزامی است.'];
        }
        $table = Biawin_CRM_DB::table('invoices');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE invoice_code=%s", $invoice_code));
        if ($existing) {
            if ((int)$existing->customer_id === $customer_id) {
                return ['ok' => true, 'message' => 'این فاکتور از قبل به همین پروفایل متصل است.'];
            }
            return self::attach_invoice($customer_id, $invoice_code, 'اتصال فاکتور در فرآیند تکمیل مالی');
        }
        $now = Biawin_CRM_DB::now();
        Biawin_CRM_DB::write('insert', $table, [
            'customer_id' => $customer_id,
            'invoice_code' => sanitize_text_field($invoice_code),
            'invoice_name' => sanitize_text_field($name),
            'amount_paid' => $amount,
            'invoice_status' => 'ثبت تکمیلی مالی',
            'payment_info' => sanitize_textarea_field($payment_info),
            'has_input_conflict' => 0,
            'source_import_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $invoice_id = (int)$wpdb->insert_id;
        if (!$invoice_id) {
            return ['ok' => false, 'message' => 'ثبت فاکتور تکمیلی انجام نشد.'];
        }
        Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('payments'), [
            'invoice_id' => $invoice_id,
            'amount' => $amount,
            'payment_info' => sanitize_textarea_field($payment_info),
            'source_import_id' => null,
            'created_at' => $now,
        ]);
        self::recalculate_customer($customer_id);
        Biawin_CRM_DB::log('invoice', $invoice_id, 'manual_financial_invoice_created', null, ['customer_id'=>$customer_id,'invoice_code'=>$invoice_code,'amount'=>$amount]);
        if (class_exists('Biawin_CRM_Workflow_Service')) {
            Biawin_CRM_Workflow_Service::activity($customer_id, 'manual_invoice_created', 'فاکتور تکمیلی مالی ثبت شد.', 'invoice', $invoice_id, ['invoice_code'=>$invoice_code,'amount'=>$amount]);
        }
        return ['ok' => true, 'message' => 'فاکتور تکمیلی ثبت و وضعیت پروفایل دوباره محاسبه شد.'];
    }

    private static function grade(int $customer_id, float $total, ?float $avg, int $open_complaints, float $discrepancy): string
    {
        $score = self::financial_score($total);
        if ($avg !== null) {
            $score += (int) round($avg * 2);
        }
        if ($open_complaints > 0) {
            return 'Disputed';
        }
        if (abs($discrepancy) > 0) {
            return 'Disputed';
        }
        if ($score >= 45) {
            return 'VIP';
        }
        if ($score >= 35) {
            return 'A';
        }
        if ($score >= 25) {
            return 'B';
        }
        if ($score >= 15) {
            return 'C';
        }
        return 'D';
    }

    private static function financial_score(float $total): int
    {
        if ($total >= 1000000000) {
            return 30;
        }
        if ($total >= 300000000) {
            return 24;
        }
        if ($total >= 100000000) {
            return 18;
        }
        if ($total >= 30000000) {
            return 10;
        }
        return 5;
    }

}
