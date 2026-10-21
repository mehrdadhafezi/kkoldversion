<?php
if (!defined('ABSPATH')) { exit; }

/**
 * C3 thin transport adapters for Ownership Reconciliation.
 *
 * Each command op validates the session/nonce and presentation input shape,
 * then makes exactly one call into the approved C2 application command
 * (Workflow / OTP / Transfer). The adapter never transitions state, never
 * writes ownership tables, never computes risk and never re-implements
 * authorization: the backend command derives the actor from the session and
 * enforces capability, case scope, state, SoD and optimistic version.
 *
 * Read ops only serialize data that existing portal reads already expose
 * (Customer_Service::search_related, Workflow::get) plus read-only lock/hold
 * hints, so the UI can explain why a mutation is unavailable.
 */
class Biawin_CRM_Ownership_Ajax
{
    const ACTION = 'bcrm_ownership';
    const NONCE = 'bcrm_ownership_ui';
    const EVIDENCE_UPLOAD_MIMES = [
        'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'pdf' => 'application/pdf', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
    ];

    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [__CLASS__, 'deny_guest']);
    }

    public static function deny_guest(): void
    {
        wp_send_json_error(['code' => 'permission', 'message' => 'برای این عملیات باید وارد پرتال شوید.'], 401);
    }

    public static function handle(): void
    {
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['code' => 'nonce', 'message' => 'نشست امنیتی منقضی شده است. صفحه را تازه‌سازی کنید.'], 403);
        }
        if (!get_current_user_id() || (bool)get_user_meta(get_current_user_id(), 'bcrm_disabled', true)) {
            wp_send_json_error(['code' => 'permission', 'message' => 'حساب شما برای این عملیات فعال نیست.'], 403);
        }
        $result = self::dispatch(sanitize_key((string)($_POST['op'] ?? '')), wp_unslash($_POST));
        if ($result['ok']) wp_send_json_success($result['data']);
        $status = ['permission' => 403, 'stale' => 409, 'state' => 409, 'active_ownership_case' => 409, 'ownership_locked' => 423, 'ownership_hold' => 423, 'not_found' => 404, 'input' => 400][$result['error']['code']] ?? 422;
        wp_send_json_error($result['error'], $status);
    }

    private static function int(array $in, string $key): int { return isset($in[$key]) ? absint($in[$key]) : 0; }
    private static function str(array $in, string $key): string { return isset($in[$key]) && is_scalar($in[$key]) ? (string)$in[$key] : ''; }
    private static function flag(array $in, string $key): bool { return !empty($in[$key]) && (string)$in[$key] !== '0' && (string)$in[$key] !== 'false'; }

    private static function input_error(string $message): array
    {
        return ['ok' => false, 'error' => ['code' => 'input', 'message' => $message, 'detail' => '']];
    }

    /** Fresh authoritative version after a command, read through the scoped backend get(). */
    private static function fresh(int $id): array
    {
        try {
            $case = Biawin_CRM_Ownership_Workflow::get($id)['case'];
            return ['case_id' => $id, 'version' => (int)$case->lock_version, 'status' => (string)$case->status, 'execution' => (string)$case->execution_state];
        } catch (Throwable $e) {
            return ['case_id' => $id, 'version' => null];
        }
    }

    /** Asset input exactly as the backend asset_data() reads it; the backend validates every field. */
    private static function asset_input(array $in): array
    {
        $asset = ['asset_type' => strtoupper(self::str($in, 'asset_type')), 'asset_id' => self::int($in, 'asset_id')];
        $action = strtoupper(self::str($in, 'requested_action'));
        if ($action !== '') $asset['requested_action'] = $action;
        if ($asset['asset_type'] === 'INVOICE') {
            $asset['phone_resolution'] = strtoupper(self::str($in, 'phone_resolution'));
            $asset['phone_id'] = self::int($in, 'phone_id') ?: null;
            $asset['phone'] = sanitize_text_field(self::str($in, 'phone')) ?: null;
        }
        return $asset;
    }

    public static function dispatch(string $op, array $in): array
    {
        $id = self::int($in, 'case_id');
        $version = self::int($in, 'version');
        try {
            switch ($op) {
                // ---- Requester commands ----
                case 'create_draft':
                    $source = self::int($in, 'source');
                    $claimant = self::int($in, 'claimant');
                    if (!$source || !$claimant) return self::input_error('پروفایل مبدأ و مقصد را انتخاب کنید.');
                    if ($source === $claimant) return self::input_error('پروفایل مبدأ و مقصد باید متفاوت باشند.');
                    $new = Biawin_CRM_Ownership_Workflow::create_draft($source, $claimant, self::str($in, 'note'), self::str($in, 'reason'));
                    return ['ok' => true, 'data' => self::fresh($new)];
                case 'update_draft':
                    Biawin_CRM_Ownership_Workflow::update_draft($id, $version, self::str($in, 'note'), self::str($in, 'reason'));
                    break;
                case 'add_draft_asset':
                    Biawin_CRM_Ownership_Workflow::add_draft_asset($id, $version, self::asset_input($in));
                    break;
                case 'remove_draft_asset':
                    Biawin_CRM_Ownership_Workflow::remove_draft_asset($id, $version, self::int($in, 'asset_row'));
                    break;
                case 'submit':
                    Biawin_CRM_Ownership_Workflow::submit($id, $version);
                    break;
                case 'cancel':
                    Biawin_CRM_Ownership_Workflow::cancel($id, $version, self::str($in, 'reason'));
                    break;
                case 'add_evidence':
                    return self::add_evidence($id, $version, $in);
                // ---- Specialist / supervisor commands ----
                case 'assign':
                    Biawin_CRM_Ownership_Workflow::assign($id, $version, self::int($in, 'specialist'));
                    break;
                case 'start_review':
                    Biawin_CRM_Ownership_Workflow::start_review($id, $version);
                    break;
                case 'request_evidence':
                    Biawin_CRM_Ownership_Workflow::request_evidence($id, $version, self::str($in, 'note'), self::flag($in, 'customer_confirmation'));
                    break;
                case 'record_contact':
                    Biawin_CRM_Ownership_Workflow::record_contact($id, $version, sanitize_key(self::str($in, 'result')), self::str($in, 'note'));
                    break;
                case 'amend':
                    $assets = json_decode(self::str($in, 'assets'), true);
                    if (!is_array($assets) || !$assets) return self::input_error('حداقل یک دارایی برای اصلاحیه انتخاب کنید.');
                    Biawin_CRM_Ownership_Workflow::amend($id, $version, array_map([__CLASS__, 'asset_input'], array_values(array_filter($assets, 'is_array'))), self::str($in, 'reason'));
                    break;
                case 'set_final_plan':
                    Biawin_CRM_Ownership_Workflow::set_final_plan($id, $version, self::int($in, 'asset_row'), [
                        'action' => strtoupper(self::str($in, 'action')) ?: 'TRANSFER',
                        'phone_resolution' => strtoupper(self::str($in, 'phone_resolution')),
                        'phone_id' => self::int($in, 'phone_id') ?: null,
                        'phone' => sanitize_text_field(self::str($in, 'phone')) ?: null,
                    ], self::str($in, 'reason'));
                    break;
                case 'evaluate_risk':
                    Biawin_CRM_Ownership_Workflow::evaluate_risk($id, $version, self::str($in, 'escalation_reason'));
                    break;
                case 'decide':
                    Biawin_CRM_Ownership_Workflow::decide($id, $version, strtoupper(self::str($in, 'decision')), strtoupper(self::str($in, 'method')), self::str($in, 'note'));
                    break;
                case 'supervisor_decision':
                    $choice = self::str($in, 'approve');
                    if ($choice !== '1' && $choice !== '0') return self::input_error('تأیید یا بازگرداندن را مشخص کنید.');
                    Biawin_CRM_Ownership_Workflow::supervisor_decision($id, $version, $choice === '1', self::str($in, 'note'));
                    break;
                case 'otp_issue':
                    $challenge = Biawin_CRM_Ownership_OTP::issue($id, $version, self::int($in, 'phone_asset'), strtoupper(self::str($in, 'purpose')) ?: 'CONFIRM_PHONE_CONTROL');
                    return ['ok' => true, 'data' => self::fresh($id) + ['challenge_id' => (int)$challenge]];
                case 'otp_verify':
                    $code = preg_replace('/\D+/', '', self::str($in, 'code'));
                    $verified = Biawin_CRM_Ownership_OTP::verify($id, $version, self::int($in, 'challenge'), self::int($in, 'phone_asset'), strtoupper(self::str($in, 'purpose')), (string)$code);
                    return ['ok' => true, 'data' => self::fresh($id) + ['verified' => (bool)$verified]];
                case 'execute':
                    Biawin_CRM_Ownership_Transfer_Service::execute($id, $version);
                    break;
                case 'resume':
                    Biawin_CRM_Ownership_Transfer_Service::resume($id, $version);
                    break;
                // ---- Read-only lookups ----
                case 'lookup':
                    return ['ok' => true, 'data' => self::lookup(sanitize_text_field(self::str($in, 'query')))];
                case 'claimant_search':
                    return ['ok' => true, 'data' => self::claimant_search(sanitize_text_field(self::str($in, 'query')))];
                case 'profile_summary':
                    return ['ok' => true, 'data' => self::profile_summary(self::int($in, 'customer'), $id)];
                case 'legacy_status':
                    self::require_ownership_reader();
                    $codes = json_decode(self::str($in, 'codes'), true);
                    return ['ok' => true, 'data' => Biawin_CRM_Ownership_Legacy_Bridge::status(self::int($in, 'customer'), is_array($codes) ? array_map('sanitize_text_field', array_filter($codes, 'is_string')) : [])];
                case 'normalize_phone':
                    $phone = Biawin_CRM_Normalizer::phone(sanitize_text_field(self::str($in, 'phone')));
                    return ['ok' => true, 'data' => ['normalized' => $phone, 'valid' => $phone !== '']];
                default:
                    return self::input_error('عملیات ناشناخته است.');
            }
            return ['ok' => true, 'data' => self::fresh($id)];
        } catch (Throwable $e) {
            // Always report failure with a fresh version so the UI can reload authoritative state
            // (e.g. execute committed ownership but finalization failed).
            return ['ok' => false, 'error' => Biawin_CRM_Ownership_Presenter::error($e) + ($id ? ['fresh' => self::fresh($id)] : [])];
        }
    }

    /** Same media pattern as the existing financial-evidence upload: WordPress media + backend registration. */
    private static function add_evidence(int $id, int $version, array $in): array
    {
        $kind = sanitize_key(self::str($in, 'kind'));
        if (!isset(Biawin_CRM_Ownership_Presenter::EVIDENCE[$kind])) return self::input_error('نوع مدرک را انتخاب کنید.');
        if (empty($_FILES['file']['name'])) return self::input_error('فایل مدرک انتخاب نشده است.');
        // Authorize reading the case before accepting any upload bytes; add_evidence() re-checks scope.
        Biawin_CRM_Ownership_Workflow::get($id);
        if (!current_user_can('submit_ownership_claim') && !current_user_can('manage_ownership_evidence')) {
            return ['ok' => false, 'error' => ['code' => 'permission', 'message' => 'دسترسی ثبت مدرک ندارید.', 'detail' => '']];
        }
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $media = media_handle_upload('file', 0, [], ['test_form' => false, 'mimes' => self::EVIDENCE_UPLOAD_MIMES]);
        if (is_wp_error($media)) return self::input_error('بارگذاری فایل ناموفق بود: ' . $media->get_error_message());
        try {
            Biawin_CRM_Ownership_Workflow::add_evidence($id, $version, (int)$media, $kind, self::str($in, 'note'), self::flag($in, 'conflicting'));
        } catch (Throwable $e) {
            // Registration failed: the media was never linked to the case, so remove the orphan upload.
            wp_delete_attachment((int)$media, true);
            throw $e;
        }
        return ['ok' => true, 'data' => self::fresh($id) + ['media_id' => (int)$media]];
    }

    private static function require_ownership_reader(): void
    {
        if (!current_user_can('submit_ownership_claim') && !current_user_can('view_ownership_cases')) {
            throw new RuntimeException('Ownership permission denied: submit_ownership_claim');
        }
    }

    /** Source asset search: exactly the existing related-search scope, plus read-only ownership hints. */
    public static function lookup(string $query): array
    {
        global $wpdb;
        self::require_ownership_reader();
        if (function_exists('mb_strlen') ? mb_strlen($query) < 2 : strlen($query) < 2) throw new RuntimeException('Query too short.');
        $found = Biawin_CRM_Customer_Service::search_related($query);
        $owner = static function ($row) { return ['id' => (int)$row->customer_id, 'name' => (string)($row->display_name ?: 'بدون نام'), 'code' => (string)($row->customer_code ?? '')]; };
        $locked = static function (string $type, int $asset) use ($wpdb) {
            return (bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('ownership_locks') . ' WHERE entity_type=%s AND entity_id=%d AND released_at IS NULL LIMIT 1', $type, $asset));
        };
        $out = ['invoices' => [], 'phones' => []];
        foreach ((array)($found['invoices'] ?? []) as $invoice) {
            if (empty($invoice->customer_id)) continue;
            $current = null; $ambiguous = false;
            try { $current = Biawin_CRM_Invoice_Phone_Service::current($invoice); } catch (Throwable $e) { $ambiguous = true; }
            $services = $wpdb->get_col($wpdb->prepare('SELECT service_name FROM ' . Biawin_CRM_DB::table('services') . ' WHERE invoice_id=%d ORDER BY id', (int)$invoice->id));
            $out['invoices'][] = [
                'id' => (int)$invoice->id, 'code' => (string)$invoice->invoice_code, 'amount' => (float)$invoice->amount_paid,
                'owner' => $owner($invoice), 'phones' => array_values(array_map('strval', (array)($invoice->phones ?? []))),
                'canonical' => (string)($invoice->canonical_phone_normalized ?? ''), 'current_phone' => (string)$current, 'ambiguous' => $ambiguous,
                'services' => array_values(array_map('strval', (array)$services)),
                'locked' => $locked('INVOICE', (int)$invoice->id),
                'active_case' => Biawin_CRM_Ownership_Repository::conflicting(0, 'INVOICE', (int)$invoice->id),
            ];
        }
        foreach ((array)($found['phones'] ?? []) as $phone) {
            if (empty($phone->customer_id)) continue;
            $out['phones'][] = [
                'id' => (int)$phone->id, 'phone' => (string)$phone->normalized_phone, 'owner' => $owner($phone),
                'status' => (string)($phone->phone_status ?? ''), 'primary' => !empty($phone->is_primary),
                'locked' => $locked('PHONE', (int)$phone->id),
                'active_case' => Biawin_CRM_Ownership_Repository::conflicting(0, 'PHONE', (int)$phone->id),
            ];
        }
        $out['phone_usage_count'] = (int)($found['phone_usage_count'] ?? 0);
        return $out;
    }

    /**
     * Re-validate wizard prefill hints (claimant, source, asset_type, asset_id).
     * Every value is a hint: it is re-read from the database, checked against the
     * current user's access and dropped with a visible notice when stale or not
     * permitted. Nothing is created, added or submitted here.
     */
    public static function prefill(array $hints): array
    {
        global $wpdb;
        $out = ['claimant' => null, 'source' => null, 'asset' => null, 'notices' => []];
        if (!current_user_can('submit_ownership_claim')) return $out;
        $claimant = absint($hints['claimant'] ?? 0);
        if ($claimant) {
            $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $claimant));
            if ($exists && Biawin_CRM_Frontend::can_access_customer($claimant)) $out['claimant'] = self::profile_summary($claimant);
            else $out['notices'][] = 'پروفایل مقصد پیشنهادی وجود ندارد یا به آن دسترسی ندارید؛ نادیده گرفته شد.';
        }
        $type = strtoupper(sanitize_key((string)($hints['asset_type'] ?? '')));
        $asset_id = absint($hints['asset_id'] ?? 0);
        $source_hint = absint($hints['source'] ?? 0);
        $owner = 0;
        if ($asset_id || $type !== '') {
            $row = null;
            if ($type === 'INVOICE' && $asset_id) $row = $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,invoice_code FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE id=%d', $asset_id));
            if ($type === 'PHONE' && $asset_id) $row = $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE id=%d', $asset_id));
            if (!$row || empty($row->customer_id)) {
                $out['notices'][] = 'دارایی پیشنهادی معتبر نیست یا مالک فعلی ندارد؛ نادیده گرفته شد.';
            } elseif ($out['claimant'] && (int)$row->customer_id === $claimant) {
                $out['notices'][] = 'دارایی پیشنهادی هم‌اکنون در پروفایل مقصد است؛ نادیده گرفته شد.';
            } else {
                $found = self::lookup($type === 'INVOICE' ? (string)$row->invoice_code : (string)$row->normalized_phone);
                foreach ($found[$type === 'INVOICE' ? 'invoices' : 'phones'] as $item) if ($item['id'] === (int)$row->id) $out['asset'] = $item + ['asset_type' => $type];
                if ($out['asset']) {
                    $owner = (int)$row->customer_id;
                    if ($source_hint && $source_hint !== $owner) $out['notices'][] = 'مالک فعلی دارایی با مبدأ پیشنهادی متفاوت است؛ مبدأ از اطلاعات فعلی سیستم تعیین شد.';
                } else {
                    $out['notices'][] = 'دارایی پیشنهادی در جست‌وجوی فعلی یافت نشد؛ نادیده گرفته شد.';
                }
            }
        }
        if (!$owner && $source_hint && !$out['asset'] && $asset_id === 0) {
            $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $source_hint));
            if ($exists) $owner = $source_hint; else $out['notices'][] = 'پروفایل مبدأ پیشنهادی وجود ندارد؛ نادیده گرفته شد.';
        }
        if ($owner && $out['claimant'] && $owner === $claimant) {
            $out['notices'][] = 'مبدأ و مقصد پیشنهادی یکسان‌اند؛ مبدأ نادیده گرفته شد.';
            $owner = 0;
            $out['asset'] = null;
        }
        if ($owner) $out['source'] = self::profile_summary($owner);
        return $out;
    }

    /** Claimant candidates: only profiles the user can already access (create_draft re-checks). */
    public static function claimant_search(string $query): array
    {
        global $wpdb;
        Biawin_CRM_Ownership_Authorization::require_cap('submit_ownership_claim');
        if (function_exists('mb_strlen') ? mb_strlen($query) < 2 : strlen($query) < 2) throw new RuntimeException('Query too short.');
        $c = Biawin_CRM_DB::table('customers');
        $p = Biawin_CRM_DB::table('phones');
        $phone = Biawin_CRM_Normalizer::phone($query);
        $like = '%' . $wpdb->esc_like(trim($query)) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT c.id FROM $c c LEFT JOIN $p p ON p.customer_id=c.id WHERE c.display_name LIKE %s OR c.customer_code=%s OR (%s<>'' AND p.normalized_phone=%s) ORDER BY c.id DESC LIMIT 40", $like, trim($query), $phone, $phone));
        $out = [];
        foreach ((array)$rows as $row) {
            if (!Biawin_CRM_Frontend::can_access_customer((int)$row->id)) continue;
            $out[] = self::profile_summary((int)$row->id);
            if (count($out) >= 10) break;
        }
        return ['profiles' => $out];
    }

    /**
     * Profile card. Full financial/phone detail only for profiles the user can
     * already open, or for the two profiles of a case the user reviews.
     */
    public static function profile_summary(int $customer, int $case_id = 0): array
    {
        global $wpdb;
        self::require_ownership_reader();
        $row = Biawin_CRM_Ownership_Repository::row('customers', $customer);
        $full = Biawin_CRM_Frontend::can_access_customer($customer);
        if (!$full && $case_id) {
            $case = Biawin_CRM_Ownership_Workflow::get($case_id)['case'];
            $full = Biawin_CRM_Ownership_Presenter::is_reviewer($case) && in_array($customer, [(int)$case->current_owner_customer_id, (int)$case->claimant_customer_id], true);
        }
        $out = ['id' => $customer, 'name' => (string)($row->display_name ?: 'بدون نام'), 'code' => (string)($row->customer_code ?? ''), 'restricted' => !$full];
        $holds = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT h.case_id,h.created_at,c.case_code FROM ' . Biawin_CRM_DB::table('customer_holds') . ' h LEFT JOIN ' . Biawin_CRM_DB::table('ownership_cases') . " c ON c.id=h.case_id WHERE h.customer_id=%d AND h.status='ACTIVE' ORDER BY h.id", $customer));
        $out['holds'] = array_map(static function ($h) { return ['case_id' => (int)$h->case_id, 'case_code' => (string)$h->case_code, 'created_at' => (string)$h->created_at]; }, $holds);
        if (!$full) return $out;
        $out += [
            'completeness' => (string)($row->profile_completeness ?? ''), 'total_paid' => (float)($row->total_paid ?? 0),
            'declared_paid' => (float)($row->declared_paid ?? 0), 'discrepancy' => (float)($row->discrepancy_amount ?? 0),
        ];
        $out['phones'] = array_map(static function ($p) {
            return ['id' => (int)$p->id, 'phone' => (string)$p->normalized_phone, 'status' => (string)$p->phone_status, 'primary' => !empty($p->is_primary)];
        }, Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,normalized_phone,phone_status,is_primary FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d ORDER BY is_primary DESC,id', $customer)));
        $out['locked_invoices'] = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Biawin_CRM_DB::table('ownership_locks') . " WHERE entity_type='INVOICE' AND customer_id=%d AND released_at IS NULL", $customer));
        return $out;
    }
}
