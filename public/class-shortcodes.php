<?php

if (!defined('ABSPATH')) {
    exit;
}

class Sahayya_Booking_Shortcodes {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_booking_scripts'));
    }
    
    public function init() {
        add_shortcode('sahayya_booking_form', array($this, 'render_booking_form'));
        add_shortcode('sahayya_my_bookings', array($this, 'render_my_bookings'));
        add_shortcode('sahayya_services', array($this, 'render_services'));
        add_shortcode('sahayya_services_list', array($this, 'render_services_list'));
        add_shortcode('sahayya_customer_dashboard', array($this, 'render_customer_dashboard'));
        add_shortcode('sahayya_my_invoices', array($this, 'render_my_invoices'));
    }
    
    public function render_booking_form($atts) {
        if (!is_user_logged_in()) {
            return '<div class="sahayya-login-notice">
                <p>' . __('Please login to book a service.', 'sahayya-booking') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">' . __('Login', 'sahayya-booking') . '</a>
            </div>';
        }
        
        $user_id = get_current_user_id();
        $services = Sahayya_Booking_Database::get_services();
        $dependents = Sahayya_Booking_Database::get_dependents($user_id);
        
        // Add cache-busting headers
        if (!headers_sent()) {
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        }
        
        ob_start();
        ?>
        <!-- Professional Booking Form v2.1 - Cache Cleared -->
        <div class="sahayya-booking-container" data-version="2.1">
            <div class="sahayya-booking-form">
                <h3><?php _e('Book a Service', 'sahayya-booking'); ?></h3>
                
                <!-- Progress Indicator -->
                <div class="booking-progress">
                    <div class="progress-step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-title"><?php _e('Service', 'sahayya-booking'); ?></div>
                    </div>
                    <div class="progress-step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-title"><?php _e('Extras', 'sahayya-booking'); ?></div>
                    </div>
                    <div class="progress-step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-title"><?php _e('Dependents', 'sahayya-booking'); ?></div>
                    </div>
                    <div class="progress-step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-title"><?php _e('Details', 'sahayya-booking'); ?></div>
                    </div>
                    <div class="progress-step" data-step="5">
                        <div class="step-number">5</div>
                        <div class="step-title"><?php _e('Schedule', 'sahayya-booking'); ?></div>
                    </div>
                    <div class="progress-step" data-step="6">
                        <div class="step-number">6</div>
                        <div class="step-title"><?php _e('Review', 'sahayya-booking'); ?></div>
                    </div>
                </div>
                
                <form id="sahayya-booking-form" method="post">
                    <?php wp_nonce_field('sahayya_create_booking', 'sahayya_booking_nonce'); ?>
                    
                    <!-- Hidden fields to track selections across steps -->
                    <input type="hidden" name="selected_service_id" id="selected_service_id" value="" />
                    <input type="hidden" name="selected_dependent_ids" id="selected_dependent_ids" value="" />
                    <input type="hidden" name="current_step" id="current_step" value="1" />
                    
                    <!-- Step 1: Service Selection -->
                    <div class="booking-step step-1 active">
                        <h4><?php _e('Step 1: Select Service', 'sahayya-booking'); ?></h4>
                        <div class="services-grid">
                            <?php if (!empty($services)): ?>
                                <!-- Debug: <?php echo count($services); ?> services found -->
                                <?php foreach ($services as $service): ?>
                                    <div class="service-option" data-service-id="<?php echo $service->id; ?>">
                                        <input type="radio" name="service_id" value="<?php echo $service->id; ?>" id="service_<?php echo $service->id; ?>" onchange="handleServiceSelection(this)" />
                                        <label for="service_<?php echo $service->id; ?>">
                                            <?php if ($service->service_image): ?>
                                                <img src="<?php echo esc_url($service->service_image); ?>" alt="<?php echo esc_attr($service->name); ?>" />
                                            <?php endif; ?>
                                            <h5><?php echo esc_html($service->name); ?></h5>
                                            <p><?php echo esc_html($service->description); ?></p>
                                            <div class="pricing">
                                                <span class="base-price">₹<?php echo number_format($service->base_price, 2); ?></span>
                                                <?php if ($service->per_person_price > 0): ?>
                                                    <span class="per-person">+ ₹<?php echo number_format($service->per_person_price, 2); ?>/person</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($service->available_24_7): ?>
                                                <span class="availability-badge">24/7 Available</span>
                                            <?php endif; ?>
                                            <?php if ($service->enable_group_booking && $service->max_group_size > 1): ?>
                                                <span class="group-booking-badge">Group Booking (Max: <?php echo $service->max_group_size; ?>)</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p><?php _e('No services available at the moment.', 'sahayya-booking'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="step-navigation">
                            <button type="button" class="next-step button button-primary" disabled onclick="handleManualNext()"><?php _e('Continue', 'sahayya-booking'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Service Extras Selection -->
                    <div class="booking-step step-2">
                        <h4><?php _e('Step 2: Add Service Extras (Optional)', 'sahayya-booking'); ?></h4>
                        
                        <div id="service-extras-container">
                            <p class="loading-extras"><?php _e('Loading available extras...', 'sahayya-booking'); ?></p>
                        </div>
                        
                        <div class="step-navigation">
                            <button type="button" class="prev-step button"><?php _e('Previous', 'sahayya-booking'); ?></button>
                            <button type="button" class="next-step button button-primary"><?php _e('Continue', 'sahayya-booking'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Dependent Selection -->
                    <div class="booking-step step-3">
                        <h4><?php _e('Step 3: Select Dependents', 'sahayya-booking'); ?></h4>
                        
                        <?php if (!empty($dependents)): ?>
                            <div class="dependents-grid">
                                <?php foreach ($dependents as $dependent): ?>
                                    <div class="dependent-option">
                                        <input type="checkbox" name="dependent_ids[]" value="<?php echo $dependent->id; ?>" id="dependent_<?php echo $dependent->id; ?>" />
                                        <label for="dependent_<?php echo $dependent->id; ?>">
                                            <?php if ($dependent->photo): ?>
                                                <img src="<?php echo esc_url($dependent->photo); ?>" alt="<?php echo esc_attr($dependent->name); ?>" class="dependent-photo" />
                                            <?php else: ?>
                                                <div class="no-photo-placeholder">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                </div>
                                            <?php endif; ?>
                                            <h5><?php echo esc_html($dependent->name); ?></h5>
                                            <p><?php echo esc_html($dependent->age); ?> years, <?php echo esc_html(ucfirst($dependent->gender)); ?></p>
                                            <div class="dependent-address"><?php echo esc_html($dependent->address); ?></div>
                                            <?php if ($dependent->medical_conditions): ?>
                                                <div class="medical-info">
                                                    <small><strong><?php _e('Medical:', 'sahayya-booking'); ?></strong> <?php echo esc_html($dependent->medical_conditions); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="step-navigation">
                                <button type="button" class="prev-step button"><?php _e('Previous', 'sahayya-booking'); ?></button>
                                <button type="button" class="next-step button button-primary" disabled><?php _e('Continue', 'sahayya-booking'); ?></button>
                            </div>
                        <?php else: ?>
                            <div class="no-dependents">
                                <p><?php _e('You need to add dependents before booking a service.', 'sahayya-booking'); ?></p>
                                <a href="#" class="add-dependent-btn button button-primary"><?php _e('Add Dependent', 'sahayya-booking'); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 4: Custom Fields -->
                    <div class="booking-step step-4">
                        <h4><?php _e('Step 4: Additional Information', 'sahayya-booking'); ?></h4>
                        
                        <div id="custom-fields-container">
                            <p class="loading-fields"><?php _e('Loading additional fields...', 'sahayya-booking'); ?></p>
                        </div>
                        
                        <div class="step-navigation">
                            <button type="button" class="prev-step button"><?php _e('Previous', 'sahayya-booking'); ?></button>
                            <button type="button" class="next-step button button-primary"><?php _e('Continue', 'sahayya-booking'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Step 5: Date & Time Selection -->
                    <div class="booking-step step-5">
                        <h4><?php _e('Step 5: Schedule Appointment', 'sahayya-booking'); ?></h4>
                        
                        <div class="datetime-selection">
                            <div class="date-field">
                                <label for="booking_date"><?php _e('Preferred Date', 'sahayya-booking'); ?></label>
                                <input type="date" name="booking_date" id="booking_date" required min="<?php echo date('Y-m-d'); ?>" />
                            </div>
                            
                            <div class="time-field">
                                <label for="booking_time"><?php _e('Preferred Time', 'sahayya-booking'); ?></label>
                                <input type="time" name="booking_time" id="booking_time" required />
                            </div>
                            
                            <div class="urgency-field">
                                <label for="urgency_level"><?php _e('Urgency Level', 'sahayya-booking'); ?></label>
                                <select name="urgency_level" id="urgency_level">
                                    <option value="normal"><?php _e('Normal', 'sahayya-booking'); ?></option>
                                    <option value="urgent"><?php _e('Urgent', 'sahayya-booking'); ?></option>
                                    <option value="emergency"><?php _e('Emergency', 'sahayya-booking'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="special-instructions">
                            <label for="special_instructions"><?php _e('Special Instructions', 'sahayya-booking'); ?></label>
                            <textarea name="special_instructions" id="special_instructions" rows="3" placeholder="<?php _e('Any special requirements or notes...', 'sahayya-booking'); ?>"></textarea>
                        </div>
                        
                        <div class="step-navigation">
                            <button type="button" class="prev-step button"><?php _e('Previous', 'sahayya-booking'); ?></button>
                            <button type="button" class="next-step button button-primary"><?php _e('Continue', 'sahayya-booking'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Step 6: Review & Payment -->
                    <div class="booking-step step-6">
                        <h4><?php _e('Step 6: Review & Payment', 'sahayya-booking'); ?></h4>
                        
                        <div class="booking-summary">
                            <div class="summary-section">
                                <h5><?php _e('Service Details', 'sahayya-booking'); ?></h5>
                                <div id="selected-service-summary"></div>
                            </div>
                            
                            <div class="summary-section">
                                <h5><?php _e('Service Extras', 'sahayya-booking'); ?></h5>
                                <div id="selected-extras-summary"></div>
                            </div>
                            
                            <div class="summary-section">
                                <h5><?php _e('Selected Dependents', 'sahayya-booking'); ?></h5>
                                <div id="selected-dependents-summary"></div>
                            </div>
                            
                            <div class="summary-section">
                                <h5><?php _e('Additional Information', 'sahayya-booking'); ?></h5>
                                <div id="selected-custom-fields-summary"></div>
                            </div>
                            
                            <div class="summary-section">
                                <h5><?php _e('Schedule', 'sahayya-booking'); ?></h5>
                                <div id="selected-datetime-summary"></div>
                            </div>
                            
                            <div class="summary-section">
                                <h5><?php _e('Pricing Breakdown', 'sahayya-booking'); ?></h5>
                                <div id="pricing-breakdown">
                                    <div class="price-line">
                                        <span><?php _e('Base Service Fee:', 'sahayya-booking'); ?></span>
                                        <span id="base-price-display">₹0.00</span>
                                    </div>
                                    <div class="price-line">
                                        <span><?php _e('Service Extras:', 'sahayya-booking'); ?></span>
                                        <span id="extras-price-display">₹0.00</span>
                                    </div>
                                    <div class="price-line">
                                        <span><?php _e('Additional Dependents:', 'sahayya-booking'); ?></span>
                                        <span id="dependent-price-display">₹0.00</span>
                                    </div>
                                    <div class="price-line total">
                                        <span><strong><?php _e('Total Amount:', 'sahayya-booking'); ?></strong></span>
                                        <span id="total-price-display"><strong>₹0.00</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-method">
                            <h5><?php _e('Payment Method', 'sahayya-booking'); ?></h5>
                            <div class="payment-options">
                                <label>
                                    <input type="radio" name="payment_method" value="cash" checked />
                                    <span><?php _e('Cash Payment (Pay on Service)', 'sahayya-booking'); ?></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="step-navigation">
                            <button type="button" class="prev-step button"><?php _e('Previous', 'sahayya-booking'); ?></button>
                            <button type="submit" class="submit-booking button button-primary button-large"><?php _e('Confirm Booking', 'sahayya-booking'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Booking Success Message - Moved outside form container -->
            <div class="booking-success" style="display: none;">
                <div class="success-icon">✓</div>
                <h3><?php _e('Booking Confirmed!', 'sahayya-booking'); ?></h3>
                <p><?php _e('Your booking has been submitted successfully. You will receive a confirmation shortly.', 'sahayya-booking'); ?></p>
                <div class="booking-details"></div>
                
                <!-- Invoice Actions -->
                <div class="invoice-actions" style="margin: 25px 0; display: none;">
                    <h4 style="margin-bottom: 15px; color: rgba(255, 255, 255, 0.9);"><?php _e('Invoice', 'sahayya-booking'); ?></h4>
                    <div class="invoice-buttons" style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <button type="button" class="button invoice-preview-btn" 
                                style="background: rgba(255, 255, 255, 0.2) !important; color: white !important; border: 2px solid rgba(255, 255, 255, 0.3) !important; padding: 12px 24px !important; border-radius: 6px !important; text-decoration: none !important; transition: all 0.3s ease !important; backdrop-filter: blur(5px) !important;">
                            <i class="dashicons dashicons-visibility" style="margin-right: 8px; vertical-align: middle;"></i>
                            <?php _e('Preview Invoice', 'sahayya-booking'); ?>
                        </button>
                        <button type="button" class="button invoice-download-btn"
                                style="background: rgba(255, 255, 255, 0.2) !important; color: white !important; border: 2px solid rgba(255, 255, 255, 0.3) !important; padding: 12px 24px !important; border-radius: 6px !important; text-decoration: none !important; transition: all 0.3s ease !important; backdrop-filter: blur(5px) !important;">
                            <i class="dashicons dashicons-download" style="margin-right: 8px; vertical-align: middle;"></i>
                            <?php _e('Download PDF', 'sahayya-booking'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="success-actions" style="margin-top: 30px;">
                    <a href="<?php echo home_url(); ?>" class="button button-primary" 
                       style="background: rgba(255, 255, 255, 0.15) !important; color: white !important; border: 2px solid rgba(255, 255, 255, 0.3) !important; padding: 15px 30px !important; border-radius: 8px !important; text-decoration: none !important; transition: all 0.3s ease !important; backdrop-filter: blur(10px) !important;">
                        <?php _e('Back to Home', 'sahayya-booking'); ?>
                    </a>
                </div>
            </div>
        </div>
            
            <!-- Add Dependent Modal -->
            <div id="add-dependent-modal" class="sahayya-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4><?php _e('Add New Dependent', 'sahayya-booking'); ?></h4>
                        <span class="close-modal">&times;</span>
                    </div>
                    <div class="modal-body">
                        <!-- Add dependent form will be loaded dynamically -->
                        <p>Add dependent form coming soon...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    public function enqueue_booking_scripts() {
        // Check if we're on any Sahayya page
        global $wp;
        $current_url = home_url($wp->request);

        $is_booking_page = strpos($current_url, 'book-a-service') !== false;
        $is_my_bookings = strpos($current_url, 'my-bookings') !== false;
        $is_my_account = strpos($current_url, 'my-account') !== false;
        $is_dashboard = strpos($current_url, 'dashboard') !== false;

        // Enqueue account management styles and scripts for account-related pages
        if ($is_my_bookings || $is_my_account || $is_dashboard) {
            wp_enqueue_style(
                'sahayya-account-management',
                SAHAYYA_BOOKING_PLUGIN_URL . 'public/css/account-management.css',
                array(),
                SAHAYYA_BOOKING_VERSION
            );

            wp_enqueue_script(
                'sahayya-account-management',
                SAHAYYA_BOOKING_PLUGIN_URL . 'public/js/account-management.js',
                array('jquery'),
                SAHAYYA_BOOKING_VERSION,
                true
            );

            // Localize script for AJAX
            wp_localize_script('sahayya-account-management', 'sahayya_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sahayya_booking_nonce')
            ));
        }

        // Only enqueue booking-specific scripts on the booking page
        if (!$is_booking_page) {
            return;
        }
        
        // Enqueue professional booking CSS with maximum priority
        wp_enqueue_style(
            'sahayya-booking-professional',
            SAHAYYA_BOOKING_PLUGIN_URL . 'public/css/booking-professional.css',
            array(),
            '3.1.0'
        );
        
        // Add inline style to force even higher specificity
        $force_style = "
        html body .sahayya-booking-container .sahayya-booking-form .booking-progress {
            display: flex !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            padding: 40px 30px !important;
        }
        ";
        wp_add_inline_style('sahayya-booking-professional', $force_style);
        
        // Add inline script using WordPress API with AJAX URL and booking submission
        $inline_script = "
        console.log('Professional Booking JavaScript v4.0 loaded - Complete booking system with direct step navigation and validation');
        
        // Set AJAX URL for WordPress AJAX calls
        var ajaxurl = '" . admin_url('admin-ajax.php') . "';
        
        // Step validation and navigation system
        var stepValidation = {
            mandatorySteps: [1, 3, 5, 6], // Service, Dependents, Schedule, Review
            optionalSteps: [2, 4], // Extras, Additional Information
            
            validateStep: function(stepNumber) {
                console.log('Validating step:', stepNumber);
                
                switch(stepNumber) {
                    case 1: // Service Selection - Mandatory
                        var selectedService = document.querySelector('input[name=\"service_id\"]:checked');
                        if (!selectedService) {
                            return { valid: false, message: 'Please select a service to continue.' };
                        }
                        return { valid: true };
                        
                    case 2: // Service Extras - Optional
                        return { valid: true }; // Always valid since optional
                        
                    case 3: // Dependents Selection - Mandatory
                        var selectedDependents = document.querySelectorAll('input[name=\"dependent_ids[]\"]:checked');
                        if (selectedDependents.length === 0) {
                            return { valid: false, message: 'Please select at least one dependent to continue.' };
                        }
                        return { valid: true };
                        
                    case 4: // Additional Information - Optional
                        return { valid: true }; // Always valid since optional
                        
                    case 5: // Schedule - Mandatory
                        var bookingDate = document.getElementById('booking_date');
                        var bookingTime = document.getElementById('booking_time');
                        
                        if (!bookingDate || !bookingDate.value) {
                            return { valid: false, message: 'Please select a booking date.' };
                        }
                        if (!bookingTime || !bookingTime.value) {
                            return { valid: false, message: 'Please select a booking time.' };
                        }
                        return { valid: true };
                        
                    case 6: // Review - Mandatory (all previous mandatory steps must be valid)
                        var errors = [];
                        
                        // Check Step 1 - Service
                        var step1Result = this.validateStep(1);
                        if (!step1Result.valid) errors.push('Step 1 (Service): ' + step1Result.message);
                        
                        // Check Step 3 - Dependents  
                        var step3Result = this.validateStep(3);
                        if (!step3Result.valid) errors.push('Step 3 (Dependents): ' + step3Result.message);
                        
                        // Check Step 5 - Schedule
                        var step5Result = this.validateStep(5);
                        if (!step5Result.valid) errors.push('Step 5 (Schedule): ' + step5Result.message);
                        
                        if (errors.length > 0) {
                            return { valid: false, message: 'Please complete the following mandatory steps:\\n\\n' + errors.join('\\n') };
                        }
                        
                        return { valid: true };
                        
                    default:
                        return { valid: true };
                }
            },
            
            showValidationError: function(message, targetStep) {
                console.log('Showing validation error for step', targetStep, ':', message);
                
                // Remove any existing error messages
                var existingErrors = document.querySelectorAll('.step-validation-error');
                existingErrors.forEach(function(error) {
                    error.remove();
                });
                
                // Create error message element
                var errorDiv = document.createElement('div');
                errorDiv.className = 'step-validation-error';
                errorDiv.innerHTML = message.replace(/\\n/g, '<br>');
                
                // Find the target step container
                var targetStepContainer = document.querySelector('.step-' + targetStep);
                if (targetStepContainer) {
                    // Insert error message at the top of the step
                    targetStepContainer.insertBefore(errorDiv, targetStepContainer.firstChild);
                } else {
                    // Fallback: show in currently active step
                    var activeStep = document.querySelector('.booking-step.active');
                    if (activeStep) {
                        activeStep.insertBefore(errorDiv, activeStep.firstChild);
                    }
                }
                
                // Add error state to progress indicator
                var progressStep = document.querySelector('[data-step=\"' + targetStep + '\"]');
                if (progressStep) {
                    progressStep.classList.add('error');
                    setTimeout(function() {
                        progressStep.classList.remove('error');
                    }, 2000);
                }
                
                // Auto-remove error message after 5 seconds
                setTimeout(function() {
                    if (errorDiv && errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                }, 5000);
            },
            
            clearValidationErrors: function() {
                var existingErrors = document.querySelectorAll('.step-validation-error');
                existingErrors.forEach(function(error) {
                    error.remove();
                });
            }
        };
        
        // Enhanced step navigation with validation
        function navigateToStep(targetStep) {
            console.log('Direct navigation requested to step:', targetStep);
            
            // Clear any existing validation errors
            stepValidation.clearValidationErrors();
            
            // Get current step
            var currentStepField = document.getElementById('current_step');
            var currentStep = currentStepField ? parseInt(currentStepField.value) : 1;
            
            // If going backward, allow without validation
            if (targetStep < currentStep) {
                console.log('Going backward, allowing navigation');
                goToStep(targetStep);
                return;
            }
            
            // If staying on same step, no validation needed
            if (targetStep === currentStep) {
                console.log('Already on target step');
                return;
            }
            
            // Validate all steps between current and target
            var stepsToValidate = [];
            for (var i = currentStep; i < targetStep; i++) {
                if (stepValidation.mandatorySteps.indexOf(i) !== -1) {
                    stepsToValidate.push(i);
                }
            }
            
            console.log('Steps to validate:', stepsToValidate);
            
            // Check each step
            for (var j = 0; j < stepsToValidate.length; j++) {
                var stepToValidate = stepsToValidate[j];
                var validation = stepValidation.validateStep(stepToValidate);
                
                if (!validation.valid) {
                    console.log('Validation failed for step:', stepToValidate);
                    stepValidation.showValidationError(validation.message, stepToValidate);
                    
                    // Navigate to the first invalid step instead
                    goToStep(stepToValidate);
                    return;
                }
            }
            
            // All validations passed, proceed to target step
            console.log('All validations passed, navigating to step:', targetStep);
            goToStep(targetStep);
        }
        
        // Global functions for booking form
        function handleServiceSelection(radio) {
            console.log('Service selected:', radio.value);
            
            // Update hidden field
            var hiddenField = document.getElementById('selected_service_id');
            if (hiddenField) {
                hiddenField.value = radio.value;
            }
            
            // Enable the continue button
            var nextBtn = document.querySelector('.step-1 .next-step');
            if (nextBtn) {
                nextBtn.disabled = false;
                console.log('Continue button enabled');
            }
        }
        
        function handleManualNext() {
            console.log('Continue button clicked');
            
            var selectedService = document.querySelector('input[name=\"service_id\"]:checked');
            if (!selectedService) {
                alert('Please select a service to continue.');
                return;
            }
            
            // Hide step 1, show step 2
            var step1 = document.querySelector('.step-1');
            var step2 = document.querySelector('.step-2');
            
            if (step1) step1.style.display = 'none';
            if (step2) step2.style.display = 'block';
            
            // Update progress indicator
            var progress1 = document.querySelector('[data-step=\"1\"]');
            var progress2 = document.querySelector('[data-step=\"2\"]');
            
            if (progress1) {
                progress1.classList.remove('active');
                progress1.classList.add('completed');
            }
            if (progress2) {
                progress2.classList.add('active');
            }
            
            console.log('Advanced to step 2');
        }
        
        function handleDependentSelection() {
            console.log('Checking dependent selections');
            
            // Check if any dependent is selected
            var checkedDependents = document.querySelectorAll('input[name=\"dependent_ids[]\"]:checked');
            var continueBtn = document.querySelector('.step-3 .next-step');
            
            if (continueBtn) {
                if (checkedDependents.length > 0) {
                    continueBtn.disabled = false;
                    console.log('Step 3 Continue button enabled - ' + checkedDependents.length + ' dependents selected');
                    
                    // Update hidden field with selected dependent IDs
                    var dependentIds = Array.from(checkedDependents).map(function(cb) { return cb.value; });
                    var hiddenField = document.getElementById('selected_dependent_ids');
                    if (hiddenField) {
                        hiddenField.value = dependentIds.join(',');
                    }
                } else {
                    continueBtn.disabled = true;
                    console.log('Step 3 Continue button disabled - no dependents selected');
                }
            }
        }
        
        // Universal step navigation functions
        function goToStep(targetStep) {
            console.log('Navigating to step:', targetStep);
            
            // Hide all steps
            var allSteps = document.querySelectorAll('.booking-step');
            allSteps.forEach(function(step) {
                step.style.display = 'none';
            });
            
            // Show target step
            var targetStepElement = document.querySelector('.step-' + targetStep);
            if (targetStepElement) {
                targetStepElement.style.display = 'block';
            }
            
            // Update progress indicator
            var allProgress = document.querySelectorAll('.progress-step');
            allProgress.forEach(function(progress, index) {
                var stepNumber = index + 1;
                progress.classList.remove('active', 'completed');
                
                if (stepNumber < targetStep) {
                    progress.classList.add('completed');
                } else if (stepNumber === targetStep) {
                    progress.classList.add('active');
                }
            });
            
            // Update hidden current step field
            var currentStepField = document.getElementById('current_step');
            if (currentStepField) {
                currentStepField.value = targetStep;
            }
            
            console.log('Successfully navigated to step', targetStep);
        }
        
        function handlePrevious(currentStep) {
            console.log('Previous button clicked from step:', currentStep);
            var prevStep = parseInt(currentStep) - 1;
            if (prevStep >= 1) {
                goToStep(prevStep);
            }
        }
        
        function handleNext(currentStep) {
            console.log('Next button clicked from step:', currentStep);
            var nextStep = parseInt(currentStep) + 1;
            if (nextStep <= 6) {
                goToStep(nextStep);
                
                // If moving to step 6 (review), calculate and display prices
                if (nextStep === 6) {
                    calculateAndDisplayPrices();
                    populateReviewSummary();
                }
            }
        }
        
        function calculateAndDisplayPrices() {
            console.log('Calculating prices for review step');
            
            // Get selected service
            var selectedService = document.querySelector('input[name=\"service_id\"]:checked');
            if (!selectedService) {
                console.log('No service selected');
                return;
            }
            
            var serviceId = selectedService.value;
            var serviceElement = selectedService.closest('.service-option');
            var basePriceElement = serviceElement.querySelector('.base-price');
            var perPersonElement = serviceElement.querySelector('.per-person');
            
            if (!basePriceElement) {
                console.log('Could not find base price element');
                return;
            }
            
            // Extract base price (remove ₹ and parse)
            var basePriceText = basePriceElement.textContent.replace('₹', '').replace(',', '');
            var basePrice = parseFloat(basePriceText) || 0;
            
            // Extract per-person price
            var perPersonPrice = 0;
            if (perPersonElement) {
                var perPersonText = perPersonElement.textContent.replace('+ ₹', '').replace('/person', '').replace(',', '');
                perPersonPrice = parseFloat(perPersonText) || 0;
            }
            
            // Count selected dependents
            var selectedDependents = document.querySelectorAll('input[name=\"dependent_ids[]\"]:checked');
            var dependentCount = selectedDependents.length;
            
            // Calculate dependent cost
            var dependentCost = dependentCount * perPersonPrice;
            
            // Service extras cost (for now, set to 0 as it's optional)
            var extrasCost = 0;
            
            // Calculate total
            var totalAmount = basePrice + dependentCost + extrasCost;
            
            console.log('Price calculation:', {
                basePrice: basePrice,
                perPersonPrice: perPersonPrice,
                dependentCount: dependentCount,
                dependentCost: dependentCost,
                extrasCost: extrasCost,
                totalAmount: totalAmount
            });
            
            // Update pricing display
            var basePriceDisplay = document.getElementById('base-price-display');
            var extrasPriceDisplay = document.getElementById('extras-price-display');
            var dependentPriceDisplay = document.getElementById('dependent-price-display');
            var totalPriceDisplay = document.getElementById('total-price-display');
            
            if (basePriceDisplay) basePriceDisplay.textContent = '₹' + basePrice.toFixed(2);
            if (extrasPriceDisplay) extrasPriceDisplay.textContent = '₹' + extrasCost.toFixed(2);
            if (dependentPriceDisplay) dependentPriceDisplay.textContent = '₹' + dependentCost.toFixed(2);
            if (totalPriceDisplay) totalPriceDisplay.textContent = '₹' + totalAmount.toFixed(2);
            
            console.log('Prices updated in review step');
        }
        
        function populateReviewSummary() {
            console.log('Populating review summary');
            
            // Service details
            var selectedService = document.querySelector('input[name=\"service_id\"]:checked');
            if (selectedService) {
                var serviceElement = selectedService.closest('.service-option');
                var serviceName = serviceElement.querySelector('h5').textContent;
                var serviceDescription = serviceElement.querySelector('p').textContent;
                
                var serviceSummary = document.getElementById('selected-service-summary');
                if (serviceSummary) {
                    serviceSummary.innerHTML = '<p><strong>' + serviceName + '</strong></p><p>' + serviceDescription + '</p>';
                }
            }
            
            // Dependents
            var selectedDependents = document.querySelectorAll('input[name=\"dependent_ids[]\"]:checked');
            var dependentsSummary = document.getElementById('selected-dependents-summary');
            if (dependentsSummary) {
                if (selectedDependents.length > 0) {
                    var dependentNames = [];
                    selectedDependents.forEach(function(checkbox) {
                        var dependentElement = checkbox.closest('.dependent-option');
                        var name = dependentElement.querySelector('h5').textContent;
                        dependentNames.push(name);
                    });
                    dependentsSummary.innerHTML = '<p>' + dependentNames.join(', ') + '</p>';
                } else {
                    dependentsSummary.innerHTML = '<p>No dependents selected</p>';
                }
            }
            
            // Schedule
            var bookingDate = document.getElementById('booking_date').value;
            var bookingTime = document.getElementById('booking_time').value;
            var urgencyLevel = document.getElementById('urgency_level').value;
            
            var scheduleSummary = document.getElementById('selected-datetime-summary');
            if (scheduleSummary) {
                var scheduleText = '';
                if (bookingDate) scheduleText += '<p><strong>Date:</strong> ' + bookingDate + '</p>';
                if (bookingTime) scheduleText += '<p><strong>Time:</strong> ' + bookingTime + '</p>';
                if (urgencyLevel) scheduleText += '<p><strong>Urgency:</strong> ' + urgencyLevel + '</p>';
                scheduleSummary.innerHTML = scheduleText || '<p>Schedule not set</p>';
            }
        }
        
        function handleBookingSubmission(e) {
            e.preventDefault();
            console.log('Handling booking submission via AJAX');
            
            // Clear any existing validation errors
            stepValidation.clearValidationErrors();
            
            // Validate all mandatory steps before submission
            console.log('Validating all mandatory steps before booking submission');
            var step6Validation = stepValidation.validateStep(6);
            
            if (!step6Validation.valid) {
                console.log('Booking submission blocked - validation failed');
                stepValidation.showValidationError(step6Validation.message, 6);
                return;
            }
            
            // Get form data
            var form = document.getElementById('sahayya-booking-form');
            if (!form) {
                console.error('Booking form not found');
                return;
            }
            
            // Create FormData object
            var formData = new FormData(form);
            formData.append('action', 'sahayya_create_booking');
            
            // Debug: Log form data being sent
            console.log('Form data being sent:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            // Show loading state
            var submitButton = document.querySelector('.submit-booking');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }
            
            // Send AJAX request with proper jQuery settings
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                console.log('Booking response:', response);
                
                if (response.success) {
                    // Show success message
                    showBookingSuccess(response.data);
                } else {
                    // Show error message
                    showBookingError(response.data);
                }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed:', error);
                    showBookingError('Connection error. Please try again.');
                },
                complete: function() {
                    // Reset button state
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Confirm Booking';
                    }
                }
            });
        }
        
        function showBookingSuccess(data) {
            console.log('Showing booking success message');
            console.log('Success data received:', data);
            
            // Hide the form container
            var formContainer = document.querySelector('.sahayya-booking-form');
            if (formContainer) {
                formContainer.style.display = 'none';
            }
            
            // Show success message with proper positioning (now outside form container)
            var successContainer = document.querySelector('.booking-success');
            if (successContainer) {
                // Use professional styling that matches the CSS file
                successContainer.style.cssText = 'display: block !important; text-align: center !important; padding: 60px 40px !important; background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%) !important; color: white !important; border-radius: 12px !important; margin: 20px auto !important; box-shadow: 0 10px 30px rgba(39, 174, 96, 0.3) !important; max-width: 600px !important; position: relative !important; z-index: 10 !important;';
                
                // Update success message details with booking information
                var detailsContainer = successContainer.querySelector('.booking-details');
                if (detailsContainer && data) {
                    var bookingDetails = '';
                    if (data.booking_number) {
                        bookingDetails += '<p><strong>Booking Number:</strong> ' + data.booking_number + '</p>';
                    }
                    if (data.booking_id) {
                        bookingDetails += '<p><strong>Booking ID:</strong> #' + data.booking_id + '</p>';
                    }
                    if (data.total_amount) {
                        bookingDetails += '<p><strong>Total Amount:</strong> ₹' + parseFloat(data.total_amount).toFixed(2) + '</p>';
                    }
                    if (data.extras_count !== undefined) {
                        bookingDetails += '<p><strong>Service Extras:</strong> ' + data.extras_count + ' items</p>';
                    }
                    bookingDetails += '<p><strong>Status:</strong> Confirmed</p>';
                    
                    detailsContainer.innerHTML = bookingDetails;
                    detailsContainer.style.cssText = 'background: rgba(255, 255, 255, 0.15) !important; border-radius: 8px !important; padding: 20px !important; margin: 25px 0 !important; backdrop-filter: blur(10px) !important;';
                }
                
                // Show invoice actions if invoice_id is available
                var invoiceActions = successContainer.querySelector('.invoice-actions');
                if (invoiceActions && data && data.invoice_id) {
                    invoiceActions.style.display = 'block';
                    
                    // Set up invoice preview button
                    var previewBtn = invoiceActions.querySelector('.invoice-preview-btn');
                    if (previewBtn) {
                        previewBtn.onclick = function() {
                            previewInvoice(data.invoice_id);
                        };
                        
                        // Add hover effects
                        previewBtn.addEventListener('mouseenter', function() {
                            this.style.background = 'rgba(255, 255, 255, 0.3) !important';
                            this.style.transform = 'translateY(-2px)';
                        });
                        previewBtn.addEventListener('mouseleave', function() {
                            this.style.background = 'rgba(255, 255, 255, 0.2) !important';
                            this.style.transform = 'translateY(0)';
                        });
                    }
                    
                    // Set up invoice download button
                    var downloadBtn = invoiceActions.querySelector('.invoice-download-btn');
                    if (downloadBtn) {
                        downloadBtn.onclick = function() {
                            downloadInvoice(data.invoice_id);
                        };
                        
                        // Add hover effects
                        downloadBtn.addEventListener('mouseenter', function() {
                            this.style.background = 'rgba(255, 255, 255, 0.3) !important';
                            this.style.transform = 'translateY(-2px)';
                        });
                        downloadBtn.addEventListener('mouseleave', function() {
                            this.style.background = 'rgba(255, 255, 255, 0.2) !important';
                            this.style.transform = 'translateY(0)';
                        });
                    }
                }
                
                // Scroll to success message to ensure it's visible
                successContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                console.log('Success message displayed with booking details and scrolled into view');
            } else {
                console.error('Success container not found');
            }
        }
        
        function showBookingError(message) {
            console.log('Showing booking error:', message);
            alert('Booking Error: ' + message);
        }
        
        
        // Document ready - Setup event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Setting up complete event listener system');
            
            // Add event listeners for all Previous/Continue buttons
            var prevButtons = document.querySelectorAll('.prev-step');
            var nextButtons = document.querySelectorAll('.next-step');
            
            prevButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var currentStep = this.closest('.booking-step').classList.toString().match(/step-(\d+)/);
                    if (currentStep) {
                        handlePrevious(parseInt(currentStep[1]));
                    }
                });
            });
            
            nextButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var currentStep = this.closest('.booking-step').classList.toString().match(/step-(\d+)/);
                    if (currentStep) {
                        handleNext(parseInt(currentStep[1]));
                    }
                });
            });
            
            // Add event listeners for dependent checkboxes
            var dependentCheckboxes = document.querySelectorAll('input[name=\"dependent_ids[]\"]');
            dependentCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    console.log('Dependent checkbox changed:', this.value, this.checked);
                    handleDependentSelection();
                });
            });
            
            // Add event listener for form submission
            var bookingForm = document.getElementById('sahayya-booking-form');
            if (bookingForm) {
                bookingForm.addEventListener('submit', handleBookingSubmission);
                console.log('Form submission listener added');
            }
            
            // Add event listener for submit button
            var submitButton = document.querySelector('.submit-booking');
            if (submitButton) {
                submitButton.addEventListener('click', handleBookingSubmission);
                console.log('Submit button listener added');
            }
            
            // Add event listeners for direct step navigation
            var progressSteps = document.querySelectorAll('.progress-step');
            progressSteps.forEach(function(step) {
                step.addEventListener('click', function(e) {
                    e.preventDefault();
                    var targetStep = parseInt(this.getAttribute('data-step'));
                    console.log('Progress step clicked:', targetStep);
                    
                    if (targetStep && targetStep >= 1 && targetStep <= 6) {
                        navigateToStep(targetStep);
                    }
                });
            });
            
            console.log('All event listeners setup complete - buttons:', nextButtons.length, 'dependents:', dependentCheckboxes.length, 'progress steps:', progressSteps.length);
        });
        
        // Make functions globally available
        window.handleServiceSelection = handleServiceSelection;
        window.handleManualNext = handleManualNext;
        window.handleDependentSelection = handleDependentSelection;
        window.goToStep = goToStep;
        window.handlePrevious = handlePrevious;
        window.handleNext = handleNext;
        window.calculateAndDisplayPrices = calculateAndDisplayPrices;
        window.handleBookingSubmission = handleBookingSubmission;
        window.navigateToStep = navigateToStep;
        window.stepValidation = stepValidation;
        
        // Invoice management functions
        var sahayya_invoice_nonce = '" . wp_create_nonce('sahayya_invoice_nonce') . "';
        var sahayya_ajax_url = '" . admin_url('admin-ajax.php') . "';
        
        function previewInvoice(invoiceId) {
            console.log('Previewing invoice:', invoiceId);
            
            // Open invoice preview in a new window
            var previewUrl = sahayya_ajax_url + '?action=sahayya_preview_invoice_pdf&invoice_id=' + invoiceId + '&nonce=' + sahayya_invoice_nonce;
            
            // Open in new window/tab with appropriate dimensions
            var previewWindow = window.open(previewUrl, 'InvoicePreview', 'width=800,height=900,scrollbars=yes,resizable=yes');
            
            if (!previewWindow) {
                alert('Please allow pop-ups to preview the invoice.');
            }
        }
        
        function downloadInvoice(invoiceId) {
            console.log('Downloading invoice:', invoiceId);
            
            // Show loading state
            var downloadBtn = document.querySelector('.invoice-download-btn');
            var originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class=\"dashicons dashicons-update\" style=\"margin-right: 8px; vertical-align: middle; animation: spin 1s linear infinite;\"></i>Generating PDF...';
            downloadBtn.disabled = true;
            
            // Create a hidden form for downloading
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = sahayya_ajax_url;
            form.target = '_blank'; // Open in new tab to avoid losing the page
            form.style.display = 'none';
            
            // Add form fields
            var actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'sahayya_download_invoice_pdf';
            form.appendChild(actionField);
            
            var invoiceField = document.createElement('input');
            invoiceField.type = 'hidden';
            invoiceField.name = 'invoice_id';
            invoiceField.value = invoiceId;
            form.appendChild(invoiceField);
            
            var nonceField = document.createElement('input');
            nonceField.type = 'hidden';
            nonceField.name = 'nonce';
            nonceField.value = sahayya_invoice_nonce;
            form.appendChild(nonceField);
            
            // Append form to body and submit
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            // Restore button state after a delay
            setTimeout(function() {
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
                console.log('Invoice download initiated successfully');
            }, 2000);
        }
        
        console.log('Complete booking system ready with AJAX submission');
        ";
        
        wp_add_inline_script('jquery', $inline_script);
    }
    
    public function render_my_bookings($atts) {
        if (!is_user_logged_in()) {
            return '<div class="sahayya-login-notice">
                <p>' . __('Please login to view your bookings.', 'sahayya-booking') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">' . __('Login', 'sahayya-booking') . '</a>
            </div>';
        }

        $user_id = get_current_user_id();

        // Get all bookings for the current user
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'sahayya_bookings';
        $services_table = $wpdb->prefix . 'sahayya_services';
        $employees_table = $wpdb->prefix . 'sahayya_employees';

        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   s.service_image,
                   u.display_name as employee_name,
                   e.phone as employee_phone
            FROM $bookings_table b
            LEFT JOIN $services_table s ON b.service_id = s.id
            LEFT JOIN $employees_table e ON b.assigned_employee_id = e.id
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE b.subscriber_id = %d
            ORDER BY b.created_at DESC
        ", $user_id));

        ob_start();
        ?>
        <div class="sahayya-my-bookings-container">
            <div class="bookings-header">
                <h3><?php _e('My Bookings', 'sahayya-booking'); ?></h3>
                <p><?php _e('View and manage your service bookings', 'sahayya-booking'); ?></p>
            </div>

            <?php if (!empty($bookings)): ?>
                <div class="bookings-grid">
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        $status_class = 'status-' . $booking->booking_status;
                        $can_cancel = in_array($booking->booking_status, ['pending', 'assigned']);
                        $can_modify = in_array($booking->booking_status, ['pending']);
                        ?>
                        <div class="booking-card <?php echo esc_attr($status_class); ?>" data-booking-id="<?php echo $booking->id; ?>">
                            <div class="booking-card-header">
                                <div class="booking-number">
                                    <span class="label"><?php _e('Booking #', 'sahayya-booking'); ?></span>
                                    <span class="value"><?php echo esc_html($booking->booking_number); ?></span>
                                </div>
                                <span class="status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                    <?php echo esc_html(ucfirst($booking->booking_status)); ?>
                                </span>
                            </div>

                            <div class="booking-card-body">
                                <?php if ($booking->service_image): ?>
                                    <div class="service-image">
                                        <img src="<?php echo esc_url($booking->service_image); ?>" alt="<?php echo esc_attr($booking->service_name); ?>" />
                                    </div>
                                <?php endif; ?>

                                <div class="booking-details">
                                    <h4 class="service-name"><?php echo esc_html($booking->service_name); ?></h4>

                                    <div class="detail-row">
                                        <span class="icon dashicons dashicons-calendar"></span>
                                        <span><?php echo date('M j, Y', strtotime($booking->booking_date)); ?> at <?php echo date('g:i A', strtotime($booking->booking_time)); ?></span>
                                    </div>

                                    <?php if ($booking->employee_name): ?>
                                        <div class="detail-row">
                                            <span class="icon dashicons dashicons-businessman"></span>
                                            <span><?php echo esc_html($booking->employee_name); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="detail-row">
                                        <span class="icon dashicons dashicons-money-alt"></span>
                                        <span class="amount"><strong>₹<?php echo number_format($booking->total_amount, 2); ?></strong></span>
                                    </div>

                                    <?php if ($booking->urgency_level !== 'normal'): ?>
                                        <div class="detail-row urgency">
                                            <span class="icon dashicons dashicons-warning"></span>
                                            <span class="urgency-<?php echo esc_attr($booking->urgency_level); ?>">
                                                <?php echo esc_html(ucfirst($booking->urgency_level)); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="booking-card-footer">
                                <button type="button" class="button view-booking-details" data-booking-id="<?php echo $booking->id; ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('View Details', 'sahayya-booking'); ?>
                                </button>

                                <?php if ($can_modify): ?>
                                    <button type="button" class="button modify-booking" data-booking-id="<?php echo $booking->id; ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php _e('Modify', 'sahayya-booking'); ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($can_cancel): ?>
                                    <button type="button" class="button cancel-booking" data-booking-id="<?php echo $booking->id; ?>">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Cancel', 'sahayya-booking'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-bookings">
                    <div class="no-bookings-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <h4><?php _e('No Bookings Yet', 'sahayya-booking'); ?></h4>
                    <p><?php _e('You haven\'t made any bookings yet. Book your first service now!', 'sahayya-booking'); ?></p>
                    <a href="<?php echo home_url('/book-a-service/'); ?>" class="button button-primary">
                        <?php _e('Book a Service', 'sahayya-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Booking Details Modal -->
        <div id="booking-details-modal" class="sahayya-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h4><?php _e('Booking Details', 'sahayya-booking'); ?></h4>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="booking-details-content">
                        <p class="loading"><?php _e('Loading...', 'sahayya-booking'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function render_customer_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<div class="sahayya-login-notice">
                <p>' . __('Please login to access your dashboard.', 'sahayya-booking') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">' . __('Login', 'sahayya-booking') . '</a>
            </div>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Get stats
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'sahayya_bookings';

        $total_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $bookings_table WHERE subscriber_id = %d
        ", $user_id));

        $active_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $bookings_table
            WHERE subscriber_id = %d AND booking_status IN ('pending', 'assigned', 'in_progress')
        ", $user_id));

        $completed_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $bookings_table
            WHERE subscriber_id = %d AND booking_status = 'completed'
        ", $user_id));

        $total_spent = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(total_amount) FROM $bookings_table
            WHERE subscriber_id = %d AND booking_status IN ('completed', 'in_progress', 'assigned')
        ", $user_id));

        // Get dependents
        $dependents = Sahayya_Booking_Database::get_dependents($user_id);

        // Get recent bookings
        $recent_bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, s.name as service_name
            FROM $bookings_table b
            LEFT JOIN {$wpdb->prefix}sahayya_services s ON b.service_id = s.id
            WHERE b.subscriber_id = %d
            ORDER BY b.created_at DESC
            LIMIT 5
        ", $user_id));

        ob_start();
        ?>
        <div class="sahayya-customer-dashboard">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="user-welcome">
                    <h2><?php printf(__('Welcome back, %s!', 'sahayya-booking'), esc_html($user->display_name)); ?></h2>
                    <p><?php _e('Manage your bookings and account from your dashboard', 'sahayya-booking'); ?></p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo number_format($total_bookings); ?></h4>
                        <p><?php _e('Total Bookings', 'sahayya-booking'); ?></p>
                    </div>
                </div>

                <div class="stat-card active">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo number_format($active_bookings); ?></h4>
                        <p><?php _e('Active Bookings', 'sahayya-booking'); ?></p>
                    </div>
                </div>

                <div class="stat-card completed">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo number_format($completed_bookings); ?></h4>
                        <p><?php _e('Completed', 'sahayya-booking'); ?></p>
                    </div>
                </div>

                <div class="stat-card money">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-info">
                        <h4>₹<?php echo number_format($total_spent ? $total_spent : 0, 2); ?></h4>
                        <p><?php _e('Total Spent', 'sahayya-booking'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-quick-actions">
                <h3><?php _e('Quick Actions', 'sahayya-booking'); ?></h3>
                <div class="action-buttons">
                    <a href="<?php echo home_url('/book-a-service/'); ?>" class="action-btn primary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('New Booking', 'sahayya-booking'); ?>
                    </a>
                    <button type="button" class="action-btn secondary" onclick="showAddDependentModal()">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php _e('Add Dependent', 'sahayya-booking'); ?>
                    </button>
                    <a href="#my-bookings" class="action-btn">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('View All Bookings', 'sahayya-booking'); ?>
                    </a>
                    <a href="#my-invoices" class="action-btn">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php _e('My Invoices', 'sahayya-booking'); ?>
                    </a>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="dashboard-content-grid">
                <!-- Recent Bookings -->
                <div class="dashboard-section" id="my-bookings">
                    <div class="section-header">
                        <h3><?php _e('Recent Bookings', 'sahayya-booking'); ?></h3>
                        <a href="<?php echo home_url('/my-bookings/'); ?>" class="view-all"><?php _e('View All', 'sahayya-booking'); ?></a>
                    </div>

                    <?php if (!empty($recent_bookings)): ?>
                        <div class="bookings-list">
                            <?php foreach ($recent_bookings as $booking): ?>
                                <div class="booking-item status-<?php echo esc_attr($booking->booking_status); ?>">
                                    <div class="booking-info">
                                        <h5><?php echo esc_html($booking->service_name); ?></h5>
                                        <p class="booking-meta">
                                            <span class="booking-number">#<?php echo esc_html($booking->booking_number); ?></span>
                                            <span class="booking-date"><?php echo date('M j, Y', strtotime($booking->booking_date)); ?></span>
                                        </p>
                                    </div>
                                    <div class="booking-status-amount">
                                        <span class="status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                            <?php echo esc_html(ucfirst($booking->booking_status)); ?>
                                        </span>
                                        <span class="amount">₹<?php echo number_format($booking->total_amount, 2); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p><?php _e('No bookings yet', 'sahayya-booking'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Dependents Management -->
                <div class="dashboard-section" id="my-dependents">
                    <div class="section-header">
                        <h3><?php _e('My Dependents', 'sahayya-booking'); ?></h3>
                        <button type="button" class="add-dependent-btn" onclick="showAddDependentModal()">
                            <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add', 'sahayya-booking'); ?>
                        </button>
                    </div>

                    <?php if (!empty($dependents)): ?>
                        <div class="dependents-list">
                            <?php foreach ($dependents as $dependent): ?>
                                <div class="dependent-item" data-dependent-id="<?php echo $dependent->id; ?>">
                                    <?php if ($dependent->photo): ?>
                                        <img src="<?php echo esc_url($dependent->photo); ?>" alt="<?php echo esc_attr($dependent->name); ?>" class="dependent-photo" />
                                    <?php else: ?>
                                        <div class="dependent-photo-placeholder">
                                            <span class="dashicons dashicons-admin-users"></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="dependent-info">
                                        <h5><?php echo esc_html($dependent->name); ?></h5>
                                        <p><?php echo esc_html($dependent->age); ?> years, <?php echo esc_html(ucfirst($dependent->gender)); ?></p>
                                    </div>
                                    <div class="dependent-actions">
                                        <button type="button" class="edit-dependent" data-dependent-id="<?php echo $dependent->id; ?>" title="<?php _e('Edit', 'sahayya-booking'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button type="button" class="delete-dependent" data-dependent-id="<?php echo $dependent->id; ?>" title="<?php _e('Delete', 'sahayya-booking'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p><?php _e('No dependents added yet', 'sahayya-booking'); ?></p>
                            <button type="button" class="button button-primary" onclick="showAddDependentModal()">
                                <?php _e('Add Your First Dependent', 'sahayya-booking'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Add/Edit Dependent Modal -->
        <div id="add-dependent-modal" class="sahayya-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 id="dependent-modal-title"><?php _e('Add New Dependent', 'sahayya-booking'); ?></h4>
                    <span class="close-modal" onclick="closeAddDependentModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="add-dependent-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('sahayya_add_dependent', 'dependent_nonce'); ?>
                        <input type="hidden" name="dependent_id" id="dependent_id" value="" />

                        <div class="form-group">
                            <label for="dependent_name"><?php _e('Name', 'sahayya-booking'); ?> <span class="required">*</span></label>
                            <input type="text" name="dependent_name" id="dependent_name" required />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="dependent_age"><?php _e('Age', 'sahayya-booking'); ?> <span class="required">*</span></label>
                                <input type="number" name="dependent_age" id="dependent_age" min="0" max="120" required />
                            </div>

                            <div class="form-group">
                                <label for="dependent_gender"><?php _e('Gender', 'sahayya-booking'); ?> <span class="required">*</span></label>
                                <select name="dependent_gender" id="dependent_gender" required>
                                    <option value=""><?php _e('Select Gender', 'sahayya-booking'); ?></option>
                                    <option value="male"><?php _e('Male', 'sahayya-booking'); ?></option>
                                    <option value="female"><?php _e('Female', 'sahayya-booking'); ?></option>
                                    <option value="other"><?php _e('Other', 'sahayya-booking'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dependent_address"><?php _e('Address', 'sahayya-booking'); ?> <span class="required">*</span></label>
                            <textarea name="dependent_address" id="dependent_address" rows="3" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="dependent_medical_conditions"><?php _e('Medical Conditions', 'sahayya-booking'); ?></label>
                            <textarea name="dependent_medical_conditions" id="dependent_medical_conditions" rows="3" placeholder="<?php _e('Any medical conditions or special needs...', 'sahayya-booking'); ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="dependent_photo"><?php _e('Photo (Optional)', 'sahayya-booking'); ?></label>
                            <input type="file" name="dependent_photo" id="dependent_photo" accept="image/*" />
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button" onclick="closeAddDependentModal()"><?php _e('Cancel', 'sahayya-booking'); ?></button>
                            <button type="submit" class="button button-primary"><?php _e('Save Dependent', 'sahayya-booking'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function render_my_invoices($atts) {
        if (!is_user_logged_in()) {
            return '<div class="sahayya-login-notice">
                <p>' . __('Please login to view your invoices.', 'sahayya-booking') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">' . __('Login', 'sahayya-booking') . '</a>
            </div>';
        }
        
        $user_id = get_current_user_id();
        
        // Get all invoices for the current user
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'sahayya_invoices';
        $bookings_table = $wpdb->prefix . 'sahayya_bookings';
        $services_table = $wpdb->prefix . 'sahayya_services';
        
        $invoices = $wpdb->get_results($wpdb->prepare("
            SELECT i.*, b.booking_number, b.booking_date, b.booking_time, s.name as service_name
            FROM $invoices_table i
            LEFT JOIN $bookings_table b ON i.booking_id = b.id
            LEFT JOIN $services_table s ON b.service_id = s.id
            WHERE i.customer_id = %d
            ORDER BY i.created_at DESC
        ", $user_id));
        
        ob_start();
        ?>
        <div class="sahayya-invoices-container">
            <div class="sahayya-invoices-header">
                <h3><?php _e('My Invoices', 'sahayya-booking'); ?></h3>
                <p><?php _e('Download and view your service invoices below.', 'sahayya-booking'); ?></p>
            </div>
            
            <?php if (!empty($invoices)): ?>
                <div class="invoices-table-wrapper">
                    <table class="sahayya-invoices-table">
                        <thead>
                            <tr>
                                <th><?php _e('Invoice #', 'sahayya-booking'); ?></th>
                                <th><?php _e('Service', 'sahayya-booking'); ?></th>
                                <th><?php _e('Date', 'sahayya-booking'); ?></th>
                                <th><?php _e('Amount', 'sahayya-booking'); ?></th>
                                <th><?php _e('Status', 'sahayya-booking'); ?></th>
                                <th><?php _e('Actions', 'sahayya-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td class="invoice-number">
                                        <strong><?php echo esc_html($invoice->invoice_number); ?></strong>
                                    </td>
                                    <td class="service-info">
                                        <div class="service-name"><?php echo esc_html($invoice->service_name); ?></div>
                                        <small class="booking-number">Booking: <?php echo esc_html($invoice->booking_number); ?></small>
                                    </td>
                                    <td class="invoice-date">
                                        <?php echo date('M j, Y', strtotime($invoice->issue_date)); ?>
                                        <small class="service-date">Service: <?php echo date('M j, Y', strtotime($invoice->booking_date)); ?></small>
                                    </td>
                                    <td class="invoice-amount">
                                        <strong>₹<?php echo number_format($invoice->total_amount, 2); ?></strong>
                                        <?php if ($invoice->balance_amount > 0): ?>
                                            <small class="balance-due">Due: ₹<?php echo number_format($invoice->balance_amount, 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="invoice-status">
                                        <span class="status-badge status-<?php echo esc_attr($invoice->status); ?>">
                                            <?php echo esc_html(ucfirst($invoice->status)); ?>
                                        </span>
                                    </td>
                                    <td class="invoice-actions">
                                        <div class="action-buttons">
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=sahayya_preview_invoice_pdf&invoice_id=' . $invoice->id), 'sahayya_invoice_nonce', 'nonce')); ?>" 
                                               class="button button-small preview-pdf" 
                                               target="_blank" 
                                               title="<?php _e('Preview PDF', 'sahayya-booking'); ?>">
                                                <span class="dashicons dashicons-visibility"></span> <?php _e('View', 'sahayya-booking'); ?>
                                            </a>
                                            <button type="button" 
                                                    class="button button-small download-pdf" 
                                                    data-invoice-id="<?php echo $invoice->id; ?>"
                                                    data-nonce="<?php echo wp_create_nonce('sahayya_invoice_nonce'); ?>"
                                                    title="<?php _e('Download PDF', 'sahayya-booking'); ?>">
                                                <span class="dashicons dashicons-download"></span> <?php _e('Download', 'sahayya-booking'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-invoices">
                    <div class="no-invoices-icon">
                        <span class="dashicons dashicons-media-document"></span>
                    </div>
                    <h4><?php _e('No Invoices Found', 'sahayya-booking'); ?></h4>
                    <p><?php _e('You don\'t have any invoices yet. Book a service to get started!', 'sahayya-booking'); ?></p>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="button button-primary">
                        <?php _e('Book a Service', 'sahayya-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <style type="text/css">
        .sahayya-invoices-container {
            max-width: 1000px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .sahayya-invoices-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .sahayya-invoices-header h3 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .sahayya-invoices-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .invoices-table-wrapper {
            padding: 30px;
            overflow-x: auto;
        }
        
        .sahayya-invoices-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .sahayya-invoices-table thead {
            background: #f8f9fa;
        }
        
        .sahayya-invoices-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        
        .sahayya-invoices-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: top;
        }
        
        .sahayya-invoices-table tbody tr:hover {
            background: #f8f9ff;
        }
        
        .invoice-number strong {
            color: #3498db;
            font-size: 1.1rem;
        }
        
        .service-info .service-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        
        .service-info .booking-number {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .invoice-date {
            font-weight: 500;
        }
        
        .invoice-date .service-date {
            display: block;
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        
        .invoice-amount strong {
            color: #27ae60;
            font-size: 1.1rem;
        }
        
        .balance-due {
            display: block;
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-draft {
            background: #ffeaa7;
            color: #d63031;
        }
        
        .status-sent {
            background: #74b9ff;
            color: #0984e3;
        }
        
        .status-paid {
            background: #55efc4;
            color: #00b894;
        }
        
        .status-overdue {
            background: #fd79a8;
            color: #e84393;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-buttons .button {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            font-size: 0.85rem;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .preview-pdf {
            background: #3498db;
            color: white;
            border: none;
        }
        
        .preview-pdf:hover {
            background: #2980b9;
            color: white;
        }
        
        .download-pdf {
            background: #27ae60;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .download-pdf:hover {
            background: #229954;
            color: white;
        }
        
        .download-pdf:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .no-invoices {
            text-align: center;
            padding: 60px 30px;
            color: #6c757d;
        }
        
        .no-invoices-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .no-invoices h4 {
            color: #495057;
            margin: 0 0 15px 0;
            font-size: 1.3rem;
        }
        
        .no-invoices p {
            margin: 0 0 25px 0;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sahayya-invoices-container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .sahayya-invoices-header {
                padding: 20px;
            }
            
            .invoices-table-wrapper {
                padding: 15px;
            }
            
            .sahayya-invoices-table th,
            .sahayya-invoices-table td {
                padding: 10px 8px;
                font-size: 0.9rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 6px;
            }
            
            .action-buttons .button {
                justify-content: center;
                width: 100%;
                padding: 10px;
            }
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle PDF download
            $('.download-pdf').on('click', function() {
                var button = $(this);
                var invoiceId = button.data('invoice-id');
                var nonce = button.data('nonce');
                
                // Disable button during download
                button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Generating...');
                
                // Create hidden form for download
                var form = $('<form>', {
                    method: 'POST',
                    action: ajaxurl,
                    style: 'display: none;'
                });
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'action',
                    value: 'sahayya_download_invoice_pdf'
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'invoice_id',
                    value: invoiceId
                }));
                
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'nonce',
                    value: nonce
                }));
                
                $('body').append(form);
                form.submit();
                form.remove();
                
                // Re-enable button after a delay
                setTimeout(function() {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Download');
                }, 2000);
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
}
?>
