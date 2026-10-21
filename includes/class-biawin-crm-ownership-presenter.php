<?php
if (!defined('ABSPATH')) { exit; }

/**
 * C3 read-only presentation layer for Ownership Reconciliation.
 *
 * Every case read starts from the authoritative backend reads
 * (Biawin_CRM_Ownership_Workflow::get()/list_cases()); this class only adds
 * labels and display context for rows the backend already authorized. It never
 * writes, never evaluates risk and never decides which action is legal: the
 * action hints below are visibility hints only, the backend command remains
 * the final authority.
 */
class Biawin_CRM_Ownership_Presenter
{
    const STATUSES = [
        'DRAFT' => 'پیش‌نویس',
        'SUBMITTED' => 'ثبت‌شده — در انتظار تخصیص',
        'ASSIGNED' => 'تخصیص‌یافته به کارشناس',
        'UNDER_REVIEW' => 'در حال بررسی',
        'AWAITING_EVIDENCE' => 'در انتظار مدرک',
        'AWAITING_CUSTOMER_CONFIRMATION' => 'در انتظار تأیید مشتری',
        'AWAITING_SUPERVISOR_APPROVAL' => 'در انتظار تأیید سرپرست',
        'DECIDED' => 'تصمیم‌گیری‌شده',
        'CLOSED' => 'بسته‌شده',
        'CANCELLED' => 'لغوشده',
    ];
    const STATUS_TONES = [
        'DRAFT' => 'neutral', 'SUBMITTED' => 'primary', 'ASSIGNED' => 'primary', 'UNDER_REVIEW' => 'primary',
        'AWAITING_EVIDENCE' => 'warning', 'AWAITING_CUSTOMER_CONFIRMATION' => 'warning',
        'AWAITING_SUPERVISOR_APPROVAL' => 'warning', 'DECIDED' => 'success', 'CLOSED' => 'neutral', 'CANCELLED' => 'neutral',
    ];
    const EXECUTION = [
        'NOT_STARTED' => 'اجرا نشده',
        'COMMITTED' => 'انتقال ثبت شد — نهایی‌سازی در جریان',
        'FINALIZATION_FAILED' => 'انتقال ثبت شد — نهایی‌سازی ناتمام',
        'FINALIZED' => 'اجرا و نهایی‌سازی کامل',
    ];
    const RISK = ['NORMAL' => 'ریسک عادی', 'HIGH_RISK' => 'پرریسک (High Risk)'];
    const RISK_REASONS = [
        'DISPUTED_PHONE' => 'انتقال مالکیت شماره مورد اختلاف',
        'PHONE_TRANSFER' => 'انتقال مالکیت شماره فعلی فاکتور',
        'MULTIPLE_INVOICES' => 'بیش از یک فاکتور در دامنه پرونده',
        'SCOPE_EXPANDED' => 'گسترش دامنه پرونده پس از ثبت (اصلاحیه)',
        'PLAN_OVERRIDE' => 'تغییر برنامه انتقال توسط کارشناس',
        'CONFLICTING_EVIDENCE' => 'ثبت مدرک متعارض',
        'OTP_DELIVERY_FAILED' => 'ارسال کد تأیید ناموفق بود',
        'OTP_FAILED' => 'کد تأیید نادرست وارد شد',
        'OTP_EXPIRED' => 'کد تأیید منقضی شد',
        'MANUAL_ESCALATION' => 'ارجاع دستی به سطح پرریسک',
        'PRIOR_HIGH_RISK' => 'سابقه پرریسک ماندگار',
    ];
    const DECISIONS = [
        'TRANSFER_APPROVED' => 'تأیید انتقال',
        'CLAIM_REJECTED' => 'رد درخواست',
        'CLAIM_WITHDRAWN' => 'انصراف از درخواست',
    ];
    const METHODS = [
        'CLAIMANT_RECEIPT' => 'رسید پرداخت مدعی',
        'CURRENT_OWNER_CONSENT' => 'رضایت مالک فعلی (تماس ثبت‌شده)',
        'OTP_VERIFICATION' => 'تأیید کد یکبارمصرف شماره مورد اختلاف',
        'DOCUMENT_REVIEW' => 'بررسی مدارک',
        'MULTI_EVIDENCE' => 'مجموعه مدارک',
    ];
    const SUPERVISOR = ['NOT_REQUIRED' => 'نیاز ندارد', 'PENDING' => 'در انتظار تأیید سرپرست', 'APPROVED' => 'تأیید سرپرست', 'REJECTED' => 'بازگردانده به بررسی'];
    const PHONE_MODES = [
        'KEEP_CURRENT_PHONE' => 'حفظ شماره فعلی فاکتور',
        'USE_TARGET_PROFILE_PHONE' => 'استفاده از شماره موجود پروفایل مقصد',
        'CLAIM_CURRENT_PHONE' => 'انتقال مالکیت شماره فعلی',
        'PROPOSE_NEW_PHONE' => 'ثبت شماره جدید برای این فاکتور',
    ];
    const PHONE_MODE_HELP = [
        'KEEP_CURRENT_PHONE' => 'فاکتور با همان شماره فعلی منتقل می‌شود. مالکیت خود شماره در پروفایل مبدأ باقی می‌ماند.',
        'USE_TARGET_PROFILE_PHONE' => 'یکی از شماره‌های فعلی پروفایل مقصد شماره رسمی این فاکتور می‌شود. مالکیت هیچ شماره‌ای جابه‌جا نمی‌شود.',
        'CLAIM_CURRENT_PHONE' => 'شماره فعلی فاکتور هم به مقصد منتقل می‌شود. باید خود شماره را جداگانه به‌عنوان دارایی انتخاب کنید؛ پرونده پرریسک می‌شود.',
        'PROPOSE_NEW_PHONE' => 'یک شماره جدید برای فاکتور ثبت می‌شود و فقط پس از اجرای تأییدشده در پروفایل مقصد ایجاد می‌شود. اگر شماره متعلق به پروفایل مبدأ باشد باید جداگانه ادعا شود.',
    ];
    /** Whether a phone mode moves ownership of a phone record (C2 semantics, presentation only). */
    const PHONE_MODE_OWNERSHIP = [
        'KEEP_CURRENT_PHONE' => 'مالکیت شماره منتقل نمی‌شود',
        'USE_TARGET_PROFILE_PHONE' => 'مالکیت شماره منتقل نمی‌شود',
        'CLAIM_CURRENT_PHONE' => 'مالکیت خود شماره منتقل می‌شود (پرونده پرریسک)',
        'PROPOSE_NEW_PHONE' => 'شماره جدید در مقصد ایجاد می‌شود؛ مالکیت شماره موجودی منتقل نمی‌شود',
    ];
    const ACTIONS = ['TRANSFER' => 'انتقال', 'REVIEW_ONLY' => 'فقط بررسی (بدون انتقال)'];
    const EVIDENCE = ['ownership_evidence' => 'مدرک مالکیت', 'claimant_receipt' => 'رسید پرداخت مدعی', 'consent_document' => 'رضایت‌نامه کتبی'];
    const EVIDENCE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'audio/mpeg', 'audio/wav'];
    const OTP_PURPOSES = ['CONFIRM_PHONE_CONTROL' => 'تأیید کنترل شماره', 'CONFIRM_TRANSFER_CONSENT' => 'تأیید رضایت انتقال'];
    const CONTACT_RESULTS = [
        'current_owner_consent' => 'رضایت مالک فعلی',
        'current_owner_objection' => 'اعتراض مالک فعلی',
        'claimant_confirmed' => 'تأیید مدعی',
        'no_answer' => 'عدم پاسخ',
        'other' => 'سایر',
    ];
    const HOLD_REASONS = ['CLAIM_REJECTED' => 'رد درخواست', 'CLAIM_WITHDRAWN' => 'انصراف از درخواست', 'CANCELLED' => 'لغو پرونده', 'FINALIZED' => 'اجرای کامل انتقال'];
    const LOCK_REASONS = ['OWNERSHIP_TRANSFER_APPROVED' => 'انتقال تأییدشده تطبیق مالکیت', 'PROFILE_COMPLETED' => 'تکمیل پروفایل'];
    const EVENTS = [
        'CASE_CREATED' => 'ایجاد پیش‌نویس پرونده',
        'CASE_DRAFT_UPDATED' => 'ویرایش پیش‌نویس',
        'DRAFT_ASSET_ADDED' => 'افزودن دارایی به پیش‌نویس',
        'DRAFT_ASSET_REMOVED' => 'حذف دارایی از پیش‌نویس',
        'CASE_SUBMITTED' => 'ارسال برای بررسی',
        'PROFILE_HOLD_APPLIED' => 'اعمال توقف موقت پروفایل',
        'CASE_ASSIGNED' => 'تخصیص به کارشناس',
        'REVIEW_STARTED' => 'شروع بررسی',
        'MORE_EVIDENCE_REQUESTED' => 'درخواست مدرک / تأیید مشتری',
        'CONTACT_RECORDED' => 'ثبت تماس',
        'EVIDENCE_ADDED' => 'ثبت مدرک',
        'CASE_AMENDED' => 'اصلاحیه رسمی پرونده',
        'ASSET_ADDED' => 'افزودن دارایی با اصلاحیه',
        'REQUESTED_TRANSFER_PLAN_CHANGED' => 'تغییر برنامه انتقال درخواستی',
        'FINAL_PLAN_CONFIRMED' => 'تأیید برنامه نهایی بدون تغییر',
        'RISK_ESCALATED' => 'افزایش سطح ریسک',
        'MANUAL_RISK_ESCALATION' => 'ارجاع دستی به سطح پرریسک',
        'OTP_SENT' => 'ارسال کد تأیید',
        'OTP_DELIVERY_FAILED' => 'ارسال کد تأیید ناموفق',
        'OTP_VERIFIED' => 'تأیید کد یکبارمصرف',
        'OTP_FAILED' => 'کد تأیید نادرست',
        'OTP_EXPIRED' => 'انقضای کد تأیید',
        'OTP_REUSE_REJECTED' => 'رد استفاده مجدد از کد',
        'TRANSFER_APPROVED' => 'تأیید انتقال توسط کارشناس',
        'CLAIM_REJECTED' => 'رد درخواست',
        'CLAIM_WITHDRAWN' => 'انصراف از درخواست',
        'SUPERVISOR_APPROVED' => 'تأیید سرپرست',
        'SUPERVISOR_REJECTED' => 'بازگرداندن به بررسی توسط سرپرست',
        'INVOICE_OWNER_CHANGED' => 'تغییر مالک فاکتور',
        'PHONE_OWNER_CHANGED' => 'تغییر مالک شماره',
        'INVOICE_PHONE_CHANGED' => 'تغییر شماره رسمی فاکتور',
        'SERVICE_OWNER_ALIGNED' => 'هم‌راستاسازی مالک خدمت با فاکتور',
        'OWNERSHIP_LOCK_CREATED' => 'ایجاد قفل مالکیت',
        'OWNERSHIP_LOCK_RELEASED' => 'آزادسازی قفل مالکیت',
        'LEGACY_LOCK_REVIEW_REQUIRED' => 'نیاز به بررسی تاریخی قفل مالکیت',
        'TRANSFER_COMMITTED' => 'ثبت قطعی انتقال مالکیت',
        'PROFILE_RECALCULATED' => 'بازمحاسبه پروفایل',
        'DEFICIENCY_TRIGGERED' => 'ارجاع به فرایند رفع ناقصی',
        'PROFILE_HOLD_RELEASED' => 'آزادسازی توقف موقت پروفایل',
        'CASE_EXECUTION_FAILED' => 'نهایی‌سازی ناتمام ماند',
        'CASE_CLOSED' => 'بستن پرونده',
        'CASE_CANCELLED' => 'لغو پرونده',
    ];
    /** Exact backend messages → Persian operator text. Unknown messages are shown verbatim. */
    const ERRORS = [
        'Stale ownership case version.' => 'پرونده توسط کاربر یا فرایند دیگری تغییر کرده است. صفحه را تازه‌سازی کنید و دوباره بررسی کنید.',
        'Stale recovery version.' => 'وضعیت پرونده تغییر کرده است. صفحه را تازه‌سازی کنید.',
        'Only your own ownership claims are accessible.' => 'فقط درخواست‌های ثبت‌شده توسط خودتان قابل دسترسی است.',
        'Ownership case is not assigned to you.' => 'این پرونده به شما تخصیص داده نشده است.',
        'A reason/note is required.' => 'ثبت دلیل/توضیح الزامی است.',
        'Two distinct customer profiles are required.' => 'پروفایل مبدأ و مقصد باید متفاوت باشند.',
        'Claimant profile access denied.' => 'به پروفایل مقصد (مدعی) دسترسی ندارید.',
        'Claimant profile access changed.' => 'دسترسی شما به پروفایل مقصد تغییر کرده است.',
        'Unsupported asset type.' => 'نوع دارایی پشتیبانی نمی‌شود.',
        'Asset does not belong to source profile.' => 'این دارایی متعلق به پروفایل مبدأ نیست.',
        'Unsupported requested asset action.' => 'اقدام درخواستی برای دارایی معتبر نیست.',
        'Explicit invoice phone plan required.' => 'برای هر فاکتور باید یکی از روش‌های تعیین شماره را صریحاً انتخاب کنید.',
        'At least one asset is required.' => 'حداقل یک دارایی (فاکتور یا شماره) باید انتخاب شود.',
        'Asset already participates in an active ownership case.' => 'این دارایی در پرونده تطبیق مالکیت فعال دیگری است.',
        'Asset owner changed.' => 'مالک دارایی پس از انتخاب تغییر کرده است.',
        'Ambiguous legacy service owner; historical review required.' => 'مالک خدمات این فاکتور در داده‌های قدیمی مبهم است؛ بررسی تاریخی لازم است.',
        'Ambiguous legacy invoice phone; review required.' => 'شماره تاریخی این فاکتور مبهم است؛ «حفظ شماره فعلی» و «انتقال شماره فعلی» ممکن نیست.',
        'Invalid canonical invoice phone.' => 'شماره رسمی ثبت‌شده برای فاکتور معتبر نیست.',
        'Destination phone owner changed.' => 'شماره انتخاب‌شده دیگر متعلق به پروفایل مقصد نیست.',
        'Exactly one valid canonical phone is required.' => 'دقیقاً یک شماره معتبر برای فاکتور لازم است.',
        'Ambiguous phone identity.' => 'هویت شماره مبهم است (چند رکورد هم‌شماره).',
        'Proposed phone already belongs to another profile; claim it explicitly.' => 'شماره پیشنهادی متعلق به پروفایل دیگری است؛ باید جداگانه و صریح ادعا شود.',
        'Disputed phone owner changed.' => 'مالک شماره مورد اختلاف تغییر کرده است.',
        'Claim current phone requires an explicit PHONE asset.' => 'برای «انتقال مالکیت شماره فعلی» باید خود شماره را جداگانه به‌عنوان دارایی انتخاب کنید.',
        'Eligible ownership specialist required.' => 'کاربر انتخاب‌شده کارشناس فعال تطبیق مالکیت نیست.',
        'Media access denied.' => 'دسترسی به فایل مدرک مجاز نیست.',
        'Unsupported evidence file.' => 'نوع فایل مدرک مجاز نیست (فقط JPG، PNG، WEBP، PDF، MP3، WAV).',
        'Unsupported ownership evidence kind.' => 'نوع مدرک معتبر نیست.',
        'Amendment assets required.' => 'برای اصلاحیه حداقل یک دارایی لازم است.',
        'Invoice case asset required.' => 'برنامه نهایی فقط برای دارایی فاکتور قابل تعیین است.',
        'Unsupported final asset action.' => 'اقدام نهایی دارایی معتبر نیست.',
        'An approved transfer requires at least one explicit transfer asset.' => 'تأیید انتقال حداقل به یک دارایی با اقدام «انتقال» نیاز دارد.',
        'Documented supporting evidence is required for approval.' => 'برای تأیید، ثبت مدرک پشتیبان الزامی است.',
        'Claimant receipt evidence is required.' => 'برای این روش، مدرک «رسید پرداخت مدعی» لازم است.',
        'Audited current-owner consent contact is required.' => 'برای این روش، ثبت تماس با نتیجه «رضایت مالک فعلی» لازم است.',
        'Delivered and verified disputed phone OTP evidence missing.' => 'کد یکبارمصرف تحویل‌شده و تأییدشده برای شماره مورد اختلاف وجود ندارد.',
        'OTP is not invoice ownership evidence.' => 'تأیید کد یکبارمصرف به‌تنهایی مدرک مالکیت فاکتور نیست.',
        'High risk requires a different approving supervisor.' => 'تأیید پرونده پرریسک باید توسط سرپرستی غیر از تصمیم‌گیرنده انجام شود.',
        'Executed case cannot be cancelled.' => 'پرونده‌ای که انتقال آن ثبت شده قابل لغو نیست.',
        'Ownership evidence changed or is unavailable; return to review.' => 'فایل یکی از مدارک تغییر کرده یا در دسترس نیست؛ پرونده باید دوباره بررسی شود.',
        'Approval evidence ledger is missing.' => 'سابقه ثبت مدارک برای تأیید یافت نشد.',
        'Approved transfer required.' => 'این اقدام فقط برای انتقال تأییدشده مجاز است.',
        'Transfer already applied; use resume.' => 'انتقال قبلاً ثبت شده است؛ فقط «ادامه نهایی‌سازی» مجاز است.',
        'Independent supervisor approval required.' => 'تأیید مستقل سرپرست لازم است.',
        'No committed transfer to resume.' => 'انتقال ثبت‌شده‌ای برای ادامه نهایی‌سازی وجود ندارد.',
        'OTP resend throttled.' => 'ارسال مجدد کد فقط پس از ۶۰ ثانیه مجاز است.',
        'OTP is restricted to an explicitly disputed phone.' => 'کد یکبارمصرف فقط برای شماره‌ای که صریحاً مورد ادعاست مجاز است.',
        'OTP scope mismatch.' => 'کد با این پرونده/شماره/هدف مطابقت ندارد.',
        'Unsupported OTP purpose.' => 'هدف کد تأیید معتبر نیست.',
        'Supported verification method required.' => 'روش احراز معتبر انتخاب نشده است.',
        'Unsupported ownership decision.' => 'تصمیم انتخاب‌شده معتبر نیست.',
        'Ownership safety lock unavailable; retry.' => 'سیستم در حال انجام عملیات دیگری است؛ چند لحظه بعد دوباره تلاش کنید.',
        'Customer is read-only during ownership review.' => 'این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است.',
        'Asset ownership is locked; use Ownership Reconciliation.' => 'تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است.',
        'Stale asset ownership snapshot.' => 'مالکیت دارایی پس از ثبت درخواست تغییر کرده است.',
        'Stale invoice snapshot.' => 'اطلاعات فاکتور پس از ثبت درخواست تغییر کرده است.',
        'Stale invoice services snapshot.' => 'خدمات فاکتور پس از ثبت درخواست تغییر کرده است.',
        'Stale phone snapshot.' => 'اطلاعات شماره پس از ثبت درخواست تغییر کرده است.',
        'Ownership hold is missing or duplicated.' => 'وضعیت توقف موقت پروفایل‌ها ناسازگار است.',
        'Conflicting active ownership case.' => 'پرونده فعال متعارض برای این دارایی وجود دارد.',
        'Ownership commands require an autocommit connection.' => 'اتصال پایگاه داده برای عملیات تطبیق مالکیت مناسب نیست.',
        'Ownership command cannot nest inside an existing transaction.' => 'عملیات تطبیق مالکیت نمی‌تواند داخل تراکنش دیگری اجرا شود.',
        'Query too short.' => 'حداقل دو نویسه برای جست‌وجو وارد کنید.',
    ];

    public static function label(array $map, $key): string
    {
        $key = (string)$key;
        return $map[$key] ?? ($key === '' ? '—' : $key);
    }

    /** Translate a backend exception into an operator-facing payload; the raw message is kept for support. */
    public static function error(Throwable $e): array
    {
        $raw = $e->getMessage();
        $message = self::ERRORS[$raw] ?? null;
        $code = 'domain';
        if (strpos($raw, 'Stale ownership case version') === 0 || strpos($raw, 'Stale recovery version') === 0) $code = 'stale';
        if (strpos($raw, 'Ownership permission denied: ') === 0) {
            $code = 'permission';
            $message = 'دسترسی لازم برای این اقدام را ندارید.';
        } elseif (in_array($raw, ['Only your own ownership claims are accessible.', 'Ownership case is not assigned to you.', 'Claimant profile access denied.', 'Media access denied.'], true)) {
            $code = 'permission';
        } elseif ($raw === 'Asset ownership is locked; use Ownership Reconciliation.') {
            $code = 'ownership_locked';
        } elseif ($raw === 'Customer is read-only during ownership review.') {
            $code = 'ownership_hold';
        } elseif (in_array($raw, ['Asset already participates in an active ownership case.', 'Conflicting active ownership case.'], true)) {
            $code = 'active_ownership_case';
        } elseif (strpos($raw, 'Ownership action is not allowed in state ') === 0) {
            $code = 'state';
            $message = 'این اقدام در وضعیت «' . self::label(self::STATUSES, substr($raw, strlen('Ownership action is not allowed in state '))) . '» مجاز نیست. صفحه را تازه‌سازی کنید.';
        } elseif (strpos($raw, 'Ownership record not found: ') === 0) {
            $code = 'not_found';
            $message = 'رکورد موردنظر یافت نشد.';
        } elseif (strpos($raw, 'Profile recalculation failed: ') === 0) {
            $message = 'بازمحاسبه پروفایل پس از انتقال ناموفق بود.';
        }
        return ['code' => $code, 'message' => $message ?: 'خطای سیستم: ' . $raw, 'detail' => $raw];
    }

    public static function user_name($id): string
    {
        $id = (int)$id;
        if (!$id) return '—';
        $user = function_exists('get_userdata') ? get_userdata($id) : null;
        return $user && !empty($user->display_name) ? (string)$user->display_name : ('کاربر #' . $id);
    }

    /** Same viewer split as Workflow::get(): reviewers see snapshots/risk reasons, requesters their claim view. */
    public static function is_reviewer(object $case): bool
    {
        return current_user_can('view_ownership_cases') && (current_user_can('assign_ownership_cases') || (int)($case->assigned_to_user_id ?? 0) === get_current_user_id());
    }

    /** Visibility of ownership workspaces; every action is still re-authorized by the backend. */
    public static function workspaces(): array
    {
        $tabs = [];
        if (current_user_can('submit_ownership_claim')) $tabs['request'] = 'درخواست تطبیق مالکیت';
        if (current_user_can('view_own_ownership_claims') || current_user_can('view_ownership_cases')) $tabs['mine'] = 'پرونده‌های من';
        if (current_user_can('view_ownership_cases')) $tabs['queue'] = 'صف کارشناس تطبیق مالکیت';
        if (current_user_can('approve_high_risk_ownership_transfer')) $tabs['supervisor'] = 'صف سرپرست / High Risk';
        if (current_user_can('view_own_ownership_claims') || current_user_can('view_ownership_cases')) $tabs['history'] = 'بسته / لغوشده';
        return $tabs;
    }

    public static function can_enter(): bool
    {
        return (bool)self::workspaces();
    }

    /** Customer label already exposed to portal workers by the existing related search. */
    public static function customer_labels(array $ids): array
    {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $rows = Biawin_CRM_Ownership_Repository::rows('SELECT id,display_name,customer_code FROM ' . Biawin_CRM_DB::table('customers') . ' WHERE id IN (' . implode(',', $ids) . ')');
        $out = [];
        foreach ($rows as $row) $out[(int)$row->id] = ['id' => (int)$row->id, 'name' => (string)($row->display_name ?: 'بدون نام'), 'code' => (string)($row->customer_code ?? '')];
        return $out;
    }

    /** Pages through the backend list_cases() scope. Never widens it. */
    public static function scoped_cases(int $max = 1000): array
    {
        $rows = [];
        $after = 0;
        while (count($rows) < $max) {
            $page = Biawin_CRM_Ownership_Workflow::list_cases($after, 200);
            if (!$page) break;
            foreach ($page as $row) { $rows[] = $row; $after = (int)$row->id; }
            if (count($page) < 200) break;
        }
        return $rows;
    }

    /** Display context for rows list_cases() already returned to this user. */
    public static function list_rows(array $cases): array
    {
        global $wpdb;
        if (!$cases) return [];
        $ids = implode(',', array_map(static function ($c) { return (int)$c->id; }, $cases));
        $extra = [];
        foreach (Biawin_CRM_Ownership_Repository::rows('SELECT id,requested_by_user_id,decided_by_user_id,supervisor_user_id,supervisor_approval_status,decision,risk_reasons_json,created_at,updated_at,submitted_at,assigned_at,decided_at,closed_at,cancelled_at FROM ' . Biawin_CRM_DB::table('ownership_cases') . ' WHERE id IN (' . $ids . ')') as $row) $extra[(int)$row->id] = $row;
        $counts = [];
        foreach (Biawin_CRM_Ownership_Repository::rows('SELECT case_id,asset_type,COUNT(*) n FROM ' . Biawin_CRM_DB::table('ownership_case_assets') . ' WHERE case_id IN (' . $ids . ') GROUP BY case_id,asset_type') as $row) {
            $counts[(int)$row->case_id][$row->asset_type] = (int)$row->n;
        }
        $holds = [];
        foreach (Biawin_CRM_Ownership_Repository::rows('SELECT case_id,COUNT(*) n FROM ' . Biawin_CRM_DB::table('customer_holds') . " WHERE status='ACTIVE' AND case_id IN (" . $ids . ') GROUP BY case_id') as $row) $holds[(int)$row->case_id] = (int)$row->n;
        $amend = [];
        foreach (Biawin_CRM_Ownership_Repository::rows('SELECT case_id,COUNT(*) n FROM ' . Biawin_CRM_DB::table('ownership_case_amendments') . ' WHERE case_id IN (' . $ids . ') GROUP BY case_id') as $row) $amend[(int)$row->case_id] = (int)$row->n;
        $evidence = [];
        foreach (Biawin_CRM_Ownership_Repository::rows('SELECT object_id,uploaded_by FROM ' . Biawin_CRM_DB::table('attachments') . " WHERE object_type='ownership_case' AND object_id IN (" . $ids . ')') as $row) $evidence[(int)$row->object_id][] = (int)$row->uploaded_by;
        $otp = [];
        foreach (Biawin_CRM_Ownership_Repository::rows('SELECT case_id,sent_at,expires_at,verified_at,consumed_at,attempt_count,max_attempts,provider_meta_json FROM ' . Biawin_CRM_DB::table('ownership_otp_challenges') . ' WHERE case_id IN (' . $ids . ') ORDER BY id') as $row) $otp[(int)$row->case_id] = $row;
        $profile_ids = [];
        foreach ($cases as $case) { $profile_ids[] = $case->current_owner_customer_id; $profile_ids[] = $case->claimant_customer_id; }
        $labels = self::customer_labels($profile_ids);
        $out = [];
        foreach ($cases as $case) {
            $id = (int)$case->id;
            $x = $extra[$id] ?? (object)[];
            $reviewer = self::is_reviewer($case);
            $uploads = $evidence[$id] ?? [];
            if (!$reviewer) $uploads = array_values(array_filter($uploads, static function ($u) { return $u === get_current_user_id(); }));
            $reasons = $reviewer ? Biawin_CRM_Ownership_Repository::decode($x->risk_reasons_json ?? null) : [];
            $row = [
                'id' => $id, 'code' => (string)$case->case_code, 'status' => (string)$case->status, 'risk' => (string)$case->risk_level,
                'execution' => (string)$case->execution_state, 'version' => (int)$case->lock_version,
                'source' => $labels[(int)$case->current_owner_customer_id] ?? ['id' => (int)$case->current_owner_customer_id, 'name' => '#' . (int)$case->current_owner_customer_id, 'code' => ''],
                'claimant' => $labels[(int)$case->claimant_customer_id] ?? ['id' => (int)$case->claimant_customer_id, 'name' => '#' . (int)$case->claimant_customer_id, 'code' => ''],
                'assignee_id' => (int)$case->assigned_to_user_id, 'assignee' => self::user_name($case->assigned_to_user_id),
                'requested_by' => (int)($x->requested_by_user_id ?? 0), 'decided_by' => (int)($x->decided_by_user_id ?? 0),
                'supervisor' => (string)($x->supervisor_approval_status ?? 'NOT_REQUIRED'), 'decision' => (string)($x->decision ?? ''),
                'invoices' => (int)($counts[$id]['INVOICE'] ?? 0), 'phones' => (int)($counts[$id]['PHONE'] ?? 0),
                'holds' => (int)($holds[$id] ?? 0), 'evidence' => count($uploads),
                'created_at' => (string)($x->created_at ?? ''), 'updated_at' => (string)($x->updated_at ?? ''), 'submitted_at' => (string)($x->submitted_at ?? ''),
                'reviewer' => $reviewer, 'reasons' => $reasons,
                'amendments' => $reviewer ? (int)($amend[$id] ?? 0) : null,
                'conflicting' => in_array('CONFLICTING_EVIDENCE', $reasons, true),
                'otp' => $reviewer && isset($otp[$id]) ? self::otp_state($otp[$id]) : '',
            ];
            $row['assets'] = $row['invoices'] + $row['phones'];
            $row['next'] = self::next_action($row);
            $out[] = $row;
        }
        return $out;
    }

    public static function otp_state(object $otp): string
    {
        if ($otp->verified_at) return 'تأییدشده';
        $accepted = (Biawin_CRM_Ownership_Repository::decode($otp->provider_meta_json)['accepted'] ?? false) === true;
        if (!$accepted) return 'ارسال ناموفق';
        if ($otp->consumed_at) return (int)$otp->attempt_count >= (int)$otp->max_attempts ? 'تلاش‌ها تمام شد' : 'مصرف/باطل‌شده';
        if (strtotime($otp->expires_at . ' UTC') <= time()) return 'منقضی‌شده';
        return 'ارسال‌شده — در انتظار ورود کد';
    }

    /** Human description of who is expected to act next; derived from status only. */
    public static function next_action(array $row): string
    {
        switch ($row['status']) {
            case 'DRAFT': return 'تکمیل و ارسال برای بررسی توسط درخواست‌دهنده';
            case 'SUBMITTED': return 'تخصیص به کارشناس توسط سرپرست';
            case 'ASSIGNED': return 'شروع بررسی توسط کارشناس';
            case 'UNDER_REVIEW': return 'بررسی و تصمیم کارشناس';
            case 'AWAITING_EVIDENCE': return 'ارائه مدرک تکمیلی';
            case 'AWAITING_CUSTOMER_CONFIRMATION': return 'دریافت تأیید مشتری';
            case 'AWAITING_SUPERVISOR_APPROVAL': return 'تأیید یا بازگرداندن توسط سرپرست مستقل';
            case 'DECIDED':
                if ($row['execution'] === 'FINALIZATION_FAILED') return 'ادامه نهایی‌سازی توسط کاربر مجاز';
                if ($row['execution'] === 'COMMITTED') return 'تکمیل نهایی‌سازی';
                return 'اجرای انتقال تأییدشده';
            case 'CLOSED': return 'بدون اقدام — بسته‌شده';
            case 'CANCELLED': return 'بدون اقدام — لغوشده';
        }
        return '—';
    }

    /** Read display context for one case. Workflow::get() performs the authorization. */
    public static function case_view(int $id): array
    {
        global $wpdb;
        $data = Biawin_CRM_Ownership_Workflow::get($id);
        $case = $data['case'];
        $reviewer = self::is_reviewer($case);
        $assets = [];
        $current = [];
        foreach ($data['current_assets'] ?? [] as $row) if ($row) $current[(int)$row->id . ':' . (isset($row->invoice_code) ? 'INVOICE' : 'PHONE')] = $row;
        foreach ($data['assets'] as $asset) {
            $type = (string)$asset->asset_type;
            $snapshot = $reviewer ? Biawin_CRM_Ownership_Repository::decode($asset->snapshot_json) : [];
            $live = $current[(int)$asset->asset_id . ':' . $type] ?? null;
            if (!$live) {
                // Requesters: identifiers already exposed by the existing related search.
                $live = $type === 'INVOICE'
                    ? $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,invoice_code,amount_paid,canonical_phone_normalized FROM ' . Biawin_CRM_DB::table('invoices') . ' WHERE id=%d', $asset->asset_id))
                    : $wpdb->get_row($wpdb->prepare('SELECT id,customer_id,normalized_phone,phone_status FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE id=%d', $asset->asset_id));
            }
            $locks = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,customer_id,lock_reason,locked_at,released_at,release_reason,released_by_case_id FROM ' . Biawin_CRM_DB::table('ownership_locks') . ' WHERE entity_type=%s AND entity_id=%d ORDER BY id', $type, $asset->asset_id));
            $assets[] = ['row' => $asset, 'live' => $live, 'snapshot' => $snapshot, 'locks' => $locks];
        }
        $holds = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,customer_id,hold_type,status,created_at,created_by,released_at,released_by,release_reason FROM ' . Biawin_CRM_DB::table('customer_holds') . ' WHERE case_id=%d ORDER BY id', $id));
        $view = [
            'data' => $data, 'case' => $case, 'reviewer' => $reviewer, 'assets' => $assets, 'holds' => $holds,
            'labels' => self::customer_labels([$case->current_owner_customer_id, $case->claimant_customer_id]),
            'evidence' => [], 'otp' => [], 'amendments' => [], 'other_holds' => [], 'profile_phones' => [], 'specialists' => [],
        ];
        foreach ($data['evidence'] as $evidence) {
            $media = (int)$evidence->wp_attachment_id;
            $view['evidence'][] = ['row' => $evidence,
                'url' => function_exists('wp_get_attachment_url') ? (string)wp_get_attachment_url($media) : '',
                'mime' => function_exists('get_post_mime_type') ? (string)get_post_mime_type($media) : '',
                'filename' => function_exists('get_attached_file') ? basename((string)get_attached_file($media)) : '',
                'uploader' => self::user_name($evidence->uploaded_by)];
        }
        if ($reviewer) {
            // Non-secret OTP metadata only: never otp_hash or provider payloads beyond acceptance.
            $view['otp'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,phone_asset_id,phone_normalized,purpose,expires_at,attempt_count,max_attempts,sent_at,verified_at,consumed_at,provider_meta_json FROM ' . Biawin_CRM_DB::table('ownership_otp_challenges') . ' WHERE case_id=%d ORDER BY id', $id));
            $view['amendments'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,amendment_type,reason,created_by_user_id,created_at FROM ' . Biawin_CRM_DB::table('ownership_case_amendments') . ' WHERE case_id=%d ORDER BY id', $id));
            $view['other_holds'] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,customer_id,case_id,created_at FROM ' . Biawin_CRM_DB::table('customer_holds') . " WHERE status='ACTIVE' AND case_id<>%d AND customer_id IN (%d,%d) ORDER BY id", $id, $case->current_owner_customer_id, $case->claimant_customer_id));
            foreach ([(int)$case->current_owner_customer_id, (int)$case->claimant_customer_id] as $customer) {
                $view['profile_phones'][$customer] = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT id,normalized_phone,phone_status FROM ' . Biawin_CRM_DB::table('phones') . ' WHERE customer_id=%d ORDER BY id', $customer));
            }
        }
        if (current_user_can('assign_ownership_cases')) $view['specialists'] = self::specialists();
        return $view;
    }

    /** Candidate list only; Workflow::assign() re-validates eligibility. */
    public static function specialists(): array
    {
        if (!function_exists('get_users')) return [];
        $out = [];
        foreach ((array)get_users(['role__in' => ['bcrm_ownership_specialist', 'bcrm_ownership_supervisor', 'bcrm_portal_admin', 'administrator'], 'fields' => ['ID', 'display_name']]) as $user) {
            if (function_exists('user_can') && !user_can((int)$user->ID, 'review_ownership_cases')) continue;
            if (function_exists('get_user_meta') && get_user_meta((int)$user->ID, 'bcrm_disabled', true)) continue;
            $out[(int)$user->ID] = (string)$user->display_name;
        }
        return $out;
    }

    /**
     * Visibility hints for the case action panel. These mirror capability/state
     * gates only so unavailable buttons can be explained; the backend command
     * always re-checks capability, scope, state, SoD and version.
     */
    public static function actions(object $case): array
    {
        $me = get_current_user_id();
        $status = (string)$case->status;
        $own = (int)$case->requested_by_user_id === $me;
        $scoped = current_user_can('assign_ownership_cases') || (int)$case->assigned_to_user_id === $me;
        $review = in_array($status, Biawin_CRM_Ownership_Workflow::REVIEW_STATES, true);
        $a = [];
        $a['update_draft'] = $status === 'DRAFT' && $own && current_user_can('submit_ownership_claim');
        $a['submit'] = $a['update_draft'];
        $a['cancel_draft'] = $a['update_draft'];
        $a['add_evidence'] = in_array($status, ['DRAFT', 'SUBMITTED', 'ASSIGNED', 'UNDER_REVIEW', 'AWAITING_EVIDENCE', 'AWAITING_CUSTOMER_CONFIRMATION'], true)
            && (($own && current_user_can('submit_ownership_claim')) || (!$own && $scoped && current_user_can('manage_ownership_evidence')));
        $a['conflicting_evidence'] = $a['add_evidence'] && $scoped && current_user_can('review_ownership_cases') && $status !== 'DRAFT';
        $a['assign'] = current_user_can('assign_ownership_cases') && in_array($status, ['SUBMITTED', 'ASSIGNED', 'UNDER_REVIEW', 'AWAITING_EVIDENCE', 'AWAITING_CUSTOMER_CONFIRMATION'], true);
        $a['start_review'] = $scoped && current_user_can('review_ownership_cases') && in_array($status, ['ASSIGNED', 'AWAITING_EVIDENCE', 'AWAITING_CUSTOMER_CONFIRMATION'], true);
        $a['request_evidence'] = $scoped && current_user_can('request_ownership_evidence') && $review;
        $a['record_contact'] = $scoped && current_user_can('record_ownership_contact') && $review;
        $a['amend'] = $scoped && current_user_can('amend_ownership_case') && $review;
        $a['final_plan'] = $scoped && current_user_can('review_ownership_cases') && $review;
        $a['escalate'] = $a['final_plan'];
        $a['otp_issue'] = $scoped && current_user_can('issue_ownership_otp') && $review;
        $a['otp_verify'] = $scoped && current_user_can('verify_ownership_otp') && $review;
        $a['approve'] = $scoped && current_user_can('approve_normal_ownership_transfer') && $status === 'UNDER_REVIEW';
        $a['reject'] = $scoped && current_user_can('reject_ownership_claim') && $status === 'UNDER_REVIEW';
        $a['withdraw'] = $scoped && current_user_can('record_ownership_withdrawal') && $status === 'UNDER_REVIEW';
        $a['supervisor'] = current_user_can('approve_high_risk_ownership_transfer') && $scoped && $status === 'AWAITING_SUPERVISOR_APPROVAL';
        $a['supervisor_self'] = $a['supervisor'] && (int)$case->decided_by_user_id === $me;
        if ($a['supervisor_self']) $a['supervisor'] = false;
        $a['cancel_case'] = current_user_can('assign_ownership_cases') && $status !== 'DRAFT' && ($case->execution_state ?? 'NOT_STARTED') === 'NOT_STARTED'
            && in_array($status, ['SUBMITTED', 'ASSIGNED', 'UNDER_REVIEW', 'AWAITING_EVIDENCE', 'AWAITING_CUSTOMER_CONFIRMATION', 'AWAITING_SUPERVISOR_APPROVAL'], true);
        $executable = $scoped && current_user_can('approve_normal_ownership_transfer') && $status === 'DECIDED' && ($case->decision ?? '') === 'TRANSFER_APPROVED';
        $a['execute'] = $executable && ($case->execution_state ?? '') === 'NOT_STARTED';
        $a['resume'] = $executable && in_array($case->execution_state ?? '', ['COMMITTED', 'FINALIZATION_FAILED'], true);
        return $a;
    }
}
