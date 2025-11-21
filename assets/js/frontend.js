// Sahayya Booking System - Frontend JavaScript

jQuery(document).ready(function($) {

    let currentStep = 1;
    let selectedService = null;
    let selectedDependents = [];
    
    // Initialize first step
    showStep(1);
    
    // Step navigation
    function showStep(step) {
        $('.booking-step').removeClass('active').hide();
        $('.booking-step.step-' + step).addClass('active').show();
        currentStep = step;
        
        // Update step indicator if exists
        $('.step-indicator .step').removeClass('active current');
        $('.step-indicator .step').slice(0, step - 1).addClass('active');
        $('.step-indicator .step').eq(step - 1).addClass('current');
        
        // Scroll to top of form
        $('.sahayya-booking-form').get(0).scrollIntoView({ behavior: 'smooth' });
    }
    
    // Service selection
    $(document).on('change', 'input[name="service_id"]', function() {
        selectedService = {
            id: $(this).val(),
            name: $(this).closest('.service-option').find('h5').text(),
            basePrice: parseFloat($(this).closest('.service-option').find('.base-price').text().replace('₹', '').replace(',', '')),
            perPersonPrice: parseFloat($(this).closest('.service-option').find('.per-person').text().replace('+ ₹', '').replace('/person', '').replace(',', '')) || 0
        };
        
        $('.step-1 .next-step').prop('disabled', false);
    });
    
    // Dependent selection
    $(document).on('change', 'input[name="dependent_ids[]"]', function() {
        selectedDependents = [];
        $('input[name="dependent_ids[]"]:checked').each(function() {
            selectedDependents.push({
                id: $(this).val(),
                name: $(this).closest('.dependent-option').find('h5').text(),
                age: $(this).closest('.dependent-option').find('p').text(),
                address: $(this).closest('.dependent-option').find('.dependent-address').text()
            });
        });

        $('.step-2 .next-step').prop('disabled', selectedDependents.length === 0);
        updatePricingBreakdown();
    });
    
    // Check for already selected dependents on page load  
    setTimeout(function() {
        if ($('input[name="dependent_ids[]"]:checked').length > 0) {
            $('input[name="dependent_ids[]"]:checked').first().trigger('change');
        }
    }, 1000);
    
    // Step navigation buttons - DISABLED to prevent conflict with inline navigation
    // The shortcode template has its own navigation system that handles step transitions
    // This jQuery handler was causing conflicts by running after the inline handler
    /*
    $(document).on('click', 'button.next-step', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Next step clicked, current step:', currentStep);
        console.log('Selected service:', selectedService);
        console.log('Selected dependents:', selectedDependents);

        // Validate current step before proceeding
        if (currentStep === 1 && !selectedService) {
            alert('Please select a service first.');
            return;
        }

        if (currentStep === 2 && selectedDependents.length === 0) {
            alert('Please select at least one dependent.');
            return;
        }

        // Additional validation for step 3 (datetime)
        if (currentStep === 3) {
            const bookingDate = $('#booking_date').val();
            const bookingTime = $('#booking_time').val();

            if (!bookingDate || !bookingTime) {
                alert('Please select both date and time for your appointment.');
                return;
            }

            updateBookingSummary();
        }

        if (currentStep < 6) {
            showStep(currentStep + 1);
        }
    });
    */
    
    // Previous step button - DISABLED to prevent conflict with inline navigation
    /*
    $(document).on('click', '.prev-step', function(e) {
        e.preventDefault();
        console.log('Previous step clicked, current step:', currentStep);
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });
    */
    
    // Update pricing breakdown
    function updatePricingBreakdown() {
        if (!selectedService || selectedDependents.length === 0) return;
        
        const basePrice = selectedService.basePrice;
        const dependentCount = selectedDependents.length;
        const dependentPrice = (dependentCount > 1) ? (dependentCount - 1) * selectedService.perPersonPrice : 0;
        const totalPrice = basePrice + dependentPrice;
        
        $('#base-price-display').text('₹' + basePrice.toFixed(2));
        $('#dependent-price-display').text('₹' + dependentPrice.toFixed(2));
        $('#total-price-display').text('₹' + totalPrice.toFixed(2));
    }
    
    // Update booking summary
    function updateBookingSummary() {
        // Service summary
        $('#selected-service-summary').html(
            '<h6>' + selectedService.name + '</h6>' +
            '<p>Base Price: ₹' + selectedService.basePrice.toFixed(2) + '</p>'
        );
        
        // Dependents summary
        let dependentsHtml = '';
        selectedDependents.forEach(function(dep) {
            dependentsHtml += '<div class="dependent-summary">' +
                '<strong>' + dep.name + '</strong><br>' +
                '<small>' + dep.age + ' • ' + dep.address + '</small>' +
                '</div>';
        });
        $('#selected-dependents-summary').html(dependentsHtml);
        
        // DateTime summary
        const bookingDate = $('#booking_date').val();
        const bookingTime = $('#booking_time').val();
        const urgencyLevel = $('#urgency_level').val();
        
        $('#selected-datetime-summary').html(
            '<p><strong>Date:</strong> ' + new Date(bookingDate).toLocaleDateString() + '</p>' +
            '<p><strong>Time:</strong> ' + bookingTime + '</p>' +
            '<p><strong>Urgency:</strong> ' + urgencyLevel.charAt(0).toUpperCase() + urgencyLevel.slice(1) + '</p>'
        );
        
        updatePricingBreakdown();
    }
    
    // DISABLED: Submit booking form (handled by inline JavaScript in shortcode)
    // Commenting out to prevent duplicate submissions
    /*
    $(document).on('submit', '#sahayya-booking-form', function(e) {
        // This handler is disabled to prevent duplicate submissions
        // Form submission is now handled by inline JavaScript in the shortcode
    });
    */
    
    // Add dependent modal
    $(document).on('click', '.add-dependent-btn', function(e) {
        e.preventDefault();
        $('#add-dependent-modal').show();
    });
    
    $(document).on('click', '.close-modal', function() {
        $('.sahayya-modal').hide();
    });
    
    $(document).on('click', '.sahayya-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Submit add dependent form
    $(document).on('submit', '#add-dependent-form', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'sahayya_add_dependent');
        
        $.ajax({
            url: sahayya_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Close modal and refresh dependents list
                    $('#add-dependent-modal').hide();
                    location.reload(); // Simple refresh - you can make this more elegant
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to add dependent. Please try again.');
            }
        });
    });
    
    // Cancel booking
    $(document).on('click', '.cancel-booking', function() {
        if (!confirm('Are you sure you want to cancel this booking?')) {
            return;
        }
        
        const bookingId = $(this).data('booking-id');
        const bookingCard = $(this).closest('.booking-card');
        
        $.ajax({
            url: sahayya_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sahayya_cancel_booking',
                booking_id: bookingId,
                nonce: sahayya_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    bookingCard.addClass('status-cancelled');
                    bookingCard.find('.status-badge').removeClass().addClass('status-badge status-cancelled').text('Cancelled');
                    bookingCard.find('.cancel-booking').remove();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to cancel booking. Please try again.');
            }
        });
    });
    
    // View booking details
    $(document).on('click', '.view-details', function() {
        const bookingId = $(this).data('booking-id');
        
        $.ajax({
            url: sahayya_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sahayya_get_booking_details',
                booking_id: bookingId,
                nonce: sahayya_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // You can create a modal to show booking details here
                    // For now, just alert - you can make this more elegant
                    alert('Booking details loaded.');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to load booking details.');
            }
        });
    });
    
    // Pre-select service if URL parameter exists
    const urlParams = new URLSearchParams(window.location.search);
    const preSelectedService = urlParams.get('service');
    if (preSelectedService) {
        setTimeout(function() {
            const serviceRadio = $('#service_' + preSelectedService);
            if (serviceRadio.length > 0) {
                serviceRadio.prop('checked', true).trigger('change');
                // Auto-advance to step 2 if service is pre-selected
                showStep(2);
            }
        }, 500);
    }
    
});

// Global function for service details toggle (outside jQuery ready)
function toggleServiceDetails(serviceId) {
    const detailsDiv = document.getElementById('service-details-' + serviceId);
    const button = event.target;
    
    if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
        detailsDiv.style.display = 'block';
        button.textContent = 'Hide Details';
    } else {
        detailsDiv.style.display = 'none';
        button.textContent = 'View Details';
    }
}