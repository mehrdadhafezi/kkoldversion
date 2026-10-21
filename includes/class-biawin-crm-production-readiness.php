<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.53 Production Readiness + Blacklist Final Reconciliation.
 *
 * This class never rewrites customer identity/domain rows. It maintains a small
 * control-plane quarantine table so ordinary agents cannot work on identities
 * whose CustomerID is in the official blacklist while reconciliation continues.
 */
class Biawin_CRM_Production_Readiness
{
    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';
    private const OPTION_GUARD = 'bcrm_v0853_readiness_guard_enabled';
    private const OPTION_SYNCED_AT = 'bcrm_v0853_readiness_guard_synced_at';
    private static $blacklist = null;

    public static function init(): void
    {
        add_action('admin_post_bcrm_v0853_readiness_audit', [__CLASS__, 'handle_audit']);
        add_action('admin_post_bcrm_v0853_sync_guard', [__CLASS__, 'handle_sync_guard']);
        add_action('admin_post_bcrm_v0853_disable_guard', [__CLASS__, 'handle_disable_guard']);
        add_action('admin_post_bcrm_v0853_export_reconciliation', [__CLASS__, 'export_reconciliation_csv']);
    }

    public static function table(): string
    {
        return Biawin_CRM_DB::table('readiness_quarantine');
    }

    public static function ensure_schema(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            source_customer_id VARCHAR(64) NULL,
            reason VARCHAR(64) NOT NULL DEFAULT 'BLACKLISTED_CUSTOMER_ID',
            detected_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY customer_id (customer_id),
            KEY source_customer_id (source_customer_id),
            KEY reason (reason)
        ) $charset;");
    }

    public static function guard_enabled(): bool
    {
        return (bool) get_option(self::OPTION_GUARD, false);
    }

    public static function is_blacklisted_id(string $customer_id): bool
    {
        $set = self::load_blacklist();
        $key = self::canonical_cid($customer_id);
        return $key !== '' && isset($set[$key]);
    }

    public static function is_quarantined_customer(int $customer_id): bool
    {
        if (!$customer_id) return false;
        global $wpdb;
        $table = self::table();
        if (!self::table_exists($table)) return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE customer_id=%d LIMIT 1", $customer_id));
    }

    public static function ordinary_agent_may_work(int $customer_id): bool
    {
        if (!self::guard_enabled()) return true;
        if (current_user_can('bcrm_manage_cases') || current_user_can('manage_options')) return true;
        return !self::is_quarantined_customer($customer_id);
    }

    public static function sql_not_quarantined(string $customer_alias = 'c'): string
    {
        if (!self::guard_enabled()) return '1=1';
        $q = self::table();
        return "NOT EXISTS (SELECT 1 FROM $q rq WHERE rq.customer_id={$customer_alias}.id)";
    }

    public static function sync_guard(): array
    {
        global $wpdb;
        self::ensure_schema();
        $customers = Biawin_CRM_DB::table('customers');
        $table = self::table();
        $blacklist = self::load_blacklist();
        if (!$blacklist) return ['ok'=>false,'message'=>'Blacklist رسمی پیدا نشد.'];

        $wpdb->query('START TRANSACTION');
        try {
            if ($wpdb->query("DELETE FROM $table") === false) throw new RuntimeException('quarantine reset failed');
            $found = 0;
            foreach (array_chunk(array_keys($blacklist), 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '%s'));
                $sql = $wpdb->prepare("SELECT id,source_customer_id FROM $customers WHERE source_customer_id IN ($ph)", ...$chunk);
                foreach ((array)$wpdb->get_results($sql) as $row) {
                    $ok = $wpdb->insert($table, [
                        'customer_id'=>(int)$row->id,
                        'source_customer_id'=>(string)$row->source_customer_id,
                        'reason'=>'BLACKLISTED_CUSTOMER_ID',
                        'detected_at'=>Biawin_CRM_DB::now(),
                    ]);
                    if ($ok === false) throw new RuntimeException('quarantine insert failed');
                    $found++;
                }
            }
            $wpdb->query('COMMIT');
            update_option(self::OPTION_GUARD, 1, false);
            update_option(self::OPTION_SYNCED_AT, current_time('mysql'), false);
            Biawin_CRM_DB::log('readiness_guard', null, 'v0853_blacklist_guard_synced', null, ['quarantined_customers'=>$found,'actor_user_id'=>get_current_user_id()]);
            return ['ok'=>true,'quarantined'=>$found,'message'=>'Readiness Guard فعال و لیست Quarantine با Production همگام شد.'];
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return ['ok'=>false,'message'=>'همگام‌سازی Guard ناموفق بود؛ هیچ تغییر ناقصی نگه داشته نشد.'];
        }
    }

    public static function audit(): array
    {
        global $wpdb;
        $c = Biawin_CRM_DB::table('customers');
        $a = Biawin_CRM_DB::table('assignments');
        $i = Biawin_CRM_DB::table('invoices');
        $p = Biawin_CRM_DB::table('phones');
        $ip = Biawin_CRM_DB::table('invoice_phones');
        $blacklist = self::load_blacklist();
        $present = [];
        foreach (array_chunk(array_keys($blacklist), 500) as $chunk) {
            $ph=implode(',',array_fill(0,count($chunk),'%s'));
            $sql=$wpdb->prepare("SELECT id,source_customer_id,display_name FROM $c WHERE source_customer_id IN ($ph)",...$chunk);
            foreach((array)$wpdb->get_results($sql) as $r) $present[(int)$r->id]=$r;
        }
        $ids=array_keys($present);
        $counts=['blacklist_total'=>count($blacklist),'present_blacklisted'=>count($ids),'absent_blacklisted'=>max(0,count($blacklist)-count($ids)),'active_assignments'=>0,'invoices'=>0,'phones'=>0,'invoice_phones'=>0,'activities'=>0,'assignments_all'=>0,'interactions'=>0,'complaints'=>0,'financial_cases'=>0,'deficiency_cases'=>0,'service_cases'=>0,'customer_tags'=>0];
        if($ids){
            foreach(array_chunk($ids,500) as $chunk){
                $ph=implode(',',array_fill(0,count($chunk),'%d'));
                $counts['active_assignments'] += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $a WHERE customer_id IN ($ph) AND status='assigned'",...$chunk));
                foreach(['invoices'=>'customer_id','phones'=>'customer_id','activities'=>'customer_id','assignments'=>'customer_id','interactions'=>'customer_id','complaints'=>'customer_id','financial_cases'=>'customer_id','deficiency_cases'=>'customer_id','service_cases'=>'customer_id','customer_tags'=>'customer_id'] as $logical=>$col){
                    $t=Biawin_CRM_DB::table($logical);
                    if(!self::table_exists($t)) continue;
                    $key=$logical==='assignments'?'assignments_all':$logical;
                    $counts[$key] += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE $col IN ($ph)",...$chunk));
                }
                if(self::table_exists($ip)){
                    $counts['invoice_phones'] += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ip x INNER JOIN $i ii ON ii.id=x.invoice_id WHERE ii.customer_id IN ($ph)",...$chunk));
                }
            }
        }
        $q_count = self::table_exists(self::table()) ? (int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table()) : 0;
        return ['ok'=>true,'at'=>current_time('mysql'),'counts'=>$counts,'guard_enabled'=>self::guard_enabled(),'guard_synced_at'=>(string)get_option(self::OPTION_SYNCED_AT,''),'quarantine_rows'=>$q_count];
    }

    public static function handle_audit(): void
    {
        self::require_manager('bcrm_v0853_readiness_audit');
        @set_time_limit(0);
        set_transient('bcrm_v0853_readiness_audit_'.get_current_user_id(), self::audit(), 2*HOUR_IN_SECONDS);
        self::redirect();
    }

    public static function handle_sync_guard(): void
    {
        self::require_manager('bcrm_v0853_sync_guard');
        @set_time_limit(0);
        $res=self::sync_guard();
        set_transient('bcrm_v0853_readiness_message_'.get_current_user_id(),$res,10*MINUTE_IN_SECONDS);
        set_transient('bcrm_v0853_readiness_audit_'.get_current_user_id(), self::audit(), 2*HOUR_IN_SECONDS);
        self::redirect();
    }

    public static function handle_disable_guard(): void
    {
        self::require_manager('bcrm_v0853_disable_guard');
        update_option(self::OPTION_GUARD,0,false);
        Biawin_CRM_DB::log('readiness_guard', null, 'v0853_blacklist_guard_disabled', null, ['actor_user_id'=>get_current_user_id()]);
        set_transient('bcrm_v0853_readiness_message_'.get_current_user_id(),['ok'=>true,'message'=>'Readiness Guard غیرفعال شد؛ Quarantine metadata حذف نشده است.'],10*MINUTE_IN_SECONDS);
        self::redirect();
    }

    public static function export_reconciliation_csv(): void
    {
        self::require_manager('bcrm_v0853_export_reconciliation');
        @set_time_limit(0);
        global $wpdb;
        $current=[];
        $customers=Biawin_CRM_DB::table('customers');
        foreach(array_chunk(array_keys(self::load_blacklist()),500) as $chunk){
            $ph=implode(',',array_fill(0,count($chunk),'%s'));
            foreach((array)$wpdb->get_results($wpdb->prepare("SELECT id,source_customer_id,display_name FROM $customers WHERE source_customer_id IN ($ph)",...$chunk),ARRAY_A) as $r){$current[(int)$r['id']]=$r;}
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-v0.8.53-blacklist-final-reconciliation-'.gmdate('Ymd-His').'.csv"');
        $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['current_internal_id','current_blacklisted_customer_id','current_name','manifest_class','destination_strategy','corrected_target','proposed_final_customer_id','target_internal_id','target_name','recent_source_activity','recent_target_activity','historical_ops','action']);
        $emitted=[];
        if(class_exists('Biawin_CRM_Replacement_Manifest')){
            Biawin_CRM_Replacement_Manifest::stream_manifest(function(array $m) use($out,$current,&$emitted){
                $oid=(int)($m['old_internal_id']??0);
                $tid=(int)($m['target_internal_id']??0);
                $match=null;
                if(isset($current[$oid])) $match=$current[$oid];
                elseif($tid && isset($current[$tid])) $match=$current[$tid];
                if(!$match) return;
                $key=(int)$match['id'].'|'.($m['corrected_target']??'').'|'.($m['proposed_final_customer_id']??'');
                if(isset($emitted[$key])) return; $emitted[$key]=1;
                fputcsv($out,[(int)$match['id'],$match['source_customer_id'],$match['display_name'],$m['class']??'',$m['destination_strategy']??'',$m['corrected_target']??'',$m['proposed_final_customer_id']??'',$m['target_internal_id']??'',$m['target_name']??'',$m['recent_source_activity']??0,$m['recent_target_activity']??0,$m['historical_ops']??0,$m['action']??'']);
            });
        }
        // Any current blacklisted row not represented by the historical manifest must remain manual/quarantined.
        $seen_internal=[]; foreach(array_keys($emitted) as $k){$parts=explode('|',$k);$seen_internal[(int)$parts[0]]=1;}
        foreach($current as $id=>$r){if(isset($seen_internal[$id]))continue;fputcsv($out,[$id,$r['source_customer_id'],$r['display_name'],'CURRENT_BLACKLISTED_NOT_IN_MANIFEST','MANUAL_REVIEW','','','','',0,0,0,'در Production وجود دارد اما Manifest تاریخی مقصد قطعی برای این Internal ID تولید نکرد؛ Auto Repair ممنوع و Quarantine حفظ شود.']);}
        fclose($out);exit;
    }

    public static function render_latest(): void
    {
        $r=get_transient('bcrm_v0853_readiness_audit_'.get_current_user_id());
        $msg=get_transient('bcrm_v0853_readiness_message_'.get_current_user_id());
        if($msg) delete_transient('bcrm_v0853_readiness_message_'.get_current_user_id());
        echo '<div class="bcrm-card" style="border:2px solid #2271b1"><h2>v0.8.53 — Production Readiness + Blacklist Final Reconciliation</h2>';
        echo '<p class="description"><strong>هدف:</strong> قبل از شروع کارشناسان، CustomerIDهای ممنوع از چرخه عملیاتی عادی خارج شوند، بدون حذف Customer/Invoice/Phone/History. این مرحله Reconciliation را گزارش می‌کند و Readiness Guard فقط Control Metadata می‌نویسد؛ هیچ CustomerID یا مالکیت فاکتور را خودکار تغییر نمی‌دهد.</p>';
        echo '<p><strong>Readiness Guard:</strong> '.(self::guard_enabled()?'<span style="color:#008a20">فعال</span>':'<span style="color:#b32d2e">غیرفعال</span>').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_v0853_readiness_audit');echo '<input type="hidden" name="action" value="bcrm_v0853_readiness_audit"><button class="button button-primary">Readiness Audit (فقط‌خواندنی)</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_v0853_sync_guard');echo '<input type="hidden" name="action" value="bcrm_v0853_sync_guard"><button class="button button-secondary" onclick="return confirm(\'Guard فقط دسترسی عملیاتی CustomerIDهای Blacklist را محدود می‌کند و History را حذف نمی‌کند. ادامه؟\')">فعال‌سازی/همگام‌سازی Readiness Guard</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_v0853_export_reconciliation');echo '<input type="hidden" name="action" value="bcrm_v0853_export_reconciliation"><button class="button">دانلود Final Reconciliation CSV</button></form>';
        if(self::guard_enabled()){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">';wp_nonce_field('bcrm_v0853_disable_guard');echo '<input type="hidden" name="action" value="bcrm_v0853_disable_guard"><button class="button">غیرفعال‌سازی Guard</button></form>';}
        echo '</div>';
        if(is_array($msg)) echo '<div class="notice '.(!empty($msg['ok'])?'notice-success':'notice-error').' inline"><p>'.esc_html((string)($msg['message']??'')).'</p></div>';
        if(!is_array($r)||empty($r['ok'])) return;
        $c=$r['counts'];
        echo '<div class="bcrm-card"><h2>Production Readiness Snapshot</h2><p>زمان: '.esc_html($r['at']).' — Guard rows: <strong>'.number_format_i18n((int)$r['quarantine_rows']).'</strong></p><div class="bcrm-grid">';
        $labels=['blacklist_total'=>'Blacklist رسمی','present_blacklisted'=>'CustomerID ممنوع موجود در Production','absent_blacklisted'=>'Blacklist خارج از Production','active_assignments'=>'Active Assignment روی Blacklist','invoices'=>'Invoice روی Blacklist','phones'=>'Phone روی Blacklist','invoice_phones'=>'Invoice Phone روی Blacklist','activities'=>'Activity روی Blacklist','assignments_all'=>'Assignment History روی Blacklist','interactions'=>'Interaction روی Blacklist','complaints'=>'Complaint روی Blacklist','financial_cases'=>'Financial Case روی Blacklist','deficiency_cases'=>'Deficiency Case روی Blacklist','service_cases'=>'Service Case روی Blacklist','customer_tags'=>'Customer Tag روی Blacklist'];
        foreach($labels as $k=>$l) echo '<div class="bcrm-card"><strong>'.number_format_i18n((int)($c[$k]??0)).'</strong><span>'.esc_html($l).'</span></div>';
        echo '</div><p class="description">PASS عملیاتی فردا به معنی Guard فعال + صفر دسترسی عادی کارشناسان به Quarantine است؛ صفر شدن واقعی Blacklist نیازمند Commit Engine جداگانه از روی Frozen Final Reconciliation Plan است.</p></div>';
    }

    private static function require_manager(string $nonce): void
    {
        if((!current_user_can('bcrm_reports')&&!current_user_can('bcrm_manage_cases'))||!check_admin_referer($nonce)) wp_die('دسترسی غیرمجاز است.');
    }
    private static function redirect(): void { wp_safe_redirect(add_query_arg('page','biawin-crm-historical-audit',admin_url('admin.php'))); exit; }
    private static function load_blacklist(): array
    {
        if(is_array(self::$blacklist)) return self::$blacklist;
        $set=[];$path=BIAWIN_CRM_DIR.self::BLACKLIST_FILE;
        if(is_readable($path)) foreach(file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $x){$cid=self::canonical_cid((string)$x);if($cid!=='')$set[$cid]=1;}
        return self::$blacklist=$set;
    }
    private static function canonical_cid(string $id): string
    {
        $id=strtoupper(trim($id));if($id==='')return '';
        if(preg_match('/^C(\d{1,6})$/',$id,$m))return sprintf('C%06d',(int)$m[1]);
        if(preg_match('/^(\d{1,6})$/',$id,$m))return sprintf('C%06d',(int)$m[1]);
        return $id;
    }
    private static function table_exists(string $table): bool
    {
        global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;
    }
}
