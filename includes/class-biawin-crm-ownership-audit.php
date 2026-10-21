<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Ownership_Audit
{
    public static function event(int $case_id, string $event, array $meta = [], ?int $customer = null, ?string $type = null, ?int $asset = null): void {
        Biawin_CRM_Ownership_Repository::insert('ownership_events', [
            'case_id'=>$case_id, 'customer_id'=>$customer, 'asset_type'=>$type, 'asset_id'=>$asset,
            'event_type'=>$event, 'actor_user_id'=>get_current_user_id() ?: null,
            'meta_json'=>Biawin_CRM_Ownership_Repository::json($meta), 'created_at'=>Biawin_CRM_DB::now(),
        ]);
    }
    public static function timeline(object $case, string $event): void {
        foreach ([$case->current_owner_customer_id, $case->claimant_customer_id] as $customer) {
            Biawin_CRM_Ownership_Repository::insert('activities', ['customer_id'=>(int)$customer,
                'activity_type'=>$event, 'title'=>$event, 'object_type'=>'ownership_case', 'object_id'=>(int)$case->id,
                'actor_user_id'=>get_current_user_id() ?: null, 'meta_json'=>Biawin_CRM_Ownership_Repository::json(['case_code'=>$case->case_code]), 'created_at'=>Biawin_CRM_DB::now()]);
        }
    }
}
