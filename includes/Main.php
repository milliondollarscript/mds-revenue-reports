<?php
/**
 * Revenue Reports admin UI and exports.
 *
 * @package MillionDollarScript\Extensions\RevenueReports
 */

namespace MillionDollarScript\Extensions\RevenueReports;

if (!defined('ABSPATH')) {
    exit;
}

final class Main {

    private const SLUG = 'mds-revenue-reports';

    private static $instance;

    private $repository;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->repository = new ReportRepository();
    }

    public function register() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_post_mds_revenue_reports_export', [$this, 'handle_export']);
        add_filter('million-dollar-script/dashboard/paid/revenue/url', [$this, 'dashboard_paid_revenue_url']);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'extension_onboarding_items']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('mds-revenue-reports', false, dirname(MDS_REVENUE_REPORTS_BASENAME) . '/languages');
    }

    public function admin_menu() {
        add_submenu_page(
            $this->admin_parent_slug(),
            __('Revenue Reports', 'mds-revenue-reports'),
            __('Revenue Reports', 'mds-revenue-reports'),
            'manage_options',
            self::SLUG,
            [$this, 'admin_page']
        );
    }

    public function admin_assets($hook) {
        if (false === strpos((string) $hook, self::SLUG)) {
            return;
        }

        if (class_exists('\MillionDollarScript\Core\Runtime')) {
            wp_enqueue_style('million-dollar-script-admin', \MillionDollarScript\Core\Runtime::url('assets/mds3/') . 'css/admin.css', [], \MillionDollarScript\Core\Runtime::version());
        }

        wp_enqueue_style('mds-revenue-reports-admin', MDS_REVENUE_REPORTS_URL . 'assets/css/admin.css', [], MDS_REVENUE_REPORTS_VERSION);
    }

    public function dashboard_paid_revenue_url($url) {
        return $this->admin_url();
    }

    public function extension_onboarding_items(array $items) {
        $items[self::SLUG] = [
            'name' => __('Revenue Reports', 'mds-revenue-reports'),
            'summary' => __('Review paid revenue, order status, grid performance, provider trends, and exportable income records.', 'mds-revenue-reports'),
            'priority' => 12,
            'actions' => [
                [
                    'label' => __('Open revenue reports', 'mds-revenue-reports'),
                    'url' => $this->admin_url(),
                    'primary' => true,
                    'icon' => 'dashicons-chart-line',
                ],
            ],
        ];

        return $items;
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view revenue reports.', 'mds-revenue-reports'));
        }

        $args = $this->repository->args_from_request($_GET);
        $summary = $this->repository->summary($args);
        $export_url = wp_nonce_url(add_query_arg(array_merge(
            [
                'action' => 'mds_revenue_reports_export',
            ],
            $this->query_args($args)
        ), admin_url('admin-post.php')), 'mds_revenue_reports_export');
        ?>
        <div class="wrap mds3-admin mds-revenue-reports-admin">
            <div class="mds-revenue-reports-hero">
                <div>
                    <p class="mds-revenue-reports-eyebrow"><?php esc_html_e('Million Dollar Script extension', 'mds-revenue-reports'); ?></p>
                    <h1><?php esc_html_e('Revenue Reports', 'mds-revenue-reports'); ?></h1>
                    <p><?php esc_html_e('Track paid income, open reservations, problem orders, grid performance, and exportable records from one reporting workspace.', 'mds-revenue-reports'); ?></p>
                </div>
                <div class="mds-extension-header-actions">
                    <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-revenue-reports')); ?>
                    <a class="button button-primary" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'mds-revenue-reports'); ?></a>
                </div>
            </div>
            <hr class="wp-header-end">

            <?php if (!$this->repository->tables_ready()) : ?>
                <section class="mds3-card mds-revenue-reports-empty">
                    <h2><?php esc_html_e('Reports are waiting for Million Dollar Script order tables', 'mds-revenue-reports'); ?></h2>
                    <p><?php esc_html_e('Complete setup or run the plugin installer repair so revenue reports can read orders and order items.', 'mds-revenue-reports'); ?></p>
                </section>
            <?php else : ?>
                <?php $this->filters($args); ?>
                <?php $this->metrics($summary); ?>
                <?php if (class_exists('\MillionDollarScript\Extensions\Subscriptions\SubscriptionMetrics')) : ?>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The subscription extension returns capability-gated, escaped report markup.
                    echo \MillionDollarScript\Extensions\Subscriptions\SubscriptionMetrics::render($args);
                    ?>
                <?php endif; ?>
                <?php $this->report_guidance($summary); ?>
                <div class="mds-revenue-reports-layout">
                    <section class="mds3-card mds-revenue-reports-panel mds-revenue-reports-panel-wide">
                        <div class="mds-revenue-reports-panel-heading">
                            <div>
                                <h2><?php esc_html_e('Revenue trend', 'mds-revenue-reports'); ?></h2>
                                <p><?php echo esc_html(sprintf(__('Shown in %s for the selected filters.', 'mds-revenue-reports'), strtoupper((string) ($summary['primary_currency'] ?? '')))); ?></p>
                            </div>
                        </div>
                        <?php $this->trend_chart($summary['trend'] ?? [], (string) ($summary['primary_currency'] ?? '')); ?>
                    </section>

                    <section class="mds3-card mds-revenue-reports-panel">
                        <h2><?php esc_html_e('Status breakdown', 'mds-revenue-reports'); ?></h2>
                        <p><?php esc_html_e('All outcomes for the selected date, grid, provider, and search filters, regardless of the status filter.', 'mds-revenue-reports'); ?></p>
                        <?php $this->status_breakdown($summary['status_counts'] ?? []); ?>
                    </section>

                    <section class="mds3-card mds-revenue-reports-panel">
                        <h2><?php esc_html_e('Top grids', 'mds-revenue-reports'); ?></h2>
                        <p><?php echo esc_html(sprintf(__('Shown in %s for the selected filters.', 'mds-revenue-reports'), strtoupper((string) ($summary['primary_currency'] ?? '')))); ?></p>
                        <?php $this->grid_breakdown($summary['grid_breakdown'] ?? [], (string) ($summary['primary_currency'] ?? '')); ?>
                    </section>
                </div>

                <section class="mds3-card mds-revenue-reports-panel">
                    <div class="mds-revenue-reports-panel-heading">
                        <div>
                            <h2><?php esc_html_e('Recent matching orders', 'mds-revenue-reports'); ?></h2>
                            <p><?php esc_html_e('Use the filters above to narrow the report before exporting.', 'mds-revenue-reports'); ?></p>
                        </div>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-orders')); ?>"><?php esc_html_e('Open orders', 'mds-revenue-reports'); ?></a>
                    </div>
                    <?php $this->orders_table($summary['recent_orders'] ?? []); ?>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export revenue reports.', 'mds-revenue-reports'));
        }

        check_admin_referer('mds_revenue_reports_export');

        $args = $this->repository->args_from_request($_GET);
        $filename = 'million-dollar-script-revenue-' . gmdate('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'w');
        if (!$out) {
            exit;
        }

        CsvWriter::write_header($out);

        $batch_size = 500;
        $before_id = 0;
        do {
            $rows = $this->repository->export_rows($args, $batch_size, $before_id);
            foreach ($rows as $row) {
                CsvWriter::write_order($out, $row);
            }

            $last_row = $rows ? end($rows) : [];
            $next_before_id = absint($last_row['id'] ?? 0);
            if (count($rows) < $batch_size || !$next_before_id || ($before_id && $next_before_id >= $before_id)) {
                break;
            }

            $before_id = $next_before_id;
            if (function_exists('flush')) {
                flush();
            }
        } while (true);

        fclose($out);
        exit;
    }

    private function filters(array $args) {
        $grid_options = $this->repository->grid_options();
        $provider_options = $this->repository->provider_options();
        ?>
        <section class="mds3-card mds-revenue-reports-filters" aria-label="<?php esc_attr_e('Report filters', 'mds-revenue-reports'); ?>">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <label>
                    <span><?php esc_html_e('Date range', 'mds-revenue-reports'); ?></span>
                    <select name="preset">
                        <?php foreach ($this->repository->preset_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($args['preset'] ?? ''), (string) $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('From', 'mds-revenue-reports'); ?></span>
                    <input type="date" name="date_from" value="<?php echo esc_attr((string) ($args['date_from'] ?? '')); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('To', 'mds-revenue-reports'); ?></span>
                    <input type="date" name="date_to" value="<?php echo esc_attr((string) ($args['date_to'] ?? '')); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Status', 'mds-revenue-reports'); ?></span>
                    <select name="status">
                        <?php foreach ($this->repository->status_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($args['status'] ?? ''), (string) $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Grid', 'mds-revenue-reports'); ?></span>
                    <select name="grid_id">
                        <option value="0"><?php esc_html_e('All grids', 'mds-revenue-reports'); ?></option>
                        <?php foreach ($grid_options as $id => $label) : ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(absint($args['grid_id'] ?? 0), absint($id)); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Provider', 'mds-revenue-reports'); ?></span>
                    <select name="provider">
                        <?php foreach ($provider_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($args['provider'] ?? ''), (string) $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Search', 'mds-revenue-reports'); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr((string) ($args['search'] ?? '')); ?>" placeholder="<?php esc_attr_e('Order, email, provider, grid', 'mds-revenue-reports'); ?>">
                </label>
                <div class="mds-revenue-reports-filter-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply filters', 'mds-revenue-reports'); ?></button>
                    <a class="button" href="<?php echo esc_url($this->admin_url()); ?>"><?php esc_html_e('Reset', 'mds-revenue-reports'); ?></a>
                </div>
            </form>
        </section>
        <?php
    }

    private function metrics(array $summary) {
        $revenue = $summary['revenue_by_currency'] ?? [];
        $primary_currency = (string) ($summary['primary_currency'] ?? $this->repository->default_currency());
        $primary_total = 0.0;
        foreach ($revenue as $row) {
            if (strtoupper((string) ($row['currency'] ?? '')) === strtoupper($primary_currency)) {
                $primary_total = (float) ($row['total'] ?? 0);
                break;
            }
        }

        $average = (float) (($summary['average_by_currency'][strtoupper($primary_currency)] ?? 0));
        ?>
        <section class="mds-revenue-reports-metrics" aria-label="<?php esc_attr_e('Revenue summary', 'mds-revenue-reports'); ?>">
            <?php $this->metric(__('Revenue', 'mds-revenue-reports'), $this->repository->money($primary_total, $primary_currency), __('Primary filtered currency total.', 'mds-revenue-reports')); ?>
            <?php $this->metric(__('Orders', 'mds-revenue-reports'), number_format_i18n(absint($summary['order_count'] ?? 0)), __('Orders matching the current filters.', 'mds-revenue-reports')); ?>
            <?php $this->metric(__('Average order', 'mds-revenue-reports'), $this->repository->money($average, $primary_currency), __('Average paid order value for the primary currency.', 'mds-revenue-reports')); ?>
            <?php $this->metric(__('Currencies', 'mds-revenue-reports'), number_format_i18n(count($revenue)), __('Distinct currencies in the filtered results.', 'mds-revenue-reports')); ?>
        </section>
        <?php if (count($revenue) > 1) : ?>
            <section class="mds3-card mds-revenue-reports-currency-note">
                <h2><?php esc_html_e('Multiple currencies detected', 'mds-revenue-reports'); ?></h2>
                <p><?php esc_html_e('Revenue is grouped by currency and is not converted. Export the CSV when you need accounting totals by gateway, date, or currency.', 'mds-revenue-reports'); ?></p>
                <ul>
                    <?php foreach ($revenue as $row) : ?>
                        <li><?php echo esc_html($this->repository->money((float) ($row['total'] ?? 0), (string) ($row['currency'] ?? '')) . ' · ' . sprintf(_n('%d order', '%d orders', absint($row['orders'] ?? 0), 'mds-revenue-reports'), absint($row['orders'] ?? 0))); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php
    }

    private function metric($label, $value, $description) {
        ?>
        <article class="mds3-card mds-revenue-reports-metric">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <small><?php echo esc_html($description); ?></small>
        </article>
        <?php
    }

    private function report_guidance(array $summary) {
        $status_counts = is_array($summary['status_counts'] ?? null) ? $summary['status_counts'] : [];
        $status_map = [];
        foreach ($status_counts as $row) {
            $status_map[sanitize_key((string) ($row['status'] ?? ''))] = [
                'orders' => absint($row['orders'] ?? 0),
                'total' => (float) ($row['total'] ?? 0),
            ];
        }

        $open_orders = absint($status_map['reserved']['orders'] ?? 0) + absint($status_map['pending_payment']['orders'] ?? 0);
        $issue_orders = 0;
        foreach (['failed', 'refunded', 'cancelled', 'denied', 'expired'] as $status) {
            $issue_orders += absint($status_map[$status]['orders'] ?? 0);
        }

        $tips = [];
        if ($open_orders > 0) {
            $tips[] = sprintf(
                /* translators: %d: number of open orders */
                _n('Follow up on %d open reservation before it expires.', 'Follow up on %d open reservations before they expire.', $open_orders, 'mds-revenue-reports'),
                $open_orders
            );
        } else {
            $tips[] = __('No open reservations match these filters. Use the status filter to review pending or expired orders separately.', 'mds-revenue-reports');
        }

        if (!empty($summary['grid_breakdown'])) {
            $top_grid = (array) $summary['grid_breakdown'][0];
            $tips[] = sprintf(
                /* translators: %s: grid title */
                __('Compare the top grid, %s, with lower-performing grids before changing prices or promotion.', 'mds-revenue-reports'),
                (string) ($top_grid['grid_title'] ?? __('Untitled grid', 'mds-revenue-reports'))
            );
        } else {
            $tips[] = __('No grid has matching paid revenue in this range. Broaden the date range or include open statuses to inspect demand.', 'mds-revenue-reports');
        }

        if ($issue_orders > 0) {
            $tips[] = sprintf(
                /* translators: %d: issue order count */
                _n('Review %d failed, refunded, cancelled, denied, or expired order before exporting final totals.', 'Review %d failed, refunded, cancelled, denied, or expired orders before exporting final totals.', $issue_orders, 'mds-revenue-reports'),
                $issue_orders
            );
        } else {
            $tips[] = __('Export the filtered CSV for reconciliation, accountant review, gateway comparison, or tax preparation.', 'mds-revenue-reports');
        }
        ?>
        <section class="mds3-card mds-revenue-reports-guidance" aria-label="<?php esc_attr_e('Revenue review guidance', 'mds-revenue-reports'); ?>">
            <div>
                <h2><?php esc_html_e('Revenue review', 'mds-revenue-reports'); ?></h2>
                <p><?php esc_html_e('Use this pass to decide what needs follow-up before exporting or changing prices.', 'mds-revenue-reports'); ?></p>
            </div>
            <ul>
                <?php foreach ($tips as $tip) : ?>
                    <li><?php echo esc_html($tip); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
    }

    private function trend_chart(array $rows, $currency) {
        if (!$rows) {
            echo '<p class="mds-revenue-reports-empty-line">' . esc_html__('No matching revenue trend data yet.', 'mds-revenue-reports') . '</p>';
            return;
        }

        $values = array_map(static function ($row) {
            return max(0.0, (float) ($row['total'] ?? 0));
        }, $rows);
        $max = max($values) ?: 1.0;
        $points = [];
        $markers = [];
        $count = max(1, count($rows) - 1);
        foreach ($rows as $index => $row) {
            $x = count($rows) > 1 ? ($index / $count) * 100 : 50;
            $y = 92 - (((float) ($row['total'] ?? 0) / $max) * 76);
            $points[] = round($x, 2) . ',' . round($y, 2);
            $markers[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'period' => (string) ($row['period'] ?? ''),
                'orders' => absint($row['orders'] ?? 0),
                'total' => (float) ($row['total'] ?? 0),
            ];
        }

        $total = array_sum($values);
        $orders = array_sum(array_map(static function ($row) {
            return absint($row['orders'] ?? 0);
        }, $rows));
        $best = $markers[0];
        foreach ($markers as $marker) {
            if ($marker['total'] > $best['total']) {
                $best = $marker;
            }
        }
        $latest = end($markers);
        $previous = count($markers) > 1 ? $markers[count($markers) - 2] : null;
        $change_label = __('No previous period', 'mds-revenue-reports');
        if ($previous) {
            $delta = (float) ($latest['total'] ?? 0) - (float) ($previous['total'] ?? 0);
            $delta_label = ($delta >= 0 ? '+' : '-') . $this->repository->money(abs($delta), $currency);
            $change_label = sprintf(
                /* translators: %s: formatted money delta */
                __('%s vs previous period', 'mds-revenue-reports'),
                $delta_label
            );
        }
        ?>
        <div class="mds-revenue-reports-trend-summary">
            <span><strong><?php echo esc_html($this->repository->money($total, $currency)); ?></strong><?php esc_html_e('Trend total', 'mds-revenue-reports'); ?></span>
            <span><strong><?php echo esc_html(number_format_i18n($orders)); ?></strong><?php esc_html_e('Orders in trend', 'mds-revenue-reports'); ?></span>
            <span><strong><?php echo esc_html((string) ($best['period'] ?? '')); ?></strong><?php echo esc_html(sprintf(__('Best period: %s', 'mds-revenue-reports'), $this->repository->money((float) ($best['total'] ?? 0), $currency))); ?></span>
            <span><strong><?php echo esc_html((string) ($latest['period'] ?? '')); ?></strong><?php echo esc_html($change_label); ?></span>
        </div>
        <div class="mds-revenue-reports-chart" role="group" aria-label="<?php esc_attr_e('Revenue trend line chart', 'mds-revenue-reports'); ?>">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false" aria-hidden="true">
                <path class="mds-revenue-reports-chart-grid" d="M0 16H100 M0 54H100 M0 92H100"></path>
                <polyline points="<?php echo esc_attr(implode(' ', $points)); ?>"></polyline>
            </svg>
            <div class="mds-revenue-reports-chart-bars">
                <?php foreach ($rows as $row) : ?>
                    <?php $height = max(2, ((float) ($row['total'] ?? 0) / $max) * 100); ?>
                    <span style="height: <?php echo esc_attr(round($height, 2)); ?>%" title="<?php echo esc_attr((string) ($row['period'] ?? '') . ': ' . $this->repository->money((float) ($row['total'] ?? 0), $currency)); ?>"></span>
                <?php endforeach; ?>
            </div>
            <div class="mds-revenue-reports-chart-points" aria-hidden="false">
                <?php foreach ($markers as $marker) : ?>
                    <?php
                    $orders_label = sprintf(
                        /* translators: %d: order count */
                        _n('%d order', '%d orders', absint($marker['orders']), 'mds-revenue-reports'),
                        absint($marker['orders'])
                    );
                    $label = sprintf(
                        /* translators: 1: period, 2: revenue, 3: formatted order count */
                        __('%1$s: %2$s from %3$s', 'mds-revenue-reports'),
                        (string) $marker['period'],
                        $this->repository->money((float) $marker['total'], $currency),
                        $orders_label
                    );
                    ?>
                    <span tabindex="0" style="left: <?php echo esc_attr((string) $marker['x']); ?>%; top: <?php echo esc_attr((string) $marker['y']); ?>%;" aria-label="<?php echo esc_attr($label); ?>" title="<?php echo esc_attr($label); ?>"></span>
                <?php endforeach; ?>
            </div>
        </div>
        <ol class="mds-revenue-reports-trend-list">
            <?php foreach (array_slice($rows, -6) as $row) : ?>
                <li><span><?php echo esc_html((string) ($row['period'] ?? '')); ?></span><strong><?php echo esc_html($this->repository->money((float) ($row['total'] ?? 0), $currency)); ?></strong></li>
            <?php endforeach; ?>
        </ol>
        <?php
    }

    private function status_breakdown(array $rows) {
        if (!$rows) {
            echo '<p class="mds-revenue-reports-empty-line">' . esc_html__('No matching status data yet.', 'mds-revenue-reports') . '</p>';
            return;
        }

        $max = max(array_map(static function ($row) {
            return absint($row['orders'] ?? 0);
        }, $rows)) ?: 1;
        ?>
        <ul class="mds-revenue-reports-bars">
            <?php foreach ($rows as $row) : ?>
                <?php $orders = absint($row['orders'] ?? 0); ?>
                <li>
                    <div>
                        <span><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($row['status'] ?? '')))); ?></span>
                        <strong><?php echo esc_html(number_format_i18n($orders)); ?></strong>
                    </div>
                    <span class="mds-revenue-reports-bar"><span style="width: <?php echo esc_attr(round(($orders / $max) * 100, 2)); ?>%"></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private function grid_breakdown(array $rows, $currency) {
        if (!$rows) {
            echo '<p class="mds-revenue-reports-empty-line">' . esc_html__('No grid performance data matches these filters.', 'mds-revenue-reports') . '</p>';
            return;
        }

        $max = max(array_map(static function ($row) {
            return (float) ($row['total'] ?? 0);
        }, $rows)) ?: 1.0;
        ?>
        <ul class="mds-revenue-reports-bars">
            <?php foreach ($rows as $row) : ?>
                <?php $total = (float) ($row['total'] ?? 0); ?>
                <li>
                    <div>
                        <span><?php echo esc_html((string) ($row['grid_title'] ?? __('Untitled grid', 'mds-revenue-reports'))); ?></span>
                        <strong><?php echo esc_html($this->repository->money($total, $currency)); ?></strong>
                    </div>
                    <small><?php echo esc_html(sprintf(_n('%d order', '%d orders', absint($row['orders'] ?? 0), 'mds-revenue-reports'), absint($row['orders'] ?? 0))); ?></small>
                    <span class="mds-revenue-reports-bar"><span style="width: <?php echo esc_attr(round(($total / $max) * 100, 2)); ?>%"></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private function orders_table(array $orders) {
        if (!$orders) {
            echo '<p class="mds-revenue-reports-empty-line">' . esc_html__('No matching orders found.', 'mds-revenue-reports') . '</p>';
            return;
        }
        ?>
        <div class="mds-revenue-reports-table-wrap">
            <table class="widefat striped mds-revenue-reports-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Order', 'mds-revenue-reports'); ?></th>
                        <th scope="col"><?php esc_html_e('Date', 'mds-revenue-reports'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'mds-revenue-reports'); ?></th>
                        <th scope="col"><?php esc_html_e('Customer', 'mds-revenue-reports'); ?></th>
                        <th scope="col"><?php esc_html_e('Grid', 'mds-revenue-reports'); ?></th>
                        <th scope="col"><?php esc_html_e('Provider', 'mds-revenue-reports'); ?></th>
                        <th scope="col"><?php esc_html_e('Total', 'mds-revenue-reports'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) : ?>
                        <tr>
                            <th scope="row"><a href="<?php echo esc_url(add_query_arg(['page' => 'mds3-orders', 'order_id' => absint($order['id'] ?? 0)], admin_url('admin.php'))); ?>">#<?php echo esc_html(absint($order['id'] ?? 0)); ?></a></th>
                            <td><?php echo esc_html((string) ($order['created_at'] ?? '')); ?></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($order['status'] ?? '')))); ?></td>
                            <td><?php echo esc_html((string) ($order['email'] ?? __('No email', 'mds-revenue-reports'))); ?></td>
                            <td><?php echo esc_html((string) ($order['grids'] ?? __('Unknown grid', 'mds-revenue-reports'))); ?></td>
                            <td><?php echo esc_html($this->provider_label((string) ($order['commerce_provider'] ?? ''))); ?></td>
                            <td><?php echo esc_html($this->repository->money((float) ($order['total'] ?? 0), (string) ($order['currency'] ?? ''))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function provider_label($provider) {
        $provider = sanitize_key((string) $provider);
        return $provider ? ucwords(str_replace(['-', '_'], ' ', $provider)) : __('Standalone', 'mds-revenue-reports');
    }

    private function query_args(array $args) {
        return [
            'preset' => sanitize_key((string) ($args['preset'] ?? '30')),
            'date_from' => (string) ($args['date_from'] ?? ''),
            'date_to' => (string) ($args['date_to'] ?? ''),
            'status' => sanitize_key((string) ($args['status'] ?? 'income')),
            'grid_id' => absint($args['grid_id'] ?? 0),
            'provider' => sanitize_key((string) ($args['provider'] ?? 'all')),
            's' => sanitize_text_field((string) ($args['search'] ?? '')),
        ];
    }

    private function admin_url(array $args = []) {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::admin_url(self::SLUG, $args);
        }

        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php'));
    }

    private function admin_parent_slug() {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::admin_parent_slug();
        }

        return class_exists('\MillionDollarScript\Core\Runtime') || class_exists('\\MillionDollarScript\\Core\\Runtime') ? 'mds3' : 'options-general.php';
    }
}
