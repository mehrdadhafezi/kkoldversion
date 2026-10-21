<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Ownership_OTP
{
    private static function disputed(object $case, int $phone): object {
        foreach (Biawin_CRM_Ownership_Repository::assets((int)$case->id) as $asset) {
            if ($asset->asset_type === 'PHONE' && (int)$asset->asset_id === $phone) {
                $row = Biawin_CRM_Ownership_Repository::row('phones',$phone,true);
                if ((int)$row->customer_id !== (int)$case->current_owner_customer_id) throw new RuntimeException('Disputed phone ownership changed.');
                return $row;
            }
        }
        throw new RuntimeException('OTP is restricted to an explicitly disputed phone.');
    }
    public static function issue(int $id, int $version, int $phone_id, string $purpose = 'CONFIRM_PHONE_CONTROL'): int {
        if (!in_array($purpose,['CONFIRM_PHONE_CONTROL','CONFIRM_TRANSFER_CONSENT'],true)) throw new RuntimeException('Unsupported OTP purpose.');
        return Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($phone_id,$purpose) {
            global $wpdb;
            Biawin_CRM_Ownership_Authorization::scope($case,'issue_ownership_otp');
            Biawin_CRM_Ownership_Workflow::state($case,Biawin_CRM_Ownership_Workflow::REVIEW_STATES);
            $phone = self::disputed($case,$phone_id);
            $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('ownership_otp_challenges') . ' WHERE case_id=%d AND phone_asset_id=%d AND purpose=%s ORDER BY id DESC LIMIT 1 FOR UPDATE',$case->id,$phone_id,$purpose));
            if ($rows && strtotime($rows[0]->sent_at . ' UTC') > time()-60) throw new RuntimeException('OTP resend throttled.');
            // Supersede every earlier unused challenge for this exact scope.
            foreach (Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('ownership_otp_challenges') . ' WHERE case_id=%d AND phone_asset_id=%d AND purpose=%s AND consumed_at IS NULL',$case->id,$phone_id,$purpose)) as $old) {
                Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['consumed_at'=>gmdate('Y-m-d H:i:s')],['id'=>(int)$old->id]);
            }
            $code = (string)random_int(100000,999999);
            $challenge = Biawin_CRM_Ownership_Repository::insert('ownership_otp_challenges',['case_id'=>(int)$case->id,'phone_asset_id'=>$phone_id,
                'customer_id'=>(int)$case->current_owner_customer_id,'phone_normalized'=>$phone->normalized_phone,'purpose'=>$purpose,'otp_hash'=>password_hash($code,PASSWORD_DEFAULT),
                'expires_at'=>gmdate('Y-m-d H:i:s',time()+300),'attempt_count'=>0,'max_attempts'=>5,'sent_at'=>gmdate('Y-m-d H:i:s'),'created_by_user_id'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now()]);
            // A site-specific SMS adapter must return true after provider acceptance.
            // The code is passed only in memory and is never returned or logged.
            $sent = apply_filters('biawin_crm_ownership_send_otp',false,$phone->normalized_phone,$code,(int)$case->id,$purpose) === true;
            unset($code);
            Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['provider_meta_json'=>Biawin_CRM_Ownership_Repository::json(['accepted'=>$sent]),'consumed_at'=>$sent ? null : gmdate('Y-m-d H:i:s')],['id'=>$challenge]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,$sent ? 'OTP_SENT' : 'OTP_DELIVERY_FAILED',['challenge_id'=>$challenge,'phone_asset_id'=>$phone_id,'purpose'=>$purpose]);
            Biawin_CRM_Ownership_Risk_Service::refresh($case,$sent ? [] : ['OTP_DELIVERY_FAILED']);
            return $challenge;
        });
    }
    public static function verify(int $id, int $version, int $challenge_id, int $phone_id, string $purpose, string $code): bool {
        return Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($challenge_id,$phone_id,$purpose,$code) {
            Biawin_CRM_Ownership_Authorization::scope($case,'verify_ownership_otp');
            Biawin_CRM_Ownership_Workflow::state($case,Biawin_CRM_Ownership_Workflow::REVIEW_STATES);
            $phone = self::disputed($case,$phone_id);
            $otp = Biawin_CRM_Ownership_Repository::row('ownership_otp_challenges',$challenge_id,true);
            if ((int)$otp->case_id !== (int)$case->id || (int)$otp->phone_asset_id !== $phone_id || $otp->phone_normalized !== $phone->normalized_phone || $otp->purpose !== $purpose) throw new RuntimeException('OTP scope mismatch.');
            if ((Biawin_CRM_Ownership_Repository::decode($otp->provider_meta_json)['accepted'] ?? false) !== true || $otp->consumed_at || (int)$otp->attempt_count >= (int)$otp->max_attempts) {
                Biawin_CRM_Ownership_Audit::event((int)$case->id,'OTP_REUSE_REJECTED',['challenge_id'=>$challenge_id]); return false;
            }
            $expired = strtotime($otp->expires_at . ' UTC') <= time();
            $valid = !$expired && preg_match('/^\d{6}$/',$code) && password_verify($code,$otp->otp_hash);
            $attempts = (int)$otp->attempt_count+1;
            $now = gmdate('Y-m-d H:i:s');
            Biawin_CRM_Ownership_Repository::update('ownership_otp_challenges',['attempt_count'=>$attempts,'verified_at'=>$valid ? $now : null,'consumed_at'=>($valid || $expired || $attempts >= (int)$otp->max_attempts) ? $now : null],['id'=>$challenge_id]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,$valid ? 'OTP_VERIFIED' : ($expired ? 'OTP_EXPIRED' : 'OTP_FAILED'),['challenge_id'=>$challenge_id,'attempt'=>$attempts,'phone_asset_id'=>$phone_id]);
            Biawin_CRM_Ownership_Risk_Service::refresh($case,$valid ? [] : [$expired ? 'OTP_EXPIRED' : 'OTP_FAILED']);
            return (bool)$valid;
        });
    }
    public static function expired_risk(object $case): array {
        global $wpdb;
        $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table('ownership_otp_challenges') . ' WHERE case_id=%d AND verified_at IS NULL AND consumed_at IS NULL AND expires_at<=%s LIMIT 1',$case->id,gmdate('Y-m-d H:i:s')));
        return $rows ? ['OTP_EXPIRED'] : [];
    }
    public static function assert_verified(object $case): void {
        global $wpdb;
        $found = false;
        foreach (Biawin_CRM_Ownership_Repository::assets((int)$case->id) as $asset) if ($asset->asset_type === 'PHONE') {
            $found = true;
            $phone = self::disputed($case,(int)$asset->asset_id);
            $rows = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('ownership_otp_challenges') . ' WHERE case_id=%d AND phone_asset_id=%d AND phone_normalized=%s AND verified_at IS NOT NULL AND consumed_at IS NOT NULL',$case->id,$asset->asset_id,$phone->normalized_phone));
            $verified = false;
            foreach ($rows as $otp) {
                if ((Biawin_CRM_Ownership_Repository::decode($otp->provider_meta_json)['accepted'] ?? false) === true &&
                    in_array($otp->purpose,['CONFIRM_PHONE_CONTROL','CONFIRM_TRANSFER_CONSENT'],true) &&
                    (int)$otp->attempt_count > 0 && (int)$otp->attempt_count <= (int)$otp->max_attempts &&
                    $otp->verified_at >= $otp->sent_at && $otp->verified_at < $otp->expires_at) $verified = true;
            }
            if (!$verified) throw new RuntimeException('Delivered and verified disputed phone OTP evidence missing.');
        }
        if (!$found) throw new RuntimeException('OTP is not invoice ownership evidence.');
    }
}
