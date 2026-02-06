<?php
/**
 * Security Headers.
 *
 * Adds HTTP security headers to protect against common attacks.
 *
 * @package Built Mighty Kit
 * @since   5.0.0
 */
namespace BuiltMightyKit\Public;

class security_headers {

    /**
     * Construct.
     *
     * @since   5.0.0
     */
    public function __construct() {

        // Check if enabled.
        if ( get_option( 'kit_security_headers' ) !== 'enable' ) {
            return;
        }

        // Add security headers.
        add_action( 'send_headers', [ $this, 'send_security_headers' ] );

        // Add headers via PHP for cases where send_headers doesn't work.
        add_action( 'init', [ $this, 'send_security_headers_fallback' ], 1 );

    }

    /**
     * Send security headers.
     *
     * @since   5.0.0
     */
    public function send_security_headers() {

        // Don't send headers if they're already sent.
        if ( headers_sent() ) {
            return;
        }

        // X-Frame-Options - Prevent clickjacking.
        $x_frame = get_option( 'kit_header_x_frame', 'SAMEORIGIN' );
        if ( $x_frame && $x_frame !== 'disable' ) {
            header( 'X-Frame-Options: ' . $x_frame );
        }

        // X-Content-Type-Options - Prevent MIME sniffing.
        if ( get_option( 'kit_header_x_content_type', 'enable' ) === 'enable' ) {
            header( 'X-Content-Type-Options: nosniff' );
        }

        // Referrer-Policy - Control referrer information.
        $referrer_policy = get_option( 'kit_header_referrer_policy', 'strict-origin-when-cross-origin' );
        if ( $referrer_policy && $referrer_policy !== 'disable' ) {
            header( 'Referrer-Policy: ' . $referrer_policy );
        }

        // Permissions-Policy - Restrict browser features.
        if ( get_option( 'kit_header_permissions_policy', 'enable' ) === 'enable' ) {
            $permissions = $this->get_permissions_policy();
            if ( $permissions ) {
                header( 'Permissions-Policy: ' . $permissions );
            }
        }

        // Content-Security-Policy - Prevent XSS and injection attacks.
        if ( get_option( 'kit_header_csp', 'disable' ) === 'enable' ) {
            $csp = $this->get_content_security_policy();
            if ( $csp ) {
                // Use Report-Only mode if configured.
                $header_name = get_option( 'kit_header_csp_report_only', 'disable' ) === 'enable'
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy';
                header( $header_name . ': ' . $csp );
            }
        }

        // X-XSS-Protection - Legacy XSS protection (for older browsers).
        if ( get_option( 'kit_header_x_xss', 'enable' ) === 'enable' ) {
            header( 'X-XSS-Protection: 1; mode=block' );
        }

        // Strict-Transport-Security (HSTS) - Force HTTPS.
        if ( is_ssl() && get_option( 'kit_header_hsts', 'disable' ) === 'enable' ) {
            $max_age = get_option( 'kit_header_hsts_max_age', '31536000' );
            $include_subdomains = get_option( 'kit_header_hsts_subdomains', 'disable' ) === 'enable' ? '; includeSubDomains' : '';
            $preload = get_option( 'kit_header_hsts_preload', 'disable' ) === 'enable' ? '; preload' : '';
            header( 'Strict-Transport-Security: max-age=' . intval( $max_age ) . $include_subdomains . $preload );
        }

    }

    /**
     * Fallback for sending headers early in init.
     *
     * @since   5.0.0
     */
    public function send_security_headers_fallback() {

        // Only run on frontend and if headers not sent.
        if ( is_admin() || headers_sent() ) {
            return;
        }

        $this->send_security_headers();

    }

    /**
     * Get Permissions-Policy header value.
     *
     * @return  string  Permissions-Policy header value.
     *
     * @since   5.0.0
     */
    private function get_permissions_policy() {

        // Default restrictive policy.
        $policies = [
            'accelerometer'        => '()',
            'camera'               => '()',
            'geolocation'          => '()',
            'gyroscope'            => '()',
            'magnetometer'         => '()',
            'microphone'           => '()',
            'payment'              => '()',
            'usb'                  => '()',
            'interest-cohort'      => '()', // Disable FLoC.
        ];

        // Allow customization via filter.
        $policies = apply_filters( 'kit_permissions_policy', $policies );

        // Build policy string.
        $policy_parts = [];
        foreach ( $policies as $feature => $value ) {
            $policy_parts[] = $feature . '=' . $value;
        }

        return implode( ', ', $policy_parts );

    }

    /**
     * Get Content-Security-Policy header value.
     *
     * @return  string  CSP header value.
     *
     * @since   5.0.0
     */
    private function get_content_security_policy() {

        // Get custom CSP or use default.
        $custom_csp = get_option( 'kit_header_csp_custom', '' );
        if ( ! empty( $custom_csp ) ) {
            return sanitize_text_field( $custom_csp );
        }

        // Default CSP directives.
        $directives = [
            "default-src"  => "'self'",
            "script-src"   => "'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src"    => "'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src"      => "'self' data: https:",
            "font-src"     => "'self' https://fonts.gstatic.com",
            "connect-src"  => "'self'",
            "frame-src"    => "'self'",
            "object-src"   => "'none'",
            "base-uri"     => "'self'",
            "form-action"  => "'self'",
        ];

        // Allow customization via filter.
        $directives = apply_filters( 'kit_csp_directives', $directives );

        // Build CSP string.
        $csp_parts = [];
        foreach ( $directives as $directive => $value ) {
            $csp_parts[] = $directive . ' ' . $value;
        }

        return implode( '; ', $csp_parts );

    }

}
