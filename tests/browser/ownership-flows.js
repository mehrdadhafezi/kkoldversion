/* C3.9 automated browser acceptance for the Ownership UI (headless Chromium via Playwright).
 * Drives real DOM interactions against tests/browser/router.php (real plugin classes on a
 * SQLite fixture). Run through tests/browser/run.sh. Not a WordPress/MySQL runtime claim. */
"use strict";
let playwright;
try { playwright = require("playwright"); } catch (e) { playwright = require(process.env.PLAYWRIGHT_PATH || "/opt/node-tools/node_modules/playwright"); }
const fs = require("fs");
const path = require("path");
const BASE = process.env.BCRM_BASE || "http://127.0.0.1:8099";
const SHOTS = process.env.BCRM_SHOTS || "/tmp/bcrm-shots";
fs.mkdirSync(SHOTS, {recursive: true});

let checks = 0; const failures = []; const seenStatus = new Set(); const seenExecution = new Set();
function ok(cond, msg) { checks++; if (!cond) { failures.push(msg); console.log("  FAIL: " + msg); } }
function step(title) { console.log("\n== " + title); }
const sql = async (q) => (await fetch(BASE + "/__test/sql?q=" + encodeURIComponent(q))).json();
const get = async (p) => (await fetch(BASE + p)).json();

let browser, F;
const pageErrors = [];
async function open(uid, viewport = {width: 1366, height: 900}) {
    const context = await browser.newContext({viewport, locale: "fa-IR"});
    await context.addCookies([{name: "bcrm_uid", value: String(uid), url: BASE}]);
    const page = await context.newPage();
    page.on("pageerror", (e) => pageErrors.push(uid + ": " + e.message));
    page.ajax = []; page.raw = [];
    page.on("request", (r) => { if (r.url().endsWith("/ajax") && r.method() === "POST") page.raw.push(r.postData() || ""); });
    page.on("request", (r) => { if (r.url().endsWith("/ajax") && r.method() === "POST") { const d = r.postData() || ""; const m = /name="op"\r\n\r\n([^\r]+)/.exec(d) || /(?:^|&)op=([^&]+)/.exec(d); const v = /name="version"\r\n\r\n([^\r]+)/.exec(d) || /(?:^|&)version=([^&]+)/.exec(d); page.ajax.push({op: m ? decodeURIComponent(m[1]) : (/(?:^|&)action=([^&]+)/.exec(d) || [])[1], version: v ? v[1] : null}); } });
    return page;
}
async function portal(page, params) { await page.goto(BASE + "/portal?bcrm_view=ownership&" + new URLSearchParams(params).toString()); await page.waitForLoadState("load"); }
async function caseState(page) {
    const el = page.locator("[data-own-case]");
    if (!(await el.count())) return {};
    const s = {status: await el.getAttribute("data-own-status"), execution: await el.getAttribute("data-own-execution"), risk: await el.getAttribute("data-own-risk"), id: Number(await el.getAttribute("data-own-case"))};
    seenStatus.add(s.status); seenExecution.add(s.execution);
    return s;
}
async function confirmIfAsked(page) {
    const dialog = page.locator("dialog[data-own-dialog][open]");
    try { await dialog.waitFor({state: "visible", timeout: 1500}); } catch (e) { return false; }
    await dialog.locator("[data-own-dialog-confirm]").click();
    return true;
}
/** Open the accordion that contains a form, as a user would before using it. */
async function reveal(form) {
    const d = form.locator("xpath=ancestor::details[1]");
    if (await d.count() && (await d.getAttribute("open")) === null) await d.locator("summary").first().click();
}
/** Submit a case action form; returns after the authoritative reload. */
async function act(page, op, fill = {}, nth = 0, expectReload = true) {
    const form = page.locator('form[data-own-op="' + op + '"]').nth(nth);
    const details = form.locator("xpath=ancestor::details[1]");
    if (await details.count()) { if (!(await details.getAttribute("open") !== null)) await details.locator("summary").first().click(); }
    for (const [name, value] of Object.entries(fill)) {
        const field = form.locator('[name="' + name + '"]');
        const type = await field.first().getAttribute("type");
        if (type === "radio") await form.locator('[name="' + name + '"][value="' + value + '"]').check();
        else if (type === "file") await field.setInputFiles(value);
        else if ((await field.first().evaluate((e) => e.tagName)) === "SELECT") await field.selectOption(String(value));
        else await field.fill(String(value));
    }
    const nav = expectReload ? page.waitForNavigation({waitUntil: "load", timeout: 15000}).catch(() => null) : null;
    await form.locator('button[type="submit"]').click();
    await confirmIfAsked(page);
    if (nav) {
        const done = await nav;
        if (!done) ok(false, op + " did not complete: " + ((await form.locator("[data-own-form-error]").textContent().catch(() => "")) || "no reload"));
    }
    return caseState(page);
}
async function overflow(page) { return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth); }
async function visual(page, name, url) {
    for (const [w, h, tag] of [[1366, 900, "desktop"], [768, 1024, "tablet"], [390, 844, "mobile"]]) {
        await page.setViewportSize({width: w, height: h});
        if (url) await page.goto(url); else await page.reload();
        await page.waitForLoadState("load");
        const over = await overflow(page);
        ok(over <= 1, `${name} ${tag}: no horizontal overflow (${over}px)`);
        await page.screenshot({path: path.join(SHOTS, `${name}-${tag}.png`), fullPage: true});
        const overlaps = await page.evaluate(() => {
            const els = Array.from(document.querySelectorAll(".bcrm-own button, .bcrm-own a.bcrm-primary-button, .bcrm-own a.bcrm-secondary-button")).filter((b) => (b.checkVisibility ? b.checkVisibility({checkVisibilityCSS: true, contentVisibilityAuto: true}) : b.offsetParent !== null) && b.getClientRects().length);
            const btns = els.map((b) => [b, b.getBoundingClientRect()]).filter(([, r]) => r.width > 0 && r.height > 0);
            const hits = [];
            for (let i = 0; i < btns.length; i++) for (let j = i + 1; j < btns.length; j++) { const a = btns[i][1], b = btns[j][1]; const x = Math.min(a.right, b.right) - Math.max(a.left, b.left), y = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top); if (x > 2 && y > 2) hits.push(btns[i][0].textContent.trim() + " × " + btns[j][0].textContent.trim()); }
            return hits.length ? hits.length + ": " + hits.join(" | ") : 0;
        });
        ok(overlaps === 0, `${name} ${tag}: no overlapping buttons (${overlaps})`);
        if (tag === "desktop" && await page.locator(".bcrm-own-case-actions").count()) {
            const sep = await page.evaluate(() => { const m = document.querySelector(".bcrm-own-case-main").getBoundingClientRect(), a = document.querySelector(".bcrm-own-case-actions").getBoundingClientRect(); return Math.min(m.right, a.right) - Math.max(m.left, a.left); });
            ok(sep <= 0, `${name} desktop: sticky action panel does not cover the content column`);
        }
    }
    await page.setViewportSize({width: 1366, height: 900});
}

/** Request wizard driven through the DOM. */
async function wizard(page, {code, claimant, picks, modes, reason, submit, phoneClaim}) {
    await portal(page, {own_tab: "request"});
    page.ajax = [];
    ok(await page.locator('[data-own-step-nav="1"][aria-current="step"]').count() === 1, "wizard starts at step 1 with progress indicator");
    ok(await page.locator('[data-own-step="1"] [data-own-next]').isDisabled(), "step 1 next disabled until a source is chosen");
    await page.fill("[data-own-lookup-q]", code);
    await page.click("[data-own-lookup]");
    await page.waitForSelector("[data-own-pick-source]");
    await page.locator("[data-own-pick-source]").first().click();
    await page.waitForSelector("[data-own-source-card] .bcrm-own-profile");
    await page.click('[data-own-step="1"] [data-own-next]');
    await page.fill("[data-own-claimant-q]", claimant.name);
    await page.click("[data-own-claimant-search]");
    await page.waitForSelector('[data-own-pick-claimant="' + claimant.id + '"]');
    await page.click('[data-own-pick-claimant="' + claimant.id + '"]');
    await page.click('[data-own-step="2"] [data-own-next]');
    await page.waitForSelector('[data-own-step="3"]:not([hidden]) [data-result]');
    for (const p of picks) {
        if (!(await page.locator('[data-own-step="3"] [data-pick="' + p.key + '"]').count())) {
            await page.fill('[data-own-step="3"] [data-q]', p.search);
            await page.click('[data-own-step="3"] [data-search]');
            await page.waitForSelector('[data-own-step="3"] [data-pick="' + p.key + '"]');
        }
        await page.click('[data-own-step="3"] [data-pick="' + p.key + '"]');
        ok(await page.locator('[data-own-step="3"] [data-result="' + p.key + '"].is-selected').count() === 1, "selected state is visually marked for " + p.key);
    }
    await page.click('[data-own-step="3"] [data-own-next]');
    ok(!(await page.locator('[data-own-step="4"]').isHidden()), "step 4 (phone resolution) shown after asset selection");
    ok(await page.locator('[data-own-step="4"] input[data-field="mode"]:checked').count() === 0, "no phone mode is pre-selected");
    await page.click('[data-own-step="4"] [data-own-next]');
    ok(!(await page.locator('[data-own-step="4"]').isHidden()) && (await page.locator('[data-own-step="4"] [data-sel-error]').first().textContent()).length > 0, "missing phone mode blocks step 4 with an inline error");
    for (const [key, mode] of Object.entries(modes)) {
        await page.check('[data-own-modes] [data-sel="' + key + '"] input[value="' + mode + '"]');
        if (mode === "USE_TARGET_PROFILE_PHONE") {
            const sel = '[data-own-modes] [data-sel="' + key + '"] select[data-field="phone_id"]';
            await page.waitForSelector(sel + ' option:nth-child(2)', {state: "attached"});
            await page.selectOption(sel, {index: 1});
            ok((await page.locator('[data-own-modes] [data-sel="' + key + '"] .bcrm-own-moves').first().textContent()).length > 0, "each phone mode states whether phone ownership moves");
        }
        if (mode === "CLAIM_CURRENT_PHONE" && phoneClaim) {
            ok(await page.locator('[data-own-modes] [data-sel="' + key + '"] [data-claim-phone]').count() === 1, "CLAIM_CURRENT_PHONE asks for the phone itself as a separate asset");
            await page.click('[data-own-modes] [data-sel="' + key + '"] [data-claim-phone]');
            await page.waitForSelector('[data-own-step="3"] [data-sel^="PHONE:"]', {state: "attached"});
        }
    }
    await page.click('[data-own-step="4"] [data-own-next]');
    ok(!(await page.locator('[data-own-step="5"]').isHidden()), "valid phone modes advance to step 5");
    await page.click('[data-own-step="5"] [data-own-next]');
    ok(await page.locator("[data-own-reason][aria-invalid=true]").count() === 1, "missing reason is flagged inline");
    await page.fill("[data-own-reason]", reason);
    await page.fill("[data-own-note]", "توضیحات آزمون خودکار — این متن طولانی برای بررسی شکستن خط و نمایش درست متن فارسی در صفحه مرور نهایی نوشته شده است. ".repeat(3));
    await page.click('[data-own-step="5"] [data-own-next]');
    const review = await page.locator("[data-own-review]").textContent();
    ok(review.includes(code), "final review lists the selected invoice");
    ok((await page.locator('[data-own-step="6"]').textContent()).includes("ثبت درخواست به معنی انتقال مالکیت نیست"), "final review states claim ≠ transfer");
    const before = (await sql("SELECT COUNT(*) n FROM test_bcrm_ownership_cases"))[0].n;
    const nav = page.waitForURL(/own_case=/, {timeout: 15000});
    await page.click(submit ? "[data-own-save-submit]" : "[data-own-save-draft]");
    if (submit) ok(await confirmIfAsked(page), "submission asks for confirmation");
    await nav; await page.waitForLoadState("load");
    const after = (await sql("SELECT COUNT(*) n FROM test_bcrm_ownership_cases"))[0].n;
    ok(after === before + 1, "exactly one case created by the wizard");
    const ops = page.ajax.filter((a) => ["create_draft", "add_draft_asset", "submit"].includes(a.op));
    let chained = ops[0] && ops[0].op === "create_draft";
    for (let i = 1; i < ops.length; i++) chained = chained && Number(ops[i].version) === i;
    ok(chained, "AJAX payload chain create_draft → add_draft_asset(v1..) → submit uses fresh versions (" + ops.map((o) => o.op + "@" + o.version).join(", ") + ")");
    return caseState(page);
}
async function assignAndReview(caseId, specialist = 2, supervisor = 3) {
    const sup = await open(supervisor); await portal(sup, {own_case: caseId});
    let s = await act(sup, "assign", {specialist});
    ok(s.status === "ASSIGNED", "supervisor assignment → ASSIGNED");
    await sup.context().close();
    const sp = await open(specialist); await portal(sp, {own_case: caseId});
    ok((await sp.locator(".bcrm-own-next.is-you").textContent()).includes("شروع بررسی"), "next-step card tells the specialist to start review");
    s = await act(sp, "start_review");
    ok(s.status === "UNDER_REVIEW", "start review → UNDER_REVIEW");
    return sp;
}
const PDF = path.join(SHOTS, "evidence.pdf");
fs.writeFileSync(PDF, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

(async () => {
    browser = await playwright.chromium.launch();
    F = await get("/__test/fixture");

    // ------------------------------------------------------------------ permissions
    step("Permissions / unauthorized");
    let p = await open(6); await portal(p, {own_tab: "request"});
    ok((await p.content()).includes("دسترسی مجاز نیست"), "user without ownership capability gets a permission-denied state");
    ok(await p.locator('a[data-bcrm-view="ownership"]').count() === 0, "no ownership nav link without capability");
    let r = await p.request.post(BASE + "/ajax", {form: {action: "bcrm_ownership", nonce: "nonce-bcrm_ownership_ui", op: "start_review", case_id: "1", version: "1"}});
    ok(r.status() === 403 || r.status() === 404, "unauthorized adapter call is refused (" + r.status() + ")");
    await p.context().close();
    p = await open(1); await portal(p, {own_tab: "queue"});
    ok(!(await p.content()).includes("صف کارشناس تطبیق مالکیت</h2>"), "requester cannot open the specialist queue via URL");
    ok(await p.locator('.bcrm-own-tabs a:has-text("صف سرپرست")').count() === 0, "requester sees no supervisor workspace");
    await p.context().close();
    p = await open(7); await portal(p, {own_tab: "supervisor"});
    ok((await p.content()).includes("صف سرپرست / High Risk"), "administrator sees the supervisor workspace");
    await p.context().close();

    // ------------------------------------------------------------------ FLOW A
    step("FLOW A — normal request on a persistently locked invoice");
    const A = F.A;
    let req = await open(1);
    await portal(req, {own_tab: "request"});
    await req.fill("[data-own-lookup-q]", A.invoice_code); await req.click("[data-own-lookup]");
    await req.waitForSelector("[data-own-pick-source]");
    await visual(req, "request-wizard", null);
    let s = await wizard(req, {code: A.invoice_code, claimant: {id: A.claimant, name: "مدعی A"}, picks: [{key: "INVOICE:" + A.invoice}], modes: {["INVOICE:" + A.invoice]: "KEEP_CURRENT_PHONE"}, reason: "پرداخت توسط مدعی انجام شده است", submit: false});
    ok(s.status === "DRAFT" && s.execution === "NOT_STARTED", "draft created and shown on the case page");
    const caseA = s.id;
    ok((await req.content()).includes("قفل مالکیت دائمی"), "case page marks the persistent ownership lock");
    ok(await req.locator('.bcrm-own-group.is-requester form[data-own-op="submit"]').count() === 1, "requester actions grouped under «اقدامات درخواست‌دهنده»");
    ok(await req.locator('.bcrm-own-group.is-specialist').count() === 0, "no specialist controls for the requester");
    s = await act(req, "submit");
    ok(s.status === "SUBMITTED", "submit → SUBMITTED");
    ok((await sql("SELECT COUNT(*) n FROM test_bcrm_customer_holds WHERE case_id=" + caseA + " AND status='ACTIVE'"))[0].n === 2, "both profile holds are active after submit");
    ok((await req.content()).includes("این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است."), "hold banner shown with approved wording");
    ok((await sql("SELECT customer_id FROM test_bcrm_invoices WHERE id=" + A.invoice))[0].customer_id === A.source, "claim ≠ transfer: invoice still with source after submit");
    await req.context().close();

    // ------------------------------------------------------------------ FLOW B
    step("FLOW B — specialist NORMAL path");
    let sq = await open(3); await portal(sq, {own_tab: "queue"});
    ok(await sq.locator('tr[data-own-groups~="SUBMITTED"]').count() >= 1, "specialist queue lists the SUBMITTED case");
    await visual(sq, "specialist-queue", null);
    await sq.context().close();
    let sp = await assignAndReview(caseA);
    await visual(sp, "case-detail-review", null);
    s = await act(sp, "add_evidence", {kind: "claimant_receipt", file: PDF, note: "رسید بانکی"});
    ok((await sp.locator(".bcrm-own-evidence-item").count()) === 1, "uploaded evidence is displayed");
    s = await act(sp, "record_contact", {result: "current_owner_consent", note: "مالک فعلی رضایت داد"});
    s = await act(sp, "set_final_plan", {phone_resolution: "KEEP_CURRENT_PHONE"});
    s = await act(sp, "evaluate_risk");
    ok(s.risk === "NORMAL" && (await sp.content()).includes("ریسک عادی"), "backend risk is NORMAL and shown as text");
    const todo = await sp.locator(".bcrm-own-checklist-card li.is-todo").allTextContents();
    ok(todo.length === 0, "review checklist complete (open: " + todo.join(" / ") + ")");
    ok((await sp.locator('form[data-own-op="supervisor_decision"]').count()) === 0, "specialist has no supervisor approval control");
    s = await act(sp, "decide", {method: "CURRENT_OWNER_CONSENT", note: "رضایت مالک فعلی ثبت شد"}, 0);
    ok(s.status === "DECIDED", "NORMAL approval → DECIDED");
    ok((await sp.locator('.bcrm-own-group.is-execution form[data-own-op="execute"]').count()) === 1, "execution action grouped under «اجرا و بازیابی»");
    s = await act(sp, "execute");
    ok(s.status === "CLOSED" && s.execution === "FINALIZED", "execute → CLOSED / FINALIZED");
    ok((await sql("SELECT customer_id FROM test_bcrm_invoices WHERE id=" + A.invoice))[0].customer_id === A.claimant, "locked invoice moved only through the formal workflow");
    ok((await sql("SELECT customer_id FROM test_bcrm_ownership_locks WHERE entity_type='INVOICE' AND entity_id=" + A.invoice + " AND released_at IS NULL"))[0].customer_id === A.claimant, "persistent lock follows the transfer");
    ok((await sp.locator("form[data-own-op]").count()) === 0, "closed case has no mutation controls");
    ok((await sp.locator(".bcrm-own-timeline li").count()) >= 10 && (await sp.content()).includes("تغییر مالک فاکتور"), "timeline is human-readable");
    ok((await sp.locator(".bcrm-own-tech").count()) > 0 && !(await sp.locator(".bcrm-own-tech[open]").count()), "technical details are collapsed by default");
    await visual(sp, "closed-case", null);
    await sp.context().close();

    // ------------------------------------------------------------------ FLOW C
    step("FLOW C — HIGH_RISK (multiple invoices) with independent supervisor approval");
    const C = F.C;
    req = await open(1);
    s = await wizard(req, {code: C.invoice_code, claimant: {id: C.claimant, name: "مدعی C"}, picks: [{key: "INVOICE:" + C.invoice}, {key: "INVOICE:" + C.sibling, search: C.sibling_code}], modes: {["INVOICE:" + C.invoice]: "KEEP_CURRENT_PHONE", ["INVOICE:" + C.sibling]: "USE_TARGET_PROFILE_PHONE"}, reason: "خرید برای شخص دیگر", submit: true});
    ok(s.status === "SUBMITTED", "multi-invoice request submitted");
    const caseC = s.id;
    await req.context().close();
    sp = await assignAndReview(caseC, 2, 7);
    s = await caseState(sp);
    ok(s.risk === "HIGH_RISK" && (await sp.content()).includes("بیش از یک فاکتور در دامنه پرونده"), "backend HIGH_RISK with its reason shown");
    ok((await sp.content()).includes("تأیید مستقل سرپرستی غیر از تصمیم‌گیرنده لازم است"), "specialist sees that supervisor approval is required");
    s = await act(sp, "add_evidence", {kind: "claimant_receipt", file: PDF});
    s = await act(sp, "decide", {method: "CLAIMANT_RECEIPT", note: "پیشنهاد انتقال"}, 0);
    ok(s.status === "AWAITING_SUPERVISOR_APPROVAL" && s.execution === "NOT_STARTED", "specialist cannot finalize HIGH_RISK alone → AWAITING_SUPERVISOR_APPROVAL");
    ok((await sp.locator('form[data-own-op="supervisor_decision"]').count()) === 0 && (await sp.locator('form[data-own-op="execute"]').count()) === 0, "specialist has neither supervisor approval nor execution");
    await sp.context().close();
    let sup = await open(5); await portal(sup, {own_tab: "supervisor"});
    ok(await sup.locator("tbody tr:not([hidden])").count() >= 1, "supervisor queue shows the HIGH_RISK case");
    await visual(sup, "supervisor-queue", null);
    await portal(sup, {own_case: caseC});
    ok((await sup.content()).includes("خلاصه برای تصمیم سرپرست"), "supervisor sees the decision summary");
    s = await act(sup, "supervisor_decision", {note: "تأیید مستقل"}, 0);
    ok(s.status === "DECIDED", "supervisor approval → DECIDED");
    await sup.context().close();
    sp = await open(2); await portal(sp, {own_case: caseC});
    s = await act(sp, "execute");
    ok(s.status === "CLOSED", "HIGH_RISK case executed → CLOSED");
    await sp.context().close();

    // ------------------------------------------------------------------ FLOW D
    step("FLOW D — supervisor rejection, SoD and OTP on an explicit phone claim");
    const D = F.D;
    req = await open(1);
    s = await wizard(req, {code: D.invoice_code, claimant: {id: D.claimant, name: "مدعی D"}, picks: [{key: "INVOICE:" + D.invoice}], modes: {["INVOICE:" + D.invoice]: "CLAIM_CURRENT_PHONE"}, phoneClaim: true, reason: "ثبت اشتباه شماره هنگام فروش", submit: true});
    const caseD = s.id;
    await req.context().close();
    sup = await assignAndReview(caseD, 3, 3); // supervisor reviews and will be the decision maker
    s = await caseState(sup);
    ok(s.risk === "HIGH_RISK", "phone claim → HIGH_RISK");
    ok(await sup.locator("#own-otp").count() === 1, "OTP section shown for the explicitly claimed phone");
    s = await act(sup, "otp_issue", {purpose: "CONFIRM_PHONE_CONTROL"});
    ok((await sup.content()).includes("ارسال‌شده — در انتظار ورود کد"), "OTP sent state shown");
    const otp = await get("/__test/otp");
    ok(!(await sup.content()).includes(String(otp.code)), "plaintext OTP never rendered");
    s = await act(sup, "otp_verify", {code: otp.code});
    ok((await sup.content()).includes("تأییدشده") && (await sql("SELECT customer_id FROM test_bcrm_phones WHERE id=" + D.phone))[0].customer_id === D.source, "OTP verified; phone ownership unchanged");
    s = await act(sup, "add_evidence", {kind: "ownership_evidence", file: PDF});
    s = await act(sup, "decide", {method: "MULTI_EVIDENCE", note: "پیشنهاد"}, 0);
    ok(s.status === "AWAITING_SUPERVISOR_APPROVAL", "awaiting independent approval");
    ok((await sup.locator('form[data-own-op="supervisor_decision"]').count()) === 0 && (await sup.content()).includes("تفکیک وظایف"), "decision maker sees SoD explanation and no approval action");
    await sup.context().close();
    sup = await open(5); await portal(sup, {own_case: caseD});
    const rej = sup.locator('form[data-own-op="supervisor_decision"]').nth(1);
    const before = sup.ajax.length;
    ok(await rej.isHidden(), "destructive supervisor return is collapsed by default (accordion)");
    await reveal(rej);
    await rej.locator('button[type="submit"]').click();
    ok((await rej.locator("[data-own-form-error]").textContent()).length > 0 && sup.ajax.length === before, "rejection without reason is blocked inline (no request sent)");
    s = await act(sup, "supervisor_decision", {note: "مدرک کنترل شماره کافی نیست"}, 1);
    ok(s.status === "UNDER_REVIEW", "supervisor rejection → UNDER_REVIEW");
    ok((await sup.content()).includes("بازگرداندن به بررسی توسط سرپرست"), "rejection appears in the timeline");
    await sup.context().close();

    // ------------------------------------------------------------------ FLOW E
    step("FLOW E — evidence request, requester upload, customer confirmation");
    const E = F.E;
    // FLOW G — draft cancelled by the requester is read-only.
    const G = F.G;
    req = await open(1);
    s = await wizard(req, {code: G.invoice_code, claimant: {id: G.claimant, name: "مدعی G"}, picks: [{key: "INVOICE:" + G.invoice}], modes: {["INVOICE:" + G.invoice]: "KEEP_CURRENT_PHONE"}, reason: "ثبت اشتباه شماره هنگام فروش", submit: false});
    s = await act(req, "cancel", {reason: "پیش‌نویس اشتباه ثبت شد"});
    ok(s.status === "CANCELLED", "draft cancelled → CANCELLED");
    ok((await req.locator("form[data-own-op]").count()) === 0 && (await req.content()).includes("لغو شده"), "cancelled case is read-only with explanation");
    s = await wizard(req, {code: E.invoice_code, claimant: {id: E.claimant, name: "مدعی E"}, picks: [{key: "INVOICE:" + E.invoice}], modes: {["INVOICE:" + E.invoice]: "KEEP_CURRENT_PHONE"}, reason: "پرداخت توسط مدعی انجام شده است", submit: true});
    const caseE = s.id;
    await req.context().close();
    sp = await assignAndReview(caseE);
    s = await act(sp, "request_evidence", {customer_confirmation: "0", note: "لطفاً رسید بانکی را بارگذاری کنید"});
    ok(s.status === "AWAITING_EVIDENCE", "request evidence → AWAITING_EVIDENCE");
    req = await open(1); await portal(req, {own_case: caseE});
    ok((await req.locator('form[data-own-op="add_evidence"]').count()) === 1, "requester can upload while evidence is awaited");
    s = await act(req, "add_evidence", {kind: "claimant_receipt", file: PDF, note: "رسید"});
    ok((await req.locator(".bcrm-own-evidence-item").count()) === 1, "requester evidence displayed");
    await req.context().close();
    await sp.reload();
    ok((await sp.content()).includes("ثبت مدرک"), "evidence event appears in the specialist timeline");
    s = await act(sp, "start_review");
    s = await act(sp, "request_evidence", {customer_confirmation: "1", note: "تأیید مشتری لازم است"});
    ok(s.status === "AWAITING_CUSTOMER_CONFIRMATION", "request customer confirmation → AWAITING_CUSTOMER_CONFIRMATION");
    await sp.context().close();

    // ------------------------------------------------------------------ FLOW F + COMMITTED
    for (const key of ["F", "K"]) {
        step("FLOW " + key + " — finalization failure / recovery");
        const X = F[key];
        req = await open(1);
        s = await wizard(req, {code: X.invoice_code, claimant: {id: X.claimant, name: "مدعی " + key}, picks: [{key: "INVOICE:" + X.invoice}], modes: {["INVOICE:" + X.invoice]: "KEEP_CURRENT_PHONE"}, reason: "پرداخت توسط مدعی انجام شده است", submit: true});
        const cid = s.id;
        await req.context().close();
        sp = await assignAndReview(cid);
        await act(sp, "add_evidence", {kind: "claimant_receipt", file: PDF});
        s = await act(sp, "decide", {method: "CLAIMANT_RECEIPT", note: "تأیید"}, 0);
        await get("/__test/fail?customer=" + X.source);
        s = await act(sp, "execute", {}, 0, false);
        await sp.waitForTimeout(800);
        ok((await sp.locator('form[data-own-op="execute"] [data-own-form-error]').textContent()).includes("بازمحاسبه") || s.execution === "FINALIZATION_FAILED" || (await sp.locator("[data-own-stale]:not([hidden])").count()) === 1, "execute with injected post-commit failure surfaces a controlled error");
        await sp.reload(); s = await caseState(sp);
        ok(s.execution === "FINALIZATION_FAILED", "case is FINALIZATION_FAILED");
        ok((await sql("SELECT customer_id FROM test_bcrm_invoices WHERE id=" + X.invoice))[0].customer_id === X.claimant, "ownership was already committed");
        if (key === "K") {
            await get("/__test/set-execution?case=" + cid);
            await sp.reload(); s = await caseState(sp);
            ok(s.execution === "COMMITTED" && (await sp.content()).includes("انتقال ثبت شد — نهایی‌سازی در جریان"), "COMMITTED state rendered");
        } else {
            const html = await sp.content();
            ok(html.includes("انتقال مالکیت قبلاً به‌صورت قطعی ثبت شده است؛ نهایی‌سازی ناتمام مانده است"), "UI states the transfer is committed and finalization incomplete");
            ok(!/retry\s*transfer/i.test(html) && (await sp.locator('form[data-own-op="execute"]').count()) === 0, "no retry-transfer / execute action");
            ok((await sp.locator("form[data-own-op]").count()) === 1 && (await sp.locator('form[data-own-op="resume"]').count()) === 1, "resume is the only action");
            await visual(sp, "finalization-failed", null);
        }
        s = await act(sp, "resume");
        ok(s.status === "CLOSED" && s.execution === "FINALIZED", "resume → CLOSED / FINALIZED (" + key + ")");
        await sp.context().close();
    }

    // ------------------------------------------------------------------ stale version + inline validation + dialog fit
    step("Stale version, inline validation, dialog fit");
    const S = F.S;
    req = await open(1);
    s = await wizard(req, {code: S.invoice_code, claimant: {id: S.claimant, name: "مدعی S"}, picks: [{key: "INVOICE:" + S.invoice}], modes: {["INVOICE:" + S.invoice]: "KEEP_CURRENT_PHONE"}, reason: "ادغام نادرست پروفایل", submit: true});
    const caseS = s.id;
    await req.context().close();
    sp = await assignAndReview(caseS);
    const sp2 = await open(2); await portal(sp2, {own_case: caseS});
    await act(sp, "record_contact", {result: "no_answer", note: "پاسخ نداد"});
    const rc = sp2.locator('form[data-own-op="record_contact"]');
    await reveal(rc);
    await rc.locator('button[type="submit"]').click();
    ok((await rc.locator("[data-own-form-error]").textContent()).includes("فیلدهای الزامی") && (await rc.locator('[aria-invalid="true"]').count()) >= 1, "empty required note blocked inline with aria-invalid");
    await act(sp2, "request_evidence", {note: "مدرک"}, 0, false);
    await sp2.waitForTimeout(500);
    ok(await sp2.locator("[data-own-stale]:not([hidden])").count() === 1, "stale version shows the reload banner");
    await sp2.setViewportSize({width: 390, height: 700});
    await sp2.reload();
    await reveal(sp2.locator('form[data-own-op="evaluate_risk"]'));
    await sp2.locator('form[data-own-op="evaluate_risk"] button[type="submit"]').click();
    ok((await sp2.locator("dialog[data-own-dialog][open] .bcrm-own-dialog-facts").textContent()).includes(S.invoice_code) && await sp2.locator("dialog[data-own-dialog][open] .bcrm-own-dialog-reversible.is-no").count() === 1, "confirmation dialog shows case, parties, assets and irreversibility");
    const box = await sp2.locator("dialog[data-own-dialog][open]").boundingBox();
    ok(box && box.x >= 0 && box.y >= 0 && box.x + box.width <= 390 && box.y + box.height <= 700, "confirmation dialog fits a 390px viewport");
    await sp2.locator("[data-own-dialog-cancel]").click();
    await sp2.context().close(); await sp.context().close();

    // ------------------------------------------------------------------ C3.10 final-plan payload normalization
    step("C3.10 — final plan TRANSFER + KEEP_CURRENT_PHONE with claimant owning a phone");
    const fields = (body) => { const out = {}; const re = /name="([^"]+)"\r\n\r\n([^\r]*)/g; let m; while ((m = re.exec(body))) out[m[1]] = m[2]; return out; };
    const lastPlan = (page) => { const b = page.raw.filter((x) => /name="op"\r\n\r\nset_final_plan/.test(x)).pop(); return b ? fields(b) : null; };
    ok((await sql("SELECT COUNT(*) n FROM test_bcrm_phones WHERE customer_id=" + S.claimant))[0].n >= 1, "claimant owns its own phone (runtime reproduction)");
    const fp = await open(2); await portal(fp, {own_case: caseS});
    const plan = fp.locator('form[data-own-op="set_final_plan"]').first();
    await plan.locator("xpath=ancestor::details[1]/summary").click();
    ok(await plan.locator('[data-own-mode-field="USE_TARGET_PROFILE_PHONE"]').isHidden() && await plan.locator('[name="phone_id"]').isDisabled(), "phone_id hidden and disabled while KEEP_CURRENT_PHONE is selected");
    await plan.locator('[name="phone_resolution"][value="USE_TARGET_PROFILE_PHONE"]').check();
    ok(!(await plan.locator('[data-own-mode-field="USE_TARGET_PROFILE_PHONE"]').isHidden()), "USE_TARGET_PROFILE_PHONE reveals the target phone field");
    await plan.locator('[name="phone_id"]').selectOption({index: 1});
    await plan.locator('[name="phone_resolution"][value="KEEP_CURRENT_PHONE"]').check();
    ok((await plan.locator('[name="phone_id"]').inputValue()) === "" && await plan.locator('[name="phone_id"]').isDisabled(), "switching back to KEEP clears and disables the stale phone_id");
    const riskBefore = (await caseState(fp)).risk;
    s = await act(fp, "set_final_plan", {}, 0);
    let pl = lastPlan(fp);
    ok(pl && pl.final_action === "TRANSFER" && pl.phone_resolution === "KEEP_CURRENT_PHONE" && !("phone_id" in pl) && !("phone" in pl), "KEEP_CURRENT_PHONE payload carries no phone_id and no phone (" + JSON.stringify(pl) + ")");
    const evs = (await sql("SELECT event_type FROM test_bcrm_ownership_events WHERE case_id=" + caseS + " AND event_type IN ('FINAL_PLAN_CONFIRMED','REQUESTED_TRANSFER_PLAN_CHANGED')")).map((r) => r.event_type);
    ok(evs.includes("FINAL_PLAN_CONFIRMED") && !evs.includes("REQUESTED_TRANSFER_PLAN_CHANGED"), "unchanged plan → FINAL_PLAN_CONFIRMED only (" + evs.join(",") + ")");
    const stored = (await sql("SELECT final_phone_id, final_phone_normalized FROM test_bcrm_ownership_case_assets WHERE case_id=" + caseS))[0];
    ok(stored.final_phone_id === null && stored.final_phone_normalized === null, "stored final plan has phone_id=null and phone=null");
    ok(riskBefore === "NORMAL" && s.risk === "NORMAL", "risk remains NORMAL after confirming the unchanged plan");
    s = await act(fp, "set_final_plan", {phone_resolution: "USE_TARGET_PROFILE_PHONE", phone_id: (await sql("SELECT id FROM test_bcrm_phones WHERE customer_id=" + S.claimant))[0].id, reason: "شماره مقصد"}, 0);
    pl = lastPlan(fp);
    ok(pl && pl.phone_resolution === "USE_TARGET_PROFILE_PHONE" && pl.phone_id && !("phone" in pl), "USE_TARGET_PROFILE_PHONE sends only phone_id");
    const plan2 = fp.locator('form[data-own-op="set_final_plan"]').first();
    if (!(await plan2.locator("xpath=ancestor::details[1]").getAttribute("open"))) await plan2.locator("xpath=ancestor::details[1]/summary").click();
    await plan2.locator('[name="phone_resolution"][value="PROPOSE_NEW_PHONE"]').check();
    ok((await plan2.locator('[name="phone_id"]').inputValue()) === "" && await plan2.locator('[name="phone_id"]').isDisabled(), "switching USE → PROPOSE clears the prior phone_id");
    s = await act(fp, "set_final_plan", {phone: "09398887766", reason: "شماره جدید"}, 0);
    pl = lastPlan(fp);
    ok(pl && pl.phone_resolution === "PROPOSE_NEW_PHONE" && pl.phone === "09398887766" && !("phone_id" in pl), "PROPOSE_NEW_PHONE sends only phone");
    const plan3 = fp.locator('form[data-own-op="set_final_plan"]').first();
    if (!(await plan3.locator("xpath=ancestor::details[1]").getAttribute("open"))) await plan3.locator("xpath=ancestor::details[1]/summary").click();
    await plan3.locator('[name="phone_resolution"][value="USE_TARGET_PROFILE_PHONE"]').check();
    await plan3.locator('[name="phone_id"]').selectOption({index: 1});
    s = await act(fp, "set_final_plan", {phone_resolution: "CLAIM_CURRENT_PHONE", reason: "ادعای شماره"}, 0, false);
    await fp.waitForTimeout(600);
    pl = lastPlan(fp);
    ok(pl && pl.phone_resolution === "CLAIM_CURRENT_PHONE" && !("phone_id" in pl) && !("phone" in pl), "CLAIM_CURRENT_PHONE does not leak the target profile phone_id");
    await fp.context().close();

    // ------------------------------------------------------------------ FLOW H
    step("FLOW H — legacy locked Attach bridge");
    const H = F.H;
    const lg = await open(1);
    await lg.goto(BASE + "/legacy?customer=" + H.claimant);
    await lg.click("[data-bcrm-toggle-related]");
    await lg.fill("[data-bcrm-related-query]", H.invoice_code);
    await lg.click("[data-bcrm-related-search]");
    await lg.waitForSelector(".bcrm-own-legacy-note", {timeout: 10000});
    const note = await lg.locator(".bcrm-own-legacy-note").first().textContent();
    ok(note.includes("قفل مالکیت") && note.includes("تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است."), "legacy result shows the lock before mutation");
    ok(await lg.locator('[data-bcrm-invoice-result="' + H.invoice_code + '"] [data-attach-invoice-only]').isDisabled(), "legacy Attach button disabled for the locked invoice");
    const casesBefore = (await sql("SELECT COUNT(*) n FROM test_bcrm_ownership_cases"))[0].n;
    r = await lg.request.post(BASE + "/ajax", {form: {action: "bcrm_attach_invoice", nonce: "nonce-bcrm_front_ajax", customer_id: String(H.claimant), invoice_code: H.invoice_code}});
    const body = await r.json();
    ok(r.status() === 423 && body.success === false && body.data.code === "ownership_locked", "forced legacy Attach → controlled 423 ownership_locked (no critical error)");
    ok((await sql("SELECT customer_id FROM test_bcrm_invoices WHERE id=" + H.invoice))[0].customer_id === H.source, "legacy Attach moved nothing");
    await lg.screenshot({path: path.join(SHOTS, "legacy-locked-attach.png"), fullPage: true});
    const nav2 = lg.waitForURL(/own_tab=request/);
    await lg.locator(".bcrm-own-legacy-cta").first().click();
    await nav2; await lg.waitForLoadState("load");
    ok((await lg.locator("[data-own-source-card]").textContent()).includes("مالک فعلی H"), "prefilled wizard shows the current owner as source");
    ok((await lg.locator("[data-own-claimant-card]").textContent()).includes("مدعی H"), "prefilled wizard shows the claimant");
    ok((await lg.content()).includes("هیچ پرونده‌ای ایجاد یا ارسال نشده است"), "prefill is review-only");
    await lg.click('[data-own-step="1"] [data-own-next]'); await lg.click('[data-own-step="2"] [data-own-next]');
    ok(await lg.locator(".bcrm-own-result.is-suggested").count() === 1 && await lg.locator('[data-own-step="3"] [data-sel]').count() === 0, "suggested invoice highlighted but not selected");
    ok((await lg.locator(".bcrm-own-result.is-suggested").textContent()).includes("قفل مالکیت") && !(await lg.locator(".bcrm-own-result.is-suggested [data-pick]").isDisabled()), "locked invoice stays selectable in the formal workflow");
    ok((await sql("SELECT COUNT(*) n FROM test_bcrm_ownership_cases"))[0].n === casesBefore, "prefill created no case");
    await lg.context().close();

    // ------------------------------------------------------------------ coverage summary
    step("State coverage");
    for (const st of ["DRAFT", "SUBMITTED", "ASSIGNED", "UNDER_REVIEW", "AWAITING_EVIDENCE", "AWAITING_CUSTOMER_CONFIRMATION", "AWAITING_SUPERVISOR_APPROVAL", "DECIDED", "CLOSED", "CANCELLED"]) ok(seenStatus.has(st), "rendered status " + st);
    for (const ex of ["NOT_STARTED", "COMMITTED", "FINALIZATION_FAILED", "FINALIZED"]) ok(seenExecution.has(ex), "rendered execution state " + ex);
    ok(pageErrors.length === 0, "no JavaScript page errors (" + pageErrors.join(" | ") + ")");

    await browser.close();
    console.log("\n" + (failures.length ? "FAILED: " + failures.length + " of " + checks + " browser checks" : "PASS: " + checks + " C3.9 browser flow checks (headless Chromium; SQLite/WordPress doubles; no WordPress runtime or MySQL claim)."));
    process.exit(failures.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
