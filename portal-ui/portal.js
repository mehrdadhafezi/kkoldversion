/* Biawin CRM — C3.11 portal progressive enhancement.
 * Works on the frozen portal markup without changing routes, form fields or data.
 * Every enhancement is optional: without this script the portal behaves exactly as before. */
(function () {
    "use strict";
    function qs(s, r) { return (r || document).querySelector(s); }
    function qsa(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

    /* 1. Group the flat sidebar into task-oriented sections (same links, same order inside a group). */
    var GROUPS = [
        ["کار روزانه", ["dashboard", "customers", "ownership", "deficiency", "financial", "complaints", "service-cases"]],
        ["عودت وجه", ["refunds", "refunded"]],
        ["تخصیص و مدیریت", ["assignments", "users", "services", "service-units", "settings"]],
        ["داده و گزارش", ["import", "reports", "data-audit", "audit"]]
    ];
    function groupNav() {
        var nav = qs(".bcrm-shell-nav"); if (!nav || nav.getAttribute("data-grouped")) return;
        var links = {}; qsa("a[data-bcrm-view]", nav).forEach(function (a) { links[a.getAttribute("data-bcrm-view")] = a; });
        var placed = {}, frag = document.createDocumentFragment();
        GROUPS.forEach(function (g) {
            var items = g[1].filter(function (k) { return links[k]; });
            if (!items.length) return;
            var title = document.createElement("span"); title.className = "bcrm-nav-group-title"; title.textContent = g[0];
            frag.appendChild(title);
            items.forEach(function (k) { frag.appendChild(links[k]); placed[k] = true; });
        });
        var rest = Object.keys(links).filter(function (k) { return !placed[k]; });
        if (rest.length) { var t = document.createElement("span"); t.className = "bcrm-nav-group-title"; t.textContent = "سایر"; frag.appendChild(t); rest.forEach(function (k) { frag.appendChild(links[k]); }); }
        nav.appendChild(frag); nav.setAttribute("data-grouped", "1");
        var active = qs("a.is-active", nav); if (active && !active.hasAttribute("aria-current")) active.setAttribute("aria-current", "page");
    }

    /* 2. Browser tab title tells where the user is. */
    function pageTitle() {
        var t = qs(".bcrm-shell-header strong"); if (t && t.textContent.trim()) document.title = t.textContent.trim() + " — Biawin CRM";
    }

    /* 3. Whole customer row opens the profile (the explicit link stays the keyboard target). */
    function clickableRows() {
        qsa(".bcrm-case-row").forEach(function (row) {
            var link = qs("a[href]", row); if (!link) return;
            row.classList.add("is-clickable");
            row.addEventListener("click", function (e) { if (e.target.closest("a,button,input,select,textarea,label,summary")) return; if (window.getSelection && String(window.getSelection())) return; window.location.href = link.href; });
        });
    }

    /* 4. Repeated-submission protection for classic (non-AJAX) portal forms. Buttons are disabled
     *    only after the browser has built the form data, so submitter name/value pairs are kept. */
    function submitGuard() {
        document.addEventListener("submit", function (e) {
            var form = e.target; if (!form || form.matches("[data-own-op]") || !form.closest(".bcrm-shell-content")) return;
            setTimeout(function () {
                if (e.defaultPrevented || form.getAttribute("data-bcrm-submitted")) return;
                if ((form.getAttribute("method") || "get").toLowerCase() !== "post" || (form.target && form.target !== "_self")) return;
                form.setAttribute("data-bcrm-submitted", "1"); form.setAttribute("aria-busy", "true");
                qsa('button[type="submit"],input[type="submit"]', form).forEach(function (b) { b.disabled = true; });
            }, 0);
        });
        window.addEventListener("pageshow", function (e) { if (!e.persisted) return; qsa("form[data-bcrm-submitted]").forEach(function (f) { f.removeAttribute("data-bcrm-submitted"); f.removeAttribute("aria-busy"); qsa("[disabled]", f).forEach(function (b) { b.disabled = false; }); }); });
    }

    /* 5. Loading feedback for the profile «افزودن شماره / فاکتور» search (the frozen script replaces it with results). */
    function relatedSearchLoading() {
        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-bcrm-related-search]"); if (!btn) return;
            var panel = btn.closest("[data-bcrm-related-panel]"); if (!panel) return;
            var q = qs("[data-bcrm-related-query]", panel), out = qs("[data-bcrm-related-results]", panel);
            if (!q || !out || q.value.trim().length < 2) return;
            out.setAttribute("aria-busy", "true");
            out.innerHTML = '<div data-bcrm-loading role="status">در حال جست‌وجو…</div>';
            new MutationObserver(function (m, o) { if (!qs("[data-bcrm-loading]", out)) { out.removeAttribute("aria-busy"); o.disconnect(); } }).observe(out, {childList: true});
        }, true);
    }

    /* 6. Persian-first labels for the few English-first legacy headings (display text only). */
    var COPY = {"Workflow رفع ناقصی": "کارتابل رفع ناقصی", "Data Audit": "ممیزی داده (Data Audit)"};
    function persianCopy() {
        qsa(".bcrm-shell-content h1, .bcrm-shell-content h2, .bcrm-shell-nav a span, .bcrm-quick-actions strong, .bcrm-shell-header strong").forEach(function (el) {
            var t = el.textContent.trim(); if (COPY[t] && el.children.length === 0) el.textContent = COPY[t];
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        if (!qs(".bcrm-portal-shell")) return;
        persianCopy(); groupNav(); pageTitle(); clickableRows(); submitGuard(); relatedSearchLoading();
    });
})();
