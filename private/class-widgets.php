<?php
/**
 * Dashboard widget.
 *
 * One "Built Mighty" widget inside WordPress's own postbox. The Built Mighty
 * team sees the developer view (stack, repository, sign-ins, systems);
 * everyone else sees a welcome and, when Slack is connected, a form to
 * message the team.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 6.0.0
 */
namespace BuiltMightyKit\Private;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\environment;
use BuiltMightyKit\Utility\safeguards;
use BuiltMightyKit\Admin\ui;
use BuiltMightyKit\Admin\app;
use function BuiltMightyKit\is_builtmighty_user;

if( ! defined( 'ABSPATH' ) ) { exit; }

class widgets {

    /**
     * Construct.
     */
    public function __construct() {
        add_filter( 'admin_footer_text', [ $this, 'footer' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard' ] );
        add_action( 'admin_post_kit_slack_message', [ $this, 'send_slack_message' ] );
    }

    /**
     * Footer credit.
     */
    public function footer( $text ) {
        return 'Built and looked after by <a href="https://builtmighty.com" target="_blank" rel="noopener">Built Mighty</a>.';
    }

    /**
     * Register the widget.
     */
    public function dashboard() {
        // Staff only: subscribers and customers never see the widget or its Slack form.
        if( ! current_user_can( 'edit_posts' ) ) return;
        wp_add_dashboard_widget( 'builtmighty_dashboard_widget', 'Built Mighty', [ $this, 'render' ] );
    }

    /**
     * Render.
     */
    public function render() {

        echo ui::sprite(); // phpcs:ignore
        echo '<div class="bmk-wp bmk-widget" data-env="' . esc_attr( environment::type() ) . '">';

        if( is_builtmighty_user() ) {
            $this->developer();
        } else {
            $this->client();
        }

        echo '</div>';

    }

    /**
     * Developer view.
     */
    private function developer() {

        global $wpdb;

        echo '<div class="bmk-wh"><span class="bmk-mark" aria-hidden="true"></span><b>Developer</b>' . ui::env_chip() . '</div>'; // phpcs:ignore

        // Stack.
        $wc_latest = get_transient( plugins::OPTION_KEY . 'wc_version' );
        $wc_old    = defined( 'WC_VERSION' ) && $wc_latest && version_compare( WC_VERSION, (string) $wc_latest, '<' );
        echo '<div class="bmk-wsec"><h3>Stack</h3><div class="bmk-vers">';
        $maria = stripos( $wpdb->db_server_info(), 'maria' ) !== false;
        echo '<div><small class="bmk-v-php">PHP</small><b>' . esc_html( PHP_VERSION ) . '</b></div>';
        echo '<div><small class="' . ( $maria ? 'bmk-v-mariadb' : 'bmk-v-mysql' ) . '">' . ( $maria ? 'MariaDB' : 'MySQL' ) . '</small><b>' . esc_html( $wpdb->db_version() ) . '</b></div>';
        echo '<div><small class="bmk-v-wp">WordPress</small><b>' . esc_html( get_bloginfo( 'version' ) ) . '</b></div>';
        if( defined( 'WC_VERSION' ) ) {
            echo '<div' . ( $wc_old ? ' class="bmk-warn" title="' . esc_attr( 'WooCommerce ' . $wc_latest . ' is available' ) . '"' : '' ) . '><small class="bmk-v-woo">WooCommerce</small><b>' . esc_html( WC_VERSION ) . ( $wc_old ? ' <span class="bmk-v-note">' . esc_html( $wc_latest . ' available' ) . '</span>' : '' ) . '</b></div>';
        }
        echo '</div></div>';

        // Repository.
        $git = safeguards::git();
        if( ! empty( $git['branch'] ) || ! empty( $git['commit'] ) ) {
            echo '<div class="bmk-wsec"><h3>Repository</h3><div class="bmk-branch">' . ui::icon( 'branch', 'bmk-sm' ); // phpcs:ignore
            if( ! empty( $git['repo'] ) ) echo '<code>' . esc_html( $git['repo'] ) . '</code> on ';
            echo '<code>' . esc_html( $git['branch'] ?: 'detached' ) . '</code>';
            if( ! empty( $git['commit'] ) ) echo '<span class="bmk-muted">' . esc_html( $git['commit'] ) . '</span>';
            echo '</div></div>';
        }

        // Sign-ins.
        if( settings::is_on( 'kit_login_logging' ) ) {
            $stats = \BuiltMightyKit\Public\login_logging::get_stats( 7 );
            echo '<div class="bmk-wsec"><h3>Sign-ins this week</h3><div class="bmk-branch">';
            echo ui::pill( sprintf( '%d signed in', $stats['success'] ), 'ok' ) . ui::pill( sprintf( '%d failed', $stats['failed'] ), $stats['failed'] ? 'danger' : 'off' ) . ui::pill( sprintf( _n( '%d IP address', '%d IP addresses', $stats['unique_ips'], 'builtmighty-kit' ), $stats['unique_ips'] ), 'off' ); // phpcs:ignore
            echo '</div></div>';
        }

        // Systems.
        echo '<div class="bmk-wsec"><h3>Systems</h3><div class="bmk-sysgrid">';
        foreach( safeguards::status() as $key => $s ) {
            $labels = [
                'kit_block_email'     => [ 'Email', 'Redirected', 'Sending' ],
                'kit_block_external'  => [ 'Outside requests', 'Blocked', 'Allowed' ],
                'kit_actionscheduler' => [ 'Scheduled actions', 'Paused', 'Running' ],
                'kit_block_access'    => [ 'Visitors', 'Kept out', 'Allowed' ],
                'kit_test_payments'   => [ 'Payments', 'Test mode', 'Live' ],
                'kit_hold_renewals'   => [ 'Renewals', 'Held', 'Charging' ],
                'kit_pause_webhooks'  => [ 'Webhooks', 'Paused', 'Sending' ],
                'kit_noindex'         => [ 'Search engines', 'Hidden', 'Indexing' ],
            ];
            $label = $labels[ $key ] ?? [ $s['label'], 'On', 'Off' ];
            $tone  = $s['on'] ? 'ok' : ( environment::is_kit() && ! $s['optional'] ? 'warn' : 'off' );
            echo '<div><span>' . esc_html( $label[0] ) . '</span>' . ui::pill( $s['on'] ? $label[1] : $label[2], $tone ) . '</div>'; // phpcs:ignore
        }
        echo '<div><span>Two-factor</span>' . ui::pill( settings::is_on( 'kit_enable_2fa' ) ? 'Required' : 'Off', settings::is_on( 'kit_enable_2fa' ) ? 'ok' : 'off' ) . '</div>'; // phpcs:ignore
        $slack = get_option( 'built_slack_token' ) && settings::get( 'slack-channel' );
        echo '<div><span>Slack</span>' . ui::pill( $slack ? 'Connected' : 'Not connected', $slack ? 'ok' : 'off' ) . '</div>'; // phpcs:ignore
        echo '</div></div>';

        if( current_user_can( 'manage_options' ) ) {
            echo '<div class="bmk-wsec bmk-wfoot"><a class="bmk-btn bmk-btn-secondary bmk-btn-sm" href="' . esc_url( app::url() ) . '">Open Kit</a></div>';
        }

    }

    /**
     * Client view.
     */
    private function client() {

        $user = wp_get_current_user();
        echo '<div class="bmk-wh"><span class="bmk-mark" aria-hidden="true"></span><b>' . esc_html( sprintf( 'Hi %s', $user->first_name ?: $user->display_name ) ) . '</b></div>';
        echo '<div class="bmk-wsec"><p>Built Mighty looks after this store. Questions, bugs and ideas go straight to your team.</p></div>';

        if( isset( $_GET['kit_sent'] ) ) {
            echo '<div class="bmk-wsec">' . ui::alert( 'ok', 'Message sent', 'The Built Mighty team will reply in Slack.' ) . '</div>'; // phpcs:ignore
        }

        if( ! get_option( 'built_slack_token' ) || ! settings::get( 'slack-channel' ) ) {
            echo '<div class="bmk-wsec"><p class="bmk-muted">Email <a href="mailto:support@builtmighty.com">support@builtmighty.com</a> for help.</p></div>';
            return;
        }

        echo '<form class="bmk-wsec bmk-wform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-bmk-slack-form>';
        echo '<input type="hidden" name="action" value="kit_slack_message">';
        wp_nonce_field( 'kit_slack_message' );
        echo '<label class="bmk-label" for="bmk-slack-msg">What do you need?</label>';
        echo '<textarea class="bmk-textarea" id="bmk-slack-msg" name="slack_message" rows="4" required placeholder="The shipping rate on the cart page looks wrong for Canada"></textarea>';
        echo '<div class="bmk-drop" tabindex="0" data-bmk-paste>' . ui::icon( 'image' ) . '<span>Paste a screenshot here (Ctrl V)</span></div>'; // phpcs:ignore
        echo '<input type="hidden" name="slack_screenshot" value="">';
        echo '<div class="bmk-wrow"><span class="bmk-help">Posts to your team\'s Slack channel.</span><button type="submit" class="bmk-btn bmk-btn-primary bmk-btn-sm">Send to Built Mighty</button></div>';
        echo '</form>';

    }

    /**
     * Send the client's message to Slack.
     *
     * Redirects first, then talks to Slack, so the page never waits.
     */
    public function send_slack_message() {

        if( ! current_user_can( 'edit_posts' ) ) wp_die( 'This account can\'t send messages to Built Mighty.', 403 );
        check_admin_referer( 'kit_slack_message' );

        $user       = wp_get_current_user();
        $message    = sanitize_textarea_field( wp_unslash( $_POST['slack_message'] ?? '' ) );
        $screenshot = (string) ( $_POST['slack_screenshot'] ?? '' );

        // 5 MB image limit (base64 is about 4/3 larger).
        if( strlen( $screenshot ) > 7 * MB_IN_BYTES || ! preg_match( '/^[A-Za-z0-9+\/=]*$/', $screenshot ) ) $screenshot = '';

        wp_safe_redirect( add_query_arg( 'kit_sent', '1', admin_url( 'index.php' ) ) );

        if( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        }

        if( $message === '' && $screenshot === '' ) exit;

        $slack = new \BuiltMightyKit\Utility\slack();
        if( $message !== '' ) {
            $slack->message( sprintf( "💬 Message from %s (%s) on %s\n\n%s", $user->display_name, $user->user_email, home_url(), $message ) );
        }
        if( $screenshot !== '' ) {
            $slack->image( $screenshot );
        }

        exit;

    }

}
