<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Activator {
    
    public static function activate() {
        // Enable error logging for debugging
        error_log('Sahayya Booking System: Activation started');

        // Create database tables
        self::create_tables();

        // Create user roles
        self::create_roles();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();

        error_log('Sahayya Booking System: Activation completed');
    }
    
    private static function create_tables() {
        global $wpdb;

        error_log('Sahayya: create_tables() called');

        $charset_collate = $wpdb->get_charset_collate();
        error_log('Sahayya: charset_collate = ' . $charset_collate);
        
        // Services table (enhanced for group bookings)
        $table_services = $wpdb->prefix . 'sahayya_services';
        $sql_services = "CREATE TABLE $table_services (
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
        
        // Service categories table
        $table_categories = $wpdb->prefix . 'sahayya_service_categories';
        $sql_categories = "CREATE TABLE $table_categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Dependents table
        $table_dependents = $wpdb->prefix . 'sahayya_dependents';
        $sql_dependents = "CREATE TABLE $table_dependents (
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
        
        // Bookings table (enhanced for invoices and group bookings)
        $table_bookings = $wpdb->prefix . 'sahayya_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
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
        
        /*
         * Removed unused tables (not implemented in current version):
         * - sahayya_progress_updates (employee GPS tracking - planned for future)
         * - sahayya_payments (payment gateway integration - planned for future)
         * These can be added back when the features are implemented.
         */
        
        // Employee profiles table
        $table_employees = $wpdb->prefix . 'sahayya_employees';
        $sql_employees = "CREATE TABLE $table_employees (
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
        $table_service_extras = $wpdb->prefix . 'sahayya_service_extras';
        $sql_service_extras = "CREATE TABLE $table_service_extras (
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
        
        // Booking extras table (junction table)
        $table_booking_extras = $wpdb->prefix . 'sahayya_booking_extras';
        $sql_booking_extras = "CREATE TABLE $table_booking_extras (
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
        $table_invoices = $wpdb->prefix . 'sahayya_invoices';
        $sql_invoices = "CREATE TABLE $table_invoices (
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
        $table_invoice_items = $wpdb->prefix . 'sahayya_invoice_items';
        $sql_invoice_items = "CREATE TABLE $table_invoice_items (
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
        
        // Email notifications table (used by email scheduler for queued emails)
        $table_email_notifications = $wpdb->prefix . 'sahayya_email_notifications';
        $sql_email_notifications = "CREATE TABLE $table_email_notifications (
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

        /*
         * Removed unused tables (not implemented in current version):
         * - sahayya_email_templates (custom email templates - not implemented)
         * - sahayya_custom_fields (dynamic form fields - not implemented)
         * - sahayya_booking_custom_data (custom field storage - not implemented)
         *
         * These tables can be added in future versions when features are implemented.
         */

        // Use direct SQL queries instead of dbDelta for better compatibility
        // dbDelta is finicky with different MySQL versions and configurations
        error_log('Sahayya: Starting direct SQL table creation');

        $tables_to_create = array(
            $wpdb->prefix . 'sahayya_services' => $sql_services,
            $wpdb->prefix . 'sahayya_service_categories' => $sql_categories,
            $wpdb->prefix . 'sahayya_dependents' => $sql_dependents,
            $wpdb->prefix . 'sahayya_bookings' => $sql_bookings,
            $wpdb->prefix . 'sahayya_employees' => $sql_employees,
            $wpdb->prefix . 'sahayya_service_extras' => $sql_service_extras,
            $wpdb->prefix . 'sahayya_booking_extras' => $sql_booking_extras,
            $wpdb->prefix . 'sahayya_invoices' => $sql_invoices,
            $wpdb->prefix . 'sahayya_invoice_items' => $sql_invoice_items,
            $wpdb->prefix . 'sahayya_email_notifications' => $sql_email_notifications
        );

        foreach ($tables_to_create as $table_full_name => $sql) {
            // Remove IF NOT EXISTS if present (we'll check manually)
            $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TABLE', $sql);

            // Check if table exists first
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_full_name));

            if ($table_exists) {
                error_log("Sahayya: Table $table_full_name already exists, skipping");
                continue;
            }

            // Execute the CREATE TABLE query
            $result = $wpdb->query($sql);

            if ($result === false) {
                error_log("Sahayya: FAILED to create $table_full_name table. Error: " . $wpdb->last_error);
                error_log("Sahayya: SQL was: " . substr($sql, 0, 200) . "...");
            } else {
                error_log("Sahayya: Successfully created $table_full_name table");
            }
        }
        
        // Email logs table
        $table_email_logs = $wpdb->prefix . 'sahayya_email_logs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_email_logs));

        if (!$table_exists) {
            $sql_email_logs = "CREATE TABLE $table_email_logs (
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

            $result = $wpdb->query($sql_email_logs);
            if ($result === false) {
                error_log("Sahayya: FAILED to create email_logs table. Error: " . $wpdb->last_error);
            } else {
                error_log("Sahayya: Successfully created email_logs table");
            }
        }
        
        // Insert default service categories
        self::insert_default_categories();
    }
    
    private static function insert_default_categories() {
        global $wpdb;
        
        $table_categories = $wpdb->prefix . 'sahayya_service_categories';
        
        $default_categories = array(
            array('name' => 'Emergency Services', 'description' => 'Urgent medical and emergency assistance'),
            array('name' => 'Routine Care', 'description' => 'Regular health check-ups and appointments'),
            array('name' => 'Home Services', 'description' => 'In-home care and assistance'),
            array('name' => 'Transportation', 'description' => 'Medical transportation and mobility assistance')
        );
        
        foreach ($default_categories as $category) {
            $wpdb->insert($table_categories, $category);
        }
    }
    
    private static function create_roles() {
        // Create employee role
        add_role('sahayya_employee', 'Sahayya Employee', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
        ));
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_sahayya_bookings');
            $admin_role->add_cap('manage_sahayya_services');
            $admin_role->add_cap('manage_sahayya_employees');
        }
        
        // Add capabilities to subscriber for booking management
        $subscriber_role = get_role('subscriber');
        if ($subscriber_role) {
            $subscriber_role->add_cap('create_sahayya_bookings');
            $subscriber_role->add_cap('manage_sahayya_dependents');
        }
    }
    
    private static function set_default_options() {
        // Plugin settings
        add_option('sahayya_booking_settings', array(
            'business_hours_start' => '09:00',
            'business_hours_end' => '21:00',
            'default_currency' => 'INR',
            'currency_symbol' => '₹',
            'enable_24_7_booking' => false,
            'min_advance_booking' => 2,
            'max_advance_booking' => 30,
            'cancellation_hours' => 24,
            'enable_notifications' => true,
            'admin_notification_email' => get_option('admin_email'),
            'enable_sms' => false,
            'enable_whatsapp' => false
        ));
        
        // Payment settings
        add_option('sahayya_payment_settings', array(
            'enable_online_payment' => true,
            'enable_cash_payment' => true,
            'payment_gateways' => array('stripe', 'paypal', 'razorpay'),
            'test_mode' => true
        ));
    }
}