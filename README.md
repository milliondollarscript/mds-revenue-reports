# Million Dollar Script - Revenue Reports

Track Million Dollar Script revenue, order status, grid performance, and exportable income reports.

## Features

- Dedicated admin reporting page.
- Date, status, grid, provider, and search filters.
- Currency-aware totals without hardcoded USD.
- Revenue trend chart, status breakdown, and top-grid summaries.
- Trend summaries with point tooltips, best-period context, and last-year filtering.
- Complete, bounded-memory CSV exports with spreadsheet formula protection.
- Dashboard Paid revenue metric links directly to reports while active.

## Changelog

### 0.1.2 - 2026-07-13

- Stream all matching orders in stable batches instead of silently truncating exports at 5,000 rows.
- Protect text cells from spreadsheet formula injection and remove embedded NUL bytes.
- Validate real calendar dates and calculate date presets in the WordPress site timezone.
- Align report surfaces, tables, charts, and text with Million Dollar Script light and dark admin themes.

### 0.1.1 - 2026-06-05

- Added a Last year preset, clearer revenue review guidance, normal-sized report action buttons, and more informative revenue trend summaries with focusable point tooltips.

## Requirements

- Million Dollar Script 3.0+
- WordPress 6.0+
- PHP 8.1+

## Installation

Upload and activate the extension in WordPress. Open **Million Dollar Script -> Revenue Reports**.

## License

GPL-3.0-or-later.
