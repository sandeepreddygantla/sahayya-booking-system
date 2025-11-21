<?php
/**
 * Test script to verify service creation works
 * Access at: http://yoursite.com/wp-content/plugins/sahayya-booking-system/test-service-add.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is logged in and is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('You must be logged in as admin to run this test');
}

echo "<h1>Service Creation Test</h1>";

// Test 1: Check if database class exists
echo "<h2>Test 1: Database Class</h2>";
if (class_exists('Sahayya_Booking_Database')) {
    echo "✓ Sahayya_Booking_Database class exists<br>";
} else {
    echo "✗ Sahayya_Booking_Database class NOT found<br>";
}

// Test 2: Check if table exists
echo "<h2>Test 2: Database Table</h2>";
global $wpdb;
$table = $wpdb->prefix . 'sahayya_services';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if ($table_exists) {
    echo "✓ Table $table exists<br>";
} else {
    echo "✗ Table $table does NOT exist<br>";
}

// Test 3: Try to create a test service
echo "<h2>Test 3: Create Test Service</h2>";
$test_data = array(
    'name' => 'Test Service ' . date('H:i:s'),
    'description' => 'Automated test service',
    'category_id' => 1,
    'base_price' => 100.00,
    'per_person_price' => 50.00,
    'estimated_duration' => 60,
    'status' => 'active'
);

$service_id = Sahayya_Booking_Database::create_service($test_data);

if ($service_id) {
    echo "✓ Test service created successfully! ID: $service_id<br>";
    echo "Service name: " . $test_data['name'] . "<br>";

    // Verify it's in database
    $service = $wpdb->get_row("SELECT * FROM $table WHERE id = $service_id");
    if ($service) {
        echo "✓ Service verified in database<br>";
        echo "<pre>" . print_r($service, true) . "</pre>";
    }
} else {
    echo "✗ Failed to create service<br>";
    echo "Last database error: " . $wpdb->last_error . "<br>";
}

// Test 4: Check POST handling
echo "<h2>Test 4: Form POST Test</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✓ POST request received<br>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
} else {
    echo "<form method='post'>";
    echo "<input type='text' name='test_field' value='test_value' />";
    echo "<input type='submit' name='submit' value='Test POST' />";
    echo "</form>";
}

echo "<br><a href='" . admin_url('admin.php?page=sahayya-booking-services') . "'>← Back to Services</a>";
