<?php
/**
 * C3.8 legacy Attach ↔ Ownership bridge tests on the C2 SQLite/WordPress double.
 * Runs the unchanged C2 and C3 suites first, then exercises the bridge.
 * Run: php -n -d extension=pdo -d extension=pdo_sqlite tests/ownership-c3-legacy-bridge.php
 */
ob_start();
require __DIR__ . '/ownership-c3-ui.php';
echo ob_get_clean();
$before_c38 = $checks;

class C38Exit extends Exception { public $body; public $status; function __construct($body, $status) { parent::__construct('exit'); $this->body = $body; $this->status = $status; } }
$current_action = '';
function current_action() { global $current_action; return $current_action; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid-' . $action ? 1 : false; }
function wp_unslash($v) { return $v; }
function wp_send_json_error($data = null, $status = null) { throw new C38Exit(['success' => false, 'data' => $data], $status); }
function wp_die($html = '', $title = '', $args = []) { throw new C38Exit(['die' => $html, 'args' => $args], $args['response'] ?? 500); }
require_once __DIR__ . '/../includes/class-biawin-crm-ownership-legacy-bridge.php';
$previous_handler = set_exception_handler(null); set_exception_handler($previous_handler);

foreach ([1, 2, 3, 5] as $u) if (!in_array('bcrm_work_cases', $role_caps[$u], true)) $role_caps[$u][] = 'bcrm_work_cases';
function reset_bridge(): void { $r = new ReflectionProperty('Biawin_CRM_Ownership_Legacy_Bridge', 'mode'); $r->setAccessible(true); $r->setValue(null, null); set_exception_handler(null); }
/** Emulates admin-ajax: bridge at priority 1, then the legacy callback only if the bridge did not end the request. */
function legacy_ajax(string $action, array $post, callable $legacy): array {
    global $current_action; reset_bridge(); $_POST = $post; $current_action = 'wp_ajax_' . $action;
    try { Biawin_CRM_Ownership_Legacy_Bridge::guard_ajax(); }
    catch (C38Exit $exit) { set_exception_handler(null); return ['ended_by' => 'bridge', 'status' => $exit->status, 'body' => $exit->body]; }
    try { $result = $legacy(); set_exception_handler(null); return ['ended_by' => 'legacy', 'result' => $result]; }
    catch (Throwable $e) {
        try { Biawin_CRM_Ownership_Legacy_Bridge::on_exception($e); } catch (C38Exit $exit) { set_exception_handler(null); return ['ended_by' => 'safety_net', 'status' => $exit->status, 'body' => $exit->body]; }
        set_exception_handler(null); return ['ended_by' => 'uncaught'];
    }
}
function lock_invoice(int $invoice, int $owner): void { Biawin_CRM_Ownership_Repository::atomic(static function () use ($invoice, $owner) { Biawin_CRM_Ownership_Lock_Service::create('INVOICE', $invoice, $owner); }); }
function state_of(int $invoice): string { global $wpdb; return json_encode([$wpdb->get_results('SELECT id,customer_id FROM test_bcrm_invoices WHERE id=' . $invoice), $wpdb->get_results('SELECT id,customer_id FROM test_bcrm_services WHERE invoice_id=' . $invoice), $wpdb->get_results('SELECT id,customer_id,released_at FROM test_bcrm_ownership_locks WHERE entity_id=' . $invoice . " AND entity_type='INVOICE'")]); }
function cases_count(): int { global $wpdb; return (int)$wpdb->get_var('SELECT COUNT(*) FROM test_bcrm_ownership_cases'); }

// ---------------------------------------------------------------- 1-9: locked invoice + ordinary Attach
$pair = setup_pair(2); [$s, $t, $p, $ids] = $pair;
$inv = row('invoices', $ids[0]); lock_invoice((int)$inv->id, $s);
$snapshot = state_of((int)$inv->id);
$uid = 1;
$legacy = static function () use ($t, $inv) { return Biawin_CRM_Customer_Service::attach_invoice($t, $inv->invoice_code, 'اتصال فقط فاکتور توسط کارشناس'); };
// Backend still rejects the direct mutation (guard untouched).
denied($legacy, 'ordinary Attach of a locked invoice is still rejected by the C2 guard');
check(state_of((int)$inv->id) === $snapshot, 'rejected Attach changed no invoice/service/lock row');
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $t, 'invoice_code' => $inv->invoice_code], $legacy);
check($r['ended_by'] === 'bridge', 'bridge answers before the legacy handler runs');
check($r['status'] === 423 && $r['body']['success'] === false, 'response stays an HTTP failure (423)');
$data = $r['body']['data'];
check($data['code'] === 'ownership_locked', 'structured ownership_locked code');
check(strpos($data['message'], 'تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است.') !== false, 'required Persian lock message');
check($data['ownership']['locked'] === true && $data['ownership']['asset_type'] === 'INVOICE' && $data['ownership']['asset_id'] === (int)$inv->id && $data['ownership']['asset_label'] === $inv->invoice_code, 'asset identity and lock state returned');
check($data['ownership']['source']['id'] === $s && $data['ownership']['claimant']['id'] === $t, 'source owner and claimant returned');
$url = (string)$data['ownership']['request_url'];
check(strpos($url, 'bcrm_view=ownership') !== false && strpos($url, 'own_tab=request') !== false, 'CTA points to the formal request workspace');
check(strpos($url, 'claimant=' . $t) !== false && strpos($url, 'source=' . $s) !== false && strpos($url, 'asset_type=INVOICE') !== false && strpos($url, 'asset_id=' . $inv->id) !== false, 'CTA carries source/claimant/asset hints');
check($data['ownership']['action_label'] === 'ثبت درخواست تطبیق مالکیت', 'CTA label');
check($data['ownership']['active_case'] === null, 'persistent lock is not reported as an active case');
check(state_of((int)$inv->id) === $snapshot, 'bridged Attach changed no invoice/service/lock row');
// Without the pre-check (e.g. an unforeseen path) the safety net still yields a controlled failure.
reset_bridge(); global $current_action; $current_action = 'wp_ajax_bcrm_attach_invoice';
try { $legacy(); check(false, 'expected guard exception'); } catch (RuntimeException $e) {
    try { Biawin_CRM_Ownership_Legacy_Bridge::guard_ajax(); } catch (C38Exit $x) {}
    try { Biawin_CRM_Ownership_Legacy_Bridge::on_exception($e); check(false, 'safety net must end the request'); }
    catch (C38Exit $x) { check($x->status === 423 && $x->body['data']['code'] === 'ownership_locked', 'safety net turns an escaped lock exception into ownership_locked'); }
}
reset_bridge();
check(state_of((int)$inv->id) === $snapshot, 'lock still active after every rejected attempt');
// Bundle attach and invalid nonce.
$r = legacy_ajax('bcrm_attach_bundle', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $t, 'invoice_code' => $inv->invoice_code, 'phone' => row('phones', $p)->normalized_phone], static function () { return 'legacy'; });
check($r['ended_by'] === 'bridge' && $r['body']['data']['code'] === 'ownership_locked', 'bundle Attach of a locked invoice is bridged too');
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'bad', 'customer_id' => $t, 'invoice_code' => $inv->invoice_code], static function () { return 'legacy-nonce-check'; });
check($r['ended_by'] === 'legacy' && $r['result'] === 'legacy-nonce-check', 'invalid nonce is left to the legacy handler');
// admin-post (non-AJAX form) path.
reset_bridge(); $_POST = ['_wpnonce' => 'valid-bcrm_front_attach_invoice', 'customer_id' => $t, 'invoice_code' => $inv->invoice_code]; $current_action = 'admin_post_bcrm_front_attach_invoice';
try { Biawin_CRM_Ownership_Legacy_Bridge::guard_post(); check(false, 'post precheck must stop'); }
catch (C38Exit $x) { check($x->status === 423 && strpos($x->body['die'], 'ثبت درخواست تطبیق مالکیت') !== false && strpos($x->body['die'], 'own_tab=request') !== false, 'admin-post Attach shows a controlled page with CTA'); }
reset_bridge();
// Locked phone in a bundle.
$pair2 = setup_pair(); [$s2, $t2, $p2, $ids2] = $pair2;
Biawin_CRM_Ownership_Repository::atomic(static function () use ($p2, $s2) { Biawin_CRM_Ownership_Lock_Service::create('PHONE', $p2, $s2); });
$r = legacy_ajax('bcrm_attach_bundle', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $t2, 'invoice_code' => row('invoices', $ids2[0])->invoice_code, 'phone' => row('phones', $p2)->normalized_phone], static function () { return 'legacy'; });
check($r['ended_by'] === 'bridge' && $r['body']['data']['ownership']['asset_type'] === 'PHONE' && strpos($r['body']['data']['message'], 'این شماره') !== false, 'locked phone in a bundle reported as a phone lock');

// ---------------------------------------------------------------- completed source: C2 lazily creates the lock on the first protected write
$pairC = setup_pair(); [$sC, $tC, $pC, $idsC] = $pairC; $codeC = row('invoices', $idsC[0])->invoice_code;
check(!Biawin_CRM_Ownership_Legacy_Bridge::locked('INVOICE', $idsC[0]), 'completed-profile invoice has no lock row yet');
$statusC = ok(op('legacy_status', ['customer' => $tC, 'codes' => json_encode([$codeC])]), 'status completed source');
check($statusC['invoices'][$codeC]['maybe_locked'] === true && $statusC['invoices'][$codeC]['locked'] === false, 'completed source is only hinted (maybe_locked), not claimed as locked');
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $tC, 'invoice_code' => $codeC], static function () use ($tC, $codeC) { return Biawin_CRM_Customer_Service::attach_invoice($tC, $codeC, 'اتصال فقط فاکتور توسط کارشناس'); });
check($r['ended_by'] === 'safety_net' && $r['status'] === 423 && $r['body']['data']['code'] === 'ownership_locked', 'C2 completion lock rejection → controlled ownership_locked (no critical error)');
check(strpos((string)$r['body']['data']['ownership']['request_url'], 'asset_id=' . $idsC[0]) !== false && $r['body']['data']['ownership']['source']['id'] === $sC, 'safety-net failure still carries asset/source and the Ownership CTA');
check((int)row('invoices', $idsC[0])->customer_id === $sC && (int)$wpdb->get_var('SELECT customer_id FROM test_bcrm_services WHERE invoice_id=' . $idsC[0]) === $sC, 'blocked completed-profile Attach moved no invoice/service');
check(Biawin_CRM_Ownership_Legacy_Bridge::locked('INVOICE', $idsC[0]), 'C2 completion lock is in place after the rejected write (C2 semantics unchanged)');

// ---------------------------------------------------------------- 19: unlocked Attach unchanged (incomplete source profile → no completion lock)
$pair3 = setup_pair(); [$s3, $t3, $p3, $ids3] = $pair3; $code3 = row('invoices', $ids3[0])->invoice_code;
Biawin_CRM_Ownership_Repository::update('customers', ['profile_completeness' => 'incomplete'], ['id' => $s3]);
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $t3, 'invoice_code' => $code3], static function () use ($t3, $code3) { return Biawin_CRM_Customer_Service::attach_invoice($t3, $code3, 'اتصال فقط فاکتور توسط کارشناس'); });
check($r['ended_by'] === 'legacy' && !empty($r['result']['ok']), 'unlocked Attach runs the legacy handler unchanged');
check((int)row('invoices', $ids3[0])->customer_id === $t3, 'unlocked Attach still moves the invoice as before');
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $t3, 'invoice_code' => 'NO-SUCH-CODE'], static function () { return Biawin_CRM_Customer_Service::attach_invoice(0, 'NO-SUCH-CODE', 'x'); });
check($r['ended_by'] === 'legacy' && $r['result']['ok'] === false, 'unknown invoice left to legacy messaging');

// ---------------------------------------------------------------- hold + error classification
$pair4 = setup_pair(2); [$s4, $t4, $p4, $ids4] = $pair4;
$uid = 1; $held_case = ok(op('create_draft', ['source' => $s4, 'claimant' => $t4, 'reason' => 'r']), 'hold draft')['case_id'];
ok(op('add_draft_asset', ['case_id' => $held_case, 'version' => v($held_case), 'asset_type' => 'INVOICE', 'asset_id' => $ids4[0], 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'hold asset');
ok(op('submit', ['case_id' => $held_case, 'version' => v($held_case)]), 'hold submit');
$other = add('customers', ['internal_uuid' => bin2hex(random_bytes(12)), 'display_name' => 'Third', 'created_at' => Biawin_CRM_DB::now(), 'updated_at' => Biawin_CRM_DB::now()]);
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $other, 'invoice_code' => row('invoices', $ids4[1])->invoice_code], static function () { return 'legacy'; });
check($r['ended_by'] === 'bridge' && $r['body']['data']['code'] === 'ownership_hold' && strpos($r['body']['data']['message'], 'این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است.') !== false, 'held source profile → ownership_hold with approved wording');
$r = legacy_ajax('bcrm_attach_invoice', ['nonce' => 'valid-bcrm_front_ajax', 'customer_id' => $other, 'invoice_code' => row('invoices', $ids4[0])->invoice_code], static function () { return 'legacy'; });
check($r['body']['data']['ownership']['active_case']['id'] === $held_case && $r['body']['data']['ownership']['request_url'] === null, 'active case is reported distinctly and no duplicate-claim CTA is offered');
$r = legacy_ajax('bcrm_save_interaction', ['nonce' => 'valid-bcrm_front_ajax'], static function () use ($s4) { Biawin_CRM_DB::write('update', 'test_bcrm_customers', ['display_name' => 'x'], ['id' => $s4]); });
check($r['ended_by'] === 'safety_net' && $r['body']['data']['code'] === 'ownership_hold' && $r['status'] === 423, 'other legacy mutation on a held profile → controlled ownership_hold');
$r = legacy_ajax('bcrm_save_interaction', ['nonce' => 'valid-bcrm_front_ajax'], static function () { throw new RuntimeException('database exploded'); });
check($r['ended_by'] === 'safety_net' && $r['body']['data']['code'] === 'unexpected' && $r['status'] === 500 && $r['body']['data']['detail'] === 'database exploded', 'unexpected failure stays a visible error, not disguised as a lock');
foreach ([['Asset ownership is locked; use Ownership Reconciliation.', 'ownership_locked'], ['Customer is read-only during ownership review.', 'ownership_hold'], ['Ownership safety lock unavailable; retry.', 'ownership_busy'], ['anything else', 'unexpected']] as [$msg, $code]) {
    check(Biawin_CRM_Ownership_Legacy_Bridge::classify(new RuntimeException($msg))['code'] === $code, 'legacy classification ' . $code);
}
foreach ([['Asset ownership is locked; use Ownership Reconciliation.', 'ownership_locked'], ['Customer is read-only during ownership review.', 'ownership_hold'], ['Asset already participates in an active ownership case.', 'active_ownership_case'], ['Stale ownership case version.', 'stale'], ['Ownership permission denied: x', 'permission'], ['Ownership action is not allowed in state DRAFT', 'state'], ['Something odd', 'domain']] as [$msg, $code]) {
    check(Biawin_CRM_Ownership_Presenter::error(new RuntimeException($msg))['code'] === $code, 'C3 classification ' . $code);
}
check(strpos(Biawin_CRM_Ownership_Presenter::error(new RuntimeException('Something odd'))['message'], 'Something odd') !== false, 'unknown backend message remains visible');

// ---------------------------------------------------------------- 13-14: lookup lock visibility
$uid = 1;
$found = ok(op('lookup', ['query' => $inv->invoice_code]), 'locked lookup');
$item = array_values(array_filter($found['invoices'], static function ($i) use ($inv) { return $i['id'] === (int)$inv->id; }))[0];
check($item['locked'] === true && $item['active_case'] === false, 'active persistent lock: locked=true, not an active case');
$released = setup_pair(); $rid = $released[3][0]; lock_invoice($rid, $released[0]);
Biawin_CRM_Ownership_Repository::atomic(static function () use ($rid) { Biawin_CRM_Ownership_Repository::sql("UPDATE test_bcrm_ownership_locks SET released_at='2026-01-01 00:00:00' WHERE entity_type='INVOICE' AND entity_id=" . $rid); });
$found = ok(op('lookup', ['query' => row('invoices', $rid)->invoice_code]), 'released lookup');
check($found['invoices'][0]['locked'] === false, 'released lock: locked=false');
$js = file_get_contents(__DIR__ . '/../ownership-ui/ownership.js');
check(strpos($js, 'قفل مالکیت') !== false && strpos($js, 'cfg.lockText') !== false, 'wizard renders «قفل مالکیت» and the lock explanation');
$why = substr($js, strpos($js, 'function invoiceWhy('), strpos($js, 'function createPicker(') - strpos($js, 'function invoiceWhy('));
check($why !== '' && strpos($why, 'active_case') !== false && strpos($why, 'locked') === false, 'a persistent lock alone never disables selection in the wizard');
check(strpos($js, 'inv.active_case') !== false, 'only an active case disables selection');
$cfg_html = page(['own_tab' => 'request'], 1);
$status = ok(op('legacy_status', ['customer' => $t, 'codes' => json_encode([$inv->invoice_code, row('invoices', $ids[1])->invoice_code])]), 'legacy status');
check(isset($status['invoices'][$inv->invoice_code]) && $status['invoices'][$inv->invoice_code]['locked'] === true && strpos($status['invoices'][$inv->invoice_code]['request_url'], 'asset_id=' . $inv->id) !== false, 'pre-mutation status exposes the lock and CTA');
$sib = $status['invoices'][row('invoices', $ids[1])->invoice_code] ?? null;
check(($sib['locked'] ?? false) === Biawin_CRM_Ownership_Legacy_Bridge::locked('INVOICE', $ids[1]) && ($sib['invoice_id'] ?? $ids[1]) === $ids[1], 'status is per invoice and reflects only that invoice\'s own lock row');
$pre_sib = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t, 'asset_type' => 'INVOICE', 'asset_id' => $inv->id]);
check($pre_sib['asset']['id'] === (int)$inv->id, 'prefill suggests exactly the blocked invoice, never siblings sharing its phone');
$deny_profiles = [$t]; fails(op('legacy_status', ['customer' => $t, 'codes' => json_encode([$inv->invoice_code])]), 'permission', 'status needs access to the target profile'); $deny_profiles = [];

// ---------------------------------------------------------------- 10-12, G: prefill re-validation, never creates/submits
$n = cases_count(); $uid = 1;
$pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t, 'source' => $s, 'asset_type' => 'INVOICE', 'asset_id' => $inv->id]);
check($pre['claimant']['id'] === $t && $pre['source']['id'] === $s && $pre['asset']['id'] === (int)$inv->id && $pre['asset']['locked'] === true && !$pre['notices'], 'valid prefill re-read from the database');
$pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t, 'source' => 999999, 'asset_type' => 'INVOICE', 'asset_id' => $inv->id]);
check($pre['source']['id'] === $s && count($pre['notices']) === 1, 'stale source hint replaced by the authoritative owner, with a notice');
$deny_profiles = [$t];
$pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t, 'asset_type' => 'INVOICE', 'asset_id' => $inv->id]);
check($pre['claimant'] === null && $pre['notices'], 'inaccessible claimant hint ignored'); $deny_profiles = [];
$pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => 'abc', 'source' => '-5', 'asset_type' => "INVOICE' OR 1=1", 'asset_id' => 'x']);
check($pre['claimant'] === null && $pre['asset'] === null, 'malformed hints ignored safely');
$pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t, 'asset_type' => 'INVOICE', 'asset_id' => 987654]);
check($pre['asset'] === null && $pre['notices'], 'missing asset ignored with notice');
$pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t3, 'asset_type' => 'INVOICE', 'asset_id' => $ids3[0]]);
check($pre['asset'] === null && $pre['source'] === null, 'asset already owned by the claimant ignored');
$uid = 6; $pre = Biawin_CRM_Ownership_Ajax::prefill(['claimant' => $t, 'asset_type' => 'INVOICE', 'asset_id' => $inv->id]);
check($pre['claimant'] === null && $pre['asset'] === null, 'user without claim capability gets no prefill');
$html = page(['own_tab' => 'request', 'claimant' => $t, 'source' => $s, 'asset_type' => 'INVOICE', 'asset_id' => $inv->id], 1);
has($html, 'data-own-prefill=', 'wizard embeds re-validated prefill'); has($html, 'هیچ پرونده‌ای ایجاد یا ارسال نشده است', 'prefill is visibly review-only');
check(cases_count() === $n, 'prefill and wizard rendering never create a case');
check(state_of((int)$inv->id) === $snapshot, 'prefill changed no ownership/lock');

// ---------------------------------------------------------------- 15-18: formal workflow with a persistently locked invoice
$uid = 1; $formal = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'پرداخت توسط مدعی']), 'formal draft with locked invoice')['case_id'];
ok(op('add_draft_asset', ['case_id' => $formal, 'version' => v($formal), 'asset_type' => 'INVOICE', 'asset_id' => $inv->id, 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'locked invoice added to formal draft');
ok(op('submit', ['case_id' => $formal, 'version' => v($formal)]), 'formal submit with locked invoice');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM test_bcrm_customer_holds WHERE case_id=$formal AND status='ACTIVE'") === 2, 'formal submit applied both holds');
check(state_of((int)$inv->id) === $snapshot, 'formal submit still transfers nothing');
$dup = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'duplicate draft')['case_id'];
ok(op('add_draft_asset', ['case_id' => $dup, 'version' => v($dup), 'asset_type' => 'INVOICE', 'asset_id' => $inv->id, 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'duplicate draft asset');
fails(op('submit', ['case_id' => $dup, 'version' => v($dup)]), 'active_ownership_case', 'active conflicting case blocks a duplicate active claim');
$uid = 3; ok(op('assign', ['case_id' => $formal, 'version' => v($formal), 'specialist' => 2]), 'formal assign');
$uid = 2; ok(op('start_review', ['case_id' => $formal, 'version' => v($formal)]), 'formal review'); ok(evidence($formal), 'formal evidence');
ok(op('decide', ['case_id' => $formal, 'version' => v($formal), 'decision' => 'TRANSFER_APPROVED', 'method' => 'CLAIMANT_RECEIPT', 'note' => 'ok']), 'formal approve');
ok(op('execute', ['case_id' => $formal, 'version' => v($formal)]), 'formal execute');
check((int)row('invoices', $inv->id)->customer_id === $t, 'locked invoice moved only through the formal workflow');
check((int)$wpdb->get_var("SELECT customer_id FROM test_bcrm_ownership_locks WHERE entity_type='INVOICE' AND entity_id={$inv->id} AND released_at IS NULL") === $t, 'persistent lock follows the formal transfer (not released/bypassed)');

// ---------------------------------------------------------------- 20: customer search regression (no source change)
$found = Biawin_CRM_Customer_Service::search_related(row('phones', $p4)->normalized_phone);
check(count($found['phones']) === 1 && (int)$found['phones'][0]->customer_id === $s4, 'legacy related search by phone unchanged');

echo 'PASS: ' . ($checks - $before_c38) . " C3.8 legacy-bridge checks (SQLite/WordPress doubles; no browser, WordPress runtime or MySQL claim).\n";
