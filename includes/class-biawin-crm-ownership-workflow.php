<?php
if (!defined('ABSPATH')) { exit; }
class Biawin_CRM_Ownership_Workflow
{
    const REVIEW_STATES = ['UNDER_REVIEW','AWAITING_EVIDENCE','AWAITING_CUSTOMER_CONFIRMATION'];
    public static function state(object $case, array $allowed): void {
        if (!in_array($case->status, $allowed, true)) throw new RuntimeException('Ownership action is not allowed in state ' . $case->status);
    }
    private static function text(string $value): string {
        $value = trim(sanitize_textarea_field($value));
        if ($value === '') throw new RuntimeException('A reason/note is required.');
        return $value;
    }
    private static function transition(object $case, string $state, string $event, array $data = [], array $meta = []): void {
        Biawin_CRM_Ownership_Repository::update('ownership_cases', array_merge($data, ['status'=>$state]), ['id'=>(int)$case->id]);
        Biawin_CRM_Ownership_Audit::event((int)$case->id, $event, array_merge(['from'=>$case->status,'to'=>$state], $meta));
        $case->status = $state;
    }
    public static function create_draft(int $source, int $claimant, string $note = '', string $reason = ''): int {
        Biawin_CRM_Ownership_Authorization::require_cap('submit_ownership_claim');
        if ($source === $claimant) throw new RuntimeException('Two distinct customer profiles are required.');
        if (!Biawin_CRM_Frontend::can_access_customer($claimant)) throw new RuntimeException('Claimant profile access denied.');
        return Biawin_CRM_Ownership_Repository::atomic(static function () use ($source, $claimant, $note, $reason) {
            Biawin_CRM_Ownership_Repository::row('customers', $source, true);
            Biawin_CRM_Ownership_Repository::row('customers', $claimant, true);
            $id = Biawin_CRM_Ownership_Repository::insert('ownership_cases', ['case_code'=>'OWN-' . gmdate('Y') . '-' . bin2hex(random_bytes(12)),
                'current_owner_customer_id'=>$source,'claimant_customer_id'=>$claimant,'status'=>'DRAFT',
                'requested_by_user_id'=>get_current_user_id(),'request_note'=>sanitize_textarea_field($note),'claim_reason'=>substr(sanitize_text_field($reason),0,120),
                'created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()]);
            Biawin_CRM_Ownership_Audit::event($id, 'CASE_CREATED', ['source'=>$source,'claimant'=>$claimant]);
            return $id;
        });
    }
    public static function update_draft(int $id, int $version, string $note, string $reason): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($note,$reason) {
            Biawin_CRM_Ownership_Authorization::scope($case,'submit_ownership_claim',true);
            self::state($case,['DRAFT']);
            Biawin_CRM_Ownership_Repository::update('ownership_cases',['request_note'=>sanitize_textarea_field($note),'claim_reason'=>substr(sanitize_text_field($reason),0,120)],['id'=>(int)$case->id]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,'CASE_DRAFT_UPDATED');
        });
    }
    private static function asset_data(object $case, array $input): array {
        $type = (string)($input['asset_type'] ?? '');
        $id = (int)($input['asset_id'] ?? 0);
        if (!in_array($type,['INVOICE','PHONE'],true)) throw new RuntimeException('Unsupported asset type.');
        $asset = Biawin_CRM_Ownership_Repository::row($type === 'INVOICE' ? 'invoices' : 'phones', $id, true);
        if ((int)$asset->customer_id !== (int)$case->current_owner_customer_id) throw new RuntimeException('Asset does not belong to source profile.');
        $action = (string)($input['requested_action'] ?? 'TRANSFER');
        if (!in_array($action,['TRANSFER','REVIEW_ONLY'],true)) throw new RuntimeException('Unsupported requested asset action.');
        $data = ['case_id'=>(int)$case->id,'asset_type'=>$type,'asset_id'=>$id,'source_customer_id'=>(int)$case->current_owner_customer_id,
            'target_customer_id'=>(int)$case->claimant_customer_id,'requested_action'=>$action,'snapshot_json'=>'{}',
            'created_by_user_id'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now(),'updated_at'=>Biawin_CRM_DB::now()];
        if ($type === 'INVOICE') {
            $mode = (string)($input['phone_resolution'] ?? '');
            if (!in_array($mode,['KEEP_CURRENT_PHONE','USE_TARGET_PROFILE_PHONE','CLAIM_CURRENT_PHONE','PROPOSE_NEW_PHONE'],true)) throw new RuntimeException('Explicit invoice phone plan required.');
            $data['requested_phone_resolution'] = $mode;
            $data['requested_phone_id'] = !empty($input['phone_id']) ? (int)$input['phone_id'] : null;
            $data['requested_phone_normalized'] = !empty($input['phone']) ? Biawin_CRM_Normalizer::phone($input['phone']) : null;
        }
        return $data;
    }
    public static function add_draft_asset(int $id, int $version, array $input): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($input) {
            Biawin_CRM_Ownership_Authorization::scope($case,'submit_ownership_claim',true);
            self::state($case,['DRAFT']);
            Biawin_CRM_Ownership_Repository::insert('ownership_case_assets', self::asset_data($case,$input));
            Biawin_CRM_Ownership_Audit::event((int)$case->id,'DRAFT_ASSET_ADDED',$input);
        });
    }
    public static function remove_draft_asset(int $id, int $version, int $asset_row): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($asset_row) {
            global $wpdb;
            Biawin_CRM_Ownership_Authorization::scope($case,'submit_ownership_claim',true);
            self::state($case,['DRAFT']);
            $asset = Biawin_CRM_Ownership_Repository::row('ownership_case_assets',$asset_row,true);
            if ((int)$asset->case_id !== (int)$case->id) throw new RuntimeException('Asset case mismatch.');
            if ($wpdb->delete(Biawin_CRM_DB::table('ownership_case_assets'),['id'=>$asset_row]) === false) throw new RuntimeException('Draft asset removal failed.');
            Biawin_CRM_Ownership_Audit::event((int)$case->id,'DRAFT_ASSET_REMOVED',['asset'=>$asset]);
        });
    }
    public static function snapshot(object $case): array {
        global $wpdb;
        $snapshot = ['profiles'=>[], 'assets'=>[]];
        foreach ([$case->current_owner_customer_id,$case->claimant_customer_id] as $id) {
            $customer = Biawin_CRM_Ownership_Repository::row('customers',(int)$id,true);
            $summary = [];
            foreach (['id','customer_code','display_name','profile_completeness','declared_paid','total_paid','discrepancy_amount'] as $field) $summary[$field] = $customer->$field ?? null;
            $summary['phones'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,customer_id,normalized_phone,phone_status FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d ORDER BY id',$id));
            $snapshot['profiles'][(string)$id] = $summary;
        }
        foreach (Biawin_CRM_Ownership_Repository::assets((int)$case->id) as $asset) {
            if (Biawin_CRM_Ownership_Repository::conflicting((int)$case->id,$asset->asset_type,(int)$asset->asset_id)) throw new RuntimeException('Asset already participates in an active ownership case.');
            $row = Biawin_CRM_Ownership_Repository::row($asset->asset_type === 'INVOICE' ? 'invoices' : 'phones',(int)$asset->asset_id,true);
            if ((int)$row->customer_id !== (int)$case->current_owner_customer_id) throw new RuntimeException('Asset owner changed.');
            $item = ['asset_type'=>$asset->asset_type,'asset_id'=>(int)$asset->asset_id,'customer_id'=>(int)$row->customer_id];
            if ($asset->asset_type === 'INVOICE') {
                $item['invoice_code'] = $row->invoice_code;
                $item['amount_paid'] = $row->amount_paid;
                $item['phone'] = Biawin_CRM_Invoice_Phone_Service::current($row);
                $item['phone_relations'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('invoice_phones') . ' WHERE invoice_id=%d ORDER BY id',$row->id));
                $item['services'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,invoice_id,customer_id,service_name FROM ' . Biawin_CRM_DB::table('services') . ' WHERE invoice_id=%d ORDER BY id',$row->id));
                foreach ($item['services'] as $service) if ((int)$service->customer_id !== (int)$row->customer_id) throw new RuntimeException('Ambiguous legacy service owner; historical review required.');
                $plan = Biawin_CRM_Invoice_Phone_Service::plan($case,$asset);
                $item['plan'] = ['mode'=>$plan['mode'],'number'=>$plan['number'],'phone_id'=>$plan['phone'] ? (int)$plan['phone']->id : null];
            } else $item['phone'] = $row->normalized_phone;
            $snapshot['assets'][(string)$asset->id] = $item;
        }
        return $snapshot;
    }
    public static function submit(int $id, int $version): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) {
            Biawin_CRM_Ownership_Authorization::scope($case,'submit_ownership_claim',true);
            self::state($case,['DRAFT']);
            if (!Biawin_CRM_Frontend::can_access_customer((int)$case->claimant_customer_id)) throw new RuntimeException('Claimant profile access changed.');
            $assets = Biawin_CRM_Ownership_Repository::assets((int)$case->id);
            if (!$assets) throw new RuntimeException('At least one asset is required.');
            $snapshot = self::snapshot($case);
            foreach ($assets as $asset) Biawin_CRM_Ownership_Repository::update('ownership_case_assets',['snapshot_json'=>Biawin_CRM_Ownership_Repository::json($snapshot['assets'][(string)$asset->id])],['id'=>(int)$asset->id]);
            Biawin_CRM_Customer_Hold_Service::apply($case);
            Biawin_CRM_Ownership_Risk_Service::refresh($case);
            self::transition($case,'SUBMITTED','CASE_SUBMITTED',['snapshot_json'=>Biawin_CRM_Ownership_Repository::json($snapshot),'submitted_at'=>Biawin_CRM_DB::now()]);
            Biawin_CRM_Ownership_Audit::timeline($case,'OWNERSHIP_CASE_OPENED');
        });
    }
    public static function assign(int $id, int $version, int $specialist): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($specialist) {
            Biawin_CRM_Ownership_Authorization::scope($case,'assign_ownership_cases');
            self::state($case,['SUBMITTED','ASSIGNED','UNDER_REVIEW','AWAITING_EVIDENCE','AWAITING_CUSTOMER_CONFIRMATION']);
            $user = get_user_by('id',$specialist);
            if (!$user || !$user->has_cap('review_ownership_cases') || get_user_meta($specialist,'bcrm_disabled',true)) throw new RuntimeException('Eligible ownership specialist required.');
            self::transition($case,$case->status === 'SUBMITTED' ? 'ASSIGNED' : $case->status,'CASE_ASSIGNED', ['assigned_to_user_id'=>$specialist,'assigned_by_user_id'=>get_current_user_id(),'assigned_at'=>Biawin_CRM_DB::now()],['before'=>$case->assigned_to_user_id,'after'=>$specialist]);
        });
    }
    public static function start_review(int $id, int $version): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) {
            Biawin_CRM_Ownership_Authorization::scope($case,'review_ownership_cases');
            self::state($case,array_merge(['ASSIGNED'],self::REVIEW_STATES));
            self::transition($case,'UNDER_REVIEW','REVIEW_STARTED',['review_started_at'=>$case->review_started_at ?: Biawin_CRM_DB::now()]);
        });
    }
    public static function request_evidence(int $id, int $version, string $note, bool $customer_confirmation = false): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($note,$customer_confirmation) {
            Biawin_CRM_Ownership_Authorization::scope($case,'request_ownership_evidence'); self::state($case,self::REVIEW_STATES);
            self::transition($case,$customer_confirmation ? 'AWAITING_CUSTOMER_CONFIRMATION' : 'AWAITING_EVIDENCE','MORE_EVIDENCE_REQUESTED',[],['note'=>self::text($note)]);
        });
    }
    public static function record_contact(int $id, int $version, string $result, string $note): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($result,$note) {
            Biawin_CRM_Ownership_Authorization::scope($case,'record_ownership_contact'); self::state($case,self::REVIEW_STATES);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,'CONTACT_RECORDED',['result'=>self::text($result),'note'=>self::text($note)]);
        });
    }
    public static function add_evidence(int $id, int $version, int $media_id, string $kind, string $note = '', bool $conflicting = false): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($media_id,$kind,$note,$conflicting) {
            $own = (int)$case->requested_by_user_id === get_current_user_id();
            Biawin_CRM_Ownership_Authorization::scope($case,$own ? 'submit_ownership_claim' : 'manage_ownership_evidence',$own);
            self::state($case,array_merge(['DRAFT','SUBMITTED','ASSIGNED'],self::REVIEW_STATES));
            if (!in_array($kind,['ownership_evidence','claimant_receipt','consent_document'],true)) throw new RuntimeException('Unsupported ownership evidence kind.');
            $post = get_post($media_id);
            if (!$post || $post->post_type !== 'attachment' || ((int)$post->post_author !== get_current_user_id() && !current_user_can('edit_post',$media_id))) throw new RuntimeException('Media access denied.');
            if (!is_file(get_attached_file($media_id)) || !in_array(get_post_mime_type($media_id),['image/jpeg','image/png','image/webp','application/pdf','audio/mpeg','audio/wav'],true)) throw new RuntimeException('Unsupported evidence file.');
            if ($conflicting) Biawin_CRM_Ownership_Authorization::scope($case,'review_ownership_cases');
            $attachment = Biawin_CRM_Ownership_Repository::insert('attachments',['customer_id'=>(int)$case->claimant_customer_id,'object_type'=>'ownership_case','object_id'=>(int)$case->id,'wp_attachment_id'=>$media_id,'file_kind'=>$kind,'note'=>substr(sanitize_textarea_field($note),0,255),'uploaded_by'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now()]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,'EVIDENCE_ADDED',['attachment_id'=>$attachment,'media_id'=>$media_id,'sha256'=>hash_file('sha256',get_attached_file($media_id)),'kind'=>$kind,'conflicting'=>$conflicting]);
            if ($case->status !== 'DRAFT') Biawin_CRM_Ownership_Risk_Service::refresh($case,$conflicting ? ['CONFLICTING_EVIDENCE'] : []);
        });
    }
    public static function amend(int $id, int $version, array $inputs, string $reason): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($inputs,$reason) {
            Biawin_CRM_Ownership_Authorization::scope($case,'amend_ownership_case'); self::state($case,self::REVIEW_STATES);
            $reason = self::text($reason);
            if (!$inputs) throw new RuntimeException('Amendment assets required.');
            $before = self::snapshot($case);
            $added = [];
            foreach ($inputs as $input) $added[] = Biawin_CRM_Ownership_Repository::insert('ownership_case_assets',self::asset_data($case,$input));
            $after = self::snapshot($case);
            foreach ($added as $asset) Biawin_CRM_Ownership_Repository::update('ownership_case_assets',['snapshot_json'=>Biawin_CRM_Ownership_Repository::json($after['assets'][(string)$asset])],['id'=>$asset]);
            $amendment = Biawin_CRM_Ownership_Repository::insert('ownership_case_amendments',['case_id'=>(int)$case->id,'amendment_type'=>'ADD_ASSET','reason'=>$reason,'before_snapshot_json'=>Biawin_CRM_Ownership_Repository::json($before),'after_snapshot_json'=>Biawin_CRM_Ownership_Repository::json($after),'created_by_user_id'=>get_current_user_id(),'created_at'=>Biawin_CRM_DB::now()]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,'CASE_AMENDED',['id'=>$amendment,'reason'=>$reason]);
            foreach ($added as $asset) Biawin_CRM_Ownership_Audit::event((int)$case->id,'ASSET_ADDED',['asset_row_id'=>$asset,'amendment_id'=>$amendment]);
            Biawin_CRM_Ownership_Risk_Service::refresh($case,['SCOPE_EXPANDED']);
        });
    }
    public static function set_final_plan(int $id, int $version, int $asset_row, array $plan, string $reason): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($asset_row,$plan,$reason) {
            Biawin_CRM_Ownership_Authorization::scope($case,'review_ownership_cases'); self::state($case,self::REVIEW_STATES);
            $asset = Biawin_CRM_Ownership_Repository::row('ownership_case_assets',$asset_row,true);
            if ((int)$asset->case_id !== (int)$case->id || $asset->asset_type !== 'INVOICE') throw new RuntimeException('Invoice case asset required.');
            $action = (string)($plan['action'] ?? 'TRANSFER');
            if (!in_array($action,['TRANSFER','REVIEW_ONLY'],true)) throw new RuntimeException('Unsupported final asset action.');
            $data = ['final_action'=>$action,'final_phone_resolution'=>(string)($plan['phone_resolution'] ?? ''),'final_phone_id'=>!empty($plan['phone_id']) ? (int)$plan['phone_id'] : null,'final_phone_normalized'=>!empty($plan['phone']) ? Biawin_CRM_Normalizer::phone($plan['phone']) : null];
            $before = ['phone_resolution'=>$asset->requested_phone_resolution,'phone_id'=>$asset->requested_phone_id,'phone'=>$asset->requested_phone_normalized];
            $changed = $data['final_action'] !== $asset->requested_action || $data['final_phone_resolution'] !== $before['phone_resolution'] || (int)$data['final_phone_id'] !== (int)$before['phone_id'] || (string)$data['final_phone_normalized'] !== (string)$before['phone'];
            if ($changed) $data['specialist_override_reason'] = self::text($reason);
            $proposed = (object)array_merge((array)$asset,$data);
            Biawin_CRM_Invoice_Phone_Service::plan($case,$proposed);
            Biawin_CRM_Ownership_Repository::update('ownership_case_assets',$data,['id'=>$asset_row]);
            Biawin_CRM_Ownership_Audit::event((int)$case->id,$changed ? 'REQUESTED_TRANSFER_PLAN_CHANGED' : 'FINAL_PLAN_CONFIRMED',['before'=>$asset,'after'=>$data,'reason'=>$reason]);
            Biawin_CRM_Ownership_Risk_Service::refresh($case,$changed ? ['PLAN_OVERRIDE'] : []);
        });
    }
    public static function evaluate_risk(int $id, int $version, string $escalation_reason = ''): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($escalation_reason) {
            Biawin_CRM_Ownership_Authorization::scope($case,'review_ownership_cases'); self::state($case,self::REVIEW_STATES);
            if ($escalation_reason !== '') Biawin_CRM_Ownership_Audit::event((int)$case->id,'MANUAL_RISK_ESCALATION',['reason'=>self::text($escalation_reason)]);
            Biawin_CRM_Ownership_Risk_Service::refresh($case,$escalation_reason !== '' ? ['MANUAL_ESCALATION'] : []);
        });
    }
    public static function decide(int $id, int $version, string $decision, string $method, string $note): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($decision,$method,$note) {
            global $wpdb;
            $caps = ['CLAIM_WITHDRAWN'=>'record_ownership_withdrawal','CLAIM_REJECTED'=>'reject_ownership_claim','TRANSFER_APPROVED'=>'approve_normal_ownership_transfer'];
            if (!isset($caps[$decision])) throw new RuntimeException('Unsupported ownership decision.');
            Biawin_CRM_Ownership_Authorization::scope($case,$caps[$decision]); self::state($case,['UNDER_REVIEW']);
            $note = self::text($note);
            if (!in_array($method,['CLAIMANT_RECEIPT','CURRENT_OWNER_CONSENT','OTP_VERIFICATION','DOCUMENT_REVIEW','MULTI_EVIDENCE'],true)) throw new RuntimeException('Supported verification method required.');
            $case = Biawin_CRM_Ownership_Risk_Service::refresh($case,Biawin_CRM_Ownership_OTP::expired_risk($case));
            if ($decision === 'TRANSFER_APPROVED') {
                if (!array_filter(Biawin_CRM_Ownership_Repository::assets((int)$case->id),static function ($a) { return ($a->final_action ?: $a->requested_action) === 'TRANSFER'; })) throw new RuntimeException('An approved transfer requires at least one explicit transfer asset.');
                self::snapshot($case); // revalidate owners and phone plans before recording approval
                $evidence = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('attachments') . " WHERE object_type='ownership_case' AND object_id=%d",$case->id));
                if (!$evidence) throw new RuntimeException('Documented supporting evidence is required for approval.');
                self::verify_evidence($case);
                if ($method === 'CLAIMANT_RECEIPT' && !array_filter($evidence,static function ($e) { return $e->file_kind === 'claimant_receipt'; })) throw new RuntimeException('Claimant receipt evidence is required.');
                if ($method === 'CURRENT_OWNER_CONSENT') {
                    $contacts = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT meta_json FROM ' . Biawin_CRM_DB::table('ownership_events') . " WHERE case_id=%d AND event_type='CONTACT_RECORDED'",$case->id));
                    $consent = false;
                    foreach ($contacts as $contact) if ((Biawin_CRM_Ownership_Repository::decode($contact->meta_json)['result'] ?? '') === 'current_owner_consent') $consent = true;
                    if (!$consent) throw new RuntimeException('Audited current-owner consent contact is required.');
                }
                if ($method === 'OTP_VERIFICATION') Biawin_CRM_Ownership_OTP::assert_verified($case);
            }
            $high = $decision === 'TRANSFER_APPROVED' && $case->risk_level === 'HIGH_RISK';
            self::transition($case,$high ? 'AWAITING_SUPERVISOR_APPROVAL' : 'DECIDED',$decision,
                ['decision'=>$decision,'decision_method'=>$method,'decision_note'=>$note,'decided_by_user_id'=>get_current_user_id(),'decided_at'=>Biawin_CRM_DB::now(),'supervisor_approval_status'=>$high ? 'PENDING' : 'NOT_REQUIRED'],['note'=>$note,'method'=>$method,'risk_level'=>$case->risk_level,'risk_reasons'=>Biawin_CRM_Ownership_Repository::decode($case->risk_reasons_json)]);
            if ($decision !== 'TRANSFER_APPROVED') {
                Biawin_CRM_Customer_Hold_Service::release($case,$decision);
                self::transition($case,'CLOSED','CASE_CLOSED',['closed_at'=>Biawin_CRM_DB::now()]);
                Biawin_CRM_Ownership_Audit::timeline($case,'OWNERSHIP_CASE_CLOSED');
            }
        });
    }
    /** Internal integrity check; registration snapshots the bytes in the append-only ledger. */
    public static function verify_evidence(object $case): void {
        global $wpdb;
        $events = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT meta_json FROM ' . Biawin_CRM_DB::table('ownership_events') . " WHERE case_id=%d AND event_type='EVIDENCE_ADDED'",$case->id));
        if (!$events) throw new RuntimeException('Approval evidence ledger is missing.');
        foreach ($events as $event) {
            $meta = Biawin_CRM_Ownership_Repository::decode($event->meta_json);
            $path = get_attached_file((int)($meta['media_id'] ?? 0));
            if (!$path || !is_file($path) || empty($meta['sha256']) || !hash_equals($meta['sha256'],hash_file('sha256',$path))) throw new RuntimeException('Ownership evidence changed or is unavailable; return to review.');
        }
    }
    public static function supervisor_decision(int $id, int $version, bool $approve, string $note): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($approve,$note) {
            Biawin_CRM_Ownership_Authorization::scope($case,'approve_high_risk_ownership_transfer'); self::state($case,['AWAITING_SUPERVISOR_APPROVAL']);
            if ((int)$case->decided_by_user_id === get_current_user_id()) throw new RuntimeException('High risk requires a different approving supervisor.');
            $note = self::text($note);
            self::transition($case,$approve ? 'DECIDED' : 'UNDER_REVIEW',$approve ? 'SUPERVISOR_APPROVED' : 'SUPERVISOR_REJECTED',
                ['supervisor_approval_status'=>$approve ? 'APPROVED' : 'REJECTED','supervisor_user_id'=>get_current_user_id(),'supervisor_note'=>$note,'supervisor_decided_at'=>Biawin_CRM_DB::now()],['note'=>$note]);
        });
    }
    public static function cancel(int $id, int $version, string $reason): void {
        Biawin_CRM_Ownership_Repository::case_command($id,$version,static function ($case) use ($reason) {
            $draft = $case->status === 'DRAFT';
            Biawin_CRM_Ownership_Authorization::scope($case,$draft ? 'submit_ownership_claim' : 'assign_ownership_cases',$draft);
            self::state($case,['DRAFT','SUBMITTED','ASSIGNED','UNDER_REVIEW','AWAITING_EVIDENCE','AWAITING_CUSTOMER_CONFIRMATION','AWAITING_SUPERVISOR_APPROVAL']);
            if ($case->execution_state !== 'NOT_STARTED') throw new RuntimeException('Executed case cannot be cancelled.');
            $reason = self::text($reason);
            if (!$draft) Biawin_CRM_Customer_Hold_Service::release($case,'CANCELLED');
            self::transition($case,'CANCELLED','CASE_CANCELLED',['cancelled_at'=>Biawin_CRM_DB::now()],['reason'=>$reason]);
            if (!$draft) Biawin_CRM_Ownership_Audit::timeline($case,'OWNERSHIP_CASE_CANCELLED');
        });
    }
    public static function get(int $id): array {
        $case = Biawin_CRM_Ownership_Repository::row('ownership_cases',$id);
        $own = (int)$case->requested_by_user_id === get_current_user_id() && current_user_can('view_own_ownership_claims');
        Biawin_CRM_Ownership_Authorization::scope($case,$own ? 'view_own_ownership_claims' : 'view_ownership_cases',$own);
        $data = ['case'=>$case,'assets'=>Biawin_CRM_Ownership_Repository::assets($id)];
        // Requesters see their request, status and decision; full snapshots/contact/OTP
        // and internal error details are reserved for authorized ownership reviewers.
        $reviewer = current_user_can('view_ownership_cases') && (current_user_can('assign_ownership_cases') || (int)$case->assigned_to_user_id === get_current_user_id());
        if (!$reviewer) {
            foreach (['snapshot_json','risk_reasons_json','execution_error','finalization_json','supervisor_note'] as $field) unset($case->$field);
            foreach ($data['assets'] as $asset) $asset->snapshot_json = null;
        } else {
            global $wpdb;
            if (current_user_can('view_ownership_audit')) $data['events'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('ownership_events') . ' WHERE case_id=%d ORDER BY id',$id));
        }
        global $wpdb;
        $evidence_scope = $reviewer ? '' : $wpdb->prepare(' AND uploaded_by=%d',get_current_user_id());
        $data['evidence'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,wp_attachment_id,file_kind,note,uploaded_by,created_at FROM ' . Biawin_CRM_DB::table('attachments') . " WHERE object_type='ownership_case' AND object_id=%d" . $evidence_scope . ' ORDER BY id',$id));
        if ($reviewer) {
            $data['current_profiles'] = [];
            foreach ([$case->current_owner_customer_id,$case->claimant_customer_id] as $customer) $data['current_profiles'][] = $wpdb->get_row($wpdb->prepare('SELECT id,customer_code,display_name,profile_completeness,declared_paid,total_paid,discrepancy_amount FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d',$customer));
            $data['current_assets'] = [];
            foreach ($data['assets'] as $asset) {
                $fields = $asset->asset_type === 'INVOICE' ? 'id,customer_id,invoice_code,amount_paid,canonical_phone_normalized' : 'id,customer_id,normalized_phone,phone_status';
                $table = Biawin_CRM_DB::table($asset->asset_type === 'INVOICE' ? 'invoices' : 'phones');
                $data['current_assets'][] = $wpdb->get_row($wpdb->prepare('SELECT ' . $fields . ' FROM ' . $table . ' WHERE id=%d',$asset->asset_id));
            }
        }
        return $data;
    }
    public static function list_cases(int $after = 0, int $limit = 100): array {
        global $wpdb;
        $limit = max(1,min(200,$limit));
        if (current_user_can('view_ownership_cases')) {
            $scope = current_user_can('assign_ownership_cases') ? '1=1' : $wpdb->prepare('assigned_to_user_id=%d',get_current_user_id());
        } else {
            Biawin_CRM_Ownership_Authorization::require_cap('view_own_ownership_claims');
            $scope = $wpdb->prepare('requested_by_user_id=%d',get_current_user_id());
        }
        return Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,case_code,status,risk_level,current_owner_customer_id,claimant_customer_id,assigned_to_user_id,lock_version,execution_state FROM ' . Biawin_CRM_DB::table('ownership_cases') . ' WHERE id>%d AND (' . $scope . ') ORDER BY id LIMIT %d',max(0,$after),$limit));
    }
}
