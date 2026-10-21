"""Static regression checks against the approved C1 commit; run from repository root."""
from pathlib import Path
import re
import subprocess

BASE = "991c3bac815c9fe153096831107441f2194ed632"

def original(name):
    return subprocess.check_output(["git", "show", BASE + ":" + name]).decode("utf-8").replace("\r\n", "\n")

def unwrap(text):
    return re.sub(r"Biawin_CRM_DB::write\('(insert|update|delete|replace|query)', ", lambda m: "$wpdb->" + m[1] + "(", text)

names = ["admin/class-biawin-crm-admin.php"] + ["includes/class-biawin-crm-" + name + ".php" for name in [
    "after-sales-config", "blacklist-final-commit-engine", "controlled-reconstruction", "deficiency-workflow",
    "finance-review", "frontend", "import-service", "production-readiness", "receipt-staging", "refund-register", "rest", "customer-service", "workflow-service"]]
for name in names:
    current = unwrap(Path(name).read_text(encoding="utf-8"))
    current = re.sub(r"Biawin_CRM_DB::read_refresh\([\$]customer_id, static function \(\) use \([\$]customer_id\) \{ (Biawin_CRM_Customer_Service::\w+\([\$]customer_id\);) \}\);", r"\1", current)
    current = re.sub(r"^        Biawin_CRM_DB::assert_customer_mutable\([^\n]+\);\n", "", current, flags=re.M)
    current = re.sub(r"        if \([\$]case_id\) \{\n            [\$]ownership_case_customer = [^\n]+\n            Biawin_CRM_DB::assert_customer_mutable\([^\n]+\);\n        \}\n", "", current)
    current = re.sub(r"        if \([\$]profile === self::PROFILE_COMPLETE && class_exists\('Biawin_CRM_Ownership_Lock_Service'\).*?        \}\n", "", current, count=1, flags=re.S)
    assert current == original(name), name + ": changes beyond the approved guard/completion hooks"
print("PASS: 14 legacy service/controller files retain their original business logic after removing guard delegation/hooks")

tracked = subprocess.check_output(["git", "ls-files"], text=True).splitlines()
protected = [name for name in tracked if name.startswith("assets/") or "reporting-" in name or name in ["includes/class-biawin-crm-auth.php", "includes/class-biawin-crm-finance-report.php", "tests/ownership-c1.php"]]
for name in protected:
    assert Path(name).read_text(encoding="utf-8") == original(name), name
print("PASS:", len(protected), "protected assets/auth/reporting/C1 test files are unchanged")

schema = Path("includes/class-biawin-crm-ownership-schema.php").read_text(encoding="utf-8")
design = Path("docs/OWNERSHIP-RECONCILIATION-IMPLEMENTATION-DESIGN.md").read_text(encoding="utf-8")
for name, section in re.findall(r"### 3\.\d+ `([^`]+)`(.*?)(?=### 3\.|## 4\.)", design, re.S):
    expected = re.search(r"```text\n(.*?)```", section, re.S).group(1)
    actual = re.search(r"CREATE TABLE \{\$p\}" + name + r" \((.*?)\n            \)", schema, re.S).group(1)
    for line in expected.splitlines():
        line = line.split("#")[0].strip()
        if line:
            assert re.search(r"^\s*" + line.split()[0] + r"\s", actual, re.M), name + ":" + line
for column in ["snapshot_json", "execution_state", "execution_error", "executed_at", "finalization_json"]:
    assert re.search(r"^\s*" + column + r"\s", schema, re.M), column
print("PASS: approved C1 schema fields plus all five additive C2 execution fields are present")

for path in Path(".").rglob("*.php"):
    result = subprocess.run(["php", "-n", "-l", str(path)], capture_output=True, text=True)
    assert result.returncode == 0, (str(path), result.stdout, result.stderr)
print("PASS: PHP lint on", len(list(Path(".").rglob("*.php"))), "files")
subprocess.run(["git", "-c", "core.safecrlf=false", "diff", "--check"], check=True)
print("PASS: git diff --check")
