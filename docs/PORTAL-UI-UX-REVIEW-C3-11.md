# Biawin CRM Portal — UI/UX Audit, Redesign & Interaction Pass (C3.11)

Branch `feature/ownership-c3-ui` · plugin version unchanged (0.8.65.19) · local/cloud test doubles only.
**No production changes were made.** Nothing here is a production-readiness claim.

## 1. Baseline commit

- Baseline: `372c601` — fix(ownership-c3): normalize final-plan payload by phone mode (C3.10).
- C2 frozen checkpoint: `38779e8` (`feature/ownership-reconciliation`, untouched).

## 2. Pages audited

All 30 screens were rendered in headless Chromium against the SQLite/WordPress-double harness (`tests/browser/`). Each was checked at 1440, 1366, 1024, 768, 430 and 390 px.

| Area | Screens |
|---|---|
| Daily work | dashboard, customers, customer search (hits), customer search (no results), customer profile (most invoices) |
| Workflows | deficiency (کارتابل رفع ناقصی), financial, complaints, service-cases |
| Refunds | refunds, refunded |
| Assignment & admin | assignments, users, services, service-units, settings |
| Data & reports | import, reports, data-audit, audit |
| Ownership | request wizard, my cases, specialist queue, supervisor High Risk queue, history, case under review, closed HIGH_RISK case, closed NORMAL case, access denied (uid 6) |
| Legacy | customer profile panel with legacy related search + Attach lock decoration |

## 3. Navigation map

The frozen shell (`Biawin_CRM_Frontend::render_shell`) renders a flat list of views. `portal-ui/portal.js` regroups it on the client. It does not change any link or `data-bcrm-view`, and nothing changes if JS is off.

```
Header: brand · global search (pill) · user menu · theme toggle · sidebar toggle
Sidebar
├─ کار روزانه        dashboard · customers → profile (?bcrm_customer=) · ownership (تطبیق مالکیت)
│                    · deficiency (کارتابل رفع ناقصی) · financial · complaints · service-cases
├─ عودت وجه          refunds · refunded
├─ تخصیص و مدیریت    assignments · users · services · service-units · settings
├─ داده و گزارش      import · reports · data-audit (ممیزی داده) · audit
└─ سایر              any future view not in a group (fallback, nothing is hidden)

Ownership (bcrm_view=ownership)
├─ own_tab=request     6-step wizard
├─ own_tab=mine        my cases
├─ own_tab=queue       specialist queue
├─ own_tab=supervisor  High Risk second approval
├─ own_tab=history     closed / cancelled
└─ own_case=<id>       case detail (sticky «گام بعدی», actions aside #own-actions)
WP admin: biawin-crm → biawin-crm-ownership (same workspaces)
Legacy profile panel → Attach on locked/held asset → C3.8 bridge → prefilled ownership request
```

Only views the user's capabilities already allow are rendered by the backend. The grouping never adds views.

## 4. UX issues found (defect log, before → after)

| # | Screen | Problem | Sev | Solution | Implemented |
|---|---|---|---|---|---|
| U1 | Case detail | «گام بعدی» and the user's own action sat below the fold on every width; on mobile it took 3–4 screens of scrolling to reach the actions | P1 | Sticky case bar under the back link (code, status/risk/execution badges, «گام بعدی», «رفتن به اقدام»), compact hero | Yes |
| U2 | Case detail | Primary and destructive actions were equally prominent and always expanded, so a reject/return could be mistaken for the main action | P1 | Primary action stays open; secondary/destructive actions are collapsed accordions with tone | Yes |
| U3 | Ownership confirm dialogs | The confirm text did not show which case, which parties or which assets would be affected | P1 | Dialog lists the case, «مالک فعلی ← مدعی», the assets, the consequence and whether the action can be reversed | Yes |
| U4 | Ownership forms | A double click could send two submits; the browser restored stale field values after back navigation | P1 | `data-own-busy` guard plus `autocomplete=off`; verified with a double `requestSubmit` → exactly one request | Yes |
| U5 | All portal pages | Flat 17-item sidebar with no grouping and no current-page signal for assistive technology | P2 | Four labelled groups plus `aria-current="page"` | Yes |
| U6 | All portal pages | `document.title` was the same on every view, so tabs and history were indistinguishable | P2 | Title is set to «<view> — Biawin CRM» | Yes |
| U7 | Lists (customers, ownership) | Only a small link in each row was clickable | P2 | The whole row navigates (to its first link or `data-own-href`); links and buttons inside keep their own behaviour | Yes |
| U8 | Classic POST forms | Repeated submission was possible on slow servers | P2 | Submit buttons are disabled after the form data is built, and re-enabled on bfcache `pageshow` | Yes |
| U9 | Ownership lists | Empty lists showed one generic line | P2 | Per-tab empty state (heading + hint, `role=status`) | Yes |
| U10 | Ownership tables | The roles «مبدأ/مقصد» were ambiguous | P2 | Columns «مالک فعلی (مبدأ)» / «مدعی (مقصد)»; the first case anchor is «مالک فعلی و مدعی» | Yes |
| U11 | High Risk case | The reason for High Risk and the required approver were not stated together | P2 | Sections «چرا پرونده پرریسک شده؟» and «چه کسی باید تأیید کند؟» | Yes |
| U12 | Case detail | Long timelines and long request notes made the page very tall | P3 | Timeline shows the last 8 events plus «نمایش N رویداد قدیمی‌تر»; notes over 220 characters collapse; the execution track is collapsed while NOT_STARTED | Yes |
| U13 | Sidebar copy | Mixed English: «Workflow رفع ناقصی», «Data Audit» | P3 | «کارتابل رفع ناقصی», «ممیزی داده (Data Audit)» (client-side copy map; frozen PHP untouched) | Yes |
| U14 | Legacy views | Empty-state and helper copy is hard-coded in the frozen PHP | P3 | Empty table rows (`td[colspan]:only-child`) are styled as empty states; the copy itself cannot change | Partial (UX debt) |

## 5. Visual issues found (before → after)

| # | Screen | Problem | Sev | Solution | Implemented |
|---|---|---|---|---|---|
| V1 | Case detail | «رفتن به اقدام» CTA had blue text on a blue background, so it was invisible | P1 | White text, no underline | Yes |
| V2 | Assignments @1024/768 | Horizontal page overflow; the execute grid pushed the page wider than the viewport | P1 | `.widefat` scrolls inside its container at ≤1100 px; the execute grid is `auto-fit minmax(220px)` | Yes |
| V3 | Users @768 | Columns collapsed to one character per line because cells used `overflow-wrap:anywhere` | P1 | `break-word` | Yes |
| V4 | Settings @390/430 | Inputs overflowed the viewport | P1 | Inputs get `max-width:100%` | Yes |
| V5 | Profile | The invoice table was clipped with no scroll cue | P2 | Wrapped scroll with a scroll shadow and sticky header | Yes |
| V6 | Dashboard / profile | KPI grid left a single orphan card on its own row | P2 | `auto-fit minmax(140px/150px)`; two columns on mobile | Yes |
| V7 | Shell | Large blank band under the header; header height was inconsistent | P2 | 64 px header and tightened content padding | Yes |
| V8 | Sidebar | Too tall: 17 items × large padding scrolled on 768-high screens | P2 | 36 px links, 2 px gap, group titles | Yes |
| V9 | Lists | Each row had several heavy primary buttons competing with the content | P2 | Buttons inside rows use the secondary style; one primary button per area | Yes |
| V10 | Deficiency tabs / filters | Dense underlined tabs wrapped to 3–4 lines on mobile | P2 | Pill tabs; a single swipeable no-wrap row ≤760 px | Yes |
| V11 | Ownership tables | Long names wrapped onto many lines | P3 | Two-line clamp with a `title` tooltip; compact case-code link | Yes |
| V12 | Status | Some badges relied on colour alone | P2 | Every badge carries a text label; hold/lock/active-case have distinct glyph + text | Yes |
| V13 | Mixed Persian/LTR | Phone numbers and codes reordered inside RTL cells | P2 | `unicode-bidi:plaintext` on cells and summary values; tabular numerals | Yes |
| V14 | Legacy related search | A second loader added in an early draft duplicated the frozen `.bcrm-loading-row` | P3 | The duplicate was removed and the frozen row styled (⏳ + muted text) | Yes |

## 6. Redesign decisions

- **Additive layer only.** Frozen markup is enhanced, never replaced. `Biawin_CRM_Portal_UI` only enqueues `portal-ui/portal.css` (dependency `biawin-crm-portal-fixes`) and `portal-ui/portal.js` at `wp_enqueue_scripts` priority 40, and only for logged-in users with `bcrm_work_cases`. It has no DB access and `portal.js` makes no server calls; the C3 static suite enforces both.
- **Design tokens** (`--p-*`) scoped to `.bcrm-portal-shell`, with dark-mode overrides under the frozen `.is-dark`.
- **One primary action per area.** Secondary actions are outlined; destructive actions are collapsed behind an explicit, toned accordion.
- **Status is text + shape + colour**, never colour alone. Hold (⏸ «توقف مشتری»), Lock (🔒 «قفل مالکیت») and Active Case («پرونده فعال») are separate badges with separate copy.
- **Actions are grouped by actor.** The sticky bar says whether the next step is yours (`is-you`, with «رفتن به اقدام») or someone else's (`is-other`, read-only text).
- **Progressive enhancement.** If `portal.js`/`ownership.js` fail, every link, form and route still works as before.

## 7. Screenshots reviewed

- 91 screenshots per run (`$BCRM_SHOTS/review`): 30 screens × 1366/768/390 plus the legacy locked-result shot. In addition, `ownership-flows.js` captures its own flow screenshots (`$BCRM_SHOTS`).
- Manually inspected at every iteration: dashboard, customers, search, empty search, profile, assignments, users, settings, deficiency, refunds, reports, all ownership tabs, case review (sticky bar, accordion, dialog), the closed High Risk case and the legacy panel.
- Each defect in §4/§5 marked "Yes" was re-inspected after its fix.

## 8. Responsive findings

| Width | Result after the pass |
|---|---|
| 1440 / 1366 | Sidebar + content; sticky case bar (`top:72px`); KPI grids without orphans |
| 1024 | Legacy wide tables scroll inside their container; the assign-execute grid reflows. No page overflow |
| 768 | Sidebar becomes the frozen off-canvas menu; tabs/chips become a swipeable row; users table readable |
| 430 / 390 | Ownership tables become cards; KPI grid has 2 columns; buttons ≥42 px; «رفتن به اقدام» reachable without scrolling the case |

Automated: 30 screens × 6 widths, with **no horizontal document overflow** on any of them (`scrollWidth ≤ clientWidth + 1`).

## 9. Accessibility findings

- Visible `:focus-visible` ring on links, buttons, inputs, summary and rows. Keyboard focus visibility is asserted by the review suite.
- `aria-current="page"` on the active nav item; nav group titles are presentational text, and the links keep their names.
- Dialogs: native modal `<dialog>` (focus trapped, Esc cancels natively), focus moved to «انصراف» on open and restored to the opener on close. The flow suite asserts dialog content and that it fits 390 px; Esc is native behaviour and is not separately asserted.
- Empty states use `role=status`; AJAX errors use `role=alert` («ارتباط با سرور برقرار نشد» is asserted on an aborted request).
- «رفتن به اقدام» moves focus to `#own-actions` (`tabindex=-1`).
- Clickable rows remain usable from the keyboard through their real inner link; the row click is a mouse/touch convenience only.
- `prefers-reduced-motion` disables transitions.
- **Not verified:** real screen readers (NVDA/VoiceOver), real devices, WCAG contrast measurement with a tool on the production theme.

## 10. Interaction improvements

| Interaction | Behaviour | Verified |
|---|---|---|
| Keyboard | Tab order and visible focus (asserted); Esc closes dialogs and Enter submits (native, not separately asserted) | review suite |
| Mouse/touch | Whole-row navigation; 42 px touch targets ≤760 px | review suite |
| Back/forward/refresh | The page renders the same view with no resubmission prompt; history title changes | review suite |
| Restored form state | `autocomplete=off` on ownership forms; buttons re-enabled on bfcache restore | flow suite |
| Repeated submission | Ownership: `data-own-busy` (exactly one request); classic forms: disabled after submit | review suite |
| Stale state | Backend version conflicts are surfaced as a Persian error and a reload hint (unchanged C3 behaviour) | flow suite |
| AJAX error | Aborted request → «ارتباط با سرور برقرار نشد», form re-enabled | review suite |
| Loading | Busy state on ownership submits; frozen `.bcrm-loading-row` for related search | review suite |
| Success | Ownership actions reload into the updated case with a success notice | flow suite |

## 11. Files changed (372c601 → HEAD)

| File | Purpose |
|---|---|
| `includes/class-biawin-crm-portal-ui.php` (new) | Enqueue the portal design system and script only |
| `portal-ui/portal.css` (new) | Design system, shell, tables, tabs, forms, responsive, dark mode |
| `portal-ui/portal.js` (new) | Nav grouping, `aria-current`, titles, clickable rows, submit guard, Persian copy |
| `biawin-crm-integration.php` | Require and init `Biawin_CRM_Portal_UI` (version unchanged) |
| `includes/class-biawin-crm-ownership-ui.php` | Sticky next-step bar, action accordions, empty states, role labels, risk headings, name span |
| `ownership-ui/ownership.css`, `ownership-ui/ownership.js` | Sticky bar, accordions, consequence dialog, busy guard, timeline collapse, clickable rows |
| `tests/browser/{bootstrap,router,seed}.php` | Harness renders every portal view (WP doubles, users/posts tables) |
| `tests/browser/ownership-flows.js` | Reveal collapsed actions; dialog-facts and accordion assertions |
| `tests/browser/portal-review.js` (new), `tests/browser/run.sh` | Portal-wide review suite |
| `tests/ownership-c3-static.py` | Allow-list plus C3.11 enqueue-only/no-server-call checks |
| `docs/PORTAL-UI-UX-REVIEW-C3-11.md`, `CHANGELOG.md`, `STRUCTURE.md` | Documentation |

## 12. Frozen-file verification

`git diff --quiet 38779e8 -- admin/class-biawin-crm-admin.php includes/class-biawin-crm-frontend.php assets` → **identical** (exit 0). The C2 static suite also re-asserts this. No backend class (Workflow, OTP, Transfer, Repository, Risk, Hold, Lock, Write_Guard, Invoice_Phone, Presenter semantics, AJAX ops, Legacy Bridge) was changed in C3.11.

## 13. Test matrix (final run on HEAD)

| Suite | Result |
|---|---|
| C1 `tests/ownership-c1.php` | PASS 179 |
| C2 `tests/ownership-c2.php` (includes the hardening checks) | PASS 304 |
| C3 UI/adapters `tests/ownership-c3-ui.php` | PASS 275 |
| C3.8 legacy bridge `tests/ownership-c3-legacy-bridge.php` | PASS 87 |
| C3.10 final plan `tests/ownership-c3-final-plan.php` | PASS 33 |
| Browser flows `tests/browser/ownership-flows.js` | PASS 272 |
| Portal review `tests/browser/portal-review.js` | PASS 558 (30 screens × 6 widths) |
| C2 static / C3 static (PHP lint on 64 files, frozen files, allow-list, C3.11 checks) | PASS |
| `node --check` on all UI and browser JS | PASS |
| `git diff --check` | PASS |

The environment was PHP 8.3 CLI, SQLite and WordPress doubles, and headless Chromium (Playwright). **No WordPress runtime, MySQL or real-browser/device acceptance is claimed**, and the local lab (`localhost:8088`) is not reachable from the cloud.

## 14. Remaining UX debt

1. Legacy view copy and markup are frozen, so empty states, helper text and column order in legacy views can only be restyled, not rewritten.
2. Nav grouping and Persian copy fixes run on the client, so there is a brief flash of the flat nav before JS runs. A server-side fix needs a frozen-file change, which is out of scope.
3. A specialist's own claims do not appear in «پرونده‌های من» (the list follows the backend's requester scope).
4. Ownership lists are capped at 1,000 cases by the backend list call, and there is no pagination UI.
5. Full-page screenshots capture fixed/sticky elements mid-page (a capture artifact, not a layout bug).
6. The hold glyph ⏸ renders as ■ in the headless test font. This is acceptable because the text label carries the meaning.
7. There has been no real screen-reader, device or contrast-tool verification.

## 15. Remaining functional defects

**None found in this pass.** No UI work exposed a backend problem requiring a "FUNCTIONAL DEFECT — NOT FIXED IN UI PASS" entry.

One note carried from C3.10 is not a defect: a case that was already raised to HIGH_RISK by the old payload bug stays HIGH_RISK. Risk never lowers, by design.

## 16. Readiness classification

**UI READY FOR LOCAL ACCEPTANCE**

The portal is ready to be accepted in the local WordPress/MySQL lab on real browsers and devices. This is not a production-readiness claim. **No production changes were made.**
