<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Employee_Dashboard {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        if (Sahayya_Booking_Roles::is_employee()) {
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
            add_action('admin_menu', array($this, 'add_employee_menu'));
        }
    }
    
    public function add_employee_menu() {
        add_menu_page(
            __('My Services', 'sahayya-booking'),
            __('My Services', 'sahayya-booking'),
            'view_assigned_bookings',
            'sahayya-employee-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-clipboard',
            25
        );
        
        add_submenu_page(
            'sahayya-employee-dashboard',
            __('Assigned Bookings', 'sahayya-booking'),
            __('My Bookings', 'sahayya-booking'),
            'view_assigned_bookings',
            'sahayya-employee-bookings',
            array($this, 'render_assigned_bookings')
        );
        
        add_submenu_page(
            'sahayya-employee-dashboard',
            __('Update Progress', 'sahayya-booking'),
            __('Progress Updates', 'sahayya-booking'),
            'update_booking_progress',
            'sahayya-employee-progress',
            array($this, 'render_progress_updates')
        );
        
        add_submenu_page(
            'sahayya-employee-dashboard',
            __('My Profile', 'sahayya-booking'),
            __('My Profile', 'sahayya-booking'),
            'read',
            'sahayya-employee-profile',
            array($this, 'render_employee_profile')
        );
    }
    
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'sahayya_employee_stats',
            __('My Service Statistics', 'sahayya-booking'),
            array($this, 'render_stats_widget')
        );
        
        wp_add_dashboard_widget(
            'sahayya_today_bookings',
            __('Today\'s Assigned Services', 'sahayya-booking'),
            array($this, 'render_today_bookings_widget')
        );
    }
    
    public function render_dashboard() {
        $user_id = get_current_user_id();
        $employee = Sahayya_Booking_Roles::get_employee_profile($user_id);
        
        if (!$employee) {
            echo '<div class="wrap"><h1>' . __('Employee Dashboard', 'sahayya-booking') . '</h1>';
            echo '<p>' . __('Employee profile not found. Please contact administrator.', 'sahayya-booking') . '</p></div>';
            return;
        }
        
        // Get employee statistics
        $assigned_bookings = Sahayya_Booking_Database::get_bookings(array('employee_id' => $user_id));
        $pending_bookings = Sahayya_Booking_Database::get_bookings(array('employee_id' => $user_id, 'status' => 'assigned'));
        $completed_bookings = Sahayya_Booking_Database::get_bookings(array('employee_id' => $user_id, 'status' => 'completed'));
        $today_bookings = $this->get_today_bookings($user_id);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Employee Dashboard', 'sahayya-booking'); ?></h1>
            
            <div class="employee-welcome">
                <h2><?php printf(__('Welcome, %s!', 'sahayya-booking'), esc_html($employee->employee_code)); ?></h2>
                <p><?php _e('Manage your assigned services and update progress from here.', 'sahayya-booking'); ?></p>
            </div>
            
            <div class="employee-stats-grid">
                <div class="stat-card">
                    <h3><?php _e('Total Assigned', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo count($assigned_bookings); ?></span>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Pending Services', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo count($pending_bookings); ?></span>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Completed Services', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo count($completed_bookings); ?></span>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Today\'s Bookings', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo count($today_bookings); ?></span>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Rating', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo number_format($employee->rating, 1); ?>/5</span>
                </div>
                
                <div class="stat-card">
                    <h3><?php _e('Availability', 'sahayya-booking'); ?></h3>
                    <span class="status-badge status-<?php echo esc_attr($employee->availability_status); ?>">
                        <?php echo esc_html(ucfirst($employee->availability_status)); ?>
                    </span>
                </div>
            </div>
            
            <div class="employee-quick-actions">
                <h3><?php _e('Quick Actions', 'sahayya-booking'); ?></h3>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=sahayya-employee-bookings'); ?>" class="button button-primary">
                        <?php _e('View My Bookings', 'sahayya-booking'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sahayya-employee-progress'); ?>" class="button button-secondary">
                        <?php _e('Update Progress', 'sahayya-booking'); ?>
                    </a>
                    <button class="button toggle-availability" data-current="<?php echo esc_attr($employee->availability_status); ?>">
                        <?php _e('Toggle Availability', 'sahayya-booking'); ?>
                    </button>
                </div>
            </div>
            
            <?php if (!empty($today_bookings)): ?>
                <div class="today-bookings">
                    <h3><?php _e('Today\'s Schedule', 'sahayya-booking'); ?></h3>
                    <?php $this->render_bookings_table($today_bookings); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .employee-welcome {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #0073aa;
        }
        
        .employee-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-busy {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }
        
        .employee-quick-actions {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .today-bookings {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        </style>
        <?php
    }
    
    public function render_assigned_bookings() {
        $user_id = get_current_user_id();
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $args = array('employee_id' => $user_id);
        if ($status_filter) {
            $args['status'] = $status_filter;
        }
        
        $bookings = Sahayya_Booking_Database::get_bookings($args);
        
        ?>
        <div class="wrap">
            <h1><?php _e('My Assigned Bookings', 'sahayya-booking'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="sahayya-employee-bookings" />
                        <select name="status">
                            <option value=""><?php _e('All Statuses', 'sahayya-booking'); ?></option>
                            <option value="assigned" <?php selected($status_filter, 'assigned'); ?>><?php _e('Assigned', 'sahayya-booking'); ?></option>
                            <option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>><?php _e('In Progress', 'sahayya-booking'); ?></option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'sahayya-booking'); ?></option>
                        </select>
                        <input type="submit" class="button" value="<?php _e('Filter', 'sahayya-booking'); ?>">
                    </form>
                </div>
            </div>
            
            <?php if (!empty($bookings)): ?>
                <?php $this->render_bookings_table($bookings, true); ?>
            <?php else: ?>
                <div class="no-bookings">
                    <p><?php _e('No bookings assigned to you yet.', 'sahayya-booking'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_progress_updates() {
        ?>
        <div class="wrap">
            <h1><?php _e('Update Service Progress', 'sahayya-booking'); ?></h1>
            <p><?php _e('Select a booking to update its progress and add notes.', 'sahayya-booking'); ?></p>
            
            <div id="progress-update-form" style="display: none;">
                <h3><?php _e('Update Progress', 'sahayya-booking'); ?></h3>
                <form id="progress-form">
                    <input type="hidden" id="booking_id" name="booking_id" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Status Update', 'sahayya-booking'); ?></th>
                            <td>
                                <select name="new_status" id="new_status">
                                    <option value="in_progress"><?php _e('Service Started', 'sahayya-booking'); ?></option>
                                    <option value="completed"><?php _e('Service Completed', 'sahayya-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Progress Notes', 'sahayya-booking'); ?></th>
                            <td>
                                <textarea name="progress_notes" id="progress_notes" rows="4" cols="50" placeholder="<?php _e('Add details about the service progress...', 'sahayya-booking'); ?>"></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Upload Photos', 'sahayya-booking'); ?></th>
                            <td>
                                <input type="file" name="progress_photos[]" multiple accept="image/*" />
                                <p class="description"><?php _e('Upload photos documenting the service (optional)', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php _e('Update Progress', 'sahayya-booking'); ?>" />
                        <button type="button" class="button" onclick="hideProgressForm()"><?php _e('Cancel', 'sahayya-booking'); ?></button>
                    </p>
                </form>
            </div>
            
            <script>
            function showProgressForm(bookingId, serviceName) {
                document.getElementById('booking_id').value = bookingId;
                document.getElementById('progress-update-form').style.display = 'block';
                document.querySelector('#progress-update-form h3').textContent = 'Update Progress - ' + serviceName;
            }
            
            function hideProgressForm() {
                document.getElementById('progress-update-form').style.display = 'none';
                document.getElementById('progress-form').reset();
            }
            </script>
        </div>
        <?php
    }
    
    public function render_employee_profile() {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $employee = Sahayya_Booking_Roles::get_employee_profile($user_id);
        
        ?>
        <div class="wrap">
            <h1><?php _e('My Profile', 'sahayya-booking'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('update_employee_profile'); ?>
                
                <h2><?php _e('Personal Information', 'sahayya-booking'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Name', 'sahayya-booking'); ?></th>
                        <td><?php echo esc_html($user->display_name); ?></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Email', 'sahayya-booking'); ?></th>
                        <td><?php echo esc_html($user->user_email); ?></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Employee Code', 'sahayya-booking'); ?></th>
                        <td><?php echo esc_html($employee->employee_code); ?></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Phone Number', 'sahayya-booking'); ?></th>
                        <td>
                            <input type="tel" name="phone" value="<?php echo esc_attr($employee->phone); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Address', 'sahayya-booking'); ?></th>
                        <td>
                            <textarea name="address" rows="3" cols="50"><?php echo esc_textarea($employee->address); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Emergency Contact', 'sahayya-booking'); ?></th>
                        <td>
                            <input type="text" name="emergency_contact" value="<?php echo esc_attr($employee->emergency_contact); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Skills', 'sahayya-booking'); ?></th>
                        <td>
                            <textarea name="skills" rows="3" cols="50" placeholder="<?php _e('First Aid, Driving, Medical Care, etc.', 'sahayya-booking'); ?>"><?php echo esc_textarea($employee->skills); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Vehicle Details', 'sahayya-booking'); ?></th>
                        <td>
                            <textarea name="vehicle_details" rows="2" cols="50" placeholder="<?php _e('Car model, license plate, etc.', 'sahayya-booking'); ?>"><?php echo esc_textarea($employee->vehicle_details); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Service Statistics', 'sahayya-booking'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Total Services Completed', 'sahayya-booking'); ?></th>
                        <td><?php echo esc_html($employee->total_services); ?></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Current Rating', 'sahayya-booking'); ?></th>
                        <td><?php echo number_format($employee->rating, 1); ?>/5.0</td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Member Since', 'sahayya-booking'); ?></th>
                        <td><?php echo date('F j, Y', strtotime($employee->created_at)); ?></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_profile" class="button button-primary" value="<?php _e('Update Profile', 'sahayya-booking'); ?>" />
                </p>
            </form>
        </div>
        <?php
        
        // Handle form submission
        if (isset($_POST['update_profile']) && wp_verify_nonce($_POST['_wpnonce'], 'update_employee_profile')) {
            $this->handle_profile_update($user_id);
        }
    }
    
    private function render_bookings_table($bookings, $show_actions = false) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Booking #', 'sahayya-booking'); ?></th>
                    <th><?php _e('Service', 'sahayya-booking'); ?></th>
                    <th><?php _e('Customer', 'sahayya-booking'); ?></th>
                    <th><?php _e('Dependents', 'sahayya-booking'); ?></th>
                    <th><?php _e('Date/Time', 'sahayya-booking'); ?></th>
                    <th><?php _e('Status', 'sahayya-booking'); ?></th>
                    <th><?php _e('Amount', 'sahayya-booking'); ?></th>
                    <?php if ($show_actions): ?>
                        <th><?php _e('Actions', 'sahayya-booking'); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $service = Sahayya_Booking_Database::get_service($booking->service_id);
                    $customer = get_userdata($booking->subscriber_id);
                    $dependent_ids = json_decode($booking->dependent_ids, true);
                    $dependent_names = array();
                    
                    if (!empty($dependent_ids)) {
                        foreach ($dependent_ids as $dep_id) {
                            $dep = Sahayya_Booking_Database::get_dependent($dep_id);
                            if ($dep) $dependent_names[] = $dep->name;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html($booking->booking_number); ?></td>
                        <td><?php echo esc_html($service ? $service->name : 'Unknown Service'); ?></td>
                        <td><?php echo esc_html($customer ? $customer->display_name : 'Unknown Customer'); ?></td>
                        <td><?php echo esc_html(implode(', ', $dependent_names)); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($booking->booking_date . ' ' . $booking->booking_time)); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->booking_status))); ?>
                            </span>
                        </td>
                        <td>₹<?php echo number_format($booking->total_amount, 2); ?></td>
                        <?php if ($show_actions): ?>
                            <td>
                                <?php if (in_array($booking->booking_status, ['assigned', 'in_progress'])): ?>
                                    <button class="button button-small" onclick="showProgressForm(<?php echo $booking->id; ?>, '<?php echo esc_js($service ? $service->name : 'Service'); ?>')">
                                        <?php _e('Update', 'sahayya-booking'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-small" onclick="viewBookingDetails(<?php echo $booking->id; ?>)">
                                    <?php _e('Details', 'sahayya-booking'); ?>
                                </button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function get_today_bookings($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sahayya_bookings';
        $today = date('Y-m-d');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE assigned_employee_id = %d AND booking_date = %s ORDER BY booking_time ASC",
            $user_id, $today
        ));
    }
    
    private function handle_profile_update($user_id) {
        $data = array(
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'emergency_contact' => sanitize_text_field($_POST['emergency_contact']),
            'skills' => sanitize_textarea_field($_POST['skills']),
            'vehicle_details' => sanitize_textarea_field($_POST['vehicle_details'])
        );
        
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_employees';
        
        $result = $wpdb->update($table, $data, array('user_id' => $user_id));
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Profile updated successfully!', 'sahayya-booking') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to update profile. Please try again.', 'sahayya-booking') . '</p></div>';
        }
    }
    
    public function render_stats_widget() {
        $user_id = get_current_user_id();
        $employee = Sahayya_Booking_Roles::get_employee_profile($user_id);
        
        if (!$employee) {
            echo '<p>' . __('Employee profile not found.', 'sahayya-booking') . '</p>';
            return;
        }
        
        $pending_count = count(Sahayya_Booking_Database::get_bookings(array('employee_id' => $user_id, 'status' => 'assigned')));
        $completed_count = count(Sahayya_Booking_Database::get_bookings(array('employee_id' => $user_id, 'status' => 'completed')));
        
        ?>
        <div class="employee-widget-stats">
            <div class="stat-item">
                <span class="stat-label"><?php _e('Pending Services:', 'sahayya-booking'); ?></span>
                <span class="stat-value"><?php echo $pending_count; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label"><?php _e('Completed Services:', 'sahayya-booking'); ?></span>
                <span class="stat-value"><?php echo $completed_count; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label"><?php _e('Rating:', 'sahayya-booking'); ?></span>
                <span class="stat-value"><?php echo number_format($employee->rating, 1); ?>/5</span>
            </div>
        </div>
        
        <style>
        .employee-widget-stats {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
            padding: 10px;
        }
        
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .stat-value {
            display: block;
            font-size: 20px;
            font-weight: bold;
            color: #0073aa;
            margin-top: 5px;
        }
        </style>
        <?php
    }
    
    public function render_today_bookings_widget() {
        $user_id = get_current_user_id();
        $today_bookings = $this->get_today_bookings($user_id);
        
        if (empty($today_bookings)) {
            echo '<p>' . __('No services scheduled for today.', 'sahayya-booking') . '</p>';
            return;
        }
        
        foreach ($today_bookings as $booking) {
            $service = Sahayya_Booking_Database::get_service($booking->service_id);
            ?>
            <div class="today-booking-item">
                <h4><?php echo esc_html($service ? $service->name : 'Unknown Service'); ?></h4>
                <p><strong><?php _e('Time:', 'sahayya-booking'); ?></strong> <?php echo date('g:i A', strtotime($booking->booking_time)); ?></p>
                <p><strong><?php _e('Status:', 'sahayya-booking'); ?></strong> 
                   <span class="status-<?php echo esc_attr($booking->booking_status); ?>">
                       <?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->booking_status))); ?>
                   </span>
                </p>
            </div>
            <?php
        }
    }
}

// Initialize employee dashboard
new Sahayya_Employee_Dashboard();