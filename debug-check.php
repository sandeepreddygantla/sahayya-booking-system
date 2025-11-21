<?php
/**
 * Debug Checker - Shows exact state of plugin on server
 * Upload and access at: /wp-content/plugins/sahayya-booking-system/debug-check.php
 * DELETE THIS FILE AFTER CHECKING!
 */

// Load WordPress
require_once('../../../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('You must be logged in as admin');
}

echo "<h1>Sahayya Booking System - Debug Check</h1>";
echo "<p>Checking plugin state on: <strong>" . get_site_url() . "</strong></p>";
echo "<hr>";

// 1. Plugin Version
echo "<h2>1. Plugin Version</h2>";
echo "<p>Defined Version: <strong>" . (defined('SAHAYYA_BOOKING_VERSION') ? SAHAYYA_BOOKING_VERSION : 'NOT DEFINED') . "</strong></p>";
echo "<p>Database Version: <strong>" . get_option('sahayya_booking_db_version', 'NOT SET') . "</strong></p>";

// 2. Database Tables
echo "<h2>2. Database Tables</h2>";
global $wpdb;
$required_tables = array(
    'wp_sahayya_services',
    'wp_sahayya_service_categories',
    'wp_sahayya_service_extras',
    'wp_sahayya_booking_extras',
    'wp_sahayya_dependents',
    'wp_sahayya_bookings',
    'wp_sahayya_employees',
    'wp_sahayya_invoices',
    'wp_sahayya_invoice_items',
    'wp_sahayya_email_notifications',
    'wp_sahayya_email_logs'
);

$missing = array();
foreach ($required_tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists) {
        echo "<p>✓ $table</p>";
    } else {
        echo "<p style='color:red;'>✗ $table - MISSING</p>";
        $missing[] = $table;
    }
}
echo "<p><strong>" . (11 - count($missing)) . " / 11 tables exist</strong></p>";

// 3. Check Critical Classes
echo "<h2>3. Critical Classes</h2>";
$classes = array(
    'SahayyaBookingSystem',
    'Sahayya_Booking_Activator',
    'Sahayya_Booking_Database',
    'Sahayya_Booking_Services',
    'Sahayya_Booking_Employees'
);
foreach ($classes as $class) {
    echo "<p>" . (class_exists($class) ? '✓' : '✗') . " $class</p>";
}

// 4. Check Services Class render_page method
echo "<h2>4. Services Class - POST Handling Check</h2>";
if (class_exists('Sahayya_Booking_Services')) {
    $reflection = new ReflectionClass('Sahayya_Booking_Services');
    if ($reflection->hasMethod('render_page')) {
        $method = $reflection->getMethod('render_page');
        $filename = $method->getFileName();
        $start_line = $method->getStartLine();
        $end_line = $method->getEndLine();

        echo "<p>File: $filename</p>";
        echo "<p>Lines: $start_line - $end_line</p>";

        // Read the method source
        $file_contents = file($filename);
        $method_code = implode('', array_slice($file_contents, $start_line - 1, $end_line - $start_line + 1));

        // Check for POST handling
        if (strpos($method_code, 'isset($_POST[\'submit\'])') !== false) {
            echo "<p style='color:green;'>✓ POST handling found in render_page()</p>";

            // Check for return statement
            if (preg_match('/handle.*submission.*\(\);\s*return;/s', $method_code)) {
                echo "<p style='color:green;'>✓ Return statement present after form submission</p>";
            } else {
                echo "<p style='color:red;'>✗ MISSING return statement after form submission!</p>";
            }
        } else {
            echo "<p style='color:red;'>✗ NO POST handling in render_page() - OLD CODE!</p>";
        }

        echo "<details><summary>View render_page() source</summary><pre>" . htmlspecialchars($method_code) . "</pre></details>";
    }
} else {
    echo "<p style='color:red;'>Sahayya_Booking_Services class not found!</p>";
}

// 5. Check Employees Class
echo "<h2>5. Employees Class - POST Handling Check</h2>";
if (class_exists('Sahayya_Booking_Employees')) {
    $reflection = new ReflectionClass('Sahayya_Booking_Employees');
    if ($reflection->hasMethod('render_page')) {
        $method = $reflection->getMethod('render_page');
        $file_contents = file($method->getFileName());
        $method_code = implode('', array_slice($file_contents, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        if (strpos($method_code, 'isset($_POST[\'submit\'])') !== false) {
            echo "<p style='color:green;'>✓ POST handling found</p>";
        } else {
            echo "<p style='color:red;'>✗ NO POST handling - OLD CODE!</p>";
        }

        echo "<details><summary>View render_page() source</summary><pre>" . htmlspecialchars($method_code) . "</pre></details>";
    }
}

// 6. Test Service Creation Directly
echo "<h2>6. Test Direct Service Creation</h2>";
if (class_exists('Sahayya_Booking_Database') && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", 'wp_sahayya_services'))) {
    $test_data = array(
        'name' => 'Debug Test Service ' . time(),
        'description' => 'Auto-created by debug checker',
        'category_id' => 1,
        'base_price' => 100,
        'status' => 'active'
    );

    $service_id = Sahayya_Booking_Database::create_service($test_data);

    if ($service_id) {
        echo "<p style='color:green;'>✓ Test service created! ID: $service_id</p>";
        echo "<p>Database write is working. Issue is in form submission handling.</p>";
    } else {
        echo "<p style='color:red;'>✗ Failed to create test service</p>";
        echo "<p>Database error: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color:red;'>Cannot test - services table missing or Database class not loaded</p>";
}

// 7. File Modification Times
echo "<h2>7. File Modification Times</h2>";
$files_to_check = array(
    'sahayya-booking-system.php' => SAHAYYA_BOOKING_PLUGIN_DIR . 'sahayya-booking-system.php',
    'class-services.php' => SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-services.php',
    'class-employees.php' => SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-employees.php',
    'class-activator.php' => SAHAYYA_BOOKING_PLUGIN_DIR . 'includes/class-activator.php'
);

foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        $mtime = filemtime($path);
        $date = date('Y-m-d H:i:s', $mtime);
        echo "<p>$name: <strong>$date</strong></p>";
    } else {
        echo "<p style='color:red;'>$name: NOT FOUND</p>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";

if (count($missing) > 0) {
    echo "<p style='color:red;'><strong>❌ PROBLEM: Missing tables - " . implode(', ', $missing) . "</strong></p>";
    echo "<p>Visit any admin page to trigger automatic table creation, then refresh this page.</p>";
}

echo "<p><strong>⚠️ DELETE THIS FILE AFTER CHECKING!</strong></p>";
?>
