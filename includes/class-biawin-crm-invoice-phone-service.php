<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Invoice_Phone_Service
{
    public static function current(object $invoice): string {
        global $wpdb;
        $canonical = (string)($invoice->canonical_phone_normalized ?? '');
        if ($canonical !== '') {
            if (Biawin_CRM_Normalizer::phone($canonical) !== $canonical) throw new RuntimeException('Invalid canonical invoice phone.');
            return $canonical;
        }
        $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('invoice_phones') . ' WHERE invoice_id=%d ORDER BY id', $invoice->id));
        $values = array_map(static function ($row) { return $row->normalized_phone; }, $rows);
        $result = Biawin_CRM_Ownership_Migration_Audit::classify($values);
        if ($result['classification'] === Biawin_CRM_Ownership_Migration_Audit::AMBIGUOUS) throw new RuntimeException('Ambiguous legacy invoice phone; review required.');
        return (string)$result['normalized_candidates'][0];
    }
    public static function plan(object $case, object $asset): array {
        global $wpdb;
        $mode = (string)($asset->final_phone_resolution ?: $asset->requested_phone_resolution);
        $phone_id = (int)($asset->final_phone_id ?: $asset->requested_phone_id);
        $proposed = (string)($asset->final_phone_normalized ?: $asset->requested_phone_normalized);
        $invoice = Biawin_CRM_Ownership_Repository::row('invoices', (int)$asset->asset_id, true);
        if ($mode === 'KEEP_CURRENT_PHONE' || $mode === 'CLAIM_CURRENT_PHONE') $number = self::current($invoice);
        elseif ($mode === 'USE_TARGET_PROFILE_PHONE') {
            $phone = Biawin_CRM_Ownership_Repository::row('phones', $phone_id, true);
            if ((int)$phone->customer_id !== (int)$case->claimant_customer_id) throw new RuntimeException('Destination phone owner changed.');
            $number = $phone->normalized_phone;
        } elseif ($mode === 'PROPOSE_NEW_PHONE') $number = Biawin_CRM_Normalizer::phone($proposed);
        else throw new RuntimeException('Explicit invoice phone resolution required.');
        if ($number === '' || Biawin_CRM_Normalizer::phone($number) !== $number) throw new RuntimeException('Exactly one valid canonical phone is required.');
        $phones = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE normalized_phone=%s FOR UPDATE', $number));
        if (count($phones) > 1) throw new RuntimeException('Ambiguous phone identity.');
        $phone = $phones[0] ?? null;
        if ($mode === 'PROPOSE_NEW_PHONE' && $phone && (int)$phone->customer_id !== (int)$case->claimant_customer_id) {
            $explicit = false;
            if ((int)$phone->customer_id === (int)$case->current_owner_customer_id) foreach (Biawin_CRM_Ownership_Repository::assets((int)$case->id) as $a) {
                if ($a->asset_type === 'PHONE' && (int)$a->asset_id === (int)$phone->id && ($a->final_action ?: $a->requested_action) === 'TRANSFER') $explicit = true;
            }
            if (!$explicit) throw new RuntimeException('Proposed phone already belongs to another profile; claim it explicitly.');
        }
        if ($mode === 'CLAIM_CURRENT_PHONE') {
            if (!$phone || (int)$phone->customer_id !== (int)$case->current_owner_customer_id) throw new RuntimeException('Disputed phone owner changed.');
            $explicit = false;
            foreach (Biawin_CRM_Ownership_Repository::assets((int)$case->id) as $a) if ($a->asset_type === 'PHONE' && (int)$a->asset_id === (int)$phone->id && ($a->final_action ?: $a->requested_action) === 'TRANSFER') $explicit = true;
            if (!$explicit) throw new RuntimeException('Claim current phone requires an explicit PHONE asset.');
        }
        return ['mode'=>$mode,'number'=>$number,'phone'=>$phone];
    }
    public static function apply(object $case, object $asset, array $plan): void {
        global $wpdb;
        $id = (int)$asset->asset_id;
        $before = Biawin_CRM_Ownership_Repository::row('invoices', $id)->canonical_phone_normalized;
        if ($plan['mode'] === 'PROPOSE_NEW_PHONE' && !$plan['phone']) {
            Biawin_CRM_Ownership_Repository::insert('phones', ['customer_id'=>(int)$case->claimant_customer_id,'raw_phone'=>$plan['number'],'normalized_phone'=>$plan['number'],'source'=>'ownership_case','created_at'=>Biawin_CRM_DB::now()]);
        }
        Biawin_CRM_Ownership_Repository::update('invoices', ['canonical_phone_normalized'=>$plan['number'],'canonical_phone_source'=>'OWNERSHIP_CASE','canonical_phone_updated_at'=>Biawin_CRM_DB::now(),'canonical_phone_case_id'=>(int)$case->id], ['id'=>$id]);
        $links = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('invoice_phones') . ' WHERE invoice_id=%d AND normalized_phone=%s', $id, $plan['number']));
        if (!$links) Biawin_CRM_Ownership_Repository::insert('invoice_phones', ['invoice_id'=>$id,'normalized_phone'=>$plan['number'],'created_at'=>Biawin_CRM_DB::now()]);
        Biawin_CRM_Ownership_Audit::event((int)$case->id, 'INVOICE_PHONE_CHANGED', ['before'=>$before,'after'=>$plan['number'],'resolution'=>$plan['mode']], null, 'INVOICE', $id);
    }
}
