<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Initialize employee management early to handle form submissions
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'sahayya-booking-employees') {
            require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-employees.php';
            new Sahayya_Booking_Employees();
        }
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Sahayya Booking', 'sahayya-booking'),
            __('Sahayya Booking', 'sahayya-booking'),
            'manage_sahayya_bookings',
            'sahayya-booking',
            array($this, 'dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'sahayya-booking',
            __('Dashboard', 'sahayya-booking'),
            __('Dashboard', 'sahayya-booking'),
            'manage_sahayya_bookings',
            'sahayya-booking',
            array($this, 'dashboard_page')
        );
        
        // Bookings submenu
        add_submenu_page(
            'sahayya-booking',
            __('All Bookings', 'sahayya-booking'),
            __('Bookings', 'sahayya-booking'),
            'manage_sahayya_bookings',
            'sahayya-booking-bookings',
            array($this, 'bookings_page')
        );
        
        // Services submenu
        add_submenu_page(
            'sahayya-booking',
            __('Services', 'sahayya-booking'),
            __('Services', 'sahayya-booking'),
            'manage_sahayya_services',
            'sahayya-booking-services',
            array($this, 'services_page')
        );
        
        // Service Categories submenu
        add_submenu_page(
            'sahayya-booking',
            __('Service Categories', 'sahayya-booking'),
            __('Categories', 'sahayya-booking'),
            'manage_sahayya_services',
            'sahayya-booking-categories',
            array($this, 'categories_page')
        );
        
        // Employees submenu
        add_submenu_page(
            'sahayya-booking',
            __('Employees', 'sahayya-booking'),
            __('Employees', 'sahayya-booking'),
            'manage_sahayya_employees',
            'sahayya-booking-employees',
            array($this, 'employees_page')
        );
        
        // Customers submenu
        add_submenu_page(
            'sahayya-booking',
            __('Customers & Dependents', 'sahayya-booking'),
            __('Customers', 'sahayya-booking'),
            'manage_sahayya_bookings',
            'sahayya-booking-customers',
            array($this, 'customers_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'sahayya-booking',
            __('Settings', 'sahayya-booking'),
            __('Settings', 'sahayya-booking'),
            'manage_sahayya_settings',
            'sahayya-booking-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_init() {
        // Register settings
        register_setting('sahayya_booking_settings', 'sahayya_booking_settings');
        register_setting('sahayya_payment_settings', 'sahayya_payment_settings');
    }
    
    public function dashboard_page() {
        $this->render_admin_header();
        ?>
        <div class="wrap">
            <h1><?php _e('Sahayya Booking Dashboard', 'sahayya-booking'); ?></h1>
            
            <div class="sahayya-dashboard-stats">
                <div class="sahayya-stat-card">
                    <h3><?php _e('Today\'s Bookings', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo $this->get_today_bookings_count(); ?></span>
                </div>
                
                <div class="sahayya-stat-card">
                    <h3><?php _e('Pending Assignments', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo $this->get_pending_assignments_count(); ?></span>
                </div>
                
                <div class="sahayya-stat-card">
                    <h3><?php _e('Active Services', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo $this->get_active_services_count(); ?></span>
                </div>
                
                <div class="sahayya-stat-card">
                    <h3><?php _e('Total Employees', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo $this->get_employees_count(); ?></span>
                </div>
            </div>
            
            <div class="sahayya-dashboard-content">
                <div class="sahayya-recent-bookings">
                    <h2><?php _e('Recent Bookings', 'sahayya-booking'); ?></h2>
                    <?php $this->render_recent_bookings(); ?>
                </div>
                
                <div class="sahayya-quick-actions">
                    <h2><?php _e('Quick Actions', 'sahayya-booking'); ?></h2>
                    <div class="quick-action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-services&action=add'); ?>" class="button button-primary">
                            <?php _e('Add New Service', 'sahayya-booking'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=add'); ?>" class="button button-secondary">
                            <?php _e('Add Employee', 'sahayya-booking'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings'); ?>" class="button button-secondary">
                            <?php _e('View All Bookings', 'sahayya-booking'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function bookings_page() {
        if (!class_exists('Sahayya_Booking_Bookings')) {
            require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-bookings.php';
        }
        
        $bookings_admin = new Sahayya_Booking_Bookings();
        $bookings_admin->render_page();
    }
    
    public function services_page() {
        if (!class_exists('Sahayya_Booking_Services')) {
            require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-services.php';
        }
        
        $services_admin = new Sahayya_Booking_Services();
        $services_admin->render_page();
    }
    
    public function categories_page() {
        if (!class_exists('Sahayya_Booking_Categories')) {
            require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-categories.php';
        }
        
        $categories_admin = new Sahayya_Booking_Categories();
        $categories_admin->render_page();
    }
    
    public function employees_page() {
        if (!class_exists('Sahayya_Booking_Employees')) {
            require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-employees.php';
        }
        
        if (!isset($this->employees_admin)) {
            $this->employees_admin = new Sahayya_Booking_Employees();
        }
        $this->employees_admin->render_page();
    }
    
    public function customers_page() {
        if (!class_exists('Sahayya_Booking_Customers')) {
            require_once SAHAYYA_BOOKING_PLUGIN_DIR . 'admin/class-customers.php';
        }
        
        $customers_admin = new Sahayya_Booking_Customers();
        $customers_admin->render_page();
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Sahayya Booking Settings', 'sahayya-booking'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('sahayya_booking_settings'); ?>
                <?php do_settings_sections('sahayya_booking_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Business Hours', 'sahayya-booking'); ?></th>
                        <td>
                            <?php $settings = get_option('sahayya_booking_settings', array()); ?>
                            <input type="time" name="sahayya_booking_settings[business_hours_start]" 
                                   value="<?php echo esc_attr($settings['business_hours_start'] ?? '09:00'); ?>" />
                            <span><?php _e('to', 'sahayya-booking'); ?></span>
                            <input type="time" name="sahayya_booking_settings[business_hours_end]" 
                                   value="<?php echo esc_attr($settings['business_hours_end'] ?? '21:00'); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Default Currency', 'sahayya-booking'); ?></th>
                        <td>
                            <select name="sahayya_booking_settings[default_currency]">
                                <option value="INR" <?php selected($settings['default_currency'] ?? 'INR', 'INR'); ?>>INR (₹)</option>
                                <option value="USD" <?php selected($settings['default_currency'] ?? 'INR', 'USD'); ?>>USD ($)</option>
                                <option value="EUR" <?php selected($settings['default_currency'] ?? 'INR', 'EUR'); ?>>EUR (€)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enable 24/7 Booking', 'sahayya-booking'); ?></th>
                        <td>
                            <input type="checkbox" name="sahayya_booking_settings[enable_24_7_booking]" 
                                   value="1" <?php checked($settings['enable_24_7_booking'] ?? false, 1); ?> />
                            <label><?php _e('Allow bookings outside business hours', 'sahayya-booking'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private function render_admin_header() {
        ?>
        <style>
        .sahayya-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .sahayya-stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sahayya-stat-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .sahayya-dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        .quick-action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        </style>
        <?php
    }
    
    private function get_today_bookings_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()"
        );
    }
    
    private function get_pending_assignments_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE booking_status = 'pending'"
        );
    }
    
    private function get_active_services_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_services';
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'active'"
        );
    }
    
    private function get_employees_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'active'"
        );
    }
    
    private function render_recent_bookings() {
        $bookings = Sahayya_Booking_Database::get_bookings(array('limit' => 5));
        
        if (empty($bookings)) {
            echo '<p>' . __('No recent bookings found.', 'sahayya-booking') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Booking #', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Customer', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Service', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Date', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Status', 'sahayya-booking') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($bookings as $booking) {
            $customer = get_userdata($booking->subscriber_id);
            $service = Sahayya_Booking_Database::get_service($booking->service_id);
            
            echo '<tr>';
            echo '<td>' . esc_html($booking->booking_number) . '</td>';
            echo '<td>' . esc_html($customer ? $customer->display_name : 'Unknown') . '</td>';
            echo '<td>' . esc_html($service ? $service->name : 'Unknown Service') . '</td>';
            echo '<td>' . esc_html($booking->booking_date) . '</td>';
            echo '<td><span class="status-' . esc_attr($booking->booking_status) . '">' . esc_html(ucfirst($booking->booking_status)) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}