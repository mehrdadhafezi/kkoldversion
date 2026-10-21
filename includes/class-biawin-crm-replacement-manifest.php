<?php

if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.43 read-only Replacement ID Allocation + Migration Manifest.
 *
 * Safety guarantees:
 * - Never INSERT/UPDATE/DELETE CRM domain rows.
 * - Proposed replacement CustomerIDs are computed in memory only.
 * - Blacklisted IDs are permanently invalid and are never proposed.
 * - Existing CRM IDs, baseline IDs, corrected-target IDs and staged-import IDs are reserved.
 * - Deterministic profiles that share the same blacklisted corrected target share one replacement ID.
 */
class Biawin_CRM_Replacement_Manifest
{
    private const BASELINE_FILE = 'data/historical-identity-baseline.ndjson';
    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';
    private const BASELINE_CUTOFF = '2026-08-18 14:28:35';
    private const BATCH_SIZE = 200;
    private const SAMPLE_LIMIT = 300;
    private const MAX_CUSTOMER_NUMBER = 999999;
    private static $blacklist_cache = null;

    public static function handle(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_run_replacement_manifest')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        @set_time_limit(0);
        $result = self::build(false);
        set_transient('bcrm_replacement_manifest_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-historical-audit', admin_url('admin.php')));
        exit;
    }

    public static function export_csv(): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer('bcrm_export_replacement_manifest_csv')) {
            wp_die('دسترسی غیرمجاز است.');
        }
        @set_time_limit(0);
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-migration-manifest-v0.8.43-' . gmdate('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::csv_headers());
        $result = self::build(true, function(array $row) use ($out) {
            fputcsv($out, self::csv_row($row));
        });
        if (empty($result['ok'])) {
            fputcsv($out, ['ERROR', $result['error'] ?? 'Manifest build failed']);
        }
        fclose($out);
        exit;
    }

    public static function render_latest(): void
    {
        $result = get_transient('bcrm_replacement_manifest_' . get_current_user_id());
        echo '<div class="bcrm-card"><h2>v0.8.43 — Replacement ID Allocation + Migration Manifest</h2>';
        echo '<p class="description">این مرحله کاملاً Dry Run است. هیچ CustomerID جدیدی ثبت نمی‌شود و هیچ رکورد CRM جابه‌جا، حذف یا ایجاد نمی‌شود. Allocation فقط در حافظه محاسبه می‌شود.</p>';
        echo '<p><strong>قاعده تخصیص:</strong> اگر مقصد اصلاحی معتبر باشد همان مقصد استفاده می‌شود؛ فقط زمانی CustomerID جدید پیشنهاد می‌شود که <strong>خود مقصد اصلاحی</strong> در Blacklist باشد. چند پروفایل تاریخی که به یک مقصد ممنوع مشترک نگاشت شده‌اند یک Replacement مشترک می‌گیرند.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:10px">';
        wp_nonce_field('bcrm_run_replacement_manifest');
        echo '<input type="hidden" name="action" value="bcrm_run_replacement_manifest">';
        echo '<button class="button button-primary button-hero">ساخت Migration Manifest (Dry Run)</button></form>';
        if (is_array($result) && !empty($result['ok'])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block">';
            wp_nonce_field('bcrm_export_replacement_manifest_csv');
            echo '<input type="hidden" name="action" value="bcrm_export_replacement_manifest_csv">';
            echo '<button class="button button-secondary">دانلود CSV کامل Manifest</button></form>';
        }
        echo '</div>';

        if (!is_array($result)) return;
        if (empty($result['ok'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html((string)($result['error'] ?? 'Manifest ساخته نشد.')) . '</p></div>';
            return;
        }

        $c = $result['counts'];
        echo '<div class="bcrm-card"><h2>خلاصه Allocation و Manifest</h2>';
        echo '<p class="description">شروع: ' . esc_html((string)$result['started_at']) . ' — پایان: ' . esc_html((string)$result['finished_at']) . '</p>';
        echo '<div class="bcrm-grid">';
        $labels = [
            'profiles' => 'پروفایل تاریخی بررسی‌شده',
            'deterministic' => 'نگاشت قطعی',
            'manual' => 'نیازمند بررسی دستی',
            'unique_corrected_targets' => 'هویت مقصد یکتای فایل اصلاحی',
            'valid_target_existing_profiles' => 'پروفایل → مقصد معتبر موجود',
            'valid_target_missing_profiles' => 'پروفایل → مقصد معتبر مفقود',
            'blacklisted_target_profiles' => 'پروفایل → مقصد اصلاحی ممنوع',
            'unique_blacklisted_targets' => 'مقصد ممنوع یکتا',
            'replacement_ids_allocated' => 'Replacement ID یکتای پیشنهادی',
            'current_blacklisted_source_rows' => 'رکورد فعلی با CustomerID ممنوع',
            'recent_source_conflicts' => 'Conflict با فعالیت جدید مبدأ',
            'recent_target_activity_profiles' => 'دارای فعالیت جدید روی مقصد',
            'historical_complete_recoverable' => 'Complete تاریخی قابل بازیابی',
            'declared_recoverable' => 'Declared تاریخی قابل بازیابی',
            'allocation_collisions_skipped' => 'ID رزروشده/Collision ردشده',
        ];
        foreach ($labels as $key => $label) {
            echo '<div class="bcrm-card"><strong>' . esc_html(number_format_i18n((int)($c[$key] ?? 0))) . '</strong><span>' . esc_html($label) . '</span></div>';
        }
        echo '</div>';

        $a = $result['allocation'];
        echo '<h3>Registry تخصیص</h3><p>';
        echo 'بیشترین شماره رزروشده قبل از Allocation: <strong>' . esc_html((string)$a['max_reserved_number']) . '</strong> — ';
        echo 'اولین Replacement: <strong>' . esc_html((string)$a['first_replacement']) . '</strong> — ';
        echo 'آخرین Replacement: <strong>' . esc_html((string)$a['last_replacement']) . '</strong> — ';
        echo 'کل IDهای رزروشده: <strong>' . esc_html(number_format_i18n((int)$a['reserved_total'])) . '</strong>';
        echo '</p>';
        echo '<p class="description">Registry شامل CustomerIDهای فعلی CRM، Blacklist رسمی، CustomerIDهای قدیمی Baseline، تمام مقصدهای فایل اصلاحی و CustomerIDهای موجود در Import Stage است. در Commit نهایی باید Generator نیز به‌طور دائمی از Blacklist و Registry مهاجرت عبور کند.</p>';
        echo '</div>';

        if (!empty($result['strategy_counts'])) {
            echo '<div class="bcrm-card"><h2>استراتژی مقصد</h2><ul>';
            foreach ($result['strategy_counts'] as $name => $count) {
                echo '<li><code>' . esc_html($name) . '</code>: <strong>' . esc_html(number_format_i18n((int)$count)) . '</strong></li>';
            }
            echo '</ul></div>';
        }

        if (!empty($result['replacement_samples'])) {
            echo '<div class="bcrm-card"><h2>نمونه Replacement Registry</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Corrected Target ممنوع</th><th>Replacement پیشنهادی</th><th>تعداد پروفایل تاریخی</th><th>Target فعلی وجود دارد؟</th><th>Internal ID فعلی Target</th></tr></thead><tbody>';
            foreach ($result['replacement_samples'] as $x) {
                echo '<tr><td>' . esc_html((string)$x['blacklisted_target']) . '</td><td><strong>' . esc_html((string)$x['replacement_id']) . '</strong></td><td>' . esc_html(number_format_i18n((int)$x['profile_count'])) . '</td><td>' . esc_html($x['target_exists'] ? 'بله' : 'خیر') . '</td><td>' . esc_html((string)$x['target_internal_id']) . '</td></tr>';
            }
            echo '</tbody></table></div></div>';
        }

        if (!empty($result['samples'])) {
            echo '<div class="bcrm-card"><h2>نمونه Manifest</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr>';
            $headers = ['Class','Destination Strategy','Old Internal','Old CustomerID','Old Name','Corrected Target','Proposed Final CustomerID','Target Internal','Recent Source','Recent Target','Historical Ops','Old Complete','Target Complete','Declared Old','Declared Target','Action'];
            foreach ($headers as $h) echo '<th>' . esc_html($h) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($result['samples'] as $x) {
                $keys = ['class','destination_strategy','old_internal_id','old_customer_id','old_name','corrected_target','proposed_final_customer_id','target_internal_id','recent_source_activity','recent_target_activity','historical_ops','old_complete','target_complete','old_declared','target_declared','action'];
                echo '<tr>';
                foreach ($keys as $k) {
                    $v = $x[$k] ?? '';
                    if (in_array($k, ['old_declared','target_declared'], true) && is_numeric($v)) $v = number_format_i18n((float)$v);
                    echo '<td>' . esc_html((string)$v) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    }

    public static function stream_manifest(callable $emit): array
    {
        @set_time_limit(0);
        return self::build(true, $emit);
    }

    private static function build(bool $for_export = false, ?callable $emit = null): array
    {
        global $wpdb;
        $result = [
            'ok' => false,
            'error' => '',
            'started_at' => current_time('mysql'),
            'finished_at' => null,
            'counts' => [
                'profiles'=>0,'deterministic'=>0,'manual'=>0,'unique_corrected_targets'=>0,
                'valid_target_existing_profiles'=>0,'valid_target_missing_profiles'=>0,
                'blacklisted_target_profiles'=>0,'unique_blacklisted_targets'=>0,
                'replacement_ids_allocated'=>0,'current_blacklisted_source_rows'=>0,
                'recent_source_conflicts'=>0,'recent_target_activity_profiles'=>0,
                'historical_complete_recoverable'=>0,'declared_recoverable'=>0,
                'allocation_collisions_skipped'=>0,
            ],
            'allocation' => [
                'reserved_total'=>0,'max_reserved_number'=>0,'first_replacement'=>'-','last_replacement'=>'-',
            ],
            'strategy_counts' => [],
            'replacement_samples' => [],
            'samples' => [],
        ];

        $baseline = BIAWIN_CRM_DIR . self::BASELINE_FILE;
        if (!is_readable($baseline)) { $result['error'] = 'Baseline تاریخی پیدا نشد.'; return $result; }
        $blacklist = self::load_blacklist();
        if (!$blacklist) { $result['error'] = 'Blacklist رسمی پیدا نشد.'; return $result; }

        // Pass 1: reserve every known CustomerID and discover logical corrected targets.
        $reserved = $blacklist;
        $target_profile_counts = [];
        $all_targets = [];
        $fh = fopen($baseline, 'rb');
        if (!$fh) { $result['error'] = 'Baseline قابل خواندن نیست.'; return $result; }
        $meta = json_decode(trim((string)fgets($fh)), true);
        if (!is_array($meta) || ($meta['type'] ?? '') !== 'meta') { fclose($fh); $result['error'] = 'Baseline معتبر نیست.'; return $result; }
        while (($line = fgets($fh)) !== false) {
            $row = json_decode(trim($line), true);
            if (!is_array($row) || ($row['type'] ?? '') !== 'profile') continue;
            $p = $row['profile'] ?? [];
            $old = self::canonical_cid((string)($p['old_source_customer_id'] ?? ''));
            $target = self::canonical_cid((string)($p['target_customer_id'] ?? ''));
            if ($old !== '') $reserved[$old] = true;
            if ($target !== '') {
                $reserved[$target] = true;
                $all_targets[$target] = true;
                if (($p['mapping_status'] ?? '') === 'deterministic') {
                    $target_profile_counts[$target] = ($target_profile_counts[$target] ?? 0) + 1;
                }
            }
        }
        fclose($fh);
        $result['counts']['unique_corrected_targets'] = count($all_targets);

        // Reserve all CustomerIDs currently present in CRM.
        $customers = Biawin_CRM_DB::table('customers');
        foreach ((array)$wpdb->get_col("SELECT DISTINCT source_customer_id FROM $customers WHERE source_customer_id IS NOT NULL AND source_customer_id<>''") as $cid) {
            $cid = self::canonical_cid((string)$cid); if ($cid !== '') $reserved[$cid] = true;
        }

        // Reserve IDs visible in staged imports, because a queued/staged import may later materialize them.
        $stage = Biawin_CRM_DB::table('import_stage_invoices');
        $has_stage_source = $wpdb->get_var($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME='source_customer_id' LIMIT 1", $stage));
        if ($has_stage_source) {
            foreach ((array)$wpdb->get_col("SELECT DISTINCT source_customer_id FROM $stage WHERE source_customer_id IS NOT NULL AND source_customer_id<>''") as $cid) {
                $cid = self::canonical_cid((string)$cid); if ($cid !== '') $reserved[$cid] = true;
            }
        }

        $max = 0;
        foreach (array_keys($reserved) as $cid) {
            $n = self::cid_number($cid); if ($n > $max) $max = $n;
        }
        $result['allocation']['reserved_total'] = count($reserved);
        $result['allocation']['max_reserved_number'] = $max;

        // Query whether blacklisted corrected targets currently exist. This decides REKEY vs CREATE.
        $blacklisted_targets = [];
        foreach ($target_profile_counts as $target => $count) {
            if (isset($blacklist[$target])) $blacklisted_targets[$target] = $count;
        }
        $result['counts']['unique_blacklisted_targets'] = count($blacklisted_targets);
        $target_rows = self::load_customer_rows_by_source(array_keys($all_targets));

        // Allocate exactly one replacement per UNIQUE blacklisted corrected target.
        $replacement_map = [];
        $cursor = $max + 1;
        foreach ($blacklisted_targets as $target => $profile_count) {
            while ($cursor <= self::MAX_CUSTOMER_NUMBER) {
                $candidate = sprintf('C%06d', $cursor);
                $cursor++;
                if (isset($reserved[$candidate])) {
                    $result['counts']['allocation_collisions_skipped']++;
                    continue;
                }
                $reserved[$candidate] = true;
                $replacement_map[$target] = $candidate;
                break;
            }
            if (!isset($replacement_map[$target])) {
                $result['error'] = 'فضای CustomerID شش‌رقمی برای Allocation کافی نیست. هیچ تغییری در CRM انجام نشده است.';
                return $result;
            }
            if (count($result['replacement_samples']) < 100) {
                $tr = $target_rows[$target] ?? null;
                $result['replacement_samples'][] = [
                    'blacklisted_target'=>$target,
                    'replacement_id'=>$replacement_map[$target],
                    'profile_count'=>$profile_count,
                    'target_exists'=>(bool)$tr,
                    'target_internal_id'=>$tr ? (int)$tr->id : '-',
                ];
            }
        }
        $result['counts']['replacement_ids_allocated'] = count($replacement_map);
        if ($replacement_map) {
            $vals = array_values($replacement_map);
            $result['allocation']['first_replacement'] = reset($vals);
            $result['allocation']['last_replacement'] = end($vals);
        }

        // Pass 2: build row-level manifest and summary.
        $fh = fopen($baseline, 'rb');
        if (!$fh) { $result['error'] = 'Baseline در Pass دوم قابل خواندن نیست.'; return $result; }
        fgets($fh); // meta
        $batch = [];
        while (($line = fgets($fh)) !== false) {
            $row = json_decode(trim($line), true);
            if (!is_array($row) || ($row['type'] ?? '') !== 'profile') continue;
            $batch[] = $row['profile'] ?? [];
            if (count($batch) >= self::BATCH_SIZE) {
                self::manifest_batch($batch, $replacement_map, $blacklist, $result, $emit);
                $batch = [];
            }
        }
        if ($batch) self::manifest_batch($batch, $replacement_map, $blacklist, $result, $emit);
        fclose($fh);

        $result['ok'] = true;
        $result['finished_at'] = current_time('mysql');
        return $result;
    }

    private static function manifest_batch(array $profiles, array $replacement_map, array $blacklist, array &$result, ?callable $emit): void
    {
        $internal_ids = [];
        $target_ids = [];
        foreach ($profiles as $p) {
            $old_id = absint($p['old_internal_id'] ?? 0); if ($old_id) $internal_ids[$old_id] = $old_id;
            $target = self::canonical_cid((string)($p['target_customer_id'] ?? '')); if ($target !== '') $target_ids[$target] = $target;
        }
        $old_rows = self::load_customer_rows_by_internal(array_values($internal_ids));
        $target_rows = self::load_customer_rows_by_source(array_values($target_ids));
        $recent_source = self::detect_recent_activity(array_values($internal_ids));
        $target_internal_ids = [];
        foreach ($target_rows as $row) $target_internal_ids[(int)$row->id] = (int)$row->id;
        $recent_target = self::detect_recent_activity(array_values($target_internal_ids));

        foreach ($profiles as $p) {
            $result['counts']['profiles']++;
            $mapping = (string)($p['mapping_status'] ?? 'none');
            $old_id = absint($p['old_internal_id'] ?? 0);
            $old_cid = self::canonical_cid((string)($p['old_source_customer_id'] ?? ''));
            $target_cid = self::canonical_cid((string)($p['target_customer_id'] ?? ''));
            $old_row = $old_rows[$old_id] ?? null;
            $target_row = $target_rows[$target_cid] ?? null;
            $source_recent = (int)($recent_source[$old_id] ?? 0);
            $target_recent = $target_row ? (int)($recent_target[(int)$target_row->id] ?? 0) : 0;
            $history = is_array($p['history_counts'] ?? null) ? $p['history_counts'] : [];
            $history_total = array_sum(array_map('intval', $history));
            $old_complete = (string)($p['old_profile_completeness'] ?? '');
            $old_declared = $p['old_declared_paid'] ?? null;

            if ($mapping !== 'deterministic' || $target_cid === '') {
                $class = 'AMBIGUOUS_MANUAL';
                $strategy = 'MANUAL_REVIEW';
                $final_cid = '';
                $action = $mapping === 'ambiguous' ? 'Split/Merge مبهم؛ Auto Commit ممنوع.' : 'نگاشت قطعی مقصد وجود ندارد؛ Auto Commit ممنوع.';
                $result['counts']['manual']++;
            } else {
                $result['counts']['deterministic']++;
                $target_blacklisted = isset($blacklist[$target_cid]);
                if ($target_blacklisted) {
                    $result['counts']['blacklisted_target_profiles']++;
                    $final_cid = $replacement_map[$target_cid] ?? '';
                    if ($target_row) {
                        $strategy = 'REKEY_EXISTING_BLACKLISTED_TARGET';
                        $action = 'Internal target موجود حفظ شود؛ CustomerID ممنوع آن در Commit به Replacement تغییر کند و سوابق شخص به همان هویت ادغام شود.';
                    } else {
                        $strategy = 'CREATE_REPLACEMENT_TARGET';
                        $action = 'برای هویت مقصد ممنوع، Customer مقصد با Replacement جدید در Commit ساخته شود.';
                    }
                } else {
                    $final_cid = $target_cid;
                    if ($target_row) {
                        $strategy = 'USE_EXISTING_VALID_TARGET';
                        $action = 'از Customer مقصد معتبر موجود استفاده شود؛ CustomerID جدید لازم نیست.';
                        $result['counts']['valid_target_existing_profiles']++;
                    } else {
                        $strategy = 'CREATE_VALID_TARGET';
                        $action = 'Customer مقصد با همان CustomerID معتبر فایل اصلاحی در Commit ساخته شود؛ CustomerID جدید لازم نیست.';
                        $result['counts']['valid_target_missing_profiles']++;
                    }
                }

                if ($old_row && isset($blacklist[self::canonical_cid((string)$old_row->source_customer_id)])) {
                    $result['counts']['current_blacklisted_source_rows']++;
                }
                if ($target_recent > 0) $result['counts']['recent_target_activity_profiles']++;
                if ($source_recent > 0 && (!$target_row || (int)$target_row->id !== $old_id)) {
                    $class = 'CONFLICT_CURRENT_ACTIVITY';
                    $result['counts']['recent_source_conflicts']++;
                    $action .= ' فعالیت جدید بعد از Cutoff روی مبدأ وجود دارد؛ Commit باید این رکوردهای جدید را preserve/migrate کند و overwrite نکند.';
                } else {
                    $class = 'READY_MANIFEST';
                }

                if ($old_complete === 'complete' && (!$target_row || (string)$target_row->profile_completeness !== 'complete')) {
                    $result['counts']['historical_complete_recoverable']++;
                }
                if ($old_row && $old_declared !== null && $old_declared !== '' && self::money_equal($old_row->declared_paid ?? null, $old_declared) && (!$target_row || !self::money_equal($target_row->declared_paid ?? null, $old_declared))) {
                    $result['counts']['declared_recoverable']++;
                }
            }

            $result['strategy_counts'][$strategy] = ($result['strategy_counts'][$strategy] ?? 0) + 1;
            $manifest = [
                'class'=>$class,
                'destination_strategy'=>$strategy,
                'old_internal_id'=>$old_id,
                'old_customer_id'=>$old_cid,
                'old_name'=>(string)($p['old_name'] ?? ''),
                'corrected_target'=>$target_cid,
                'expected_name'=>(string)($p['expected_name'] ?? ''),
                'proposed_final_customer_id'=>$final_cid,
                'target_internal_id'=>$target_row ? (int)$target_row->id : '',
                'target_current_customer_id'=>$target_row ? (string)$target_row->source_customer_id : '',
                'target_name'=>$target_row ? (string)$target_row->display_name : '',
                'recent_source_activity'=>$source_recent,
                'recent_target_activity'=>$target_recent,
                'historical_ops'=>$history_total,
                'old_complete'=>$old_complete,
                'target_complete'=>$target_row ? (string)$target_row->profile_completeness : '',
                'old_declared'=>$old_declared,
                'target_declared'=>$target_row ? $target_row->declared_paid : null,
                'old_invoice_count'=>(int)($p['old_invoice_count'] ?? 0),
                'target_invoice_count_excel'=>(int)($p['target_invoice_count_in_excel'] ?? 0),
                'action'=>$action,
            ];
            if (count($result['samples']) < self::SAMPLE_LIMIT && ($class !== 'READY_MANIFEST' || $old_complete === 'complete' || $strategy !== 'USE_EXISTING_VALID_TARGET')) {
                $result['samples'][] = $manifest;
            }
            if ($emit) $emit($manifest);
        }
    }

    private static function load_customer_rows_by_internal(array $ids): array
    {
        global $wpdb;
        $out = []; if (!$ids) return $out;
        $table = Biawin_CRM_DB::table('customers');
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 500) as $chunk) {
            if (!$chunk) continue;
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT id,source_customer_id,display_name,profile_completeness,status,total_paid,declared_paid,updated_at FROM $table WHERE id IN ($ph)", ...$chunk);
            foreach ((array)$wpdb->get_results($sql) as $row) $out[(int)$row->id] = $row;
        }
        return $out;
    }

    private static function load_customer_rows_by_source(array $ids): array
    {
        global $wpdb;
        $out = []; if (!$ids) return $out;
        $table = Biawin_CRM_DB::table('customers');
        $ids = array_values(array_unique(array_filter(array_map([__CLASS__, 'canonical_cid'], $ids))));
        foreach (array_chunk($ids, 500) as $chunk) {
            if (!$chunk) continue;
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            $sql = $wpdb->prepare("SELECT id,source_customer_id,display_name,profile_completeness,status,total_paid,declared_paid,updated_at FROM $table WHERE source_customer_id IN ($ph)", ...$chunk);
            foreach ((array)$wpdb->get_results($sql) as $row) $out[self::canonical_cid((string)$row->source_customer_id)] = $row;
        }
        return $out;
    }

    private static function detect_recent_activity(array $customer_ids): array
    {
        global $wpdb;
        $out = []; if (!$customer_ids) return $out;
        $customer_ids = array_values(array_unique(array_filter(array_map('intval', $customer_ids))));
        $tables = ['interactions','assignments','complaints','deficiency_cases','financial_cases','service_cases','customer_tags','attachments','case_actions','activities','payments','refunds'];
        foreach ($tables as $name) {
            $table = Biawin_CRM_DB::table($name);
            $cols = (array)$wpdb->get_col($wpdb->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME IN ('customer_id','created_at','updated_at')", $table));
            if (!in_array('customer_id', $cols, true)) continue;
            $date_col = in_array('updated_at', $cols, true) ? 'updated_at' : (in_array('created_at', $cols, true) ? 'created_at' : '');
            if (!$date_col) continue;
            foreach (array_chunk($customer_ids, 300) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '%d'));
                $args = array_merge($chunk, [self::BASELINE_CUTOFF]);
                $sql = $wpdb->prepare("SELECT customer_id,COUNT(*) c FROM $table WHERE customer_id IN ($ph) AND $date_col > %s GROUP BY customer_id", ...$args);
                foreach ((array)$wpdb->get_results($sql) as $row) {
                    $cid = (int)$row->customer_id;
                    $out[$cid] = ($out[$cid] ?? 0) + (int)$row->c;
                }
            }
        }
        return $out;
    }

    private static function load_blacklist(): array
    {
        if (is_array(self::$blacklist_cache)) return self::$blacklist_cache;
        $path = BIAWIN_CRM_DIR . self::BLACKLIST_FILE;
        if (!is_readable($path)) return self::$blacklist_cache = [];
        $set = [];
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $id) {
            $id = self::canonical_cid((string)$id);
            if ($id !== '') $set[$id] = true;
        }
        return self::$blacklist_cache = $set;
    }

    private static function canonical_cid(string $id): string
    {
        $id = strtoupper(trim($id));
        if (preg_match('/^C(\d{1,6})$/', $id, $m)) return sprintf('C%06d', (int)$m[1]);
        if (preg_match('/^(\d{1,6})$/', $id, $m)) return sprintf('C%06d', (int)$m[1]);
        return '';
    }

    private static function cid_number(string $id): int
    {
        $id = self::canonical_cid($id);
        return $id === '' ? 0 : (int)substr($id, 1);
    }

    private static function money_equal($a, $b): bool
    {
        if ($a === null || $a === '' || $b === null || $b === '') return false;
        return abs((float)$a - (float)$b) < 0.01;
    }

    private static function csv_headers(): array
    {
        return ['Class','Destination Strategy','Old Internal ID','Old CustomerID','Old Name','Corrected Target','Expected Name','Proposed Final CustomerID','Target Internal ID','Target Current CustomerID','Target Name','Recent Source Activity','Recent Target Activity','Historical Ops','Old Complete','Target Complete','Old Declared','Target Declared','Old Invoice Count','Target Invoice Count Excel','Action'];
    }

    private static function csv_row(array $x): array
    {
        return [
            $x['class'] ?? '',$x['destination_strategy'] ?? '',$x['old_internal_id'] ?? '',$x['old_customer_id'] ?? '',$x['old_name'] ?? '',
            $x['corrected_target'] ?? '',$x['expected_name'] ?? '',$x['proposed_final_customer_id'] ?? '',$x['target_internal_id'] ?? '',$x['target_current_customer_id'] ?? '',
            $x['target_name'] ?? '',$x['recent_source_activity'] ?? 0,$x['recent_target_activity'] ?? 0,$x['historical_ops'] ?? 0,$x['old_complete'] ?? '',
            $x['target_complete'] ?? '',$x['old_declared'] ?? '',$x['target_declared'] ?? '',$x['old_invoice_count'] ?? 0,$x['target_invoice_count_excel'] ?? 0,$x['action'] ?? '',
        ];
    }
}
