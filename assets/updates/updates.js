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
            // Add attribute.
            $('#builtmighty-kit-update').attr('id', id);
        }
    });

    // If close updates.
    $('#builtmighty-kit-close,.builtmighty-kit-modal-close').on('click', function() {
        // Close confirm.
        $('#builtmighty-kit-updates').css('display', 'none');
    });

    // If update.
    $('button#builtmighty-kit-update').on('click', function() {
        // Add class.
        $(this).addClass('updating');
        // Check for bulk.
        if($('input#upgrade-plugins').length) {
            // Click bulk.
            $('input#upgrade-plugins').click();
        } else if($('input#upgrade-themes').length) {
            // Click bulk.
            $('input#upgrade-themes').click();
        } else {
            // Get ID.
            var id = $(this).attr('id');
            // Click update.
            $('#' + id).find('.update-link').click();
        }
        // Close confirm.
        $('#builtmighty-kit-updates').css('display', 'none');
    });

    // On install.
    $('#builtmighty-kit-install').on('click', function() {
        // Close modal.
        $('div#builtmighty-kit-install').css('display', 'none');
    });
    $('#builtmighty-kit-install-close').on('click', function() {
        // Redirect to plugins.
        window.location.href = 'plugins.php';
    });

});