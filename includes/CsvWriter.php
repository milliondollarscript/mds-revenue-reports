<?php
/**
 * Safe Revenue Reports CSV output.
 *
 * @package MillionDollarScript\Extensions\RevenueReports
 */

namespace MillionDollarScript\Extensions\RevenueReports;

if (!defined('ABSPATH')) {
    exit;
}

final class CsvWriter {

    /**
     * Write the report header row.
     *
     * @param resource $stream Writable output stream.
     * @return void
     */
    public static function write_header($stream) {
        self::write($stream, [
            'Order ID',
            'Date',
            'Status',
            'Email',
            'Currency',
            'Subtotal',
            'Total',
            'Provider',
            'Provider Order ID',
            'Grids',
        ]);
    }

    /**
     * Write one normalized order row.
     *
     * @param resource $stream Writable output stream.
     * @param array    $row    Order report row.
     * @return void
     */
    public static function write_order($stream, array $row) {
        self::write($stream, [
            absint($row['id'] ?? 0),
            self::protect_cell($row['created_at'] ?? ''),
            self::protect_cell($row['status'] ?? ''),
            self::protect_cell($row['email'] ?? ''),
            self::protect_cell(strtoupper((string) ($row['currency'] ?? ''))),
            number_format((float) ($row['subtotal'] ?? 0), 2, '.', ''),
            number_format((float) ($row['total'] ?? 0), 2, '.', ''),
            self::protect_cell($row['commerce_provider'] ?? ''),
            self::protect_cell($row['commerce_order_id'] ?? ''),
            self::protect_cell($row['grids'] ?? ''),
        ]);
    }

    /**
     * Prevent spreadsheet software from interpreting text as a formula.
     *
     * @param mixed $value Raw cell value.
     * @return string
     */
    public static function protect_cell($value) {
        $value = str_replace("\0", '', (string) $value);

        if (preg_match('/^[\x09\x0A\x0D\x20]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Write a row using RFC 4180-compatible escaping.
     *
     * @param resource $stream Writable output stream.
     * @param array    $fields CSV fields.
     * @return void
     */
    private static function write($stream, array $fields) {
        if (!is_resource($stream)) {
            return;
        }

        fputcsv($stream, $fields, ',', '"', '');
    }
}
