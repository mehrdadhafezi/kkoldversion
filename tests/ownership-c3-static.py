"""C3 static guards; run from the repository root after tests/ownership-c2-static.py.

Checks that the C3 UI layer stays a presentation/transport layer on top of the
approved C2 checkpoint: no backend/schema/state-machine file changed, no frozen
asset touched, no direct ownership-table writes or risk computation in UI code,
and no 'retry transfer' affordance anywhere in the UI.
"""
from pathlib import Path
import re
import subprocess

C2 = "38779e8f5fa02e3544f0d3a51263aca2416b83b2"
UI_PHP = ["includes/class-biawin-crm-ownership-presenter.php", "includes/class-biawin-crm-ownership-ajax.php", "includes/class-biawin-crm-ownership-ui.php", "includes/class-biawin-crm-ownership-legacy-bridge.php"]
UI_ASSETS = ["ownership-ui/ownership.js", "ownership-ui/ownership.css"]

changed = subprocess.check_output(["git", "diff", "--name-only", C2, "--"], text=True).split()
changed += subprocess.check_output(["git", "ls-files", "--others", "--exclude-standard"], text=True).split()
allowed = set(UI_PHP + UI_ASSETS + ["biawin-crm-integration.php", "CHANGELOG.md", "STRUCTURE.md", "docs/OWNERSHIP-RECONCILIATION-C3-UI.md", "tests/ownership-c3-ui.php", "tests/ownership-c3-static.py", "tests/ownership-c3-legacy-bridge.php",
    "tests/browser/bootstrap.php", "tests/browser/seed.php", "tests/browser/router.php", "tests/browser/ownership-flows.js", "tests/browser/run.sh"])
unexpected = sorted(set(changed) - allowed)
assert not unexpected, "C3 changed files outside the UI layer: %s" % unexpected
print("PASS: only C3 UI/adapter/doc/test files differ from the approved C2 checkpoint (%d files)" % len(set(changed)))

boot = subprocess.check_output(["git", "diff", C2, "--", "biawin-crm-integration.php"], text=True)
for line in boot.splitlines():
    if line.startswith(("+", "-")) and not line.startswith(("+++", "---")):
        assert line.startswith("+") and ("ownership" in line or "Ownership" in line), "bootstrap change beyond C3 registration: " + line
assert "define('BIAWIN_CRM_VERSION', '0.8.65.19');" in Path("biawin-crm-integration.php").read_text(encoding="utf-8")
print("PASS: bootstrap only registers the C3 classes; plugin version remains 0.8.65.19")

for name in UI_PHP:
    text = Path(name).read_text(encoding="utf-8")
    assert not re.search(r"\$wpdb->(insert|update|delete|replace|query)\(", text), name + ": direct wpdb write"
    assert not re.search(r"Biawin_CRM_Ownership_Repository::(insert|update|sql|atomic|case_command|gate)\(", text), name + ": repository write"
    assert not re.search(r"Biawin_CRM_DB::write\(", text), name + ": legacy write gateway"
    assert not re.search(r"Risk_Service::|Hold_Service::(apply|release)|Lock_Service::(create|move|sync)", text), name + ": risk/hold/lock mutation"
    assert "otp_hash" not in re.sub(r"//.*|/\*.*?\*/", "", text, flags=re.S) or name.endswith("presenter.php") and "never otp_hash" in text, name + ": OTP hash read"
print("PASS: C3 PHP performs no direct writes, risk evaluation, hold/lock mutation or OTP-hash reads")

ajax = Path("includes/class-biawin-crm-ownership-ajax.php").read_text(encoding="utf-8")
calls = re.findall(r"(Biawin_CRM_Ownership_(?:Workflow|OTP|Transfer_Service)::\w+)\(", ajax)
commands = sorted(set(c for c in calls if not c.endswith("::get")))
expected = sorted("Biawin_CRM_Ownership_" + c for c in ["Workflow::create_draft", "Workflow::update_draft", "Workflow::add_draft_asset", "Workflow::remove_draft_asset", "Workflow::submit", "Workflow::cancel", "Workflow::add_evidence", "Workflow::assign", "Workflow::start_review", "Workflow::request_evidence", "Workflow::record_contact", "Workflow::amend", "Workflow::set_final_plan", "Workflow::evaluate_risk", "Workflow::decide", "Workflow::supervisor_decision", "OTP::issue", "OTP::verify", "Transfer_Service::execute", "Transfer_Service::resume"])
assert commands == expected, commands
assert "check_ajax_referer(self::NONCE" in ajax and "wp_ajax_nopriv_" in ajax
print("PASS: AJAX adapter maps exactly the %d approved backend commands behind a nonce" % len(expected))

js = Path("ownership-ui/ownership.js").read_text(encoding="utf-8")
ui = Path("includes/class-biawin-crm-ownership-ui.php").read_text(encoding="utf-8")
for text, name in [(js, "js"), (ui, "ui")]:
    assert not re.search(r"retry\s*transfer", text, re.I), name + ": retry transfer affordance"
assert not re.search(r"risk_level\s*=|HIGH_RISK", js), "JS must not compute or assign risk"
assert "customer_id" not in js, "JS must not reference ownership columns"
print("PASS: no Retry Transfer affordance; JS neither computes risk nor touches ownership fields")

for name in UI_ASSETS:
    assert not name.startswith("assets/")
assert not [f for f in subprocess.check_output(["git", "ls-files", "assets"], text=True).split() if f.startswith("assets/ownership")]
result = subprocess.run(["node", "--check", "ownership-ui/ownership.js"], capture_output=True, text=True)
assert result.returncode == 0, result.stderr
print("PASS: C3 assets live outside frozen assets/; JS syntax valid (node --check)")

# ---- C3.8 legacy Attach bridge
bridge = Path("includes/class-biawin-crm-ownership-legacy-bridge.php").read_text(encoding="utf-8")
frontend = Path("includes/class-biawin-crm-frontend.php").read_text(encoding="utf-8")
ajax_actions = re.search(r"const AJAX_ACTIONS = \[(.*?)\];", bridge, re.S).group(1)
post_actions = re.search(r"const POST_ACTIONS = \[(.*?)\];", bridge, re.S).group(1)
for action in re.findall(r"'([a-z_]+)'", ajax_actions):
    assert "add_action('wp_ajax_" + action + "'" in frontend, "bridge covers a non-existent legacy ajax action: " + action
for action in re.findall(r"'([a-z_]+)'", post_actions):
    assert "'" + action + "' =>" in frontend, "bridge covers a non-existent legacy admin-post action: " + action
assert "[__CLASS__, 'guard_ajax'], 1)" in bridge and "[__CLASS__, 'guard_post'], 1)" in bridge, "bridge must run before legacy priority-10 callbacks"
assert "Biawin_CRM_Customer_Service::" not in bridge, "bridge must never perform the Attach itself"
assert not re.search(r"Lock_Service::|Transfer_Service::|Workflow::", bridge), "bridge must not call lock/transfer/workflow commands"
assert "'ownership_locked' => 423" in bridge and "'unexpected' => 500" in bridge
assert "تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است." in bridge and "این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است." in bridge
print("PASS: C3.8 bridge hooks existing legacy actions at priority 1, never performs Attach or ownership commands, keeps failures as failures")

# ---- C3.9: form fields must never collide with WordPress's AJAX routing key "action"
ui = Path("includes/class-biawin-crm-ownership-ui.php").read_text(encoding="utf-8")
assert not re.search(r'name="action"', ui), "UI form field named 'action' collides with admin-ajax routing"
assert 'name="final_action"' in ui and "isset($in['final_action'])" in ajax
result = subprocess.run(["node", "--check", "tests/browser/ownership-flows.js"], capture_output=True, text=True)
assert result.returncode == 0, result.stderr
print("PASS: C3.9 no form field shadows the admin-ajax 'action' key; browser suite syntax valid")
