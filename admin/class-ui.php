<?php
/**
 * UI components.
 *
 * Small server-rendered building blocks for Kit's admin screens, following
 * the Kit design system: setting groups, switch rows, fields, pills,
 * environment chips, alerts and icons. Settings controls are rendered
 * straight from the settings schema.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Admin;

use BuiltMightyKit\Utility\settings;
use BuiltMightyKit\Utility\environment;

if( ! defined( 'ABSPATH' ) ) { exit; }

class ui {

    /**
     * Icon.
     */
    public static function icon( string $name, string $class = '' ): string {
        return '<svg class="bmk-icon ' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"><use href="#bmk-i-' . esc_attr( $name ) . '"/></svg>';
    }

    /**
     * Environment chip.
     */
    public static function env_chip( string $type = '', string $class = '' ): string {
        $type   = $type ?: environment::type();
        $labels = [ 'production' => 'Production', 'staging' => 'Staging', 'development' => 'Development', 'local' => 'Local' ];
        return '<span class="bmk-env-chip ' . esc_attr( $class ) . '" data-env="' . esc_attr( $type ) . '"><span>' . esc_html( $labels[ $type ] ?? ucfirst( $type ) ) . '</span></span>';
    }

    /**
     * Status pill.
     *
     * @param string $tone ok | warn | danger | info | off
     */
    public static function pill( string $text, string $tone = 'off', bool $dot = true ): string {
        return '<span class="bmk-pill bmk-pill-' . esc_attr( $tone ) . ( $dot ? '' : ' bmk-nodot' ) . '">' . esc_html( $text ) . '</span>';
    }

    /**
     * Alert.
     *
     * @param string $tone info | warn | danger | ok
     * @param string $actions Pre-escaped HTML for buttons.
     */
    public static function alert( string $tone, string $title, string $body = '', string $actions = '' ): string {
        $icons = [ 'info' => 'info', 'warn' => 'alert', 'danger' => 'alert', 'ok' => 'checkc' ];
        $html  = '<div class="bmk-alert bmk-alert-' . esc_attr( $tone ) . '" role="' . ( $tone === 'danger' ? 'alert' : 'status' ) . '">';
        $html .= self::icon( $icons[ $tone ] ?? 'info' );
        $html .= '<div><b>' . esc_html( $title ) . '</b>';
        if( $body !== '' ) $html .= '<p>' . wp_kses_post( $body ) . '</p>';
        if( $actions !== '' ) $html .= '<div class="bmk-actions">' . $actions . '</div>';
        $html .= '</div></div>';
        return $html;
    }

    /**
     * A settings group rendered from the schema.
     *
     * @param string $page   Page slug.
     * @param string $group  Group slug.
     * @param string $title  Group title.
     * @param string $desc   Group description.
     * @param string $status Pre-rendered pill HTML for the header.
     * @param string $after  Extra HTML at the end of the group.
     */
    public static function group( string $page, string $group, string $title, string $desc = '', string $status = '', string $after = '' ): string {

        $fields = array_filter( settings::schema(), function( $f ) use ( $page, $group ) {
            return ( $f['page'] ?? '' ) === $page && ( $f['group'] ?? '' ) === $group;
        } );
        if( ! $fields && $after === '' ) return '';

        $html  = '<section class="bmk-group" id="bmk-group-' . esc_attr( $group ) . '">';
        $html .= '<div class="bmk-group-head"><div><h2>' . esc_html( $title ) . '</h2>';
        if( $desc !== '' ) $html .= '<p>' . esc_html( $desc ) . '</p>';
        $html .= '</div>' . $status . '</div>';

        foreach( $fields as $key => $field ) {
            // Children render inside their parent when the parent is in this group.
            if( ! empty( $field['requires'] ) && isset( $fields[ $field['requires'] ] ) ) continue;
            $html .= self::row( $key, $field, $fields );
        }

        $html .= $after;
        $html .= '</section>';
        return $html;

    }

    /**
     * One setting row (and its dependents).
     */
    public static function row( string $key, array $field, array $siblings = [] ): string {

        $id       = 'bmk-f-' . sanitize_html_class( $key );
        $children = array_filter( $siblings, function( $f ) use ( $key ) { return ( $f['requires'] ?? '' ) === $key; } );
        $locked   = settings::locked_by( $key );

        $html = '<div class="bmk-setting" id="bmk-row-' . esc_attr( $key ) . '" data-row="' . esc_attr( $key ) . '">';

        if( $field['type'] === 'bool' ) {
            $html .= '<div class="bmk-setting-text"><span class="bmk-setting-title" id="' . esc_attr( $id ) . '-l">' . esc_html( $field['label'] ) . '</span>';
            if( ! empty( $field['desc'] ) ) $html .= '<p class="bmk-setting-desc">' . esc_html( $field['desc'] ) . '</p>';
            if( $locked ) $html .= '<p class="bmk-setting-desc bmk-locked">' . self::icon( 'lock', 'bmk-sm' ) . ' Set by <code>' . esc_html( $locked ) . '</code> in wp-config.php.</p>';
            $html .= '</div>';
            $html .= self::control( $key, $field, $id );
        } elseif( in_array( $field['type'], [ 'list', 'textarea', 'roles', 'multi' ], true ) ) {
            $html .= '<div class="bmk-setting-text bmk-span">' . self::field( $key, $field ) . '</div>';
        } else {
            $html .= '<div class="bmk-setting-text"><label class="bmk-setting-title" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
            if( ! empty( $field['desc'] ) ) $html .= '<p class="bmk-setting-desc">' . esc_html( $field['desc'] ) . '</p>';
            if( $locked ) $html .= '<p class="bmk-setting-desc bmk-locked">' . self::icon( 'lock', 'bmk-sm' ) . ' Set by <code>' . esc_html( $locked ) . '</code> in wp-config.php.</p>';
            $html .= '</div><div class="bmk-setting-control">' . self::control( $key, $field, $id ) . '</div>';
        }

        if( $children ) {
            $open = $field['type'] !== 'bool' || settings::is_on( $key );
            $html .= '<div class="bmk-setting-more" data-parent="' . esc_attr( $key ) . '"' . ( $open ? '' : ' hidden' ) . '>';
            foreach( $children as $child_key => $child ) {
                $grand = array_filter( $siblings, function( $f ) use ( $child_key ) { return ( $f['requires'] ?? '' ) === $child_key; } );
                if( $child['type'] === 'bool' || $grand ) {
                    $html .= self::row( $child_key, $child, $siblings );
                } else {
                    $html .= self::field( $child_key, $child );
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;

    }

    /**
     * Labeled field (label above control).
     */
    public static function field( string $key, array $field ): string {
        $id     = 'bmk-f-' . sanitize_html_class( $key );
        $locked = settings::locked_by( $key );
        $group  = in_array( $field['type'], [ 'roles', 'multi', 'list' ], true );
        $html   = '<div class="bmk-field" id="bmk-row-' . esc_attr( $key ) . '" data-field="' . esc_attr( $key ) . '">';
        $html  .= $group
            ? '<span class="bmk-label" id="' . esc_attr( $id ) . '-l">' . esc_html( $field['label'] ) . '</span>'
            : '<label class="bmk-label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
        $html  .= self::control( $key, $field, $id );
        if( ! empty( $field['desc'] ) ) $html .= '<span class="bmk-help">' . esc_html( $field['desc'] ) . '</span>';
        if( $locked ) $html .= '<span class="bmk-help bmk-locked">' . self::icon( 'lock', 'bmk-sm' ) . ' Set by <code>' . esc_html( $locked ) . '</code> in wp-config.php.</span>';
        $html  .= '<span class="bmk-errmsg" hidden></span>';
        $html  .= '</div>';
        return $html;
    }

    /**
     * Control for a field.
     */
    public static function control( string $key, array $field, string $id ): string {

        $value    = settings::get( $key );
        $locked   = settings::locked_by( $key ) !== '';
        $initial  = esc_attr( wp_json_encode( $value ) );
        $data     = 'data-kit-key="' . esc_attr( $key ) . '" data-type="' . esc_attr( $field['type'] ) . '" data-initial="' . $initial . '"';
        $disabled = $locked ? ' disabled' : '';

        switch( $field['type'] ) {

            case 'bool':
                return '<button type="button" class="bmk-switch" role="switch" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id ) . '-l" aria-checked="' . ( $value ? 'true' : 'false' ) . '" ' . $data . $disabled . '><span class="bmk-knob"></span></button>';

            case 'enum':
                $options = (array) ( $field['options'] ?? [] );
                if( count( $options ) <= 3 && empty( $field['select'] ) ) {
                    $html = '<div class="bmk-seg" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-l" id="' . esc_attr( $id ) . '" ' . $data . '>';
                    foreach( $options as $v => $label ) {
                        $on = (string) $v === (string) $value;
                        $html .= '<button type="button" role="radio" data-value="' . esc_attr( $v ) . '" aria-checked="' . ( $on ? 'true' : 'false' ) . '" tabindex="' . ( $on ? '0' : '-1' ) . '"' . $disabled . '>' . esc_html( $label ) . '</button>';
                    }
                    return $html . '</div>';
                }
                $html = '<select class="bmk-select" id="' . esc_attr( $id ) . '" ' . $data . $disabled . '>';
                foreach( $options as $v => $label ) {
                    $html .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $v, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
                }
                return $html . '</select>';

            case 'int':
                return '<input type="number" class="bmk-input bmk-input-num" id="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '"'
                    . ( isset( $field['min'] ) ? ' min="' . (int) $field['min'] . '"' : '' )
                    . ( isset( $field['max'] ) ? ' max="' . (int) $field['max'] . '"' : '' )
                    . ' inputmode="numeric" ' . $data . $disabled . '>';

            case 'textarea':
                return '<textarea class="bmk-textarea bmk-mono" id="' . esc_attr( $id ) . '" rows="4" ' . $data . $disabled . '>' . esc_textarea( (string) $value ) . '</textarea>';

            case 'list':
                $html = '<div class="bmk-tags" role="group" aria-labelledby="' . esc_attr( $id ) . '-l" ' . $data . '>';
                foreach( (array) $value as $item ) {
                    $html .= '<span class="bmk-tag" data-value="' . esc_attr( $item ) . '">' . esc_html( $item ) . '<button type="button" aria-label="' . esc_attr( 'Remove ' . $item ) . '">' . self::icon( 'x', 'bmk-sm' ) . '</button></span>';
                }
                $html .= '<input type="text" id="' . esc_attr( $id ) . '" placeholder="Add and press Enter" aria-label="' . esc_attr( 'Add to ' . $field['label'] ) . '"' . $disabled . '>';
                return $html . '</div>';

            case 'roles':
            case 'multi':
                $options = $field['type'] === 'roles' ? self::roles() : (array) ( $field['options'] ?? [] );
                $html = '<div class="bmk-checks" role="group" aria-labelledby="' . esc_attr( $id ) . '-l" ' . $data . '>';
                if( $field['type'] === 'roles' ) {
                    $html .= '<label class="bmk-check bmk-is-locked"><input type="checkbox" checked disabled> Administrator<span class="bmk-why">' . self::icon( 'lock', 'bmk-sm' ) . 'Always required</span></label>';
                }
                foreach( $options as $v => $label ) {
                    $html .= '<label class="bmk-check"><input type="checkbox" value="' . esc_attr( $v ) . '"' . checked( in_array( (string) $v, array_map( 'strval', (array) $value ), true ), true, false ) . $disabled . '> ' . esc_html( $label ) . '</label>';
                }
                return $html . '</div>';

            case 'url':
                return '<input type="url" class="bmk-input bmk-mono" id="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '" placeholder="https://shop.example.com" ' . $data . $disabled . '>';

            case 'email':
                return '<input type="email" class="bmk-input bmk-mono" id="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '" ' . $data . $disabled . '>';

            case 'text':
            default:
                if( $key === 'kit_login_url' ) {
                    return '<div class="bmk-affix"><span>' . esc_html( untrailingslashit( preg_replace( '#^https?://#', '', home_url() ) ) ) . '/</span><input type="text" class="bmk-input bmk-mono" id="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '" ' . $data . $disabled . ' autocomplete="off" spellcheck="false"></div>';
                }
                return '<input type="text" class="bmk-input" id="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $value ) . '" ' . $data . $disabled . '>';

        }

    }

    /**
     * Roles other than administrator.
     */
    public static function roles(): array {
        $roles = wp_roles()->get_names();
        unset( $roles['administrator'] );
        return array_map( 'translate_user_role', $roles );
    }

    /**
     * Relative time ("4 minutes ago") from a UTC MySQL datetime.
     */
    public static function ago( string $utc ): string {
        $ts = strtotime( $utc . ' UTC' );
        if( ! $ts ) return '';
        $diff = time() - $ts;
        if( $diff < 60 ) return 'Just now';
        if( $diff < DAY_IN_SECONDS ) return sprintf( '%s ago', human_time_diff( $ts ) );
        if( $diff < 2 * DAY_IN_SECONDS ) return 'Yesterday';
        return wp_date( 'M j', $ts );
    }

    /**
     * Icon sprite (once per page).
     */
    public static function sprite(): string {
        static $done = false;
        if( $done ) return '';
        $done = true;
        $p = [
            'shield'   => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
            'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
            'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'lock'     => '<rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
            'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l2 2M14 9l1.5 1.5"/>',
            'bolt'     => '<path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3Z"/>',
            'activity' => '<path d="M4 6h16M4 12h10M4 18h13"/><circle cx="19" cy="12" r="1.2"/>',
            'bell'     => '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
            'gauge'    => '<path d="M4.5 18a9 9 0 1 1 15 0"/><path d="m12 13 4-4"/><circle cx="12" cy="13" r="1.2"/>',
            'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
            'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'checkc'   => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.4 2.4 4.6-4.8"/>',
            'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
            'alert'    => '<path d="M10.3 4.2 2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17h.01"/>',
            'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
            'copy'     => '<rect x="8.5" y="8.5" width="12" height="12" rx="2.5"/><path d="M15.5 8.5V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7A2.5 2.5 0 0 0 6 15.5h2.5"/>',
            'branch'   => '<circle cx="6" cy="5.5" r="2.5"/><circle cx="6" cy="18.5" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M6 8v8M18 10.5c0 4-4 4.5-9.5 6.2"/>',
            'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
            'card'     => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
            'repeat'   => '<path d="M17 2.5 20.5 6 17 9.5"/><path d="M3.5 11.5V10a4 4 0 0 1 4-4h13M7 21.5 3.5 18 7 14.5"/><path d="M20.5 12.5V14a4 4 0 0 1-4 4h-13"/>',
            'home'     => '<path d="M3.5 10.5 12 4l8.5 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-4.5v-6h-5v6H5A1.5 1.5 0 0 1 3.5 19v-8.5Z"/>',
            'refresh'  => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.5 4.5L20 16M20 20v-4h-4"/>',
            'phone'    => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
            'ban'      => '<circle cx="12" cy="12" r="9"/><path d="m5.7 5.7 12.6 12.6"/>',
            'image'    => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path d="m20.5 16-5-5L6 19.5"/>',
            'server'   => '<rect x="3.5" y="4" width="17" height="7" rx="2"/><rect x="3.5" y="13" width="17" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
            'layers'   => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
            'link'     => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
            'download' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/>',
            'eye'      => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
            'chev'     => '<path d="m6 9 6 6 6-6"/>',
            'webhook'  => '<circle cx="12" cy="6" r="2.5"/><circle cx="6" cy="17" r="2.5"/><circle cx="18" cy="17" r="2.5"/><path d="M10.8 8.2 7.2 14.8M8.5 17h7M13.2 8.2l3.6 6.6"/>',
            'robot'    => '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9.5 16.5h5"/>',
            'heart'    => '<path d="M12 20s-7.5-4.4-7.5-10A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 7.5 3c0 5.6-7.5 10-7.5 10Z"/>',
            'fingerprint' => '<path d="M12 11v3a8 8 0 0 1-1.5 4.7M8 9.5a4 4 0 0 1 8 0V13a11 11 0 0 1-.6 3.6M5 12V9.5a7 7 0 0 1 12-4.9M19 9.5V12a14 14 0 0 1-.4 3.4M8 14.5a11 11 0 0 1-2 4"/>',
        ];
        $out = '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
        foreach( $p as $id => $paths ) $out .= '<symbol id="bmk-i-' . $id . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
        return $out . '</defs></svg>';
    }

}
