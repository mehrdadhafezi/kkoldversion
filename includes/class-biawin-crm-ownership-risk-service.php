<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Ownership_Risk_Service
{
    public static function reasons(array $assets, array $existing = []): array {
        $reasons = $existing;
        $invoices = 0;
        foreach ($assets as $asset) {
            if ($asset->asset_type === 'INVOICE') $invoices++;
            if ($asset->asset_type === 'PHONE' && ($asset->final_action ?: $asset->requested_action) === 'TRANSFER') $reasons[] = 'DISPUTED_PHONE';
            if (($asset->final_phone_resolution ?: $asset->requested_phone_resolution) === 'CLAIM_CURRENT_PHONE') $reasons[] = 'PHONE_TRANSFER';
            if ($asset->specialist_override_reason) $reasons[] = 'PLAN_OVERRIDE';
        }
        if ($invoices > 1) $reasons[] = 'MULTIPLE_INVOICES';
        return array_values(array_unique($reasons));
    }
    public static function refresh(object $case, array $extra = []): object {
        $existing = Biawin_CRM_Ownership_Repository::decode($case->risk_reasons_json);
        // Sticky high risk: no public action can downgrade a persisted system decision.
        if ($case->risk_level === 'HIGH_RISK' && !$existing) $existing[] = 'PRIOR_HIGH_RISK';
        $reasons = self::reasons(Biawin_CRM_Ownership_Repository::assets((int)$case->id), array_merge($existing, $extra));
        $level = $reasons ? 'HIGH_RISK' : 'NORMAL';
        Biawin_CRM_Ownership_Repository::update('ownership_cases', ['risk_level'=>$level,'risk_reasons_json'=>Biawin_CRM_Ownership_Repository::json($reasons),'risk_evaluated_at'=>Biawin_CRM_DB::now()], ['id'=>(int)$case->id]);
        if ($level === 'HIGH_RISK' && ($case->risk_level !== $level || $existing !== $reasons)) Biawin_CRM_Ownership_Audit::event((int)$case->id,'RISK_ESCALATED',['reasons'=>$reasons]);
        $case->risk_level = $level;
        $case->risk_reasons_json = Biawin_CRM_Ownership_Repository::json($reasons);
        return $case;
    }
}
