// Sahayya Booking System - Admin JavaScript

jQuery(document).ready(function($) {

    // Image upload handling
    $('.upload-image-button').click(function(e) {
        e.preventDefault();
        
        var button = $(this);
        var wp_media_post_id = wp.media.model.settings.post.id;
        var set_to_post_id = button.data('post-id');
        
        var file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Select Image',
            button: {
                text: 'Use this image',
            },
            multiple: false
        });
        
        file_frame.on('select', function() {
            var attachment = file_frame.state().get('selection').first().toJSON();
            button.siblings('input').val(attachment.url);
            button.siblings('.image-preview').html('<img src="' + attachment.url + '" style="max-width: 150px;">');
            wp.media.model.settings.post.id = wp_media_post_id;
        });
        
        file_frame.open();
    });
    
});