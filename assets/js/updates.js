/* updates.js */
jQuery(document).ready(function($) {

    // Show.
    $('div#builtmighty-kit-modal').show();

    // Close.
    $('span.builtmighty-kit-modal-close, button#builtmighty-kit-modal-continue').on('click', function() {
        $('div#builtmighty-kit-modal').hide();
    });

    // Leave.
    $('button#builtmighty-kit-modal-leave').on('click', function() {
        // Redirect.
        window.location.href = kit_updates.admin_url;
    });

});