<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.45 read-only Expert Intent & Manual Merge Reconciliation Audit.
 *
 * Purpose: separate real human intent from system migration/automation evidence,
 * reconcile manual merge decisions with historical/corrective identity mappings,
 * and produce a safe-set classification before any future commit engine exists.
 * This class performs no INSERT/UPDATE/DELETE on CRM domain tables.
 */
class Biawin_CRM_Expert_Intent_Merge_Audit
{
    private const CUTOFF = '2026-08-18 14:28:35';
    private const SAMPLE_LIMIT = 250;

    private static $system_migration_actions = [
        'invoice_ownership_corrected','customer_identity_repaired_from_invoices',
        'single_unapproved_invoice_profile_deleted','PROFILE_MARKED_INCOMPLETE',
        'ownership_repair','invoice_ownership_repair','historical_identity_repair'
    ];

    private static $strong_human_actions = [
        'assigned_to_agent','assignment_returned','interaction_created','interaction_updated',
        'phone_verification_changed','customer_code_generated','case_created','case_updated',
        'case_action_created','tag_added','tag_removed','complaint_created','complaint_updated',
        'merge','customer_merge','manual_merge'
    ];

    public static function handle(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_run_expert_intent_merge_audit')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0);
        $result=self::run(null);
        set_transient('bcrm_expert_intent_merge_audit_'.get_current_user_id(),$result,30*MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page','biawin-crm-historical-audit',admin_url('admin.php'))); exit;
    }

    public static function export_identity_csv(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_export_expert_intent_identity_csv')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0); nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-expert-intent-reconciliation-v0.8.45-'.gmdate('Ymd-His').'.csv"');
        $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['old_internal_id','old_customer_id','old_name','corrected_target','proposed_final_customer_id','destination_strategy','manifest_class','human_confirmed_events','human_inferred_events','system_migration_events','system_automation_events','unknown_events','manual_merge_events','manual_merge_primary_hits','manual_merge_merged_hits','expert_activity_after_cutoff','target_expert_activity','decision','auto_commit_allowed','reason']);
        self::run(function(array $r) use($out){ fputcsv($out,[$r['old_internal_id'],$r['old_customer_id'],$r['old_name'],$r['corrected_target'],$r['proposed_final_customer_id'],$r['destination_strategy'],$r['manifest_class'],$r['human_confirmed_events'],$r['human_inferred_events'],$r['system_migration_events'],$r['system_automation_events'],$r['unknown_events'],$r['manual_merge_events'],$r['manual_merge_primary_hits'],$r['manual_merge_merged_hits'],$r['expert_activity_after_cutoff'],$r['target_expert_activity'],$r['decision'],$r['auto_commit_allowed'],$r['reason']]); });
        fclose($out); exit;
    }

    public static function export_merge_csv(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_export_merge_reconciliation_csv')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0); nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-manual-merge-reconciliation-v0.8.45-'.gmdate('Ymd-His').'.csv"');
        $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['merge_event_id','created_at','performed_by','actor_name','merge_type','action_type','reason','primary_internal_id','primary_customer_id_now','primary_name_now','merged_internal_ids','merged_customer_ids_now','corrective_targets_involved','proposed_final_ids_involved','before_json','after_json','post_merge_human_events','decision','auto_commit_allowed','reason_code']);
        foreach(self::merge_rows() as $r) fputcsv($out,[$r['merge_event_id'],$r['created_at'],$r['performed_by'],$r['actor_name'],$r['merge_type'],$r['action_type'],$r['reason'],$r['primary_internal_id'],$r['primary_customer_id_now'],$r['primary_name_now'],$r['merged_internal_ids'],$r['merged_customer_ids_now'],$r['corrective_targets_involved'],$r['proposed_final_ids_involved'],$r['before_json'],$r['after_json'],$r['post_merge_human_events'],$r['decision'],$r['auto_commit_allowed'],$r['reason_code']]);
        fclose($out); exit;
    }

    public static function render_latest(): void
    {
        $result=get_transient('bcrm_expert_intent_merge_audit_'.get_current_user_id());
        echo '<div class="bcrm-card"><h2>v0.8.45 — Expert Intent & Manual Merge Reconciliation Audit</h2>';
        echo '<p class="description"><strong>فقط خواندنی:</strong> این مرحله تصمیم‌های واقعی کارشناسان را از تغییرات سیستمی/مهاجرتی جدا می‌کند و Mergeهای دستی را با Historical Truth، فایل اصلاحی و وضعیت فعلی تطبیق می‌دهد.</p>';
        echo '<p><strong>اصل ایمنی:</strong> Merge دستی و تغییر انسانی شواهد درجه‌یک هستند؛ اما تغییر بعد از Cutoff به‌تنهایی «فعالیت انسانی» محسوب نمی‌شود.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_run_expert_intent_merge_audit'); echo '<input type="hidden" name="action" value="bcrm_run_expert_intent_merge_audit"><button class="button button-primary button-hero">اجرای Expert Intent Audit</button></form>';
        if(is_array($result)&&!empty($result['ok'])){
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_export_expert_intent_identity_csv'); echo '<input type="hidden" name="action" value="bcrm_export_expert_intent_identity_csv"><button class="button button-secondary">دانلود CSV تطبیق نیت کارشناسان</button></form>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">'; wp_nonce_field('bcrm_export_merge_reconciliation_csv'); echo '<input type="hidden" name="action" value="bcrm_export_merge_reconciliation_csv"><button class="button button-secondary">دانلود CSV تطبیق Mergeهای دستی</button></form>';
        }
        echo '</div>';
        if(!is_array($result))return; if(empty($result['ok'])){echo '<div class="notice notice-error inline"><p>'.esc_html($result['error']??'Audit اجرا نشد.').'</p></div>';return;}
        $c=$result['counts']; echo '<div class="bcrm-card"><h2>خلاصه Expert Intent Reconciliation</h2><p class="description">شروع: '.esc_html($result['started_at']).' — پایان: '.esc_html($result['finished_at']).'</p><div class="bcrm-grid">';
        $labels=['manifest_profiles'=>'پروفایل Manifest','safe_reconstruction'=>'SAFE_RECONSTRUCTION','expert_merge_confirmed'=>'EXPERT_MERGE_CONFIRMED','expert_change_preserve'=>'EXPERT_CHANGE_PRESERVE','system_only_change'=>'SYSTEM_ONLY_CHANGE','identity_conflict'=>'IDENTITY_CONFLICT','ambiguous'=>'AMBIGUOUS','manual_merge_events'=>'Merge دستی بررسی‌شده','human_confirmed_events'=>'رویداد HUMAN_CONFIRMED','human_inferred_events'=>'رویداد HUMAN_INFERRED','system_migration_events'=>'رویداد SYSTEM_MIGRATION','system_automation_events'=>'رویداد SYSTEM_AUTOMATION','unknown_events'=>'رویداد UNKNOWN','auto_commit_candidate_profiles'=>'کاندید Auto Commit بعدی','blocked_profiles'=>'پروفایل Block شده'];
        foreach($labels as $k=>$label)echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($c[$k]??0))).'</strong><span>'.esc_html($label).'</span></div>'; echo '</div></div>';
        if(!empty($result['samples'])){echo '<div class="bcrm-card"><h2>نمونه موارد نیازمند توجه</h2><table class="widefat striped"><thead><tr><th>Old ID</th><th>نام</th><th>Target</th><th>Final</th><th>Human</th><th>System</th><th>Merge</th><th>Decision</th><th>Reason</th></tr></thead><tbody>'; foreach($result['samples'] as $r)echo '<tr><td>'.esc_html($r['old_customer_id']).'</td><td>'.esc_html($r['old_name']).'</td><td>'.esc_html($r['corrected_target']).'</td><td>'.esc_html($r['proposed_final_customer_id']).'</td><td>'.esc_html($r['human_confirmed_events']+$r['human_inferred_events']).'</td><td>'.esc_html($r['system_migration_events']+$r['system_automation_events']).'</td><td>'.esc_html($r['manual_merge_events']).'</td><td><code>'.esc_html($r['decision']).'</code></td><td>'.esc_html($r['reason']).'</td></tr>'; echo '</tbody></table></div>';}
    }

    private static function run(?callable $emitter): array
    {
        global $wpdb;
        $res=['ok'=>false,'error'=>'','started_at'=>current_time('mysql'),'finished_at'=>null,'counts'=>['manifest_profiles'=>0,'safe_reconstruction'=>0,'expert_merge_confirmed'=>0,'expert_change_preserve'=>0,'system_only_change'=>0,'identity_conflict'=>0,'ambiguous'=>0,'manual_merge_events'=>0,'human_confirmed_events'=>0,'human_inferred_events'=>0,'system_migration_events'=>0,'system_automation_events'=>0,'unknown_events'=>0,'auto_commit_candidate_profiles'=>0,'blocked_profiles'=>0],'samples'=>[]];
        if(!class_exists('Biawin_CRM_Replacement_Manifest')||!method_exists('Biawin_CRM_Replacement_Manifest','stream_manifest')){$res['error']='Manifest v0.8.43 در دسترس نیست.';return $res;}
        $plans=[]; $targets=[];
        $mr=Biawin_CRM_Replacement_Manifest::stream_manifest(function(array $m)use(&$plans,&$targets){$id=(int)($m['old_internal_id']??0);if(!$id)return;$plans[$id]=$m;$ti=(int)($m['target_internal_id']??0);if($ti)$targets[$ti]=true;});
        if(empty($mr['ok'])){$res['error']='Manifest پایه ساخته نشد: '.($mr['error']??'');return $res;}
        $res['counts']['manifest_profiles']=count($plans);
        $merge_index=self::merge_index(); $activity=self::activity_classification(array_keys($plans),array_keys($targets),$res);
        foreach($plans as $old=>$m){
            $a=$activity[$old]??self::empty_activity(); $ti=(int)($m['target_internal_id']??0); $ta=$ti?($activity[$ti]??self::empty_activity()):self::empty_activity(); $mi=$merge_index[$old]??['events'=>0,'primary'=>0,'merged'=>0];
            $decision='SAFE_RECONSTRUCTION'; $reason='نگاشت قطعی است و شواهد انسانی/سیستمی متعارض مشاهده نشد.'; $allow=1;
            if(($m['manifest_class']??'')==='AMBIGUOUS_MANUAL'||($m['destination_strategy']??'')==='MANUAL_REVIEW'){$decision='AMBIGUOUS';$reason='نگاشت تاریخی/اصلاحی یکتا نیست؛ Auto Commit ممنوع.';$allow=0;}
            elseif($mi['events']>0){
                if(($m['corrected_target']??'')!=='' && ($m['proposed_final_customer_id']??'')!=='' ){$decision='EXPERT_MERGE_CONFIRMED';$reason='پروفایل در Merge دستی کارشناس حضور دارد؛ Merge باید به‌عنوان تصمیم هویتی حفظ و قبل از Commit نهایی reconcile شود.';$allow=0;}
            }
            elseif(($a['human_confirmed']+$a['human_inferred'])>0){$decision='EXPERT_CHANGE_PRESERVE';$reason='تغییرات قابل انتساب به کارشناس وجود دارد؛ رکوردها حفظ/منتقل می‌شوند و aggregateها overwrite نمی‌شوند.';$allow=0;}
            elseif(($a['system_migration']+$a['system_automation'])>0 && $a['unknown']===0){$decision='SYSTEM_ONLY_CHANGE';$reason='شواهد تغییر موجود فقط سیستمی/مهاجرتی تشخیص داده شد؛ قابل ورود به Safe Set پس از validation رکوردبه‌رکورد.';$allow=1;}
            if($ti && ($ta['human_confirmed']+$ta['human_inferred'])>0 && $allow){$decision='IDENTITY_CONFLICT';$reason='روی Target فعالیت انسانی وجود دارد؛ merge/overwrite خودکار Target ممنوع است.';$allow=0;}
            if(($m['manifest_class']??'')==='CONFLICT_CURRENT_ACTIVITY' && $a['human_confirmed']===0 && $a['human_inferred']===0 && ($a['system_migration']+$a['system_automation'])>0){$decision='SYSTEM_ONLY_CHANGE';$reason='Conflict قبلی فقط به‌علت timestamp بعد Cutoff بود اما Evidence انسانی مستقیم پیدا نشد.';$allow=1;}
            $row=['old_internal_id'=>$old,'old_customer_id'=>$m['old_customer_id']??'','old_name'=>$m['old_name']??'','corrected_target'=>$m['corrected_target']??'','proposed_final_customer_id'=>$m['proposed_final_customer_id']??'','destination_strategy'=>$m['destination_strategy']??'','manifest_class'=>$m['manifest_class']??'','human_confirmed_events'=>$a['human_confirmed'],'human_inferred_events'=>$a['human_inferred'],'system_migration_events'=>$a['system_migration'],'system_automation_events'=>$a['system_automation'],'unknown_events'=>$a['unknown'],'manual_merge_events'=>$mi['events'],'manual_merge_primary_hits'=>$mi['primary'],'manual_merge_merged_hits'=>$mi['merged'],'expert_activity_after_cutoff'=>$a['human_after_cutoff'],'target_expert_activity'=>$ta['human_confirmed']+$ta['human_inferred'],'decision'=>$decision,'auto_commit_allowed'=>$allow,'reason'=>$reason];
            $key=strtolower($decision); if($decision==='SAFE_RECONSTRUCTION')$res['counts']['safe_reconstruction']++; elseif($decision==='EXPERT_MERGE_CONFIRMED')$res['counts']['expert_merge_confirmed']++; elseif($decision==='EXPERT_CHANGE_PRESERVE')$res['counts']['expert_change_preserve']++; elseif($decision==='SYSTEM_ONLY_CHANGE')$res['counts']['system_only_change']++; elseif($decision==='IDENTITY_CONFLICT')$res['counts']['identity_conflict']++; else $res['counts']['ambiguous']++;
            if($allow)$res['counts']['auto_commit_candidate_profiles']++; else $res['counts']['blocked_profiles']++;
            if($emitter)$emitter($row); if(!$allow && count($res['samples'])<self::SAMPLE_LIMIT)$res['samples'][]=$row;
        }
        $res['counts']['manual_merge_events']=self::count_manual_merges();
        $res['ok']=true;$res['finished_at']=current_time('mysql');return $res;
    }

    private static function activity_classification(array $old_ids,array $target_ids,array &$res): array
    {
        global $wpdb; $ids=array_values(array_unique(array_merge($old_ids,$target_ids))); $out=[]; foreach($ids as $id)$out[$id]=self::empty_activity(); if(!$ids)return $out;
        $audit=Biawin_CRM_DB::table('audit_log'); if(self::table_exists($audit)){
            $rows=$wpdb->get_results("SELECT id,actor_user_id,object_type,object_id,action,created_at,before_json,after_json FROM $audit ORDER BY id ASC",ARRAY_A);
            foreach((array)$rows as $r){$cid=(($r['object_type']??'')==='customer')?(int)($r['object_id']??0):self::extract_customer_id_from_json($r['after_json']??'', $r['before_json']??''); if(!$cid||!isset($out[$cid]))continue; $cls=self::classify_event((string)($r['action']??''),(int)($r['actor_user_id']??0)); $out[$cid][$cls]++; $res['counts'][$cls.'_events']++; if(in_array($cls,['human_confirmed','human_inferred'],true)&&self::is_after_cutoff($r['created_at']??''))$out[$cid]['human_after_cutoff']++; }
        }
        foreach(['interactions'=>'agent_user_id','assignments'=>'assigned_by','activities'=>'actor_user_id','financial_cases'=>'created_by','service_cases'=>'created_by','customer_tags'=>'created_by','attachments'=>'uploaded_by','case_actions'=>'actor_user_id'] as $logical=>$actor){$t=Biawin_CRM_DB::table($logical);if(!self::table_exists($t)||!self::has_column($t,'customer_id')||!self::has_column($t,$actor))continue;$time=self::first_existing_column($t,['created_at','assigned_at','updated_at']);$sql="SELECT customer_id,$actor actor_user_id".($time?",$time event_time":"")." FROM $t WHERE $actor IS NOT NULL AND $actor<>0";foreach((array)$wpdb->get_results($sql,ARRAY_A) as $r){$cid=(int)$r['customer_id'];if(!$cid||!isset($out[$cid]))continue;$out[$cid]['human_confirmed']++;$res['counts']['human_confirmed_events']++;if($time&&self::is_after_cutoff($r['event_time']??''))$out[$cid]['human_after_cutoff']++;}}
        return $out;
    }

    private static function classify_event(string $action,int $actor): string
    {
        if(in_array($action,self::$system_migration_actions,true)||strpos($action,'ownership_')===0||strpos($action,'historical_')===0)return 'system_migration';
        if(strpos($action,'auto_')===0||strpos($action,'workflow_')===0||strpos($action,'recalculate')!==false)return 'system_automation';
        if($actor>0 && in_array($action,self::$strong_human_actions,true))return 'human_confirmed';
        if($actor>0)return 'human_inferred';
        return 'unknown';
    }

    private static function merge_index(): array
    {
        global $wpdb; $idx=[]; $t=Biawin_CRM_DB::table('merge_events'); if(!self::table_exists($t))return $idx; foreach((array)$wpdb->get_results("SELECT primary_customer_id,merged_customer_ids,performed_by FROM $t WHERE performed_by IS NOT NULL AND performed_by<>0",ARRAY_A) as $r){$p=(int)($r['primary_customer_id']??0);if($p){if(!isset($idx[$p]))$idx[$p]=['events'=>0,'primary'=>0,'merged'=>0];$idx[$p]['events']++;$idx[$p]['primary']++;}$j=json_decode((string)($r['merged_customer_ids']??''),true);if(is_array($j))foreach($j as $x){$id=(int)$x;if(!$id)continue;if(!isset($idx[$id]))$idx[$id]=['events'=>0,'primary'=>0,'merged'=>0];$idx[$id]['events']++;$idx[$id]['merged']++;}} return $idx;
    }

    private static function merge_rows(): array
    {
        global $wpdb; $rows=[]; $t=Biawin_CRM_DB::table('merge_events'); if(!self::table_exists($t))return $rows; $cust=Biawin_CRM_DB::table('customers');
        $manifest=[]; if(class_exists('Biawin_CRM_Replacement_Manifest'))Biawin_CRM_Replacement_Manifest::stream_manifest(function(array $m)use(&$manifest){$id=(int)($m['old_internal_id']??0);if($id)$manifest[$id]=$m;});
        foreach((array)$wpdb->get_results("SELECT m.*,u.display_name actor_name FROM $t m LEFT JOIN {$wpdb->users} u ON u.ID=m.performed_by WHERE m.performed_by IS NOT NULL AND m.performed_by<>0 ORDER BY m.id ASC",ARRAY_A) as $r){$ids=[];$p=(int)($r['primary_customer_id']??0);if($p)$ids[]=$p;$j=json_decode((string)($r['merged_customer_ids']??''),true);if(is_array($j))foreach($j as $x)if((int)$x)$ids[]=(int)$x;$ids=array_values(array_unique($ids));$current=[];if($ids){$ph=implode(',',array_fill(0,count($ids),'%d'));$sql=$wpdb->prepare("SELECT id,source_customer_id,display_name FROM $cust WHERE id IN ($ph)",...$ids);foreach((array)$wpdb->get_results($sql,ARRAY_A) as $c)$current[(int)$c['id']]=$c;}$targets=[];$finals=[];foreach($ids as $id)if(isset($manifest[$id])){$targets[]=$manifest[$id]['corrected_target']??'';$finals[]=$manifest[$id]['proposed_final_customer_id']??'';}$post=self::count_post_merge_human_events($ids,(string)($r['created_at']??''));$decision='PRESERVE_MERGE';$reason='MANUAL_MERGE_IS_PRIMARY_EVIDENCE';if(count(array_unique(array_filter($targets)))>1){$decision='CONFLICT';$reason='MERGE_SPANS_MULTIPLE_CORRECTIVE_TARGETS';}elseif(!$targets){$decision='MANUAL_REVIEW';$reason='MERGE_NOT_LINKED_TO_MANIFEST';}
            $rows[]=['merge_event_id'=>$r['id']??'','created_at'=>$r['created_at']??'','performed_by'=>$r['performed_by']??'','actor_name'=>$r['actor_name']??'','merge_type'=>$r['merge_type']??'','action_type'=>$r['action_type']??'','reason'=>$r['reason']??'','primary_internal_id'=>$p,'primary_customer_id_now'=>$current[$p]['source_customer_id']??'','primary_name_now'=>$current[$p]['display_name']??'','merged_internal_ids'=>implode('|',array_diff($ids,[$p])),'merged_customer_ids_now'=>implode('|',array_map(function($id)use($current){return $current[$id]['source_customer_id']??'';},array_diff($ids,[$p]))),'corrective_targets_involved'=>implode('|',array_values(array_unique(array_filter($targets)))),'proposed_final_ids_involved'=>implode('|',array_values(array_unique(array_filter($finals)))),'before_json'=>$r['before_json']??'','after_json'=>$r['after_json']??'','post_merge_human_events'=>$post,'decision'=>$decision,'auto_commit_allowed'=>$decision==='PRESERVE_MERGE'?0:0,'reason_code'=>$reason]; }
        return $rows;
    }

    private static function count_post_merge_human_events(array $ids,string $after): int
    {global $wpdb;if(!$ids)return 0;$n=0;foreach(['interactions'=>'agent_user_id','activities'=>'actor_user_id'] as $logical=>$actor){$t=Biawin_CRM_DB::table($logical);if(!self::table_exists($t)||!self::has_column($t,'customer_id')||!self::has_column($t,$actor)||!self::has_column($t,'created_at'))continue;$ph=implode(',',array_fill(0,count($ids),'%d'));$args=$ids;$args[]=$after;$sql=$wpdb->prepare("SELECT COUNT(*) FROM $t WHERE customer_id IN ($ph) AND $actor IS NOT NULL AND $actor<>0 AND created_at>=%s",...$args);$n+=(int)$wpdb->get_var($sql);}return $n;}
    private static function count_manual_merges(): int {global $wpdb;$t=Biawin_CRM_DB::table('merge_events');if(!self::table_exists($t))return 0;return (int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE performed_by IS NOT NULL AND performed_by<>0");}
    private static function empty_activity(): array{return ['human_confirmed'=>0,'human_inferred'=>0,'system_migration'=>0,'system_automation'=>0,'unknown'=>0,'human_after_cutoff'=>0];}
    private static function extract_customer_id_from_json($a,$b): int {foreach([$a,$b] as $j){$x=json_decode((string)$j,true);if(is_array($x)){foreach(['customer_id','id','primary_customer_id'] as $k)if(!empty($x[$k])&&is_numeric($x[$k]))return (int)$x[$k];}}return 0;}
    private static function is_after_cutoff($v): bool {if(!$v)return false;$t=strtotime((string)$v);return $t&&$t>=strtotime(self::CUTOFF);}
    private static function table_exists(string $table): bool {global $wpdb;return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;}
    private static function columns(string $table): array {global $wpdb;$o=[];foreach((array)$wpdb->get_results("SHOW COLUMNS FROM `$table`",ARRAY_A) as $r)$o[(string)$r['Field']]=true;return $o;}
    private static function has_column(string $table,string $col): bool {$c=self::columns($table);return isset($c[$col]);}
    private static function first_existing_column(string $table,array $cols): string {$c=self::columns($table);foreach($cols as $x)if(isset($c[$x]))return $x;return '';}
}
