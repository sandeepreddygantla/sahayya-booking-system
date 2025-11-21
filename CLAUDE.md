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

### Class Loading Strategy (Critical)
Plugin uses **conditional loading** in `sahayya-booking-system.php`:
- Admin classes (`admin/class-*.php`) load only when `is_admin() === true`
- Frontend classes (`public/class-*.php`) always load
- Core classes (`includes/class-*.php`) always load
- All classes instantiate in `init_hooks()` - no autoloading

**Early Action Handling Pattern:**
Admin classes that handle POST/GET actions must register on `admin_init` with priority < 10 to execute before WordPress output. See `class-admin.php:14-34` for examples of delete/cancel actions that need early registration to allow redirects.

### Request Lifecycle

**Admin Request Flow:**
1. WordPress loads plugin → `SahayyaBookingSystem::__construct()`
2. `plugins_loaded` hook → `init()` → conditional `includes()` loads admin classes
3. Admin classes register on `admin_menu` (priority 10)
4. Early actions fire on `admin_init` (priority 5) - handles POST before output
5. Page render methods check `$_GET['action']` to route to add/edit/list views

**Frontend Booking Request Flow:**
1. User visits page with `[sahayya_booking_form]` shortcode
2. `Sahayya_Booking_Shortcodes::render_booking_form()` outputs 6-step wizard HTML
3. JavaScript in `assets/js/frontend.js` manages step transitions
4. Step 2 triggers AJAX to load service extras dynamically
5. Final submission → `wp_ajax_sahayya_create_booking` in `class-ajax.php`
6. Creates booking → fires `do_action('sahayya_booking_created')` hook
7. Email system (`class-email-notifications.php`) hooks into action, sends confirmation
8. Email scheduler (`class-email-scheduler.php`) queues in `wp_sahayya_email_notifications` table

### Invoice Generation Architecture
Two-stage process linking bookings to PDFs:

**Stage 1: Database Record (on booking creation)**
- `class-ajax.php::create_booking()` → `Sahayya_Booking_Database::create_invoice()`
- Creates record in `wp_sahayya_invoices` with invoice_number, totals, status='draft'
- Populates `wp_sahayya_invoice_items` with line items (service + each extra)
- Links via `bookings.invoice_id` → `invoices.id`

**Stage 2: PDF Generation (on demand)**
- AJAX endpoint: `wp_ajax_sahayya_download_invoice_pdf` or `wp_ajax_sahayya_preview_invoice_pdf`
- `class-pdf-invoice.php` extends TCPDF library
- Fetches invoice data, booking details, customer info from database
- Generates PDF with company branding (logo, address from WordPress options)
- Outputs PDF directly to browser (preview) or forces download

### Service Extras Junction Pattern
Many-to-many relationship between bookings and extras:

**Admin Side:**
- `admin/class-services.php` renders extras management UI in service edit page
- AJAX endpoint `wp_ajax_sahayya_service_extras` handles add/edit/delete
- Operations: `action_type` in POST data determines operation (add/edit/delete/reorder)

**Frontend Side:**
- Step 2 of booking form loads extras via `wp_ajax_nopriv_sahayya_get_service_extras`
- Returns JSON: `[{id, name, description, price, is_required, max_quantity}, ...]`
- JavaScript renders checkboxes with quantity inputs
- On submit, selected extras sent as array in POST: `extra_ids[]`, `extra_quantities[]`
- `class-ajax.php::create_booking()` loops through selections, calls `add_booking_extra()`

### Email System Dual Architecture
Plugin implements **two parallel email systems**:

**Direct Send (class-email-notifications.php):**
- Hooks into booking events: `sahayya_booking_created`, `sahayya_booking_status_changed`
- Calls `wp_mail()` immediately with template variables
- Logs to `wp_sahayya_email_logs` for tracking
- Used for: booking confirmations, status changes, invoice notifications

**Queue-Based (class-email-scheduler.php):**
- Scheduled via WordPress cron (every 5 minutes)
- Inserts records into `wp_sahayya_email_notifications` with status='pending'
- Cron job `sahayya_process_email_queue` processes pending emails
- Implements retry logic (max 3 attempts) with failure tracking
- Used for: booking reminders (24h before), payment reminders, weekly reports

### Database Schema

12 custom tables with prefix `wp_sahayya_` (actively used):

**Core Tables:**
- `services` - Service definitions (base_price, per_person_price, estimated_duration)
- `service_categories` - Service categorization (4 default categories auto-created)
- `service_extras` - Add-ons for services (price, is_required, duration_impact)
- `bookings` - Booking records with status (pending/confirmed/assigned/in_progress/completed/cancelled)
- `booking_extras` - Junction table linking bookings to selected extras
- `dependents` - Customer dependent information (name, age, special_needs)
- `employees` - Staff with availability status and skills

**Invoice & Financial:**
- `invoices` - Invoice records (invoice_number, total_amount, status)
- `invoice_items` - Line items for services and extras

**Email System:**
- `email_notifications` - Queue-based email system (reminders, confirmations)
- `email_logs` - Email history tracking

**Relationships:**
- `bookings.service_id` → `services.id`
- `bookings.subscriber_id` → WordPress `wp_users.ID`
- `booking_extras.booking_id` → `bookings.id`
- `booking_extras.extra_id` → `service_extras.id`
- `invoices.booking_id` → `bookings.id` (one-to-one)

**Removed Tables (not implemented):**
- `sahayya_payments` - Payment gateway integration (future)
- `sahayya_progress_updates` - Employee GPS tracking (future)
- `sahayya_email_templates` - Custom email templates (future)
- `sahayya_custom_fields` - Dynamic form fields (future)
- `sahayya_booking_custom_data` - Custom field storage (future)

## Development Commands

```bash
# Start/stop local XAMPP server
sudo /opt/lampp/lampp start
sudo /opt/lampp/lampp stop

# Plugin activation (creates all 12 database tables)
cd /opt/lampp/htdocs/sahayya/wp-content/plugins/sahayya-booking-system
wp plugin deactivate sahayya-booking-system  # Runs deactivator (does NOT drop tables)
wp plugin activate sahayya-booking-system    # Runs activator, creates tables via dbDelta()

# Database operations
mysql -u root -p'Sandeep@7288' sahayya

# Verify tables exist (should return 12)
mysql -u root -p'Sandeep@7288' sahayya -e "SHOW TABLES LIKE 'wp_sahayya_%';" | wc -l

# View booking data
mysql -u root -p'Sandeep@7288' sahayya -e "SELECT id, booking_number, booking_status, service_id FROM wp_sahayya_bookings ORDER BY id DESC LIMIT 10;"

# Check WordPress debug logs
tail -f /opt/lampp/htdocs/sahayya/wp-content/debug.log
tail -f /opt/lampp/logs/error_log | grep -i sahayya

# Database backup
mysqldump -u root -p'Sandeep@7288' sahayya > backup_$(date +%Y%m%d_%H%M%S).sql

# Test AJAX endpoints (from browser console when logged in as admin)
jQuery.post(ajaxurl, {
    action: 'sahayya_test_ajax',
    nonce: '<?php echo wp_create_nonce("sahayya_test_ajax"); ?>'
}, function(response) { console.log(response); });

# Clear WordPress cron jobs (if email scheduler misbehaves)
wp cron event list --search=sahayya
wp cron event delete sahayya_process_email_queue
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

## Critical Architectural Gotchas

### 1. AJAX Handler Registration (class-ajax.php)
**All AJAX endpoints must register in `__construct()` → `init()` hook.** Common mistake: registering directly in constructor fails because WordPress actions haven't loaded yet.

```php
// WRONG - fires too early
public function __construct() {
    add_action('wp_ajax_myaction', array($this, 'handler'));
}

// CORRECT - waits for 'init' hook
public function __construct() {
    add_action('init', array($this, 'init'));
}
public function init() {
    add_action('wp_ajax_myaction', array($this, 'handler'));
}
```

### 2. Admin Page POST Handling Order
Admin CRUD pages must process POST **before** routing to views. If you handle POST inside `render_add_form()` or `render_edit_form()`, form submission from those pages will fail.

**Pattern in all `admin/class-*.php` files:**
```php
public function render_page() {
    // STEP 1: Handle POST (must be first)
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'action_name')) {
        $this->handle_form_submission();
        return; // Exit early after redirect
    }

    // STEP 2: Route to appropriate view
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    switch ($action) {
        case 'add': $this->render_add_form(); break;
        case 'edit': $this->render_edit_form(); break;
        default: $this->render_list(); break;
    }
}
```

### 3. Duplicate Booking Prevention
`class-ajax.php::create_booking()` implements 2-minute window duplicate detection using `booking_date`, `booking_time`, and `subscriber_id`. If user refreshes during AJAX call, duplicate check prevents double booking. If this breaks, check the SQL query at line ~120 in `class-ajax.php`.

### 4. Invoice-Booking Linking
Invoices link to bookings via TWO fields (bidirectional):
- `invoices.booking_id` → `bookings.id`
- `bookings.invoice_id` → `invoices.id`

When creating invoice, MUST update both:
1. Insert invoice record → get `$invoice_id`
2. Update `bookings.invoice_id = $invoice_id`

Missing step 2 breaks invoice lookups in customer dashboard.

### 5. Email Template Variables
Email templates expect specific variable names. When adding new emails in `class-email-notifications.php`, ensure variables match what templates expect:
- `$customer_name` (not `$user_name`)
- `$booking_number` (not `$booking_id`)
- `$booking_date` (formatted as 'F j, Y', not raw SQL date)

See `class-email-notifications.php:55-69` for required variables structure.

### 6. WordPress Media Library Requirement
Image upload fields in admin require `wp_enqueue_media()` called in `enqueue_scripts()` method. Without this, clicking "Select Image" button does nothing. Every admin class with image fields must include:

```php
public function enqueue_scripts($hook) {
    wp_enqueue_media(); // Required for media library
}
```

### 7. Shortcode Login Requirements
All shortcodes except `[sahayya_services_list]` require logged-in users. They render login prompt if `!is_user_logged_in()`. To add public booking form, modify `class-shortcodes.php::render_booking_form()` line 24-29.

## Known Issues & Historical Fixes

### Fixed: CRUD Form Submissions (v3.1.0)
**Problem**: Forms only worked on list pages, not add/edit pages.
**Root Cause**: Form handler executed after view routing, so POST data processed in wrong context.
**Solution**: Move POST handling to top of `render_page()` before `switch($action)` statement.
**Files affected**: All `admin/class-*.php` files.

### Fixed: Service Extras AJAX (v3.0.5)
**Problem**: Adding/editing extras returned success but didn't save.
**Root Cause**: Nonce verification used wrong nonce name.
**Solution**: Changed nonce from `'sahayya_nonce'` to `'sahayya_service_extras'` to match frontend.
**File**: `includes/class-ajax.php:49`

### Active Bug: Email Queue Not Processing
**Symptom**: Booking reminders don't send, `wp_sahayya_email_notifications` table fills with status='pending'.
**Likely cause**: WordPress cron not firing. Test with:
```bash
wp cron event list --search=sahayya_process_email_queue
wp cron event run sahayya_process_email_queue --due-now
```
**Workaround**: Check hosting has server-side cron calling wp-cron.php every 5 minutes.