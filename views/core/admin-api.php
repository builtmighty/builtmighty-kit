<?php
// Check if option exists.
if( ! get_option( 'built-data-key' ) ) return;

// Output horizontal rule.
echo '<hr style="opacity:0.1">';

// Data API.
echo $this->field( 'built-data-key', 'Data API Key', [
    'type'      => 'text',
    'readonly'  => true
] );