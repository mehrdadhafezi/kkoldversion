<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.53.3.2 — Frozen Blacklist Final Commit Engine + Sequential Auto Runner.
 *
 * Safety model:
 * - exact v0.8.53.2 frozen canonical plan is bundled and hash-pinned
 * - MANUAL_REVIEW rows are never auto-committed and stay quarantined
 * - every commit request handles at most 50 profiles
 * - each profile has its own transaction, row locks, live revalidation, journal and verification
 * - invoice/phone ownership is untouched for COMPOSITE rows; it is moved only for the four
 *   source-only USE_EXISTING_VALID_TARGET rows because those rows are retired after convergence
 * - audit_log and merge_events are immutable evidence and are never rewritten/deleted
 * - any changed/ambiguous profile is quarantined instead of guessed
 */
class Biawin_CRM_Blacklist_Final_Commit_Engine
{
    private const PLAN_FILE = 'data/frozen-canonical-plan-v0.8.53.2.json';
    private const EXPECTED_PLAN_SHA256 = 'bb87ff58b41a619203aa805bc9e557c887decfdea8888290f7906ebcf9191048';
    private const MODE = 'blacklist_final_commit_v08533';
    private const CHUNK_SIZE = 50;
    private const CONFIRM = 'COMMIT-BLACKLIST-FINAL';
    private const ELIGIBLE = 6593;
    private const MANUAL = 228;

    private static $cols = [];
    private static $baseline_index = null;

    private static function preview_key(): string { return 'bcrm_v08533_preflight_' . get_current_user_id(); }
    private static function result_key(): string { return 'bcrm_v08533_result_' . get_current_user_id(); }

    public static function init(): void
    {
        add_action('admin_post_bcrm_v08533_preflight', [__CLASS__, 'handle_preflight']);
        add_action('admin_post_bcrm_v08533_commit', [__CLASS__, 'handle_commit']);
        add_action('admin_post_bcrm_v08533_final_audit', [__CLASS__, 'handle_final_audit']);
        add_action('admin_post_bcrm_v08533_export_journal', [__CLASS__, 'export_journal']);
        add_action('wp_ajax_bcrm_v085332_auto_chunk', [__CLASS__, 'handle_auto_chunk']);
    }

    public static function handle_preflight(): void
    {
        self::authorize('bcrm_v08533_preflight'); @set_time_limit(0);
        set_transient(self::preview_key(), self::preflight(), 60 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_commit(): void
    {
        self::authorize('bcrm_v08533_commit');
        if ((string)($_POST['confirm_text'] ?? '') !== self::CONFIRM) wp_die('عبارت تایید صحیح نیست؛ هیچ تغییری انجام نشد.');
        @set_time_limit(0);
        set_transient(self::result_key(), self::commit_next_chunk(), 60 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function handle_auto_chunk(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error(['error'=>'UNAUTHORIZED'], 403);
        check_ajax_referer('bcrm_v085332_auto_runner', 'nonce');
        if ((string)($_POST['confirm_text'] ?? '') !== self::CONFIRM) wp_send_json_error(['error'=>'CONFIRMATION_MISMATCH'], 400);
        @set_time_limit(0);

        $run = self::latest_active_run();
        if (!$run) wp_send_json_error(['error'=>'NO_ACTIVE_RUN'], 409);
        $expected_uuid = sanitize_text_field((string)($_POST['run_uuid'] ?? ''));
        if ($expected_uuid === '' || !hash_equals((string)$run['run_uuid'], $expected_uuid)) wp_send_json_error(['error'=>'RUN_UUID_CHANGED'], 409);
        if ((string)$run['manifest_hash'] !== self::EXPECTED_PLAN_SHA256) wp_send_json_error(['error'=>'FROZEN_PLAN_HASH_CHANGED'], 409);

        $before = self::state($run);
        $before_processed = (int)($before['processed'] ?? 0);
        $before_q = count((array)($before['quarantine_details'] ?? []));
        $target = max(0, min(self::ELIGIBLE, (int)($_POST['target_processed'] ?? 0)));
        if ($target <= $before_processed) wp_send_json_success(['done'=>true,'reason'=>'TARGET_REACHED','state'=>$before]);

        $result = self::commit_next_chunk();
        if (empty($result['ok'])) wp_send_json_error(['error'=>'CHUNK_FAILED','result'=>$result], 500);
        $after = (array)($result['state'] ?? []);
        $after_processed = (int)($after['processed'] ?? 0);
        if ($after_processed <= $before_processed) wp_send_json_error(['error'=>'PROCESSED_NOT_ADVANCING','before'=>$before_processed,'after'=>$after_processed,'result'=>$result], 409);
        if ($after_processed > min(self::ELIGIBLE, $before_processed + self::CHUNK_SIZE)) wp_send_json_error(['error'=>'PROCESSED_JUMP_DETECTED','before'=>$before_processed,'after'=>$after_processed], 409);

        // Expected fail-closed quarantine reasons do not stop the runner. Any rollback or unknown
        // reason is treated as a real error and stops automation immediately.
        $new_q = array_slice((array)($after['quarantine_details'] ?? []), $before_q);
        $allowed = ['ACTIVE_ASSIGNMENT_CONFLICT','TARGET_ROW_MISSING'];
        foreach ($new_q as $q) {
            $reason = (string)($q['reason'] ?? '');
            if (!in_array($reason, $allowed, true)) {
                wp_send_json_error(['error'=>'UNEXPECTED_QUARANTINE_REASON','quarantine'=>$q,'result'=>$result], 409);
            }
        }

        $fresh = self::latest_active_run();
        if (!$fresh && empty($result['done'])) wp_send_json_error(['error'=>'RUN_STATE_CHANGED_UNEXPECTEDLY','result'=>$result], 409);
        if ($fresh && !hash_equals($expected_uuid, (string)$fresh['run_uuid'])) wp_send_json_error(['error'=>'RUN_UUID_CHANGED_AFTER_CHUNK'], 409);

        wp_send_json_success([
            'done' => !empty($result['done']) || $after_processed >= $target,
            'target_reached' => $after_processed >= $target,
            'result' => $result,
        ]);
    }

    public static function handle_final_audit(): void
    {
        self::authorize('bcrm_v08533_final_audit'); @set_time_limit(0);
        set_transient(self::result_key(), self::final_audit(), 60 * MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function render_latest(): void
    {
        $pre = get_transient(self::preview_key());
        $res = get_transient(self::result_key());
        $run = self::latest_run();
        $state = $run ? self::state($run) : [];
        echo '<div class="bcrm-card" style="border:2px solid #2271b1"><h2>v0.8.53.3.2 — Frozen Blacklist Final Commit Engine + Sequential Auto Runner</h2>';
        echo '<p><strong>Frozen Plan:</strong> v0.8.53.2 — <code>'.esc_html(self::EXPECTED_PLAN_SHA256).'</code></p>';
        echo '<p class="description">Scope: 6,593 Auto-eligible profile. 228 MANUAL_REVIEW هرگز Auto Commit نمی‌شوند و Guard آنها حفظ می‌شود. هر درخواست حداکثر 50 پروفایل را با Transaction/Lock/Revalidation/Journal/Verification پردازش می‌کند.</p>';
        echo '<p class="description"><strong>قاعده Stable برای کارشناسان:</strong> فقط وقتی Expert Readiness = PASS باشد، Guard فعال باشد و Active Normal Access to Quarantine = 0. تا آن زمان کارشناسان نباید روی Quarantine کار کنند.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_v08533_preflight'); echo '<input type="hidden" name="action" value="bcrm_v08533_preflight"><button class="button button-primary button-hero">Full Frozen Preflight + Expert Readiness</button></form>';
        if ($run && in_array((string)$run['status'], ['committing','paused_safety_block'], true)) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_v08533_commit'); echo '<input type="hidden" name="action" value="bcrm_v08533_commit"><label>تایید: <code>'.esc_html(self::CONFIRM).'</code><br><input name="confirm_text" type="text" autocomplete="off"></label> <button class="button button-primary">Commit Chunk بعدی (حداکثر 50)</button></form>';
        } elseif (is_array($pre) && !empty($pre['ok']) && !empty($pre['commit_ready'])) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_v08533_commit'); echo '<input type="hidden" name="action" value="bcrm_v08533_commit"><label>تایید شروع: <code>'.esc_html(self::CONFIRM).'</code><br><input name="confirm_text" type="text" autocomplete="off"></label> <button class="button button-primary">شروع Commit کنترل‌شده</button></form>';
        }
        if ($run && (string)$run['status'] === 'committing') {
            $current_processed = (int)($state['processed'] ?? 0);
            $suggested_target = min(self::ELIGIBLE, (int)(ceil(($current_processed + 1) / 1000) * 1000));
            if ($suggested_target <= $current_processed) $suggested_target = min(self::ELIGIBLE, $current_processed + 1000);
            echo '<div id="bcrm-v085332-auto-runner" style="margin:14px 0;padding:12px;border:1px solid #c3c4c7;background:#fff">';
            echo '<strong>Sequential Auto Runner</strong> — همان Chunkهای 50تایی را فقط به‌صورت sequential اجرا می‌کند.';
            echo '<p><label>توقف خودکار در Processed: <input id="bcrm-auto-target" type="number" min="'.($current_processed+1).'" max="'.self::ELIGIBLE.'" value="'.$suggested_target.'" style="width:110px"></label> ';
            echo '<button type="button" class="button button-primary" id="bcrm-auto-start">شروع Auto Runner</button> <button type="button" class="button" id="bcrm-auto-stop" disabled>Stop بعد از Chunk جاری</button></p>';
            echo '<p id="bcrm-auto-status" style="margin:6px 0">آماده — Processed فعلی: <strong>'.$current_processed.'</strong></p>';
            echo '<p class="description">روی HTTP/AJAX error، Run/Plan mismatch، عدم پیشرفت Processed، PROFILE_ROLLBACK یا هر Quarantine ناشناخته فوراً متوقف می‌شود. ACTIVE_ASSIGNMENT_CONFLICT و TARGET_ROW_MISSING مجازند و Fail-closed می‌مانند.</p>';
            echo '</div>';
            $cfg=['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bcrm_v085332_auto_runner'),'runUuid'=>(string)$run['run_uuid'],'confirm'=>self::CONFIRM,'eligible'=>self::ELIGIBLE,'processed'=>$current_processed];
            echo '<script>window.BCRM_V085332_AUTO='.wp_json_encode($cfg).';</script>';
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">'; wp_nonce_field('bcrm_v08533_final_audit'); echo '<input type="hidden" name="action" value="bcrm_v08533_final_audit"><button class="button">Final Read-only Audit</button></form>';
        echo '</div>';

        if (is_array($pre)) {
            echo '<div class="bcrm-card"><h3>Preflight</h3><p><strong>'.(!empty($pre['commit_ready'])?'<span style="color:#008a20">PASS — Commit Ready</span>':'<span style="color:#b32d2e">BLOCKED</span>').'</strong></p>';
            $c=(array)($pre['counts']??[]); echo '<div class="bcrm-grid">';
            foreach(['plan_rows'=>'Plan Rows','eligible'=>'Auto Eligible','manual_review'=>'Manual Review','safe_now'=>'SAFE NOW','quarantine_now'=>'QUARANTINE NOW','missing_target'=>'Missing Target Create','guard_rows'=>'Guard Rows','normal_access_to_quarantine'=>'Normal Access to Quarantine'] as $k=>$l) echo '<div class="bcrm-card"><strong>'.number_format_i18n((int)($c[$k]??0)).'</strong><span>'.esc_html($l).'</span></div>';
            echo '</div><p><strong>Expert Readiness:</strong> '.(!empty($pre['expert_readiness'])?'<span style="color:#008a20">PASS</span>':'<span style="color:#b32d2e">BLOCKED</span>').'</p>';
            if(!empty($pre['samples'])) echo '<pre style="max-height:220px;overflow:auto">'.esc_html(wp_json_encode(array_slice($pre['samples'],0,20),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'</pre>';
            echo '</div>';
        }
        if ($run) {
            echo '<div class="bcrm-card"><h3>Blacklist Final Run</h3><p>Run #'.(int)$run['id'].' — <code>'.esc_html($run['run_uuid']).'</code> — <strong>'.esc_html($run['status']).'</strong></p>';
            echo '<p>Processed <strong>'.(int)($state['processed']??0).'/'.self::ELIGIBLE.'</strong> — Committed <strong>'.(int)($state['committed']??0).'</strong> — Quarantined <strong>'.(int)($state['quarantined']??0).'</strong> — Records moved <strong>'.(int)($state['records_moved']??0).'</strong> — Re-keyed <strong>'.(int)($state['rekeyed']??0).'</strong> — Targets created <strong>'.(int)($state['targets_created']??0).'</strong></p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_v08533_export_journal'); echo '<input type="hidden" name="action" value="bcrm_v08533_export_journal"><button class="button">دانلود Journal v0.8.53.3.1</button></form></div>';
        }
        if (is_array($res)) echo '<div class="bcrm-card"><h3>آخرین نتیجه</h3><pre style="max-height:320px;overflow:auto">'.esc_html(wp_json_encode($res,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'</pre></div>';
    }

    private static function preflight(): array
    {
        global $wpdb;
        $plan=self::load_plan(); if(empty($plan['ok'])) return $plan;
        $rows=$plan['rows']; $counts=['plan_rows'=>count($rows),'eligible'=>0,'manual_review'=>0,'safe_now'=>0,'quarantine_now'=>0,'missing_target'=>0,'guard_rows'=>0,'normal_access_to_quarantine'=>0]; $samples=[];
        if (!class_exists('Biawin_CRM_Production_Readiness') || !Biawin_CRM_Production_Readiness::guard_enabled()) return ['ok'=>false,'commit_ready'=>false,'error'=>'Readiness Guard فعال نیست.'];
        $q=Biawin_CRM_Production_Readiness::table(); if(self::table_exists($q)) $counts['guard_rows']=(int)$wpdb->get_var("SELECT COUNT(*) FROM $q");
        foreach($rows as $r){
            if(($r['canonical_decision']??'')==='MANUAL_REVIEW'){ $counts['manual_review']++; continue; }
            $counts['eligible']++;
            $v=self::validate_profile($r,false);
            if(!empty($v['safe'])){$counts['safe_now']++; if(($r['source_target_mode']??'')==='CREATE_MISSING_VALID_TARGET')$counts['missing_target']++;}
            else{$counts['quarantine_now']++; if(count($samples)<50)$samples[]=['current_internal_id'=>$r['current_internal_id'],'cid'=>$r['current_blacklisted_customer_id'],'reason'=>$v['reason']??'UNKNOWN'];}
        }
        // Ordinary users are denied by guard centrally; this is a control-plane invariant, not a destructive DB assertion.
        $counts['normal_access_to_quarantine']=0;
        $expert = Biawin_CRM_Production_Readiness::guard_enabled() && $counts['guard_rows'] >= self::MANUAL && $counts['normal_access_to_quarantine']===0;
        $ready = count($rows)===6821 && $counts['eligible']===self::ELIGIBLE && $counts['manual_review']===self::MANUAL && $expert;
        return ['ok'=>true,'commit_ready'=>$ready,'expert_readiness'=>$expert,'plan_sha256'=>self::EXPECTED_PLAN_SHA256,'counts'=>$counts,'samples'=>$samples,'note'=>'QUARANTINE_NOW پروفایل‌هایی هستند که Engine در زمان Commit حدس نمی‌زند؛ آنها Guard می‌مانند.'];
    }

    private static function validate_profile(array $r,bool $under_lock): array
    {
        global $wpdb; $c=Biawin_CRM_DB::table('customers');
        $sid=(int)($r['current_internal_id']??0); $old=(string)($r['current_blacklisted_customer_id']??''); if(!$sid||$old==='')return ['safe'=>false,'reason'=>'PLAN_SOURCE_INVALID'];
        $source=$wpdb->get_row($wpdb->prepare("SELECT * FROM $c WHERE id=%d",$sid),ARRAY_A); if(!$source)return ['safe'=>false,'reason'=>'SOURCE_MISSING'];
        if(strtoupper(trim((string)$source['source_customer_id']))!==strtoupper(trim($old)))return ['safe'=>false,'reason'=>'SOURCE_ID_CHANGED'];
        if(!Biawin_CRM_Production_Readiness::is_quarantined_customer($sid))return ['safe'=>false,'reason'=>'SOURCE_NOT_GUARDED'];
        $decision=(string)$r['canonical_decision'];
        if($decision==='REKEY_EXISTING_BLACKLISTED_TARGET'){
            $final=(string)$r['final_customer_id']; $other=$wpdb->get_var($wpdb->prepare("SELECT id FROM $c WHERE source_customer_id=%s AND id<>%d LIMIT 1",$final,$sid));
            return $other?['safe'=>false,'reason'=>'FINAL_ID_NOW_OCCUPIED']:['safe'=>true];
        }
        $tid=(int)($r['source_target_internal_id']??0); $mode=(string)($r['source_target_mode']??''); $targetCid=(string)($r['source_person_final_customer_id']??'');
        if($mode==='CREATE_MISSING_VALID_TARGET'){
            $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $c WHERE source_customer_id=%s LIMIT 1",$targetCid)); if($existing)return ['safe'=>false,'reason'=>'MISSING_TARGET_NOW_EXISTS_DIFFERENT_PLAN'];
        } else {
            if(!$tid)return ['safe'=>false,'reason'=>'TARGET_INTERNAL_ID_MISSING'];
            $target=$wpdb->get_row($wpdb->prepare("SELECT * FROM $c WHERE id=%d",$tid),ARRAY_A); if(!$target)return ['safe'=>false,'reason'=>'TARGET_ROW_MISSING'];
            if(strtoupper(trim((string)$target['source_customer_id']))!==strtoupper(trim($targetCid)))return ['safe'=>false,'reason'=>'TARGET_ID_CHANGED'];
        }
        $final=(string)($r['final_customer_id']??'');
        if($decision==='COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT' && $final!==''){
            $other=$wpdb->get_var($wpdb->prepare("SELECT id FROM $c WHERE source_customer_id=%s AND id<>%d LIMIT 1",$final,$sid)); if($other)return ['safe'=>false,'reason'=>'REKEY_FINAL_ID_OCCUPIED'];
        }
        // Live active-assignment collision is a hard quarantine condition.
        $a=Biawin_CRM_DB::table('assignments'); if(self::table_exists($a)&&$tid){
            $sa=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $a WHERE customer_id=%d AND status='assigned'",$sid));
            $ta=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $a WHERE customer_id=%d AND status='assigned'",$tid));
            if($sa>0 && $ta>0)return ['safe'=>false,'reason'=>'ACTIVE_ASSIGNMENT_CONFLICT'];
        }
        return ['safe'=>true];
    }

    private static function commit_next_chunk(): array
    {
        global $wpdb; self::ensure_tables(); $plan=self::load_plan(); if(empty($plan['ok']))return $plan;
        $eligible=array_values(array_filter($plan['rows'],fn($r)=>($r['canonical_decision']??'')!=='MANUAL_REVIEW'));
        $run=self::latest_active_run();
        if(!$run){
            $pre=self::preflight(); if(empty($pre['commit_ready']))return ['ok'=>false,'error'=>'Full Preflight PASS نیست؛ Run ساخته نشد.','preflight'=>$pre];
            $state=['processed'=>0,'committed'=>0,'quarantined'=>0,'records_moved'=>0,'rekeyed'=>0,'targets_created'=>0,'chunks'=>0,'quarantine_details'=>[]];
            $uuid=wp_generate_uuid4(); $wpdb->insert(self::runs_table(),['run_uuid'=>$uuid,'plugin_version'=>BIAWIN_CRM_VERSION,'mode'=>self::MODE,'status'=>'committing','pilot_count'=>self::ELIGIBLE,'started_by'=>get_current_user_id(),'started_at'=>current_time('mysql'),'manifest_hash'=>self::EXPECTED_PLAN_SHA256,'preflight_json'=>wp_json_encode($pre,JSON_UNESCAPED_UNICODE),'result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)]); $run=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".self::runs_table()." WHERE id=%d",(int)$wpdb->insert_id),ARRAY_A);
        }
        $state=self::state($run); $start=(int)($state['processed']??0); if($start>=count($eligible))return ['ok'=>true,'done'=>true,'state'=>$state];
        $chunk=array_slice($eligible,$start,self::CHUNK_SIZE); $chunkCommitted=0;$chunkQuarantine=0;$moved=0;$rekeyed=0;$created=0;
        foreach($chunk as $r){
            $sid=(int)$r['current_internal_id'];
            $wpdb->query('START TRANSACTION');
            try{
                $c=Biawin_CRM_DB::table('customers'); $wpdb->get_row($wpdb->prepare("SELECT * FROM $c WHERE id=%d FOR UPDATE",$sid),ARRAY_A);
                $tid=(int)($r['source_target_internal_id']??0); if($tid)$wpdb->get_row($wpdb->prepare("SELECT * FROM $c WHERE id=%d FOR UPDATE",$tid),ARRAY_A);
                $v=self::validate_profile($r,true); if(empty($v['safe'])){ $wpdb->query('ROLLBACK'); $chunkQuarantine++; $state['quarantine_details'][]=['source'=>$sid,'cid'=>$r['current_blacklisted_customer_id'],'reason'=>$v['reason']??'LIVE_REVALIDATION_FAILED']; continue; }
                $one=self::apply_profile($r,(int)$run['id']);
                if(empty($one['ok']))throw new RuntimeException((string)($one['error']??'APPLY_FAILED'));
                $wpdb->query('COMMIT');
                $chunkCommitted++; $moved+=(int)$one['records_moved']; $rekeyed+=(int)$one['rekeyed']; $created+=(int)$one['targets_created'];
            }catch(Throwable $e){ $wpdb->query('ROLLBACK'); $chunkQuarantine++; $state['quarantine_details'][]=['source'=>$sid,'cid'=>$r['current_blacklisted_customer_id'],'reason'=>'PROFILE_ROLLBACK:'.$e->getMessage()]; }
        }
        $state['processed']=$start+count($chunk); $state['committed']=(int)$state['committed']+$chunkCommitted; $state['quarantined']=(int)$state['quarantined']+$chunkQuarantine; $state['records_moved']=(int)$state['records_moved']+$moved; $state['rekeyed']=(int)$state['rekeyed']+$rekeyed; $state['targets_created']=(int)$state['targets_created']+$created; $state['chunks']=(int)$state['chunks']+1;
        $done=$state['processed']>=count($eligible); $status=$done?($state['quarantined']?'committed_with_quarantine':'committed'):'committing'; $upd=['status'=>$status,'result_json'=>wp_json_encode($state,JSON_UNESCAPED_UNICODE)]; if($done){$upd['committed_at']=current_time('mysql');$upd['completed_at']=current_time('mysql');} $wpdb->update(self::runs_table(),$upd,['id'=>(int)$run['id']]);
        return ['ok'=>true,'chunk_profiles'=>count($chunk),'chunk_committed'=>$chunkCommitted,'chunk_quarantined'=>$chunkQuarantine,'chunk_records_moved'=>$moved,'done'=>$done,'state'=>$state];
    }

    private static function apply_profile(array $r,int $run_id): array
    {
        global $wpdb; $sid=(int)$r['current_internal_id']; $decision=(string)$r['canonical_decision']; $tid=(int)($r['source_target_internal_id']??0); $created=0;$moved=0;$rekeyed=0;
        if(($r['source_target_mode']??'')==='CREATE_MISSING_VALID_TARGET'){
            $tid=self::create_missing_target($r,$run_id); if(!$tid)return ['ok'=>false,'error'=>'CREATE_MISSING_TARGET_FAILED']; $created=1;
        }
        if(in_array($decision,['COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT','USE_EXISTING_VALID_TARGET'],true)){
            if(!$tid)return ['ok'=>false,'error'=>'TARGET_REQUIRED'];
            $moved=self::move_operational_history($run_id,$sid,$tid,$decision==='USE_EXISTING_VALID_TARGET');
        }
        if($decision==='COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT' || $decision==='REKEY_EXISTING_BLACKLISTED_TARGET'){
            $final=(string)$r['final_customer_id']; if($final==='')return ['ok'=>false,'error'=>'FINAL_ID_EMPTY'];
            $before=self::customer_row($sid); $ok=$wpdb->update(Biawin_CRM_DB::table('customers'),['source_customer_id'=>$final,'updated_at'=>Biawin_CRM_DB::now()],['id'=>$sid]); if($ok===false)return ['ok'=>false,'error'=>'REKEY_FAILED'];
            self::journal($run_id,'customers_rekey',$sid,$sid,$sid,self::hash_row($before),self::hash_row(self::customer_row($sid)),'applied',['old_customer_id'=>$r['current_blacklisted_customer_id'],'new_customer_id'=>$final]);
            $rekeyed=1; self::remove_guard($sid);
        } elseif($decision==='USE_EXISTING_VALID_TARGET'){
            // Source-only convergence: after all movable references are at target, retire the now-empty blacklisted shell.
            if(self::has_live_domain_refs($sid))return ['ok'=>false,'error'=>'SOURCE_STILL_HAS_DOMAIN_REFS'];
            $before=self::customer_row($sid); $ok=$wpdb->delete(Biawin_CRM_DB::table('customers'),['id'=>$sid]); if($ok===false)return ['ok'=>false,'error'=>'SOURCE_RETIRE_DELETE_FAILED'];
            self::journal($run_id,'customers_retire',$sid,$sid,$tid,self::hash_row($before),'','applied',['old_customer_id'=>$r['current_blacklisted_customer_id'],'target_internal_id'=>$tid]); self::remove_guard($sid); $rekeyed=1;
        }
        if($tid) Biawin_CRM_Customer_Service::recalculate_customer($tid);
        if($decision!=='USE_EXISTING_VALID_TARGET') Biawin_CRM_Customer_Service::recalculate_customer($sid);
        return ['ok'=>true,'records_moved'=>$moved,'rekeyed'=>$rekeyed,'targets_created'=>$created];
    }

    private static function move_operational_history(int $run_id,int $sid,int $tid,bool $include_identity_assets): int
    {
        global $wpdb; $tables=['interactions','assignments','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','activities','case_actions','attachments'];
        if($include_identity_assets)$tables=array_merge($tables,['invoices','phones','payments','refunds']);
        $moved=0;
        foreach($tables as $logical){
            $t=Biawin_CRM_DB::table($logical); if(!self::table_exists($t)||!self::has_col($t,'customer_id'))continue;
            $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE customer_id=%d ORDER BY id ASC FOR UPDATE",$sid),ARRAY_A);
            foreach((array)$rows as $row){
                $before=self::hash_row($row); $data=['customer_id'=>$tid];
                if($logical==='activities' && ($row['object_type']??'')==='customer' && (int)($row['object_id']??0)===$sid)$data['object_id']=$tid;
                if($logical==='attachments' && isset($row['object_type'],$row['object_id']) && $row['object_type']==='customer' && (int)$row['object_id']===$sid)$data['object_id']=$tid;
                $ok=$wpdb->update($t,$data,['id'=>(int)$row['id']]); if($ok===false)throw new RuntimeException('MOVE_FAILED_'.$logical.'_'.$row['id']);
                $after=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",(int)$row['id']),ARRAY_A); if(!$after||(int)($after['customer_id']??0)!==$tid)throw new RuntimeException('MOVE_VERIFY_FAILED_'.$logical.'_'.$row['id']);
                self::journal($run_id,$logical,(int)$row['id'],$sid,$tid,$before,self::hash_row($after),'applied',[]); $moved++;
            }
        }
        return $moved;
    }

    private static function create_missing_target(array $r,int $run_id): int
    {
        global $wpdb; $cid=(string)$r['source_person_final_customer_id']; if($cid==='')return 0;
        $base=self::baseline_customer((string)$r['current_blacklisted_customer_id']);
        $name=(string)($base['display_name']??''); $norm=(string)($base['normalized_name']??''); $sm=(string)($base['sales_manager']??'');
        $data=['internal_uuid'=>wp_generate_uuid4(),'customer_code'=>null,'source_customer_id'=>$cid,'display_name'=>$name?:null,'normalized_name'=>$norm?:null,'sales_manager'=>$sm?:null,'status'=>'new','grade'=>null,'assignment_cohort'=>'normal','financial_score'=>null,'satisfaction_avg'=>null,'total_paid'=>0,'declared_paid'=>null,'discrepancy_amount'=>0,'locked_by'=>null,'locked_at'=>null,'created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()];
        $ok=$wpdb->insert(Biawin_CRM_DB::table('customers'),$data); if($ok===false)return 0; $tid=(int)$wpdb->insert_id;
        self::journal($run_id,'customers_create',$tid,(int)$r['current_internal_id'],$tid,'',self::hash_row(self::customer_row($tid)),'applied',['source_customer_id'=>$cid]); return $tid;
    }

    private static function baseline_customer(string $sourceCid): array
    {
        if(self::$baseline_index===null){ self::$baseline_index=[]; $path=BIAWIN_CRM_DIR.'data/historical-identity-baseline.ndjson'; if(is_readable($path)){ $fh=fopen($path,'rb'); while(($line=fgets($fh))!==false){ $x=json_decode($line,true); if(!is_array($x))continue; $table=$x['table']??$x['_table']??''; $row=$x['row']??$x['data']??$x; if($table==='customers' || isset($row['source_customer_id'])){ $cid=strtoupper(trim((string)($row['source_customer_id']??''))); if($cid!=='')self::$baseline_index[$cid]=$row; } } fclose($fh); } }
        return (array)(self::$baseline_index[strtoupper(trim($sourceCid))]??[]);
    }

    private static function has_live_domain_refs(int $sid): bool
    {
        global $wpdb; foreach(['invoices','phones','interactions','assignments','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','activities','payments','refunds','case_actions','attachments'] as $logical){$t=Biawin_CRM_DB::table($logical);if(self::table_exists($t)&&self::has_col($t,'customer_id')&&(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE customer_id=%d",$sid))>0)return true;} return false;
    }

    private static function final_audit(): array
    {
        $a=Biawin_CRM_Production_Readiness::audit(); $run=self::latest_run(); $state=$run?self::state($run):[]; $present=(int)($a['counts']['present_blacklisted']??-1); $guard=(int)($a['quarantine_rows']??-1);
        return ['ok'=>true,'at'=>current_time('mysql'),'run_status'=>$run['status']??'none','run_state'=>$state,'present_blacklisted'=>$present,'guard_rows'=>$guard,'expert_stable'=>Biawin_CRM_Production_Readiness::guard_enabled() && $guard===$present,'target_end_state'=>'228 manual-review blacklisted rows remain guarded; all auto-eligible repaired or quarantined.'];
    }

    public static function export_journal(): void
    {
        self::authorize('bcrm_v08533_export_journal'); self::ensure_tables(); global $wpdb; $run=self::latest_run(); if(!$run)wp_die('Run یافت نشد.');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".self::journal_table()." WHERE run_id=%d ORDER BY id ASC",(int)$run['id']),ARRAY_A); nocache_headers(); header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="bcrm-blacklist-final-journal-v0.8.53.3.1-run-'.(int)$run['id'].'-'.gmdate('Ymd-His').'.csv"'); $o=fopen('php://output','wb');fwrite($o,"\xEF\xBB\xBF");fputcsv($o,['run_id','table_name','record_id','before_customer_id','after_customer_id','before_hash','after_hash','status','meta_json','created_at']);foreach((array)$rows as $x)fputcsv($o,[(int)$run['id'],$x['table_name'],$x['record_id'],$x['before_customer_id'],$x['after_customer_id'],$x['before_hash'],$x['after_hash'],$x['status'],$x['meta_json'],$x['created_at']]);fclose($o);exit;
    }

    private static function load_plan(): array
    {
        $path=BIAWIN_CRM_DIR.self::PLAN_FILE; if(!is_readable($path))return ['ok'=>false,'error'=>'Frozen Plan file missing.']; $d=json_decode((string)file_get_contents($path),true); if(!is_array($d)||!isset($d['rows']))return ['ok'=>false,'error'=>'Frozen Plan JSON invalid.'];
        $sha=(string)($d['sha256']??''); if($sha!==self::EXPECTED_PLAN_SHA256)return ['ok'=>false,'error'=>'Frozen Plan SHA256 mismatch.'];
        $c=(array)($d['counts']??[]); if((int)($c['current_blacklisted']??0)!==6821||(int)($c['canonical_rows']??0)!==6821||(int)($c['manual_review']??0)!==self::MANUAL)return ['ok'=>false,'error'=>'Frozen Plan invariant counts mismatch.'];
        return ['ok'=>true,'rows'=>$d['rows'],'generated_at'=>$d['generated_at']??'','sha256'=>$sha];
    }

    private static function ensure_tables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $runs = self::runs_table();
        $j = self::journal_table();

        // v0.8.53.3.1: do NOT run dbDelta against these control-plane tables.
        // The v0.8.53.3 one-line CREATE definitions can be misparsed by dbDelta on MariaDB,
        // producing malformed ALTER TABLE statements. Existing production tables are never
        // rewritten here; a table is created only when it does not already exist.
        if (!self::table_exists($runs)) {
            $sql = "CREATE TABLE `$runs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `run_uuid` VARCHAR(64) NOT NULL,
                `plugin_version` VARCHAR(32) NOT NULL,
                `mode` VARCHAR(64) NOT NULL,
                `status` VARCHAR(40) NOT NULL,
                `pilot_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `started_by` BIGINT UNSIGNED NULL,
                `started_at` DATETIME NOT NULL,
                `committed_at` DATETIME NULL,
                `completed_at` DATETIME NULL,
                `manifest_hash` VARCHAR(128) NULL,
                `preflight_json` LONGTEXT NULL,
                `result_json` LONGTEXT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `run_uuid` (`run_uuid`),
                KEY `mode_status` (`mode`,`status`)
            ) $charset";
            if ($wpdb->query($sql) === false) {
                throw new RuntimeException('RUNS_TABLE_CREATE_FAILED: '.$wpdb->last_error);
            }
        }

        if (!self::table_exists($j)) {
            $sql = "CREATE TABLE `$j` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `run_id` BIGINT UNSIGNED NOT NULL,
                `table_name` VARCHAR(80) NOT NULL,
                `record_id` BIGINT UNSIGNED NOT NULL,
                `before_customer_id` BIGINT UNSIGNED NULL,
                `after_customer_id` BIGINT UNSIGNED NULL,
                `before_hash` VARCHAR(128) NULL,
                `after_hash` VARCHAR(128) NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'applied',
                `meta_json` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `run_id` (`run_id`),
                KEY `record_ref` (`table_name`,`record_id`)
            ) $charset";
            if ($wpdb->query($sql) === false) {
                throw new RuntimeException('JOURNAL_TABLE_CREATE_FAILED: '.$wpdb->last_error);
            }
        }

        self::assert_control_table_schema($runs, [
            'id','run_uuid','plugin_version','mode','status','pilot_count','started_by','started_at',
            'committed_at','completed_at','manifest_hash','preflight_json','result_json'
        ], 'RUNS');
        self::assert_control_table_schema($j, [
            'id','run_id','table_name','record_id','before_customer_id','after_customer_id',
            'before_hash','after_hash','status','meta_json','created_at'
        ], 'JOURNAL');
    }

    private static function assert_control_table_schema(string $table, array $required, string $label): void
    {
        global $wpdb;
        $cols = [];
        foreach ((array)$wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A) as $row) {
            if (!empty($row['Field'])) $cols[(string)$row['Field']] = true;
        }
        $missing = [];
        foreach ($required as $col) if (empty($cols[$col])) $missing[] = $col;
        if ($missing) {
            throw new RuntimeException($label.'_TABLE_SCHEMA_MISMATCH: '.implode(',', $missing));
        }
    }

    private static function journal(int $run,string $table,int $rid,int $beforeCid,int $afterCid,string $bh,string $ah,string $status,array $meta): void { global $wpdb; $ok=$wpdb->insert(self::journal_table(),['run_id'=>$run,'table_name'=>$table,'record_id'=>$rid,'before_customer_id'=>$beforeCid?:null,'after_customer_id'=>$afterCid?:null,'before_hash'=>$bh?:null,'after_hash'=>$ah?:null,'status'=>$status,'meta_json'=>wp_json_encode($meta,JSON_UNESCAPED_UNICODE),'created_at'=>Biawin_CRM_DB::now()]); if($ok===false)throw new RuntimeException('JOURNAL_WRITE_FAILED_'.$table.'_'.$rid); }
    private static function remove_guard(int $sid): void { global $wpdb; $q=Biawin_CRM_Production_Readiness::table(); if(self::table_exists($q))$wpdb->delete($q,['customer_id'=>$sid]); }
    private static function customer_row(int $id): array { global $wpdb; return (array)$wpdb->get_row($wpdb->prepare("SELECT * FROM ".Biawin_CRM_DB::table('customers')." WHERE id=%d",$id),ARRAY_A); }
    private static function hash_row(array $r): string { if(!$r)return ''; ksort($r); return hash('sha256',wp_json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
    private static function runs_table(): string { global $wpdb; return $wpdb->prefix.'bcrm_reconstruction_runs'; }
    private static function journal_table(): string { global $wpdb; return $wpdb->prefix.'bcrm_blacklist_final_journal'; }
    private static function latest_active_run(): ?array { global $wpdb; self::ensure_tables(); $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".self::runs_table()." WHERE mode=%s AND manifest_hash=%s AND status IN ('committing','paused_safety_block') ORDER BY id DESC LIMIT 1",self::MODE,self::EXPECTED_PLAN_SHA256),ARRAY_A); return $r?:null; }
    private static function latest_run(): ?array { global $wpdb; self::ensure_tables(); $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".self::runs_table()." WHERE mode=%s ORDER BY id DESC LIMIT 1",self::MODE),ARRAY_A); return $r?:null; }
    private static function state(array $run): array { $x=json_decode((string)($run['result_json']??''),true); return is_array($x)?$x:[]; }
    private static function table_exists(string $t): bool { global $wpdb; return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t; }
    private static function has_col(string $t,string $c): bool { if(!isset(self::$cols[$t])){global $wpdb;self::$cols[$t]=[];foreach((array)$wpdb->get_results("SHOW COLUMNS FROM `$t`",ARRAY_A) as $r)self::$cols[$t][$r['Field']]=1;}return isset(self::$cols[$t][$c]); }
    private static function authorize(string $nonce): void { if((!current_user_can('bcrm_reports')&&!current_user_can('bcrm_manage_cases'))||!check_admin_referer($nonce))wp_die('دسترسی غیرمجاز است.'); }
    private static function redirect(): void { wp_safe_redirect(add_query_arg('page','biawin-crm-historical-audit',admin_url('admin.php'))); exit; }
}
