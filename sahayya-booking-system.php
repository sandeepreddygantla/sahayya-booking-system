<?php
/**
 * Plugin Name: Sahayya Booking System
 * Plugin URI: https://sahayyagroup.com
 * Description: Custom booking system for dependent care services with dynamic service management, employee assignment, and progress tracking.
 * Version: 1.0.0
 * Author: Sahayya Group
 * License: GPL v2 or later
 * Text Domain: sahayya-booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SAHAYYA_BOOKING_VERSION', '3.1.1');
define('SAHAYYA_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAHAYYA_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAHAYYA_BOOKING_PLUGIN_FILE', __FILE__);

// Main plugin class
class SahayyaBookingSystem {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('sahayya-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Check and create database tables if missing
        $this->check_database_tables();

        // Include required files
        $this->includes();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Check if database tables exist and create them if missing
     * This ensures tables are created even when plugin is activated via deployment
     */
    private function check_database_tables() {
        $installed_version = get_option('sahayya_booking_db_version', '0');

        // If version doesn't match or tables are missing, create/update them
        if (version_compare($installed_version, SAHAYYA_BOOKING_VERSION, '<')) {
            require_once(SAHAYYA_BOOKING_PLUGIN_DIR . 'includes/class-activator.php');
            Sahayya_Booking_Activator::activate();

            // Update version number
            update_option('sahayya_booking_db_version', SAHAYYA_BOOKING_VERSION);
        }
    }
    
    private function includes() {
        // Core classes
        $this->require_file('includes/class-activator.php');
        $this->require_file('includes/class-deactivator.php');
        $this->require_file('includes/class-database.php');
        $this->require_file('includes/class-roles.php');
        
        // Admin classes
        if (is_admin()) {
            $this->require_file('admin/class-admin.php');
            $this->require_file('admin/class-services.php');
            $this->require_file('admin/class-bookings.php');
            $this->require_file('admin/class-employees.php');
        }
        
        // Frontend classes
        $this->require_file('public/class-frontend.php');
        $this->require_file('public/class-shortcodes.php');
        $this->require_file('public/class-employee-dashboard.php');
        
        // AJAX handlers
        $this->require_file('includes/class-ajax.php');
        
        // Email notifications
        $this->require_file('includes/class-email-notifications.php');
        $this->require_file('includes/class-email-scheduler.php');
    }
    
    private function require_file($file) {
        $file_path = SAHAYYA_BOOKING_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array('Sahayya_Booking_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('Sahayya_Booking_Deactivator', 'deactivate'));
        
        // Initialize classes
        if (is_admin()) {
            new Sahayya_Booking_Admin();
        }
        
        new Sahayya_Booking_Frontend();
        new Sahayya_Booking_Shortcodes();
        new Sahayya_Booking_Ajax();
        new Sahayya_Email_Notifications();
        new Sahayya_Email_Scheduler();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('sahayya-booking-frontend', SAHAYYA_BOOKING_PLUGIN_URL . 'assets/css/frontend.css', array(), SAHAYYA_BOOKING_VERSION);
        wp_enqueue_script('sahayya-booking-frontend', SAHAYYA_BOOKING_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SAHAYYA_BOOKING_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('sahayya-booking-frontend', 'sahayya_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sahayya_booking_nonce'),
            'messages' => array(
                'booking_success' => __('Booking created successfully!', 'sahayya-booking'),
                'booking_error' => __('Error creating booking. Please try again.', 'sahayya-booking'),
                'invalid_selection' => __('Please select at least one dependent.', 'sahayya-booking')
            )
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'sahayya-booking') !== false) {
            wp_enqueue_style('sahayya-booking-admin', SAHAYYA_BOOKING_PLUGIN_URL . 'assets/css/admin.css', array(), SAHAYYA_BOOKING_VERSION);
            wp_enqueue_script('sahayya-booking-admin', SAHAYYA_BOOKING_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SAHAYYA_BOOKING_VERSION, true);
            
            // Enqueue WordPress media uploader
            wp_enqueue_media();
            
            wp_localize_script('sahayya-booking-admin', 'sahayya_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sahayya_admin_nonce')
            ));
        }
    }
}

// Initialize the plugin
new SahayyaBookingSystem();