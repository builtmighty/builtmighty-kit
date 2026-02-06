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

class session_management {

    /**
     * Construct.
     *
     * @since   5.0.0
     */
    public function __construct() {

        // Check if enabled.
        if ( get_option( 'kit_session_management' ) !== 'enable' ) {
            return;
        }

        // Force logout on password change.
        add_action( 'after_password_reset', [ $this, 'destroy_other_sessions_on_password_reset' ], 10, 2 );
        add_action( 'profile_update', [ $this, 'check_password_change' ], 10, 2 );

        // Limit concurrent sessions.
        add_action( 'wp_login', [ $this, 'enforce_session_limit' ], 10, 2 );

        // Session timeout for idle users.
        add_action( 'init', [ $this, 'check_session_timeout' ] );
        add_action( 'wp_login', [ $this, 'record_last_activity' ], 10, 2 );
        add_filter( 'heartbeat_received', [ $this, 'heartbeat_activity' ], 10, 2 );

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

        if ( get_option( 'kit_session_logout_on_password_change' ) !== 'enable' ) {
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

        if ( get_option( 'kit_session_logout_on_password_change' ) !== 'enable' ) {
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

        $limit = (int) get_option( 'kit_session_limit', 0 );

        if ( $limit <= 0 ) {
            return;
        }

        // Get user's sessions.
        $sessions = \WP_Session_Tokens::get_instance( $user->ID );
        $all_sessions = $sessions->get_all();

        // If we're at or over the limit, destroy oldest sessions.
        if ( count( $all_sessions ) >= $limit ) {
            // Sort by login time.
            uasort( $all_sessions, function( $a, $b ) {
                return ( $a['login'] ?? 0 ) - ( $b['login'] ?? 0 );
            } );

            // Destroy oldest sessions to make room.
            $to_destroy = count( $all_sessions ) - $limit + 1;
            $count = 0;

            foreach ( $all_sessions as $token => $session ) {
                if ( $count >= $to_destroy ) {
                    break;
                }
                $sessions->destroy( $token );
                $count++;
            }
        }

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

        $timeout = (int) get_option( 'kit_session_timeout', 0 );

        if ( $timeout <= 0 ) {
            return;
        }

        $user_id = get_current_user_id();
        $last_activity = get_user_meta( $user_id, 'kit_last_activity', true );

        if ( empty( $last_activity ) ) {
            $this->record_activity( $user_id );
            return;
        }

        // Check if session has timed out.
        $timeout_seconds = $timeout * MINUTE_IN_SECONDS;
        if ( ( time() - $last_activity ) > $timeout_seconds ) {
            // Log out the user.
            wp_logout();

            // Redirect to login page with message.
            $redirect_url = add_query_arg( 'session_expired', '1', wp_login_url() );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // Update activity timestamp.
        $this->record_activity( $user_id );

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

        if ( is_user_logged_in() ) {
            $this->record_activity( get_current_user_id() );
        }

        return $response;

    }

    /**
     * Record activity for a user.
     *
     * @param   int $user_id  User ID.
     *
     * @since   5.0.0
     */
    private function record_activity( $user_id ) {
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
        <h2><?php esc_html_e( 'Session Management', 'builtmighty-kit' ); ?></h2>
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
