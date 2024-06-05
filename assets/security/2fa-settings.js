/* 2fa-settings.js */
jQuery(document).ready(function($) {

    // Listen for any form submissions and stop them.
    $('form').submit(function(e) {
        // Check if authentication field exists.
        if($('form input[name="authentication_code"]').length > 0) {
            // Allow submission.
        } else {
            // Stop input.
            e.preventDefault();
            // Open the modal.
            $('#builtmighty-setting-authentication').show();
        }
    });

    // Confirm the authentication code.
    $('span#builtmighty-submit-auth').on('click', function() {
        // Check for the authentication code.
        var code = $('input#builtmighty-setting-auth').val();
        // Check if the code is empty.
        if(code == '') {
            // Show the error.
            $('span#builtmighty-authentication-error').show();
        } else {
            // Hide the error.
            $('span#builtmighty-authentication-error').hide();
            // Hide the modal.
            $('#builtmighty-setting-authentication').hide();
            // Add the authentication code to the form.
            $('form').append('<input type="hidden" name="authentication_code" value="' + code + '">');
            // Submit the form.
            $('form button[type=submit]').click();
        }
    });

});