<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Finance approval reporting (read-only).
 *
 * Reports deficiency cases that were sent to Finance and their bank receipts,
 * including per-receipt review state and invoice numbers entered by Finance.
 */
class Biawin_CRM_Finance_Report
{
    private const PAGE = 'biawin-crm-finance-report';
    private const EXPORT_ACTION = 'bcrm_finance_report_export';
    private const NONCE = 'bcrm_finance_report_export';
    private const KNOWN_STATUSES = ['approved', 'rejected', 'refunded'];

    public static function allowed(): bool
    {
        return current_user_can('view_finance_reports') || current_user_can('bcrm_reports') || current_user_can('bcrm_manage_cases');
    }

    public static function menu_capability(): string
    {
        // Keep the menu scoped to finance reporting. Site administrators bypass caps as usual.
        return 'view_finance_reports';
    }

    public static function page_slug(): string
    {
        return self::PAGE;
    }

    public static function register_hooks(): void
    {
        add_action('admin_post_' . self::EXPORT_ACTION, [__CLASS__, 'export']);
    }

    private static function filters(): array
    {
        $date = static function ($v): string {
            $v = sanitize_text_field(wp_unslash((string)$v));
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        };
        $status = sanitize_key(wp_unslash((string)($_GET['fin_status'] ?? '')));
        if (!in_array($status, ['', 'pending', 'approved', 'rejected', 'refunded', 'other'], true)) $status = '';
        $type = sanitize_key(wp_unslash((string)($_GET['fin_type'] ?? '')));
        if (!in_array($type, ['', 'with_invoice', 'without_invoice', 'registered', 'unregistered'], true)) $type = '';

        return [
            'sent_from'   => $date($_GET['sent_from'] ?? ''),
            'sent_to'     => $date($_GET['sent_to'] ?? ''),
            'review_from' => $date($_GET['review_from'] ?? ''),
            'review_to'   => $date($_GET['review_to'] ?? ''),
            'status'      => $status,
            'type'        => $type,
            'finance_agent_id' => max(0, (int)($_GET['finance_agent_id'] ?? 0)),
            'reviewer_id'      => max(0, (int)($_GET['reviewer_id'] ?? 0)),
            'q' => sanitize_text_field(wp_unslash((string)($_GET['q'] ?? ''))),
        ];
    }

    private static function build_where(array $f): array
    {
        global $wpdb;
        $where = ["dc.sent_to_finance_at IS NOT NULL", "dc.sent_to_finance_at<>''"];
        $args = [];

        if ($f['sent_from'] !== '') { $where[] = 'dc.sent_to_finance_at >= %s'; $args[] = $f['sent_from'] . ' 00:00:00'; }
        if ($f['sent_to'] !== '') { $where[] = 'dc.sent_to_finance_at <= %s'; $args[] = $f['sent_to'] . ' 23:59:59'; }
        if ($f['review_from'] !== '') { $where[] = 'rr.reviewed_at >= %s'; $args[] = $f['review_from'] . ' 00:00:00'; }
        if ($f['review_to'] !== '') { $where[] = 'rr.reviewed_at <= %s'; $args[] = $f['review_to'] . ' 23:59:59'; }
        if ($f['finance_agent_id'] > 0) { $where[] = 'dc.finance_agent_id=%d'; $args[] = $f['finance_agent_id']; }
        if ($f['reviewer_id'] > 0) { $where[] = 'rr.reviewed_by=%d'; $args[] = $f['reviewer_id']; }
        if ($f['type'] !== '') { $where[] = 'rr.review_type=%s'; $args[] = $f['type']; }

        if ($f['status'] === 'pending') {
            $where[] = '(a.id IS NOT NULL AND (rr.review_status IS NULL OR rr.review_status=%s))';
            $args[] = 'pending';
        } elseif (in_array($f['status'], self::KNOWN_STATUSES, true)) {
            $where[] = 'rr.review_status=%s';
            $args[] = $f['status'];
        } elseif ($f['status'] === 'other') {
            $where[] = "a.id IS NOT NULL AND rr.review_status IS NOT NULL AND rr.review_status<>'' AND rr.review_status NOT IN ('approved','rejected','refunded','pending')";
        }

        if ($f['q'] !== '') {
            $like = '%' . $wpdb->esc_like($f['q']) . '%';
            $where[] = '(c.display_name LIKE %s OR c.customer_code LIKE %s OR c.source_customer_id LIKE %s OR dc.case_code LIKE %s OR rr.details_json LIKE %s)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $args];
    }

    private static function base_sql(string $where): string
    {
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $cases = Biawin_CRM_DB::table('deficiency_cases');
        $attachments = Biawin_CRM_DB::table('attachments');
        $reviews = Biawin_CRM_DB::table('receipt_reviews');
        $users = $wpdb->users;

        return "SELECT
            c.id customer_internal_id,c.customer_code,c.source_customer_id,c.display_name customer_name,
            dc.id case_id,dc.case_code,dc.status case_status,dc.current_department,
            dc.reported_amount,dc.registered_invoice_amount,dc.difference_amount,dc.final_approved_amount,
            dc.sent_to_finance_at,dc.finance_assigned_at,dc.finance_finished_at,dc.finance_agent_id,
            fa.display_name finance_agent_name,
            a.id attachment_id,a.wp_attachment_id,a.note receipt_note,a.created_at receipt_created_at,a.uploaded_by,
            rr.review_status,rr.review_type,rr.reason_key,rr.approved_amount,rr.review_note,rr.details_json,rr.reviewed_by,rr.reviewed_at,
            reviewer.display_name reviewer_name
        FROM {$cases} dc
        INNER JOIN {$customers} c ON c.id=dc.customer_id
        LEFT JOIN {$attachments} a ON a.object_type='deficiency_case' AND a.object_id=dc.id AND a.file_kind='bank_receipt'
        LEFT JOIN {$reviews} rr ON rr.attachment_id=a.id AND rr.case_id=dc.id
        LEFT JOIN {$users} fa ON fa.ID=dc.finance_agent_id
        LEFT JOIN {$users} reviewer ON reviewer.ID=rr.reviewed_by
        WHERE {$where}";
    }

    private static function query_rows(array $filters): array
    {
        global $wpdb;
        [$where, $args] = self::build_where($filters);
        $sql = self::base_sql($where) . ' ORDER BY c.id ASC,dc.sent_to_finance_at ASC,dc.id ASC,a.id ASC';
        if ($args) $sql = $wpdb->prepare($sql, $args);
        return (array)$wpdb->get_results($sql);
    }

    private static function details(?string $json): array
    {
        if (!$json) return [];
        $v = json_decode($json, true);
        return is_array($v) ? $v : [];
    }

    private static function invoice_numbers(array $details, string $status): array
    {
        $out = [];
        if (!empty($details['invoice_number'])) $out[] = (string)$details['invoice_number'];
        $keys = $status === 'refunded' ? ['invoice_links', 'refund_links'] : ['invoice_links'];
        foreach ($keys as $key) {
            if (empty($details[$key]) || !is_array($details[$key])) continue;
            foreach ($details[$key] as $link) {
                if (is_array($link) && !empty($link['invoice_number'])) $out[] = (string)$link['invoice_number'];
            }
        }
        return array_values(array_unique(array_filter($out, 'strlen')));
    }

    private static function receipt_amount(string $note): ?float
    {
        if (preg_match('/مبلغ فیش:\s*([\d\x{06F0}-\x{06F9}]+(?:[.,،][\d\x{06F0}-\x{06F9}]+)*)/u', $note, $m)) {
            $v = Biawin_CRM_Normalizer::money($m[1]);
            return $v > 0 ? (float)$v : null;
        }
        return null;
    }

    private static function status_label(string $status): string
    {
        return [''=>'بررسی‌نشده','pending'=>'بررسی‌نشده','approved'=>'تأیید شده','rejected'=>'عدم تأیید','refunded'=>'عودت شده'][$status] ?? $status;
    }

    private static function type_label(string $type): string
    {
        return ['with_invoice'=>'تأیید با فاکتور','without_invoice'=>'تأیید بدون فاکتور','registered'=>'عودت ثبت شده','unregistered'=>'عودت ثبت نشده'][$type] ?? $type;
    }

    private static function reason_label(string $key): string
    {
        $map = [];
        if (class_exists('Biawin_CRM_Finance_Review')) {
            foreach ((array)Biawin_CRM_Finance_Review::financial_option_groups()['reject_reasons'] as $item) {
                if (!empty($item['key'])) $map[(string)$item['key']] = (string)($item['label'] ?? $item['key']);
            }
        }
        return $map[$key] ?? $key;
    }

    private static function aggregate(array $rows): array
    {
        $profiles = [];
        foreach ($rows as $r) {
            $id = (int)$r->customer_internal_id;
            if (!isset($profiles[$id])) {
                $profiles[$id] = [
                    'id'=>$id,'customer_code'=>(string)$r->customer_code,'source_customer_id'=>(string)$r->source_customer_id,'name'=>(string)$r->customer_name,
                    'cases'=>[],'case_codes'=>[],'receipts'=>0,'reviewed'=>0,'approved'=>0,'rejected'=>0,'refunded'=>0,'other'=>0,
                    'with_invoice'=>0,'without_invoice'=>0,'refund_registered'=>0,'refund_unregistered'=>0,'approved_amount'=>0.0,
                    'invoices'=>[],'reasons'=>[],'agents'=>[],'sent_dates'=>[],
                ];
            }
            $p =& $profiles[$id];
            $p['cases'][(int)$r->case_id] = true;
            $p['case_codes'][(string)$r->case_code] = true;
            if (!empty($r->sent_to_finance_at)) $p['sent_dates'][] = (string)$r->sent_to_finance_at;
            if (!empty($r->finance_agent_name)) $p['agents'][(string)$r->finance_agent_name] = true;
            if (!$r->attachment_id) { unset($p); continue; }
            $p['receipts']++;
            $st = (string)($r->review_status ?? '');
            if ($st !== '' && $st !== 'pending') $p['reviewed']++;
            if ($st === 'approved') {
                $p['approved']++; $p['approved_amount'] += (float)$r->approved_amount;
                if ((string)$r->review_type === 'with_invoice') $p['with_invoice']++;
                if ((string)$r->review_type === 'without_invoice') $p['without_invoice']++;
                foreach (self::invoice_numbers(self::details((string)$r->details_json), $st) as $inv) $p['invoices'][$inv] = true;
            } elseif ($st === 'rejected') {
                $p['rejected']++;
                $reason = self::reason_label((string)$r->reason_key);
                if ($reason !== '') $p['reasons'][$reason] = true;
            } elseif ($st === 'refunded') {
                $p['refunded']++;
                if ((string)$r->review_type === 'registered') $p['refund_registered']++;
                if ((string)$r->review_type === 'unregistered') $p['refund_unregistered']++;
            } elseif ($st !== '' && $st !== 'pending') $p['other']++;
            unset($p);
        }
        return $profiles;
    }

    private static function totals(array $rows): array
    {
        $t = ['profiles'=>0,'cases'=>0,'receipts'=>0,'reviewed'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'refunded'=>0,'other'=>0,'approved_amount'=>0.0];
        $profiles=[];$cases=[];
        foreach ($rows as $r) {
            $profiles[(int)$r->customer_internal_id]=1; $cases[(int)$r->case_id]=1;
            if (!$r->attachment_id) continue;
            $t['receipts']++;
            $st=(string)($r->review_status ?? '');
            if ($st==='' || $st==='pending') $t['pending']++;
            else { $t['reviewed']++; if (isset($t[$st])) $t[$st]++; else $t['other']++; }
            if ($st==='approved') $t['approved_amount'] += (float)$r->approved_amount;
        }
        $t['profiles']=count($profiles);$t['cases']=count($cases); return $t;
    }

    private static function user_options(array $rows, string $id_field, string $name_field): array
    {
        $out=[];
        foreach($rows as $r){$id=(int)($r->{$id_field}??0);$name=(string)($r->{$name_field}??'');if($id>0&&$name!=='')$out[$id]=$name;}
        asort($out,SORT_NATURAL|SORT_FLAG_CASE);return $out;
    }

    public static function page(): void
    {
        if (!self::allowed()) wp_die('دسترسی غیرمجاز است.');
        if (!self::table_exists(Biawin_CRM_DB::table('receipt_reviews'))) {
            echo '<div class="wrap bcrm" dir="rtl"><h1>گزارش تأیید مالی</h1><div class="notice notice-error"><p>جدول بررسی فیش‌ها در دیتابیس موجود نیست.</p></div></div>'; return;
        }
        $filters=self::filters();$rows=self::query_rows($filters);$profiles=self::aggregate($rows);$totals=self::totals($rows);
        $all_agents=self::all_finance_users();
        $view=sanitize_key((string)($_GET['view']??'profiles')); if(!in_array($view,['profiles','receipts','approved','rejected','other'],true))$view='profiles';

        echo '<div class="wrap bcrm bcrm-fin-report" dir="rtl"><h1>گزارش تأیید مالی</h1>';
        echo '<p>گزارش فقط‌خواندنی پرونده‌هایی که به تأیید مالی ارسال شده‌اند؛ شامل وضعیت تک‌تک فیش‌ها و کد فاکتورهای ثبت‌شده توسط تأیید مالی.</p>';
        self::render_filters($filters,$all_agents);
        self::render_metrics($totals);
        $tabs=['profiles'=>'خلاصه پروفایل‌ها','receipts'=>'جزئیات فیش‌ها','approved'=>'فاکتورهای تأییدشده','rejected'=>'عدم تأییدها','other'=>'سایر وضعیت‌ها'];
        echo '<nav class="nav-tab-wrapper" style="margin:18px 0">'; foreach($tabs as $k=>$label){$url=add_query_arg(array_merge(self::query_args($filters),['page'=>self::PAGE,'view'=>$k]),admin_url('admin.php'));echo '<a class="nav-tab '.($view===$k?'nav-tab-active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';} echo '</nav>';
        $paged=max(1,(int)($_GET['paged']??1));
        if($view==='profiles')self::render_profiles($profiles,$filters,$view,$paged); else self::render_receipts($rows,$view,$filters,$paged);
        echo '</div>';
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb; return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table;
    }

    private static function all_finance_users(): array
    {
        global $wpdb; $cases=Biawin_CRM_DB::table('deficiency_cases');$reviews=Biawin_CRM_DB::table('receipt_reviews');
        $ids=(array)$wpdb->get_col("SELECT DISTINCT finance_agent_id FROM {$cases} WHERE finance_agent_id IS NOT NULL UNION SELECT DISTINCT reviewed_by FROM {$reviews} WHERE reviewed_by IS NOT NULL");
        $out=[]; foreach($ids as $id){$u=get_user_by('id',(int)$id);if($u)$out[(int)$id]=$u->display_name;} asort($out,SORT_NATURAL|SORT_FLAG_CASE);return $out;
    }

    private static function query_args(array $f): array
    {
        $a=[];foreach(['sent_from','sent_to','review_from','review_to','status','type','q'] as $k)if($f[$k]!=='')$a[$k==='status'?'fin_status':($k==='type'?'fin_type':$k)]=$f[$k];
        if($f['finance_agent_id'])$a['finance_agent_id']=$f['finance_agent_id'];if($f['reviewer_id'])$a['reviewer_id']=$f['reviewer_id'];return $a;
    }

    private static function render_filters(array $f,array $users): void
    {
        echo '<form method="get" style="background:#fff;border:1px solid #dcdcde;padding:14px;margin:14px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:end">';
        echo '<input type="hidden" name="page" value="'.esc_attr(self::PAGE).'">';
        $field=static function($label,$name,$value,$type='date'){echo '<label style="display:flex;flex-direction:column;gap:4px"><span>'.esc_html($label).'</span><input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" style="min-width:145px"></label>';};
        $field('ارسال مالی از','sent_from',$f['sent_from']);$field('ارسال مالی تا','sent_to',$f['sent_to']);$field('بررسی از','review_from',$f['review_from']);$field('بررسی تا','review_to',$f['review_to']);
        echo '<label><span style="display:block;margin-bottom:4px">وضعیت فیش</span><select name="fin_status"><option value="">همه</option>';foreach(['pending'=>'بررسی‌نشده','approved'=>'تأیید شده','rejected'=>'عدم تأیید','refunded'=>'عودت شده','other'=>'سایر'] as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($f['status'],$k,false).'>'.esc_html($v).'</option>';echo '</select></label>';
        echo '<label><span style="display:block;margin-bottom:4px">نوع نتیجه</span><select name="fin_type"><option value="">همه</option>';foreach(['with_invoice'=>'تأیید با فاکتور','without_invoice'=>'تأیید بدون فاکتور','registered'=>'عودت ثبت شده','unregistered'=>'عودت ثبت نشده'] as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($f['type'],$k,false).'>'.esc_html($v).'</option>';echo '</select></label>';
        foreach([['finance_agent_id','کارشناس مالی',$f['finance_agent_id']],['reviewer_id','بررسی‌کننده',$f['reviewer_id']]] as $u){echo '<label><span style="display:block;margin-bottom:4px">'.esc_html($u[1]).'</span><select name="'.esc_attr($u[0]).'"><option value="0">همه</option>';foreach($users as $id=>$name)echo '<option value="'.(int)$id.'" '.selected((int)$u[2],(int)$id,false).'>'.esc_html($name).'</option>';echo '</select></label>';}
        echo '<label style="flex:1;min-width:220px"><span style="display:block;margin-bottom:4px">جستجو</span><input type="search" name="q" value="'.esc_attr($f['q']).'" placeholder="نام، کد مشتری، Case یا کد فاکتور" style="width:100%"></label>';
        submit_button('اعمال فیلتر','primary','',false);echo ' <a class="button" href="'.esc_url(admin_url('admin.php?page='.self::PAGE)).'">پاک کردن</a>';
        echo '</form>';
        $export=add_query_arg(array_merge(self::query_args($f),['action'=>self::EXPORT_ACTION,'_wpnonce'=>wp_create_nonce(self::NONCE)]),admin_url('admin-post.php'));
        echo '<p><a class="button button-primary" href="'.esc_url($export).'">خروجی Excel همین فیلترها</a></p>';
    }

    private static function render_metrics(array $t): void
    {
        $items=['پروفایل'=>$t['profiles'],'پرونده'=>$t['cases'],'کل فیش'=>$t['receipts'],'بررسی‌شده'=>$t['reviewed'],'بررسی‌نشده'=>$t['pending'],'تأیید'=>$t['approved'],'عدم تأیید'=>$t['rejected'],'عودت'=>$t['refunded'],'مبلغ تأییدشده'=>number_format_i18n($t['approved_amount'])];
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:12px 0">';foreach($items as $k=>$v)echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:7px;padding:12px"><div style="color:#646970;font-size:12px">'.esc_html($k).'</div><strong style="font-size:20px">'.esc_html((string)$v).'</strong></div>';echo '</div>';
    }

    private static function pagination(int $total,int $paged,int $per_page,array $filters,string $view): void
    {
        $pages=max(1,(int)ceil($total/$per_page));if($pages<=1)return;
        $base=add_query_arg(array_merge(self::query_args($filters),['page'=>self::PAGE,'view'=>$view,'paged'=>'%#%']),admin_url('admin.php'));
        echo '<div class="tablenav"><div class="tablenav-pages">'.paginate_links(['base'=>$base,'format'=>'','current'=>$paged,'total'=>$pages,'prev_text'=>'‹','next_text'=>'›']).'</div></div>';
    }

    private static function render_profiles(array $profiles,array $filters,string $view,int $paged): void
    {
        $per_page=100;$profiles=array_values($profiles);$total=count($profiles);$slice=array_slice($profiles,($paged-1)*$per_page,$per_page);
        echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>مشتری</th><th>کد/Source</th><th>پرونده‌ها</th><th>کل فیش</th><th>بررسی‌شده</th><th>بررسی‌نشده</th><th>تأیید</th><th>عدم تأیید</th><th>عودت</th><th>با فاکتور</th><th>بدون فاکتور</th><th>مبلغ تأییدشده</th><th>کد فاکتورهای تأییدشده</th><th>دلایل عدم تأیید</th><th>کارشناس مالی</th></tr></thead><tbody>';
        if(!$slice)echo '<tr><td colspan="15">موردی پیدا نشد.</td></tr>';
        foreach($slice as $p){$pending=$p['receipts']-$p['reviewed'];echo '<tr><td><strong>'.esc_html($p['name']).'</strong><br><small>#'.(int)$p['id'].'</small></td><td>'.esc_html($p['source_customer_id']?:$p['customer_code']).'</td><td>'.esc_html(implode('، ',array_keys($p['case_codes']))).'</td><td>'.(int)$p['receipts'].'</td><td>'.(int)$p['reviewed'].'</td><td>'.(int)$pending.'</td><td>'.(int)$p['approved'].'</td><td>'.(int)$p['rejected'].'</td><td>'.(int)$p['refunded'].'</td><td>'.(int)$p['with_invoice'].'</td><td>'.(int)$p['without_invoice'].'</td><td>'.esc_html(number_format_i18n($p['approved_amount'])).'</td><td>'.esc_html(implode('، ',array_keys($p['invoices']))?:'—').'</td><td>'.esc_html(implode('، ',array_keys($p['reasons']))?:'—').'</td><td>'.esc_html(implode('، ',array_keys($p['agents']))?:'—').'</td></tr>';}
        echo '</tbody></table></div>';self::pagination($total,$paged,$per_page,$filters,$view);
    }

    private static function render_receipts(array $rows,string $view,array $filters,int $paged): void
    {
        $filtered=[];foreach($rows as $r){if(!$r->attachment_id)continue;$st=(string)($r->review_status??'');if($view==='approved'&&($st!=='approved'||(string)$r->review_type!=='with_invoice'))continue;if($view==='rejected'&&$st!=='rejected')continue;if($view==='other'&&in_array($st,['','pending','approved','rejected'],true))continue;$filtered[]=$r;}
        $per_page=100;$total=count($filtered);$slice=array_slice($filtered,($paged-1)*$per_page,$per_page);
        echo '<div style="overflow:auto"><table class="widefat striped"><thead><tr><th>مشتری</th><th>Case</th><th>فیش</th><th>تاریخ ارسال</th><th>وضعیت</th><th>نوع</th><th>مبلغ فیش</th><th>مبلغ تأیید</th><th>کد فاکتور</th><th>علت</th><th>بررسی‌کننده</th><th>تاریخ بررسی</th></tr></thead><tbody>';
        $shown=0;foreach($slice as $r){$st=(string)($r->review_status??'');$d=self::details((string)$r->details_json);$invs=self::invoice_numbers($d,$st);echo '<tr><td>'.esc_html($r->customer_name).'<br><small>'.esc_html($r->source_customer_id?:$r->customer_code).'</small></td><td>'.esc_html($r->case_code).'</td><td>#'.(int)$r->attachment_id.'</td><td>'.esc_html((string)$r->receipt_created_at).'</td><td>'.esc_html(self::status_label($st)).'</td><td>'.esc_html(self::type_label((string)$r->review_type)).'</td><td>'.esc_html(number_format_i18n((float)(self::receipt_amount((string)$r->receipt_note)??0))).'</td><td>'.esc_html($r->approved_amount!==null?number_format_i18n((float)$r->approved_amount):'—').'</td><td>'.esc_html(implode('، ',$invs)?:'—').'</td><td>'.esc_html(self::reason_label((string)$r->reason_key)?:'—').'</td><td>'.esc_html($r->reviewer_name?:'—').'</td><td>'.esc_html($r->reviewed_at?:'—').'</td></tr>';$shown++;}
        if(!$shown)echo '<tr><td colspan="12">موردی پیدا نشد.</td></tr>';echo '</tbody></table></div>';self::pagination($total,$paged,$per_page,$filters,$view);
    }

    public static function export(): void
    {
        if(!self::allowed())wp_die('دسترسی غیرمجاز است.');check_admin_referer(self::NONCE);
        $filters=self::filters();$rows=self::query_rows($filters);$profiles=self::aggregate($rows);
        $profile_headers=['Customer ID','کد مشتری','Source Customer ID','نام مشتری','Caseها','تعداد فیش','بررسی‌شده','بررسی‌نشده','تأیید','عدم تأیید','عودت','سایر','تأیید با فاکتور','تأیید بدون فاکتور','عودت ثبت‌شده','عودت ثبت‌نشده','مجموع مبلغ تأییدشده','کد فاکتورهای تأییدشده','دلایل عدم تأیید','کارشناس مالی','اولین ارسال مالی','آخرین ارسال مالی'];
        $profile_rows=[];foreach($profiles as $p){sort($p['sent_dates']);$profile_rows[]=[(int)$p['id'],$p['customer_code'],$p['source_customer_id'],$p['name'],implode(' | ',array_keys($p['case_codes'])),(int)$p['receipts'],(int)$p['reviewed'],(int)($p['receipts']-$p['reviewed']),(int)$p['approved'],(int)$p['rejected'],(int)$p['refunded'],(int)$p['other'],(int)$p['with_invoice'],(int)$p['without_invoice'],(int)$p['refund_registered'],(int)$p['refund_unregistered'],(float)$p['approved_amount'],implode(' | ',array_keys($p['invoices'])),implode(' | ',array_keys($p['reasons'])),implode(' | ',array_keys($p['agents'])),$p['sent_dates'][0]??'',!empty($p['sent_dates'])?$p['sent_dates'][count($p['sent_dates'])-1]:''];}
        $detail_headers=['Customer ID','کد مشتری','Source Customer ID','نام مشتری','Case ID','کد پرونده','وضعیت پرونده','واحد فعلی','تاریخ ارسال مالی','Attachment ID','تاریخ ارسال فیش','مبلغ فیش','وضعیت بررسی','نوع نتیجه','Reason Key','شرح علت','مبلغ تأییدشده','کد فاکتور/فاکتورها','مبلغ فاکتور در نتیجه','تاریخ فاکتور','شماره مشتری','بررسی‌کننده','تاریخ بررسی','کارشناس مالی','یادداشت'];
        $detail=[];$approved=[];$rejected=[];$other=[];
        foreach($rows as $r){if(!$r->attachment_id)continue;$st=(string)($r->review_status??'');$d=self::details((string)$r->details_json);$invs=implode(' | ',self::invoice_numbers($d,$st));$row=[(int)$r->customer_internal_id,(string)$r->customer_code,(string)$r->source_customer_id,(string)$r->customer_name,(int)$r->case_id,(string)$r->case_code,(string)$r->case_status,(string)$r->current_department,(string)$r->sent_to_finance_at,(int)$r->attachment_id,(string)$r->receipt_created_at,self::receipt_amount((string)$r->receipt_note)??'',self::status_label($st),self::type_label((string)$r->review_type),(string)$r->reason_key,self::reason_label((string)$r->reason_key),$r->approved_amount===null?'':(float)$r->approved_amount,$invs,isset($d['invoice_amount'])?(float)$d['invoice_amount']:'',(string)($d['invoice_date']??''),(string)($d['customer_phone']??''),(string)$r->reviewer_name,(string)$r->reviewed_at,(string)$r->finance_agent_name,(string)$r->review_note];$detail[]=$row;if($st==='approved'&&(string)$r->review_type==='with_invoice')$approved[]=$row;elseif($st==='rejected')$rejected[]=$row;elseif($st!==''&&!in_array($st,['approved','rejected'],true))$other[]=$row;}
        self::xlsx('biawin-finance-approval-report-'.gmdate('Ymd-His').'.xlsx',[
            ['name'=>'خلاصه پروفایل','headers'=>$profile_headers,'rows'=>$profile_rows],
            ['name'=>'جزئیات فیش‌ها','headers'=>$detail_headers,'rows'=>$detail],
            ['name'=>'فاکتورهای تأییدشده','headers'=>$detail_headers,'rows'=>$approved],
            ['name'=>'عدم تأییدها','headers'=>$detail_headers,'rows'=>$rejected],
            ['name'=>'سایر وضعیت‌ها','headers'=>$detail_headers,'rows'=>$other],
        ]);
    }

    private static function xlsx(string $file_name,array $sheets): void
    {
        $esc=static function($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');};
        $col=static function(int $i):string{$s='';for(++$i;$i>0;$i=intdiv($i-1,26))$s=chr(65+($i-1)%26).$s;return $s;};
        $sheet_xml=static function(array $headers,array $rows)use($esc,$col):string{$row_xml=static function(int $rn,array $cells)use($esc,$col):string{$x='<row r="'.$rn.'">';foreach($cells as $i=>$v){$ref=$col($i).$rn;if(is_int($v)||is_float($v))$x.='<c r="'.$ref.'"><v>'.$esc($v).'</v></c>';else$x.='<c r="'.$ref.'" t="inlineStr"><is><t>'.$esc($v).'</t></is></c>';}return $x.'</row>';};$xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$row_xml(1,$headers);$rn=1;foreach($rows as $row)$xml.=$row_xml(++$rn,array_values($row));return $xml.'</sheetData></worksheet>';};
        $safe=static function(string $name,int $idx):string{$name=trim(preg_replace('/[\\[\\]\\:\\*\\?\/\\\\]/',' ',$name));if($name==='')$name='Sheet'.$idx;return mb_substr($name,0,31);};
        $ct='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';$wbs='';$wbr='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';$parts=[];
        foreach($sheets as $i=>$s){$n=$i+1;$ct.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';$wbs.='<sheet name="'.$esc($safe((string)$s['name'],$n)).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';$wbr.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';$parts['xl/worksheets/sheet'.$n.'.xml']=$sheet_xml((array)$s['headers'],(array)$s['rows']);}
        $ct.='</Types>';$rels='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';$wb='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$wbs.'</sheets></workbook>';$wbr.='</Relationships>';
        $parts=['[Content_Types].xml'=>$ct,'_rels/.rels'=>$rels,'xl/workbook.xml'=>$wb,'xl/_rels/workbook.xml.rels'=>$wbr]+$parts;
        Biawin_CRM_File_Safety::quiet_output();Biawin_CRM_File_Safety::sweep_stale();$dir=Biawin_CRM_File_Safety::make_temp_dir('finance-report-xlsx');$xlsx=$dir!==''?Biawin_CRM_File_Safety::build_zip($dir,'report.xlsx',$parts):'';if($xlsx===''){if($dir!=='')Biawin_CRM_File_Safety::remove($dir);wp_die('ساخت فایل اکسل گزارش ممکن نشد.','خروجی گزارش',['response'=>503]);}Biawin_CRM_File_Safety::send_file($xlsx,$file_name,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');Biawin_CRM_File_Safety::remove($dir);exit;
    }
}
