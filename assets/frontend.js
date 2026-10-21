(function () {
    "use strict";

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function esc(value) { return String(value == null ? "" : value).replace(/[&<>"']/g, function (m) { return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"})[m]; }); }
    function faToEn(value) { return String(value == null ? "" : value).replace(/[۰-۹]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹".indexOf(d); }).replace(/[٠-٩]/g, function (d) { return "٠١٢٣٤٥٦٧٨٩".indexOf(d); }); }
    function numberValue(value) { var n = faToEn(value).replace(/[^0-9.-]/g, ""); return Number(n || 0); }
    function money(value) { return new Intl.NumberFormat("fa-IR", {maximumFractionDigits: 0}).format(Number(value || 0)) + " تومان"; }
    function formatNumberInput(value) { var raw = faToEn(value).replace(/[^0-9]/g, ""); return raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, ",") : ""; }
    function jalaliToGregorianDateTime(value) {
        var text=faToEn(value).trim(); if(!text) return "";
        var m=text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/); if(!m) return text;
        var jy=Number(m[1]), jm=Number(m[2]), jd=Number(m[3]), hh=Number(m[4]||0), mm=Number(m[5]||0);
        jy += 1595; var days=-355668 + (365*jy) + (Math.floor(jy/33)*8) + Math.floor(((jy%33)+3)/4) + jd + (jm<7 ? (jm-1)*31 : ((jm-7)*30)+186);
        var gy=400*Math.floor(days/146097); days%=146097; if(days>36524){gy+=100*Math.floor(--days/36524);days%=36524;if(days>=365)days++;}
        gy+=4*Math.floor(days/1461);days%=1461;if(days>365){gy+=Math.floor((days-1)/365);days=(days-1)%365;} var gd=days+1;
        var sal=[0,31,((gy%4===0&&gy%100!==0)||gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31], gm=1; while(gm<=12 && gd>sal[gm]){gd-=sal[gm];gm++;}
        function pad(n){return String(n).padStart(2,"0");}
        return gy+"-"+pad(gm)+"-"+pad(gd)+" "+pad(hh)+":"+pad(mm)+":00";
    }
    function debounce(fn, wait) { var t; return function () { var args = arguments, self = this; clearTimeout(t); t = setTimeout(function () { fn.apply(self, args); }, wait); }; }

    function post(action, data) {
        var body = new URLSearchParams();
        body.append("action", action);
        body.append("nonce", BiawinCRM.nonce);
        Object.keys(data || {}).forEach(function (key) {
            // Never let a hidden admin-post action/nonce override the AJAX route.
            if (key === "action" || key === "nonce" || key === "_wpnonce" || key === "_wp_http_referer") return;
            var value = data[key];
            if (Array.isArray(value)) value.forEach(function (item) { body.append(key + "[]", item); });
            else if (value !== undefined && value !== null) body.append(key, value);
        });
        return fetch(BiawinCRM.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: body.toString(),
            cache: "no-store"
        }).then(function (response) {
            return response.text().then(function (text) {
                var json = null;
                try { json = JSON.parse(text); } catch (e) {}
                if (!json) {
                    var compact = String(text || "").replace(/\s+/g, " ").trim();
                    if (compact === "0") compact = "درخواست AJAX به اکشن معتبر نرسید.";
                    else if (compact === "-1") compact = "نشست امنیتی منقضی شده است؛ صفحه را یک بار باز کنید.";
                    else if (!compact) compact = "پاسخ خالی از سرور دریافت شد.";
                    else if (compact.length > 180) compact = compact.slice(0, 180) + "…";
                    return {success:false, data:{message:compact}, httpStatus:response.status};
                }
                if (!response.ok && json.success !== false) {
                    return {success:false, data:{message:"خطای سرور (" + response.status + ")"}, httpStatus:response.status};
                }
                return json;
            });
        });
    }

    function currentCustomerId() {
        var root = qs("[data-bcrm-profile-customer-id]");
        var id = root ? Number(root.getAttribute("data-bcrm-profile-customer-id") || 0) : 0;
        if (!id) {
            var form = interactionForm();
            var field = form ? qs('input[name="customer_id"]', form) : null;
            id = field ? Number(field.value || 0) : 0;
        }
        if (!id) {
            var selector = qs("[data-bcrm-service-selector]");
            id = selector ? Number(selector.getAttribute("data-customer-id") || 0) : 0;
        }
        return id > 0 ? String(Math.trunc(id)) : "";
    }

    function formDataObject(form) {
        var out = {};
        new FormData(form).forEach(function (value, key) {
            if (key.slice(-2) === "[]") {
                key = key.slice(0, -2); if (!out[key]) out[key] = []; out[key].push(value);
            } else if (out[key] !== undefined) {
                if (!Array.isArray(out[key])) out[key] = [out[key]]; out[key].push(value);
            } else out[key] = value;
        });
        ["declared_paid","requested_amount","amount"].forEach(function(key){ if(out[key]!==undefined && out[key]!=="") out[key]=String(numberValue(out[key])); });
        if(out.next_followup_at) out.next_followup_at=jalaliToGregorianDateTime(out.next_followup_at);
        return out;
    }

    function feedback(el, message, error) {
        if (!el) return;
        el.className = "bcrm-inline-feedback " + (error ? "is-error" : "is-success");
        el.textContent = message || "";
    }

    var interactionState = { savedAutoSignature: "", saving: null, previewComplete: null, dirtyDuringSave: false, pendingIncludeDeclared: false };

    function interactionForm() { return qs("[data-bcrm-interaction-form]"); }
    function interactionPayload(includeDeclared) {
        var form = interactionForm();
        if (!form) return {};
        var data = formDataObject(form);
        if (!includeDeclared) delete data.declared_paid;
        return data;
    }
    function interactionSignature(includeDeclared) {
        return JSON.stringify(interactionPayload(!!includeDeclared));
    }
    function setSaveState(label, type) {
        var el = qs("[data-bcrm-save-state]"); if (!el) return;
        el.textContent = label; el.className = "bcrm-save-state" + (type ? " is-" + type : "");
    }

    function updateProfileGate(complete, snapshot) {
        var journey = qs("[data-bcrm-journey-panel]");
        var services = qs("[data-bcrm-services-panel]");
        var declaredInput = qs("[data-bcrm-declared-paid]");
        var hasDeclared = snapshot && snapshot.declared_paid != null ? true : !!(declaredInput && String(declaredInput.value || "").trim() && numberValue(declaredInput.value) > 0);
        if (journey) {
            journey.classList.toggle("is-blocked", !hasDeclared);
            journey.setAttribute("data-has-declared", hasDeclared ? "1" : "0");
            journey.setAttribute("data-profile-complete", complete ? "1" : "0");
            qsa("[data-bcrm-select-journey]", journey).forEach(function (el) { el.disabled = !hasDeclared; });
        }
        if (services) {
            services.classList.toggle("is-blocked", !hasDeclared);
            services.setAttribute("data-has-declared", hasDeclared ? "1" : "0");
            services.setAttribute("data-profile-complete", complete ? "1" : "0");
            qsa('input[type="checkbox"], button[type="submit"]', services).forEach(function (el) {
                if (el.closest("[data-bcrm-service-status-form]")) return;
                if (el.type === "checkbox" || el.closest("[data-bcrm-service-selector]")) el.disabled = !hasDeclared;
            });
        }
        var gate = qs("[data-bcrm-gate-label]");
        if (gate) gate.textContent = !hasDeclared ? "ابتدا مبلغ اعلامی مشتری را ثبت کنید" : (complete ? "آماده انتخاب مسیر" : "مغایرت مالی مانع تعیین مسیر نیست؛ مسیر را انتخاب کنید");
        var serviceGate = qs("[data-bcrm-service-gate]");
        if (serviceGate) { serviceGate.style.display = hasDeclared ? "none" : "block"; serviceGate.textContent = "ابتدا مبلغ اعلامی مشتری را در فرم ثبت کارشناس ذخیره کنید."; }
        if (snapshot) updateSnapshot(snapshot);
    }

    function updateSnapshot(s) {
        if (!s) return;
        var mappings = {
            "total_paid": s.total_paid,
            "declared_paid": s.declared_paid == null ? null : s.declared_paid,
            "difference": Math.abs(Number(s.difference || 0)),
            "service_credit_total": s.service_credit_total,
            "service_credit_remaining": s.service_credit_remaining,
            "service_credit_used": Number(s.service_credit_reserved || 0) + Number(s.service_credit_consumed || 0)
        };
        Object.keys(mappings).forEach(function (key) {
            var el = qs('[data-bcrm-summary="' + key + '"]'); if (!el) return;
            el.textContent = mappings[key] == null ? "ثبت نشده" : money(mappings[key]);
        });
        var credit = qs("[data-bcrm-credit-remaining]"); if (credit) credit.textContent = money(s.service_credit_remaining || 0);
        var selector = qs("[data-bcrm-service-selector]");
        if (selector) {
            selector.setAttribute("data-credit-remaining", Number(s.service_credit_remaining || 0));
            qsa('input[type="checkbox"]', selector).forEach(function (input) {
                input.disabled = false;
                var label = input.closest(".bcrm-service-option"); if (label) label.classList.remove("is-disabled");
            });
            updateServiceTotals(selector);
        }
        updatePhoneList(s.phones || []);
        updateProfileGate(s.profile_completeness === "complete");
    }

    function updatePhoneList(phones) {
        var list = qs("#bcrm-phone-list"); if (!list) return;
        var existing = {};
        qsa("[data-phone]", list).forEach(function (li) { existing[li.getAttribute("data-phone")] = true; });
        phones.forEach(function (phone, idx) {
            if (existing[phone]) return;
            var li = document.createElement("li"); li.setAttribute("data-phone", phone);
            li.innerHTML = '<div><strong>' + esc(phone) + '</strong><span>متصل به پروفایل</span></div>' + (idx === 0 ? '<span class="bcrm-badge is-primary">اصلی</span>' : '');
            list.appendChild(li); existing[phone] = true;
        });
        var empty = qs("li:not([data-phone])", list); if (empty && phones.length) empty.remove();
    }

    function previewReconciliation() {
        var form = interactionForm(); if (!form) return;
        var input = qs("[data-bcrm-declared-paid]", form), box = qs("[data-bcrm-reconciliation-preview]");
        if (!input || !box) return;
        var value = String(numberValue(input.value));
        if (!value) {
            interactionState.previewComplete = null;
            box.className = "bcrm-reconciliation-preview";
            box.innerHTML = '<span>مبلغ اعلامی را وارد کنید تا سیستم بدون ذخیره، مغایرت را بررسی کند.</span>';
            // Declared amount may be empty at preview time; services stay selectable, while journey handler requires a saved declared amount.
            updateProfileGate(false);
            return;
        }
        box.className = "bcrm-reconciliation-preview is-loading";
        box.innerHTML = '<span>در حال بررسی مبلغ با فاکتورها...</span>';
        post("bcrm_preview_reconciliation", {customer_id: currentCustomerId(), declared_paid: value}).then(function (json) {
            if (!json.success) { box.className="bcrm-reconciliation-preview is-error"; box.textContent=(json.data&&json.data.message)||"خطا در بررسی مبلغ"; return; }
            var d=json.data||{}; interactionState.previewComplete=!!d.complete;
            box.className="bcrm-reconciliation-preview " + (d.complete ? "is-complete" : "is-incomplete");
            if (d.complete) {
                box.innerHTML='<div><strong>بدون مغایرت ✓</strong><span>مبلغ اعلامی: '+money(d.declared)+' · مجموع فاکتورها: '+money(d.total)+'</span></div><div><span>اعتبار قابل تخصیص</span><b>'+money(d.credit)+'</b><small>مانده قابل انتخاب: '+money(d.remaining == null ? d.credit : d.remaining)+'</small></div>';
            } else {
                box.innerHTML='<div><strong>مغایرت مالی</strong><span>مبلغ اعلامی: '+money(d.declared)+' · مجموع فاکتورها: '+money(d.total)+'</span></div><div><span>اختلاف</span><b>'+money(d.abs_difference)+'</b><small>با ثبت تماس، پرونده مستقیماً به تأیید مالی می‌رود.</small></div>';
            }
            updateProfileGate(!!d.complete);
            var selector=qs("[data-bcrm-service-selector]");
            if(selector){ selector.setAttribute("data-credit-remaining",Number(d.remaining == null ? d.credit : d.remaining)||0); qsa('input[type="checkbox"]',selector).forEach(function(input){ input.disabled=false; var label=input.closest(".bcrm-service-option"); if(label) label.classList.remove("is-disabled"); }); var cr=qs("[data-bcrm-credit-remaining]"); if(cr) cr.textContent=money(d.remaining == null ? d.credit : d.remaining||0); updateServiceTotals(selector); }
        }).catch(function(){ box.className="bcrm-reconciliation-preview is-error"; box.textContent="خطا در ارتباط با سرور."; });
    }

    function saveInteraction(force, includeDeclared) {
        var form=interactionForm(); if(!form) return Promise.resolve({success:true});
        includeDeclared = includeDeclared === true || force === true;
        var signature=interactionSignature(false);
        if(!force && !includeDeclared && signature===interactionState.savedAutoSignature) return Promise.resolve({success:true, data:{snapshot:null, skipped:true}});
        if(interactionState.saving) {
            if (includeDeclared) interactionState.pendingIncludeDeclared = true;
            return interactionState.saving;
        }
        setSaveState("در حال ذخیره...", "saving");
        var data=interactionPayload(includeDeclared);
        if (!data.customer_id) data.customer_id = currentCustomerId();
        if (!data.customer_id) return Promise.reject(new Error('شناسه پروفایل مشتری در صفحه پیدا نشد.'));
        data.save_mode = force ? "manual" : (includeDeclared ? "route" : "auto");
        interactionState.dirtyDuringSave = false;
        interactionState.pendingIncludeDeclared = false;
        interactionState.saving=post("bcrm_save_interaction", data).then(function(json){
            interactionState.saving=null;
            if(!json.success){ var msg=(json.data&&json.data.message)||"ذخیره انجام نشد"; setSaveState("خطا: "+msg, "error"); throw new Error(msg); }
            if (json.data && json.data.interaction_id) {
                var interactionId = qs('input[name="interaction_id"]', form);
                if (interactionId) interactionId.value = json.data.interaction_id;
            }
            interactionState.savedAutoSignature=interactionSignature(false);
            if (!interactionState.dirtyDuringSave && !interactionState.pendingIncludeDeclared) {
                setSaveState(force ? "ذخیره شد ✓" : "ذخیره خودکار ✓", "success");
            } else {
                var mustIncludeDeclared = interactionState.pendingIncludeDeclared;
                interactionState.savedAutoSignature="";
                setSaveState("تغییر جدید در انتظار ذخیره", "dirty");
                setTimeout(function(){ saveInteraction(false, mustIncludeDeclared).catch(function(){}); }, 80);
            }
            if(json.data&&json.data.snapshot) updateSnapshot(json.data.snapshot);
            return json;
        }).catch(function(err){ interactionState.saving=null; setSaveState("خطا: "+(err&&err.message?err.message:"ذخیره انجام نشد"), "error"); throw err; });
        return interactionState.saving;
    }

    function initInteraction() {
        var form=interactionForm(); if(!form) return;
        interactionState.savedAutoSignature = interactionSignature(false);
        var declared=qs("[data-bcrm-declared-paid]", form);
        if(declared){ var run=debounce(previewReconciliation,260); declared.addEventListener("input",run); declared.addEventListener("change",previewReconciliation); }
        var autoSave = debounce(function(){
            saveInteraction(false, false).catch(function(err){
                setSaveState("ذخیره خودکار ناموفق", "error");
                if (window.console) console.error("Biawin CRM autosave:", err);
            });
        }, 650);
        qsa("input,select,textarea",form).forEach(function(el){
            el.addEventListener("change",function(){
                setSaveState("در حال آماده‌سازی ذخیره...", "dirty");
                // declared_paid must remain preview-only until explicit save / route action.
                if (el.name !== "declared_paid") {
                    if (interactionState.saving) interactionState.dirtyDuringSave = true;
                    autoSave();
                }
            });
        });
        form.addEventListener("submit",function(e){ e.preventDefault(); var btn=qs("[data-bcrm-save-interaction]",form); if(btn) btn.disabled=true; saveInteraction(true, true).then(function(){
            if(interactionState.previewComplete===false) setSaveState("ثبت شد؛ ارسال به تأیید مالی ✓","success");
        }).catch(function(err){ alert(err.message); }).finally(function(){ if(btn) btn.disabled=false; }); });
        if(declared && declared.value.trim()) previewReconciliation();
    }

    function initJourney() {
        qsa("[data-bcrm-select-journey]").forEach(function(button){
            button.addEventListener("click",function(){
                var journey=button.getAttribute("data-bcrm-select-journey"), card=button.closest("[data-bcrm-journey-card]"), panel=button.closest("[data-bcrm-journey-panel]"), feed=qs("[data-bcrm-journey-feedback]",panel);
                var declared=qs("[data-bcrm-declared-paid]"); if(!declared || numberValue(declared.value)<=0){ feedback(feed,"ابتدا مبلغ اعلامی مشتری را وارد و ثبت کنید.",true); return; }
                button.disabled=true; button.textContent="در حال ثبت...";
                saveInteraction(false, true).then(function(){
                    var satisfaction=""; var sel=card?qs("[data-bcrm-satisfaction]",card):null; if(sel) satisfaction=sel.value;
                    var customerId=currentCustomerId(); if(!customerId) throw new Error('شناسه پروفایل مشتری در صفحه پیدا نشد.'); return post("bcrm_set_journey",{customer_id:customerId,journey:journey,satisfaction:satisfaction});
                }).then(function(json){
                    if(!json.success) throw new Error((json.data&&json.data.message)||"ثبت مسیر انجام نشد");
                    qsa("[data-bcrm-journey-card]",panel).forEach(function(x){x.classList.remove("is-selected");}); if(card) card.classList.add("is-selected");
                    feedback(feed,json.data.message,false); if(json.data.snapshot) updateSnapshot(json.data.snapshot);
                    if(journey==="service_request") { var services=qs("#bcrm-services"); if(services) services.scrollIntoView({behavior:"smooth",block:"start"}); }
                    if(journey==="complaint") { var complaints=qs("#bcrm-complaints"); if(complaints){ var det=qs("details",complaints); if(det) det.open=true; complaints.scrollIntoView({behavior:"smooth",block:"start"}); } }
                }).catch(function(err){ feedback(feed,err.message,true); }).finally(function(){ button.disabled=false; button.textContent="انتخاب این مسیر"; });
            });
        });
    }

    function updateServiceTotals(form) {
        if(!form) return;
        var remaining=Number(form.getAttribute("data-credit-remaining")||0), total=0;
        qsa('input[type="checkbox"]:checked',form).forEach(function(input){ total+=Number(input.getAttribute("data-cost")||0); });
        var totalEl=qs("[data-bcrm-selection-total]",form), remainEl=qs("[data-bcrm-selection-remaining]",form), submit=qs('button[type="submit"]',form);
        if(totalEl) totalEl.textContent=money(total);
        if(remainEl){ remainEl.textContent="مانده پس از انتخاب: "+money(remaining-total); remainEl.classList.toggle("is-danger",total>remaining); }
        if(submit) submit.disabled=total<=0;
    }

    function renderServiceCases(cases) {
        var wrap=qs("[data-bcrm-service-cases]"); if(!wrap) return;
        if(!cases||!cases.length) return;
        wrap.innerHTML="";
        cases.forEach(function(c){
            var article=document.createElement("article"); article.setAttribute("data-service-case-id",c.id);
            article.innerHTML='<div><strong>'+esc(c.service_name)+'</strong><span>'+esc(c.case_code)+' · '+money(c.credit_amount)+'</span></div><div class="bcrm-case-actions"><span class="bcrm-badge is-primary" data-case-status-label>'+esc(c.status_label)+'</span></div>';
            wrap.appendChild(article);
        });
    }

    function initServiceSelector() {
        var form=qs("[data-bcrm-service-selector]"); if(!form) return;
        qsa('input[type="checkbox"]',form).forEach(function(input){ input.addEventListener("change",function(){updateServiceTotals(form);}); }); updateServiceTotals(form);
        form.addEventListener("submit",function(e){
            e.preventDefault(); var feed=qs("[data-bcrm-service-feedback]",form), ids=qsa('input[type="checkbox"]:checked',form).map(function(x){return x.value;});
            var btn=qs('button[type="submit"]',form); if(btn) btn.disabled=true;
            saveInteraction(false, true).then(function(){ var cid=currentCustomerId()||form.getAttribute("data-customer-id"); if(!cid) throw new Error("شناسه پروفایل مشتری پیدا نشد."); return post("bcrm_select_services",{customer_id:cid,service_ids:ids,note:(qs('[name="note"]',form)||{}).value||""}); }).then(function(json){
                if(!json.success) throw new Error((json.data&&json.data.message)||"ثبت خدمات انجام نشد");
                feedback(feed,json.data.message,false); qsa('input[type="checkbox"]',form).forEach(function(x){x.checked=false;}); if(json.data.snapshot) updateSnapshot(json.data.snapshot); renderServiceCases(json.data.cases||[]);
            }).catch(function(err){ feedback(feed,err.message,true); }).finally(function(){updateServiceTotals(form);});
        });
    }

    function initServiceAssignmentForms() {
        qsa("[data-bcrm-service-assign-form]").forEach(function(form){
            form.addEventListener("submit",function(e){
                e.preventDefault();
                var data=formDataObject(form), btn=qs('button[type="submit"]',form);
                if(!data.agent_user_id){alert("کارشناس را انتخاب کنید.");return;}
                if(btn)btn.disabled=true;
                post("bcrm_assign_service_case_front",data).then(function(json){
                    if(!json.success) throw new Error((json.data&&json.data.message)||"تخصیص انجام نشد");
                    feedback(qs("[data-bcrm-service-feedback]"),json.data.message,false);
                }).catch(function(err){alert(err.message);}).finally(function(){if(btn)btn.disabled=false;});
            });
        });
    }

    function initServiceStatusForms() {
        qsa("[data-bcrm-service-status-form]").forEach(function(form){ form.addEventListener("submit",function(e){ e.preventDefault(); var btn=qs('button[type="submit"]',form); if(btn){btn.disabled=true;btn.textContent="...";} var data=formDataObject(form); post("bcrm_update_service_case",data).then(function(json){ if(!json.success) throw new Error((json.data&&json.data.message)||"خطا"); var article=form.closest("[data-service-case-id]"), label=article?qs("[data-case-status-label]",article):null; if(label) label.textContent=json.data.status_label; if(json.data.snapshot) updateSnapshot(json.data.snapshot); }).catch(function(err){alert(err.message);}).finally(function(){if(btn){btn.disabled=false;btn.textContent="ثبت";}}); }); });
    }

    function initComplaintForms() {
        qsa('form input[name="action"][value="bcrm_front_complaint"]').forEach(function(actionInput){
            var form=actionInput.closest("form"); if(!form) return;
            form.addEventListener("submit",function(e){ e.preventDefault(); var data=formDataObject(form), btn=qs('button[type="submit"],button:not([type])',form); if(btn) btn.disabled=true; post("bcrm_create_complaint",data).then(function(json){ if(!json.success) throw new Error((json.data&&json.data.message)||"ثبت شکایت انجام نشد"); form.reset(); var details=form.closest("details"); if(details) details.open=false; alert(json.data.message); if(json.data.snapshot) updateSnapshot(json.data.snapshot); }).catch(function(err){alert(err.message);}).finally(function(){if(btn)btn.disabled=false;}); });
        });
    }

    function initPhoneVerification() {
        var list=qs("#bcrm-phone-list"); if(!list) return;
        list.addEventListener("click",function(e){
            var btn=e.target.closest("[data-bcrm-phone-verify],[data-bcrm-phone-unverify],[data-bcrm-phone-primary],[data-bcrm-edit-manual-phone],[data-bcrm-detach-phone]"); if(!btn) return;
            var li=btn.closest("[data-phone-id]"); if(!li) return;
            if(btn.hasAttribute("data-bcrm-edit-manual-phone")){var current=(li.getAttribute("data-phone")||"");var next=window.prompt("شماره تماس را ویرایش کنید",current);if(next===null)return;next=next.trim();if(!next)return alert("شماره موبایل را وارد کنید.");btn.disabled=true;post("bcrm_edit_manual_phone",{customer_id:currentCustomerId(),phone_id:li.getAttribute("data-phone-id"),phone:next}).then(function(json){if(!json.success)throw new Error((json.data&&json.data.message)||"ویرایش شماره انجام نشد");var val=qs("[data-bcrm-phone-value]",li);if(val)val.textContent=json.data.phone;li.setAttribute("data-phone",json.data.phone);alert(json.data.message);}).catch(function(err){alert(err.message);}).finally(function(){btn.disabled=false;});return;}
            if(btn.hasAttribute("data-bcrm-detach-phone")){var ok=window.confirm("این شماره از پروفایل فعلی جدا خواهد شد\nو به عنوان مشتری مستقل ایجاد می‌شود.\n\nادامه می‌دهید؟");if(!ok)return;btn.disabled=true;post("bcrm_detach_customer_phone",{customer_id:currentCustomerId(),phone_id:li.getAttribute("data-phone-id")}).then(function(json){if(!json.success)throw new Error((json.data&&json.data.message)||"جدا کردن شماره انجام نشد");li.remove();alert(json.data.message+"\\nCustomer ID جدید: "+json.data.new_customer_id);if(json.data.snapshot)updateSnapshot(json.data.snapshot);}).catch(function(err){alert(err.message);}).finally(function(){btn.disabled=false;});return;}
            var phoneId=li.getAttribute("data-phone-id"), makePrimary=btn.hasAttribute("data-bcrm-phone-primary")?1:0;
            var status=makePrimary?"":(btn.hasAttribute("data-bcrm-phone-unverify")?"unverified":"verified");
            btn.disabled=true;
            post("bcrm_phone_verification",{customer_id:currentCustomerId(),phone_id:phoneId,status:status,make_primary:makePrimary}).then(function(json){
                if(!json.success) throw new Error((json.data&&json.data.message)||"ذخیره وضعیت شماره انجام نشد");
                if(!makePrimary){
                    li.setAttribute("data-phone-status",status);
                    var label=qs("[data-phone-status-label]",li); if(label) label.textContent=status==="verified"?"تأییدشده":"تأییدنشده";
                    btn.textContent=status==="verified"?"لغو تأیید":"تأیید شماره";
                    btn.removeAttribute("data-bcrm-phone-verify");btn.removeAttribute("data-bcrm-phone-unverify");
                    btn.setAttribute(status==="verified"?"data-bcrm-phone-unverify":"data-bcrm-phone-verify","");
                }
                if(makePrimary){
                    qsa(".bcrm-phone-actions .bcrm-badge.is-primary",list).forEach(function(x){x.remove();});
                    qsa("[data-bcrm-phone-primary]",list).forEach(function(x){x.style.display="";});
                    var actions=qs(".bcrm-phone-actions",li); if(actions){ var badge=document.createElement("span"); badge.className="bcrm-badge is-primary"; badge.textContent="اصلی"; actions.prepend(badge); }
                    btn.style.display="none";
                }
            }).catch(function(err){alert(err.message);}).finally(function(){btn.disabled=false;});
        });
    }

    function initRelatedPanel() {
        var panel=qs("[data-bcrm-related-panel]"); if(!panel) return;
        qsa("[data-bcrm-toggle-related]").forEach(function(button){button.addEventListener("click",function(){panel.hidden=false;document.body.classList.add("bcrm-modal-open");setTimeout(function(){var i=qs("[data-bcrm-related-query]",panel);if(i)i.focus();},30);});});
        function closePanel(){panel.hidden=true;document.body.classList.remove("bcrm-modal-open");}
        var close=qs("[data-bcrm-close-related]",panel); if(close)close.addEventListener("click",closePanel); panel.addEventListener("click",function(e){if(e.target===panel)closePanel();});
        document.addEventListener("keydown",function(e){if(e.key==="Escape"&&!panel.hidden)closePanel();});
        var searchButton=qs("[data-bcrm-related-search]",panel), query=qs("[data-bcrm-related-query]",panel); if(query)query.addEventListener("keydown",function(e){if(e.key==="Enter"){e.preventDefault();searchButton.click();}}); if(searchButton)searchButton.addEventListener("click",function(){searchRelated(panel);});
        var manual=qs("[data-bcrm-manual-bundle-form]",panel); if(manual) manual.addEventListener("submit",function(e){e.preventDefault();var btn=qs('button[type="submit"]',manual),feed=qs('[data-bcrm-manual-feedback]',manual);if(btn)btn.disabled=true;post("bcrm_manual_bundle",formDataObject(manual)).then(function(json){if(!json.success)throw new Error((json.data&&json.data.message)||"ثبت انجام نشد");feedback(feed,json.data.message,false);if(json.data.invoice)appendInvoiceRow(json.data.invoice);if(json.data.snapshot)updateSnapshot(json.data.snapshot);manual.reset();}).catch(function(err){feedback(feed,err.message,true);}).finally(function(){if(btn)btn.disabled=false;});});
    }

    function searchRelated(panel) {
        var query=qs("[data-bcrm-related-query]",panel),results=qs("[data-bcrm-related-results]",panel); if(!query||!results)return; var q=query.value.trim();
        if(q.length<2){results.innerHTML='<div class="bcrm-alert is-warning">حداقل دو کاراکتر وارد کنید.</div>';return;} results.innerHTML='<div class="bcrm-loading-row">در حال جست‌وجو...</div>';
        post("bcrm_search_related",{query:q}).then(function(json){if(!json.success){results.innerHTML='<div class="bcrm-alert is-danger">'+esc(json.data&&json.data.message||"خطا در جست‌وجو")+'</div>';return;}renderRelatedResults(panel,json.data.results||{},q);}).catch(function(){results.innerHTML='<div class="bcrm-alert is-danger">خطا در ارتباط با سرور.</div>';});
    }

    function renderRelatedResults(panel,data,searchedQuery){
        var results=qs("[data-bcrm-related-results]",panel),customerId=qs("[data-bcrm-customer-id]",panel).value,phones=data.phones||[],invoices=data.invoices||[],usage=Number(data.phone_usage_count||0);
        if(!phones.length&&!invoices.length){results.innerHTML='<div class="bcrm-empty-state is-small"><strong>موردی پیدا نشد</strong><span>در صورت نبود فاکتور، از بخش ثبت شماره و فاکتور جدید استفاده کنید.</span></div>';return;}
        var searchedPhone = faToEn(searchedQuery).replace(/\D/g,"").length>=10 ? searchedQuery : "";
        var html="";
        if(searchedPhone) html+='<div class="bcrm-search-context"><strong>شماره جست‌وجوشده: '+esc(searchedPhone)+'</strong><span>فقط فاکتورهایی نمایش داده می‌شوند که همین شماره مستقیماً روی خود فاکتورشان ثبت شده است.</span></div>';
        if(searchedPhone&&usage>=10) html+='<div class="bcrm-alert is-warning"><strong>هشدار شماره پرتکرار</strong><span>این شماره روی '+usage.toLocaleString("fa-IR")+' فاکتور مختلف دیده شده است. اگر شماره واقعاً متعلق به همین مشتری است اتصال را انجام دهید؛ فقط شماره‌های بسیار پرتکرار عمومی نیازمند بررسی سرپرست هستند.</span></div>';
        if(phones.length){html+='<div class="bcrm-phone-chips">';phones.forEach(function(p){html+='<span>'+esc(p.normalized_phone)+' · '+esc(p.display_name||"بدون نام")+'</span>';});html+='</div>';}
        if(invoices.length){html+='<h4>فاکتورهای مرتبط</h4><div class="bcrm-search-results-grid">';invoices.forEach(function(i){var ps=Array.isArray(i.phones)?i.phones:[];var selected=searchedPhone&&ps.indexOf(BiawinCRM.normalizePhone?BiawinCRM.normalizePhone(searchedPhone):searchedPhone)>-1?searchedPhone:"";var opts='<option value="">انتخاب شماره</option>';ps.forEach(function(ph){opts+='<option value="'+esc(ph)+'"'+(selected&&faToEn(selected).replace(/\D/g,"")===faToEn(ph).replace(/\D/g,"")?' selected':'')+'>'+esc(ph)+'</option>';});html+='<article class="bcrm-bundle-result" data-bcrm-invoice-result="'+esc(i.invoice_code)+'"><div><strong>فاکتور '+esc(i.invoice_code)+'</strong><span>'+esc(i.display_name||i.invoice_name||"-")+' · '+money(i.amount_paid)+'</span><span>شماره‌های فاکتور: '+esc(ps.length?ps.join("، "):"ثبت نشده")+'</span></div><div class="bcrm-bundle-actions"><button type="button" class="bcrm-secondary-button is-small" data-attach-invoice-only="'+esc(i.invoice_code)+'">اتصال فقط فاکتور</button><select data-attach-phone '+(ps.length?'':'disabled')+'>'+opts+'</select><button type="button" class="bcrm-primary-button is-small" data-attach-bundle="'+esc(i.invoice_code)+'" '+(ps.length?'':'disabled')+'>اتصال فاکتور + شماره انتخاب‌شده</button></div></article>';});html+='</div>';}
        results.innerHTML=html;
        function done(button,json,label){button.textContent=label||"متصل شد ✓";button.classList.add("is-done");showPanelMessage(panel,json.data.message+(json.data.profile_deleted&&json.data.profile_count!=null?" پروفایل مبدا خالی شد و حذف گردید؛ تعداد کل پروفایل‌ها: "+Number(json.data.profile_count).toLocaleString("fa-IR")+".":""),false);if(json.data.profile_count!=null){var pc=qs("[data-bcrm-profile-count]");if(pc)pc.textContent=Number(json.data.profile_count).toLocaleString("fa-IR");}if(json.data.invoice)appendInvoiceRow(json.data.invoice);if(json.data.snapshot)updateSnapshot(json.data.snapshot);}
        qsa("[data-attach-invoice-only]",results).forEach(function(button){button.addEventListener("click",function(){var old=button.textContent;button.disabled=true;button.textContent="در حال اتصال...";post("bcrm_attach_invoice",{customer_id:customerId,invoice_code:button.getAttribute("data-attach-invoice-only")}).then(function(json){if(!json.success)throw new Error((json.data&&json.data.message)||"اتصال فاکتور انجام نشد");done(button,json,"فاکتور متصل شد ✓");}).catch(function(err){button.disabled=false;button.textContent=old;showPanelMessage(panel,err.message,true);});});});
        qsa("[data-attach-bundle]",results).forEach(function(button){button.addEventListener("click",function(){var card=button.closest("[data-bcrm-invoice-result]"),sel=card?qs("[data-attach-phone]",card):null,phone=sel?sel.value:"";if(!phone){showPanelMessage(panel,"برای اتصال فاکتور + شماره، شماره را صریحاً انتخاب کنید.",true);return;}var old=button.textContent;button.disabled=true;button.textContent="در حال اتصال...";post("bcrm_attach_bundle",{customer_id:customerId,invoice_code:button.getAttribute("data-attach-bundle"),phone:phone,searched_query:""}).then(function(json){if(!json.success)throw new Error((json.data&&json.data.message)||"اتصال انجام نشد");done(button,json,"فاکتور + شماره متصل شد ✓");}).catch(function(err){button.disabled=false;button.textContent=old;showPanelMessage(panel,err.message,true);});});});
    }

    function appendInvoiceRow(invoice){
        if(!invoice)return;var tbody=qs("#bcrm-invoice-rows");if(!tbody)return;var code=String(invoice.invoice_code||"");if(qs('[data-invoice-code="'+CSS.escape(code)+'"]',tbody))return;var empty=qs(".bcrm-table-empty",tbody);if(empty&&empty.parentNode)empty.parentNode.remove();var tr=document.createElement("tr");tr.setAttribute("data-invoice-code",code);tr.innerHTML='<td><b>'+esc(code)+'</b></td><td>'+esc((invoice.phones||[]).join("، ")+(invoice.invoice_name?" · "+invoice.invoice_name:""))+'</td><td>'+esc(invoice.items||"-")+'</td><td>'+esc(invoice.amount_paid||"0")+' تومان</td><td>'+esc(invoice.seller||"-")+'</td><td>'+esc(invoice.callcenter||"-")+'</td><td>-</td><td><span class="bcrm-badge is-neutral">'+esc(invoice.invoice_status||"بدون وضعیت")+'</span></td>';tbody.prepend(tr);
    }

    function showPanelMessage(panel,message,error){var el=qs(".bcrm-result-message",panel);if(!el)return;el.className="bcrm-result-message "+(error?"is-error":"is-success");el.textContent=message;}

    function initProfileTabs(){var links=qsa('.bcrm-profile-tabs a');links.forEach(function(link){link.addEventListener('click',function(){links.forEach(function(x){x.classList.remove('is-active');});link.classList.add('is-active');});});}
    function initMoneyInputs(){qsa('input[name="declared_paid"],input[name="requested_amount"],input[name="amount"]').forEach(function(input){
        if(input.value) input.value=formatNumberInput(input.value);
        input.addEventListener("input",function(){var raw=faToEn(input.value).replace(/[^0-9]/g,"");input.dataset.raw=raw;input.value=formatNumberInput(raw);});
    });}

    function initInvoicePhoneEditing(){
        document.addEventListener("click",function(e){
            var btn=e.target.closest("[data-bcrm-add-invoice-phone]"); if(!btn)return;
            var phone=window.prompt("شماره موبایل مرتبط با این فاکتور را وارد کنید");
            if(phone===null)return; phone=phone.trim(); if(!phone)return alert("شماره موبایل را وارد کنید.");
            btn.disabled=true;
            post("bcrm_add_invoice_phone",{customer_id:currentCustomerId(),invoice_id:btn.getAttribute("data-invoice-id"),phone:phone}).then(function(json){
                if(!json.success) throw new Error((json.data&&json.data.message)||"ثبت شماره روی فاکتور انجام نشد");
                var row=btn.closest("tr"), identity=row&&row.children&&row.children[1];
                if(identity && Array.isArray(json.data.phones)) identity.childNodes[0].nodeValue=json.data.phones.join("، ")+" ";
                if(json.data.snapshot) updateSnapshot(json.data.snapshot);
                alert(json.data.message);
            }).catch(function(err){alert(err.message);}).finally(function(){btn.disabled=false;});
        });
    }

    function initManualInvoiceEditing(){
        document.addEventListener("click",function(e){
            var btn=e.target.closest("[data-bcrm-edit-manual-invoice]"); if(!btn)return;
            var row=btn.closest("tr"), oldCode=btn.getAttribute("data-invoice-code")||"", oldAmount=btn.getAttribute("data-invoice-amount")||"0", oldName=btn.getAttribute("data-invoice-name")||"", oldInfo=btn.getAttribute("data-payment-info")||"";
            var code=window.prompt("کد فاکتور",oldCode);if(code===null)return;
            var amount=window.prompt("مبلغ واریزی",oldAmount);if(amount===null)return;
            var name=window.prompt("نام ثبت‌شده روی فاکتور",oldName);if(name===null)return;
            var info=window.prompt("اطلاعات واریز",oldInfo);if(info===null)return;
            btn.disabled=true;
            post("bcrm_edit_manual_invoice",{customer_id:currentCustomerId(),invoice_id:btn.getAttribute("data-invoice-id"),invoice_code:code,amount:amount,name:name,payment_info:info}).then(function(json){if(!json.success)throw new Error((json.data&&json.data.message)||"ویرایش فاکتور انجام نشد");btn.setAttribute("data-invoice-code",json.data.invoice_code||code);if(row){row.setAttribute("data-invoice-code",json.data.invoice_code||code);var first=row.querySelector("td b");if(first)first.textContent=json.data.invoice_code||code;var amt=row.querySelector("[data-bcrm-invoice-amount]");if(amt)amt.textContent=formatNumberInput(amount)+" تومان";}if(json.data.snapshot)updateSnapshot(json.data.snapshot);alert(json.data.message);}).catch(function(err){alert(err.message);}).finally(function(){btn.disabled=false;});
        });
    }

    function initWorkflowForms(){
        qsa(".bcrm-workflow-final-form").forEach(function(form){
            if(form.dataset.workflowReady==="1") return;
            form.dataset.workflowReady="1";
            form.classList.add("is-workflow-ready");
            function apply(){
                var stepField=qs("[name=workflow_step]",form);
                var financeField=qs("[name=finance_result]",form);
                var step=stepField ? stepField.value : "";
                var finance=financeField ? financeField.value : "";
                qsa("[data-bcrm-step]",form).forEach(function(el){
                    var allowed=(el.getAttribute("data-bcrm-step")||"").split(/\s+/);
                    el.classList.toggle("is-active", allowed.indexOf(step)>-1);
                });
                qsa("[data-bcrm-finance]",form).forEach(function(el){
                    el.classList.toggle("is-active", (el.getAttribute("data-bcrm-finance")||"")===finance);
                });
                var returnAction=qs("[name=finance_return_action]",form);
                var returnMore=step==="finance_return_followup"&&returnAction&&returnAction.value==="send_more_receipts";
                if(returnMore){
                    var sendSection=qs('[data-bcrm-step="send_finance"]',form);
                    if(sendSection) sendSection.classList.add("is-active");
                    var rt=qs("[data-bcrm-receipt-toggle]",form);
                    if(rt) rt.checked=true;
                }
                qsa("[data-bcrm-receipt-fields]",form).forEach(function(el){
                    var toggle=qs("[data-bcrm-receipt-toggle]",form);
                    el.classList.toggle("is-active", !!(toggle&&(toggle.checked||returnMore)));
                });
            }
            form.addEventListener("change",apply);
            form.addEventListener("input",function(e){
                if(e.target && e.target.matches('input[inputmode="numeric"]')) e.target.value=formatNumberInput(e.target.value);
            });
            /* v0.8.62.27 — آپلود فیش فقط در portal-fixes.js (initReceiptUploader) انجام می‌شود؛
               نسخه تکراری اینجا حذف شد تا دو پیاده‌سازی هم‌زمان روی یک فرم رقابت نکنند. */
            apply();
        });
    }

    document.addEventListener("DOMContentLoaded",function(){initPhoneVerification();initRelatedPanel();initInteraction();initJourney();initServiceSelector();initServiceAssignmentForms();initServiceStatusForms();initComplaintForms();initMoneyInputs();initProfileTabs();initInvoicePhoneEditing();initManualInvoiceEditing();initWorkflowForms();});
})();

// v0.7.1 unified portal shell + desktop collapse
(function(){
  function shell(){ return document.querySelector('.bcrm-portal-shell'); }
  function applyStoredState(){
    var el=shell(); if(!el) return;
    try {
      if(localStorage.getItem('bcrm_sidebar_collapsed')==='1') el.classList.add('is-sidebar-collapsed');
      if(localStorage.getItem('bcrm_theme')==='dark') el.classList.add('is-dark');
    } catch(e){}
  }
  document.addEventListener('DOMContentLoaded', applyStoredState);
  document.addEventListener('click', function(e){
    var el=shell(); if(!el) return;
    var mobile=e.target.closest('[data-bcrm-menu-toggle]');
    if(mobile){ el.classList.toggle('is-menu-open'); return; }

    var collapse=e.target.closest('[data-bcrm-sidebar-collapse]');
    if(collapse){
      el.classList.toggle('is-sidebar-collapsed');
      try { localStorage.setItem('bcrm_sidebar_collapsed', el.classList.contains('is-sidebar-collapsed') ? '1':'0'); } catch(err){}
      return;
    }

    if(e.target.closest('[data-bcrm-menu-close]')){ el.classList.remove('is-menu-open'); return; }
    if(e.target.closest('.bcrm-shell-nav a')){ el.classList.remove('is-menu-open'); }
  });
})();

// v0.7.5 Jalali calendar picker for next follow-up
(function(){
  "use strict";
  var months=["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
  function faToEnLocal(v){return String(v||"").replace(/[۰-۹]/g,function(d){return "۰۱۲۳۴۵۶۷۸۹".indexOf(d);}).replace(/[٠-٩]/g,function(d){return "٠١٢٣٤٥٦٧٨٩".indexOf(d);});}
  function pad(n){return String(n).padStart(2,"0");}
  function div(a,b){return Math.floor(a/b);}
  function mod(a,b){return a-Math.floor(a/b)*b;}
  function g2j(gy,gm,gd){var gdm=[0,31,59,90,120,151,181,212,243,273,304,334],gy2=gm>2?gy+1:gy,days=355666+365*gy+div(gy2+3,4)-div(gy2+99,100)+div(gy2+399,400)+gd+gdm[gm-1],jy=-1595+33*div(days,12053);days%=12053;jy+=4*div(days,1461);days%=1461;if(days>365){jy+=div(days-1,365);days=(days-1)%365;}var jm=days<186?1+div(days,31):7+div(days-186,30),jd=1+(days<186?mod(days,31):mod(days-186,30));return [jy,jm,jd];}
  function j2g(jy,jm,jd){jy+=1595;var days=-355668+365*jy+div(jy,33)*8+div(mod(jy,33)+3,4)+jd+(jm<7?(jm-1)*31:(jm-7)*30+186),gy=400*div(days,146097);days%=146097;if(days>36524){gy+=100*div(--days,36524);days%=36524;if(days>=365)days++;}gy+=4*div(days,1461);days%=1461;if(days>365){gy+=div(days-1,365);days=(days-1)%365;}var gd=days+1,sal=[0,31,((gy%4===0&&gy%100!==0)||gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31],gm=1;while(gm<=12&&gd>sal[gm])gd-=sal[gm++];return [gy,gm,gd];}
  function leap(jy){var g1=j2g(jy,1,1),g2=j2g(jy+1,1,1);return (Date.UTC(g2[0],g2[1]-1,g2[2])-Date.UTC(g1[0],g1[1]-1,g1[2]))/86400000===366;}
  function dim(y,m){return m<=6?31:(m<=11?30:(leap(y)?30:29));}
  function parse(v){var m=faToEnLocal(v).match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?/);return m?{y:+m[1],m:+m[2],d:+m[3],h:+(m[4]||9),i:+(m[5]||0)}:null;}
  function init(){document.querySelectorAll('[data-bcrm-jalali-datetime]').forEach(function(input){if(input.dataset.pickerReady)return;input.dataset.pickerReady="1";var wrap=input.closest('.bcrm-date-picker-wrap')||input.parentNode,btn=wrap.querySelector('[data-bcrm-open-jalali]');function open(){document.querySelectorAll('.bcrm-jalali-popover').forEach(function(x){x.remove();});var now=new Date(),today=g2j(now.getFullYear(),now.getMonth()+1,now.getDate()),sel=parse(input.value)||{y:today[0],m:today[1],d:today[2],h:now.getHours(),i:0},view={y:sel.y,m:sel.m};var pop=document.createElement('div');pop.className='bcrm-jalali-popover';document.body.appendChild(pop);function close(){pop.remove();document.removeEventListener('mousedown',outside,true);}function outside(e){if(!pop.contains(e.target)&&e.target!==input&&e.target!==btn)close();}setTimeout(function(){document.addEventListener('mousedown',outside,true);},0);function render(){var g=j2g(view.y,view.m,1),first=new Date(g[0],g[1]-1,g[2]).getDay(),offset=(first+1)%7,html='<div class="bcrm-cal-head"><button type="button" data-next>‹</button><strong>'+months[view.m-1]+' '+view.y+'</strong><button type="button" data-prev>›</button></div><div class="bcrm-cal-week"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div><div class="bcrm-cal-days">';for(var x=0;x<offset;x++)html+='<span></span>';for(var d=1;d<=dim(view.y,view.m);d++){var active=sel.y===view.y&&sel.m===view.m&&sel.d===d?' is-selected':'',ist=today[0]===view.y&&today[1]===view.m&&today[2]===d?' is-today':'';html+='<button type="button" data-day="'+d+'" class="'+active+ist+'">'+d+'</button>';}html+='</div><div class="bcrm-cal-time"><label>ساعت<select data-hour>';for(var h=0;h<24;h++)html+='<option value="'+h+'" '+(h===sel.h?'selected':'')+'>'+pad(h)+'</option>';html+='</select></label><label>دقیقه<select data-minute>';for(var mi=0;mi<60;mi+=5)html+='<option value="'+mi+'" '+(Math.round(sel.i/5)*5===mi?'selected':'')+'>'+pad(mi)+'</option>';html+='</select></label></div><div class="bcrm-cal-actions"><button type="button" data-today>امروز</button><button type="button" class="bcrm-primary-button" data-apply>تأیید</button></div>';pop.innerHTML=html;var r=input.getBoundingClientRect(),pw=Math.min(330,window.innerWidth-24),left=Math.max(12,Math.min(window.innerWidth-pw-12,r.right-pw));pop.style.width=pw+'px';pop.style.left=left+'px';pop.style.top=(r.bottom+window.scrollY+6)+'px';pop.querySelector('[data-prev]').onclick=function(){if(--view.m<1){view.m=12;view.y--;}render();};pop.querySelector('[data-next]').onclick=function(){if(++view.m>12){view.m=1;view.y++;}render();};pop.querySelectorAll('[data-day]').forEach(function(b){b.onclick=function(){sel.y=view.y;sel.m=view.m;sel.d=+b.dataset.day;render();};});pop.querySelector('[data-hour]').onchange=function(e){sel.h=+e.target.value;};pop.querySelector('[data-minute]').onchange=function(e){sel.i=+e.target.value;};pop.querySelector('[data-today]').onclick=function(){sel.y=today[0];sel.m=today[1];sel.d=today[2];view.y=sel.y;view.m=sel.m;render();};pop.querySelector('[data-apply]').onclick=function(){input.value=sel.y+'/'+pad(sel.m)+'/'+pad(sel.d)+' '+pad(sel.h)+':'+pad(sel.i);input.dispatchEvent(new Event('change',{bubbles:true}));input.dispatchEvent(new Event('input',{bubbles:true}));close();};}render();}input.addEventListener('click',open);if(btn)btn.addEventListener('click',open);});}
  document.addEventListener('DOMContentLoaded',init);
})();
