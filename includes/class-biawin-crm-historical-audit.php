<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only historical identity audit for the 2026-08-18 -> corrected CustomerID migration.
 *
 * The bundled baseline was generated from:
 * - bcrm-backup-20260818-142835-manual.ndjson
 * - خروجی_نهایی_مشتریان_اصلاح_شده.xlsx
 *
 * This class NEVER mutates CRM domain tables. It only reads them and stores a short-lived
 * transient containing the last audit summary for the current admin user.
 */
class Biawin_CRM_Historical_Audit
{
    private const BASELINE_FILE = 'data/historical-identity-baseline.ndjson';
    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';
    private const BATCH_SIZE = 250;
    private const SAMPLE_LIMIT = 150;

    public static function page(): void
    {
        if (!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) {
            wp_die('دسترسی غیرمجاز است.');
        }

        $last = get_transient('bcrm_historical_identity_audit_' . get_current_user_id());
        echo '<div class="wrap bcrm" dir="rtl"><h1>ممیزی تاریخی هویت مشتریان</h1>';
        echo '<div class="notice notice-warning inline"><p><strong>نسخه 0.8.53.2 شامل Production Readiness Guard و Missing Target Resolution + Frozen Canonical Plan است.</strong> ممیزی‌ها و Final Reconciliation این نسخه فقط‌خواندنی‌اند؛ Guard فقط Control Metadata می‌نویسد و هیچ CustomerID/Invoice/Phone/History را بازنویسی نمی‌کند. Blacklist رسمی CustomerID همچنان Hard Constraint است.</p></div>';
        echo '<div class="bcrm-card"><h2>هدف ممیزی</h2>';
        echo '<p class="description">Baseline داخل افزونه از بک‌آپ سالم 2026-08-18 و فایل اصلاحی CustomerID ساخته شده است. همچنین 48,331 CustomerID ممنوعه از فایل رسمی حذف داخل افزونه قرار گرفته‌اند و هیچ‌کدام مقصد معتبر محسوب نمی‌شوند. ممیزی بررسی می‌کند سوابق عملیاتیِ شخص قدیمی بعد از تغییر CustomerID روی کدام رکورد مانده و چه تعداد پروفایل Complete تاریخی اکنون ناقص یا روی هویت دیگری قرار گرفته‌اند.</p>';
        echo '<p><strong>قفل ایمنی:</strong> در این نسخه اجرای واقعی «اصلاح مالکیت» و «پاکسازی فاکتورهای بدون تایید» به‌صورت پیش‌فرض مسدود است تا هنگام کار کارشناسان عملیات گروهی ناخواسته اجرا نشود.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcrm_run_historical_identity_audit');
        echo '<input type="hidden" name="action" value="bcrm_run_historical_identity_audit">';
        echo '<button class="button button-primary button-hero">اجرای ممیزی فقط-خواندنی</button></form></div>';

        if (is_array($last)) {
            self::render($last);
        }
        if (class_exists('Biawin_CRM_Forward_Reconstruction_Planner')) {
            Biawin_CRM_Forward_Reconstruction_Planner::render_latest();
        }
        if (class_exists('Biawin_CRM_Replacement_Manifest')) {
            Biawin_CRM_Replacement_Manifest::render_latest();
        }
        if (class_exists('Biawin_CRM_Commit_Safety_Preflight')) {
            Biawin_CRM_Commit_Safety_Preflight::render_latest();
        }
        if (class_exists('Biawin_CRM_Expert_Intent_Merge_Audit')) {
            Biawin_CRM_Expert_Intent_Merge_Audit::render_latest();
        }
        if (class_exists('Biawin_CRM_Final_Safe_Set')) {
            Biawin_CRM_Final_Safe_Set::render_latest();
        }
        if (class_exists('Biawin_CRM_Production_Readiness')) {
            Biawin_CRM_Production_Readiness::render_latest();
        }
        if (class_exists('Biawin_CRM_Canonical_Reconciliation_Plan')) {
            Biawin_CRM_Canonical_Reconciliation_Plan::render_latest();
        }
        if (class_exists('Biawin_CRM_Blacklist_Final_Commit_Engine')) {
            Biawin_CRM_Blacklist_Final_Commit_Engine::render_latest();
        }
        if (class_exists('Biawin_CRM_Post_Run7_Reconciliation')) {
            Biawin_CRM_Post_Run7_Reconciliation::render_latest();
        }
        if (class_exists('Biawin_CRM_Controlled_Reconstruction')) {
            Biawin_CRM_Controlled_Reconstruction::render_latest();
        }
        if (class_exists('Biawin_CRM_Controlled_Job_1000_Planner')) {
            Biawin_CRM_Controlled_Job_1000_Planner::render_latest();
        }
        echo '</div>';
    }

    public static function handle(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_run_historical_identity_audit')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        @set_time_limit(0);
        $result = self::run();
        set_transient('bcrm_historical_identity_audit_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-historical-audit', admin_url('admin.php')));
        exit;
    }

    public static function maintenance_lock_enabled(): bool
    {
        if (defined('BIAWIN_CRM_MAINTENANCE_SAFETY_LOCK')) {
            return (bool) BIAWIN_CRM_MAINTENANCE_SAFETY_LOCK;
        }
        return true;
    }

    public static function maintenance_lock_message(): string
    {
        return 'قفل ایمنی v0.8.45 فعال است. اجرای عملیات گروهی تغییردهنده داده مسدود شده است. برای تست کنترل‌شده روی Clone/Local می‌توانید در wp-config.php مقدار BIAWIN_CRM_MAINTENANCE_SAFETY_LOCK را false تعریف کنید.';
    }

    private static function run(): array
    {
        global $wpdb;
        $path = BIAWIN_CRM_DIR . self::BASELINE_FILE;
        $result = [
            'ok' => false,
            'error' => '',
            'meta' => [],
            'counts' => [
                'baseline_profiles' => 0,
                'deterministic' => 0,
                'ambiguous' => 0,
                'no_mapping' => 0,
                'historical_complete' => 0,
                'historical_complete_deterministic' => 0,
                'target_found' => 0,
                'target_missing' => 0,
                'target_identity_name_mismatch' => 0,
                'historical_complete_target_not_complete' => 0,
                'old_internal_row_identity_changed' => 0,
                'confirmed_stranded_declared_paid' => 0,
                'confirmed_stranded_customer_code' => 0,
                'profiles_with_historical_operations' => 0,
                'historical_operations_total' => 0,
                'safe_reconstruction_candidates' => 0,
                'manual_review_candidates' => 0,
                'corrected_targets_blacklisted' => 0,
            ],
            'samples' => [],
            'started_at' => current_time('mysql'),
            'finished_at' => null,
            'blacklist' => [],
        ];

        if (!is_readable($path)) {
            $result['error'] = 'فایل baseline تاریخی داخل افزونه پیدا نشد.';
            return $result;
        }

        $fh = fopen($path, 'rb');
        if (!$fh) {
            $result['error'] = 'Baseline تاریخی قابل خواندن نیست.';
            return $result;
        }

        $first = fgets($fh);
        if ($first === false) {
            fclose($fh);
            $result['error'] = 'Baseline تاریخی خالی است.';
            return $result;
        }
        $meta = json_decode(trim($first), true);
        if (!is_array($meta) || ($meta['type'] ?? '') !== 'meta') {
            fclose($fh);
            $result['error'] = 'ساختار baseline تاریخی معتبر نیست.';
            return $result;
        }
        $result['meta'] = $meta;
        $blacklist = self::load_blacklist();
        if (!$blacklist) { fclose($fh); $result['error'] = 'فایل Blacklist داخل افزونه پیدا نشد یا خالی است.'; return $result; }
        $result['blacklist'] = self::audit_blacklist_current_state($blacklist);

        $batch = [];
        while (($line = fgets($fh)) !== false) {
            $row = json_decode(trim($line), true);
            if (!is_array($row) || ($row['type'] ?? '') !== 'profile') continue;
            $batch[] = $row['profile'] ?? [];
            if (count($batch) >= self::BATCH_SIZE) {
                self::audit_batch($batch, $result);
                $batch = [];
            }
        }
        if ($batch) self::audit_batch($batch, $result);
        fclose($fh);

        $result['ok'] = true;
        $result['finished_at'] = current_time('mysql');
        return $result;
    }

    private static function audit_batch(array $profiles, array &$result): void
    {
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');

        $internal_ids = [];
        $target_source_ids = [];
        foreach ($profiles as $p) {
            $id = absint($p['old_internal_id'] ?? 0);
            if ($id) $internal_ids[$id] = $id;
            $target = (string)($p['target_customer_id'] ?? '');
            if ($target !== '') $target_source_ids[$target] = $target;
        }

        $by_internal = [];
        if ($internal_ids) {
            $ph = implode(',', array_fill(0, count($internal_ids), '%d'));
            $sql = $wpdb->prepare("SELECT id,source_customer_id,customer_code,display_name,profile_completeness,status,total_paid,declared_paid,discrepancy_amount,journey_state,satisfaction_state,satisfaction_avg FROM $customers WHERE id IN ($ph)", ...array_values($internal_ids));
            foreach ((array)$wpdb->get_results($sql) as $row) $by_internal[(int)$row->id] = $row;
        }

        $by_source = [];
        if ($target_source_ids) {
            $ph = implode(',', array_fill(0, count($target_source_ids), '%s'));
            $sql = $wpdb->prepare("SELECT id,source_customer_id,customer_code,display_name,profile_completeness,status,total_paid,declared_paid,discrepancy_amount,journey_state,satisfaction_state,satisfaction_avg FROM $customers WHERE source_customer_id IN ($ph)", ...array_values($target_source_ids));
            foreach ((array)$wpdb->get_results($sql) as $row) $by_source[(string)$row->source_customer_id] = $row;
        }

        foreach ($profiles as $p) {
            $result['counts']['baseline_profiles']++;
            $mapping = (string)($p['mapping_status'] ?? 'none');
            if ($mapping === 'deterministic') $result['counts']['deterministic']++;
            elseif ($mapping === 'ambiguous') $result['counts']['ambiguous']++;
            else $result['counts']['no_mapping']++;

            $historical_complete = (($p['old_profile_completeness'] ?? '') === 'complete');
            if ($historical_complete) $result['counts']['historical_complete']++;
            if ($historical_complete && $mapping === 'deterministic') $result['counts']['historical_complete_deterministic']++;

            $history_counts = is_array($p['history_counts'] ?? null) ? $p['history_counts'] : [];
            $history_total = array_sum(array_map('intval', $history_counts));
            if ($history_total > 0) {
                $result['counts']['profiles_with_historical_operations']++;
                $result['counts']['historical_operations_total'] += $history_total;
            }

            if ($mapping !== 'deterministic') {
                if ($mapping === 'ambiguous') {
                    $result['counts']['manual_review_candidates']++;
                    self::sample($result, $p, null, null, 'AMBIGUOUS', 'یک پروفایل قدیمی در فایل اصلاحی به چند CustomerID مقصد شکسته شده و Repair خودکار نباید روی آن اجرا شود.');
                }
                continue;
            }

            $old_internal_id = absint($p['old_internal_id'] ?? 0);
            $old_source = (string)($p['old_source_customer_id'] ?? '');
            $target_source = (string)($p['target_customer_id'] ?? '');
            $old_row_now = $by_internal[$old_internal_id] ?? null;
            if ($target_source !== '' && self::is_blacklisted($target_source)) {
                $result['counts']['corrected_targets_blacklisted']++;
                $result['counts']['manual_review_candidates']++;
                self::sample($result, $p, $old_row_now, null, 'BLACKLISTED_TARGET', 'CustomerID مقصد فایل اصلاحی در Blacklist رسمی است و نمی‌تواند مقصد Repair باشد؛ این شخص نیازمند CustomerID معتبر جایگزین است.');
                continue;
            }
            $target_row_now = $by_source[$target_source] ?? null;

            if (!$target_row_now) {
                $result['counts']['target_missing']++;
                $result['counts']['manual_review_candidates']++;
                self::sample($result, $p, $old_row_now, null, 'TARGET_MISSING', 'CustomerID مقصد در دیتابیس فعلی وجود ندارد.');
                continue;
            }
            $result['counts']['target_found']++;

            $expected_name = self::normalize_name((string)($p['expected_name'] ?? ''));
            $current_name = self::normalize_name((string)($target_row_now->display_name ?? ''));
            if ($expected_name !== '' && $current_name !== '' && $expected_name !== $current_name) {
                $result['counts']['target_identity_name_mismatch']++;
                self::sample($result, $p, $old_row_now, $target_row_now, 'NAME_MISMATCH', 'نام فعلی CustomerID مقصد با نام غالب فایل اصلاحی یکسان نیست.');
            }

            if ($historical_complete && (string)$target_row_now->profile_completeness !== 'complete') {
                $result['counts']['historical_complete_target_not_complete']++;
                self::sample($result, $p, $old_row_now, $target_row_now, 'COMPLETE_LOST', 'این شخص در بک‌آپ سالم Complete بوده ولی پروفایل مقصد فعلی Complete نیست.');
            }

            $old_name = self::normalize_name((string)($p['old_name'] ?? ''));
            $current_old_name = $old_row_now ? self::normalize_name((string)($old_row_now->display_name ?? '')) : '';
            $old_row_identity_changed = $old_row_now && (int)$target_row_now->id !== $old_internal_id && (
                (string)$old_row_now->source_customer_id !== $old_source ||
                ($old_name !== '' && $current_old_name !== '' && $current_old_name !== $old_name)
            );
            if ($old_row_identity_changed) {
                $result['counts']['old_internal_row_identity_changed']++;
            }

            $old_declared = $p['old_declared_paid'] ?? null;
            if ($old_row_now && (int)$target_row_now->id !== $old_internal_id && $old_declared !== null && $old_declared !== '' && self::money_equal($old_row_now->declared_paid ?? null, $old_declared)) {
                if (!self::money_equal($target_row_now->declared_paid ?? null, $old_declared)) {
                    $result['counts']['confirmed_stranded_declared_paid']++;
                    self::sample($result, $p, $old_row_now, $target_row_now, 'DECLARED_STRANDED', 'مبلغ اعلامی تاریخی هنوز روی internal customer قدیمی مانده و به CustomerID مقصد منتقل نشده است.');
                }
            }

            $old_customer_code = trim((string)($p['old_customer_code'] ?? ''));
            if ($old_row_now && (int)$target_row_now->id !== $old_internal_id && $old_customer_code !== '' && trim((string)($old_row_now->customer_code ?? '')) === $old_customer_code && trim((string)($target_row_now->customer_code ?? '')) !== $old_customer_code) {
                $result['counts']['confirmed_stranded_customer_code']++;
            }

            // Deterministic identity + existing target is eligible for a future forward-reconstruction plan.
            // This version intentionally does not perform that reconstruction.
            if ($target_row_now && $mapping === 'deterministic') {
                $result['counts']['safe_reconstruction_candidates']++;
            }
        }
    }

    private static function sample(array &$result, array $p, $old_row_now, $target_row_now, string $reason, string $note): void
    {
        if (count($result['samples']) >= self::SAMPLE_LIMIT) return;
        $result['samples'][] = [
            'reason' => $reason,
            'old_internal_id' => (int)($p['old_internal_id'] ?? 0),
            'old_customer_id' => (string)($p['old_source_customer_id'] ?? ''),
            'old_name' => (string)($p['old_name'] ?? ''),
            'target_customer_id' => (string)($p['target_customer_id'] ?? ''),
            'expected_name' => (string)($p['expected_name'] ?? ''),
            'current_old_row_customer_id' => $old_row_now ? (string)$old_row_now->source_customer_id : '-',
            'current_target_name' => $target_row_now ? (string)$target_row_now->display_name : '-',
            'old_completeness' => (string)($p['old_profile_completeness'] ?? ''),
            'current_target_completeness' => $target_row_now ? (string)$target_row_now->profile_completeness : '-',
            'old_declared_paid' => $p['old_declared_paid'] ?? null,
            'current_old_row_declared_paid' => $old_row_now ? $old_row_now->declared_paid : null,
            'current_target_declared_paid' => $target_row_now ? $target_row_now->declared_paid : null,
            'history_counts' => $p['history_counts'] ?? [],
            'note' => $note,
        ];
    }


    private static $blacklist_cache = null;

    private static function load_blacklist(): array
    {
        if (is_array(self::$blacklist_cache)) return self::$blacklist_cache;
        $path = BIAWIN_CRM_DIR . self::BLACKLIST_FILE;
        if (!is_readable($path)) return self::$blacklist_cache = [];
        $set = [];
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $id) {
            $id = strtoupper(trim((string)$id));
            if (preg_match('/^C\d{6}$/', $id)) $set[$id] = true;
        }
        return self::$blacklist_cache = $set;
    }

    private static function is_blacklisted(string $source_id): bool
    {
        $set = self::load_blacklist();
        return isset($set[strtoupper(trim($source_id))]);
    }

    private static function audit_blacklist_current_state(array $blacklist): array
    {
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $ids = array_keys($blacklist);
        $found = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            $sql = $wpdb->prepare("SELECT id,source_customer_id,display_name,profile_completeness,status,total_paid,declared_paid FROM $customers WHERE source_customer_id IN ($ph)", ...$chunk);
            foreach ((array)$wpdb->get_results($sql) as $row) $found[(int)$row->id] = $row;
        }
        $internal_ids = array_keys($found);
        $relations = [];
        $relation_tables = ['invoices','phones','interactions','assignments','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','attachments','case_actions','activities','payments','refunds'];
        foreach ($relation_tables as $name) {
            $table = Biawin_CRM_DB::table($name);
            $col = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME='customer_id' LIMIT 1", $table));
            if (!$col || !$internal_ids) { $relations[$name] = 0; continue; }
            $count = 0;
            foreach (array_chunk($internal_ids, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '%d'));
                $count += (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE customer_id IN ($ph)", ...$chunk));
            }
            $relations[$name] = $count;
        }
        $samples = [];
        foreach ($found as $row) {
            if (count($samples) >= 100) break;
            $samples[] = [
                'internal_id'=>(int)$row->id,
                'customer_id'=>(string)$row->source_customer_id,
                'name'=>(string)$row->display_name,
                'completeness'=>(string)$row->profile_completeness,
                'status'=>(string)$row->status,
                'total_paid'=>$row->total_paid,
                'declared_paid'=>$row->declared_paid,
                'replacement'=>'REQUIRED_NEW_VALID_ID',
            ];
        }
        return [
            'blacklist_total'=>count($ids),
            'present_customers'=>count($found),
            'absent_customers'=>count($ids)-count($found),
            'relation_counts'=>$relations,
            'relation_total'=>array_sum($relations),
            'samples'=>$samples,
            'hard_constraint'=>'Every listed CustomerID is permanently invalid and must never be assigned to any customer.',
        ];
    }

    private static function normalize_name(string $name): string
    {
        $name = trim(str_replace(['ي','ك'], ['ی','ک'], $name));
        return preg_replace('/\s+/u', ' ', $name) ?: '';
    }

    private static function money_equal($a, $b): bool
    {
        if ($a === null || $a === '' || $b === null || $b === '') return false;
        return abs((float)$a - (float)$b) < 0.01;
    }

    private static function render(array $result): void
    {
        if (empty($result['ok'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html((string)($result['error'] ?? 'ممیزی اجرا نشد.')) . '</p></div>';
            return;
        }
        echo '<div class="bcrm-card"><h2>نتیجه آخرین ممیزی</h2>';
        echo '<p class="description">شروع: ' . esc_html((string)$result['started_at']) . ' — پایان: ' . esc_html((string)$result['finished_at']) . '</p>';
        echo '<div class="bcrm-grid">';
        $labels = [
            'baseline_profiles'=>'پروفایل تاریخی بررسی‌شده',
            'deterministic'=>'نگاشت قطعی',
            'ambiguous'=>'نیازمند بررسی دستی',
            'historical_complete'=>'Complete در بک‌آپ سالم',
            'target_found'=>'مقصد موجود',
            'target_missing'=>'مقصد مفقود',
            'historical_complete_target_not_complete'=>'Complete تاریخی که مقصد فعلی Complete نیست',
            'old_internal_row_identity_changed'=>'internal ID قدیمی اکنون هویت دیگری دارد',
            'confirmed_stranded_declared_paid'=>'مبلغ اعلامی قطعاً روی هویت قدیمی جا مانده',
            'profiles_with_historical_operations'=>'دارای سابقه عملیاتی تاریخی',
            'historical_operations_total'=>'تعداد رکوردهای عملیاتی تاریخی',
            'safe_reconstruction_candidates'=>'کاندید Forward Reconstruction',
            'corrected_targets_blacklisted'=>'مقصد فایل اصلاحی که به‌علت Blacklist نامعتبر است',
        ];
        foreach ($labels as $key=>$label) {
            echo '<div class="bcrm-card"><strong>' . esc_html(number_format_i18n((int)($result['counts'][$key] ?? 0))) . '</strong><span>' . esc_html($label) . '</span></div>';
        }
        echo '</div></div>';

        if (!empty($result['blacklist'])) {
            $b = $result['blacklist'];
            echo '<div class="bcrm-card"><h2>Hard Constraint — CustomerID Blacklist</h2>';
            echo '<p><strong>قانون:</strong> تمام CustomerIDهای این لیست برای همیشه نامعتبرند و نباید به هیچ مشتری اختصاص داده شوند.</p>';
            echo '<p>کل Blacklist: <strong>' . esc_html(number_format_i18n((int)$b['blacklist_total'])) . '</strong> — هنوز موجود در CRM: <strong>' . esc_html(number_format_i18n((int)$b['present_customers'])) . '</strong> — خارج از CRM: <strong>' . esc_html(number_format_i18n((int)$b['absent_customers'])) . '</strong></p>';
            echo '<p>کل رکوردهای وابسته به Customerهای Blacklisted: <strong>' . esc_html(number_format_i18n((int)$b['relation_total'])) . '</strong></p><ul>';
            foreach ((array)$b['relation_counts'] as $name=>$count) echo '<li>' . esc_html($name) . ': ' . esc_html(number_format_i18n((int)$count)) . '</li>';
            echo '</ul>';
            if (!empty($b['samples'])) {
                echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Internal ID</th><th>CustomerID ممنوع</th><th>نام فعلی</th><th>Completeness</th><th>Status</th><th>Total Paid</th><th>Declared Paid</th><th>اقدام لازم</th></tr></thead><tbody>';
                foreach ($b['samples'] as $x) { echo '<tr>'; foreach (['internal_id','customer_id','name','completeness','status','total_paid','declared_paid','replacement'] as $k) echo '<td>'.esc_html((string)($x[$k]??'')).'</td>'; echo '</tr>'; }
                echo '</tbody></table></div>';
            }
            echo '</div>';
        }

        if (!empty($result['samples'])) {
            echo '<div class="bcrm-card"><h2>نمونه موارد مسئله‌دار</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr>';
            foreach (['علت','internal ID قدیمی','CustomerID قدیمی','نام قدیمی','CustomerID مقصد','نام مورد انتظار','CustomerID فعلی روی internal ID قدیمی','نام فعلی مقصد','Complete قدیم','Complete مقصد','Declared قدیم','Declared روی رکورد قدیمی','Declared مقصد','توضیح'] as $h) echo '<th>' . esc_html($h) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($result['samples'] as $s) {
                echo '<tr>';
                foreach (['reason','old_internal_id','old_customer_id','old_name','target_customer_id','expected_name','current_old_row_customer_id','current_target_name','old_completeness','current_target_completeness','old_declared_paid','current_old_row_declared_paid','current_target_declared_paid','note'] as $k) {
                    $v = $s[$k] ?? '';
                    if (is_numeric($v) && strpos($k, 'paid') !== false) $v = number_format_i18n((float)$v);
                    echo '<td>' . esc_html((string)$v) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    }
}
