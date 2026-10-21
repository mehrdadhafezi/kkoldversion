(function () {
    'use strict';

    const form = document.getElementById('bcrm-import-form');
    if (!form || typeof BCRM_ADMIN === 'undefined') return;

    const fileInput = document.getElementById('bcrm-import-file');
    const uploadTokenInput = document.getElementById('bcrm-upload-token');
    const readButton = document.getElementById('bcrm-read-headers');
    const status = document.getElementById('bcrm-import-inspect-status');
    const panel = document.getElementById('bcrm-mapping-panel');
    const rowsEl = document.getElementById('bcrm-mapping-rows');
    const resetButton = document.getElementById('bcrm-reset-mapping');
    const submitButton = document.getElementById('bcrm-import-preview-submit');
    const summary = document.getElementById('bcrm-mapping-summary');
    const progressBox = document.getElementById('bcrm-batch-progress');
    const progressTitle = document.getElementById('bcrm-progress-title');
    const progressPercent = document.getElementById('bcrm-progress-percent');
    const progressBar = document.getElementById('bcrm-progress-bar');
    const progressText = document.getElementById('bcrm-progress-text');
    const previewBox = document.getElementById('bcrm-batch-preview');
    const cancelButton = document.getElementById('bcrm-cancel-import');

    let fieldLabels = {};
    let jobToken = '';
    let running = false;
    try {
        fieldLabels = JSON.parse(document.getElementById('bcrm-import-field-labels')?.textContent || '{}');
    } catch (e) { fieldLabels = {}; }

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    function setStatus(message, type) {
        status.textContent = message || '';
        status.className = 'bcrm-import-status ' + (type ? 'is-' + type : '');
    }

    function mappingSelect(header, selected) {
        let options = '<option value="">— نادیده بگیر —</option><option value="__custom__" '+(selected === '__custom__' ? 'selected' : '')+'>ذخیره با همین سرستون</option>';
        Object.entries(fieldLabels).forEach(([key, label]) => {
            options += `<option value="${esc(key)}" ${key === selected ? 'selected' : ''}>${esc(label)}</option>`;
        });
        return `<select class="bcrm-map-target" name="map_target[]" aria-label="معادل ستون ${esc(header)}">${options}</select>`;
    }

    function validateMapping() {
        const selects = Array.from(rowsEl.querySelectorAll('.bcrm-map-target'));
        const selected = selects.map(s => s.value).filter(v => v && v !== '__custom__');
        const invoiceMapped = selected.includes('invoice_code');
        const duplicate = selected.find((v, i) => selected.indexOf(v) !== i);
        const customCount = selects.filter(s => s.value === '__custom__').length;
        const count = selected.length + customCount;
        rowsEl.querySelectorAll('tr').forEach(tr => tr.classList.remove('has-map-error'));
        if (duplicate) selects.filter(s => s.value === duplicate).forEach(s => s.closest('tr')?.classList.add('has-map-error'));
        if (duplicate) {
            summary.innerHTML = `<span class="bcrm-map-error">فیلد «${esc(fieldLabels[duplicate] || duplicate)}» بیش از یک‌بار انتخاب شده است.</span>`;
            submitButton.disabled = true; return false;
        }
        if (!invoiceMapped) {
            summary.innerHTML = '<span class="bcrm-map-error">«کد فاکتور» را مشخص کنید.</span>';
            submitButton.disabled = true; return false;
        }
        summary.innerHTML = `<span class="bcrm-map-ok">${count} ستون انتخاب شده است${customCount ? `؛ ${customCount} ستون اضافه با نام اصلی ذخیره می‌شود` : ''}.</span>`;
        submitButton.disabled = running; return true;
    }

    function renderMapping(data) {
        fieldLabels = data.fields || fieldLabels;
        uploadTokenInput.value = data.uploadToken || '';
        rowsEl.innerHTML = '';
        (data.headers || []).forEach((header) => {
            const tr = document.createElement('tr');
            const samples = (data.samples?.[header] || []).slice(0, 3);
            const selected = data.suggestions?.[header] || '';
            tr.innerHTML = `<td><strong class="bcrm-source-header">${esc(header)}</strong><input type="hidden" name="map_header[]" value="${esc(header)}"></td>
                <td class="bcrm-map-samples">${samples.length ? samples.map(v => `<code>${esc(v)}</code>`).join(' ') : '<span>—</span>'}</td>
                <td>${mappingSelect(header, selected)}</td>`;
            rowsEl.appendChild(tr);
        });
        panel.hidden = false;
        previewBox.hidden = true;
        setStatus(`${data.headers.length} سرستون و حدود ${Number(data.rowCount || 0).toLocaleString('fa-IR')} ردیف شناسایی شد.`, 'success');
        validateMapping();
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function post(action, fields) {
        const body = new FormData();
        body.append('action', action);
        body.append('nonce', BCRM_ADMIN.batchNonce);
        Object.entries(fields || {}).forEach(([key, value]) => {
            if (Array.isArray(value)) value.forEach(v => body.append(key + '[]', v));
            else body.append(key, value == null ? '' : value);
        });
        const response = await fetch(BCRM_ADMIN.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
        let json;
        try { json = await response.json(); }
        catch (_) { throw new Error('پاسخ نامعتبر از سرور دریافت شد. لاگ PHP را بررسی کنید.'); }
        if (!json.success) throw new Error(json.data?.message || 'عملیات ناموفق بود.');
        return json.data;
    }

    async function inspectFile() {
        const file = fileInput.files?.[0];
        if (!file) { setStatus('ابتدا فایل را انتخاب کنید.', 'error'); return; }
        panel.hidden = true; previewBox.hidden = true; submitButton.disabled = true; readButton.disabled = true;
        uploadTokenInput.value = ''; setStatus('در حال خواندن سرستون‌های فایل…', 'loading');
        const body = new FormData(); body.append('action','bcrm_inspect_import_headers'); body.append('nonce',BCRM_ADMIN.importNonce); body.append('file',file);
        try {
            const response = await fetch(BCRM_ADMIN.ajaxUrl,{method:'POST',credentials:'same-origin',body});
            const json=await response.json(); if(!json.success) throw new Error(json.data?.message||'خواندن فایل ناموفق بود.'); renderMapping(json.data);
        } catch(err) { rowsEl.innerHTML=''; panel.hidden=true; setStatus(err.message||'خطا در خواندن سرستون‌ها.','error'); }
        finally { readButton.disabled=false; }
    }

    function mappingPayload() {
        return {
            upload_token: uploadTokenInput.value,
            map_header: Array.from(rowsEl.querySelectorAll('input[name="map_header[]"]')).map(x=>x.value),
            map_target: Array.from(rowsEl.querySelectorAll('.bcrm-map-target')).map(x=>x.value),
            fill_customer_id_gaps: document.getElementById('bcrm-fill-customerid-gaps')?.checked ? '1' : '',
            correction_mode: document.getElementById('bcrm-correction-mode')?.checked ? '1' : ''
        };
    }

    function updateProgress(title, done, total, text) {
        progressBox.hidden = false;
        progressTitle.textContent = title;
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        progressPercent.textContent = pct.toLocaleString('fa-IR') + '٪';
        progressBar.style.width = pct + '%';
        progressText.textContent = text || '';
    }

    async function runStaging() {
        if (!validateMapping() || !uploadTokenInput.value) return;
        running=true; submitButton.disabled=true; fileInput.disabled=true; readButton.disabled=true; previewBox.hidden=true;
        try {
            setStatus('در حال آماده‌سازی فایل برای پردازش Batch…','loading');
            // Start-stage is special: include the still-selected browser File as a
            // transparent fallback. This prevents a lost temporary-file reference
            // from forcing the operator to choose the XLSX again.
            const payload = mappingPayload();
            const body = new FormData();
            body.append('action','bcrm_import_start_stage');
            body.append('nonce',BCRM_ADMIN.batchNonce);
            Object.entries(payload).forEach(([key,value])=>{
                if(Array.isArray(value)) value.forEach(v=>body.append(key+'[]',v));
                else body.append(key,value==null?'':value);
            });
            if(fileInput.files?.[0]) body.append('file',fileInput.files[0]);
            const response = await fetch(BCRM_ADMIN.ajaxUrl,{method:'POST',credentials:'same-origin',body});
            let json;
            try{json=await response.json();}catch(_){throw new Error('پاسخ نامعتبر از سرور دریافت شد. لاگ PHP را بررسی کنید.');}
            if(!json.success) throw new Error(json.data?.message||'شروع پردازش ناموفق بود.');
            const start=json.data;
            // Backend may have created a replacement upload token transparently.
            if(start.uploadToken) uploadTokenInput.value=start.uploadToken;
            jobToken = start.jobToken;
            updateProgress('در حال تحلیل و تجمیع فایل', 0, start.totalRows, 'فایل به صورت Streaming پردازش می‌شود؛ صفحه را نبندید.');
            while (true) {
                const data = await post('bcrm_import_stage_batch', {job_token: jobToken});
                updateProgress('در حال تحلیل و تجمیع فایل', data.processedRows || 0, data.totalRows || start.totalRows,
                    `${Number(data.processedRows||0).toLocaleString('fa-IR')} ردیف پردازش شده است.`);
                if (data.done) { renderPreview(data.preview || {}); break; }
                await new Promise(r=>setTimeout(r,40));
            }
            setStatus('پیش‌نمایش آماده است. برای ورود نهایی اطلاعات، ثبت نهایی را بزنید.','success');
        } catch(err) { setStatus(err.message||'پردازش فایل ناموفق بود.','error'); }
        finally { running=false; submitButton.disabled=false; fileInput.disabled=false; readButton.disabled=false; validateMapping(); }
    }

    function renderPreview(preview) {
        progressBox.hidden = true;
        previewBox.hidden = false;
        const risks = Object.keys(preview.high_risk_phones || {}).length;
        previewBox.innerHTML = `<h2>پیش‌نمایش Streaming</h2>
            <div class="bcrm-grid">
                <div class="bcrm-card"><strong>${Number(preview.row_count||0).toLocaleString('fa-IR')}</strong><span>ردیف پردازش‌شده</span></div>
                <div class="bcrm-card"><strong>${Number(preview.invoice_count||0).toLocaleString('fa-IR')}</strong><span>فاکتور یونیک</span></div>
                <div class="bcrm-card"><strong>${Number(preview.source_customer_count||0).toLocaleString('fa-IR')}</strong><span>CustomerID یکتای موجود در فایل</span></div>
                <div class="bcrm-card"><strong>${Number(preview.source_customer_max||0).toLocaleString('fa-IR')}</strong><span>بزرگ‌ترین CustomerID</span></div>
                <div class="bcrm-card"><strong>${Number(preview.missing_source_customer_count||0).toLocaleString('fa-IR')}</strong><span>CustomerID مفقود در بازه</span></div>
                <div class="bcrm-card"><strong>${Number(preview.conflicted_invoice_count||0).toLocaleString('fa-IR')}</strong><span>فاکتور دارای مغایرت</span></div>
                <div class="bcrm-card"><strong>${Number(preview.missing_invoice_code_rows||0).toLocaleString('fa-IR')}</strong><span>ردیف بدون کد فاکتور</span></div>
                <div class="bcrm-card"><strong>${Number(risks).toLocaleString('fa-IR')}</strong><span>شماره پرتکرار</span></div>
            </div>
            ${(preview.missing_source_customer_count||0)>0 ? `<div class="notice notice-warning inline"><p><strong>توجه:</strong> تعداد CustomerID یکتای واقعی فایل ${Number(preview.source_customer_count||0).toLocaleString('fa-IR')} است، اما بزرگ‌ترین شناسه ${Number(preview.source_customer_max||0).toLocaleString('fa-IR')} است؛ بنابراین ${Number(preview.missing_source_customer_count||0).toLocaleString('fa-IR')} شناسه در فایل هیچ ردیفی ندارد و سیستم برایشان پروفایل ساختگی ایجاد نمی‌کند.</p></div>` : ''}
            <p><button type="button" id="bcrm-final-commit" class="button button-primary button-hero">ثبت نهایی ایمپورت</button></p>`;
        document.getElementById('bcrm-final-commit')?.addEventListener('click', runCommit);
    }

    async function runCommit() {
        if (!jobToken || running) return;
        running=true; previewBox.hidden=true; submitButton.disabled=true; fileInput.disabled=true;
        try {
            const start=await post('bcrm_import_start_commit',{
                job_token:jobToken,
                create_backup: document.getElementById('bcrm-backup-before-import')?.checked ? '1' : ''
            });
            updateProgress('در حال ثبت نهایی فاکتورها و پروفایل‌ها',0,start.totalInvoices,'اطلاعات در Batchهای کوچک وارد دیتابیس می‌شوند.');
            while(true){
                const data=await post('bcrm_import_commit_batch',{job_token:jobToken});
                updateProgress('در حال ثبت نهایی فاکتورها و پروفایل‌ها',data.committedInvoices||0,data.totalInvoices||start.totalInvoices,
                    `${Number(data.committedInvoices||0).toLocaleString('fa-IR')} فاکتور ثبت شده است.`);
                if(data.done){ updateProgress('ایمپورت کامل شد',data.totalInvoices||0,data.totalInvoices||1,'تمام فاکتورها و پروفایل‌ها ثبت شدند.'); setStatus('ایمپورت با موفقیت کامل شد.','success'); cancelButton.hidden=true; break; }
                await new Promise(r=>setTimeout(r,40));
            }
        }catch(err){setStatus(err.message||'ثبت نهایی ناموفق بود.','error');}
        finally{running=false;submitButton.disabled=false;fileInput.disabled=false;validateMapping();}
    }

    async function cancelImport(){
        if(!jobToken || !confirm('پردازش ایمپورت لغو شود؟')) return;
        try{await post('bcrm_import_cancel',{job_token:jobToken});jobToken='';running=false;progressBox.hidden=true;previewBox.hidden=true;setStatus('پردازش لغو شد.','success');}
        catch(err){setStatus(err.message||'لغو پردازش ممکن نشد.','error');}
    }

    fileInput.addEventListener('change',()=>{rowsEl.innerHTML='';panel.hidden=true;previewBox.hidden=true;progressBox.hidden=true;submitButton.disabled=true;jobToken='';if(fileInput.files?.[0])inspectFile();});
    readButton.addEventListener('click',inspectFile);
    rowsEl.addEventListener('change',e=>{if(e.target.matches('.bcrm-map-target'))validateMapping();});
    resetButton.addEventListener('click',()=>{rowsEl.querySelectorAll('.bcrm-map-target').forEach(s=>s.value='');validateMapping();});
    form.addEventListener('submit',e=>{e.preventDefault();if(!running)runStaging();});
    cancelButton?.addEventListener('click',cancelImport);
})();

/* v0.8.53.3.2 — Sequential Auto Runner for Frozen Blacklist Final Commit Engine */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var cfg = window.BCRM_V085332_AUTO;
    var start = document.getElementById('bcrm-auto-start');
    var stop = document.getElementById('bcrm-auto-stop');
    var targetEl = document.getElementById('bcrm-auto-target');
    var status = document.getElementById('bcrm-auto-status');
    if (!cfg || !start || !stop || !targetEl || !status) return;

    var running = false, stopRequested = false, processed = Number(cfg.processed || 0), chunkNo = 0;
    function setStatus(text) { status.textContent = text; }
    function controls(on) { running = on; start.disabled = on; stop.disabled = !on; targetEl.disabled = on; }
    function hardStop(message) { controls(false); setStatus('STOPPED — ' + message); }

    stop.addEventListener('click', function () {
      stopRequested = true;
      stop.disabled = true;
      setStatus('Stop درخواست شد؛ بعد از پایان Chunk جاری متوقف می‌شود…');
    });

    start.addEventListener('click', async function () {
      if (running) return;
      var target = Number(targetEl.value || 0);
      if (!Number.isInteger(target) || target <= processed || target > Number(cfg.eligible)) {
        hardStop('Target نامعتبر است.'); return;
      }
      if (!window.confirm('Auto Runner از Processed ' + processed + ' تا حداکثر ' + target + ' اجرا شود؟ هر Chunk همچنان حداکثر 50 پروفایل است.')) return;
      controls(true); stopRequested = false; chunkNo = 0;

      while (running && processed < target) {
        if (stopRequested) { controls(false); setStatus('متوقف شد — Processed: ' + processed); break; }
        var before = processed; chunkNo++;
        setStatus('در حال اجرای Chunk خودکار #' + chunkNo + ' — Processed: ' + processed + ' / Target: ' + target);
        try {
          var body = new URLSearchParams();
          body.set('action', 'bcrm_v085332_auto_chunk'); body.set('nonce', cfg.nonce);
          body.set('confirm_text', cfg.confirm); body.set('run_uuid', cfg.runUuid); body.set('target_processed', String(target));
          var response = await fetch(cfg.ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:body.toString()});
          var payload;
          try { payload = await response.json(); } catch (e) { hardStop('پاسخ سرور JSON معتبر نبود (HTTP ' + response.status + ').'); break; }
          if (!response.ok || !payload || payload.success !== true) {
            var err = payload && payload.data ? (payload.data.error || JSON.stringify(payload.data)) : ('HTTP ' + response.status);
            hardStop(err); break;
          }
          var data = payload.data || {}, result = data.result || {}, state = result.state || {};
          processed = Number(state.processed || before);
          if (processed <= before) { hardStop('Processed جلو نرفت.'); break; }
          setStatus('PASS Chunk #' + chunkNo + ' — Processed: ' + processed + ' — Committed: ' + Number(state.committed || 0) + ' — Quarantined: ' + Number(state.quarantined || 0));
          if (data.done || data.target_reached || result.done || processed >= target) {
            controls(false); setStatus('DONE — Target رسید. Processed: ' + processed + '. صفحه را Refresh کن.'); break;
          }
        } catch (e) { hardStop('AJAX/Network error: ' + (e && e.message ? e.message : String(e))); break; }
      }
    });
  });
})();
