<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Deactivator {
    
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled cron jobs
        wp_clear_scheduled_hook('sahayya_booking_notifications');
        wp_clear_scheduled_hook('sahayya_booking_cleanup');
    }
}