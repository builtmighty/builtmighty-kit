<?php
// Check if 2FA is enabled.
if( ! defined( 'BUILT_2FA' ) ) return;

// Output horizontal rule.
echo '<hr style="opacity:0.1">';

// Get user roles.
$roles = get_editable_roles();

// Remove administrator.
unset( $roles['administrator'] );

// Remove super administator if multisite.
if( is_multisite() ) unset( $roles['super_admin'] );

// Get roles.
$roles = array_keys( $roles );

// Output user roles checkbox.
echo $this->field( '2fa-roles', '2FA Roles', [
    'type'      => 'checkbox',
    'options'   => $roles
] );