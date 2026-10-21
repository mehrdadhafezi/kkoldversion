<?php
/**
 * C3 UI/adapter integration test on the C2 SQLite/WordPress double.
 * Runs the full C2 suite first (unchanged), then drives the C3 AJAX adapter ops and
 * server renderers through every workflow state. No browser/MySQL claim is made.
 * Run: php -n -d extension=pdo -d extension=pdo_sqlite tests/ownership-c3-ui.php
 */
ob_start();
require __DIR__ . '/ownership-c2.php';
$c2_output = ob_get_clean();
echo $c2_output;
$c2_checks = $checks;

// ---- additional WordPress doubles needed by the presentation layer only ----
class C3Fixture extends SQLFixture { function esc_like($text) { return addcslashes($text, '_%\\'); } }
$c3 = new C3Fixture(); $c3->pdo = $wpdb->pdo; $wpdb = $c3;
function absint($v) { return abs((int)$v); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return (string)$s; }
function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function admin_url($p = '') { return 'https://example.test/wp-admin/' . $p; }
$is_admin = false;
function is_admin() { global $is_admin; return $is_admin; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function checked($a, $b, $echo = true) { return (string)$a === (string)$b ? ' checked="checked"' : ''; }
function selected($a, $b, $echo = true) { return (string)$a === (string)$b ? ' selected="selected"' : ''; }
function number_format_i18n($n, $d = 0) { return number_format((float)$n, $d); }
function get_userdata($id) { return (object)['ID' => $id, 'display_name' => 'User ' . $id]; }
function wp_get_attachment_url($id) { return 'https://example.test/media/' . $id . '.pdf'; }
function get_date_from_gmt($v) { return $v; }
function user_can($id, $cap) { global $role_caps; return in_array($cap, $role_caps[$id] ?? [], true); }
function get_users($args) { global $role_caps; $out = []; foreach (array_keys($role_caps) as $id) $out[] = (object)['ID' => $id, 'display_name' => 'User ' . $id]; return $out; }
$uploads = 1000;
function media_handle_upload($field, $post) { global $uploads; return ++$uploads; }
function is_wp_error($v) { return false; }
$deleted_media = [];
function wp_delete_attachment($id, $force) { global $deleted_media; $deleted_media[] = $id; return true; }
class Biawin_CRM_Auth { static function portal_url($args = []) { return add_query_arg($args, 'https://example.test/portal/'); } }
foreach (['ownership-presenter', 'ownership-ajax', 'ownership-ui'] as $name) require_once __DIR__ . '/../includes/class-biawin-crm-' . $name . '.php';

$map = Biawin_CRM_Ownership_Authorization::role_capabilities();
$role_caps[5] = $map['bcrm_ownership_supervisor'];   // independent second supervisor
$role_caps[6] = ['bcrm_work_cases'];                   // portal user without ownership caps

function op(string $op, array $in = []): array { return Biawin_CRM_Ownership_Ajax::dispatch($op, $in); }
function ok(array $r, string $m): array { check($r['ok'] === true, $m . ': ' . json_encode($r['error'] ?? null, JSON_UNESCAPED_UNICODE)); return $r['data']; }
function fails(array $r, string $code, string $m): void { check($r['ok'] === false && ($r['error']['code'] ?? '') === $code, $m . ': ' . json_encode($r, JSON_UNESCAPED_UNICODE)); }
function page(array $get, int $user): string { global $uid; $uid = $user; $_GET = $get; $_FILES = []; return Biawin_CRM_Ownership_UI::render(); }
function has(string $html, string $needle, string $m): void { check(strpos($html, $needle) !== false, $m); }
function lacks(string $html, string $needle, string $m): void { check(strpos($html, $needle) === false, $m); }
function v(int $case): int { return version($case); }
function evidence(int $case, string $kind = 'claimant_receipt', bool $conflicting = false): array { $_FILES = ['file' => ['name' => 'r.pdf']]; $r = op('add_evidence', ['case_id' => $case, 'version' => v($case), 'kind' => $kind, 'note' => 'رسید', 'conflicting' => $conflicting ? '1' : '']); $_FILES = []; return $r; }

// ---------------------------------------------------------------- labels coverage
$required_events = ['CASE_CREATED','CASE_DRAFT_UPDATED','DRAFT_ASSET_ADDED','DRAFT_ASSET_REMOVED','CASE_SUBMITTED','CASE_ASSIGNED','REVIEW_STARTED','MORE_EVIDENCE_REQUESTED','CONTACT_RECORDED','EVIDENCE_ADDED','CASE_AMENDED','ASSET_ADDED','REQUESTED_TRANSFER_PLAN_CHANGED','FINAL_PLAN_CONFIRMED','MANUAL_RISK_ESCALATION','TRANSFER_APPROVED','CLAIM_REJECTED','CLAIM_WITHDRAWN','SUPERVISOR_APPROVED','SUPERVISOR_REJECTED','INVOICE_OWNER_CHANGED','PHONE_OWNER_CHANGED','INVOICE_PHONE_CHANGED','SERVICE_OWNER_ALIGNED','TRANSFER_COMMITTED','PROFILE_RECALCULATED','DEFICIENCY_TRIGGERED','PROFILE_HOLD_RELEASED','CASE_EXECUTION_FAILED','CASE_CLOSED','CASE_CANCELLED'];
foreach ($required_events as $event) check(isset(Biawin_CRM_Ownership_Presenter::EVENTS[$event]), 'event label ' . $event);
// Every event type literal the backend emits through Audit::event()/transition() has a Persian label.
$statuses = ['DRAFT','SUBMITTED','ASSIGNED','UNDER_REVIEW','AWAITING_EVIDENCE','AWAITING_CUSTOMER_CONFIRMATION','AWAITING_SUPERVISOR_APPROVAL','DECIDED','CLOSED','CANCELLED','INVOICE','PHONE'];
foreach (glob(__DIR__ . '/../includes/class-biawin-crm-{ownership-*,customer-hold-service,invoice-phone-service}.php', GLOB_BRACE) as $file) {
    foreach (file($file) as $line) {
        if (strpos($line, 'Audit::event(') === false && strpos($line, 'self::transition(') === false) continue;
        preg_match_all("/(?:Audit::event\([^,]+,|self::transition\([^,]+,[^,]+,)\s*([^,]+)/", $line, $calls);
        foreach ($calls[1] as $expr) {
            preg_match_all("/'([A-Z][A-Z_]+)'/", $expr, $names);
            foreach ($names[1] as $event) if (!in_array($event, $statuses, true)) check(isset(Biawin_CRM_Ownership_Presenter::EVENTS[$event]), 'backend event has a Persian label: ' . $event . ' (' . basename($file) . ')');
        }
    }
}
foreach (['DRAFT','SUBMITTED','ASSIGNED','UNDER_REVIEW','AWAITING_EVIDENCE','AWAITING_CUSTOMER_CONFIRMATION','AWAITING_SUPERVISOR_APPROVAL','DECIDED','CLOSED','CANCELLED'] as $s) check(isset(Biawin_CRM_Ownership_Presenter::STATUSES[$s]), 'status label ' . $s);
foreach (['NOT_STARTED','COMMITTED','FINALIZATION_FAILED','FINALIZED'] as $s) check(isset(Biawin_CRM_Ownership_Presenter::EXECUTION[$s]), 'execution label ' . $s);
check(array_keys(Biawin_CRM_Ownership_Presenter::PHONE_MODES) === ['KEEP_CURRENT_PHONE','USE_TARGET_PROFILE_PHONE','CLAIM_CURRENT_PHONE','PROPOSE_NEW_PHONE'], 'phone mode enum values unchanged');
check(Biawin_CRM_Ownership_Presenter::PHONE_MODES['KEEP_CURRENT_PHONE'] === 'حفظ شماره فعلی فاکتور' && Biawin_CRM_Ownership_Presenter::PHONE_MODES['PROPOSE_NEW_PHONE'] === 'ثبت شماره جدید برای این فاکتور', 'approved Persian phone mode labels');
check(array_keys(Biawin_CRM_Ownership_Presenter::EVIDENCE) === ['ownership_evidence','claimant_receipt','consent_document'], 'evidence kinds match backend');
foreach (['DISPUTED_PHONE','PHONE_TRANSFER','MULTIPLE_INVOICES','SCOPE_EXPANDED','PLAN_OVERRIDE','CONFLICTING_EVIDENCE','OTP_DELIVERY_FAILED','OTP_FAILED','OTP_EXPIRED','MANUAL_ESCALATION','PRIOR_HIGH_RISK'] as $r) check(isset(Biawin_CRM_Ownership_Presenter::RISK_REASONS[$r]), 'risk reason label ' . $r);

// ---------------------------------------------------------------- workspace visibility
$uid = 1; $t = Biawin_CRM_Ownership_Presenter::workspaces(); check(isset($t['request'], $t['mine']) && !isset($t['queue']) && !isset($t['supervisor']), 'requester sees request + my cases only');
$uid = 2; $t = Biawin_CRM_Ownership_Presenter::workspaces(); check(isset($t['queue']) && !isset($t['supervisor']), 'specialist sees queue but not supervisor queue');
$uid = 3; $t = Biawin_CRM_Ownership_Presenter::workspaces(); check(isset($t['queue'], $t['supervisor']), 'supervisor sees supervisor queue');
$html = page([], 6); has($html, 'دسترسی مجاز نیست', 'portal user without ownership caps gets permission-denied state');
$html = page(['own_tab' => 'queue'], 1); lacks($html, 'صف کارشناس تطبیق مالکیت</h2>', 'requester cannot open specialist queue via URL');
$html = page(['own_tab' => 'mine'], 1); has($html, 'dir="rtl"', 'workspace is RTL'); has($html, 'هنوز درخواستی ثبت نکرده‌اید', 'empty queue state');

// ---------------------------------------------------------------- lookups
$pair = setup_pair(2); [$s, $t, $p, $ids] = $pair;
$code0 = row('invoices', $ids[0])->invoice_code; $phone = row('phones', $p)->normalized_phone;
$uid = 1; $found = ok(op('lookup', ['query' => $phone]), 'phone lookup');
check(count($found['invoices']) === 2 && count($found['phones']) === 1, 'shared phone surfaces both invoices but selects none');
check($found['invoices'][0]['owner']['id'] === $s && $found['invoices'][0]['services'] === ['Service'], 'lookup carries owner and linked services');
fails(op('lookup', ['query' => 'x']), 'domain', 'short lookup rejected');
$uid = 6; fails(op('lookup', ['query' => $phone]), 'permission', 'lookup needs ownership capability');
$deny_profiles = [$t]; $uid = 1;
$found = ok(op('claimant_search', ['query' => 'Target']), 'claimant search');
check(!array_filter($found['profiles'], static function ($x) use ($t) { return $x['id'] === $t; }), 'claimant search hides inaccessible profiles');
$deny_profiles = [$s];
$summary = ok(op('profile_summary', ['customer' => $s]), 'restricted summary');
check($summary['restricted'] === true && !isset($summary['total_paid']), 'source financials hidden without profile access');
$deny_profiles = [];
$summary = ok(op('profile_summary', ['customer' => $s]), 'full summary');
check($summary['restricted'] === false && (float)$summary['total_paid'] === 2000000.0 && count($summary['phones']) === 1, 'full summary for accessible profile');
check(ok(op('normalize_phone', ['phone' => '۰۹۱۲۱۲۳۴۵۶۷']), 'normalize')['valid'] === true, 'normalize delegates to backend normalizer');

// ---------------------------------------------------------------- NORMAL flow through adapters
$before = owner_digest($pair);
$uid = 1; $d = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'پرداخت توسط مدعی', 'note' => str_repeat('توضیح طولانی ', 80)]), 'create draft');
$case = $d['case_id'];
fails(op('create_draft', ['source' => $s, 'claimant' => $s]), 'input', 'same source/claimant rejected');
fails(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'INVOICE', 'asset_id' => $ids[0]]), 'domain', 'invoice without explicit phone plan rejected by backend');
$d = ok(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'INVOICE', 'asset_id' => $ids[0], 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'add invoice');
check($d['version'] === v($case) && $d['status'] === 'DRAFT', 'adapter returns fresh authoritative version');
fails(op('add_draft_asset', ['case_id' => $case, 'version' => v($case) - 1, 'asset_type' => 'INVOICE', 'asset_id' => $ids[1], 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'stale', 'stale version reported as stale');
$html = page(['own_case' => $case], 1);
has($html, 'ثبت درخواست به معنی انتقال مالکیت نیست.', 'claim != transfer banner on draft'); has($html, 'data-own-op="submit"', 'requester can submit own draft');
has($html, 'data-own-op="remove_draft_asset"', 'draft asset removable'); lacks($html, 'data-own-op="start_review"', 'requester sees no specialist action');
has($html, 'data-own-picker-mode="draft"', 'draft asset picker present');
$uid = 1; ok(evidence($case), 'requester evidence upload in draft');
ok(op('submit', ['case_id' => $case, 'version' => v($case)]), 'submit');
check(owner_digest($pair) === $before, 'draft + submit through UI adapters change no canonical ownership');
$html = page(['own_case' => $case], 1); has($html, 'فقط خواندنی است', 'hold text shown after submit'); lacks($html, 'data-own-op="assign"', 'requester cannot assign');
has($html, 'پرونده منتظر تخصیص', 'SUBMITTED requester explanation');
$uid = 2; fails(op('assign', ['case_id' => $case, 'version' => v($case), 'specialist' => 2]), 'permission', 'specialist cannot assign');
$uid = 1; fails(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'permission', 'requester cannot review');
$html = page(['own_tab' => 'queue'], 3); has($html, 'data-own-groups="SUBMITTED active"', 'supervisor queue lists SUBMITTED');
$html = page(['own_case' => $case], 3); has($html, 'data-own-op="assign"', 'supervisor sees assignment'); has($html, '<option value="2"', 'specialist candidates listed');
$uid = 3; ok(op('assign', ['case_id' => $case, 'version' => v($case), 'specialist' => 2]), 'assign');
$html = page(['own_case' => $case], 2); has($html, 'data-own-op="start_review"', 'ASSIGNED specialist can start review');
$uid = 4; fails(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'permission', 'unassigned specialist blocked');
$uid = 2; ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'start review');
ok(op('request_evidence', ['case_id' => $case, 'version' => v($case), 'note' => 'رسید بانکی', 'customer_confirmation' => '0']), 'request evidence');
check(row('ownership_cases', $case)->status === 'AWAITING_EVIDENCE', 'AWAITING_EVIDENCE');
$html = page(['own_case' => $case], 2); has($html, 'بازگشت به بررسی', 'resume review from AWAITING_EVIDENCE'); has($html, 'فقط در وضعیت «در حال بررسی»', 'decision unavailability explained');
$html = page(['own_case' => $case], 1); has($html, 'data-own-op="add_evidence"', 'requester can add evidence while awaiting');
$uid = 2; ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'back to review');
ok(op('request_evidence', ['case_id' => $case, 'version' => v($case), 'note' => 'تأیید مشتری', 'customer_confirmation' => '1']), 'request customer confirmation');
check(row('ownership_cases', $case)->status === 'AWAITING_CUSTOMER_CONFIRMATION', 'AWAITING_CUSTOMER_CONFIRMATION');
ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'back to review 2');
ok(op('record_contact', ['case_id' => $case, 'version' => v($case), 'result' => 'claimant_confirmed', 'note' => 'تماس']), 'record contact');
ok(op('set_final_plan', ['case_id' => $case, 'version' => v($case), 'asset_row' => Biawin_CRM_Ownership_Repository::assets($case)[0]->id, 'action' => 'TRANSFER', 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'confirm unchanged final plan');
check(row('ownership_cases', $case)->risk_level === 'NORMAL', 'unchanged plan keeps backend NORMAL risk');
$html = page(['own_case' => $case], 2);
has($html, 'data-own-op="decide"', 'UNDER_REVIEW specialist can decide'); has($html, 'رد درخواست', 'reject available'); has($html, 'ثبت انصراف', 'withdraw available');
lacks($html, 'name="approve" value="1"', 'specialist sees no supervisor approval'); has($html, 'ریسک عادی', 'NORMAL risk shown as text');
ok(op('decide', ['case_id' => $case, 'version' => v($case), 'decision' => 'TRANSFER_APPROVED', 'method' => 'CLAIMANT_RECEIPT', 'note' => 'رسید معتبر']), 'approve NORMAL');
check(row('ownership_cases', $case)->status === 'DECIDED' && owner_digest($pair) === $before, 'DECIDED but nothing transferred yet');
$html = page(['own_case' => $case], 2); has($html, 'data-own-op="execute"', 'DECIDED shows execute'); has($html, 'فاکتورهای منتقل‌شونده', 'decision summary before execution');
lacks($html, 'data-own-op="resume"', 'no resume before commit');
$html = page(['own_case' => $case], 1); lacks($html, 'data-own-op="execute"', 'requester cannot execute');
$uid = 1; fails(op('execute', ['case_id' => $case, 'version' => v($case)]), 'permission', 'requester execute denied by backend');
$uid = 2; ok(op('execute', ['case_id' => $case, 'version' => v($case)]), 'execute');
check(row('ownership_cases', $case)->status === 'CLOSED' && (int)row('invoices', $ids[0])->customer_id === $t && (int)row('invoices', $ids[1])->customer_id === $s, 'execution moved exactly the selected invoice');
$html = page(['own_case' => $case], 2);
lacks($html, 'data-own-op=', 'CLOSED case has no mutation controls'); has($html, 'فقط‌خواندنی', 'CLOSED read-only'); has($html, 'تغییر مالک فاکتور', 'timeline shows owner change'); has($html, 'ثبت قطعی انتقال مالکیت', 'timeline shows commit');
$html = page(['own_tab' => 'history'], 1); has($html, 'data-own-groups="all closed"', 'requester history lists closed case');

// ---------------------------------------------------------------- HIGH_RISK: multiple invoices + shared phone claim, supervisor rejection, SoD
$pair = setup_pair(2); [$s, $t, $p, $ids] = $pair; $before = owner_digest($pair);
$uid = 1; $case = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'hr draft')['case_id'];
foreach ($ids as $inv) ok(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'INVOICE', 'asset_id' => $inv, 'phone_resolution' => 'CLAIM_CURRENT_PHONE']), 'hr invoice');
ok(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'PHONE', 'asset_id' => $p]), 'hr phone');
ok(op('submit', ['case_id' => $case, 'version' => v($case)]), 'hr submit');
$uid = 3; ok(op('assign', ['case_id' => $case, 'version' => v($case), 'specialist' => 3]), 'assign to supervisor-as-reviewer');
ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'hr review'); ok(evidence($case), 'hr evidence');
ok(evidence($case, 'ownership_evidence', true), 'conflicting evidence');
ok(op('evaluate_risk', ['case_id' => $case, 'version' => v($case), 'escalation_reason' => 'بررسی دستی']), 'manual escalation');
$reasons = Biawin_CRM_Ownership_Repository::decode(row('ownership_cases', $case)->risk_reasons_json);
check(row('ownership_cases', $case)->risk_level === 'HIGH_RISK' && count($reasons) >= 4, 'backend produced HIGH_RISK with multiple reasons');
$html = page(['own_case' => $case], 3);
foreach ($reasons as $reason) has($html, Biawin_CRM_Ownership_Presenter::RISK_REASONS[$reason], 'every backend risk reason rendered: ' . $reason);
has($html, 'پرریسک (High Risk)', 'HIGH_RISK has a text label'); has($html, 'مدرک متعارض', 'conflicting evidence flagged');
has($html, 'data-own-picker-mode="amend"', 'amendment available in review'); has($html, 'id="own-otp"', 'OTP section for explicitly claimed phone');
$uid = 1; $html = page(['own_case' => $case], 1); lacks($html, 'بررسی دستی', 'requester does not see internal risk reasons'); lacks($html, 'id="own-otp"', 'requester sees no OTP section');
$html = page(['own_tab' => 'queue'], 3); has($html, 'مدرک متعارض', 'queue shows conflicting indicator'); has($html, 'توقف پروفایل فعال', 'queue shows hold state');
$uid = 3; ok(op('decide', ['case_id' => $case, 'version' => v($case), 'decision' => 'TRANSFER_APPROVED', 'method' => 'CLAIMANT_RECEIPT', 'note' => 'پیشنهاد']), 'hr propose');
check(row('ownership_cases', $case)->status === 'AWAITING_SUPERVISOR_APPROVAL' && owner_digest($pair) === $before, 'HIGH_RISK waits for supervisor, nothing moved');
$html = page(['own_case' => $case], 3); lacks($html, 'name="approve" value="1"', 'decision maker sees no supervisor approval'); has($html, 'تفکیک وظایف', 'SoD explained to decision maker');
$html = page(['own_tab' => 'supervisor'], 3); has($html, 'شما تصمیم‌گیرنده‌اید', 'supervisor queue marks own decision');
$uid = 3; fails(op('supervisor_decision', ['case_id' => $case, 'version' => v($case), 'approve' => '1', 'note' => 'self']), 'domain', 'decision maker cannot self-approve High Risk');
$uid = 2; fails(op('supervisor_decision', ['case_id' => $case, 'version' => v($case), 'approve' => '1', 'note' => 'x']), 'permission', 'specialist cannot supervisor-approve');
$html = page(['own_case' => $case], 5); has($html, 'name="approve" value="1"', 'independent supervisor sees approval'); has($html, 'بازگرداندن به بررسی', 'supervisor rejection available');
$uid = 5; fails(op('supervisor_decision', ['case_id' => $case, 'version' => v($case), 'approve' => '0', 'note' => '']), 'domain', 'supervisor rejection requires reason');
ok(op('supervisor_decision', ['case_id' => $case, 'version' => v($case), 'approve' => '0', 'note' => 'مدرک ناکافی']), 'supervisor rejection');
check(row('ownership_cases', $case)->status === 'UNDER_REVIEW', 'supervisor rejection returns to UNDER_REVIEW');
$uid = 3; ok(op('decide', ['case_id' => $case, 'version' => v($case), 'decision' => 'TRANSFER_APPROVED', 'method' => 'CLAIMANT_RECEIPT', 'note' => 'دوباره']), 'hr re-propose');
$uid = 5; ok(op('supervisor_decision', ['case_id' => $case, 'version' => v($case), 'approve' => '1', 'note' => 'تأیید']), 'independent approval');
// Finalization failure: ownership committed, resume only.
$wpdb->fail_customer = $s;
$uid = 3; $r = op('execute', ['case_id' => $case, 'version' => v($case)]);
check($r['ok'] === false && $r['error']['fresh']['execution'] === 'FINALIZATION_FAILED', 'post-commit failure reported with fresh FINALIZATION_FAILED state');
check((int)row('invoices', $ids[0])->customer_id === $t && (int)row('phones', $p)->customer_id === $t, 'ownership was committed before finalization failed');
$html = page(['own_case' => $case], 3);
has($html, 'ادامه نهایی‌سازی', 'resume action offered'); has($html, 'data-own-op="resume"', 'resume form'); lacks($html, 'data-own-op="execute"', 'execute not offered after commit');
lacks($html, 'Retry Transfer', 'no Retry Transfer'); lacks($html, 'تکرار انتقال</button>', 'no repeat-transfer CTA'); has($html, 'نهایی‌سازی پس از انتقال کامل نشد', 'finalization failure explained');
$html = page(['own_tab' => 'queue'], 3); has($html, 'finfail', 'queue groups finalization failures');
$uid = 3; fails(op('execute', ['case_id' => $case, 'version' => v($case)]), 'domain', 'execute cannot run twice');
ok(op('resume', ['case_id' => $case, 'version' => v($case)]), 'resume finalization');
check(row('ownership_cases', $case)->status === 'CLOSED' && row('ownership_cases', $case)->execution_state === 'FINALIZED', 'resume closes the case');

// ---------------------------------------------------------------- OTP, cancellation, withdrawal, rejection
$pair = setup_pair(); [$s, $t, $p, $ids] = $pair;
$uid = 1; $case = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'otp draft')['case_id'];
ok(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'PHONE', 'asset_id' => $p]), 'phone only');
ok(op('submit', ['case_id' => $case, 'version' => v($case)]), 'otp submit');
$uid = 3; ok(op('assign', ['case_id' => $case, 'version' => v($case), 'specialist' => 2]), 'otp assign');
$uid = 2; ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'otp review');
$sms_adapter = true; $sms = [];
$sent = ok(op('otp_issue', ['case_id' => $case, 'version' => v($case), 'phone_asset' => $p, 'purpose' => 'CONFIRM_PHONE_CONTROL']), 'otp issue');
$code = end($sms)[1];
$html = page(['own_case' => $case], 2);
lacks($html, (string)$code, 'plaintext OTP never rendered'); lacks($html, '$2y$', 'OTP hash never rendered'); lacks($html, 'otp_hash', 'OTP hash field never rendered');
has($html, 'ارسال‌شده — در انتظار ورود کد', 'OTP sent state'); has($html, 'به‌تنهایی مالکیت', 'OTP is not transfer');
$uid = 2; $verified = ok(op('otp_verify', ['case_id' => $case, 'version' => v($case), 'challenge' => $sent['challenge_id'], 'phone_asset' => $p, 'purpose' => 'CONFIRM_PHONE_CONTROL', 'code' => (string)$code]), 'otp verify');
check($verified['verified'] === true && (int)row('phones', $p)->customer_id === $s, 'OTP success transfers nothing');
$uid = 3; ok(op('cancel', ['case_id' => $case, 'version' => v($case), 'reason' => 'لغو توسط سرپرست']), 'supervisor cancel');
$html = page(['own_case' => $case], 1); lacks($html, 'data-own-op=', 'CANCELLED has no mutation controls'); has($html, 'لغو شده', 'CANCELLED explained');
$uid = 1; $case = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'draft to cancel')['case_id'];
fails(op('cancel', ['case_id' => $case, 'version' => v($case), 'reason' => '']), 'domain', 'cancel requires reason');
ok(op('cancel', ['case_id' => $case, 'version' => v($case), 'reason' => 'اشتباه']), 'requester cancels own draft');
check(row('ownership_cases', $case)->status === 'CANCELLED', 'DRAFT -> CANCELLED');
foreach (['CLAIM_REJECTED', 'CLAIM_WITHDRAWN'] as $decision) {
    $uid = 1; $case = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'draft ' . $decision)['case_id'];
    ok(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'INVOICE', 'asset_id' => $ids[0], 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'asset ' . $decision);
    ok(op('submit', ['case_id' => $case, 'version' => v($case)]), 'submit ' . $decision);
    $uid = 3; ok(op('assign', ['case_id' => $case, 'version' => v($case), 'specialist' => 2]), 'assign ' . $decision);
    $uid = 2; ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'review ' . $decision);
    ok(op('decide', ['case_id' => $case, 'version' => v($case), 'decision' => $decision, 'method' => 'DOCUMENT_REVIEW', 'note' => 'n']), $decision);
    check(row('ownership_cases', $case)->status === 'CLOSED' && (int)row('invoices', $ids[0])->customer_id === $s, $decision . ' closes without transfer');
    $html = page(['own_case' => $case], 2); has($html, Biawin_CRM_Ownership_Presenter::DECISIONS[$decision], 'closed decision shown');
}

// ---------------------------------------------------------------- evidence upload failure cleans the orphan media
$uid = 1; $case = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'evidence draft')['case_id'];
$deleted_media = [];
$_FILES = ['file' => ['name' => 'x.pdf']]; $r = op('add_evidence', ['case_id' => $case, 'version' => v($case) - 1, 'kind' => 'claimant_receipt']); $_FILES = [];
check($r['ok'] === false && count($deleted_media) === 1, 'failed evidence registration removes the unregistered upload');
fails(op('add_evidence', ['case_id' => $case, 'version' => v($case), 'kind' => 'bogus']), 'input', 'unknown evidence kind rejected before upload');
$uid = 6; $_FILES = ['file' => ['name' => 'x.pdf']]; $r = op('add_evidence', ['case_id' => $case, 'version' => v($case), 'kind' => 'claimant_receipt']); $_FILES = [];
check($r['ok'] === false && $r['error']['code'] === 'permission', 'unauthorized user cannot upload evidence to a case');

// ---------------------------------------------------------------- error translation + portal nav injection
$e = Biawin_CRM_Ownership_Presenter::error(new RuntimeException('Ownership action is not allowed in state CLOSED'));
check($e['code'] === 'state' && strpos($e['message'], 'بسته‌شده') !== false, 'state errors translated');
$e = Biawin_CRM_Ownership_Presenter::error(new RuntimeException('Customer is read-only during ownership review.'));
check($e['message'] === 'این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است.', 'hold error uses approved wording');
$uid = 1; $shell = '<div><nav class="bcrm-shell-nav"><a href="#">x</a></nav></div>';
$out = Biawin_CRM_Ownership_UI::portal_nav($shell, 'biawin_crm_portal');
check($out === $shell, 'nav untouched for users without bcrm_work_cases');
$role_caps[1][] = 'bcrm_work_cases';
$out = Biawin_CRM_Ownership_UI::portal_nav($shell, 'biawin_crm_portal');
check(substr_count($out, 'data-bcrm-view="ownership"') === 1 && strpos($out, '</nav>') > strpos($out, 'ownership'), 'ownership nav link injected once');
check(Biawin_CRM_Ownership_UI::portal_nav($out, 'biawin_crm_portal') === $out, 'nav injection idempotent');
check(Biawin_CRM_Ownership_UI::portal_nav($shell, 'other_tag') === $shell, 'other shortcodes untouched');
$_GET = ['bcrm_view' => 'customers']; check(Biawin_CRM_Ownership_UI::portal_intercept(false, 'biawin_crm_portal') === false, 'other portal views not intercepted');
$_GET = ['bcrm_view' => 'ownership']; check(strpos((string)Biawin_CRM_Ownership_UI::portal_intercept(false, 'biawin_crm_portal'), 'data-bcrm-own') !== false, 'ownership view intercepted');
$uid = 6; $role_caps[6] = ['bcrm_work_cases']; check(Biawin_CRM_Ownership_UI::portal_nav($shell, 'biawin_crm_portal') === $shell, 'no nav link without ownership capability');

echo 'PASS: ' . ($checks - $c2_checks) . " C3 UI/adapter checks (SQLite/WordPress doubles; no browser, WordPress runtime or MySQL claim).\n";
