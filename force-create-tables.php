<?php
/**
 * Force Create Database Tables
 *
 * This script manually creates all required database tables.
 * Upload to: /wp-content/plugins/sahayya-booking-system/force-create-tables.php
 * Access at: http://yoursite.com/wp-content/plugins/sahayya-booking-system/force-create-tables.php
 *
 * IMPORTANT: Delete this file after running it!
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is logged in and is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('You must be logged in as admin to run this script');
}

echo "<h1>Force Create Database Tables</h1>";
echo "<p>This will create all required Sahayya Booking System database tables.</p>";

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
echo "<p>Character set: $charset_collate</p>";

$tables_created = 0;
$tables_skipped = 0;
$errors = array();

// Define all tables
$table_definitions = array();

// Services table
$table_definitions[$wpdb->prefix . 'sahayya_services'] = "CREATE TABLE {$wpdb->prefix}sahayya_services (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    category_id int(11) DEFAULT 0,
    base_price decimal(10,2) DEFAULT 0.00,
    per_person_price decimal(10,2) DEFAULT 0.00,
    estimated_duration int(11) DEFAULT 60,
    travel_charges decimal(10,2) DEFAULT 0.00,
    waiting_charges decimal(10,2) DEFAULT 0.00,
    max_dependents int(11) DEFAULT 10,
    max_group_size int(11) DEFAULT 1,
    enable_group_booking tinyint(1) DEFAULT 0,
    available_24_7 tinyint(1) DEFAULT 0,
    advance_booking_hours int(11) DEFAULT 2,
    special_requirements text,
    service_image varchar(255),
    status enum('active','inactive') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

// Service categories table - THIS ONE EXISTS
$table_definitions[$wpdb->prefix . 'sahayya_service_categories'] = "CREATE TABLE {$wpdb->prefix}sahayya_service_categories (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    status enum('active','inactive') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

// Dependents table
$table_definitions[$wpdb->prefix . 'sahayya_dependents'] = "CREATE TABLE {$wpdb->prefix}sahayya_dependents (
    id int(11) NOT NULL AUTO_INCREMENT,
    subscriber_id int(11) NOT NULL,
    name varchar(255) NOT NULL,
    age int(11),
    gender enum('male','female','other') DEFAULT 'male',
    address text,
    phone varchar(20),
    medical_conditions text,
    emergency_contact varchar(255),
    photo varchar(255),
    status enum('active','inactive') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY subscriber_id (subscriber_id)
) $charset_collate;";

// Bookings table
$table_definitions[$wpdb->prefix . 'sahayya_bookings'] = "CREATE TABLE {$wpdb->prefix}sahayya_bookings (
    id int(11) NOT NULL AUTO_INCREMENT,
    booking_number varchar(50) NOT NULL UNIQUE,
    subscriber_id int(11) NOT NULL,
    service_id int(11) NOT NULL,
    dependent_ids text NOT NULL,
    group_size int(11) DEFAULT 1,
    booking_date date NOT NULL,
    booking_time time NOT NULL,
    urgency_level enum('normal','urgent','emergency') DEFAULT 'normal',
    special_instructions text,
    base_amount decimal(10,2) DEFAULT 0.00,
    extras_amount decimal(10,2) DEFAULT 0.00,
    tax_amount decimal(10,2) DEFAULT 0.00,
    total_amount decimal(10,2) NOT NULL,
    payment_status enum('pending','paid','failed','refunded') DEFAULT 'pending',
    booking_status enum('pending','confirmed','assigned','in_progress','completed','cancelled') DEFAULT 'pending',
    assigned_employee_id int(11) DEFAULT NULL,
    invoice_id int(11) DEFAULT NULL,
    custom_fields_data text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY subscriber_id (subscriber_id),
    KEY service_id (service_id),
    KEY assigned_employee_id (assigned_employee_id),
    KEY invoice_id (invoice_id)
) $charset_collate;";

// Employees table
$table_definitions[$wpdb->prefix . 'sahayya_employees'] = "CREATE TABLE {$wpdb->prefix}sahayya_employees (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) NOT NULL,
    employee_code varchar(50) NOT NULL UNIQUE,
    phone varchar(20),
    address text,
    skills text,
    service_areas text,
    availability_status enum('available','busy','offline') DEFAULT 'available',
    rating decimal(3,2) DEFAULT 5.00,
    total_services int(11) DEFAULT 0,
    license_number varchar(100),
    vehicle_details text,
    emergency_contact varchar(255),
    status enum('active','inactive','suspended') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY employee_code (employee_code)
) $charset_collate;";

// Service extras table
$table_definitions[$wpdb->prefix . 'sahayya_service_extras'] = "CREATE TABLE {$wpdb->prefix}sahayya_service_extras (
    id int(11) NOT NULL AUTO_INCREMENT,
    service_id int(11) NOT NULL,
    name varchar(255) NOT NULL,
    description text,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    duration_minutes int(11) DEFAULT 0,
    max_quantity int(11) DEFAULT 1,
    is_required tinyint(1) DEFAULT 0,
    sort_order int(11) DEFAULT 0,
    status enum('active','inactive') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY service_id (service_id)
) $charset_collate;";

// Booking extras table
$table_definitions[$wpdb->prefix . 'sahayya_booking_extras'] = "CREATE TABLE {$wpdb->prefix}sahayya_booking_extras (
    id int(11) NOT NULL AUTO_INCREMENT,
    booking_id int(11) NOT NULL,
    extra_id int(11) NOT NULL,
    quantity int(11) DEFAULT 1,
    unit_price decimal(10,2) NOT NULL,
    total_price decimal(10,2) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY booking_id (booking_id),
    KEY extra_id (extra_id)
) $charset_collate;";

// Invoices table
$table_definitions[$wpdb->prefix . 'sahayya_invoices'] = "CREATE TABLE {$wpdb->prefix}sahayya_invoices (
    id int(11) NOT NULL AUTO_INCREMENT,
    invoice_number varchar(50) NOT NULL UNIQUE,
    booking_id int(11) NOT NULL,
    customer_id int(11) NOT NULL,
    issue_date date NOT NULL,
    due_date date NOT NULL,
    subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
    tax_rate decimal(5,2) DEFAULT 0.00,
    tax_amount decimal(10,2) DEFAULT 0.00,
    discount_amount decimal(10,2) DEFAULT 0.00,
    total_amount decimal(10,2) NOT NULL,
    paid_amount decimal(10,2) DEFAULT 0.00,
    balance_amount decimal(10,2) DEFAULT 0.00,
    currency varchar(3) DEFAULT 'INR',
    status enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
    payment_terms text,
    notes text,
    sent_at datetime NULL,
    paid_at datetime NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY invoice_number (invoice_number),
    KEY booking_id (booking_id),
    KEY customer_id (customer_id),
    KEY status (status)
) $charset_collate;";

// Invoice items table
$table_definitions[$wpdb->prefix . 'sahayya_invoice_items'] = "CREATE TABLE {$wpdb->prefix}sahayya_invoice_items (
    id int(11) NOT NULL AUTO_INCREMENT,
    invoice_id int(11) NOT NULL,
    item_type enum('service','extra','tax','discount','other') DEFAULT 'service',
    description text NOT NULL,
    quantity decimal(10,2) DEFAULT 1.00,
    unit_price decimal(10,2) NOT NULL,
    total_price decimal(10,2) NOT NULL,
    sort_order int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY invoice_id (invoice_id)
) $charset_collate;";

// Email notifications table
$table_definitions[$wpdb->prefix . 'sahayya_email_notifications'] = "CREATE TABLE {$wpdb->prefix}sahayya_email_notifications (
    id int(11) NOT NULL AUTO_INCREMENT,
    booking_id int(11) DEFAULT NULL,
    invoice_id int(11) DEFAULT NULL,
    recipient_email varchar(255) NOT NULL,
    recipient_name varchar(255),
    subject varchar(500) NOT NULL,
    content longtext NOT NULL,
    notification_type enum('booking_confirmation','booking_reminder','invoice','payment_confirmation','booking_cancelled','booking_completed','custom') NOT NULL,
    status enum('pending','sent','failed','cancelled') DEFAULT 'pending',
    scheduled_at datetime DEFAULT NULL,
    sent_at datetime DEFAULT NULL,
    error_message text,
    attempts int(11) DEFAULT 0,
    max_attempts int(11) DEFAULT 3,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY booking_id (booking_id),
    KEY invoice_id (invoice_id),
    KEY status (status),
    KEY scheduled_at (scheduled_at),
    KEY notification_type (notification_type)
) $charset_collate;";

// Email logs table
$table_definitions[$wpdb->prefix . 'sahayya_email_logs'] = "CREATE TABLE {$wpdb->prefix}sahayya_email_logs (
    id int(11) NOT NULL AUTO_INCREMENT,
    recipient varchar(255) NOT NULL,
    subject varchar(500) NOT NULL,
    template varchar(100) NOT NULL,
    status enum('sent','failed') DEFAULT 'sent',
    sent_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY recipient (recipient),
    KEY template (template),
    KEY status (status)
) $charset_collate;";

// Create tables
echo "<h2>Creating Tables...</h2>";
foreach ($table_definitions as $table_name => $sql) {
    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

    if ($table_exists) {
        echo "<p>⏭️ <strong>$table_name</strong> - Already exists, skipping</p>";
        $tables_skipped++;
        continue;
    }

    // Create table
    $result = $wpdb->query($sql);

    if ($result === false) {
        echo "<p>❌ <strong>$table_name</strong> - FAILED</p>";
        echo "<p style='color: red; margin-left: 20px;'>Error: " . $wpdb->last_error . "</p>";
        $errors[] = array('table' => $table_name, 'error' => $wpdb->last_error);
    } else {
        echo "<p>✅ <strong>$table_name</strong> - Created successfully</p>";
        $tables_created++;
    }
}

// Summary
echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>✅ Tables created: <strong>$tables_created</strong></p>";
echo "<p>⏭️ Tables skipped (already exist): <strong>$tables_skipped</strong></p>";

if (!empty($errors)) {
    echo "<p>❌ Errors: <strong>" . count($errors) . "</strong></p>";
    echo "<h3>Error Details:</h3>";
    foreach ($errors as $error) {
        echo "<p><strong>" . $error['table'] . ":</strong> " . $error['error'] . "</p>";
    }
}

echo "<hr>";
echo "<h2>Verification</h2>";
echo "<p>Checking all tables...</p>";

$all_tables = array(
    'wp_sahayya_services',
    'wp_sahayya_service_categories',
    'wp_sahayya_dependents',
    'wp_sahayya_bookings',
    'wp_sahayya_employees',
    'wp_sahayya_service_extras',
    'wp_sahayya_booking_extras',
    'wp_sahayya_invoices',
    'wp_sahayya_invoice_items',
    'wp_sahayya_email_notifications',
    'wp_sahayya_email_logs'
);

$existing_count = 0;
foreach ($all_tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists) {
        echo "<p>✓ $table</p>";
        $existing_count++;
    } else {
        echo "<p style='color: red;'>✗ $table - MISSING</p>";
    }
}

echo "<p><strong>Total: $existing_count / 11 tables exist</strong></p>";

if ($existing_count === 11) {
    echo "<h2 style='color: green;'>✅ SUCCESS! All tables created.</h2>";
    echo "<p><a href='" . admin_url('admin.php?page=sahayya-booking-services&action=add') . "'>Now try adding a service</a></p>";
} else {
    echo "<h2 style='color: red;'>⚠️ Some tables are missing. Check the errors above.</h2>";
}

echo "<hr>";
echo "<p><strong>IMPORTANT:</strong> Delete this file after running it!</p>";
echo "<p>File location: /wp-content/plugins/sahayya-booking-system/force-create-tables.php</p>";
?>
