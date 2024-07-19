<?php
// Slack.
if( empty( get_option( 'built_slack_token' ) ) ) {

    // Set authorization key.
    if( empty( get_option( 'built_api_key' ) ) ) {

        // Set key.
        $key = bin2hex( random_bytes( 16 ) );

        // Save.
        update_option( 'built_api_key', $key );

    } else {

        // Set key.
        $key = get_option( 'built_api_key' );

    }

    // Slack.
    $state = http_build_query( [
        'site'  => site_url(),
        'key'   => $key
    ] ); ?>
    <a href="https://slack.com/oauth/v2/authorize?scope=chat%3Awrite%2Cchannels%3Aread%2Cgroups%3Aread%2Cim%3Aread%2Cmpim%3Aread%2Cchannels%3Ajoin%2Cfiles%3Awrite&amp;user_scope=&amp;redirect_uri=https%3A%2F%2Fbuiltmighty.com%2Fwp-json%2Fbuiltmighty-kit%2Fv1%2Fslack&amp;state=<?php echo base64_encode( $state ); ?>&amp;client_id=3387858095.7426170344038" style="align-items:center;color:#000;background-color:#fff;border:1px solid #ddd;border-radius:4px;display:inline-flex;font-family:Lato, sans-serif;font-size:16px;font-weight:600;height:48px;justify-content:center;text-decoration:none;width:236px;margin-bottom:15px;"><svg xmlns="http://www.w3.org/2000/svg" style="height:20px;width:20px;margin-right:12px" viewBox="0 0 122.8 122.8"><path d="M25.8 77.6c0 7.1-5.8 12.9-12.9 12.9S0 84.7 0 77.6s5.8-12.9 12.9-12.9h12.9v12.9zm6.5 0c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9v32.3c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V77.6z" fill="#e01e5a"></path><path d="M45.2 25.8c-7.1 0-12.9-5.8-12.9-12.9S38.1 0 45.2 0s12.9 5.8 12.9 12.9v12.9H45.2zm0 6.5c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H12.9C5.8 58.1 0 52.3 0 45.2s5.8-12.9 12.9-12.9h32.3z" fill="#36c5f0"></path><path d="M97 45.2c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9-5.8 12.9-12.9 12.9H97V45.2zm-6.5 0c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V12.9C64.7 5.8 70.5 0 77.6 0s12.9 5.8 12.9 12.9v32.3z" fill="#2eb67d"></path><path d="M77.6 97c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9-12.9-5.8-12.9-12.9V97h12.9zm0-6.5c-7.1 0-12.9-5.8-12.9-12.9s5.8-12.9 12.9-12.9h32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H77.6z" fill="#ecb22e"></path></svg>Add to Slack</a><?php
    

} else {

    // Delete.
    if( isset( $_POST['remove_slack'] ) && $_POST['remove_slack'] == 'true' ) {

        // Delete.
        delete_option( 'built_slack_token' );
        delete_option( 'built_slack_channel' );
        
        // Reload. 
        wp_safe_redirect( admin_url( 'admin.php?page=builtmighty' ) );

    }

    // Get Slack.
    $slack = new \BuiltMightyKit\Plugins\builtSlack();

    // Channel select field
    echo $this->field( 'slack-channel', 'Slack Channel', [
        'type'      => 'select',
        'options'   => $slack->get_channels()
    ] ); 

    // Disconnect Slack. ?>
    <button name="remove_slack" class="button button-secondary" value="true"style="align-items:center;color:#fff;background:none;border:1px solid #ddd;border-radius:4px;display:inline-flex;font-family:Lato, sans-serif;font-size:16px;font-weight:600;height:48px;justify-content:center;text-decoration:none;width:236px;margin-bottom:15px;"><svg xmlns="http://www.w3.org/2000/svg" style="height:20px;width:20px;margin-right:12px" viewBox="0 0 122.8 122.8"><path d="M25.8 77.6c0 7.1-5.8 12.9-12.9 12.9S0 84.7 0 77.6s5.8-12.9 12.9-12.9h12.9v12.9zm6.5 0c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9v32.3c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V77.6z" fill="#e01e5a"></path><path d="M45.2 25.8c-7.1 0-12.9-5.8-12.9-12.9S38.1 0 45.2 0s12.9 5.8 12.9 12.9v12.9H45.2zm0 6.5c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H12.9C5.8 58.1 0 52.3 0 45.2s5.8-12.9 12.9-12.9h32.3z" fill="#36c5f0"></path><path d="M97 45.2c0-7.1 5.8-12.9 12.9-12.9s12.9 5.8 12.9 12.9-5.8 12.9-12.9 12.9H97V45.2zm-6.5 0c0 7.1-5.8 12.9-12.9 12.9s-12.9-5.8-12.9-12.9V12.9C64.7 5.8 70.5 0 77.6 0s12.9 5.8 12.9 12.9v32.3z" fill="#2eb67d"></path><path d="M77.6 97c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9-12.9-5.8-12.9-12.9V97h12.9zm0-6.5c-7.1 0-12.9-5.8-12.9-12.9s5.8-12.9 12.9-12.9h32.3c7.1 0 12.9 5.8 12.9 12.9s-5.8 12.9-12.9 12.9H77.6z" fill="#ecb22e"></path></svg>Disconnect Slack</button><?php

    // Notification options.
    echo $this->field( 'slack-notifications', 'Slack Notifications', [
        'type'      => 'checkbox',
        'options'   => [
            'woocommerce'       => 'WooCommerce Settings',
            'plugin-install'    => 'Plugin Installation',
            'plugin-update'     => 'Plugin Update',
            'plugin-activate'   => 'Plugin Activation',
            'plugin-deactivate' => 'Plugin Deactivation',
            'plugin-editor'     => 'Plugin Editor',
            'theme-install'     => 'Theme Installation',
            'theme-update'      => 'Theme Update',
            'theme-change'      => 'Theme Change',
            'theme-editor'      => 'Theme Editor',
            'admin-create'      => 'Admin User Creation',
            'admin-delete'      => 'Admin User Deletion',
            'admin-role'        => 'Admin User Role Change',
            'admin-password'    => 'Admin User Password Change',
            'admin-email'       => 'Admin User Email Change',
            'admin-login'       => 'Admin User Login'
        ]
    ] );

}