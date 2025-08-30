<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email system testing and management
 */
class Sahayya_Email_Test {
    
    public function __construct() {
        add_action('admin_init', array($this, 'handle_test_email'));
    }
    
    /**
     * Handle test email sending
     */
    public function handle_test_email() {
        if (isset($_GET['sahayya_test_email']) && current_user_can('manage_options')) {
            $this->send_test_email();
        }
    }
    
    /**
     * Send test email
     */
    private function send_test_email() {
        $notifications = new Sahayya_Email_Notifications();
        $scheduler = new Sahayya_Email_Scheduler();
        
        // Test booking confirmation email
        $test_variables = array(
            'customer_name' => 'John Doe',
            'booking_number' => 'SB20250001',
            'service_name' => 'Emergency Hospital Visit',
            'service_description' => 'Immediate transportation to hospital with medical escort',
            'booking_date' => 'August 1, 2025',
            'booking_time' => '2:30 PM',
            'urgency_level' => 'Emergency',
            'special_instructions' => 'Patient needs wheelchair assistance',
            'dependents' => array(
                (object)array('name' => 'Jane Doe', 'age' => 75, 'gender' => 'female')
            ),
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Queue test email
        $scheduler->queue_email(array(
            'recipient_email' => $admin_email,
            'recipient_name' => 'Administrator',
            'subject' => 'Test: Booking Confirmation - SB20250001',
            'content' => $notifications->get_template_content('booking-confirmation', $test_variables),
            'notification_type' => 'booking_confirmation'
        ));
        
        // Redirect with success message
        wp_redirect(add_query_arg(array(
            'page' => 'sahayya-bookings',
            'email_test' => 'success'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Get email system status
     */
    public function get_system_status() {
        $scheduler = new Sahayya_Email_Scheduler();
        $stats = $scheduler->get_email_stats(7);
        
        $status = array(
            'email_queue_active' => wp_next_scheduled('sahayya_process_email_queue') !== false,
            'daily_reminders_active' => wp_next_scheduled('sahayya_send_daily_reminders') !== false,
            'weekly_reports_active' => wp_next_scheduled('sahayya_send_weekly_reports') !== false,
            'cleanup_active' => wp_next_scheduled('sahayya_cleanup_old_logs') !== false,
            'stats' => $stats
        );
        
        return $status;
    }
    
    /**
     * Display email system status in admin
     */
    public function display_admin_status() {
        $status = $this->get_system_status();
        
        echo '<div class="card" style="margin: 20px 0;">';
        echo '<h3>📧 Email System Status</h3>';
        
        echo '<table class="widefat">';
        echo '<tr><td>Email Queue Processing</td><td>' . ($status['email_queue_active'] ? '✅ Active' : '❌ Inactive') . '</td></tr>';
        echo '<tr><td>Daily Reminders</td><td>' . ($status['daily_reminders_active'] ? '✅ Active' : '❌ Inactive') . '</td></tr>';
        echo '<tr><td>Weekly Reports</td><td>' . ($status['weekly_reports_active'] ? '✅ Active' : '❌ Inactive') . '</td></tr>';
        echo '<tr><td>Log Cleanup</td><td>' . ($status['cleanup_active'] ? '✅ Active' : '❌ Inactive') . '</td></tr>';
        echo '</table>';
        
        echo '<h4>Email Statistics (Last 7 Days)</h4>';
        echo '<table class="widefat">';
        echo '<tr><td>Emails Sent</td><td>' . $status['stats']['sent_count'] . '</td></tr>';
        echo '<tr><td>Failed Emails</td><td>' . $status['stats']['failed_count'] . '</td></tr>';
        echo '<tr><td>Pending Emails</td><td>' . $status['stats']['pending_count'] . '</td></tr>';
        echo '<tr><td>Success Rate</td><td>' . $status['stats']['success_rate'] . '%</td></tr>';
        echo '</table>';
        
        echo '<p>';
        echo '<a href="' . add_query_arg('sahayya_test_email', '1') . '" class="button button-secondary">Send Test Email</a>';
        echo '</p>';
        
        echo '</div>';
    }
}