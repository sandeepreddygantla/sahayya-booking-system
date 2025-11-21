<?php
/**
 * Database Table Checker
 * Upload this file to your WordPress root and access it via browser
 * Example: http://yoursite.com/wp-content/plugins/sahayya-booking-system/check-tables.php
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Must be admin.');
}

global $wpdb;

echo '<h1>Sahayya Booking System - Database Table Check</h1>';
echo '<style>body{font-family:monospace;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#0073aa;color:white;} .exists{color:green;font-weight:bold;} .missing{color:red;font-weight:bold;}</style>';

$required_tables = array(
    'sahayya_services' => 'Services',
    'sahayya_service_categories' => 'Service Categories',
    'sahayya_service_extras' => 'Service Extras',
    'sahayya_booking_extras' => 'Booking Extras',
    'sahayya_bookings' => 'Bookings',
    'sahayya_dependents' => 'Dependents',
    'sahayya_employees' => 'Employees',
    'sahayya_invoices' => 'Invoices',
    'sahayya_invoice_items' => 'Invoice Items',
    'sahayya_email_notifications' => 'Email Notifications',
    'sahayya_email_logs' => 'Email Logs'
);

echo '<table>';
echo '<tr><th>Table Name</th><th>Description</th><th>Status</th><th>Row Count</th></tr>';

$all_exist = true;
$missing_tables = array();

foreach ($required_tables as $table_suffix => $description) {
    $table_name = $wpdb->prefix . $table_suffix;

    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ));

    if ($table_exists) {
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $status = '<span class="exists">✓ EXISTS</span>';
        $rows = $row_count . ' rows';
    } else {
        $status = '<span class="missing">✗ MISSING</span>';
        $rows = 'N/A';
        $all_exist = false;
        $missing_tables[] = $table_name;
    }

    echo "<tr><td>$table_name</td><td>$description</td><td>$status</td><td>$rows</td></tr>";
}

echo '</table>';

echo '<h2>Database Info</h2>';
echo '<ul>';
echo '<li>WordPress Table Prefix: <strong>' . $wpdb->prefix . '</strong></li>';
echo '<li>Database Name: <strong>' . DB_NAME . '</strong></li>';
echo '<li>Database Host: <strong>' . DB_HOST . '</strong></li>';
echo '<li>Database User: <strong>' . DB_USER . '</strong></li>';
echo '<li>Plugin Version: <strong>' . (defined('SAHAYYA_BOOKING_VERSION') ? SAHAYYA_BOOKING_VERSION : 'Not loaded') . '</strong></li>';
echo '</ul>';

if (!$all_exist) {
    echo '<h2 style="color:red;">⚠️ PROBLEM DETECTED</h2>';
    echo '<p><strong>Missing Tables:</strong></p>';
    echo '<ul>';
    foreach ($missing_tables as $table) {
        echo "<li>$table</li>";
    }
    echo '</ul>';
    echo '<p><strong>Solution:</strong> Run the activation fix below.</p>';

    // Provide a button to manually trigger activation
    if (isset($_GET['force_activate'])) {
        echo '<h2>Forcing Table Creation...</h2>';
        require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'includes/class-activator.php';
        Sahayya_Booking_Activator::activate();
        echo '<p style="color:green;">Activation complete! <a href="?">Refresh to check results</a></p>';
    } else {
        echo '<p><a href="?force_activate=1" style="display:inline-block;padding:10px 20px;background:#0073aa;color:white;text-decoration:none;border-radius:5px;">Force Create Tables Now</a></p>';
    }
} else {
    echo '<h2 style="color:green;">✓ All database tables exist!</h2>';
    echo '<p>The plugin is properly installed.</p>';
}

// Show any WordPress database errors
if ($wpdb->last_error) {
    echo '<h2 style="color:red;">Database Error:</h2>';
    echo '<pre>' . htmlspecialchars($wpdb->last_error) . '</pre>';
}

// Show phpinfo database section
echo '<h2>PHP MySQL Info</h2>';
ob_start();
phpinfo(INFO_MODULES);
$phpinfo = ob_get_clean();
if (preg_match('/<h2>mysqli<\/h2>.*?(?=<h2>|$)/s', $phpinfo, $matches)) {
    echo $matches[0];
}
