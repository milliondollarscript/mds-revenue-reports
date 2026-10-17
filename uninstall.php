<?php
/** Revenue Reports uninstall cleanup. */
if (!defined('WP_UNINSTALL_PLUGIN') || !class_exists('\\MillionDollarScript\\Extensions\\CleanupPolicy')) { exit; }
\MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-revenue-reports', [
    'option_prefixes' => ['mds_revenue_reports_'],
    'transient_prefixes' => ['mds_revenue_reports_'],
]);
