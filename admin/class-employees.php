<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Employees {
    
    public function __construct() {
        add_action('init', array($this, 'handle_actions'));
    }
    
    public function handle_actions() {
        if (!current_user_can('manage_sahayya_employees')) {
            return;
        }
        
        $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
        
        switch ($action) {
            case 'add_employee':
                $this->add_employee();
                break;
            case 'create_new_employee':
                $this->create_new_employee();
                break;
            case 'edit_employee':
                $this->edit_employee();
                break;
            case 'delete_employee':
                $this->delete_employee();
                break;
            case 'toggle_availability':
                $this->toggle_availability();
                break;
        }
    }
    
    public function render_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_add_form();
                break;
            case 'edit':
                $this->render_edit_form($employee_id);
                break;
            case 'view':
                $this->render_employee_details($employee_id);
                break;
            default:
                $this->render_employees_list();
                break;
        }
    }
    
    private function render_employees_list() {
        $stats = Sahayya_Booking_Database::get_employee_stats();
        $employees = Sahayya_Booking_Database::get_employees();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Employees', 'sahayya-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=add'); ?>" class="page-title-action"><?php _e('Add New Employee', 'sahayya-booking'); ?></a>
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php _e('Success!', 'sahayya-booking'); ?></strong> <?php echo esc_html($this->get_message($_GET['message'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong><?php _e('Error!', 'sahayya-booking'); ?></strong> <?php echo esc_html($this->get_error_message($_GET['error'])); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="employee-stats">
                <div class="stat-box">
                    <h3><?php _e('Total Employees', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo intval($stats['total']); ?></span>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Available Now', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo intval($stats['available']); ?></span>
                </div>
                <div class="stat-box">
                    <h3><?php _e('On Service', 'sahayya-booking'); ?></h3>
                    <span class="stat-number"><?php echo intval($stats['on_service']); ?></span>
                </div>
            </div>
            
            <?php if (empty($employees)): ?>
                <div class="no-items">
                    <p><?php _e('No employees found.', 'sahayya-booking'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=add'); ?>" class="button button-primary"><?php _e('Add Your First Employee', 'sahayya-booking'); ?></a>
                </div>
            <?php else: ?>
                <div class="employees-grid">
                    <?php foreach ($employees as $employee): 
                        $bookings_count = Sahayya_Booking_Database::get_employee_bookings_count($employee->id);
                    ?>
                        <div class="employee-card" data-employee-id="<?php echo $employee->id; ?>">
                            <div class="employee-header">
                                <div class="employee-avatar">
                                    <?php echo get_avatar($employee->user_email, 60); ?>
                                </div>
                                <div class="employee-info">
                                    <h3><?php echo esc_html($employee->display_name); ?></h3>
                                    <p class="employee-code"><?php echo esc_html($employee->employee_code); ?></p>
                                    <div class="employee-badges">
                                        <span class="status-badge status-<?php echo esc_attr($employee->status); ?>">
                                            <?php echo ucfirst($employee->status); ?>
                                        </span>
                                        <span class="availability-badge availability-<?php echo esc_attr($employee->availability_status); ?>">
                                            <?php echo ucfirst($employee->availability_status); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="employee-details">
                                <div class="detail-row">
                                    <span class="label"><?php _e('Phone:', 'sahayya-booking'); ?></span>
                                    <span class="value"><?php echo esc_html($employee->phone ?: 'Not provided'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label"><?php _e('Rating:', 'sahayya-booking'); ?></span>
                                    <span class="value rating">
                                        <?php echo number_format($employee->rating, 1); ?> ⭐
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="label"><?php _e('Total Services:', 'sahayya-booking'); ?></span>
                                    <span class="value"><?php echo intval($bookings_count); ?></span>
                                </div>
                                <?php if (!empty($employee->skills)): ?>
                                    <div class="detail-row">
                                        <span class="label"><?php _e('Skills:', 'sahayya-booking'); ?></span>
                                        <span class="value"><?php echo esc_html($employee->skills); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="employee-actions">
                                <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=view&employee_id=' . $employee->id); ?>" class="button button-small"><?php _e('View Details', 'sahayya-booking'); ?></a>
                                <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=edit&employee_id=' . $employee->id); ?>" class="button button-small"><?php _e('Edit', 'sahayya-booking'); ?></a>
                                
                                <?php if ($employee->availability_status === 'available'): ?>
                                    <button class="button button-small toggle-availability" data-employee-id="<?php echo $employee->id; ?>" data-status="busy">
                                        <?php _e('Mark Busy', 'sahayya-booking'); ?>
                                    </button>
                                <?php else: ?>
                                    <button class="button button-small toggle-availability" data-employee-id="<?php echo $employee->id; ?>" data-status="available">
                                        <?php _e('Mark Available', 'sahayya-booking'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-small button-link-delete delete-employee" data-employee-id="<?php echo $employee->id; ?>" data-name="<?php echo esc_attr($employee->display_name); ?>" data-nonce="<?php echo wp_create_nonce('delete_employee_' . $employee->id); ?>">
                                    <?php _e('Delete', 'sahayya-booking'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $this->render_employee_styles();
    }
    
    private function render_add_form() {
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Employee', 'sahayya-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees'); ?>" class="page-title-action"><?php _e('← Back to Employees', 'sahayya-booking'); ?></a>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong><?php _e('Error!', 'sahayya-booking'); ?></strong> <?php echo esc_html($this->get_error_message($_GET['error'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Registration Method Tabs -->
            <div class="registration-tabs">
                <button type="button" class="tab-button active" data-tab="new-user"><?php _e('Create New Employee', 'sahayya-booking'); ?></button>
                <button type="button" class="tab-button" data-tab="existing-user"><?php _e('Use Existing User', 'sahayya-booking'); ?></button>
            </div>
            
            <!-- Tab 1: Create New Employee -->
            <div id="new-user-tab" class="tab-content active">
                <form method="post" class="employee-form">
                    <?php wp_nonce_field('sahayya_employee_action', 'employee_nonce'); ?>
                    <input type="hidden" name="action" value="create_new_employee">
                    
                    <table class="form-table">
                        <tr>
                            <th colspan="2">
                                <h3 style="margin: 20px 0 10px 0; color: #0073aa;"><?php _e('User Account Information', 'sahayya-booking'); ?></h3>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="username"><?php _e('Username', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" name="username" id="username" class="regular-text" required>
                                <div id="username_validation" class="validation-message"></div>
                                <p class="description"><?php _e('Username for login (cannot be changed later).', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="email"><?php _e('Email Address', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <input type="email" name="email" id="email" class="regular-text" required>
                                <div id="email_validation" class="validation-message"></div>
                                <p class="description"><?php _e('Email address for the employee account.', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="display_name"><?php _e('Full Name', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" name="display_name" id="display_name" class="regular-text" required>
                                <p class="description"><?php _e('Employee\'s full name for display.', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="password"><?php _e('Password', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <input type="password" name="password" id="password" class="regular-text">
                                <p class="description"><?php _e('Leave blank to auto-generate a password and send email.', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2">
                                <h3 style="margin: 20px 0 10px 0; color: #0073aa;"><?php _e('Employee Information', 'sahayya-booking'); ?></h3>
                            </th>
                        </tr>
                    <tr>
                        <th scope="row">
                            <label for="phone"><?php _e('Phone Number', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="tel" name="phone" id="phone" class="regular-text" placeholder="+91 98765 43210">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="address"><?php _e('Address', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="address" id="address" class="large-text" rows="3" placeholder="Full address with pincode"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="skills"><?php _e('Skills & Specializations', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="skills" id="skills" class="large-text" rows="2" placeholder="e.g., First Aid, Elderly Care, Emergency Response"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="service_areas"><?php _e('Service Areas', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="service_areas" id="service_areas" class="large-text" rows="2" placeholder="Areas/localities where employee can provide services"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="license_number"><?php _e('License Number', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="license_number" id="license_number" class="regular-text" placeholder="Driving license or professional license">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vehicle_details"><?php _e('Vehicle Details', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="vehicle_details" id="vehicle_details" class="regular-text" placeholder="Vehicle type and registration number">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="emergency_contact"><?php _e('Emergency Contact', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="emergency_contact" id="emergency_contact" class="regular-text" placeholder="Emergency contact person and number">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="availability_status"><?php _e('Initial Availability', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <select name="availability_status" id="availability_status" class="regular-text">
                                <option value="available"><?php _e('Available', 'sahayya-booking'); ?></option>
                                <option value="busy"><?php _e('Busy', 'sahayya-booking'); ?></option>
                                <option value="offline"><?php _e('Offline', 'sahayya-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Create Employee Account', 'sahayya-booking')); ?>
            </table>
            </form>
        </div>
        
        <!-- Tab 2: Use Existing User -->
        <div id="existing-user-tab" class="tab-content">
            <form method="post" class="employee-form">
                <?php wp_nonce_field('sahayya_employee_action', 'employee_nonce'); ?>
                <input type="hidden" name="action" value="add_employee">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_search"><?php _e('Search User', 'sahayya-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="user_search" class="regular-text" placeholder="Type username or email to search...">
                            <div id="user_search_results" class="search-results"></div>
                            <input type="hidden" name="user_id" id="selected_user_id" required>
                            <p class="description"><?php _e('Search for an existing WordPress user by username or email.', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <h3 style="margin: 20px 0 10px 0; color: #0073aa;"><?php _e('Employee Information', 'sahayya-booking'); ?></h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="phone_existing"><?php _e('Phone Number', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="tel" name="phone" id="phone_existing" class="regular-text" placeholder="+91 98765 43210">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="address_existing"><?php _e('Address', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="address" id="address_existing" class="large-text" rows="3" placeholder="Full address with pincode"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="skills_existing"><?php _e('Skills & Specializations', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="skills" id="skills_existing" class="large-text" rows="2" placeholder="e.g., First Aid, Elderly Care, Emergency Response"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="service_areas_existing"><?php _e('Service Areas', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="service_areas" id="service_areas_existing" class="large-text" rows="2" placeholder="Areas/localities where employee can provide services"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="license_number_existing"><?php _e('License Number', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="license_number" id="license_number_existing" class="regular-text" placeholder="Driving license or professional license">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vehicle_details_existing"><?php _e('Vehicle Details', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="vehicle_details" id="vehicle_details_existing" class="regular-text" placeholder="Vehicle type and registration number">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="emergency_contact_existing"><?php _e('Emergency Contact', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="emergency_contact" id="emergency_contact_existing" class="regular-text" placeholder="Emergency contact person and number">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="availability_status_existing"><?php _e('Initial Availability', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <select name="availability_status" id="availability_status_existing" class="regular-text">
                                <option value="available"><?php _e('Available', 'sahayya-booking'); ?></option>
                                <option value="busy"><?php _e('Busy', 'sahayya-booking'); ?></option>
                                <option value="offline"><?php _e('Offline', 'sahayya-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Add Employee', 'sahayya-booking')); ?>
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.tab-button').on('click', function() {
            var tab = $(this).data('tab');
            
            $('.tab-button').removeClass('active');
            $('.tab-content').removeClass('active');
            
            $(this).addClass('active');
            $('#' + tab + '-tab').addClass('active');
        });
        
        // Real-time validation
        var validationTimeout;
        var validationNonce = '<?php echo wp_create_nonce('employee_validation'); ?>';
        
        // Username validation
        $('#username').on('input', function() {
            var username = $(this).val();
            var validationDiv = $('#username_validation');
            
            clearTimeout(validationTimeout);
            
            if (username.length < 3) {
                validationDiv.html('<span class="validation-info">Username must be at least 3 characters</span>');
                return;
            }
            
            validationDiv.html('<span class="validation-checking">Checking availability...</span>');
            
            validationTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sahayya_validate_username',
                        username: username,
                        nonce: validationNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            validationDiv.html('<span class="validation-success">✓ Username is available</span>');
                        } else {
                            validationDiv.html('<span class="validation-error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        validationDiv.html('<span class="validation-error">Error checking username availability</span>');
                    }
                });
            }, 500);
        });
        
        // Email validation
        $('#email').on('input', function() {
            var email = $(this).val();
            var validationDiv = $('#email_validation');
            
            clearTimeout(validationTimeout);
            
            if (email.length < 3 || !email.includes('@')) {
                validationDiv.html('<span class="validation-info">Please enter a valid email address</span>');
                return;
            }
            
            validationDiv.html('<span class="validation-checking">Checking availability...</span>');
            
            validationTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sahayya_validate_email',
                        email: email,
                        nonce: validationNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            validationDiv.html('<span class="validation-success">✓ Email is available</span>');
                        } else {
                            validationDiv.html('<span class="validation-error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        validationDiv.html('<span class="validation-error">Error checking email availability</span>');
                    }
                });
            }, 500);
        });
        
        // Form submission with progress indicator
        $('.employee-form').on('submit', function(e) {
            var form = $(this);
            var submitButton = form.find('input[type="submit"]');
            var originalText = submitButton.val();
            
            // Show loading state
            submitButton.val('Creating Employee...').prop('disabled', true);
            
            // Add loading indicator
            if (!form.find('.loading-indicator').length) {
                form.append('<div class="loading-indicator">Processing... Please wait.</div>');
            }
            
            // If there are validation errors, prevent submission
            if ($('.validation-error').length > 0) {
                e.preventDefault();
                submitButton.val(originalText).prop('disabled', false);
                form.find('.loading-indicator').remove();
                alert('Please fix the validation errors before submitting.');
                return false;
            }
        });
        
        // User search functionality
        var searchTimeout;
        $('#user_search').on('input', function() {
            var searchTerm = $(this).val();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                $('#user_search_results').empty();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sahayya_search_users',
                        search: searchTerm,
                        nonce: '<?php echo wp_create_nonce('search_users'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var results = '<div class="user-results">';
                            $.each(response.data, function(index, user) {
                                results += '<div class="user-result" data-user-id="' + user.ID + '">';
                                results += '<strong>' + user.display_name + '</strong> (' + user.user_email + ')';
                                results += '<br><small>Username: ' + user.user_login + '</small>';
                                results += '</div>';
                            });
                            results += '</div>';
                            $('#user_search_results').html(results);
                        }
                    }
                });
            }, 300);
        });
        
        // Select user from search results
        $(document).on('click', '.user-result', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).find('strong').text();
            var userEmail = $(this).text().match(/\(([^)]+)\)/)[1];
            
            $('#selected_user_id').val(userId);
            $('#user_search').val(userName + ' (' + userEmail + ')');
            $('#user_search_results').empty();
        });
        
        // Clear search when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#user_search, #user_search_results').length) {
                $('#user_search_results').empty();
            }
        });
    });
    </script>
    
    <style>
    .registration-tabs {
        margin: 20px 0;
        border-bottom: 1px solid #ddd;
    }
    
    .tab-button {
        background: #f1f1f1;
        border: 1px solid #ddd;
        border-bottom: none;
        padding: 10px 20px;
        margin-right: 5px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .tab-button.active {
        background: #fff;
        font-weight: bold;
    }
    
    .tab-content {
        display: none;
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-top: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .search-results {
        position: relative;
        z-index: 1000;
    }
    
    .user-results {
        border: 1px solid #ddd;
        background: #fff;
        max-height: 200px;
        overflow-y: auto;
        margin-top: 5px;
    }
    
    .user-result {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }
    
    .user-result:hover {
        background: #f1f1f1;
    }
    
    .user-result:last-child {
        border-bottom: none;
    }
    
    /* Validation Messages */
    .validation-message {
        margin: 5px 0;
        font-size: 13px;
    }
    
    .validation-success {
        color: #046f46;
        font-weight: bold;
    }
    
    .validation-error {
        color: #cc1818;
        font-weight: bold;
    }
    
    .validation-checking {
        color: #0073aa;
        font-style: italic;
    }
    
    .validation-info {
        color: #646970;
        font-style: italic;
    }
    
    /* Loading indicator */
    .loading-indicator {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 4px;
        padding: 10px;
        margin: 10px 0;
        color: #856404;
        text-align: center;
        font-weight: bold;
    }
    
    /* Form improvements */
    .employee-form input:invalid {
        border-color: #dc3545;
    }
    
    .employee-form input:valid {
        border-color: #28a745;
    }
    
    /* Submit button disabled state */
    .employee-form input[type="submit"]:disabled {
        background: #f0f0f1;
        border-color: #dcdcde;
        color: #a7aaad;
        cursor: not-allowed;
    }
    </style>
    
    <?php
    $this->render_employee_styles();
    }
    
    private function add_employee() {
        if (!wp_verify_nonce($_POST['employee_nonce'], 'sahayya_employee_action')) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=invalid_user'));
            exit;
        }
        
        // Check if user is already an employee
        $existing = Sahayya_Booking_Database::get_employee_by_user_id($user_id);
        if ($existing) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=already_employee'));
            exit;
        }
        
        // Add employee role to user
        $user = new WP_User($user_id);
        $user->add_role('sahayya_employee');
        
        // Create employee record
        $employee_data = array(
            'user_id' => $user_id,
            'employee_code' => 'EMP' . str_pad($user_id, 4, '0', STR_PAD_LEFT),
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'skills' => sanitize_textarea_field($_POST['skills']),
            'service_areas' => sanitize_textarea_field($_POST['service_areas']),
            'availability_status' => sanitize_text_field($_POST['availability_status']),
            'rating' => 5.00,
            'total_services' => 0,
            'license_number' => sanitize_text_field($_POST['license_number']),
            'vehicle_details' => sanitize_text_field($_POST['vehicle_details']),
            'emergency_contact' => sanitize_text_field($_POST['emergency_contact']),
            'status' => 'active'
        );
        
        $employee_id = Sahayya_Booking_Database::create_employee($employee_data);
        
        if ($employee_id) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&message=employee_added'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=creation_failed'));
        }
        exit;
    }
    
    private function create_new_employee() {
        if (!wp_verify_nonce($_POST['employee_nonce'], 'sahayya_employee_action')) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $password = sanitize_text_field($_POST['password']);
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($display_name)) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=missing_fields'));
            exit;
        }
        
        // Check if username already exists
        if (username_exists($username)) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=username_exists'));
            exit;
        }
        
        // Check if email already exists
        if (email_exists($email)) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=email_exists'));
            exit;
        }
        
        // Generate password if not provided
        if (empty($password)) {
            $password = wp_generate_password(12, false);
            $send_notification = true;
        } else {
            $send_notification = false;
        }
        
        // Create WordPress user
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'display_name' => $display_name,
            'user_pass' => $password,
            'role' => 'sahayya_employee'
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=user_creation_failed'));
            exit;
        }
        
        // Create employee record
        $employee_data = array(
            'user_id' => $user_id,
            'employee_code' => 'EMP' . str_pad($user_id, 4, '0', STR_PAD_LEFT),
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'skills' => sanitize_textarea_field($_POST['skills']),
            'service_areas' => sanitize_textarea_field($_POST['service_areas']),
            'availability_status' => sanitize_text_field($_POST['availability_status']),
            'rating' => 5.00,
            'total_services' => 0,
            'license_number' => sanitize_text_field($_POST['license_number']),
            'vehicle_details' => sanitize_text_field($_POST['vehicle_details']),
            'emergency_contact' => sanitize_text_field($_POST['emergency_contact']),
            'status' => 'active'
        );
        
        $employee_id = Sahayya_Booking_Database::create_employee($employee_data);
        
        if ($employee_id) {
            // Send notification email if password was auto-generated
            if ($send_notification) {
                wp_new_user_notification($user_id, null, 'both');
            }
            
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&message=employee_created'));
        } else {
            // Delete the user if employee creation failed
            wp_delete_user($user_id);
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=add&error=employee_creation_failed'));
        }
        exit;
    }
    
    private function render_edit_form($employee_id) {
        $employee = Sahayya_Booking_Database::get_employee($employee_id);
        if (!$employee) {
            echo '<div class="notice notice-error"><p>' . __('Employee not found.', 'sahayya-booking') . '</p></div>';
            return;
        }
        
        $user = get_userdata($employee->user_id);
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Edit Employee: %s', 'sahayya-booking'), esc_html($user->display_name)); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees'); ?>" class="page-title-action"><?php _e('← Back to Employees', 'sahayya-booking'); ?></a>
            
            <form method="post" class="employee-form">
                <?php wp_nonce_field('sahayya_employee_action', 'employee_nonce'); ?>
                <input type="hidden" name="action" value="edit_employee">
                <input type="hidden" name="employee_id" value="<?php echo $employee->id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('User Account', 'sahayya-booking'); ?></th>
                        <td>
                            <strong><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></strong>
                            <p class="description"><?php _e('User account cannot be changed after creation.', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="employee_code"><?php _e('Employee Code', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="employee_code" id="employee_code" class="regular-text" value="<?php echo esc_attr($employee->employee_code); ?>" readonly>
                            <p class="description"><?php _e('Employee code is auto-generated and cannot be changed.', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="phone"><?php _e('Phone Number', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="tel" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($employee->phone); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="address"><?php _e('Address', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="address" id="address" class="large-text" rows="3"><?php echo esc_textarea($employee->address); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="skills"><?php _e('Skills & Specializations', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="skills" id="skills" class="large-text" rows="2"><?php echo esc_textarea($employee->skills); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="service_areas"><?php _e('Service Areas', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="service_areas" id="service_areas" class="large-text" rows="2"><?php echo esc_textarea($employee->service_areas); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="license_number"><?php _e('License Number', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="license_number" id="license_number" class="regular-text" value="<?php echo esc_attr($employee->license_number); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vehicle_details"><?php _e('Vehicle Details', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="vehicle_details" id="vehicle_details" class="regular-text" value="<?php echo esc_attr($employee->vehicle_details); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="emergency_contact"><?php _e('Emergency Contact', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="emergency_contact" id="emergency_contact" class="regular-text" value="<?php echo esc_attr($employee->emergency_contact); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="availability_status"><?php _e('Availability Status', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <select name="availability_status" id="availability_status" class="regular-text">
                                <option value="available" <?php selected($employee->availability_status, 'available'); ?>><?php _e('Available', 'sahayya-booking'); ?></option>
                                <option value="busy" <?php selected($employee->availability_status, 'busy'); ?>><?php _e('Busy', 'sahayya-booking'); ?></option>
                                <option value="offline" <?php selected($employee->availability_status, 'offline'); ?>><?php _e('Offline', 'sahayya-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e('Employee Status', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <select name="status" id="status" class="regular-text">
                                <option value="active" <?php selected($employee->status, 'active'); ?>><?php _e('Active', 'sahayya-booking'); ?></option>
                                <option value="inactive" <?php selected($employee->status, 'inactive'); ?>><?php _e('Inactive', 'sahayya-booking'); ?></option>
                                <option value="suspended" <?php selected($employee->status, 'suspended'); ?>><?php _e('Suspended', 'sahayya-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rating"><?php _e('Rating', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="rating" id="rating" class="small-text" value="<?php echo esc_attr($employee->rating); ?>" min="1" max="5" step="0.1">
                            <span class="description"><?php _e('Rating out of 5', 'sahayya-booking'); ?></span>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Update Employee', 'sahayya-booking')); ?>
            </form>
        </div>
        <?php
        $this->render_employee_styles();
    }
    
    private function render_employee_details($employee_id) {
        $employee = Sahayya_Booking_Database::get_employee($employee_id);
        if (!$employee) {
            echo '<div class="notice notice-error"><p>' . __('Employee not found.', 'sahayya-booking') . '</p></div>';
            return;
        }
        
        $user = get_userdata($employee->user_id);
        $bookings_count = Sahayya_Booking_Database::get_employee_bookings_count($employee->id);
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('Employee Details: %s', 'sahayya-booking'), esc_html($user->display_name)); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees'); ?>" class="page-title-action"><?php _e('← Back to Employees', 'sahayya-booking'); ?></a>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=edit&employee_id=' . $employee->id); ?>" class="page-title-action"><?php _e('Edit Employee', 'sahayya-booking'); ?></a>
            
            <div class="employee-details-view">
                <div class="employee-profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo get_avatar($user->user_email, 80); ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo esc_html($user->display_name); ?></h2>
                            <p class="employee-code"><?php echo esc_html($employee->employee_code); ?></p>
                            <div class="status-badges">
                                <span class="status-badge status-<?php echo esc_attr($employee->status); ?>">
                                    <?php echo ucfirst($employee->status); ?>
                                </span>
                                <span class="availability-badge availability-<?php echo esc_attr($employee->availability_status); ?>">
                                    <?php echo ucfirst($employee->availability_status); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <h3><?php _e('Contact Information', 'sahayya-booking'); ?></h3>
                        <table class="employee-details-table">
                            <tr>
                                <th><?php _e('Email:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($user->user_email); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Phone:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->phone ?: 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Address:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->address ?: 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Emergency Contact:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->emergency_contact ?: 'Not provided'); ?></td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Professional Information', 'sahayya-booking'); ?></h3>
                        <table class="employee-details-table">
                            <tr>
                                <th><?php _e('Skills:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->skills ?: 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Service Areas:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->service_areas ?: 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('License Number:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->license_number ?: 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Vehicle Details:', 'sahayya-booking'); ?></th>
                                <td><?php echo esc_html($employee->vehicle_details ?: 'Not provided'); ?></td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Performance Statistics', 'sahayya-booking'); ?></h3>
                        <table class="employee-details-table">
                            <tr>
                                <th><?php _e('Rating:', 'sahayya-booking'); ?></th>
                                <td><?php echo number_format($employee->rating, 1); ?> ⭐</td>
                            </tr>
                            <tr>
                                <th><?php _e('Total Services:', 'sahayya-booking'); ?></th>
                                <td><?php echo intval($bookings_count); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Joined:', 'sahayya-booking'); ?></th>
                                <td><?php echo date('F j, Y', strtotime($employee->created_at)); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Last Updated:', 'sahayya-booking'); ?></th>
                                <td><?php echo date('F j, Y g:i A', strtotime($employee->updated_at)); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .employee-details-view {
            margin-top: 20px;
        }
        
        .employee-profile-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 30px;
            max-width: 800px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-avatar {
            margin-right: 20px;
        }
        
        .profile-avatar img {
            border-radius: 50%;
        }
        
        .profile-info h2 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .employee-code {
            color: #666;
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        
        .status-badges {
            display: flex;
            gap: 8px;
        }
        
        .employee-details-table {
            width: 100%;
            margin-bottom: 25px;
        }
        
        .employee-details-table th {
            text-align: left;
            padding: 8px 0;
            width: 150px;
            font-weight: bold;
            color: #555;
        }
        
        .employee-details-table td {
            padding: 8px 0;
            color: #333;
        }
        
        .profile-details h3 {
            margin: 25px 0 15px 0;
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 5px;
        }
        </style>
        <?php
    }
    
    private function edit_employee() {
        if (!wp_verify_nonce($_POST['employee_nonce'], 'sahayya_employee_action')) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $employee_id = intval($_POST['employee_id']);
        
        $employee_data = array(
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'skills' => sanitize_textarea_field($_POST['skills']),
            'service_areas' => sanitize_textarea_field($_POST['service_areas']),
            'availability_status' => sanitize_text_field($_POST['availability_status']),
            'license_number' => sanitize_text_field($_POST['license_number']),
            'vehicle_details' => sanitize_text_field($_POST['vehicle_details']),
            'emergency_contact' => sanitize_text_field($_POST['emergency_contact']),
            'status' => sanitize_text_field($_POST['status']),
            'rating' => floatval($_POST['rating'])
        );
        
        $result = Sahayya_Booking_Database::update_employee($employee_id, $employee_data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&message=employee_updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&action=edit&employee_id=' . $employee_id . '&error=update_failed'));
        }
        exit;
    }
    
    private function delete_employee() {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_employee_' . $_GET['employee_id'])) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $employee_id = intval($_GET['employee_id']);
        $employee = Sahayya_Booking_Database::get_employee($employee_id);
        
        if ($employee) {
            // Remove employee role from user
            $user = new WP_User($employee->user_id);
            $user->remove_role('sahayya_employee');
            
            // Delete employee record
            Sahayya_Booking_Database::delete_employee($employee_id);
            
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&message=employee_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&error=employee_not_found'));
        }
        exit;
    }
    
    private function toggle_availability() {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'toggle_availability')) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $employee_id = intval($_GET['employee_id']);
        $new_status = sanitize_text_field($_GET['status']);
        
        if (!in_array($new_status, array('available', 'busy', 'offline'))) {
            wp_die(__('Invalid status.', 'sahayya-booking'));
        }
        
        $result = Sahayya_Booking_Database::update_employee_availability($employee_id, $new_status);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&message=availability_updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-employees&error=update_failed'));
        }
        exit;
    }
    
    private function get_message($message) {
        $messages = array(
            'employee_added' => __('Employee added successfully.', 'sahayya-booking'),
            'employee_created' => __('New employee account created successfully. Login credentials have been sent via email.', 'sahayya-booking'),
            'employee_updated' => __('Employee updated successfully.', 'sahayya-booking'),
            'employee_deleted' => __('Employee deleted successfully.', 'sahayya-booking'),
            'availability_updated' => __('Employee availability updated.', 'sahayya-booking'),
        );
        
        return isset($messages[$message]) ? $messages[$message] : '';
    }
    
    private function get_error_message($error) {
        $errors = array(
            'invalid_user' => __('Please select a valid user.', 'sahayya-booking'),
            'already_employee' => __('This user is already an employee.', 'sahayya-booking'),
            'creation_failed' => __('Failed to create employee record.', 'sahayya-booking'),
            'update_failed' => __('Failed to update employee record.', 'sahayya-booking'),
            'employee_not_found' => __('Employee not found.', 'sahayya-booking'),
            'missing_fields' => __('Please fill in all required fields (username, email, full name).', 'sahayya-booking'),
            'username_exists' => __('Username already exists. Please choose a different username.', 'sahayya-booking'),
            'email_exists' => __('Email address already exists. Please use a different email address.', 'sahayya-booking'),
            'user_creation_failed' => __('Failed to create WordPress user account.', 'sahayya-booking'),
            'employee_creation_failed' => __('Failed to create employee record.', 'sahayya-booking'),
        );
        
        return isset($errors[$error]) ? $errors[$error] : '';
    }
    
    private function render_employee_styles() {
        ?>
        <style>
        .employee-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
        }
        
        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .employees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .employee-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .employee-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .employee-avatar {
            margin-right: 15px;
        }
        
        .employee-avatar img {
            border-radius: 50%;
        }
        
        .employee-info h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .employee-code {
            color: #666;
            font-size: 12px;
            margin: 0 0 8px 0;
        }
        
        .employee-badges {
            display: flex;
            gap: 5px;
        }
        
        .status-badge, .availability-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #fff3cd; color: #856404; }
        
        .availability-available { background: #d1ecf1; color: #0c5460; }
        .availability-busy { background: #f8d7da; color: #721c24; }
        .availability-offline { background: #e2e3e5; color: #383d41; }
        
        .employee-details {
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .detail-row .label {
            font-weight: bold;
            color: #555;
        }
        
        .detail-row .value {
            color: #333;
        }
        
        .rating {
            color: #ff9800;
        }
        
        .employee-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .employee-actions .button {
            font-size: 11px;
            padding: 4px 8px;
            height: auto;
        }
        
        .no-items {
            text-align: center;
            padding: 40px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .employee-form .form-table th {
            width: 200px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Delete employee
            $('.delete-employee').on('click', function() {
                var employeeId = $(this).data('employee-id');
                var employeeName = $(this).data('name');

                if (confirm('Are you sure you want to delete employee "' + employeeName + '"? This action cannot be undone.')) {
                    // Create nonce with employee ID on server side for each employee
                    var nonce = $(this).data('nonce');
                    var deleteUrl = '<?php echo admin_url('admin.php?page=sahayya-booking-employees&action=delete_employee&employee_id='); ?>' + employeeId + '&_wpnonce=' + nonce;
                    window.location.href = deleteUrl;
                }
            });
        });
        </script>
        <?php
    }
}