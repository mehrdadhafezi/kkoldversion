<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v0.8.55.2 fail-closed reporting population source-of-truth.
 *
 * Unknown dependency state is never converted into a trusted zero.
 * This service is read-only and has no Frozen Plan business dependency.
 */
class Biawin_CRM_Reporting_Population_Service
{
    public const ALL_LIVE = 'ALL_LIVE';
    public const NORMAL_CANONICAL = 'NORMAL_CANONICAL';
    public const GUARDED = 'GUARDED';
    public const BLACKLISTED_LIVE = 'BLACKLISTED_LIVE';
    public const UNRESOLVED = 'UNRESOLVED';
    public const NORMAL_PLUS_VISIBLE_QUARANTINE = 'NORMAL_PLUS_VISIBLE_QUARANTINE';

    private const BLACKLIST_FILE = 'data/blacklisted-customer-ids.txt';

    private static $memo = [];

    public static function definitions(): array
    {
        return [
            self::ALL_LIVE => [
                'key' => self::ALL_LIVE,
                'description' => 'Every current customer row. Technical population only.',
                'guard_policy' => 'included',
                'blacklist_policy' => 'included',
                'canonical_identity_assumption' => 'customers.id is a technical key; source_customer_id is not assumed safe.',
                'live_or_historical_source' => 'live customers table',
            ],
            self::NORMAL_CANONICAL => [
                'key' => self::NORMAL_CANONICAL,
                'description' => 'Normal business population for future management KPIs.',
                'guard_policy' => 'excluded and disclosed separately',
                'blacklist_policy' => 'excluded and disclosed separately',
                'canonical_identity_assumption' => 'live unguarded non-blacklisted customer rows only',
                'live_or_historical_source' => 'live customers + live Guard + official blacklist',
            ],
            self::GUARDED => [
                'key' => self::GUARDED,
                'description' => 'Current live Readiness Guard / quarantine rows with a live customer row.',
                'guard_policy' => 'source population',
                'blacklist_policy' => 'not assumed equal',
                'canonical_identity_assumption' => 'unresolved/quarantined current live customer rows',
                'live_or_historical_source' => 'live readiness_quarantine table',
            ],
            self::BLACKLISTED_LIVE => [
                'key' => self::BLACKLISTED_LIVE,
                'description' => 'Current live customers whose source_customer_id is in the official blacklist.',
                'guard_policy' => 'reported independently',
                'blacklist_policy' => 'source population',
                'canonical_identity_assumption' => 'blacklisted CustomerID is not normal canonical identity',
                'live_or_historical_source' => 'live customers + official blacklist file',
            ],
            self::UNRESOLVED => [
                'key' => self::UNRESOLVED,
                'description' => 'Current unresolved identity population. In this foundation release it maps to live Guard rows.',
                'guard_policy' => 'included',
                'blacklist_policy' => 'reported independently',
                'canonical_identity_assumption' => 'must not be hidden inside normal KPIs',
                'live_or_historical_source' => 'live readiness_quarantine table',
            ],
            self::NORMAL_PLUS_VISIBLE_QUARANTINE => [
                'key' => self::NORMAL_PLUS_VISIBLE_QUARANTINE,
                'description' => 'Management display population: normal canonical plus explicitly visible quarantine counts.',
                'guard_policy' => 'visible as a separate segment',
                'blacklist_policy' => 'visible as a separate segment',
                'canonical_identity_assumption' => 'normal rows and unresolved rows are not merged',
                'live_or_historical_source' => 'live customers + live Guard + official blacklist',
            ],
        ];
    }

    public static function summary(): array
    {
        $all = self::all_live_source();
        $guard = self::guard_source();
        $blacklist = self::blacklist_live_source();
        $canonical = self::canonical_count_result($all, $guard, $blacklist);
        $trusted = $canonical['trusted'];
        $divergence = self::divergence($guard, $blacklist);

        return [
            'definitions' => self::definitions(),
            'counts' => [
                self::ALL_LIVE => self::metric(self::ALL_LIVE, 'All live customers', $all['count'], $all['available'], $all['status'], 'live customers table'),
                self::GUARDED => self::metric(self::GUARDED, 'Guarded live customers', $guard['count'], $guard['available'], $guard['status'], 'live readiness_quarantine table'),
                self::BLACKLISTED_LIVE => self::metric(self::BLACKLISTED_LIVE, 'Blacklisted live customers', $blacklist['count'], $blacklist['available'], $blacklist['status'], 'official blacklist + live customers table'),
                self::NORMAL_CANONICAL => self::metric(self::NORMAL_CANONICAL, 'Normal canonical customers', $canonical['count'], $trusted, $canonical['status'], 'ALL_LIVE minus union(GUARDED, BLACKLISTED_LIVE)', $canonical['dependency_errors']),
                self::UNRESOLVED => self::metric(self::UNRESOLVED, 'Unresolved customers', $guard['count'], $guard['available'], $guard['status'], 'Guard rows; unavailable Guard remains unavailable'),
                self::NORMAL_PLUS_VISIBLE_QUARANTINE => self::metric(
                    self::NORMAL_PLUS_VISIBLE_QUARANTINE,
                    'Normal plus visible quarantine',
                    $trusted ? (int)$canonical['count'] + (int)$guard['count'] : null,
                    $trusted,
                    $trusted ? 'AVAILABLE' : 'BLOCKED',
                    'normal canonical plus visible Guard segment',
                    $trusted ? [] : $canonical['dependency_errors']
                ),
            ],
            'sources' => [
                'all_live' => self::source_public($all),
                'guard' => self::source_public($guard),
                'blacklist' => self::source_public($blacklist),
            ],
            'invariants' => [
                'canonical_trusted' => $trusted,
                'normal_count_plus_exclusion_union_equals_all_live' => $trusted ? (((int)$canonical['count'] + (int)$canonical['excluded_union_count']) === (int)$all['count']) : null,
                'excluded_union_count' => $trusted ? $canonical['excluded_union_count'] : null,
            ],
            'divergence' => $divergence,
            'metadata' => [
                'read_only' => true,
                'frozen_plan_dependency' => 'NO',
                'guard_source' => 'live readiness_quarantine table only',
                'blacklist_source' => self::BLACKLIST_FILE,
                'canonical_population_status' => $trusted ? 'HEALTHY' : 'BLOCKED',
                'dependency_errors' => $canonical['dependency_errors'],
                'unknown_is_not_zero' => true,
                'empty_blacklist_policy' => 'EMPTY_UNEXPECTED blocks trusted canonical reporting',
            ],
        ];
    }

    public static function all_live_source(): array
    {
        if (isset(self::$memo['all_live_source'])) return self::$memo['all_live_source'];
        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        if (!self::table_exists($customers)) {
            return self::$memo['all_live_source'] = self::source_result(false, 'TABLE_UNAVAILABLE', [], 'customers table unavailable');
        }
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $customers");
        if (self::last_query_failed()) {
            return self::$memo['all_live_source'] = self::source_result(false, 'QUERY_FAILED', [], self::last_error());
        }
        return self::$memo['all_live_source'] = self::source_result(true, 'AVAILABLE', [], null, (int)$count);
    }

    public static function guard_source(): array
    {
        if (isset(self::$memo['guard_source'])) return self::$memo['guard_source'];
        global $wpdb;
        if (!class_exists('Biawin_CRM_Production_Readiness')) {
            return self::$memo['guard_source'] = self::source_result(false, 'CLASS_UNAVAILABLE', [], 'Production Readiness class unavailable');
        }
        $q = Biawin_CRM_Production_Readiness::table();
        $c = Biawin_CRM_DB::table('customers');
        if (!self::table_exists($q) || !self::table_exists($c)) {
            return self::$memo['guard_source'] = self::source_result(false, 'TABLE_UNAVAILABLE', [], 'Guard/quarantine or customers table unavailable');
        }
        $ids = $wpdb->get_col("SELECT DISTINCT q.customer_id FROM $q q INNER JOIN $c c ON c.id=q.customer_id ORDER BY q.customer_id ASC");
        if (self::last_query_failed() || !is_array($ids)) {
            return self::$memo['guard_source'] = self::source_result(false, 'QUERY_FAILED', [], self::last_error());
        }
        return self::$memo['guard_source'] = self::source_result(true, 'AVAILABLE', array_map('intval', $ids));
    }

    public static function blacklist_file_source(): array
    {
        if (isset(self::$memo['blacklist_file_source'])) return self::$memo['blacklist_file_source'];
        $path = defined('BIAWIN_CRM_DIR') ? BIAWIN_CRM_DIR . self::BLACKLIST_FILE : '';
        if ($path === '') {
            return self::$memo['blacklist_file_source'] = self::source_result(false, 'MISSING', [], 'BIAWIN_CRM_DIR unavailable');
        }
        if (!file_exists($path)) {
            return self::$memo['blacklist_file_source'] = self::source_result(false, 'MISSING', [], 'blacklist file missing', null, ['path' => $path]);
        }
        if (!is_readable($path)) {
            return self::$memo['blacklist_file_source'] = self::source_result(false, 'UNREADABLE', [], 'blacklist file unreadable', null, ['path' => $path]);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return self::$memo['blacklist_file_source'] = self::source_result(false, 'LOAD_FAILED', [], 'blacklist file could not be loaded', null, ['path' => $path]);
        }
        $set = self::parse_blacklist_lines($lines);
        if (!$set) {
            return self::$memo['blacklist_file_source'] = self::source_result(false, 'EMPTY_UNEXPECTED', [], 'official production blacklist unexpectedly empty', null, ['path' => $path]);
        }
        return self::$memo['blacklist_file_source'] = self::source_result(true, 'AVAILABLE', array_keys($set), null, null, ['path' => $path]);
    }

    public static function blacklist_live_source(): array
    {
        if (isset(self::$memo['blacklist_live_source'])) return self::$memo['blacklist_live_source'];
        global $wpdb;
        $file = self::blacklist_file_source();
        if (!$file['available']) {
            return self::$memo['blacklist_live_source'] = self::source_result(false, $file['status'], [], $file['error'], null, $file['metadata']);
        }
        $customers = Biawin_CRM_DB::table('customers');
        if (!self::table_exists($customers)) {
            return self::$memo['blacklist_live_source'] = self::source_result(false, 'TABLE_UNAVAILABLE', [], 'customers table unavailable', null, $file['metadata']);
        }
        $ids = [];
        foreach (array_chunk($file['ids'], 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '%s'));
            $sql = $wpdb->prepare("SELECT id FROM $customers WHERE source_customer_id IN ($ph)", ...$chunk);
            $rows = $wpdb->get_col($sql);
            if (self::last_query_failed() || !is_array($rows)) {
                return self::$memo['blacklist_live_source'] = self::source_result(false, 'QUERY_FAILED', [], self::last_error(), null, $file['metadata']);
            }
            foreach ($rows as $id) $ids[(int)$id] = (int)$id;
        }
        ksort($ids);
        return self::$memo['blacklist_live_source'] = self::source_result(true, 'AVAILABLE', array_values($ids), null, null, $file['metadata'] + ['blacklist_id_count' => $file['count']]);
    }

    public static function normal_canonical_source(int $limit = 0, int $offset = 0): array
    {
        $all = self::all_live_source();
        $guard = self::guard_source();
        $blacklist = self::blacklist_live_source();
        $counts = self::canonical_count_result($all, $guard, $blacklist);
        if (!$counts['trusted']) {
            return self::source_result(false, 'BLOCKED', [], implode('; ', $counts['dependency_errors']), null, ['dependency_errors' => $counts['dependency_errors']]);
        }

        global $wpdb;
        $customers = Biawin_CRM_DB::table('customers');
        $excluded = array_fill_keys(array_merge($guard['ids'], $blacklist['ids']), true);
        $where = '';
        if ($excluded) {
            $ids = array_map('intval', array_keys($excluded));
            $where = ' WHERE id NOT IN (' . implode(',', $ids) . ')';
        }
        $sql = "SELECT id FROM $customers$where ORDER BY id ASC";
        if ($limit > 0) $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, max(0, $offset));
        $ids = $wpdb->get_col($sql);
        if (self::last_query_failed() || !is_array($ids)) {
            return self::source_result(false, 'QUERY_FAILED', [], self::last_error());
        }
        return self::source_result(true, 'AVAILABLE', array_map('intval', $ids));
    }

    public static function guarded_customer_ids(): array
    {
        $source = self::guard_source();
        return $source['available'] ? $source['ids'] : [];
    }

    public static function blacklisted_live_customer_ids(): array
    {
        $source = self::blacklist_live_source();
        return $source['available'] ? $source['ids'] : [];
    }

    public static function normal_canonical_customer_ids(int $limit = 0, int $offset = 0): array
    {
        $source = self::normal_canonical_source($limit, $offset);
        return $source['available'] ? $source['ids'] : [];
    }

    public static function load_blacklist(): array
    {
        $source = self::blacklist_file_source();
        if (!$source['available']) return [];
        $set = [];
        foreach ($source['ids'] as $id) $set[(string)$id] = true;
        return $set;
    }

    public static function parse_blacklist_lines(array $lines): array
    {
        $set = [];
        foreach ($lines as $line) {
            $id = strtoupper(trim((string)$line));
            if ($id !== '') $set[$id] = true;
        }
        return $set;
    }

    public static function canonical_count_result(array $all, array $guard, array $blacklist): array
    {
        $errors = self::dependency_errors($all, $guard, $blacklist);
        if ($errors) {
            return [
                'trusted' => false,
                'status' => 'BLOCKED',
                'count' => null,
                'excluded_union_count' => null,
                'dependency_errors' => $errors,
            ];
        }
        $excluded = array_fill_keys(array_merge($guard['ids'], $blacklist['ids']), true);
        return [
            'trusted' => true,
            'status' => 'AVAILABLE',
            'count' => max(0, (int)$all['count'] - count($excluded)),
            'excluded_union_count' => count($excluded),
            'dependency_errors' => [],
        ];
    }

    public static function source_result(bool $available, string $status, array $ids = [], ?string $error = null, ?int $count = null, array $metadata = []): array
    {
        $ids = array_values(array_unique($ids));
        return [
            'available' => $available,
            'trusted' => $available,
            'status' => $status,
            'ids' => $ids,
            'count' => $count !== null ? $count : ($available ? count($ids) : null),
            'error' => $error,
            'metadata' => $metadata,
        ];
    }

    public static function reset_request_memo(): void
    {
        self::$memo = [];
    }

    public static function table_exists(string $table): bool
    {
        global $wpdb;
        if (!isset($wpdb)) return false;
        if (isset($wpdb->last_error)) $wpdb->last_error = '';
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (self::last_query_failed()) return false;
        return (string)$found === $table;
    }

    private static function dependency_errors(array $all, array $guard, array $blacklist): array
    {
        $errors = [];
        if (!$all['available']) $errors[] = 'ALL_LIVE_' . $all['status'];
        if (!$guard['available']) $errors[] = 'GUARD_' . $guard['status'];
        if (!$blacklist['available']) $errors[] = 'BLACKLIST_' . $blacklist['status'];
        return $errors;
    }

    private static function divergence(array $guard, array $blacklist): array
    {
        if (!$guard['available'] || !$blacklist['available']) {
            return [
                'trusted' => false,
                'status' => 'BLOCKED',
                'guarded_not_blacklisted' => null,
                'blacklisted_not_guarded' => null,
            ];
        }
        $g = array_fill_keys($guard['ids'], true);
        $b = array_fill_keys($blacklist['ids'], true);
        return [
            'trusted' => true,
            'status' => 'AVAILABLE',
            'guarded_not_blacklisted' => count(array_diff_key($g, $b)),
            'blacklisted_not_guarded' => count(array_diff_key($b, $g)),
        ];
    }

    private static function metric(string $key, string $label, ?int $count, bool $trusted, string $status, string $description, array $errors = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'trusted' => $trusted,
            'status' => $status,
            'source_status' => $status,
            'description' => $description,
            'dependency_errors' => $errors,
        ];
    }

    private static function source_public(array $source): array
    {
        return [
            'available' => $source['available'],
            'trusted' => $source['trusted'],
            'status' => $source['status'],
            'count' => $source['count'],
            'error' => $source['error'],
            'metadata' => $source['metadata'],
        ];
    }

    private static function last_query_failed(): bool
    {
        global $wpdb;
        return isset($wpdb->last_error) && (string)$wpdb->last_error !== '';
    }

    private static function last_error(): string
    {
        global $wpdb;
        return isset($wpdb->last_error) && (string)$wpdb->last_error !== '' ? (string)$wpdb->last_error : 'database query failed';
    }
}
