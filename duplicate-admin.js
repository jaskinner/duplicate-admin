jQuery(document).ready(function($) {
    $('#ajax-button').on('click', function(e) {
        e.preventDefault();

        $.ajax({
            url: my_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'my_ajax_action',
                security: my_ajax_object.security,
            },
            success: function(response) {
                console.log('AJAX request successful:', response);
            },
            error: function(xhr, status, error) {
                console.log('AJAX request failed:', error);
            }
        });
    });
});