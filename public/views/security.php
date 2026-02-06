<?php
/**
 * Security.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */ ?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title><?php echo get_bloginfo( 'name' ); ?> | Two-Factor Authentication</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@100..900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo KIT_URI . 'public/css/security.css?ver=' . date( 'YmdHis' ); ?>">
    </head>
    <body><?php

        // Check for a key.
        if( isset( $_GET['key'] ) ) {

            // Sanitize the key parameter.
            $key_param = sanitize_text_field( wp_unslash( $_GET['key'] ) );

            // Get auth.
            $auth = new \BuiltMightyKit\Utility\authentication();

            // Get key.
            $key = $auth->get_key( $key_param );

            // Get current user.
            $user = get_user_by( 'ID', $key['user_id'] ); 
            
            // Container. ?>
            <div class="built-security built-security-container"><?php

                // Header.
                include KIT_PATH . 'public/views/security-header.php';

                // Check for keys.
                if( isset( $_GET['confirm'] ) && sanitize_text_field( wp_unslash( $_GET['confirm'] ) ) === 'true' ) {

                    // Load confirm.
                    include KIT_PATH . 'public/views/security-confirm.php';

                } elseif( isset( $_GET['status'] ) && sanitize_text_field( wp_unslash( $_GET['status'] ) ) === 'confirmed' ) {

                    // Load confirmed.
                    include KIT_PATH . 'public/views/security-confirmed.php';

                } else {
                
                    // Load form.
                    include KIT_PATH . 'public/views/security-setup.php';
                
                } ?>

            </div><?php

        } else {

            // Redirect.
            wp_redirect( site_url( '/login' ) );
            exit;

        } ?>

    </body>
</html>