// dev-admin.js
jQuery(document).ready(function($) {

    // On run.
    $('.button.built-action').on('click', function(e) {
        // Prevent default.
        e.preventDefault();
        // Confirm tool run.
        if( ! confirm('Are you sure you want to run?') ) {
            return false;
        } else {
            // Run tool.
            runAJAX($(this).data('set'));
        }
    });

    // Run AJAX.
    function runAJAX(data_set) {
        // Show loading icon.
        if( $('.built-loading').css('opacity') != 1 ) {
            $('.built-loading').css('opacity', 1);
        }
        // AJAX.
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: data_set.action,
                nonce: built.nonce,
                data_set: data_set,
            },
            success: function(response) {
                // Parse response.
                let resp = JSON.parse(response);
                // Check if data.percentage is set.
                if(resp.percentage != undefined && resp.percentage != null) {
                    // Update percentage.
                    $('#' + resp.id + ' .built-bar-status').text(resp.percentage + '%');
                    // Animate percentage text.
                    $('#' + resp.id + ' .built-bar-status').animate({
                        opacity: 1,
                        width: resp.percentage + '%',
                    }, 500);
                    // Animate progress bar.
                    $('#' + resp.id + ' .built-bar-inner').animate({
                        width: resp.percentage + '%',
                    }, 500, function() {
                        // Check if we're done via percentage.
                        if(resp.percentage < 100) {
                            // Run tool.
                            runAJAX(resp);
                        } else {
                            // Hide loading icon.
                            $('.built-loading').css('opacity', 0);
                            // Change button text.
                            $('#' + resp.id + ' .button.built-action').attr('value', 'Complete');
                        }
                    });
                }
            }
        });
    }

});