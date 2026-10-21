/* Biawin CRM — C3 Ownership Reconciliation (تطبیق مالکیت) UI controller.
 * Presentation only: every mutation is one POST to the bcrm_ownership AJAX adapter,
 * which calls the approved backend command. Nothing here changes ownership, computes
 * risk or decides legality; the page always reloads authoritative state afterwards. */
(function () {
    "use strict";
    var cfg = window.BCRM_OWN || null;
    if (!cfg) return;

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function esc(value) { return String(value == null ? "" : value).replace(/[&<>"']/g, function (m) { return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"})[m]; }); }
    function faToEn(value) { return String(value == null ? "" : value).replace(/[۰-۹]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹".indexOf(d); }).replace(/[٠-٩]/g, function (d) { return "٠١٢٣٤٥٦٧٨٩".indexOf(d); }); }
    function money(value) { try { return new Intl.NumberFormat("fa-IR", {maximumFractionDigits: 0}).format(Number(value || 0)) + " تومان"; } catch (e) { return String(value) + " تومان"; } }
    function num(value) { try { return new Intl.NumberFormat("fa-IR").format(Number(value || 0)); } catch (e) { return String(value); } }
    function badge(text, tone) { return '<span class="bcrm-badge bcrm-own-badge is-' + esc(tone) + '">' + esc(text) + "</span>"; }
    function caseUrl(id) { return String(cfg.caseUrl).replace("__ID__", encodeURIComponent(id)); }

    /* ------------------------------------------------------------ transport */
    function post(op, data) {
        var body = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) Object.keys(data || {}).forEach(function (k) { if (data[k] !== undefined && data[k] !== null) body.append(k, data[k]); });
        body.append("action", cfg.action);
        body.append("nonce", cfg.nonce);
        body.append("op", op);
        return fetch(cfg.ajaxUrl, {method: "POST", credentials: "same-origin", body: body}).then(function (res) {
            return res.text().then(function (text) {
                var json = null;
                try { json = JSON.parse(text); } catch (e) { json = null; }
                if (!json) return {ok: false, error: {code: "network", message: "پاسخ نامعتبر از سرور دریافت شد (" + res.status + "). صفحه را تازه‌سازی کنید."}};
                if (json.success) return {ok: true, data: json.data || {}};
                var err = json.data && typeof json.data === "object" ? json.data : {message: String(json.data || "خطای ناشناخته")};
                err.code = err.code || (res.status === 403 ? "permission" : "domain");
                return {ok: false, error: err};
            });
        }).catch(function () { return {ok: false, error: {code: "network", message: "ارتباط با سرور برقرار نشد. اتصال را بررسی و دوباره تلاش کنید."}}; });
    }

    /* ------------------------------------------------------------ flash + dialog */
    var FLASH = "bcrm_own_flash";
    function flashLater(message) { try { sessionStorage.setItem(FLASH, message); } catch (e) {} }
    function showFlash() {
        var host = qs("[data-own-flash]"); if (!host) return;
        var msg = null; try { msg = sessionStorage.getItem(FLASH); sessionStorage.removeItem(FLASH); } catch (e) {}
        if (msg) { host.innerHTML = '<div class="bcrm-own-alert is-success">' + esc(msg) + "</div>"; }
    }
    function confirmDialog(title, body) {
        var dialog = qs("[data-own-dialog]");
        if (!dialog || typeof dialog.showModal !== "function") return Promise.resolve(window.confirm(title + "\n\n" + body));
        var opener = document.activeElement;
        qs("[data-own-dialog-title]", dialog).textContent = title;
        qs("[data-own-dialog-body]", dialog).innerHTML = "<p>" + esc(body) + "</p>";
        return new Promise(function (resolve) {
            function done() {
                dialog.removeEventListener("close", done);
                resolve(dialog.returnValue === "confirm");
                if (opener && opener.focus) opener.focus();
            }
            dialog.returnValue = "";
            dialog.addEventListener("close", done);
            dialog.showModal();
            var cancel = qs("[data-own-dialog-cancel]", dialog); if (cancel) cancel.focus();
        });
    }
    function setBusy(button, busy, label) {
        if (!button) return;
        if (busy) { button.dataset.label = button.textContent; button.textContent = label || "در حال ارسال…"; button.setAttribute("aria-busy", "true"); button.disabled = true; }
        else { if (button.dataset.label) button.textContent = button.dataset.label; button.removeAttribute("aria-busy"); button.disabled = false; }
    }
    function showError(host, error) {
        if (!host) return;
        host.dataset.code = error.code || "";
        host.innerHTML = esc(error.message || "خطا") + (error.detail && error.detail !== error.message ? ' <small class="bcrm-own-code">' + esc(error.detail) + "</small>" : "");
        if (host.focus) { host.setAttribute("tabindex", "-1"); host.focus(); }
    }
    function markStale(root) {
        var banner = qs("[data-own-stale]", root || document);
        if (banner) { banner.hidden = false; banner.setAttribute("tabindex", "-1"); banner.focus(); }
    }
    document.addEventListener("click", function (e) { if (e.target.closest("[data-own-reload]")) { window.location.reload(); } });

    /* ------------------------------------------------------------ list filters */
    function initLists() {
        qsa("[data-own-list]").forEach(function (list) {
            var chips = qsa("[data-own-filter]", list), search = qs("[data-own-search]", list), rows = qsa("tbody tr", list), empty = qs("[data-own-empty]", list);
            var current = list.getAttribute("data-own-default") || "all";
            function apply() {
                var text = (search && search.value || "").trim().toLowerCase(), shown = 0;
                rows.forEach(function (row) {
                    var groups = (" " + row.getAttribute("data-own-groups") + " ");
                    var ok = (current === "all" || groups.indexOf(" " + current + " ") !== -1) && (!text || row.getAttribute("data-own-text").toLowerCase().indexOf(text) !== -1);
                    row.hidden = !ok; if (ok) shown++;
                });
                if (empty) empty.hidden = shown !== 0;
                chips.forEach(function (chip) { chip.setAttribute("aria-pressed", chip.getAttribute("data-own-filter") === current ? "true" : "false"); });
            }
            chips.forEach(function (chip) { chip.addEventListener("click", function () { current = chip.getAttribute("data-own-filter"); apply(); }); });
            if (search) search.addEventListener("input", apply);
            apply();
        });
    }

    /* ------------------------------------------------------------ case actions */
    function validateRequired(form) {
        var first = null;
        qsa("[required]", form).forEach(function (field) {
            var bad = field.type === "radio" ? !qs('input[name="' + field.name + '"]:checked', form) : field.type === "file" ? !field.files.length : !String(field.value || "").trim();
            field.setAttribute("aria-invalid", bad ? "true" : "false");
            if (bad && !first) first = field;
        });
        return first;
    }
    function planChanged(form) {
        var mode = qs('input[name="phone_resolution"]:checked', form);
        return (qs('[name="action"]', form).value !== form.dataset.reqAction) ||
            ((mode ? mode.value : "") !== form.dataset.reqMode) ||
            (String(Number(qs('[name="phone_id"]', form).value || 0)) !== String(Number(form.dataset.reqPid || 0))) ||
            ((qs('[name="phone"]', form).value || "").trim() !== (form.dataset.reqPhone || ""));
    }
    function validatePlan(form) {
        var errors = [], mode = qs('input[name="phone_resolution"]:checked', form), reason = qs('[name="reason"]', form);
        var pid = qs('[name="phone_id"]', form), phone = qs('[name="phone"]', form);
        [pid, phone, reason].forEach(function (f) { f.setAttribute("aria-invalid", "false"); });
        if (!mode) errors.push("روش تعیین شماره فاکتور را انتخاب کنید.");
        if (mode && mode.value === "USE_TARGET_PROFILE_PHONE" && !pid.value) { pid.setAttribute("aria-invalid", "true"); errors.push("شماره پروفایل مقصد را انتخاب کنید."); }
        if (mode && mode.value === "PROPOSE_NEW_PHONE" && !phone.value.trim()) { phone.setAttribute("aria-invalid", "true"); errors.push("شماره جدید را وارد کنید."); }
        if (planChanged(form) && !reason.value.trim()) { reason.setAttribute("aria-invalid", "true"); errors.push("برنامه نهایی با درخواست متفاوت است؛ ثبت دلیل تغییر الزامی است."); }
        return errors;
    }
    function initCase() {
        var root = qs("[data-own-case]"); if (!root) return;
        var caseId = root.getAttribute("data-own-case");
        function version() { return root.getAttribute("data-own-version"); }
        qsa("[data-own-plan]", root).forEach(function (form) {
            var hint = qs("[data-own-plan-hint]", form);
            function sync() { var changed = planChanged(form); qs('[name="reason"]', form).required = changed; if (hint) hint.textContent = changed ? "(برنامه با درخواست متفاوت است — دلیل الزامی است)" : "(در صورت تغییر نسبت به درخواست الزامی است)"; }
            form.addEventListener("change", sync); form.addEventListener("input", sync); sync();
        });
        qsa("[data-own-otp-challenge]", root).forEach(function (select) {
            var form = select.form;
            function sync() { var o = select.options[select.selectedIndex]; if (!o) return; qs('[name="phone_asset"]', form).value = o.getAttribute("data-phone-asset"); qs('[name="purpose"]', form).value = o.getAttribute("data-purpose"); }
            select.addEventListener("change", sync); sync();
        });
        qsa("form[data-own-op]", root).forEach(function (form) {
            form.addEventListener("submit", function (e) {
                e.preventDefault();
                var errorHost = qs("[data-own-form-error]", form), button = qs('button[type="submit"]', form);
                if (errorHost) errorHost.innerHTML = "";
                var op = form.getAttribute("data-own-op");
                var missing = validateRequired(form);
                if (missing) { showError(errorHost, {message: "فیلدهای الزامی را تکمیل کنید."}); missing.focus(); return; }
                if (form.hasAttribute("data-own-plan")) { var errs = validatePlan(form); if (errs.length) { showError(errorHost, {message: errs.join(" ")}); return; } }
                var ask = form.getAttribute("data-own-confirm");
                var title = (qs("h4", form) || button || {}).textContent || "تأیید اقدام";
                (ask ? confirmDialog(title, ask) : Promise.resolve(true)).then(function (yes) {
                    if (!yes) return;
                    var data = new FormData(form);
                    data.append("case_id", caseId);
                    data.append("version", version());
                    var progress = qs("[data-own-progress]", form); if (progress) progress.hidden = false;
                    setBusy(button, true, form.hasAttribute("data-own-upload") ? "در حال بارگذاری…" : "در حال ثبت…");
                    post(op, data).then(function (res) {
                        if (progress) progress.hidden = true;
                        if (res.ok) {
                            if (op === "otp_verify") flashLater(res.data.verified ? "کد تأیید شد. (تأیید کد به‌تنهایی مالکیت را منتقل نمی‌کند.)" : "کد تأیید نشد؛ تلاش ثبت شد.");
                            else if (op === "otp_issue") flashLater("درخواست ارسال کد ثبت شد؛ وضعیت تحویل را در جدول OTP بررسی کنید.");
                            else flashLater((title || "اقدام").trim() + " با موفقیت ثبت شد.");
                            window.location.reload();
                            return;
                        }
                        setBusy(button, false);
                        showError(errorHost, res.error);
                        var fresh = res.error.fresh;
                        if (res.error.code === "stale" || res.error.code === "state" || (fresh && fresh.version !== null && String(fresh.version) !== String(version()))) markStale(root);
                    });
                });
            });
        });
        qsa("[data-own-picker]", root).forEach(function (host) {
            var mode = host.getAttribute("data-own-picker-mode");
            createPicker(host, {
                mode: mode, caseId: caseId, version: version,
                source: {id: Number(root.getAttribute("data-own-source"))},
                claimant: {id: Number(root.getAttribute("data-own-claimant"))},
                existing: (host.getAttribute("data-own-existing") || "").split(",").filter(Boolean),
                existingPhones: (host.getAttribute("data-own-existing-phones") || "").split(",").filter(Boolean),
                onStale: function () { markStale(root); }
            });
        });
    }

    /* ------------------------------------------------------------ profile card */
    function profileCard(p, role) {
        if (!p) return "";
        var html = '<div class="bcrm-own-profile is-' + role + '"><span class="bcrm-own-kicker">' + (role === "source" ? "مبدأ — مالک فعلی" : "مقصد — مدعی") + "</span><h4>" + esc(p.name) + (p.code ? ' <small class="bcrm-own-code">' + esc(p.code) + "</small>" : "") + "</h4>";
        if (p.holds && p.holds.length) html += '<div class="bcrm-own-alert is-hold">' + badge("توقف فعال", "warning") + " این پروفایل در " + num(p.holds.length) + " پرونده تطبیق فعال است (" + esc(p.holds.map(function (h) { return h.case_code; }).join("، ")) + "). " + esc(cfg.holdText) + "</div>";
        if (p.restricted) return html + '<p class="bcrm-own-muted">جزئیات مالی و شماره‌های این پروفایل برای شما قابل مشاهده نیست؛ کارشناس تطبیق مالکیت آن را بررسی می‌کند.</p></div>';
        var completeness = {complete: "کامل", incomplete: "ناقص", pending: "در انتظار"}[p.completeness] || p.completeness || "—";
        html += '<dl class="bcrm-own-facts is-compact"><div><dt>وضعیت تکمیل</dt><dd>' + esc(completeness) + "</dd></div><div><dt>جمع پرداختی ثبت‌شده</dt><dd>" + esc(money(p.total_paid)) + "</dd></div><div><dt>مبلغ اعلامی</dt><dd>" + esc(money(p.declared_paid)) + "</dd></div><div><dt>مغایرت</dt><dd>" + esc(money(p.discrepancy)) + "</dd></div><div><dt>فاکتورهای دارای قفل مالکیت</dt><dd>" + esc(num(p.locked_invoices)) + "</dd></div></dl>";
        html += '<h5>شماره‌ها</h5><ul class="bcrm-own-phones">' + ((p.phones || []).map(function (ph) { return '<li><bdi dir="ltr">' + esc(ph.phone) + "</bdi> " + (ph.primary ? badge("اصلی", "primary") : "") + badge(ph.status === "verified" ? "تأییدشده" : (ph.status === "unverified" ? "تأییدنشده" : (ph.status || "نامشخص")), "neutral") + "</li>"; }).join("") || '<li class="bcrm-own-muted">بدون شماره</li>') + "</ul></div>";
        return html;
    }

    /* ------------------------------------------------------------ asset picker */
    function key(type, id) { return type + ":" + id; }
    function invoiceWhy(inv, ctx) {
        if (ctx.source && ctx.source.id && inv.owner.id !== ctx.source.id) return "این دارایی متعلق به پروفایل مبدأ نیست.";
        if (ctx.claimant && ctx.claimant.id && inv.owner.id === ctx.claimant.id) return "این دارایی هم‌اکنون در پروفایل مقصد است.";
        if (inv.active_case) return "این دارایی در پرونده تطبیق مالکیت فعال دیگری است.";
        return "";
    }
    function createPicker(host, ctx) {
        var selected = {}, order = [], results = {invoices: [], phones: []}, claimantPhones = null;
        var mode = ctx.mode || "wizard";
        host.innerHTML = '<div class="bcrm-own-searchbar"><label for="own-pick-' + mode + '">جست‌وجوی فاکتور یا شماره در پروفایل مبدأ</label><div><input id="own-pick-' + mode + '" type="search" data-q autocomplete="off" placeholder="کد فاکتور یا شماره موبایل"><button type="button" class="bcrm-secondary-button" data-search>جست‌وجو</button></div></div>' +
            '<div class="bcrm-own-results" data-results aria-live="polite"></div><h4 data-sel-title>دارایی‌های انتخاب‌شده</h4><div class="bcrm-own-selected" data-selected></div>' +
            (mode === "amend" ? '<label for="own-amend-reason">دلیل اصلاحیه <small>(الزامی)</small></label><textarea id="own-amend-reason" rows="2" data-amend-reason></textarea>' : "") +
            (mode === "wizard" ? "" : '<button type="button" class="bcrm-primary-button" data-commit>' + (mode === "amend" ? "ثبت اصلاحیه رسمی" : "افزودن به پیش‌نویس") + '</button>') +
            '<div class="bcrm-own-form-error" data-error role="alert"></div>';
        var q = qs("[data-q]", host), out = qs("[data-results]", host), sel = qs("[data-selected]", host), errHost = qs("[data-error]", host);

        function loadClaimantPhones() {
            if (claimantPhones || !ctx.claimant || !ctx.claimant.id) return Promise.resolve(claimantPhones || []);
            return post("profile_summary", {customer: ctx.claimant.id, case_id: ctx.caseId || ""}).then(function (res) { claimantPhones = res.ok && !res.data.restricted ? (res.data.phones || []) : []; return claimantPhones; });
        }
        function renderResults(note) {
            var html = note ? '<p class="bcrm-own-muted">' + esc(note) + "</p>" : "";
            results.invoices.forEach(function (inv) {
                var why = invoiceWhy(inv, ctx), k = key("INVOICE", inv.id), chosen = !!selected[k] || ctx.existing.indexOf(k) !== -1;
                html += '<div class="bcrm-own-result' + (why ? " is-disabled" : "") + (chosen ? " is-selected" : "") + '"><div class="bcrm-own-result-main"><strong>فاکتور <bdi>' + esc(inv.code) + "</bdi></strong> " + (inv.locked ? badge("قفل مالکیت", "warning") : "") + (inv.ambiguous ? badge("شماره تاریخی مبهم", "danger") : "") +
                    "<p>مبلغ: " + esc(money(inv.amount)) + " · مالک: " + esc(inv.owner.name) + (inv.owner.code ? " (" + esc(inv.owner.code) + ")" : "") + "</p>" +
                    "<p>شماره رسمی: " + (inv.canonical ? '<bdi dir="ltr">' + esc(inv.canonical) + "</bdi>" : "ثبت نشده") + " · شماره‌های سوابق: " + (inv.phones.length ? '<bdi dir="ltr">' + esc(inv.phones.join("، ")) + "</bdi>" : "—") + "</p>" +
                    "<p>خدمات وابسته: " + esc(inv.services.length ? inv.services.join("، ") : "بدون خدمت") + "</p>" +
                    (inv.locked ? "<p>" + esc(cfg.lockText) + "</p>" : "") + (why ? '<p class="bcrm-own-result-why">' + esc(why) + "</p>" : "") +
                    '</div><button type="button" class="bcrm-secondary-button" data-pick="' + esc(k) + '"' + (why || chosen ? " disabled" : "") + ">" + (chosen ? "انتخاب‌شده" : "انتخاب این فاکتور") + "</button></div>";
            });
            results.phones.forEach(function (ph) {
                var why = invoiceWhy(ph, ctx), k = key("PHONE", ph.id), chosen = !!selected[k] || ctx.existing.indexOf(k) !== -1;
                html += '<div class="bcrm-own-result' + (why ? " is-disabled" : "") + (chosen ? " is-selected" : "") + '"><div class="bcrm-own-result-main"><strong>شماره <bdi dir="ltr">' + esc(ph.phone) + "</bdi></strong> " + (ph.primary ? badge("اصلی", "primary") : "") + badge(ph.status === "verified" ? "تأییدشده" : (ph.status || "نامشخص"), "neutral") + (ph.locked ? badge("قفل مالکیت", "warning") : "") +
                    "<p>مالک: " + esc(ph.owner.name) + (ph.owner.code ? " (" + esc(ph.owner.code) + ")" : "") + "</p>" + (why ? '<p class="bcrm-own-result-why">' + esc(why) + "</p>" : "") +
                    '</div><button type="button" class="bcrm-secondary-button" data-pick="' + esc(k) + '"' + (why || chosen ? " disabled" : "") + ">" + (chosen ? "انتخاب‌شده" : "ادعای مالکیت خود شماره") + "</button></div>";
            });
            if (!results.invoices.length && !results.phones.length) html += '<div class="bcrm-own-empty"><strong>نتیجه‌ای یافت نشد.</strong><p>کد فاکتور یا شماره موبایل را کامل وارد کنید.</p></div>';
            out.innerHTML = html;
        }
        function search(term) {
            term = faToEn(term || q.value).trim();
            if (term.length < 2) { out.innerHTML = '<p class="bcrm-own-field-error">حداقل دو نویسه وارد کنید.</p>'; return Promise.resolve(); }
            out.innerHTML = '<p class="bcrm-own-muted" role="status">در حال جست‌وجو…</p>';
            return post("lookup", {query: term}).then(function (res) {
                if (!res.ok) { out.innerHTML = ""; showError(errHost, res.error); return; }
                results = res.data;
                var note = results.phone_usage_count > 1 ? "این شماره در " + num(results.phone_usage_count) + " فاکتور استفاده شده است. مشترک بودن شماره به معنی انتقال فاکتورهای دیگر نیست؛ هر فاکتور را جداگانه انتخاب کنید." : "";
                renderResults(note);
            });
        }
        function add(k) {
            if (selected[k]) return;
            var parts = k.split(":"), list = parts[0] === "INVOICE" ? results.invoices : results.phones, item = null;
            list.forEach(function (x) { if (String(x.id) === parts[1]) item = x; });
            if (!item || invoiceWhy(item, ctx)) return;
            selected[k] = {type: parts[0], data: item, action: "TRANSFER", mode: "", phone_id: "", phone: "", proposedInfo: ""};
            order.push(k);
            renderResults(); renderSelected();
        }
        function remove(k) { delete selected[k]; order = order.filter(function (x) { return x !== k; }); renderResults(); renderSelected(); }
        function phoneSelected(number) {
            if (ctx.existingPhones.indexOf(number) !== -1) return true;
            return order.some(function (k) { var s = selected[k]; return s.type === "PHONE" && s.data.phone === number && s.action === "TRANSFER"; });
        }
        function renderSelected() {
            if (!order.length) { sel.innerHTML = '<p class="bcrm-own-muted">هنوز دارایی انتخاب نشده است.</p>'; return; }
            sel.innerHTML = order.map(function (k) {
                var s = selected[k], d = s.data, id = k.replace(":", "-") + "-" + mode;
                var html = '<article class="bcrm-own-selected-item" data-sel="' + esc(k) + '"><header><strong>' + (s.type === "INVOICE" ? "فاکتور <bdi>" + esc(d.code) + "</bdi>" : 'شماره <bdi dir="ltr">' + esc(d.phone) + "</bdi>") + '</strong><button type="button" class="bcrm-secondary-button" data-unpick="' + esc(k) + '">حذف از انتخاب</button></header>';
                html += '<label for="act-' + id + '">اقدام درخواستی</label><select id="act-' + id + '" data-field="action">' + Object.keys(cfg.actions).map(function (a) { return '<option value="' + a + '"' + (s.action === a ? " selected" : "") + ">" + esc(cfg.actions[a]) + "</option>"; }).join("") + "</select>";
                if (s.type === "PHONE") {
                    html += '<p class="bcrm-own-muted">' + (s.action === "TRANSFER" ? "با اجرای تأییدشده، مالکیت خود این شماره از مبدأ به مقصد منتقل می‌شود. سطح ریسک را سیستم تعیین می‌کند." : "مالکیت این شماره منتقل نمی‌شود؛ فقط بررسی می‌شود.") + "</p>";
                    return html + "</article>";
                }
                var current = d.current_phone || d.canonical || "";
                html += '<fieldset class="bcrm-own-modes" aria-describedby="err-' + id + '"><legend>روش تعیین شماره فاکتور (الزامی)</legend>';
                Object.keys(cfg.phoneModes).forEach(function (m) {
                    var blocked = d.ambiguous && (m === "KEEP_CURRENT_PHONE" || m === "CLAIM_CURRENT_PHONE");
                    html += '<label class="bcrm-own-mode"><input type="radio" name="mode-' + id + '" value="' + m + '" data-field="mode"' + (s.mode === m ? " checked" : "") + (blocked ? " disabled" : "") + '><span><strong>' + esc(cfg.phoneModes[m]) + "</strong><small>" + esc(cfg.phoneModeHelp[m]) + (blocked ? " — به‌دلیل مبهم بودن شماره تاریخی این فاکتور در دسترس نیست." : "") + "</small></span></label>";
                });
                html += "</fieldset>";
                if (s.mode === "KEEP_CURRENT_PHONE" || s.mode === "CLAIM_CURRENT_PHONE") html += '<p>شماره فعلی فاکتور: <bdi dir="ltr">' + esc(current || "—") + "</bdi></p>";
                if (s.mode === "CLAIM_CURRENT_PHONE") {
                    html += phoneSelected(current) ? '<p class="bcrm-own-muted">✓ خود شماره به‌عنوان دارایی جداگانه برای انتقال انتخاب شده است؛ مالکیت شماره هم منتقل می‌شود.</p>'
                        : '<p class="bcrm-own-field-error">برای این گزینه باید خود شماره را جداگانه به‌عنوان دارایی انتخاب کنید.</p><button type="button" class="bcrm-secondary-button" data-claim-phone="' + esc(current) + '">افزودن خود شماره به‌عنوان دارایی</button>';
                }
                if (s.mode === "USE_TARGET_PROFILE_PHONE") {
                    html += '<label for="pid-' + id + '">شماره پروفایل مقصد</label><select id="pid-' + id + '" data-field="phone_id"><option value="">— انتخاب —</option>' + (claimantPhones || []).map(function (p) { return '<option value="' + p.id + '"' + (String(s.phone_id) === String(p.id) ? " selected" : "") + ">" + esc(p.phone) + "</option>"; }).join("") + "</select>";
                    if (claimantPhones && !claimantPhones.length) html += '<p class="bcrm-own-field-error">پروفایل مقصد شماره‌ای ندارد یا شماره‌های آن برای شما قابل مشاهده نیست.</p>';
                    html += '<p class="bcrm-own-muted">مالکیت هیچ شماره‌ای جابه‌جا نمی‌شود.</p>';
                }
                if (s.mode === "PROPOSE_NEW_PHONE") {
                    html += '<label for="ph-' + id + '">شماره جدید</label><input id="ph-' + id + '" dir="ltr" inputmode="tel" data-field="phone" value="' + esc(s.phone) + '"><div class="bcrm-own-muted" data-proposed-info>' + s.proposedInfo + "</div>";
                }
                if (s.mode === "KEEP_CURRENT_PHONE") html += '<p class="bcrm-own-muted">مالکیت خود شماره در پروفایل مبدأ باقی می‌ماند.</p>';
                return html + '<span class="bcrm-own-field-error" id="err-' + id + '" data-sel-error></span></article>';
            }).join("");
        }
        function checkProposed(k) {
            var s = selected[k]; if (!s || !s.phone.trim()) return;
            post("normalize_phone", {phone: faToEn(s.phone)}).then(function (res) {
                if (!res.ok || !res.data.valid) { s.proposedInfo = '<span class="bcrm-own-field-error">شماره معتبر نیست.</span>'; s.normalized = ""; renderSelected(); return; }
                s.normalized = res.data.normalized;
                post("lookup", {query: res.data.normalized}).then(function (r2) {
                    var owner = null;
                    if (r2.ok) (r2.data.phones || []).forEach(function (p) { if (p.phone === res.data.normalized) owner = p.owner; });
                    var info = 'شماره استاندارد: <bdi dir="ltr">' + esc(res.data.normalized) + "</bdi>. ";
                    if (!owner) info += "این شماره در سیستم ثبت نیست و پس از اجرای تأییدشده در پروفایل مقصد ایجاد می‌شود.";
                    else if (ctx.claimant && owner.id === ctx.claimant.id) info += "این شماره هم‌اکنون در پروفایل مقصد است؛ گزینه «استفاده از شماره موجود پروفایل مقصد» را بررسی کنید.";
                    else if (ctx.source && owner.id === ctx.source.id) info += '<span class="bcrm-own-field-error">این شماره متعلق به پروفایل مبدأ است؛ باید خود شماره را جداگانه به‌عنوان دارایی ادعا کنید.</span>';
                    else info += '<span class="bcrm-own-field-error">این شماره متعلق به پروفایل دیگری (' + esc(owner.name) + ") است؛ ادعای آن نیازمند پرونده جداگانه با طرفین صحیح است.</span>";
                    s.proposedInfo = info + " بررسی نهایی توسط سیستم انجام می‌شود.";
                    renderSelected();
                });
            });
        }
        function claimPhone(number) {
            if (!number) return;
            post("lookup", {query: number}).then(function (res) {
                if (!res.ok) { showError(errHost, res.error); return; }
                var found = null;
                (res.data.phones || []).forEach(function (p) { if (p.phone === number) found = p; });
                if (!found) { showError(errHost, {message: "رکورد این شماره در سیستم یافت نشد."}); return; }
                results.phones = results.phones.filter(function (p) { return p.id !== found.id; }).concat([found]);
                var why = invoiceWhy(found, ctx);
                if (why) { showError(errHost, {message: why}); renderResults(); return; }
                add(key("PHONE", found.id));
            });
        }
        function validate() {
            var errors = [];
            qsa("[aria-invalid]", sel).forEach(function (f) { f.setAttribute("aria-invalid", "false"); });
            order.forEach(function (k) {
                var s = selected[k], node = qs('[data-sel="' + k + '"]', sel), err = node ? qs("[data-sel-error]", node) : null, msg = "";
                if (s.type === "INVOICE") {
                    if (!s.mode) msg = "روش تعیین شماره این فاکتور را صریحاً انتخاب کنید.";
                    else if (s.mode === "USE_TARGET_PROFILE_PHONE" && !s.phone_id) msg = "شماره پروفایل مقصد را انتخاب کنید.";
                    else if (s.mode === "PROPOSE_NEW_PHONE" && !s.phone.trim()) msg = "شماره جدید را وارد کنید.";
                    else if (s.mode === "PROPOSE_NEW_PHONE" && s.normalized === "") msg = "شماره جدید معتبر نیست.";
                    else if (s.mode === "CLAIM_CURRENT_PHONE" && !phoneSelected(s.data.current_phone || s.data.canonical || "")) msg = "برای «انتقال مالکیت شماره فعلی» خود شماره را به‌عنوان دارایی انتخاب کنید.";
                }
                if (err) err.textContent = msg;
                if (msg) { errors.push(msg); if (node) { var f = qs("input,select", node); if (f) f.setAttribute("aria-invalid", "true"); } }
            });
            return errors;
        }
        function inputs() {
            return order.map(function (k) {
                var s = selected[k], o = {asset_type: s.type, asset_id: s.data.id, requested_action: s.action};
                if (s.type === "INVOICE") { o.phone_resolution = s.mode; o.phone_id = s.mode === "USE_TARGET_PROFILE_PHONE" ? s.phone_id : ""; o.phone = s.mode === "PROPOSE_NEW_PHONE" ? faToEn(s.phone).trim() : ""; }
                return o;
            });
        }
        host.addEventListener("click", function (e) {
            var t = e.target;
            if (t.closest("[data-search]")) search();
            var pick = t.closest("[data-pick]"); if (pick) add(pick.getAttribute("data-pick"));
            var unpick = t.closest("[data-unpick]"); if (unpick) remove(unpick.getAttribute("data-unpick"));
            var claim = t.closest("[data-claim-phone]"); if (claim) claimPhone(claim.getAttribute("data-claim-phone"));
            if (t.closest("[data-commit]")) commit(t.closest("[data-commit]"));
        });
        q.addEventListener("keydown", function (e) { if (e.key === "Enter") { e.preventDefault(); search(); } });
        host.addEventListener("change", function (e) {
            var node = e.target.closest("[data-sel]"); if (!node) return;
            var s = selected[node.getAttribute("data-sel")], field = e.target.getAttribute("data-field");
            if (field === "action") s.action = e.target.value;
            if (field === "mode") { s.mode = e.target.value; if (s.mode === "USE_TARGET_PROFILE_PHONE") { loadClaimantPhones().then(renderSelected); return; } }
            if (field === "phone_id") s.phone_id = e.target.value;
            if (field === "phone") { s.phone = e.target.value; s.normalized = undefined; checkProposed(node.getAttribute("data-sel")); return; }
            renderSelected();
            var again = qs('[data-sel="' + node.getAttribute("data-sel") + '"] [data-field="' + field + '"]' + (field === "mode" ? ":checked" : ""), sel); if (again) again.focus();
        });
        function commit(button) {
            errHost.innerHTML = "";
            if (!order.length) { showError(errHost, {message: "حداقل یک دارایی انتخاب کنید."}); return; }
            var errors = validate(); if (errors.length) { showError(errHost, {message: errors[0]}); return; }
            if (mode === "amend") {
                var reason = qs("[data-amend-reason]", host);
                if (!reason.value.trim()) { reason.setAttribute("aria-invalid", "true"); showError(errHost, {message: "دلیل اصلاحیه الزامی است."}); reason.focus(); return; }
                confirmDialog("ثبت اصلاحیه رسمی", "دارایی‌های انتخاب‌شده با اصلاحیه رسمی به پرونده اضافه می‌شوند. گسترش دامنه پرونده توسط سیستم در ارزیابی ریسک لحاظ می‌شود. ادامه می‌دهید؟").then(function (yes) {
                    if (!yes) return;
                    setBusy(button, true, "در حال ثبت…");
                    post("amend", {case_id: ctx.caseId, version: ctx.version(), assets: JSON.stringify(inputs()), reason: reason.value}).then(function (res) {
                        if (res.ok) { flashLater("اصلاحیه رسمی ثبت شد."); window.location.reload(); return; }
                        setBusy(button, false); showError(errHost, res.error); if (res.error.code === "stale") ctx.onStale();
                    });
                });
                return;
            }
            setBusy(button, true, "در حال افزودن…");
            var list = inputs(), version = ctx.version();
            (function next(i) {
                if (i >= list.length) { flashLater("دارایی‌ها به پیش‌نویس اضافه شد."); window.location.reload(); return; }
                var payload = list[i]; payload.case_id = ctx.caseId; payload.version = version;
                post("add_draft_asset", payload).then(function (res) {
                    if (!res.ok) { setBusy(button, false); showError(errHost, res.error); if (i > 0) { errHost.innerHTML += " " + esc(num(i) + " دارایی اضافه شد؛ صفحه را تازه‌سازی کنید."); ctx.onStale(); } else if (res.error.code === "stale") ctx.onStale(); return; }
                    version = res.data.version; next(i + 1);
                });
            })(0);
        }
        renderSelected();
        return {
            setSource: function (src) { ctx.source = src; order.slice().forEach(function (k) { if (selected[k].data.owner.id !== src.id) remove(k); }); renderResults(); },
            setClaimant: function (c) { if (!ctx.claimant || ctx.claimant.id !== c.id) { claimantPhones = null; order.forEach(function (k) { if (selected[k].mode === "USE_TARGET_PROFILE_PHONE") selected[k].phone_id = ""; }); } ctx.claimant = c; renderResults(); renderSelected(); },
            seed: function (data) { results = data; renderResults(); },
            search: search, validate: validate, inputs: inputs,
            items: function () { return order.map(function (k) { return selected[k]; }); },
            loadClaimantPhones: loadClaimantPhones
        };
    }

    /* ------------------------------------------------------------ request wizard */
    function initWizard() {
        var root = qs("[data-own-wizard]"); if (!root) return;
        var state = {step: 1, source: null, claimant: null, lookup: null};
        var picker = createPicker(qs("[data-own-picker]", root), {mode: "wizard", source: {id: 0}, claimant: {id: 0}, existing: [], existingPhones: [], onStale: function () {}});
        function go(step) {
            state.step = step;
            qsa("[data-own-step]", root).forEach(function (s) { s.hidden = Number(s.getAttribute("data-own-step")) !== step; });
            qsa("[data-own-step-nav]", root).forEach(function (li) {
                var n = Number(li.getAttribute("data-own-step-nav"));
                if (n === step) li.setAttribute("aria-current", "step"); else li.removeAttribute("aria-current");
                li.classList.toggle("is-done", n < step);
            });
            var heading = qs('[data-own-step="' + step + '"] h3', root); if (heading) { heading.setAttribute("tabindex", "-1"); heading.focus(); }
            if (step === 3 && state.lookup) picker.seed(state.lookup);
            if (step === 5) renderReview();
        }
        function stepError(step, message) {
            var host = qs('[data-own-step="' + step + '"] [data-own-err]', root) || qs("[data-own-wizard-error]", root);
            if (host) host.textContent = message;
        }
        function nextEnabled(step, ok) { var b = qs('[data-own-step="' + step + '"] [data-own-next]', root); if (b) b.disabled = !ok; }

        // Step 1 — source discovered from the asset owner.
        function lookupSource() {
            var q = qs("[data-own-lookup-q]", root), out = qs("[data-own-lookup-results]", root);
            var term = faToEn(q.value).trim();
            if (term.length < 2) { out.innerHTML = '<p class="bcrm-own-field-error">حداقل دو نویسه وارد کنید.</p>'; return; }
            out.innerHTML = '<p class="bcrm-own-muted" role="status">در حال جست‌وجو…</p>';
            post("lookup", {query: term}).then(function (res) {
                if (!res.ok) { out.innerHTML = '<p class="bcrm-own-field-error">' + esc(res.error.message) + "</p>"; return; }
                state.lookup = res.data;
                var owners = {};
                res.data.invoices.concat(res.data.phones).forEach(function (a) {
                    var o = owners[a.owner.id] || (owners[a.owner.id] = {owner: a.owner, invoices: [], phones: []});
                    (a.code !== undefined ? o.invoices : o.phones).push(a.code !== undefined ? a.code : a.phone);
                });
                var ids = Object.keys(owners);
                if (!ids.length) { out.innerHTML = '<div class="bcrm-own-empty"><strong>دارایی‌ای یافت نشد.</strong><p>کد فاکتور یا شماره را کامل وارد کنید.</p></div>'; return; }
                out.innerHTML = '<div class="bcrm-own-results">' + ids.map(function (id) {
                    var o = owners[id];
                    return '<div class="bcrm-own-result' + (state.source && state.source.id === o.owner.id ? " is-selected" : "") + '"><div class="bcrm-own-result-main"><strong>' + esc(o.owner.name) + "</strong>" + (o.owner.code ? ' <small class="bcrm-own-code">' + esc(o.owner.code) + "</small>" : "") +
                        "<p>" + (o.invoices.length ? "فاکتورهای منطبق: <bdi>" + esc(o.invoices.join("، ")) + "</bdi>" : "") + (o.phones.length ? ' شماره‌های منطبق: <bdi dir="ltr">' + esc(o.phones.join("، ")) + "</bdi>" : "") + '</p></div><button type="button" class="bcrm-secondary-button" data-own-pick-source="' + esc(id) + '">انتخاب به‌عنوان مبدأ</button></div>';
                }).join("") + "</div>";
                state.owners = owners;
            });
        }
        function pickSource(id) {
            var o = state.owners[id]; if (!o) return;
            if (state.claimant && state.claimant.id === o.owner.id) { stepError(1, "این پروفایل به‌عنوان مقصد انتخاب شده است؛ مبدأ و مقصد باید متفاوت باشند."); return; }
            state.source = o.owner;
            picker.setSource(o.owner);
            var card = qs("[data-own-source-card]", root);
            card.innerHTML = '<p class="bcrm-own-muted" role="status">در حال دریافت خلاصه پروفایل…</p>';
            post("profile_summary", {customer: o.owner.id}).then(function (res) {
                card.innerHTML = res.ok ? profileCard(res.data, "source") : '<p class="bcrm-own-field-error">' + esc(res.error.message) + "</p>";
                if (res.ok) state.sourceSummary = res.data;
            });
            qsa("[data-own-pick-source]", root).forEach(function (b) { b.closest(".bcrm-own-result").classList.toggle("is-selected", b.getAttribute("data-own-pick-source") === String(id)); });
            nextEnabled(1, true);
        }

        // Step 2 — claimant among profiles the user can access.
        function searchClaimant(term) {
            var out = qs("[data-own-claimant-results]", root);
            term = faToEn(term).trim();
            if (term.length < 2) { out.innerHTML = '<p class="bcrm-own-field-error">حداقل دو نویسه وارد کنید.</p>'; return; }
            out.innerHTML = '<p class="bcrm-own-muted" role="status">در حال جست‌وجو…</p>';
            post("claimant_search", {query: term}).then(function (res) {
                if (!res.ok) { out.innerHTML = '<p class="bcrm-own-field-error">' + esc(res.error.message) + "</p>"; return; }
                state.claimants = {};
                if (!res.data.profiles.length) { out.innerHTML = '<div class="bcrm-own-empty"><strong>پروفایلی یافت نشد.</strong><p>فقط پروفایل‌هایی که به آن‌ها دسترسی دارید نمایش داده می‌شوند.</p></div>'; return; }
                out.innerHTML = '<div class="bcrm-own-results">' + res.data.profiles.map(function (p) {
                    state.claimants[p.id] = p;
                    var same = state.source && state.source.id === p.id;
                    return '<div class="bcrm-own-result' + (same ? " is-disabled" : "") + '"><div class="bcrm-own-result-main"><strong>' + esc(p.name) + "</strong>" + (p.code ? ' <small class="bcrm-own-code">' + esc(p.code) + "</small>" : "") + "<p>" + esc((p.phones || []).map(function (x) { return x.phone; }).join("، ")) + "</p>" + (same ? '<p class="bcrm-own-result-why">این پروفایل مبدأ است؛ مقصد باید متفاوت باشد.</p>' : "") +
                        '</div><button type="button" class="bcrm-secondary-button" data-own-pick-claimant="' + p.id + '"' + (same ? " disabled" : "") + ">انتخاب به‌عنوان مقصد</button></div>";
                }).join("") + "</div>";
            });
        }
        function pickClaimant(p) {
            if (state.source && state.source.id === p.id) return;
            state.claimant = p;
            picker.setClaimant(p);
            qs("[data-own-claimant-card]", root).innerHTML = profileCard(p, "claimant");
            nextEnabled(2, true);
        }

        function renderReview() {
            var items = picker.items();
            var rows = items.map(function (s) {
                if (s.type === "PHONE") return "<li>شماره <bdi dir=\"ltr\">" + esc(s.data.phone) + "</bdi> — " + esc(cfg.actions[s.action]) + (s.action === "TRANSFER" ? " (مالکیت خود شماره منتقل می‌شود)" : "") + "</li>";
                var extra = s.mode === "USE_TARGET_PROFILE_PHONE" ? " — شماره مقصد انتخاب‌شده" : (s.mode === "PROPOSE_NEW_PHONE" ? ' — <bdi dir="ltr">' + esc(s.normalized || s.phone) + "</bdi>" : "");
                return "<li>فاکتور <bdi>" + esc(s.data.code) + "</bdi> (" + esc(money(s.data.amount)) + ") — " + esc(cfg.actions[s.action]) + " — " + esc(cfg.phoneModes[s.mode] || "روش شماره انتخاب نشده") + extra + "</li>";
            }).join("");
            var reason = qs("[data-own-reason]", root).value, note = qs("[data-own-note]", root).value;
            qs("[data-own-review]", root).innerHTML = '<div class="bcrm-own-compare">' + profileCard(state.sourceSummary || state.source, "source") + profileCard(state.claimant, "claimant") + "</div>" +
                '<dl class="bcrm-own-summary"><div><dt>دارایی‌های انتخاب‌شده</dt><dd><ul class="bcrm-own-list-plain">' + (rows || "<li>هیچ</li>") + "</ul></dd></div><div><dt>دلیل درخواست</dt><dd>" + esc(reason || "—") + "</dd></div><div><dt>توضیحات</dt><dd>" + esc(note || "—").replace(/\n/g, "<br>") + "</dd></div></dl>";
        }
        function save(submit, button) {
            var errHost = qs("[data-own-wizard-error]", root); errHost.textContent = "";
            var list = picker.inputs();
            if (!state.source || !state.claimant) { errHost.textContent = "پروفایل مبدأ و مقصد را انتخاب کنید."; return; }
            if (!list.length) { errHost.textContent = "حداقل یک دارایی انتخاب کنید."; go(3); return; }
            if (picker.validate().length) { go(3); return; }
            var run = function () {
                setBusy(button, true, submit ? "در حال ثبت و ارسال…" : "در حال ذخیره…");
                var caseId = null, version = null;
                var fail = function (error) {
                    setBusy(button, false);
                    showError(errHost, error);
                    if (caseId) errHost.innerHTML += ' <a href="' + esc(caseUrl(caseId)) + '">پیش‌نویس ذخیره شده است؛ ادامه در صفحه پرونده</a>';
                };
                post("create_draft", {source: state.source.id, claimant: state.claimant.id, reason: qs("[data-own-reason]", root).value, note: qs("[data-own-note]", root).value}).then(function (res) {
                    if (!res.ok) return fail(res.error);
                    caseId = res.data.case_id; version = res.data.version;
                    (function next(i) {
                        if (i >= list.length) {
                            if (!submit) { flashLater("پیش‌نویس درخواست ذخیره شد. " + cfg.notTransfer); window.location.href = caseUrl(caseId); return; }
                            post("submit", {case_id: caseId, version: version}).then(function (r) {
                                if (!r.ok) return fail(r.error);
                                flashLater("درخواست تطبیق برای بررسی ارسال شد. " + cfg.notTransfer);
                                window.location.href = caseUrl(caseId);
                            });
                            return;
                        }
                        var payload = list[i]; payload.case_id = caseId; payload.version = version;
                        post("add_draft_asset", payload).then(function (r) { if (!r.ok) return fail(r.error); version = r.data.version; next(i + 1); });
                    })(0);
                });
            };
            if (!submit) { run(); return; }
            confirmDialog("ثبت درخواست تطبیق و ارسال برای بررسی", cfg.notTransfer + " با ارسال، هر دو پروفایل تا تعیین تکلیف پرونده فقط خواندنی می‌شوند. ادامه می‌دهید؟").then(function (yes) { if (yes) run(); });
        }

        root.addEventListener("click", function (e) {
            var t = e.target;
            if (t.closest("[data-own-lookup]")) lookupSource();
            var ps = t.closest("[data-own-pick-source]"); if (ps) pickSource(ps.getAttribute("data-own-pick-source"));
            if (t.closest("[data-own-claimant-search]")) searchClaimant(qs("[data-own-claimant-q]", root).value);
            var pc = t.closest("[data-own-pick-claimant]"); if (pc) pickClaimant(state.claimants[pc.getAttribute("data-own-pick-claimant")]);
            if (t.closest("[data-own-prev]")) go(Math.max(1, state.step - 1));
            if (t.closest("[data-own-next]")) {
                if (state.step === 3) { if (!picker.inputs().length) { var s3 = qs('[data-own-step="3"] [data-error]', root); if (s3) s3.textContent = "حداقل یک دارایی انتخاب کنید."; return; } if (picker.validate().length) return; }
                if (state.step === 4) { var r = qs("[data-own-reason]", root); if (!r.value.trim()) { r.setAttribute("aria-invalid", "true"); stepError(4, "دلیل درخواست را وارد کنید."); r.focus(); return; } r.setAttribute("aria-invalid", "false"); stepError(4, ""); }
                go(state.step + 1);
            }
            if (t.closest("[data-own-save-draft]")) save(false, t.closest("[data-own-save-draft]"));
            if (t.closest("[data-own-save-submit]")) save(true, t.closest("[data-own-save-submit]"));
        });
        qsa("[data-own-lookup-q],[data-own-claimant-q]", root).forEach(function (input) {
            input.addEventListener("keydown", function (e) { if (e.key !== "Enter") return; e.preventDefault(); if (input.hasAttribute("data-own-lookup-q")) lookupSource(); else searchClaimant(input.value); });
        });
        var preset = Number(root.getAttribute("data-own-claimant"));
        if (preset) post("profile_summary", {customer: preset}).then(function (res) { if (res.ok && !res.data.restricted) { state.claimants = {}; state.claimants[preset] = res.data; pickClaimant(res.data); } });
    }

    document.addEventListener("DOMContentLoaded", function () {
        if (!qs("[data-bcrm-own]")) return;
        showFlash();
        initLists();
        initCase();
        initWizard();
    });
})();
