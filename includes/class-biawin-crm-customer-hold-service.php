<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Customer_Hold_Service
{
    public static function is_held(int $customer): bool {
        if (!$customer) return false;
        global $wpdb;
        return (bool)Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('customer_holds') . " WHERE customer_id=%d AND status='ACTIVE' LIMIT 1" . (Biawin_CRM_Ownership_Repository::in_transaction() ? ' FOR UPDATE' : ''), $customer));
    }
    public static function assert_mutable(int $customer): void {
        if (!$customer || Biawin_CRM_Ownership_Transfer_Service::permits_customer($customer)) return;
        if (self::is_held($customer)) throw new RuntimeException('Customer is read-only during ownership review.');
    }
    public static function apply(object $case): void {
        foreach ([$case->current_owner_customer_id, $case->claimant_customer_id] as $customer) {
            Biawin_CRM_Ownership_Repository::insert('customer_holds', ['customer_id'=>(int)$customer,'case_id'=>(int)$case->id,
                'hold_type'=>'OWNERSHIP_REVIEW','status'=>'ACTIVE','created_by'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now()]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id, 'PROFILE_HOLD_APPLIED', [], (int)$customer);
        }
    }
    public static function verify(object $case): void {
        global $wpdb;
        foreach ([$case->current_owner_customer_id, $case->claimant_customer_id] as $customer) {
            $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('customer_holds') . " WHERE customer_id=%d AND case_id=%d AND status='ACTIVE' FOR UPDATE", $customer, $case->id));
            if (count($rows) !== 1) throw new RuntimeException('Ownership hold is missing or duplicated.');
        }
    }
    public static function release(object $case, string $reason): void {
        self::verify($case);
        foreach ([$case->current_owner_customer_id, $case->claimant_customer_id] as $customer) {
            Biawin_CRM_Ownership_Repository::update('customer_holds', ['status'=>'RELEASED','released_by'=>get_current_user_id(),'released_at'=>Biawin_CRM_DB::now(),'release_reason'=>$reason], ['case_id'=>(int)$case->id,'customer_id'=>(int)$customer,'status'=>'ACTIVE']);
            Biawin_CRM_Ownership_Audit::event((int)$case->id, 'PROFILE_HOLD_RELEASED', ['reason'=>$reason], (int)$customer);
        }
    }
}
