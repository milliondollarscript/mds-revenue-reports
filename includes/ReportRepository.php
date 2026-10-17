<?php
/**
 * Revenue reporting queries.
 *
 * @package MillionDollarScript\Extensions\RevenueReports
 */

namespace MillionDollarScript\Extensions\RevenueReports;

use MillionDollarScript\Commerce\Currency;
use MillionDollarScript\Core\Database as DB;

if (!defined('ABSPATH')) {
    exit;
}

final class ReportRepository {

    private const STATUS_GROUPS = [
        'income' => ['paid'],
        'open' => ['reserved', 'pending_payment'],
        'issues' => ['failed', 'refunded', 'cancelled', 'denied', 'expired'],
    ];

    private const STATUSES = ['reserved', 'pending_payment', 'paid', 'cancelled', 'failed', 'refunded', 'expired', 'denied', 'deleted'];

    private $orders_table;
    private $items_table;
    private $grids_table;

    public function __construct() {
        $this->orders_table = DB::table('orders');
        $this->items_table = DB::table('order_items');
        $this->grids_table = DB::table('grids');
    }

    public function tables_ready() {
        return DB::table_exists($this->orders_table);
    }

    public function args_from_request(array $source) {
        $source = wp_unslash($source);
        $preset = sanitize_key((string) ($source['preset'] ?? '30'));
        $dates = $this->date_range($preset, (string) ($source['date_from'] ?? ''), (string) ($source['date_to'] ?? ''));

        return [
            'preset' => $preset,
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'status' => sanitize_key((string) ($source['status'] ?? 'income')),
            'grid_id' => absint($source['grid_id'] ?? 0),
            'provider' => sanitize_key((string) ($source['provider'] ?? 'all')),
            'search' => sanitize_text_field((string) ($source['s'] ?? '')),
        ];
    }

    public function status_options() {
        return [
            'income' => __('Paid revenue', 'mds-revenue-reports'),
            'all' => __('All statuses', 'mds-revenue-reports'),
            'open' => __('Reserved or awaiting payment', 'mds-revenue-reports'),
            'issues' => __('Expired, failed, refunded, cancelled, or denied', 'mds-revenue-reports'),
            'reserved' => __('Reserved', 'mds-revenue-reports'),
            'pending_payment' => __('Pending payment', 'mds-revenue-reports'),
            'paid' => __('Paid', 'mds-revenue-reports'),
            'expired' => __('Expired', 'mds-revenue-reports'),
            'cancelled' => __('Cancelled', 'mds-revenue-reports'),
            'failed' => __('Failed', 'mds-revenue-reports'),
            'refunded' => __('Refunded', 'mds-revenue-reports'),
            'denied' => __('Denied', 'mds-revenue-reports'),
            'deleted' => __('Deleted', 'mds-revenue-reports'),
        ];
    }

    public function preset_options() {
        return [
            '7' => __('Last 7 days', 'mds-revenue-reports'),
            '30' => __('Last 30 days', 'mds-revenue-reports'),
            '90' => __('Last 90 days', 'mds-revenue-reports'),
            'month' => __('This month', 'mds-revenue-reports'),
            'year' => __('This year', 'mds-revenue-reports'),
            'last_year' => __('Last year', 'mds-revenue-reports'),
            'all' => __('All time', 'mds-revenue-reports'),
            'custom' => __('Custom range', 'mds-revenue-reports'),
        ];
    }

    public function grid_options() {
        global $wpdb;

        if (!DB::table_exists($this->grids_table)) {
            return [];
        }

        $rows = $wpdb->get_results(
            'SELECT id, title, slug, status FROM ' . DB::ident($this->grids_table) . ' ORDER BY title ASC, id ASC',
            ARRAY_A
        );

        $options = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = absint($row['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $label = trim((string) ($row['title'] ?? ''));
            if ('' === $label) {
                $label = sprintf(__('Grid #%d', 'mds-revenue-reports'), $id);
            }
            if (!empty($row['status']) && 'active' !== (string) $row['status']) {
                $label .= ' - ' . ucfirst((string) $row['status']);
            }

            $options[$id] = $label;
        }

        return $options;
    }

    public function provider_options() {
        global $wpdb;

        if (!$this->tables_ready()) {
            return [];
        }

        $rows = $wpdb->get_col(
            'SELECT DISTINCT commerce_provider FROM ' . DB::ident($this->orders_table) . " WHERE commerce_provider IS NOT NULL AND commerce_provider <> '' ORDER BY commerce_provider ASC"
        );

        $options = [
            'all' => __('All providers', 'mds-revenue-reports'),
            '__manual__' => __('Standalone or manual orders', 'mds-revenue-reports'),
        ];

        foreach (is_array($rows) ? $rows : [] as $provider) {
            $provider = sanitize_key((string) $provider);
            if ($provider && !isset($options[$provider])) {
                $options[$provider] = ucwords(str_replace(['-', '_'], ' ', $provider));
            }
        }

        return $options;
    }

    public function summary(array $args) {
        $revenue_by_currency = $this->revenue_by_currency($args);
        $primary_currency = (string) ($revenue_by_currency[0]['currency'] ?? $this->default_currency());
        $order_count = 0;
        $total_revenue = 0.0;

        foreach ($revenue_by_currency as $row) {
            $order_count += absint($row['orders'] ?? 0);
            $total_revenue += (float) ($row['total'] ?? 0);
        }

        return [
            'revenue_by_currency' => $revenue_by_currency,
            'primary_currency' => $primary_currency,
            'total_revenue' => $total_revenue,
            'order_count' => $order_count,
            'average_by_currency' => $this->average_by_currency($revenue_by_currency),
            'status_counts' => $this->status_counts($args),
            'trend' => $this->trend($args, $primary_currency),
            'grid_breakdown' => $this->grid_breakdown($args, $primary_currency),
            'recent_orders' => $this->orders($args, 25),
        ];
    }

    public function orders(array $args, $limit = 50) {
        global $wpdb;

        if (!$this->tables_ready()) {
            return [];
        }

        $params = [];
        $where = $this->order_where($args, $params);
        $params[] = max(1, min(5000, absint($limit)));

        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT o.* FROM ' . DB::ident($this->orders_table) . ' o' . $where . ' ORDER BY o.created_at DESC, o.id DESC LIMIT %d',
                $params
            ),
            ARRAY_A
        );
        return $this->with_grid_labels(is_array($rows) ? $rows : []);
    }

    public function export_rows(array $args, $limit = 500, $before_id = 0) {
        global $wpdb;

        if (!$this->tables_ready()) {
            return [];
        }

        $params = [];
        $where = $this->order_where($args, $params);
        $before_id = absint($before_id);
        if ($before_id) {
            $where .= ' AND o.id < %d';
            $params[] = $before_id;
        }
        $params[] = max(1, min(1000, absint($limit)));

        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT o.* FROM ' . DB::ident($this->orders_table) . ' o' . $where . ' ORDER BY o.id DESC LIMIT %d',
                $params
            ),
            ARRAY_A
        );

        return $this->with_grid_labels(is_array($rows) ? $rows : []);
    }

    public function money($amount, $currency = '') {
        $currency = class_exists(Currency::class)
            ? Currency::normalize_code($currency, $this->default_currency())
            : $this->default_currency();

        return $currency . ' ' . number_format_i18n((float) $amount, 2);
    }

    public function default_currency() {
        if (class_exists(Currency::class)) {
            return Currency::current_code();
        }

        return 'USD';
    }

    private function revenue_by_currency(array $args) {
        global $wpdb;

        if (!$this->tables_ready()) {
            return [];
        }

        $where_params = [];
        $where = $this->order_where($args, $where_params);
        $currency_expr = "UPPER(COALESCE(NULLIF(o.currency, ''), %s))";
        $params = array_merge([$this->default_currency()], $where_params);

        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT ' . $currency_expr . ' AS currency, COUNT(*) AS orders, COALESCE(SUM(o.total), 0) AS total FROM ' . DB::ident($this->orders_table) . ' o' . $where . ' GROUP BY currency ORDER BY total DESC, currency ASC',
                $params
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function average_by_currency(array $revenue_by_currency) {
        $averages = [];
        foreach ($revenue_by_currency as $row) {
            $orders = max(1, absint($row['orders'] ?? 0));
            $currency = class_exists(Currency::class)
                ? Currency::normalize_code($row['currency'] ?? '', $this->default_currency())
                : $this->default_currency();
            $averages[$currency] = ((float) ($row['total'] ?? 0)) / $orders;
        }

        return $averages;
    }

    private function status_counts(array $args) {
        global $wpdb;

        if (!$this->tables_ready()) {
            return [];
        }

        $params = [];
        $where = $this->order_where($args, $params, ['ignore_status' => true]);
        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT o.status, COUNT(*) AS orders, COALESCE(SUM(o.total), 0) AS total FROM ' . DB::ident($this->orders_table) . ' o' . $where . ' GROUP BY o.status ORDER BY orders DESC, o.status ASC',
                $params
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function trend(array $args, $currency) {
        global $wpdb;

        if (!$this->tables_ready()) {
            return [];
        }

        $params = [];
        $where = $this->order_where($args, $params);
        $currency = class_exists(Currency::class)
            ? Currency::normalize_code($currency, $this->default_currency())
            : $this->default_currency();
        $group = $this->trend_grouping($args);
        $date_expr = 'month' === $group ? "DATE_FORMAT(o.created_at, '%%Y-%%m')" : 'DATE(o.created_at)';
        $where .= " AND UPPER(COALESCE(NULLIF(o.currency, ''), %s)) = %s";
        $params[] = $this->default_currency();
        $params[] = $currency;

        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT ' . $date_expr . ' AS period, COUNT(*) AS orders, COALESCE(SUM(o.total), 0) AS total FROM ' . DB::ident($this->orders_table) . ' o' . $where . ' GROUP BY period ORDER BY period ASC',
                $params
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function grid_breakdown(array $args, $currency) {
        global $wpdb;

        if (!$this->tables_ready() || !DB::table_exists($this->items_table)) {
            return [];
        }

        $params = [];
        $where = $this->order_where($args, $params, ['ignore_grid' => true]);
        $currency = class_exists(Currency::class)
            ? Currency::normalize_code($currency, $this->default_currency())
            : $this->default_currency();
        $where .= " AND UPPER(COALESCE(NULLIF(o.currency, ''), %s)) = %s";
        $params[] = $this->default_currency();
        $params[] = $currency;
        $grid_id = absint($args['grid_id'] ?? 0);
        if ($grid_id) {
            $where .= ' AND oi.grid_id = %d';
            $params[] = $grid_id;
        }

        $grid_join = DB::table_exists($this->grids_table)
            ? ' LEFT JOIN ' . DB::ident($this->grids_table) . ' g ON g.id = oi.grid_id'
            : '';
        $grid_label = DB::table_exists($this->grids_table)
            ? "COALESCE(NULLIF(g.title, ''), CONCAT('Grid #', oi.grid_id))"
            : "CONCAT('Grid #', oi.grid_id)";

        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT oi.grid_id, ' . $grid_label . ' AS grid_title, COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(oi.quantity), 0) AS units, COALESCE(SUM(oi.total), 0) AS total FROM ' . DB::ident($this->items_table) . ' oi INNER JOIN ' . DB::ident($this->orders_table) . ' o ON o.id = oi.order_id' . $grid_join . $where . ' GROUP BY oi.grid_id, grid_title ORDER BY total DESC, orders DESC LIMIT 10',
                $params
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    private function grid_labels_for_orders(array $order_ids) {
        global $wpdb;

        $order_ids = array_values(array_unique(array_filter(array_map('absint', $order_ids))));
        if (!$order_ids || !DB::table_exists($this->items_table)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($order_ids), '%d'));
        $has_grids = DB::table_exists($this->grids_table);
        $grid_join = $has_grids
            ? ' LEFT JOIN ' . DB::ident($this->grids_table) . ' g ON g.id = oi.grid_id'
            : '';
        $grid_title = $has_grids ? ', g.title AS grid_title' : '';

        $rows = $wpdb->get_results(
            $this->prepare(
                'SELECT DISTINCT oi.order_id, oi.grid_id' . $grid_title . ' FROM ' . DB::ident($this->items_table) . ' oi' . $grid_join . ' WHERE oi.order_id IN (' . $placeholders . ') ORDER BY oi.order_id ASC, oi.grid_id ASC',
                $order_ids
            ),
            ARRAY_A
        );

        $labels = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $order_id = absint($row['order_id'] ?? 0);
            $grid_id = absint($row['grid_id'] ?? 0);
            if (!$order_id || !$grid_id) {
                continue;
            }

            if (!isset($labels[$order_id])) {
                $labels[$order_id] = [];
            }

            if ($has_grids) {
                $title = trim((string) ($row['grid_title'] ?? ''));
                $labels[$order_id][] = '#' . $grid_id . ' ' . ($title ?: __('Untitled grid', 'mds-revenue-reports'));
            } else {
                $labels[$order_id][] = sprintf(__('Grid #%d', 'mds-revenue-reports'), $grid_id);
            }
        }

        foreach ($labels as $order_id => $order_labels) {
            $labels[$order_id] = implode(', ', array_values(array_unique($order_labels)));
        }

        return $labels;
    }

    /**
     * Add readable grid labels to order report rows.
     *
     * @param array $rows Raw order rows.
     * @return array
     */
    private function with_grid_labels(array $rows) {
        $labels = $this->grid_labels_for_orders(array_map('absint', wp_list_pluck($rows, 'id')));

        foreach ($rows as $index => $row) {
            $order_id = absint($row['id'] ?? 0);
            $rows[$index]['grids'] = (string) ($labels[$order_id] ?? '');
        }

        return $rows;
    }

    private function order_where(array $args, array &$params, array $options = []) {
        global $wpdb;

        $where = ['1=1'];
        if (!empty($args['date_from'])) {
            $where[] = 'o.created_at >= %s';
            $params[] = (string) $args['date_from'] . ' 00:00:00';
        }
        if (!empty($args['date_to'])) {
            $where[] = 'o.created_at <= %s';
            $params[] = (string) $args['date_to'] . ' 23:59:59';
        }

        if (empty($options['ignore_status'])) {
            $statuses = $this->selected_statuses((string) ($args['status'] ?? 'income'));
            if ($statuses) {
                $where[] = 'o.status IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')';
                foreach ($statuses as $status) {
                    $params[] = $status;
                }
            }
        }

        $provider = sanitize_key((string) ($args['provider'] ?? 'all'));
        if ('__manual__' === $provider) {
            $where[] = "(o.commerce_provider IS NULL OR o.commerce_provider = '')";
        } elseif ($provider && 'all' !== $provider) {
            $where[] = 'o.commerce_provider = %s';
            $params[] = $provider;
        }

        $grid_id = absint($args['grid_id'] ?? 0);
        if ($grid_id && empty($options['ignore_grid'])) {
            if (DB::table_exists($this->items_table)) {
                $where[] = 'EXISTS (SELECT 1 FROM ' . DB::ident($this->items_table) . ' oi_filter WHERE oi_filter.order_id = o.id AND oi_filter.grid_id = %d)';
                $params[] = $grid_id;
            } else {
                $where[] = '0=1';
            }
        }

        $search = trim((string) ($args['search'] ?? ''));
        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_clauses = [
                'o.email LIKE %s',
                'o.order_key LIKE %s',
                'o.commerce_order_id LIKE %s',
            ];
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;

            if (ctype_digit($search)) {
                $search_clauses[] = 'o.id = %d';
                $params[] = absint($search);
            }

            if (DB::table_exists($this->items_table) && DB::table_exists($this->grids_table)) {
                $search_clauses[] = 'EXISTS (SELECT 1 FROM ' . DB::ident($this->items_table) . ' oi_search INNER JOIN ' . DB::ident($this->grids_table) . ' g_search ON g_search.id = oi_search.grid_id WHERE oi_search.order_id = o.id AND (g_search.title LIKE %s OR g_search.slug LIKE %s))';
                $params[] = $like;
                $params[] = $like;
            }

            $where[] = '(' . implode(' OR ', $search_clauses) . ')';
        }

        return ' WHERE ' . implode(' AND ', $where);
    }

    private function selected_statuses($status) {
        $status = sanitize_key((string) $status);
        if ('all' === $status) {
            return [];
        }
        if (isset(self::STATUS_GROUPS[$status])) {
            return self::STATUS_GROUPS[$status];
        }
        if (in_array($status, self::STATUSES, true)) {
            return [$status];
        }

        return self::STATUS_GROUPS['income'];
    }

    private function date_range($preset, $date_from, $date_to) {
        $preset = sanitize_key((string) $preset);
        $now = current_datetime();
        $today = $now->format('Y-m-d');

        if ('all' === $preset) {
            return ['from' => '', 'to' => ''];
        }

        if ('custom' === $preset) {
            $from = $this->sanitize_date($date_from);
            $to = $this->sanitize_date($date_to);
            if ($from && $to && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            return ['from' => $from, 'to' => $to];
        }

        if ('month' === $preset) {
            return ['from' => $now->format('Y-m-01'), 'to' => $today];
        }

        if ('year' === $preset) {
            return ['from' => $now->format('Y-01-01'), 'to' => $today];
        }

        if ('last_year' === $preset) {
            $last_year = (int) $now->format('Y') - 1;

            return [
                'from' => sprintf('%04d-01-01', $last_year),
                'to' => sprintf('%04d-12-31', $last_year),
            ];
        }

        $days = max(1, absint($preset ?: 30));
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        return [
            'from' => $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d'),
            'to' => $today,
        ];
    }

    private function sanitize_date($value) {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
            return '';
        }

        return $date->format('Y-m-d') === $value ? $value : '';
    }

    private function trend_grouping(array $args) {
        if (empty($args['date_from']) || empty($args['date_to'])) {
            return 'month';
        }

        $from = strtotime((string) $args['date_from']);
        $to = strtotime((string) $args['date_to']);
        if (!$from || !$to) {
            return 'month';
        }

        return (($to - $from) / DAY_IN_SECONDS) > 120 ? 'month' : 'day';
    }

    private function prepare($sql, array $params) {
        global $wpdb;

        return $params ? $wpdb->prepare($sql, $params) : $sql;
    }
}
