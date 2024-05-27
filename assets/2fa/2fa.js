/* 2fa.js */
jQuery(document).ready(function($) {

    // Intercept hitting "enter" to submit the form.
    $('input#user_login,input#user_pass').keypress(function(e) {
        if(e.which == 13) {
            e.preventDefault();
            $('span#check-2fa').click();
        }
    });

    // On click.
    $('span#check-2fa').on('click', function() {
        // Get login.
        var login = $('input#user_login').val();
        // AJAX. 
        $.ajax({
            url: built2FA.ajaxurl,
            type: 'POST',
            data: {
                action: 'check_2fa',
                login: login,
                nonce: built2FA.nonce
            },
            success: function(response) {
                // Check response.
                if(response == 'continue') {
                    // Submit the form.
                    $('form#loginform').submit();
                } else if(response == 'confirm') {
                    // Reveal the 2FA field.
                    $('p#authenticator-code').css('display', 'block');
                    $('p#authenticator-code').css('height', 'auto');
                    var fieldHeight = $('p#authenticator-code').height();
                    $('p#authenticator-code').css('height', '0');
                    $('p#authenticator-code').animate({
                        height: fieldHeight,
                    },500);
                    // Hide the check button.
                    $('span#check-2fa').css('display', 'none');
                    // Show the submit button.
                    $('input#wp-submit').css('display', 'block');
                }
            }
        });
    });

});