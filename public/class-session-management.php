<?php
/**
 * Session Management.
 *
 * Provides enhanced session security features.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class session_management {

    /**
     * Construct.
     *
     * @since   5.0.0
     */
    public function __construct() {

        // Check if enabled.
        if ( ! settings::is_on( 'kit_session_management' ) ) {
            return;
        }

        // Explain an idle sign-out on the login screen.
        add_filter( 'login_message', [ $this, 'expired_message' ] );

        // Force logout on password change.
        add_action( 'after_password_reset', [ $this, 'destroy_other_sessions_on_password_reset' ], 10, 2 );
        add_action( 'profile_update', [ $this, 'check_password_change' ], 10, 2 );

        // Limit concurrent sessions.
        add_action( 'wp_login', [ $this, 'enforce_session_limit' ], 10, 2 );

        // Session timeout for idle users.
        add_action( 'init', [ $this, 'check_session_timeout' ] );
        add_action( 'wp_login', [ $this, 'record_last_activity' ], 10, 2 );

        // Add "Log out everywhere" to profile.
        add_action( 'show_user_profile', [ $this, 'show_logout_everywhere_button' ] );
        add_action( 'personal_options_update', [ $this, 'handle_logout_everywhere' ] );

        // Add session info to admin bar.
        add_action( 'admin_bar_menu', [ $this, 'add_session_info_to_admin_bar' ], 999 );

    }

    /**
     * Destroy other sessions after password reset.
     *
     * @param   \WP_User $user      User object.
     * @param   string   $new_pass  New password.
     *
     * @since   5.0.0
     */
    public function destroy_other_sessions_on_password_reset( $user, $new_pass ) {

        if ( ! settings::is_on( 'kit_session_logout_on_password_change' ) ) {
            return;
        }

        // Destroy all sessions for this user.
        $sessions = \WP_Session_Tokens::get_instance( $user->ID );
        $sessions->destroy_all();

    }

    /**
     * Check if password was changed during profile update.
     *
     * @param   int   $user_id       User ID.
     * @param   array $old_user_data Old user data.
     *
     * @since   5.0.0
     */
    public function check_password_change( $user_id, $old_user_data ) {

        if ( ! settings::is_on( 'kit_session_logout_on_password_change' ) ) {
            return;
        }

        // Check if password field was submitted.
        if ( empty( $_POST['pass1'] ) ) {
            return;
        }

        // Get user's session tokens.
        $sessions = \WP_Session_Tokens::get_instance( $user_id );

        // Get current session token.
        $current_token = wp_get_session_token();

        // Destroy all other sessions.
        $sessions->destroy_others( $current_token );

    }

    /**
     * Enforce session limit on login.
     *
     * @param   string   $user_login  Username.
     * @param   \WP_User $user        User object.
     *
     * @since   5.0.0
     */
    public function enforce_session_limit( $user_login, $user ) {

        $limit = (int) settings::get( 'kit_session_limit' );

        if ( $limit <= 0 ) {
            return;
        }

        // Only the default token manager stores sessions where we can trim them.
        $manager = \WP_Session_Tokens::get_instance( $user->ID );
        if ( ! $manager instanceof \WP_User_Meta_Session_Tokens ) {
            return;
        }

        // Keyed by verifier. The new session already exists when wp_login fires.
        $stored = get_user_meta( $user->ID, 'session_tokens', true );
        if ( ! is_array( $stored ) || count( $stored ) <= $limit ) {
            return;
        }

        // Keep the newest sessions.
        uasort( $stored, function( $a, $b ) {
            return ( $b['login'] ?? 0 ) <=> ( $a['login'] ?? 0 );
        } );
        update_user_meta( $user->ID, 'session_tokens', array_slice( $stored, 0, $limit, true ) );

    }

    /**
     * Check session timeout for idle users.
     *
     * @since   5.0.0
     */
    public function check_session_timeout() {

        // Only check for logged-in users.
        if ( ! is_user_logged_in() ) {
            return;
        }

        $timeout = (int) settings::get( 'kit_session_timeout' );

        if ( $timeout <= 0 ) {
            return;
        }

        $user_id = get_current_user_id();
        $last_activity = (int) get_user_meta( $user_id, 'kit_last_activity', true );

        if ( empty( $last_activity ) ) {
            $this->record_activity( $user_id );
            return;
        }

        // Check if session has timed out.
        if ( ( time() - $last_activity ) > $timeout * MINUTE_IN_SECONDS ) {

            wp_logout();

            // AJAX and REST clients get a 401 they can handle, not a redirect.
            $is_rest = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
                || ! empty( $_GET['rest_route'] )
                || strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/' . rest_get_url_prefix() . '/' ) !== false;
            if ( wp_doing_ajax() || $is_rest ) {
                wp_send_json( [ 'code' => 'kit_session_expired', 'message' => 'Signed out after inactivity.' ], 401 );
            }

            wp_safe_redirect( add_query_arg( 'session_expired', '1', wp_login_url() ) );
            exit;
        }

        // Heartbeat ticks keep an open tab alive; they aren't activity.
        if ( wp_doing_ajax() && isset( $_POST['action'] ) && $_POST['action'] === 'heartbeat' ) {
            return;
        }

        // Update activity timestamp (at most once a minute).
        $this->record_activity( $user_id, $last_activity );

    }

    /**
     * Record last activity on login.
     *
     * @param   string   $user_login  Username.
     * @param   \WP_User $user        User object.
     *
     * @since   5.0.0
     */
    public function record_last_activity( $user_login, $user ) {
        $this->record_activity( $user->ID );
    }

    /**
     * Update activity on heartbeat.
     *
     * @param   array $response  Heartbeat response.
     * @param   array $data      Heartbeat data.
     *
     * @return  array  Modified response.
     *
     * @since   5.0.0
     */
    public function heartbeat_activity( $response, $data ) {
        return $response;
    }

    /**
     * Login screen message after an idle sign-out.
     *
     * @param   string $message
     * @return  string
     */
    public function expired_message( $message ) {
        if ( empty( $_GET['session_expired'] ) ) {
            return $message;
        }
        $minutes = (int) settings::get( 'kit_session_timeout' );
        if ( $minutes >= 60 ) {
            $hours = (int) ( $minutes / 60 );
            $label = sprintf( _n( '%d hour', '%d hours', $hours, 'builtmighty-kit' ), $hours );
        } else {
            $label = sprintf( _n( '%d minute', '%d minutes', $minutes, 'builtmighty-kit' ), $minutes );
        }
        return $message . '<p class="message">' . esc_html( sprintf( 'You were signed out after %s without activity. Sign in again to continue.', $label ) ) . '</p>';
    }

    /**
     * Record activity for a user.
     *
     * @param   int $user_id  User ID.
     *
     * @since   5.0.0
     */
    private function record_activity( $user_id, $last = 0 ) {
        if ( $last && ( time() - (int) $last ) < MINUTE_IN_SECONDS ) {
            return;
        }
        update_user_meta( $user_id, 'kit_last_activity', time() );
    }

    /**
     * Show logout everywhere button on user profile.
     *
     * @param   \WP_User $user  User object.
     *
     * @since   5.0.0
     */
    public function show_logout_everywhere_button( $user ) {

        // Get session count.
        $sessions = \WP_Session_Tokens::get_instance( $user->ID );
        $all_sessions = $sessions->get_all();
        $count = count( $all_sessions );

        ?>
        <h2 id="kit-session-management"><?php esc_html_e( 'Sign-in sessions', 'builtmighty-kit' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Active Sessions', 'builtmighty-kit' ); ?></th>
                <td>
                    <p>
                        <?php
                        printf(
                            /* translators: %d: Number of active sessions */
                            esc_html( _n(
                                'You have %d active session.',
                                'You have %d active sessions.',
                                $count,
                                'builtmighty-kit'
                            ) ),
                            $count
                        );
                        ?>
                    </p>
                    <?php if ( $count > 1 ) : ?>
                        <p>
                            <label>
                                <input type="checkbox" name="kit_logout_everywhere" value="1" />
                                <?php esc_html_e( 'Log out of all other sessions', 'builtmighty-kit' ); ?>
                            </label>
                        </p>
                        <p class="description">
                            <?php esc_html_e( 'This will log you out of all devices and browsers except this one.', 'builtmighty-kit' ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php

    }

    /**
     * Handle logout everywhere form submission.
     *
     * @param   int $user_id  User ID.
     *
     * @since   5.0.0
     */
    public function handle_logout_everywhere( $user_id ) {

        if ( empty( $_POST['kit_logout_everywhere'] ) ) {
            return;
        }

        // Verify it's the current user.
        if ( $user_id !== get_current_user_id() ) {
            return;
        }

        // Get current session token.
        $current_token = wp_get_session_token();

        // Destroy all other sessions.
        $sessions = \WP_Session_Tokens::get_instance( $user_id );
        $sessions->destroy_others( $current_token );

    }

    /**
     * Add session info to admin bar.
     *
     * @param   \WP_Admin_Bar $wp_admin_bar  Admin bar object.
     *
     * @since   5.0.0
     */
    public function add_session_info_to_admin_bar( $wp_admin_bar ) {

        if ( ! is_user_logged_in() || ! is_admin_bar_showing() ) {
            return;
        }

        $user_id = get_current_user_id();
        $sessions = \WP_Session_Tokens::get_instance( $user_id );
        $all_sessions = $sessions->get_all();
        $count = count( $all_sessions );

        if ( $count <= 1 ) {
            return;
        }

        $wp_admin_bar->add_node( [
            'id'     => 'kit-sessions',
            'parent' => 'my-account',
            'title'  => sprintf(
                /* translators: %d: Number of active sessions */
                esc_html__( '%d Active Sessions', 'builtmighty-kit' ),
                $count
            ),
            'href'   => admin_url( 'profile.php#kit-session-management' ),
        ] );

    }

    /**
     * Get active sessions for a user.
     *
     * @param   int $user_id  User ID.
     *
     * @return  array  Session data.
     *
     * @since   5.0.0
     */
    public static function get_sessions( $user_id ) {

        $sessions = \WP_Session_Tokens::get_instance( $user_id );
        $all_sessions = $sessions->get_all();

        $session_data = [];
        foreach ( $all_sessions as $token => $session ) {
            $session_data[] = [
                'token'      => substr( $token, 0, 8 ) . '...',
                'login'      => isset( $session['login'] ) ? date( 'Y-m-d H:i:s', $session['login'] ) : 'Unknown',
                'expiration' => isset( $session['expiration'] ) ? date( 'Y-m-d H:i:s', $session['expiration'] ) : 'Unknown',
                'ip'         => $session['ip'] ?? 'Unknown',
                'ua'         => $session['ua'] ?? 'Unknown',
            ];
        }

        return $session_data;

    }

    /**
     * Destroy all sessions for a user.
     *
     * @param   int  $user_id        User ID.
     * @param   bool $except_current Whether to keep current session.
     *
     * @since   5.0.0
     */
    public static function destroy_all_sessions( $user_id, $except_current = false ) {

        $sessions = \WP_Session_Tokens::get_instance( $user_id );

        if ( $except_current && $user_id === get_current_user_id() ) {
            $current_token = wp_get_session_token();
            $sessions->destroy_others( $current_token );
        } else {
            $sessions->destroy_all();
        }

    }

}
