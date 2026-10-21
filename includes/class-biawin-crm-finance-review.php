<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * v0.8.62.28 — Finance receipt review, sales-net style: the finance agent opens a receipt
 * and, in that same window, sees the case context and approves / rejects THAT receipt
 * (with amount / reason) or records the case's final finance result — no page hopping.
 *
 * - Per-receipt review lives in {prefix}bcrm_receipt_reviews (one row per attachment),
 *   created on first use (the activation hook does not run on ZIP updates).
 * - The case-level decision reuses Biawin_CRM_Deficiency_Workflow::process_action()
 *   ('finance_submit'), so every rule/transition/tag of the regular form still applies.
 * - All endpoints: logged-in + nonce (bcrm_fin_review) + can_access_case + capabilities.
 */
class Biawin_CRM_Finance_Review
{
    public const NONCE_ACTION = 'bcrm_fin_review';
    public const SCHEMA_OPTION = 'bcrm_receipt_review_schema';
    public const SCHEMA_VERSION = '2';
    public const REVIEWABLE_STATUSES = ['FINANCE_REVIEW_PENDING', 'FINANCE_ASSIGNED', 'FINANCE_UNDER_REVIEW'];

    private static $table_ready = false;

    public static function init(): void
    {
        add_action('wp_ajax_bcrm_fin_case_review', [__CLASS__, 'ajax_case_review']);
        add_action('wp_ajax_bcrm_fin_receipt_review', [__CLASS__, 'ajax_receipt_review']);
        add_action('wp_ajax_bcrm_fin_case_decide', [__CLASS__, 'ajax_case_decide']);
        add_action('wp_ajax_bcrm_fin_finalize_receipts', [__CLASS__, 'ajax_finalize_receipts']);
    }

    public static function table(): string
    {
        return Biawin_CRM_DB::table('receipt_reviews');
    }

    public static function ensure_table(): bool
    {
        if (self::$table_ready) return true;
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
            self::$table_ready = true;
            return true;
        }
        global $wpdb;
        $table = self::table();
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $wpdb->last_error = '';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
            attachment_id BIGINT UNSIGNED NOT NULL,
            case_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            review_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            review_type VARCHAR(40) NULL,
            reason_key VARCHAR(80) NULL,
            approved_amount DECIMAL(20,2) NULL,
            review_note TEXT NULL,
            details_json LONGTEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            PRIMARY KEY (attachment_id),
            KEY case_id (case_id)
        ) $charset");
        if ((string)$wpdb->last_error !== '') return false;
        $cols = (array)$wpdb->get_col("SHOW COLUMNS FROM $table", 0);
        foreach (['review_type'=>"VARCHAR(40) NULL AFTER review_status", 'reason_key'=>"VARCHAR(80) NULL AFTER review_type", 'details_json'=>"LONGTEXT NULL AFTER review_note"] as $col=>$ddl) {
            if (!in_array($col, $cols, true)) { $wpdb->query("ALTER TABLE $table ADD COLUMN $col $ddl"); if ((string)$wpdb->last_error !== '') return false; }
        }
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        self::$table_ready = true;
        return true;
    }

    public static function status_labels(): array
    {
        return ['pending' => 'بررسی‌نشده', 'approved' => 'تأییدشده', 'rejected' => 'عدم تأیید', 'refunded' => 'عودت شده'];
    }

    public static function financial_option_groups(): array
    {
        return [
            'approve_types' => [
                ['key'=>'without_invoice','label'=>'تأیید بدون فاکتور','active'=>1,'sort'=>10],
                ['key'=>'with_invoice','label'=>'تأیید با فاکتور','active'=>1,'sort'=>20],
            ],
            'reject_reasons' => [
                ['key'=>'invalid_receipt','label'=>'فیش نامعتبر','active'=>1,'sort'=>10],
                ['key'=>'payment_not_found','label'=>'واریز پیدا نشد','active'=>1,'sort'=>20],
                ['key'=>'amount_mismatch','label'=>'مبلغ مغایر است','active'=>1,'sort'=>30],
                ['key'=>'not_customer_payment','label'=>'واریزی متعلق به مشتری نیست','active'=>1,'sort'=>40],
                ['key'=>'invalid_invoice','label'=>'فاکتور نامعتبر','active'=>1,'sort'=>50],
                ['key'=>'insufficient_info','label'=>'اطلاعات ناکافی','active'=>1,'sort'=>60],
                ['key'=>'invalid_document','label'=>'مدرک نامعتبر','active'=>1,'sort'=>70],
                ['key'=>'other','label'=>'سایر','active'=>1,'sort'=>80],
            ],
            'refund_types' => [
                ['key'=>'registered','label'=>'ثبت شده','active'=>1,'sort'=>10],
                ['key'=>'unregistered','label'=>'ثبت نشده','active'=>1,'sort'=>20],
            ],
        ];
    }
    public static function financial_options(string $group, bool $active_only = true): array
    {
        $defaults=self::financial_option_groups(); if(!isset($defaults[$group])) return [];
        $stored=get_option('biawin_crm_finance_'.$group,null); $items=is_array($stored)&&$stored?$stored:$defaults[$group]; $clean=[];$seen=[];
        foreach($items as $i=>$item){ if(!is_array($item)) continue; $key=sanitize_key((string)($item['key']??'')); $label=sanitize_text_field((string)($item['label']??'')); if($key===''||$label===''||isset($seen[$key])) continue; $seen[$key]=1; $clean[]=['key'=>$key,'label'=>$label,'active'=>!empty($item['active'])?1:0,'sort'=>(int)($item['sort']??(($i+1)*10))]; }
        usort($clean,static fn($a,$b)=>($a['sort']<=>$b['sort'])?:strcmp($a['label'],$b['label'])); return $active_only?array_values(array_filter($clean,static fn($i)=>!empty($i['active']))):$clean;
    }
    public static function financial_option_map(string $group): array { $out=[]; foreach(self::financial_options($group,true) as $item)$out[$item['key']]=$item['label']; return $out; }

    /** @return array<int,object> attachment_id => review row */
    public static function reviews_for_case(int $case_id): array
    {
        if (!self::ensure_table()) return [];
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE case_id=%d', $case_id));
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) $out[(int)$row->attachment_id] = $row;
        return $out;
    }

    /** Compact outcome stored with the case-level finance decision. */
    public static function summary(int $case_id): array
    {
        $s = ['approved_count' => 0, 'rejected_count' => 0, 'refunded_count' => 0, 'approved_amount' => 0.0];
        foreach (self::reviews_for_case($case_id) as $row) {
            if ($row->review_status === 'approved') { $s['approved_count']++; $s['approved_amount'] += (float)$row->approved_amount; }
            elseif ($row->review_status === 'rejected') $s['rejected_count']++;
            elseif ($row->review_status === 'refunded') $s['refunded_count']++;
        }
        return $s;
    }

    private static function can_review(object $case): bool
    {
        if (!in_array((string)$case->status, self::REVIEWABLE_STATUSES, true)) return false;
        return current_user_can('review_finance_case') || current_user_can('approve_finance_case') || current_user_can('reject_finance_case') || current_user_can('manage_finance_cases') || current_user_can('bcrm_manage_cases');
    }

    private static function can_decide(object $case): bool
    {
        if (!in_array((string)$case->status, self::REVIEWABLE_STATUSES, true)) return false;
        return current_user_can('review_finance_case') || current_user_can('manage_finance_cases');
    }

    /** Auth + load the case, or send a JSON error and exit. */
    private static function guard_case(): object
    {
        if (!is_user_logged_in()) wp_send_json(['ok' => false, 'message' => 'نشست شما منقضی شده است؛ دوباره وارد شوید.'], 401);
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) wp_send_json(['ok' => false, 'message' => 'اعتبار صفحه منقضی شده است؛ صفحه را دوباره بارگذاری کنید.', 'code' => 'nonce'], 403);
        global $wpdb;
        $case_id = absint($_POST['case_id'] ?? 0);
        $case = $case_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' WHERE id=%d', $case_id)) : null;
        if (!$case) wp_send_json(['ok' => false, 'message' => 'پرونده پیدا نشد.'], 404);
        $can_view = current_user_can('view_finance_cases') || current_user_can('view_deficiency_cases') || current_user_can('bcrm_manage_cases');
        if (!$can_view || !Biawin_CRM_Deficiency_Workflow::can_access_case($case)) wp_send_json(['ok' => false, 'message' => 'دسترسی به این پرونده مجاز نیست.'], 403);
        return $case;
    }

    /** Everything the modal needs for one case. */
    public static function payload(object $case): array
    {
        global $wpdb;
        $case_id = (int)$case->id;
        $customer = $wpdb->get_row($wpdb->prepare('SELECT display_name, customer_code, source_customer_id, sales_manager, declared_paid, total_paid FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id=%d', (int)$case->customer_id));
        $finance_agent = !empty($case->finance_agent_id) ? get_user_by('id', (int)$case->finance_agent_id) : false;
        $phones = $wpdb->get_col($wpdb->prepare('SELECT normalized_phone FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d ORDER BY is_primary DESC,id', (int)$case->customer_id));
        $invoice_count = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d', (int)$case->customer_id));
        $invoice_total = (float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(CASE WHEN COALESCE(exclude_from_total,0)=1 THEN 0 ELSE amount_paid END),0) FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE customer_id=%d', (int)$case->customer_id));
        $atts = $wpdb->get_results($wpdb->prepare('SELECT at.*, p.post_title FROM ' . Biawin_CRM_DB::table('attachments') . ' at LEFT JOIN ' . $wpdb->posts . ' p ON p.ID=at.wp_attachment_id WHERE at.object_type=%s AND at.object_id=%d ORDER BY at.id ASC', 'deficiency_case', $case_id));
        $atts = is_array($atts) ? $atts : [];
        $analysis = Biawin_CRM_Deficiency_Workflow::analyze_case_receipts($atts);
        $reviews = self::reviews_for_case($case_id);
        $labels = self::status_labels();
        $receipts = [];
        $approved_total = 0.0; $pending = 0; $approved_n = 0; $rejected_n = 0; $refunded_n = 0;
        foreach ($analysis['rows'] as $row) {
            $att = $row['att'];
            $wp_id = (int)$att->wp_attachment_id;
            $url = $wp_id && function_exists('wp_get_attachment_url') ? (string)wp_get_attachment_url($wp_id) : '';
            $mime = $wp_id && function_exists('get_post_mime_type') ? (string)get_post_mime_type($wp_id) : '';
            $path = strtolower((string)parse_url($url, PHP_URL_PATH));
            $kind = (strpos($mime, 'image/') === 0 || preg_match('/\.(jpe?g|png|gif|webp|avif|bmp)$/', $path)) ? 'image' : (($mime === 'application/pdf' || substr($path, -4) === '.pdf') ? 'pdf' : 'file');
            if (in_array($mime, ['image/heic', 'image/heif', 'image/tiff'], true) || preg_match('/\.(heic|heif|tiff?)$/', $path)) $kind = 'file'; // most browsers cannot render these inline
            $review = $reviews[(int)$att->id] ?? null;
            $status = $review ? (string)$review->review_status : 'pending';
            if ($status === 'approved') { $approved_n++; $approved_total += (float)$review->approved_amount; }
            elseif ($status === 'rejected') $rejected_n++;
            elseif ($status === 'refunded') $refunded_n++;
            else $pending++;
            $uploader = $att->uploaded_by ? get_user_by('id', (int)$att->uploaded_by) : false;
            $reviewer = ($review && $review->reviewed_by) ? get_user_by('id', (int)$review->reviewed_by) : false;
            $review_details = $review && !empty($review->details_json) ? (array)json_decode((string)$review->details_json, true) : [];
            foreach (['invoice_date','payment_date'] as $date_key) if (!empty($review_details[$date_key])) $review_details[$date_key] = self::display_date((string)$review_details[$date_key]);
            $receipts[] = [
                'id'            => (int)$att->id,
                'title'         => (string)($att->post_title ?: ('فیش #' . (int)$att->id)),
                'kind'          => $kind,
                'mime'          => $mime,
                'url'           => $url,
                'is_receipt'    => (string)$att->file_kind === 'bank_receipt',
                'amount'        => $row['amount'],
                'note'          => trim((string)preg_replace('/\s*\|?\s*مبلغ فیش:.*/u', '', (string)$att->note)),
                'uploaded_at'   => Biawin_CRM_Deficiency_Workflow::jalali_datetime((string)$att->created_at),
                'uploaded_by'   => $uploader ? (string)$uploader->display_name : '',
                'review'        => [
                    'status'      => $status,
                    'label'       => $labels[$status] ?? $status,
                    'amount'      => $review && $review->approved_amount !== null ? (float)$review->approved_amount : null,
                    'note'        => $review ? (string)$review->review_note : '',
                    'type'        => $review ? (string)($review->review_type ?? '') : '',
                    'reason_key'  => $review ? (string)($review->reason_key ?? '') : '',
                    'details'     => $review_details,
                    'by'          => $reviewer ? (string)$reviewer->display_name : '',
                    'at'          => $review ? Biawin_CRM_Deficiency_Workflow::jalali_datetime((string)$review->reviewed_at) : '',
                ],
            ];
        }
        $history = [];
        $actions = $wpdb->get_results($wpdb->prepare('SELECT a.action_type, a.description, a.metadata_json, a.created_at, u.display_name FROM ' . Biawin_CRM_DB::table('case_actions') . ' a LEFT JOIN ' . $wpdb->users . ' u ON u.ID=a.user_id WHERE a.case_id=%d ORDER BY a.id DESC LIMIT 15', $case_id));
        foreach (is_array($actions) ? $actions : [] as $a) {
            $meta = json_decode((string)$a->metadata_json, true);
            $note = (string)($a->description ?? '');
            if ($note === '' && is_array($meta)) $note = (string)($meta['note'] ?? ($meta['reason'] ?? ''));
            $history[] = [
                'label' => Biawin_CRM_Deficiency_Workflow::label_for_event((string)$a->action_type),
                'by'    => (string)($a->display_name ?: 'سیستم'),
                'at'    => Biawin_CRM_Deficiency_Workflow::jalali_datetime((string)$a->created_at),
                'note'  => $note,
            ];
        }
        $status_labels = Biawin_CRM_Deficiency_Workflow::status_labels();
        $reported = $customer && $customer->declared_paid !== null ? (float)$customer->declared_paid : (float)$case->reported_amount;
        $registered_live = $customer ? (float)$customer->total_paid : (float)$case->registered_invoice_amount;
        return [
            'case' => [
                'id'               => $case_id,
                'code'             => (string)$case->case_code,
                'status'           => (string)$case->status,
                'status_label'     => $status_labels[(string)$case->status] ?? (string)$case->status,
                'customer_name'    => $customer ? (string)($customer->display_name ?: 'پروفایل بدون نام') : '-',
                'customer_code'    => $customer ? (string)($customer->source_customer_id ?: $customer->customer_code) : '',
                'sales_manager'    => $customer ? (string)$customer->sales_manager : '',
                'finance_agent_name'=> $finance_agent ? (string)$finance_agent->display_name : 'تخصیص‌نیافته',
                'phones'           => array_values(array_map('strval', is_array($phones) ? $phones : [])),
                'reported_amount'  => $reported,
                'registered_amount'=> $registered_live,
                'invoice_count'     => $invoice_count,
                'invoice_total'     => $invoice_total,
                'difference'       => abs($reported - $registered_live),
                'receipts_total'   => (float)$analysis['total'],
                'approved_total'   => $approved_total,
                'remaining'        => max(0, $reported - (float)$analysis['total']),
                'description'      => (string)($case->deficiency_description ?? ''),
            ],
            'counts'   => ['total' => count($receipts), 'pending' => $pending, 'approved' => $approved_n, 'rejected' => $rejected_n, 'refunded' => $refunded_n],
            'receipts' => $receipts,
            'history'  => $history,
            'perms'    => ['review' => self::can_review($case), 'decide' => self::can_decide($case)],
            'approve_types' => self::financial_option_map('approve_types'),
            'reject_reasons' => self::financial_option_map('reject_reasons'),
            'refund_types' => self::financial_option_map('refund_types'),
        ];
    }

    /** AJAX: modal data for one case. */
    public static function ajax_case_review(): void
    {
        nocache_headers();
        $case = self::guard_case();
        self::ensure_table();
        wp_send_json(['ok' => true] + self::payload($case));
    }

    /** AJAX: approve / reject / reset ONE receipt (attachment) of the case. */
    public static function ajax_receipt_review(): void
    {
        $case = self::guard_case();
        $invoice_links = [];
        $links_raw = isset($_POST['invoice_links_json']) ? wp_unslash((string)$_POST['invoice_links_json']) : '';
        if ($links_raw !== '') {
            $decoded = json_decode($links_raw, true);
            if (is_array($decoded)) $invoice_links = $decoded;
        }
        $refund_links = [];
        $refund_links_raw = isset($_POST['refund_links_json']) ? wp_unslash((string)$_POST['refund_links_json']) : '';
        if ($refund_links_raw !== '') {
            $decoded = json_decode($refund_links_raw, true);
            if (is_array($decoded)) $refund_links = $decoded;
        }
        $result = self::review_receipt($case, absint($_POST['attachment_id'] ?? 0), sanitize_key(wp_unslash($_POST['decision'] ?? '')), (string)wp_unslash($_POST['amount'] ?? ''), sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')), sanitize_key(wp_unslash($_POST['subtype'] ?? '')), sanitize_key(wp_unslash($_POST['reason_key'] ?? '')), ['invoice_number'=>sanitize_text_field(wp_unslash($_POST['invoice_number'] ?? '')), 'customer_phone'=>sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? '')), 'invoice_links'=>$invoice_links, 'refund_links'=>$refund_links, 'invoice_amount'=>(string)wp_unslash($_POST['invoice_amount'] ?? ''), 'invoice_date'=>sanitize_text_field(wp_unslash($_POST['invoice_date'] ?? '')), 'payment_date'=>sanitize_text_field(wp_unslash($_POST['payment_date'] ?? '')), 'tracking_number'=>sanitize_text_field(wp_unslash($_POST['tracking_number'] ?? '')), 'bank_or_gateway'=>sanitize_text_field(wp_unslash($_POST['bank_or_gateway'] ?? ''))]);
        if (empty($result['ok'])) wp_send_json($result, 400);
        global $wpdb;
        $fresh = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('deficiency_cases') . ' WHERE id=%d', (int)$case->id));
        wp_send_json($result + self::payload($fresh ?: $case));
    }

    /** Core per-receipt review (shared by AJAX and tests). */
    public static function review_receipt(object $case, int $attachment_id, string $decision, string $amount_raw = '', string $note = '', string $subtype = '', string $reason_key = '', array $details = []): array
    {
        if (!self::can_review($case)) return ['ok'=>false,'message'=>in_array((string)$case->status,self::REVIEWABLE_STATUSES,true)?'دسترسی بررسی فیش ندارید.':'این پرونده در وضعیت بررسی مالی نیست؛ بررسی فیش فقط در صف مالی ممکن است.'];
        if (!in_array($decision,['approve','reject','refund','reset'],true)) return ['ok'=>false,'message'=>'اقدام نامعتبر است.'];
        if (!self::ensure_table()) return ['ok'=>false,'message'=>'جدول بررسی فیش‌ها در دیتابیس ساخته نشد.'];
        global $wpdb; $att=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Biawin_CRM_DB::table('attachments').' WHERE id=%d AND object_type=%s AND object_id=%d',$attachment_id,'deficiency_case',(int)$case->id)); if(!$att)return ['ok'=>false,'message'=>'این فیش متعلق به این پرونده نیست.'];
        $table=self::table(); $receipt_amount=null; if(preg_match('/مبلغ فیش:\s*([\d\x{06F0}-\x{06F9}]+(?:[.,،][\d\x{06F0}-\x{06F9}]+)*)/u',(string)$att->note,$m))$receipt_amount=Biawin_CRM_Normalizer::money($m[1]);
        if($decision==='reset'){ $wpdb->delete($table,['attachment_id'=>$attachment_id]); Biawin_CRM_Deficiency_Workflow::action((int)$case->id,(int)$case->customer_id,'finance','RECEIPT_REVIEW_RESET',(string)$case->status,(string)$case->status,['attachment_id'=>$attachment_id]); return ['ok'=>true,'message'=>'فیش به حالت «بررسی‌نشده» برگشت.']; }
        $status=$decision==='approve'?'approved':($decision==='refund'?'refunded':'rejected'); $amount=null; $reason_key=sanitize_key($reason_key); $subtype=sanitize_key($subtype); $safe_details=[];
        if($decision==='approve'){ $types=self::financial_option_map('approve_types'); if(!isset($types[$subtype]))return ['ok'=>false,'message'=>'نوع تأیید را انتخاب کنید.']; $amount=trim($amount_raw)!==''?Biawin_CRM_Normalizer::money($amount_raw):$receipt_amount; if($amount===null||$amount<=0)return ['ok'=>false,'message'=>'مبلغ این فیش معتبر نیست.']; if($subtype==='with_invoice'){
            $links=[]; $raw_links=$details['invoice_links']??[];
            if(is_array($raw_links)) foreach($raw_links as $link){ if(!is_array($link))continue; $inv=sanitize_text_field((string)($link['invoice_number']??'')); $phone=Biawin_CRM_Normalizer::phone((string)($link['customer_phone']??'')); if($inv===''&&$phone==='')continue; if($inv===''||$phone==='')return ['ok'=>false,'message'=>'در هر ردیف اتصال، شماره فاکتور و شماره تماس هر دو الزامی هستند.']; $key=$inv.'|'.$phone; $links[$key]=['invoice_number'=>$inv,'customer_phone'=>$phone]; }
            if(!$links){ $inv=sanitize_text_field((string)($details['invoice_number']??'')); $phone=Biawin_CRM_Normalizer::phone((string)($details['customer_phone']??'')); if($inv!==''||$phone!==''){ if($inv===''||$phone==='')return ['ok'=>false,'message'=>'شماره فاکتور و شماره تماس مشتری برای «تأیید با فاکتور» الزامی است.']; $links[$inv.'|'.$phone]=['invoice_number'=>$inv,'customer_phone'=>$phone]; } }
            $links=array_values($links); if(!$links)return ['ok'=>false,'message'=>'حداقل یک شماره فاکتور و شماره تماس برای «تأیید با فاکتور» وارد کنید.'];
            $safe_details=['invoice_links'=>$links,'invoice_number'=>$links[0]['invoice_number'],'customer_phone'=>$links[0]['customer_phone']];
        } else { $safe_details=['payment_date'=>Biawin_CRM_Normalizer::date((string)($details['payment_date']??'')),'tracking_number'=>sanitize_text_field((string)($details['tracking_number']??'')),'bank_or_gateway'=>sanitize_text_field((string)($details['bank_or_gateway']??''))]; } }
        elseif($decision==='reject'){ $reasons=self::financial_option_map('reject_reasons'); if(!isset($reasons[$reason_key]))return ['ok'=>false,'message'=>'دلیل عدم تأیید را انتخاب کنید.']; }
        else {
            $types=self::financial_option_map('refund_types');
            if(!isset($types[$subtype])) return ['ok'=>false,'message'=>'وضعیت عودت را انتخاب کنید.'];
            if($subtype==='registered'){
                $links=[];
                $raw_links=$details['refund_links']??[];
                if(is_array($raw_links)) foreach($raw_links as $link){
                    if(!is_array($link)) continue;
                    $inv=sanitize_text_field((string)($link['invoice_number']??''));
                    $phone=Biawin_CRM_Normalizer::phone((string)($link['customer_phone']??''));
                    $link_amount=Biawin_CRM_Normalizer::money($link['invoice_amount']??'');
                    if($inv===''&&$phone===''&&$link_amount<=0) continue;
                    if($inv===''||$link_amount<=0) return ['ok'=>false,'message'=>'در هر ردیف عودت ثبت‌شده، شماره فاکتور و مبلغ الزامی است.'];
                    $links[]=['invoice_number'=>$inv,'customer_phone'=>$phone,'invoice_amount'=>$link_amount];
                }
                if(!$links){
                    $inv=sanitize_text_field((string)($details['invoice_number']??''));
                    $phone=Biawin_CRM_Normalizer::phone((string)($details['customer_phone']??''));
                    $link_amount=Biawin_CRM_Normalizer::money($details['invoice_amount']??'');
                    if($inv!==''||$phone!==''||$link_amount>0) $links[]=['invoice_number'=>$inv,'customer_phone'=>$phone,'invoice_amount'=>$link_amount];
                }
                if(!$links || $links[0]['invoice_number']==='' || (float)$links[0]['invoice_amount']<=0) return ['ok'=>false,'message'=>'برای عودت «ثبت شده»، حداقل یک شماره فاکتور و مبلغ ثبت الزامی است.'];
                $amount=0.0; foreach($links as $link) $amount+=(float)$link['invoice_amount'];
                $safe_details=['refund_links'=>$links,'invoice_number'=>$links[0]['invoice_number'],'customer_phone'=>$links[0]['customer_phone'],'invoice_amount'=>$links[0]['invoice_amount']];
            }
        }
        $data=['case_id'=>(int)$case->id,'review_status'=>$status,'review_type'=>$subtype?:null,'reason_key'=>$reason_key?:null,'approved_amount'=>$amount,'review_note'=>$note,'details_json'=>$safe_details?wp_json_encode($safe_details):null,'reviewed_by'=>get_current_user_id()?:null,'reviewed_at'=>Biawin_CRM_DB::now()]; $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE attachment_id=%d",$attachment_id)); $saved=$exists?$wpdb->update($table,$data,['attachment_id'=>$attachment_id]):$wpdb->insert($table,['attachment_id'=>$attachment_id]+$data); if($saved===false)return ['ok'=>false,'message'=>'ذخیره نتیجه بررسی فیش ناموفق بود.'];
        $from_status=(string)$case->status; $to_status=$from_status;
        if($from_status==='FINANCE_ASSIGNED'){
            $moved=$wpdb->update(Biawin_CRM_DB::table('deficiency_cases'),['status'=>'FINANCE_UNDER_REVIEW','updated_at'=>Biawin_CRM_DB::now()],['id'=>(int)$case->id,'status'=>'FINANCE_ASSIGNED']);
            if($moved!==false){ $to_status='FINANCE_UNDER_REVIEW'; $case->status='FINANCE_UNDER_REVIEW'; }
        }
        $event=$decision==='approve'?'RECEIPT_APPROVED':($decision==='refund'?'RECEIPT_REFUNDED':'RECEIPT_REJECTED'); $meta=['attachment_id'=>$attachment_id,'receipt_amount'=>$receipt_amount,'review_type'=>$subtype,'reason_key'=>$reason_key,'details'=>$safe_details]; if($amount!==null)$meta['approved_amount']=$amount; if($note!=='')$meta['description']=$note; Biawin_CRM_Deficiency_Workflow::action((int)$case->id,(int)$case->customer_id,'finance',$event,$from_status,$to_status,$meta);
        return ['ok'=>true,'message'=>$decision==='approve'?'فیش تأیید شد.':($decision==='refund'?'وضعیت فیش «عودت شده» ثبت شد.':'عدم تأیید فیش ثبت شد.')];
    }

    /** Display canonical Gregorian DB dates back in the Jalali picker format. */
    private static function display_date(string $value): string
    {
        $value = trim($value); if ($value === '') return '';
        if (preg_match('/^(13|14)\d{2}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $value)) return str_replace('-', '/', $value);
        if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) return $value;
        $gy=(int)$m[1];$gm=(int)$m[2];$gd=(int)$m[3];
        $gdm=[0,31,59,90,120,151,181,212,243,273,304,334]; $gy2=$gm>2?$gy+1:$gy;
        $days=355666+(365*$gy)+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400)+$gd+$gdm[$gm-1];
        $jy=-1595+33*(int)($days/12053);$days%=12053;$jy+=4*(int)($days/1461);$days%=1461;
        if($days>365){$jy+=(int)(($days-1)/365);$days=($days-1)%365;}
        if($days<186){$jm=1+(int)($days/31);$jd=1+($days%31);}else{$jm=7+(int)(($days-186)/30);$jd=1+(($days-186)%30);}
        return sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
    }

    /** v0.8.65.5: finish finance only after every bank receipt has a final review, then return the case to deficiency. */
    public static function ajax_finalize_receipts(): void
    {
        $case = self::guard_case();
        if (!self::can_decide($case)) wp_send_json(['ok'=>false,'message'=>'دسترسی برگرداندن پرونده از مالی را ندارید.'],403);

        global $wpdb;
        $cases   = Biawin_CRM_DB::table('deficiency_cases');
        $atts    = Biawin_CRM_DB::table('attachments');
        $reviews = self::table();
        $case_id = (int)$case->id;

        $wpdb->query('START TRANSACTION');
        try {
            // Lock the case so two finalize clicks cannot return the same case twice.
            $locked = $wpdb->get_row($wpdb->prepare("SELECT * FROM $cases WHERE id=%d FOR UPDATE", $case_id));
            if (!$locked) throw new RuntimeException('پرونده پیدا نشد.');

            // Idempotent success for a concurrent/repeated request that already finished correctly.
            if ((string)$locked->status === 'RETURNED_TO_DEFICIENCY' && (string)$locked->current_department === 'deficiency' && !empty($locked->finance_finished_at)) {
                $wpdb->query('COMMIT');
                wp_send_json(['ok'=>true,'message'=>'این پرونده قبلاً به رفع ناقصی برگشت داده شده است.','closed'=>true,'already_finalized'=>true]);
            }

            if (!in_array((string)$locked->status, self::REVIEWABLE_STATUSES, true)) {
                throw new RuntimeException('وضعیت پرونده تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            }

            // Recompute from canonical production tables while the case row is locked; never trust stale browser counts.
            $total = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $atts WHERE object_type='deficiency_case' AND file_kind='bank_receipt' AND object_id=%d",
                $case_id
            ));
            $reviewed = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT rr.attachment_id)
                 FROM $reviews rr
                 INNER JOIN $atts a ON a.id=rr.attachment_id
                 WHERE rr.case_id=%d
                   AND a.object_type='deficiency_case'
                   AND a.file_kind='bank_receipt'
                   AND a.object_id=%d
                   AND rr.review_status IN ('approved','rejected','refunded')",
                $case_id, $case_id
            ));

            if ($total <= 0) throw new RuntimeException('این پرونده فیش بانکی برای تعیین تکلیف ندارد.');
            if ($reviewed < $total) {
                throw new RuntimeException('ابتدا وضعیت همه فیش‌ها را تعیین کنید؛ هنوز ' . ($total - $reviewed) . ' فیش بررسی‌نشده وجود دارد.');
            }

            $summary = self::summary($case_id);
            $now = Biawin_CRM_DB::now();
            $meta = [
                'receipt_reviews' => $summary,
                'receipt_count' => $total,
                'reviewed_count' => $reviewed,
                'completion_mode' => 'per_receipt',
                'description' => 'تمام فیش‌ها توسط مالی تعیین تکلیف شدند و پرونده به رفع ناقصی برگشت داده شد.',
            ];

            // No extra "final approval" state: this is the single terminal finance transition requested by the workflow.
            $ok = $wpdb->update($cases, [
                'status' => 'RETURNED_TO_DEFICIENCY',
                'current_department' => 'deficiency',
                'finance_result_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
                'finance_finished_at' => $now,
                'updated_at' => $now,
            ], [
                'id' => $case_id,
                'status' => (string)$locked->status,
            ]);
            if ($ok === false) throw new RuntimeException('برگرداندن پرونده به رفع ناقصی ذخیره نشد.');

            Biawin_CRM_Deficiency_Workflow::action(
                $case_id,
                (int)$locked->customer_id,
                'finance',
                'FINANCE_RETURNED_TO_DEFICIENCY',
                (string)$locked->status,
                'RETURNED_TO_DEFICIENCY',
                $meta
            );

            $wpdb->query('COMMIT');
            wp_send_json([
                'ok'=>true,
                'message'=>'بررسی مالی تکمیل شد و پرونده به رفع ناقصی برگشت داده شد.',
                'closed'=>true,
                'status'=>'RETURNED_TO_DEFICIENCY',
                'current_department'=>'deficiency',
                'finance_finished_at'=>$now,
            ]);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json(['ok'=>false,'message'=>$e->getMessage() ?: 'عملیات انجام نشد.'],400);
        }
    }

    /** Legacy endpoint retained for backward compatibility; the current UI no longer uses a case-level result form. */
    public static function ajax_case_decide(): void
    {
        $case = self::guard_case();
        if (!self::can_decide($case)) wp_send_json(['ok' => false, 'message' => 'ثبت نتیجه نهایی مالی برای این پرونده/کاربر مجاز نیست.'], 403);
        $fields = ['finance_result', 'invoice_number', 'customer_phone', 'invoice_date', 'invoice_amount', 'approved_amount', 'payment_date', 'tracking_number', 'bank_or_gateway', 'reject_reason', 'description', 'receipt_attachment_id'];
        $raw = ['case_id' => (int)$case->id, 'case_action' => 'finance_submit'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) $raw[$f] = is_scalar($_POST[$f]) ? wp_unslash((string)$_POST[$f]) : '';
        }
        $result = Biawin_CRM_Deficiency_Workflow::process_action($raw);
        wp_send_json(['ok' => !empty($result['ok']), 'message' => (string)($result['message'] ?? (!empty($result['ok']) ? 'نتیجه مالی ثبت شد.' : 'ثبت نتیجه مالی ناموفق بود.'))], !empty($result['ok']) ? 200 : 400);
    }
}
