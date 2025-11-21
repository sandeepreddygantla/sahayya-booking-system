# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin for dependent care services booking system with service extras, employee management, and automated invoicing.

- **Environment**: Local XAMPP/LAMPP on Linux
- **URLs**:
  - Frontend: http://localhost/sahayya/book-a-service/
  - Admin: http://localhost/sahayya/wp-admin/admin.php?page=sahayya-booking
- **Database**: MySQL `sahayya` database (root / Sandeep@7288)
- **Plugin Path**: `/opt/lampp/htdocs/sahayya/wp-content/plugins/sahayya-booking-system/`

## Architecture Overview

### MVC-Style Structure
- **admin/** - Admin UI controllers (services, bookings, employees, customers, categories)
- **includes/** - Business logic and models (database, AJAX, email, roles, activator)
- **public/** - Frontend controllers (shortcodes, employee dashboard)
- **assets/** - CSS/JS for admin and frontend
- **lib/TCPDF/** - PDF invoice generation library

### Key Data Flow Patterns

**Booking Creation Flow:**
1. Frontend form → AJAX handler (`class-ajax.php::create_booking()`)
2. Validates user login, service selection, dependents, datetime
3. Duplicate check prevents double submission (2-minute window)
4. Creates booking record via `Sahayya_Booking_Database::create_booking()`
5. Links selected service extras via junction table `wp_sahayya_booking_extras`
6. Generates invoice with line items (service + extras)
7. Triggers email notification system

**Service Extras System (Critical Feature):**
- Admin manages extras per service via AJAX (`handle_service_extras` in `class-ajax.php`)
- Extras stored in `wp_sahayya_service_extras` with pricing, requirements, duration impact
- Frontend displays as checkboxes in booking step 2
- Selected extras stored in junction table `wp_sahayya_booking_extras`
- Automatically included in invoice generation and total calculation

**Employee Assignment:**
- Can be automatic (based on availability + skills) or manual
- Availability tracked in real-time (available/busy/offline)
- Employee dashboard provides frontend interface for status updates

### Database Schema

15+ custom tables with prefix `wp_sahayya_`:

**Core Tables:**
- `services` - Service definitions (base_price, per_person_price, estimated_duration)
- `service_extras` - Add-ons for services (price, is_required, duration_impact)
- `bookings` - Booking records with status (pending/confirmed/in_progress/completed/cancelled)
- `booking_extras` - Junction table linking bookings to selected extras
- `dependents` - Customer dependent information (name, age, special_needs)
- `employees` - Staff with availability status and skills

**Relationships:**
- `bookings.service_id` → `services.id`
- `bookings.subscriber_id` → WordPress `wp_users.ID`
- `booking_extras.booking_id` → `bookings.id`
- `booking_extras.extra_id` → `service_extras.id`
- `invoices.booking_id` → `bookings.id` (one-to-one)
- `invoice_items` - Line items for services and extras

## Development Commands

```bash
# Start local server
sudo /opt/lampp/lampp start

# Plugin management
cd /opt/lampp/htdocs/sahayya/wp-content/plugins/sahayya-booking-system
wp plugin deactivate sahayya-booking-system  # Triggers deactivator
wp plugin activate sahayya-booking-system    # Rebuilds database schema

# Database access
mysql -u root -p'Sandeep@7288' sahayya

# View logs
tail -f /opt/lampp/logs/error_log | grep -i sahayya
tail -f /opt/lampp/htdocs/sahayya/wp-content/debug.log

# Database backup
mysqldump -u root -p'Sandeep@7288' sahayya > backup_$(date +%Y%m%d_%H%M%S).sql
```

## Critical Development Patterns

### AJAX Security Pattern (All AJAX in class-ajax.php)
```php
// 1. Register in __construct()
add_action('wp_ajax_sahayya_service_extras', array($this, 'handle_service_extras'));

// 2. Verify nonce + capability
public function handle_service_extras() {
    check_ajax_referer('sahayya_service_extras', 'nonce');
    if (!current_user_can('manage_sahayya_services')) {
        wp_send_json_error('Insufficient permissions');
    }
    // Process action_type: add/edit/delete
}

// 3. Frontend with nonce
$.post(ajaxurl, {
    action: 'sahayya_service_extras',
    nonce: '<?php echo wp_create_nonce('sahayya_service_extras'); ?>',
    // data...
});
```

### Database Layer (Centralized in class-database.php)
All database operations go through static methods using `$wpdb->prepare()`:
```php
Sahayya_Booking_Database::create_service($data);
Sahayya_Booking_Database::get_service($id);
Sahayya_Booking_Database::update_service($id, $data);
Sahayya_Booking_Database::delete_service($id);
```

### Admin Page Pattern (CRITICAL for CRUD)
**Common bug**: Form submissions only worked on list pages, not add/edit pages.
**Solution**: Handle POST submissions BEFORE action routing:

```php
public function render_page() {
    // MUST BE FIRST - handle form submission before routing
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'action_name')) {
        $this->handle_form_submission();
        return; // Prevent double processing
    }

    // Then route to action
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    switch ($action) {
        case 'add': $this->render_add_form(); break;
        case 'edit': $this->render_edit_form($id); break;
        default: $this->render_list(); break;
    }
}
```

### WordPress Media Library Integration
Admin pages must call `wp_enqueue_media()` before using media uploader:
```php
// In admin class enqueue_scripts():
wp_enqueue_media();

// Handle both Media Library (preferred) and direct upload (fallback)
if (!empty($_POST['service_image_url'])) {
    $data['service_image'] = esc_url_raw($_POST['service_image_url']);
} elseif (!empty($_FILES['service_image']['name'])) {
    $data['service_image'] = $this->handle_image_upload($_FILES['service_image']);
}
```

## Security & User Roles

### Custom Capabilities
- `manage_sahayya_bookings` - View/manage all bookings (admin)
- `create_sahayya_bookings` - Create bookings (subscribers)
- `manage_sahayya_services` - Service and extras management (admin)
- `manage_sahayya_employees` - Employee management (admin)
- `manage_sahayya_dependents` - Manage dependents (subscribers)

### Custom Role: "Sahayya Employee"
- Access to employee dashboard (`class-employee-dashboard.php`)
- Can update booking progress, status, and add GPS/photos
- Limited to assigned bookings only

### Security Requirements
- **Nonce verification**: All forms and AJAX
- **Capability checks**: Every admin action
- **SQL injection prevention**: `$wpdb->prepare()` for all queries
- **XSS protection**: `esc_html()`, `esc_attr()`, `esc_url()` on output
- **File upload validation**: Type checking and WordPress media handling

## Known Issues & Solutions

### Fixed: CRUD Form Submissions
**Problem**: Forms only worked on list pages, not add/edit pages.
**Root Cause**: Form handler was in wrong method order.
**Solution**: Process POST submissions BEFORE action routing in `render_page()`.

### Common Debugging Issues
1. **Service extras not saving**: Check browser console for AJAX errors, verify nonce
2. **Booking submission fails**: User must be logged in, check `create_sahayya_bookings` capability
3. **Images not uploading**: Verify `wp_enqueue_media()` called, check upload directory permissions
4. **Database tables missing**: Run activation hook via plugin deactivate/activate

## Plugin Initialization

Entry point: `sahayya-booking-system.php`
1. Defines constants (VERSION, PLUGIN_DIR, PLUGIN_URL, PLUGIN_FILE)
2. Loads core classes conditionally (admin vs frontend)
3. Registers activation/deactivation hooks
4. Initializes classes: Admin, Frontend, Shortcodes, Ajax, Email system
5. Enqueues scripts/styles with localized AJAX variables