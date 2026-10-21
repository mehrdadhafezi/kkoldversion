<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سامانه ثبت عودت وجه مبتنی بر اکسل (v0.8.62.0 — v0.8.62.11).
 *
 * دو تگ مجزا روی پروفایل مشتری:
 *  - REFUND_REPORTED  «عودت اعلامی»  → از نتیجه تماس کارشناس («عودت شده است»)
 *  - REFUND_CONFIRMED «عودت قطعی»    → از آپلود اکسل (وضعیت عودت = 1)
 *
 * v0.8.62.11 — تطبیق ردیف اکسل:
 *  کد فاکتور یونیک است و اگر در ردیف باشد، تطبیق فقط از روی آن انجام می‌شود؛
 *  در نبود کد فاکتور، ردیف از روی Customer ID تطبیق داده می‌شود (یک Customer ID
 *  می‌تواند چند کد فاکتور داشته باشد، پس در فایل تکراری است اما کد فاکتور هرگز).
 *  اگر هر دو ستون موجود باشند مشکلی نیست — کد فاکتور ملاک است.
 *
 * v0.8.62.11 — دو نوع رکورد اکسل در refund_registers:
 *  - source_type='excel' «عودت شده»  → مبلغ بازگشت‌یافته (مانند قبل) + تگ قطعی
 *  - source_type='used'  «استفاده شده» → مبلغ مصرف‌شده (بدون تگ؛ فقط مانده را کم می‌کند)
 *
 * مانده (v0.8.62.11):
 *  مانده = مجموع فاکتورها − آخرین عودت قطعی اکسل − آخرین «استفاده شده»
 *  هر دو با منطق Last-Record (جایگزینی) — هرگز SUM.
 *
 * هر ردیف اکسل یک رکورد تاریخچه در refund_registers می‌سازد؛ آپلود مجدد همان
 * کد فاکتور (یا همان Customer ID بدون کد فاکتور) مبلغ را جایگزین می‌کند و
 * رکورد قبلی در تاریخچه می‌ماند.
 */
class Biawin_CRM_Refund_Register
{
    public const TAG_REPORTED = 'REFUND_REPORTED';
    public const TAG_CONFIRMED = 'REFUND_CONFIRMED';
    public const BATCH_TTL_HOURS = 48;

    /** v0.8.62.11: نوع رکورد اکسل — «عودت شده» (مانند قبل) و «استفاده شده» (جدید). */
    public const SOURCE_EXCEL_REFUND = 'excel';
    public const SOURCE_EXCEL_USED = 'used';

    /** v0.8.62.1: sanitize_key() lowercases — tag filters arriving via HTTP must be
     *  uppercased BEFORE the allowlist check or they silently fall back to 'any'. */
    public static function sanitize_tag_filter($raw): string
    {
        $v = strtoupper(trim((string)$raw));
        if (!in_array($v, ['any', 'ANY', self::TAG_REPORTED, self::TAG_CONFIRMED, 'BOTH'], true)) return 'any';
        return $v === 'ANY' ? 'any' : $v;
    }

    /** v0.8.62.8: tag-filter modes for SQL JOINs (portal refunded list).
     *  Names are stable ASCII identifiers — never localized values over HTTP. */
    public const TAG_MODE_CONFIRMED = 'T_CONFIRMED';
    public const TAG_MODE_REPORTED = 'T_REPORTED';
    public const TAG_MODE_BOTH = 'T_BOTH';
    public const TAG_MODE_NONE = 'T_NONE';

    /** v0.8.62.8: allowlist an HTTP tag filter into a TAG_MODE_* constant (''=any).
     *  Accepts both stable mode ids and friendly aliases (confirmed/reported/both/none). */
    public static function sanitize_tag_mode($raw): string
    {
        $v = strtoupper(trim((string)$raw));
        $v = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $v);
        if ($v === '') return '';
        if (in_array($v, [self::TAG_MODE_CONFIRMED, 'CONFIRMED', 'REFUND_CONFIRMED'], true)) return self::TAG_MODE_CONFIRMED;
        if (in_array($v, [self::TAG_MODE_REPORTED, 'REPORTED', 'REFUND_REPORTED'], true)) return self::TAG_MODE_REPORTED;
        if (in_array($v, [self::TAG_MODE_BOTH, 'BOTH'], true)) return self::TAG_MODE_BOTH;
        if (in_array($v, [self::TAG_MODE_NONE, 'NONE'], true)) return self::TAG_MODE_NONE;
        return '';
    }

    /** v0.8.62.8: legacy alias-based predicate (kept only for backward compat).
     *  The portal refunded list no longer uses it — tag filtering there is done with
     *  local EXISTS predicates on customer alias c (no `t` JOIN exists anymore). */
    public static function tag_mode_predicate(string $mode, string $alias = 't'): string
    {
        switch ($mode) {
            case self::TAG_MODE_CONFIRMED: return "$alias.tag = '" . self::TAG_CONFIRMED . "'";
            case self::TAG_MODE_REPORTED: return "$alias.tag = '" . self::TAG_REPORTED . "'";
            case self::TAG_MODE_BOTH: return "$alias.tag IN ('" . self::TAG_CONFIRMED . "','" . self::TAG_REPORTED . "')";
            case self::TAG_MODE_NONE: return "$alias.tag IS NULL";
        }
        return '';
    }

    /** v0.8.62.11: headers the excel may carry (normalized).
     *  کد فاکتور اولویت تطبیق دارد و Customer ID جایگزین آن است؛ هیچ‌کدام به‌تنهایی اجباری نیستند
     *  (حداقل یکی باید در ردیف باشد) و اگر هر دو باشند کد فاکتور ملاک است. */
    public static function required_headers(): array
    {
        return ['invoice code', 'customer id', 'وضعیت عودت', 'مبلغ عودت'];
    }

    public static function header_aliases(): array
    {
        return [
            'invoice code' => ['invoice code', 'invoice_code', 'invoicecode', 'invoice', 'invoice no', 'invoice number', 'کد فاکتور', 'کد فاکتور crm', 'شماره فاکتور', 'شماره فاکتور crm', 'فاکتور'],
            'customer id' => ['customer id', 'customer_id', 'customerid', 'شناسه مشتری', 'کد مشتری', 'کد مشتری crm', 'شناسه مشتری crm'],
            'وضعیت عودت' => ['وضعیت عودت', 'وضعیت', 'status', 'refund status'],
            'مبلغ عودت' => ['مبلغ عودت', 'مبلغ عودتی', 'مبلغ', 'amount', 'refund amount'],
        ];
    }

    /** Normalize a header cell to one of the canonical keys; '' when unknown. */
    public static function canonical_header(string $header): string
    {
        $norm = self::norm($header);
        foreach (self::header_aliases() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (self::norm($alias) === $norm) return $canonical;
            }
        }
        return '';
    }

    private static function norm(string $s): string
    {
        $s = strtolower(trim((string)preg_replace('/[\x{200c}\s]+/u', ' ', $s)));
        $s = str_replace(['ی', 'ک'], ['ی', 'ک'], $s); // normalize arabic yeh/kaf variants
        $s = str_replace(['ي', 'ك'], ['ی', 'ک'], $s);
        $s = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $s);
        return trim($s);
    }

    /** v0.8.62.11: normalize an invoice code cell (zero-width/NBSP strip، نویسه‌های عربی→فارسی،
     *  ارقام فارسی/عربی→لاتین، حروف کوچک). کد فاکتور یونیک است — تطبیق دقیق از روی نسخه نرمال‌شده. */
    public static function norm_code(string $s): string
    {
        $s = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xC2\xA0"], '', $s);
        $s = str_replace(['ي', 'ك'], ['ی', 'ک'], $s);
        $s = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $s);
        return strtolower(trim($s));
    }

    /** v0.8.62.11: only the ASCII digits of a (normalized) code — for zero-padding fallback. */
    private static function digits_only(string $s): string
    {
        return (string)preg_replace('/[^0-9]/', '', $s);
    }

    /**
     * Parse the uploaded excel/csv through the SAME engine the main import uses.
     * @return array{headers:array,rows:array,errors:array}
     */
    public static function parse_upload(string $path, string $file_name): array
    {
        if (!class_exists('Biawin_CRM_Import_Service')) {
            require_once BIAWIN_CRM_DIR . 'includes/class-biawin-crm-import-service.php';
        }
        [$headers, $rows, $errors] = Biawin_CRM_Import_Service::parse_file($path, $file_name);
        return ['headers' => (array)$headers, 'rows' => (array)$rows, 'errors' => (array)$errors];
    }

    /**
     * Validate + persist one batch of rows. Never silently drops a row:
     * every row ends up in ok / not_found / invalid with its excel line number.
     *
     * v0.8.62.11: $source_type = SOURCE_EXCEL_REFUND («عودت شده» + تگ قطعی) یا
     * SOURCE_EXCEL_USED («استفاده شده» — فقط مبلغ، بدون تگ). تطبیق ردیف: کد فاکتور
     * اولویت دارد؛ در نبود آن Customer ID. آپلود مجدد همان کلید = جایگزینی مبلغ.
     *
     * @param array $rows  header => value maps from parse_upload()
     * @param int   $actor_user_id
     * @param string $source_type  self::SOURCE_EXCEL_REFUND | self::SOURCE_EXCEL_USED
     * @return array{ok:bool,message:string,batch_code:string,ok_count:int,replaced_count:int,not_found:array,invalid:array}
     */
    public static function import_batch(array $rows, int $actor_user_id = 0, string $source_type = self::SOURCE_EXCEL_REFUND): array
    {
        global $wpdb;
        $actor_user_id = $actor_user_id ?: get_current_user_id();
        $source_type = $source_type === self::SOURCE_EXCEL_USED ? self::SOURCE_EXCEL_USED : self::SOURCE_EXCEL_REFUND;
        $batch_code = 'RR-' . substr((string)wp_generate_password(10, false, false), 0, 10) . '-' . gmdate('ymdHis');
        $customers = Biawin_CRM_DB::table('customers');
        $registers = Biawin_CRM_DB::table('refund_registers');
        $now = Biawin_CRM_DB::now();

        $ok_count = 0;
        $replaced_count = 0;
        $not_found = [];
        $invalid = [];
        $line = 1; // excel line 1 = header

        foreach ($rows as $row) {
            $line++;
            $code_raw = trim((string)($row['invoice code'] ?? ''));
            $cid_raw = trim((string)($row['customer id'] ?? ''));
            $status_raw = trim((string)($row['وضعیت عودت'] ?? ''));
            $amount_raw = trim((string)($row['مبلغ عودت'] ?? ''));

            // v0.8.62.11: هیچ‌کدام از دو کلید اجباری نیست — اما حداقل یکی باید باشد.
            if ($code_raw === '' && $cid_raw === '') {
                $invalid[] = ['row' => $line, 'reason' => 'کد فاکتور یا شناسه مشتری (حداقل یکی) باید در ردیف باشد'];
                continue;
            }

            // وضعیت: در فایل «استفاده شده» اگر ستون وضعیت خالی باشد، ردیف معتبر فرض می‌شود (=1).
            $status = self::parse_status($status_raw);
            if ($status === null) {
                if ($source_type === self::SOURCE_EXCEL_USED && $status_raw === '') {
                    $status = 1;
                } else {
                    $invalid[] = ['row' => $line, 'key' => ($code_raw !== '' ? $code_raw : $cid_raw), 'reason' => 'وضعیت عودت فقط 0 یا 1 است'];
                    continue;
                }
            }
            if ($status === 0) {
                $invalid[] = ['row' => $line, 'key' => ($code_raw !== '' ? $code_raw : $cid_raw), 'reason' => 'وضعیت 0 (بدون عودت) ثبت نمی‌شود'];
                continue;
            }

            if ($amount_raw === '') {
                $invalid[] = ['row' => $line, 'key' => ($code_raw !== '' ? $code_raw : $cid_raw), 'reason' => 'مبلغ عودت خالی است'];
                continue;
            }
            $amount = Biawin_CRM_Normalizer::money($amount_raw);
            if ($amount <= 0) {
                $invalid[] = ['row' => $line, 'key' => ($code_raw !== '' ? $code_raw : $cid_raw), 'reason' => 'مبلغ عودت نامعتبر است (باید عدد بزرگ‌تر از صفر باشد)'];
                continue;
            }

            // تطبیق ردیف: کد فاکتور (یونیک) اولویت دارد؛ در نبود آن Customer ID.
            // اگر هر دو ستون پر باشند کد فاکتور ملاک است و Customer ID صرفاً بازبینی می‌شود.
            $customer_id = 0;
            $invoice_code = '';
            if ($code_raw !== '') {
                [$customer_id, $invoice_code] = self::resolve_by_invoice_code($code_raw);
                if (!$customer_id) { $not_found[] = ['row' => $line, 'key' => $code_raw, 'kind' => 'invoice_code']; continue; }
            } else {
                $customer_id = self::resolve_customer($cid_raw);
                if (!$customer_id) { $not_found[] = ['row' => $line, 'key' => $cid_raw, 'kind' => 'customer_id']; continue; }
            }

            // جایگزینی: آخرین رکورد همان کلید (کد فاکتور، یا مشتریِ بدون کد فاکتور) از همین نوع.
            if ($invoice_code !== '') {
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $registers WHERE invoice_code=%s AND source_type=%s ORDER BY id DESC LIMIT 1", $invoice_code, $source_type));
            } else {
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $registers WHERE customer_id=%d AND invoice_code='' AND source_type=%s ORDER BY id DESC LIMIT 1", $customer_id, $source_type));
            }
            $is_replacement = (bool)$existing;

            $inserted = $wpdb->insert($registers, [
                'customer_id' => $customer_id,
                'invoice_code' => $invoice_code,
                'refund_status' => 1,
                'refund_amount' => $amount,
                'source_type' => $source_type,
                'source_row' => $line,
                'batch_code' => $batch_code,
                'created_by' => $actor_user_id ?: null,
                'created_at' => $now,
            ]);
            if ($inserted === false) {
                $invalid[] = ['row' => $line, 'key' => ($invoice_code !== '' ? $invoice_code : $cid_raw), 'reason' => 'ثبت در دیتابیس انجام نشد: ' . ($wpdb->last_error ?: 'خطای ناشناخته')];
                continue;
            }
            if ($is_replacement) $replaced_count++;
            $ok_count++;

            // فقط فایل «عودت شده» تگ قطعی می‌دهد؛ «استفاده شده» هیچ تگی نمی‌گیرد.
            if ($source_type === self::SOURCE_EXCEL_REFUND) {
                self::apply_confirmed_tag($customer_id, $actor_user_id);
            }
            Biawin_CRM_DB::log('refund_register', $customer_id, 'refund_register_excel_row', null, [
                'batch_code' => $batch_code, 'row' => $line, 'customer_id' => $customer_id,
                'amount' => $amount, 'invoice_code' => $invoice_code, 'source_type' => $source_type,
            ]);
        }

        $total = $ok_count + count($not_found) + count($invalid);
        $label = $source_type === self::SOURCE_EXCEL_REFUND ? 'عودت' : 'استفاده';
        $message = sprintf('%s ردیف %s ثبت شد؛ %s ردیف جایگزین رکورد قبلی شد.', number_format_i18n($ok_count), $label, number_format_i18n($replaced_count));
        if ($not_found) $message .= ' ' . sprintf('%s ردیف مشتری/فاکتور پیدا نشد.', number_format_i18n(count($not_found)));
        if ($invalid) $message .= ' ' . sprintf('%s ردیف نامعتبر رد شد.', number_format_i18n(count($invalid)));

        Biawin_CRM_DB::log('refund_register', null, 'refund_register_batch', null, [
            'batch_code' => $batch_code, 'total_rows' => $total, 'ok' => $ok_count,
            'replaced' => $replaced_count, 'not_found' => count($not_found), 'invalid' => count($invalid),
            'source_type' => $source_type,
        ]);

        return [
            'ok' => $ok_count > 0 || ($not_found || $invalid) ? true : false,
            'message' => $message,
            'batch_code' => $batch_code,
            'source_type' => $source_type,
            'ok_count' => $ok_count,
            'replaced_count' => $replaced_count,
            'not_found' => $not_found,
            'invalid' => $invalid,
        ];
    }

    /** Resolve by numeric id, customer_code or source_customer_id (the excel CustomerID). */
    public static function resolve_customer(string $raw): int
    {
        global $wpdb;
        $raw = trim($raw);
        if ($raw === '') return 0;
        $customers = Biawin_CRM_DB::table('customers');
        if (ctype_digit($raw)) {
            $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE id=%d", (int)$raw));
            if ($id) return $id;
            $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE customer_code=%s", $raw));
            if ($id) return $id;
        }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE source_customer_id=%s", $raw));
    }

    /**
     * v0.8.62.11: resolve a row by its UNIQUE invoice code.
     * 1) تطبیق دقیق روی نسخه نرمال‌شده؛ 2) fallback صفرهای ابتدایی/جست‌وجوی جزئی.
     * @return array{0:int,1:string} [customer_id, canonical invoice_code] — [0,''] اگر پیدا نشد.
     */
    public static function resolve_by_invoice_code(string $raw): array
    {
        global $wpdb;
        $invoices = Biawin_CRM_DB::table('invoices');
        $code = self::norm_code($raw);
        if ($code === '') return [0, ''];
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, customer_id, invoice_code FROM $invoices WHERE invoice_code=%s LIMIT 1", $code));
        if (!$row) {
            $digits = self::digits_only($code);
            if ($digits !== '') {
                if (ctype_digit($digits) && ltrim($code, '0') !== ltrim($digits, '0')) {
                    // کد عددی بود — صفرهای ابتدایی فایل با پایه یکی می‌شود.
                    $row = $wpdb->get_row($wpdb->prepare("SELECT id, customer_id, invoice_code FROM $invoices WHERE TRIM(LEADING '0' FROM invoice_code)=%s LIMIT 1", ltrim($digits, '0')));
                }
                if (!$row) {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT id, customer_id, invoice_code FROM $invoices WHERE invoice_code LIKE %s LIMIT 1", '%' . $wpdb->esc_like($code) . '%'));
                }
            }
        }
        if (!$row) return [0, ''];
        return [(int)$row->customer_id, (string)$row->invoice_code];
    }

    /** Excel status column accepts 0/1 (also Persian digits / yes-no).
     *  v0.8.62.11: «استفاده شده/used» هم مثل بله پذیرفته می‌شود. */
    public static function parse_status(string $raw): ?int
    {
        $v = self::norm($raw);
        if ($v === '') return null;
        if (in_array($v, ['0', '1'], true)) return (int)$v;
        if (in_array($v, ['عودت شده', 'عودت شده است', 'استفاده شده', 'بله', 'yes', 'y', 'true', 'used'], true)) return 1;
        if (in_array($v, ['خیر', 'no', 'n', 'false', 'عودت نشده', 'استفاده نشده'], true)) return 0;
        return null;
    }

    /** Grant the confirmed tag (idempotent, keeps who/when). */
    public static function apply_confirmed_tag(int $customer_id, int $actor_user_id = 0): void
    {
        global $wpdb;
        $tags = Biawin_CRM_DB::table('customer_tags');
        $now = Biawin_CRM_DB::now();
        $actor = $actor_user_id ?: get_current_user_id();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $tags (customer_id,tag,case_id,created_by,created_at) VALUES (%d,%s,0,%d,%s) ON DUPLICATE KEY UPDATE created_by=VALUES(created_by)",
            $customer_id, self::TAG_CONFIRMED, $actor, $now
        ));
    }

    /** Grant the reported tag when an expert marks «عودت شده است» on a call.
     *  v0.8.62.1: also writes a source_type='call' history row (no amount) so the
     *  declaration shows up in the profile «عودت‌ها» tab with agent + timestamp. */
    public static function apply_reported_tag(int $customer_id, int $actor_user_id = 0, int $interaction_id = 0): void
    {
        global $wpdb;
        $tags = Biawin_CRM_DB::table('customer_tags');
        $now = Biawin_CRM_DB::now();
        $actor = $actor_user_id ?: get_current_user_id();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $tags (customer_id,tag,case_id,created_by,created_at) VALUES (%d,%s,0,%d,%s) ON DUPLICATE KEY UPDATE created_by=VALUES(created_by)",
            $customer_id, self::TAG_REPORTED, $actor, $now
        ));
        // Idempotent per interaction: a saved call must not duplicate history rows on re-save.
        if ($interaction_id > 0) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . Biawin_CRM_DB::table('refund_registers') . " WHERE customer_id=%d AND source_type='call' AND batch_code=%s LIMIT 1",
                $customer_id, 'call-' . $interaction_id
            ));
            if ($existing) return;
        }
        $wpdb->insert(Biawin_CRM_DB::table('refund_registers'), [
            'customer_id' => $customer_id,
            'invoice_code' => '',
            'refund_status' => 1,
            'refund_amount' => 0,
            'source_type' => 'call',
            'source_row' => null,
            'batch_code' => $interaction_id > 0 ? 'call-' . $interaction_id : null,
            'created_by' => $actor ?: null,
            'created_at' => $now,
        ]);
        Biawin_CRM_DB::log('refund_register', $customer_id, 'refund_reported_by_agent', null, [
            'customer_id' => $customer_id, 'interaction_id' => $interaction_id,
        ]);
    }

    /** v0.8.62.13: ابزار پاک‌سازی — حذف رکوردهای اکسل (عودت/استفاده‌شده) با فیلتر نوع و کلید.
     *  فقط رکوردهای منبع اکسل حذف می‌شوند (اعلامی کارشناس source_type='call' دست‌نخورده).
     *  تگ قطعی مشتری‌هایی که دیگر هیچ رکورد عودت اکسل ندارند حذف می‌شود (تگ اعلامی حفظ می‌شود).
     *  همه‌چیز در audit log ثبت می‌شود. @return array{deleted:int,tags_removed:int} */
    public static function purge_excel_rows(string $source_type = 'excel', bool $only_no_invoice = false, string $batch_code = ''): array
    {
        global $wpdb;
        $registers = Biawin_CRM_DB::table('refund_registers');
        $source_type = $source_type === self::SOURCE_EXCEL_USED ? self::SOURCE_EXCEL_USED : self::SOURCE_EXCEL_REFUND;
        $where = ["source_type=%s"];
        $args = [$source_type];
        if ($only_no_invoice) { $where[] = "invoice_code=''"; }
        if ($batch_code !== '') { $where[] = "batch_code=%s"; $args[] = $batch_code; }
        $targets = (array)$wpdb->get_results($wpdb->prepare("SELECT id, customer_id, batch_code FROM $registers WHERE " . implode(' AND ', $where), ...$args));
        $deleted = 0;
        $affected_customers = [];
        foreach ($targets as $t) {
            $ok = $wpdb->delete($registers, ['id' => (int)$t->id], ['%d']);
            if ($ok) {
                $deleted++;
                $affected_customers[(int)$t->customer_id] = (string)$t->batch_code;
                Biawin_CRM_DB::log('refund_register', (int)$t->customer_id, 'refund_register_row_purged', null, [
                    'row_id' => (int)$t->id, 'source_type' => $source_type,
                    'batch_code' => (string)$t->batch_code, 'actor' => get_current_user_id(),
                ]);
            }
        }
        // تگ قطعی فقط برای مشتری‌هایی که دیگر هیچ رکورد عودت اکسل ندارند برداشته می‌شود.
        $tags_removed = 0;
        $tags = Biawin_CRM_DB::table('customer_tags');
        foreach (array_keys($affected_customers) as $cid) {
            $remaining = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $registers WHERE customer_id=%d AND source_type='excel'", $cid));
            if ($remaining === 0) {
                $tags_removed += (int)$wpdb->query($wpdb->prepare("DELETE FROM $tags WHERE customer_id=%d AND tag=%s", $cid, self::TAG_CONFIRMED));
            }
        }
        if ($deleted > 0) {
            Biawin_CRM_DB::log('refund_register', null, 'refund_register_purge_batch', null, [
                'source_type' => $source_type, 'deleted' => $deleted, 'tags_removed' => $tags_removed,
                'only_no_invoice' => $only_no_invoice, 'batch_filter' => $batch_code, 'actor' => get_current_user_id(),
            ]);
        }
        return ['deleted' => $deleted, 'tags_removed' => $tags_removed];
    }

    /** CONFIRMED (excel «عودت شده») refund amount for a customer = SUM of the LATEST record
     *  per unique invoice code (replacement semantics per code; older uploads of the same
     *  code are history only). Records with no invoice code (legacy Customer-ID uploads)
     *  form ONE shared group for the whole customer.
     *  v0.8.62.13: فقط رکوردهای «عودت» (source_type='excel') — «استفاده شده» و اعلامی کارشناس در این عدد نیست. */
    public static function confirmed_total(int $customer_id): float
    {
        global $wpdb;
        $registers = Biawin_CRM_DB::table('refund_registers');
        return (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(refund_amount),0) FROM $registers
             WHERE customer_id=%d AND source_type='excel'
               AND id IN (SELECT MAX(id) FROM $registers WHERE customer_id=%d AND source_type='excel' GROUP BY invoice_code)",
            $customer_id,
            $customer_id
        ));
    }

    /** v0.8.62.11: مجموع «استفاده شده» مشتری = آخرین رکورد استفاده (source_type='used')
     *  — همان منطق Last-Record/جایگزینی confirmed_total؛ هرگز SUM همه رکوردها. */
    public static function used_total(int $customer_id): float
    {
        global $wpdb;
        return (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(refund_amount),0) FROM " . Biawin_CRM_DB::table('refund_registers') . "
             WHERE id IN (SELECT MAX(id) FROM " . Biawin_CRM_DB::table('refund_registers') . " WHERE customer_id=%d AND source_type='used')",
            $customer_id
        ));
    }

    /** مانده = مجموع فاکتورها − آخرین عودت قطعی اکسل − آخرین «استفاده شده» (v0.8.62.11).
     *  Negative means the customer over-refunded/over-consumed. */
    public static function balance_for(int $customer_id): float
    {
        global $wpdb;
        $invoices = Biawin_CRM_DB::table('invoices');
        $total = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(CASE WHEN COALESCE(exclude_from_total,0)=1 THEN 0 ELSE amount_paid END),0) FROM $invoices WHERE customer_id=%d",
            $customer_id
        ));
        return $total - self::confirmed_total($customer_id) - self::used_total($customer_id);
    }

    /** Full history (excel refund/used + call reported) for the profile tab, newest first. */
    public static function history_for(int $customer_id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT rr.*, u.display_name actor_name FROM " . Biawin_CRM_DB::table('refund_registers') . " rr
             LEFT JOIN {$wpdb->users} u ON u.ID=rr.created_by
             WHERE rr.customer_id=%d ORDER BY rr.id DESC LIMIT 200",
            $customer_id
        ));
        return is_array($rows) ? $rows : [];
    }

    /** Customer ids carrying either refund tag (for the portal tab + assignment filter). */
    public static function tagged_customer_ids(string $which = 'any'): array
    {
        global $wpdb;
        $tags = Biawin_CRM_DB::table('customer_tags');
        if ($which === self::TAG_REPORTED || $which === self::TAG_CONFIRMED) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT customer_id FROM $tags WHERE tag=%s", $which));
        } else {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT customer_id FROM $tags WHERE tag IN (%s,%s)", self::TAG_REPORTED, self::TAG_CONFIRMED));
        }
        return array_map('intval', is_array($ids) ? $ids : []);
    }

    /** Both tags for one customer (fast path for list rows). */
    public static function tags_for(int $customer_id): array
    {
        global $wpdb;
        $tags = Biawin_CRM_DB::table('customer_tags');
        $rows = $wpdb->get_col($wpdb->prepare("SELECT tag FROM $tags WHERE customer_id=%d AND tag IN (%s,%s)", $customer_id, self::TAG_REPORTED, self::TAG_CONFIRMED));
        return is_array($rows) ? array_values($rows) : [];
    }

    /** v0.8.62.8: SUM of the last (MAX(id)) excel «عودت» refund_amount per customer,
     *  grouped per unique invoice code (v0.8.62.13 — mirrors confirmed_total() exactly).
     *  رکوردهای «استفاده شده» (source_type='used') در این عدد نیستند. */
    public static function refund_amounts_for(array $customer_ids): array
    {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $customer_ids))));
        if (!$ids) return [];
        $registers = Biawin_CRM_DB::table('refund_registers');
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $rows = (array)$wpdb->get_results($wpdb->prepare(
            "SELECT r.customer_id, SUM(r.refund_amount) refund_amount FROM $registers r
             JOIN (SELECT customer_id, MAX(id) max_id FROM $registers WHERE customer_id IN ($in) AND source_type='excel' GROUP BY customer_id, invoice_code) m
               ON m.max_id = r.id GROUP BY r.customer_id",
            ...$ids
        ));
        $out = [];
        foreach ($rows as $row) $out[(int)$row->customer_id] = (float)$row->refund_amount;
        return $out;
    }

    /** v0.8.62.8: recompute the REFUND_CONFIRMED / REFUND_REPORTED tags from the
     *  refund_registers rows (excel → CONFIRMED with amount, call → REPORTED).
     *  Self-heals rows that lost their tag (e.g. old bug) — ONLY adds tags, never removes.
     *  @return int number of rows healed. */
    public static function refresh_tag_rows(array $rows): int
    {
        global $wpdb;
        $healed = 0;
        foreach ((array)$rows as $row) {
            $cid = (int)($row->customer_id ?? 0);
            if ($cid <= 0) continue;
            $expected = (($row->source_type ?? '') === 'excel') ? self::TAG_CONFIRMED : self::TAG_REPORTED;
            $tags = self::tags_for($cid);
            if (in_array($expected, $tags, true)) continue;
            if ($expected === self::TAG_CONFIRMED) self::apply_confirmed_tag($cid);
            else self::apply_reported_tag($cid);
            $healed++;
        }
        return $healed;
    }
}
