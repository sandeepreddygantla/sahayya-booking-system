<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Bookings {
    
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
        
        // Handle form submissions
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'sahayya_booking_action')) {
            $this->handle_form_submission();
        }
        
        switch ($action) {
            case 'view':
                $this->render_booking_details($booking_id);
                break;
            case 'edit':
                $this->render_edit_booking_form($booking_id);
                break;
            case 'assign':
                $this->render_assign_employee_form($booking_id);
                break;
            case 'cancel':
                $this->handle_cancel_booking($booking_id);
                break;
            default:
                $this->render_bookings_list();
                break;
        }
    }
    
    private function render_bookings_list() {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        
        $args = array();
        if ($status_filter) $args['status'] = $status_filter;
        if ($customer_id) $args['subscriber_id'] = $customer_id;
        
        $bookings = Sahayya_Booking_Database::get_bookings($args);
        
        // Filter by search term
        if ($search) {
            $bookings = array_filter($bookings, function($booking) use ($search) {
                $customer = get_userdata($booking->subscriber_id);
                $service = Sahayya_Booking_Database::get_service($booking->service_id);
                
                return (
                    stripos($booking->booking_number, $search) !== false ||
                    ($customer && stripos($customer->display_name, $search) !== false) ||
                    ($service && stripos($service->name, $search) !== false)
                );
            });
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('All Bookings', 'sahayya-booking'); ?></h1>
            
            <?php $this->show_messages(); ?>
            
            <div class="bookings-overview">
                <div class="bookings-stats">
                    <div class="stat-box">
                        <h3><?php _e('Total Bookings', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_total_bookings(); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Pending Assignment', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_pending_assignments(); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('In Progress', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_in_progress_bookings(); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Today\'s Revenue', 'sahayya-booking'); ?></h3>
                        <span class="stat-number">₹<?php echo number_format($this->get_today_revenue(), 0); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="sahayya-booking-bookings" />
                        <select name="status">
                            <option value=""><?php _e('All Statuses', 'sahayya-booking'); ?></option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'sahayya-booking'); ?></option>
                            <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php _e('Confirmed', 'sahayya-booking'); ?></option>
                            <option value="assigned" <?php selected($status_filter, 'assigned'); ?>><?php _e('Assigned', 'sahayya-booking'); ?></option>
                            <option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>><?php _e('In Progress', 'sahayya-booking'); ?></option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'sahayya-booking'); ?></option>
                            <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php _e('Cancelled', 'sahayya-booking'); ?></option>
                        </select>
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search bookings...', 'sahayya-booking'); ?>" />
                        <input type="submit" class="button" value="<?php _e('Filter', 'sahayya-booking'); ?>" />
                    </form>
                </div>
                
                <div class="alignright actions">
                    <select id="bulk-action-selector">
                        <option value=""><?php _e('Bulk Actions', 'sahayya-booking'); ?></option>
                        <option value="confirm"><?php _e('Confirm Bookings', 'sahayya-booking'); ?></option>
                        <option value="cancel"><?php _e('Cancel Bookings', 'sahayya-booking'); ?></option>
                        <option value="export"><?php _e('Export Selected', 'sahayya-booking'); ?></option>
                    </select>
                    <input type="button" class="button" value="<?php _e('Apply', 'sahayya-booking'); ?>" onclick="applyBulkAction()" />
                </div>
            </div>
            
            <?php if (!empty($bookings)): ?>
                <table class="wp-list-table widefat fixed striped" id="bookings-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all" />
                            </td>
                            <th scope="col" class="manage-column column-booking"><?php _e('Booking #', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-service"><?php _e('Service', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-dependents"><?php _e('Dependents', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-datetime"><?php _e('Date/Time', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-employee"><?php _e('Assigned To', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-amount"><?php _e('Amount', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php _e('Status', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'sahayya-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $customer = get_userdata($booking->subscriber_id);
                            $service = Sahayya_Booking_Database::get_service($booking->service_id);
                            $dependent_ids = json_decode($booking->dependent_ids, true);
                            $employee = $booking->assigned_employee_id ? get_userdata($booking->assigned_employee_id) : null;
                            
                            $dependent_names = array();
                            if (!empty($dependent_ids)) {
                                foreach ($dependent_ids as $dep_id) {
                                    $dep = Sahayya_Booking_Database::get_dependent($dep_id);
                                    if ($dep) $dependent_names[] = $dep->name;
                                }
                            }
                            ?>
                            <tr data-booking-id="<?php echo $booking->id; ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="booking_ids[]" value="<?php echo $booking->id; ?>" />
                                </th>
                                <td class="column-booking">
                                    <strong><?php echo esc_html($booking->booking_number); ?></strong>
                                    <div class="booking-meta">
                                        <?php echo date('M j, Y', strtotime($booking->created_at)); ?>
                                    </div>
                                </td>
                                <td class="column-customer">
                                    <?php if ($customer): ?>
                                        <strong><?php echo esc_html($customer->display_name); ?></strong>
                                        <div class="customer-contact">
                                            <?php echo esc_html($customer->user_email); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="unknown"><?php _e('Unknown Customer', 'sahayya-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-service">
                                    <?php if ($service): ?>
                                        <strong><?php echo esc_html($service->name); ?></strong>
                                        <?php if ($service->description): ?>
                                            <div class="service-desc"><?php echo esc_html(wp_trim_words($service->description, 8)); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="unknown"><?php _e('Unknown Service', 'sahayya-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-dependents">
                                    <?php if (!empty($dependent_names)): ?>
                                        <span class="dependents-count"><?php echo count($dependent_names); ?></span>
                                        <div class="dependents-list">
                                            <?php echo esc_html(implode(', ', array_slice($dependent_names, 0, 2))); ?>
                                            <?php if (count($dependent_names) > 2): ?>
                                                <span class="more-deps">+<?php echo count($dependent_names) - 2; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-dependents"><?php _e('No dependents', 'sahayya-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-datetime">
                                    <strong><?php echo date('M j, Y', strtotime($booking->booking_date)); ?></strong>
                                    <div class="booking-time">
                                        <?php echo date('g:i A', strtotime($booking->booking_time)); ?>
                                    </div>
                                </td>
                                <td class="column-employee">
                                    <?php if ($employee): ?>
                                        <strong><?php echo esc_html($employee->display_name); ?></strong>
                                    <?php elseif ($booking->booking_status === 'pending'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=assign&booking_id=' . $booking->id); ?>" 
                                           class="button button-small button-primary"><?php _e('Assign', 'sahayya-booking'); ?></a>
                                    <?php else: ?>
                                        <span class="not-assigned"><?php _e('Not assigned', 'sahayya-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-amount">
                                    <strong>₹<?php echo number_format($booking->total_amount, 2); ?></strong>
                                    <div class="payment-status">
                                        <span class="payment-<?php echo esc_attr($booking->payment_status); ?>">
                                            <?php echo esc_html(ucfirst($booking->payment_status)); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="column-status">
                                    <span class="status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->booking_status))); ?>
                                    </span>
                                </td>
                                <td class="column-actions">
                                    <div class="action-buttons">
                                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=view&booking_id=' . $booking->id); ?>" 
                                           class="button button-small"><?php _e('View', 'sahayya-booking'); ?></a>
                                        
                                        <?php if (in_array($booking->booking_status, ['pending', 'confirmed', 'assigned'])): ?>
                                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=edit&booking_id=' . $booking->id); ?>" 
                                               class="button button-small"><?php _e('Edit', 'sahayya-booking'); ?></a>
                                        <?php endif; ?>
                                        
                                        <?php if ($booking->booking_status !== 'cancelled'): ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sahayya-booking-bookings&action=cancel&booking_id=' . $booking->id), 'cancel_booking_' . $booking->id); ?>" 
                                               class="button button-small button-link-delete" 
                                               onclick="return confirm('<?php _e('Are you sure you want to cancel this booking?', 'sahayya-booking'); ?>')"><?php _e('Cancel', 'sahayya-booking'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-bookings-message">
                    <h3><?php _e('No bookings found', 'sahayya-booking'); ?></h3>
                    <p><?php _e('Bookings will appear here as customers make reservations through the booking system.', 'sahayya-booking'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .bookings-overview {
            margin: 20px 0;
        }
        
        .bookings-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .booking-meta, .customer-contact, .service-desc, .booking-time, .dependents-list {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        .dependents-count {
            font-weight: bold;
            color: #0073aa;
        }
        
        .more-deps {
            color: #999;
            font-style: italic;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-assigned {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-in-progress {
            background: #e2e3e5;
            color: #41464b;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-pending {
            color: #856404;
        }
        
        .payment-paid {
            color: #155724;
        }
        
        .payment-failed {
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .no-bookings-message {
            text-align: center;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .unknown, .not-assigned, .no-dependents {
            color: #999;
            font-style: italic;
        }
        </style>
        
        <script>
        document.getElementById('cb-select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="booking_ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        function applyBulkAction() {
            const action = document.getElementById('bulk-action-selector').value;
            const selected = document.querySelectorAll('input[name="booking_ids[]"]:checked');
            
            if (!action) {
                alert('<?php _e('Please select an action', 'sahayya-booking'); ?>');
                return;
            }
            
            if (selected.length === 0) {
                alert('<?php _e('Please select at least one booking', 'sahayya-booking'); ?>');
                return;
            }
            
            if (action === 'cancel' && !confirm('<?php _e('Are you sure you want to cancel the selected bookings?', 'sahayya-booking'); ?>')) {
                return;
            }
            
            // Handle bulk actions
            const bookingIds = Array.from(selected).map(cb => cb.value);
            console.log('Bulk action:', action, 'on bookings:', bookingIds);
            
            // This would typically submit to a handler
            // For now, just alert
            alert('Bulk action "' + action + '" on ' + bookingIds.length + ' bookings');
        }
        </script>
        <?php
    }
    
    private function render_booking_details($booking_id) {
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        if (!$booking) {
            wp_die(__('Booking not found.', 'sahayya-booking'));
        }
        
        $customer = get_userdata($booking->subscriber_id);
        $service = Sahayya_Booking_Database::get_service($booking->service_id);
        $employee = $booking->assigned_employee_id ? get_userdata($booking->assigned_employee_id) : null;
        $dependent_ids = json_decode($booking->dependent_ids, true);
        
        $dependents = array();
        if (!empty($dependent_ids)) {
            foreach ($dependent_ids as $dep_id) {
                $dep = Sahayya_Booking_Database::get_dependent($dep_id);
                if ($dep) $dependents[] = $dep;
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Booking Details: %s', 'sahayya-booking'), esc_html($booking->booking_number)); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings'); ?>" class="button">
                ← <?php _e('Back to All Bookings', 'sahayya-booking'); ?>
            </a>
            
            <?php $this->show_messages(); ?>
            
            <div class="booking-details-grid">
                <!-- Booking Information -->
                <div class="booking-info-card">
                    <h2><?php _e('Booking Information', 'sahayya-booking'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Booking Number:', 'sahayya-booking'); ?></th>
                            <td><strong><?php echo esc_html($booking->booking_number); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php _e('Service:', 'sahayya-booking'); ?></th>
                            <td><?php echo esc_html($service ? $service->name : 'Unknown Service'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Date & Time:', 'sahayya-booking'); ?></th>
                            <td><?php echo date('F j, Y g:i A', strtotime($booking->booking_date . ' ' . $booking->booking_time)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status:', 'sahayya-booking'); ?></th>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->booking_status))); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Total Amount:', 'sahayya-booking'); ?></th>
                            <td><strong>₹<?php echo number_format($booking->total_amount, 2); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php _e('Payment Status:', 'sahayya-booking'); ?></th>
                            <td>
                                <span class="payment-status payment-<?php echo esc_attr($booking->payment_status); ?>">
                                    <?php echo esc_html(ucfirst($booking->payment_status)); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Created:', 'sahayya-booking'); ?></th>
                            <td><?php echo date('F j, Y g:i A', strtotime($booking->created_at)); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Customer Information -->
                <div class="customer-info-card">
                    <h2><?php _e('Customer Information', 'sahayya-booking'); ?></h2>
                    <?php if ($customer): ?>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Name:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($customer->display_name); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Email:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($customer->user_email); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Phone:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html(get_user_meta($customer->ID, 'phone', true) ?: 'Not provided'); ?></td>
                            </tr>
                        </table>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $customer->ID); ?>" class="button">
                                <?php _e('View Customer Profile', 'sahayya-booking'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p><?php _e('Customer information not available.', 'sahayya-booking'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Employee Assignment -->
                <div class="employee-info-card">
                    <h2><?php _e('Employee Assignment', 'sahayya-booking'); ?></h2>
                    <?php if ($employee): ?>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Assigned To:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->display_name); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Email:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->user_email); ?></td>
                            </tr>
                        </table>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=assign&booking_id=' . $booking->id); ?>" class="button">
                                <?php _e('Reassign Employee', 'sahayya-booking'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p><?php _e('No employee assigned yet.', 'sahayya-booking'); ?></p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=assign&booking_id=' . $booking->id); ?>" class="button button-primary">
                                <?php _e('Assign Employee', 'sahayya-booking'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Dependents Information -->
                <div class="dependents-info-card">
                    <h2><?php _e('Dependents', 'sahayya-booking'); ?></h2>
                    <?php if (!empty($dependents)): ?>
                        <div class="dependents-list">
                            <?php foreach ($dependents as $dependent): ?>
                                <div class="dependent-item">
                                    <h4><?php echo esc_html($dependent->name); ?></h4>
                                    <p><strong><?php _e('Age:', 'sahayya-booking'); ?></strong> <?php echo esc_html($dependent->age); ?> years</p>
                                    <p><strong><?php _e('Gender:', 'sahayya-booking'); ?></strong> <?php echo esc_html(ucfirst($dependent->gender)); ?></p>
                                    <?php if ($dependent->medical_conditions): ?>
                                        <p><strong><?php _e('Medical Conditions:', 'sahayya-booking'); ?></strong></p>
                                        <div class="medical-info"><?php echo esc_html($dependent->medical_conditions); ?></div>
                                    <?php endif; ?>
                                    <?php if ($dependent->emergency_contact): ?>
                                        <p><strong><?php _e('Emergency Contact:', 'sahayya-booking'); ?></strong> <?php echo esc_html($dependent->emergency_contact); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No dependents specified for this booking.', 'sahayya-booking'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Special Instructions -->
                <?php if ($booking->special_instructions): ?>
                    <div class="instructions-card">
                        <h2><?php _e('Special Instructions', 'sahayya-booking'); ?></h2>
                        <div class="instructions-content">
                            <?php echo esc_html($booking->special_instructions); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="booking-actions">
                <h3><?php _e('Actions', 'sahayya-booking'); ?></h3>
                <div class="action-buttons">
                    <?php if (in_array($booking->booking_status, ['pending', 'confirmed', 'assigned'])): ?>
                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=edit&booking_id=' . $booking->id); ?>" class="button button-primary">
                            <?php _e('Edit Booking', 'sahayya-booking'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($booking->booking_status !== 'cancelled'): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sahayya-booking-bookings&action=cancel&booking_id=' . $booking->id), 'cancel_booking_' . $booking->id); ?>" 
                           class="button button-link-delete" 
                           onclick="return confirm('<?php _e('Are you sure you want to cancel this booking?', 'sahayya-booking'); ?>')">
                            <?php _e('Cancel Booking', 'sahayya-booking'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .booking-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .booking-info-card, .customer-info-card, .employee-info-card, .dependents-info-card, .instructions-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .instructions-card {
            grid-column: 1 / -1;
        }
        
        .dependent-item {
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        
        .dependent-item h4 {
            margin-top: 0;
            color: #333;
        }
        
        .medical-info {
            background: #fff5f5;
            border-left: 3px solid #dc3545;
            padding: 8px;
            margin: 5px 0;
            font-size: 13px;
        }
        
        .instructions-content {
            background: #f8f9fa;
            border-left: 3px solid #0073aa;
            padding: 15px;
            margin: 10px 0;
        }
        
        .booking-actions {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        </style>
        <?php
    }
    
    private function show_messages() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'booking_updated' => __('Booking updated successfully!', 'sahayya-booking'),
                'employee_assigned' => __('Employee assigned successfully!', 'sahayya-booking'),
                'booking_cancelled' => __('Booking cancelled successfully!', 'sahayya-booking')
            );
            
            if (isset($messages[$message])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $messages[$message] . '</p></div>';
            }
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $errors = array(
                'db_error' => __('Database error occurred. Please try again.', 'sahayya-booking'),
                'cancel_failed' => __('Failed to cancel booking. Please try again.', 'sahayya-booking')
            );
            
            if (isset($errors[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $errors[$error] . '</p></div>';
            }
        }
    }
    
    // Helper methods for statistics
    private function get_total_bookings() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    private function get_pending_assignments() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE booking_status = 'pending'");
    }
    
    private function get_in_progress_bookings() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE booking_status IN ('assigned', 'in_progress')");
    }
    
    private function get_today_revenue() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        $today = date('Y-m-d');
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_amount) FROM $table WHERE DATE(created_at) = %s AND booking_status != 'cancelled'",
            $today
        )) ?: 0;
    }
    
    private function handle_form_submission() {
        $action = sanitize_text_field($_POST['action']);
        
        if ($action === 'assign_employee') {
            $this->handle_assign_employee();
        }
    }
    
    private function handle_assign_employee() {
        $booking_id = intval($_POST['booking_id']);
        $employee_id = intval($_POST['employee_id']);
        
        if (empty($booking_id) || empty($employee_id)) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-bookings&error=missing_data'));
            exit;
        }
        
        // Assign employee to booking
        $result = Sahayya_Booking_Database::assign_employee($booking_id, $employee_id);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-bookings&message=employee_assigned'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-bookings&error=assignment_failed'));
        }
        exit;
    }
    
    private function render_assign_employee_form($booking_id) {
        $booking = Sahayya_Booking_Database::get_booking($booking_id);
        if (!$booking) {
            wp_die(__('Booking not found.', 'sahayya-booking'));
        }
        
        // Get available employees
        global $wpdb;
        $employees_table = $wpdb->prefix . 'sahayya_employees';
        $employees = $wpdb->get_results("SELECT * FROM $employees_table WHERE status = 'active'");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Assign Employee', 'sahayya-booking'); ?></h1>
            
            <div class="booking-info">
                <h3><?php _e('Booking Details', 'sahayya-booking'); ?></h3>
                <p><strong><?php _e('Booking Number:', 'sahayya-booking'); ?></strong> <?php echo esc_html($booking->booking_number); ?></p>
                <p><strong><?php _e('Date:', 'sahayya-booking'); ?></strong> <?php echo date('M j, Y', strtotime($booking->booking_date)); ?></p>
                <p><strong><?php _e('Time:', 'sahayya-booking'); ?></strong> <?php echo date('g:i A', strtotime($booking->booking_time)); ?></p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('sahayya_booking_action'); ?>
                <input type="hidden" name="action" value="assign_employee" />
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>" />
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="employee_id"><?php _e('Select Employee', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <select id="employee_id" name="employee_id" required>
                                    <option value=""><?php _e('-- Select Employee --', 'sahayya-booking'); ?></option>
                                    <?php if (!empty($employees)): ?>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo esc_attr($employee->id); ?>" 
                                                    <?php selected($booking->assigned_employee_id, $employee->id); ?>>
                                                <?php 
                                                $user = get_user_by('id', $employee->user_id);
                                                echo esc_html($user ? $user->display_name : 'Employee #' . $employee->id); 
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled><?php _e('No employees available', 'sahayya-booking'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" 
                           value="<?php _e('Assign Employee', 'sahayya-booking'); ?>" />
                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings'); ?>" class="button">
                        <?php _e('Cancel', 'sahayya-booking'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
}