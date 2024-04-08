/* updates.js */
jQuery(document).ready(function($) {

    // On update.
    $('tr.plugin-update-tr,form.upgrade > p,.theme .update-message').on('click', function() {
        // Get ID.
        var id = $(this).attr('id');
        // Check if we have class.
        if($(this).hasClass('updating')) {
            // Do nothing.
        } else {
            // Open confirm.
            $('#builtmighty-kit-updates').css('display', 'flex');
            // If close.
            $('#builtmighty-kit-close,.builtmighty-kit-modal-close').on('click', function() {
                // Close confirm.
                $('#builtmighty-kit-updates').css('display', 'none');
            });
            // If update.
            $('#builtmighty-kit-update').on('click', function() {
                // Update.
                $('#builtmighty-kit-updates').css('display', 'none');
                // Add class.
                $(this).addClass('updating');
                // Click update.
                $('#' + id).find('.update-link').click();
            });
        }
    });

});