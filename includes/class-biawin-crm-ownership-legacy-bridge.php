<?php
if (!defined('ABSPATH')) { exit; }

/**
 * C3.8 compatibility boundary between the legacy customer/profile Attach UI
 * and Ownership Reconciliation.
 *
 * The C2 write guard correctly rejects ordinary mutations of locked assets and
 * held profiles, but the frozen legacy AJAX/admin-post callbacks do not catch
 * that rejection, so it surfaced as a WordPress critical error. This class
 * runs on the same actions at priority 1 (before the legacy priority-10
 * callbacks) and:
 *
 * 1. For Attach requests it reads the lock/hold state and, when the backend is
 *    known to refuse the operation, answers with a structured failure plus a
 *    path into the formal Ownership request. The legacy handler then never
 *    runs, so nothing is written. Unlocked requests return untouched and the
 *    legacy handler runs exactly as before.
 * 2. For every covered legacy mutation it installs a request-scoped exception
 *    handler, so an Ownership rejection the pre-check could not foresee still
 *    becomes a controlled failure instead of an uncaught Throwable. Unknown
 *    errors stay visible as errors.
 *
 * It never performs the Attach, never changes ownership/locks/holds and never
 * evaluates approval or risk. The backend guard remains authoritative.
 */
class Biawin_CRM_Ownership_Legacy_Bridge
{
    /** Legacy frontend AJAX mutations that can reach the Ownership write guard. */
    const AJAX_ACTIONS = [
        'bcrm_attach_invoice', 'bcrm_attach_bundle', 'bcrm_add_phone', 'bcrm_add_invoice_phone', 'bcrm_manual_bundle',
        'bcrm_save_interaction', 'bcrm_set_journey', 'bcrm_select_services', 'bcrm_create_complaint', 'bcrm_update_service_case',
        'bcrm_phone_verification', 'bcrm_edit_manual_phone', 'bcrm_detach_customer_phone', 'bcrm_edit_manual_invoice', 'bcrm_assign_service_case_front',
    ];
    /** Legacy frontend admin-post mutations (non-AJAX forms). */
    const POST_ACTIONS = [
        'bcrm_front_attach_invoice', 'bcrm_front_add_phone', 'bcrm_front_invoice_phone_bundle', 'bcrm_front_manual_invoice',
        'bcrm_front_interaction', 'bcrm_front_journey', 'bcrm_front_services', 'bcrm_front_complaint', 'bcrm_front_complaint_resolution',
        'bcrm_front_service_case', 'bcrm_front_financial_evidence',
    ];
    const LOCK_MESSAGE = 'تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است.';
    const PHONE_LOCK_MESSAGE = 'تغییر مالکیت این شماره فقط از مسیر تطبیق مالکیت مجاز است.';
    const HOLD_MESSAGE = 'این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است.';
    const CTA_LABEL = 'ثبت درخواست تطبیق مالکیت';
    const STATUS = ['ownership_locked' => 423, 'ownership_hold' => 423, 'active_ownership_case' => 409, 'ownership_busy' => 503, 'unexpected' => 500];

    private static $mode = null;
    /** Attach request being guarded: [target, invoice code, phones]; used to enrich safety-net failures. */
    private static $attach = null;

    public static function init(): void
    {
        foreach (self::AJAX_ACTIONS as $action) add_action('wp_ajax_' . $action, [__CLASS__, 'guard_ajax'], 1);
        foreach (self::POST_ACTIONS as $action) add_action('admin_post_' . $action, [__CLASS__, 'guard_post'], 1);
    }

    private static function action(string $prefix): string
    {
        $hook = function_exists('current_action') ? (string)current_action() : '';
        return strpos($hook, $prefix) === 0 ? substr($hook, strlen($prefix)) : '';
    }

    public static function guard_ajax(): void
    {
        self::install('ajax');
        $action = self::action('wp_ajax_');
        if (!in_array($action, ['bcrm_attach_invoice', 'bcrm_attach_bundle'], true)) return;
        if (!wp_verify_nonce((string)($_POST['nonce'] ?? ''), 'bcrm_front_ajax')) return; // the legacy handler rejects it
        $phones = $action === 'bcrm_attach_bundle' ? [(string)wp_unslash($_POST['phone'] ?? ''), (string)wp_unslash($_POST['searched_query'] ?? '')] : [];
        self::$attach = [absint($_POST['customer_id'] ?? 0), sanitize_text_field(wp_unslash($_POST['invoice_code'] ?? '')), $phones];
        $verdict = self::attach_precheck(self::$attach[0], self::$attach[1], $phones);
        if ($verdict) wp_send_json_error($verdict, self::STATUS[$verdict['code']]);
    }

    public static function guard_post(): void
    {
        self::install('post');
        if (self::action('admin_post_') !== 'bcrm_front_attach_invoice') return;
        if (!wp_verify_nonce((string)($_POST['_wpnonce'] ?? ''), 'bcrm_front_attach_invoice')) return;
        self::$attach = [absint($_POST['customer_id'] ?? 0), sanitize_text_field(wp_unslash($_POST['invoice_code'] ?? '')), []];
        $verdict = self::attach_precheck(self::$attach[0], self::$attach[1], []);
        if ($verdict) self::die_page($verdict);
    }

    /**
     * Read-only prediction of the backend guard for an ordinary Attach. Returns
     * null whenever the legacy handler should decide (unknown invoice, no
     * access, already attached, or nothing protected).
     */
    public static function attach_precheck(int $target, string $invoice_code, array $phones = []): ?array
    {
        global $wpdb;
        if (!$target || $invoice_code === '' || !is_user_logged_in() || !current_user_can('bcrm_work_cases')) return null;
        if (!Biawin_CRM_Frontend::can_access_customer($target)) return null;
        $invoice = $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,invoice_code FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE invoice_code=%s', trim($invoice_code)));
        if (!$invoice) return null;
        $source = (int)$invoice->customer_id;
        if ($source === $target) return null;
        $asset = ['type' => 'INVOICE', 'id' => (int)$invoice->id, 'label' => (string)$invoice->invoice_code];
        if (self::locked('INVOICE', (int)$invoice->id)) return self::payload('ownership_locked', $asset, $source, $target);
        foreach ($phones as $raw) {
            $normalized = Biawin_CRM_Normalizer::phone((string)$raw);
            if ($normalized === '') continue;
            $phone = $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE normalized_phone=%s', $normalized));
            if ($phone && (int)$phone->customer_id !== $target && self::locked('PHONE', (int)$phone->id)) {
                return self::payload('ownership_locked', ['type' => 'PHONE', 'id' => (int)$phone->id, 'label' => (string)$phone->normalized_phone], (int)$phone->customer_id, $target);
            }
        }
        if (Biawin_CRM_Customer_Hold_Service::is_held($target) || ($source && Biawin_CRM_Customer_Hold_Service::is_held($source))) return self::payload('ownership_hold', $asset, $source, $target);
        return null;
    }

    private static function describe_blocked_attach(string $code, int $target, string $invoice_code, array $phones): ?array
    {
        global $wpdb;
        if (!$target || $invoice_code === '') return null;
        $invoice = $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,invoice_code FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE invoice_code=%s', trim($invoice_code)));
        if (!$invoice) return null;
        $asset = ['type' => 'INVOICE', 'id' => (int)$invoice->id, 'label' => (string)$invoice->invoice_code];
        $owner = (int)$invoice->customer_id;
        if ($code === 'ownership_locked' && !self::locked('INVOICE', (int)$invoice->id)) {
            foreach ($phones as $raw) {
                $normalized = Biawin_CRM_Normalizer::phone((string)$raw);
                $phone = $normalized === '' ? null : $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE normalized_phone=%s', $normalized));
                if ($phone && self::locked('PHONE', (int)$phone->id)) { $asset = ['type' => 'PHONE', 'id' => (int)$phone->id, 'label' => (string)$phone->normalized_phone]; $owner = (int)$phone->customer_id; break; }
            }
        }
        return self::payload($code, $asset, $owner, $target);
    }

    public static function locked(string $type, int $id): bool
    {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('ownership_locks') . ' WHERE entity_type=%s AND entity_id=%d AND released_at IS NULL LIMIT 1', $type, $id));
    }

    /** Active (submitted, not closed/cancelled) case containing the asset. Distinct from a persistent lock. */
    public static function active_case(string $type, int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT c.id,c.case_code FROM ' . Biawin_CRM_DB::table('ownership_case_assets') . ' a JOIN ' . Biawin_CRM_DB::table('ownership_cases') . " c ON c.id=a.case_id WHERE a.asset_type=%s AND a.asset_id=%d AND c.status NOT IN ('DRAFT','CLOSED','CANCELLED') ORDER BY c.id DESC LIMIT 1", $type, $id));
        return $row ? ['id' => (int)$row->id, 'code' => (string)$row->case_code] : null;
    }

    private static function profile(int $id): ?array
    {
        if (!$id) return null;
        $labels = Biawin_CRM_Ownership_Presenter::customer_labels([$id]);
        return $labels[$id] ?? ['id' => $id, 'name' => '#' . $id, 'code' => ''];
    }

    /** Ownership request link with hint parameters; the wizard re-validates every hint. */
    public static function request_url(int $claimant, int $source, string $type, int $asset): string
    {
        return Biawin_CRM_Auth::portal_url(array_filter([
            'bcrm_view' => Biawin_CRM_Ownership_UI::VIEW, 'own_tab' => 'request',
            'claimant' => $claimant ?: null, 'source' => $source ?: null, 'asset_type' => $asset ? $type : null, 'asset_id' => $asset ?: null,
        ]));
    }

    /** Structured, user-safe failure body (the response stays a failure). */
    public static function payload(string $code, array $asset, int $source, int $target): array
    {
        $message = $code === 'ownership_hold' ? self::HOLD_MESSAGE : ($asset['type'] === 'PHONE' ? self::PHONE_LOCK_MESSAGE : self::LOCK_MESSAGE);
        $case = self::active_case($asset['type'], (int)$asset['id']);
        $can_request = current_user_can('submit_ownership_claim');
        $text = '🔒 ' . $message;
        if ($case) $text .= ' این دارایی هم‌اکنون در پرونده تطبیق فعال ' . $case['code'] . ' است.';
        elseif ($can_request) $text .= ' برای ادامه «' . self::CTA_LABEL . '» را انتخاب کنید.';
        return [
            'code' => $code,
            'message' => $text,
            'ownership' => [
                'asset_type' => $asset['type'], 'asset_id' => (int)$asset['id'], 'asset_label' => $asset['label'],
                'locked' => self::locked($asset['type'], (int)$asset['id']),
                'held' => array_values(array_filter([$source, $target], static function ($id) { return $id && Biawin_CRM_Customer_Hold_Service::is_held($id); })),
                'active_case' => $case,
                'source' => self::profile($source), 'claimant' => self::profile($target),
                'request_url' => $can_request && !$case ? self::request_url($target, $source, $asset['type'], (int)$asset['id']) : null,
                'action_label' => $can_request && !$case ? self::CTA_LABEL : null,
            ],
        ];
    }

    /** Map an exception escaping a legacy handler to a presentation code. Unknown errors stay "unexpected". */
    public static function classify(Throwable $e): array
    {
        $raw = $e->getMessage();
        if ($raw === 'Asset ownership is locked; use Ownership Reconciliation.') return ['code' => 'ownership_locked', 'message' => '🔒 ' . self::LOCK_MESSAGE . ' (این دارایی با قفل مالکیت محافظت می‌شود؛ تغییر آن فقط با «' . self::CTA_LABEL . '» ممکن است.)'];
        if ($raw === 'Customer is read-only during ownership review.') return ['code' => 'ownership_hold', 'message' => '🔒 ' . self::HOLD_MESSAGE];
        if ($raw === 'Ownership safety lock unavailable; retry.') return ['code' => 'ownership_busy', 'message' => 'سیستم در حال انجام عملیات تطبیق مالکیت دیگری است؛ چند لحظه بعد دوباره تلاش کنید.'];
        return ['code' => 'unexpected', 'message' => 'خطای غیرمنتظره رخ داد و عملیات انجام نشد. در صورت تکرار با پشتیبانی تماس بگیرید.', 'detail' => $raw];
    }

    private static function install(string $mode): void
    {
        if (self::$mode !== null) return;
        self::$mode = $mode;
        set_exception_handler([__CLASS__, 'on_exception']);
    }

    /** Request-scoped safety net for exceptions escaping a covered legacy handler. */
    public static function on_exception(Throwable $e): void
    {
        // An uncommitted legacy transaction must not survive into shutdown code.
        if (class_exists('Biawin_CRM_Ownership_Repository') && Biawin_CRM_Ownership_Repository::in_transaction()) {
            try { Biawin_CRM_Ownership_Repository::legacy_transaction('ROLLBACK'); } catch (Throwable $ignored) {}
        }
        $result = self::classify($e);
        // The guard may have just materialized a completion lock (C2 sync_completed); describe the
        // blocked Attach the same way the pre-check would, with the Ownership request path.
        if (in_array($result['code'], ['ownership_locked', 'ownership_hold'], true) && self::$attach) {
            $enriched = self::describe_blocked_attach($result['code'], self::$attach[0], self::$attach[1], self::$attach[2]);
            if ($enriched) $result = $enriched;
        }
        if ($result['code'] === 'unexpected') error_log('[biawin-crm] legacy handler failure: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (self::$mode === 'post') { self::die_page($result); return; }
        wp_send_json_error($result, self::STATUS[$result['code']]);
    }

    private static function die_page(array $result): void
    {
        $html = '<div dir="rtl" style="text-align:right"><p>' . esc_html($result['message']) . '</p>';
        if (!empty($result['ownership']['request_url'])) $html .= '<p><a class="button button-primary" href="' . esc_url($result['ownership']['request_url']) . '">' . esc_html(self::CTA_LABEL) . '</a></p>';
        wp_die($html . '</div>', 'تطبیق مالکیت', ['response' => self::STATUS[$result['code']], 'back_link' => true]);
    }

    /** Read-only lock/hold/case status for invoices the legacy related-search already showed. */
    public static function status(int $target, array $codes): array
    {
        global $wpdb;
        if (!Biawin_CRM_Frontend::can_access_customer($target)) throw new RuntimeException('Ownership permission denied: customer access');
        $out = ['target_held' => Biawin_CRM_Customer_Hold_Service::is_held($target), 'invoices' => []];
        foreach (array_slice(array_unique(array_filter(array_map('strval', $codes))), 0, 60) as $code) {
            $invoice = $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,invoice_code FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE invoice_code=%s', $code));
            if (!$invoice || (int)$invoice->customer_id === $target) continue;
            $source = (int)$invoice->customer_id;
            $locked = self::locked('INVOICE', (int)$invoice->id);
            $case = self::active_case('INVOICE', (int)$invoice->id);
            $held = $out['target_held'] || ($source && Biawin_CRM_Customer_Hold_Service::is_held($source));
            // A completed source profile may acquire its persistent lock on the first protected write
            // (C2 completion sync). Only a hint: the legacy button stays enabled and the guard decides.
            $complete = !$locked && $source && (string)$wpdb->get_var($wpdb->prepare('SELECT profile_completeness FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $source)) === 'complete';
            if (!$locked && !$case && !$held && !$complete) continue;
            $can = current_user_can('submit_ownership_claim') && !$case;
            $out['invoices'][$code] = [
                'invoice_id' => (int)$invoice->id, 'locked' => $locked, 'held' => $held, 'active_case' => $case, 'maybe_locked' => $complete && !$held && !$case,
                'source' => self::profile($source),
                'message' => $locked ? self::LOCK_MESSAGE : ($held ? self::HOLD_MESSAGE : ($complete ? 'پروفایل مبدأ تکمیل‌شده است؛ ممکن است مالکیت این فاکتور با قفل مالکیت محافظت شود و تغییر آن فقط از مسیر تطبیق مالکیت مجاز باشد.' : '')),
                'request_url' => $can ? self::request_url($target, $source, 'INVOICE', (int)$invoice->id) : null,
            ];
        }
        return $out;
    }
}
