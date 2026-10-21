<?php
if (!defined('ABSPATH')) { exit; }
/** One write boundary for existing plugin mutations, including raw import SQL. */
class Biawin_CRM_Ownership_Write_Guard
{
    public static function protect_media($delete, $post, $force_delete) {
        if (version_compare((string)get_option('biawin_crm_db_version',''),'0.8.65.19','<')) return $delete;
        global $wpdb;
        $links = Biawin_CRM_Ownership_Repository::rows($wpdb->prepare('SELECT * FROM ' . Biawin_CRM_DB::table('attachments') . ' WHERE wp_attachment_id=%d',(int)$post->ID));
        foreach ($links as $link) {
            if ($link->object_type === 'ownership_case') return false;
            Biawin_CRM_Customer_Hold_Service::assert_mutable((int)$link->customer_id);
        }
        return $delete;
    }
    private static function logical(string $table): string {
        global $wpdb;
        $prefix = $wpdb->prefix . 'bcrm_';
        $table = trim($table,'`');
        return strpos($table,$prefix) === 0 ? substr($table,strlen($prefix)) : '';
    }
    private static function protected_table(string $name): bool {
        return in_array($name,['customers','invoices','phones','invoice_phones','invoice_items','payments','services','interactions','assignments','complaints','complaint_inquiries','financial_cases','service_cases','refunds','refund_registers','attachments','activities','deficiency_cases','case_actions','customer_tags','merge_events','receipt_reviews'],true) || strpos($name,'ownership_') === 0 || $name === 'customer_holds';
    }
    public static function write(string $method, array $args) {
        global $wpdb;
        if ($method === 'query') {
            $sql = ltrim($args[0]);
            if (preg_match('/^(START TRANSACTION|BEGIN|COMMIT|ROLLBACK)\\s*;?\\s*$/i',$sql)) return Biawin_CRM_Ownership_Repository::legacy_transaction($args[0]);
            if (!preg_match('/^(?:UPDATE|DELETE|INSERT|REPLACE|TRUNCATE|DROP)\b/i',$sql)) return $wpdb->query(...$args);
            if (!preg_match('/^(?:(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO|UPDATE|DELETE\s+FROM|TRUNCATE\s+(?:TABLE\s+)?|DROP\s+TABLE\s+)\s*(`?\w+`?)/i',$sql,$match)) {
                throw new RuntimeException('Unsupported CRM mutation form.');
            }
            $table = trim($match[1],'`');
        } else $table = $args[0];
        $name = self::logical($table);
        if (!self::protected_table($name)) return $wpdb->$method(...$args);
        // A plain INSERT cannot change an existing profile (duplicate keys fail).
        // No hold can apply to the newly allocated identity before it exists.
        if ($name === 'customers' && $method === 'insert') return $wpdb->insert(...$args);
        return Biawin_CRM_Ownership_Repository::guarded_write(static function () use ($method,$args,$table,$name) {
            global $wpdb;
            if (strpos($name,'ownership_') === 0 || $name === 'customer_holds') throw new RuntimeException('Ownership records must be changed through ownership services.');
            if ($method === 'query') self::raw($args[0],$table,$name);
            else {
                $data = in_array($method,['insert','replace','update'],true) ? $args[1] : [];
                $where = $method === 'update' ? $args[2] : ($method === 'delete' ? $args[1] : []);
                $rows = $where ? self::where_rows($table,$where) : [];
                if (in_array($method,['insert','replace'],true)) $rows = self::upsert_rows($table,$data);
                self::validate($name,$data,$rows,$method === 'delete' || $method === 'replace');
            }
            $result = $wpdb->$method(...$args);
            return $result;
        });
    }
    private static function where_rows(string $table, array $where): array {
        global $wpdb;
        $parts = [];
        foreach ($where as $key=>$value) {
            if (!preg_match('/^\w+$/',$key)) throw new RuntimeException('Invalid mutation key.');
            $parts[] = '`' . $key . '`' . ($value === null ? ' IS NULL' : $wpdb->prepare('=%s',$value));
        }
        return self::current_rows('SELECT * FROM `' . $table . '` WHERE ' . implode(' AND ',$parts));
    }
    /** A legacy transaction may already have a repeatable-read snapshot before its first write. */
    private static function current_rows(string $sql): array {
        return Biawin_CRM_Ownership_Repository::rows($sql . (Biawin_CRM_Ownership_Repository::in_transaction() ? ' FOR UPDATE' : ''));
    }
    private static function upsert_rows(string $table, array $data): array {
        // Include existing identities reached by UNIQUE keys, not only a supplied id.
        $rows = [];
        if (self::logical($table) === 'invoice_phones' && isset($data['invoice_id'],$data['normalized_phone'])) return self::where_rows($table,['invoice_id'=>$data['invoice_id'],'normalized_phone'=>$data['normalized_phone']]);
        foreach (['id','normalized_phone','invoice_code','internal_uuid','source_customer_id','customer_code','attachment_id'] as $key) {
            if (!isset($data[$key]) || $data[$key] === '') continue;
            if ($key === 'normalized_phone' && self::logical($table) !== 'phones') continue;
            if ($key === 'invoice_code' && self::logical($table) !== 'invoices') continue;
            foreach (self::where_rows($table,[$key=>$data[$key]]) as $row) $rows[] = $row;
        }
        return $rows;
    }
    private static function raw(string $sql, string $table, string $name): void {
        global $wpdb;
        if (preg_match('/^(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s*\((.*?)\)\s*VALUES\s*\((.*?)\)\s*(?:ON DUPLICATE KEY UPDATE\s+(.*))?\s*$/is',$sql,$m)) {
            $columns = array_map(static function ($c) { return trim($c," `\t\r\n"); },explode(',',$m[1]));
            // Existing raw writes use one VALUES tuple; let MySQL evaluate literals,
            // including escaped Unicode and commas inside text, without PHP SQL unquoting.
            $values = self::split_values($m[2]);
            if (count($columns) !== count($values)) throw new RuntimeException('Unsupported raw CRM insert.');
            $select = [];
            foreach ($columns as $i=>$column) {
                if (!preg_match('/^\w+$/',$column)) throw new RuntimeException('Invalid insert column.');
                $select[] = $values[$i] . ' AS `' . $column . '`';
            }
            $result = Biawin_CRM_Ownership_Repository::rows('SELECT ' . implode(',',$select));
            $data = (array)$result[0];
            $rows = self::upsert_rows($table,$data);
            if (!empty($m[3]) && stripos($m[3],'auto_match_blocked') !== false && $rows && !empty($rows[0]->auto_match_blocked)) $data['customer_id'] = $rows[0]->customer_id;
            self::validate($name,$data,$rows,stripos($sql,'REPLACE') === 0);
            return;
        }
        if (preg_match('/^UPDATE\s+(.+?)\s+SET\s+(.+?)(?:\s+WHERE\s+(.+))?$/is',$sql,$m)) {
            $header = $m[1];
            $alias = preg_match('/^`?\w+`?\s+(?:AS\s+)?(\w+)\b/i',$header,$a) && !in_array(strtoupper($a[1]),['INNER','LEFT','JOIN'],true) ? $a[1] : '`' . $table . '`';
            $where = $m[3] ?? '1=1';
            $expressions = [];
            $fields = [];
            foreach (self::split_values($m[2]) as $assignment) {
                if (!preg_match('/^(?:`?\\w+`?\\.)?`?(\\w+)`?\\s*=\\s*(.*)$/s',trim($assignment),$set)) throw new RuntimeException('Unsupported raw assignment.');
                $fields[] = $set[1];
                $expressions[] = $set[2] . ' AS `__ownership_new_' . $set[1] . '`';
            }
            $rows = self::current_rows('SELECT ' . $alias . '.*,' . implode(',',$expressions) . ' FROM ' . $header . ' WHERE ' . $where);
            foreach ($rows as $row) {
                $data = [];
                foreach ($fields as $field) {
                    $key = '__ownership_new_' . $field;
                    $data[$field] = $row->$key;
                    unset($row->$key);
                }
                self::validate($name,$data,[$row],false);
            }
            return;
        }
        if (preg_match('/^DELETE\s+FROM\s+`?\w+`?(?:\s+WHERE\s+(.*))?$/is',$sql,$m)) {
            $rows = self::current_rows('SELECT * FROM `' . $table . '` WHERE ' . ($m[1] ?? '1=1'));
            self::validate($name,[],$rows,true); return;
        }
        if (!preg_match('/^(TRUNCATE|DROP)\\b/i',ltrim($sql))) throw new RuntimeException('Unsupported canonical mutation SQL; add an explicit ownership-safe mapping.');
        // No guessing for destructive or previously unseen SQL against canonical tables.
        // Reading affected rows still permits baseline operations on unheld/unlocked data.
        $rows = self::current_rows('SELECT * FROM `' . $table . '`');
        self::validate($name,[],$rows,true);
    }
    private static function split_values(string $text): array {
        $parts = []; $start = 0; $depth = 0; $quote = '';
        for ($i=0,$n=strlen($text);$i<$n;$i++) {
            $c = $text[$i];
            if ($quote !== '') {
                if ($c === "\\") { $i++; continue; }
                if ($c === $quote) { if ($i+1<$n && $text[$i+1] === $quote) $i++; else $quote=''; }
            } elseif ($c === "'" || $c === '"' || $c === '`') $quote=$c;
            elseif ($c === '(') $depth++;
            elseif ($c === ')') $depth--;
            elseif ($c === ',' && !$depth) { $parts[]=substr($text,$start,$i-$start); $start=$i+1; }
        }
        $parts[]=substr($text,$start);
        return $parts;
    }
    private static function validate(string $name, array $data, array $rows, bool $deleting): void {
        global $wpdb;
        foreach (array_merge($rows,[(object)$data]) as $row) {
            if ($name === 'attachments' && ($row->object_type ?? '') === 'ownership_case') throw new RuntimeException('Ownership evidence is append-only through ownership services.');
            $customers = [];
            if ($name === 'customers' && isset($row->id)) $customers[] = (int)$row->id;
            if (isset($row->customer_id)) $customers[] = (int)$row->customer_id;
            if (isset($row->primary_customer_id)) $customers[] = (int)$row->primary_customer_id;
            if (!empty($row->merged_customer_ids)) $customers = array_merge($customers,Biawin_CRM_Ownership_Repository::decode($row->merged_customer_ids));
            if (isset($row->invoice_id)) {
                $invoice = Biawin_CRM_Ownership_Repository::row('invoices',(int)$row->invoice_id,true);
                $customers[] = (int)$invoice->customer_id;
            }
            if (isset($row->case_id) && $name === 'receipt_reviews') {
                $case = Biawin_CRM_Ownership_Repository::row('deficiency_cases',(int)$row->case_id,true);
                $customers[] = (int)$case->customer_id;
            }
            if (isset($row->complaint_id)) {
                $complaint = Biawin_CRM_Ownership_Repository::row('complaints',(int)$row->complaint_id,true);
                $customers[] = (int)$complaint->customer_id;
            }
            foreach ($customers as $customer) Biawin_CRM_Customer_Hold_Service::assert_mutable((int)$customer);
        }
        if ($name === 'invoice_phones') {
            $bindings = $rows ?: [(object)$data];
            foreach ($bindings as $row) {
                $changed = $deleting || !$rows || (isset($data['invoice_id']) && (int)$data['invoice_id'] !== (int)$row->invoice_id) || (isset($data['normalized_phone']) && $data['normalized_phone'] !== $row->normalized_phone);
                if ($changed && !empty($row->invoice_id)) Biawin_CRM_Ownership_Lock_Service::assert_can_change_invoice_binding((int)$row->invoice_id);
                if ($changed && !empty($data['invoice_id'])) Biawin_CRM_Ownership_Lock_Service::assert_can_change_invoice_binding((int)$data['invoice_id']);
            }
        }
        if ($name === 'services' && array_key_exists('customer_id',$data)) foreach (array_merge($rows,[(object)$data]) as $row) {
            if (empty($row->invoice_id)) continue;
            $invoice = Biawin_CRM_Ownership_Repository::row('invoices',(int)$row->invoice_id,true);
            if ((int)$data['customer_id'] !== (int)$invoice->customer_id) Biawin_CRM_Ownership_Lock_Service::assert_can_change_invoice_owner((int)$invoice->id);
        }
        if (in_array($name,['invoices','phones'],true)) foreach ($rows as $row) {
            if ($deleting || (array_key_exists('customer_id',$data) && (int)$row->customer_id !== (int)$data['customer_id']) || ($name === 'phones' && isset($data['normalized_phone']) && $data['normalized_phone'] !== $row->normalized_phone)) {
                // Complete legacy profiles acquire locks only for deterministic invoice phones.
                if ($name === 'invoices' && $row->customer_id && !Biawin_CRM_Ownership_Transfer_Service::permits_customer((int)$row->customer_id)) Biawin_CRM_Ownership_Lock_Service::sync_completed((int)$row->customer_id);
                if ($name === 'invoices') Biawin_CRM_Ownership_Lock_Service::assert_can_change_invoice_owner((int)$row->id);
                else Biawin_CRM_Ownership_Lock_Service::assert_can_change_phone_owner((int)$row->id);
            }
        }
        if ($name === 'customers' && $deleting) foreach ($rows as $row) {
            foreach (['invoices'=>'INVOICE','phones'=>'PHONE'] as $table=>$type) foreach (self::current_rows($wpdb->prepare('SELECT id FROM ' . Biawin_CRM_DB::table($table) . ' WHERE customer_id=%d',$row->id)) as $asset) {
                if ($type === 'INVOICE') Biawin_CRM_Ownership_Lock_Service::assert_can_change_invoice_owner((int)$asset->id);
                else Biawin_CRM_Ownership_Lock_Service::assert_can_change_phone_owner((int)$asset->id);
            }
        }
    }
}
