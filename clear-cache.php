<?php
/**
 * Cache Clearing Script
 * Upload this to: /wp-content/plugins/sahayya-booking-system/clear-cache.php
 * Access at: http://yoursite.com/wp-content/plugins/sahayya-booking-system/clear-cache.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is logged in and is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('You must be logged in as admin to run this script');
}

echo "<h1>Cache Clearing Script</h1>";

// Clear WordPress object cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "✓ WordPress object cache cleared<br>";
}

// Clear PHP opcode cache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ PHP OPcache cleared<br>";
} else {
    echo "ℹ PHP OPcache not available or not enabled<br>";
}

// Clear APCu cache if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✓ APCu cache cleared<br>";
}

// Delete transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
echo "✓ WordPress transients cleared<br>";

echo "<br><strong>All caches cleared! Now try adding a service.</strong><br>";
echo "<br><a href='" . admin_url('admin.php?page=sahayya-booking-services&action=add') . "'>Add New Service</a>";
echo "<br><a href='" . admin_url('admin.php?page=sahayya-booking-services') . "'>View All Services</a>";
echo "<br><br><em>You can delete this file after clearing the cache.</em>";
?>
