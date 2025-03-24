/* login-security.js */
jQuery(document).ready(function($) {

    // Intercept hitting "enter" to submit the form.
    $('input#user_login,input#user_pass,input#username,input#password').keypress(function(e) {
        if(e.which == 13) {
            e.preventDefault();
            $('span#check-2fa').click();
        }
    });

    // On click.
    $('span#check-2fa').on('click', function() {
        // Get login.
        if($('input#user_login').length > 0) {
            var login = $('input#user_login').val();
        } else if($('input#username').length > 0) {
            var login = $('input#username').val();
        }
        // Check if login has data.
        if(login == '') {
            // No data.
        } else {
            // AJAX. 
            $.ajax({
                url: builttwofactor.ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_user',
                    login: login,
                    nonce: builttwofactor.nonce
                },
                success: function(response) {
                    // Check response.
                    if(response == 'continue') {
                        // Submit the form.
                        if($('form#loginform').length > 0) {
                            $('form#loginform').submit();
                        } else if($('form.woocommerce-form-login').length > 0) {
                            $('form.woocommerce-form-login button[type=submit]').click();
                        }
                    } else if(response == 'confirm') {
                        // Reveal the 2FA field.
                        $('p#authentication-code').css('display', 'block');
                        $('p#authentication-code').css('height', 'auto');
                        var fieldHeight = $('p#authentication-code').height();
                        $('p#authentication-code').css('height', '0');
                        $('p#authentication-code').animate({
                            height: fieldHeight,
                        },500);
                        // Hide the check button.
                        $('span#check-2fa').css('display', 'none');
                        // Show the submit button.
                        $('input#wp-submit').css('display', 'block');
                        $('button.woocommerce-form-login__submit').css('display', 'block');
                    }
                }
            });
        }
    });

});