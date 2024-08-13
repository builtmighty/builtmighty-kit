<?php
// Check if option exists.
if( ! get_option( 'built-data-key' ) ) return;

// Data API.
echo $this->field( 'built-data-key', 'Data API Key', [
    'type'      => 'text',
    'readonly'  => true
] );