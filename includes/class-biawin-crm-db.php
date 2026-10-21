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
