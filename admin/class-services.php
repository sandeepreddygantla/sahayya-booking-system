<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Services {
    
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
        
        // Handle form submissions first
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'sahayya_service_action')) {
            $this->handle_service_form_submission();
            return;
        }
        
        switch ($action) {
            case 'add':
                $this->render_add_service_form();
                break;
            case 'edit':
                $this->render_edit_service_form($service_id);
                break;
            case 'delete':
                $this->handle_delete_service($service_id);
                break;
            default:
                $this->render_services_list();
                break;
        }
    }
    
    private function render_services_list() {
        $services = Sahayya_Booking_Database::get_services();
        $categories = Sahayya_Booking_Database::get_categories();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Services', 'sahayya-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-services&action=add'); ?>" class="page-title-action">
                <?php _e('Add New Service', 'sahayya-booking'); ?>
            </a>
            
            <div class="sahayya-services-grid">
                <?php if (!empty($services)): ?>
                    <?php foreach ($services as $service): ?>
                        <div class="service-card">
                            <div class="service-image">
                                <?php if ($service->service_image): ?>
                                    <img src="<?php echo esc_url($service->service_image); ?>" alt="<?php echo esc_attr($service->name); ?>" />
                                <?php else: ?>
                                    <div class="no-image-placeholder">
                                        <span class="dashicons dashicons-image-alt3"></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="service-content">
                                <h3><?php echo esc_html($service->name); ?></h3>
                                <p class="service-description"><?php echo esc_html(wp_trim_words($service->description, 15)); ?></p>
                                
                                <div class="service-details">
                                    <div class="price-info">
                                        <strong><?php _e('Base Price:', 'sahayya-booking'); ?></strong> 
                                        ₹<?php echo number_format($service->base_price, 2); ?>
                                    </div>
                                    <?php if ($service->per_person_price > 0): ?>
                                        <div class="per-person-price">
                                            <strong><?php _e('Per Person:', 'sahayya-booking'); ?></strong> 
                                            ₹<?php echo number_format($service->per_person_price, 2); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="duration">
                                        <strong><?php _e('Duration:', 'sahayya-booking'); ?></strong> 
                                        <?php echo $service->estimated_duration; ?> <?php _e('minutes', 'sahayya-booking'); ?>
                                    </div>
                                </div>
                                
                                <div class="service-status">
                                    <span class="status-badge status-<?php echo esc_attr($service->status); ?>">
                                        <?php echo esc_html(ucfirst($service->status)); ?>
                                    </span>
                                    <?php if ($service->available_24_7): ?>
                                        <span class="availability-badge">24/7</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="service-actions">
                                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-services&action=edit&service_id=' . $service->id); ?>" 
                                       class="button button-small"><?php _e('Edit', 'sahayya-booking'); ?></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sahayya-booking-services&action=delete&service_id=' . $service->id), 'delete_service_' . $service->id); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this service?', 'sahayya-booking'); ?>')">
                                        <?php _e('Delete', 'sahayya-booking'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-services-message">
                        <h3><?php _e('No services found', 'sahayya-booking'); ?></h3>
                        <p><?php _e('Create your first service to get started.', 'sahayya-booking'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=sahayya-booking-services&action=add'); ?>" class="button button-primary">
                            <?php _e('Add New Service', 'sahayya-booking'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .sahayya-services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .service-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
        }
        
        .service-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .service-image {
            height: 150px;
            background: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .service-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image-placeholder {
            color: #999;
        }
        
        .no-image-placeholder .dashicons {
            font-size: 48px;
        }
        
        .service-content {
            padding: 20px;
        }
        
        .service-content h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .service-description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .service-details {
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .service-details > div {
            margin-bottom: 5px;
        }
        
        .service-status {
            margin-bottom: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .availability-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #007cba;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .service-actions {
            display: flex;
            gap: 10px;
        }
        
        .no-services-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        </style>
        <?php
    }
    
    private function render_add_service_form() {
        $this->render_service_form();
    }
    
    private function render_edit_service_form($service_id) {
        $service = Sahayya_Booking_Database::get_service($service_id);
        if (!$service) {
            wp_die(__('Service not found.', 'sahayya-booking'));
        }
        
        $this->render_service_form($service);
    }
    
    private function render_service_form($service = null) {
        $is_edit = !empty($service);
        $form_title = $is_edit ? __('Edit Service', 'sahayya-booking') : __('Add New Service', 'sahayya-booking');
        $categories = Sahayya_Booking_Database::get_categories();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($form_title); ?></h1>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sahayya_service_action'); ?>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="service_id" value="<?php echo esc_attr($service->id); ?>" />
                    <input type="hidden" name="action" value="edit_service" />
                <?php else: ?>
                    <input type="hidden" name="action" value="add_service" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="service_name"><?php _e('Service Name', 'sahayya-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="service_name" name="service_name" 
                                   value="<?php echo esc_attr($service->name ?? ''); ?>" 
                                   class="regular-text" required />
                            <p class="description"><?php _e('Enter the service name (e.g., Emergency Hospital Transport)', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="service_description"><?php _e('Description', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea id="service_description" name="service_description" 
                                      rows="4" class="large-text"><?php echo esc_textarea($service->description ?? ''); ?></textarea>
                            <p class="description"><?php _e('Describe what this service includes', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="category_id"><?php _e('Service Category', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <select id="category_id" name="category_id">
                                <option value="0"><?php _e('Select Category', 'sahayya-booking'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" 
                                            <?php selected($service->category_id ?? 0, $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="base_price"><?php _e('Base Price (₹)', 'sahayya-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" id="base_price" name="base_price" 
                                   value="<?php echo esc_attr($service->base_price ?? ''); ?>" 
                                   step="0.01" min="0" class="small-text" required />
                            <p class="description"><?php _e('Fixed cost for this service', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="per_person_price"><?php _e('Per Person Rate (₹)', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="per_person_price" name="per_person_price" 
                                   value="<?php echo esc_attr($service->per_person_price ?? ''); ?>" 
                                   step="0.01" min="0" class="small-text" />
                            <p class="description"><?php _e('Additional cost per dependent', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="estimated_duration"><?php _e('Estimated Duration (minutes)', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="estimated_duration" name="estimated_duration" 
                                   value="<?php echo esc_attr($service->estimated_duration ?? 60); ?>" 
                                   min="15" step="15" class="small-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="travel_charges"><?php _e('Travel Charges (₹ per km)', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="travel_charges" name="travel_charges" 
                                   value="<?php echo esc_attr($service->travel_charges ?? ''); ?>" 
                                   step="0.01" min="0" class="small-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="waiting_charges"><?php _e('Waiting Charges (₹ per hour)', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="waiting_charges" name="waiting_charges" 
                                   value="<?php echo esc_attr($service->waiting_charges ?? ''); ?>" 
                                   step="0.01" min="0" class="small-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_dependents"><?php _e('Max Dependents per Booking', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="max_dependents" name="max_dependents" 
                                   value="<?php echo esc_attr($service->max_dependents ?? 10); ?>" 
                                   min="1" max="20" class="small-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="advance_booking_hours"><?php _e('Advance Booking Required (hours)', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="advance_booking_hours" name="advance_booking_hours" 
                                   value="<?php echo esc_attr($service->advance_booking_hours ?? 2); ?>" 
                                   min="0" class="small-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="available_24_7"><?php _e('Availability', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="available_24_7" name="available_24_7" 
                                       value="1" <?php checked($service->available_24_7 ?? 0, 1); ?> />
                                <?php _e('Available 24/7', 'sahayya-booking'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="special_requirements"><?php _e('Special Requirements', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <textarea id="special_requirements" name="special_requirements" 
                                      rows="3" class="large-text"><?php echo esc_textarea($service->special_requirements ?? ''); ?></textarea>
                            <p class="description"><?php _e('e.g., Medical equipment, wheelchair accessible vehicle', 'sahayya-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="service_image"><?php _e('Service Image', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="service_image" name="service_image" accept="image/*" />
                            <?php if ($is_edit && $service->service_image): ?>
                                <p>
                                    <img src="<?php echo esc_url($service->service_image); ?>" 
                                         alt="<?php echo esc_attr($service->name); ?>" 
                                         style="max-width: 150px; height: auto; display: block; margin-top: 10px;" />
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e('Status', 'sahayya-booking'); ?></label>
                        </th>
                        <td>
                            <select id="status" name="status">
                                <option value="active" <?php selected($service->status ?? 'active', 'active'); ?>>
                                    <?php _e('Active', 'sahayya-booking'); ?>
                                </option>
                                <option value="inactive" <?php selected($service->status ?? 'active', 'inactive'); ?>>
                                    <?php _e('Inactive', 'sahayya-booking'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php if ($is_edit): ?>
                    <h2><?php _e('Service Extras', 'sahayya-booking'); ?></h2>
                    <p><?php _e('Add optional extras that customers can choose when booking this service.', 'sahayya-booking'); ?></p>
                    
                    <div id="service-extras-container">
                        <?php $this->render_service_extras_section($service->id); ?>
                    </div>
                    
                    <div class="service-extras-form" style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
                        <h3><?php _e('Add New Extra', 'sahayya-booking'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="extra_name"><?php _e('Extra Name', 'sahayya-booking'); ?> *</label>
                                </th>
                                <td>
                                    <input type="text" id="extra_name" name="extra_name" class="regular-text" 
                                           placeholder="<?php _e('e.g., Wheelchair Accessible Vehicle', 'sahayya-booking'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="extra_description"><?php _e('Description', 'sahayya-booking'); ?></label>
                                </th>
                                <td>
                                    <textarea id="extra_description" name="extra_description" rows="2" class="large-text"
                                              placeholder="<?php _e('Brief description of this extra service', 'sahayya-booking'); ?>"></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="extra_price"><?php _e('Price (₹)', 'sahayya-booking'); ?> *</label>
                                </th>
                                <td>
                                    <input type="number" id="extra_price" name="extra_price" step="0.01" min="0" class="small-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="extra_duration"><?php _e('Additional Duration (minutes)', 'sahayya-booking'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="extra_duration" name="extra_duration" min="0" step="15" class="small-text" value="0" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="extra_max_quantity"><?php _e('Max Quantity', 'sahayya-booking'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="extra_max_quantity" name="extra_max_quantity" min="1" class="small-text" value="1" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="extra_required"><?php _e('Required', 'sahayya-booking'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="extra_required" name="extra_required" value="1" />
                                        <?php _e('This extra is required for all bookings', 'sahayya-booking'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <button type="button" id="add-service-extra" class="button button-secondary">
                                <?php _e('Add Extra', 'sahayya-booking'); ?>
                            </button>
                        </p>
                    </div>
                <?php endif; ?>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" 
                           value="<?php echo $is_edit ? __('Update Service', 'sahayya-booking') : __('Add Service', 'sahayya-booking'); ?>" />
                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-services'); ?>" class="button">
                        <?php _e('Cancel', 'sahayya-booking'); ?>
                    </a>
                </p>
            </form>
        </div>
        
        <?php if ($is_edit): ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var serviceId = <?php echo $service->id; ?>;
            
            // Add new extra
            $('#add-service-extra').click(function() {
                var data = {
                    action: 'sahayya_service_extras',
                    action_type: 'add',
                    service_id: serviceId,
                    name: $('#extra_name').val(),
                    description: $('#extra_description').val(),
                    price: $('#extra_price').val(),
                    duration_minutes: $('#extra_duration').val(),
                    max_quantity: $('#extra_max_quantity').val(),
                    is_required: $('#extra_required').is(':checked') ? 1 : 0,
                    nonce: '<?php echo wp_create_nonce('sahayya_service_extras'); ?>'
                };
                
                if (!data.name || !data.price) {
                    alert('<?php _e('Please fill in required fields', 'sahayya-booking'); ?>');
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Adding...', 'sahayya-booking'); ?>');
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        // Clear form
                        $('#extra_name, #extra_description, #extra_price, #extra_duration').val('');
                        $('#extra_max_quantity').val('1');
                        $('#extra_required').prop('checked', false);
                        
                        // Reload the extras section
                        loadServiceExtras();
                        
                        alert(response.data.message);
                    } else {
                        alert(response.data || '<?php _e('Failed to add extra', 'sahayya-booking'); ?>');
                    }
                    
                    $('#add-service-extra').prop('disabled', false).text('<?php _e('Add Extra', 'sahayya-booking'); ?>');
                });
            });
            
            // Delete extra
            $(document).on('click', '.delete-extra', function() {
                if (!confirm('<?php _e('Are you sure you want to delete this extra?', 'sahayya-booking'); ?>')) {
                    return;
                }
                
                var extraId = $(this).data('extra-id');
                var $button = $(this);
                
                var data = {
                    action: 'sahayya_service_extras',
                    action_type: 'delete',
                    extra_id: extraId,
                    nonce: '<?php echo wp_create_nonce('sahayya_service_extras'); ?>'
                };
                
                $button.prop('disabled', true);
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        loadServiceExtras();
                        alert(response.data.message);
                    } else {
                        alert(response.data || '<?php _e('Failed to delete extra', 'sahayya-booking'); ?>');
                        $button.prop('disabled', false);
                    }
                });
            });
            
            // Load service extras via AJAX
            function loadServiceExtras() {
                $('#service-extras-container').load(location.href + ' #service-extras-container > *');
            }
        });
        </script>
        <?php endif; ?>
        <?php
    }
    
    private function handle_service_form_submission() {
        $action = sanitize_text_field($_POST['action']);
        
        if ($action === 'add_service') {
            $this->handle_add_service();
        } elseif ($action === 'edit_service') {
            $this->handle_edit_service();
        }
    }
    
    private function handle_add_service() {
        $service_data = $this->sanitize_service_data($_POST);
        
        // Handle image upload
        if (!empty($_FILES['service_image']['name'])) {
            $image_url = $this->handle_image_upload($_FILES['service_image']);
            if ($image_url) {
                $service_data['service_image'] = $image_url;
            }
        }
        
        $service_id = Sahayya_Booking_Database::create_service($service_data);
        
        if ($service_id) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-services&message=service_added'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-services&action=add&error=db_error'));
            exit;
        }
    }
    
    private function handle_edit_service() {
        $service_id = intval($_POST['service_id']);
        $service_data = $this->sanitize_service_data($_POST);
        
        // Handle image upload
        if (!empty($_FILES['service_image']['name'])) {
            $image_url = $this->handle_image_upload($_FILES['service_image']);
            if ($image_url) {
                $service_data['service_image'] = $image_url;
            }
        }
        
        $result = Sahayya_Booking_Database::update_service($service_id, $service_data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-services&message=service_updated'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-services&action=edit&service_id=' . $service_id . '&error=db_error'));
            exit;
        }
    }
    
    private function handle_delete_service($service_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_service_' . $service_id)) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        $result = Sahayya_Booking_Database::delete_service($service_id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-services&message=service_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-services&error=delete_failed'));
        }
        exit;
    }
    
    private function sanitize_service_data($data) {
        return array(
            'name' => sanitize_text_field($data['service_name']),
            'description' => sanitize_textarea_field($data['service_description']),
            'category_id' => intval($data['category_id']),
            'base_price' => floatval($data['base_price']),
            'per_person_price' => floatval($data['per_person_price']),
            'estimated_duration' => intval($data['estimated_duration']),
            'travel_charges' => floatval($data['travel_charges']),
            'waiting_charges' => floatval($data['waiting_charges']),
            'max_dependents' => intval($data['max_dependents']),
            'advance_booking_hours' => intval($data['advance_booking_hours']),
            'available_24_7' => isset($data['available_24_7']) ? 1 : 0,
            'special_requirements' => sanitize_textarea_field($data['special_requirements']),
            'status' => sanitize_text_field($data['status'])
        );
    }
    
    private function handle_image_upload($file) {
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
    
    private function render_service_extras_section($service_id) {
        $extras = Sahayya_Booking_Database::get_service_extras($service_id);
        
        if (empty($extras)) {
            echo '<p class="no-extras-message">' . __('No extras added yet.', 'sahayya-booking') . '</p>';
            return;
        }
        
        echo '<div class="service-extras-list">';
        echo '<h4>' . __('Current Extras', 'sahayya-booking') . '</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Name', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Price', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Duration', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Max Qty', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Required', 'sahayya-booking') . '</th>';
        echo '<th>' . __('Actions', 'sahayya-booking') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($extras as $extra) {
            echo '<tr data-extra-id="' . esc_attr($extra->id) . '">';
            echo '<td>';
            echo '<strong>' . esc_html($extra->name) . '</strong>';
            if ($extra->description) {
                echo '<br><span class="description">' . esc_html($extra->description) . '</span>';
            }
            echo '</td>';
            echo '<td>₹' . number_format($extra->price, 2) . '</td>';
            echo '<td>' . $extra->duration_minutes . ' ' . __('min', 'sahayya-booking') . '</td>';
            echo '<td>' . $extra->max_quantity . '</td>';
            echo '<td>' . ($extra->is_required ? '<span class="dashicons dashicons-yes-alt" style="color: green;"></span>' : '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>') . '</td>';
            echo '<td>';
            echo '<button type="button" class="button button-small edit-extra" data-extra-id="' . esc_attr($extra->id) . '">' . __('Edit', 'sahayya-booking') . '</button> ';
            echo '<button type="button" class="button button-small button-link-delete delete-extra" data-extra-id="' . esc_attr($extra->id) . '">' . __('Delete', 'sahayya-booking') . '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
}