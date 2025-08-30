<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Ajax {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Booking related AJAX
        add_action('wp_ajax_sahayya_create_booking', array($this, 'create_booking'));
        add_action('wp_ajax_sahayya_cancel_booking', array($this, 'cancel_booking'));
        add_action('wp_ajax_sahayya_get_booking_details', array($this, 'get_booking_details'));
        
        // Dependent management AJAX
        add_action('wp_ajax_sahayya_add_dependent', array($this, 'add_dependent'));
        add_action('wp_ajax_sahayya_edit_dependent', array($this, 'edit_dependent'));
        add_action('wp_ajax_sahayya_delete_dependent', array($this, 'delete_dependent'));
        
        // Service related AJAX
        add_action('wp_ajax_sahayya_get_service_details', array($this, 'get_service_details'));
        add_action('wp_ajax_nopriv_sahayya_get_service_details', array($this, 'get_service_details'));
        add_action('wp_ajax_sahayya_get_service_extras', array($this, 'get_service_extras'));
        add_action('wp_ajax_nopriv_sahayya_get_service_extras', array($this, 'get_service_extras'));
        
        // Employee related AJAX
        add_action('wp_ajax_sahayya_toggle_employee_availability', array($this, 'toggle_employee_availability'));
        add_action('wp_ajax_sahayya_search_users', array($this, 'search_users'));
        add_action('wp_ajax_sahayya_validate_username', array($this, 'validate_username'));
        add_action('wp_ajax_sahayya_validate_email', array($this, 'validate_email'));
        
        // Custom fields AJAX
        add_action('wp_ajax_sahayya_get_custom_fields', array($this, 'get_custom_fields'));
        add_action('wp_ajax_nopriv_sahayya_get_custom_fields', array($this, 'get_custom_fields'));
        
        // Test AJAX
        add_action('wp_ajax_sahayya_test_ajax', array($this, 'test_ajax'));
        add_action('wp_ajax_nopriv_sahayya_test_ajax', array($this, 'test_ajax'));
        
        // PDF Invoice AJAX
        add_action('wp_ajax_sahayya_download_invoice_pdf', array($this, 'download_invoice_pdf'));
        add_action('wp_ajax_sahayya_preview_invoice_pdf', array($this, 'preview_invoice_pdf'));
        
        // Service Extras Management AJAX  
        add_action('wp_ajax_sahayya_service_extras', array($this, 'handle_service_extras'));
    }
    
    public function create_booking() {
        // Debug: Log received POST data
        error_log('AJAX create_booking called with POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!isset($_POST['sahayya_booking_nonce']) || !wp_verify_nonce($_POST['sahayya_booking_nonce'], 'sahayya_create_booking')) {
            error_log('Nonce verification failed. Nonce: ' . (isset($_POST['sahayya_booking_nonce']) ? $_POST['sahayya_booking_nonce'] : 'NOT SET'));
            wp_send_json_error('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('Please login to book a service');
        }
        
        $user_id = get_current_user_id();
        
        // Validate required fields - check both field name formats
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : intval($_POST['selected_service_id']);
        
        if (isset($_POST['dependent_ids']) && is_array($_POST['dependent_ids'])) {
            $dependent_ids = array_map('intval', $_POST['dependent_ids']);
        } else {
            $dependent_ids_str = isset($_POST['selected_dependent_ids']) ? $_POST['selected_dependent_ids'] : '';
            $dependent_ids = !empty($dependent_ids_str) ? array_map('intval', explode(',', $dependent_ids_str)) : array();
        }
        
        // Remove duplicates and filter out empty values
        $dependent_ids = array_unique(array_filter($dependent_ids));
        $booking_date = sanitize_text_field($_POST['booking_date']);
        $booking_time = sanitize_text_field($_POST['booking_time']);
        
        // Validate required fields
        
        if (empty($service_id)) {
            wp_send_json_error('Please select a service');
        }
        if (empty($dependent_ids)) {
            wp_send_json_error('Please select at least one dependent');
        }
        if (empty($booking_date)) {
            wp_send_json_error('Please select a booking date');
        }
        if (empty($booking_time)) {
            wp_send_json_error('Please select a booking time');
        }
        
        // Check for duplicate submissions (same user, service, date, time within 2 minutes)
        global $wpdb;
        $booking_table = $wpdb->prefix . 'sahayya_bookings';
        $duplicate_check = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $booking_table 
             WHERE subscriber_id = %d 
             AND service_id = %d 
             AND booking_date = %s 
             AND booking_time = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
            $user_id, $service_id, $booking_date, $booking_time
        ));
        
        if ($duplicate_check) {
            wp_send_json_error('A similar booking was just created. Please check your bookings list.');
        }
        
        // Get service details for pricing
        $service = Sahayya_Booking_Database::get_service($service_id);
        if (!$service) {
            wp_send_json_error('Invalid service selected');
        }
        
        // Calculate dependent count
        $dependent_count = count($dependent_ids);
        
        // Check group booking limits with safe property access
        $enable_group_booking = isset($service->enable_group_booking) ? $service->enable_group_booking : 0;
        $max_group_size = isset($service->max_group_size) ? $service->max_group_size : 10;
        $max_dependents = isset($service->max_dependents) ? $service->max_dependents : 10;
        
        if ($enable_group_booking && $dependent_count > $max_group_size) {
            wp_send_json_error('This service allows maximum ' . $max_group_size . ' people in a group. Please select fewer dependents.');
        }
        
        // Check if dependents exceed regular max limit
        if (!$enable_group_booking && $dependent_count > $max_dependents) {
            wp_send_json_error('This service allows maximum ' . $max_dependents . ' dependents.');
        }
        
        // Calculate total amount
        $base_amount = floatval($service->base_price);
        $extras_amount = 0;
        
        // Calculate dependent pricing
        $dependent_price = 0;
        if ($dependent_count > 0) {
            $dependent_price = $dependent_count * floatval($service->per_person_price);
        }
        
        // Handle service extras
        $selected_extras = array();
        if (isset($_POST['service_extras']) && is_array($_POST['service_extras'])) {
            foreach ($_POST['service_extras'] as $extra_id) {
                $extra_id = intval($extra_id);
                $extra = Sahayya_Booking_Database::get_service_extra($extra_id);
                if ($extra && $extra->service_id == $service_id) {
                    $quantity = 1;
                    if (isset($_POST['extra_quantities']) && isset($_POST['extra_quantities'][$extra_id])) {
                        $quantity = max(1, intval($_POST['extra_quantities'][$extra_id]));
                    }
                    
                    $extra_total = floatval($extra->price) * $quantity;
                    $extras_amount += $extra_total;
                    
                    $selected_extras[] = array(
                        'extra_id' => $extra_id,
                        'quantity' => $quantity,
                        'unit_price' => floatval($extra->price),
                        'total_price' => $extra_total
                    );
                }
            }
        }
        
        $total_amount = $base_amount + $extras_amount + $dependent_price;
        
        // Create booking data - simplified to match existing database table structure
        $booking_data = array(
            'subscriber_id' => $user_id,
            'service_id' => $service_id,
            'dependent_ids' => json_encode($dependent_ids),
            'booking_date' => $booking_date,
            'booking_time' => $booking_time,
            'urgency_level' => sanitize_text_field($_POST['urgency_level']),
            'special_instructions' => sanitize_textarea_field($_POST['special_instructions']),
            'booking_status' => 'pending'
        );
        
        // Create booking
        $booking_id = Sahayya_Booking_Database::create_booking($booking_data);
        
        if ($booking_id) {
            // Save booking extras if any were selected
            if (!empty($selected_extras)) {
                foreach ($selected_extras as $extra) {
                    Sahayya_Booking_Database::add_booking_extra(
                        $booking_id,
                        $extra['extra_id'],
                        $extra['quantity'],
                        $extra['unit_price']
                    );
                }
            }
            
            // Get the created booking
            $booking = Sahayya_Booking_Database::get_booking($booking_id);
            
            // Create invoice for this booking with calculated amounts
            $invoice_id = self::create_booking_invoice($booking_id, $user_id, $total_amount, $base_amount, $extras_amount, $selected_extras);
            
            // Trigger email notifications
            do_action('sahayya_booking_created', $booking_id, array(
                'user_id' => $user_id,
                'service_id' => $service_id,
                'dependent_ids' => $dependent_ids,
                'total_amount' => $total_amount
            ));
            
            if ($invoice_id) {
                do_action('sahayya_invoice_created', $invoice_id, $booking_id);
            }
            
            wp_send_json_success(array(
                'message' => 'Booking created successfully',
                'booking_id' => $booking_id,
                'booking_number' => $booking->booking_number,
                'total_amount' => $total_amount,
                'extras_count' => count($selected_extras),
                'invoice_id' => $invoice_id
            ));
        } else {
            wp_send_json_error('Failed to create booking. Please try again.');
        }
    }
    
    public function cancel_booking() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sahayya_booking_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Please login to cancel booking');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $user_id = get_current_user_id();
        
        // Get booking and verify ownership
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        
        if (!$booking || $booking->subscriber_id != $user_id) {
            wp_send_json_error('Invalid booking or access denied');
        }
        
        // Check if booking can be cancelled
        if (!in_array($booking->booking_status, ['pending', 'confirmed'])) {
            wp_send_json_error('This booking cannot be cancelled');
        }
        
        // Update booking status
        $result = Sahayya_Booking_Database::update_booking_status($booking_id, 'cancelled');
        
        if ($result !== false) {
            wp_send_json_success('Booking cancelled successfully');
        } else {
            wp_send_json_error('Failed to cancel booking');
        }
    }
    
    public function add_dependent() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['dependent_nonce'], 'sahayya_add_dependent')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Please login to add dependent');
        }
        
        $user_id = get_current_user_id();
        
        // Validate required fields
        $name = sanitize_text_field($_POST['dependent_name']);
        $age = intval($_POST['dependent_age']);
        $address = sanitize_textarea_field($_POST['dependent_address']);
        
        if (empty($name) || empty($age) || empty($address)) {
            wp_send_json_error('Please fill in all required fields');
        }
        
        // Create dependent data
        $dependent_data = array(
            'subscriber_id' => $user_id,
            'name' => $name,
            'age' => $age,
            'gender' => sanitize_text_field($_POST['dependent_gender']),
            'address' => $address,
            'phone' => sanitize_text_field($_POST['dependent_phone']),
            'medical_conditions' => sanitize_textarea_field($_POST['medical_conditions']),
            'emergency_contact' => sanitize_text_field($_POST['emergency_contact'])
        );
        
        // Create dependent
        $dependent_id = Sahayya_Booking_Database::create_dependent($dependent_data);
        
        if ($dependent_id) {
            $dependent = Sahayya_Booking_Database::get_dependent($dependent_id);
            wp_send_json_success(array(
                'message' => 'Dependent added successfully',
                'dependent' => $dependent
            ));
        } else {
            wp_send_json_error('Failed to add dependent. Please try again.');
        }
    }
    
    public function get_service_details() {
        $service_id = intval($_POST['service_id']);
        $service = Sahayya_Booking_Database::get_service($service_id);
        
        if ($service) {
            wp_send_json_success($service);
        } else {
            wp_send_json_error('Service not found');
        }
    }
    
    public function get_service_extras() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sahayya_extras_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $service_id = intval($_POST['service_id']);
        
        if (!$service_id) {
            wp_send_json_error('Invalid service ID');
        }
        
        $extras = Sahayya_Booking_Database::get_service_extras($service_id, 'active');
        
        if ($extras !== false) {
            wp_send_json_success($extras);
        } else {
            wp_send_json_error('Failed to load service extras');
        }
    }
    
    public function get_custom_fields() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sahayya_fields_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : null;
        
        $custom_fields = Sahayya_Booking_Database::get_custom_fields($service_id);
        
        if ($custom_fields !== false) {
            wp_send_json_success($custom_fields);
        } else {
            wp_send_json_error('Failed to load custom fields');
        }
    }
    
    public function get_booking_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please login to view booking details');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $user_id = get_current_user_id();
        
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
        }
        
        // Check permission (user must own the booking or be admin/employee)
        if ($booking->subscriber_id != $user_id && !current_user_can('manage_sahayya_bookings')) {
            wp_send_json_error('Access denied');
        }
        
        // Get related data
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $dependent_ids = json_decode($booking->dependent_ids, true);
        $dependents = array();
        
        if (!empty($dependent_ids)) {
            foreach ($dependent_ids as $dep_id) {
                $dep = Sahayya_Booking_Database::get_dependent($dep_id);
                if ($dep) $dependents[] = $dep;
            }
        }
        
        $booking_details = array(
            'booking' => $booking,
            'service' => $service,
            'dependents' => $dependents
        );
        
        wp_send_json_success($booking_details);
    }
    
    public function test_ajax() {
        wp_send_json_success('AJAX is working');
    }
    
    public function toggle_employee_availability() {
        if (!wp_verify_nonce($_POST['nonce'], 'toggle_availability')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_sahayya_employees')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $employee_id = intval($_POST['employee_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        if (!in_array($new_status, array('available', 'busy', 'offline'))) {
            wp_send_json_error('Invalid status');
        }
        
        $result = Sahayya_Booking_Database::update_employee_availability($employee_id, $new_status);
        
        if ($result !== false) {
            wp_send_json_success('Availability updated successfully');
        } else {
            wp_send_json_error('Failed to update availability');
        }
    }
    
    public function search_users() {
        if (!wp_verify_nonce($_POST['nonce'], 'search_users')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_sahayya_employees')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $search = sanitize_text_field($_POST['search']);
        
        if (strlen($search) < 2) {
            wp_send_json_error('Search term too short');
        }
        
        // Get users that are not already employees
        $existing_employee_users = array();
        $employees = Sahayya_Booking_Database::get_employees();
        foreach ($employees as $employee) {
            $existing_employee_users[] = $employee->user_id;
        }
        
        $args = array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'exclude' => $existing_employee_users,
            'number' => 10,
            'fields' => array('ID', 'user_login', 'user_email', 'display_name')
        );
        
        $users = get_users($args);
        
        if (empty($users)) {
            wp_send_json_error('No users found');
        }
        
        wp_send_json_success($users);
    }
    
    public function validate_username() {
        if (!wp_verify_nonce($_POST['nonce'], 'employee_validation')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_sahayya_employees')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $username = sanitize_user($_POST['username']);
        
        if (empty($username)) {
            wp_send_json_error('Username is required');
        }
        
        if (username_exists($username)) {
            wp_send_json_error('Username already exists. Please choose a different username.');
        }
        
        wp_send_json_success('Username is available');
    }
    
    public function validate_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'employee_validation')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_sahayya_employees')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (empty($email)) {
            wp_send_json_error('Email is required');
        }
        
        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
        }
        
        if (email_exists($email)) {
            wp_send_json_error('Email address already exists. Please use a different email address.');
        }
        
        wp_send_json_success('Email is available');
    }
    
    private static function create_booking_invoice($booking_id, $customer_id, $total_amount, $base_amount, $extras_amount, $selected_extras) {
        // Create invoice data
        $invoice_data = array(
            'booking_id' => $booking_id,
            'customer_id' => $customer_id,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'subtotal' => $base_amount + $extras_amount,
            'tax_rate' => 0.00, // No tax for now
            'tax_amount' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => $total_amount,
            'paid_amount' => 0.00,
            'balance_amount' => $total_amount,
            'currency' => 'INR',
            'status' => 'draft',
            'payment_terms' => 'Payment due within 30 days',
            'notes' => 'Thank you for choosing our services!'
        );
        
        // Create the invoice
        $invoice_id = Sahayya_Booking_Database::create_invoice($invoice_data);
        
        if ($invoice_id) {
            // Get booking and service details
            $booking = Sahayya_Booking_Database::get_booking($booking_id);
            $service = Sahayya_Booking_Database::get_service($booking->service_id);
            
            // Add base service item to invoice
            Sahayya_Booking_Database::add_invoice_item($invoice_id, array(
                'item_type' => 'service',
                'description' => $service->name . ' (Base Service)',
                'quantity' => 1.00,
                'unit_price' => $base_amount,
                'total_price' => $base_amount,
                'sort_order' => 1
            ));
            
            // Add extras as separate line items
            if (!empty($selected_extras)) {
                $sort_order = 2;
                foreach ($selected_extras as $extra) {
                    $extra_details = Sahayya_Booking_Database::get_service_extra($extra['extra_id']);
                    if ($extra_details) {
                        Sahayya_Booking_Database::add_invoice_item($invoice_id, array(
                            'item_type' => 'extra',
                            'description' => $extra_details->name . ' (Extra Service)',
                            'quantity' => floatval($extra['quantity']),
                            'unit_price' => floatval($extra['unit_price']),
                            'total_price' => floatval($extra['total_price']),
                            'sort_order' => $sort_order++
                        ));
                    }
                }
            }
            
            // Update booking with invoice reference
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'sahayya_bookings',
                array('invoice_id' => $invoice_id),
                array('id' => $booking_id)
            );
        }
        
        return $invoice_id;
    }
    
    public function download_invoice_pdf() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sahayya_invoice_nonce')) {
            http_response_code(403);
            die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            http_response_code(401);
            die('Please login to download invoice');
        }
        
        $invoice_id = intval($_POST['invoice_id']);
        $user_id = get_current_user_id();
        
        // Get invoice and verify ownership
        $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
        
        if (!$invoice) {
            http_response_code(404);
            die('Invoice not found');
        }
        
        // Check permission (user must own the invoice or be admin)
        if ($invoice->customer_id != $user_id && !current_user_can('manage_sahayya_bookings')) {
            http_response_code(403);
            die('Access denied');
        }
        
        try {
            // Include PDF class
            require_once plugin_dir_path(__FILE__) . 'class-pdf-invoice.php';
            
            // Generate and download PDF (this will handle headers and die)
            Sahayya_PDF_Invoice::download_invoice_pdf($invoice_id, true);
            
        } catch (Exception $e) {
            http_response_code(500);
            die('Error generating PDF: ' . $e->getMessage());
        }
        
        // This should never be reached as PDF output dies
        die();
    }
    
    public function preview_invoice_pdf() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'], 'sahayya_invoice_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_die('Please login to preview invoice');
        }
        
        $invoice_id = intval($_GET['invoice_id']);
        $user_id = get_current_user_id();
        
        // Get invoice and verify ownership
        $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
        
        if (!$invoice) {
            wp_die('Invoice not found');
        }
        
        // Check permission (user must own the invoice or be admin)
        if ($invoice->customer_id != $user_id && !current_user_can('manage_sahayya_bookings')) {
            wp_die('Access denied');
        }
        
        try {
            // Include PDF class
            require_once plugin_dir_path(__FILE__) . 'class-pdf-invoice.php';
            
            // Generate PDF for inline display
            $pdf = new Sahayya_PDF_Invoice();
            $pdf->generate_invoice($invoice_id);
            
            $filename = 'invoice-' . $invoice->invoice_number . '.pdf';
            $pdf->Output($filename, 'I');
            
        } catch (Exception $e) {
            wp_die('Error generating PDF: ' . $e->getMessage());
        }
    }
    
    public function handle_service_extras() {
        check_ajax_referer('sahayya_service_extras', 'nonce');
        
        if (!current_user_can('manage_sahayya_services')) {
            wp_send_json_error(__('Insufficient permissions', 'sahayya-booking'));
        }
        
        $action_type = sanitize_text_field($_POST['action_type']);
        $service_id = intval($_POST['service_id']);
        
        switch ($action_type) {
            case 'add':
                $this->add_service_extra($service_id);
                break;
            case 'edit':
                $this->edit_service_extra();
                break;
            case 'delete':
                $this->delete_service_extra();
                break;
            default:
                wp_send_json_error(__('Invalid action', 'sahayya-booking'));
        }
    }
    
    private function add_service_extra($service_id) {
        $data = array(
            'service_id' => $service_id,
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'price' => floatval($_POST['price']),
            'duration_minutes' => intval($_POST['duration_minutes']),
            'max_quantity' => intval($_POST['max_quantity']),
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
            'sort_order' => intval($_POST['sort_order'] ?? 0)
        );
        
        if (empty($data['name']) || $data['price'] < 0) {
            wp_send_json_error(__('Please fill in all required fields', 'sahayya-booking'));
        }
        
        $result = Sahayya_Booking_Database::create_service_extra($data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Extra added successfully', 'sahayya-booking'),
                'extra_id' => $result
            ));
        } else {
            wp_send_json_error(__('Failed to add extra', 'sahayya-booking'));
        }
    }
    
    private function edit_service_extra() {
        $extra_id = intval($_POST['extra_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'price' => floatval($_POST['price']),
            'duration_minutes' => intval($_POST['duration_minutes']),
            'max_quantity' => intval($_POST['max_quantity']),
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
            'sort_order' => intval($_POST['sort_order'] ?? 0)
        );
        
        if (empty($data['name']) || $data['price'] < 0) {
            wp_send_json_error(__('Please fill in all required fields', 'sahayya-booking'));
        }
        
        $result = Sahayya_Booking_Database::update_service_extra($extra_id, $data);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Extra updated successfully', 'sahayya-booking')
            ));
        } else {
            wp_send_json_error(__('Failed to update extra', 'sahayya-booking'));
        }
    }
    
    private function delete_service_extra() {
        $extra_id = intval($_POST['extra_id']);
        
        if (!$extra_id) {
            wp_send_json_error(__('Invalid extra ID', 'sahayya-booking'));
        }
        
        $result = Sahayya_Booking_Database::delete_service_extra($extra_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Extra deleted successfully', 'sahayya-booking')
            ));
        } else {
            wp_send_json_error(__('Failed to delete extra', 'sahayya-booking'));
        }
    }
}