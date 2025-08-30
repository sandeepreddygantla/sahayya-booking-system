<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Email_Scheduler {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Register custom cron schedules
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
        
        // Hook into WordPress cron
        add_action('sahayya_process_email_queue', array($this, 'process_email_queue'));
        add_action('sahayya_send_daily_reminders', array($this, 'send_daily_reminders'));
        add_action('sahayya_send_weekly_reports', array($this, 'send_weekly_reports'));
        add_action('sahayya_cleanup_old_logs', array($this, 'cleanup_old_logs'));
        
        // Schedule recurring events if not already scheduled
        if (!wp_next_scheduled('sahayya_process_email_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'sahayya_process_email_queue');
        }
        
        if (!wp_next_scheduled('sahayya_send_daily_reminders')) {
            wp_schedule_event(strtotime('today 08:00:00'), 'daily', 'sahayya_send_daily_reminders');
        }
        
        if (!wp_next_scheduled('sahayya_send_weekly_reports')) {
            wp_schedule_event(strtotime('next Monday 09:00:00'), 'weekly', 'sahayya_send_weekly_reports');
        }
        
        if (!wp_next_scheduled('sahayya_cleanup_old_logs')) {
            wp_schedule_event(strtotime('tomorrow 02:00:00'), 'daily', 'sahayya_cleanup_old_logs');
        }
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules($schedules) {
        $schedules['every_5_minutes'] = array(
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes', 'sahayya-booking')
        );
        
        return $schedules;
    }
    
    /**
     * Process email queue - sends scheduled emails
     */
    public function process_email_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sahayya_email_notifications';
        
        // Get pending emails that are due to be sent
        $pending_emails = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             AND (scheduled_at IS NULL OR scheduled_at <= %s)
             AND attempts < max_attempts
             ORDER BY created_at ASC
             LIMIT 50",
            current_time('mysql')
        ));
        
        foreach ($pending_emails as $email) {
            $this->send_scheduled_email($email);
        }
    }
    
    /**
     * Send a scheduled email
     */
    private function send_scheduled_email($email_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sahayya_email_notifications';
        
        // Increment attempt counter
        $wpdb->update(
            $table_name,
            array('attempts' => $email_data->attempts + 1),
            array('id' => $email_data->id)
        );
        
        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('sahayya_company_name', 'Sahayya Booking Services') . ' <' . get_option('sahayya_company_email', get_option('admin_email')) . '>'
        );
        
        // Send email
        $sent = wp_mail($email_data->recipient_email, $email_data->subject, $email_data->content, $headers);
        
        if ($sent) {
            // Mark as sent
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                ),
                array('id' => $email_data->id)
            );
            
            error_log("Sahayya Email: Successfully sent {$email_data->notification_type} to {$email_data->recipient_email}");
        } else {
            // Mark as failed if max attempts reached
            if ($email_data->attempts + 1 >= $email_data->max_attempts) {
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'error_message' => 'Maximum send attempts reached'
                    ),
                    array('id' => $email_data->id)
                );
            }
            
            error_log("Sahayya Email: Failed to send {$email_data->notification_type} to {$email_data->recipient_email}");
        }
    }
    
    /**
     * Send daily reminders
     */
    public function send_daily_reminders() {
        $this->send_booking_reminders();
        $this->send_payment_reminders();
    }
    
    /**
     * Send booking reminders for appointments tomorrow
     */
    private function send_booking_reminders() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'sahayya_bookings';
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // Get bookings for tomorrow that haven't been reminded yet
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, u.user_email, u.display_name, s.name as service_name
             FROM $bookings_table b
             JOIN {$wpdb->users} u ON b.subscriber_id = u.ID
             JOIN {$wpdb->prefix}sahayya_services s ON b.service_id = s.id
             WHERE b.booking_date = %s 
             AND b.booking_status IN ('confirmed', 'pending')
             AND b.id NOT IN (
                 SELECT DISTINCT booking_id 
                 FROM {$wpdb->prefix}sahayya_email_notifications 
                 WHERE booking_id IS NOT NULL 
                 AND notification_type = 'booking_reminder'
                 AND status = 'sent'
             )",
            $tomorrow
        ));
        
        foreach ($bookings as $booking) {
            $this->queue_email(array(
                'booking_id' => $booking->id,
                'recipient_email' => $booking->user_email,
                'recipient_name' => $booking->display_name,
                'subject' => 'Booking Reminder - Tomorrow at ' . date('g:i A', strtotime($booking->booking_time)),
                'content' => $this->get_booking_reminder_content($booking),
                'notification_type' => 'booking_reminder'
            ));
        }
    }
    
    /**
     * Send payment reminders for overdue invoices
     */
    private function send_payment_reminders() {
        global $wpdb;
        
        $invoices_table = $wpdb->prefix . 'sahayya_invoices';
        $today = date('Y-m-d');
        
        // Get overdue invoices that haven't been reminded in the last 7 days
        $overdue_invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.user_email, u.display_name
             FROM $invoices_table i
             JOIN {$wpdb->users} u ON i.customer_id = u.ID
             WHERE i.due_date < %s 
             AND i.status != 'paid'
             AND i.balance_amount > 0
             AND (
                 i.id NOT IN (
                     SELECT DISTINCT invoice_id 
                     FROM {$wpdb->prefix}sahayya_email_notifications 
                     WHERE invoice_id IS NOT NULL 
                     AND notification_type = 'payment_reminder'
                     AND status = 'sent'
                     AND sent_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                 )
             )",
            $today
        ));
        
        foreach ($overdue_invoices as $invoice) {
            $days_overdue = floor((strtotime($today) - strtotime($invoice->due_date)) / (24 * 60 * 60));
            
            $this->queue_email(array(
                'invoice_id' => $invoice->id,
                'recipient_email' => $invoice->user_email,
                'recipient_name' => $invoice->display_name,
                'subject' => 'Payment Overdue - Invoice ' . $invoice->invoice_number,
                'content' => $this->get_payment_reminder_content($invoice, $days_overdue),
                'notification_type' => 'payment_reminder'
            ));
        }
    }
    
    /**
     * Send weekly reports to admin
     */
    public function send_weekly_reports() {
        $admin_email = get_option('sahayya_admin_notification_email', get_option('admin_email'));
        
        $report_data = $this->generate_weekly_report();
        
        $this->queue_email(array(
            'recipient_email' => $admin_email,
            'recipient_name' => 'Administrator',
            'subject' => 'Weekly Booking Report - ' . date('M j, Y'),
            'content' => $this->get_weekly_report_content($report_data),
            'notification_type' => 'custom'
        ));
    }
    
    /**
     * Queue an email for sending
     */
    public function queue_email($email_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sahayya_email_notifications';
        
        $defaults = array(
            'booking_id' => null,
            'invoice_id' => null,
            'recipient_email' => '',
            'recipient_name' => '',
            'subject' => '',
            'content' => '',
            'notification_type' => 'custom',
            'status' => 'pending',
            'scheduled_at' => null,
            'attempts' => 0,
            'max_attempts' => 3
        );
        
        $email_data = wp_parse_args($email_data, $defaults);
        
        $result = $wpdb->insert($table_name, $email_data);
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Schedule an email for future sending
     */
    public function schedule_email($email_data, $send_time) {
        $email_data['scheduled_at'] = date('Y-m-d H:i:s', $send_time);
        return $this->queue_email($email_data);
    }
    
    /**
     * Generate booking reminder content
     */
    private function get_booking_reminder_content($booking) {
        $company_name = get_option('sahayya_company_name', 'Sahayya Booking Services');
        $company_phone = get_option('sahayya_company_phone', '+91 XXXXXXXXXX');
        
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f39c12; color: white; padding: 20px; text-align: center;">
                <h1>⏰ Booking Reminder</h1>
            </div>
            <div style="background: #f9f9f9; padding: 20px;">
                <p>Dear ' . esc_html($booking->display_name) . ',</p>
                <p>This is a friendly reminder about your upcoming appointment:</p>
                <div style="background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f39c12;">
                    <p><strong>Booking Number:</strong> ' . esc_html($booking->booking_number) . '</p>
                    <p><strong>Service:</strong> ' . esc_html($booking->service_name) . '</p>
                    <p><strong>Date:</strong> ' . date('F j, Y', strtotime($booking->booking_date)) . '</p>
                    <p><strong>Time:</strong> ' . date('g:i A', strtotime($booking->booking_time)) . '</p>
                </div>
                <p>We look forward to serving you tomorrow! If you need to reschedule, please contact us at ' . esc_html($company_phone) . '.</p>
                <p>Thank you,<br>' . esc_html($company_name) . '</p>
            </div>
        </div>';
    }
    
    /**
     * Generate payment reminder content
     */
    private function get_payment_reminder_content($invoice, $days_overdue) {
        $company_name = get_option('sahayya_company_name', 'Sahayya Booking Services');
        $invoice_url = admin_url('admin-ajax.php?action=sahayya_preview_invoice_pdf&invoice_id=' . $invoice->id . '&nonce=' . wp_create_nonce('sahayya_invoice_nonce'));
        
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #e74c3c; color: white; padding: 20px; text-align: center;">
                <h1>💳 Payment Overdue</h1>
            </div>
            <div style="background: #f9f9f9; padding: 20px;">
                <p>Dear ' . esc_html($invoice->display_name) . ',</p>
                <p>Your invoice payment is now <strong>' . $days_overdue . ' days overdue</strong>. Please make payment as soon as possible:</p>
                <div style="background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #e74c3c;">
                    <p><strong>Invoice Number:</strong> ' . esc_html($invoice->invoice_number) . '</p>
                    <p><strong>Amount Due:</strong> ₹' . number_format($invoice->balance_amount, 2) . '</p>
                    <p><strong>Due Date:</strong> ' . date('F j, Y', strtotime($invoice->due_date)) . '</p>
                    <p><strong>Days Overdue:</strong> ' . $days_overdue . ' days</p>
                </div>
                <p style="text-align: center;">
                    <a href="' . esc_url($invoice_url) . '" style="display: inline-block; background: #e74c3c; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;">View Invoice</a>
                </p>
                <p>Late fees may apply. Please contact us if you need to discuss payment arrangements.</p>
                <p>Thank you,<br>' . esc_html($company_name) . '</p>
            </div>
        </div>';
    }
    
    /**
     * Generate weekly report data
     */
    private function generate_weekly_report() {
        global $wpdb;
        
        $week_start = date('Y-m-d', strtotime('last Monday'));
        $week_end = date('Y-m-d', strtotime('this Sunday'));
        
        // Get booking statistics
        $bookings_table = $wpdb->prefix . 'sahayya_bookings';
        $invoices_table = $wpdb->prefix . 'sahayya_invoices';
        
        $new_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE DATE(created_at) BETWEEN %s AND %s",
            $week_start, $week_end
        ));
        
        $completed_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE booking_status = 'completed' AND DATE(updated_at) BETWEEN %s AND %s",
            $week_start, $week_end
        ));
        
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(paid_amount) FROM $invoices_table WHERE status = 'paid' AND DATE(paid_at) BETWEEN %s AND %s",
            $week_start, $week_end
        ));
        
        $pending_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(balance_amount) FROM $invoices_table WHERE status != 'paid' AND balance_amount > 0"
        ));
        
        return array(
            'week_start' => $week_start,
            'week_end' => $week_end,
            'new_bookings' => $new_bookings ?: 0,
            'completed_bookings' => $completed_bookings ?: 0,
            'total_revenue' => $total_revenue ?: 0,
            'pending_payments' => $pending_payments ?: 0
        );
    }
    
    /**
     * Generate weekly report content
     */
    private function get_weekly_report_content($data) {
        $company_name = get_option('sahayya_company_name', 'Sahayya Booking Services');
        
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #2c3e50; color: white; padding: 20px; text-align: center;">
                <h1>📊 Weekly Report</h1>
                <p>' . date('F j', strtotime($data['week_start'])) . ' - ' . date('F j, Y', strtotime($data['week_end'])) . '</p>
            </div>
            <div style="background: #f9f9f9; padding: 20px;">
                <h2>Booking Statistics</h2>
                <div style="display: flex; gap: 20px; margin: 20px 0;">
                    <div style="background: white; padding: 15px; border-left: 4px solid #3498db; flex: 1;">
                        <h3 style="margin: 0; color: #3498db;">New Bookings</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 5px 0;">' . $data['new_bookings'] . '</p>
                    </div>
                    <div style="background: white; padding: 15px; border-left: 4px solid #27ae60; flex: 1;">
                        <h3 style="margin: 0; color: #27ae60;">Completed</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 5px 0;">' . $data['completed_bookings'] . '</p>
                    </div>
                </div>
                
                <h2>Financial Summary</h2>
                <div style="display: flex; gap: 20px; margin: 20px 0;">
                    <div style="background: white; padding: 15px; border-left: 4px solid #2ecc71; flex: 1;">
                        <h3 style="margin: 0; color: #2ecc71;">Revenue</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 5px 0;">₹' . number_format($data['total_revenue'], 2) . '</p>
                    </div>
                    <div style="background: white; padding: 15px; border-left: 4px solid #e74c3c; flex: 1;">
                        <h3 style="margin: 0; color: #e74c3c;">Pending Payments</h3>
                        <p style="font-size: 24px; font-weight: bold; margin: 5px 0;">₹' . number_format($data['pending_payments'], 2) . '</p>
                    </div>
                </div>
                
                <p>This automated report was generated by ' . esc_html($company_name) . ' booking system.</p>
            </div>
        </div>';
    }
    
    /**
     * Cleanup old email logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'sahayya_email_logs';
        $notifications_table = $wpdb->prefix . 'sahayya_email_notifications';
        
        // Delete logs older than 90 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $logs_table WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        ));
        
        // Delete old sent notifications older than 30 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $notifications_table WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
        
        // Delete old failed notifications older than 7 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $notifications_table WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ));
        
        error_log('Sahayya Email: Cleaned up old email logs and notifications');
    }
    
    /**
     * Get email statistics
     */
    public function get_email_stats($days = 30) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'sahayya_email_logs';
        $notifications_table = $wpdb->prefix . 'sahayya_email_notifications';
        
        $stats = array();
        
        // Sent emails in last X days
        $stats['sent_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table WHERE status = 'sent' AND sent_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // Failed emails in last X days
        $stats['failed_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table WHERE status = 'failed' AND sent_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // Pending emails
        $stats['pending_count'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $notifications_table WHERE status = 'pending'"
        );
        
        // Success rate
        $total_attempts = $stats['sent_count'] + $stats['failed_count'];
        $stats['success_rate'] = $total_attempts > 0 ? round(($stats['sent_count'] / $total_attempts) * 100, 2) : 0;
        
        return $stats;
    }
}