<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Email_Notifications {
    
    private $plugin_path;
    private $templates_dir;
    
    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->templates_dir = $this->plugin_path . '../templates/emails/';
        
        // Create templates directory if it doesn't exist
        if (!file_exists($this->templates_dir)) {
            wp_mkdir_p($this->templates_dir);
        }
        
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Hook into booking events
        add_action('sahayya_booking_created', array($this, 'send_booking_confirmation'), 10, 2);
        add_action('sahayya_booking_status_changed', array($this, 'send_status_change_notification'), 10, 3);
        add_action('sahayya_invoice_created', array($this, 'send_invoice_notification'), 10, 2);
        add_action('sahayya_payment_received', array($this, 'send_payment_confirmation'), 10, 2);
        
        // Schedule reminder emails
        add_action('sahayya_send_booking_reminder', array($this, 'send_booking_reminder'), 10, 1);
        add_action('sahayya_send_payment_reminder', array($this, 'send_payment_reminder'), 10, 1);
    }
    
    /**
     * Send booking confirmation email
     */
    public function send_booking_confirmation($booking_id, $customer_data) {
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $customer = get_userdata($booking->subscriber_id);
        
        // Get dependents
        $dependent_ids = json_decode($booking->dependent_ids, true);
        $dependents = array();
        if (!empty($dependent_ids)) {
            foreach ($dependent_ids as $dep_id) {
                $dep = Sahayya_Booking_Database::get_dependent($dep_id);
                if ($dep) $dependents[] = $dep;
            }
        }
        
        // Template variables
        $variables = array(
            'customer_name' => $customer->display_name,
            'booking_number' => $booking->booking_number,
            'service_name' => $service->name,
            'service_description' => $service->description,
            'booking_date' => date('F j, Y', strtotime($booking->booking_date)),
            'booking_time' => date('g:i A', strtotime($booking->booking_time)),
            'urgency_level' => ucfirst($booking->urgency_level),
            'special_instructions' => $booking->special_instructions,
            'dependents' => $dependents,
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        // Send to customer
        $this->send_email(
            $customer->user_email,
            'Booking Confirmation - ' . $booking->booking_number,
            'booking-confirmation',
            $variables
        );
        
        // Send to admin
        $admin_email = get_option('sahayya_admin_notification_email', get_option('admin_email'));
        $this->send_email(
            $admin_email,
            'New Booking Received - ' . $booking->booking_number,
            'booking-admin-notification',
            $variables
        );
        
        // Schedule reminder email (24 hours before service)
        $reminder_time = strtotime($booking->booking_date . ' ' . $booking->booking_time) - (24 * 60 * 60);
        if ($reminder_time > time()) {
            wp_schedule_single_event($reminder_time, 'sahayya_send_booking_reminder', array($booking_id));
        }
    }
    
    /**
     * Send booking status change notification
     */
    public function send_status_change_notification($booking_id, $old_status, $new_status) {
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $customer = get_userdata($booking->subscriber_id);
        
        $variables = array(
            'customer_name' => $customer->display_name,
            'booking_number' => $booking->booking_number,
            'service_name' => $service->name,
            'old_status' => ucfirst($old_status),
            'new_status' => ucfirst($new_status),
            'booking_date' => date('F j, Y', strtotime($booking->booking_date)),
            'booking_time' => date('g:i A', strtotime($booking->booking_time)),
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        $this->send_email(
            $customer->user_email,
            'Booking Status Update - ' . $booking->booking_number,
            'booking-status-change',
            $variables
        );
    }
    
    /**
     * Send invoice notification
     */
    public function send_invoice_notification($invoice_id, $booking_id) {
        $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $customer = get_userdata($invoice->customer_id);
        
        $variables = array(
            'customer_name' => $customer->display_name,
            'invoice_number' => $invoice->invoice_number,
            'booking_number' => $booking->booking_number,
            'service_name' => $service->name,
            'invoice_amount' => number_format($invoice->total_amount, 2),
            'due_date' => date('F j, Y', strtotime($invoice->due_date)),
            'invoice_url' => admin_url('admin-ajax.php?action=sahayya_preview_invoice_pdf&invoice_id=' . $invoice_id . '&nonce=' . wp_create_nonce('sahayya_invoice_nonce')),
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        $this->send_email(
            $customer->user_email,
            'Invoice Generated - ' . $invoice->invoice_number,
            'invoice-notification',
            $variables
        );
        
        // Schedule payment reminder (7 days before due date)
        $reminder_time = strtotime($invoice->due_date) - (7 * 24 * 60 * 60);
        if ($reminder_time > time() && $invoice->status !== 'paid') {
            wp_schedule_single_event($reminder_time, 'sahayya_send_payment_reminder', array($invoice_id));
        }
    }
    
    /**
     * Send payment confirmation
     */
    public function send_payment_confirmation($invoice_id, $payment_data) {
        $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
        $customer = get_userdata($invoice->customer_id);
        
        $variables = array(
            'customer_name' => $customer->display_name,
            'invoice_number' => $invoice->invoice_number,
            'payment_amount' => number_format($payment_data['amount'], 2),
            'payment_date' => date('F j, Y'),
            'payment_method' => $payment_data['method'],
            'transaction_id' => $payment_data['transaction_id'],
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        $this->send_email(
            $customer->user_email,
            'Payment Confirmation - ' . $invoice->invoice_number,
            'payment-confirmation',
            $variables
        );
    }
    
    /**
     * Send booking reminder
     */
    public function send_booking_reminder($booking_id) {
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $customer = get_userdata($booking->subscriber_id);
        
        // Only send if booking is still active
        if (!in_array($booking->booking_status, ['confirmed', 'pending'])) {
            return;
        }
        
        $variables = array(
            'customer_name' => $customer->display_name,
            'booking_number' => $booking->booking_number,
            'service_name' => $service->name,
            'booking_date' => date('F j, Y', strtotime($booking->booking_date)),
            'booking_time' => date('g:i A', strtotime($booking->booking_time)),
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        $this->send_email(
            $customer->user_email,
            'Booking Reminder - Tomorrow at ' . date('g:i A', strtotime($booking->booking_time)),
            'booking-reminder',
            $variables
        );
    }
    
    /**
     * Send payment reminder
     */
    public function send_payment_reminder($invoice_id) {
        $invoice = Sahayya_Booking_Database::get_invoice($invoice_id);
        $customer = get_userdata($invoice->customer_id);
        
        // Only send if invoice is still unpaid
        if ($invoice->status === 'paid' || $invoice->balance_amount <= 0) {
            return;
        }
        
        $variables = array(
            'customer_name' => $customer->display_name,
            'invoice_number' => $invoice->invoice_number,
            'amount_due' => number_format($invoice->balance_amount, 2),
            'due_date' => date('F j, Y', strtotime($invoice->due_date)),
            'days_until_due' => max(0, floor((strtotime($invoice->due_date) - time()) / (24 * 60 * 60))),
            'invoice_url' => admin_url('admin-ajax.php?action=sahayya_preview_invoice_pdf&invoice_id=' . $invoice_id . '&nonce=' . wp_create_nonce('sahayya_invoice_nonce')),
            'company_name' => get_option('sahayya_company_name', 'Sahayya Booking Services'),
            'company_phone' => get_option('sahayya_company_phone', '+91 XXXXXXXXXX'),
            'company_email' => get_option('sahayya_company_email', get_option('admin_email')),
            'site_url' => get_site_url()
        );
        
        $this->send_email(
            $customer->user_email,
            'Payment Reminder - Invoice Due Soon',
            'payment-reminder',
            $variables
        );
    }
    
    /**
     * Send email using template
     */
    private function send_email($to, $subject, $template, $variables = array()) {
        $html_content = $this->get_template_content($template, $variables);
        $text_content = $this->get_template_content($template . '-text', $variables);
        
        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('sahayya_company_name', 'Sahayya Booking Services') . ' <' . get_option('sahayya_company_email', get_option('admin_email')) . '>'
        );
        
        // Send HTML email
        $sent = wp_mail($to, $subject, $html_content, $headers);
        
        // Log email activity
        $this->log_email_activity($to, $subject, $template, $sent);
        
        return $sent;
    }
    
    /**
     * Get template content with variable substitution
     */
    private function get_template_content($template, $variables = array()) {
        $template_file = $this->templates_dir . $template . '.php';
        
        // Create template if it doesn't exist
        if (!file_exists($template_file)) {
            $this->create_default_template($template);
        }
        
        // Start output buffering
        ob_start();
        
        // Extract variables for use in template
        extract($variables);
        
        // Include template file
        include $template_file;
        
        // Get content and clean buffer
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * Create default email templates
     */
    private function create_default_template($template) {
        $template_content = '';
        
        switch ($template) {
            case 'booking-confirmation':
                $template_content = $this->get_booking_confirmation_template();
                break;
            case 'booking-admin-notification':
                $template_content = $this->get_booking_admin_notification_template();
                break;
            case 'booking-status-change':
                $template_content = $this->get_booking_status_change_template();
                break;
            case 'invoice-notification':
                $template_content = $this->get_invoice_notification_template();
                break;
            case 'payment-confirmation':
                $template_content = $this->get_payment_confirmation_template();
                break;
            case 'booking-reminder':
                $template_content = $this->get_booking_reminder_template();
                break;
            case 'payment-reminder':
                $template_content = $this->get_payment_reminder_template();
                break;
            default:
                $template_content = $this->get_default_template();
                break;
        }
        
        file_put_contents($this->templates_dir . $template . '.php', $template_content);
    }
    
    /**
     * Log email activity
     */
    private function log_email_activity($to, $subject, $template, $sent) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sahayya_email_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'recipient' => $to,
                'subject' => $subject,
                'template' => $template,
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Template: Booking Confirmation
     */
    private function get_booking_confirmation_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .booking-details { background: white; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #667eea; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        h1, h2 { margin-top: 0; }
        .dependents { margin: 15px 0; }
        .dependent-item { background: #f0f0f0; padding: 10px; margin: 5px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Booking Confirmed!</h1>
            <p>Thank you for choosing <?php echo esc_html($company_name); ?></p>
        </div>
        
        <div class="content">
            <p>Dear <?php echo esc_html($customer_name); ?>,</p>
            
            <p>Your booking has been confirmed! Here are the details:</p>
            
            <div class="booking-details">
                <h2>Booking Details</h2>
                <p><strong>Booking Number:</strong> <?php echo esc_html($booking_number); ?></p>
                <p><strong>Service:</strong> <?php echo esc_html($service_name); ?></p>
                <p><strong>Date:</strong> <?php echo esc_html($booking_date); ?></p>
                <p><strong>Time:</strong> <?php echo esc_html($booking_time); ?></p>
                <p><strong>Urgency Level:</strong> <?php echo esc_html($urgency_level); ?></p>
                
                <?php if (!empty($dependents)): ?>
                <div class="dependents">
                    <h3>Selected Dependents:</h3>
                    <?php foreach ($dependents as $dependent): ?>
                        <div class="dependent-item">
                            <strong><?php echo esc_html($dependent->name); ?></strong> 
                            (<?php echo esc_html($dependent->age); ?> years, <?php echo esc_html(ucfirst($dependent->gender)); ?>)
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($special_instructions)): ?>
                <p><strong>Special Instructions:</strong><br><?php echo nl2br(esc_html($special_instructions)); ?></p>
                <?php endif; ?>
            </div>
            
            <p>We will contact you shortly to confirm the appointment details. If you have any questions, please don\'t hesitate to reach out.</p>
            
            <a href="<?php echo esc_url($site_url); ?>" class="button">View My Bookings</a>
        </div>
        
        <div class="footer">
            <p><strong><?php echo esc_html($company_name); ?></strong></p>
            <p>Phone: <?php echo esc_html($company_phone); ?> | Email: <?php echo esc_html($company_email); ?></p>
            <p><a href="<?php echo esc_url($site_url); ?>">Visit our website</a></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template: Admin Notification
     */
    private function get_booking_admin_notification_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Booking Received</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e74c3c; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 New Booking Received</h1>
        </div>
        
        <div class="content">
            <p>A new booking has been created:</p>
            
            <div class="booking-details">
                <p><strong>Booking Number:</strong> <?php echo esc_html($booking_number); ?></p>
                <p><strong>Customer:</strong> <?php echo esc_html($customer_name); ?></p>
                <p><strong>Service:</strong> <?php echo esc_html($service_name); ?></p>
                <p><strong>Date:</strong> <?php echo esc_html($booking_date); ?></p>
                <p><strong>Time:</strong> <?php echo esc_html($booking_time); ?></p>
                <p><strong>Urgency:</strong> <?php echo esc_html($urgency_level); ?></p>
                
                <?php if (!empty($dependents)): ?>
                <p><strong>Dependents:</strong> <?php echo count($dependents); ?> selected</p>
                <?php endif; ?>
            </div>
            
            <p>Please review and confirm this booking in the admin panel.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template: Status Change
     */
    private function get_booking_status_change_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Booking Status Update</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3498db; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .status-update { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Booking Status Update</h1>
        </div>
        
        <div class="content">
            <p>Dear <?php echo esc_html($customer_name); ?>,</p>
            
            <p>Your booking status has been updated:</p>
            
            <div class="status-update">
                <p><strong>Booking Number:</strong> <?php echo esc_html($booking_number); ?></p>
                <p><strong>Service:</strong> <?php echo esc_html($service_name); ?></p>
                <p><strong>Previous Status:</strong> <?php echo esc_html($old_status); ?></p>
                <p><strong>New Status:</strong> <?php echo esc_html($new_status); ?></p>
                <p><strong>Date:</strong> <?php echo esc_html($booking_date); ?></p>
                <p><strong>Time:</strong> <?php echo esc_html($booking_time); ?></p>
            </div>
            
            <p>If you have any questions about this update, please contact us.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template: Invoice Notification
     */
    private function get_invoice_notification_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice Generated</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #27ae60; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .invoice-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #27ae60; }
        .button { display: inline-block; background: #27ae60; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Invoice Generated</h1>
        </div>
        
        <div class="content">
            <p>Dear <?php echo esc_html($customer_name); ?>,</p>
            
            <p>Your invoice has been generated:</p>
            
            <div class="invoice-details">
                <p><strong>Invoice Number:</strong> <?php echo esc_html($invoice_number); ?></p>
                <p><strong>Booking Number:</strong> <?php echo esc_html($booking_number); ?></p>
                <p><strong>Service:</strong> <?php echo esc_html($service_name); ?></p>
                <p><strong>Amount:</strong> ₹<?php echo esc_html($invoice_amount); ?></p>
                <p><strong>Due Date:</strong> <?php echo esc_html($due_date); ?></p>
            </div>
            
            <p>Please review your invoice and make payment by the due date.</p>
            
            <a href="<?php echo esc_url($invoice_url); ?>" class="button">View Invoice</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template: Payment Confirmation
     */
    private function get_payment_confirmation_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2ecc71; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .payment-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2ecc71; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Payment Received</h1>
        </div>
        
        <div class="content">
            <p>Dear <?php echo esc_html($customer_name); ?>,</p>
            
            <p>Thank you! Your payment has been received:</p>
            
            <div class="payment-details">
                <p><strong>Invoice Number:</strong> <?php echo esc_html($invoice_number); ?></p>
                <p><strong>Payment Amount:</strong> ₹<?php echo esc_html($payment_amount); ?></p>
                <p><strong>Payment Date:</strong> <?php echo esc_html($payment_date); ?></p>
                <p><strong>Payment Method:</strong> <?php echo esc_html($payment_method); ?></p>
                <p><strong>Transaction ID:</strong> <?php echo esc_html($transaction_id); ?></p>
            </div>
            
            <p>Your payment has been processed successfully. Thank you for your business!</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template: Booking Reminder
     */
    private function get_booking_reminder_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Booking Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f39c12; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .reminder-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f39c12; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Booking Reminder</h1>
        </div>
        
        <div class="content">
            <p>Dear <?php echo esc_html($customer_name); ?>,</p>
            
            <p>This is a friendly reminder about your upcoming appointment:</p>
            
            <div class="reminder-details">
                <p><strong>Booking Number:</strong> <?php echo esc_html($booking_number); ?></p>
                <p><strong>Service:</strong> <?php echo esc_html($service_name); ?></p>
                <p><strong>Date:</strong> <?php echo esc_html($booking_date); ?></p>
                <p><strong>Time:</strong> <?php echo esc_html($booking_time); ?></p>
            </div>
            
            <p>We look forward to serving you tomorrow! If you need to reschedule, please contact us as soon as possible.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Template: Payment Reminder
     */
    private function get_payment_reminder_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e67e22; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .payment-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #e67e22; }
        .button { display: inline-block; background: #e67e22; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Payment Reminder</h1>
        </div>
        
        <div class="content">
            <p>Dear <?php echo esc_html($customer_name); ?>,</p>
            
            <p>This is a friendly reminder that your invoice payment is due soon:</p>
            
            <div class="payment-details">
                <p><strong>Invoice Number:</strong> <?php echo esc_html($invoice_number); ?></p>
                <p><strong>Amount Due:</strong> ₹<?php echo esc_html($amount_due); ?></p>
                <p><strong>Due Date:</strong> <?php echo esc_html($due_date); ?></p>
                <p><strong>Days Until Due:</strong> <?php echo esc_html($days_until_due); ?> days</p>
            </div>
            
            <p>Please make your payment by the due date to avoid any late fees.</p>
            
            <a href="<?php echo esc_url($invoice_url); ?>" class="button">View Invoice</a>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Default template
     */
    private function get_default_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <p>This is a notification from <?php echo esc_html($company_name ?? "Sahayya Booking Services"); ?>.</p>
    </div>
</body>
</html>';
    }
}