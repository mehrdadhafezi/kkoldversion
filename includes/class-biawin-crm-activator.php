<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Activator
{
    /** Marker bumped whenever role/cap definitions change; drives the admin_init re-grant. */
    const CAPS_MARKER_OPTION = 'biawin_crm_caps_marker';
    const CAPS_MARKER_VERSION = 7; // v3 = bcrm_refund_register; v4 = after-sales foundation; v5/v6 used by prior releases; v7 = finance-return follow-up compatibility sync
    /**
     * v0.8.63.0 — provisioning ledger: every role the plugin ever created and every capability it
     * ever granted, per role. A capability is granted ONLY if it is not in the ledger (never granted
     * before) and the role does not already carry it (granted or explicitly denied). So a capability
     * an admin removed stays removed, a role an admin deleted stays deleted, and manual additions
     * are never touched. Nothing is ever removed by the plugin.
     */
    const CAPS_LEDGER_OPTION = 'biawin_crm_caps_ledger';
    const FINANCE_RETURN_FOLLOWUP_CAP_OPTION = 'biawin_crm_finance_return_followup_cap_v1';
    /** Seed ledgers: stable codes the plugin already inserted once — never re-inserted after an admin deletes them. */
    const SERVICE_SEED_LEDGER_OPTION = 'biawin_crm_service_seed_ledger';
    const UNIT_SEED_LEDGER_OPTION = 'biawin_crm_unit_seed_ledger';
    const CATALOG_SEED_LEDGER_OPTION = 'biawin_crm_catalog_seed_ledger'; // v0.8.64.0
    /** Sequential, idempotent, non-destructive migrations: version => callable name suffix. */
    const MIGRATIONS = [
        '0.8.62.0' => 'migrate_0862_0',
        '0.8.62.2' => 'migrate_0862_2',
        '0.8.62.11' => 'migrate_0862_11',
        '0.8.63.0' => 'migrate_0863_0',
        '0.8.64.0' => 'migrate_0864_0',
    ];

    /**
     * v0.8.62.3: version-safe upgrade on EVERY load.
     * - Capabilities re-synced whenever the caps marker changes (fixes existing installs
     *   whose administrator role predates bcrm_refund_register — no reinstall needed).
     * - DB migrations run sequentially, each idempotent, under a lock; a failed migration
     *   does NOT advance biawin_crm_db_version, so the next load retries it safely.
     */
    public static function maybe_upgrade(): void
    {
        global $wpdb;
        // 1) Compatibility repair for the finance-return follow-up permission.
        // Some older installations already used marker 6 for another capability batch, so merely
        // bumping the shared marker to 6 did not run the new grant there. This feature-scoped,
        // one-time repair is independent of the shared marker and makes upgrades deterministic.
        self::ensure_finance_return_followup_capability();

        // 2) Capability sync — only when the definitions marker changed (v0.8.63.0: no longer on
        //    every load, and ledger-based, so manual role customizations survive updates).
        self::ensure_roles_and_caps();

        // 2) DB migrations under lock, in order.
        $db_version = (string) get_option('biawin_crm_db_version', '');
        if (version_compare($db_version, BIAWIN_CRM_VERSION, '>=')) {
            return;
        }
        // v0.8.63.2 — concurrency: a MySQL named lock is atomic (two requests can never both get
        // it) and the server releases it automatically if the PHP process dies mid-migration.
        // The option lock is kept as a fallback only where GET_LOCK is unavailable.
        // GET_LOCK names are server-wide: scope to this database + prefix so other sites on the
        // same MySQL server never block each other.
        $lock_name = 'bcrm_upgrade_' . md5((string)$wpdb->dbname . '|' . $wpdb->prefix);
        $named = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name));
        if ($named === '0' || $named === 0) {
            return; // another request is migrating right now; it will finish the job
        }
        $use_named = ((string)$named === '1');
        if (!$use_named) {
            $lock = (string) get_option('bcrm_upgrade_lock', '');
            if ($lock !== '' && (time() - (int) $lock) < 300) return;
            update_option('bcrm_upgrade_lock', (string) time(), false);
        }

        try {
            // Re-read under the lock: another request may have finished while we waited.
            wp_cache_delete('biawin_crm_db_version', 'options');
            $db_version = (string) get_option('biawin_crm_db_version', '');
            if (version_compare($db_version, BIAWIN_CRM_VERSION, '>=')) return;
            $pending = [];
            foreach (array_keys(self::MIGRATIONS) as $migration_version) {
                if (version_compare($db_version, $migration_version, '<')) {
                    $pending[$migration_version] = self::MIGRATIONS[$migration_version];
                }
            }
            foreach ($pending as $migration_version => $method) {
                try {
                    self::$method();
                    // Mark each migration's floor so a later crash does not replay earlier steps.
                    update_option('biawin_crm_db_version', $migration_version, false);
                } catch (Throwable $e) {
                    self::log_upgrade_failure($migration_version, $db_version, $e);
                    return; // version NOT advanced → the next request retries this migration
                }
            }
            // Schema ensure (idempotent) then stamp the plugin version. v0.8.63.2: a failure here is
            // logged and retried on the next request instead of fataling the page.
            try {
                self::activate();
            } catch (Throwable $e) {
                self::log_upgrade_failure('schema:' . BIAWIN_CRM_VERSION, $db_version, $e);
                if (version_compare((string)get_option('biawin_crm_db_version', ''), BIAWIN_CRM_VERSION, '>=')) {
                    // activate() stamps the version at its very end; never leave a stamp after a failure.
                    $floor = $pending ? (string)array_key_last($pending) : $db_version;
                    update_option('biawin_crm_db_version', $floor, false);
                }
                return;
            }
            update_option('biawin_crm_db_version', BIAWIN_CRM_VERSION, false);
        } finally {
            if ($use_named) {
                $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            } else {
                delete_option('bcrm_upgrade_lock');
            }
        }
    }

    private static function log_upgrade_failure(string $step, string $from_version, Throwable $e): void
    {
        try {
            Biawin_CRM_DB::log('plugin', null, 'upgrade_migration_failed', null, [
                'version' => $step,
                'error' => $e->getMessage(),
                'from_db_version' => $from_version,
                'timestamp' => Biawin_CRM_DB::now(),
            ]);
        } catch (Throwable $ignored) {
            // logging must never break the upgrade path
        }
        error_log('[biawin-crm] migration ' . $step . ' failed: ' . $e->getMessage());
    }

    /** 0.8.62.0: refund_registers table + refund tags/caps. Idempotent. */
    private static function migrate_0862_0(): void
    {
        // Schema is ensured by self::activate() right after migrations; nothing destructive here.
        return;
    }

    /** v0.8.62.11: invoice_code column on refund_registers (قانون تطبیق کد فاکتور). Idempotent. */
    private static function migrate_0862_11(): void
    {
        // Schema is ensured by self::activate() right after migrations; nothing destructive here.
        return;
    }

    /** 0.8.62.2: admin-only refund upload architecture. Idempotent. */
    private static function migrate_0862_2(): void
    {
    }

    /**
     * 0.8.63.0 — After-Sales foundation (Phase 1). Idempotent, non-destructive, retryable:
     * - unit hierarchy columns on service_units (+ indexes);
     * - initial after-sales units, inserted once by stable unit_key, never overwritten;
     * - existing service units typed 'service' and parented under «خدمات» only where still empty;
     * - complaint roles relabelled to «نارضایتی» only if the label is still the old default;
     * - new roles/capabilities granted additively (ledger).
     * Throws on any schema failure so maybe_upgrade() does NOT advance the version.
     */
    private static function migrate_0863_0(): void
    {
        global $wpdb;
        $p = $wpdb->prefix . 'bcrm_';
        self::ensure_service_unit_hierarchy_schema($p, true);
        self::seed_after_sales_units($p);
        self::relabel_legacy_roles();
        self::sync_roles_and_caps();
        update_option(self::CAPS_MARKER_OPTION, self::CAPS_MARKER_VERSION, false);
    }

    /**
     * 0.8.64.0 — After-Sales Phase 2: admin configuration foundation. Idempotent, non-destructive,
     * retryable:
     * - new tables catalog_items (complaint subjects + outcomes) and complaint_inquiries (N per complaint);
     * - complaints.subject_id (NULL for every historical row — no reinterpretation of free-text subjects);
     * - service_units.inquiry_responders (who in a unit may answer; default = all members);
     * - the three outcome keys the existing resolve flow already writes to complaints.resolution_outcome
     *   are registered once (insert-only, ledger) so historical outcomes stay resolvable;
     * - manage_inquiry_units granted additively (ledger) to administrator / portal admin.
     * Throws on any schema failure so maybe_upgrade() does NOT advance the version.
     */
    private static function migrate_0864_0(): void
    {
        global $wpdb;
        $p = $wpdb->prefix . 'bcrm_';
        self::ensure_after_sales_config_schema($p, true);
        self::seed_legacy_complaint_outcomes($p);
        self::sync_roles_and_caps();
        update_option(self::CAPS_MARKER_OPTION, self::CAPS_MARKER_VERSION, false);
    }

    /** v0.8.64.0 — Phase 2 tables (also part of activate()'s full schema for fresh installs). */
    private static function after_sales_config_tables(string $p, string $charset): array
    {
        return [
            // Generic admin-managed catalog. catalog = 'complaint_subject' | 'complaint_outcome' (extensible).
            // Items are never hard-deleted by the UI: deactivation keeps historical references valid.
            // meta_json is reserved for per-item metadata, e.g. future outcome actions
            // {"actions":[{"type":"refund_workflow"},{"type":"tag","tag":"..."}],"required_fields":[...]}.
            "CREATE TABLE {$p}catalog_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            catalog VARCHAR(40) NOT NULL,
            item_key VARCHAR(80) NOT NULL,
            label VARCHAR(190) NOT NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            meta_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY catalog_item (catalog,item_key),
            KEY catalog_active_order (catalog,is_active,sort_order)
        ) $charset",
            // One complaint → many inquiries (one row per unit request/record). mode separates
            // portal requests (answered by the unit in the portal) from manual records (agent
            // already contacted the unit). unit_name_snapshot keeps history readable if a unit changes.
            "CREATE TABLE {$p}complaint_inquiries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            complaint_id BIGINT UNSIGNED NOT NULL,
            unit_id BIGINT UNSIGNED NOT NULL,
            unit_name_snapshot VARCHAR(180) NULL,
            mode VARCHAR(20) NOT NULL,
            status VARCHAR(30) NOT NULL,
            request_note LONGTEXT NULL,
            result_key VARCHAR(80) NULL,
            result_text LONGTEXT NULL,
            notes LONGTEXT NULL,
            requested_by BIGINT UNSIGNED NULL,
            requested_at DATETIME NULL,
            responded_by BIGINT UNSIGNED NULL,
            responded_at DATETIME NULL,
            contacted_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY complaint_id (complaint_id),
            KEY unit_status (unit_id,status),
            KEY mode_status (mode,status)
        ) $charset",
        ];
    }

    /** v0.8.64.0 — ensure Phase 2 schema; $strict throws so a failed migration is retried. */
    private static function ensure_after_sales_config_schema(string $p, bool $strict = false): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (self::after_sales_config_tables($p, $wpdb->get_charset_collate()) as $statement) {
            dbDelta($statement);
        }
        self::ensure_column("{$p}complaints", 'subject_id', 'BIGINT UNSIGNED NULL');
        self::ensure_index("{$p}complaints", 'subject_id', 'subject_id');
        self::ensure_column("{$p}service_units", 'inquiry_responders', "VARCHAR(20) NOT NULL DEFAULT 'members'");
        if (!$strict) return;
        foreach (['catalog_items', 'complaint_inquiries'] as $t) {
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', "{$p}$t")) !== "{$p}$t") {
                throw new RuntimeException("$t could not be created: " . (string)$wpdb->last_error);
            }
        }
        foreach (['complaints' => 'subject_id', 'service_units' => 'inquiry_responders'] as $t => $column) {
            if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$p}$t` LIKE %s", $column))) {
                throw new RuntimeException("$t.$column could not be added: " . (string)$wpdb->last_error);
            }
        }
    }

    /**
     * The outcome keys the EXISTING resolve flow (Workflow_Service::resolve_complaint) already
     * writes to complaints.resolution_outcome — evidence from code + historical data, not a new
     * business list. Labels are the ones the current form shows. Admin may rename/reorder/deactivate.
     */
    public static function legacy_complaint_outcomes(): array
    {
        return [
            'satisfied' => ['مشتری راضی شد', 10],
            'service_request' => ['تبدیل به درخواست خدمت', 20],
            'refund' => ['درخواست عودت وجه', 30],
        ];
    }

    /** Insert-once by stable key (ledger): never updates an existing item, never re-creates one. */
    private static function seed_legacy_complaint_outcomes(string $p): void
    {
        global $wpdb;
        $table = "{$p}catalog_items";
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        $ledger = get_option(self::CATALOG_SEED_LEDGER_OPTION, []);
        if (!is_array($ledger)) $ledger = [];
        $now = current_time('mysql');
        foreach (self::legacy_complaint_outcomes() as $key => [$label, $order]) {
            $ledger_key = 'complaint_outcome:' . $key;
            $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE catalog='complaint_outcome' AND item_key=%s", $key));
            if ($exists || !empty($ledger[$ledger_key])) { $ledger[$ledger_key] = true; continue; }
            $ok = $wpdb->insert($table, [
                'catalog' => 'complaint_outcome', 'item_key' => $key, 'label' => $label, 'is_active' => 1,
                'sort_order' => $order, 'is_system' => 1,
                'meta_json' => wp_json_encode(['legacy_handler' => $key, 'actions' => []]),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($ok === false) throw new RuntimeException('complaint outcome seed failed: ' . (string)$wpdb->last_error);
            $ledger[$ledger_key] = true;
        }
        update_option(self::CATALOG_SEED_LEDGER_OPTION, $ledger, false);
    }

    /**
     * Role/capability definitions. 'since' = the caps-marker version that introduced the
     * definition; caps with since <= 3 already existed on every install before the ledger.
     */
    public static function role_definitions(): array
    {
        $crm_all = ['read','bcrm_work_cases','bcrm_manage_cases','bcrm_import','bcrm_reports','bcrm_sensitive_merge','bcrm_financial_verify','bcrm_complaints','bcrm_services','bcrm_refunds','bcrm_manage_customer_phones','view_deficiency_cases','manage_deficiency_cases','assign_deficiency_cases','process_deficiency_case','send_case_to_finance','complete_deficiency_case','archive_deficiency_case','view_deficiency_reports','process_finance_return_followup','view_finance_cases','manage_finance_cases','assign_finance_cases','review_finance_case','approve_finance_case','reject_finance_case','return_finance_case','view_finance_reports'];
        return [
            // ---- legacy roles (caps unchanged; after-sales caps are added in after_sales_caps()) ----
            'bcrm_portal_admin' => ['label' => 'مدیر پرتال CRM', 'caps' => $crm_all],
            'bcrm_agent' => ['label' => 'کارشناس تماس CRM', 'caps' => ['read','bcrm_work_cases']],
            'bcrm_supervisor' => ['label' => 'سرپرست CRM', 'caps' => $crm_all],
            'bcrm_deficiency_supervisor' => ['label' => 'سرپرست رفع ناقصی CRM', 'caps' => ['read','bcrm_work_cases','view_deficiency_cases','manage_deficiency_cases','assign_deficiency_cases','process_deficiency_case','send_case_to_finance','complete_deficiency_case','archive_deficiency_case','view_deficiency_reports','process_finance_return_followup']],
            'bcrm_deficiency_agent' => ['label' => 'کارشناس رفع ناقصی CRM', 'caps' => ['read','bcrm_work_cases','view_deficiency_cases','process_deficiency_case','send_case_to_finance','process_finance_return_followup']],
            'bcrm_financial_supervisor' => ['label' => 'سرپرست تأیید مالی CRM', 'caps' => ['read','bcrm_work_cases','bcrm_financial_verify','view_finance_cases','manage_finance_cases','assign_finance_cases','review_finance_case','approve_finance_case','reject_finance_case','return_finance_case','view_finance_reports']],
            'bcrm_financial_agent' => ['label' => 'کارشناس تأیید مالی CRM', 'caps' => ['read','bcrm_work_cases','bcrm_financial_verify','view_finance_cases','review_finance_case','approve_finance_case','reject_finance_case','return_finance_case']],
            'bcrm_complaint_supervisor' => ['label' => 'سرپرست نارضایتی‌ها CRM', 'caps' => ['read','bcrm_work_cases','bcrm_complaints']],
            'bcrm_complaint_agent' => ['label' => 'کارشناس نارضایتی‌ها CRM', 'caps' => ['read','bcrm_work_cases','bcrm_complaints']],
            'bcrm_service_supervisor' => ['label' => 'سرپرست واحد خدمات CRM', 'caps' => ['read','bcrm_work_cases','bcrm_services']],
            'bcrm_service_agent' => ['label' => 'کارشناس خدمات CRM', 'caps' => ['read','bcrm_work_cases','bcrm_services']],
            'bcrm_refund_supervisor' => ['label' => 'سرپرست عودت وجه CRM', 'caps' => ['read','bcrm_work_cases','bcrm_refunds','bcrm_refund_register']],
            'bcrm_refund_agent' => ['label' => 'کارشناس عودت CRM', 'caps' => ['read','bcrm_work_cases','bcrm_refunds']],
            'bcrm_refund_register' => ['label' => 'ثبت‌کننده عودت اکسلی CRM', 'caps' => ['read','bcrm_work_cases','bcrm_refund_register']],
            // ---- v0.8.63.0 new after-sales roles (created only if missing, never recreated after deletion) ----
            'bcrm_after_sales_manager' => ['label' => 'مدیر خدمات پس از فروش CRM', 'caps' => ['read','bcrm_work_cases','bcrm_complaints']],
            'bcrm_loan_supervisor' => ['label' => 'سرپرست وام CRM', 'caps' => ['read','bcrm_work_cases']],
            'bcrm_loan_agent' => ['label' => 'کارشناس وام CRM', 'caps' => ['read','bcrm_work_cases']],
            'bcrm_treasury_supervisor' => ['label' => 'سرپرست خزانه CRM', 'caps' => ['read','bcrm_work_cases']],
            'bcrm_treasury_agent' => ['label' => 'کارشناس خزانه CRM', 'caps' => ['read','bcrm_work_cases']],
        ];
    }

    /** v0.8.63.0 — after-sales capability foundation (consumed by later phases). */
    public static function after_sales_capabilities(): array
    {
        return [
            'view_after_sales' => 'مشاهده خدمات پس از فروش',
            'manage_after_sales' => 'مدیریت خدمات پس از فروش',
            'view_dissatisfaction' => 'مشاهده نارضایتی‌ها',
            'manage_dissatisfaction' => 'مدیریت نارضایتی‌ها',
            'assign_dissatisfaction' => 'تخصیص نارضایتی',
            'process_dissatisfaction' => 'رسیدگی به نارضایتی',
            'view_after_sales_reports' => 'گزارش‌های خدمات پس از فروش',
            'manage_after_sales_settings' => 'تنظیمات خدمات پس از فروش',
            'manage_dissatisfaction_subjects' => 'مدیریت موضوعات نارضایتی',
            'manage_dissatisfaction_outcomes' => 'مدیریت نتیجه‌های نارضایتی',
            'manage_inquiry_units' => 'مدیریت واحدهای قابل استعلام', // v0.8.64.0 (admin + portal admin only)
            'view_finance_inquiries' => 'مشاهده استعلام‌های مالی',
            'respond_finance_inquiries' => 'پاسخ استعلام‌های مالی',
            'view_loan_inquiries' => 'مشاهده استعلام‌های وام',
            'respond_loan_inquiries' => 'پاسخ استعلام‌های وام',
            'view_service_inquiries' => 'مشاهده استعلام‌های خدمات',
            'respond_service_inquiries' => 'پاسخ استعلام‌های خدمات',
            'view_treasury' => 'مشاهده خزانه',
            'manage_treasury' => 'مدیریت خزانه',
            'manage_treasury_capacity' => 'تنظیم ظرفیت خزانه',
            'approve_refund_schedule' => 'تأیید زمان‌بندی عودت',
            'process_refund_payment' => 'ثبت پرداخت عودت',
        ];
    }

    /** v0.8.63.0 — which role receives which after-sales capability (additive; admin can change later). */
    public static function after_sales_caps(): array
    {
        $all = array_keys(self::after_sales_capabilities());
        $dissat_manage = ['view_after_sales','view_dissatisfaction','manage_dissatisfaction','assign_dissatisfaction','process_dissatisfaction','view_after_sales_reports'];
        return [
            'bcrm_portal_admin' => $all,
            'bcrm_supervisor' => $dissat_manage,
            'bcrm_after_sales_manager' => ['view_after_sales','manage_after_sales','view_dissatisfaction','manage_dissatisfaction','assign_dissatisfaction','process_dissatisfaction','view_after_sales_reports','manage_after_sales_settings','manage_dissatisfaction_subjects','manage_dissatisfaction_outcomes','view_finance_inquiries','view_loan_inquiries','view_service_inquiries','view_treasury','approve_refund_schedule'],
            'bcrm_complaint_supervisor' => $dissat_manage,
            'bcrm_complaint_agent' => ['view_after_sales','view_dissatisfaction','process_dissatisfaction'],
            'bcrm_financial_supervisor' => ['view_finance_inquiries','respond_finance_inquiries'],
            'bcrm_financial_agent' => ['view_finance_inquiries','respond_finance_inquiries'],
            'bcrm_service_supervisor' => ['view_service_inquiries','respond_service_inquiries'],
            'bcrm_service_agent' => ['view_service_inquiries','respond_service_inquiries'],
            'bcrm_loan_supervisor' => ['view_loan_inquiries','respond_loan_inquiries'],
            'bcrm_loan_agent' => ['view_loan_inquiries','respond_loan_inquiries'],
            'bcrm_treasury_supervisor' => ['view_treasury','manage_treasury','manage_treasury_capacity','process_refund_payment'],
            'bcrm_treasury_agent' => ['view_treasury','process_refund_payment'],
            'administrator' => $all,
        ];
    }

    /**
     * v0.8.65.15 — one-time compatibility repair for existing installations.
     *
     * A previous release reused shared capability marker 6 even though marker 6 was already
     * occupied in some installations. Those sites therefore skipped the additive role sync and
     * deficiency agents never received process_finance_return_followup. This repair has its own
     * feature marker, never recreates deleted roles, never removes capabilities, and does not
     * override an explicit false/deny already stored on a role.
     */
    public static function ensure_finance_return_followup_capability(): void
    {
        if ((int)get_option(self::FINANCE_RETURN_FOLLOWUP_CAP_OPTION, 0) >= 1) {
            return;
        }

        $cap = 'process_finance_return_followup';
        $role_names = [
            'bcrm_deficiency_agent',
            'bcrm_deficiency_supervisor',
            'bcrm_portal_admin',
            'bcrm_supervisor',
            'administrator',
        ];
        $ledger = get_option(self::CAPS_LEDGER_OPTION, null);
        if (!is_array($ledger)) {
            $ledger = ['roles' => [], 'caps' => []];
        }
        if (!isset($ledger['roles']) || !is_array($ledger['roles'])) $ledger['roles'] = [];
        if (!isset($ledger['caps']) || !is_array($ledger['caps'])) $ledger['caps'] = [];

        foreach ($role_names as $role_name) {
            $role = get_role($role_name);
            if (!$role) continue; // never recreate a role an admin intentionally deleted

            $caps = (array)$role->capabilities;
            if (array_key_exists($cap, $caps) && $caps[$cap] === false) {
                // Respect an explicit deny. Absence means this new capability has never been provisioned.
                continue;
            }
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
            $ledger['roles'][$role_name] = true;
            $ledger['caps'][$role_name][$cap] = true;
        }

        update_option(self::CAPS_LEDGER_OPTION, $ledger, false);
        update_option(self::FINANCE_RETURN_FOLLOWUP_CAP_OPTION, 1, false);
        if ((int)get_option(self::CAPS_MARKER_OPTION, 0) < self::CAPS_MARKER_VERSION) {
            update_option(self::CAPS_MARKER_OPTION, self::CAPS_MARKER_VERSION, false);
        }
    }

    /**
     * Cheap guard (runs on plugins_loaded/admin_init): only syncs when the definitions marker
     * changed. v0.8.63.0 — previously this re-granted EVERY defined cap on EVERY load, which
     * silently undid any capability an admin removed from a role (part of S4).
     */
    public static function ensure_roles_and_caps(): void
    {
        if ((int)get_option(self::CAPS_MARKER_OPTION, 0) >= self::CAPS_MARKER_VERSION && is_array(get_option(self::CAPS_LEDGER_OPTION))) {
            return;
        }
        self::sync_roles_and_caps();
        update_option(self::CAPS_MARKER_OPTION, self::CAPS_MARKER_VERSION, false);
    }

    /**
     * Additive, ledger-based role/capability provisioning. Never removes a capability, never
     * resets a role, never recreates a role the plugin created earlier and an admin deleted,
     * never re-grants a capability the plugin granted earlier and an admin removed/denied.
     */
    public static function sync_roles_and_caps(): void
    {
        $definitions = self::role_definitions();
        $extra = self::after_sales_caps();
        $ledger = get_option(self::CAPS_LEDGER_OPTION, null);
        if (!is_array($ledger) || !isset($ledger['roles'], $ledger['caps'])) {
            // First run of the ledger on an EXISTING install: everything the pre-0.8.63 code
            // defined was (re)granted on every load, so it is already present — record it as
            // provisioned without writing anything. Only NEW (after-sales) caps get granted below.
            $ledger = ['roles' => [], 'caps' => []];
            $legacy_admin = [];
            foreach ($definitions as $role_name => $definition) {
                if (!get_role($role_name)) continue;
                $ledger['roles'][$role_name] = true;
                foreach ((array)$definition['caps'] as $cap) $ledger['caps'][$role_name][$cap] = true;
                $legacy_admin = array_merge($legacy_admin, (array)$definition['caps']);
            }
            if (get_role('administrator')) {
                foreach (array_unique($legacy_admin) as $cap) $ledger['caps']['administrator'][$cap] = true;
            }
        }
        $wanted = [];
        foreach ($definitions as $role_name => $definition) $wanted[$role_name] = (array)$definition['caps'];
        foreach ($extra as $role_name => $caps) $wanted[$role_name] = array_merge($wanted[$role_name] ?? [], (array)$caps);
        // administrator: every CRM capability any role defines (legacy behaviour), plus all after-sales caps.
        $admin_caps = [];
        foreach ($definitions as $definition) $admin_caps = array_merge($admin_caps, (array)$definition['caps']);
        $wanted['administrator'] = array_merge($admin_caps, (array)($extra['administrator'] ?? []));

        foreach ($wanted as $role_name => $caps) {
            $caps = array_values(array_unique($caps));
            $role = get_role($role_name);
            if (!$role) {
                if ($role_name === 'administrator' || !isset($definitions[$role_name]) || !empty($ledger['roles'][$role_name])) {
                    continue; // never recreate a role the plugin already created once (admin deleted it)
                }
                add_role($role_name, (string)$definitions[$role_name]['label'], array_fill_keys($caps, true));
                $ledger['roles'][$role_name] = true;
                foreach ($caps as $cap) $ledger['caps'][$role_name][$cap] = true;
                continue;
            }
            $ledger['roles'][$role_name] = true;
            foreach ($caps as $cap) {
                if (!empty($ledger['caps'][$role_name][$cap])) continue;           // granted before → admin owns it now
                if (array_key_exists($cap, (array)$role->capabilities)) {           // already present (granted or explicitly denied)
                    $ledger['caps'][$role_name][$cap] = true;
                    continue;
                }
                $role->add_cap($cap);
                $ledger['caps'][$role_name][$cap] = true;
            }
        }
        update_option(self::CAPS_LEDGER_OPTION, $ledger, false);
    }

    /** v0.8.63.0 — «شکایت» roles become «نارضایتی» roles, ONLY if the label is still the old default. */
    private static function relabel_legacy_roles(): void
    {
        $renames = [
            'bcrm_complaint_supervisor' => ['سرپرست شکایات CRM', 'سرپرست نارضایتی‌ها CRM'],
            'bcrm_complaint_agent' => ['کارشناس شکایت CRM', 'کارشناس نارضایتی‌ها CRM'],
        ];
        $wp_roles = function_exists('wp_roles') ? wp_roles() : null;
        if (!$wp_roles || empty($wp_roles->roles)) return;
        $changed = false;
        foreach ($renames as $role_name => [$old, $new]) {
            if (isset($wp_roles->roles[$role_name]) && (string)$wp_roles->roles[$role_name]['name'] === $old) {
                $wp_roles->roles[$role_name]['name'] = $new;
                if (isset($wp_roles->role_names)) $wp_roles->role_names[$role_name] = $new;
                $changed = true;
            }
        }
        if ($changed && !empty($wp_roles->use_db)) update_option($wp_roles->role_key, $wp_roles->roles);
    }

    /** v0.8.63.0 — hierarchy/inquiry columns on the EXISTING service_units table (no new unit table). */
    private static function ensure_service_unit_hierarchy_schema(string $p, bool $strict = false): void
    {
        global $wpdb;
        $table = "{$p}service_units";
        $columns = [
            'parent_unit_id' => 'BIGINT UNSIGNED NULL',
            'unit_type' => "VARCHAR(40) NOT NULL DEFAULT 'service'",
            'can_receive_inquiry' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'requires_portal_response' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'display_order' => 'INT NOT NULL DEFAULT 0',
        ];
        foreach ($columns as $column => $definition) {
            self::ensure_column($table, $column, $definition);
            if ($strict && !$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $column))) {
                throw new RuntimeException("service_units.$column could not be added: " . (string)$wpdb->last_error);
            }
        }
        self::ensure_index($table, 'parent_unit', 'parent_unit_id');
        self::ensure_index($table, 'type_active_order', 'unit_type,is_active,display_order');
        self::ensure_index("{$p}service_unit_members", 'user_role', 'user_id,member_role');
    }

    /** Initial after-sales structure — stable unit_keys; the plugin never renames/reconfigures an existing unit. */
    public static function after_sales_unit_seeds(): array
    {
        return [
            // key => [name, type, parent_key, can_receive_inquiry, requires_portal_response, order]
            'after_sales' => ['خدمات پس از فروش', 'after_sales', '', 0, 0, 10],
            'dissatisfaction' => ['نارضایتی‌ها', 'dissatisfaction', 'after_sales', 0, 0, 20],
            'services' => ['خدمات', 'service_group', 'after_sales', 1, 1, 30],
            'finance_approval' => ['تأیید مالی', 'finance', 'after_sales', 1, 1, 40],
            'loan' => ['وام', 'loan', 'after_sales', 1, 1, 50],
            'treasury' => ['خزانه', 'treasury', 'after_sales', 1, 1, 60],
        ];
    }

    /**
     * Insert each initial unit once (by unit_key). Existing rows are never updated; a unit the
     * plugin seeded and an admin later deleted is not recreated (seed ledger). No users/members
     * are assigned — membership is data managed from «واحدها».
     */
    private static function seed_after_sales_units(string $p): void
    {
        global $wpdb;
        $table = "{$p}service_units";
        $ledger = get_option(self::UNIT_SEED_LEDGER_OPTION, []);
        if (!is_array($ledger)) $ledger = [];
        $now = current_time('mysql');
        $ids = [];
        foreach (self::after_sales_unit_seeds() as $key => [$name, $type, $parent_key, $inquiry, $portal, $order]) {
            $existing = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE unit_key=%s", $key));
            if ($existing) { $ids[$key] = $existing; $ledger[$key] = true; continue; }
            if (!empty($ledger[$key])) continue; // seeded before, deleted by an admin → respect it
            $row = ['unit_key'=>$key,'unit_name'=>$name,'supervisor_user_id'=>null,'is_active'=>1,'unit_type'=>$type,
                'parent_unit_id'=>($parent_key !== '' && !empty($ids[$parent_key])) ? $ids[$parent_key] : null,
                'can_receive_inquiry'=>$inquiry,'requires_portal_response'=>$portal,'display_order'=>$order,
                'created_at'=>$now,'updated_at'=>$now];
            if ($wpdb->insert($table, $row) === false) throw new RuntimeException("unit $key insert failed: " . (string)$wpdb->last_error);
            $ids[$key] = (int)$wpdb->insert_id;
            $ledger[$key] = true;
        }
        update_option(self::UNIT_SEED_LEDGER_OPTION, $ledger, false);
        // Pre-existing service units (the service cartables) become typed children of «خدمات» —
        // only rows whose new columns are still at their defaults (empty), never an admin's choice.
        // One-time only (flag), so an admin who later moves a unit to the top level keeps that choice.
        if (!empty($ids['services']) && get_option('biawin_crm_service_units_parented_0863') !== '1') {
            $seed_keys = array_keys(self::after_sales_unit_seeds());
            $ph = implode(',', array_fill(0, count($seed_keys), '%s'));
            $ok = $wpdb->query($wpdb->prepare("UPDATE `$table` SET parent_unit_id=%d WHERE parent_unit_id IS NULL AND unit_type='service' AND unit_key NOT IN ($ph)", array_merge([$ids['services']], $seed_keys)));
            if ($ok === false) throw new RuntimeException('service unit parenting failed: ' . (string)$wpdb->last_error);
            update_option('biawin_crm_service_units_parented_0863', '1', false);
        }
    }

    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $previous_suppression = $wpdb->suppress_errors(true);
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'bcrm_';

        $sql = [];
        $sql[] = "CREATE TABLE {$p}customers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            internal_uuid VARCHAR(36) NOT NULL,
            customer_code VARCHAR(32) NULL,
            source_customer_id VARCHAR(100) NULL,
            display_name VARCHAR(255) NULL,
            normalized_name VARCHAR(255) NULL,
            sales_manager VARCHAR(255) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            grade VARCHAR(20) NULL,
            assignment_cohort VARCHAR(20) NOT NULL DEFAULT 'normal',
            financial_score TINYINT UNSIGNED NULL,
            satisfaction_avg DECIMAL(5,2) NULL,
            total_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
            declared_paid DECIMAL(18,2) NULL,
            discrepancy_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            locked_by BIGINT UNSIGNED NULL,
            locked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY internal_uuid (internal_uuid),
            UNIQUE KEY customer_code (customer_code),
            UNIQUE KEY source_customer_id (source_customer_id),
            KEY status (status),
            KEY grade (grade)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}phones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NULL,
            raw_phone VARCHAR(60) NOT NULL,
            normalized_phone VARCHAR(30) NOT NULL,
            phone_status VARCHAR(50) NOT NULL DEFAULT 'unverified',
            relation_type VARCHAR(50) NOT NULL DEFAULT 'unknown',
            source VARCHAR(40) NOT NULL DEFAULT 'import',
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            auto_match_blocked TINYINT(1) NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY normalized_phone (normalized_phone),
            KEY customer_id (customer_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NULL,
            invoice_code VARCHAR(80) NOT NULL,
            invoice_name VARCHAR(255) NULL,
            amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
            seller VARCHAR(255) NULL,
            callcenter VARCHAR(255) NULL,
            sales_manager VARCHAR(255) NULL,
            invoice_status VARCHAR(80) NULL,
            payment_info LONGTEXT NULL,
            prepayment_approval_status VARCHAR(80) NULL,
            prepayment_unapproved TINYINT(1) NOT NULL DEFAULT 0,
            exclude_from_total TINYINT(1) NOT NULL DEFAULT 0,
            excluded_amount DECIMAL(18,2) NULL,
            excluded_reason VARCHAR(120) NULL,
            excluded_at DATETIME NULL,
            excluded_by BIGINT UNSIGNED NULL,
            seller_action_date VARCHAR(80) NULL,
            seller_action_year SMALLINT UNSIGNED NULL,
            extra_json LONGTEXT NULL,
            has_input_conflict TINYINT(1) NOT NULL DEFAULT 0,
            conflict_json LONGTEXT NULL,
            source_import_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_code (invoice_code),
            KEY customer_id (customer_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}invoice_phones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            normalized_phone VARCHAR(30) NOT NULL,
            phone_position TINYINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_phone (invoice_id, normalized_phone),
            KEY normalized_phone (normalized_phone)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            UNIQUE KEY invoice_product (invoice_id, product_name(120))
        ) $charset";
        $sql[] = "CREATE TABLE {$p}payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            payment_info LONGTEXT NULL,
            source_import_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NULL,
            customer_id BIGINT UNSIGNED NULL,
            service_name VARCHAR(255) NOT NULL,
            service_status VARCHAR(120) NULL,
            source_import_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY customer_id (customer_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}import_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_name VARCHAR(120) NOT NULL,
            mapping_json LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY template_name (template_name)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}imports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            mapping_json LONGTEXT NOT NULL,
            dry_run_json LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'dry_run',
            imported_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            committed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}import_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_token VARCHAR(64) NOT NULL,
            upload_token VARCHAR(64) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            source_path LONGTEXT NOT NULL,
            normalized_csv_path LONGTEXT NULL,
            mapping_json LONGTEXT NOT NULL,
            headers_json LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'staging',
            phase VARCHAR(30) NOT NULL DEFAULT 'stage',
            total_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
            processed_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
            staged_invoices BIGINT UNSIGNED NOT NULL DEFAULT 0,
            committed_invoices BIGINT UNSIGNED NOT NULL DEFAULT 0,
            missing_invoice_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
            conflicted_invoices BIGINT UNSIGNED NOT NULL DEFAULT 0,
            csv_offset BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_stage_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            high_risk_json LONGTEXT NULL,
            import_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_token (job_token),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}import_stage_invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_token VARCHAR(64) NOT NULL,
            invoice_code VARCHAR(80) NOT NULL,
            source_customer_id VARCHAR(100) NULL,
            invoice_json LONGTEXT NOT NULL,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            has_conflict TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_invoice (job_token, invoice_code),
            KEY job_id_scan (job_token, id),
            KEY job_source_customer (job_token, source_customer_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}import_stage_phones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_token VARCHAR(64) NOT NULL,
            invoice_code VARCHAR(80) NOT NULL,
            normalized_phone VARCHAR(30) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_invoice_phone (job_token, invoice_code, normalized_phone),
            KEY job_phone (job_token, normalized_phone)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}service_units (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_key VARCHAR(80) NOT NULL,
            unit_name VARCHAR(180) NOT NULL,
            supervisor_user_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            parent_unit_id BIGINT UNSIGNED NULL,
            unit_type VARCHAR(40) NOT NULL DEFAULT 'service',
            can_receive_inquiry TINYINT(1) NOT NULL DEFAULT 0,
            requires_portal_response TINYINT(1) NOT NULL DEFAULT 0,
            display_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unit_key (unit_key),
            KEY supervisor_user_id (supervisor_user_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}service_unit_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            member_role VARCHAR(30) NOT NULL DEFAULT 'agent',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unit_user (unit_id,user_id),
            KEY user_id (user_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'assigned',
            assigned_by BIGINT UNSIGNED NOT NULL,
            assigned_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY agent_user_id (agent_user_id),
            KEY customer_id (customer_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}sales_manager_mapping (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sales_manager VARCHAR(255) NOT NULL,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY sales_manager (sales_manager),
            KEY agent_user_id (agent_user_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}interactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            agent_user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(40) NOT NULL,
            contact_phone VARCHAR(30) NULL,
            outcome VARCHAR(80) NULL,
            outcome_key VARCHAR(80) NULL,
            satisfaction_status VARCHAR(30) NULL,
            request_status VARCHAR(40) NULL,
            requested_loan_amount DECIMAL(18,2) NULL,
            requested_service VARCHAR(255) NULL,
            satisfaction_score TINYINT UNSIGNED NULL,
            sales_satisfaction TINYINT UNSIGNED NULL,
            followup_satisfaction TINYINT UNSIGNED NULL,
            service_quality_satisfaction TINYINT UNSIGNED NULL,
            service_timing_satisfaction TINYINT UNSIGNED NULL,
            promised_services_received TINYINT(1) NULL,
            repurchase_intent TINYINT(1) NULL,
            referral_intent TINYINT(1) NULL,
            declared_paid DECIMAL(18,2) NULL,
            complaint_reason LONGTEXT NULL,
            complaint_priority VARCHAR(30) NULL,
            next_followup_at DATETIME NULL,
            notes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY agent_user_id (agent_user_id),
            KEY type (type)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}complaints (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            interaction_id BIGINT UNSIGNED NULL,
            subject VARCHAR(255) NULL,
            related_unit VARCHAR(120) NULL,
            description LONGTEXT NULL,
            priority VARCHAR(30) NOT NULL DEFAULT 'normal',
            needs_manager_call TINYINT(1) NOT NULL DEFAULT 0,
            due_at DATETIME NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            resolution LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}merge_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            primary_customer_id BIGINT UNSIGNED NULL,
            merged_customer_ids LONGTEXT NOT NULL,
            merge_type VARCHAR(30) NOT NULL,
            action_type VARCHAR(30) NOT NULL DEFAULT 'merge',
            confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
            reason LONGTEXT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            performed_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY merge_type (merge_type)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id BIGINT UNSIGNED NULL,
            object_type VARCHAR(60) NOT NULL,
            object_id BIGINT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY object_lookup (object_type, object_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}financial_cases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            case_code VARCHAR(40) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            declared_amount DECIMAL(18,2) NULL,
            system_amount DECIMAL(18,2) NULL,
            difference_amount DECIMAL(18,2) NULL,
            reason VARCHAR(120) NULL,
            note LONGTEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            assigned_user_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            resolved_by BIGINT UNSIGNED NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY case_code (case_code),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY assigned_user_id (assigned_user_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}service_catalog (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_code VARCHAR(60) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            credit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
            unit_key VARCHAR(80) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY service_code (service_code),
            KEY is_active (is_active),
            KEY unit_key (unit_key)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}service_cases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            case_code VARCHAR(40) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            credit_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            unit_key VARCHAR(80) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'unassigned',
            assigned_user_id BIGINT UNSIGNED NULL,
            assigned_by BIGINT UNSIGNED NULL,
            assigned_at DATETIME NULL,
            note LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY case_code (case_code),
            KEY customer_id (customer_id),
            KEY service_id (service_id),
            KEY status (status),
            KEY assigned_user_id (assigned_user_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}refunds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            refund_code VARCHAR(40) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            complaint_id BIGINT UNSIGNED NULL,
            requested_amount DECIMAL(18,2) NULL,
            approved_amount DECIMAL(18,2) NULL,
            reason LONGTEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            assigned_user_id BIGINT UNSIGNED NULL,
            payment_reference VARCHAR(120) NULL,
            paid_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY refund_code (refund_code),
            KEY customer_id (customer_id),
            KEY complaint_id (complaint_id),
            KEY status (status)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}attachments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(60) NOT NULL,
            object_id BIGINT UNSIGNED NULL,
            wp_attachment_id BIGINT UNSIGNED NOT NULL,
            file_kind VARCHAR(60) NULL,
            note VARCHAR(255) NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY object_lookup (object_type,object_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}activities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            actor_user_id BIGINT UNSIGNED NULL,
            activity_type VARCHAR(80) NOT NULL,
            object_type VARCHAR(60) NOT NULL,
            object_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY activity_type (activity_type),
            KEY object_lookup (object_type, object_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}deficiency_cases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            case_code VARCHAR(40) NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'INCOMPLETE_NEW',
            reported_amount DECIMAL(18,2) NULL,
            registered_invoice_amount DECIMAL(18,2) NULL,
            difference_amount DECIMAL(18,2) NULL,
            deficiency_supervisor_id BIGINT UNSIGNED NULL,
            deficiency_agent_id BIGINT UNSIGNED NULL,
            finance_supervisor_id BIGINT UNSIGNED NULL,
            finance_agent_id BIGINT UNSIGNED NULL,
            current_department VARCHAR(30) NOT NULL DEFAULT 'deficiency',
            deficiency_result VARCHAR(80) NULL,
            customer_claimed_amount DECIMAL(18,2) NULL,
            confirmed_difference_amount DECIMAL(18,2) NULL,
            deficiency_description LONGTEXT NULL,
            last_contact_outcome VARCHAR(60) NULL,
            follow_up_at DATETIME NULL,
            finance_result VARCHAR(40) NULL,
            finance_result_json LONGTEXT NULL,
            final_decision VARCHAR(40) NULL,
            final_approved_amount DECIMAL(18,2) NULL,
            entered_at DATETIME NULL,
            assigned_at DATETIME NULL,
            first_contact_at DATETIME NULL,
            sent_to_finance_at DATETIME NULL,
            finance_assigned_at DATETIME NULL,
            finance_finished_at DATETIME NULL,
            completed_at DATETIME NULL,
            archived_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY case_code (case_code),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY current_department (current_department),
            KEY deficiency_agent_id (deficiency_agent_id),
            KEY finance_agent_id (finance_agent_id),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}case_actions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            case_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            department VARCHAR(30) NOT NULL,
            action_type VARCHAR(80) NOT NULL,
            from_status VARCHAR(50) NULL,
            to_status VARCHAR(50) NULL,
            user_id BIGINT UNSIGNED NULL,
            description LONGTEXT NULL,
            metadata_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY case_id (case_id),
            KEY customer_id (customer_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}customer_tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            tag VARCHAR(80) NOT NULL,
            case_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY customer_tag (customer_id, tag),
            KEY tag (tag),
            KEY case_id (case_id)
        ) $charset";
        $sql[] = "CREATE TABLE {$p}refund_registers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            invoice_code VARCHAR(80) NOT NULL DEFAULT '',
            refund_status TINYINT(1) NOT NULL DEFAULT 0,
            refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            source_type VARCHAR(20) NOT NULL DEFAULT 'excel',
            source_row INT UNSIGNED NULL,
            batch_code VARCHAR(32) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY batch_code (batch_code),
            KEY created_at (created_at)
        ) $charset";
        // v0.8.64.0 — Phase 2 configuration foundation (complaint subject/outcome catalogs, inquiries).
        $sql = array_merge($sql, self::after_sales_config_tables($p, $charset));

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        // v0.8.62.11: ستون کد فاکتور در refund_registers (برای ردیف‌های موجود ALTER بدون تغییر داده).
        self::ensure_column("{$p}refund_registers", 'invoice_code', "VARCHAR(80) NOT NULL DEFAULT '' AFTER customer_id");
        self::ensure_column("{$p}invoices", 'extra_json', "LONGTEXT NULL");
        self::ensure_column("{$p}customers", 'source_customer_id', "VARCHAR(100) NULL");
        self::ensure_column("{$p}customers", 'sales_manager', "VARCHAR(255) NULL");
        self::ensure_column("{$p}invoices", 'prepayment_approval_status', "VARCHAR(80) NULL");
        self::ensure_column("{$p}invoices", 'prepayment_unapproved', "TINYINT(1) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}invoices", 'exclude_from_total', "TINYINT(1) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}invoices", 'excluded_amount', "DECIMAL(18,2) NULL");
        self::ensure_column("{$p}invoices", 'excluded_reason', "VARCHAR(120) NULL");
        self::ensure_column("{$p}invoices", 'excluded_at', "DATETIME NULL");
        self::ensure_column("{$p}invoices", 'excluded_by', "BIGINT UNSIGNED NULL");
        self::ensure_column("{$p}invoices", 'seller_action_date', "VARCHAR(80) NULL");
        self::ensure_column("{$p}invoices", 'seller_action_year', "SMALLINT UNSIGNED NULL");
        self::ensure_column("{$p}customers", 'assignment_cohort', "VARCHAR(20) NOT NULL DEFAULT 'normal'");
        self::ensure_column("{$p}import_stage_invoices", 'source_customer_id', "VARCHAR(100) NULL");
        self::ensure_column("{$p}customers", 'profile_completeness', "VARCHAR(30) NOT NULL DEFAULT 'pending'");
        self::ensure_column("{$p}customers", 'completion_source', "VARCHAR(40) NOT NULL DEFAULT 'NORMAL'");
        self::ensure_column("{$p}customers", 'journey_state', "VARCHAR(40) NULL");
        self::ensure_column("{$p}customers", 'satisfaction_state', "VARCHAR(40) NULL");
        self::ensure_column("{$p}customers", 'service_credit_total', "DECIMAL(18,2) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}customers", 'service_credit_reserved', "DECIMAL(18,2) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}customers", 'service_credit_consumed', "DECIMAL(18,2) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}customers", 'service_credit_remaining', "DECIMAL(18,2) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}complaints", 'resolution_outcome', "VARCHAR(40) NULL");
        self::ensure_column("{$p}complaints", 'needs_manager_call', "TINYINT(1) NOT NULL DEFAULT 0");
        self::ensure_column("{$p}complaints", 'due_at', "DATETIME NULL");
        self::ensure_column("{$p}complaints", 'owner_user_id', "BIGINT UNSIGNED NULL");
        self::ensure_column("{$p}interactions", 'contact_phone', "VARCHAR(30) NULL");
        self::ensure_column("{$p}interactions", 'outcome', "VARCHAR(80) NULL");
        self::ensure_column("{$p}interactions", 'outcome_key', "VARCHAR(80) NULL");
        self::ensure_column("{$p}interactions", 'satisfaction_status', "VARCHAR(30) NULL");
        self::ensure_column("{$p}interactions", 'request_status', "VARCHAR(40) NULL");
        self::ensure_column("{$p}interactions", 'requested_loan_amount', "DECIMAL(18,2) NULL");
        self::ensure_column("{$p}interactions", 'requested_service', "VARCHAR(255) NULL");
        self::ensure_column("{$p}interactions", 'satisfaction_score', "TINYINT UNSIGNED NULL");
        self::ensure_column("{$p}interactions", 'sales_satisfaction', "TINYINT UNSIGNED NULL");
        self::ensure_column("{$p}interactions", 'followup_satisfaction', "TINYINT UNSIGNED NULL");
        self::ensure_column("{$p}interactions", 'service_quality_satisfaction', "TINYINT UNSIGNED NULL");
        self::ensure_column("{$p}interactions", 'service_timing_satisfaction', "TINYINT UNSIGNED NULL");
        self::ensure_column("{$p}interactions", 'promised_services_received', "TINYINT(1) NULL");
        self::ensure_column("{$p}interactions", 'repurchase_intent', "TINYINT(1) NULL");
        self::ensure_column("{$p}interactions", 'referral_intent', "TINYINT(1) NULL");
        self::ensure_column("{$p}interactions", 'declared_paid', "DECIMAL(18,2) NULL");
        self::ensure_column("{$p}interactions", 'complaint_reason', "LONGTEXT NULL");
        self::ensure_column("{$p}interactions", 'complaint_priority', "VARCHAR(30) NULL");
        self::ensure_column("{$p}interactions", 'next_followup_at', "DATETIME NULL");
        self::ensure_column("{$p}interactions", 'notes', "LONGTEXT NULL");
        self::ensure_column("{$p}deficiency_cases", 'final_approved_amount', "DECIMAL(18,2) NULL");

        self::ensure_index("{$p}customers", 'source_customer_id', 'source_customer_id', true);
        self::ensure_index("{$p}customers", 'assignment_cohort', 'assignment_cohort');
        self::ensure_index("{$p}customers", 'sales_manager', 'sales_manager');
        self::ensure_index("{$p}customers", 'profile_completeness', 'profile_completeness');
        self::ensure_index("{$p}customers", 'completion_source', 'completion_source');
        self::ensure_index("{$p}customers", 'journey_state', 'journey_state');
        self::ensure_index("{$p}customers", 'updated_id', 'updated_at,id');
        self::ensure_index("{$p}invoices", 'seller_action_year', 'seller_action_year');
        self::ensure_index("{$p}invoices", 'customer_invoice', 'customer_id,id');
        self::ensure_index("{$p}phones", 'customer_primary', 'customer_id,is_primary,id');
        self::ensure_index("{$p}assignments", 'agent_status_customer', 'agent_user_id,status,customer_id');
        self::ensure_index("{$p}assignments", 'customer_status_id', 'customer_id,status,id');
        self::ensure_index("{$p}interactions", 'customer_created', 'customer_id,created_at');
        self::ensure_index("{$p}interactions", 'customer_outcome_created', 'customer_id,outcome_key,created_at');
        self::ensure_index("{$p}complaints", 'customer_status_owner', 'customer_id,status,owner_user_id');
        self::ensure_index("{$p}financial_cases", 'customer_status_assignee', 'customer_id,status,assigned_user_id');
        self::ensure_index("{$p}service_cases", 'customer_status_assignee', 'customer_id,status,assigned_user_id');
        self::ensure_index("{$p}refunds", 'customer_status_assignee', 'customer_id,status,assigned_user_id');
        self::ensure_index("{$p}activities", 'customer_activity_id', 'customer_id,id');
        self::ensure_index("{$p}deficiency_cases", 'customer_status_department', 'customer_id,status,current_department');
        self::ensure_index("{$p}deficiency_cases", 'department_status_agent', 'current_department,status,deficiency_agent_id,finance_agent_id');
        self::ensure_index("{$p}deficiency_cases", 'department_status_created', 'current_department,status,created_at');
        self::ensure_index("{$p}case_actions", 'case_created', 'case_id,created_at');

        // Repair legacy duplicate active assignments before adding the database-level guard.
        // Exactly one active assignment is allowed per customer. Historical rows are preserved.
        self::repair_duplicate_active_assignments($p);
        self::ensure_active_assignment_guard($p);

        $wpdb->suppress_errors($previous_suppression);

        // v0.8.63.0 (S4/L2): activation/upgrade NEVER creates, recreates, re-enables, renames or
        // re-roles users and NEVER sets passwords — the former default-staff seeders are removed.
        // Services/units are only inserted once by stable code, never overwritten or reactivated.
        self::ensure_roles_and_caps();
        self::ensure_service_unit_hierarchy_schema($p);
        self::ensure_after_sales_config_schema($p);
        self::seed_default_services($p);
        try {
            self::seed_after_sales_units($p);
        } catch (Throwable $e) {
            error_log('[biawin-crm] after-sales unit seed failed: ' . $e->getMessage());
        }
        try {
            self::seed_legacy_complaint_outcomes($p);
        } catch (Throwable $e) {
            error_log('[biawin-crm] complaint outcome seed failed: ' . $e->getMessage());
        }
        self::ensure_portal_page();
        self::ensure_reconciliation_tolerance();
        self::backfill_deficiency_cases($p);
        update_option('biawin_crm_db_version', BIAWIN_CRM_VERSION);
    }

    private static function repair_duplicate_active_assignments(string $p): void
    {
        global $wpdb;
        $assignments = "{$p}assignments";
        $activities = "{$p}activities";
        $customers = "{$p}customers";

        $customer_ids = array_map('intval', $wpdb->get_col(
            "SELECT customer_id FROM $assignments WHERE status='assigned' GROUP BY customer_id HAVING COUNT(*) > 1"
        ));
        if (!$customer_ids) return;

        $fixed = 0;
        foreach ($customer_ids as $customer_id) {
            $wpdb->query('START TRANSACTION');
            $locked = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE id=%d FOR UPDATE", $customer_id));
            if (!$locked) { $wpdb->query('ROLLBACK'); continue; }

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.id,a.agent_user_id,a.assigned_at,
                    (SELECT COUNT(*) FROM $activities act WHERE act.customer_id=a.customer_id AND act.actor_user_id=a.agent_user_id AND act.created_at>=a.assigned_at) activity_count,
                    (SELECT MAX(act.created_at) FROM $activities act WHERE act.customer_id=a.customer_id AND act.actor_user_id=a.agent_user_id AND act.created_at>=a.assigned_at) last_activity_at
                 FROM $assignments a
                 WHERE a.customer_id=%d AND a.status='assigned'
                 ORDER BY a.id ASC FOR UPDATE",
                $customer_id
            ));
            if (count($rows) < 2) { $wpdb->query('COMMIT'); continue; }

            usort($rows, static function($a, $b) {
                $a_last = (string)($a->last_activity_at ?? '');
                $b_last = (string)($b->last_activity_at ?? '');
                if ($a_last !== $b_last) return strcmp($b_last, $a_last); // newest real activity wins
                $a_count = (int)$a->activity_count; $b_count = (int)$b->activity_count;
                if ($a_count !== $b_count) return $b_count <=> $a_count;
                $a_assigned = (string)($a->assigned_at ?? '');
                $b_assigned = (string)($b->assigned_at ?? '');
                if ($a_assigned !== $b_assigned) return strcmp($b_assigned, $a_assigned);
                return (int)$b->id <=> (int)$a->id;
            });

            $keep = array_shift($rows);
            $close_ids = array_map(static fn($r)=>(int)$r->id, $rows);
            if ($close_ids) {
                $placeholders = implode(',', array_fill(0, count($close_ids), '%d'));
                $args = array_merge([Biawin_CRM_DB::now()], $close_ids);
                $sql = "UPDATE $assignments SET status='duplicate_closed', completed_at=%s WHERE id IN ($placeholders) AND status='assigned'";
                $ok = $wpdb->query($wpdb->prepare($sql, ...$args));
                if ($ok === false) { $wpdb->query('ROLLBACK'); continue; }
            }
            $wpdb->query('COMMIT');
            $fixed++;

            if (class_exists('Biawin_CRM_DB')) {
                Biawin_CRM_DB::log('customer', $customer_id, 'duplicate_assignments_repaired', null, [
                    'kept_assignment_id'=>(int)$keep->id,
                    'kept_agent_user_id'=>(int)$keep->agent_user_id,
                    'closed_assignment_ids'=>$close_ids,
                    'rule'=>'latest_real_activity_then_count_then_assignment_time',
                ]);
            }
            if (class_exists('Biawin_CRM_Workflow_Service')) {
                Biawin_CRM_Workflow_Service::activity($customer_id, 'duplicate_assignments_repaired', 'تخصیص‌های هم‌زمان تکراری اصلاح شدند و فقط مالک معتبر پرونده فعال ماند.', 'customer', $customer_id, [
                    'kept_assignment_id'=>(int)$keep->id,
                    'closed_assignment_ids'=>$close_ids,
                ]);
            }
        }
        update_option('bcrm_assignment_repair_086', ['fixed_customers'=>$fixed,'ran_at'=>Biawin_CRM_DB::now()], false);
    }

    private static function backfill_deficiency_cases(string $p): void
    {
        global $wpdb;
        if (!class_exists('Biawin_CRM_Deficiency_Workflow')) return;

        $customers = "{$p}customers";
        $cases = "{$p}deficiency_cases";
        $tolerance = max(500000, (float) get_option('biawin_crm_reconciliation_tolerance', 500000));
        $batch_size = 200;
        $max_batches = 25;
        $created = 0;

        for ($batch = 0; $batch < $max_batches; $batch++) {
            $customer_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT c.id
                 FROM $customers c
                 WHERE c.profile_completeness=%s
                   AND ABS(COALESCE(c.discrepancy_amount, COALESCE(c.declared_paid, 0) - COALESCE(c.total_paid, 0))) > %f
                   AND NOT EXISTS (
                       SELECT 1 FROM $cases d
                       WHERE d.customer_id=c.id
                         AND d.status NOT IN ('COMPLETED','ARCHIVED')
                   )
                 ORDER BY c.id ASC
                 LIMIT %d",
                'incomplete',
                $tolerance,
                $batch_size
            )));
            if (!$customer_ids) break;

            foreach ($customer_ids as $customer_id) {
                $case_id = Biawin_CRM_Deficiency_Workflow::ensure_case_for_customer($customer_id);
                if ($case_id) {
                    Biawin_CRM_Deficiency_Workflow::action($case_id, $customer_id, 'deficiency', 'DEFICIENCY_CASE_BACKFILLED', null, 'INCOMPLETE_NEW', []);
                    $created++;
                }
            }

            if (count($customer_ids) < $batch_size) break;
        }

        update_option('biawin_crm_deficiency_backfill_last_run', [
            'created' => $created,
            'remaining' => (int) $wpdb->get_var(
                "SELECT COUNT(*)
                 FROM $customers c
                 WHERE c.profile_completeness='incomplete'
                   AND ABS(COALESCE(c.discrepancy_amount, COALESCE(c.declared_paid, 0) - COALESCE(c.total_paid, 0))) > " . (float) $tolerance . "
                   AND NOT EXISTS (
                       SELECT 1 FROM $cases d
                       WHERE d.customer_id=c.id
                         AND d.status NOT IN ('COMPLETED','ARCHIVED')
                   )"
            ),
            'ran_at' => Biawin_CRM_DB::now(),
        ], false);
    }

    private static function ensure_reconciliation_tolerance(): void
    {
        $current = (float) get_option('biawin_crm_reconciliation_tolerance', 0);
        if ($current < 500000) {
            update_option('biawin_crm_reconciliation_tolerance', 500000, false);
        }
    }

    private static function ensure_active_assignment_guard(string $p): void
    {
        global $wpdb;
        $table = "{$p}assignments";
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'active_customer_guard'));
        if (!$column) {
            // NULL values are allowed multiple times in a UNIQUE index, while an active assignment exposes customer_id.
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `active_customer_guard` BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `status`='assigned' THEN `customer_id` ELSE NULL END) STORED");
        }
        $index = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM `$table` WHERE Key_name=%s", 'one_active_assignment_per_customer'));
        if (!$index) {
            $wpdb->query("ALTER TABLE `$table` ADD UNIQUE KEY `one_active_assignment_per_customer` (`active_customer_guard`)");
        }
    }

    private static function ensure_column(string $table, string $column, string $definition): void
    {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $column));
        if (!$exists) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    private static function ensure_index(string $table, string $index, string $columns, bool $unique = false): void
    {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM `$table` WHERE Key_name=%s", $index));
        if (!$exists) {
            $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
            $wpdb->query("ALTER TABLE `$table` ADD $kind `$index` ($columns)");
        }
    }

    /**
     * v0.8.63.0 (L2) — the default service catalog is seeded ONCE per stable service_code:
     * - a code that already exists is NEVER touched (name, credit_cost, unit_key, is_active,
     *   sort_order, description all stay exactly as the admin left them);
     * - a code the plugin seeded before and an admin deleted is NOT re-inserted (seed ledger);
     * - legacy rows are no longer force-deactivated on every update;
     * - no service-unit supervisors/users are created and no unit is reconfigured.
     * Previously every activate()/upgrade UPDATEd all 8 defaults back to hard-coded values,
     * re-deactivated legacy codes, reset each default unit's name/supervisor/active flag and
     * created supervisor logins with a hard-coded password.
     */
    private static function seed_default_services(string $prefix): void
    {
        global $wpdb;
        $table = $prefix . 'service_catalog';
        $units = $prefix . 'service_units';
        $now = current_time('mysql');
        $ledger = get_option(self::SERVICE_SEED_LEDGER_OPTION, []);
        if (!is_array($ledger)) $ledger = [];
        $unit_ledger = get_option(self::UNIT_SEED_LEDGER_OPTION, []);
        if (!is_array($unit_ledger)) $unit_ledger = [];

        $defaults = [
            // code, name, credit_cost, unit_key, unit display name
            ['furniture-loan-score', 'امتیاز وام مبل', 200000000, 'furniture_loan'],
            ['bedding-loan-score', 'امتیاز وام کالای خواب و تشک', 50000000, 'bedding_loan'],
            ['motorcycle-loan-score', 'امتیاز وام خرید موتور', 800000000, 'motorcycle_loan'],
            ['clothing-loan-score', 'امتیاز وام پوشاک', 50000000, 'clothing_loan'],
            ['retirement-loan-score', 'امتیاز وام بازنشستگی', 300000000, 'retirement_loan'],
            ['resalat-cash-loan-score', 'امتیاز وام رسالت (نقدی)', 200000000, 'resalat_cash_loan'],
            ['free-cosmetics', 'ارسال کالای آرایشی رایگان', 10000000, 'free_cosmetics'],
            ['life-insurance', 'بیمه عمر', 250000000, 'life_insurance'],
        ];
        foreach ($defaults as $i => [$code, $name, $cost, $unit_key]) {
            $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE service_code=%s", $code));
            if (!$exists && empty($ledger[$code])) {
                $wpdb->insert($table, ['service_code'=>$code, 'name'=>$name, 'credit_cost'=>$cost, 'unit_key'=>$unit_key, 'is_active'=>1, 'sort_order'=>($i + 1) * 10, 'created_at'=>$now, 'updated_at'=>$now]);
            }
            $ledger[$code] = true;

            $unit_exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `$units` WHERE unit_key=%s", $unit_key));
            if (!$unit_exists && empty($unit_ledger[$unit_key])) {
                $wpdb->insert($units, ['unit_key'=>$unit_key, 'unit_name'=>'کارتابل ' . $name, 'supervisor_user_id'=>null, 'is_active'=>1, 'created_at'=>$now, 'updated_at'=>$now]);
            }
            $unit_ledger[$unit_key] = true;
        }
        update_option(self::SERVICE_SEED_LEDGER_OPTION, $ledger, false);
        update_option(self::UNIT_SEED_LEDGER_OPTION, $unit_ledger, false);
    }

    private static function ensure_portal_page(): void
    {
        $existing = (int) get_option('biawin_crm_portal_page_id', 0);
        if ($existing && get_post($existing)) {
            return;
        }
        $by_path = get_page_by_path('biawin-crm-portal');
        if ($by_path) {
            update_option('biawin_crm_portal_page_id', (int) $by_path->ID);
            return;
        }
        $id = wp_insert_post([
            'post_title' => 'پرتال CRM بیاوین',
            'post_name' => 'biawin-crm-portal',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '[biawin_crm_portal]',
        ], true);
        if (!is_wp_error($id)) {
            update_option('biawin_crm_portal_page_id', (int) $id);
        }
    }

}
