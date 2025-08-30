<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Database {
    
    public static function get_services($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'category_id' => 0,
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'sahayya_services';
        $where = array('1=1');
        
        if ($args['status']) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if ($args['category_id'] > 0) {
            $where[] = $wpdb->prepare('category_id = %d', $args['category_id']);
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $sql .= $wpdb->prepare(" ORDER BY {$args['orderby']} {$args['order']}");
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            if ($args['offset'] > 0) {
                $sql .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        return $wpdb->get_results($sql);
    }
    
    public static function get_service($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_services';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function create_service($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_services';
        
        $defaults = array(
            'name' => '',
            'description' => '',
            'category_id' => 0,
            'base_price' => 0.00,
            'per_person_price' => 0.00,
            'estimated_duration' => 60,
            'travel_charges' => 0.00,
            'waiting_charges' => 0.00,
            'max_dependents' => 10,
            'max_group_size' => 1,
            'enable_group_booking' => 0,
            'available_24_7' => 0,
            'advance_booking_hours' => 2,
            'special_requirements' => '',
            'service_image' => '',
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public static function update_service($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_services';
        
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function delete_service($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_services';
        
        return $wpdb->delete($table, array('id' => $id));
    }
    
    public static function get_categories($status = 'active') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_service_categories';
        $where = '1=1';
        
        if ($status) {
            $where = $wpdb->prepare('status = %s', $status);
        }
        
        return $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY name ASC");
    }
    
    public static function get_dependents($subscriber_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_dependents';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE subscriber_id = %d AND status = 'active' ORDER BY name ASC",
            $subscriber_id
        ));
    }
    
    public static function create_dependent($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_dependents';
        
        $defaults = array(
            'subscriber_id' => 0,
            'name' => '',
            'age' => 0,
            'gender' => 'male',
            'address' => '',
            'phone' => '',
            'medical_conditions' => '',
            'emergency_contact' => '',
            'photo' => '',
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    public static function create_booking($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_bookings';
        
        // Generate unique booking number
        $booking_number = 'SB' . date('Y') . str_pad(wp_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Simplified defaults for existing table structure
        $defaults = array(
            'booking_number' => $booking_number,
            'subscriber_id' => 0,
            'service_id' => 0,
            'dependent_ids' => '',
            'booking_date' => date('Y-m-d'),
            'booking_time' => date('H:i:s'),
            'urgency_level' => 'normal',
            'special_instructions' => '',
            'booking_status' => 'pending'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Add timestamp fields
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        // First check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            error_log('Table does not exist: ' . $table);
            return false;
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        // Log detailed error info
        error_log('Booking creation failed. Result: ' . var_export($result, true));
        error_log('WordPress error: ' . $wpdb->last_error);
        error_log('Data: ' . print_r($data, true));
        error_log('Table: ' . $table);
        
        return false;
    }
    
    public static function get_bookings($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'subscriber_id' => 0,
            'employee_id' => 0,
            'status' => '',
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'sahayya_bookings';
        $where = array('1=1');
        
        if ($args['subscriber_id'] > 0) {
            $where[] = $wpdb->prepare('subscriber_id = %d', $args['subscriber_id']);
        }
        
        if ($args['employee_id'] > 0) {
            $where[] = $wpdb->prepare('assigned_employee_id = %d', $args['employee_id']);
        }
        
        if ($args['status']) {
            $where[] = $wpdb->prepare('booking_status = %s', $args['status']);
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            if ($args['offset'] > 0) {
                $sql .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        return $wpdb->get_results($sql);
    }
    
    public static function update_booking_status($booking_id, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_bookings';
        
        return $wpdb->update(
            $table,
            array('booking_status' => $status),
            array('id' => $booking_id)
        );
    }
    
    public static function assign_employee($booking_id, $employee_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_bookings';
        
        return $wpdb->update(
            $table,
            array(
                'assigned_employee_id' => $employee_id,
                'booking_status' => 'assigned'
            ),
            array('id' => $booking_id)
        );
    }
    
    public static function get_dependent($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_dependents';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function update_dependent($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_dependents';
        
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function delete_dependent($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_dependents';
        
        return $wpdb->delete($table, array('id' => $id));
    }
    
    public static function get_booking($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    // Category CRUD methods
    public static function create_category($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_categories';
        
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_category($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_categories';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function update_category($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_categories';
        
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function delete_category($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_categories';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    public static function get_category_services_count($category_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_services';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE category_id = %d AND status = 'active'",
            $category_id
        ));
    }
    
    // Employee CRUD methods
    public static function create_employee($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_employee($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function get_employee_by_user_id($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
    }
    
    public static function get_employees($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        
        $defaults = array(
            'status' => '',
            'availability_status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if (!empty($args['availability_status'])) {
            $where[] = $wpdb->prepare('availability_status = %s', $args['availability_status']);
        }
        
        $where_clause = implode(' AND ', $where);
        $order_clause = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $query = "SELECT e.*, u.display_name, u.user_email 
                  FROM $table e 
                  LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY $order_clause 
                  $limit_clause";
        
        return $wpdb->get_results($query);
    }
    
    public static function update_employee($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function delete_employee($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    public static function get_employee_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        
        $stats = array();
        
        // Total employees
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        
        // Available employees
        $stats['available'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active' AND availability_status = 'available'");
        
        // On service employees
        $stats['on_service'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active' AND availability_status = 'busy'");
        
        return $stats;
    }
    
    public static function get_employee_bookings_count($employee_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE assigned_employee_id = %d",
            $employee_id
        ));
    }
    
    public static function update_employee_availability($employee_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        
        return $wpdb->update(
            $table,
            array(
                'availability_status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $employee_id)
        );
    }
    
    // Service Extras CRUD methods
    public static function get_service_extras($service_id, $status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_extras';
        
        $where = array('service_id = %d');
        $values = array($service_id);
        
        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY sort_order ASC, name ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    public static function get_service_extra($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_extras';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function create_service_extra($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_extras';
        
        $defaults = array(
            'service_id' => 0,
            'name' => '',
            'description' => '',
            'price' => 0.00,
            'duration_minutes' => 0,
            'max_quantity' => 1,
            'is_required' => 0,
            'sort_order' => 0,
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        $data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function update_service_extra($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_extras';
        
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function delete_service_extra($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_service_extras';
        return $wpdb->delete($table, array('id' => $id));
    }
    
    // Booking Extras methods
    public static function add_booking_extra($booking_id, $extra_id, $quantity, $unit_price) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_booking_extras';
        
        $data = array(
            'booking_id' => $booking_id,
            'extra_id' => $extra_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $quantity * $unit_price,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_booking_extras($booking_id) {
        global $wpdb;
        $booking_extras_table = $wpdb->prefix . 'sahayya_booking_extras';
        $service_extras_table = $wpdb->prefix . 'sahayya_service_extras';
        
        $sql = "SELECT be.*, se.name, se.description 
                FROM $booking_extras_table be 
                LEFT JOIN $service_extras_table se ON be.extra_id = se.id 
                WHERE be.booking_id = %d 
                ORDER BY se.name ASC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $booking_id));
    }
    
    public static function remove_booking_extra($booking_id, $extra_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_booking_extras';
        
        return $wpdb->delete($table, array(
            'booking_id' => $booking_id,
            'extra_id' => $extra_id
        ));
    }
    
    // Invoice CRUD methods
    public static function create_invoice($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_invoices';
        
        // Generate unique invoice number
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad(wp_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $defaults = array(
            'invoice_number' => $invoice_number,
            'booking_id' => 0,
            'customer_id' => 0,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'subtotal' => 0.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'discount_amount' => 0.00,
            'total_amount' => 0.00,
            'paid_amount' => 0.00,
            'balance_amount' => 0.00,
            'currency' => 'INR',
            'status' => 'draft',
            'payment_terms' => '',
            'notes' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        $data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_invoice($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_invoices';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function get_invoice_by_booking($booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_invoices';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE booking_id = %d", $booking_id));
    }
    
    public static function update_invoice($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_invoices';
        
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($table, $data, array('id' => $id));
    }
    
    public static function add_invoice_item($invoice_id, $item_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_invoice_items';
        
        $defaults = array(
            'invoice_id' => $invoice_id,
            'item_type' => 'service',
            'description' => '',
            'quantity' => 1.00,
            'unit_price' => 0.00,
            'total_price' => 0.00,
            'sort_order' => 0
        );
        
        $item_data = wp_parse_args($item_data, $defaults);
        $item_data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert($table, $item_data);
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_invoice_items($invoice_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_invoice_items';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE invoice_id = %d ORDER BY sort_order ASC, id ASC",
            $invoice_id
        ));
    }
    
    // Custom Fields methods
    public static function get_custom_fields($service_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_custom_fields';
        
        $where = "status = 'active'";
        $params = array();
        
        if ($service_id) {
            $where .= " AND (applies_to = 'all_services' OR (applies_to = 'specific_services' AND FIND_IN_SET(%d, service_ids)))";
            $params[] = $service_id;
        }
        
        $sql = "SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, label ASC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }
        
        return $wpdb->get_results($sql);
    }
    
    public static function save_booking_custom_data($booking_id, $field_id, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_booking_custom_data';
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior
        return $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (booking_id, field_id, field_value, created_at) 
             VALUES (%d, %d, %s, %s) 
             ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)",
            $booking_id, $field_id, $value, current_time('mysql')
        ));
    }
    
    public static function get_booking_custom_data($booking_id) {
        global $wpdb;
        $custom_data_table = $wpdb->prefix . 'sahayya_booking_custom_data';
        $custom_fields_table = $wpdb->prefix . 'sahayya_custom_fields';
        
        $sql = "SELECT cd.*, cf.label, cf.field_type 
                FROM $custom_data_table cd 
                LEFT JOIN $custom_fields_table cf ON cd.field_id = cf.id 
                WHERE cd.booking_id = %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $booking_id));
    }
}