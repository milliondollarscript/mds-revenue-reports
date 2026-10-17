=== Million Dollar Script - Revenue Reports ===
Contributors: milliondollarscript
Tags: million dollar script, revenue, reports, csv, analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Track Million Dollar Script revenue, order status, grid performance, and exportable income reports.

== Description ==

Revenue Reports adds a dedicated Million Dollar Script admin page with date, status, grid, provider, and search filters. It summarizes paid revenue, matching orders, average order value, currency counts, revenue trends, status breakdowns, top grids, recent matching orders, revenue review guidance, and CSV exports.

The extension reads Million Dollar Script orders instead of querying WooCommerce directly, so it works with standalone checkout, WooCommerce Checkout, and future payment providers that write through the current order layer.

== Installation ==

1. Install and activate Million Dollar Script 3.0 or newer.
2. Upload and activate this extension.
3. Open Million Dollar Script -> Revenue Reports.

== Changelog ==

= 0.1.2 =
* Streams every matching export row in bounded batches and protects spreadsheet cells from formula injection.
* Validates custom calendar dates and calculates presets in the configured WordPress timezone.
* Improves light, dark, and system admin contrast while flattening nested report surfaces.

= 0.1.1 =
* Adds Last year filtering, revenue review guidance, clearer trend summaries, focusable chart point tooltips, and normal-sized report action buttons.

= 0.1.0 =
* Initial local development release.
