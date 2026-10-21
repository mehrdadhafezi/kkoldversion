<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.51.1 — Fresh Production Safe Set + Controlled Job 1000 Planner.
 * STRICTLY READ-ONLY for CRM domain tables. It only stores the generated report/plan
 * in a short-lived WordPress transient so the operator can inspect/download it.
 */
class Biawin_CRM_Controlled_Job_1000_Planner
{
    private const LIMIT = 1000;
    private const CHUNK_SIZE = 50;
    private const TRANSIENT_TTL = 2 * HOUR_IN_SECONDS;
    private static $columns_cache = [];

    private static function key(): string { return 'bcrm_job1000_planner_v08511_' . get_current_user_id(); }

    public static function handle(): void
    {
        self::authorize('bcrm_run_job1000_planner');
        @set_time_limit(0);

        // PHP 8.1+ / WordPress compatibility guard:
        // Some production stacks emit an E_DEPRECATED notice from wpdb::prepare()
        // before admin-post can send its redirect headers. The planner is read-only,
        // so capture/suppress only deprecation notices during this request while
        // preserving warnings/errors and record the notices for diagnostics.
        $deprecated = [];
        $buffer_level = ob_get_level();
        ob_start();
        $previous_handler = set_error_handler(static function ($severity, $message, $file, $line) use (&$deprecated) {
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                $deprecated[] = [
                    'message' => (string)$message,
                    'file' => (string)$file,
                    'line' => (int)$line,
                ];
                return true;
            }
            return false;
        });

        try {
            $r = self::run();
        } finally {
            restore_error_handler();
            while (ob_get_level() > $buffer_level) { ob_end_clean(); }
        }

        if (!empty($deprecated)) {
            $r['php_deprecation_count'] = count($deprecated);
            $r['php_deprecations'] = array_slice($deprecated, 0, 20);
        }
        set_transient(self::key(), $r, self::TRANSIENT_TTL);
        self::redirect();
    }

    public static function export_json(): void
    {
        self::authorize('bcrm_export_job1000_plan_json');
        $r = get_transient(self::key());
        if (!is_array($r) || empty($r['ok']) || empty($r['plan'])) wp_die('ابتدا Planner v0.8.51.1 را اجرا کنید.');
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="controlled-job-v0.8.51.1-planner-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($r['plan'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function export_csv(): void
    {
        self::authorize('bcrm_export_job1000_plan_csv');
        $r = get_transient(self::key());
        if (!is_array($r) || empty($r['ok']) || empty($r['profiles'])) wp_die('ابتدا Planner v0.8.51.1 را اجرا کنید.');
        nocache_headers(); header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="controlled-job-v0.8.51.1-planner-' . gmdate('Ymd-His') . '.csv"');
        $o=fopen('php://output','wb'); fwrite($o,"\xEF\xBB\xBF");
        fputcsv($o,['index','old_internal_id','old_customer_id','old_name','target_internal_id','target_customer_id','target_name','records','activities','assignments','target_invoices','target_phones','source_hash','target_hash','status']);
        foreach($r['profiles'] as $i=>$p) fputcsv($o,[$i,(int)$p['old_internal_id'],$p['old_customer_id'],$p['old_name'],(int)$p['target_internal_id'],$p['corrected_target'],$p['target_name'],count($p['records']),2,1,count($p['expected_invoice_codes']),count($p['expected_phones']),$p['source_customer_hash'],$p['target_customer_hash'],'SELECTED_SAFE_NOW']);
        fclose($o); exit;
    }

    public static function render_latest(): void
    {
        $r=get_transient(self::key());
        echo '<div class="bcrm-card" style="border:2px solid #2271b1"><h2>v0.8.51.1 — Fresh Production Safe Set + Controlled Job 1000 Planner</h2>';
        echo '<p><strong>فقط‌خواندنی:</strong> این مرحله هیچ Customer/CustomerID/Invoice/Phone/Interaction/Assignment/Activity/Merge را تغییر نمی‌دهد و هیچ Commit ندارد.</p>';
        echo '<p>Candidateها از وضعیت زنده Production دوباره ساخته می‌شوند. فقط پروفایل‌های <code>PRESERVE_AND_COMMIT + EXPERT_ACTIVITY + USE_EXISTING_VALID_TARGET</code> با دقیقاً ۲ Activity تاریخی + ۱ Assignment برگشت‌خورده، بدون Merge/فعالیت انسانی جدید/Active Assignment و با Evidence زنده مقصد انتخاب می‌شوند.</p>';
        echo '<p>هدف Planner حداکثر <strong>1,000</strong> پروفایل است؛ خروجی JSON فقط Frozen Candidate Plan برای بررسی و ساخت نسخه Commit بعدی است.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bcrm_run_job1000_planner'); echo '<input type="hidden" name="action" value="bcrm_run_job1000_planner"><button class="button button-primary button-hero">ساخت Fresh Safe Set + Job 1000 Plan (Read-only)</button></form></div>';
        if(!is_array($r))return;
        if(empty($r['ok'])){echo '<div class="notice notice-error inline"><p>'.esc_html($r['error']??'Planner ناموفق بود.').'</p></div>';return;}
        $c=$r['counts'];
        echo '<div class="bcrm-card"><h2>نتیجه Planner v0.8.51.1</h2><p>Snapshot: <strong>'.esc_html($r['snapshot_at']).'</strong> — Plan Hash: <code>'.esc_html($r['plan_hash']).'</code></p><div class="bcrm-grid">';
        foreach(['manifest_profiles'=>'Manifest Profiles','class_candidates'=>'Class Candidates','complexity_match'=>'2 Activity + 1 Returned Assignment','safe_now'=>'SAFE_NOW','selected'=>'Selected for Job 1000','blocked_active_assignment'=>'Active Assignment Block','blocked_new_human'=>'New Human Block','blocked_identity'=>'Identity/Evidence Block','blocked_other_domain'=>'Other Domain Block'] as $k=>$l) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($c[$k]??0))).'</strong><span>'.esc_html($l).'</span></div>';
        echo '</div>';
        if((int)$c['selected']===self::LIMIT) echo '<div class="notice notice-success inline"><p><strong>PLAN READY:</strong> 1,000 پروفایل Safe-Now انتخاب شد. هنوز هیچ Commit مجاز یا اجرا نشده است.</p></div>';
        else echo '<div class="notice notice-warning inline"><p><strong>PLAN NOT FULL:</strong> فقط '.esc_html(number_format_i18n((int)$c['selected'])).' پروفایل واجد شرایط زنده پیدا شد؛ Planner برای رسیدن مصنوعی به 1,000، شرط ایمنی را ضعیف نکرد.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_export_job1000_plan_json');echo '<input type="hidden" name="action" value="bcrm_export_job1000_plan_json"><button class="button button-secondary">دانلود Frozen Plan JSON</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">';wp_nonce_field('bcrm_export_job1000_plan_csv');echo '<input type="hidden" name="action" value="bcrm_export_job1000_plan_csv"><button class="button button-secondary">دانلود Candidate CSV</button></form>';
        if(!empty($r['profiles'])){echo '<h3>نمونه 100 Candidate اول</h3><table class="widefat striped"><thead><tr><th>#</th><th>Old</th><th>Name</th><th>Target</th><th>Records</th><th>Target Evidence</th></tr></thead><tbody>';foreach(array_slice($r['profiles'],0,100) as $i=>$p)echo '<tr><td>'.($i+1).'</td><td>'.esc_html($p['old_customer_id']).' (#'.(int)$p['old_internal_id'].')</td><td>'.esc_html($p['old_name']).'</td><td>'.esc_html($p['corrected_target']).' (#'.(int)$p['target_internal_id'].')</td><td>'.count($p['records']).'</td><td>'.count($p['expected_invoice_codes']).' invoice / '.count($p['expected_phones']).' phone</td></tr>';echo '</tbody></table>';}
        echo '<p class="description"><strong>Hard constraints:</strong> Blacklist مقصد معتبر نیست؛ Merge دستی/Conflict، Human Activity جدید، Active Assignment، تغییر Hash/Identity و وجود Operational Record خارج از Scope باعث حذف Candidate از Plan می‌شود. این نسخه عمداً هیچ دکمه Commit ندارد.</p></div>';
    }

    private static function run(): array
    {
        global $wpdb;
        $snapshot=current_time('mysql');
        $res=['ok'=>false,'error'=>'','snapshot_at'=>$snapshot,'counts'=>['manifest_profiles'=>0,'class_candidates'=>0,'complexity_match'=>0,'safe_now'=>0,'selected'=>0,'blocked_active_assignment'=>0,'blocked_new_human'=>0,'blocked_identity'=>0,'blocked_other_domain'=>0],'profiles'=>[],'plan'=>[],'plan_hash'=>''];
        if(!class_exists('Biawin_CRM_Final_Safe_Set') || !method_exists('Biawin_CRM_Final_Safe_Set','stream_final_safe_set')){$res['error']='Final Safe Set در دسترس نیست.';return $res;}
        $safe=[];
        $summary=Biawin_CRM_Final_Safe_Set::stream_final_safe_set(function($r)use(&$safe,&$res){
            $res['counts']['manifest_profiles']++;
            if(($r['final_class']??'')!=='PRESERVE_AND_COMMIT' || ($r['protected_expert_override']??'')!=='EXPERT_ACTIVITY' || ($r['destination_strategy']??'')!=='USE_EXISTING_VALID_TARGET' || (int)($r['manual_merge_events']??0)!==0 || (int)($r['merge_conflict']??0)!==0) return;
            $res['counts']['class_candidates']++;
            $safe[]=$r;
        });
        if(empty($summary['ok'])){$res['error']='Fresh Final Safe Set ساخته نشد.';return $res;}
        usort($safe,function($a,$b){return ((int)$a['old_internal_id'])<=>((int)$b['old_internal_id']);});
        $customers=Biawin_CRM_DB::table('customers');$activities=Biawin_CRM_DB::table('activities');$assignments=Biawin_CRM_DB::table('assignments');$invoices=Biawin_CRM_DB::table('invoices');$phones=Biawin_CRM_DB::table('phones');
        foreach($safe as $s){
            if(count($res['profiles'])>=self::LIMIT)break;
            $oid=(int)$s['old_internal_id']; $targetCid=(string)$s['proposed_final_customer_id'];
            if((int)($s['source_human_after_v45']??0)>0 || (int)($s['target_human_after_v45']??0)>0){$res['counts']['blocked_new_human']++;continue;}
            $src=$wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE id=%d",$oid),ARRAY_A);
            $tgt=$wpdb->get_row($wpdb->prepare("SELECT * FROM $customers WHERE source_customer_id=%s LIMIT 1",$targetCid),ARRAY_A);
            if(!$src || !$tgt || (string)($src['source_customer_id']??'')!==(string)$s['old_customer_id'] || (string)($tgt['source_customer_id']??'')!==$targetCid || self::norm_name((string)($src['display_name']??''))!==self::norm_name((string)($tgt['display_name']??''))){$res['counts']['blocked_identity']++;continue;}
            $tid=(int)$tgt['id']; if($tid===$oid){$res['counts']['blocked_identity']++;continue;}
            $acts=$wpdb->get_results($wpdb->prepare("SELECT * FROM $activities WHERE customer_id=%d ORDER BY id ASC",$oid),ARRAY_A);
            $asgs=$wpdb->get_results($wpdb->prepare("SELECT * FROM $assignments WHERE customer_id=%d ORDER BY id ASC",$oid),ARRAY_A);
            if(count($acts)!==2 || count($asgs)!==1 || (string)($asgs[0]['status']??'')!=='returned') continue;
            $res['counts']['complexity_match']++;
            $active=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $assignments WHERE customer_id IN (%d,%d) AND status='assigned'",$oid,$tid));
            if($active>0){$res['counts']['blocked_active_assignment']++;continue;}
            if(self::other_domain_count($oid)>0){$res['counts']['blocked_other_domain']++;continue;}
            $invoiceCodes=array_values(array_filter(array_map('strval',(array)$wpdb->get_col($wpdb->prepare("SELECT invoice_code FROM $invoices WHERE customer_id=%d AND invoice_code IS NOT NULL AND invoice_code<>'' ORDER BY id ASC",$tid)))));
            $phoneVals=array_values(array_filter(array_map('strval',(array)$wpdb->get_col($wpdb->prepare("SELECT normalized_phone FROM $phones WHERE customer_id=%d AND normalized_phone IS NOT NULL AND normalized_phone<>'' ORDER BY id ASC",$tid)))));
            if(!$invoiceCodes && !$phoneVals){$res['counts']['blocked_identity']++;continue;}
            $records=[];
            foreach($acts as $a)$records[]=['table'=>'activities','record_id'=>(int)$a['id'],'fresh_hash'=>self::row_hash($a),'expected_row'=>$a];
            $records[]=['table'=>'assignments','record_id'=>(int)$asgs[0]['id'],'fresh_hash'=>self::row_hash($asgs[0]),'expected_row'=>$asgs[0]];
            $p=['old_internal_id'=>$oid,'old_customer_id'=>(string)$s['old_customer_id'],'old_name'=>(string)($src['display_name']??$s['old_name']),'corrected_target'=>$targetCid,'target_internal_id'=>$tid,'target_name'=>(string)($tgt['display_name']??''),'expected_phones'=>$phoneVals,'expected_invoice_codes'=>$invoiceCodes,'source_customer_hash'=>self::row_hash($src),'target_customer_hash'=>self::row_hash($tgt),'records'=>$records];
            $res['profiles'][]=$p;$res['counts']['safe_now']++;$res['counts']['selected']++;
        }
        $profilePayload=$res['profiles'];
        $profilesHash=hash('sha256',wp_json_encode($profilePayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $res['plan']=['version'=>'0.8.51.1','batch_type'=>'controlled_job_1000_planner','read_only'=>true,'generated_at'=>$snapshot,'selection_rule'=>'Fresh Production Safe Set; PRESERVE_AND_COMMIT + EXPERT_ACTIVITY + USE_EXISTING_VALID_TARGET; no manual merge/conflict; no human activity after v0.8.45 snapshot; exactly 2 source activities + 1 returned assignment; no other source operational-domain records; no source/target active assignment; live target identity/evidence; deterministic old_internal_id order.','requested_profiles'=>self::LIMIT,'selected_profiles'=>count($profilePayload),'chunk_size'=>self::CHUNK_SIZE,'planned_chunks'=>(int)ceil(count($profilePayload)/self::CHUNK_SIZE),'profiles_sha256'=>$profilesHash,'profiles'=>$profilePayload];
        $res['plan_hash']=hash('sha256',wp_json_encode($res['plan'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $res['plan']['plan_sha256']=$res['plan_hash'];
        $res['ok']=true; return $res;
    }

    private static function other_domain_count(int $customer_id): int
    {
        global $wpdb; $n=0;
        foreach(['interactions','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','attachments','case_actions','payments','refunds'] as $logical){$t=Biawin_CRM_DB::table($logical);if(!self::table_exists($t)||!self::has_col($t,'customer_id'))continue;$n+=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE customer_id=%d",$customer_id));if($n>0)break;}
        return $n;
    }
    private static function norm_name(string $s): string {$s=str_replace(['ي','ك','ۀ','ة'],['ی','ک','ه','ه'],$s);$s=preg_replace('/[\s\x{200c}]+/u',' ',trim($s));return mb_strtolower((string)$s,'UTF-8');}
    private static function row_hash(array $row): string {ksort($row);foreach($row as $k=>$v)if(is_object($v)||is_array($v))$row[$k]=wp_json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return hash('sha256',wp_json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    private static function table_exists(string $t): bool {global $wpdb;return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t;}
    private static function columns(string $t): array {global $wpdb;if(isset(self::$columns_cache[$t]))return self::$columns_cache[$t];$o=[];foreach((array)$wpdb->get_results("SHOW COLUMNS FROM `$t`",ARRAY_A) as $r)$o[(string)$r['Field']]=1;return self::$columns_cache[$t]=$o;}
    private static function has_col(string $t,string $c): bool {$x=self::columns($t);return isset($x[$c]);}
    private static function authorize(string $nonce): void {if(!current_user_can('bcrm_reports')&&!current_user_can('bcrm_manage_cases'))wp_die('دسترسی غیرمجاز است.');check_admin_referer($nonce);}
    private static function redirect(): void {wp_safe_redirect(add_query_arg('page','biawin-crm-historical-audit',admin_url('admin.php')));exit;}
}
