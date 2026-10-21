/* C3.11 portal-wide visual + interaction review (headless Chromium). Run after ownership-flows.js
 * by tests/browser/run.sh so the fixture contains cases in every state. Screenshots are written to
 * $BCRM_SHOTS/review for manual inspection. Not a WordPress/MySQL runtime claim. */
"use strict";
let playwright;
try { playwright = require("playwright"); } catch (e) { playwright = require(process.env.PLAYWRIGHT_PATH || "/opt/node-tools/node_modules/playwright"); }
const fs = require("fs");
const path = require("path");
const BASE = process.env.BCRM_BASE || "http://127.0.0.1:8099";
const OUT = path.join(process.env.BCRM_SHOTS || "/tmp/bcrm-shots", "review");
fs.mkdirSync(OUT, {recursive: true});
let checks = 0; const failures = [];
function ok(c, m) { checks++; if (!c) { failures.push(m); console.log("  FAIL: " + m); } }
const sql = async (q) => (await fetch(BASE + "/__test/sql?q=" + encodeURIComponent(q))).json();
const WIDTHS = [[1440, 900, "1440"], [1366, 900, "1366"], [1024, 768, "1024"], [768, 1024, "768"], [430, 932, "430"], [390, 844, "390"]];

(async () => {
    const browser = await playwright.chromium.launch();
    const fixture = await (await fetch(BASE + "/__test/fixture")).json();
    const closedCase = (await sql("SELECT id FROM test_bcrm_ownership_cases WHERE status='CLOSED' AND risk_level='HIGH_RISK' ORDER BY id LIMIT 1"))[0];
    const normalCase = (await sql("SELECT id FROM test_bcrm_ownership_cases WHERE status='CLOSED' AND risk_level='NORMAL' ORDER BY id LIMIT 1"))[0];
    const reviewCase = (await sql("SELECT id FROM test_bcrm_ownership_cases WHERE status IN ('UNDER_REVIEW','AWAITING_CUSTOMER_CONFIRMATION') ORDER BY id DESC LIMIT 1"))[0];
    const profileWithInvoices = (await sql("SELECT customer_id id FROM test_bcrm_invoices GROUP BY customer_id ORDER BY COUNT(*) DESC LIMIT 1"))[0];
    const pages = [
        [8, "dashboard", "/portal?bcrm_view=dashboard"], [8, "customers", "/portal?bcrm_view=customers"], [8, "customer-search", "/portal?bcrm_view=customers&bcrm_q=0912"],
        [8, "customer-search-empty", "/portal?bcrm_view=customers&bcrm_q=NO-SUCH-CUSTOMER-XYZ"], [8, "profile", "/portal?bcrm_customer=" + profileWithInvoices.id],
        [8, "assignments", "/portal?bcrm_view=assignments"], [8, "import", "/portal?bcrm_view=import"], [8, "deficiency", "/portal?bcrm_view=deficiency"],
        [8, "financial", "/portal?bcrm_view=financial"], [8, "complaints", "/portal?bcrm_view=complaints"], [8, "service-cases", "/portal?bcrm_view=service-cases"],
        [8, "services", "/portal?bcrm_view=services"], [8, "service-units", "/portal?bcrm_view=service-units"], [8, "refunds", "/portal?bcrm_view=refunds"],
        [8, "refunded", "/portal?bcrm_view=refunded"], [8, "reports", "/portal?bcrm_view=reports"], [8, "data-audit", "/portal?bcrm_view=data-audit"],
        [8, "users", "/portal?bcrm_view=users"], [8, "audit", "/portal?bcrm_view=audit"], [8, "settings", "/portal?bcrm_view=settings"],
        [1, "own-request", "/portal?bcrm_view=ownership&own_tab=request"], [1, "own-mine", "/portal?bcrm_view=ownership&own_tab=mine"],
        [3, "own-queue", "/portal?bcrm_view=ownership&own_tab=queue"], [3, "own-supervisor", "/portal?bcrm_view=ownership&own_tab=supervisor"],
        [3, "own-history", "/portal?bcrm_view=ownership&own_tab=history"], [2, "own-case-review", "/portal?bcrm_view=ownership&own_case=" + reviewCase.id],
        [3, "own-case-closed-high", "/portal?bcrm_view=ownership&own_case=" + closedCase.id], [3, "own-case-closed-normal", "/portal?bcrm_view=ownership&own_case=" + normalCase.id],
        [6, "own-denied", "/portal?bcrm_view=ownership"], [1, "legacy-profile-panel", "/legacy?customer=" + fixture.H.claimant],
    ];
    console.log("== Visual sweep: " + pages.length + " screens × " + WIDTHS.length + " widths");
    const errors = [];
    for (const [w, h, tag] of WIDTHS) {
        const contexts = {};
        for (const [uid, name, url] of pages) {
            if (!contexts[uid]) { contexts[uid] = await browser.newContext({viewport: {width: w, height: h}}); await contexts[uid].addCookies([{name: "bcrm_uid", value: String(uid), url: BASE}]); }
            const page = await contexts[uid].newPage();
            page.on("pageerror", (e) => errors.push(name + "@" + tag + ": " + e.message));
            const res = await page.goto(BASE + url); await page.waitForLoadState("load");
            ok(res.status() < 500 && !(await page.content()).includes("Fatal error"), `${name} @${tag}: renders without server error`);
            const over = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            ok(over <= 1, `${name} @${tag}: no horizontal overflow (${over}px)`);
            const heading = await page.locator(".bcrm-shell-content h1, .bcrm-shell-content h2, .bcrm-own h1, .bcrm-own h2, h1").count();
            ok(heading > 0, `${name} @${tag}: has a page heading`);
            if (tag === "1366" || tag === "768" || tag === "390") await page.screenshot({path: path.join(OUT, `${name}-${tag}.png`), fullPage: false});
            await page.close();
        }
        for (const c of Object.values(contexts)) await c.close();
    }
    ok(errors.length === 0, "no JavaScript errors on any screen (" + errors.slice(0, 3).join(" | ") + ")");

    console.log("== Shell & navigation");
    let ctx = await browser.newContext({viewport: {width: 1366, height: 900}}); await ctx.addCookies([{name: "bcrm_uid", value: "8", url: BASE}]);
    let p = await ctx.newPage(); await p.goto(BASE + "/portal?bcrm_view=customers");
    ok(await p.locator(".bcrm-nav-group-title").count() >= 3, "sidebar grouped into task sections");
    ok(await p.locator('.bcrm-shell-nav a.is-active[aria-current="page"]').count() === 1, "active navigation item exposes aria-current");
    ok((await p.title()).startsWith("مشتریان"), "browser tab title reflects the current page");
    ok(await p.locator('a[data-bcrm-view="ownership"]').count() === 1, "ownership entry injected once into the real shell");
    await p.keyboard.press("Tab");
    let focusOk = false;
    for (let i = 0; i < 6 && !focusOk; i++) { const o = await p.evaluate(() => { const a = document.activeElement; const cs = a ? getComputedStyle(a) : null; return a && a !== document.body && cs && (parseFloat(cs.outlineWidth) >= 2 || cs.boxShadow !== "none"); }); focusOk = !!o; if (!focusOk) await p.keyboard.press("Tab"); }
    ok(focusOk, "keyboard focus is visibly indicated");
    const row = p.locator(".bcrm-case-row.is-clickable").first();
    ok(await row.count() === 1, "customer rows are clickable targets");
    await Promise.all([p.waitForNavigation(), row.locator(".bcrm-case-identity").click()]);
    ok(p.url().includes("bcrm_customer="), "clicking a customer row opens the profile");
    await p.goBack(); ok(p.url().includes("bcrm_view=customers"), "browser back returns to the list");
    await p.goForward(); ok(p.url().includes("bcrm_customer="), "browser forward re-opens the profile");
    await p.reload(); ok((await p.content()).includes("پروفایل"), "refresh keeps the current page");
    const kpi = await p.evaluate(() => { const g = document.querySelector(".bcrm-summary"); if (!g) return null; const cols = getComputedStyle(g).gridTemplateColumns.split(" ").length; return [cols, g.children.length]; });
    ok(kpi && (kpi[1] % kpi[0] === 0 || kpi[1] < kpi[0] || kpi[1] % kpi[0] >= kpi[0] / 2), "profile KPI grid has no lone orphan card (" + JSON.stringify(kpi) + ")");
    await ctx.close();

    console.log("== Legacy search loading + lock decoration");
    ctx = await browser.newContext({viewport: {width: 1366, height: 900}}); await ctx.addCookies([{name: "bcrm_uid", value: "1", url: BASE}]);
    p = await ctx.newPage();
    await p.route("**/ajax", async (route) => { await new Promise((r) => setTimeout(r, 400)); await route.continue(); });
    await p.goto(BASE + "/legacy?customer=" + fixture.H.claimant);
    await p.click("[data-bcrm-toggle-related]"); await p.fill("[data-bcrm-related-query]", fixture.H.invoice_code);
    await p.click("[data-bcrm-related-search]");
    ok(await p.locator("[data-bcrm-related-results] .bcrm-loading-row").count() === 1, "related search shows a loading state");
    await p.waitForSelector(".bcrm-own-legacy-note", {timeout: 10000});
    ok(await p.locator("[data-bcrm-related-results] .bcrm-loading-row").count() === 0, "loading state replaced by results");
    await p.screenshot({path: path.join(OUT, "legacy-locked-result-1366.png")});
    await ctx.close();

    console.log("== Ownership interaction safety");
    ctx = await browser.newContext({viewport: {width: 1366, height: 900}}); await ctx.addCookies([{name: "bcrm_uid", value: "1", url: BASE}]);
    p = await ctx.newPage();
    await p.goto(BASE + "/portal?bcrm_view=ownership&own_tab=request");
    await p.route("**/ajax", (route) => route.abort());
    await p.fill("[data-own-lookup-q]", fixture.H.invoice_code); await p.click("[data-own-lookup]");
    await p.waitForTimeout(300);
    ok((await p.locator("[data-own-lookup-results]").textContent()).includes("ارتباط با سرور برقرار نشد"), "AJAX failure shows a Persian, actionable error");
    await p.unroute("**/ajax");
    const draft = await (await p.request.post(BASE + "/ajax", {form: {action: "bcrm_ownership", nonce: "nonce-bcrm_ownership_ui", op: "create_draft", source: String(fixture.E.source), claimant: String(fixture.E.claimant), reason: "آزمون ارسال تکراری"}})).json();
    await p.request.post(BASE + "/ajax", {form: {action: "bcrm_ownership", nonce: "nonce-bcrm_ownership_ui", op: "add_draft_asset", case_id: String(draft.data.case_id), version: "1", asset_type: "INVOICE", asset_id: String(fixture.E.sibling), phone_resolution: "KEEP_CURRENT_PHONE"}});
    await p.goto(BASE + "/portal?bcrm_view=ownership&own_case=" + draft.data.case_id);
    let submits = 0; p.on("request", (r) => { if (r.url().endsWith("/ajax") && /op=submit|name="op"\r\n\r\nsubmit/.test(r.postData() || "")) submits++; });
    await p.route("**/ajax", async (route) => { await new Promise((r) => setTimeout(r, 500)); await route.continue(); });
    const form = p.locator('form[data-own-op="submit"]');
    await form.locator('button[type="submit"]').click();
    await p.locator("dialog[data-own-dialog][open] [data-own-dialog-confirm]").click();
    await form.evaluate((f) => { f.requestSubmit(); f.requestSubmit(); });
    await p.waitForNavigation({timeout: 10000}).catch(() => null);
    ok(submits === 1, "repeated submission sends exactly one request (" + submits + ")");
    ok((await sql("SELECT status FROM test_bcrm_ownership_cases WHERE id=" + draft.data.case_id))[0].status === "SUBMITTED", "single submission applied once");
    await p.unroute("**/ajax");
    await p.setViewportSize({width: 390, height: 700}); await p.reload();
    const go = p.locator(".bcrm-own-sticky-go");
    if (await go.count()) { const box = await go.boundingBox(); ok(box && box.y < 700, "«رفتن به اقدام» reachable above the fold on mobile"); }
    else ok((await p.locator(".bcrm-own-sticky-next").textContent()).length > 0, "next step visible on mobile");
    await ctx.close();

    await browser.close();
    console.log("\n" + (failures.length ? "FAILED: " + failures.length + " of " + checks + " portal review checks" : "PASS: " + checks + " C3.11 portal review checks (" + pages.length + " screens × " + WIDTHS.length + " widths; headless Chromium; no WordPress runtime or MySQL claim)."));
    process.exit(failures.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
