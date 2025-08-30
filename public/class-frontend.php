<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Frontend {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Frontend initialization
    }
}