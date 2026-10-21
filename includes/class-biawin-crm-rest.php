<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_REST
{
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route('biawin-crm/v1', '/customers/(?P<id>\d+)/interaction', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_interaction'],
            'permission_callback' => fn($request) => current_user_can('bcrm_work_cases') && Biawin_CRM_Frontend::can_access_customer((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/customers/(?P<id>\d+)/generate-code', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'generate_customer_code'],
            'permission_callback' => fn($request) => current_user_can('bcrm_work_cases') && Biawin_CRM_Frontend::can_access_customer((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/customers/(?P<id>\d+)/journey', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'set_journey'],
            'permission_callback' => fn($request) => current_user_can('bcrm_work_cases') && Biawin_CRM_Frontend::can_access_customer((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/customers/(?P<id>\d+)/services', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'select_services'],
            'permission_callback' => fn($request) => current_user_can('bcrm_work_cases') && Biawin_CRM_Frontend::can_access_customer((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/complaints/(?P<id>\d+)/resolve', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'resolve_complaint'],
            'permission_callback' => fn($request) => Biawin_CRM_Workflow_Service::can_manage_complaint((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/service-cases/(?P<id>\d+)/status', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'update_service_case'],
            'permission_callback' => fn() => current_user_can('bcrm_services') || current_user_can('bcrm_manage_cases'),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/deficiency', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'deficiency_cases'],
            'permission_callback' => fn() => current_user_can('view_deficiency_cases') || current_user_can('view_finance_cases') || current_user_can('bcrm_manage_cases'),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/finance', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'deficiency_cases'],
            'permission_callback' => fn() => current_user_can('view_finance_cases') || current_user_can('bcrm_manage_cases'),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/dashboard', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'workflow_dashboard'],
            'permission_callback' => fn() => current_user_can('view_deficiency_cases') || current_user_can('view_finance_cases') || current_user_can('bcrm_manage_cases'),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/assign', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'workflow_assign'],
            'permission_callback' => fn() => current_user_can('assign_deficiency_cases') || current_user_can('assign_finance_cases') || current_user_can('bcrm_manage_cases'),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/export', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'workflow_export'],
            'permission_callback' => fn() => current_user_can('view_deficiency_reports') || current_user_can('view_finance_reports') || current_user_can('bcrm_reports'),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'workflow_case_detail'],
            'permission_callback' => fn($request) => self::can_access_workflow_case((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/(?P<id>\d+)/timeline', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'workflow_case_timeline'],
            'permission_callback' => fn($request) => self::can_access_workflow_case((int)$request['id']),
        ]);
        register_rest_route('biawin-crm/v1', '/cases/(?P<id>\d+)/attachments', [
            'methods' => ['GET','POST'],
            'callback' => [__CLASS__, 'workflow_case_attachments'],
            'permission_callback' => fn($request) => self::can_access_workflow_case((int)$request['id']),
        ]);
        foreach (['contact','send-finance','finance-result','complete','archive'] as $endpoint) {
            register_rest_route('biawin-crm/v1', '/cases/(?P<id>\d+)/' . $endpoint, [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'deficiency_case_action'],
                'permission_callback' => fn($request) => self::can_access_workflow_case((int)$request['id']),
            ]);
        }
    }

    public static function create_interaction(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $customer_id = (int) ($request['id'] ?: $request->get_param('customer_id'));
        if (!$customer_id) {
            return new WP_REST_Response(['ok' => false, 'message' => 'شناسه مشتری در درخواست تماس ارسال نشده است.'], 400);
        }

        $body = $request->get_json_params() ?: $request->get_body_params();
        $now = Biawin_CRM_DB::now();
        $current_user_id = get_current_user_id();
        $interaction_id = absint($body['interaction_id'] ?? 0);
        $save_mode = sanitize_key($body['save_mode'] ?? 'manual');

        $requested_outcome_key = sanitize_key($body['outcome_key'] ?? '');
        $requested_outcome_label = sanitize_text_field($body['outcome'] ?? '');
        if ($requested_outcome_key === '' && $requested_outcome_label !== '') $requested_outcome_key = Biawin_CRM_Workflow_Service::contact_outcome_key_from_label($requested_outcome_label);
        $outcome_def = $requested_outcome_key !== '' ? Biawin_CRM_Workflow_Service::contact_outcome_by_key($requested_outcome_key) : null;
        if (!$outcome_def || empty($outcome_def['active'])) {
            return new WP_REST_Response(['ok'=>false,'message'=>'نتیجه تماس انتخاب‌شده معتبر یا فعال نیست.'],400);
        }
        $requested_outcome_label = (string)$outcome_def['label'];

        $contact_phone = Biawin_CRM_Normalizer::phone($body['contact_phone'] ?? '');
        if ($contact_phone === '') {
            return new WP_REST_Response(['ok'=>false,'message'=>'شماره تماس استفاده‌شده در ثبت کارشناس الزامی است.'], 400);
        }
        $phone_belongs = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d AND normalized_phone=%s',
            $customer_id,
            $contact_phone
        ));
        if (!$phone_belongs) {
            return new WP_REST_Response(['ok'=>false,'message'=>'شماره تماس انتخاب‌شده به این پروفایل متصل نیست. ابتدا شماره را به پروفایل اضافه کنید.'], 400);
        }

        $payload = [
            'customer_id' => $customer_id,
            'agent_user_id' => $current_user_id,
            'type' => sanitize_key($body['type'] ?? 'call'),
            'contact_phone' => $contact_phone,
            'outcome' => $requested_outcome_label,
            'outcome_key' => $requested_outcome_key,
            'satisfaction_status' => sanitize_key($body['satisfaction_status'] ?? ''),
            'request_status' => sanitize_key($body['request_status'] ?? ''),
            'requested_loan_amount' => isset($body['requested_loan_amount']) && $body['requested_loan_amount'] !== '' ? Biawin_CRM_Normalizer::money($body['requested_loan_amount']) : null,
            'requested_service' => self::sanitize_service_selection($body['requested_service'] ?? ''),
            'satisfaction_score' => self::bounded_int($body['satisfaction_score'] ?? null, 1, 10),
            'sales_satisfaction' => self::bounded_int($body['sales_satisfaction'] ?? null, 1, 10),
            'followup_satisfaction' => self::bounded_int($body['followup_satisfaction'] ?? null, 1, 10),
            'service_quality_satisfaction' => self::bounded_int($body['service_quality_satisfaction'] ?? null, 1, 10),
            'service_timing_satisfaction' => self::bounded_int($body['service_timing_satisfaction'] ?? null, 1, 10),
            'promised_services_received' => isset($body['promised_services_received']) && $body['promised_services_received'] !== '' ? (int) !!$body['promised_services_received'] : null,
            'repurchase_intent' => isset($body['repurchase_intent']) && $body['repurchase_intent'] !== '' ? (int) !!$body['repurchase_intent'] : null,
            'referral_intent' => isset($body['referral_intent']) && $body['referral_intent'] !== '' ? (int) !!$body['referral_intent'] : null,
            'declared_paid' => isset($body['declared_paid']) && trim((string) $body['declared_paid']) !== '' ? Biawin_CRM_Normalizer::money($body['declared_paid']) : null,
            'complaint_reason' => sanitize_textarea_field($body['complaint_reason'] ?? ''),
            'complaint_priority' => sanitize_key($body['complaint_priority'] ?? ''),
            'next_followup_at' => self::sanitize_datetime($body['next_followup_at'] ?? ''),
            'notes' => sanitize_textarea_field($body['notes'] ?? ''),
        ];

        $created = false;
        if ($interaction_id) {
            $existing = $wpdb->get_row($wpdb->prepare(
                'SELECT id, customer_id, agent_user_id FROM ' . Biawin_CRM_DB::table('interactions') . ' WHERE id=%d',
                $interaction_id
            ));
            if (!$existing || (int) $existing->customer_id !== $customer_id || (int) $existing->agent_user_id !== $current_user_id) {
                $interaction_id = 0;
            }
        }

        if ($interaction_id) {
            unset($payload['customer_id'], $payload['agent_user_id']);
            $updated = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('interactions'), $payload, ['id' => $interaction_id]);
            if ($updated === false) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'بروزرسانی اطلاعات تماس در دیتابیس انجام نشد.',
                    'db_error' => current_user_can('manage_options') ? $wpdb->last_error : null,
                ], 500);
            }
        } else {
            $payload['created_at'] = $now;
            $inserted = Biawin_CRM_DB::write('insert', Biawin_CRM_DB::table('interactions'), $payload);
            if ($inserted === false || !(int) $wpdb->insert_id) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'ذخیره تماس در دیتابیس انجام نشد. ساختار دیتابیس افزونه نیاز به ترمیم دارد.',
                    'db_error' => current_user_can('manage_options') ? $wpdb->last_error : null,
                ], 500);
            }
            $interaction_id = (int) $wpdb->insert_id;
            $created = true;
        }

        $code = Biawin_CRM_Customer_Service::ensure_customer_code($customer_id);
        if ($code === '' || !Biawin_CRM_Customer_Service::recalculate_customer($customer_id)) {
            return new WP_REST_Response([
                'ok'=>false,
                'message'=>'اطلاعات تماس ذخیره شد اما محاسبات پروفایل کامل نشد. لطفاً «ترمیم دیتابیس» را اجرا یا دوباره تلاش کنید.',
                'interaction_id'=>$interaction_id,
                'db_error'=>current_user_can('manage_options') ? $wpdb->last_error : null,
            ],500);
        }

        if ($created || $save_mode === 'manual') {
            Biawin_CRM_Workflow_Service::activity(
                $customer_id,
                $created ? 'interaction_created' : 'interaction_updated',
                $created ? 'اقدام کارشناس ثبت شد.' : 'اطلاعات تماس توسط کارشناس بروزرسانی شد.',
                'interaction',
                $interaction_id,
                ['outcome' => $requested_outcome_label, 'outcome_key' => $requested_outcome_key]
            );
        }

        $customer = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', $customer_id));
        $outcome = $requested_outcome_label;
        $operational_status = sanitize_key((string)($outcome_def['operational_status'] ?? ''));
        if ($operational_status === '') $operational_status = !empty($body['next_followup_at']) ? 'followup_needed' : 'in_review';
        $status_updated = Biawin_CRM_DB::write('update', Biawin_CRM_DB::table('customers'), ['status' => $operational_status, 'updated_at' => $now], ['id' => $customer_id]);
        if ($status_updated === false) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'اطلاعات تماس ذخیره شد اما بروزرسانی وضعیت پروفایل انجام نشد. لطفاً دوباره تلاش کنید.',
                'db_error' => current_user_can('manage_options') ? $wpdb->last_error : null,
                'interaction_id' => $interaction_id,
            ], 500);
        }

        Biawin_CRM_DB::log('customer', $customer_id, $created ? 'interaction_created' : 'interaction_updated', null, [
            'interaction_id' => $interaction_id,
            'save_mode' => $save_mode,
            'outcome' => $requested_outcome_label,
            'outcome_key' => $requested_outcome_key,
        ]);

        // v0.8.62.0: نتیجه تماس «عودت شده است» → تگ «عودت اعلامی» با نام کارشناس و تاریخ در تاریخچه.
        if ($requested_outcome_key === 'refund_reported' && class_exists('Biawin_CRM_Refund_Register')) {
            Biawin_CRM_Refund_Register::apply_reported_tag($customer_id, $current_user_id, $interaction_id);
        }

        return new WP_REST_Response([
            'ok' => true,
            'interaction_id' => $interaction_id,
            'created' => $created,
            'profile_completeness' => $customer ? $customer->profile_completeness : null,
        ], $created ? 201 : 200);
    }

    public static function generate_customer_code(WP_REST_Request $request): WP_REST_Response
    {
        $code = Biawin_CRM_Customer_Service::ensure_customer_code((int) $request['id']);
        return new WP_REST_Response(['customer_code' => $code], 200);
    }

    public static function set_journey(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $result = Biawin_CRM_Workflow_Service::set_customer_journey(
            (int) $request['id'],
            sanitize_key($body['journey'] ?? ''),
            sanitize_key($body['satisfaction'] ?? ''),
            sanitize_textarea_field($body['note'] ?? '')
        );
        return new WP_REST_Response($result, $result['ok'] ? 200 : 422);
    }

    public static function select_services(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $ids = $body['service_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = preg_split('/[,،]+/', (string) $ids);
        }
        $result = Biawin_CRM_Workflow_Service::select_services((int) $request['id'], $ids, sanitize_textarea_field($body['note'] ?? ''));
        return new WP_REST_Response($result, $result['ok'] ? 201 : 422);
    }

    public static function resolve_complaint(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $result = Biawin_CRM_Workflow_Service::resolve_complaint((int) $request['id'], sanitize_key($body['outcome'] ?? ''), sanitize_textarea_field($body['note'] ?? ''));
        return new WP_REST_Response($result, $result['ok'] ? 200 : 422);
    }

    public static function update_service_case(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $result = Biawin_CRM_Workflow_Service::update_service_case((int) $request['id'], sanitize_key($body['status'] ?? ''), sanitize_textarea_field($body['note'] ?? ''));
        return new WP_REST_Response($result, $result['ok'] ? 200 : 422);
    }

    public static function deficiency_cases(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $route = $request->get_route();
        $department = strpos($route, '/cases/finance') !== false ? 'finance' : sanitize_key($request->get_param('department') ?? '');
        $status = sanitize_key($request->get_param('status') ?? '');
        $where = ["d.status NOT IN ('COMPLETED','ARCHIVED')"];
        $args = [];
        if ($department === 'finance') $where[] = "d.current_department='finance'";
        elseif ($department === 'deficiency') $where[] = "d.current_department='deficiency'";
        if ($status !== '') {
            $where[] = 'd.status=%s';
            $args[] = $status;
        }
        $search = sanitize_text_field($request->get_param('search') ?? $request->get_param('s') ?? '');
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(d.case_code LIKE %s OR c.display_name LIKE %s OR c.customer_code LIKE %s OR c.source_customer_id LIKE %s OR c.sales_manager LIKE %s OR EXISTS(SELECT 1 FROM " . Biawin_CRM_DB::table('phones') . " p WHERE p.customer_id=c.id AND p.normalized_phone LIKE %s) OR EXISTS(SELECT 1 FROM " . Biawin_CRM_DB::table('invoices') . " i WHERE i.customer_id=c.id AND i.invoice_code LIKE %s))";
            array_push($args, $like, $like, $like, $like, $like, $like, $like);
        }
        $agent = absint($request->get_param('agent') ?? 0);
        if ($agent) {
            if ($department === 'finance') $where[] = 'd.finance_agent_id=' . (int)$agent;
            else $where[] = 'd.deficiency_agent_id=' . (int)$agent;
        }
        $sales_manager = sanitize_text_field($request->get_param('sales_manager') ?? '');
        if ($sales_manager !== '') { $where[] = 'c.sales_manager=%s'; $args[] = $sales_manager; }
        foreach (['date_from'=>'>=','date_to'=>'<='] as $param=>$op) {
            $value = sanitize_text_field($request->get_param($param) ?? '');
            if ($value !== '') { $where[] = "d.created_at $op %s"; $args[] = $value; }
        }
        $min_difference = $request->get_param('min_difference');
        if ($min_difference !== null && $min_difference !== '') { $where[] = 'ABS(d.difference_amount) >= %f'; $args[] = Biawin_CRM_Normalizer::money($min_difference); }
        $max_difference = $request->get_param('max_difference');
        if ($max_difference !== null && $max_difference !== '') { $where[] = 'ABS(d.difference_amount) <= %f'; $args[] = Biawin_CRM_Normalizer::money($max_difference); }
        $min_invoices = absint($request->get_param('min_invoices') ?? 0);
        if ($min_invoices) $where[] = "(SELECT COUNT(*) FROM " . Biawin_CRM_DB::table('invoices') . " inv WHERE inv.customer_id=d.customer_id) >= $min_invoices";
        $max_invoices = absint($request->get_param('max_invoices') ?? 0);
        if ($max_invoices) $where[] = "(SELECT COUNT(*) FROM " . Biawin_CRM_DB::table('invoices') . " inv WHERE inv.customer_id=d.customer_id) <= $max_invoices";
        if (!current_user_can('bcrm_manage_cases')) {
            $uid = get_current_user_id();
            $roles = (array)wp_get_current_user()->roles;
            if (current_user_can('view_finance_cases') && !in_array('bcrm_financial_supervisor', $roles, true)) {
                $where[] = 'd.finance_agent_id=%d';
                $args[] = $uid;
            } elseif (current_user_can('view_deficiency_cases') && !in_array('bcrm_deficiency_supervisor', $roles, true)) {
                $where[] = 'd.deficiency_agent_id=%d';
                $args[] = $uid;
            }
        }
        $page = max(1, absint($request->get_param('page') ?? 1));
        $per_page = max(1, min(200, absint($request->get_param('per_page') ?? 50)));
        $offset = ($page - 1) * $per_page;
        $base_from = ' FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' d LEFT JOIN ' . Biawin_CRM_DB::table('customers') . ' c ON c.id=d.customer_id WHERE ' . implode(' AND ', $where);
        $total_sql = 'SELECT COUNT(*)' . $base_from;
        $total = (int)($args ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql));
        $sql = 'SELECT d.id,d.case_code,d.customer_id,d.status,d.current_department,d.difference_amount,d.deficiency_agent_id,d.finance_agent_id,c.display_name,c.customer_code,c.source_customer_id,c.sales_manager' . $base_from . ' ORDER BY d.updated_at DESC,d.id DESC LIMIT %d OFFSET %d';
        $args_with_page = array_merge($args, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args_with_page), ARRAY_A);
        return new WP_REST_Response(['ok'=>true,'cases'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$per_page,'total'=>$total,'total_pages'=>(int)ceil($total / $per_page)]], 200);
    }

    public static function workflow_case_detail(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = (int)$request['id'];
        $case = $wpdb->get_row($wpdb->prepare('SELECT d.*,c.display_name,c.customer_code,c.source_customer_id,c.sales_manager,c.profile_completeness FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' d LEFT JOIN ' . Biawin_CRM_DB::table('customers') . ' c ON c.id=d.customer_id WHERE d.id=%d', $id), ARRAY_A);
        if (!$case) return new WP_REST_Response(['ok'=>false,'message'=>'پرونده پیدا نشد.'], 404);
        $case['phones'] = $wpdb->get_col($wpdb->prepare('SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d ORDER BY is_primary DESC,id', (int)$case['customer_id']));
        $case['invoices'] = $wpdb->get_results($wpdb->prepare('SELECT invoice_code,amount_paid,sales_manager,seller_action_year FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d ORDER BY id DESC LIMIT 200', (int)$case['customer_id']), ARRAY_A);
        return new WP_REST_Response(['ok'=>true,'case'=>$case], 200);
    }

    public static function workflow_dashboard(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $cases = Biawin_CRM_DB::table('deficiency_cases');
        $rows = $wpdb->get_results("SELECT status,current_department,COUNT(*) total FROM $cases GROUP BY status,current_department", ARRAY_A);
        $agents = $wpdb->get_results("SELECT deficiency_agent_id,finance_agent_id,COUNT(*) total FROM $cases WHERE deficiency_agent_id IS NOT NULL OR finance_agent_id IS NOT NULL GROUP BY deficiency_agent_id,finance_agent_id ORDER BY total DESC LIMIT 100", ARRAY_A);
        return new WP_REST_Response(['ok'=>true,'status_counts'=>$rows,'agent_counts'=>$agents], 200);
    }

    public static function workflow_assign(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $department = sanitize_key($body['department'] ?? 'deficiency');
        $case_ids = is_array($body['case_ids'] ?? null) ? $body['case_ids'] : [];
        if (!empty($body['return_assignment'])) {
            $result = Biawin_CRM_Deficiency_Workflow::return_assignments($case_ids, $department);
        } else {
            $agents = is_array($body['agent_user_ids'] ?? null) ? $body['agent_user_ids'] : [absint($body['agent_user_id'] ?? 0)];
            $result = Biawin_CRM_Deficiency_Workflow::assign_cases_multi($case_ids, $agents, $department, absint($body['limit'] ?? 100));
        }
        return new WP_REST_Response($result, !empty($result['ok']) ? 200 : 422);
    }

    public static function workflow_case_timeline(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT a.*,u.display_name FROM ' . Biawin_CRM_DB::table('case_actions') . ' a LEFT JOIN ' . $wpdb->users . ' u ON u.ID=a.user_id WHERE a.case_id=%d ORDER BY a.id ASC LIMIT 500', (int)$request['id']), ARRAY_A);
        return new WP_REST_Response(['ok'=>true,'timeline'=>$rows], 200);
    }

    public static function workflow_case_attachments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $case = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' WHERE id=%d', (int)$request['id']));
        if (!$case) return new WP_REST_Response(['ok'=>false,'message'=>'پرونده پیدا نشد.'], 404);
        if ($request->get_method() === 'POST') {
            $_FILES['case_document'] = ($request->get_file_params()['file'] ?? null) ?: ($request->get_file_params()['case_document'] ?? null);
            if (!$_FILES['case_document']) return new WP_REST_Response(['ok'=>false,'message'=>'فایل ارسال نشده است.'], 400);
            $body = $request->get_body_params();
            $body['case_id'] = (int)$case->id;
            $body['case_action'] = 'upload_document';
            $body['document_type'] = sanitize_key($body['document_type'] ?? 'document');
            $result = Biawin_CRM_Deficiency_Workflow::process_action($body);
            return new WP_REST_Response($result, !empty($result['ok']) ? 201 : 422);
        }
        $rows = $wpdb->get_results($wpdb->prepare('SELECT at.*,p.post_title FROM ' . Biawin_CRM_DB::table('attachments') . ' at LEFT JOIN ' . $wpdb->posts . ' p ON p.ID=at.wp_attachment_id WHERE at.object_type=%s AND at.object_id=%d ORDER BY at.id DESC', 'deficiency_case', (int)$case->id), ARRAY_A);
        foreach ($rows as &$row) $row['url'] = wp_get_attachment_url((int)$row['wp_attachment_id']);
        unset($row);
        return new WP_REST_Response(['ok'=>true,'attachments'=>$rows], 200);
    }

    public static function workflow_export(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT d.*,c.display_name,c.source_customer_id,c.customer_code,c.sales_manager,(SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' p WHERE p.customer_id=d.customer_id ORDER BY p.is_primary DESC,p.id LIMIT 1) phone FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' d LEFT JOIN ' . Biawin_CRM_DB::table('customers') . ' c ON c.id=d.customer_id ORDER BY d.id DESC LIMIT 5000', ARRAY_A);
        $refund_rows = $wpdb->get_col("SELECT DISTINCT case_id FROM " . Biawin_CRM_DB::table('case_actions') . " WHERE action_type='CASE_SENT_TO_FINANCE' AND metadata_json LIKE '%\"refund_status\":\"refunded\"%'");
        $refund_map = array_fill_keys(array_map('intval', (array)$refund_rows), true);
        foreach ($rows as &$row) $row['refund_status'] = !empty($refund_map[(int)($row['id'] ?? 0)]) ? 'refunded' : '';
        unset($row);
        return new WP_REST_Response(['ok'=>true,'columns'=>['Customer ID','نام','موبایل','مدیر فروش','مبلغ اعلامی','مجموع Invoice','Difference','Status','Deficiency Agent','Finance Agent','Finance Result','Refund Status','Final Decision','Created At','Completed At'],'rows'=>$rows], 200);
    }

    public static function deficiency_case_action(WP_REST_Request $request): WP_REST_Response
    {
        $route = $request->get_route();
        $map = [
            'contact'=>'contact',
            'send-finance'=>'send_finance',
            'finance-result'=>'finance_result',
            'complete'=>'complete',
            'archive'=>'archive',
        ];
        $action = '';
        foreach ($map as $slug=>$case_action) {
            $suffix = '/' . $slug;
            if (substr($route, -strlen($suffix)) === $suffix) { $action = $case_action; break; }
        }
        $body = $request->get_json_params() ?: $request->get_body_params();
        $body['case_id'] = (int)$request['id'];
        $body['case_action'] = $action;
        $result = Biawin_CRM_Deficiency_Workflow::process_action($body);
        return new WP_REST_Response($result, !empty($result['ok']) ? 200 : 422);
    }

    private static function can_access_workflow_case(int $case_id): bool
    {
        global $wpdb;
        $case = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' WHERE id=%d', $case_id));
        return $case ? Biawin_CRM_Deficiency_Workflow::can_access_case($case) : false;
    }

    private static function sanitize_datetime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $normalized = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $value);
        if (preg_match('/^(1[34]\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/', $normalized, $m)) {
            [$gy,$gm,$gd] = self::jalali_to_gregorian((int)$m[1], (int)$m[2], (int)$m[3]);
            $hh=(int)($m[4]??0); $ii=(int)($m[5]??0);
            if ($gm>=1 && $gm<=12 && checkdate($gm,$gd,$gy) && $hh<=23 && $ii<=59) return sprintf('%04d-%02d-%02d %02d:%02d:00',$gy,$gm,$gd,$hh,$ii);
            return null;
        }
        $timestamp = strtotime($normalized);
        return $timestamp ? wp_date('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function jalali_to_gregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097); $days %= 146097;
        if ($days > 36524) { $gy += 100 * intdiv(--$days, 36524); $days %= 36524; if ($days >= 365) $days++; }
        $gy += 4 * intdiv($days, 1461); $days %= 1461;
        if ($days > 365) { $gy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
        $gd = $days + 1;
        $sal = [0,31,(($gy%4===0 && $gy%100!==0)||$gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31];
        $gm=1; while($gm<=12 && $gd>$sal[$gm]) { $gd-=$sal[$gm]; $gm++; }
        return [$gy,$gm,$gd];
    }

    private static function sanitize_service_selection($value): string
    {
        if (is_array($value)) {
            return implode('، ', array_values(array_filter(array_map('sanitize_text_field', $value))));
        }
        return sanitize_text_field((string) $value);
    }

    private static function bounded_int($value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return max($min, min($max, (int) $value));
    }
}
