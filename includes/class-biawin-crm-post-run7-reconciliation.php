<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.54.2 — Post-Run-7 Remaining Blacklist Read-only Reconciliation.
 *
 * This class derives scope only from the live Readiness Guard and performs no
 * CRM domain mutation. It does not call any domain-changing helper path.
 */
class Biawin_CRM_Post_Run7_Reconciliation
{
    private const VERSION = '0.8.54.2';
    private const PLAN_FILE = 'data/frozen-canonical-plan-v0.8.53.2.json';
    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';
    private const SOURCE_RUN_UUID = '39c9c292-31e1-43df-8305-201bdec445ca';
    private const SOURCE_PLAN_SHA256 = 'bb87ff58b41a619203aa805bc9e557c887decfdea8888290f7906ebcf9191048';
    private const RUN_MODE = 'blacklist_final_commit_v08533';

    private static $blacklist = null;
    private static $plan_index = null;
    private static $cols = [];

    private static function result_key(): string { return 'bcrm_v08542_post_run7_reconciliation_' . get_current_user_id(); }

    public static function init(): void
    {
        add_action('admin_post_bcrm_v0854_run_reconciliation', [__CLASS__, 'handle_run']);
        add_action('admin_post_bcrm_v0854_export_reconciliation_csv', [__CLASS__, 'export_csv']);
        add_action('admin_post_bcrm_v0854_export_reconciliation_json', [__CLASS__, 'export_json']);
    }

    public static function handle_run(): void
    {
        self::authorize('bcrm_v0854_run_reconciliation');
        @set_time_limit(0);
        set_transient(self::result_key(), self::build_report(), 2 * HOUR_IN_SECONDS);
        self::redirect();
    }

    public static function export_csv(): void
    {
        self::authorize('bcrm_v0854_export_reconciliation_csv');
        @set_time_limit(0);
        $report = self::build_report();
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-v0.8.54.2-post-run7-reconciliation-' . gmdate('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::csv_headers());
        foreach ((array)($report['rows'] ?? []) as $row) {
            $line = [];
            foreach (self::csv_headers() as $key) $line[] = self::csv_value($row[$key] ?? '');
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    public static function export_json(): void
    {
        self::authorize('bcrm_v0854_export_reconciliation_json');
        @set_time_limit(0);
        $report = self::build_report();
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bcrm-v0.8.54.2-post-run7-reconciliation-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function render_latest(): void
    {
        $r = get_transient(self::result_key());
        echo '<div class="bcrm-card" style="border:2px solid #d63638"><h2>v0.8.54.2 — Post-Run-7 Remaining Blacklist Reconciliation</h2>';
        echo '<p><strong>READ-ONLY — NO COMMIT</strong></p>';
        echo '<p class="description"><strong>READ-ONLY — این مرحله هیچ تغییری در مشتری، CustomerID، فاکتور، تلفن، تاریخچه یا Guard ایجاد نمی‌کند.</strong></p>';
        echo '<p class="description">Scope از Readiness Guard زنده مشتق می‌شود، نه از کل Frozen Preflight قدیمی. نتیجه فقط مشخص می‌کند هر Guard row برای مرحله بعدی چه وضعیت ایمنی دارد.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_v0854_run_reconciliation'); echo '<input type="hidden" name="action" value="bcrm_v0854_run_reconciliation"><button class="button button-primary">Run Post-Run-7 Read-only Reconciliation</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px">'; wp_nonce_field('bcrm_v0854_export_reconciliation_csv'); echo '<input type="hidden" name="action" value="bcrm_v0854_export_reconciliation_csv"><button class="button">Download v0.8.54.2 Reconciliation CSV</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block">'; wp_nonce_field('bcrm_v0854_export_reconciliation_json'); echo '<input type="hidden" name="action" value="bcrm_v0854_export_reconciliation_json"><button class="button">Download v0.8.54.2 Reconciliation JSON</button></form>';
        echo '</div>';
        if (!is_array($r)) return;
        if (empty($r['ok'])) {
            echo '<div class="notice notice-error inline"><p>'.esc_html((string)($r['error'] ?? 'v0.8.54.2 reconciliation failed.')).'</p></div>';
            return;
        }
        $s = (array)$r['summary'];
        echo '<div class="bcrm-card"><h3>v0.8.54.2 Summary</h3><p>Generated: '.esc_html((string)$r['generated_at']).' — Reconciliation SHA256: <code>'.esc_html((string)$r['reconciliation_sha256']).'</code></p>';
        echo '<p><strong>Expert Readiness / Guard state:</strong> '.(!empty($r['invariants']['guard_enabled'])?'<span style="color:#008a20">Guard Enabled</span>':'<span style="color:#b32d2e">Guard Disabled</span>').' — normal access to quarantine: <strong>'.number_format_i18n((int)($s['normal_access_to_quarantine'] ?? 0)).'</strong></p>';
        echo '<div class="bcrm-grid">';
        foreach (self::summary_labels() as $key=>$label) echo '<div class="bcrm-card"><strong>'.number_format_i18n((int)($s[$key] ?? 0)).'</strong><span>'.esc_html($label).'</span></div>';
        echo '</div><p class="description">Canonicalization: '.esc_html((string)$r['canonicalization_method']).'</p></div>';
        if (!empty($r['samples'])) {
            echo '<div class="bcrm-card"><h3>Blocked Samples</h3><div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Internal</th><th>CustomerID</th><th>Name</th><th>Classification</th><th>Reasons</th></tr></thead><tbody>';
            foreach ((array)$r['samples'] as $x) echo '<tr><td>'.esc_html((string)$x['current_internal_id']).'</td><td>'.esc_html((string)$x['current_customer_id']).'</td><td>'.esc_html((string)$x['current_name']).'</td><td>'.esc_html((string)$x['classification']).'</td><td>'.esc_html(implode(',', (array)$x['block_reason_codes'])).'</td></tr>';
            echo '</tbody></table></div></div>';
        }
    }

    private static function build_report(): array
    {
        global $wpdb;
        $plan = self::load_plan_index();
        if (!$plan) return ['ok'=>false, 'error'=>'Frozen Plan v0.8.53.2 missing or invalid.'];
        $blacklist = self::load_blacklist();
        if (!$blacklist) return ['ok'=>false, 'error'=>'Official blacklist file missing or empty.'];

        $customers = Biawin_CRM_DB::table('customers');
        $guard = Biawin_CRM_Production_Readiness::table();
        if (!self::table_exists($guard)) return ['ok'=>false, 'error'=>'Readiness Guard table does not exist.'];

        $guard_rows = (array)$wpdb->get_results("SELECT q.*, c.id live_customer_id, c.source_customer_id current_customer_id, c.display_name current_name, c.normalized_name current_normalized_name, c.updated_at customer_updated_at FROM $guard q LEFT JOIN $customers c ON c.id=q.customer_id ORDER BY q.customer_id ASC", ARRAY_A);
        $ids = array_map('intval', array_column($guard_rows, 'customer_id'));
        $current_blacklisted = self::current_blacklisted_customers($blacklist);
        $current_blacklisted_ids = array_fill_keys(array_map('intval', array_column($current_blacklisted, 'id')), true);
        $guard_ids = array_fill_keys($ids, true);

        $run = self::source_run();
        $run_state = $run ? self::decode_json((string)($run['result_json'] ?? '')) : [];
        $run7_started_at = (string)($run['started_at'] ?? '2026-08-31 01:13:36');
        $run7_status = (string)($run['status'] ?? '');
        $quarantine_by_source = self::run_quarantine_index($run_state);

        $target_ids = [];
        $target_cids = [];
        foreach ($guard_rows as $g) {
            $p = $plan[(int)$g['customer_id']] ?? null;
            if ($p) {
                $tid = (int)($p['source_target_internal_id'] ?? 0);
                $tcid = self::canonical_cid((string)($p['source_person_final_customer_id'] ?? ''));
                if ($tid) $target_ids[$tid] = $tid;
                if ($tcid !== '') $target_cids[$tcid] = $tcid;
            }
        }
        $targets_by_id = self::customers_by_ids(array_values($target_ids));
        $holders_by_cid = self::customers_by_cids(array_values($target_cids));

        $counts_by_table = self::domain_counts_by_customer($ids);
        $assignments = self::active_assignments_by_customer($ids);
        $human = self::human_activity_by_customer($ids, $run7_started_at);
        $merge = self::manual_merge_by_customer($ids, $run7_started_at);
        $evidence = self::invoice_phone_evidence_by_customer($ids, $plan, $targets_by_id, $holders_by_cid, $run7_started_at);

        $rows = [];
        foreach ($guard_rows as $g) {
            $row = self::build_row($g, $plan[(int)$g['customer_id']] ?? null, $blacklist, $current_blacklisted_ids, $quarantine_by_source, $run7_status, $run7_started_at, $targets_by_id, $holders_by_cid, $counts_by_table, $assignments, $human, $merge, $evidence);
            $rows[] = $row;
        }
        usort($rows, fn($a, $b) => (int)$a['current_internal_id'] <=> (int)$b['current_internal_id']);

        $summary = self::summarize($rows, $guard_rows, $current_blacklisted, $guard_ids);
        $report_hash = self::report_hash($rows);
        return [
            'ok'=>true,
            'version'=>self::VERSION,
            'mode'=>'READ_ONLY',
            'generated_at'=>current_time('mysql'),
            'source_run_uuid'=>self::SOURCE_RUN_UUID,
            'source_frozen_plan_sha256'=>self::SOURCE_PLAN_SHA256,
            'reconciliation_sha256'=>$report_hash,
            'canonicalization_method'=>'rows ordered by current_internal_id ASC; selected stable scalar fields joined as key=value lines; arrays sorted and pipe-joined; SHA-256 over UTF-8 canonical string',
            'summary'=>$summary,
            'invariants'=>[
                'guard_enabled'=>class_exists('Biawin_CRM_Production_Readiness') && Biawin_CRM_Production_Readiness::guard_enabled(),
                'remaining_guarded_equals_rows'=>count($rows) === (int)$summary['remaining_guarded'],
                'classification_sum_equals_remaining_guarded'=>self::classification_sum($summary) === (int)$summary['remaining_guarded'],
                'csv_row_count_expected'=>(int)$summary['remaining_guarded'],
                'json_row_count'=>count($rows),
                'run7_started_at_used'=>$run7_started_at,
                'run7_status'=>$run7_status,
            ],
            'rows'=>$rows,
            'samples'=>array_slice(array_values(array_filter($rows, fn($r) => (string)$r['classification'] !== 'SECOND_PASS_SAFE')), 0, 50),
        ];
    }

    private static function build_row(array $g, ?array $p, array $blacklist, array $current_blacklisted_ids, array $q7, string $run7_status, string $run7_started_at, array $targets_by_id, array $holders_by_cid, array $counts, array $assignments, array $human, array $merge, array $evidence): array
    {
        $sid = (int)$g['customer_id'];
        $cid = self::canonical_cid((string)($g['current_customer_id'] ?? ''));
        $target_id = $p ? (int)($p['source_target_internal_id'] ?? 0) : 0;
        $target_cid = $p ? self::canonical_cid((string)($p['source_person_final_customer_id'] ?? '')) : '';
        $target = $target_id ? ($targets_by_id[$target_id] ?? null) : null;
        $holder = $target_cid !== '' ? ($holders_by_cid[$target_cid] ?? null) : null;
        if (!$target && $holder) $target = $holder;
        $active = (array)($assignments[$sid] ?? ['count'=>0,'ids'=>[],'agents'=>[],'statuses'=>[],'latest'=>'']);
        $ha = (array)($human[$sid] ?? ['state'=>'no','count'=>0,'latest'=>'','types'=>[],'actors'=>[]]);
        $me = (array)($merge[$sid] ?? ['present'=>false,'conflict'=>false,'count'=>0]);
        $runq = $q7[$sid] ?? null;
        $reasons = [];

        $customer_exists = !empty($g['live_customer_id']);
        if (!$customer_exists) $reasons[] = 'GUARD_WITHOUT_CUSTOMER';
        if ($customer_exists && !isset($blacklist[$cid])) $reasons[] = 'GUARD_CUSTOMER_NOT_BLACKLISTED';
        if ($customer_exists && (string)($g['source_customer_id'] ?? '') !== '' && self::canonical_cid((string)$g['source_customer_id']) !== $cid) $reasons[] = 'GUARD_CUSTOMER_ID_MISMATCH';
        if (!isset($current_blacklisted_ids[$sid])) $reasons[] = 'GUARD_WITHOUT_BLACKLISTED_CUSTOMER';
        if (!$p) $reasons[] = 'FROZEN_PLAN_ROW_MISSING';

        $manual = $p && (string)($p['canonical_decision'] ?? '') === 'MANUAL_REVIEW';
        if ($manual) $reasons[] = 'ORIGINAL_MANUAL_REVIEW_PROTECTED';
        if ($p && !$manual && !$runq) $reasons[] = 'RUN7_QUARANTINE_NOT_FOUND';
        $q_reason = (string)($runq['reason'] ?? '');
        if ($q_reason !== '' && !in_array($q_reason, ['ACTIVE_ASSIGNMENT_CONFLICT','TARGET_ROW_MISSING'], true)) $reasons[] = 'UNKNOWN_RUN7_QUARANTINE_REASON';

        $final = $p ? self::canonical_cid((string)($p['final_customer_id'] ?? '')) : '';
        if ($final === '' && !$manual) $reasons[] = 'EXPECTED_FINAL_CUSTOMER_ID_MISSING';
        if ($final !== '' && isset($blacklist[$final])) $reasons[] = 'EXPECTED_FINAL_CUSTOMER_ID_BLACKLISTED';
        if ($final !== '' && self::customer_id_collision($final, $sid)) $reasons[] = 'FINAL_CUSTOMER_ID_COLLISION';

        $mode = $p ? (string)($p['source_target_mode'] ?? '') : '';
        $target_exists = (bool)$target;
        $target_blacklisted = $target_exists && isset($blacklist[self::canonical_cid((string)$target['source_customer_id'])]);
        if ($target_blacklisted) $reasons[] = 'TARGET_CUSTOMER_ID_BLACKLISTED';
        if ($p && $mode === 'CREATE_MISSING_VALID_TARGET') {
            if ($target_exists) $reasons[] = 'MISSING_TARGET_NOW_EXISTS';
        } elseif ($p && !$manual && $target_id && !$target_exists) {
            $reasons[] = 'TARGET_ROW_MISSING';
        } elseif ($p && !$manual && !$target_id && $mode !== '' && $mode !== 'CREATE_MISSING_VALID_TARGET') {
            $reasons[] = 'TARGET_INTERNAL_ID_MISSING';
        }
        if ($target_exists && $target_cid !== '' && self::canonical_cid((string)$target['source_customer_id']) !== $target_cid) $reasons[] = 'TARGET_CUSTOMER_ID_CHANGED';
        if ($target_id && $holder && (int)$holder['id'] !== $target_id) $reasons[] = 'TARGET_CUSTOMER_ID_COLLISION';
        if ($target_exists && $p && !self::same_name((string)($target['display_name'] ?? ''), (string)($p['source_target_name'] ?? ''))) $reasons[] = 'TARGET_IDENTITY_MISMATCH';

        if ((int)$active['count'] > 0) $reasons[] = 'ACTIVE_ASSIGNMENT_PRESENT';
        if (($ha['state'] ?? 'no') === 'yes') $reasons[] = 'HUMAN_ACTIVITY_AFTER_RUN7';
        if (($ha['state'] ?? 'no') === 'unknown') $reasons[] = 'HUMAN_ACTIVITY_ATTRIBUTION_UNKNOWN';
        if (!empty($me['present'])) $reasons[] = 'MANUAL_MERGE_EVIDENCE';
        if (!empty($me['conflict'])) $reasons[] = 'MANUAL_MERGE_CONFLICT';

        $ev = (array)($evidence[$sid] ?? self::empty_evidence());
        $invoice_state = (string)($ev['invoice_evidence_state'] ?? 'UNKNOWN');
        $phone_state = (string)($ev['phone_evidence_state'] ?? 'UNKNOWN');
        $invoice_reasons = (array)($ev['invoice_evidence_reason_codes'] ?? ['INVOICE_EVIDENCE_UNKNOWN']);
        $phone_reasons = (array)($ev['phone_evidence_reason_codes'] ?? ['PHONE_EVIDENCE_UNKNOWN']);
        if ($invoice_state === 'FAIL' || $invoice_state === 'UNKNOWN') $reasons = array_merge($reasons, $invoice_reasons);
        if ($phone_state === 'FAIL' || $phone_state === 'UNKNOWN') $reasons = array_merge($reasons, $phone_reasons);
        $invoice_ok = $invoice_state === 'PASS' || $invoice_state === 'NOT_APPLICABLE';
        $phone_ok = $phone_state === 'PASS' || $phone_state === 'NOT_APPLICABLE';
        $identity_ok = $customer_exists && $p && ($manual || self::same_name((string)($g['current_name'] ?? ''), (string)($p['current_name'] ?? '')));
        if (!$identity_ok) $reasons[] = 'IDENTITY_EVIDENCE_INSUFFICIENT';

        $classification = self::classify($reasons, $manual, $q_reason, $mode, (int)$active['count']);
        $safe = $classification === 'SECOND_PASS_SAFE';
        return [
            'current_internal_id'=>$sid,
            'current_customer_id'=>$cid,
            'current_name'=>(string)($g['current_name'] ?? ''),
            'guarded'=>true,
            'guard_reason'=>(string)($g['reason'] ?? ''),
            'guard_created_at'=>(string)($g['detected_at'] ?? ''),
            'origin_class'=>$manual ? 'original_manual_review' : ($runq ? 'run7_quarantine' : 'inconsistent_state'),
            'original_manual_review'=>$manual,
            'run7_uuid'=>self::SOURCE_RUN_UUID,
            'run7_status'=>$run7_status,
            'run7_quarantine_reason'=>$q_reason,
            'frozen_plan_sha256'=>self::SOURCE_PLAN_SHA256,
            'frozen_canonical_decision'=>$p['canonical_decision'] ?? '',
            'expected_final_customer_id'=>$final,
            'expected_source_person_customer_id'=>$target_cid,
            'expected_target_internal_id'=>$target_id ?: '',
            'expected_target_customer_id'=>$target_cid,
            'source_target_mode'=>$mode,
            'current_target_exists'=>$target_exists,
            'current_target_internal_id'=>$target ? (int)$target['id'] : '',
            'current_target_customer_id'=>$target ? self::canonical_cid((string)$target['source_customer_id']) : '',
            'current_target_name'=>$target ? (string)$target['display_name'] : '',
            'current_target_blacklisted'=>$target_blacklisted,
            'active_assignment_now'=>(int)$active['count'] > 0,
            'active_assignment_count'=>(int)$active['count'],
            'active_assignment_ids'=>$active['ids'],
            'assigned_agent_user_ids'=>$active['agents'],
            'assignment_statuses'=>$active['statuses'],
            'last_assignment_activity_timestamp'=>(string)($active['latest'] ?? ''),
            'activities_count'=>(int)($counts[$sid]['activities'] ?? 0),
            'assignments_count'=>(int)($counts[$sid]['assignments'] ?? 0),
            'interactions_count'=>(int)($counts[$sid]['interactions'] ?? 0),
            'complaints_count'=>(int)($counts[$sid]['complaints'] ?? 0),
            'financial_cases_count'=>(int)($counts[$sid]['financial_cases'] ?? 0),
            'deficiency_cases_count'=>(int)($counts[$sid]['deficiency_cases'] ?? 0),
            'service_cases_count'=>(int)($counts[$sid]['service_cases'] ?? 0),
            'customer_tags_count'=>(int)($counts[$sid]['customer_tags'] ?? 0),
            'human_activity_after_run7'=>(string)($ha['state'] ?? 'no'),
            'human_activity_count'=>(int)($ha['count'] ?? 0),
            'latest_human_activity_at'=>(string)($ha['latest'] ?? ''),
            'human_activity_types'=>array_values((array)($ha['types'] ?? [])),
            'human_actor_ids'=>array_values((array)($ha['actors'] ?? [])),
            'manual_merge_evidence'=>!empty($me['present']),
            'manual_merge_conflict'=>!empty($me['conflict']),
            'invoice_evidence_state'=>$invoice_state,
            'invoice_evidence_consistent'=>$invoice_ok,
            'invoice_evidence_reason_codes'=>array_values($invoice_reasons),
            'current_invoice_count'=>(int)($ev['current_invoice_count'] ?? 0),
            'current_invoice_codes'=>array_values((array)($ev['current_invoice_codes'] ?? [])),
            'conflicting_invoice_count'=>(int)($ev['conflicting_invoice_count'] ?? 0),
            'invoice_evidence_detail'=>(array)($ev['invoice_evidence_detail'] ?? []),
            'phone_evidence_state'=>$phone_state,
            'phone_evidence_consistent'=>$phone_ok,
            'phone_evidence_reason_codes'=>array_values($phone_reasons),
            'current_phone_count'=>(int)($ev['current_phone_count'] ?? 0),
            'current_phone_values'=>array_values((array)($ev['current_phone_values'] ?? [])),
            'conflicting_phone_count'=>(int)($ev['conflicting_phone_count'] ?? 0),
            'phone_evidence_detail'=>(array)($ev['phone_evidence_detail'] ?? []),
            'identity_evidence_consistent'=>$identity_ok,
            'classification'=>$classification,
            'safe_for_second_pass'=>$safe,
            'block_reason_codes'=>array_values(array_unique($safe ? [] : $reasons)),
            'notes'=>self::notes($classification, $reasons, $run7_started_at),
        ];
    }

    private static function classify(array $reasons, bool $manual, string $q_reason, string $mode, int $active_count): string
    {
        $unexpected = ['GUARD_WITHOUT_CUSTOMER','GUARD_CUSTOMER_NOT_BLACKLISTED','GUARD_CUSTOMER_ID_MISMATCH','GUARD_WITHOUT_BLACKLISTED_CUSTOMER','FROZEN_PLAN_ROW_MISSING','RUN7_QUARANTINE_NOT_FOUND','UNKNOWN_RUN7_QUARANTINE_REASON','EXPECTED_FINAL_CUSTOMER_ID_MISSING','EXPECTED_FINAL_CUSTOMER_ID_BLACKLISTED','FINAL_CUSTOMER_ID_COLLISION','TARGET_CUSTOMER_ID_BLACKLISTED','MISSING_TARGET_NOW_EXISTS','TARGET_CUSTOMER_ID_CHANGED','TARGET_CUSTOMER_ID_COLLISION'];
        foreach ($unexpected as $x) if (in_array($x, $reasons, true)) return 'UNEXPECTED_BLOCK';
        $manual_reasons = ['MANUAL_MERGE_EVIDENCE','MANUAL_MERGE_CONFLICT','HUMAN_ACTIVITY_AFTER_RUN7','HUMAN_ACTIVITY_ATTRIBUTION_UNKNOWN','IDENTITY_EVIDENCE_INSUFFICIENT','INVOICE_EVIDENCE_CONFLICT','INVOICE_EVIDENCE_UNKNOWN','INVOICE_OWNER_MISMATCH','INVOICE_MAPPING_MISMATCH','INVOICE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN','PHONE_EVIDENCE_CONFLICT','PHONE_EVIDENCE_UNKNOWN','PHONE_OWNER_CONFLICT','PHONE_MAPPING_MISMATCH','PHONE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN','MANUAL_PHONE_EVIDENCE_CONFLICT'];
        foreach ($manual_reasons as $x) if ($manual || in_array($x, $reasons, true)) return 'MANUAL_REVIEW_REQUIRED';
        if ($q_reason === 'TARGET_ROW_MISSING' || $mode === 'CREATE_MISSING_VALID_TARGET' || in_array('TARGET_ROW_MISSING', $reasons, true)) return 'TARGET_RECONSTRUCTION_REQUIRED';
        if ($active_count > 0) return 'ACTIVE_ASSIGNMENT_STILL_CONFLICTED';
        return $reasons ? 'MANUAL_REVIEW_REQUIRED' : 'SECOND_PASS_SAFE';
    }

    private static function invoice_phone_evidence_by_customer(array $ids, array $plan, array $targets_by_id, array $holders_by_cid, string $cutoff): array
    {
        $out = [];
        foreach ($ids as $id) $out[(int)$id] = self::empty_evidence();
        $invoice_table = Biawin_CRM_DB::table('invoices');
        $phone_table = Biawin_CRM_DB::table('phones');
        $invoice_phone_table = Biawin_CRM_DB::table('invoice_phones');
        $has_invoices = self::table_exists($invoice_table);
        $has_phones = self::table_exists($phone_table);
        $has_invoice_phones = self::table_exists($invoice_phone_table);
        if (!$has_invoices) {
            foreach ($ids as $id) {
                $out[(int)$id]['invoice_evidence_state'] = 'UNKNOWN';
                $out[(int)$id]['invoice_evidence_reason_codes'] = ['INVOICE_EVIDENCE_UNKNOWN'];
            }
        }
        if (!$has_phones) {
            foreach ($ids as $id) {
                $out[(int)$id]['phone_evidence_state'] = 'UNKNOWN';
                $out[(int)$id]['phone_evidence_reason_codes'] = ['PHONE_EVIDENCE_UNKNOWN'];
            }
        }

        $invoices_by_customer = $has_invoices ? self::invoices_by_customer($ids, $cutoff) : [];
        $phones_by_customer = $has_phones ? self::phones_by_customer($ids, $cutoff) : [];
        $invoice_ids = [];
        foreach ($invoices_by_customer as $rows) foreach ($rows as $r) $invoice_ids[(int)$r['id']] = (int)$r['id'];
        $invoice_phones_by_invoice = $has_invoice_phones ? self::invoice_phones_by_invoice(array_values($invoice_ids)) : [];

        $all_phone_values = [];
        foreach ($phones_by_customer as $rows) foreach ($rows as $r) if ((string)$r['normalized_phone'] !== '') $all_phone_values[(string)$r['normalized_phone']] = (string)$r['normalized_phone'];
        foreach ($invoice_phones_by_invoice as $phones) foreach ($phones as $p) if ($p !== '') $all_phone_values[$p] = $p;
        $phone_owners = $has_phones ? self::phone_owners_by_value(array_values($all_phone_values)) : [];
        $invoice_usage_by_phone = ($has_invoices && $has_invoice_phones) ? self::invoice_usage_by_phone(array_values($all_phone_values)) : [];

        foreach ($ids as $sid) {
            $sid = (int)$sid;
            $p = $plan[$sid] ?? null;
            $target_id = self::expected_target_id($p, $targets_by_id, $holders_by_cid);
            $allowed = array_values(array_unique(array_filter([$sid, $target_id])));
            $invoices = (array)($invoices_by_customer[$sid] ?? []);
            $phones = (array)($phones_by_customer[$sid] ?? []);
            $source_phone_values = [];
            foreach ($phones as $phone_row) {
                $phone = (string)($phone_row['normalized_phone'] ?? '');
                if ($phone !== '') $source_phone_values[$phone] = $phone;
            }
            $linked_invoice_usage = [];
            foreach ($source_phone_values as $phone) {
                foreach ((array)($invoice_usage_by_phone[$phone] ?? []) as $usage) $linked_invoice_usage[] = $usage;
            }
            $linked_phone_values = [];
            foreach ($invoices as $inv) {
                foreach ((array)($invoice_phones_by_invoice[(int)$inv['id']] ?? []) as $phone) {
                    if ($phone !== '') $linked_phone_values[$phone] = $phone;
                }
            }
            if ($has_invoices) $out[$sid] = array_merge($out[$sid], self::evaluate_invoice_evidence($sid, $allowed, $invoices, $invoice_phones_by_invoice, $phone_owners, $cutoff, $linked_invoice_usage));
            if ($has_invoices && !$has_invoice_phones && $invoices) {
                $out[$sid]['invoice_evidence_state'] = 'UNKNOWN';
                $out[$sid]['invoice_evidence_reason_codes'] = ['INVOICE_EVIDENCE_UNKNOWN'];
                $out[$sid]['invoice_evidence_detail']['invoice_phones_table_missing'] = true;
            }
            if ($has_phones) $out[$sid] = array_merge($out[$sid], self::evaluate_phone_evidence($sid, $allowed, $phones, $invoice_usage_by_phone, $cutoff, array_values($linked_phone_values), $phone_owners));
            if ($has_phones && !$has_invoice_phones && $phones) {
                $out[$sid]['phone_evidence_state'] = 'UNKNOWN';
                $out[$sid]['phone_evidence_reason_codes'] = ['PHONE_EVIDENCE_UNKNOWN'];
                $out[$sid]['phone_evidence_detail']['invoice_phones_table_missing'] = true;
            }
        }
        return $out;
    }

    private static function empty_evidence(): array
    {
        return [
            'invoice_evidence_state'=>'UNKNOWN',
            'invoice_evidence_reason_codes'=>['INVOICE_EVIDENCE_UNKNOWN'],
            'current_invoice_count'=>0,
            'current_invoice_codes'=>[],
            'conflicting_invoice_count'=>0,
            'invoice_evidence_detail'=>[],
            'phone_evidence_state'=>'UNKNOWN',
            'phone_evidence_reason_codes'=>['PHONE_EVIDENCE_UNKNOWN'],
            'current_phone_count'=>0,
            'current_phone_values'=>[],
            'conflicting_phone_count'=>0,
            'phone_evidence_detail'=>[],
        ];
    }

    private static function evaluate_invoice_evidence(int $sid, array $allowed_customer_ids, array $invoices, array $invoice_phones_by_invoice, array $phone_owners, string $cutoff, array $linked_invoice_usage = []): array
    {
        $reasons = [];
        $codes = [];
        $conflicts = [];
        $changed_unknown = 0;
        foreach ($invoices as $inv) {
            $iid = (int)$inv['id'];
            $codes[] = (string)$inv['invoice_code'];
            if ((string)($inv['updated_at'] ?? '') > $cutoff) {
                $changed_unknown++;
                $reasons[] = 'INVOICE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN';
            }
            foreach ((array)($invoice_phones_by_invoice[$iid] ?? []) as $phone) {
                $owners = (array)($phone_owners[$phone] ?? []);
                if (!$owners) {
                    $changed_unknown++;
                    $reasons[] = 'INVOICE_EVIDENCE_UNKNOWN';
                }
                foreach ($owners as $owner) {
                    $owner_id = (int)($owner['customer_id'] ?? 0);
                    if ($owner_id <= 0) {
                        $changed_unknown++;
                        $reasons[] = 'INVOICE_EVIDENCE_UNKNOWN';
                        continue;
                    }
                    if (in_array($owner_id, $allowed_customer_ids, true)) continue;
                    $reasons[] = 'INVOICE_OWNER_MISMATCH';
                    $reasons[] = 'INVOICE_EVIDENCE_CONFLICT';
                    $conflicts[] = ['invoice_id'=>$iid, 'invoice_code'=>(string)$inv['invoice_code'], 'normalized_phone'=>$phone, 'phone_owner_customer_id'=>$owner_id];
                }
            }
        }
        foreach ($linked_invoice_usage as $usage) {
            $owner = (int)($usage['customer_id'] ?? 0);
            if ($owner <= 0) {
                $changed_unknown++;
                $reasons[] = 'INVOICE_EVIDENCE_UNKNOWN';
                continue;
            }
            if (in_array($owner, $allowed_customer_ids, true)) continue;
            $reasons[] = 'INVOICE_MAPPING_MISMATCH';
            $reasons[] = 'INVOICE_EVIDENCE_CONFLICT';
            $conflicts[] = ['invoice_id'=>(int)($usage['invoice_id'] ?? 0), 'invoice_code'=>(string)($usage['invoice_code'] ?? ''), 'normalized_phone'=>(string)($usage['normalized_phone'] ?? ''), 'invoice_owner_customer_id'=>$owner, 'source'=>'linked_phone_invoice_usage'];
        }
        $codes = self::compact_strings($codes, 40);
        if (!$invoices && !$linked_invoice_usage) $state = 'NOT_APPLICABLE';
        elseif ($conflicts) $state = 'FAIL';
        elseif ($changed_unknown > 0) $state = 'UNKNOWN';
        else $state = 'PASS';
        if ($state === 'UNKNOWN' && !$reasons) $reasons[] = 'INVOICE_EVIDENCE_UNKNOWN';
        if ($state === 'PASS' || $state === 'NOT_APPLICABLE') $reasons = [];
        return [
            'invoice_evidence_state'=>$state,
            'invoice_evidence_reason_codes'=>array_values(array_unique($reasons)),
            'current_invoice_count'=>count($invoices),
            'current_invoice_codes'=>$codes,
            'conflicting_invoice_count'=>count($conflicts),
            'invoice_evidence_detail'=>['source_customer_id'=>$sid, 'allowed_customer_ids'=>$allowed_customer_ids, 'linked_invoice_usage_count'=>count($linked_invoice_usage), 'changed_after_run7_without_actor'=>$changed_unknown, 'conflicts'=>array_slice($conflicts, 0, 50)],
        ];
    }

    private static function evaluate_phone_evidence(int $sid, array $allowed_customer_ids, array $phones, array $invoice_usage_by_phone, string $cutoff, array $linked_phone_values = [], array $phone_owners = []): array
    {
        $reasons = [];
        $values = [];
        $conflicts = [];
        $changed_unknown = 0;
        foreach ($phones as $phone_row) {
            $phone = (string)($phone_row['normalized_phone'] ?? '');
            if ($phone === '') continue;
            $values[] = $phone . ((int)($phone_row['is_primary'] ?? 0) ? ':primary' : '');
            $manual = in_array((string)($phone_row['source'] ?? ''), ['agent','manual','manual_invoice_bundle','manual_invoice_phone'], true);
            if ($manual && (string)($phone_row['created_at'] ?? '') > $cutoff) {
                $changed_unknown++;
                $reasons[] = 'PHONE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN';
            }
            foreach ((array)($invoice_usage_by_phone[$phone] ?? []) as $usage) {
                $owner = (int)($usage['customer_id'] ?? 0);
                if ($owner && !in_array($owner, $allowed_customer_ids, true)) {
                    $reasons[] = 'PHONE_OWNER_CONFLICT';
                    $reasons[] = 'PHONE_EVIDENCE_CONFLICT';
                    if ($manual) $reasons[] = 'MANUAL_PHONE_EVIDENCE_CONFLICT';
                    $conflicts[] = ['normalized_phone'=>$phone, 'phone_source'=>(string)($phone_row['source'] ?? ''), 'invoice_id'=>(int)$usage['invoice_id'], 'invoice_code'=>(string)$usage['invoice_code'], 'invoice_owner_customer_id'=>$owner];
                }
            }
        }
        foreach (array_values(array_unique(array_filter(array_map('strval', $linked_phone_values)))) as $phone) {
            $values[] = $phone . ':linked_invoice_phone';
            $owners = (array)($phone_owners[$phone] ?? []);
            if (!$owners) {
                $changed_unknown++;
                $reasons[] = 'PHONE_EVIDENCE_UNKNOWN';
                continue;
            }
            foreach ($owners as $owner) {
                $owner_id = (int)($owner['customer_id'] ?? 0);
                if ($owner_id <= 0) {
                    $changed_unknown++;
                    $reasons[] = 'PHONE_EVIDENCE_UNKNOWN';
                    continue;
                }
                if (in_array($owner_id, $allowed_customer_ids, true)) continue;
                $reasons[] = 'PHONE_MAPPING_MISMATCH';
                $reasons[] = 'PHONE_EVIDENCE_CONFLICT';
                $conflicts[] = ['normalized_phone'=>$phone, 'phone_source'=>'linked_invoice_phone', 'phone_owner_customer_id'=>$owner_id];
            }
        }
        $values = self::compact_strings($values, 40);
        if (!$phones && !$linked_phone_values) $state = 'NOT_APPLICABLE';
        elseif ($conflicts) $state = 'FAIL';
        elseif ($changed_unknown > 0) $state = 'UNKNOWN';
        else $state = 'PASS';
        if ($state === 'UNKNOWN' && !$reasons) $reasons[] = 'PHONE_EVIDENCE_UNKNOWN';
        if ($state === 'PASS' || $state === 'NOT_APPLICABLE') $reasons = [];
        return [
            'phone_evidence_state'=>$state,
            'phone_evidence_reason_codes'=>array_values(array_unique($reasons)),
            'current_phone_count'=>count($phones),
            'current_phone_values'=>$values,
            'conflicting_phone_count'=>count($conflicts),
            'phone_evidence_detail'=>['source_customer_id'=>$sid, 'allowed_customer_ids'=>$allowed_customer_ids, 'linked_phone_value_count'=>count($linked_phone_values), 'changed_after_run7_without_actor'=>$changed_unknown, 'conflicts'=>array_slice($conflicts, 0, 50)],
        ];
    }

    private static function current_blacklisted_customers(array $blacklist): array
    {
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $rows = [];
        foreach (array_chunk(array_keys($blacklist), 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            foreach ((array)$wpdb->get_results($wpdb->prepare("SELECT id,source_customer_id,display_name FROM $customers WHERE source_customer_id IN ($ph)", ...$chunk), ARRAY_A) as $r) $rows[] = $r;
        }
        return $rows;
    }

    private static function invoices_by_customer(array $ids, string $cutoff): array
    {
        global $wpdb;
        $out = [];
        $table = Biawin_CRM_DB::table('invoices');
        if (!$ids || !self::table_exists($table)) return $out;
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT id,customer_id,invoice_code,invoice_name,updated_at,created_at FROM $table WHERE customer_id IN ($ph) ORDER BY customer_id,id", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) $out[(int)$r['customer_id']][] = $r;
        }
        return $out;
    }

    private static function phones_by_customer(array $ids, string $cutoff): array
    {
        global $wpdb;
        $out = [];
        $table = Biawin_CRM_DB::table('phones');
        if (!$ids || !self::table_exists($table)) return $out;
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT id,customer_id,normalized_phone,phone_status,relation_type,source,is_primary,auto_match_blocked,verified_at,created_at FROM $table WHERE customer_id IN ($ph) ORDER BY customer_id,is_primary DESC,id", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) $out[(int)$r['customer_id']][] = $r;
        }
        return $out;
    }

    private static function invoice_phones_by_invoice(array $invoice_ids): array
    {
        global $wpdb;
        $out = [];
        $table = Biawin_CRM_DB::table('invoice_phones');
        if (!$invoice_ids || !self::table_exists($table)) return $out;
        foreach (array_chunk($invoice_ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT invoice_id,normalized_phone FROM $table WHERE invoice_id IN ($ph) AND normalized_phone<>'' ORDER BY invoice_id,phone_position,id", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) $out[(int)$r['invoice_id']][] = (string)$r['normalized_phone'];
        }
        return $out;
    }

    private static function phone_owners_by_value(array $phones): array
    {
        global $wpdb;
        $out = [];
        $phones = array_values(array_unique(array_filter(array_map('strval', $phones))));
        $table = Biawin_CRM_DB::table('phones');
        if (!$phones || !self::table_exists($table)) return $out;
        foreach (array_chunk($phones, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            $sql = $wpdb->prepare("SELECT normalized_phone,customer_id,source,is_primary,phone_status,created_at FROM $table WHERE normalized_phone IN ($ph) ORDER BY normalized_phone,id", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) {
                $key = (string)$r['normalized_phone'];
                if ($key === '') continue;
                if (!isset($out[$key])) $out[$key] = [];
                $owner_id = (int)($r['customer_id'] ?? 0);
                if (!isset($out[$key][$owner_id])) $out[$key][$owner_id] = $r;
            }
        }
        foreach ($out as &$owners) {
            ksort($owners, SORT_NUMERIC);
            $owners = array_values($owners);
        }
        unset($owners);
        return $out;
    }

    private static function invoice_usage_by_phone(array $phones): array
    {
        global $wpdb;
        $out = [];
        $phones = array_values(array_unique(array_filter(array_map('strval', $phones))));
        $ip = Biawin_CRM_DB::table('invoice_phones');
        $inv = Biawin_CRM_DB::table('invoices');
        if (!$phones || !self::table_exists($ip) || !self::table_exists($inv)) return $out;
        foreach (array_chunk($phones, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            $sql = $wpdb->prepare("SELECT ip.normalized_phone,i.id invoice_id,i.invoice_code,i.customer_id FROM $ip ip INNER JOIN $inv i ON i.id=ip.invoice_id WHERE ip.normalized_phone IN ($ph) ORDER BY ip.normalized_phone,i.id", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) $out[(string)$r['normalized_phone']][] = $r;
        }
        return $out;
    }

    private static function expected_target_id(?array $p, array $targets_by_id, array $holders_by_cid): int
    {
        if (!$p) return 0;
        $target_id = (int)($p['source_target_internal_id'] ?? 0);
        if ($target_id) return $target_id;
        $cid = self::canonical_cid((string)($p['source_person_final_customer_id'] ?? ''));
        $holder = $cid !== '' ? ($holders_by_cid[$cid] ?? null) : null;
        return $holder ? (int)$holder['id'] : 0;
    }

    private static function compact_strings(array $values, int $limit): array
    {
        $values = array_values(array_unique(array_filter(array_map('strval', $values))));
        sort($values, SORT_STRING);
        if (count($values) <= $limit) return $values;
        $head = array_slice($values, 0, $limit);
        $head[] = '...+' . (count($values) - $limit) . '_more';
        return $head;
    }

    private static function domain_counts_by_customer(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) $out[(int)$id] = [];
        foreach (['activities','assignments','interactions','complaints','financial_cases','deficiency_cases','service_cases','customer_tags','invoices','phones'] as $logical) {
            $table = Biawin_CRM_DB::table($logical);
            if (!self::table_exists($table) || !self::has_col($table, 'customer_id')) continue;
            foreach (self::group_counts($table, 'customer_id', $ids) as $id=>$count) $out[(int)$id][$logical] = $count;
        }
        return $out;
    }

    private static function active_assignments_by_customer(array $ids): array
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('assignments');
        $out = [];
        if (!$ids || !self::table_exists($table)) return $out;
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT id,customer_id,agent_user_id,status,assigned_at,completed_at FROM $table WHERE customer_id IN ($ph) AND status='assigned' ORDER BY customer_id,id", ...$chunk);
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) {
                $id = (int)$r['customer_id'];
                if (!isset($out[$id])) $out[$id] = ['count'=>0,'ids'=>[],'agents'=>[],'statuses'=>[],'latest'=>''];
                $out[$id]['count']++;
                $out[$id]['ids'][] = (int)$r['id'];
                $out[$id]['agents'][] = (int)$r['agent_user_id'];
                $out[$id]['statuses'][] = (string)$r['status'];
                $at = (string)($r['completed_at'] ?: $r['assigned_at']);
                if ($at > (string)$out[$id]['latest']) $out[$id]['latest'] = $at;
            }
        }
        return $out;
    }

    private static function human_activity_by_customer(array $ids, string $cutoff): array
    {
        $out = [];
        foreach ($ids as $id) $out[(int)$id] = ['state'=>'no','count'=>0,'latest'=>'','types'=>[],'actors'=>[]];
        $specs = [
            ['activities','actor_user_id','created_at','activity_type'],
            ['interactions','agent_user_id','created_at','type'],
            ['assignments','assigned_by','assigned_at','assignment'],
            ['complaints','owner_user_id','created_at','complaint'],
            ['financial_cases','created_by','created_at','financial_case'],
            ['deficiency_cases','created_by','created_at','deficiency_case'],
            ['service_cases','created_by','created_at','service_case'],
            ['customer_tags','created_by','created_at','customer_tag'],
        ];
        foreach ($specs as $sp) self::collect_human_events($out, $ids, $cutoff, $sp[0], $sp[1], $sp[2], $sp[3]);
        self::collect_audit_events($out, $ids, $cutoff);
        foreach ($out as &$row) {
            $row['types'] = array_keys($row['types']);
            $row['actors'] = array_keys($row['actors']);
            if ($row['count'] > 0 && $row['state'] !== 'unknown') $row['state'] = 'yes';
        }
        unset($row);
        return $out;
    }

    private static function collect_human_events(array &$out, array $ids, string $cutoff, string $logical, string $actor_col, string $time_col, string $type_col): void
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table($logical);
        if (!$ids || !self::table_exists($table) || !self::has_col($table, 'customer_id') || !self::has_col($table, $time_col)) return;
        $has_actor = self::has_col($table, $actor_col);
        $type_sql = self::has_col($table, $type_col) ? "`$type_col`" : "'$logical'";
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT customer_id," . ($has_actor ? "`$actor_col`" : "NULL") . " actor_id, `$time_col` event_time, $type_sql event_type FROM $table WHERE customer_id IN ($ph) AND `$time_col`>%s ORDER BY customer_id,`$time_col`", ...array_merge($chunk, [$cutoff]));
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) {
                $actor = (int)($r['actor_id'] ?? 0);
                $etype = (string)($r['event_type'] ?? $logical);
                if (self::is_migration_event($etype)) continue;
                $id = (int)$r['customer_id'];
                if ($actor <= 0) { $out[$id]['state'] = 'unknown'; continue; }
                $out[$id]['count']++;
                $out[$id]['types'][$logical . ':' . $etype] = true;
                $out[$id]['actors'][$actor] = true;
                if ((string)$r['event_time'] > (string)$out[$id]['latest']) $out[$id]['latest'] = (string)$r['event_time'];
            }
        }
    }

    private static function collect_audit_events(array &$out, array $ids, string $cutoff): void
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('audit_log');
        if (!$ids || !self::table_exists($table)) return;
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = $wpdb->prepare("SELECT object_id customer_id,actor_user_id actor_id,created_at event_time,action event_type FROM $table WHERE object_type='customer' AND object_id IN ($ph) AND created_at>%s ORDER BY object_id,created_at", ...array_merge($chunk, [$cutoff]));
            foreach ((array)$wpdb->get_results($sql, ARRAY_A) as $r) {
                $etype = (string)($r['event_type'] ?? 'audit');
                if (self::is_migration_event($etype)) continue;
                $id = (int)$r['customer_id'];
                $actor = (int)($r['actor_id'] ?? 0);
                if ($actor <= 0) { $out[$id]['state'] = 'unknown'; continue; }
                $out[$id]['count']++;
                $out[$id]['types']['audit_log:' . $etype] = true;
                $out[$id]['actors'][$actor] = true;
                if ((string)$r['event_time'] > (string)$out[$id]['latest']) $out[$id]['latest'] = (string)$r['event_time'];
            }
        }
    }

    private static function manual_merge_by_customer(array $ids, string $cutoff): array
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('merge_events');
        $out = [];
        foreach ($ids as $id) $out[(int)$id] = ['present'=>false,'conflict'=>false,'count'=>0];
        if (!$ids || !self::table_exists($table)) return $out;
        $guard = array_fill_keys(array_map('intval', $ids), true);
        $rows = (array)$wpdb->get_results("SELECT * FROM $table ORDER BY id ASC", ARRAY_A);
        foreach ($rows as $r) {
            [$related, $malformed] = self::merge_related_ids_with_status($r);
            $all = array_values(array_unique(array_filter(array_merge([(int)($r['primary_customer_id'] ?? 0)], $related))));
            foreach ($all as $id) {
                if (empty($guard[$id])) continue;
                $out[$id]['present'] = true;
                $out[$id]['count']++;
                if ((int)($r['performed_by'] ?? 0) > 0 || (string)($r['created_at'] ?? '') > $cutoff || $malformed) $out[$id]['conflict'] = true;
            }
        }
        return $out;
    }

    private static function summarize(array $rows, array $guard_rows, array $current_blacklisted, array $guard_ids): array
    {
        $s = ['remaining_guarded'=>count($guard_rows), 'current_blacklisted'=>count($current_blacklisted), 'run7_quarantine'=>0, 'original_manual_review'=>0, 'SECOND_PASS_SAFE'=>0, 'ACTIVE_ASSIGNMENT_STILL_CONFLICTED'=>0, 'TARGET_RECONSTRUCTION_REQUIRED'=>0, 'MANUAL_REVIEW_REQUIRED'=>0, 'UNEXPECTED_BLOCK'=>0, 'blacklisted_without_guard'=>0, 'guard_without_blacklisted_customer'=>0, 'guard_customer_id_mismatch'=>0, 'human_activity_after_run7'=>0, 'manual_merge_evidence'=>0, 'target_missing'=>0, 'unexpected_quarantine_reasons'=>0, 'normal_access_to_quarantine'=>0, 'invoice_evidence_pass'=>0, 'invoice_evidence_fail'=>0, 'invoice_evidence_unknown'=>0, 'invoice_evidence_na'=>0, 'phone_evidence_pass'=>0, 'phone_evidence_fail'=>0, 'phone_evidence_unknown'=>0, 'phone_evidence_na'=>0, 'prevented_second_pass_by_invoice_phone_evidence'=>0];
        foreach ($current_blacklisted as $c) if (empty($guard_ids[(int)$c['id']])) $s['blacklisted_without_guard']++;
        foreach ($rows as $r) {
            $class = (string)$r['classification'];
            if (isset($s[$class])) $s[$class]++;
            if ($r['origin_class'] === 'run7_quarantine') $s['run7_quarantine']++;
            if (!empty($r['original_manual_review'])) $s['original_manual_review']++;
            if (in_array('GUARD_WITHOUT_BLACKLISTED_CUSTOMER', (array)$r['block_reason_codes'], true)) $s['guard_without_blacklisted_customer']++;
            if (in_array('GUARD_CUSTOMER_ID_MISMATCH', (array)$r['block_reason_codes'], true)) $s['guard_customer_id_mismatch']++;
            if ($r['human_activity_after_run7'] === 'yes') $s['human_activity_after_run7']++;
            if (!empty($r['manual_merge_evidence'])) $s['manual_merge_evidence']++;
            if (in_array('TARGET_ROW_MISSING', (array)$r['block_reason_codes'], true)) $s['target_missing']++;
            if (in_array('UNKNOWN_RUN7_QUARANTINE_REASON', (array)$r['block_reason_codes'], true)) $s['unexpected_quarantine_reasons']++;
            $invoice_state = strtolower((string)($r['invoice_evidence_state'] ?? 'unknown'));
            $phone_state = strtolower((string)($r['phone_evidence_state'] ?? 'unknown'));
            if ($invoice_state === 'not_applicable') $invoice_state = 'na';
            if ($phone_state === 'not_applicable') $phone_state = 'na';
            if (isset($s['invoice_evidence_' . $invoice_state])) $s['invoice_evidence_' . $invoice_state]++;
            if (isset($s['phone_evidence_' . $phone_state])) $s['phone_evidence_' . $phone_state]++;
            $evidence_reasons = array_merge((array)($r['invoice_evidence_reason_codes'] ?? []), (array)($r['phone_evidence_reason_codes'] ?? []));
            if ((string)$r['classification'] !== 'SECOND_PASS_SAFE' && $evidence_reasons) $s['prevented_second_pass_by_invoice_phone_evidence']++;
        }
        return $s;
    }

    private static function report_hash(array $rows): string
    {
        $fields = ['current_internal_id','current_customer_id','guard_reason','origin_class','run7_quarantine_reason','frozen_canonical_decision','expected_final_customer_id','expected_source_person_customer_id','expected_target_internal_id','expected_target_customer_id','source_target_mode','current_target_exists','current_target_internal_id','current_target_customer_id','current_target_blacklisted','active_assignment_now','active_assignment_count','human_activity_after_run7','manual_merge_evidence','manual_merge_conflict','invoice_evidence_state','invoice_evidence_consistent','invoice_evidence_reason_codes','phone_evidence_state','phone_evidence_consistent','phone_evidence_reason_codes','identity_evidence_consistent','classification','safe_for_second_pass','block_reason_codes'];
        $lines = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($fields as $f) $parts[] = $f . '=' . self::canonical_scalar($row[$f] ?? '');
            $lines[] = implode("\t", $parts);
        }
        return hash('sha256', implode("\n", $lines));
    }

    private static function load_plan_index(): array
    {
        if (is_array(self::$plan_index)) return self::$plan_index;
        $path = BIAWIN_CRM_DIR . self::PLAN_FILE;
        if (!is_readable($path)) return self::$plan_index = [];
        $d = json_decode((string)file_get_contents($path), true);
        if (!is_array($d) || ($d['sha256'] ?? '') !== self::SOURCE_PLAN_SHA256 || empty($d['rows']) || !is_array($d['rows'])) return self::$plan_index = [];
        $idx = [];
        foreach ($d['rows'] as $r) $idx[(int)($r['current_internal_id'] ?? 0)] = $r;
        return self::$plan_index = $idx;
    }

    private static function load_blacklist(): array
    {
        if (is_array(self::$blacklist)) return self::$blacklist;
        $path = BIAWIN_CRM_DIR . self::BLACKLIST_FILE;
        $set = [];
        if (is_readable($path)) foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $x) {
            $cid = self::canonical_cid((string)$x);
            if ($cid !== '') $set[$cid] = true;
        }
        return self::$blacklist = $set;
    }

    private static function source_run(): ?array
    {
        global $wpdb;
        $t = $wpdb->prefix . 'bcrm_reconstruction_runs';
        if (!self::table_exists($t)) return null;
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE run_uuid=%s ORDER BY id DESC LIMIT 1", self::SOURCE_RUN_UUID), ARRAY_A);
        if (!$r) $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE mode=%s AND manifest_hash=%s ORDER BY id DESC LIMIT 1", self::RUN_MODE, self::SOURCE_PLAN_SHA256), ARRAY_A);
        return $r ?: null;
    }

    private static function run_quarantine_index(array $state): array
    {
        $idx = [];
        foreach ((array)($state['quarantine_details'] ?? []) as $q) {
            $sid = (int)($q['source'] ?? $q['current_internal_id'] ?? 0);
            if ($sid) $idx[$sid] = $q;
        }
        return $idx;
    }

    private static function customers_by_ids(array $ids): array
    {
        global $wpdb;
        $out = [];
        if (!$ids) return $out;
        $t = Biawin_CRM_DB::table('customers');
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            foreach ((array)$wpdb->get_results($wpdb->prepare("SELECT id,source_customer_id,display_name,normalized_name,updated_at FROM $t WHERE id IN ($ph)", ...$chunk), ARRAY_A) as $r) $out[(int)$r['id']] = $r;
        }
        return $out;
    }

    private static function customers_by_cids(array $cids): array
    {
        global $wpdb;
        $out = [];
        if (!$cids) return $out;
        $t = Biawin_CRM_DB::table('customers');
        foreach (array_chunk($cids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            foreach ((array)$wpdb->get_results($wpdb->prepare("SELECT id,source_customer_id,display_name,normalized_name,updated_at FROM $t WHERE source_customer_id IN ($ph)", ...$chunk), ARRAY_A) as $r) $out[self::canonical_cid((string)$r['source_customer_id'])] = $r;
        }
        return $out;
    }

    private static function group_counts(string $table, string $column, array $ids): array
    {
        global $wpdb;
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            if (!$chunk) continue;
            $ph = implode(',', array_fill(0, count($chunk), '%d'));
            foreach ((array)$wpdb->get_results($wpdb->prepare("SELECT `$column` cid, COUNT(*) cnt FROM $table WHERE `$column` IN ($ph) GROUP BY `$column`", ...$chunk), ARRAY_A) as $r) $out[(int)$r['cid']] = (int)$r['cnt'];
        }
        return $out;
    }

    private static function customer_id_collision(string $cid, int $except_id): bool
    {
        global $wpdb;
        $t = Biawin_CRM_DB::table('customers');
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM $t WHERE source_customer_id=%s AND id<>%d LIMIT 1", $cid, $except_id));
    }

    private static function merge_related_ids(array $r): array
    {
        return self::merge_related_ids_with_status($r)[0];
    }

    private static function merge_related_ids_with_status(array $r): array
    {
        $ids = [];
        $malformed = false;
        $raw = (string)($r['merged_customer_ids'] ?? '');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            array_walk_recursive($decoded, function($v) use (&$ids) { if (is_numeric($v)) $ids[] = (int)$v; });
        } elseif ($raw !== '') {
            foreach (preg_split('/[^0-9]+/', $raw) as $n) if ($n !== '') $ids[] = (int)$n;
            if (!$ids) $malformed = true;
        }
        return [array_values(array_unique($ids)), $malformed];
    }

    private static function csv_headers(): array
    {
        return ['current_internal_id','current_customer_id','current_name','guarded','guard_reason','guard_created_at','origin_class','original_manual_review','run7_uuid','run7_status','run7_quarantine_reason','frozen_plan_sha256','frozen_canonical_decision','expected_final_customer_id','expected_source_person_customer_id','expected_target_internal_id','expected_target_customer_id','source_target_mode','current_target_exists','current_target_internal_id','current_target_customer_id','current_target_name','current_target_blacklisted','active_assignment_now','active_assignment_count','active_assignment_ids','assigned_agent_user_ids','assignment_statuses','last_assignment_activity_timestamp','activities_count','assignments_count','interactions_count','complaints_count','financial_cases_count','deficiency_cases_count','service_cases_count','customer_tags_count','human_activity_after_run7','human_activity_count','latest_human_activity_at','human_activity_types','human_actor_ids','manual_merge_evidence','manual_merge_conflict','invoice_evidence_state','invoice_evidence_consistent','invoice_evidence_reason_codes','current_invoice_count','current_invoice_codes','conflicting_invoice_count','phone_evidence_state','phone_evidence_consistent','phone_evidence_reason_codes','current_phone_count','current_phone_values','conflicting_phone_count','identity_evidence_consistent','classification','safe_for_second_pass','block_reason_codes','notes'];
    }

    private static function summary_labels(): array
    {
        return ['remaining_guarded'=>'Remaining Guarded','current_blacklisted'=>'Current Blacklisted','run7_quarantine'=>'Run #7 Quarantine','original_manual_review'=>'Original Manual Review','SECOND_PASS_SAFE'=>'SECOND_PASS_SAFE','ACTIVE_ASSIGNMENT_STILL_CONFLICTED'=>'ACTIVE_ASSIGNMENT_STILL_CONFLICTED','TARGET_RECONSTRUCTION_REQUIRED'=>'TARGET_RECONSTRUCTION_REQUIRED','MANUAL_REVIEW_REQUIRED'=>'MANUAL_REVIEW_REQUIRED','UNEXPECTED_BLOCK'=>'UNEXPECTED_BLOCK','blacklisted_without_guard'=>'Blacklisted Without Guard','guard_without_blacklisted_customer'=>'Guard Without Blacklisted Customer','guard_customer_id_mismatch'=>'Guard CustomerID Mismatch','human_activity_after_run7'=>'Human Activity After Run #7','manual_merge_evidence'=>'Manual Merge Evidence','target_missing'=>'Target Missing','unexpected_quarantine_reasons'=>'Unexpected Quarantine Reasons','invoice_evidence_pass'=>'Invoice Evidence PASS','invoice_evidence_fail'=>'Invoice Evidence FAIL','invoice_evidence_unknown'=>'Invoice Evidence UNKNOWN','invoice_evidence_na'=>'Invoice Evidence N/A','phone_evidence_pass'=>'Phone Evidence PASS','phone_evidence_fail'=>'Phone Evidence FAIL','phone_evidence_unknown'=>'Phone Evidence UNKNOWN','phone_evidence_na'=>'Phone Evidence N/A','prevented_second_pass_by_invoice_phone_evidence'=>'Prevented SECOND_PASS_SAFE by Invoice/Phone Evidence'];
    }

    private static function classification_sum(array $s): int
    {
        return (int)$s['SECOND_PASS_SAFE'] + (int)$s['ACTIVE_ASSIGNMENT_STILL_CONFLICTED'] + (int)$s['TARGET_RECONSTRUCTION_REQUIRED'] + (int)$s['MANUAL_REVIEW_REQUIRED'] + (int)$s['UNEXPECTED_BLOCK'];
    }

    private static function canonical_scalar($v): string
    {
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_array($v)) { $v = array_map('strval', $v); sort($v, SORT_STRING); return implode('|', $v); }
        return trim((string)$v);
    }

    private static function csv_value($v): string
    {
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_array($v)) $v = implode('|', array_map('strval', $v));
        $v = (string)$v;
        return self::neutralize_csv_formula($v);
    }

    private static function neutralize_csv_formula(string $value): string
    {
        if ($value === '') return $value;
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'" . $value : $value;
    }

    private static function notes(string $classification, array $reasons, string $cutoff): string
    {
        if ($classification === 'SECOND_PASS_SAFE') return 'All read-only v0.8.54.2 safety checks passed against live Guard scope.';
        return 'Fail-closed after live read-only checks using Run #7 cutoff ' . $cutoff . ': ' . implode(',', array_unique($reasons));
    }

    private static function is_migration_event(string $event): bool
    {
        return (bool)preg_match('/(v0853|v0854|blacklist_final|reconstruction|migration|guard|quarantine|rekey|customers_rekey|customers_retire|customers_create)/i', $event);
    }

    private static function same_name(string $a, string $b): bool
    {
        $a = preg_replace('/\s+/u', ' ', trim(str_replace(['ي','ك'], ['ی','ک'], $a)));
        $b = preg_replace('/\s+/u', ' ', trim(str_replace(['ي','ك'], ['ی','ک'], $b)));
        return $a === '' || $b === '' || $a === $b;
    }

    private static function canonical_cid(string $id): string
    {
        $id = strtoupper(trim($id));
        if ($id === '') return '';
        if (preg_match('/^C(\d{1,6})$/', $id, $m)) return sprintf('C%06d', (int)$m[1]);
        if (preg_match('/^(\d{1,6})$/', $id, $m)) return sprintf('C%06d', (int)$m[1]);
        return $id;
    }

    private static function decode_json(string $json): array
    {
        $x = json_decode($json, true);
        return is_array($x) ? $x : [];
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function has_col(string $table, string $col): bool
    {
        if (!isset(self::$cols[$table])) {
            global $wpdb;
            self::$cols[$table] = [];
            foreach ((array)$wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A) as $r) self::$cols[$table][(string)$r['Field']] = true;
        }
        return !empty(self::$cols[$table][$col]);
    }

    private static function authorize(string $nonce): void
    {
        if ((!current_user_can('bcrm_reports') && !current_user_can('bcrm_manage_cases')) || !check_admin_referer($nonce)) wp_die('دسترسی غیرمجاز است.');
    }

    private static function redirect(): void
    {
        wp_safe_redirect(add_query_arg('page', 'biawin-crm-historical-audit', admin_url('admin.php')));
        exit;
    }
}
