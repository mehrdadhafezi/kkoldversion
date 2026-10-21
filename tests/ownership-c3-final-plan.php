<?php
/**
 * C3.10 final-plan payload normalization regression (SQLite/WordPress doubles).
 * Runs the unchanged C2, C3 and C3.8 suites first, then reproduces the runtime case:
 * requested TRANSFER + KEEP_CURRENT_PHONE while the claimant owns a phone.
 * Run: php -n -d extension=pdo -d extension=pdo_sqlite tests/ownership-c3-final-plan.php
 */
ob_start();
require __DIR__ . '/ownership-c3-legacy-bridge.php';
echo ob_get_clean();
$before_c310 = $checks;

function events_of(int $case): array { global $wpdb; return array_map(static function ($r) { return $r->event_type; }, $wpdb->get_results('SELECT event_type FROM test_bcrm_ownership_events WHERE case_id=' . $case . ' ORDER BY id')); }
function reviewing_keep_case(): array {
    global $uid;
    $pair = setup_pair(); [$s, $t, $p, $ids] = $pair;
    $claimant_phone = add('phones', ['customer_id' => $t, 'raw_phone' => '0935' . str_pad((string)$t, 7, '0', STR_PAD_LEFT), 'normalized_phone' => '0935' . str_pad((string)$t, 7, '0', STR_PAD_LEFT), 'created_at' => Biawin_CRM_DB::now()]);
    $uid = 1; $case = ok(op('create_draft', ['source' => $s, 'claimant' => $t, 'reason' => 'r']), 'keep draft')['case_id'];
    ok(op('add_draft_asset', ['case_id' => $case, 'version' => v($case), 'asset_type' => 'INVOICE', 'asset_id' => $ids[0], 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'keep asset');
    ok(op('submit', ['case_id' => $case, 'version' => v($case)]), 'keep submit');
    $uid = 3; ok(op('assign', ['case_id' => $case, 'version' => v($case), 'specialist' => 2]), 'keep assign');
    $uid = 2; ok(op('start_review', ['case_id' => $case, 'version' => v($case)]), 'keep review');
    $asset = Biawin_CRM_Ownership_Repository::assets($case)[0];
    check($asset->requested_phone_id === null && $asset->requested_phone_normalized === null, 'requested plan carries no phone_id/phone for KEEP_CURRENT_PHONE');
    return [$case, (int)$asset->id, $claimant_phone];
}

// 1-4: the normalized UI payload for an unchanged plan (what ownership.js now sends).
[$case, $row, $claimant_phone] = reviewing_keep_case();
check(row('ownership_cases', $case)->risk_level === 'NORMAL', 'single-invoice KEEP case starts NORMAL');
ok(op('set_final_plan', ['case_id' => $case, 'version' => v($case), 'asset_row' => $row, 'final_action' => 'TRANSFER', 'phone_resolution' => 'KEEP_CURRENT_PHONE']), 'normalized KEEP final plan');
$asset = row('ownership_case_assets', $row);
check($asset->final_phone_id === null && $asset->final_phone_normalized === null, 'KEEP_CURRENT_PHONE stores phone_id=null and phone=null');
$ev = events_of($case);
check(in_array('FINAL_PLAN_CONFIRMED', $ev, true), 'unchanged plan emits FINAL_PLAN_CONFIRMED');
check(!in_array('REQUESTED_TRANSFER_PLAN_CHANGED', $ev, true), 'unchanged plan does not emit REQUESTED_TRANSFER_PLAN_CHANGED');
check(row('ownership_cases', $case)->risk_level === 'NORMAL' && !in_array('PLAN_OVERRIDE', Biawin_CRM_Ownership_Repository::decode(row('ownership_cases', $case)->risk_reasons_json), true), 'unchanged plan stays NORMAL (no PLAN_OVERRIDE)');

// Backend comparison semantics are unchanged: the old leaked payload is still a real change.
[$case2, $row2, $phone2] = reviewing_keep_case();
fails(op('set_final_plan', ['case_id' => $case2, 'version' => v($case2), 'asset_row' => $row2, 'final_action' => 'TRANSFER', 'phone_resolution' => 'KEEP_CURRENT_PHONE', 'phone_id' => $phone2]), 'domain', 'backend still treats a leaked phone_id as a plan change (reason required) — semantics untouched');

// Rendered form: irrelevant inputs are hidden/disabled and never preselected, even if a stale final_phone_id exists.
$html = page(['own_case' => $case], 2);
preg_match('~<form class="bcrm-own-action" data-own-op="set_final_plan".*?</form>~s', $html, $m);
$form = $m[0] ?? '';
check($form !== '' && strpos($form, 'autocomplete="off"') !== false, 'final-plan form disables browser form-state restoration');
check((bool)preg_match('~data-own-mode-field="USE_TARGET_PROFILE_PHONE" hidden>.*?<select[^>]*name="phone_id" disabled>~s', $form), 'phone_id select is hidden and disabled under KEEP_CURRENT_PHONE');
check((bool)preg_match('~data-own-mode-field="PROPOSE_NEW_PHONE" hidden>.*?name="phone"[^>]*value=""[^>]*disabled~s', $form), 'phone input is hidden, empty and disabled under KEEP_CURRENT_PHONE');
function phone_select(string $form): string { return preg_match('~<select[^>]*name="phone_id".*?</select>~s', $form, $x) ? $x[0] : ''; }
check(phone_select($form) !== '' && strpos(phone_select($form), 'selected="selected"') === false, 'no target phone is preselected under KEEP_CURRENT_PHONE');
check(strpos($form, 'data-req-pid="0"') !== false && strpos($form, 'data-req-phone=""') !== false, 'requested-plan comparison values are mode-normalized');
Biawin_CRM_Ownership_Repository::update('ownership_case_assets', ['final_phone_id' => $claimant_phone], ['id' => $row]); // simulate the polluted runtime row
$html = page(['own_case' => $case], 2);
preg_match('~<form class="bcrm-own-action" data-own-op="set_final_plan".*?</form>~s', $html, $m);
check(strpos(phone_select($m[0]), 'selected="selected"') === false && (bool)preg_match('~<select[^>]*name="phone_id" disabled>~', $m[0]), 'a stale stored final_phone_id is not re-posted under KEEP_CURRENT_PHONE');
Biawin_CRM_Ownership_Repository::update('ownership_case_assets', ['final_phone_id' => null], ['id' => $row]);

// 5-8: payload rules in ownership.js.
$js = file_get_contents(__DIR__ . '/../ownership-ui/ownership.js');
$fn = substr($js, strpos($js, 'function normalizePlanPayload('), 420);
check(strpos($fn, 'data.delete("phone_id")') !== false && strpos($fn, 'data.delete("phone")') !== false, 'payload drops phone_id/phone before re-adding by mode');
check(strpos($fn, 'if (v.mode === "USE_TARGET_PROFILE_PHONE") data.append("phone_id"') !== false && strpos($fn, 'if (v.mode === "PROPOSE_NEW_PHONE") data.append("phone"') !== false, 'USE sends only phone_id; PROPOSE sends only phone; KEEP/CLAIM send neither');
check(strpos($js, 'if (form.hasAttribute("data-own-plan")) normalizePlanPayload(form, data);') !== false, 'normalization runs on every final-plan submission');
check(strpos($js, 'if (!on) { f.value = "";') !== false && strpos($js, 'f.disabled = !on;') !== false, 'switching modes clears and disables fields of other modes');

// USE / PROPOSE through the adapter with normalized payloads are accepted and stored exactly.
ok(op('set_final_plan', ['case_id' => $case, 'version' => v($case), 'asset_row' => $row, 'final_action' => 'TRANSFER', 'phone_resolution' => 'USE_TARGET_PROFILE_PHONE', 'phone_id' => $claimant_phone, 'reason' => 'شماره مقصد']), 'USE plan');
$asset = row('ownership_case_assets', $row);
check((int)$asset->final_phone_id === $claimant_phone && $asset->final_phone_normalized === null, 'USE_TARGET_PROFILE_PHONE stores only phone_id');
ok(op('set_final_plan', ['case_id' => $case, 'version' => v($case), 'asset_row' => $row, 'final_action' => 'TRANSFER', 'phone_resolution' => 'PROPOSE_NEW_PHONE', 'phone' => '09398887766', 'reason' => 'شماره جدید']), 'PROPOSE plan');
$asset = row('ownership_case_assets', $row);
check($asset->final_phone_id === null && $asset->final_phone_normalized === '09398887766', 'PROPOSE_NEW_PHONE stores only phone');

echo 'PASS: ' . ($checks - $before_c310) . " C3.10 final-plan normalization checks (SQLite/WordPress doubles; no WordPress runtime or MySQL claim).\n";
