<?php
/**
 * Diagnostic helper for checking database tables
 *
 * Usage: Add [sahayya_check_tables] shortcode to any page as admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Diagnostic {

    public function __construct() {
        add_shortcode('sahayya_check_tables', array($this, 'check_tables_shortcode'));
    }

    /**
     * Shortcode to display database table status
     * Only accessible by administrators
     */
    public function check_tables_shortcode() {
        if (!current_user_can('manage_options')) {
            return '<p style="color: red;">This diagnostic tool is only available to administrators.</p>';
        }

        global $wpdb;

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

        $output = '<div style="padding: 20px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">';
        $output .= '<h3>Sahayya Booking System - Database Status</h3>';
        $output .= '<table style="width: 100%; border-collapse: collapse;">';
        $output .= '<tr style="background: #0073aa; color: white;"><th style="padding: 10px; text-align: left;">Table Name</th><th style="padding: 10px; text-align: left;">Status</th><th style="padding: 10px; text-align: left;">Row Count</th></tr>';

        $all_exist = true;

        foreach ($required_tables as $table_suffix => $description) {
            $table_name = $wpdb->prefix . $table_suffix;

            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));

            if ($table_exists) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $status = '<span style="color: green; font-weight: bold;">✓ EXISTS</span>';
                $rows = $row_count . ' rows';
            } else {
                $status = '<span style="color: red; font-weight: bold;">✗ MISSING</span>';
                $rows = 'N/A';
                $all_exist = false;
            }

            $output .= sprintf(
                '<tr style="border-bottom: 1px solid #ddd;"><td style="padding: 10px;">%s<br><small>%s</small></td><td style="padding: 10px;">%s</td><td style="padding: 10px;">%s</td></tr>',
                $table_name,
                $description,
                $status,
                $rows
            );
        }

        $output .= '</table>';

        if (!$all_exist) {
            $output .= '<div style="margin-top: 20px; padding: 15px; background: #ffcccc; border: 1px solid #ff0000; border-radius: 5px;">';
            $output .= '<strong>⚠️ PROBLEM DETECTED:</strong> Some database tables are missing!<br>';
            $output .= '<strong>Solution:</strong> Go to Plugins → Deactivate "Sahayya Booking System" → Then Activate it again.';
            $output .= '</div>';
        } else {
            $output .= '<div style="margin-top: 20px; padding: 15px; background: #ccffcc; border: 1px solid #00aa00; border-radius: 5px;">';
            $output .= '<strong>✓ All database tables exist!</strong> The plugin is properly installed.';
            $output .= '</div>';
        }

        // Show database info
        $output .= '<div style="margin-top: 15px; padding: 10px; background: #e0f0ff; border-left: 4px solid #0073aa;">';
        $output .= '<strong>Database Info:</strong><br>';
        $output .= 'WordPress Table Prefix: <code>' . $wpdb->prefix . '</code><br>';
        $output .= 'Database Name: <code>' . DB_NAME . '</code><br>';
        $output .= 'Plugin Version: <code>' . SAHAYYA_BOOKING_VERSION . '</code>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Force recreate tables (useful for debugging)
     * Can be called programmatically
     */
    public static function force_recreate_tables() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'includes/class-activator.php';
        Sahayya_Booking_Activator::activate();

        return 'Tables recreated successfully!';
    }
}

// Initialize diagnostic class
new Sahayya_Booking_Diagnostic();
