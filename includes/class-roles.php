<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Roles {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'add_role_capabilities'));
        add_action('admin_init', array(__CLASS__, 'handle_role_cleanup'));
    }
    
    public static function handle_role_cleanup() {
        if (current_user_can('administrator') && isset($_GET['sahayya_cleanup_roles']) && wp_verify_nonce($_GET['_wpnonce'], 'cleanup_roles')) {
            self::cleanup_extra_roles();
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&message=roles_cleaned'));
            exit;
        }
    }
    
    public static function add_role_capabilities() {
        // Add capabilities to existing roles
        self::add_admin_capabilities();
        self::add_subscriber_capabilities();
        self::add_employee_capabilities();
    }
    
    public static function cleanup_extra_roles() {
        global $wp_roles;
        
        // Default WordPress roles to keep
        $default_roles = array(
            'administrator',
            'editor', 
            'author',
            'contributor',
            'subscriber'
        );
        
        // Sahayya roles to keep
        $sahayya_roles = array(
            'sahayya_employee'
        );
        
        // Roles to keep
        $keep_roles = array_merge($default_roles, $sahayya_roles);
        
        // Get all roles
        if (!isset($wp_roles)) {
            $wp_roles = wp_roles();
        }
        
        $all_roles = $wp_roles->get_names();
        
        // Remove extra roles
        foreach ($all_roles as $role_key => $role_name) {
            if (!in_array($role_key, $keep_roles)) {
                remove_role($role_key);
            }
        }
        
        // Recreate Sahayya Employee role to ensure it has correct capabilities
        remove_role('sahayya_employee');
        self::create_sahayya_employee_role();
    }
    
    public static function create_sahayya_employee_role() {
        add_role('sahayya_employee', 'Sahayya Employee', array(
            'read' => true,
            'upload_files' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            
            // Sahayya specific capabilities
            'view_sahayya_bookings' => true,
            'manage_assigned_bookings' => true,
            'update_booking_status' => true,
            'add_booking_notes' => true,
            'view_customer_details' => true,
            'access_employee_dashboard' => true,
            'view_assigned_bookings' => true,
            'update_booking_progress' => true,
            'communicate_with_customers' => true,
            'update_location_status' => true,
            'complete_assigned_services' => true
        ));
    }
    
    private static function add_admin_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $capabilities = array(
                'manage_sahayya_bookings',
                'manage_sahayya_services',
                'manage_sahayya_employees',
                'manage_sahayya_settings',
                'view_sahayya_reports',
                'assign_sahayya_bookings',
                'cancel_sahayya_bookings',
                'refund_sahayya_payments'
            );
            
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }
    
    private static function add_subscriber_capabilities() {
        $role = get_role('subscriber');
        if ($role) {
            $capabilities = array(
                'create_sahayya_bookings',
                'manage_sahayya_dependents',
                'view_sahayya_bookings',
                'cancel_own_sahayya_bookings',
                'reschedule_sahayya_bookings'
            );
            
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }
    
    private static function add_employee_capabilities() {
        $role = get_role('sahayya_employee');
        if ($role) {
            $capabilities = array(
                'view_assigned_bookings',
                'update_booking_progress',
                'upload_service_files',
                'communicate_with_customers',
                'update_location_status',
                'complete_assigned_services'
            );
            
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }
    
    public static function user_can_manage_bookings($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_sahayya_bookings');
    }
    
    public static function user_can_create_bookings($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'create_sahayya_bookings');
    }
    
    public static function user_can_manage_services($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_sahayya_services');
    }
    
    public static function is_employee($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('sahayya_employee', $user->roles);
    }
    
    public static function is_subscriber($user_id = 0) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('subscriber', $user->roles);
    }
    
    public static function get_employee_profile($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_employees';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
    }
    
    public static function create_employee_profile($user_id, $data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_employees';
        
        // Generate employee code
        $employee_code = 'EMP' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
        
        $defaults = array(
            'user_id' => $user_id,
            'employee_code' => $employee_code,
            'phone' => '',
            'address' => '',
            'skills' => '',
            'service_areas' => '',
            'availability_status' => 'available',
            'rating' => 5.00,
            'total_services' => 0,
            'license_number' => '',
            'vehicle_details' => '',
            'emergency_contact' => '',
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
}

// Initialize roles
Sahayya_Booking_Roles::init();