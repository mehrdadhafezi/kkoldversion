<?php

if (!defined('ABSPATH')) { exit; }

/** C1 DDL only. No backfill, locks, holds or ownership mutations. */
class Biawin_CRM_Ownership_Schema
{
    public static function invoice_columns(): array
    {
        return [
            'canonical_phone_normalized' => 'VARCHAR(30) NULL',
            'canonical_phone_source' => 'VARCHAR(30) NULL',
            'canonical_phone_updated_at' => 'DATETIME NULL',
            'canonical_phone_case_id' => 'BIGINT UNSIGNED NULL',
        ];
    }

    public static function tables(string $p, string $charset): array
    {
        return [
            "CREATE TABLE {$p}ownership_cases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_code VARCHAR(40) NOT NULL,
                current_owner_customer_id BIGINT UNSIGNED NOT NULL,
                claimant_customer_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(40) NOT NULL,
                claim_reason VARCHAR(120) NULL,
                request_note LONGTEXT NULL,
                requested_by_user_id BIGINT UNSIGNED NOT NULL,
                assigned_to_user_id BIGINT UNSIGNED NULL,
                assigned_by_user_id BIGINT UNSIGNED NULL,
                risk_level VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
                risk_reasons_json LONGTEXT NULL,
                risk_evaluated_at DATETIME NULL,
                decision VARCHAR(40) NULL,
                decision_method VARCHAR(60) NULL,
                decision_note LONGTEXT NULL,
                decided_by_user_id BIGINT UNSIGNED NULL,
                decided_at DATETIME NULL,
                supervisor_approval_status VARCHAR(20) NOT NULL DEFAULT 'NOT_REQUIRED',
                supervisor_user_id BIGINT UNSIGNED NULL,
                supervisor_note LONGTEXT NULL,
                supervisor_decided_at DATETIME NULL,
                submitted_at DATETIME NULL,
                assigned_at DATETIME NULL,
                review_started_at DATETIME NULL,
                closed_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                lock_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY  (id),
                UNIQUE KEY case_code (case_code),
                KEY status (status),
                KEY risk_status (risk_level,status),
                KEY current_owner_status (current_owner_customer_id,status),
                KEY claimant_status (claimant_customer_id,status),
                KEY assignee_status (assigned_to_user_id,status),
                KEY supervisor_status (supervisor_approval_status,status)
            ) $charset",
            "CREATE TABLE {$p}ownership_case_assets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id BIGINT UNSIGNED NOT NULL,
                asset_type VARCHAR(20) NOT NULL,
                asset_id BIGINT UNSIGNED NOT NULL,
                source_customer_id BIGINT UNSIGNED NOT NULL,
                target_customer_id BIGINT UNSIGNED NOT NULL,
                requested_action VARCHAR(40) NOT NULL,
                requested_phone_resolution VARCHAR(40) NULL,
                requested_phone_id BIGINT UNSIGNED NULL,
                requested_phone_normalized VARCHAR(30) NULL,
                final_action VARCHAR(40) NULL,
                final_phone_resolution VARCHAR(40) NULL,
                final_phone_id BIGINT UNSIGNED NULL,
                final_phone_normalized VARCHAR(30) NULL,
                specialist_override_reason LONGTEXT NULL,
                snapshot_json LONGTEXT NOT NULL,
                decision VARCHAR(40) NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY case_asset (case_id,asset_type,asset_id),
                KEY asset (asset_type,asset_id),
                KEY source_customer_id (source_customer_id),
                KEY target_customer_id (target_customer_id)
            ) $charset",
            "CREATE TABLE {$p}ownership_case_amendments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id BIGINT UNSIGNED NOT NULL,
                amendment_type VARCHAR(40) NOT NULL,
                reason LONGTEXT NOT NULL,
                before_snapshot_json LONGTEXT NOT NULL,
                after_snapshot_json LONGTEXT NOT NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY case_id (case_id)
            ) $charset",
            "CREATE TABLE {$p}ownership_locks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(20) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                lock_reason VARCHAR(50) NOT NULL,
                locked_by_event VARCHAR(80) NULL,
                locked_at DATETIME NOT NULL,
                released_at DATETIME NULL,
                released_by_case_id BIGINT UNSIGNED NULL,
                release_reason VARCHAR(80) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY entity_active (entity_type,entity_id,released_at),
                KEY customer_active (customer_id,released_at)
            ) $charset",
            "CREATE TABLE {$p}customer_holds (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_id BIGINT UNSIGNED NOT NULL,
                hold_type VARCHAR(40) NOT NULL DEFAULT 'OWNERSHIP_REVIEW',
                case_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                released_by BIGINT UNSIGNED NULL,
                released_at DATETIME NULL,
                release_reason VARCHAR(120) NULL,
                PRIMARY KEY  (id),
                KEY customer_status (customer_id,status),
                KEY case_status (case_id,status)
            ) $charset",
            "CREATE TABLE {$p}ownership_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NULL,
                asset_type VARCHAR(20) NULL,
                asset_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(80) NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                meta_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY case_event (case_id,id),
                KEY customer_created (customer_id,created_at),
                KEY asset_created (asset_type,asset_id,created_at),
                KEY event_created (event_type,created_at)
            ) $charset",
            "CREATE TABLE {$p}ownership_otp_challenges (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id BIGINT UNSIGNED NOT NULL,
                phone_asset_id BIGINT UNSIGNED NULL,
                customer_id BIGINT UNSIGNED NULL,
                phone_normalized VARCHAR(30) NOT NULL,
                purpose VARCHAR(50) NOT NULL,
                otp_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
                sent_at DATETIME NOT NULL,
                verified_at DATETIME NULL,
                consumed_at DATETIME NULL,
                provider_meta_json LONGTEXT NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY case_id (case_id)
            ) $charset",
        ];
    }

    /** Throws on DDL failure so the existing upgrade runner can retry safely. */
    public static function ensure(): void
    {
        global $wpdb;
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $previous = $wpdb->suppress_errors(true);
        try {
            foreach (self::tables($wpdb->prefix . 'bcrm_', $wpdb->get_charset_collate()) as $statement) {
                $wpdb->last_error = '';
                dbDelta($statement);
                if ($wpdb->last_error !== '') {
                    throw new RuntimeException('Ownership table schema: ' . $wpdb->last_error);
                }
                // dbDelta returns descriptions, not a success flag. An earlier failed
                // ALTER may be followed by a successful query that clears last_error.
                preg_match('/CREATE TABLE (\S+)/', $statement, $match);
                $table = $match[1];
                $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`");
                $indexes = $wpdb->get_col("SHOW INDEX FROM `$table`", 2);
                preg_match_all('/^\s+(\w+) (?:BIGINT|SMALLINT|VARCHAR|LONGTEXT|DATETIME)\b/m', $statement, $fields);
                preg_match_all('/(?:UNIQUE )?KEY (\w+) \(/', $statement, $keys);
                if (array_diff($fields[1], (array)$columns) || array_diff(array_merge(['PRIMARY'], $keys[1]), (array)$indexes)) {
                    throw new RuntimeException('Incomplete ownership schema: ' . $table);
                }
            }
            $table = Biawin_CRM_DB::table('invoices');
            foreach (self::invoice_columns() as $column => $definition) {
                $wpdb->last_error = '';
                $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` WHERE Field = %s", $column));
                if ($wpdb->last_error !== '') {
                    throw new RuntimeException('Ownership column inspection: ' . $wpdb->last_error);
                }
                if (!$exists && $wpdb->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition") === false) {
                    throw new RuntimeException('Ownership invoice schema: ' . $wpdb->last_error);
                }
            }
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }
}
