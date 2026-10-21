<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Import_Service
{
    public static function field_labels(): array
    {
        return [
            'customer_external_id' => 'Customer ID / شناسه مشتری CRM',
            'name' => 'نام مشتری',
            'invoice_code' => 'کد فاکتور',
            'phone_1' => 'شماره تماس ۱',
            'phone_2' => 'شماره تماس ۲',
            'product' => 'محصول / محصولات',
            'quantity' => 'تعداد محصولات',
            'amount_paid' => 'مبلغ واریزی',
            'seller' => 'فروشنده',
            'callcenter' => 'کالسنتر',
            'sales_manager' => 'مدیر فروش',
            'invoice_status' => 'وضعیت فاکتور',
            'payment_info' => 'اطلاعات واریزی',
            'prepayment_approval' => 'تأیید پیش‌واریزی',
            'seller_action_date' => 'تاریخ اقدام فروشنده',
            'services' => 'خدمات بیاوین',
        ];
    }

    public static function suggest_header_mapping(array $headers): array
    {
        $aliases = [
            'customer_external_id' => ['customerid','customer id','customer_id','شناسه مشتری','کد مشتری crm','کد مشتری سی آر ام'],
            'name' => ['نام', 'نام مشتری', 'نام و نام خانوادگی', 'مشتری', 'customer name', 'customer_name', 'name'],
            'invoice_code' => ['کد فاکتور', 'شماره فاکتور', 'فاکتور', 'invoice code', 'invoice_code', 'invoice no', 'invoice_number'],
            'phone_1' => ['شماره تماس 1', 'شماره تماس ۱', 'شماره 1', 'شماره ۱', 'موبایل', 'شماره موبایل', 'تلفن همراه', 'phone 1', 'phone_1', 'mobile', 'mobile1'],
            'phone_2' => ['شماره تماس 2', 'شماره تماس ۲', 'شماره 2', 'شماره ۲', 'موبایل دوم', 'شماره موبایل دوم', 'تلفن دوم', 'phone 2', 'phone_2', 'mobile2'],
            'product' => ['محصول', 'محصولات', 'نام محصول', 'کالا', 'product', 'products'],
            'quantity' => ['تعداد', 'تعداد محصول', 'تعداد محصولات', 'quantity', 'qty'],
            'amount_paid' => ['مبلغ واریزی', 'مبلغ پرداختی', 'واریزی', 'جمع واریزی', 'پرداختی', 'amount paid', 'amount_paid', 'paid amount'],
            'seller' => ['فروشنده', 'کارشناس فروش', 'seller', 'salesperson'],
            'callcenter' => ['کالسنتر', 'کال سنتر', 'مرکز تماس', 'callcenter', 'call center'],
            'sales_manager' => ['مدیر فروش', 'سرپرست فروش', 'sales manager', 'sales_manager'],
            'invoice_status' => ['وضعیت', 'وضعیت فاکتور', 'invoice status', 'invoice_status', 'status'],
            'payment_info' => ['اطلاعات واریزی', 'اطلاعات پرداخت', 'شرح واریزی', 'payment info', 'payment_info'],
            'prepayment_approval' => ['تایید پیش واریزی','تأیید پیش واریزی','تایید پیش‌واریزی','تأیید پیش‌واریزی','prepayment approval','prepayment_approval'],
            'seller_action_date' => ['تاریخ اقدام فروشنده','تاریخ اقدام','seller action date','seller_action_date'],
            'services' => ['خدمات بیاوین', 'خدمات', 'سرویس', 'service', 'services', 'biawin services'],
        ];
        $normalized_aliases = [];
        foreach ($aliases as $target => $list) {
            foreach ($list as $alias) {
                $normalized_aliases[$target][] = self::normalize_header($alias);
            }
        }
        $result = [];
        $used = [];
        foreach ($headers as $header) {
            $normalized = self::normalize_header((string) $header);
            $best = '';
            foreach ($normalized_aliases as $target => $list) {
                if (!isset($used[$target]) && in_array($normalized, $list, true)) {
                    $best = $target;
                    break;
                }
            }
            if ($best === '' && isset($used['phone_1']) && !isset($used['phone_2'])) {
                $phone_base = preg_replace('/\(\d+\)$/u', '', $normalized);
                $phone_aliases = array_merge($normalized_aliases['phone_1'] ?? [], $normalized_aliases['phone_2'] ?? []);
                if (in_array($phone_base, $phone_aliases, true)) {
                    $best = 'phone_2';
                }
            }
            if ($best !== '') {
                $result[(string) $header] = $best;
                $used[$best] = true;
            }
        }
        return $result;
    }

    public static function default_mapping(): array
    {
        return [
            'customer_external_id' => 'CustomerID',
            'name' => 'نام',
            'invoice_code' => 'کد فاکتور',
            'phone_1' => 'شماره تماس 1',
            'phone_2' => 'شماره تماس 2',
            'product' => 'محصولات',
            'quantity' => 'تعداد محصولات',
            'amount_paid' => 'مبلغ واریزی',
            'seller' => 'فروشنده',
            'callcenter' => 'کالسنتر',
            'sales_manager' => 'مدیر فروش',
            'invoice_status' => 'وضعیت',
            'payment_info' => 'اطلاعات واریزی',
            'prepayment_approval' => 'تأیید پیش‌واریزی',
            'seller_action_date' => 'تاریخ اقدام فروشنده',
            'services' => 'خدمات بیاوین',
        ];
    }


    public static function inspect_file(string $path, string $file_name, int $sample_limit = 5): array
    {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            return self::inspect_xlsx($path, $sample_limit);
        }
        if ($extension === 'xls') {
            return [[], [], 0, ['فایل XLS قدیمی پشتیبانی نمی‌شود. لطفاً آن را به XLSX یا CSV تبدیل کنید.']];
        }
        return self::inspect_csv($path, $sample_limit);
    }

    private static function inspect_csv(string $path, int $sample_limit): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) return [[], [], 0, ['امکان باز کردن فایل CSV وجود ندارد.']];
        $first_line = fgets($handle);
        if ($first_line === false) { fclose($handle); return [[], [], 0, ['فایل خالی است.']]; }
        $delimiter = self::detect_delimiter($first_line);
        rewind($handle);
        $raw_headers = fgetcsv($handle, 0, $delimiter);
        if (!$raw_headers) { fclose($handle); return [[], [], 0, ['سرستون‌های فایل پیدا نشد.']]; }
        $headers = self::make_unique_headers(array_map([__CLASS__, 'clean_cell'], $raw_headers));
        $samples = array_fill_keys($headers, []);
        $row_count = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $has_value = false;
            foreach ($headers as $idx => $header) {
                $value = self::clean_cell($data[$idx] ?? '');
                if ($value !== '') $has_value = true;
                if ($row_count < $sample_limit && $value !== '' && !in_array($value, $samples[$header], true) && count($samples[$header]) < 3) {
                    $samples[$header][] = $value;
                }
            }
            if ($has_value) $row_count++;
        }
        fclose($handle);
        return [$headers, $samples, $row_count, []];
    }

    private static function inspect_xlsx(string $path, int $sample_limit): array
    {
        $result = self::xlsx_stream_rows($path, max(1, $sample_limit + 1), true);
        if (!empty($result['errors'])) return [[], [], 0, $result['errors']];
        $matrix = $result['rows'];
        if (!$matrix) return [[], [], 0, ['داده‌ای در Sheet اول فایل XLSX پیدا نشد.']];
        $headers = self::make_unique_headers(array_map([__CLASS__, 'clean_cell'], array_shift($matrix)));
        $samples = array_fill_keys($headers, []);
        foreach (array_slice($matrix, 0, $sample_limit) as $line) {
            foreach ($headers as $idx => $header) {
                $value = self::clean_cell($line[$idx] ?? '');
                if ($value !== '' && !in_array($value, $samples[$header], true) && count($samples[$header]) < 3) $samples[$header][] = $value;
            }
        }
        return [$headers, $samples, max(0, (int)$result['row_count'] - 1), []];
    }

    public static function parse_file(string $path, string $file_name): array
    {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            return self::parse_xlsx($path);
        }
        if ($extension === 'xls') {
            return [[], [], ['فایل XLS قدیمی در این نسخه پشتیبانی نمی‌شود. لطفاً فایل را به XLSX یا CSV تبدیل کنید.']];
        }
        return self::parse_csv($path);
    }

    public static function parse_csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return [[], [], ['امکان باز کردن فایل CSV وجود ندارد.']];
        }
        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            return [[], [], ['فایل خالی است.']];
        }
        $delimiter = self::detect_delimiter($first_line);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return [[], [], ['سرتیترهای فایل پیدا نشد.']];
        }
        $headers = self::make_unique_headers(array_map([__CLASS__, 'clean_cell'], $headers));
        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = self::clean_cell($data[$idx] ?? '');
            }
            if (count(array_filter($row, fn($value) => trim((string) $value) !== '')) > 0) {
                $rows[] = $row;
            }
        }
        fclose($handle);
        return [$headers, $rows, []];
    }

    private static function parse_xlsx(string $path): array
    {
        $result = self::xlsx_stream_rows($path, 0, false);
        if (!empty($result['errors'])) return [[], [], $result['errors']];
        $matrix = $result['rows'];
        if (!$matrix) return [[], [], ['داده‌ای در Sheet اول فایل XLSX پیدا نشد.']];
        $headers = self::make_unique_headers(array_map([__CLASS__, 'clean_cell'], array_shift($matrix)));
        $rows = [];
        foreach ($matrix as $line) {
            $row = [];
            foreach ($headers as $idx => $header) {
                if ($header !== '') $row[$header] = self::clean_cell($line[$idx] ?? '');
            }
            if (count(array_filter($row, fn($value) => trim((string)$value) !== '')) > 0) $rows[] = $row;
        }
        return [$headers, $rows, []];
    }

    /**
     * Stream XLSX XML instead of loading the whole worksheet XML into memory.
     * limit_rows=0 means read all rows. When count_only_after_limit=true, rows after the
     * requested sample are counted but not materialized.
     */
    private static function xlsx_stream_rows(string $path, int $limit_rows = 0, bool $count_only_after_limit = false): array
    {
        if (!class_exists('ZipArchive')) {
            return ['rows'=>[], 'row_count'=>0, 'errors'=>['ماژول ZipArchive روی PHP فعال نیست و خواندن XLSX ممکن نیست.']];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return ['rows'=>[], 'row_count'=>0, 'errors'=>['امکان باز کردن فایل XLSX وجود ندارد.']];
        $sheet_path = self::xlsx_first_sheet_path($zip);
        if (!$sheet_path) { $zip->close(); return ['rows'=>[], 'row_count'=>0, 'errors'=>['Sheet اول فایل XLSX پیدا نشد.']]; }
        $tmp_dir = self::xlsx_temp_dir();
        if (!$tmp_dir) { $zip->close(); return ['rows'=>[], 'row_count'=>0, 'errors'=>['امکان ایجاد پوشه موقت برای خواندن XLSX وجود ندارد.']]; }
        if (!wp_mkdir_p($tmp_dir)) { $zip->close(); return ['rows'=>[], 'row_count'=>0, 'errors'=>['امکان ایجاد پوشه موقت برای خواندن XLSX وجود ندارد.']]; }
        $targets = [$sheet_path];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) $targets[] = 'xl/sharedStrings.xml';
        $ok = $zip->extractTo($tmp_dir, $targets);
        $zip->close();
        if (!$ok) { self::rrmdir($tmp_dir); return ['rows'=>[], 'row_count'=>0, 'errors'=>['استخراج موقت Sheet اکسل ناموفق بود.']]; }
        $shared = self::xlsx_shared_strings_file($tmp_dir . '/xl/sharedStrings.xml');
        $sheet_file = $tmp_dir . '/' . $sheet_path;
        $rows = []; $row_count = 0; $stream_error = '';
        $ok_stream = self::stream_xml_elements($sheet_file, 'row', function(string $row_xml) use (&$rows, &$row_count, $limit_rows, $count_only_after_limit, $shared) {
            $row_count++;
            $materialize = !$limit_rows || count($rows) < $limit_rows;
            if (!$materialize) return $count_only_after_limit ? true : false;
            $rows[] = self::xlsx_row_from_xml($row_xml, $shared);
            return true;
        });
        self::rrmdir($tmp_dir);
        if (!$ok_stream) return ['rows'=>[], 'row_count'=>0, 'errors'=>['خواندن Streaming شیت اکسل ناموفق بود.']];
        return ['rows'=>$rows, 'row_count'=>$row_count, 'errors'=>[]];
    }

    private static function xlsx_temp_dir(): string
    {
        $upload = function_exists('wp_upload_dir') ? wp_upload_dir() : [];
        if (empty($upload['error']) && !empty($upload['basedir'])) {
            $base = trailingslashit((string)$upload['basedir']) . 'biawin-crm-temp';
            if (wp_mkdir_p($base)) {
                return trailingslashit($base) . 'bcrm-xlsx-' . wp_generate_password(12, false, false);
            }
        }
        $tmp_base = wp_tempnam('bcrm-xlsx');
        if (!$tmp_base) return '';
        @unlink($tmp_base);
        return $tmp_base . '-dir';
    }

    private static function xlsx_shared_strings_file(string $file): array
    {
        if (!is_file($file)) return [];
        $strings = [];
        self::stream_xml_elements($file, 'si', function(string $outer) use (&$strings) {
            preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $outer, $parts);
            $strings[] = html_entity_decode(implode('', $parts[1] ?? []), ENT_QUOTES | ENT_XML1, 'UTF-8');
            return true;
        });
        return $strings;
    }

    /** Read repeated XML elements from disk with bounded memory. */
    private static function stream_xml_elements(string $file, string $tag, callable $callback): bool
    {
        $fh = @fopen($file, 'rb');
        if (!$fh) return false;
        $buffer = ''; $open = '<' . $tag; $close = '</' . $tag . '>';
        while (!feof($fh)) {
            $chunk = fread($fh, 65536);
            if ($chunk === false) { fclose($fh); return false; }
            $buffer .= $chunk;
            while (true) {
                $start = strpos($buffer, $open);
                if ($start === false) {
                    if (strlen($buffer) > 262144) $buffer = substr($buffer, -1024);
                    break;
                }
                if ($start > 0) $buffer = substr($buffer, $start);
                $end = strpos($buffer, $close);
                if ($end === false) break;
                $end += strlen($close);
                $element = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end);
                $continue = $callback($element);
                if ($continue === false) { fclose($fh); return true; }
            }
        }
        fclose($fh);
        return true;
    }

    private static function xlsx_row_from_xml(string $row_xml, array $shared): array
    {
        preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $row_xml, $cell_matches, PREG_SET_ORDER);
        $row = [];
        foreach ($cell_matches as $cell) {
            $attrs=$cell[1]; $body=$cell[2]; $index=count($row);
            if (preg_match('/\br="([A-Z]+)\d+"/', $attrs, $m)) $index=self::column_letters_to_index($m[1]);
            $type=preg_match('/\bt="([^"]+)"/', $attrs, $m) ? $m[1] : ''; $value='';
            if ($type==='inlineStr' && preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s',$body,$tm)) $value=implode('',$tm[1]);
            elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/s',$body,$vm)) { $raw=html_entity_decode($vm[1],ENT_QUOTES|ENT_XML1,'UTF-8'); $value=$type==='s'?($shared[(int)$raw]??''):$raw; }
            $row[$index]=html_entity_decode($value,ENT_QUOTES|ENT_XML1,'UTF-8');
        }
        if (!$row) return [];
        ksort($row); $max=max(array_keys($row)); $filled=[];
        for($i=0;$i<=$max;$i++) $filled[$i]=$row[$i]??'';
        return $filled;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if (!is_array($items)) {
            @rmdir($dir);
            return;
        }
        foreach ($items as $item) {
            if ($item==='.' || $item==='..') continue;
            $path=$dir.'/'.$item; is_dir($path) ? self::rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function xlsx_shared_strings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!$xml) {
            return [];
        }

        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $xml, $items);
        $strings = [];
        foreach ($items[1] as $item) {
            preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $item, $parts);
            $strings[] = html_entity_decode(implode('', $parts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $strings;
    }

    private static function xlsx_first_sheet_path(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook && $rels && preg_match('/<sheet\b[^>]*r:id="([^"]+)"/', $workbook, $sheet_match)) {
            $rid = $sheet_match[1];
            if (preg_match('/<Relationship\b[^>]*Id="' . preg_quote($rid, '/') . '"[^>]*Target="([^"]+)"/', $rels, $rel_match)) {
                $target = $rel_match[1];
                return strpos($target, 'xl/') === 0 ? $target : 'xl/' . ltrim($target, '/');
            }
        }
        return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    private static function xlsx_sheet_matrix(string $xml, array $shared): array
    {
        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $xml, $row_matches);
        $matrix = [];
        foreach ($row_matches[1] as $row_xml) {
            preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $row_xml, $cell_matches, PREG_SET_ORDER);
            $row = [];
            foreach ($cell_matches as $cell) {
                $attrs = $cell[1];
                $body = $cell[2];
                $index = count($row);
                if (preg_match('/\br="([A-Z]+)\d+"/', $attrs, $ref_match)) {
                    $index = self::column_letters_to_index($ref_match[1]);
                }
                $type = preg_match('/\bt="([^"]+)"/', $attrs, $type_match) ? $type_match[1] : '';
                $value = '';
                if ($type === 'inlineStr' && preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $body, $text_matches)) {
                    $value = implode('', $text_matches[1]);
                } elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/s', $body, $value_match)) {
                    $raw = html_entity_decode($value_match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $value = $type === 's' ? ($shared[(int) $raw] ?? '') : $raw;
                }
                $row[$index] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            if ($row) {
                ksort($row);
                $max = max(array_keys($row));
                $filled = [];
                for ($i = 0; $i <= $max; $i++) {
                    $filled[$i] = $row[$i] ?? '';
                }
                $matrix[] = $filled;
            }
        }
        return $matrix;
    }

    private static function column_letters_to_index(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }
        return $index - 1;
    }

    private static function detect_delimiter(string $line): string
    {
        $delimiters = [',' => 0, ';' => 0, "\t" => 0];
        foreach ($delimiters as $delimiter => $_) {
            $delimiters[$delimiter] = substr_count($line, $delimiter);
        }
        arsort($delimiters);
        return (string) array_key_first($delimiters);
    }

    private static function make_unique_headers(array $headers): array
    {
        $seen = [];
        $result = [];
        foreach ($headers as $index => $header) {
            $base = self::clean_cell($header);
            if ($base === '') {
                $base = 'ستون بدون عنوان ' . ($index + 1);
            }
            $key = self::normalize_header($base);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $result[] = $seen[$key] > 1 ? $base . ' (' . $seen[$key] . ')' : $base;
        }
        return $result;
    }

    private static function clean_cell($value): string
    {
        $value = (string) $value;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        return trim($value);
    }

    private static function normalize_header(?string $value): string
    {
        $value = self::clean_cell($value);
        $value = str_replace(['ي', 'ك', 'ۀ', 'ة', 'أ', 'إ'], ['ی', 'ک', 'ه', 'ه', 'ا', 'ا'], $value);
        $value = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $value);
        $value = preg_replace('/[\s\-_‌]+/u', '', $value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function value_for(array $row, array $mapping, string $key): string
    {
        $column = $mapping[$key] ?? '';
        if ($column !== '' && array_key_exists($column, $row)) {
            return (string) $row[$column];
        }

        $wanted = self::normalize_header($column);
        foreach ($row as $header => $value) {
            if (self::normalize_header($header) === $wanted) {
                return (string) $value;
            }
        }
        return '';
    }

    public static function analyze(array $rows, array $mapping): array
    {
        $invoices = [];
        $phone_invoice_usage = [];
        $missing_invoice_code_rows = 0;
        foreach ($rows as $row) {
            $code = trim(self::value_for($row, $mapping, 'invoice_code'));
            if ($code === '') {
                $missing_invoice_code_rows++;
                continue;
            }
            if (!isset($invoices[$code])) {
                $invoices[$code] = [
                    'invoice_code' => $code,
                    'name' => trim(self::value_for($row, $mapping, 'name')),
                    'phones' => [],
                    'products' => [],
                    'services' => [],
                    'amount_paid' => Biawin_CRM_Normalizer::money(self::value_for($row, $mapping, 'amount_paid')),
                    'amount_values' => [],
                    'names' => [],
                    'conflicts' => [],
                    'seller' => trim(self::value_for($row, $mapping, 'seller')),
                    'callcenter' => trim(self::value_for($row, $mapping, 'callcenter')),
                    'sales_manager' => trim(self::value_for($row, $mapping, 'sales_manager')),
                    'invoice_status' => trim(self::value_for($row, $mapping, 'invoice_status')),
                    'payment_info' => trim(self::value_for($row, $mapping, 'payment_info')),
                    'extra_fields' => [],
                    'rows' => 0,
                ];
            }
            $row_name = trim(self::value_for($row, $mapping, 'name'));
            if ($row_name !== '') {
                $invoices[$code]['names'][$row_name] = $row_name;
            }
            $row_amount = Biawin_CRM_Normalizer::money(self::value_for($row, $mapping, 'amount_paid'));
            if ($row_amount > 0) {
                $invoices[$code]['amount_values'][(string) $row_amount] = $row_amount;
            }
            foreach (['phone_1', 'phone_2'] as $phone_key) {
                $phone = Biawin_CRM_Normalizer::phone(self::value_for($row, $mapping, $phone_key));
                if ($phone !== '') {
                    $invoices[$code]['phones'][$phone] = $phone;
                    if (!isset($phone_invoice_usage[$phone])) {
                        $phone_invoice_usage[$phone] = [];
                    }
                    $phone_invoice_usage[$phone][$code] = true;
                }
            }
            $product = trim(self::value_for($row, $mapping, 'product'));
            if ($product !== '') {
                $quantity = Biawin_CRM_Normalizer::money(self::value_for($row, $mapping, 'quantity') ?: 1);
                $invoices[$code]['products'][] = ['name' => $product, 'quantity' => $quantity > 0 ? $quantity : 1];
            }
            $services = trim(self::value_for($row, $mapping, 'services'));
            if ($services !== '') {
                foreach (preg_split('/[،,|;]+/u', $services) as $service) {
                    $service = trim($service);
                    if ($service !== '') {
                        $invoices[$code]['services'][] = $service;
                    }
                }
            }
            foreach (($mapping['__custom'] ?? []) as $custom_header) {
                if (!array_key_exists($custom_header, $row)) continue;
                $custom_value = trim((string)$row[$custom_header]);
                if ($custom_value === '') continue;
                if (!isset($invoices[$code]['extra_fields'][$custom_header])) $invoices[$code]['extra_fields'][$custom_header] = [];
                if (!in_array($custom_value, $invoices[$code]['extra_fields'][$custom_header], true)) $invoices[$code]['extra_fields'][$custom_header][] = $custom_value;
            }
            $invoices[$code]['rows']++;
        }

        $high_risk_phones = [];
        foreach ($phone_invoice_usage as $phone => $codes) {
            if (count($codes) >= 10) {
                $high_risk_phones[$phone] = count($codes);
            }
        }

        foreach ($invoices as &$invoice) {
            if (count($invoice['amount_values']) > 1) {
                $invoice['conflicts'][] = 'مبلغ واریزی در ردیف‌های این فاکتور متفاوت است.';
            }
            $normalized_names = array_unique(array_filter(array_map([Biawin_CRM_Normalizer::class, 'name'], $invoice['names'])));
            if (count($normalized_names) > 1) {
                $invoice['conflicts'][] = 'نام ثبت‌شده در ردیف‌های این فاکتور متفاوت است.';
            }
            foreach ($invoice['extra_fields'] as $header => $values) {
                $invoice['extra_fields'][$header] = count($values) <= 1 ? ($values[0] ?? '') : array_values($values);
            }
        }
        unset($invoice);

        $parent = [];
        $find = function ($x) use (&$parent, &$find) {
            if (!isset($parent[$x])) {
                $parent[$x] = $x;
            }
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }
            return $parent[$x];
        };
        $union = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        foreach ($invoices as $code => $invoice) {
            $node = 'i:' . $code;
            $find($node);
            foreach ($invoice['phones'] as $phone) {
                if (isset($high_risk_phones[$phone])) {
                    continue;
                }
                $union($node, 'p:' . $phone);
            }
        }

        $groups = [];
        foreach ($invoices as $code => $invoice) {
            $root = $find('i:' . $code);
            if (!isset($groups[$root])) {
                $groups[$root] = ['invoices' => [], 'phones' => [], 'names' => [], 'amount_paid' => 0, 'suspicious' => false, 'reasons' => [], 'merge_level' => 'definite'];
            }
            $groups[$root]['invoices'][] = $code;
            $groups[$root]['phones'] = array_values(array_unique(array_merge($groups[$root]['phones'], array_values($invoice['phones']))));
            $groups[$root]['names'][] = $invoice['name'];
            $groups[$root]['amount_paid'] += $invoice['amount_paid'];
            if (!empty($invoice['conflicts'])) {
                $groups[$root]['suspicious'] = true;
                $groups[$root]['reasons'] = array_values(array_unique(array_merge($groups[$root]['reasons'], $invoice['conflicts'])));
            }
        }

        foreach ($groups as &$group) {
            $distinct_names = array_unique(array_filter(array_map([Biawin_CRM_Normalizer::class, 'name'], $group['names'])));
            if (count($distinct_names) > 2) {
                $group['suspicious'] = true;
                $group['reasons'][] = 'چند نام متفاوت در یک زنجیره شماره وجود دارد.';
            }
            foreach ($group['phones'] as $phone) {
                if (isset($high_risk_phones[$phone])) {
                    $group['suspicious'] = true;
                    $group['reasons'][] = 'شماره پرتکرار ' . $phone . ' در ' . $high_risk_phones[$phone] . ' فاکتور دیده شده است.';
                }
            }
            $group['merge_level'] = $group['suspicious'] ? 'probable' : 'definite';
            $group['reasons'] = array_values(array_unique($group['reasons']));
            $group['names'] = array_values(array_unique(array_filter($group['names'])));
        }

        return [
            'row_count' => count($rows),
            'invoice_count' => count($invoices),
            'profile_count' => count($groups),
            'suspicious_group_count' => count(array_filter($groups, fn($g) => !empty($g['suspicious']))),
            'conflicted_invoice_count' => count(array_filter($invoices, fn($i) => !empty($i['conflicts']))),
            'high_risk_phones' => $high_risk_phones,
            'missing_invoice_code_rows' => $missing_invoice_code_rows,
            'invoices' => $invoices,
            'groups' => array_values($groups),
        ];
    }

    public static function commit(array $analysis, array $mapping, string $file_name): int
    {
        global $wpdb;
        $now = Biawin_CRM_DB::now();
        $wpdb->insert(Biawin_CRM_DB::table('imports'), [
            'file_name' => $file_name,
            'mapping_json' => wp_json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'dry_run_json' => wp_json_encode([
                'row_count' => $analysis['row_count'],
                'invoice_count' => $analysis['invoice_count'],
                'profile_count' => $analysis['profile_count'],
                'suspicious_group_count' => $analysis['suspicious_group_count'],
                'missing_invoice_code_rows' => $analysis['missing_invoice_code_rows'] ?? 0,
                'conflicted_invoice_count' => $analysis['conflicted_invoice_count'] ?? 0,
                'high_risk_phones' => $analysis['high_risk_phones'] ?? [],
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'committed',
            'imported_by' => get_current_user_id(),
            'created_at' => $now,
            'committed_at' => $now,
        ]);
        $import_id = (int) $wpdb->insert_id;

        foreach ($analysis['groups'] as $group) {
            $display_name = $group['names'][0] ?? null;
            $sales_manager = self::sales_manager_for_group($group, $analysis['invoices']);
            $existing_customer_ids = self::existing_customer_ids_for_group($group, $analysis['invoices']);
            $status = empty($group['suspicious']) ? 'auto_linked' : 'needs_review';
            if (!$existing_customer_ids) {
                $wpdb->insert(Biawin_CRM_DB::table('customers'), [
                    'internal_uuid' => wp_generate_uuid4(),
                    'display_name' => $display_name,
                    'normalized_name' => Biawin_CRM_Normalizer::name($display_name),
                    'sales_manager' => $sales_manager ?: null,
                    'status' => $status,
                    'total_paid' => $group['amount_paid'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $customer_id = (int) $wpdb->insert_id;
            } else {
                $customer_id = (int) $existing_customer_ids[0];
                $wpdb->update(Biawin_CRM_DB::table('customers'), [
                    'display_name' => $display_name,
                    'normalized_name' => Biawin_CRM_Normalizer::name($display_name),
                    'sales_manager' => $sales_manager ?: null,
                    'status' => count($existing_customer_ids) > 1 ? 'needs_review' : $status,
                    'updated_at' => $now,
                ], ['id' => $customer_id]);
            }

            foreach ($group['phones'] as $idx => $phone) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO " . Biawin_CRM_DB::table('phones') . " (customer_id, raw_phone, normalized_phone, source, is_primary, created_at)
                     VALUES (%d, %s, %s, 'import', %d, %s)
                     ON DUPLICATE KEY UPDATE customer_id = IF(auto_match_blocked = 0, VALUES(customer_id), customer_id)",
                    $customer_id,
                    $phone,
                    $phone,
                    $idx === 0 ? 1 : 0,
                    $now
                ));
            }

            foreach ($group['invoices'] as $code) {
                $invoice = $analysis['invoices'][$code];
                $invoice_id = self::upsert_invoice($customer_id, $invoice, $import_id, $now);
        self::recalculate_assignment_cohort($customer_id);
                $wpdb->delete(Biawin_CRM_DB::table('invoice_phones'), ['invoice_id' => $invoice_id]);
                foreach (array_values($invoice['phones']) as $idx => $invoice_phone) {
                    $wpdb->insert(Biawin_CRM_DB::table('invoice_phones'), [
                        'invoice_id' => $invoice_id,
                        'normalized_phone' => $invoice_phone,
                        'phone_position' => $idx + 1,
                        'created_at' => $now,
                    ]);
                }
                $wpdb->delete(Biawin_CRM_DB::table('invoice_items'), ['invoice_id' => $invoice_id]);
                $wpdb->delete(Biawin_CRM_DB::table('services'), ['invoice_id' => $invoice_id]);
                foreach ($invoice['products'] as $product) {
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO " . Biawin_CRM_DB::table('invoice_items') . " (invoice_id, product_name, quantity, created_at)
                         VALUES (%d, %s, %f, %s)
                         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
                        $invoice_id,
                        $product['name'],
                        $product['quantity'],
                        $now
                    ));
                }
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM " . Biawin_CRM_DB::table('payments') . " WHERE invoice_id=%d AND source_import_id IS NOT NULL",
                    $invoice_id
                ));
                foreach (array_unique($invoice['services']) as $service) {
                    $wpdb->insert(Biawin_CRM_DB::table('services'), [
                        'invoice_id' => $invoice_id,
                        'customer_id' => $customer_id,
                        'service_name' => $service,
                        'service_status' => null,
                        'source_import_id' => $import_id,
                        'created_at' => $now,
                    ]);
                }
                if ($invoice['amount_paid'] > 0) {
                    $wpdb->insert(Biawin_CRM_DB::table('payments'), [
                        'invoice_id' => $invoice_id,
                        'amount' => $invoice['amount_paid'],
                        'payment_info' => $invoice['payment_info'],
                        'source_import_id' => $import_id,
                        'created_at' => $now,
                    ]);
                }
            }

            $wpdb->insert(Biawin_CRM_DB::table('merge_events'), [
                'primary_customer_id' => $customer_id,
                'merged_customer_ids' => wp_json_encode([$customer_id], JSON_UNESCAPED_UNICODE),
                'merge_type' => empty($group['suspicious']) ? 'auto' : 'suspect',
                'action_type' => $existing_customer_ids ? 'reimport_link' : 'initial_import_group',
                'confidence' => empty($group['suspicious']) ? 90 : 55,
                'reason' => empty($group['reasons']) ? 'Phone-chain import grouping' : implode(' | ', $group['reasons']),
                'before_json' => wp_json_encode(['existing_customer_ids' => $existing_customer_ids], JSON_UNESCAPED_UNICODE),
                'after_json' => wp_json_encode($group, JSON_UNESCAPED_UNICODE),
                'performed_by' => get_current_user_id(),
                'created_at' => $now,
            ]);
            Biawin_CRM_DB::log('customer', $customer_id, empty($group['suspicious']) ? 'auto_profile_created' : 'suspect_profile_created', null, $group);
            Biawin_CRM_Customer_Service::recalculate_customer($customer_id);
            foreach ($existing_customer_ids as $old_customer_id) {
                if ((int)$old_customer_id !== $customer_id) {
                    Biawin_CRM_Customer_Service::recalculate_customer((int)$old_customer_id);
                }
            }
        }

        return $import_id;
    }

    private static function existing_customer_ids_for_group(array $group, array $invoices): array
    {
        global $wpdb;
        $ids = [];
        foreach ($group['invoices'] as $code) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM " . Biawin_CRM_DB::table('invoices') . " WHERE invoice_code = %s", $code));
            if ($id) {
                $ids[] = (int) $id;
            }
        }
        foreach ($group['phones'] as $phone) {
            $id = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM " . Biawin_CRM_DB::table('phones') . " WHERE normalized_phone = %s AND auto_match_blocked = 0", $phone));
            if ($id) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function sales_manager_for_group(array $group, array $invoices): string
    {
        foreach (($group['invoices'] ?? []) as $code) {
            $value = trim((string)($invoices[$code]['sales_manager'] ?? ''));
            if ($value !== '') return sanitize_text_field($value);
        }
        return '';
    }

    private static function upsert_invoice(int $customer_id, array $invoice, int $import_id, string $now): int
    {
        global $wpdb;
        $table = Biawin_CRM_DB::table('invoices');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE invoice_code = %s", $invoice['invoice_code']));
        $conflicts = $invoice['conflicts'];
        if ($existing) {
            if ((float) $existing->amount_paid !== (float) $invoice['amount_paid']) {
                $conflicts[] = 'مبلغ فایل جدید با مبلغ قبلی فاکتور متفاوت است.';
            }
            if ($existing->invoice_status && $invoice['invoice_status'] && $existing->invoice_status !== $invoice['invoice_status']) {
                $conflicts[] = 'وضعیت فایل جدید با وضعیت قبلی فاکتور متفاوت است.';
            }
            $before = $existing;
            $wpdb->update($table, [
                'customer_id' => $customer_id,
                'invoice_name' => $invoice['name'] ?: $existing->invoice_name,
                'amount_paid' => $invoice['amount_paid'] ?: $existing->amount_paid,
                'seller' => $invoice['seller'] ?: $existing->seller,
                'callcenter' => $invoice['callcenter'] ?: $existing->callcenter,
                'sales_manager' => $invoice['sales_manager'] ?: $existing->sales_manager,
                'invoice_status' => $invoice['invoice_status'] ?: $existing->invoice_status,
                'payment_info' => $invoice['payment_info'] ?: $existing->payment_info,
                'prepayment_approval_status' => $invoice['prepayment_approval'] ?: ($existing->prepayment_approval_status ?? null),
                'prepayment_unapproved' => !empty($invoice['prepayment_unapproved']) ? 1 : 0,
                'seller_action_date' => $invoice['seller_action_date'] ?? null,
                'seller_action_year' => !empty($invoice['seller_action_year']) ? (int)$invoice['seller_action_year'] : null,
                'extra_json' => !empty($invoice['extra_fields']) ? wp_json_encode($invoice['extra_fields'], JSON_UNESCAPED_UNICODE) : ($existing->extra_json ?? null),
                'has_input_conflict' => empty($conflicts) ? 0 : 1,
                'conflict_json' => empty($conflicts) ? null : wp_json_encode(array_values(array_unique($conflicts)), JSON_UNESCAPED_UNICODE),
                'source_import_id' => $import_id,
                'updated_at' => $now,
            ], ['id' => (int) $existing->id]);
            Biawin_CRM_DB::log('invoice', (int) $existing->id, empty($conflicts) ? 'invoice_reimported' : 'invoice_reimport_conflict', $before, $invoice);
            return (int) $existing->id;
        }

        $wpdb->insert($table, [
            'customer_id' => $customer_id,
            'invoice_code' => $invoice['invoice_code'],
            'invoice_name' => $invoice['name'],
            'amount_paid' => $invoice['amount_paid'],
            'seller' => $invoice['seller'],
            'callcenter' => $invoice['callcenter'],
            'sales_manager' => $invoice['sales_manager'],
            'invoice_status' => $invoice['invoice_status'],
            'payment_info' => $invoice['payment_info'],
            'prepayment_approval_status' => $invoice['prepayment_approval'] ?? null,
            'prepayment_unapproved' => !empty($invoice['prepayment_unapproved']) ? 1 : 0,
            'seller_action_date' => $invoice['seller_action_date'] ?? null,
            'seller_action_year' => !empty($invoice['seller_action_year']) ? (int)$invoice['seller_action_year'] : null,
            'extra_json' => !empty($invoice['extra_fields']) ? wp_json_encode($invoice['extra_fields'], JSON_UNESCAPED_UNICODE) : null,
            'has_input_conflict' => empty($conflicts) ? 0 : 1,
            'conflict_json' => empty($conflicts) ? null : wp_json_encode(array_values(array_unique($conflicts)), JSON_UNESCAPED_UNICODE),
            'source_import_id' => $import_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    private static function extract_jalali_year(string $value): int
    {
        $value = strtr(trim($value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if (preg_match('/(?:^|[^0-9])(13\d{2}|14\d{2})(?:[^0-9]|$)/u', $value, $m)) return (int)$m[1];
        return 0;
    }

    private static function recalculate_assignment_cohort(int $customer_id): void
    {
        if ($customer_id <= 0) return;
        global $wpdb;
        $invoices = Biawin_CRM_DB::table('invoices');
        $customers = Biawin_CRM_DB::table('customers');
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) total_count, SUM(CASE WHEN seller_action_year=1404 THEN 1 ELSE 0 END) y1404_count FROM $invoices WHERE customer_id=%d",
            $customer_id
        ));
        $total = (int)($stats->total_count ?? 0);
        $y1404 = (int)($stats->y1404_count ?? 0);
        $cohort = ($total > 0 && $total === $y1404) ? '1404' : 'normal';
        $wpdb->update($customers, ['assignment_cohort'=>$cohort, 'updated_at'=>Biawin_CRM_DB::now()], ['id'=>$customer_id]);
    }

    /* ---------------------------------------------------------------------
     * Streaming / batch import engine (v0.6.4)
     * ------------------------------------------------------------------ */

    /**
     * v0.8.63.2 — bounded import storage. Completed/cancelled jobs already delete their files;
     * this removes what abandoned uploads (header inspection without import) and killed/failed
     * jobs leave behind, so uploads/ cannot silently accumulate import copies:
     *  - uploads/biawin-crm-temp/bcrm-xlsx-* extraction dirs older than 6 h;
     *  - uploads/biawin-crm-imports/* files older than 48 h not referenced by an ACTIVE job.
     */
    public static function sweep_stale_import_files(): int
    {
        global $wpdb;
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : [];
        if (!empty($uploads['error']) || empty($uploads['basedir'])) return 0;
        $base = trailingslashit((string)$uploads['basedir']);
        $removed = 0;
        foreach ((array)@glob($base . 'biawin-crm-temp/bcrm-xlsx-*', GLOB_NOSORT) as $dir) {
            if (is_string($dir) && is_dir($dir) && (time() - (int)@filemtime($dir)) > 6 * HOUR_IN_SECONDS) { self::rrmdir($dir); $removed++; }
        }
        $active = [];
        $rows = $wpdb->get_results("SELECT source_path, normalized_csv_path FROM " . Biawin_CRM_DB::table('import_jobs') . " WHERE status NOT IN ('completed','cancelled')", ARRAY_A);
        foreach ((array)$rows as $r) {
            foreach (['source_path', 'normalized_csv_path'] as $k) if (!empty($r[$k])) $active[wp_normalize_path((string)$r[$k])] = true;
        }
        foreach ((array)@glob($base . 'biawin-crm-imports/*', GLOB_NOSORT) as $file) {
            if (!is_string($file) || !is_file($file)) continue;
            $name = basename($file);
            if (in_array($name, ['index.php', '.htaccess', 'index.html'], true)) continue;
            if (isset($active[wp_normalize_path($file)])) continue;
            if ((time() - (int)@filemtime($file)) > 2 * DAY_IN_SECONDS && @unlink($file)) $removed++;
        }
        return $removed;
    }

    public static function persist_uploaded_file(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [null, null, 'فایل آپلودشده معتبر نیست.'];
        }
        self::sweep_stale_import_files(); // v0.8.63.2
        $name = sanitize_file_name((string)($file['name'] ?? 'import.xlsx'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            return [null, null, 'فقط CSV و XLSX پشتیبانی می‌شود.'];
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) return [null, null, (string)$uploads['error']];
        $dir = trailingslashit($uploads['basedir']) . 'biawin-crm-imports';
        if (!wp_mkdir_p($dir)) return [null, null, 'امکان ساخت پوشه موقت ایمپورت وجود ندارد.'];
        // Best-effort direct web access protection.
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Deny from all\n");
        if (!is_file($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        $token = wp_generate_password(32, false, false);
        $dest = $dir . '/' . $token . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            if (!@copy($file['tmp_name'], $dest)) return [null, null, 'ذخیره موقت فایل ناموفق بود.'];
        }
        $meta = ['path' => $dest, 'name' => $name, 'time' => time()];
        $uid = get_current_user_id();
        update_user_meta($uid, 'bcrm_upload_' . $token, $meta);
        // A transient fallback makes the hand-off between separate AJAX requests
        // resilient on hosts where user-meta/object-cache state is inconsistent.
        set_transient('bcrm_upload_' . $uid . '_' . $token, $meta, 6 * HOUR_IN_SECONDS);
        return [$token, $dest, null];
    }

    public static function resolve_uploaded_file(string $token): ?array
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token);
        if ($token === '') return null;
        $uid = get_current_user_id();
        $data = get_user_meta($uid, 'bcrm_upload_' . $token, true);
        if (!is_array($data) || empty($data['path']) || !is_file((string)$data['path'])) {
            $data = get_transient('bcrm_upload_' . $uid . '_' . $token);
        }
        // Last-resort reconstruction: the token is also the persisted basename.
        if (!is_array($data) || empty($data['path']) || !is_file((string)$data['path'])) {
            $uploads = wp_upload_dir();
            if (empty($uploads['error'])) {
                $dir = trailingslashit($uploads['basedir']) . 'biawin-crm-imports/';
                foreach (['xlsx','csv'] as $ext) {
                    $candidate = $dir . $token . '.' . $ext;
                    if (is_file($candidate)) {
                        $data = ['path'=>$candidate,'name'=>'import.' . $ext,'time'=>@filemtime($candidate) ?: time()];
                        break;
                    }
                }
            }
        }
        if (!is_array($data) || empty($data['path']) || !is_file((string)$data['path'])) return null;
        if (!empty($data['time']) && (time() - (int)$data['time']) > 6 * HOUR_IN_SECONDS) return null;
        return $data;
    }

    public static function create_job(string $upload_token, array $mapping, array $headers): array
    {
        global $wpdb;
        $upload = self::resolve_uploaded_file($upload_token);
        if (!$upload) return [null, 'فایل موقت پیدا نشد یا منقضی شده است. دوباره فایل را انتخاب کنید.'];
        $job_token = wp_generate_password(40, false, false);
        $source = (string)$upload['path'];
        $name = (string)$upload['name'];
        $csv_path = preg_replace('/\.[^.]+$/', '', $source) . '-' . $job_token . '.normalized.csv';
        $prep = self::write_normalized_csv($source, $name, $csv_path);
        if (!$prep['ok']) return [null, $prep['error'] ?: 'آماده‌سازی فایل ناموفق بود.'];

        $fh = @fopen($csv_path, 'rb');
        if (!$fh) return [null, 'فایل موقت پردازش قابل باز شدن نیست.'];
        // First row is the normalized header row.
        fgetcsv($fh);
        $offset = ftell($fh);
        fclose($fh);
        $now = Biawin_CRM_DB::now();
        $ok = $wpdb->insert(Biawin_CRM_DB::table('import_jobs'), [
            'job_token' => $job_token,
            'upload_token' => $upload_token,
            'file_name' => $name,
            'source_path' => $source,
            'normalized_csv_path' => $csv_path,
            'mapping_json' => wp_json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'headers_json' => wp_json_encode(array_values($headers), JSON_UNESCAPED_UNICODE),
            'status' => 'staging',
            'phase' => 'stage',
            'total_rows' => (int)$prep['row_count'],
            'processed_rows' => 0,
            'csv_offset' => max(0, (int)$offset),
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($ok === false) return [null, 'ساخت Job ایمپورت در دیتابیس ناموفق بود.'];
        return [[
            'jobToken' => $job_token,
            'totalRows' => (int)$prep['row_count'],
            'fileName' => $name,
            'uploadToken' => $upload_token,
        ], null];
    }

    private static function write_normalized_csv(string $source, string $name, string $dest): array
    {
        $out = @fopen($dest, 'wb');
        if (!$out) return ['ok'=>false,'row_count'=>0,'error'=>'امکان ساخت فایل Streaming موقت وجود ندارد.'];
        $row_count = 0; $first = true; $error = null; $write_failed = false;
        $ok = self::stream_source_rows($source, $name, function(array $row) use ($out, &$row_count, &$first, &$write_failed) {
            if ($first) {
                $row = self::make_unique_headers(array_map([__CLASS__, 'clean_cell'], $row));
                $first = false;
            } else {
                $row_count++;
            }
            // v0.8.63.2: a failed write (e.g. errno=28 disk full) must abort — a silently truncated
            // normalized CSV would otherwise be imported as if it were the whole file.
            if (@fputcsv($out, $row) === false) { $write_failed = true; return false; }
            return true;
        }, $error);
        $closed = @fclose($out);
        if ($write_failed || !$closed) { @unlink($dest); return ['ok'=>false,'row_count'=>0,'error'=>'نوشتن فایل موقت ایمپورت ناموفق بود (احتمالاً دیسک سرور پر است). هیچ داده‌ای وارد نشد؛ پس از آزادسازی فضا دوباره تلاش کنید.']; }
        if (!$ok) { @unlink($dest); return ['ok'=>false,'row_count'=>0,'error'=>$error ?: 'خواندن فایل ناموفق بود.']; }
        return ['ok'=>true,'row_count'=>$row_count,'error'=>null];
    }

    private static function stream_source_rows(string $path, string $name, callable $callback, ?string &$error = null): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $fh = @fopen($path, 'rb');
            if (!$fh) { $error='امکان باز کردن CSV وجود ندارد.'; return false; }
            $firstLine = fgets($fh);
            if ($firstLine === false) { fclose($fh); $error='فایل خالی است.'; return false; }
            $delimiter = self::detect_delimiter($firstLine); rewind($fh);
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $row = array_map([__CLASS__, 'clean_cell'], $row);
                if ($callback($row) === false) break;
            }
            fclose($fh); return true;
        }
        if ($ext !== 'xlsx') { $error='فرمت فایل پشتیبانی نمی‌شود.'; return false; }
        if (!class_exists('ZipArchive')) { $error='ZipArchive فعال نیست.'; return false; }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) { $error='امکان باز کردن XLSX وجود ندارد.'; return false; }
        $sheet_path = self::xlsx_first_sheet_path($zip);
        if (!$sheet_path) { $zip->close(); $error='Sheet اول پیدا نشد.'; return false; }
        $tmp_base = wp_tempnam('bcrm-stream');
        if (!$tmp_base) { $zip->close(); $error='امکان ایجاد فایل موقت وجود ندارد.'; return false; }
        @unlink($tmp_base); $tmp_dir=$tmp_base.'-dir';
        if (!wp_mkdir_p($tmp_dir)) { $zip->close(); $error='ساخت پوشه موقت ناموفق بود.'; return false; }
        $targets=[$sheet_path];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) $targets[]='xl/sharedStrings.xml';
        $ok=$zip->extractTo($tmp_dir,$targets); $zip->close();
        if (!$ok) { self::rrmdir($tmp_dir); $error='استخراج XLSX ناموفق بود.'; return false; }
        $shared=self::xlsx_shared_strings_file($tmp_dir.'/xl/sharedStrings.xml');
        $sheet_file=$tmp_dir.'/'.$sheet_path;
        $stream_ok=self::stream_xml_elements($sheet_file,'row',function(string $xml) use ($callback,$shared){
            return $callback(self::xlsx_row_from_xml($xml,$shared));
        });
        self::rrmdir($tmp_dir); unset($shared);
        if (!$stream_ok) { $error='خواندن Streaming شیت ناموفق بود.'; return false; }
        return true;
    }

    public static function stage_batch(string $job_token, int $batch_size = 500): array
    {
        global $wpdb;
        $batch_size = max(100, min(3000, $batch_size));
        $job = self::get_job($job_token);
        if (!$job) return ['ok'=>false,'message'=>'Job ایمپورت پیدا نشد.'];
        if ((int)$job->created_by !== get_current_user_id()) return ['ok'=>false,'message'=>'دسترسی به این ایمپورت مجاز نیست.'];
        if ($job->phase !== 'stage') return ['ok'=>true,'done'=>true,'phase'=>$job->phase];
        $headers = json_decode((string)$job->headers_json, true) ?: [];
        $mapping = json_decode((string)$job->mapping_json, true) ?: [];
        $fh = @fopen((string)$job->normalized_csv_path, 'rb');
        if (!$fh) return ['ok'=>false,'message'=>'فایل موقت ایمپورت قابل خواندن نیست.'];
        fseek($fh, (int)$job->csv_offset);
        $rows=[]; $read=0; $new_offset=(int)$job->csv_offset; $eof=false;
        while ($read < $batch_size) {
            $data=fgetcsv($fh);
            if ($data === false) { $eof=true; break; }
            $row=[]; $nonempty=false;
            foreach ($headers as $idx=>$header) {
                $v=self::clean_cell($data[$idx] ?? ''); $row[$header]=$v; if ($v!=='') $nonempty=true;
            }
            if ($nonempty) { $rows[]=$row; $read++; }
            $new_offset=ftell($fh);
        }
        if (feof($fh)) $eof=true;
        fclose($fh);
        $batch = self::analyze_rows_for_staging($rows, $mapping);
        foreach ($batch['invoices'] as $code=>$invoice) self::merge_stage_invoice($job_token,$code,$invoice);
        foreach ($batch['invoice_phones'] as $code=>$phones) {
            foreach ($phones as $phone) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO ".Biawin_CRM_DB::table('import_stage_phones')." (job_token,invoice_code,normalized_phone) VALUES (%s,%s,%s)",
                    $job_token,$code,$phone
                ));
            }
        }
        $processed=(int)$job->processed_rows + count($rows);
        $missing=(int)$job->missing_invoice_rows + (int)$batch['missing_invoice_code_rows'];
        $updates=['processed_rows'=>$processed,'missing_invoice_rows'=>$missing,'csv_offset'=>$new_offset,'updated_at'=>Biawin_CRM_DB::now()];
        if ($eof) {
            $stats=self::finish_staging($job_token);
            $updates=array_merge($updates,[
                'phase'=>'preview','status'=>'preview_ready','staged_invoices'=>$stats['invoice_count'],
                'conflicted_invoices'=>$stats['conflicted_invoice_count'],
                'high_risk_json'=>wp_json_encode($stats['high_risk_phones'],JSON_UNESCAPED_UNICODE),
            ]);
        }
        $wpdb->update(Biawin_CRM_DB::table('import_jobs'),$updates,['id'=>(int)$job->id]);
        return [
            'ok'=>true,'done'=>$eof,'phase'=>$eof?'preview':'stage','processedRows'=>$processed,
            'totalRows'=>(int)$job->total_rows,'missingRows'=>$missing,
            'preview'=>$eof?self::job_preview($job_token):null,
        ];
    }

    private static function analyze_rows_for_staging(array $rows, array $mapping): array
    {
        $invoices=[]; $invoice_phones=[]; $missing=0;
        foreach ($rows as $row) {
            $code=trim(self::value_for($row,$mapping,'invoice_code'));
            if ($code==='') { $missing++; continue; }
            if (!isset($invoices[$code])) $invoices[$code]=self::empty_invoice($code,$row,$mapping);
            $inv=&$invoices[$code];
            self::merge_row_into_invoice($inv,$row,$mapping);
            $invoice_phones[$code]=array_values($inv['phones']);
            unset($inv);
        }
        foreach ($invoices as &$inv) self::finalize_invoice_conflicts($inv); unset($inv);
        return ['invoices'=>$invoices,'invoice_phones'=>$invoice_phones,'missing_invoice_code_rows'=>$missing];
    }

    private static function empty_invoice(string $code, array $row, array $mapping): array
    {
        return [
            'invoice_code'=>$code,'customer_external_id'=>'','customer_external_ids'=>[],'name'=>'','phones'=>[],'products'=>[],'services'=>[],
            'amount_paid'=>0,'amount_values'=>[],'names'=>[],'conflicts'=>[],
            'seller'=>'','callcenter'=>'','sales_manager'=>'','invoice_status'=>'','payment_info'=>'',
            'prepayment_approval'=>'','prepayment_unapproved'=>0,'seller_action_date'=>'','seller_action_year'=>null,'seller_action_years'=>[],
            'extra_fields'=>[],'rows'=>0,'correction_mode'=>!empty($mapping['__correction_mode']) ? 1 : 0,
        ];
    }

    private static function merge_row_into_invoice(array &$inv, array $row, array $mapping): void
    {
        $external_id=trim(self::value_for($row,$mapping,'customer_external_id'));
        if ($external_id!=='') {
            $inv['customer_external_ids'][$external_id]=$external_id;
            if ($inv['customer_external_id']==='') $inv['customer_external_id']=$external_id;
        }
        $name=trim(self::value_for($row,$mapping,'name')); if ($name!=='') { $inv['names'][$name]=$name; if ($inv['name']==='') $inv['name']=$name; }
        $amount=Biawin_CRM_Normalizer::money(self::value_for($row,$mapping,'amount_paid'));
        if ($amount>0) { $inv['amount_values'][(string)$amount]=$amount; if (!$inv['amount_paid']) $inv['amount_paid']=$amount; }
        foreach(['phone_1','phone_2'] as $k){$ph=Biawin_CRM_Normalizer::phone(self::value_for($row,$mapping,$k));if($ph!=='')$inv['phones'][$ph]=$ph;}
        $product=trim(self::value_for($row,$mapping,'product'));
        if($product!==''){ $q=Biawin_CRM_Normalizer::money(self::value_for($row,$mapping,'quantity') ?: 1); $inv['products'][]=['name'=>$product,'quantity'=>$q>0?$q:1]; }
        $services=trim(self::value_for($row,$mapping,'services'));
        if($services!=='') foreach(preg_split('/[،,|;]+/u',$services) as $sv){$sv=trim($sv);if($sv!=='')$inv['services'][]=$sv;}
        foreach(['seller','callcenter','sales_manager','invoice_status','payment_info'] as $k){$v=trim(self::value_for($row,$mapping,$k));if($v!=='' && empty($inv[$k]))$inv[$k]=$v;}
        $approval=trim(self::value_for($row,$mapping,'prepayment_approval'));
        if($approval!==''){ if(empty($inv['prepayment_approval']))$inv['prepayment_approval']=$approval; if(strpos(self::normalize_header($approval),'تاییدنشد')!==false)$inv['prepayment_unapproved']=1; }
        $seller_action_date=trim(self::value_for($row,$mapping,'seller_action_date'));
        if($seller_action_date!=='') { if(empty($inv['seller_action_date'])) $inv['seller_action_date']=$seller_action_date; $year=self::extract_jalali_year($seller_action_date); if($year) { $inv['seller_action_years'][(string)$year]=$year; $inv['seller_action_year']=$year; } }
        foreach(($mapping['__custom']??[]) as $h){if(!array_key_exists($h,$row))continue;$v=trim((string)$row[$h]);if($v==='')continue;if(!isset($inv['extra_fields'][$h]))$inv['extra_fields'][$h]=[];if(!in_array($v,$inv['extra_fields'][$h],true))$inv['extra_fields'][$h][]=$v;}
        $inv['rows']++;
    }

    private static function finalize_invoice_conflicts(array &$inv): void
    {
        $inv['conflicts']=array_values(array_filter($inv['conflicts']??[]));
        if(count($inv['amount_values']??[])>1)$inv['conflicts'][]='مبلغ واریزی در ردیف‌های این فاکتور متفاوت است.';
        $external_ids=array_values(array_unique(array_filter(array_keys($inv['customer_external_ids']??[]))));
        if(count($external_ids)>1)$inv['conflicts'][]='CustomerID در ردیف‌های این فاکتور متفاوت است.';
        if($external_ids) $inv['customer_external_id']=$external_ids[0];
        $names=array_unique(array_filter(array_map([Biawin_CRM_Normalizer::class,'name'],$inv['names']??[])));
        if(count($names)>1)$inv['conflicts'][]='نام ثبت‌شده در ردیف‌های این فاکتور متفاوت است.';
        $action_years=array_values(array_unique(array_map('intval',array_values($inv['seller_action_years']??[]))));
        if(count($action_years)>1){$inv['conflicts'][]='تاریخ اقدام فروشنده در ردیف‌های این فاکتور مربوط به چند سال متفاوت است.';$inv['seller_action_year']=null;} elseif(count($action_years)===1){$inv['seller_action_year']=$action_years[0];}
        foreach($inv['extra_fields']??[] as $h=>$vals)$inv['extra_fields'][$h]=count($vals)<=1?($vals[0]??''):array_values($vals);
        $inv['conflicts']=array_values(array_unique($inv['conflicts']));
    }

    private static function merge_stage_invoice(string $job_token,string $code,array $incoming): void
    {
        global $wpdb; $table=Biawin_CRM_DB::table('import_stage_invoices');
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE job_token=%s AND invoice_code=%s",$job_token,$code));
        if($existing){
            $base=json_decode((string)$existing->invoice_json,true)?:self::empty_invoice($code,[],[]);
            $merged=self::merge_invoice_aggregates($base,$incoming);
            self::finalize_invoice_conflicts($merged);
            $wpdb->update($table,['source_customer_id'=>$merged['customer_external_id']??null,'invoice_json'=>wp_json_encode($merged,JSON_UNESCAPED_UNICODE),'row_count'=>(int)$merged['rows'],'has_conflict'=>empty($merged['conflicts'])?0:1,'updated_at'=>Biawin_CRM_DB::now()],['id'=>(int)$existing->id]);
        }else{
            $wpdb->insert($table,['job_token'=>$job_token,'invoice_code'=>$code,'source_customer_id'=>$incoming['customer_external_id']??null,'invoice_json'=>wp_json_encode($incoming,JSON_UNESCAPED_UNICODE),'row_count'=>(int)$incoming['rows'],'has_conflict'=>empty($incoming['conflicts'])?0:1,'updated_at'=>Biawin_CRM_DB::now()]);
        }
    }

    private static function merge_invoice_aggregates(array $a,array $b): array
    {
        $a['name']=$a['name']?:($b['name']??'');
        if(empty($a['customer_external_id'])&&!empty($b['customer_external_id']))$a['customer_external_id']=$b['customer_external_id'];
        $a['customer_external_ids']=array_merge($a['customer_external_ids']??[],$b['customer_external_ids']??[]);
        foreach(['phones','names'] as $k){$a[$k]=array_merge($a[$k]??[],$b[$k]??[]);}
        $a['products']=array_merge($a['products']??[],$b['products']??[]); $a['services']=array_merge($a['services']??[],$b['services']??[]);
        $amounts=array_values(array_unique(array_map('strval',array_merge(array_values($a['amount_values']??[]),array_values($b['amount_values']??[]))))); $a['amount_values']=[]; foreach($amounts as $av){ if((float)$av>0)$a['amount_values'][$av]=(float)$av; } if(empty($a['amount_paid']))$a['amount_paid']=$b['amount_paid']??0;
        foreach(['seller','callcenter','sales_manager','invoice_status','payment_info','prepayment_approval','seller_action_date'] as $k)if(empty($a[$k])&&!empty($b[$k]))$a[$k]=$b[$k];
        $a['prepayment_unapproved']=(!empty($a['prepayment_unapproved'])||!empty($b['prepayment_unapproved']))?1:0;
        if(empty($a['seller_action_year'])&&!empty($b['seller_action_year'])) $a['seller_action_year']=(int)$b['seller_action_year'];
        $a['seller_action_years']=array_merge($a['seller_action_years']??[],$b['seller_action_years']??[]);
        foreach($b['extra_fields']??[] as $h=>$vals){$vals=is_array($vals)?$vals:[$vals];$old=$a['extra_fields'][$h]??[];$old=is_array($old)?$old:[$old];$a['extra_fields'][$h]=array_values(array_unique(array_filter(array_merge($old,$vals),'strlen')));}
        $a['conflicts']=array_merge($a['conflicts']??[],$b['conflicts']??[]); $a['rows']=(int)($a['rows']??0)+(int)($b['rows']??0);
        return $a;
    }

    private static function finish_staging(string $job_token): array
    {
        global $wpdb; $p=Biawin_CRM_DB::table('import_stage_phones');
        $high=$wpdb->get_results($wpdb->prepare("SELECT normalized_phone,COUNT(DISTINCT invoice_code) cnt FROM $p WHERE job_token=%s GROUP BY normalized_phone HAVING cnt>=10 ORDER BY cnt DESC LIMIT 500",$job_token));
        $high_map=[]; foreach($high as $r)$high_map[$r->normalized_phone]=(int)$r->cnt;
        $it=Biawin_CRM_DB::table('import_stage_invoices');
        return ['invoice_count'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $it WHERE job_token=%s",$job_token)),'conflicted_invoice_count'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $it WHERE job_token=%s AND has_conflict=1",$job_token)),'high_risk_phones'=>$high_map];
    }

    public static function job_preview(string $job_token): array
    {
        $job=self::get_job($job_token); if(!$job)return [];
        global $wpdb; $stage=Biawin_CRM_DB::table('import_stage_invoices');
        $source_count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT source_customer_id) FROM $stage WHERE job_token=%s AND source_customer_id IS NOT NULL AND source_customer_id<>''",$job_token));
        $max_source=(int)$wpdb->get_var($wpdb->prepare("SELECT MAX(CAST(REPLACE(UPPER(source_customer_id),'C','') AS UNSIGNED)) FROM $stage WHERE job_token=%s AND UPPER(source_customer_id) REGEXP '^C?[0-9]+$'",$job_token));
        $missing_source=max(0,$max_source-$source_count);
        return ['row_count'=>(int)$job->processed_rows,'invoice_count'=>(int)$job->staged_invoices,'source_customer_count'=>$source_count,'source_customer_max'=>$max_source,'missing_source_customer_count'=>$missing_source,'conflicted_invoice_count'=>(int)$job->conflicted_invoices,'missing_invoice_code_rows'=>(int)$job->missing_invoice_rows,'high_risk_phones'=>json_decode((string)$job->high_risk_json,true)?:[],'file_name'=>$job->file_name];
    }

    public static function start_commit(string $job_token): array
    {
        global $wpdb; $job=self::get_job($job_token);
        if(!$job || (int)$job->created_by!==get_current_user_id())return ['ok'=>false,'message'=>'Job معتبر نیست.'];
        if($job->phase!=='preview')return ['ok'=>false,'message'=>'ایمپورت هنوز آماده ثبت نهایی نیست.'];
        $now=Biawin_CRM_DB::now();
        $wpdb->insert(Biawin_CRM_DB::table('imports'),['file_name'=>$job->file_name,'mapping_json'=>$job->mapping_json,'dry_run_json'=>wp_json_encode(self::job_preview($job_token),JSON_UNESCAPED_UNICODE),'status'=>'processing','imported_by'=>get_current_user_id(),'created_at'=>$now]);
        $import_id=(int)$wpdb->insert_id;
        if(!$import_id)return ['ok'=>false,'message'=>'ساخت رکورد ایمپورت ناموفق بود.'];
        $wpdb->update(Biawin_CRM_DB::table('import_jobs'),['phase'=>'commit','status'=>'committing','import_id'=>$import_id,'last_stage_id'=>0,'updated_at'=>$now],['id'=>(int)$job->id]);
        return ['ok'=>true,'importId'=>$import_id,'totalInvoices'=>(int)$job->staged_invoices];
    }

    public static function commit_batch(string $job_token,int $batch_size=100): array
    {
        global $wpdb; $batch_size=max(25,min(600,$batch_size)); $job=self::get_job($job_token);
        if(!$job || (int)$job->created_by!==get_current_user_id())return ['ok'=>false,'message'=>'Job معتبر نیست.'];
        if($job->phase==='done')return ['ok'=>true,'done'=>true,'committedInvoices'=>(int)$job->committed_invoices,'totalInvoices'=>(int)$job->staged_invoices];
        if($job->phase!=='commit')return ['ok'=>false,'message'=>'مرحله ثبت نهایی فعال نیست.'];
        $table=Biawin_CRM_DB::table('import_stage_invoices');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE job_token=%s AND id>%d ORDER BY id ASC LIMIT %d",$job_token,(int)$job->last_stage_id,$batch_size));
        if(!$rows){ self::complete_job($job); return ['ok'=>true,'done'=>true,'committedInvoices'=>(int)$job->committed_invoices,'totalInvoices'=>(int)$job->staged_invoices]; }
        $high=json_decode((string)$job->high_risk_json,true)?:[]; $last=(int)$job->last_stage_id; $count=0;
        foreach($rows as $r){$invoice=json_decode((string)$r->invoice_json,true)?:[]; if(!$invoice){$last=(int)$r->id;continue;} self::commit_staged_invoice($invoice,(int)$job->import_id,$high);$last=(int)$r->id;$count++;}
        $committed=(int)$job->committed_invoices+$count;
        $wpdb->update(Biawin_CRM_DB::table('import_jobs'),['committed_invoices'=>$committed,'last_stage_id'=>$last,'updated_at'=>Biawin_CRM_DB::now()],['id'=>(int)$job->id]);
        $done=$committed >= (int)$job->staged_invoices;
        if($done){$job->committed_invoices=$committed;self::complete_job($job);}
        return ['ok'=>true,'done'=>$done,'committedInvoices'=>$committed,'totalInvoices'=>(int)$job->staged_invoices];
    }

    private static function commit_staged_invoice(array $invoice,int $import_id,array $high_risk): void
    {
        global $wpdb;
        $now=Biawin_CRM_DB::now();
        $customers=Biawin_CRM_DB::table('customers');
        $external_id=sanitize_text_field((string)($invoice['customer_external_id']??''));
        $correction_mode = !empty($invoice['correction_mode']);
        $existing_invoice=$wpdb->get_row($wpdb->prepare("SELECT id,customer_id FROM ".Biawin_CRM_DB::table('invoices')." WHERE invoice_code=%s",$invoice['invoice_code']));
        $source_customer_id=$existing_invoice ? (int)$existing_invoice->customer_id : 0;
        $customer_id=0;

        // CustomerID from the source file is the primary identity key. In correction
        // mode, phones are never used for identity because shared placeholder phones
        // are intentionally blanked in the correction file.
        if($external_id!==''){
            $customer_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE source_customer_id=%s LIMIT 1",$external_id));
            if(!$customer_id && $source_customer_id){
                $source_external=(string)$wpdb->get_var($wpdb->prepare("SELECT source_customer_id FROM $customers WHERE id=%d",$source_customer_id));
                if($source_external===''){
                    $claimed=$wpdb->update($customers,['source_customer_id'=>$external_id,'updated_at'=>$now],['id'=>$source_customer_id]);
                    if($claimed!==false)$customer_id=$source_customer_id;
                }
            }
            if(!$customer_id){
                $wpdb->insert($customers,[
                    'internal_uuid'=>wp_generate_uuid4(),'source_customer_id'=>$external_id,
                    'display_name'=>$invoice['name']?:null,'normalized_name'=>Biawin_CRM_Normalizer::name($invoice['name']??''),
                    'sales_manager'=>trim((string)($invoice['sales_manager']??'')) ?: null,
                    'status'=>'auto_linked','total_paid'=>0,'created_at'=>$now,'updated_at'=>$now
                ]);
                $customer_id=(int)$wpdb->insert_id;
            }
        }else{
            $ids=[];
            if($source_customer_id)$ids[]=$source_customer_id;
            if(!$correction_mode){
                foreach(array_values($invoice['phones']??[]) as $phone){
                    if(isset($high_risk[$phone]))continue;
                    $cid=$wpdb->get_var($wpdb->prepare("SELECT customer_id FROM ".Biawin_CRM_DB::table('phones')." WHERE normalized_phone=%s AND auto_match_blocked=0",$phone));
                    if($cid)$ids[]=(int)$cid;
                }
            }
            $ids=array_values(array_unique(array_filter($ids)));
            if(!$ids){
                $wpdb->insert($customers,['internal_uuid'=>wp_generate_uuid4(),'display_name'=>$invoice['name']?:null,'normalized_name'=>Biawin_CRM_Normalizer::name($invoice['name']??''),'sales_manager'=>trim((string)($invoice['sales_manager']??'')) ?: null,'status'=>'auto_linked','total_paid'=>0,'created_at'=>$now,'updated_at'=>$now]);
                $customer_id=(int)$wpdb->insert_id;
            }else $customer_id=(int)$ids[0];
        }

        if(!$customer_id)return;
        $wpdb->update($customers,['display_name'=>$invoice['name']?:null,'normalized_name'=>Biawin_CRM_Normalizer::name($invoice['name']??''),'sales_manager'=>trim((string)($invoice['sales_manager']??'')) ?: null,'updated_at'=>$now],['id'=>$customer_id]);

        // Reassign the invoice first; this allows old phone-based profiles to collapse as the file is re-imported.
        $invoice_id=self::upsert_invoice($customer_id,$invoice,$import_id,$now);

        $has_primary=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Biawin_CRM_DB::table('phones')." WHERE customer_id=%d AND is_primary=1",$customer_id));
        foreach(array_values($invoice['phones']??[]) as $idx=>$phone){
            $row=$wpdb->get_row($wpdb->prepare("SELECT id,customer_id,auto_match_blocked,is_primary FROM ".Biawin_CRM_DB::table('phones')." WHERE normalized_phone=%s",$phone));
            $make_primary=(!$has_primary && $idx===0)?1:0;
            if(!$row){
                $wpdb->insert(Biawin_CRM_DB::table('phones'),['customer_id'=>$customer_id,'raw_phone'=>$phone,'normalized_phone'=>$phone,'phone_status'=>'unverified','relation_type'=>'unknown','source'=>'import','is_primary'=>$make_primary,'created_at'=>$now]);
                if($make_primary)$has_primary=1;
            }elseif((int)$row->customer_id===$customer_id || empty($row->customer_id) || ($source_customer_id && (int)$row->customer_id===$source_customer_id)){
                $keep_primary = $make_primary ? 1 : (((int)$row->customer_id === $customer_id && !empty($row->is_primary)) ? 1 : 0);
                $wpdb->update(Biawin_CRM_DB::table('phones'),['customer_id'=>$customer_id,'is_primary'=>$keep_primary],['id'=>(int)$row->id]);
                if($make_primary)$has_primary=1;
            }
        }
        Biawin_CRM_Customer_Service::ensure_single_primary_phone($customer_id);

        $wpdb->delete(Biawin_CRM_DB::table('invoice_phones'),['invoice_id'=>$invoice_id]);
        foreach(array_values($invoice['phones']??[]) as $idx=>$phone)$wpdb->insert(Biawin_CRM_DB::table('invoice_phones'),['invoice_id'=>$invoice_id,'normalized_phone'=>$phone,'phone_position'=>$idx+1,'created_at'=>$now]);
        $wpdb->delete(Biawin_CRM_DB::table('invoice_items'),['invoice_id'=>$invoice_id]);
        foreach($invoice['products']??[] as $product)$wpdb->query($wpdb->prepare("INSERT INTO ".Biawin_CRM_DB::table('invoice_items')." (invoice_id,product_name,quantity,created_at) VALUES (%d,%s,%f,%s) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)",$invoice_id,$product['name'],$product['quantity'],$now));
        $wpdb->delete(Biawin_CRM_DB::table('services'),['invoice_id'=>$invoice_id]);
        foreach(array_unique($invoice['services']??[]) as $service)$wpdb->insert(Biawin_CRM_DB::table('services'),['invoice_id'=>$invoice_id,'customer_id'=>$customer_id,'service_name'=>$service,'source_import_id'=>$import_id,'created_at'=>$now]);
        $wpdb->query($wpdb->prepare("DELETE FROM ".Biawin_CRM_DB::table('payments')." WHERE invoice_id=%d AND source_import_id IS NOT NULL",$invoice_id));
        if(!empty($invoice['amount_paid']))$wpdb->insert(Biawin_CRM_DB::table('payments'),['invoice_id'=>$invoice_id,'amount'=>$invoice['amount_paid'],'payment_info'=>$invoice['payment_info']??'','source_import_id'=>$import_id,'created_at'=>$now]);
        Biawin_CRM_Customer_Service::recalculate_customer($customer_id);
        self::recalculate_assignment_cohort($customer_id);
        if($source_customer_id && $source_customer_id!==$customer_id){
            Biawin_CRM_Customer_Service::recalculate_customer($source_customer_id);
            self::recalculate_assignment_cohort($source_customer_id);
            Biawin_CRM_Customer_Service::collapse_empty_profile($source_customer_id,$customer_id,'بازآرایی پروفایل بر اساس CustomerID فایل');
        }
    }

    private static function complete_job($job): void
    {
        global $wpdb; $now=Biawin_CRM_DB::now();
        $mapping=json_decode((string)$job->mapping_json,true)?:[];
        if(!empty($mapping['__fill_customer_id_gaps'])) self::create_missing_source_profiles((string)$job->job_token,$now);
        $wpdb->update(Biawin_CRM_DB::table('import_jobs'),['phase'=>'done','status'=>'completed','updated_at'=>$now],['id'=>(int)$job->id]);
        if(!empty($job->import_id))$wpdb->update(Biawin_CRM_DB::table('imports'),['status'=>'committed','committed_at'=>$now],['id'=>(int)$job->import_id]);
        self::cleanup_job_files($job);
    }

    private static function create_missing_source_profiles(string $job_token,string $now): void
    {
        global $wpdb;
        $stage=Biawin_CRM_DB::table('import_stage_invoices');
        $customers=Biawin_CRM_DB::table('customers');
        $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT CAST(source_customer_id AS UNSIGNED) sid FROM $stage WHERE job_token=%s AND source_customer_id REGEXP '^[0-9]+$' AND CAST(source_customer_id AS UNSIGNED)>0 ORDER BY sid",$job_token));
        $nums=array_values(array_unique(array_map('intval',$ids))); if(!$nums)return;
        $max=max($nums); if($max<=0 || $max>1000000)return;
        $present=array_fill_keys($nums,true);
        for($i=1;$i<=$max;$i++){
            if(isset($present[$i]))continue;
            $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customers WHERE source_customer_id=%s LIMIT 1",(string)$i));
            if($exists)continue;
            $wpdb->insert($customers,['internal_uuid'=>wp_generate_uuid4(),'source_customer_id'=>(string)$i,'display_name'=>null,'normalized_name'=>null,'status'=>'source_only','total_paid'=>0,'created_at'=>$now,'updated_at'=>$now]);
        }
    }

    public static function cancel_job(string $job_token): bool
    {
        global $wpdb; $job=self::get_job($job_token); if(!$job || (int)$job->created_by!==get_current_user_id())return false;
        self::cleanup_job_files($job);
        $wpdb->delete(Biawin_CRM_DB::table('import_stage_phones'),['job_token'=>$job_token]);
        $wpdb->delete(Biawin_CRM_DB::table('import_stage_invoices'),['job_token'=>$job_token]);
        $wpdb->update(Biawin_CRM_DB::table('import_jobs'),['status'=>'cancelled','phase'=>'cancelled','updated_at'=>Biawin_CRM_DB::now()],['id'=>(int)$job->id]); return true;
    }

    private static function cleanup_job_files($job): void
    {
        if(!empty($job->normalized_csv_path) && is_file($job->normalized_csv_path))@unlink($job->normalized_csv_path);
        if(!empty($job->source_path) && is_file($job->source_path))@unlink($job->source_path);
        if(!empty($job->upload_token)){delete_user_meta((int)$job->created_by,'bcrm_upload_'.$job->upload_token);delete_transient('bcrm_upload_'.(int)$job->created_by.'_'.$job->upload_token);}
    }

    private static function get_job(string $job_token)
    {
        global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM ".Biawin_CRM_DB::table('import_jobs')." WHERE job_token=%s",$job_token));
    }

}
