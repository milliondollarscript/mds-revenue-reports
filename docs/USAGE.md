# Revenue Reports

Revenue Reports adds a dedicated Million Dollar Script admin page for reviewing paid income, open reservations, issue statuses, grid performance, and CSV exports.

## Practical Examples

- Compare paid revenue and order volume across several grids to identify which campaign or placement format performs best.
- Reconcile Million Dollar Script orders with a payment provider by filtering on provider and exporting the matching order references.
- Prepare a bounded date-range CSV for an accountant while keeping currencies separate and highlighting reservations or issue states that still need attention.

### Example workflow: prepare a monthly revenue review

1. Open **Million Dollar Script -> Revenue Reports** and select **This month**.
2. Filter by a grid or payment provider when reconciling one campaign, then inspect the revenue trend, status breakdown, recent orders, and review prompts.
3. Resolve or investigate open reservations and issue statuses from the linked order records.
4. Click **Export CSV** to download the filtered rows for accounting review. Each stored currency remains separate; the extension does not invent conversion rates.

## Requirements

- Million Dollar Script 3.0 or newer.
- Million Dollar Script order tables created by setup or installer repair.
- Administrator access to WordPress.

## Open Reports

After activation, open **Million Dollar Script -> Revenue Reports**.

The dashboard **Paid revenue** metric also links to Revenue Reports while the extension is active.

## Filters

Use the filters at the top of the page to narrow reports by:

- Date range.
- Order status or grouped status.
- Grid.
- Payment provider.
- Search text for order IDs, customer email, provider order IDs, grid names, or grid slugs.

Date presets include Last 7 days, Last 30 days, Last 90 days, This month, This year, Last year, All time, and Custom range.

## Revenue Review

The Revenue review panel highlights common follow-up work before export, such as open reservations, issue statuses, and top-grid comparison. Use it as a quick checklist before changing prices, sending reminders, or preparing accounting files.

The Revenue trend panel shows the selected currency total, matching order count, best period, latest period, and focusable chart points with period-level revenue and order counts.

The Status breakdown intentionally ignores only the status filter so administrators can compare all outcomes for the selected date, grid, provider, and search criteria. Revenue totals, recent orders, trends, and exports continue to respect the selected status filter.

## Currency Handling

Revenue Reports does not assume USD and does not convert currencies. It groups revenue by the currency stored on each Million Dollar Script order. If multiple currencies match the current filters, review each currency total separately or export the CSV for accounting review.

## CSV Exports

Click **Export CSV** to download matching orders. The export includes order ID, date, status, email, currency, subtotal, total, provider, provider order ID, and grid labels.

Exports stream every matching order in bounded batches rather than stopping at a fixed row limit. For a very large report, narrow the date range when the web host imposes a short request timeout.

Text cells that could be interpreted as spreadsheet formulas are escaped before download. This protection remains important when customer emails, provider references, or administrator-created grid names are opened in spreadsheet software.

Use the export for reconciliation, accountant review, gateway comparison, or tax preparation. Confirm tax treatment and reporting obligations with a qualified professional.

## Privacy And Security

Reports are visible only to administrators with `manage_options`. CSV exports use WordPress admin nonces and the same capability check.

Exported files may include customer email addresses and order identifiers. Store exported files securely and delete them when no longer needed.

## Developer Notes

Revenue Reports reads current Million Dollar Script order and order-item tables. It does not query WooCommerce directly, so it works with standalone/manual orders, WooCommerce Checkout orders, and future payment providers that write through the Million Dollar Script order layer.

The extension overrides the dashboard Paid revenue metric URL with the `million-dollar-script/dashboard/paid/revenue/url` filter only while active.
