<?php
/**
 * Git.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
// Set path.
$git = ABSPATH . '/.git';

// Check if Git is installed.
if( is_dir( $git ) ) {

    // Get remote origin from .git/config.
    $config = file_get_contents( $git . '/config' );

    // Get repo URL.
    preg_match( '/url = (.*)/', $config, $matches );

    // Get branch.
    $branch = trim( str_replace( 'ref: refs/heads/', '', file_get_contents( $git . '/HEAD' ) ) );

    // Check for matches.
    if( $matches[1] ) {

        // Set repo.
        $repo = str_replace( '.git', '', $matches[1] );

        // Set colors.
        $colors = ( in_array( $branch, [ 'master', 'main', 'prod', 'production' ] ) ) ? ' style="background:green;"' : '';

        // Output. ?>
        <div class="built-panel">
            <p style="margin-top:0;">
                <strong>💻 GitHub</strong>
            </p>
            <ul style="margin:0;">
                <li>Branch: <code<?php echo $colors; ?>><?php echo $branch; ?></code></li>
            </ul>
            <p style="margin:0;">
                <a href="<?php echo $matches[1]; ?>" target="_blank" class="built-button" style="margin-top:10px;">View Repo</a>
            </p>
        </div><?php

    } else {

        // Display message. ?>
        <div class="built-panel">
            <p style="margin:0;"><strong>A Git repo is not setup.</strong> Create a Git repo to use this feature.</p>
        </div><?php

    }

} else {

    // Display message. ?>
    <div class="built-panel">
        <p style="margin:0;"><strong>A Git repo is not setup.</strong> Create a Git repo to use this feature.</p>
    </div><?php
    
} ?>