<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_DB
{
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'bcrm_' . $name;
    }

    public static function now(): string
    {
        return current_time('mysql');
    }

    public static function assert_customer_mutable(int $customer_id): void {
        if (class_exists('Biawin_CRM_Customer_Hold_Service') && version_compare((string)get_option('biawin_crm_db_version',''),'0.8.65.19','>=')) Biawin_CRM_Customer_Hold_Service::assert_mutable($customer_id);
    }

    /** Read surfaces must remain available without their legacy refresh writes while held. */
    public static function read_refresh(int $customer_id, callable $refresh) {
        if (!class_exists('Biawin_CRM_Customer_Hold_Service') || version_compare((string)get_option('biawin_crm_db_version',''),'0.8.65.19','<')) return $refresh();
        return Biawin_CRM_Ownership_Repository::gate(static function () use ($customer_id,$refresh) {
            if (Biawin_CRM_Customer_Hold_Service::is_held($customer_id)) return null;
            return $refresh();
        });
    }

    /** Existing write signatures/return values are retained; only ownership guards are additive. */
    public static function write(string $method, ...$args) {
        global $wpdb;
        if (!in_array($method,['insert','update','delete','replace','query'],true)) throw new InvalidArgumentException('Unsupported DB operation.');
        if (!class_exists('Biawin_CRM_Ownership_Write_Guard') || version_compare((string)get_option('biawin_crm_db_version',''),'0.8.65.19','<')) return $wpdb->$method(...$args);
        return Biawin_CRM_Ownership_Write_Guard::write($method,$args);
    }

    public static function log(string $object_type, ?int $object_id, string $action, $before = null, $after = null): void
    {
        global $wpdb;
        $wpdb->insert(self::table('audit_log'), [
            'actor_user_id' => get_current_user_id() ?: null,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'action' => $action,
            'before_json' => $before === null ? null : wp_json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => $after === null ? null : wp_json_encode($after, JSON_UNESCAPED_UNICODE),
            'created_at' => self::now(),
        ]);
    }
}
