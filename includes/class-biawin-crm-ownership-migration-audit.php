<?php

if (!defined('ABSPATH')) { exit; }

/** Opt-in, read-only legacy inventory. Nothing invokes this on plugin load. */
class Biawin_CRM_Ownership_Migration_Audit
{
    const HEALTHY = 'CLEAN';
    const AUTO_REPAIRABLE = 'DETERMINISTIC_REPAIR';
    const AMBIGUOUS = 'AMBIGUOUS_REVIEW';

    /** Classification is a migration recommendation, never an authorization to transfer. */
    public static function classify(array $phones, ?string $canonical = null, array $issues = []): array
    {
        $normalized = [];
        $requires_normalization = false;
        foreach ($phones as $phone) {
            $value = Biawin_CRM_Normalizer::phone((string)$phone);
            if ($value === '') $issues[] = 'invalid_phone';
            else $normalized[$value] = true;
            if ($value !== (string)$phone) $requires_normalization = true;
        }
        if (!$phones) $issues[] = 'missing_phone';
        if (count($normalized) > 1) $issues[] = 'multiple_phone_candidates';
        if ($canonical !== null && $canonical !== '') {
            $value = Biawin_CRM_Normalizer::phone($canonical);
            if ($value === '' || !isset($normalized[$value]) || $canonical !== $value) {
                $issues[] = 'canonical_phone_conflict';
            }
        }
        $issues = array_values(array_unique($issues));
        $classification = $issues ? self::AMBIGUOUS :
            (($requires_normalization || count($phones) > 1) ? self::AUTO_REPAIRABLE : self::HEALTHY);
        return [
            'classification' => $classification,
            'relation_count' => count($phones),
            'normalized_candidates' => array_keys($normalized),
            'issues' => $issues,
        ];
    }

    /**
     * Keyset pagination: repeat with next_after_id until has_more=false.
     * Counts are per batch, not global totals or a transactionally frozen snapshot.
     * No option writes, repairs, queue inserts, initial locks or full-table counts.
     */
    public static function audit_batch(int $after_id = 0, int $limit = 200): array
    {
        global $wpdb;
        $after_id = max(0, $after_id);
        $limit = max(1, min(1000, $limit));
        $invoices = Biawin_CRM_DB::table('invoices');
        $customers = Biawin_CRM_DB::table('customers');
        $relations = Biawin_CRM_DB::table('invoice_phones');
        $phones = Biawin_CRM_DB::table('phones');
        $services = Biawin_CRM_DB::table('services');
        $locks = Biawin_CRM_DB::table('ownership_locks');
        $rows = self::read($wpdb->prepare(
            "SELECT i.id, i.customer_id, i.canonical_phone_normalized,
                    c.id AS owner_exists, c.profile_completeness,
                    EXISTS (SELECT 1 FROM $locks l WHERE l.entity_type='INVOICE'
                        AND l.entity_id=i.id AND l.released_at IS NULL) AS has_active_lock
             FROM $invoices i LEFT JOIN $customers c ON c.id=i.customer_id
             WHERE i.id > %d ORDER BY i.id ASC LIMIT %d", $after_id, $limit + 1
        ));
        $has_more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $report = [
            'counts' => [self::HEALTHY => 0, self::AUTO_REPAIRABLE => 0, self::AMBIGUOUS => 0],
            'relation_counts' => ['zero' => 0, 'one' => 0, 'multiple' => 0],
            'records' => [], 'next_after_id' => $after_id, 'has_more' => $has_more,
        ];
        if (!$rows) return $report;
        $ids = array_map(static function ($row) { return (int)$row->id; }, $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $links = self::read($wpdb->prepare(
            "SELECT invoice_id, normalized_phone FROM $relations
             WHERE invoice_id IN ($placeholders) ORDER BY invoice_id,id", ...$ids
        ));
        $by_invoice = [];
        $candidates = [];
        foreach ($links as $link) {
            $by_invoice[(int)$link->invoice_id][] = (string)$link->normalized_phone;
            $normalized = Biawin_CRM_Normalizer::phone((string)$link->normalized_phone);
            if ($normalized !== '') {
                $candidates[$normalized] = true;
                $candidates[(string)$link->normalized_phone] = true;
            }
        }
        $phone_owners = [];
        if ($candidates) {
            $phone_placeholders = implode(',', array_fill(0, count($candidates), '%s'));
            foreach (self::read($wpdb->prepare(
                "SELECT id, normalized_phone, customer_id FROM $phones WHERE normalized_phone IN ($phone_placeholders)",
                ...array_keys($candidates)
            )) as $phone) {
                $normalized = Biawin_CRM_Normalizer::phone((string)$phone->normalized_phone);
                $phone_owners[$normalized][] = $phone->customer_id;
            }
        }
        $service_mismatches = [];
        foreach (self::read($wpdb->prepare(
            "SELECT s.id, s.invoice_id FROM $services s JOIN $invoices i ON i.id=s.invoice_id
             WHERE s.invoice_id IN ($placeholders) AND NOT (s.customer_id <=> i.customer_id)", ...$ids
        )) as $service) {
            $service_mismatches[(int)$service->invoice_id][] = (int)$service->id;
        }
        foreach ($rows as $invoice) {
            $id = (int)$invoice->id;
            $values = $by_invoice[$id] ?? [];
            $issues = [];
            if (!$invoice->owner_exists) $issues[] = 'missing_invoice_owner';
            foreach ($values as $value) {
                $normalized = Biawin_CRM_Normalizer::phone($value);
                $owners = $phone_owners[$normalized] ?? [];
                if ($normalized !== '' && !$owners) $issues[] = 'missing_phone_identity';
                if (count($owners) > 1) $issues[] = 'duplicate_phone_identity';
                foreach ($owners as $owner) {
                    if (!$owner || (int)$owner !== (int)$invoice->customer_id) $issues[] = 'phone_owner_mismatch';
                }
            }
            if (!empty($service_mismatches[$id])) $issues[] = 'service_owner_mismatch';
            $record = self::classify($values, $invoice->canonical_phone_normalized, $issues);
            $record['invoice_id'] = $id;
            $record['service_mismatch_ids'] = $service_mismatches[$id] ?? [];
            $record['needs_initial_lock'] = $invoice->profile_completeness === 'complete' && !$invoice->has_active_lock;
            $report['records'][] = $record;
            $report['counts'][$record['classification']]++;
            $report['relation_counts'][count($values) === 0 ? 'zero' : (count($values) === 1 ? 'one' : 'multiple')]++;
            $report['next_after_id'] = $id;
        }
        return $report;
    }

    private static function read(string $sql): array
    {
        global $wpdb;
        $wpdb->last_error = '';
        $rows = $wpdb->get_results($sql);
        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new RuntimeException('Ownership audit read failed: ' . $wpdb->last_error);
        }
        return $rows;
    }
}
