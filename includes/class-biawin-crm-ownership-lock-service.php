<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Ownership_Lock_Service
{
    public static function assert_can_change_invoice_owner(int $id): void { self::assert_unlocked('INVOICE', $id); }
    public static function assert_can_change_invoice_binding(int $id): void { self::assert_unlocked('INVOICE', $id); }
    public static function assert_can_change_phone_owner(int $id): void { self::assert_unlocked('PHONE', $id); }
    private static function assert_unlocked(string $type, int $id): void {
        global $wpdb;
        $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('ownership_locks') . ' WHERE entity_type=%s AND entity_id=%d AND released_at IS NULL LIMIT 1' . (Biawin_CRM_Ownership_Repository::in_transaction() ? ' FOR UPDATE' : ''), $type, $id));
        if ($rows && !Biawin_CRM_Ownership_Transfer_Service::permits_asset($type, $id)) throw new RuntimeException('Asset ownership is locked; use Ownership Reconciliation.');
    }
    public static function create(string $type, int $id, int $customer, int $case_id = 0): void {
        global $wpdb;
        $locks = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('ownership_locks') . ' WHERE entity_type=%s AND entity_id=%d AND released_at IS NULL FOR UPDATE', $type, $id));
        if ($locks) {
            if (count($locks) !== 1 || (int)$locks[0]->customer_id !== $customer) throw new RuntimeException('Conflicting ownership lock.');
            return;
        }
        Biawin_CRM_Ownership_Repository::insert('ownership_locks', ['entity_type'=>$type,'entity_id'=>$id,'customer_id'=>$customer,
            'lock_reason'=>$case_id ? 'OWNERSHIP_TRANSFER_APPROVED' : 'PROFILE_COMPLETED','locked_by_event'=>$case_id ? 'TRANSFER_APPROVED' : 'PROFILE_COMPLETED',
            'locked_at'=>Biawin_CRM_DB::now(),'created_at'=>Biawin_CRM_DB::now()]);
        Biawin_CRM_Ownership_Audit::event($case_id, 'OWNERSHIP_LOCK_CREATED', [], $customer, $type, $id);
    }
    public static function move(string $type, int $id, int $source, int $target, int $case_id): void {
        global $wpdb;
        $locks = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('ownership_locks') . ' WHERE entity_type=%s AND entity_id=%d AND released_at IS NULL FOR UPDATE', $type, $id));
        foreach ($locks as $lock) {
            if ((int)$lock->customer_id !== $source || count($locks) !== 1) throw new RuntimeException('Stale ownership lock.');
            Biawin_CRM_Ownership_Repository::update('ownership_locks', ['released_at'=>Biawin_CRM_DB::now(),'released_by_case_id'=>$case_id,'release_reason'=>'OWNERSHIP_TRANSFER_APPROVED'], ['id'=>(int)$lock->id]);
            Biawin_CRM_Ownership_Audit::event($case_id, 'OWNERSHIP_LOCK_RELEASED', ['lock_id'=>(int)$lock->id], $source, $type, $id);
        }
        self::create($type, $id, $target, $case_id);
    }
    /** Explicit rollout operation. Save next_after_id and repeat; never invoked on load. */
    public static function sync_initial_batch(int $after_id = 0, int $limit = 200): array {
        Biawin_CRM_Ownership_Authorization::require_cap('manage_ownership_policy');
        global $wpdb;
        $after_id = max(0,$after_id);
        $limit = max(1,min(1000,$limit));
        $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE id>%d ORDER BY id LIMIT %d',$after_id,$limit+1));
        $report = ['records'=>[],'next_after_id'=>$after_id,'has_more'=>count($rows)>$limit,'failed_invoice_id'=>null,'error'=>null];
        foreach (array_slice($rows,0,$limit) as $row) {
            $id = (int)$row->id;
            try {
                $record = Biawin_CRM_Ownership_Repository::atomic(static function () use ($id) { return self::sync_initial_invoice($id); });
            } catch (Throwable $e) {
                // Per-invoice rollback leaves the cursor before the failure. Earlier commits stand.
                $report['failed_invoice_id'] = $id;
                $report['error'] = $e->getMessage();
                $report['has_more'] = true;
                break;
            }
            $report['records'][] = $record;
            $report['next_after_id'] = $id;
        }
        return $report;
    }
    private static function sync_initial_invoice(int $id): array {
        global $wpdb;
        $invoice = Biawin_CRM_Ownership_Repository::row('invoices',$id,true);
        $record = ['invoice_id'=>$id,'status'=>'ALREADY_LOCKED','issues'=>[]];
        if (Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('ownership_locks') . " WHERE entity_type='INVOICE' AND entity_id=%d AND released_at IS NULL FOR UPDATE",$id))) return $record;
        // Reuse C1's full historical classification, including identity and service owners.
        $audit = Biawin_CRM_Ownership_Migration_Audit::audit_batch($id-1,1)['records'][0];
        if ($audit['classification'] === Biawin_CRM_Ownership_Migration_Audit::AMBIGUOUS) {
            return ['invoice_id'=>$id,'status'=>'HISTORICAL_REVIEW_REQUIRED','issues'=>$audit['issues']];
        }
        $profile = Biawin_CRM_Ownership_Repository::row('customers',(int)$invoice->customer_id,true);
        if ($profile->profile_completeness !== 'complete') { $record['status']='INCOMPLETE_SKIPPED'; return $record; }
        self::create('INVOICE',$id,(int)$invoice->customer_id);
        $record['status']='LOCK_CREATED';
        return $record;
    }
    public static function sync_completed(int $customer): void {
        Biawin_CRM_Ownership_Repository::gate(static function () use ($customer) {
            global $wpdb;
            $work = static function () use ($customer) {
            global $wpdb;
            $profile = Biawin_CRM_Ownership_Repository::row('customers', $customer, true);
            if ($profile->profile_completeness !== 'complete') return;
            foreach (Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d ORDER BY id FOR UPDATE', $customer)) as $invoice) {
                try { Biawin_CRM_Invoice_Phone_Service::current($invoice); }
                catch (RuntimeException $e) {
                    Biawin_CRM_Ownership_Audit::event(0, 'LEGACY_LOCK_REVIEW_REQUIRED', ['reason'=>$e->getMessage()], $customer, 'INVOICE', (int)$invoice->id);
                    continue;
                }
                self::create('INVOICE', (int)$invoice->id, $customer);
            }
            };
            if (Biawin_CRM_Ownership_Repository::in_transaction()) $work();
            else Biawin_CRM_Ownership_Repository::atomic($work);
        });
    }
}
