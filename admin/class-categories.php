<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Categories {
    
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        
        // Handle form submissions
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'sahayya_category_action')) {
            $this->handle_form_submission();
        }
        
        switch ($action) {
            case 'add':
                $this->render_add_category_form();
                break;
            case 'edit':
                $this->render_edit_category_form($category_id);
                break;
            case 'delete':
                $this->handle_delete_category($category_id);
                break;
            default:
                $this->render_categories_list();
                break;
        }
    }
    
    private function render_categories_list() {
        $categories = Sahayya_Booking_Database::get_categories();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Service Categories', 'sahayya-booking'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-categories&action=add'); ?>" class="page-title-action">
                <?php _e('Add New Category', 'sahayya-booking'); ?>
            </a>
            
            <?php $this->show_messages(); ?>
            
            <div class="categories-overview">
                <div class="categories-stats">
                    <div class="stat-box">
                        <h3><?php _e('Total Categories', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo count($categories); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Active Categories', 'sahayya-booking'); ?></h3>
                        <span class="stat-number"><?php echo count(array_filter($categories, function($cat) { return $cat->status === 'active'; })); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($categories)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-name"><?php _e('Name', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-description"><?php _e('Description', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-services"><?php _e('Services Count', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php _e('Status', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-created"><?php _e('Created', 'sahayya-booking'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'sahayya-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td class="column-name">
                                    <strong><?php echo esc_html($category->name); ?></strong>
                                </td>
                                <td class="column-description">
                                    <?php echo esc_html(wp_trim_words($category->description, 10)); ?>
                                </td>
                                <td class="column-services">
                                    <?php echo $this->get_services_count($category->id); ?>
                                </td>
                                <td class="column-status">
                                    <span class="status-badge status-<?php echo esc_attr($category->status); ?>">
                                        <?php echo esc_html(ucfirst($category->status)); ?>
                                    </span>
                                </td>
                                <td class="column-created">
                                    <?php echo date('M j, Y', strtotime($category->created_at)); ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-categories&action=edit&category_id=' . $category->id); ?>" 
                                       class="button button-small"><?php _e('Edit', 'sahayya-booking'); ?></a>
                                    
                                    <?php if ($this->get_services_count($category->id) == 0): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sahayya-booking-categories&action=delete&category_id=' . $category->id), 'delete_category_' . $category->id); ?>" 
                                           class="button button-small button-link-delete" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this category?', 'sahayya-booking'); ?>')">
                                            <?php _e('Delete', 'sahayya-booking'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="description"><?php _e('Cannot delete (has services)', 'sahayya-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-categories-message">
                    <h3><?php _e('No categories found', 'sahayya-booking'); ?></h3>
                    <p><?php _e('Create your first service category to organize your services.', 'sahayya-booking'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-categories&action=add'); ?>" class="button button-primary">
                        <?php _e('Add New Category', 'sahayya-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .categories-overview {
            margin: 20px 0;
        }
        
        .categories-stats {
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
        
        .no-categories-message {
            text-align: center;
            padding: 40px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
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
        </style>
        <?php
    }
    
    private function render_add_category_form() {
        $this->render_category_form();
    }
    
    private function render_edit_category_form($category_id) {
        $category = Sahayya_Booking_Database::get_category($category_id);
        if (!$category) {
            wp_die(__('Category not found.', 'sahayya-booking'));
        }
        
        $this->render_category_form($category);
    }
    
    private function render_category_form($category = null) {
        $is_edit = !empty($category);
        $form_title = $is_edit ? __('Edit Category', 'sahayya-booking') : __('Add New Category', 'sahayya-booking');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($form_title); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('sahayya_category_action'); ?>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="category_id" value="<?php echo esc_attr($category->id); ?>" />
                    <input type="hidden" name="action" value="edit_category" />
                <?php else: ?>
                    <input type="hidden" name="action" value="add_category" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="category_name"><?php _e('Category Name', 'sahayya-booking'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="category_name" name="category_name" 
                                       value="<?php echo esc_attr($category->name ?? ''); ?>" 
                                       class="regular-text" required />
                                <p class="description"><?php _e('Enter a descriptive name for this category (e.g., Emergency Services, Routine Care)', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_description"><?php _e('Description', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <textarea id="category_description" name="category_description" 
                                          rows="4" class="large-text"><?php echo esc_textarea($category->description ?? ''); ?></textarea>
                                <p class="description"><?php _e('Brief description of what services this category includes', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_status"><?php _e('Status', 'sahayya-booking'); ?></label>
                            </th>
                            <td>
                                <select id="category_status" name="category_status">
                                    <option value="active" <?php selected($category->status ?? 'active', 'active'); ?>>
                                        <?php _e('Active', 'sahayya-booking'); ?>
                                    </option>
                                    <option value="inactive" <?php selected($category->status ?? 'active', 'inactive'); ?>>
                                        <?php _e('Inactive', 'sahayya-booking'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('Inactive categories will not be shown in service selection', 'sahayya-booking'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" 
                           value="<?php echo $is_edit ? __('Update Category', 'sahayya-booking') : __('Add Category', 'sahayya-booking'); ?>" />
                    <a href="<?php echo admin_url('admin.php?page=sahayya-booking-categories'); ?>" class="button">
                        <?php _e('Cancel', 'sahayya-booking'); ?>
                    </a>
                </p>
            </form>
            
            <?php if ($is_edit && $this->get_services_count($category->id) > 0): ?>
                <div class="category-services">
                    <h3><?php _e('Services in this Category', 'sahayya-booking'); ?></h3>
                    <?php $this->render_category_services($category->id); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_category_services($category_id) {
        $services = Sahayya_Booking_Database::get_services(array('category_id' => $category_id));
        
        if (empty($services)) {
            echo '<p>' . __('No services in this category yet.', 'sahayya-booking') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Service Name', 'sahayya-booking'); ?></th>
                    <th><?php _e('Base Price', 'sahayya-booking'); ?></th>
                    <th><?php _e('Status', 'sahayya-booking'); ?></th>
                    <th><?php _e('Actions', 'sahayya-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?php echo esc_html($service->name); ?></td>
                        <td>₹<?php echo number_format($service->base_price, 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($service->status); ?>">
                                <?php echo esc_html(ucfirst($service->status)); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=sahayya-booking-services&action=edit&service_id=' . $service->id); ?>" 
                               class="button button-small"><?php _e('Edit Service', 'sahayya-booking'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function handle_form_submission() {
        $action = sanitize_text_field($_POST['action']);
        
        if ($action === 'add_category') {
            $this->handle_add_category();
        } elseif ($action === 'edit_category') {
            $this->handle_edit_category();
        }
    }
    
    private function handle_add_category() {
        $category_data = $this->sanitize_category_data($_POST);
        
        $category_id = Sahayya_Booking_Database::create_category($category_data);
        
        if ($category_id) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&message=category_added'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&action=add&error=db_error'));
            exit;
        }
    }
    
    private function handle_edit_category() {
        $category_id = intval($_POST['category_id']);
        $category_data = $this->sanitize_category_data($_POST);
        
        $result = Sahayya_Booking_Database::update_category($category_id, $category_data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&message=category_updated'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&action=edit&category_id=' . $category_id . '&error=db_error'));
            exit;
        }
    }
    
    private function handle_delete_category($category_id) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_category_' . $category_id)) {
            wp_die(__('Security check failed.', 'sahayya-booking'));
        }
        
        // Check if category has services
        if (Sahayya_Booking_Database::get_category_services_count($category_id) > 0) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&error=category_has_services'));
            exit;
        }
        
        $result = Sahayya_Booking_Database::delete_category($category_id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&message=category_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=sahayya-booking-categories&error=delete_failed'));
        }
        exit;
    }
    
    private function sanitize_category_data($data) {
        return array(
            'name' => sanitize_text_field($data['category_name']),
            'description' => sanitize_textarea_field($data['category_description']),
            'status' => sanitize_text_field($data['category_status'])
        );
    }
    
    private function show_messages() {
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'category_added' => __('Category added successfully!', 'sahayya-booking'),
                'category_updated' => __('Category updated successfully!', 'sahayya-booking'),
                'category_deleted' => __('Category deleted successfully!', 'sahayya-booking')
            );
            
            if (isset($messages[$message])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $messages[$message] . '</p></div>';
            }
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $errors = array(
                'db_error' => __('Database error occurred. Please try again.', 'sahayya-booking'),
                'category_has_services' => __('Cannot delete category that contains services. Please move or delete the services first.', 'sahayya-booking'),
                'delete_failed' => __('Failed to delete category. Please try again.', 'sahayya-booking')
            );
            
            if (isset($errors[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $errors[$error] . '</p></div>';
            }
        }
    }
}