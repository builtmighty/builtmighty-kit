<?php
/**
 * Spam Protection.
 *
 * Comment spam and bot protection features.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\activity;
use BuiltMightyKit\Utility\request;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class spam_protection {

    /**
     * Option name for blocked IPs.
     *
     * @var string
     */
    private const BLOCKED_IPS_OPTION = 'kit_blocked_spam_ips';

    /**
     * Honeypot field name (randomized per site).
     *
     * @var string
     */
    private $honeypot_field;

    /**
     * Construct.
     *
     * @since   5.0.0
     */
    public function __construct() {

        // Check if enabled.
        if ( ! settings::is_on( 'kit_spam_protection' ) ) {
            return;
        }

        // Generate consistent honeypot field name.
        $this->honeypot_field = 'contact_' . substr( md5( get_option( 'siteurl' ) ), 0, 8 );

        // Disable comments globally.
        if ( settings::is_on( 'kit_disable_comments' ) ) {
            $this->disable_comments();
        }

        // Add honeypot to comment form.
        if ( settings::is_on( 'kit_comment_honeypot' ) ) {
            add_action( 'comment_form_after_fields', [ $this, 'add_honeypot_field' ] );
            add_action( 'comment_form_logged_in_after', [ $this, 'add_honeypot_field' ] );
            add_filter( 'preprocess_comment', [ $this, 'check_honeypot' ] );
        }

        // Block known spam IPs.
        if ( settings::is_on( 'kit_block_spam_ips' ) ) {
            add_filter( 'preprocess_comment', [ $this, 'check_blocked_ip' ], 1 );
            add_action( 'comment_post', [ $this, 'record_spam_ip' ], 10, 2 );
        }

        // Time-based spam check.
        if ( settings::is_on( 'kit_comment_time_check' ) ) {
            add_action( 'comment_form_after_fields', [ $this, 'add_timestamp_field' ] );
            add_action( 'comment_form_logged_in_after', [ $this, 'add_timestamp_field' ] );
            add_filter( 'preprocess_comment', [ $this, 'check_timestamp' ] );
        }

        // Block comments with too many links.
        add_filter( 'preprocess_comment', [ $this, 'check_link_count' ] );

        // Disable pingbacks/trackbacks.
        if ( settings::is_on( 'kit_disable_pingbacks' ) ) {
            $this->disable_pingbacks();
        }

    }

    /**
     * Disable comments globally.
     *
     * @since   5.0.0
     */
    private function disable_comments() {

        // Close comments on front-end.
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );

        // Hide existing comments.
        add_filter( 'comments_array', '__return_empty_array', 10, 2 );

        // Remove comments page from admin menu.
        add_action( 'admin_menu', function() {
            remove_menu_page( 'edit-comments.php' );
        } );

        // Remove comments link from admin bar.
        add_action( 'wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu( 'comments' );
        } );

        // Disable comments REST API endpoint.
        add_filter( 'rest_endpoints', function( $endpoints ) {
            if ( isset( $endpoints['/wp/v2/comments'] ) ) {
                unset( $endpoints['/wp/v2/comments'] );
            }
            if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\\d]+)'] ) ) {
                unset( $endpoints['/wp/v2/comments/(?P<id>[\\d]+)'] );
            }
            return $endpoints;
        } );

        // Redirect any comment post attempts.
        add_action( 'comment_form', function() {
            echo '<style>.comment-form { display: none !important; }</style>';
        } );

    }

    /**
     * Add honeypot field to comment form.
     *
     * @since   5.0.0
     */
    public function add_honeypot_field() {

        // Hidden field that should remain empty.
        printf(
            '<p class="comment-form-%1$s" style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;">
                <label for="%1$s">%2$s</label>
                <input type="text" name="%1$s" id="%1$s" value="" tabindex="-1" autocomplete="off" />
            </p>',
            esc_attr( $this->honeypot_field ),
            esc_html__( 'Leave this field empty', 'builtmighty-kit' )
        );

    }

    /**
     * Check honeypot field.
     *
     * @param   array $commentdata  Comment data.
     *
     * @return  array  Comment data or die if spam.
     *
     * @since   5.0.0
     */
    public function check_honeypot( $commentdata ) {

        if ( ! $this->applies() ) {
            return $commentdata;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! empty( $_POST[ $this->honeypot_field ] ) ) {
            $this->block_spam( __( 'Spam detected.', 'builtmighty-kit' ) );
        }

        return $commentdata;

    }

    /**
     * Add timestamp field to comment form.
     *
     * @since   5.0.0
     */
    public function add_timestamp_field() {

        printf(
            '<input type="hidden" name="kit_comment_time" value="%s" />',
            esc_attr( time() )
        );

    }

    /**
     * Check timestamp to prevent fast submissions.
     *
     * @param   array $commentdata  Comment data.
     *
     * @return  array  Comment data or die if spam.
     *
     * @since   5.0.0
     */
    public function check_timestamp( $commentdata ) {

        if ( ! $this->applies() ) {
            return $commentdata;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $timestamp = isset( $_POST['kit_comment_time'] ) ? intval( $_POST['kit_comment_time'] ) : 0;

        if ( $timestamp === 0 ) {
            $this->block_spam( __( 'Invalid form submission.', 'builtmighty-kit' ) );
        }

        // Minimum time to fill out form (3 seconds).
        $min_time = (int) settings::get( 'kit_comment_min_time' );

        if ( ( time() - $timestamp ) < $min_time ) {
            $this->block_spam( __( 'Please slow down. Form submitted too quickly.', 'builtmighty-kit' ) );
        }

        return $commentdata;

    }

    /**
     * Check for blocked IP.
     *
     * @param   array $commentdata  Comment data.
     *
     * @return  array  Comment data or die if blocked.
     *
     * @since   5.0.0
     */
    public function check_blocked_ip( $commentdata ) {

        if ( ! $this->applies() ) {
            return $commentdata;
        }

        $ip = request::client_ip();
        $blocked_ips = get_option( self::BLOCKED_IPS_OPTION, [] );

        if ( ! is_array( $blocked_ips ) ) {
            $blocked_ips = [];
        }

        if ( ! empty( $blocked_ips[ $ip ]['blocked'] ) ) {
            $this->block_spam( __( 'Your IP has been blocked due to spam.', 'builtmighty-kit' ) );
        }

        return $commentdata;

    }

    /**
     * Record IP when comment marked as spam.
     *
     * @param   int        $comment_id       Comment ID.
     * @param   int|string $comment_approved Approval status.
     *
     * @since   5.0.0
     */
    public function record_spam_ip( $comment_id, $comment_approved ) {

        // Only record for spam comments.
        if ( $comment_approved !== 'spam' ) {
            return;
        }

        $ip = request::client_ip();
        if ( $ip === '' ) {
            return;
        }
        $blocked_ips = get_option( self::BLOCKED_IPS_OPTION, [] );

        if ( ! is_array( $blocked_ips ) ) {
            $blocked_ips = [];
        }

        // Increment spam count for this IP.
        if ( ! isset( $blocked_ips[ $ip ] ) ) {
            $blocked_ips[ $ip ] = [
                'count'      => 0,
                'first_seen' => time(),
            ];
        }

        $blocked_ips[ $ip ]['count']++;
        $blocked_ips[ $ip ]['last_seen'] = time();

        // Block after threshold (default 3 spam comments).
        $threshold = (int) settings::get( 'kit_spam_block_threshold' );
        if ( $blocked_ips[ $ip ]['count'] >= $threshold && empty( $blocked_ips[ $ip ]['blocked'] ) ) {
            $blocked_ips[ $ip ]['blocked'] = true;
            activity::log( 'spam', 'ip_blocked', 'Blocked ' . $ip . ' after ' . $blocked_ips[ $ip ]['count'] . ' spam comments', [ 'level' => 'warning', 'user_id' => 0, 'ip' => $ip ] );
        }

        // Clean up old entries (older than 30 days).
        $cutoff = time() - ( 30 * DAY_IN_SECONDS );
        foreach ( $blocked_ips as $blocked_ip => $data ) {
            // Blocks an administrator added by hand stay until they're removed by hand.
            if ( empty( $data['manual'] ) && isset( $data['last_seen'] ) && $data['last_seen'] < $cutoff ) {
                unset( $blocked_ips[ $blocked_ip ] );
            }
        }

        update_option( self::BLOCKED_IPS_OPTION, $blocked_ips, false );

    }

    /**
     * Check link count in comment.
     *
     * @param   array $commentdata  Comment data.
     *
     * @return  array  Comment data or die if too many links.
     *
     * @since   5.0.0
     */
    public function check_link_count( $commentdata ) {

        if ( ! $this->applies() ) {
            return $commentdata;
        }

        $max_links = (int) settings::get( 'kit_comment_max_links' );

        if ( $max_links <= 0 ) {
            return $commentdata;
        }

        $content = $commentdata['comment_content'];

        // Count distinct URLs (an <a href="https://..."> is one link, not two).
        preg_match_all( '#(?:https?:)?//[^\s"\'<>]+|www\.[^\s"\'<>]+#i', $content, $matches );
        $link_count = count( array_unique( array_map( 'strtolower', $matches[0] ) ) );

        if ( $link_count > $max_links ) {
            $this->block_spam(
                sprintf(
                    /* translators: %d: Maximum number of links allowed */
                    __( 'Too many links. Maximum %d allowed.', 'builtmighty-kit' ),
                    $max_links
                )
            );
        }

        return $commentdata;

    }

    /**
     * Disable pingbacks and trackbacks.
     *
     * @since   5.0.0
     */
    private function disable_pingbacks() {

        // Disable pingback XMLRPC method.
        add_filter( 'xmlrpc_methods', function( $methods ) {
            unset( $methods['pingback.ping'] );
            unset( $methods['pingback.extensions.getPingbacks'] );
            return $methods;
        } );

        // Remove X-Pingback header.
        add_filter( 'wp_headers', function( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        } );

        // Disable self-pingbacks.
        add_action( 'pre_ping', function( &$links ) {
            $home = get_option( 'home' );
            foreach ( $links as $l => $link ) {
                if ( strpos( $link, $home ) === 0 ) {
                    unset( $links[ $l ] );
                }
            }
        } );

    }

    /**
     * Should spam checks run for this comment?
     *
     * Signed-in people, replies from wp-admin and comments created through
     * the REST API are trusted.
     *
     * @return  bool
     */
    private function applies() {
        if ( is_user_logged_in() ) {
            return false;
        }
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }
        return true;
    }

    /**
     * Block spam submission.
     *
     * @param   string $message  Error message.
     *
     * @since   5.0.0
     */
    private function block_spam( $message ) {
        activity::log( 'spam', 'comment_blocked', 'Blocked a comment: ' . $message, [ 'level' => 'info', 'user_id' => 0 ] );
        wp_die(
            esc_html( $message ),
            esc_html__( 'Comment Blocked', 'builtmighty-kit' ),
            [ 'response' => 403, 'back_link' => true ]
        );
    }

    /**
     * Get blocked IPs.
     *
     * @return  array  Blocked IPs.
     *
     * @since   5.0.0
     */
    public static function get_blocked_ips() {

        $blocked_ips = get_option( self::BLOCKED_IPS_OPTION, [] );

        if ( ! is_array( $blocked_ips ) ) {
            return [];
        }

        return array_filter( $blocked_ips, function( $data ) {
            return isset( $data['blocked'] ) && $data['blocked'];
        } );

    }

    /**
     * Unblock an IP.
     *
     * @param   string $ip  IP address to unblock.
     *
     * @since   5.0.0
     */
    public static function unblock_ip( $ip ) {

        $blocked_ips = get_option( self::BLOCKED_IPS_OPTION, [] );

        if ( isset( $blocked_ips[ $ip ] ) ) {
            unset( $blocked_ips[ $ip ] );
            update_option( self::BLOCKED_IPS_OPTION, $blocked_ips, false );
        }

    }

    /**
     * Manually block an IP.
     *
     * @param   string $ip  IP address to block.
     *
     * @since   5.0.0
     */
    public static function block_ip( $ip ) {

        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        $blocked_ips = get_option( self::BLOCKED_IPS_OPTION, [] );

        if ( ! is_array( $blocked_ips ) ) {
            $blocked_ips = [];
        }

        $blocked_ips[ $ip ] = [
            'count'      => 999,
            'first_seen' => time(),
            'last_seen'  => time(),
            'blocked'    => true,
            'manual'     => true,
        ];

        update_option( self::BLOCKED_IPS_OPTION, $blocked_ips, false );

        return true;

    }


}
