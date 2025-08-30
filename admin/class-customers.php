<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Customers {
    
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        $dependent_id = isset($_GET['dependent_id']) ? intval($_GET['dependent_id']) : 0;
        
        // Handle form submissions
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'sahayya_customer_action')) {
            $this->handle_form_submission();
        }
        
        switch ($action) {
            case 'view':
                $this->render_customer_details($customer_id);
                break;
            case 'edit_dependent':
                $this->render_edit_dependent_form($dependent_id);
                break;
            case 'add_dependent':
                $this->render_add_dependent_form($customer_id);
                break;
            case 'delete_dependent':
                $this->handle_delete_dependent($dependent_id);
                break;
            default:
                $this->render_customers_list();
                break;
        }
    }
    
    private function render_customers_list() {
        // Get all subscribers (customers)
        $customers = get_users(array(
            'role' => 'subscriber',
            'meta_key' => 'sahayya_is_customer',
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        // If no customers with meta, get all subscribers
        if (empty($customers)) {
            $customers = get_users(array('role' => 'subscriber'));
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Customers & Dependents', 'sahayya-booking'); ?></h1>
            
            <?php $this->show_messages(); ?>
            
            <div class="customers-overview">
                <div class="customers-stats">
                    <div class="stat-box">
                        <h3><?php _e('Total Customers', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo count($customers); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Total Dependents', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_total_dependents(); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Active Bookings', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo $this->get_active_bookings_count(); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="customer-filter">
                        <option value=""><?php _e('All Customers', 'sahayya-booking'); ?></option>
                        <option value="with-dependents"><?php _e('With Dependents', 'sahayya-booking'); ?></option>
                        <option value="with-bookings"><?php _e('With Bookings', 'sahayya-booking'); ?></option>
                        <option value="no-activity"><?php _e('No Activity', 'sahayya-booking'); ?></option>
                    </select>
                    <input type="button" class="button" value="<?php _e('Filter', 'sahayya-booking'); ?>" onclick="filterCustomers()">
                </div>
                
                <div class="alignright actions">
                    <input type="text" id="customer-search" placeholder="<?php _e('Search customers...', 'sahayya-booking'); ?>" />
                    <input type="button" class="button" value="<?php _e('Search', 'sahayya-booking'); ?>" onclick="searchCustomers()">
                </div>
            </div>
            
            <?php if (!empty($customers)): ?>
                <table class="wp-list-table widefat fixed striped" id="customers-table">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-contact"><?php _e('Contact Info', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-dependents"><?php _e('Dependents', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-bookings"><?php _e('Bookings', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-registered"><?php _e('Registered', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'sahayya-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                            $dependents = Sahayya_Booking_Database::get_dependents($customer->ID);
                            $bookings = Sahayya_Booking_Database::get_bookings(array('subscriber_id' => $customer->ID));
                            $last_booking = !empty($bookings) ? $bookings[0] : null;
                            ?>
                            <tr data-customer-id="<?php echo $customer->ID; ?>" 
                                data-has-dependents="<?php echo !empty($dependents) ? 'yes' : 'no'; ?>"
                                data-has-bookings="<?php echo !empty($bookings) ? 'yes' : 'no'; ?>">
                                <td class="column-customer">
                                    <strong><?php echo esc_html($customer->display_name); ?></strong>
                                    <div class="customer-meta">
                                        <?php echo esc_html($customer->user_login); ?>
                                    </div>
                                </td>
                                <td class="column-contact">
                                    <div class="contact-info">
                                        <div class="email"><?php echo esc_html($customer->user_email); ?></div>
                                        <?php 
                                        $phone = get_user_meta($customer->ID, 'phone', true);
                                        if ($phone): 
                                        ?>
                                            <div class="phone"><?php echo esc_html($phone); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-dependents">
                                    <span class="dependents-count"><?php echo count($dependents); ?></span>
                                    <?php if (!empty($dependents)): ?>
                                        <div class="dependents-preview">
                                            <?php foreach (array_slice($dependents, 0, 2) as $dependent): ?>
                                                <span class="dependent-name"><?php echo esc_html($dependent->name); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($dependents) > 2): ?>
                                                <span class="more-dependents">+<?php echo count($dependents) - 2; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="column-bookings">
                                    <span class="bookings-count"><?php echo count($bookings); ?></span>
                                    <?php if ($last_booking): ?>
                                        <div class="last-booking">
                                            <?php echo date('M j, Y', strtotime($last_booking->created_at)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="column-registered">
                                    <?php echo date('M j, Y', strtotime($customer->user_registered)); ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $customer->ID); ?>" 
                                       class="button button-small"><?php _e('View Details', 'sahayya-booking'); ?></a>
                                    
                                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=add_dependent&customer_id=' . $customer->ID); ?>" 
                                       class="button button-small button-secondary"><?php _e('Add Dependent', 'sahayya-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-customers-message">
                    <h3><?php _e('No customers found', 'sahayya-booking'); ?></h3>
                    <p><?php _e('Customers will appear here when they register and start using the booking system.', 'sahayya-booking'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .customers-overview {
            margin: 20px 0;
        }
        
        .customers-stats {
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
        
        .customer-meta {
            color: #666;
            font-size: 13px;
        }
        
        .contact-info .email {
            font-weight: 500;
        }
        
        .contact-info .phone {
            color: #666;
            font-size: 13px;
        }
        
        .dependents-count {
            font-weight: bold;
            font-size: 16px;
            color: #0073aa;
        }
        
        .dependents-preview {
            margin-top: 5px;
        }
        
        .dependent-name {
            display: inline-block;
            background: #f0f8ff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 5px;
            margin-bottom: 2px;
        }
        
        .more-dependents {
            color: #666;
            font-size: 12px;
        }
        
        .bookings-count {
            font-weight: bold;
            font-size: 16px;
            color: #28a745;
        }
        
        .last-booking {
            color: #666;
            font-size: 13px;
        }
        
        .no-customers-message {
            text-align: center;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        #customer-search {
            width: 200px;
            padding: 5px;
        }
        
        #customer-filter {
            margin-right: 10px;
        }
        </style>
        
        <script>
        function filterCustomers() {
            const filter = document.getElementById('customer-filter').value;
            const rows = document.querySelectorAll('#customers-table tbody tr');
            
            rows.forEach(row => {
                let show = true;
                
                switch(filter) {
                    case 'with-dependents':
                        show = row.dataset.hasDependents === 'yes';
                        break;
                    case 'with-bookings':
                        show = row.dataset.hasBookings === 'yes';
                        break;
                    case 'no-activity':
                        show = row.dataset.hasDependents === 'no' && row.dataset.hasBookings === 'no';
                        break;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        function searchCustomers() {
            const search = document.getElementById('customer-search').value.toLowerCase();
            const rows = document.querySelectorAll('#customers-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        }
        
        // Real-time search
        document.getElementById('customer-search').addEventListener('input', searchCustomers);
        </script>
        <?php
    }
    
    private function render_customer_details($customer_id) {
        $customer = get_userdata($customer_id);
        if (!$customer) {
            wp_die(__('Customer not found.', 'sahayya-booking'));
        }
        
        $dependents = Sahayya_Booking_Database::get_dependents($customer_id);
        $bookings = Sahayya_Booking_Database::get_bookings(array('subscriber_id' => $customer_id));
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Customer Details: %s', 'sahayya-booking'), esc_html($customer->display_name)); ?></h1>
            
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers'); ?>" class="button">
                ← <?php _e('Back to Customers', 'sahayya-booking'); ?>
            </a>
            
            <div class="customer-details-grid">
                <!-- Customer Information -->
                <div class="customer-info-section">
                    <h3><?php _e('Customer Information', 'sahayya-booking'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Full Name', 'sahayya-booking'); ?></th>
                            <td><?php echo esc_html($customer->display_name); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Username', 'sahayya-booking'); ?></th>
                            <td><?php echo esc_html($customer->user_login); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email', 'sahayya-booking'); ?></th>
                            <td><?php echo esc_html($customer->user_email); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Registered', 'sahayya-booking'); ?></th>
                            <td><?php echo date('F j, Y g:i A', strtotime($customer->user_registered)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Role', 'sahayya-booking'); ?></th>
                            <td><?php echo esc_html(implode(', ', $customer->roles)); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Dependents Section -->
                <div class="dependents-section">
                    <div class="section-header">
                        <h3><?php _e('Dependents', 'sahayya-booking'); ?></h3>
                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=add_dependent&customer_id=' . $customer_id); ?>" 
                           class="button button-primary"><?php _e('Add Dependent', 'sahayya-booking'); ?></a>
                    </div>
                    
                    <?php if (!empty($dependents)): ?>
                        <div class="dependents-grid">
                            <?php foreach ($dependents as $dependent): ?>
                                <div class="dependent-card">
                                    <div class="dependent-header">
                                        <h4><?php echo esc_html($dependent->name); ?></h4>
                                        <span class="dependent-age"><?php echo esc_html($dependent->age); ?> years</span>
                                    </div>
                                    
                                    <div class="dependent-details">
                                        <p><strong><?php _e('Gender:', 'sahayya-booking'); ?></strong> <?php echo esc_html(ucfirst($dependent->gender)); ?></p>
                                        <p><strong><?php _e('Address:', 'sahayya-booking'); ?></strong> <?php echo esc_html($dependent->address); ?></p>
                                        
                                        <?php if ($dependent->phone): ?>
                                            <p><strong><?php _e('Phone:', 'sahayya-booking'); ?></strong> <?php echo esc_html($dependent->phone); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($dependent->medical_conditions): ?>
                                            <p><strong><?php _e('Medical Conditions:', 'sahayya-booking'); ?></strong></p>
                                            <div class="medical-info"><?php echo esc_html($dependent->medical_conditions); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($dependent->emergency_contact): ?>
                                            <p><strong><?php _e('Emergency Contact:', 'sahayya-booking'); ?></strong> <?php echo esc_html($dependent->emergency_contact); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="dependent-actions">
                                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=edit_dependent&dependent_id=' . $dependent->id); ?>" 
                                           class="button button-small"><?php _e('Edit', 'sahayya-booking'); ?></a>
                                        
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sahayya-booking-customers&action=delete_dependent&dependent_id=' . $dependent->id), 'delete_dependent_' . $dependent->id); ?>" 
                                           class="button button-small button-link-delete" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this dependent?', 'sahayya-booking'); ?>')">
                                            <?php _e('Delete', 'sahayya-booking'); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-dependents">
                            <p><?php _e('No dependents added yet.', 'sahayya-booking'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=add_dependent&customer_id=' . $customer_id); ?>" 
                               class="button button-primary"><?php _e('Add First Dependent', 'sahayya-booking'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Bookings Section -->
                <div class="bookings-section">
                    <h3><?php _e('Booking History', 'sahayya-booking'); ?></h3>
                    
                    <?php if (!empty($bookings)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Booking #', 'sahayya-booking'); ?></th>
                                    <th><?php _e('Service', 'sahayya-booking'); ?></th>
                                    <th><?php _e('Date', 'sahayya-booking'); ?></th>
                                    <th><?php _e('Status', 'sahayya-booking'); ?></th>
                                    <th><?php _e('Amount', 'sahayya-booking'); ?></th>
                                    <th><?php _e('Actions', 'sahayya-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($bookings, 0, 10) as $booking): ?>
                                    <?php $service = Sahayya_Booking_Database::get_service($booking->service_id); ?>
                                    <tr>
                                        <td><?php echo esc_html($booking->booking_number); ?></td>
                                        <td><?php echo esc_html($service ? $service->name : 'Unknown Service'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($booking->booking_date)); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->booking_status))); ?>
                                            </span>
                                        </td>
                                        <td>₹<?php echo number_format($booking->total_amount, 2); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&action=view&booking_id=' . $booking->id); ?>" 
                                               class="button button-small"><?php _e('View', 'sahayya-booking'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (count($bookings) > 10): ?>
                            <p><a href="<?php echo admin_url('admin.php?page=sahayya-booking-bookings&customer=' . $customer_id); ?>">
                                <?php _e('View all bookings for this customer', 'sahayya-booking'); ?>
                            </a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-bookings">
                            <p><?php _e('No bookings found for this customer.', 'sahayya-booking'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .customer-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .customer-info-section,
        .dependents-section,
        .bookings-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bookings-section {
            grid-column: 1 / -1;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .dependents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .dependent-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
        }
        
        .dependent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .dependent-header h4 {
            margin: 0;
            color: #333;
        }
        
        .dependent-age {
            background: #0073aa;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .medical-info {
            background: #fff5f5;
            border-left: 3px solid #dc3545;
            padding: 8px;
            margin: 5px 0;
            font-size: 13px;
        }
        
        .dependent-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .no-dependents,
        .no-bookings {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .customer-details-grid {
                grid-template-columns: 1fr;
            }
            
            .dependents-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    private function render_add_dependent_form($customer_id) {
        $customer = get_userdata($customer_id);
        if (!$customer) {
            wp_die(__('Customer not found.', 'sahayya-booking'));
        }
        
        $this->render_dependent_form($customer, null);
    }
    
    private function render_edit_dependent_form($dependent_id) {
        $dependent = Sahayya_Booking_Database::get_dependent($dependent_id);
        if (!$dependent) {
            wp_die(__('Dependent not found.', 'sahayya-booking'));
        }
        
        $customer = get_userdata($dependent->subscriber_id);
        $this->render_dependent_form($customer, $dependent);
    }
    
    private function render_dependent_form($customer, $dependent = null) {
        $is_edit = !empty($dependent);
        $form_title = $is_edit ? 
            sprintf(__('Edit Dependent: %s', 'sahayya-booking'), $dependent->name) : 
            sprintf(__('Add Dependent for %s', 'sahayya-booking'), $customer->display_name);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($form_title); ?></h1>
            
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $customer->ID); ?>" class="button">
                ← <?php _e('Back to Customer Details', 'sahayya-booking'); ?>
            </a>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sahayya_customer_action'); ?>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="dependent_id" value="<?php echo esc_attr($dependent->id); ?>" />
                    <input type="hidden" name="action" value="edit_dependent" />
                <?php else: ?>
                    <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer->ID); ?>" />
                    <input type="hidden" name="action" value="add_dependent" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="dependent_name"><?php _e('Full Name', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="dependent_name" name="dependent_name" 
                                       value="<?php echo esc_attr($dependent->name ?? ''); ?>" 
                                       class="regular-text" required />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="dependent_age"><?php _e('Age', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <input type="number" id="dependent_age" name="dependent_age" 
                                       value="<?php echo esc_attr($dependent->age ?? ''); ?>" 
                                       min="1" max="120" class="small-text" required />
                                <span class="description"><?php _e('Age in years', 'sahayya-booking'); ?></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="dependent_gender"><?php _e('Gender', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <select id="dependent_gender" name="dependent_gender">
                                    <option value="male" <?php selected($dependent->gender ?? 'male', 'male'); ?>><?php _e('Male', 'sahayya-booking'); ?></option>
                                    <option value="female" <?php selected($dependent->gender ?? 'male', 'female'); ?>><?php _e('Female', 'sahayya-booking'); ?></option>
                                    <option value="other" <?php selected($dependent->gender ?? 'male', 'other'); ?>><?php _e('Other', 'sahayya-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="dependent_address"><?php _e('Address', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <textarea id="dependent_address" name="dependent_address" 
                                          rows="3" class="large-text" required><?php echo esc_textarea($dependent->address ?? ''); ?></textarea>
                                <p class="description"><?php _e('Complete address where services will be provided', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="dependent_phone"><?php _e('Phone Number', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <input type="tel" id="dependent_phone" name="dependent_phone" 
                                       value="<?php echo esc_attr($dependent->phone ?? ''); ?>" 
                                       class="regular-text" />
                                <p class="description"><?php _e('Direct contact number for the dependent', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="medical_conditions"><?php _e('Medical Conditions', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <textarea id="medical_conditions" name="medical_conditions" 
                                          rows="4" class="large-text"><?php echo esc_textarea($dependent->medical_conditions ?? ''); ?></textarea>
                                <p class="description"><?php _e('Any medical conditions, allergies, medications, or special care requirements', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="emergency_contact"><?php _e('Emergency Contact', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo esc_attr($dependent->emergency_contact ?? ''); ?>" 
                                       class="large-text" />
                                <p class="description"><?php _e('Local emergency contact name and phone number', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="dependent_photo"><?php _e('Photo', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <input type="file" id="dependent_photo" name="dependent_photo" accept="image/*" />
                                <?php if ($is_edit && $dependent->photo): ?>
                                    <p>
                                        <img src="<?php echo esc_url($dependent->photo); ?>" 
                                             alt="<?php echo esc_attr($dependent->name); ?>" 
                                             style="max-width: 100px; height: auto; display: block; margin-top: 10px; border-radius: 4px;" />
                                        <span class="description"><?php _e('Current photo', 'sahayya-booking'); ?></span>
                                    </p>
                                <?php endif; ?>
                                <p class="description"><?php _e('Upload a recent photo to help service providers identify the dependent', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" 
                           value="<?php echo $is_edit ? __('Update Dependent', 'sahayya-booking') : __('Add Dependent', 'sahayya-booking'); ?>" />
                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $customer->ID); ?>" class="button">
                        <?php _e('Cancel', 'sahayya-booking'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function handle_form_submission() {
        $action = sanitize_text_field($_POST['action']);
        
        if ($action === 'add_dependent') {
            $this->handle_add_dependent();
        } elseif ($action === 'edit_dependent') {
            $this->handle_edit_dependent();
        }
    }
    
    private function handle_add_dependent() {
        $customer_id = intval($_POST['customer_id']);
        $dependent_data = $this->sanitize_dependent_data($_POST);
        $dependent_data['subscriber_id'] = $customer_id;
        
        // Handle photo upload
        if (!empty($_FILES['dependent_photo']['name'])) {
            $photo_url = $this->handle_photo_upload($_FILES['dependent_photo']);
            if ($photo_url) {
                $dependent_data['photo'] = $photo_url;
            }
        }
        
        $dependent_id = Sahayya_Booking_Database::create_dependent($dependent_data);
        
        if ($dependent_id) {
            // Mark customer as having used the system
            update_user_meta($customer_id, 'sahayya_is_customer', true);
            
            wp_redirect(admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $customer_id . '&message=dependent_added'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-customers&action=add_dependent&customer_id=' . $customer_id . '&error=db_error'));
            exit;
        }
    }
    
    private function handle_edit_dependent() {
        $dependent_id = intval($_POST['dependent_id']);
        $dependent = Sahayya_Booking_Database::get_dependent($dependent_id);
        
        if (!$dependent) {
            wp_die(__('Dependent not found.', 'sahayya-booking'));
        }
        
        $dependent_data = $this->sanitize_dependent_data($_POST);
        
        // Handle photo upload
        if (!empty($_FILES['dependent_photo']['name'])) {
            $photo_url = $this->handle_photo_upload($_FILES['dependent_photo']);
            if ($photo_url) {
                $dependent_data['photo'] = $photo_url;
            }
        }
        
        $result = Sahayya_Booking_Database::update_dependent($dependent_id, $dependent_data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $dependent->subscriber_id . '&message=dependent_updated'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-customers&action=edit_dependent&dependent_id=' . $dependent_id . '&error=db_error'));
            exit;
        }
    }
    
    private function handle_delete_dependent($dependent_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_dependent_' . $dependent_id)) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $dependent = Sahayya_Booking_Database::get_dependent($dependent_id);
        if (!$dependent) {
            wp_die(__('Dependent not found.', 'sahayya-booking'));
        }
        
        $result = Sahayya_Booking_Database::delete_dependent($dependent_id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $dependent->subscriber_id . '&message=dependent_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-customers&action=view&customer_id=' . $dependent->subscriber_id . '&error=delete_failed'));
        }
        exit;
    }
    
    private function sanitize_dependent_data($data) {
        return array(
            'name' => sanitize_text_field($data['dependent_name']),
            'age' => intval($data['dependent_age']),
            'gender' => sanitize_text_field($data['dependent_gender']),
            'address' => sanitize_textarea_field($data['dependent_address']),
            'phone' => sanitize_text_field($data['dependent_phone']),
            'medical_conditions' => sanitize_textarea_field($data['medical_conditions']),
            'emergency_contact' => sanitize_text_field($data['emergency_contact'])
        );
    }
    
    private function handle_photo_upload($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            return $movefile['url'];
        }
        
        return false;
    }
    
    private function show_messages() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'dependent_added' => __('Dependent added successfully!', 'sahayya-booking'),
                'dependent_updated' => __('Dependent updated successfully!', 'sahayya-booking'),
                'dependent_deleted' => __('Dependent deleted successfully!', 'sahayya-booking')
            );
            
            if (isset($messages[$message])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $messages[$message] . '</p></div>';
            }
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $errors = array(
                'db_error' => __('Database error occurred. Please try again.', 'sahayya-booking'),
                'delete_failed' => __('Failed to delete dependent. Please try again.', 'sahayya-booking')
            );
            
            if (isset($errors[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $errors[$error] . '</p></div>';
            }
        }
    }
    
    // Helper methods
    private function get_total_dependents() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_dependents';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
    }
    
    private function get_active_bookings_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'sahayya_bookings';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE booking_status IN ('pending', 'confirmed', 'assigned', 'in_progress')");
    }
}