<?php
/**
 * Plugin Name: Million Dollar Script - Revenue Reports
 * Plugin URI: https://milliondollarscript.com/extensions/revenue-reports
 * Description: Track Million Dollar Script revenue, order status, grid performance, and exportable income reports.
 * Version: 0.1.2
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * Text Domain: mds-revenue-reports
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.1.3
 * Requires PHP: 8.1
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: million-dollar-script
 *
 * Requires MDS: 3.0.0
 * Requires MDS API: 1
 * Requires License: No
 * MDS Provides: reporting.revenue finance.reports
 * MDS Requires: platform.core
 * MDS Recommends: mds-woocommerce
 * MDS Requires Service: no
 * MDS Setup Category: reporting
 * MDS Minimum Security Level: manage_options
 * MDS Icon: dashicons-chart-line
 * MDS Accent Color: #2563eb
 *
 * @package MillionDollarScript\Extensions\RevenueReports
 */

namespace MillionDollarScript\Extensions\RevenueReports;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MDS_REVENUE_REPORTS_VERSION')) {
    define('MDS_REVENUE_REPORTS_VERSION', '0.1.2');
}

if (!defined('MDS_REVENUE_REPORTS_FILE')) {
    define('MDS_REVENUE_REPORTS_FILE', __FILE__);
}

if (!defined('MDS_REVENUE_REPORTS_PATH')) {
    define('MDS_REVENUE_REPORTS_PATH', plugin_dir_path(__FILE__));
}

if (!defined('MDS_REVENUE_REPORTS_URL')) {
    define('MDS_REVENUE_REPORTS_URL', plugin_dir_url(__FILE__));
}

if (!defined('MDS_REVENUE_REPORTS_BASENAME')) {
    define('MDS_REVENUE_REPORTS_BASENAME', plugin_basename(__FILE__));
}

function mds_revenue_reports_check_mds_active() {
    return class_exists('\\MillionDollarScript\\Core\\Runtime')
        && \MillionDollarScript\Core\Runtime::is_ready();
}

function mds_revenue_reports_missing_mds_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('Million Dollar Script - Revenue Reports', 'mds-revenue-reports') . '</strong>: ';
    echo esc_html__('This extension requires Million Dollar Script 3.0 to be installed and activated.', 'mds-revenue-reports');
    echo ' <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Manage Plugins', 'mds-revenue-reports') . '</a>';
    echo '</p></div>';
}

function mds_revenue_reports_load_files() {
    require_once MDS_REVENUE_REPORTS_PATH . 'includes/ReportRepository.php';
    require_once MDS_REVENUE_REPORTS_PATH . 'includes/CsvWriter.php';
    require_once MDS_REVENUE_REPORTS_PATH . 'includes/Main.php';
}

function mds_revenue_reports_init() {
    if (!mds_revenue_reports_check_mds_active()) {
        add_action('admin_notices', __NAMESPACE__ . '\\mds_revenue_reports_missing_mds_notice');
        return;
    }

    mds_revenue_reports_load_files();
    Main::instance()->register();
}

function mds_revenue_reports_register_extension() {
    if (!mds_revenue_reports_check_mds_active()) {
        return;
    }

    if (class_exists('\\MillionDollarScript\\Extensions\\Registry')) {
        \MillionDollarScript\Extensions\Registry::register([
            'id' => 'mds-revenue-reports',
            'name' => 'Revenue Reports',
            'description' => 'Track Million Dollar Script revenue, grid performance, order statuses, and CSV exports.',
            'version' => MDS_REVENUE_REPORTS_VERSION,
            'author' => 'Million Dollar Script',
            'url' => 'https://milliondollarscript.com/extensions/revenue-reports',
            'main_file' => __FILE__,
            'premium' => false,
            'license_required' => false,
            'cleanup' => [
                'description' => 'Revenue report settings, saved report state, and cached exports.',
            ],
            'capabilities' => [
                'admin_pages' => true,
                'revenue_reports' => true,
                'csv_export' => true,
            ],
        ]);
    }
}

function mds_revenue_reports_activate() {
    if (!mds_revenue_reports_check_mds_active()) {
        deactivate_plugins(MDS_REVENUE_REPORTS_BASENAME);
        wp_die(
            esc_html__('Million Dollar Script - Revenue Reports requires Million Dollar Script 3.0 to be installed and activated.', 'mds-revenue-reports'),
            esc_html__('Plugin Activation Error', 'mds-revenue-reports'),
            ['back_link' => true]
        );
    }
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\mds_revenue_reports_activate');

add_action('plugins_loaded', __NAMESPACE__ . '\\mds_revenue_reports_init', 20);
add_action('million-dollar-script/register/extensions', __NAMESPACE__ . '\\mds_revenue_reports_register_extension');
