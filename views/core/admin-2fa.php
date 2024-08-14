<?php
// Check if 2FA is enabled.
if( ! defined( 'BUILT_2FA' ) ) return;

// Output horizontal rule.
echo '<hr style="opacity:0.1">';

// Set roles.
$roles = [];

// Loop through roles.
foreach( get_editable_roles() as $role => $details ) {

    // Skip if administrator or super administrator.
    if( in_array( $role, [ 'administrator', 'super_admin' ] ) ) continue;

    // Add role.
    $roles[ $role ] = $details['name'];

}

// Output user roles checkbox.
echo $this->field( '2fa-roles', '2FA Roles', [
    'type'      => 'checkbox',
    'options'   => $roles
] );