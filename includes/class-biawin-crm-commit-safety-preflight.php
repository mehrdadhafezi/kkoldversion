<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.44 read-only Commit Safety Preflight + Record-Level Migration Plan.
 *
 * Core rule: operator/expert work is evidence, never disposable data.
 * Manual merges and every attributable operator action are inventoried from the
 * beginning of CRM history through the current moment. This class does not mutate CRM tables.
 */
class Biawin_CRM_Commit_Safety_Preflight
{
    private const CUTOFF = '2026-08-18 14:28:35';
    private const SAMPLE_LIMIT = 250;

    private static $domain_tables = [
        'invoices','phones','interactions','assignments','complaints','deficiency_cases',
        'financial_cases','service_cases','customer_tags','attachments','case_actions',
        'activities','payments','refunds'
    ];

    public static function handle(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_run_commit_safety_preflight')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        @set_time_limit(0);
        $result = self::run(false, null);
        set_transient('bcrm_commit_safety_preflight_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-historical-audit', admin_url('admin.php')));
        exit;
    }

    public static function export_record_plan_csv(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_export_record_plan_csv')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0); nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-record-level-migration-plan-v0.8.44-' . gmdate('Ymd-His') . '.csv"');
        $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['table','record_id','current_customer_internal_id','old_customer_id','corrected_target','proposed_final_customer_id','destination_strategy','manifest_class','record_created_at','record_updated_at','actor_user_id','actor_name','is_post_cutoff','manual_merge_protected','record_action','safety_reason']);
        self::run(true, function(array $r) use($out){ fputcsv($out,[$r['table'],$r['record_id'],$r['current_customer_internal_id'],$r['old_customer_id'],$r['corrected_target'],$r['proposed_final_customer_id'],$r['destination_strategy'],$r['manifest_class'],$r['record_created_at'],$r['record_updated_at'],$r['actor_user_id'],$r['actor_name'],$r['is_post_cutoff'],$r['manual_merge_protected'],$r['record_action'],$r['safety_reason']]); });
        fclose($out); exit;
    }

    public static function export_expert_ledger_csv(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_export_expert_ledger_csv')) wp_die('دسترسی غیرمجاز است.');
        @set_time_limit(0); nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-expert-activity-ledger-all-history-v0.8.44-' . gmdate('Ymd-His') . '.csv"');
        $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['event_time','source_table','record_id','customer_internal_id','customer_id_at_export','actor_role','actor_user_id','actor_name','event_type','event_detail','related_object_type','related_object_id','is_after_cutoff','is_manual_merge','raw_reference']);
        self::stream_expert_ledger(function(array $r) use($out){ fputcsv($out,[$r['event_time'],$r['source_table'],$r['record_id'],$r['customer_internal_id'],$r['customer_id_at_export'],$r['actor_role'],$r['actor_user_id'],$r['actor_name'],$r['event_type'],$r['event_detail'],$r['related_object_type'],$r['related_object_id'],$r['is_after_cutoff'],$r['is_manual_merge'],$r['raw_reference']]); });
        fclose($out); exit;
    }

    public static function render_latest(): void
    {
        $result=get_transient('bcrm_commit_safety_preflight_' . get_current_user_id());
        echo '<div class="bcrm-card"><h2>v0.8.44 — Commit Safety Preflight + Record-Level Migration Plan</h2>';
        echo '<p class="description"><strong>فقط خواندنی:</strong> این مرحله هیچ داده‌ای را تغییر نمی‌دهد. Mergeهای انجام‌شده توسط کارشناسان و تمام فعالیت‌های قابل انتساب به کاربران از ابتدای تاریخ CRM تا همین لحظه به‌عنوان داده محافظت‌شده بررسی می‌شوند.</p>';
        echo '<p><strong>قانون Commit آینده:</strong> هیچ فعالیت کارشناس، Merge دستی، تماس، تخصیص، پرونده، تگ، پیوست یا Activity نباید overwrite/delete شود. در تعارض، Auto Commit متوقف و مورد وارد صف حفاظت/بررسی می‌شود.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_run_commit_safety_preflight'); echo '<input type="hidden" name="action" value="bcrm_run_commit_safety_preflight"><button class="button button-primary button-hero">اجرای Commit Safety Preflight</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_export_expert_ledger_csv'); echo '<input type="hidden" name="action" value="bcrm_export_expert_ledger_csv"><button class="button button-secondary">دانلود تمام فعالیت‌های کارشناسان از ابتدا تا الان</button></form>';
        if (is_array($result) && !empty($result['ok'])) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">'; wp_nonce_field('bcrm_export_record_plan_csv'); echo '<input type="hidden" name="action" value="bcrm_export_record_plan_csv"><button class="button button-secondary">دانلود CSV برنامه رکوردبه‌رکورد</button></form>'; }
        echo '</div>';
        if (!is_array($result)) return;
        if (empty($result['ok'])) { echo '<div class="notice notice-error inline"><p>'.esc_html($result['error']??'Preflight اجرا نشد.').'</p></div>'; return; }
        $c=$result['counts'];
        echo '<div class="bcrm-card"><h2>خلاصه Preflight</h2><p class="description">شروع: '.esc_html($result['started_at']).' — پایان: '.esc_html($result['finished_at']).'</p><div class="bcrm-grid">';
        $labels=[
            'manifest_profiles'=>'پروفایل‌های Manifest','record_rows_seen'=>'رکوردهای دامنه بررسی‌شده','legacy_move_records'=>'رکورد Legacy قابل انتقال','expert_preserve_move_records'=>'فعالیت کارشناس که باید Preserve+Move شود','target_keep_records'=>'رکورد مقصد که باید دست‌نخورده بماند','manual_freeze_records'=>'رکوردهای Freeze دستی/مبهم','manual_merge_events'=>'Mergeهای ثبت‌شده توسط کاربر','profiles_touched_by_manual_merge'=>'پروفایل درگیر Merge دستی','merge_protected_manifest_profiles'=>'پروفایل Manifest محافظت‌شده به‌علت Merge','expert_ledger_events'=>'کل رویدادهای قابل انتساب به کارشناسان','post_cutoff_expert_events'=>'رویداد کارشناس بعد Cutoff','hard_conflict_profiles'=>'پروفایل‌های Hard Conflict قبل Commit'
        ];
        foreach($labels as $k=>$label) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($c[$k]??0))).'</strong><span>'.esc_html($label).'</span></div>';
        echo '</div></div>';
        if(!empty($result['table_counts'])) { echo '<div class="bcrm-card"><h2>برنامه رکوردها بر اساس جدول</h2><table class="widefat striped"><thead><tr><th>جدول</th><th>Legacy Move</th><th>Expert Preserve+Move</th><th>Keep Target</th><th>Manual Freeze</th></tr></thead><tbody>'; foreach($result['table_counts'] as $t=>$x) echo '<tr><td><code>'.esc_html($t).'</code></td><td>'.esc_html(number_format_i18n($x['MOVE_LEGACY']??0)).'</td><td>'.esc_html(number_format_i18n($x['PRESERVE_EXPERT_AND_MOVE']??0)).'</td><td>'.esc_html(number_format_i18n($x['KEEP_TARGET_EXPERT']??0)).'</td><td>'.esc_html(number_format_i18n($x['MANUAL_FREEZE']??0)).'</td></tr>'; echo '</tbody></table></div>'; }
        if(!empty($result['merge_samples'])) { echo '<div class="bcrm-card"><h2>نمونه Mergeهای کارشناس — محافظت اجباری</h2><table class="widefat striped"><thead><tr><th>زمان</th><th>Primary</th><th>Merged IDs</th><th>کاربر</th><th>نوع</th><th>Reason</th></tr></thead><tbody>'; foreach($result['merge_samples'] as $m) echo '<tr><td>'.esc_html($m['created_at']).'</td><td>'.esc_html($m['primary_customer_id']).'</td><td>'.esc_html($m['merged_customer_ids']).'</td><td>'.esc_html($m['actor_name'].' #'.$m['performed_by']).'</td><td>'.esc_html($m['merge_type'].'/'.$m['action_type']).'</td><td>'.esc_html($m['reason']).'</td></tr>'; echo '</tbody></table></div>'; }
    }

    private static function run(bool $emit_records=false, ?callable $record_emitter=null): array
    {
        global $wpdb;
        $res=['ok'=>false,'error'=>'','started_at'=>current_time('mysql'),'finished_at'=>null,'counts'=>['manifest_profiles'=>0,'record_rows_seen'=>0,'legacy_move_records'=>0,'expert_preserve_move_records'=>0,'target_keep_records'=>0,'manual_freeze_records'=>0,'manual_merge_events'=>0,'profiles_touched_by_manual_merge'=>0,'merge_protected_manifest_profiles'=>0,'expert_ledger_events'=>0,'post_cutoff_expert_events'=>0,'hard_conflict_profiles'=>0],'table_counts'=>[],'merge_samples'=>[]];
        if(!class_exists('Biawin_CRM_Replacement_Manifest') || !method_exists('Biawin_CRM_Replacement_Manifest','stream_manifest')) { $res['error']='Manifest v0.8.43 قابل دسترس نیست.'; return $res; }
        $plans=[]; $target_ids=[];
        $mres=Biawin_CRM_Replacement_Manifest::stream_manifest(function(array $m) use (&$plans,&$target_ids,&$res){
            $old=(int)($m['old_internal_id']??0); if(!$old) return; $plans[$old]=$m; $res['counts']['manifest_profiles']++;
            $ti=(int)($m['target_internal_id']??0); if($ti) $target_ids[$ti]=true;
        });
        if(empty($mres['ok'])) { $res['error']='Manifest پایه ساخته نشد: '.($mres['error']??''); return $res; }

        $merge_protected=self::load_manual_merge_protection($res);
        foreach($plans as $cid=>$m){ if(isset($merge_protected[$cid]) || (!empty($m['target_internal_id']) && isset($merge_protected[(int)$m['target_internal_id']]))){ $res['counts']['merge_protected_manifest_profiles']++; } if(($m['class']??'')==='AMBIGUOUS_MANUAL' || isset($merge_protected[$cid])) $res['counts']['hard_conflict_profiles']++; }

        $users=[]; foreach(get_users(['fields'=>['ID','display_name']]) as $u) $users[(int)$u->ID]=(string)$u->display_name;
        foreach(self::$domain_tables as $logical){
            $table=Biawin_CRM_DB::table($logical); if(!self::table_exists($table) || !self::has_column($table,'customer_id')) continue;
            $cols=self::columns($table); $select=['id','customer_id'];
            foreach(['created_at','updated_at','assigned_at','closed_at','resolved_at','paid_at','actor_user_id','agent_user_id','assigned_by','created_by','uploaded_by','user_id','owner_user_id','assigned_user_id','resolved_by'] as $c) if(isset($cols[$c])) $select[]=$c;
            $last=0; $limit=3000;
            do{
                $rows=$wpdb->get_results("SELECT ".implode(',',array_unique($select))." FROM $table WHERE id > ".(int)$last." ORDER BY id ASC LIMIT $limit",ARRAY_A); if(!$rows) break;
                foreach($rows as $r){ $last=max($last,(int)$r['id']); $cid=(int)$r['customer_id']; if(!isset($plans[$cid]) && !isset($target_ids[$cid])) continue; $res['counts']['record_rows_seen']++;
                    $m=$plans[$cid]??null; $is_target_only=!$m && isset($target_ids[$cid]); $created=self::first_time($r,['created_at','assigned_at','paid_at']); $updated=self::first_time($r,['updated_at','closed_at','resolved_at']); $after=self::is_after_cutoff($created) || self::is_after_cutoff($updated);
                    list($actor_id,$actor_name)=self::actor_from_row($r,$users); $merge_guard=isset($merge_protected[$cid]);
                    if($is_target_only){ $action='KEEP_TARGET_EXPERT'; $reason='رکورد روی مقصد فعلی است؛ هر داده موجود مقصد حفظ می‌شود.'; }
                    elseif(($m['class']??'')==='AMBIGUOUS_MANUAL' || $merge_guard){ $action='MANUAL_FREEZE'; $reason=$merge_guard?'Customer در Merge ثبت‌شده توسط کاربر درگیر بوده؛ Auto Move ممنوع تا تطبیق Merge.':'نگاشت هویتی مبهم است.'; }
                    elseif($after || $actor_id){ $action='PRESERVE_EXPERT_AND_MOVE'; $reason='رکورد قابل انتساب/جدید کارشناس است؛ فقط با حفظ کامل payload و audit منتقل شود، نه overwrite.'; }
                    else { $action='MOVE_LEGACY'; $reason='رکورد Legacy بدون نشانه تعارض کارشناس؛ قابل انتقال به مقصد Manifest.'; }
                    if(!isset($res['table_counts'][$logical])) $res['table_counts'][$logical]=[]; $res['table_counts'][$logical][$action]=($res['table_counts'][$logical][$action]??0)+1;
                    if($action==='MOVE_LEGACY')$res['counts']['legacy_move_records']++; elseif($action==='PRESERVE_EXPERT_AND_MOVE')$res['counts']['expert_preserve_move_records']++; elseif($action==='KEEP_TARGET_EXPERT')$res['counts']['target_keep_records']++; else $res['counts']['manual_freeze_records']++;
                    if($emit_records && $record_emitter){ $mm=$m?:['old_customer_id'=>'','corrected_target'=>'','proposed_final_customer_id'=>'','destination_strategy'=>'TARGET_ONLY','class'=>'TARGET_ONLY']; $record_emitter(['table'=>$logical,'record_id'=>(int)$r['id'],'current_customer_internal_id'=>$cid,'old_customer_id'=>$mm['old_customer_id']??'','corrected_target'=>$mm['corrected_target']??'','proposed_final_customer_id'=>$mm['proposed_final_customer_id']??'','destination_strategy'=>$mm['destination_strategy']??'','manifest_class'=>$mm['class']??'','record_created_at'=>$created,'record_updated_at'=>$updated,'actor_user_id'=>$actor_id,'actor_name'=>$actor_name,'is_post_cutoff'=>$after?1:0,'manual_merge_protected'=>$merge_guard?1:0,'record_action'=>$action,'safety_reason'=>$reason]); }
                }
            }while(count($rows)===$limit);
        }
        self::stream_expert_ledger(function(array $e) use (&$res){ $res['counts']['expert_ledger_events']++; if(!empty($e['is_after_cutoff']))$res['counts']['post_cutoff_expert_events']++; });
        $res['ok']=true; $res['finished_at']=current_time('mysql'); return $res;
    }

    private static function load_manual_merge_protection(array &$res): array
    {
        global $wpdb; $protected=[]; $t=Biawin_CRM_DB::table('merge_events'); if(!self::table_exists($t)) return $protected;
        $rows=$wpdb->get_results("SELECT m.*,u.display_name actor_name FROM $t m LEFT JOIN {$wpdb->users} u ON u.ID=m.performed_by WHERE m.performed_by IS NOT NULL AND m.performed_by<>0 ORDER BY m.id ASC",ARRAY_A);
        foreach($rows as $r){ $res['counts']['manual_merge_events']++; $ids=[]; if(!empty($r['primary_customer_id']))$ids[]=(int)$r['primary_customer_id']; $j=json_decode((string)($r['merged_customer_ids']??''),true); if(is_array($j)) foreach($j as $x) if((int)$x)$ids[]=(int)$x; foreach($ids as $id)$protected[$id]=true; if(count($res['merge_samples'])<self::SAMPLE_LIMIT)$res['merge_samples'][]=['created_at'=>$r['created_at']??'','primary_customer_id'=>$r['primary_customer_id']??'','merged_customer_ids'=>$r['merged_customer_ids']??'','performed_by'=>$r['performed_by']??'','actor_name'=>$r['actor_name']??'','merge_type'=>$r['merge_type']??'','action_type'=>$r['action_type']??'','reason'=>$r['reason']??'']; }
        $res['counts']['profiles_touched_by_manual_merge']=count($protected); return $protected;
    }

    private static function stream_expert_ledger(callable $emit): void
    {
        global $wpdb; $users=[]; foreach(get_users(['fields'=>['ID','display_name']]) as $u)$users[(int)$u->ID]=(string)$u->display_name; $cust=Biawin_CRM_DB::table('customers');
        $specs=[
            ['interactions','agent_user_id','created_at','interaction','type','id'],
            ['assignments','assigned_by','assigned_at','assignment_by','status','id'],
            ['assignments','agent_user_id','assigned_at','assignment_agent','status','id'],
            ['financial_cases','created_by','created_at','financial_case_created','status','id'],
            ['financial_cases','resolved_by','resolved_at','financial_case_resolved','status','id'],
            ['service_cases','created_by','created_at','service_case_created','status','id'],
            ['service_cases','assigned_by','assigned_at','service_case_assigned','status','id'],
            ['deficiency_cases','created_by','created_at','deficiency_case_created','status','id'],
            ['case_actions','user_id','created_at','case_action','action_type','case_id'],
            ['customer_tags','created_by','created_at','customer_tag','tag','case_id'],
            ['attachments','uploaded_by','created_at','attachment_uploaded','file_kind','object_id'],
            ['activities','actor_user_id','created_at','activity','activity_type','object_id'],
            ['refunds','created_by','created_at','refund_created','status','id'],
        ];
        foreach($specs as $sp){ list($logical,$actor,$time,$etype,$detail,$obj)=$sp; $t=Biawin_CRM_DB::table($logical); if(!self::table_exists($t)||!self::has_column($t,$actor)||!self::has_column($t,$time))continue; $detail_sql=self::has_column($t,$detail)?"x.`$detail`":"''"; $obj_sql=self::has_column($t,$obj)?"x.`$obj`":"NULL"; $sql="SELECT x.id,x.customer_id,x.`$actor` actor_user_id,x.`$time` event_time,$detail_sql event_detail,$obj_sql related_object_id,c.source_customer_id,u.display_name actor_name FROM $t x LEFT JOIN $cust c ON c.id=x.customer_id LEFT JOIN {$wpdb->users} u ON u.ID=x.`$actor` WHERE x.`$actor` IS NOT NULL AND x.`$actor`<>0 ORDER BY x.id ASC"; foreach($wpdb->get_results($sql,ARRAY_A) as $r){ $emit(['event_time'=>$r['event_time']??'','source_table'=>$logical,'record_id'=>$r['id']??'','customer_internal_id'=>$r['customer_id']??'','customer_id_at_export'=>$r['source_customer_id']??'','actor_role'=>$actor,'actor_user_id'=>$r['actor_user_id']??'','actor_name'=>$r['actor_name']??($users[(int)($r['actor_user_id']??0)]??''),'event_type'=>$etype,'event_detail'=>$r['event_detail']??'','related_object_type'=>$logical,'related_object_id'=>$r['related_object_id']??'','is_after_cutoff'=>self::is_after_cutoff($r['event_time']??'')?1:0,'is_manual_merge'=>0,'raw_reference'=>$logical.'#'.($r['id']??'')]); } }
        $mt=Biawin_CRM_DB::table('merge_events'); if(self::table_exists($mt)){ foreach($wpdb->get_results("SELECT m.*,u.display_name actor_name,c.source_customer_id FROM $mt m LEFT JOIN {$wpdb->users} u ON u.ID=m.performed_by LEFT JOIN $cust c ON c.id=m.primary_customer_id WHERE m.performed_by IS NOT NULL AND m.performed_by<>0 ORDER BY m.id ASC",ARRAY_A) as $r){ $emit(['event_time'=>$r['created_at']??'','source_table'=>'merge_events','record_id'=>$r['id']??'','customer_internal_id'=>$r['primary_customer_id']??'','customer_id_at_export'=>$r['source_customer_id']??'','actor_role'=>'performed_by','actor_user_id'=>$r['performed_by']??'','actor_name'=>$r['actor_name']??'','event_type'=>'customer_merge','event_detail'=>trim(($r['merge_type']??'').' / '.($r['action_type']??'').' / '.($r['reason']??'')),'related_object_type'=>'merged_customer_ids','related_object_id'=>$r['merged_customer_ids']??'','is_after_cutoff'=>self::is_after_cutoff($r['created_at']??'')?1:0,'is_manual_merge'=>1,'raw_reference'=>'merge_events#'.($r['id']??'')]); } }
        $at=Biawin_CRM_DB::table('audit_log'); if(self::table_exists($at)){ foreach($wpdb->get_results("SELECT a.*,u.display_name actor_name,c.source_customer_id FROM $at a LEFT JOIN {$wpdb->users} u ON u.ID=a.actor_user_id LEFT JOIN $cust c ON c.id=a.object_id AND a.object_type='customer' WHERE a.actor_user_id IS NOT NULL AND a.actor_user_id<>0 ORDER BY a.id ASC",ARRAY_A) as $r){ $emit(['event_time'=>$r['created_at']??'','source_table'=>'audit_log','record_id'=>$r['id']??'','customer_internal_id'=>(($r['object_type']??'')==='customer'?($r['object_id']??''):''),'customer_id_at_export'=>$r['source_customer_id']??'','actor_role'=>'actor_user_id','actor_user_id'=>$r['actor_user_id']??'','actor_name'=>$r['actor_name']??'','event_type'=>$r['action']??'audit','event_detail'=>'','related_object_type'=>$r['object_type']??'','related_object_id'=>$r['object_id']??'','is_after_cutoff'=>self::is_after_cutoff($r['created_at']??'')?1:0,'is_manual_merge'=>0,'raw_reference'=>'audit_log#'.($r['id']??'')]); } }
    }

    private static function first_time(array $r,array $keys): string { foreach($keys as $k) if(!empty($r[$k])) return (string)$r[$k]; return ''; }
    private static function is_after_cutoff($v): bool { if(!$v)return false; $t=strtotime((string)$v); return $t && $t>=strtotime(self::CUTOFF); }
    private static function actor_from_row(array $r,array $users): array { foreach(['actor_user_id','agent_user_id','assigned_by','created_by','uploaded_by','user_id','owner_user_id','assigned_user_id','resolved_by'] as $k){ if(!empty($r[$k])){ $id=(int)$r[$k]; return [$id,$users[$id]??'']; }} return [0,'']; }
    private static function table_exists(string $table): bool { global $wpdb; return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table; }
    private static function columns(string $table): array { global $wpdb; $out=[]; foreach((array)$wpdb->get_results("SHOW COLUMNS FROM `$table`",ARRAY_A) as $r)$out[(string)$r['Field']]=true; return $out; }
    private static function has_column(string $table,string $column): bool { $c=self::columns($table); return isset($c[$column]); }
}
