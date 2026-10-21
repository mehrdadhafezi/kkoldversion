<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.53.2 Missing Target Resolution + Frozen Canonical Plan.
 *
 * READ ONLY. Collapses the two historical roles a current blacklisted row may
 * play (historical source + corrected destination) into exactly one canonical
 * decision per current internal customer. No CRM/domain/control rows are written.
 */
class Biawin_CRM_Canonical_Reconciliation_Plan
{
    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';
    private const TRANSIENT = 'bcrm_v08532_canonical_plan_';
    private static $blacklist = null;

    public static function init(): void
    {
        add_action('admin_post_bcrm_v08532_canonical_plan', [__CLASS__, 'handle']);
        add_action('admin_post_bcrm_v08532_export_canonical_plan', [__CLASS__, 'export_csv']);
        add_action('admin_post_bcrm_v08532_export_frozen_json', [__CLASS__, 'export_frozen_json']);
    }

    public static function handle(): void
    {
        self::require_manager('bcrm_v08532_canonical_plan');
        @set_time_limit(0);
        $result = self::build(false, null);
        set_transient(self::TRANSIENT . get_current_user_id(), $result, 2 * HOUR_IN_SECONDS);
        self::redirect();
    }

    public static function export_csv(): void
    {
        self::require_manager('bcrm_v08532_export_canonical_plan');
        @set_time_limit(0);
        $r = get_transient(self::TRANSIENT . get_current_user_id());
        if (!is_array($r) || empty($r['ok']) || empty($r['invariants_pass']) || empty($r['frozen_payload']['rows'])) wp_die('Frozen PASS Plan موجود نیست. ابتدا Plan را بسازید و Invariant Result باید PASS باشد.');
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-v0.8.53.2-frozen-canonical-plan-'.gmdate('Ymd-His').'.csv"');
        header('X-BCRM-Plan-SHA256: '.(string)$r['plan_sha256']);
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge(self::headers(), ['plan_sha256']));
        foreach ((array)$r['frozen_payload']['rows'] as $row) fputcsv($out, array_merge(self::csv_row($row), [(string)$r['plan_sha256']]));
        fclose($out);
        exit;
    }

    private static function build(bool $for_export = false, ?callable $emit = null): array
    {
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $blacklist = self::load_blacklist();
        $current = [];
        $current_by_cid = [];
        foreach (array_chunk(array_keys($blacklist), 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            $sql = $wpdb->prepare("SELECT id,source_customer_id,display_name FROM $customers WHERE source_customer_id IN ($ph)", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) {
                $id = (int)$r['id'];
                $cid = self::canonical_cid((string)$r['source_customer_id']);
                $current[$id] = ['id'=>$id,'cid'=>$cid,'name'=>(string)$r['display_name']];
                $current_by_cid[$cid] = $id;
            }
        }

        $roles = [];
        foreach ($current as $id=>$r) $roles[$id] = ['source'=>[], 'target'=>[]];
        if (!class_exists('Biawin_CRM_Replacement_Manifest')) {
            return ['ok'=>false,'error'=>'Replacement Manifest در دسترس نیست.'];
        }

        Biawin_CRM_Replacement_Manifest::stream_manifest(function(array $m) use (&$roles, $current) {
            $oid = (int)($m['old_internal_id'] ?? 0);
            $tid = (int)($m['target_internal_id'] ?? 0);
            if ($oid && isset($current[$oid])) $roles[$oid]['source'][] = $m;
            if ($tid && isset($current[$tid])) $roles[$tid]['target'][] = $m;
        });

        $counts = [
            'current_blacklisted'=>count($current), 'canonical_rows'=>0, 'duplicate_source_decision'=>0,
            'manual_review'=>0, 'use_existing_valid_target'=>0, 'rekey_existing_blacklisted_target'=>0,
            'composite_migrate_source_then_rekey_current'=>0, 'create_valid_target'=>0, 'create_missing_valid_target'=>0, 'unresolved'=>0,
            'blacklisted_final_id'=>0, 'invalid_existing_target'=>0, 'unauthorized_final_id_collision'=>0,
            'source_role_multiple'=>0, 'target_role_multiple'=>0,
        ];
        $rows = [];
        $final_claims = [];

        foreach ($current as $id=>$cur) {
            $source = self::pick_source_role($roles[$id]['source'], $id);
            $target = self::pick_target_role($roles[$id]['target'], $id, $cur['cid']);
            if (count($roles[$id]['source']) > 1) $counts['source_role_multiple']++;
            if (count($roles[$id]['target']) > 1) $counts['target_role_multiple']++;

            $decision = self::decide($cur, $source, $target);
            // v0.8.53.2: a corrected, non-blacklisted destination can be authoritative
            // even when its internal customer row is currently missing. Model that explicitly
            // instead of falsely requiring an existing target row.
            $corrected = self::canonical_cid((string)($decision['source_corrected_target'] ?? ''));
            $target_id = (int)($decision['source_target_internal_id'] ?? 0);
            if ($source && !$target_id && $corrected !== '' && !isset($blacklist[$corrected]) && !isset($current_by_cid[$corrected])
                && in_array((string)($source['destination_strategy'] ?? ''), ['USE_EXISTING_VALID_TARGET','CREATE_VALID_TARGET'], true)) {
                $decision['source_target_mode'] = 'CREATE_MISSING_VALID_TARGET';
                $decision['canonical_decision'] = $target
                    ? 'COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT'
                    : 'CREATE_MISSING_VALID_TARGET';
                if (!$target) $decision['final_customer_id'] = $corrected;
                $decision['action'] = $target
                    ? 'ابتدا Customer Row گمشده برای مقصد معتبر Source ایجاد و History به آن منتقل/Verify شود؛ سپس ردیف Blacklisted فعلی برای شخص ورودی Re-key شود.'
                    : 'Customer Row گمشده برای CustomerID معتبر و ازپیش‌تعیین‌شده ایجاد شود؛ سپس Person/History منتقل و Source Blacklisted retire شود.';
            }
            $row = array_merge([
                'current_internal_id'=>$id,
                'current_blacklisted_customer_id'=>$cur['cid'],
                'current_name'=>$cur['name'],
                'source_role_count'=>count($roles[$id]['source']),
                'target_role_count'=>count($roles[$id]['target']),
            ], $decision);

            $final = self::canonical_cid((string)$row['final_customer_id']);
            if ($final !== '' && isset($blacklist[$final])) { $row['invariant_blacklisted_final_id']='FAIL'; $counts['blacklisted_final_id']++; }
            else $row['invariant_blacklisted_final_id']='PASS';

            if (($row['source_target_mode'] ?? '') === 'CREATE_MISSING_VALID_TARGET') {
                $row['invariant_existing_target']='CREATE_MISSING';
            } elseif (in_array($row['canonical_decision'], ['USE_EXISTING_VALID_TARGET','COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT'], true)) {
                $tid=(int)$row['source_target_internal_id'];
                if (!$tid || isset($current[$tid])) { $row['invariant_existing_target']='FAIL'; $counts['invalid_existing_target']++; }
                else $row['invariant_existing_target']='PASS';
            } else $row['invariant_existing_target']='N/A';

            if ($final !== '') $final_claims[$final][] = ['source'=>$id,'decision'=>$row['canonical_decision'],'target'=>(int)$row['source_target_internal_id']];
            $counts['canonical_rows']++;
            if (($row['source_target_mode'] ?? '') === 'CREATE_MISSING_VALID_TARGET') $counts['create_missing_valid_target']++;
            $key = strtolower($row['canonical_decision']);
            if (isset($counts[$key])) $counts[$key]++;
            else $counts['unresolved']++;
            $rows[]=$row;
        }

        // A shared existing valid target is an intentional convergence, not a collision.
        // Collision means the same final ID is claimed by incompatible create/rekey destinations.
        foreach ($final_claims as $cid=>$claims) {
            if (count($claims) < 2) continue;
            $targets=[]; $unsafe=false;
            foreach ($claims as $cl) {
                if (in_array($cl['decision'], ['CREATE_VALID_TARGET','REKEY_EXISTING_BLACKLISTED_TARGET','COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT'], true)) $unsafe=true;
                if ($cl['target']) $targets[$cl['target']]=1;
            }
            if ($unsafe || count($targets)>1) $counts['unauthorized_final_id_collision']++;
        }

        $counts['duplicate_source_decision'] = max(0, $counts['canonical_rows'] - count($current));
        $pass = $counts['canonical_rows'] === count($current)
            && $counts['duplicate_source_decision'] === 0
            && $counts['blacklisted_final_id'] === 0
            && $counts['invalid_existing_target'] === 0
            && $counts['unauthorized_final_id_collision'] === 0
            && $counts['unresolved'] === 0;

        $canonical_payload = ['version'=>'0.8.53.2','generated_at'=>current_time('mysql'),'counts'=>$counts,'rows'=>$rows];
        $canonical_json = wp_json_encode($canonical_payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $plan_sha256 = hash('sha256', (string)$canonical_json);
        if ($for_export && $emit) foreach ($rows as $r) $emit($r);
        return [
            'ok'=>true,'at'=>current_time('mysql'),'counts'=>$counts,
            'invariants_pass'=>$pass,'plan_sha256'=>$plan_sha256,'frozen_payload'=>$pass?$canonical_payload:null,
            'note'=>'v0.8.53.2 فقط Plan می‌سازد و هیچ Domain Commit ندارد. مقصد معتبرِ فاقد Internal Row با CREATE_MISSING_VALID_TARGET مدل می‌شود. Manual Review در Guard می‌ماند. PASS فقط اجازه Freeze/Hash Plan را می‌دهد، نه Migration.',
            'samples'=>array_slice($rows,0,100),
        ];
    }

    private static function pick_source_role(array $rows, int $current_id): ?array
    {
        if (!$rows) return null;
        // Exact source ownership is authoritative. Prefer manual evidence, then a non-self corrected target.
        usort($rows, function($a,$b) use($current_id){
            $sa = (($a['destination_strategy']??'')==='MANUAL_REVIEW'?100:0) + (((int)($a['target_internal_id']??0)!==$current_id)?20:0) + (($a['corrected_target']??'')!==($a['old_customer_id']??'')?10:0);
            $sb = (($b['destination_strategy']??'')==='MANUAL_REVIEW'?100:0) + (((int)($b['target_internal_id']??0)!==$current_id)?20:0) + (($b['corrected_target']??'')!==($b['old_customer_id']??'')?10:0);
            return $sb <=> $sa;
        });
        return $rows[0];
    }

    private static function pick_target_role(array $rows, int $current_id, string $current_cid): ?array
    {
        if (!$rows) return null;
        $candidates=[];
        foreach($rows as $m){
            if((int)($m['target_internal_id']??0)!==$current_id) continue;
            if(self::canonical_cid((string)($m['corrected_target']??''))!==$current_cid) continue;
            if((int)($m['old_internal_id']??0)===$current_id) continue; // same profile, not an incoming role
            $candidates[]=$m;
        }
        if(!$candidates) return null;
        usort($candidates,function($a,$b){
            $aa=(int)($a['historical_ops']??0)+(int)($a['recent_source_activity']??0);
            $bb=(int)($b['historical_ops']??0)+(int)($b['recent_source_activity']??0);
            return $bb<=>$aa;
        });
        return $candidates[0];
    }

    private static function decide(array $cur, ?array $source, ?array $target): array
    {
        $base=[
            'canonical_decision'=>'UNRESOLVED','manifest_class'=>'','final_customer_id'=>'','source_person_final_customer_id'=>'',
            'source_corrected_target'=>'','source_target_internal_id'=>'','source_target_name'=>'','source_target_mode'=>'',
            'incoming_old_internal_id'=>'','incoming_old_customer_id'=>'','incoming_old_name'=>'','incoming_replacement_id'=>'',
            'recent_source_activity'=>0,'recent_target_activity'=>0,'historical_ops'=>0,'action'=>'',
        ];
        if ($source) {
            $base['manifest_class']=(string)($source['class']??'');
            $base['source_corrected_target']=self::canonical_cid((string)($source['corrected_target']??''));
            $base['source_person_final_customer_id']=self::canonical_cid((string)($source['proposed_final_customer_id']??$source['corrected_target']??''));
            $base['source_target_internal_id']=(int)($source['target_internal_id']??0) ?: '';
            $base['source_target_name']=(string)($source['target_name']??'');
            $base['source_target_mode']=((int)($source['target_internal_id']??0)>0)?'EXISTING_TARGET':'';
            $base['recent_source_activity']=(int)($source['recent_source_activity']??0);
            $base['recent_target_activity']=(int)($source['recent_target_activity']??0);
            $base['historical_ops']=(int)($source['historical_ops']??0);
        }
        if ($target) {
            $base['incoming_old_internal_id']=(int)($target['old_internal_id']??0) ?: '';
            $base['incoming_old_customer_id']=self::canonical_cid((string)($target['old_customer_id']??''));
            $base['incoming_old_name']=(string)($target['old_name']??'');
            $base['incoming_replacement_id']=self::canonical_cid((string)($target['proposed_final_customer_id']??''));
        }

        if ($source && (($source['destination_strategy']??'')==='MANUAL_REVIEW' || ($source['class']??'')==='AMBIGUOUS_MANUAL')) {
            $base['canonical_decision']='MANUAL_REVIEW';
            $base['action']='شواهد انسانی/ابهام Source قطعی نیست؛ Guard حفظ شود و هیچ Auto Commit انجام نشود.';
            return $base;
        }

        $ss=(string)($source['destination_strategy']??'');
        if ($source && $target && in_array($ss, ['USE_EXISTING_VALID_TARGET','CREATE_VALID_TARGET'], true)) {
            $base['canonical_decision']='COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT';
            $base['final_customer_id']=$base['incoming_replacement_id'];
            $base['action']='دو نقش در یک Internal Row: ابتدا شخص Source به مقصد نهایی خودش (existing یا create طبق source strategy) منتقل/Verify شود؛ سپس همین row Blacklisted برای شخص ورودی با Replacement معتبر Re-key شود. ترتیب عملیات اجباری است.';
            return $base;
        }
        if ($source && $ss==='USE_EXISTING_VALID_TARGET') {
            $base['canonical_decision']='USE_EXISTING_VALID_TARGET';
            $base['final_customer_id']=self::canonical_cid((string)($source['proposed_final_customer_id']??$source['corrected_target']??''));
            $base['action']='Source به Customer موجود معتبر همگرا شود؛ History انسانی Preserve/Migrate و سپس Source Blacklisted retire شود.';
            return $base;
        }
        if ($source && $ss==='CREATE_VALID_TARGET') {
            $base['canonical_decision']='CREATE_VALID_TARGET';
            $base['final_customer_id']=self::canonical_cid((string)($source['proposed_final_customer_id']??''));
            $base['action']='مقصد معتبر جدید فقط با Frozen allocation ساخته شود؛ سپس History منتقل و Source Blacklisted retire شود.';
            return $base;
        }
        if ($source && $ss==='REKEY_EXISTING_BLACKLISTED_TARGET') {
            $base['canonical_decision']='REKEY_EXISTING_BLACKLISTED_TARGET';
            $base['final_customer_id']=self::canonical_cid((string)($source['proposed_final_customer_id']??''));
            $base['action']='همین Internal Row پس از preflight کامل با Replacement معتبر Re-key شود؛ History حذف نمی‌شود.';
            return $base;
        }
        if (!$source && $target) {
            $base['canonical_decision']='REKEY_EXISTING_BLACKLISTED_TARGET';
            $base['final_customer_id']=$base['incoming_replacement_id'];
            $base['action']='ردیف فعلی فقط نقش مقصد Blacklisted را دارد؛ با Replacement معتبر Re-key شود.';
            return $base;
        }
        $base['canonical_decision']='MANUAL_REVIEW';
        $base['action']='برای Internal Row فعلی نقش تاریخی قطعی پیدا نشد؛ Guard حفظ شود.';
        return $base;
    }

    public static function export_frozen_json(): void
    {
        self::require_manager('bcrm_v08532_export_frozen_json');
        $r = get_transient(self::TRANSIENT . get_current_user_id());
        if (!is_array($r) || empty($r['ok']) || empty($r['invariants_pass']) || empty($r['frozen_payload'])) wp_die('Plan PASS شده برای Freeze موجود نیست. ابتدا Plan را بسازید.');
        $payload = $r['frozen_payload'];
        $payload['sha256'] = (string)$r['plan_sha256'];
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-v0.8.53.2-frozen-canonical-plan-'.gmdate('Ymd-His').'.json"');
        echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        exit;
    }

    public static function render_latest(): void
    {
        $r=get_transient(self::TRANSIENT.get_current_user_id());
        echo '<div class="bcrm-card" style="border:2px solid #8c8f94"><h2>v0.8.53.2 — Missing Target Resolution + Frozen Canonical Plan</h2>';
        echo '<p class="description"><strong>فقط‌خواندنی.</strong> مقصدهای معتبرِ فاقد Internal Row را صریحاً مدل می‌کند و فقط در صورت PASS یک Plan قابل Freeze/Hash می‌دهد. هیچ CustomerID/Invoice/Phone/History/Guard تغییر نمی‌کند و این نسخه Commit ندارد.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">';wp_nonce_field('bcrm_v08532_canonical_plan');echo '<input type="hidden" name="action" value="bcrm_v08532_canonical_plan"><button class="button button-primary">ساخت Plan + Invariants (Read-only)</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">';wp_nonce_field('bcrm_v08532_export_canonical_plan');echo '<input type="hidden" name="action" value="bcrm_v08532_export_canonical_plan"><button class="button">دانلود Frozen Canonical CSV</button></form>';
        if(is_array($r)&&!empty($r['ok'])){
            $c=$r['counts'];
            echo '<p><strong>Invariant Result:</strong> '.(!empty($r['invariants_pass'])?'<span style="color:#008a20">PASS</span>':'<span style="color:#b32d2e">BLOCKED</span>').' — زمان: '.esc_html($r['at']).'</p>';
            echo '<div class="bcrm-grid">';
            $labels=['current_blacklisted'=>'Blacklisted Source','canonical_rows'=>'Canonical Decisions','use_existing_valid_target'=>'Use Existing Target','composite_migrate_source_then_rekey_current'=>'Composite','rekey_existing_blacklisted_target'=>'Re-key','create_valid_target'=>'Create Target','create_missing_valid_target'=>'Create Missing Target','manual_review'=>'Manual Review','blacklisted_final_id'=>'Blacklisted Final ID','invalid_existing_target'=>'Invalid Existing Target','unauthorized_final_id_collision'=>'Unauthorized Final-ID Collision','duplicate_source_decision'=>'Duplicate Source Decision','unresolved'=>'Unresolved'];
            foreach($labels as $k=>$l) echo '<div class="bcrm-card"><strong>'.number_format_i18n((int)($c[$k]??0)).'</strong><span>'.esc_html($l).'</span></div>';
            echo '</div>';
            if (!empty($r['plan_sha256'])) echo '<p><strong>Plan SHA256:</strong> <code>'.esc_html($r['plan_sha256']).'</code></p>';
            if (!empty($r['invariants_pass'])) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-top:8px">';wp_nonce_field('bcrm_v08532_export_frozen_json');echo '<input type="hidden" name="action" value="bcrm_v08532_export_frozen_json"><button class="button button-secondary">دانلود Frozen Plan JSON</button></form>';
            }
            echo '<p class="description">'.esc_html($r['note']).'</p>';
        }
        echo '</div>';
    }

    private static function headers(): array
    {
        return ['current_internal_id','current_blacklisted_customer_id','current_name','source_role_count','target_role_count','canonical_decision','manifest_class','final_customer_id','source_person_final_customer_id','source_corrected_target','source_target_internal_id','source_target_name','source_target_mode','incoming_old_internal_id','incoming_old_customer_id','incoming_old_name','incoming_replacement_id','recent_source_activity','recent_target_activity','historical_ops','invariant_blacklisted_final_id','invariant_existing_target','action'];
    }
    private static function csv_row(array $r): array { $out=[]; foreach(self::headers() as $h)$out[]=$r[$h]??''; return $out; }
    private static function require_manager(string $nonce): void { if((!current_user_can('bcrm_reports')&&!current_user_can('bcrm_manage_cases'))||!check_admin_referer($nonce)) wp_die('دسترسی غیرمجاز است.'); }
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
}
