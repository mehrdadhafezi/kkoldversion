<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Ownership_Transfer_Service
{
    private static $context = null;
    public static function permits_customer(int $id): bool { return self::$context && in_array($id,[(int)self::$context->current_owner_customer_id,(int)self::$context->claimant_customer_id],true); }
    public static function permits_asset(string $type, int $id): bool {
        if (!self::$context) return false;
        foreach (Biawin_CRM_Ownership_Repository::assets((int)self::$context->id) as $asset) if ($asset->asset_type === $type && (int)$asset->asset_id === $id) return true;
        return false;
    }
    private static function authorize(object $case): void {
        Biawin_CRM_Ownership_Authorization::scope($case,'approve_normal_ownership_transfer');
        if ($case->decision !== 'TRANSFER_APPROVED') throw new RuntimeException('Approved transfer required.');
    }
    public static function execute(int $id, int $version): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) {
            global $wpdb;
            self::authorize($case);
            Biawin_CRM_Ownership_Workflow::state($case,['DECIDED']);
            if ($case->execution_state !== 'NOT_STARTED') throw new RuntimeException('Transfer already applied; use resume.');
            $case = Biawin_CRM_Ownership_Risk_Service::refresh($case,Biawin_CRM_Ownership_OTP::expired_risk($case));
            if ($case->risk_level === 'HIGH_RISK' && ($case->supervisor_approval_status !== 'APPROVED' || !$case->supervisor_user_id || (int)$case->supervisor_user_id === (int)$case->decided_by_user_id)) throw new RuntimeException('Independent supervisor approval required.');
            Biawin_CRM_Customer_Hold_Service::verify($case);
            Biawin_CRM_Ownership_Workflow::verify_evidence($case);
            $assets = Biawin_CRM_Ownership_Repository::assets((int)$case->id);
            $plans = [];
            foreach ($assets as $asset) {
                if (Biawin_CRM_Ownership_Repository::conflicting((int)$case->id,$asset->asset_type,(int)$asset->asset_id)) throw new RuntimeException('Conflicting active ownership case.');
                $row = Biawin_CRM_Ownership_Repository::row($asset->asset_type === 'INVOICE' ? 'invoices' : 'phones',(int)$asset->asset_id,true);
                $snapshot = Biawin_CRM_Ownership_Repository::decode($asset->snapshot_json);
                if ((int)$row->customer_id !== (int)$case->current_owner_customer_id || (int)($snapshot['customer_id'] ?? 0) !== (int)$row->customer_id) throw new RuntimeException('Stale asset ownership snapshot.');
                if ($asset->asset_type === 'INVOICE') {
                    if ($snapshot['phone'] !== Biawin_CRM_Invoice_Phone_Service::current($row) || (float)$snapshot['amount_paid'] !== (float)$row->amount_paid) throw new RuntimeException('Stale invoice snapshot.');
                    $services = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,invoice_id,customer_id,service_name FROM ' . Biawin_CRM_DB::table('services') . ' WHERE invoice_id=%d ORDER BY id FOR UPDATE',$row->id));
                    if (Biawin_CRM_Ownership_Repository::json($services) !== Biawin_CRM_Ownership_Repository::json($snapshot['services'])) throw new RuntimeException('Stale invoice services snapshot.');
                    $plans[(int)$asset->id] = Biawin_CRM_Invoice_Phone_Service::plan($case,$asset);
                } elseif ($snapshot['phone'] !== $row->normalized_phone) throw new RuntimeException('Stale phone snapshot.');
            }
            self::$context = $case;
            try {
                foreach ($assets as $asset) {
                    if (($asset->final_action ?: $asset->requested_action) !== 'TRANSFER') continue;
                    $type = $asset->asset_type;
                    $asset_id = (int)$asset->asset_id;
                    $source = (int)$case->current_owner_customer_id;
                    $target = (int)$case->claimant_customer_id;
                    Biawin_CRM_Ownership_Repository::update($type === 'INVOICE' ? 'invoices' : 'phones',['customer_id'=>$target],['id'=>$asset_id,'customer_id'=>$source]);
                    Biawin_CRM_Ownership_Audit::event((int)$case->id,$type === 'INVOICE' ? 'INVOICE_OWNER_CHANGED' : 'PHONE_OWNER_CHANGED',['before'=>$source,'after'=>$target],null,$type,$asset_id);
                    if ($type === 'INVOICE') {
                        Biawin_CRM_Invoice_Phone_Service::apply($case,$asset,$plans[(int)$asset->id]);
                        foreach (Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,customer_id FROM ' . Biawin_CRM_DB::table('services') . ' WHERE invoice_id=%d FOR UPDATE',$asset_id)) as $service) {
                            Biawin_CRM_Ownership_Repository::update('services',['customer_id'=>$target],['id'=>(int)$service->id]);
                            Biawin_CRM_Ownership_Audit::event((int)$case->id,'SERVICE_OWNER_ALIGNED',['service_id'=>(int)$service->id,'before'=>$service->customer_id,'after'=>$target],null,'INVOICE',$asset_id);
                        }
                    }
                    Biawin_CRM_Ownership_Lock_Service::move($type,$asset_id,$source,$target,(int)$case->id);
                }
                Biawin_CRM_Ownership_Repository::update('ownership_cases',['execution_state'=>'COMMITTED','executed_at'=>Biawin_CRM_DB::now(),'execution_error'=>null],['id'=>(int)$case->id]);
                Biawin_CRM_Ownership_Audit::event((int)$case->id,'TRANSFER_COMMITTED');
            } finally { self::$context = null; }
        });
        self::resume($id,$version+1);
    }
    public static function resume(int $id, int $version): void {
        // Gate spans the post-commit sequence; each checkpoint and its recalculation
        // are transactional. Ownership is never reapplied during recovery.
        Biawin_CRM_Ownership_Repository::gate(static function () use ($id,$version) {
            $case = Biawin_CRM_Ownership_Repository::row('ownership_cases',$id);
            self::authorize($case);
            if ((int)$case->lock_version !== $version) throw new RuntimeException('Stale recovery version.');
            if ($case->status === 'CLOSED' && $case->execution_state === 'FINALIZED') return;
            Biawin_CRM_Ownership_Workflow::state($case,['DECIDED']);
            if (!in_array($case->execution_state,['COMMITTED','FINALIZATION_FAILED'],true)) throw new RuntimeException('No committed transfer to resume.');
            try {
                foreach (['source'=>(int)$case->current_owner_customer_id,'target'=>(int)$case->claimant_customer_id] as $step=>$customer) {
                    Biawin_CRM_Ownership_Repository::atomic(static function () use ($id,$step,$customer) {
                        $current = Biawin_CRM_Ownership_Repository::row('ownership_cases',$id,true);
                        Biawin_CRM_Customer_Hold_Service::verify($current);
                        $progress = Biawin_CRM_Ownership_Repository::decode($current->finalization_json);
                        if (!empty($progress[$step])) return;
                        self::$context = $current;
                        try {
                            if (!Biawin_CRM_Customer_Service::recalculate_customer($customer)) throw new RuntimeException('Profile recalculation failed: ' . $customer);
                        } finally { self::$context = null; }
                        $profile = Biawin_CRM_Ownership_Repository::row('customers',$customer);
                        Biawin_CRM_Ownership_Audit::event($id,'PROFILE_RECALCULATED',['step'=>$step,'profile_completeness'=>$profile->profile_completeness],$customer);
                        if ($profile->profile_completeness === 'incomplete') Biawin_CRM_Ownership_Audit::event($id,'DEFICIENCY_TRIGGERED',['via'=>'existing_recalculation'],$customer);
                        $progress[$step] = true;
                        Biawin_CRM_Ownership_Repository::update('ownership_cases',['finalization_json'=>Biawin_CRM_Ownership_Repository::json($progress)],['id'=>$id]);
                    });
                }
                Biawin_CRM_Ownership_Repository::atomic(static function () use ($id) {
                    $current = Biawin_CRM_Ownership_Repository::row('ownership_cases',$id,true);
                    Biawin_CRM_Customer_Hold_Service::release($current,'FINALIZED');
                    Biawin_CRM_Ownership_Repository::update('ownership_cases',['status'=>'CLOSED','execution_state'=>'FINALIZED','execution_error'=>null,'closed_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now(),'lock_version'=>(int)$current->lock_version+1],['id'=>$id]);
                    Biawin_CRM_Ownership_Audit::event($id,'CASE_CLOSED');
                    Biawin_CRM_Ownership_Audit::timeline($current,'OWNERSHIP_CASE_CLOSED');
                });
            } catch (Throwable $e) {
                Biawin_CRM_Ownership_Repository::atomic(static function () use ($id,$e) {
                    $current = Biawin_CRM_Ownership_Repository::row('ownership_cases',$id,true);
                    Biawin_CRM_Ownership_Repository::update('ownership_cases',['execution_state'=>'FINALIZATION_FAILED','execution_error'=>substr($e->getMessage(),0,2000),'updated_at'=>Biawin_CRM_DB::now(),'lock_version'=>(int)$current->lock_version+1],['id'=>$id]);
                    Biawin_CRM_Ownership_Audit::event($id,'CASE_EXECUTION_FAILED',['phase'=>'post_commit','error'=>substr($e->getMessage(),0,2000)]);
                });
                throw $e;
            }
        });
    }
}
