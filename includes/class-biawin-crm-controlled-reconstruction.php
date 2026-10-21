<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.52 Controlled Job 988 Frozen Plan Commit Engine.
 *
 * Scope is intentionally tiny:
 * - exactly 988 frozen safe profiles, executed as 20 resumable chunks (19x50 + final 38)
 * - USE_EXISTING_VALID_TARGET only
 * - exact legacy record IDs captured from the healthy 2026-08-18 backup
 * - no invoice/phone ownership changes
 * - no customer creation/rekey
 * - no PRESERVE record is overwritten; exact legacy rows are re-parented
 * - all writes are journaled and rollback is hash-guarded
 */
class Biawin_CRM_Controlled_Reconstruction
{
    private const PLAN_FILE = 'data/controlled-job-v0.8.52.json';
    private const GOLDEN_AT = '2026-08-30 21:17:25';
    private const CONFIRM_TEXT = 'COMMIT-JOB-988';
    private const BATCH_COUNT = 988;
    private const CHUNK_SIZE = 50;
    private const MODE = 'controlled_job_988_v0852';
    private const RECOVERY_CONFIRM_TEXT = 'RECOVER-RUN-5';
    private const RECOVERY_RUN_UUID = '2041c84f-ea80-4688-b7a5-221f95ce962f';

    private static $columns_cache = [];

    private static function preview_key(): string { return 'bcrm_reconstruction_preview_v0852_' . get_current_user_id(); }
    private static function result_key(): string { return 'bcrm_reconstruction_result_v0852_' . get_current_user_id(); }
    private static function remaining_key(): string { return 'bcrm_reconstruction_remaining_v0852_' . get_current_user_id(); }
    private static function final_audit_key(): string { return 'bcrm_reconstruction_final_audit_v0852_' . get_current_user_id(); }

    public static function handle_preview(): void
    {
        self::authorize('bcrm_preview_controlled_reconstruction');
        @set_time_limit(0);
        $result = self::preflight();
        set_transient(self::preview_key(), $result, 30 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_remaining_revalidation(): void
    {
        self::authorize('bcrm_revalidate_remaining_controlled_reconstruction');
        @set_time_limit(0);
        $result = self::remaining_revalidation();
        set_transient(self::remaining_key(), $result, 30 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_final_read_only_audit(): void
    {
        self::authorize('bcrm_final_read_only_audit');
        @set_time_limit(0);
        $result = self::final_read_only_audit();
        set_transient(self::final_audit_key(), $result, 60 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_commit(): void
    {
        self::authorize('bcrm_commit_controlled_reconstruction');
        if ((string)($_POST['confirm_text'] ?? '') !== self::CONFIRM_TEXT) wp_die('عبارت تایید Controlled Job صحیح نیست. هیچ تغییری انجام نشد.');
        @set_time_limit(0);
        $result=self::commit_next_chunk();
        set_transient(self::result_key(),$result,60*MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_recover_state(): void
    {
        self::authorize('bcrm_recover_controlled_reconstruction_state');
        if ((string)($_POST['confirm_text'] ?? '') !== self::RECOVERY_CONFIRM_TEXT) {
            wp_die('عبارت تایید Recovery صحیح نیست. هیچ تغییری انجام نشد.');
        }
        @set_time_limit(0);
        $result = self::recover_run5_state();
        set_transient(self::result_key(), $result, 60 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_rollback(): void
    {
        self::authorize('bcrm_rollback_controlled_reconstruction');
        if ((string)($_POST['confirm_text'] ?? '') !== 'ROLLBACK-JOB-500') {
            wp_die('عبارت تایید Rollback صحیح نیست.');
        }
        @set_time_limit(0);
        $result = self::rollback_latest();
        set_transient(self::result_key(), $result, 60 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function export_journal_csv(): void
    {
        self::authorize('bcrm_export_reconstruction_journal_csv');
        self::ensure_journal_tables();
        global $wpdb;
        $runs = self::runs_table();
        $journal = self::journal_table();
        $run = $wpdb->get_row("SELECT * FROM $runs ORDER BY id DESC LIMIT 1", ARRAY_A);
        if (!$run) wp_die('Journal یافت نشد.');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $journal WHERE run_id=%d ORDER BY id ASC", (int)$run['id']), ARRAY_A);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-reconstruction-journal-v0.8.52-run-' . (int)$run['id'] . '-' . gmdate('Ymd-His') . '.csv"');
        $o=fopen('php://output','wb'); fwrite($o,"\xEF\xBB\xBF");
        fputcsv($o,['run_id','run_uuid','run_status','table_name','record_id','before_customer_id','after_customer_id','before_hash','after_hash','journal_status','created_at']);
        foreach ((array)$rows as $r) {
            fputcsv($o,[(int)$run['id'],$run['run_uuid'],$run['status'],$r['table_name'],$r['record_id'],$r['before_customer_id'],$r['after_customer_id'],$r['before_hash'],$r['after_hash'],$r['status'],$r['created_at']]);
        }
        fclose($o); exit;
    }

    public static function render_latest(): void
    {
        $preview=get_transient(self::preview_key());
        $result=get_transient(self::result_key());
        $remaining=get_transient(self::remaining_key());
        $final_audit=get_transient(self::final_audit_key());
        $latest=self::latest_run_safe();
        echo '<div class="bcrm-card"><h2>v0.8.52 — Controlled Job 988 / Frozen Plan Commit Engine</h2>';
        echo '<p><strong>Job شامل ۹۸۸ پروفایل Frozen است: ۱۹ Chunk پنجاه‌تایی و Chunk آخر ۳۸ پروفایل.</strong> هر Chunk Transaction، Lock، Revalidation، Journal و Verification مستقل دارد؛ Timeout مرورگر نتیجه Migration را تعیین نمی‌کند.</p>';
        echo '<p class="description">Frozen Plan Timestamp: <code>2026-08-30 21:17:25</code>. Scope هر پروفایل: ۲ Activity تاریخی + ۱ Assignment برگشت‌خورده؛ Invoice/Phone/CustomerID/Merge تغییر نمی‌کند.</p>';
        echo '<p class="description"><strong>Plan:</strong> <code>controlled-job-v0.8.52.json</code> — <strong>Expected:</strong> 988 profiles / 2964 records. Preview/Result state is version-scoped. Live conflicts are quarantined per profile; safe profiles in the same chunk may still commit. Quarantine never rewrites the conflicted profile.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_preview_controlled_reconstruction');echo '<input type="hidden" name="action" value="bcrm_preview_controlled_reconstruction"><button class="button button-primary button-hero">Controlled Job 988 Preflight (بدون تغییر)</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_revalidate_remaining_controlled_reconstruction');echo '<input type="hidden" name="action" value="bcrm_revalidate_remaining_controlled_reconstruction"><button class="button button-secondary button-hero">Live Revalidation باقی‌مانده‌ها (فقط‌خواندنی)</button></form>';
        echo '</div>';
        if(is_array($preview))self::render_preflight($preview);
        if(is_array($remaining))self::render_remaining_revalidation($remaining);
        $can_start=is_array($preview)&&!empty($preview['ok'])&&(int)($preview['counts']['profiles']??0)===self::BATCH_COUNT;
        $resume=$latest && ($latest['mode']??'')===self::MODE && in_array(($latest['status']??''),['committing','paused_safety_block'],true);
        $run5_needs_recovery=false && (string)($latest['run_uuid']??'')===self::RECOVERY_RUN_UUID && (int)(self::run_state($latest)['next_index']??0)===0;
        if($run5_needs_recovery){
            echo '<div class="bcrm-card" style="border:2px solid #dba617"><h2>Run #5 State Recovery</h2>';
            echo '<p><strong>این عملیات هیچ Activity/Assignment/Invoice/Phone/CustomerID/Merge را تغییر نمی‌دهد.</strong> فقط اگر Journal و Live DB دقیقاً ثابت کنند Chunk 1 قبلاً کامل Commit شده، bookkeeping همان Run از 0/500 به 50/500 اصلاح می‌شود.</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('bcrm_recover_controlled_reconstruction_state');echo '<input type="hidden" name="action" value="bcrm_recover_controlled_reconstruction_state"><label>تایید Recovery: <code>'.esc_html(self::RECOVERY_CONFIRM_TEXT).'</code><br><input type="text" name="confirm_text" autocomplete="off" style="min-width:260px"></label> <button class="button button-primary">Recover Run #5 State</button></form></div>';
        }
        if($can_start || $resume){
            $state=$resume?self::run_state($latest):null;
            echo '<div class="bcrm-card" style="border:2px solid #b32d2e"><h2>'.($resume?'ادامه Controlled Job 988':'شروع Controlled Job 988').'</h2>';
            if($state)echo '<p><strong>Processed:</strong> '.(int)$state['next_index'].' / 988 — <strong>Committed:</strong> '.(int)$state['profiles_committed'].' — <strong>Quarantined:</strong> '.(int)$state['skipped'].' — '.(int)$state['chunks_committed'].' / 20 chunks — '.(int)$state['records_moved'].' records moved.</p>';
            echo '<p>هر کلیک فقط Chunk بعدی را اجرا می‌کند؛ Chunkهای ۱ تا ۱۹ حداکثر ۵۰ پروفایل و Chunk ۲۰ حداکثر ۳۸ پروفایل دارد. بعد از Redirect، برای Chunk بعدی دوباره همین دکمه را بزنید.</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('bcrm_commit_controlled_reconstruction');echo '<input type="hidden" name="action" value="bcrm_commit_controlled_reconstruction"><label>تایید: <code>'.esc_html(self::CONFIRM_TEXT).'</code><br><input type="text" name="confirm_text" autocomplete="off" style="min-width:260px"></label> <button class="button button-primary">'.($resume?'Commit Next Chunk':'Start + Commit Chunk 1').'</button></form></div>';
        }
        if(is_array($result))self::render_result($result);
        if($latest){$state=self::run_state($latest);echo '<div class="bcrm-card"><h2>آخرین Reconstruction Run</h2><p>Run #'.(int)$latest['id'].' — <code>'.esc_html($latest['run_uuid']).'</code> — Status: <strong>'.esc_html($latest['status']).'</strong> — شروع: '.esc_html($latest['started_at']).'</p>';if(($latest['mode']??'')===self::MODE)echo '<p>Processed: <strong>'.(int)$state['next_index'].' / 988</strong> — Committed: <strong>'.(int)$state['profiles_committed'].'</strong> — Quarantined: <strong>'.(int)$state['skipped'].'</strong> — Chunks: <strong>'.(int)$state['chunks_committed'].' / 20</strong> — Records: <strong>'.(int)$state['records_moved'].'</strong></p>';echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_export_reconstruction_journal_csv');echo '<input type="hidden" name="action" value="bcrm_export_reconstruction_journal_csv"><button class="button button-secondary">دانلود Journal آخرین Run</button></form>';echo '</div>';}
    }

    private static function render_preflight(array $r): void
    {
        if (empty($r['ok'])) { echo '<div class="notice notice-error inline"><p>'.esc_html($r['error']??'Preflight ناموفق بود.').'</p></div>'; return; }
        echo '<div class="bcrm-card"><h2>Controlled Job 988 Preflight</h2><p>شروع: '.esc_html($r['started_at']).' — پایان: '.esc_html($r['finished_at']).'</p><div class="bcrm-grid">';
        foreach (['profiles'=>'پروفایل Batch','pass'=>'PASS','blocked'=>'BLOCKED','records'=>'رکورد Legacy دقیق','new_activity'=>'تغییر بعد Golden Snapshot','identity_fail'=>'Identity Fail','hash_fail'=>'Hash Fail','assignment_conflict'=>'Assignment Conflict'] as $k=>$l) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($r['counts'][$k]??0))).'</strong><span>'.esc_html($l).'</span></div>';
        echo '</div><table class="widefat striped"><thead><tr><th>Old</th><th>Name</th><th>Target</th><th>Target Internal</th><th>Records</th><th>Status</th><th>Reason</th></tr></thead><tbody>';
        foreach ($r['profiles'] as $p) echo '<tr><td>'.esc_html($p['old_customer_id']).'</td><td>'.esc_html($p['old_name']).'</td><td>'.esc_html($p['corrected_target']).'</td><td>'.(int)$p['target_internal_id'].'</td><td>'.(int)$p['record_count'].'</td><td><strong>'.esc_html($p['status']).'</strong></td><td>'.esc_html(implode(' | ',$p['reasons'])).'</td></tr>';
        echo '</tbody></table></div>';
    }

    private static function render_remaining_revalidation(array $r): void
    {
        if (empty($r['ok'])) { echo '<div class="notice notice-error inline"><p>'.esc_html($r['error']??'Live Revalidation ناموفق بود.').'</p></div>'; return; }
        echo '<div class="bcrm-card"><h2>Remaining Live Revalidation — فقط‌خواندنی</h2>';
        echo '<p>از index <strong>'.(int)$r['start_index'].'</strong> تا انتهای Plan بررسی شد. هیچ داده‌ای تغییر نکرد.</p><div class="bcrm-grid">';
        foreach (['remaining'=>'Remaining','safe_now'=>'SAFE_NOW','blocked_now'=>'QUARANTINE_NOW','assignment_conflict'=>'Assignment Conflict','new_activity'=>'New/Changed Activity','identity_fail'=>'Identity Fail','hash_fail'=>'Hash Fail'] as $k=>$l) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($r['counts'][$k]??0))).'</strong><span>'.esc_html($l).'</span></div>';
        echo '</div>';
        if(!empty($r['blocked'])){
            echo '<table class="widefat striped"><thead><tr><th>Old</th><th>Target</th><th>Reason</th></tr></thead><tbody>';
            foreach(array_slice($r['blocked'],0,100) as $p)echo '<tr><td>'.esc_html($p['old_customer_id']).' (#'.(int)$p['old_internal_id'].')</td><td>'.esc_html($p['corrected_target']).' (#'.(int)$p['target_internal_id'].')</td><td>'.esc_html(implode(' | ',(array)$p['reasons'])).'</td></tr>';
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    private static function render_result(array $r): void
    {
        if (empty($r['ok'])) { echo '<div class="notice notice-error inline"><p><strong>Commit انجام نشد:</strong> '.esc_html($r['error']??'خطای نامشخص').'</p></div>'; if(!empty($r['preflight']))self::render_preflight($r['preflight']); return; }
        echo '<div class="notice notice-success inline"><p><strong>'.esc_html($r['action']??'Controlled Job').'</strong> با موفقیت انجام شد. Run #'.(int)($r['run_id']??0).'</p></div>';
        echo '<div class="bcrm-card"><h2>Verification</h2><div class="bcrm-grid">';
        foreach (['profiles_committed'=>'پروفایل Commit‌شده','records_moved'=>'رکورد منتقل‌شده','customer_status_updates'=>'Customer Status Update','verified_records'=>'رکورد Verify‌شده','verification_failures'=>'Verification Failure','skipped'=>'Skipped'] as $k=>$l) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($r['counts'][$k]??0))).'</strong><span>'.esc_html($l).'</span></div>';
        echo '</div></div>';
    }


    private static function render_final_read_only_audit(array $a): void
    {
        $ok=!empty($a['ok']);
        echo '<div class="bcrm-card" style="border:2px solid '.($ok?'#00a32a':'#b32d2e').'">';
        echo '<h2>Final Read-only Audit — Run #5</h2>';
        echo '<p><strong>'.($ok?'PASS — Journal + Live scoped proof کامل است.':'FAIL / REVIEW REQUIRED — حداقل یک Invariant شکست خورده است.').'</strong></p>';
        if(!empty($a['run'])) echo '<p>Run #'.(int)$a['run']['id'].' — <code>'.esc_html((string)$a['run']['run_uuid']).'</code> — Status: <strong>'.esc_html((string)$a['run']['status']).'</strong></p>';
        $c=(array)($a['counts']??[]);
        echo '<div class="bcrm-grid">';
        foreach([
            'plan_profiles'=>'Plan Profiles','committed_profiles'=>'Committed Profiles','quarantined_profiles'=>'Quarantined Profiles',
            'expected_journal_records'=>'Expected Journal','journal_rows'=>'Journal Rows','unique_journal_refs'=>'Unique Journal Refs',
            'live_verified_records'=>'Live Verified','quarantine_untouched_records'=>'Quarantine Untouched','protected_identity_checks'=>'Protected Identity Checks',
            'failures'=>'Failures'
        ] as $k=>$label) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($c[$k]??0))).'</strong><span>'.esc_html($label).'</span></div>';
        echo '</div>';
        if(!empty($a['checks']) && is_array($a['checks'])){
            echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
            foreach($a['checks'] as $ch){echo '<tr><td>'.esc_html((string)($ch['name']??'')).'</td><td><strong>'.(!empty($ch['ok'])?'PASS':'FAIL').'</strong></td><td>'.esc_html((string)($ch['detail']??'')).'</td></tr>';}
            echo '</tbody></table>';
        }
        if(!empty($a['quarantine']) && is_array($a['quarantine'])){
            echo '<h3>Quarantine نهایی</h3><table class="widefat striped"><thead><tr><th>Old</th><th>Target</th><th>Reason</th><th>Untouched</th></tr></thead><tbody>';
            foreach($a['quarantine'] as $q){echo '<tr><td>'.esc_html((string)($q['old_customer_id']??'')).' (#'.(int)($q['old_internal_id']??0).')</td><td>'.esc_html((string)($q['corrected_target']??'')).' (#'.(int)($q['target_internal_id']??0).')</td><td>'.esc_html(implode(', ',(array)($q['reasons']??[]))).'</td><td><strong>'.(!empty($q['untouched'])?'PASS':'FAIL').'</strong></td></tr>';}
            echo '</tbody></table>';
        }
        if(!empty($a['errors'])){
            echo '<h3>Failures</h3><pre style="white-space:pre-wrap;max-height:360px;overflow:auto">'.esc_html(implode("\n",array_slice((array)$a['errors'],0,200))).'</pre>';
        }
        echo '<p class="description"><strong>حد اثبات:</strong> این Audit با Journal + وضعیت زنده DB، تمام ۵۰۰ پروفایل Plan و ۱٬۴۸۸ رکورد Commit‌شده را بررسی می‌کند. برای اثبات صفر تغییر روی ردیف‌های کاملاً خارج از Scope در کل دیتابیس، همچنان Diff بک‌آپ قبل/بعد لازم است؛ این گزارش چنین ادعایی نمی‌کند.</p>';
        echo '</div>';
    }

    private static function preflight(): array
    {
        $plan=self::load_plan(); if(!$plan)return ['ok'=>false,'error'=>'Controlled Job plan پیدا نشد.'];
        return self::preflight_profiles($plan['profiles']);
    }

    private static function preflight_profiles(array $profiles): array
    {
        global $wpdb;
        $out=['ok'=>false,'error'=>'','started_at'=>current_time('mysql'),'finished_at'=>'','counts'=>['profiles'=>0,'pass'=>0,'blocked'=>0,'records'=>0,'new_activity'=>0,'identity_fail'=>0,'hash_fail'=>0,'assignment_conflict'=>0],'profiles'=>[]];
        $safe_rows=self::current_final_safe_rows(array_column($profiles,'old_internal_id'));
        if(count($safe_rows)!==count($profiles)){$out['error']='Final Safe Set زنده برای همه پروفایل‌های این Scope ساخته نشد.';return $out;}
        $customers=Biawin_CRM_DB::table('customers');
        $assignments=Biawin_CRM_DB::table('assignments');
        foreach($profiles as $p){
            $out['counts']['profiles']++; $reasons=[]; $oid=(int)$p['old_internal_id']; $tid=(int)$p['target_internal_id']; $record_count=count($p['records']); $out['counts']['records']+=$record_count;
            $safe=$safe_rows[$oid]??null;
            if(!$safe || ($safe['final_class']??'')!=='PRESERVE_AND_COMMIT' || ($safe['protected_expert_override']??'')!=='EXPERT_ACTIVITY' || (int)($safe['manual_merge_events']??0)!==0 || (int)($safe['merge_conflict']??0)!==0 || (string)($safe['proposed_final_customer_id']??'')!==(string)$p['corrected_target']) $reasons[]='LIVE_SAFE_SET_MISMATCH';
            if($safe && ((int)($safe['source_human_after_v45']??0)>0 || (int)($safe['target_human_after_v45']??0)>0)) $reasons[]='NEW_HUMAN_AFTER_V45';

            $src=$wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE id=%d",$oid),ARRAY_A);
            $tgt=$wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE id=%d",$tid),ARRAY_A);
            if(!$src || !$tgt){$reasons[]='CUSTOMER_ROW_MISSING';$out['counts']['identity_fail']++;}
            else {
                if((string)$src['source_customer_id']!==(string)$p['old_customer_id'] || (string)$tgt['source_customer_id']!==(string)$p['corrected_target'] || self::norm_name((string)$tgt['display_name'])!==self::norm_name((string)$p['target_name'])){$reasons[]='CUSTOMER_IDENTITY_MISMATCH';$out['counts']['identity_fail']++;}
                if(self::row_hash($src)!==(string)$p['source_customer_hash'] || self::row_hash($tgt)!==(string)$p['target_customer_hash']){$reasons[]='GOLDEN_CUSTOMER_HASH_CHANGED';$out['counts']['hash_fail']++;}
                if(!self::target_evidence_ok($tid,$p)){$reasons[]='TARGET_EVIDENCE_MISMATCH';$out['counts']['identity_fail']++;}
            }
            if(self::is_in_manual_merge([$oid,$tid])){$reasons[]='MANUAL_MERGE_DETECTED';}
            if(self::has_changes_after_golden([$oid,$tid])){$reasons[]='CHANGE_AFTER_GOLDEN_SNAPSHOT';$out['counts']['new_activity']++;}

            $planned_active_assignment_ids=[];
            foreach($p['records'] as $rec){
                $t=Biawin_CRM_DB::table($rec['table']); $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",(int)$rec['record_id']),ARRAY_A);
                if(!$row || (int)($row['customer_id']??0)!==$oid){$reasons[]='LEGACY_RECORD_NOT_AT_SOURCE:'.$rec['table'].'#'.$rec['record_id'];continue;}
                $expected_row=(array)($rec['expected_row']??[]);
                // Golden backup rows may predate generated DB columns added by later schema activations.
                $legacy_hash=self::row_hash_projected($row,array_keys($expected_row));
                if($legacy_hash!==(string)$rec['fresh_hash']){
                    $detail=self::row_diff_summary($expected_row,$row);
                    $reasons[]='LEGACY_RECORD_HASH_CHANGED:'.$rec['table'].'#'.$rec['record_id'].($detail!==''?':'.$detail:'');
                    $out['counts']['hash_fail']++;
                }
                if($rec['table']==='assignments' && ($row['status']??'')==='assigned')$planned_active_assignment_ids[]=(int)$rec['record_id'];
            }
            $target_active=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id=%d AND status='assigned'",$tid));
            if($planned_active_assignment_ids){
                $src_other=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id=%d AND status='assigned' AND id NOT IN (".implode(',',array_map('intval',$planned_active_assignment_ids)).")",$oid));
                if($target_active>0 || $src_other>0){$reasons[]='ACTIVE_ASSIGNMENT_CONFLICT';$out['counts']['assignment_conflict']++;}
            } else {
                $source_active=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id=%d AND status='assigned'",$oid));
                if($target_active>0 || $source_active>0){$reasons[]='ACTIVE_ASSIGNMENT_CONFLICT';$out['counts']['assignment_conflict']++;}
            }
            $status=$reasons?'BLOCKED':'PASS';$out['counts'][$reasons?'blocked':'pass']++;
            $out['profiles'][]=['old_internal_id'=>$oid,'old_customer_id'=>$p['old_customer_id'],'old_name'=>$p['old_name'],'corrected_target'=>$p['corrected_target'],'target_internal_id'=>$tid,'record_count'=>$record_count,'status'=>$status,'reasons'=>$reasons?:['ALL_PRECONDITIONS_PASS']];
        }
        $out['ok']=true;$out['finished_at']=current_time('mysql');return $out;
    }

    private static function remaining_revalidation(): array
    {
        $plan=self::load_plan(); if(!$plan)return ['ok'=>false,'error'=>'Plan پیدا نشد.'];
        $latest=self::latest_run_safe();
        $start=0;
        if($latest && ($latest['mode']??'')===self::MODE)$start=(int)(self::run_state($latest)['next_index']??0);
        $profiles=array_slice($plan['profiles'],$start);
        if(!$profiles)return ['ok'=>true,'start_index'=>$start,'counts'=>['remaining'=>0,'safe_now'=>0,'blocked_now'=>0,'assignment_conflict'=>0,'new_activity'=>0,'identity_fail'=>0,'hash_fail'=>0],'blocked'=>[]];
        $pre=self::preflight_profiles($profiles);
        if(empty($pre['ok']))return ['ok'=>false,'error'=>$pre['error']??'Remaining Preflight failed.'];
        $blocked=[];foreach((array)$pre['profiles'] as $p)if(($p['status']??'')!=='PASS')$blocked[]=$p;
        return ['ok'=>true,'start_index'=>$start,'generated_at'=>current_time('mysql'),'counts'=>[
            'remaining'=>(int)$pre['counts']['profiles'],
            'safe_now'=>(int)$pre['counts']['pass'],
            'blocked_now'=>(int)$pre['counts']['blocked'],
            'assignment_conflict'=>(int)$pre['counts']['assignment_conflict'],
            'new_activity'=>(int)$pre['counts']['new_activity'],
            'identity_fail'=>(int)$pre['counts']['identity_fail'],
            'hash_fail'=>(int)$pre['counts']['hash_fail'],
        ],'blocked'=>$blocked];
    }

    private static function recover_run5_state(): array
    {
        global $wpdb;
        self::ensure_journal_tables();
        $plan=self::load_plan();
        if(!$plan)return ['ok'=>false,'error'=>'Plan پیدا نشد.'];
        $runs=self::runs_table();
        $manifest_hash=hash_file('sha256',BIAWIN_CRM_DIR.self::PLAN_FILE);
        $run=$wpdb->get_row($wpdb->prepare("SELECT * FROM $runs WHERE run_uuid=%s AND mode=%s AND manifest_hash=%s LIMIT 1",self::RECOVERY_RUN_UUID,self::MODE,$manifest_hash),ARRAY_A);
        if(!$run)return ['ok'=>false,'error'=>'Run #5 با UUID و Manifest مورد انتظار پیدا نشد. Recovery انجام نشد.'];
        $state=self::run_state($run);
        if((int)$state['next_index']>=self::CHUNK_SIZE){
            return ['ok'=>true,'action'=>'Run #5 قبلاً Recovery شده است','run_id'=>(int)$run['id'],'run_uuid'=>$run['run_uuid'],'counts'=>$state,'recovery_only'=>true];
        }
        if((int)$state['next_index']!==0 || (int)$state['chunks_committed']!==0 || (int)$state['profiles_committed']!==0 || (int)$state['records_moved']!==0){
            return ['ok'=>false,'error'=>'Run #5 در State غیرمنتظره است؛ Recovery خودکار مجاز نیست.'];
        }
        $proof=self::prove_chunk_committed((int)$run['id'],array_slice($plan['profiles'],0,self::CHUNK_SIZE),true);
        if(empty($proof['ok']))return ['ok'=>false,'error'=>'Recovery Proof شکست خورد: '.implode(' | ',array_slice((array)($proof['errors']??[]),0,20)),'proof'=>$proof];
        $state['profiles_committed']=50;
        $state['records_moved']=150;
        $state['customer_status_updates']=0;
        $state['verified_records']=150;
        $state['verification_failures']=0;
        $state['skipped']=0;
        $state['chunks_committed']=1;
        $state['next_index']=50;
        $state['total_chunks']=10;
        $state['last_chunk']=['chunk'=>1,'status'=>'recovered_from_journal','profiles'=>50,'records'=>150,'verified'=>150,'recovered_at'=>current_time('mysql')];
        $u=$wpdb->update($runs,['status'=>'committing','result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)],['id'=>(int)$run['id']]);
        if($u===false)return ['ok'=>false,'error'=>'RUN_STATE_RECOVERY_WRITE_FAILED'];
        $fresh=$wpdb->get_row($wpdb->prepare("SELECT * FROM $runs WHERE id=%d",(int)$run['id']),ARRAY_A);
        $fresh_state=$fresh?self::run_state($fresh):[];
        if(!$fresh || (string)$fresh['status']!=='committing' || (int)($fresh_state['next_index']??0)!==50 || (int)($fresh_state['chunks_committed']??0)!==1 || (int)($fresh_state['records_moved']??0)!==150){
            return ['ok'=>false,'error'=>'RUN_STATE_RECOVERY_POST_WRITE_VERIFY_FAILED'];
        }
        return ['ok'=>true,'action'=>'Run #5 State Recovery — Chunk 1 bookkeeping restored','run_id'=>(int)$run['id'],'run_uuid'=>$run['run_uuid'],'counts'=>$fresh_state,'recovery_only'=>true,'proof'=>$proof];
    }

    /**
     * Proves that every exact record in a chunk was already moved by this run and is still at the expected target.
     * This function is read-only. It is the idempotency guard for a timeout/process-kill after domain+journal writes.
     */
    private static function prove_chunk_committed(int $run_id,array $profiles,bool $require_exact_run_journal_count=false): array
    {
        global $wpdb;
        $journal=self::journal_table();
        $errors=[];$verified=0;$expected_refs=[];$expected_by_ref=[];
        foreach($profiles as $p){
            $oid=(int)$p['old_internal_id'];$tid=(int)$p['target_internal_id'];
            foreach((array)$p['records'] as $rec){
                $ref=(string)$rec['table'].'#'.(int)$rec['record_id'];
                if(isset($expected_refs[$ref])){$errors[]='DUPLICATE_PLAN_REF:'.$ref;continue;}
                $expected_refs[$ref]=1;$expected_by_ref[$ref]=[$oid,$tid,$rec];
            }
        }
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $journal WHERE run_id=%d ORDER BY id ASC",$run_id),ARRAY_A);
        if($require_exact_run_journal_count && count($rows)!==count($expected_refs))$errors[]='RUN_JOURNAL_COUNT_'.count($rows).'_EXPECTED_'.count($expected_refs);
        $seen=[];
        foreach((array)$rows as $jr){
            $ref=(string)$jr['table_name'].'#'.(int)$jr['record_id'];
            if(!isset($expected_refs[$ref])){
                if($require_exact_run_journal_count)$errors[]='UNEXPECTED_JOURNAL_REF:'.$ref;
                continue;
            }
            if(isset($seen[$ref])){$errors[]='DUPLICATE_JOURNAL_REF:'.$ref;continue;}
            $seen[$ref]=1;[$oid,$tid,$rec]=$expected_by_ref[$ref];
            if((string)($jr['status']??'')!=='applied'){$errors[]='JOURNAL_NOT_APPLIED:'.$ref;continue;}
            if((int)($jr['before_customer_id']??0)!==$oid || (int)($jr['after_customer_id']??0)!==$tid){$errors[]='JOURNAL_OWNER_MISMATCH:'.$ref;continue;}
            $table=Biawin_CRM_DB::table((string)$rec['table']);
            $live=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",(int)$rec['record_id']),ARRAY_A);
            if(!$live){$errors[]='LIVE_ROW_MISSING:'.$ref;continue;}
            if((int)($live['customer_id']??0)!==$tid){$errors[]='LIVE_NOT_AT_TARGET:'.$ref;continue;}
            if((string)$rec['table']==='activities' && ($live['object_type']??'')==='customer' && (int)($live['object_id']??0)!==$tid){$errors[]='LIVE_ACTIVITY_OBJECT_NOT_AT_TARGET:'.$ref;continue;}
            if((string)($jr['after_hash']??'')!==self::row_hash($live)){$errors[]='LIVE_AFTER_HASH_MISMATCH:'.$ref;continue;}
            $verified++;
        }
        foreach($expected_refs as $ref=>$_)if(!isset($seen[$ref]))$errors[]='MISSING_JOURNAL_REF:'.$ref;
        return ['ok'=>!$errors,'expected_records'=>count($expected_refs),'journal_rows'=>count($rows),'verified_records'=>$verified,'errors'=>$errors];
    }

    /**
     * Generic stale-progress detector for future chunks. It never writes domain rows.
     * If the next planned chunk is already completely journaled and live-at-target, only run bookkeeping advances.
     */
    private static function reconcile_one_stale_chunk(array $run,array $plan,array $state): array
    {
        global $wpdb;
        $start=(int)$state['next_index'];
        if($start>=self::BATCH_COUNT)return ['advanced'=>false,'state'=>$state];
        $profiles=array_slice($plan['profiles'],$start,self::CHUNK_SIZE);
        if(count($profiles)!==self::CHUNK_SIZE)return ['advanced'=>false,'state'=>$state];
        $proof=self::prove_chunk_committed((int)$run['id'],$profiles,false);
        if(empty($proof['ok']))return ['advanced'=>false,'state'=>$state,'proof'=>$proof];
        $state['profiles_committed']=(int)$state['profiles_committed']+count($profiles);
        $state['records_moved']=(int)$state['records_moved']+(int)$proof['expected_records'];
        $state['verified_records']=(int)$state['verified_records']+(int)$proof['verified_records'];
        $state['chunks_committed']=(int)$state['chunks_committed']+1;
        $state['next_index']=$start+count($profiles);
        $chunk_no=(int)floor($start/self::CHUNK_SIZE)+1;
        $state['last_chunk']=['chunk'=>$chunk_no,'status'=>'reconciled_from_journal','profiles'=>count($profiles),'records'=>(int)$proof['expected_records'],'verified'=>(int)$proof['verified_records'],'recovered_at'=>current_time('mysql')];
        $done=(int)$state['next_index']>=self::BATCH_COUNT;$status=$done?'committed':'committing';
        $upd=['status'=>$status,'result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)];if($done){$upd['committed_at']=current_time('mysql');$upd['completed_at']=current_time('mysql');}
        $u=$wpdb->update(self::runs_table(),$upd,['id'=>(int)$run['id']]);
        if($u===false)return ['advanced'=>false,'state'=>$state,'proof'=>$proof,'error'=>'STALE_PROGRESS_RECOVERY_WRITE_FAILED'];
        return ['advanced'=>true,'state'=>$state,'proof'=>$proof,'chunk'=>$chunk_no,'done'=>$done];
    }

    private static function commit_next_chunk(): array
    {
        global $wpdb; self::ensure_journal_tables(); $plan=self::load_plan(); if(!$plan)return ['ok'=>false,'error'=>'Plan پیدا نشد.'];
        $runs=self::runs_table(); $manifest_hash=hash_file('sha256',BIAWIN_CRM_DIR.self::PLAN_FILE);
        $run=$wpdb->get_row($wpdb->prepare("SELECT * FROM $runs WHERE mode=%s AND manifest_hash=%s AND status IN ('committing','paused_safety_block') ORDER BY id DESC LIMIT 1",self::MODE,$manifest_hash),ARRAY_A);
        if(!$run){
            $pre=self::preflight();
            if(empty($pre['ok'])||(int)($pre['counts']['profiles']??0)!==self::BATCH_COUNT)return ['ok'=>false,'error'=>'Full Job Preflight نتوانست کل Frozen Plan را ارزیابی کند؛ Job شروع نشد.','preflight'=>$pre];
            $state=['profiles_committed'=>0,'records_moved'=>0,'customer_status_updates'=>0,'verified_records'=>0,'verification_failures'=>0,'skipped'=>0,'chunks_committed'=>0,'next_index'=>0,'total_chunks'=>(int)ceil(self::BATCH_COUNT/self::CHUNK_SIZE),'last_chunk'=>null,'quarantined_profiles'=>[]];
            $uuid=wp_generate_uuid4();$wpdb->insert($runs,['run_uuid'=>$uuid,'plugin_version'=>BIAWIN_CRM_VERSION,'mode'=>self::MODE,'status'=>'committing','pilot_count'=>self::BATCH_COUNT,'started_by'=>get_current_user_id(),'started_at'=>current_time('mysql'),'manifest_hash'=>$manifest_hash,'preflight_json'=>wp_json_encode($pre,JSON_UNESCAPED_UNICODE),'result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)]);
            $run=$wpdb->get_row($wpdb->prepare("SELECT * FROM $runs WHERE id=%d",(int)$wpdb->insert_id),ARRAY_A); if(!$run)return ['ok'=>false,'error'=>'ساخت Job Run ناموفق بود.'];
        }
        $state=self::run_state($run);
        $reconciled=self::reconcile_one_stale_chunk($run,$plan,$state);
        if(!empty($reconciled['advanced'])){
            return ['ok'=>true,'action'=>'Stale progress از Journal + Live DB بازیابی شد؛ هیچ Chunk جدیدی در این درخواست Commit نشد','run_id'=>(int)$run['id'],'run_uuid'=>$run['run_uuid'],'counts'=>$reconciled['state'],'recovery_only'=>true,'proof'=>$reconciled['proof']];
        }
        $start=(int)$state['next_index']; if($start>=self::BATCH_COUNT)return ['ok'=>true,'action'=>'Controlled Job 988 already complete','run_id'=>(int)$run['id'],'run_uuid'=>$run['run_uuid'],'counts'=>$state];
        $chunk_profiles=array_slice($plan['profiles'],$start,self::CHUNK_SIZE); $chunk_no=(int)floor($start/self::CHUNK_SIZE)+1;
        $chunk_pre=self::preflight_profiles($chunk_profiles);
        if(empty($chunk_pre['ok'])){$state['last_chunk']=['chunk'=>$chunk_no,'status'=>'blocked_system','preflight'=>$chunk_pre];$wpdb->update($runs,['status'=>'paused_safety_block','result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)],['id'=>(int)$run['id']]);return ['ok'=>false,'error'=>'Chunk '.$chunk_no.' preflight سیستمی ناموفق بود؛ هیچ تغییری انجام نشد.','run_id'=>(int)$run['id'],'counts'=>$state,'preflight'=>$chunk_pre];}

        $pre_by_old=[];foreach((array)$chunk_pre['profiles'] as $pp)$pre_by_old[(int)$pp['old_internal_id']]=$pp;
        $local=['profiles_committed'=>0,'records_moved'=>0,'customer_status_updates'=>0,'verified_records'=>0,'verification_failures'=>0,'skipped'=>0];
        $quarantined=[];$committed_profiles=[];
        $wpdb->query('START TRANSACTION');
        try{
            $customers=Biawin_CRM_DB::table('customers');$assignments=Biawin_CRM_DB::table('assignments');
            foreach($chunk_profiles as $p){
                $oid=(int)$p['old_internal_id'];$tid=(int)$p['target_internal_id'];
                $pre_row=$pre_by_old[$oid]??null;
                if(!$pre_row || ($pre_row['status']??'')!=='PASS'){
                    $reasons=$pre_row?(array)($pre_row['reasons']??['READ_ONLY_PREFLIGHT_BLOCK']):['READ_ONLY_PREFLIGHT_RESULT_MISSING'];
                    $quarantined[]=self::quarantine_entry($p,$reasons,'preflight');$local['skipped']++;continue;
                }

                $reasons=[];$locked_records=[];
                $src=$wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE id=%d FOR UPDATE",$oid),ARRAY_A);
                $tgt=$wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE id=%d FOR UPDATE",$tid),ARRAY_A);
                if(!$src||!$tgt)$reasons[]='CUSTOMER_ROW_MISSING_UNDER_LOCK';
                elseif(self::row_hash($src)!==(string)$p['source_customer_hash']||self::row_hash($tgt)!==(string)$p['target_customer_hash'])$reasons[]='GOLDEN_HASH_CHANGED_UNDER_LOCK';
                if(!$reasons && !self::target_evidence_ok_locked($tid,$p))$reasons[]='TARGET_EVIDENCE_CHANGED_UNDER_LOCK';
                if(!$reasons && self::is_in_manual_merge([$oid,$tid]))$reasons[]='MANUAL_MERGE_DETECTED_UNDER_LOCK';
                if(!$reasons && self::has_changes_after_golden([$oid,$tid]))$reasons[]='CHANGE_AFTER_GOLDEN_UNDER_LOCK';

                if(!$reasons){
                    $assignment_rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $assignments WHERE customer_id IN (%d,%d) FOR UPDATE",$oid,$tid),ARRAY_A);
                    foreach((array)$assignment_rows as $ar){
                        if(($ar['status']??'')==='assigned'){
                            $reasons[]='ACTIVE_ASSIGNMENT_CONFLICT_UNDER_LOCK:assignment#'.(int)$ar['id'].':customer#'.(int)$ar['customer_id'];
                        }
                    }
                }
                if(!$reasons){
                    foreach($p['records'] as $rec){
                        $table=Biawin_CRM_DB::table($rec['table']);$rid=(int)$rec['record_id'];
                        $before=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$rid),ARRAY_A);
                        if(!$before||(int)($before['customer_id']??0)!==$oid){$reasons[]='LEGACY_ROW_CHANGED_UNDER_LOCK:'.$rec['table'].'#'.$rid;break;}
                        $expected=(array)($rec['expected_row']??[]);
                        if(self::row_hash_projected($before,array_keys($expected))!==(string)$rec['fresh_hash']){$reasons[]='LEGACY_HASH_CHANGED_UNDER_LOCK:'.$rec['table'].'#'.$rid;break;}
                        $locked_records[]=['rec'=>$rec,'table'=>$table,'before'=>$before];
                    }
                }
                if($reasons){$quarantined[]=self::quarantine_entry($p,$reasons,'under_lock');$local['skipped']++;continue;}

                foreach($locked_records as $lr){
                    $rec=$lr['rec'];$table=$lr['table'];$before=$lr['before'];$rid=(int)$rec['record_id'];
                    $data=['customer_id'=>$tid];if($rec['table']==='activities'&&($before['object_type']??'')==='customer'&&(int)($before['object_id']??0)===$oid)$data['object_id']=$tid;
                    $u=$wpdb->update($table,$data,['id'=>$rid,'customer_id'=>$oid]);if($u!==1)throw new Exception('UPDATE_FAILED_'.$rec['table'].'_'.$rid);
                    $after=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$rid),ARRAY_A);self::journal((int)$run['id'],$rec['table'],$rid,$before,$after);$local['records_moved']++;
                }
                $local['profiles_committed']++;$committed_profiles[]=$p;
            }

            if($committed_profiles){
                $verify_plan=$plan;$verify_plan['profiles']=$committed_profiles;$verify_plan['batch_count']=count($committed_profiles);
                $verify=self::verify_plan($verify_plan);$local['verified_records']=$verify['verified_records'];$local['verification_failures']=$verify['failures'];
                if($verify['failures']>0)throw new Exception('CHUNK_POST_COMMIT_VERIFICATION_FAILED:'.implode(',',array_slice($verify['details'],0,20)));
            }

            foreach(['profiles_committed','records_moved','customer_status_updates','verified_records','verification_failures','skipped'] as $k)$state[$k]=(int)$state[$k]+(int)$local[$k];
            $state['chunks_committed']=(int)$state['chunks_committed']+1;$state['next_index']=$start+count($chunk_profiles);
            if(!isset($state['quarantined_profiles'])||!is_array($state['quarantined_profiles']))$state['quarantined_profiles']=[];
            foreach($quarantined as $q)$state['quarantined_profiles'][]=$q;
            $state['last_chunk']=['chunk'=>$chunk_no,'status'=>$quarantined?'committed_with_quarantine':'committed','processed'=>count($chunk_profiles),'profiles_committed'=>$local['profiles_committed'],'quarantined'=>count($quarantined),'records'=>$local['records_moved'],'verified'=>$local['verified_records'],'quarantine'=>$quarantined];
            $done=$state['next_index']>=self::BATCH_COUNT;$status=$done?((int)$state['skipped']>0?'committed_with_quarantine':'committed'):'committing';
            $upd=['status'=>$status,'result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)];if($done){$upd['committed_at']=current_time('mysql');$upd['completed_at']=current_time('mysql');}
            $ru=$wpdb->update($runs,$upd,['id'=>(int)$run['id']]);if($ru!==1)throw new Exception('RUN_PROGRESS_WRITE_FAILED_OR_NOOP');
            $run_check=$wpdb->get_row($wpdb->prepare("SELECT status,result_json FROM $runs WHERE id=%d",(int)$run['id']),ARRAY_A);$check_state=$run_check?json_decode((string)($run_check['result_json']??''),true):null;
            if(!$run_check||!is_array($check_state)||(int)($check_state['next_index']??-1)!==(int)$state['next_index']||(int)($check_state['chunks_committed']??-1)!==(int)$state['chunks_committed']||(int)($check_state['records_moved']??-1)!==(int)$state['records_moved']||(int)($check_state['skipped']??-1)!==(int)$state['skipped'])throw new Exception('RUN_PROGRESS_READBACK_FAILED');
            $wpdb->query('COMMIT');
            $action='Controlled Job 988 — Chunk '.$chunk_no.' committed'.($quarantined?' with '.count($quarantined).' quarantined profile(s)':'');
            return ['ok'=>true,'action'=>$action,'run_id'=>(int)$run['id'],'run_uuid'=>$run['run_uuid'],'counts'=>$state,'job_complete'=>$done,'quarantined'=>$quarantined];
        }catch(Throwable $e){
            $wpdb->query('ROLLBACK');$state['last_chunk']=['chunk'=>$chunk_no,'status'=>'failed','error'=>$e->getMessage()];
            $wpdb->update($runs,['status'=>'paused_safety_block','result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)],['id'=>(int)$run['id']]);
            return ['ok'=>false,'error'=>'Chunk '.$chunk_no.' rollback شد؛ Chunkهای قبلی Commit‌شده باقی ماندند. دلیل: '.$e->getMessage(),'run_id'=>(int)$run['id'],'counts'=>$state];
        }
    }

    private static function quarantine_entry(array $p,array $reasons,string $phase): array
    {
        return ['old_internal_id'=>(int)$p['old_internal_id'],'old_customer_id'=>(string)$p['old_customer_id'],'target_internal_id'=>(int)$p['target_internal_id'],'corrected_target'=>(string)$p['corrected_target'],'old_name'=>(string)($p['old_name']??''),'phase'=>$phase,'reasons'=>array_values(array_unique(array_map('strval',$reasons))),'detected_at'=>current_time('mysql')];
    }

    private static function run_state(array $run): array
    {
        $s=json_decode((string)($run['result_json']??''),true);if(!is_array($s))$s=[];return array_merge(['profiles_committed'=>0,'records_moved'=>0,'customer_status_updates'=>0,'verified_records'=>0,'verification_failures'=>0,'skipped'=>0,'chunks_committed'=>0,'next_index'=>0,'total_chunks'=>20,'last_chunk'=>null,'quarantined_profiles'=>[]],$s);
    }



    private static function final_read_only_audit(): array
    {
        global $wpdb;
        $plan=self::load_plan();
        $errors=[];$checks=[];
        $add_check=function(string $name,bool $ok,string $detail='')use(&$checks,&$errors){$checks[]=['name'=>$name,'ok'=>$ok,'detail'=>$detail];if(!$ok)$errors[]=$name.($detail!==''?': '.$detail:'');};
        if(!$plan)return ['ok'=>false,'error'=>'Plan پیدا نشد.','errors'=>['PLAN_NOT_FOUND'],'checks'=>[],'counts'=>['failures'=>1]];
        $manifest_hash=hash_file('sha256',BIAWIN_CRM_DIR.self::PLAN_FILE);
        $runs=self::runs_table();$journal=self::journal_table();
        $run=$wpdb->get_row($wpdb->prepare("SELECT * FROM $runs WHERE run_uuid=%s AND mode=%s LIMIT 1",self::RECOVERY_RUN_UUID,self::MODE),ARRAY_A);
        if(!$run)return ['ok'=>false,'errors'=>['RUN5_NOT_FOUND'],'checks'=>[],'counts'=>['plan_profiles'=>count($plan['profiles']),'failures'=>1]];
        $state=self::run_state($run);
        $add_check('RUN_UUID_MODE', (string)$run['run_uuid']===self::RECOVERY_RUN_UUID && (string)$run['mode']===self::MODE, 'uuid='.(string)$run['run_uuid'].' mode='.(string)$run['mode']);
        $add_check('MANIFEST_HASH', hash_equals((string)$run['manifest_hash'],(string)$manifest_hash), 'run='.(string)$run['manifest_hash'].' file='.(string)$manifest_hash);
        $terminal=in_array((string)$run['status'],['committed','committed_with_quarantine'],true);
        $add_check('RUN_TERMINAL_STATUS',$terminal,'status='.(string)$run['status']);
        $state_ok=(int)$state['next_index']===500 && (int)$state['chunks_committed']===10 && (int)$state['profiles_committed']===496 && (int)$state['skipped']===4 && (int)$state['records_moved']===1488 && (int)$state['verified_records']===1488 && (int)$state['verification_failures']===0 && (int)$state['customer_status_updates']===0;
        $add_check('RUN_STATE_COUNTS',$state_ok,'processed='.(int)$state['next_index'].' committed='.(int)$state['profiles_committed'].' quarantined='.(int)$state['skipped'].' chunks='.(int)$state['chunks_committed'].' records='.(int)$state['records_moved'].' verified='.(int)$state['verified_records'].' vf='.(int)$state['verification_failures'].' status_updates='.(int)$state['customer_status_updates']);

        $profiles_by_old=[];foreach((array)$plan['profiles'] as $p)$profiles_by_old[(int)$p['old_internal_id']]=$p;
        $q_entries=(array)($state['quarantined_profiles']??[]);$q_by_old=[];
        foreach($q_entries as $q){$oid=(int)($q['old_internal_id']??0);if($oid>0)$q_by_old[$oid]=$q;}
        $add_check('QUARANTINE_COUNT',count($q_by_old)===4,'unique='.count($q_by_old).' state_skipped='.(int)$state['skipped']);
        $q_known=true;foreach($q_by_old as $oid=>$q)if(!isset($profiles_by_old[$oid])){$q_known=false;$errors[]='QUARANTINE_NOT_IN_PLAN:'.$oid;}
        $add_check('QUARANTINE_IN_PLAN',$q_known,'all quarantined source profiles must be in frozen plan');

        $expected=[];$committed_profiles=0;$quarantine_profiles=0;
        foreach((array)$plan['profiles'] as $p){
            $oid=(int)$p['old_internal_id'];$tid=(int)$p['target_internal_id'];$is_q=isset($q_by_old[$oid]);
            if($is_q){$quarantine_profiles++;continue;}$committed_profiles++;
            foreach((array)$p['records'] as $rec){$ref=(string)$rec['table'].'#'.(int)$rec['record_id'];if(isset($expected[$ref]))$errors[]='DUPLICATE_PLAN_REF:'.$ref;$expected[$ref]=['p'=>$p,'rec'=>$rec,'oid'=>$oid,'tid'=>$tid];}
        }
        $add_check('EXPECTED_COMMITTED_PROFILE_COUNT',$committed_profiles===496,'committed_plan_profiles='.$committed_profiles);
        $add_check('EXPECTED_COMMITTED_RECORD_COUNT',count($expected)===1488,'expected_records='.count($expected));

        $jrows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $journal WHERE run_id=%d ORDER BY id ASC",(int)$run['id']),ARRAY_A);
        $seen=[];$live_verified=0;$scope_ok=true;$journal_self_hash_ok=true;$owner_ok=true;$status_ok=true;
        foreach((array)$jrows as $jr){
            $ref=(string)$jr['table_name'].'#'.(int)$jr['record_id'];
            if(isset($seen[$ref])){$errors[]='DUPLICATE_JOURNAL_REF:'.$ref;continue;}$seen[$ref]=1;
            if(!isset($expected[$ref])){$errors[]='UNEXPECTED_JOURNAL_REF:'.$ref;$scope_ok=false;continue;}
            if(!in_array((string)$jr['table_name'],['activities','assignments'],true)){$errors[]='FORBIDDEN_JOURNAL_TABLE:'.$ref;$scope_ok=false;}
            $e=$expected[$ref];
            if((string)($jr['status']??'')!=='applied'){$errors[]='JOURNAL_NOT_APPLIED:'.$ref;$status_ok=false;continue;}
            if((int)$jr['before_customer_id']!==$e['oid'] || (int)$jr['after_customer_id']!==$e['tid']){$errors[]='JOURNAL_OWNER_MISMATCH:'.$ref;$owner_ok=false;continue;}
            $bj=json_decode((string)$jr['before_json'],true);$aj=json_decode((string)$jr['after_json'],true);
            if(!is_array($bj)||!is_array($aj)||!hash_equals((string)$jr['before_hash'],self::row_hash((array)$bj))||!hash_equals((string)$jr['after_hash'],self::row_hash((array)$aj))){$errors[]='JOURNAL_HASH_SELF_MISMATCH:'.$ref;$journal_self_hash_ok=false;continue;}
            $table=Biawin_CRM_DB::table((string)$jr['table_name']);
            $live=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",(int)$jr['record_id']),ARRAY_A);
            if(!$live){$errors[]='LIVE_ROW_MISSING:'.$ref;continue;}
            if((int)($live['customer_id']??0)!==$e['tid']){$errors[]='LIVE_NOT_AT_TARGET:'.$ref;continue;}
            if((string)$jr['table_name']==='activities' && (string)($live['object_type']??'')==='customer' && (int)($live['object_id']??0)!==$e['tid']){$errors[]='LIVE_ACTIVITY_OBJECT_NOT_TARGET:'.$ref;continue;}
            if(!hash_equals((string)$jr['after_hash'],self::row_hash($live))){$errors[]='LIVE_AFTER_HASH_MISMATCH:'.$ref;continue;}
            $live_verified++;
        }
        foreach($expected as $ref=>$e)if(!isset($seen[$ref]))$errors[]='MISSING_JOURNAL_REF:'.$ref;
        $add_check('JOURNAL_ROW_COUNT',count($jrows)===1488,'rows='.count($jrows));
        $add_check('JOURNAL_UNIQUE_REF_COUNT',count($seen)===1488,'unique_refs='.count($seen));
        $add_check('JOURNAL_SCOPE_ONLY_ALLOWED_TABLES',$scope_ok,'allowed=activities,assignments');
        $add_check('JOURNAL_STATUS_ALL_APPLIED',$status_ok,'all expected rows must be applied');
        $add_check('JOURNAL_OWNER_TRANSITIONS',$owner_ok,'before=source after=target');
        $add_check('JOURNAL_JSON_HASH_SELF_CONSISTENCY',$journal_self_hash_ok,'before_json/after_json must reproduce stored hashes');
        $add_check('LIVE_COMMITTED_ROWS_MATCH_AFTER_HASH',$live_verified===1488,'verified='.$live_verified.'/1488');

        $q_untouched_records=0;$q_report=[];
        foreach($q_by_old as $oid=>$q){
            $p=$profiles_by_old[$oid];$tid=(int)$p['target_internal_id'];$untouched=true;$reasons=(array)($q['reasons']??[]);
            foreach((array)$p['records'] as $rec){
                $ref=(string)$rec['table'].'#'.(int)$rec['record_id'];
                if(isset($seen[$ref])){$errors[]='QUARANTINE_HAS_JOURNAL_REF:'.$ref;$untouched=false;continue;}
                $table=Biawin_CRM_DB::table((string)$rec['table']);$live=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",(int)$rec['record_id']),ARRAY_A);
                if(!$live){$errors[]='QUARANTINE_ROW_MISSING:'.$ref;$untouched=false;continue;}
                $expected_row=(array)($rec['expected_row']??[]);
                if((int)($live['customer_id']??0)!==$oid){$errors[]='QUARANTINE_ROW_MOVED:'.$ref;$untouched=false;continue;}
                if((string)$rec['table']==='activities' && (string)($live['object_type']??'')==='customer' && (int)($live['object_id']??0)!==$oid){$errors[]='QUARANTINE_ACTIVITY_OBJECT_MOVED:'.$ref;$untouched=false;continue;}
                if(self::row_hash_projected($live,array_keys($expected_row))!==(string)$rec['fresh_hash']){$errors[]='QUARANTINE_ROW_HASH_CHANGED:'.$ref;$untouched=false;continue;}
                $q_untouched_records++;
            }
            $q_report[]=['old_internal_id'=>$oid,'old_customer_id'=>(string)$p['old_customer_id'],'target_internal_id'=>$tid,'corrected_target'=>(string)$p['corrected_target'],'reasons'=>$reasons,'untouched'=>$untouched];
        }
        $add_check('QUARANTINE_EXACT_ROWS_UNTOUCHED',$q_untouched_records===12,'verified='.$q_untouched_records.'/12');

        $identity_checks=0;$identity_fail=0;$customers=Biawin_CRM_DB::table('customers');
        foreach((array)$plan['profiles'] as $p){
            $oid=(int)$p['old_internal_id'];$tid=(int)$p['target_internal_id'];
            $src=$wpdb->get_row($wpdb->prepare("SELECT source_customer_id FROM $customers WHERE id=%d",$oid),ARRAY_A);
            $tgt=$wpdb->get_row($wpdb->prepare("SELECT source_customer_id,display_name FROM $customers WHERE id=%d",$tid),ARRAY_A);
            $identity_checks++;
            if(!$src || (string)($src['source_customer_id']??'')!==(string)$p['old_customer_id']){$identity_fail++;$errors[]='SOURCE_CUSTOMER_ID_CHANGED:'.$oid;}
            $identity_checks++;
            if(!$tgt || (string)($tgt['source_customer_id']??'')!==(string)$p['corrected_target'] || self::norm_name((string)($tgt['display_name']??''))!==self::norm_name((string)$p['target_name'])){$identity_fail++;$errors[]='TARGET_IDENTITY_CHANGED:'.$tid;}
            $identity_checks++;
            if(!self::target_evidence_ok($tid,$p)){$identity_fail++;$errors[]='TARGET_EVIDENCE_FAILED:'.$tid;}
        }
        $add_check('SCOPED_CUSTOMER_ID_AND_TARGET_EVIDENCE',$identity_fail===0,'checks='.$identity_checks.' failures='.$identity_fail);
        $add_check('NO_CUSTOMER_STATUS_WRITES_REPORTED',(int)$state['customer_status_updates']===0,'run_state_customer_status_updates='.(int)$state['customer_status_updates'].'; journal contains no customers rows');

        $failures=count($errors);
        return ['ok'=>$failures===0,'run'=>['id'=>(int)$run['id'],'run_uuid'=>(string)$run['run_uuid'],'status'=>(string)$run['status'],'manifest_hash'=>(string)$run['manifest_hash']],
            'counts'=>['plan_profiles'=>count($plan['profiles']),'committed_profiles'=>$committed_profiles,'quarantined_profiles'=>$quarantine_profiles,'expected_journal_records'=>count($expected),'journal_rows'=>count($jrows),'unique_journal_refs'=>count($seen),'live_verified_records'=>$live_verified,'quarantine_untouched_records'=>$q_untouched_records,'protected_identity_checks'=>$identity_checks,'failures'=>$failures],
            'checks'=>$checks,'quarantine'=>$q_report,'errors'=>$errors,'audited_at'=>current_time('mysql')];
    }

    private static function rollback_latest(): array
    {
        return ['ok'=>false,'error'=>'Rollback کل Job 500 عمداً غیرفعال است؛ Rollback باید فقط پس از بررسی Journal و Hash Guard هر Chunk انجام شود.'];
    }

    private static function verify_plan(array $plan): array
    {
        global $wpdb;$v=0;$f=0;$details=[];$assignments=Biawin_CRM_DB::table('assignments');
        foreach($plan['profiles'] as $p){$oid=(int)$p['old_internal_id'];$tid=(int)$p['target_internal_id'];foreach($p['records'] as $rec){$t=Biawin_CRM_DB::table($rec['table']);$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",(int)$rec['record_id']),ARRAY_A);$ok=$row && (int)($row['customer_id']??0)===$tid;if($ok&&$rec['table']==='activities'&&($row['object_type']??'')==='customer')$ok=(int)($row['object_id']??0)===$tid;if($ok)$v++;else{$f++;$details[]=$rec['table'].'#'.$rec['record_id'];}}
            $src_active=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id=%d AND status='assigned'",$oid));
            $tgt_active=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id=%d AND status='assigned'",$tid));
            $expects_active=false;
            foreach((array)$p['records'] as $planned_rec){
                if(($planned_rec['table']??'')==='assignments' && (($planned_rec['expected_row']['status']??'')==='assigned')){$expects_active=true;break;}
            }
            if($src_active!==0 || ($expects_active && $tgt_active!==1) || (!$expects_active && $tgt_active!==0)){$f++;$details[]='assignment_state_'.$oid.'_to_'.$tid.'_expected_'.($expects_active?'exactly_one_active':'inactive').'_src_'.$src_active.'_tgt_'.$tgt_active;}
            if(!self::target_evidence_ok($tid,$p)){$f++;$details[]='target_evidence_'.$tid;}
            $customers=Biawin_CRM_DB::table('customers');
            $src_customer=$wpdb->get_row($wpdb->prepare("SELECT source_customer_id,status FROM $customers WHERE id=%d",$oid),ARRAY_A);
            $tgt_customer=$wpdb->get_row($wpdb->prepare("SELECT source_customer_id,display_name,status FROM $customers WHERE id=%d",$tid),ARRAY_A);
            if(!$src_customer || (string)($src_customer['source_customer_id']??'')!==(string)$p['old_customer_id']){$f++;$details[]='source_identity_'.$oid;}
            if(!$tgt_customer || (string)($tgt_customer['source_customer_id']??'')!==(string)$p['corrected_target'] || self::norm_name((string)($tgt_customer['display_name']??''))!==self::norm_name((string)$p['target_name'])){$f++;$details[]='target_identity_'.$tid;}
            if($src_customer && $src_active===0 && ($src_customer['status']??'')==='assigned'){$f++;$details[]='source_status_still_assigned_'.$oid;}
            if($tgt_customer && $expects_active && ($tgt_customer['status']??'')!=='assigned'){$f++;$details[]='target_status_not_assigned_'.$tid;}
        }return ['verified_records'=>$v,'failures'=>$f,'details'=>$details];
    }

    private static function target_evidence_ok(int $target_id,array $p): bool
    {
        global $wpdb;$phones=Biawin_CRM_DB::table('phones');$invoices=Biawin_CRM_DB::table('invoices');
        $curPhones=array_map([__CLASS__,'norm_phone'],(array)$wpdb->get_col($wpdb->prepare("SELECT normalized_phone FROM $phones WHERE customer_id=%d",$target_id)));$curPhones=array_filter($curPhones);$expected=array_filter(array_map([__CLASS__,'norm_phone'],(array)($p['expected_phones']??[])));if($expected && array_diff($expected,$curPhones))return false;
        foreach((array)($p['expected_invoice_codes']??[]) as $code){$owner=(int)$wpdb->get_var($wpdb->prepare("SELECT customer_id FROM $invoices WHERE invoice_code=%s LIMIT 1",(string)$code));if($owner!==$target_id)return false;}return true;
    }

    private static function target_evidence_ok_locked(int $target_id,array $p): bool
    {
        global $wpdb;$phones=Biawin_CRM_DB::table('phones');$invoices=Biawin_CRM_DB::table('invoices');
        $phone_rows=$wpdb->get_results($wpdb->prepare("SELECT id,normalized_phone FROM $phones WHERE customer_id=%d FOR UPDATE",$target_id),ARRAY_A);
        $curPhones=[];foreach((array)$phone_rows as $pr)$curPhones[]=self::norm_phone((string)($pr['normalized_phone']??''));
        $curPhones=array_filter($curPhones);$expected=array_filter(array_map([__CLASS__,'norm_phone'],(array)($p['expected_phones']??[])));if($expected && array_diff($expected,$curPhones))return false;
        foreach((array)($p['expected_invoice_codes']??[]) as $code){$row=$wpdb->get_row($wpdb->prepare("SELECT id,customer_id FROM $invoices WHERE invoice_code=%s LIMIT 1 FOR UPDATE",(string)$code),ARRAY_A);if(!$row || (int)($row['customer_id']??0)!==$target_id)return false;}return true;
    }

    private static function current_final_safe_rows(array $ids): array
    {
        $wanted=array_fill_keys(array_map('intval',$ids),true);$rows=[];
        if(!class_exists('Biawin_CRM_Final_Safe_Set') || !method_exists('Biawin_CRM_Final_Safe_Set','stream_final_safe_set'))return $rows;
        Biawin_CRM_Final_Safe_Set::stream_final_safe_set(function($r)use(&$rows,$wanted){$id=(int)($r['old_internal_id']??0);if(isset($wanted[$id]))$rows[$id]=$r;});return $rows;
    }

    private static function has_changes_after_golden(array $ids): bool
    {
        global $wpdb;$ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids)return false;$idSql=implode(',',$ids);
        $tables=['invoices','phones','interactions','assignments','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','attachments','case_actions','activities','payments','refunds'];
        foreach($tables as $logical){$t=Biawin_CRM_DB::table($logical);if(!self::table_exists($t)||!self::has_col($t,'customer_id'))continue;$cols=self::columns($t);$time=[];foreach(['created_at','updated_at','assigned_at','completed_at','closed_at','resolved_at','paid_at'] as $c)if(isset($cols[$c]))$time[]=$c;if(!$time)continue;$parts=[];foreach($time as $c)$parts[]="`$c`>'".esc_sql(self::GOLDEN_AT)."'";$n=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE customer_id IN ($idSql) AND (".implode(' OR ',$parts).")");if($n>0)return true;}return false;
    }

    private static function is_in_manual_merge(array $ids): bool
    {
        global $wpdb;$t=Biawin_CRM_DB::table('merge_events');if(!self::table_exists($t))return false;$set=array_fill_keys(array_map('intval',$ids),1);$rows=$wpdb->get_results("SELECT primary_customer_id,merged_customer_ids FROM $t WHERE performed_by IS NOT NULL AND performed_by<>0",ARRAY_A);foreach((array)$rows as $r){$all=[(int)$r['primary_customer_id']];$j=json_decode((string)$r['merged_customer_ids'],true);if(is_array($j))foreach($j as $x)$all[]=(int)$x;foreach($all as $x)if(isset($set[$x]))return true;}return false;
    }

    private static function journal(int $run_id,string $logical,int $record_id,array $before,array $after): void
    {
        global $wpdb;$wpdb->insert(self::journal_table(),['run_id'=>$run_id,'table_name'=>$logical,'record_id'=>$record_id,'before_customer_id'=>(int)($before['customer_id']??($before['id']??0)),'after_customer_id'=>(int)($after['customer_id']??($after['id']??0)),'before_json'=>wp_json_encode($before,JSON_UNESCAPED_UNICODE),'after_json'=>wp_json_encode($after,JSON_UNESCAPED_UNICODE),'before_hash'=>self::row_hash($before),'after_hash'=>self::row_hash($after),'status'=>'applied','created_at'=>current_time('mysql')]);if(!$wpdb->insert_id)throw new Exception('JOURNAL_WRITE_FAILED_'.$logical.'_'.$record_id);
    }

    private static function ensure_journal_tables(): void
    {
        global $wpdb;
        $charset=$wpdb->get_charset_collate();$runs=self::runs_table();$journal=self::journal_table();
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        // dbDelta parses one field/index definition per line. The previous single-line DDL could be
        // misparsed as one CHANGE COLUMN statement on MariaDB, producing invalid ALTER TABLE SQL.
        $sql_runs="CREATE TABLE $runs (
"
            ."id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
"
            ."run_uuid varchar(36) NOT NULL,
"
            ."plugin_version varchar(20) NOT NULL,
"
            ."mode varchar(40) NOT NULL,
"
            ."status varchar(30) NOT NULL,
"
            ."pilot_count int(10) unsigned NOT NULL DEFAULT 0,
"
            ."started_by bigint(20) unsigned NOT NULL,
"
            ."started_at datetime NOT NULL,
"
            ."committed_at datetime NULL,
"
            ."completed_at datetime NULL,
"
            ."manifest_hash varchar(64) NOT NULL,
"
            ."preflight_json longtext NULL,
"
            ."result_json longtext NULL,
"
            ."PRIMARY KEY  (id),
"
            ."UNIQUE KEY run_uuid (run_uuid),
"
            ."KEY status (status)
"
            .") $charset;";
        $sql_journal="CREATE TABLE $journal (
"
            ."id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
"
            ."run_id bigint(20) unsigned NOT NULL,
"
            ."table_name varchar(80) NOT NULL,
"
            ."record_id bigint(20) unsigned NOT NULL,
"
            ."before_customer_id bigint(20) unsigned NULL,
"
            ."after_customer_id bigint(20) unsigned NULL,
"
            ."before_json longtext NOT NULL,
"
            ."after_json longtext NOT NULL,
"
            ."before_hash varchar(64) NOT NULL,
"
            ."after_hash varchar(64) NOT NULL,
"
            ."status varchar(30) NOT NULL,
"
            ."created_at datetime NOT NULL,
"
            ."PRIMARY KEY  (id),
"
            ."KEY run_id (run_id),
"
            ."KEY record_ref (table_name,record_id)
"
            .") $charset;";
        dbDelta($sql_runs);
        dbDelta($sql_journal);
    }

    private static function latest_run_safe()
    {
        global $wpdb;$t=self::runs_table();if(!self::table_exists($t))return null;return $wpdb->get_row("SELECT * FROM $t ORDER BY id DESC LIMIT 1",ARRAY_A);
    }
    private static function runs_table(): string { global $wpdb; return $wpdb->prefix.'bcrm_reconstruction_runs'; }
    private static function journal_table(): string { global $wpdb; return $wpdb->prefix.'bcrm_reconstruction_journal'; }
    private static function load_plan(){ $p=BIAWIN_CRM_DIR.self::PLAN_FILE;if(!is_readable($p))return null;$j=json_decode((string)file_get_contents($p),true);return is_array($j)&&count($j['profiles']??[])===self::BATCH_COUNT?$j:null; }
    private static function authorize(string $nonce): void { if((!current_user_can('bcrm_reports')&&!current_user_can('bcrm_manage_cases'))||!check_admin_referer($nonce))wp_die('دسترسی غیرمجاز است.'); }
    private static function redirect(): void { wp_safe_redirect(add_query_arg('page','biawin-crm-historical-audit',admin_url('admin.php'))); exit; }
    private static function row_diff_summary(array $expected,array $current): string
    {
        if(!$expected)return '';
        // Only compare manifest fields. Extra live columns can be generated/schema-derived and must not
        // create a false operator-change signal.
        $parts=[];$keys=array_keys($expected);sort($keys,SORT_STRING);
        foreach($keys as $k){$a=$expected[$k];$b=array_key_exists($k,$current)?$current[$k]:'__MISSING__';if((string)$a===(string)$b && !($a===null xor $b===null))continue;$av=$a===null?'NULL':(string)$a;$bv=$b===null?'NULL':(string)$b;$parts[]=$k.'['.substr($av,0,80).'=>'.substr($bv,0,80).']';if(count($parts)>=4)break;}
        return implode('|',$parts);
    }

    private static function row_hash_projected(array $row,array $keys): string
    {
        $projected=[];foreach($keys as $k)$projected[$k]=array_key_exists($k,$row)?$row[$k]:null;
        return self::row_hash($projected);
    }

    private static function generated_columns(string $table): array
    {
        global $wpdb; $out=[];
        foreach((array)$wpdb->get_results("SHOW COLUMNS FROM `$table`",ARRAY_A) as $r){
            if(stripos((string)($r['Extra']??''),'GENERATED')!==false)$out[(string)$r['Field']]=1;
        }
        return $out;
    }

    private static function row_hash(array $row): string { ksort($row,SORT_STRING);return hash('sha256',(string)wp_json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
    private static function norm_name(string $s): string { $s=str_replace(["\xEF\xBB\xBF",'ي','ك'],['','ی','ک'],trim($s));return preg_replace('/\s+/u','',$s); }
    private static function norm_phone($s): string { $d=preg_replace('/\D+/','',(string)$s);if(strlen($d)===12&&substr($d,0,2)==='98')$d='0'.substr($d,2);if(strlen($d)===10&&substr($d,0,1)==='9')$d='0'.$d;return $d; }
    private static function table_exists(string $t): bool { global $wpdb;return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t; }
    private static function columns(string $t): array { global $wpdb;if(isset(self::$columns_cache[$t]))return self::$columns_cache[$t];$o=[];foreach((array)$wpdb->get_results("SHOW COLUMNS FROM `$t`",ARRAY_A) as $r)$o[$r['Field']]=1;return self::$columns_cache[$t]=$o; }
    private static function has_col(string $t,string $c): bool { $x=self::columns($t);return isset($x[$c]); }
}
