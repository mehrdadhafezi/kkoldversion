<?php

if (!defined('ABSPATH')) { exit; }

/** Capability definitions only. Case/assignment/risk authorization belongs to later phases. */
class Biawin_CRM_Ownership_Authorization
{
    public static function role_definitions(): array
    {
        // Operational caps stay in the additive ledger's extra map, including on
        // installations where the legacy ledger has not yet been initialized.
        return [
            'bcrm_ownership_specialist' => ['label' => 'کارشناس تطبیق مالکیت', 'caps' => ['read', 'bcrm_work_cases']],
            'bcrm_ownership_supervisor' => ['label' => 'سرپرست تطبیق مالکیت', 'caps' => ['read', 'bcrm_work_cases']],
        ];
    }

    public static function role_capabilities(): array
    {
        $requester = ['submit_ownership_claim', 'view_own_ownership_claims'];
        $specialist = [
            'submit_ownership_claim', 'view_own_ownership_claims', 'view_ownership_cases',
            'review_ownership_cases', 'amend_ownership_case', 'request_ownership_evidence',
            'record_ownership_contact', 'issue_ownership_otp', 'verify_ownership_otp',
            'approve_normal_ownership_transfer', 'reject_ownership_claim',
            'record_ownership_withdrawal', 'manage_ownership_evidence', 'view_ownership_audit',
        ];
        $supervisor = array_merge($specialist, [
            'assign_ownership_cases', 'approve_high_risk_ownership_transfer', 'manage_ownership_policy',
        ]);
        return [
            // Core customer-profile and Deficiency workers; existing profile access
            // remains a separate requirement for future request endpoints.
            'bcrm_agent' => $requester,
            'bcrm_supervisor' => $requester,
            'bcrm_deficiency_agent' => $requester,
            'bcrm_deficiency_supervisor' => $requester,
            'bcrm_ownership_specialist' => $specialist,
            'bcrm_ownership_supervisor' => $supervisor,
            'bcrm_portal_admin' => $supervisor,
            'administrator' => $supervisor,
        ];
    }
    public static function require_cap(string $cap): void {
        if (!get_current_user_id() || !current_user_can($cap)) throw new RuntimeException('Ownership permission denied: ' . $cap);
    }
    public static function scope(object $case, string $cap, bool $requester = false): void {
        self::require_cap($cap);
        if ($requester) {
            if ((int)$case->requested_by_user_id !== get_current_user_id()) throw new RuntimeException('Only your own ownership claims are accessible.');
            return;
        }
        if (current_user_can('assign_ownership_cases')) return;
        if ((int)$case->assigned_to_user_id !== get_current_user_id()) throw new RuntimeException('Ownership case is not assigned to you.');
    }
}
