<?php

if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.42 read-only Forward Reconstruction planner.
 * Produces a migration plan only; never updates CRM domain tables.
 */
class Biawin_CRM_Forward_Reconstruction_Planner
{
    private const BASELINE_FILE = 'data/historical-identity-baseline.ndjson';
    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';
    private const BATCH_SIZE = 200;
    private const SAMPLE_LIMIT = 250;
    private const BASELINE_CUTOFF = '2026-08-18 14:28:35';
    private static $blacklist_cache = null;

    public static function handle(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_run_forward_reconstruction_planner')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        @set_time_limit(0);
        $result = self::run();
        set_transient('bcrm_forward_reconstruction_plan_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-historical-audit', admin_url('admin.php')));
        exit;
    }

    public static function render_latest(): void
    {
        $result = get_transient('bcrm_forward_reconstruction_plan_' . get_current_user_id());
        echo '<div class="bcrm-card"><h2>v0.8.42 — Forward Reconstruction Dry Run Planner (مرحله قبلی)</h2>';
        echo '<p class="description">این Planner فقط برنامه مهاجرت می‌سازد و هیچ UPDATE/DELETE/INSERT روی جداول CRM انجام نمی‌دهد. CustomerIDهای Blacklist مقصد معتبر نیستند.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_run_forward_reconstruction_planner');
        echo '<input type="hidden" name="action" value="bcrm_run_forward_reconstruction_planner">';
        echo '<button class="button button-primary button-hero">اجرای Dry Run Planner</button></form></div>';
        if (!is_array($result)) return;
        if (empty($result['ok'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html((string)($result['error'] ?? 'Planner اجرا نشد.')) . '</p></div>';
            return;
        }
        $c = $result['counts'];
        echo '<div class="bcrm-card"><h2>خلاصه برنامه بازسازی</h2><p class="description">شروع: '.esc_html($result['started_at']).' — پایان: '.esc_html($result['finished_at']).'</p><div class="bcrm-grid">';
        $labels = [
            'profiles'=>'پروفایل تاریخی بررسی‌شده',
            'safe_auto'=>'SAFE_AUTO',
            'new_id_required'=>'NEW_ID_REQUIRED',
            'target_create_required'=>'TARGET_CREATE_REQUIRED',
            'ambiguous_manual'=>'AMBIGUOUS_MANUAL',
            'conflict_current_activity'=>'CONFLICT_CURRENT_ACTIVITY',
            'no_action'=>'NO_ACTION',
            'blacklisted_current_rows'=>'رکورد فعلی با CustomerID ممنوع',
            'blacklisted_corrected_targets'=>'مقصد فایل اصلاحی ممنوع',
            'historical_complete_recoverable'=>'Complete تاریخی قابل بازیابی',
            'stranded_declared_recoverable'=>'Declared تاریخی قابل بازیابی',
            'recent_activity_profiles'=>'دارای فعالیت جدید بعد از Cutoff',
            'replacement_ids_needed'=>'CustomerID جدید موردنیاز',
        ];
        foreach ($labels as $k=>$label) echo '<div class="bcrm-card"><strong>'.esc_html(number_format_i18n((int)($c[$k]??0))).'</strong><span>'.esc_html($label).'</span></div>';
        echo '</div>';
        echo '<p><strong>Cutoff تاریخی:</strong> '.esc_html(self::BASELINE_CUTOFF).' — هر فعالیت قابل تشخیص بعد از این زمان به‌عنوان کار جدید کارشناسان علامت‌گذاری می‌شود و از Auto Repair نهایی باید محافظت شود.</p></div>';

        if (!empty($result['samples'])) {
            echo '<div class="bcrm-card"><h2>نمونه Planها</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr>';
            foreach (['Plan','Old Internal ID','Old CustomerID','Old Name','Corrected Target','Expected Name','Current Old CustomerID','Current Old Name','Current Target Internal','Current Target Name','Recent Activity','Historical Ops','Complete Old','Complete Target','Declared Old','Declared Old Row','Declared Target','Replacement','Reason'] as $h) echo '<th>'.esc_html($h).'</th>';
            echo '</tr></thead><tbody>';
            foreach ($result['samples'] as $s) {
                echo '<tr>';
                foreach (['plan','old_internal_id','old_customer_id','old_name','corrected_target','expected_name','current_old_customer_id','current_old_name','target_internal_id','target_name','recent_activity','historical_ops','old_complete','target_complete','old_declared','old_row_declared','target_declared','replacement','reason'] as $k) {
                    $v = $s[$k] ?? '';
                    if (in_array($k,['old_declared','old_row_declared','target_declared'],true) && is_numeric($v)) $v = number_format_i18n((float)$v);
                    echo '<td>'.esc_html((string)$v).'</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    }

    private static function run(): array
    {
        $result = [
            'ok'=>false,'error'=>'','started_at'=>current_time('mysql'),'finished_at'=>null,
            'counts'=>[
                'profiles'=>0,'safe_auto'=>0,'new_id_required'=>0,'target_create_required'=>0,
                'ambiguous_manual'=>0,'conflict_current_activity'=>0,'no_action'=>0,
                'blacklisted_current_rows'=>0,'blacklisted_corrected_targets'=>0,
                'historical_complete_recoverable'=>0,'stranded_declared_recoverable'=>0,
                'recent_activity_profiles'=>0,'replacement_ids_needed'=>0,
            ],
            'samples'=>[],
        ];
        $path = BIAWIN_CRM_DIR . self::BASELINE_FILE;
        if (!is_readable($path)) { $result['error']='Baseline تاریخی پیدا نشد.'; return $result; }
        if (!self::load_blacklist()) { $result['error']='Blacklist پیدا نشد.'; return $result; }
        $fh = fopen($path,'rb');
        if (!$fh) { $result['error']='Baseline قابل خواندن نیست.'; return $result; }
        $meta = json_decode(trim((string)fgets($fh)),true);
        if (!is_array($meta) || ($meta['type']??'')!=='meta') { fclose($fh); $result['error']='Baseline معتبر نیست.'; return $result; }
        $batch=[];
        while (($line=fgets($fh))!==false) {
            $row=json_decode(trim($line),true);
            if (!is_array($row) || ($row['type']??'')!=='profile') continue;
            $batch[]=$row['profile']??[];
            if (count($batch)>=self::BATCH_SIZE) { self::plan_batch($batch,$result); $batch=[]; }
        }
        if ($batch) self::plan_batch($batch,$result);
        fclose($fh);
        $result['ok']=true; $result['finished_at']=current_time('mysql');
        return $result;
    }

    private static function plan_batch(array $profiles, array &$result): void
    {
        global $wpdb;
        $customers=Biawin_CRM_DB::table('customers');
        $internal=[];$targets=[];
        foreach($profiles as $p){
            $id=absint($p['old_internal_id']??0); if($id)$internal[$id]=$id;
            $t=trim((string)($p['target_customer_id']??'')); if($t!=='')$targets[$t]=$t;
        }
        $byInternal=[];$bySource=[];
        if($internal){$ph=implode(',',array_fill(0,count($internal),'%d'));$sql=$wpdb->prepare("SELECT id,source_customer_id,display_name,profile_completeness,status,total_paid,declared_paid,updated_at FROM $customers WHERE id IN ($ph)",...array_values($internal));foreach((array)$wpdb->get_results($sql) as $r)$byInternal[(int)$r->id]=$r;}
        if($targets){$ph=implode(',',array_fill(0,count($targets),'%s'));$sql=$wpdb->prepare("SELECT id,source_customer_id,display_name,profile_completeness,status,total_paid,declared_paid,updated_at FROM $customers WHERE source_customer_id IN ($ph)",...array_values($targets));foreach((array)$wpdb->get_results($sql) as $r)$bySource[(string)$r->source_customer_id]=$r;}
        $recentByCustomer=self::detect_recent_activity(array_values($internal));

        foreach($profiles as $p){
            $result['counts']['profiles']++;
            $mapping=(string)($p['mapping_status']??'none');
            $oldId=absint($p['old_internal_id']??0);$oldCid=(string)($p['old_source_customer_id']??'');$targetCid=(string)($p['target_customer_id']??'');
            $oldRow=$byInternal[$oldId]??null;$targetRow=$bySource[$targetCid]??null;
            $history=is_array($p['history_counts']??null)?$p['history_counts']:[];$historyTotal=array_sum(array_map('intval',$history));
            $recent=(int)($recentByCustomer[$oldId]??0);
            if($recent>0)$result['counts']['recent_activity_profiles']++;
            $currentBlacklisted=$oldRow && self::is_blacklisted((string)$oldRow->source_customer_id);
            if($currentBlacklisted)$result['counts']['blacklisted_current_rows']++;
            $targetBlacklisted=$targetCid!=='' && self::is_blacklisted($targetCid);
            if($targetBlacklisted)$result['counts']['blacklisted_corrected_targets']++;

            $plan='NO_ACTION';$replacement='';$reason='';
            if($mapping==='ambiguous' || $mapping==='none') {
                $plan='AMBIGUOUS_MANUAL';$reason='نگاشت تاریخی یکتا نیست؛ Split/Merge نیازمند بررسی دستی است.';
            } elseif($recent>0 && ($currentBlacklisted || $targetBlacklisted || !$targetRow || ($targetRow && (int)$targetRow->id!==$oldId))) {
                $plan='CONFLICT_CURRENT_ACTIVITY';$reason='پس از Cutoff فعالیت جدید روی رکورد قدیمی ثبت شده؛ Auto Repair باید قبل از Commit این فعالیت‌ها را جداگانه حفاظت/انتقال دهد.';
                if($targetBlacklisted || $currentBlacklisted){$replacement='ALLOCATE_NEW_VALID_ID';$result['counts']['replacement_ids_needed']++;}
            } elseif($targetBlacklisted || $currentBlacklisted) {
                $plan='NEW_ID_REQUIRED';$replacement='ALLOCATE_NEW_VALID_ID';$reason='CustomerID فعلی یا مقصد فایل اصلاحی در Blacklist است و باید ID معتبر جدید اختصاص یابد.';$result['counts']['replacement_ids_needed']++;
            } elseif(!$targetRow && $targetCid!=='') {
                $plan='TARGET_CREATE_REQUIRED';$reason='مقصد معتبر فایل اصلاحی در CRM فعلی وجود ندارد و باید در Commit کنترل‌شده ساخته شود.';
            } elseif($targetRow && (int)$targetRow->id!==$oldId) {
                $plan='SAFE_AUTO';$reason='نگاشت قطعی است، مقصد معتبر موجود است و فعالیت جدید روی رکورد قدیمی تشخیص داده نشد.';
            } elseif($targetRow && (int)$targetRow->id===$oldId && (string)$oldRow->source_customer_id===$targetCid) {
                $plan='NO_ACTION';$reason='هویت فعلی با مقصد قطعی یکسان است.';
            } else {
                $plan='AMBIGUOUS_MANUAL';$reason='وضعیت فعلی به الگوی امن خودکار نمی‌خورد.';
            }
            $key=strtolower($plan);
            if(isset($result['counts'][$key]))$result['counts'][$key]++;

            $oldComplete=(string)($p['old_profile_completeness']??'');
            if($oldComplete==='complete' && $targetRow && (string)$targetRow->profile_completeness!=='complete' && in_array($plan,['SAFE_AUTO','NEW_ID_REQUIRED','TARGET_CREATE_REQUIRED','CONFLICT_CURRENT_ACTIVITY'],true)) $result['counts']['historical_complete_recoverable']++;
            $oldDeclared=$p['old_declared_paid']??null;
            if($oldRow && $oldDeclared!==null && $oldDeclared!=='' && self::money_equal($oldRow->declared_paid??null,$oldDeclared) && (!$targetRow || !self::money_equal($targetRow->declared_paid??null,$oldDeclared))) $result['counts']['stranded_declared_recoverable']++;

            if(count($result['samples'])<self::SAMPLE_LIMIT && ($plan!=='NO_ACTION' || $oldComplete==='complete')) {
                $result['samples'][]=[
                    'plan'=>$plan,'old_internal_id'=>$oldId,'old_customer_id'=>$oldCid,'old_name'=>(string)($p['old_name']??''),'corrected_target'=>$targetCid,'expected_name'=>(string)($p['expected_name']??''),
                    'current_old_customer_id'=>$oldRow?(string)$oldRow->source_customer_id:'-','current_old_name'=>$oldRow?(string)$oldRow->display_name:'-',
                    'target_internal_id'=>$targetRow?(int)$targetRow->id:'-','target_name'=>$targetRow?(string)$targetRow->display_name:'-',
                    'recent_activity'=>$recent,'historical_ops'=>$historyTotal,'old_complete'=>$oldComplete,'target_complete'=>$targetRow?(string)$targetRow->profile_completeness:'-',
                    'old_declared'=>$oldDeclared,'old_row_declared'=>$oldRow?$oldRow->declared_paid:null,'target_declared'=>$targetRow?$targetRow->declared_paid:null,
                    'replacement'=>$replacement,'reason'=>$reason,
                ];
            }
        }
    }

    private static function detect_recent_activity(array $customerIds): array
    {
        global $wpdb;
        $out=[]; if(!$customerIds)return $out;
        $tables=['interactions','assignments','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','attachments','case_actions','activities','payments','refunds'];
        foreach($tables as $name){
            $table=Biawin_CRM_DB::table($name);
            $cols=(array)$wpdb->get_col($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME IN ('customer_id','created_at','updated_at')",$table));
            if(!in_array('customer_id',$cols,true))continue;
            $dateCol=in_array('updated_at',$cols,true)?'updated_at':(in_array('created_at',$cols,true)?'created_at':''); if(!$dateCol)continue;
            foreach(array_chunk($customerIds,300) as $chunk){
                $ph=implode(',',array_fill(0,count($chunk),'%d'));
                $sql=$wpdb->prepare("SELECT customer_id,COUNT(*) c FROM $table WHERE customer_id IN ($ph) AND $dateCol > %s GROUP BY customer_id",...array_merge($chunk,[self::BASELINE_CUTOFF]));
                foreach((array)$wpdb->get_results($sql) as $r)$out[(int)$r->customer_id]=($out[(int)$r->customer_id]??0)+(int)$r->c;
            }
        }
        return $out;
    }

    private static function load_blacklist(): array
    {
        if(is_array(self::$blacklist_cache))return self::$blacklist_cache;
        $path=BIAWIN_CRM_DIR.self::BLACKLIST_FILE;if(!is_readable($path))return self::$blacklist_cache=[];
        $set=[];foreach((array)file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $id){$id=strtoupper(trim((string)$id));if(preg_match('/^C\d{6}$/',$id))$set[$id]=true;}
        return self::$blacklist_cache=$set;
    }
    private static function is_blacklisted(string $id): bool { $s=self::load_blacklist(); return isset($s[strtoupper(trim($id))]); }
    private static function money_equal($a,$b): bool { if($a===null||$a===''||$b===null||$b==='')return false; return abs((float)$a-(float)$b)<0.01; }
}
