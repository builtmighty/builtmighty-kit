<?php
/**
 * Environment in the admin bar and wp-admin.
 *
 * An environment chip in the admin bar (front end and wp-admin) with a menu
 * of what Kit is protecting, and a striped banner on wp-admin screens of
 * copies. Uses the admin bar's own dropdowns, so hover, focus and touch
 * behave like every other admin bar menu.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Public;

use BuiltMightyKit\Utility\environment;
use BuiltMightyKit\Utility\safeguards;

if( ! defined( 'ABSPATH' ) ) { exit; }

class admin_bar {

    /**
     * Construct.
     */
    public function __construct() {
        add_action( 'admin_bar_menu', [ $this, 'node' ], 31 );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'front_assets' ] );
        add_action( 'in_admin_header', [ $this, 'banner' ] );
    }

    /**
     * Labels.
     */
    private static function label( string $type ): string {
        return [ 'production' => 'Production', 'staging' => 'Staging', 'development' => 'Development', 'local' => 'Local' ][ $type ] ?? ucfirst( $type );
    }

    /**
     * Admin bar node.
     *
     * @param \WP_Admin_Bar $bar
     */
    public function node( $bar ) {

        if( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) return;

        $type = environment::type();
        $bar->add_node( [
            'id'    => 'bmk-env',
            'title' => '<span class="bmk-env-chip" data-env="' . esc_attr( $type ) . '"><span>' . esc_html( self::label( $type ) ) . '</span></span><span class="screen-reader-text"> environment</span>',
            'href'  => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=builtmighty' ) : false,
            'meta'  => [ 'class' => 'bmk-ab', 'title' => environment::source()['label'] ],
        ] );

        if( ! current_user_can( 'manage_options' ) ) return;

        $host = preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) );
        $live = environment::live_url();
        $bar->add_node( [
            'parent' => 'bmk-env',
            'id'     => 'bmk-env-head',
            'title'  => '<b>' . esc_html( environment::is_kit() ? sprintf( '%s copy of %s', self::label( $type ), $live ? preg_replace( '#^https?://#', '', $live ) : 'the store' ) : 'The live store: ' . $host ) . '</b><small>' . esc_html( environment::is_kit() ? 'Kit is keeping this copy away from real customers.' : 'Changes here are real.' ) . '</small>',
            'meta'   => [ 'class' => 'bmk-ab-head' ],
        ] );

        if( environment::is_kit() ) {
            foreach( safeguards::status() as $key => $s ) {
                if( $s['optional'] && ! $s['on'] ) continue;
                $bar->add_node( [
                    'parent' => 'bmk-env',
                    'id'     => 'bmk-env-' . sanitize_key( $key ),
                    'title'  => '<span class="bmk-ab-dot ' . ( $s['on'] ? 'bmk-ab-on' : 'bmk-ab-off' ) . '" aria-hidden="true"></span>' . esc_html( $s['label'] ),
                    'href'   => admin_url( 'admin.php?page=builtmighty&view=safeguards' ),
                    'meta'   => [ 'class' => 'bmk-ab-item' ],
                ] );
            }
        } else {
            $steps = safeguards::hardening();
            $done  = count( array_filter( $steps, function( $s ) { return $s['done']; } ) );
            $bar->add_node( [
                'parent' => 'bmk-env',
                'id'     => 'bmk-env-hardening',
                'title'  => '<span class="bmk-ab-dot ' . ( $done === count( $steps ) ? 'bmk-ab-on' : 'bmk-ab-off' ) . '" aria-hidden="true"></span>' . esc_html( sprintf( '%d of %d hardening steps on', $done, count( $steps ) ) ),
                'href'   => admin_url( 'admin.php?page=builtmighty' ),
                'meta'   => [ 'class' => 'bmk-ab-item' ],
            ] );
        }

        $bar->add_node( [
            'parent' => 'bmk-env',
            'id'     => 'bmk-env-open',
            'title'  => 'Open Kit',
            'href'   => admin_url( 'admin.php?page=builtmighty' ),
            'meta'   => [ 'class' => 'bmk-ab-open' ],
        ] );

    }

    /**
     * wp-admin styles and scripts (small).
     */
    public function assets() {
        wp_enqueue_style( 'bmk-wp', KIT_URI . 'public/css/kit-wp.css', [], KIT_VERSION );
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if( $screen && $screen->id === 'dashboard' ) {
            wp_enqueue_script( 'bmk-wp', KIT_URI . 'public/js/kit-wp.js', [], KIT_VERSION, true );
        }
    }

    /**
     * Front end: only the chip styles, only for people who see the admin bar.
     */
    public function front_assets() {
        if( ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) return;
        wp_enqueue_style( 'bmk-wp', KIT_URI . 'public/css/kit-wp.css', [ 'admin-bar' ], KIT_VERSION );
    }

    /**
     * Striped banner on wp-admin screens of copies (not on Kit's own screens).
     */
    public function banner() {

        if( ! environment::is_kit() ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if( $screen && $screen->id === 'toplevel_page_builtmighty' ) return;

        $type = environment::type();
        $held = [];
        foreach( safeguards::status() as $key => $s ) {
            if( ! $s['on'] ) continue;
            $short = [ 'kit_block_email' => 'email', 'kit_block_external' => 'outside services', 'kit_actionscheduler' => 'scheduled actions', 'kit_block_access' => 'visitors' ];
            if( isset( $short[ $key ] ) ) $held[] = $short[ $key ];
        }
        $extra = (array) apply_filters( 'kit_banner_held', [] );
        $held  = array_merge( $held, $extra );

        $text = $held
            ? sprintf( 'Kit is holding back %s on this copy.', wp_sprintf( '%l', $held ) )
            : 'Safeguards are off. This copy can reach real customers.';

        echo '<div class="bmk-wp bmk-banner" data-env="' . esc_attr( $type ) . '" role="status"><span class="bmk-banner-msg"><b>' . esc_html( self::label( $type ) ) . '.</b> ' . esc_html( $text ) . '</span>';
        if( current_user_can( 'manage_options' ) ) {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=builtmighty&view=safeguards' ) ) . '">See safeguards</a>';
        }
        echo '</div>';

    }

}
