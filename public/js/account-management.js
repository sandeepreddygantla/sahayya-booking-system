/**
 * Sahayya Booking System - Account Management JavaScript
 * Handles user dashboard, booking management, and dependent management
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        // Initialize all event handlers
        initDependentManagement();
        initBookingManagement();
        initModalHandlers();
    });

    /**
     * Initialize Dependent Management
     */
    function initDependentManagement() {
        // Add/Edit Dependent Form Submission
        $('#add-dependent-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.append('action', 'sahayya_add_dependent');

            var submitBtn = $(this).find('button[type="submit"]');
            var originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Saving...');

            $.ajax({
                url: sahayya_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert('Dependent saved successfully!');

                        // Close modal
                        closeAddDependentModal();

                        // Reload page to show updated dependent
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Failed to save dependent. Please try again.');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Edit Dependent Button
        $(document).on('click', '.edit-dependent', function() {
            var dependentId = $(this).data('dependent-id');

            // Load dependent data
            $.ajax({
                url: sahayya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sahayya_get_dependent',
                    dependent_id: dependentId,
                    nonce: sahayya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var dependent = response.data;

                        // Fill form with dependent data
                        $('#dependent_id').val(dependent.id);
                        $('#dependent_name').val(dependent.name);
                        $('#dependent_age').val(dependent.age);
                        $('#dependent_gender').val(dependent.gender);
                        $('#dependent_address').val(dependent.address);
                        $('#dependent_medical_conditions').val(dependent.medical_conditions || '');

                        // Update modal title
                        $('#dependent-modal-title').text('Edit Dependent');

                        // Show modal
                        showAddDependentModal();
                    } else {
                        alert('Error loading dependent data: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to load dependent data. Please try again.');
                }
            });
        });

        // Delete Dependent Button
        $(document).on('click', '.delete-dependent', function() {
            if (!confirm('Are you sure you want to delete this dependent? This action cannot be undone.')) {
                return;
            }

            var dependentId = $(this).data('dependent-id');
            var dependentItem = $(this).closest('.dependent-item');

            $.ajax({
                url: sahayya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sahayya_delete_dependent',
                    dependent_id: dependentId,
                    nonce: sahayya_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the dependent item with animation
                        dependentItem.fadeOut(300, function() {
                            $(this).remove();

                            // Check if there are no more dependents
                            if ($('.dependent-item').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to delete dependent. Please try again.');
                }
            });
        });
    }

    /**
     * Initialize Booking Management
     */
    function initBookingManagement() {
        // View Booking Details
        $(document).on('click', '.view-booking-details', function() {
            var bookingId = $(this).data('booking-id');

            // Show loading
            $('#booking-details-content').html('<p class="loading">Loading...</p>');
            $('#booking-details-modal').fadeIn(300);

            // Load booking details
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
                        var booking = response.data;
                        var html = formatBookingDetails(booking);
                        $('#booking-details-content').html(html);
                    } else {
                        $('#booking-details-content').html('<p class="error">Error loading booking details: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('#booking-details-content').html('<p class="error">Failed to load booking details. Please try again.</p>');
                }
            });
        });

        // Cancel Booking
        $(document).on('click', '.cancel-booking', function() {
            if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                return;
            }

            var bookingId = $(this).data('booking-id');
            var bookingCard = $(this).closest('.booking-card');
            var button = $(this);
            var originalHtml = button.html();

            button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Cancelling...');

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
                        // Update UI to show cancelled status
                        bookingCard.find('.status-badge').removeClass()
                            .addClass('status-badge status-cancelled')
                            .text('Cancelled');

                        // Remove action buttons
                        bookingCard.find('.cancel-booking, .modify-booking').remove();

                        // Show success message
                        alert('Booking cancelled successfully.');
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    alert('Failed to cancel booking. Please try again.');
                    button.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Modify Booking (placeholder for future implementation)
        $(document).on('click', '.modify-booking', function() {
            var bookingId = $(this).data('booking-id');
            alert('Booking modification feature coming soon! Booking ID: ' + bookingId);
            // TODO: Implement booking modification
        });
    }

    /**
     * Initialize Modal Handlers
     */
    function initModalHandlers() {
        // Close modal when clicking on close button
        $(document).on('click', '.close-modal', function() {
            $(this).closest('.sahayya-modal').fadeOut(300);
        });

        // Close modal when clicking outside
        $(document).on('click', '.sahayya-modal', function(e) {
            if (e.target === this) {
                $(this).fadeOut(300);
            }
        });

        // Close modal with ESC key
        $(document).keydown(function(e) {
            if (e.key === 'Escape') {
                $('.sahayya-modal:visible').fadeOut(300);
            }
        });
    }

    /**
     * Show Add Dependent Modal
     */
    window.showAddDependentModal = function() {
        // Reset form
        $('#add-dependent-form')[0].reset();
        $('#dependent_id').val('');
        $('#dependent-modal-title').text('Add New Dependent');

        // Show modal
        $('#add-dependent-modal').fadeIn(300);
    };

    /**
     * Close Add Dependent Modal
     */
    window.closeAddDependentModal = function() {
        $('#add-dependent-modal').fadeOut(300);
    };

    /**
     * Format Booking Details for Display
     */
    function formatBookingDetails(booking) {
        var html = '<div class="booking-details-display">';

        // Service Information
        html += '<div class="detail-section">';
        html += '<h5>Service Information</h5>';
        html += '<p><strong>Service:</strong> ' + escapeHtml(booking.service_name) + '</p>';
        html += '<p><strong>Booking Number:</strong> ' + escapeHtml(booking.booking_number) + '</p>';
        html += '<p><strong>Status:</strong> <span class="status-badge status-' + booking.booking_status + '">' +
                escapeHtml(booking.booking_status.charAt(0).toUpperCase() + booking.booking_status.slice(1)) + '</span></p>';
        html += '</div>';

        // Schedule Information
        html += '<div class="detail-section">';
        html += '<h5>Schedule</h5>';
        html += '<p><strong>Date:</strong> ' + formatDate(booking.booking_date) + '</p>';
        html += '<p><strong>Time:</strong> ' + formatTime(booking.booking_time) + '</p>';
        if (booking.urgency_level && booking.urgency_level !== 'normal') {
            html += '<p><strong>Urgency:</strong> <span class="urgency-' + booking.urgency_level + '">' +
                    escapeHtml(booking.urgency_level.charAt(0).toUpperCase() + booking.urgency_level.slice(1)) + '</span></p>';
        }
        html += '</div>';

        // Dependent Information
        if (booking.dependents && booking.dependents.length > 0) {
            html += '<div class="detail-section">';
            html += '<h5>Dependents</h5>';
            html += '<ul class="dependents-list-modal">';
            booking.dependents.forEach(function(dependent) {
                html += '<li>' + escapeHtml(dependent.name) + ' (' + dependent.age + ' years)</li>';
            });
            html += '</ul>';
            html += '</div>';
        }

        // Employee Information
        if (booking.employee_name) {
            html += '<div class="detail-section">';
            html += '<h5>Assigned Employee</h5>';
            html += '<p><strong>Name:</strong> ' + escapeHtml(booking.employee_name) + '</p>';
            if (booking.employee_phone) {
                html += '<p><strong>Phone:</strong> ' + escapeHtml(booking.employee_phone) + '</p>';
            }
            html += '</div>';
        }

        // Pricing Information
        html += '<div class="detail-section pricing-section">';
        html += '<h5>Pricing</h5>';
        if (booking.base_amount) {
            html += '<p><strong>Base Service Fee:</strong> ₹' + parseFloat(booking.base_amount).toFixed(2) + '</p>';
        }
        if (booking.dependent_charges && booking.dependent_charges > 0) {
            html += '<p><strong>Dependent Charges (' + booking.dependent_count + ' × ₹' + parseFloat(booking.per_person_price).toFixed(2) + '):</strong> ₹' + parseFloat(booking.dependent_charges).toFixed(2) + '</p>';
        }
        if (booking.extras_amount && booking.extras_amount > 0) {
            html += '<p><strong>Service Extras:</strong> ₹' + parseFloat(booking.extras_amount).toFixed(2) + '</p>';
        }
        html += '<p class="total-amount"><strong>Total Amount:</strong> <span>₹' + parseFloat(booking.total_amount).toFixed(2) + '</span></p>';
        html += '</div>';

        // Special Instructions
        if (booking.special_instructions) {
            html += '<div class="detail-section">';
            html += '<h5>Special Instructions</h5>';
            html += '<p>' + escapeHtml(booking.special_instructions) + '</p>';
            html += '</div>';
        }

        html += '</div>';

        // Add some basic styling
        html += '<style>';
        html += '.booking-details-display { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; }';
        html += '.detail-section { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e9ecef; }';
        html += '.detail-section:last-child { border-bottom: none; }';
        html += '.detail-section h5 { margin: 0 0 15px 0; color: #2c3e50; font-size: 1.1rem; font-weight: 600; }';
        html += '.detail-section p { margin: 8px 0; color: #495057; line-height: 1.6; }';
        html += '.dependents-list-modal { margin: 10px 0; padding-left: 20px; }';
        html += '.dependents-list-modal li { margin: 8px 0; color: #495057; }';
        html += '.pricing-section { background: #f8f9fa; padding: 20px; border-radius: 8px; border: none !important; }';
        html += '.total-amount { font-size: 1.2rem; margin-top: 15px; padding-top: 15px; border-top: 2px solid #dee2e6; }';
        html += '.total-amount span { color: #27ae60; font-weight: 700; }';
        html += '</style>';

        return html;
    }

    /**
     * Utility Functions
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        var date = new Date(dateString);
        var options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        var time = timeString.split(':');
        var hours = parseInt(time[0]);
        var minutes = time[1];
        var ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        return hours + ':' + minutes + ' ' + ampm;
    }

})(jQuery);
