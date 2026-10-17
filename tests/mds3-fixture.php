<?php
/**
 * Revenue Reports repository regression fixture.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/mds-revenue-reports/tests/mds3-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\MillionDollarScript\\Extensions\\RevenueReports\\ReportRepository')) {
    echo wp_json_encode(['skipped' => 'revenue_reports_unavailable']) . "\n";
    return;
}

$repo = new \MillionDollarScript\Extensions\RevenueReports\ReportRepository();
$failures = [];
$fixture_order_ids = [];
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$presets = $repo->preset_options();
$assert(isset($presets['last_year']), 'Last year preset should be available.');

$last_year = (int) current_datetime()->format('Y') - 1;
$args = $repo->args_from_request(['preset' => 'last_year']);
$assert('last_year' === $args['preset'], 'Last year preset should be preserved in normalized args.');
$assert(sprintf('%04d-01-01', $last_year) === $args['date_from'], 'Last year preset should start on January 1 of the previous calendar year.');
$assert(sprintf('%04d-12-31', $last_year) === $args['date_to'], 'Last year preset should end on December 31 of the previous calendar year.');

$custom = $repo->args_from_request([
    'preset' => 'custom',
    'date_from' => '2026-06-05',
    'date_to' => '2026-01-01',
]);
$assert('2026-01-01' === $custom['date_from'], 'Custom ranges should normalize earlier date first.');
$assert('2026-06-05' === $custom['date_to'], 'Custom ranges should normalize later date second.');

$invalid_custom = $repo->args_from_request([
    'preset' => 'custom',
    'date_from' => '2026-02-30',
    'date_to' => 'not-a-date',
]);
$assert('' === $invalid_custom['date_from'], 'Impossible custom dates should be rejected.');
$assert('' === $invalid_custom['date_to'], 'Malformed custom dates should be rejected.');

$money = $repo->money(42.5, 'cad<script>');
$assert(0 === strpos($money, 'CAD '), 'Money formatting should sanitize and preserve the requested currency code.');
$assert(false === strpos($money, '<'), 'Money formatting should not retain HTML from currency input.');

$assert("'=2+5" === \MillionDollarScript\Extensions\RevenueReports\CsvWriter::protect_cell('=2+5'), 'Formula-like cells should be prefixed before CSV output.');
$assert("'  @SUM(A1:A2)" === \MillionDollarScript\Extensions\RevenueReports\CsvWriter::protect_cell('  @SUM(A1:A2)'), 'Whitespace-prefixed formula-like cells should be protected.');
$assert('customer@example.test' === \MillionDollarScript\Extensions\RevenueReports\CsvWriter::protect_cell('customer@example.test'), 'Ordinary CSV cells should be preserved.');
$assert('safevalue' === \MillionDollarScript\Extensions\RevenueReports\CsvWriter::protect_cell("safe\0value"), 'NUL bytes should be removed from CSV cells.');

$csv_stream = fopen('php://temp', 'w+');
\MillionDollarScript\Extensions\RevenueReports\CsvWriter::write_order($csv_stream, [
    'id' => 42,
    'created_at' => '2026-07-13 12:00:00',
    'status' => 'paid',
    'email' => '=HYPERLINK("https://example.test")',
    'currency' => 'cad',
    'subtotal' => 12.5,
    'total' => 12.5,
    'commerce_provider' => 'fixture',
    'commerce_order_id' => '+cmd',
    'grids' => '@grid',
]);
rewind($csv_stream);
$csv_row = fgetcsv($csv_stream, 0, ',', '"', '');
fclose($csv_stream);
$assert("'=HYPERLINK(\"https://example.test\")" === ($csv_row[3] ?? ''), 'CSV writer should protect a formula-like customer email.');
$assert("'+cmd" === ($csv_row[8] ?? ''), 'CSV writer should protect a formula-like provider order ID.');
$assert("'@grid" === ($csv_row[9] ?? ''), 'CSV writer should protect a formula-like grid label.');

global $wpdb;
$orders_table = \MillionDollarScript\Core\Database::table('orders');
$marker = 'revenue-fixture-' . wp_generate_uuid4() . '@example.test';

try {
    for ($index = 0; $index < 3; $index++) {
        $inserted = $wpdb->insert($orders_table, [
            'order_key' => wp_generate_uuid4(),
            'user_id' => null,
            'email' => $marker,
            'status' => 'paid',
            'currency' => 'CAD',
            'subtotal' => 10 + $index,
            'total' => 10 + $index,
            'commerce_provider' => 'fixture',
            'commerce_order_id' => 'fixture-' . $index,
            'metadata' => '{}',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        $assert(false !== $inserted, 'Fixture order should be inserted for export batching.');
        if (false !== $inserted) {
            $fixture_order_ids[] = absint($wpdb->insert_id);
        }
    }

    $export_args = $repo->args_from_request([
        'preset' => 'all',
        'status' => 'all',
        's' => $marker,
    ]);
    $first_batch = $repo->export_rows($export_args, 2);
    $first_ids = array_map('absint', wp_list_pluck($first_batch, 'id'));
    $before_id = $first_batch ? absint(end($first_batch)['id'] ?? 0) : 0;
    $second_batch = $repo->export_rows($export_args, 2, $before_id);
    $second_ids = array_map('absint', wp_list_pluck($second_batch, 'id'));

    $assert(2 === count($first_batch), 'The first export batch should respect its bounded batch size.');
    $assert(1 === count($second_batch), 'The next keyset export batch should return the remaining matching order.');
    $assert(!$first_batch || array_key_exists('grids', $first_batch[0]), 'Exported orders should include a portable grid-label field.');
    $assert(!$second_ids || max($second_ids) < $before_id, 'The next export batch should continue below the previous order ID.');
    $assert(!array_intersect($first_ids, $second_ids), 'Keyset export batches should not repeat orders.');
} finally {
    foreach ($fixture_order_ids as $order_id) {
        $wpdb->delete($orders_table, ['id' => $order_id], ['%d']);
    }
}

echo wp_json_encode([
    'status' => $failures ? 'failed' : 'passed',
    'failures' => $failures,
]) . "\n";
