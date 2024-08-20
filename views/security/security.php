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
        <link rel="stylesheet" href="<?php echo BUILT_URI . 'assets/security/security.css?ver=' . date( 'YmdHis' ); ?>">
    </head>
    <body><?php

        // Check for a key.
        if( isset( $_GET['key'] ) ) {

            // Get key.
            $get_key = explode( ':', base64_decode( $_GET['key'] ) );
                    
            // Set variables.
            $user_id    = $get_key[0];
            $key        = $get_key[1];

            // Get current user.
            $user = get_user_by( 'ID', $user_id ); 
            
            // Container. ?>
            <div class="built-security built-security-container"><?php

                // Header.
                include BUILT_PATH . 'views/security/security-header.php';

                // Check for keys.
                if( isset( $_GET['confirm'] ) && $_GET['confirm'] == 'true' ) {

                    // Load confirm.
                    include BUILT_PATH . 'views/security/security-confirm.php';

                } elseif( isset( $_GET['status'] ) && $_GET['status'] == 'confirmed' ) {

                    // Load confirmed.
                    include BUILT_PATH . 'views/security/security-confirmed.php';

                } else {
                
                    // Load form.
                    include BUILT_PATH . 'views/security/security-setup.php';
                
                } ?>

            </div><?php

        } else {

            // Redirect.
            wp_redirect( site_url( '/login' ) );
            exit;

        } ?>

    </body>
</html>