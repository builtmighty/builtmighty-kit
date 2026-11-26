<?php
/**
 * Settings.
 *
 * One schema and one autoloaded option (kit_settings) for every Kit setting.
 *
 * Legacy code reads options like get_option( 'kit_block_email' ) and expects
 * the old string values ('enable' / 'disable'). A pre_option shim answers those
 * reads from kit_settings, and a pre_update_option shim routes legacy writes
 * into it, so old and new code agree and no extra database queries happen.
 *
 * In every legacy radio, 'enable' meant "do the thing" (block, disable,
 * protect), so a switch that is on maps to 'enable'.
 *
 * @package Built Mighty Kit
 * @since   6.0.0
 */
namespace BuiltMightyKit\Utility;

if( ! defined( 'ABSPATH' ) ) { exit; }

class settings {

    const OPTION     = 'kit_settings';
    const DB_VERSION = 'kit_db_version';
    const VERSION    = '6.0.0';

    /** @var array|null */
    private static $schema = null;

    /** @var array|null */
    private static $values = null;

    /** @var bool */
    private static $booted = false;

    /** @var bool Suppress the update shim while we write. */
    private static $writing = false;

    /**
     * Boot: register shims and run migrations.
     */
    public static function boot(): void {

        if( self::$booted ) return;
        self::$booted = true;

        foreach( self::schema() as $key => $field ) {
            if( empty( $field['legacy'] ) ) continue;
            add_filter( 'pre_option_' . $key, [ __CLASS__, 'legacy_read' ], 1, 2 );
            add_filter( 'pre_update_option_' . $key, [ __CLASS__, 'legacy_write' ], 1, 3 );
        }

        self::maybe_migrate();

    }

    /**
     * Schema.
     *
     * Field keys:
     *  type     bool | enum | int | text | textarea | url | email | list | roles | multi
     *  default  value when unset
     *  env      per-environment default: [ 'kit' => x, 'production' => y ]
     *  page     admin page slug
     *  group    group slug on that page
     *  label    switch/field label (what happens, not the mechanism)
     *  desc     plain-language description
     *  options  enum options [ value => label ]
     *  requires key that must be on for this field to show
     *  constant constant that overrides the value
     *  legacy   how the legacy option is encoded: enable | raw | csv | b64 | array
     *  min/max  int bounds
     *
     * @return array
     */
    public static function schema(): array {

        if( self::$schema !== null ) return self::$schema;

        $on_kit = [ 'kit' => true, 'production' => false ];

        $s = [

            // ---------------- Site and environment ----------------
            'kit_environment' => [
                'type' => 'enum', 'default' => 'default', 'page' => 'settings', 'group' => 'environment',
                'label' => 'Environment', 'legacy' => 'raw',
                'desc' => 'Leave on Automatic unless Kit guesses wrong. Automatic uses WP_ENVIRONMENT_TYPE, then the live store URL.',
                'options' => [ 'default' => 'Automatic', 'production' => 'Production', 'staging' => 'Staging', 'development' => 'Development', 'local' => 'Local' ],
            ],
            'kit_production_url' => [
                'type' => 'url', 'default' => '', 'page' => 'settings', 'group' => 'environment',
                'label' => 'Live store URL', 'legacy' => 'b64',
                'desc' => 'The address of the real store. Any copy at a different address is treated as staging.',
            ],
            'kit_trusted_proxies' => [
                'type' => 'list', 'default' => [], 'page' => 'settings', 'group' => 'environment',
                'label' => 'Trusted proxies', 'legacy' => '',
                'desc' => 'IP addresses of load balancers or CDNs in front of this site. Only these may set the visitor IP through forwarded headers.',
            ],

            // ---------------- Safeguards ----------------
            'kit_block_email' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'email',
                'label' => 'Send all email to developers', 'legacy' => 'enable',
                'desc' => 'Order, password and renewal emails go to the inbox below instead of the customer. Cc and Bcc are removed.',
            ],
            'kit_email_redirect_to' => [
                'type' => 'email', 'default' => 'developers@builtmighty.com', 'page' => 'safeguards', 'group' => 'email',
                'label' => 'Deliver to', 'requires' => 'kit_block_email', 'legacy' => '',
                'desc' => 'Use a shared inbox. Each email keeps its original recipient in an X-Original-To header.',
            ],
            'kit_block_external' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'external',
                'label' => 'Block outside services', 'legacy' => 'enable',
                'desc' => 'Stops this copy from calling payment, shipping, marketing and other outside APIs. WordPress.org, GitHub, Slack and Built Mighty are always allowed.',
            ],
            'kit_allowed_external' => [
                'type' => 'list', 'default' => [], 'page' => 'safeguards', 'group' => 'external',
                'label' => 'Also allow', 'requires' => 'kit_block_external', 'legacy' => 'csv',
                'desc' => 'Hosts this copy may still call. Subdomains are included, so example.com also allows api.example.com.',
            ],
            'kit_actionscheduler' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'schedules',
                'label' => 'Pause scheduled actions', 'legacy' => 'enable', 'constant' => 'KIT_ACTION_SCHEDULER',
                'desc' => 'Action Scheduler jobs, like renewal charges and queued emails, wait until this is turned off.',
            ],
            'kit_test_payments' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'payments',
                'label' => 'Keep payments in test mode', 'legacy' => '',
                'desc' => 'Stripe, WooPayments and PayPal switch to their test or sandbox mode. Other card gateways are hidden at checkout. Nothing in their settings is changed.',
            ],
            'kit_allowed_gateways' => [
                'type' => 'list', 'default' => [], 'page' => 'safeguards', 'group' => 'payments',
                'label' => 'Also allow these gateways', 'requires' => 'kit_test_payments', 'legacy' => '',
                'desc' => 'Gateway IDs to leave untouched, like a gateway you\'ve set to its own test mode. Bank transfer, cheque and cash on delivery are always allowed.',
            ],
            'kit_hold_renewals' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'subscriptions',
                'label' => 'Hold subscription renewals', 'legacy' => '',
                'desc' => 'WooCommerce Subscriptions treats this copy as a duplicate site, so renewals switch to manual and no saved card is charged.',
            ],
            'kit_pause_webhooks' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'webhooks',
                'label' => 'Pause WooCommerce webhooks', 'legacy' => '',
                'desc' => 'Order and customer webhooks aren\'t delivered, so fulfilment, ERP and marketing tools don\'t receive test orders.',
            ],
            'kit_noindex' => [
                'type' => 'bool', 'default' => false, 'env' => $on_kit, 'page' => 'safeguards', 'group' => 'indexing',
                'label' => 'Keep search engines out', 'legacy' => '',
                'desc' => 'Adds noindex to every page and X-Robots-Tag to every response, and robots.txt disallows everything. The Reading setting isn\'t changed.',
            ],
            'kit_block_access' => [
                'type' => 'bool', 'default' => false, 'page' => 'safeguards', 'group' => 'visitors',
                'label' => 'Keep logged-out visitors out', 'legacy' => 'enable',
                'desc' => 'Visitors who aren\'t signed in are sent to builtmighty.com. Share the preview link below to let someone in without an account.',
            ],

            // ---------------- Login and two-factor ----------------
            'kit_enable_login' => [
                'type' => 'bool', 'default' => false, 'page' => 'login', 'group' => 'address',
                'label' => 'Use a custom login address', 'legacy' => 'enable',
                'desc' => 'Visitors to wp-login.php are sent to the home page. Password reset links keep working.',
            ],
            'kit_login_url' => [
                'type' => 'text', 'default' => '', 'page' => 'login', 'group' => 'address',
                'label' => 'Login address', 'requires' => 'kit_enable_login', 'legacy' => 'raw', 'constant' => 'BUILT_ENDPOINT',
                'desc' => 'Letters, numbers and dashes only.',
            ],
            'kit_enable_2fa' => [
                'type' => 'bool', 'default' => false, 'page' => 'login', 'group' => 'twofactor',
                'label' => 'Require two-factor', 'legacy' => 'enable',
                'desc' => 'People without an authenticator app get a code by email. It expires after 5 minutes.',
            ],
            'kit_2fa_users' => [
                'type' => 'roles', 'default' => [], 'page' => 'login', 'group' => 'twofactor',
                'label' => 'Also required for', 'requires' => 'kit_enable_2fa', 'legacy' => 'array',
                'desc' => 'Administrators always need two-factor.',
            ],
            'kit_2fa_unenrolled' => [
                'type' => 'enum', 'default' => 'enroll', 'page' => 'login', 'group' => 'twofactor',
                'label' => 'When someone hasn\'t set up two-factor yet', 'requires' => 'kit_enable_2fa', 'legacy' => '', 'select' => true,
                'options' => [ 'enroll' => 'Sign them in, then make them set up an app first', 'email' => 'Email them a code until they set up an app' ],
                'desc' => 'With the first option, every page sends them to setup until an authenticator app or passkey is added.',
            ],
            'kit_login_logging' => [
                'type' => 'bool', 'default' => false, 'page' => 'login', 'group' => 'alerts',
                'label' => 'Keep a sign-in log', 'legacy' => 'enable',
                'desc' => 'Records every sign-in and failed attempt with user, IP address and result. See Activity.',
            ],
            'kit_login_notify_new_ip' => [
                'type' => 'bool', 'default' => false, 'page' => 'login', 'group' => 'alerts',
                'label' => 'Email administrators about new IP addresses', 'requires' => 'kit_login_logging', 'legacy' => 'enable',
                'desc' => 'Sent when an administrator signs in from an address that isn\'t among their last 20.',
            ],
            'kit_session_management' => [
                'type' => 'bool', 'default' => false, 'page' => 'login', 'group' => 'sessions',
                'label' => 'Manage sign-in sessions', 'legacy' => 'enable',
                'desc' => 'Limit how many places someone can be signed in and sign out idle sessions.',
            ],
            'kit_session_logout_on_password_change' => [
                'type' => 'bool', 'default' => false, 'page' => 'login', 'group' => 'sessions',
                'label' => 'Sign out other devices when a password changes', 'requires' => 'kit_session_management', 'legacy' => 'enable',
                'desc' => '',
            ],
            'kit_session_limit' => [
                'type' => 'enum', 'default' => '0', 'page' => 'login', 'group' => 'sessions',
                'label' => 'Places someone can be signed in at once', 'requires' => 'kit_session_management', 'legacy' => 'raw',
                'options' => [ '0' => 'No limit', '1' => '1', '2' => '2', '3' => '3', '5' => '5' ],
                'desc' => 'When the limit is reached, the oldest session ends.',
            ],
            'kit_session_timeout' => [
                'type' => 'enum', 'default' => '0', 'page' => 'login', 'group' => 'sessions',
                'label' => 'Sign out after inactivity', 'requires' => 'kit_session_management', 'legacy' => 'raw',
                'options' => [ '0' => 'Never', '15' => '15 minutes', '30' => '30 minutes', '60' => '1 hour', '120' => '2 hours', '480' => '8 hours', '1440' => '24 hours' ],
                'desc' => '',
            ],

            // ---------------- Headers and API ----------------
            'kit_security_headers' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'headers',
                'label' => 'Send security headers', 'legacy' => 'enable',
                'desc' => 'Response headers that tell browsers how to treat this site.',
            ],
            'kit_header_x_frame' => [
                'type' => 'enum', 'default' => 'SAMEORIGIN', 'page' => 'headers', 'group' => 'headers',
                'label' => 'Frame embedding', 'requires' => 'kit_security_headers', 'legacy' => 'raw',
                'options' => [ 'SAMEORIGIN' => 'This site only', 'DENY' => 'Never', 'disable' => 'Any site' ],
                'desc' => 'Stops other sites from showing this one inside a frame (clickjacking).',
            ],
            'kit_header_x_content_type' => [
                'type' => 'bool', 'default' => true, 'page' => 'headers', 'group' => 'headers',
                'label' => 'Block MIME type sniffing', 'requires' => 'kit_security_headers', 'legacy' => 'enable', 'desc' => 'X-Content-Type-Options: nosniff.',
            ],
            'kit_header_referrer_policy' => [
                'type' => 'enum', 'default' => 'strict-origin-when-cross-origin', 'page' => 'headers', 'group' => 'headers',
                'label' => 'Referrer policy', 'requires' => 'kit_security_headers', 'legacy' => 'raw',
                'options' => [ 'strict-origin-when-cross-origin' => 'Strict origin when cross-origin (recommended)', 'no-referrer' => 'No referrer', 'no-referrer-when-downgrade' => 'No referrer when downgrading', 'same-origin' => 'Same origin', 'strict-origin' => 'Strict origin', 'disable' => 'Don\'t send' ],
                'desc' => 'How much of the current address is shared when visitors follow a link.',
            ],
            'kit_header_permissions_policy' => [
                'type' => 'bool', 'default' => true, 'page' => 'headers', 'group' => 'headers',
                'label' => 'Restrict camera, microphone and location', 'requires' => 'kit_security_headers', 'legacy' => 'enable', 'desc' => 'Permissions-Policy header.',
            ],
            'kit_header_x_xss' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'headers',
                'label' => 'Send the legacy X-XSS-Protection header', 'requires' => 'kit_security_headers', 'legacy' => 'enable',
                'desc' => 'Sends X-XSS-Protection: 0, which turns off the outdated filter in old browsers.',
            ],
            'kit_header_hsts' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'hsts',
                'label' => 'Always use HTTPS (HSTS)', 'requires' => 'kit_security_headers', 'legacy' => 'enable',
                'desc' => 'Browsers will refuse plain HTTP for this site. Only turn on once every page works over HTTPS.',
            ],
            'kit_header_hsts_max_age' => [
                'type' => 'enum', 'default' => '31536000', 'page' => 'headers', 'group' => 'hsts',
                'label' => 'Remember for', 'requires' => 'kit_header_hsts', 'legacy' => 'raw',
                'options' => [ '300' => '5 minutes (testing)', '86400' => '1 day', '2592000' => '30 days', '31536000' => '1 year', '63072000' => '2 years' ],
                'desc' => '',
            ],
            'kit_header_hsts_subdomains' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'hsts',
                'label' => 'Include subdomains', 'requires' => 'kit_header_hsts', 'legacy' => 'enable', 'desc' => '',
            ],
            'kit_header_hsts_preload' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'hsts',
                'label' => 'Allow browser preload lists', 'requires' => 'kit_header_hsts', 'legacy' => 'enable',
                'desc' => 'Hard to undo. Needs a 1-year or longer setting and subdomains included.',
            ],
            'kit_header_csp' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'csp',
                'label' => 'Send a Content-Security-Policy', 'requires' => 'kit_security_headers', 'legacy' => 'enable',
                'desc' => 'Limits where scripts, styles and images can load from. Start in report-only mode.',
            ],
            'kit_header_csp_report_only' => [
                'type' => 'bool', 'default' => true, 'page' => 'headers', 'group' => 'csp',
                'label' => 'Report only, don\'t block', 'requires' => 'kit_header_csp', 'legacy' => 'enable',
                'desc' => 'Browsers report what would be blocked without blocking it.',
            ],
            'kit_header_csp_custom' => [
                'type' => 'textarea', 'default' => '', 'page' => 'headers', 'group' => 'csp',
                'label' => 'Custom policy', 'requires' => 'kit_header_csp', 'legacy' => 'raw',
                'desc' => 'Leave empty to use Kit\'s WordPress-friendly default policy.',
            ],
            'kit_rest_api_security' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Protect the REST API', 'legacy' => 'enable', 'desc' => 'Controls for /wp-json/.',
            ],
            'kit_disable_user_enum' => [
                'type' => 'bool', 'default' => true, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Hide the user list from visitors', 'requires' => 'kit_rest_api_security', 'legacy' => 'enable',
                'desc' => 'Stops anyone from listing usernames through /wp-json/wp/v2/users.',
            ],
            'kit_rest_require_auth' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Require sign-in for private routes', 'requires' => 'kit_rest_api_security', 'legacy' => 'enable',
                'desc' => 'Public content, WooCommerce Store API and the routes below stay open.',
            ],
            'kit_rest_whitelist' => [
                'type' => 'list', 'default' => [], 'page' => 'headers', 'group' => 'rest',
                'label' => 'Also keep public', 'requires' => 'kit_rest_require_auth', 'legacy' => 'csv',
                'desc' => 'Route prefixes, like /myplugin/v1/.',
            ],
            'kit_rest_custom_disabled' => [
                'type' => 'list', 'default' => [], 'page' => 'headers', 'group' => 'rest',
                'label' => 'Turn off these routes', 'requires' => 'kit_rest_api_security', 'legacy' => 'csv',
                'desc' => 'Route prefixes to remove completely, like /wp/v2/comments.',
            ],
            'kit_rest_remove_link' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Remove the API link from page headers', 'requires' => 'kit_rest_api_security', 'legacy' => 'enable', 'desc' => '',
            ],
            'kit_rest_logging' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Log API changes', 'requires' => 'kit_rest_api_security', 'legacy' => 'enable',
                'desc' => 'Records requests that change data, and failed requests, in Activity. Personal details are removed.',
            ],
            'kit_rest_rate_limit' => [
                'type' => 'bool', 'default' => false, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Rate-limit visitors', 'requires' => 'kit_rest_api_security', 'legacy' => 'enable',
                'desc' => 'Signed-out visitors who go over the limit get a 429 response.',
            ],
            'kit_rest_rate_limit_requests' => [
                'type' => 'int', 'default' => 60, 'min' => 10, 'max' => 10000, 'page' => 'headers', 'group' => 'rest',
                'label' => 'Requests allowed', 'requires' => 'kit_rest_rate_limit', 'legacy' => 'raw', 'desc' => '',
            ],
            'kit_rest_rate_limit_window' => [
                'type' => 'enum', 'default' => '60', 'page' => 'headers', 'group' => 'rest',
                'label' => 'Per', 'requires' => 'kit_rest_rate_limit', 'legacy' => 'raw',
                'options' => [ '60' => 'Minute', '300' => '5 minutes', '3600' => 'Hour' ], 'desc' => '',
            ],

            // ---------------- Spam ----------------
            'kit_spam_protection' => [
                'type' => 'bool', 'default' => false, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Protect comments from spam', 'legacy' => 'enable', 'desc' => 'Checks only apply to signed-out visitors.',
            ],
            'kit_disable_comments' => [
                'type' => 'bool', 'default' => false, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Turn off comments everywhere', 'requires' => 'kit_spam_protection', 'legacy' => 'enable',
                'desc' => 'Removes comment forms, menus and API routes.',
            ],
            'kit_comment_honeypot' => [
                'type' => 'bool', 'default' => false, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Add a hidden honeypot field', 'requires' => 'kit_spam_protection', 'legacy' => 'enable', 'desc' => 'Bots fill it in. People never see it.',
            ],
            'kit_comment_time_check' => [
                'type' => 'bool', 'default' => false, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Reject comments sent too quickly', 'requires' => 'kit_spam_protection', 'legacy' => 'enable', 'desc' => '',
            ],
            'kit_comment_min_time' => [
                'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 60, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Minimum seconds before sending', 'requires' => 'kit_comment_time_check', 'legacy' => 'raw', 'desc' => '',
            ],
            'kit_comment_max_links' => [
                'type' => 'enum', 'default' => '2', 'page' => 'spam', 'group' => 'comments',
                'label' => 'Most links allowed in a comment', 'requires' => 'kit_spam_protection', 'legacy' => 'raw',
                'options' => [ '0' => 'No limit', '1' => '1', '2' => '2', '3' => '3', '5' => '5' ], 'desc' => '',
            ],
            'kit_block_spam_ips' => [
                'type' => 'bool', 'default' => false, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Block addresses that keep sending spam', 'requires' => 'kit_spam_protection', 'legacy' => 'enable',
                'desc' => 'Blocked addresses are listed in Activity, where you can unblock them.',
            ],
            'kit_spam_block_threshold' => [
                'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 50, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Rejected comments before blocking', 'requires' => 'kit_block_spam_ips', 'legacy' => 'raw', 'desc' => '',
            ],
            'kit_disable_pingbacks' => [
                'type' => 'bool', 'default' => false, 'page' => 'spam', 'group' => 'comments',
                'label' => 'Turn off pingbacks and trackbacks', 'requires' => 'kit_spam_protection', 'legacy' => 'enable', 'desc' => '',
            ],

            // ---------------- Performance ----------------
            'kit_disable_cart_fragments' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'woocommerce',
                'label' => 'Stop cart fragment refreshes', 'legacy' => 'enable',
                'desc' => 'WooCommerce refreshes the mini cart with an extra request on every page. Turn off if your theme doesn\'t need it.',
            ],
            'kit_cart_fragments_exclude' => [
                'type' => 'list', 'default' => [], 'page' => 'performance', 'group' => 'woocommerce',
                'label' => 'Keep it on these pages', 'requires' => 'kit_disable_cart_fragments', 'legacy' => 'csv', 'desc' => 'Page slugs, like cart or shop.',
            ],
            'kit_disable_wc_scripts' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'woocommerce',
                'label' => 'Load WooCommerce scripts only on store pages', 'legacy' => 'enable', 'desc' => '',
            ],
            'kit_wc_scripts_exclude' => [
                'type' => 'list', 'default' => [], 'page' => 'performance', 'group' => 'woocommerce',
                'label' => 'Also load them on', 'requires' => 'kit_disable_wc_scripts', 'legacy' => 'csv', 'desc' => 'Page slugs.',
            ],
            'kit_disable_wc_admin' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'woocommerce',
                'label' => 'Turn off WooCommerce analytics and inbox', 'legacy' => 'enable', 'desc' => 'Makes wp-admin lighter.',
            ],
            'kit_disable_marketing_hub' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'woocommerce',
                'label' => 'Turn off the WooCommerce marketing hub', 'legacy' => 'enable', 'desc' => '',
            ],
            'kit_disable_jquery_migrate' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Remove jQuery Migrate', 'legacy' => 'enable', 'desc' => 'Only if your theme and plugins don\'t need it.',
            ],
            'kit_remove_query_strings' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Remove version strings from file URLs', 'legacy' => 'enable', 'desc' => 'style.css?ver=1.0 becomes style.css.',
            ],
            'kit_cleanup_head' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Remove extra tags from the page head', 'legacy' => 'enable', 'desc' => 'Generator, RSD, wlwmanifest and shortlink tags.',
            ],
            'kit_remove_global_styles' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Remove block theme global styles', 'legacy' => 'enable', 'desc' => 'Only for classic themes that don\'t use them.',
            ],
            'kit_disable_password_meter' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Remove the password strength meter', 'legacy' => 'enable', 'desc' => 'Saves about 400 KB on account pages.',
            ],
            'kit_dns_prefetch' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Look up outside domains early', 'legacy' => 'enable', 'desc' => 'Adds DNS prefetch hints.',
            ],
            'kit_dns_prefetch_domains' => [
                'type' => 'list', 'default' => [], 'page' => 'performance', 'group' => 'frontend',
                'label' => 'Domains', 'requires' => 'kit_dns_prefetch', 'legacy' => 'csv', 'desc' => '',
            ],
            'kit_bundle_enabled' => [
                'type' => 'bool', 'default' => false, 'page' => 'performance', 'group' => 'bundler',
                'label' => 'Combine scripts and stylesheets', 'legacy' => 'enable',
                'desc' => 'Serves the files you pick as one script and one stylesheet.',
            ],

            // ---------------- Site admin ----------------
            'kit_disable_editor' => [
                'type' => 'bool', 'default' => false, 'page' => 'settings', 'group' => 'admin',
                'label' => 'Turn off the theme and plugin file editors', 'legacy' => 'enable', 'desc' => '',
            ],
            'kit_user_delete_search' => [
                'type' => 'bool', 'default' => true, 'page' => 'settings', 'group' => 'admin',
                'label' => 'Faster Delete Users screen', 'legacy' => 'enable',
                'desc' => 'Replaces the drop-down that loads every account with a search, and explains what deleting content removes.',
            ],
            'kit_stale_plugins' => [
                'type' => 'enum', 'default' => 'developers', 'page' => 'settings', 'group' => 'admin',
                'label' => 'Plugin freshness on the Plugins page', 'legacy' => 'raw',
                'options' => [ 'developers' => 'Built Mighty team', 'all' => 'All administrators', 'disable' => 'Nobody' ],
                'desc' => 'Shows when each plugin was last updated and which WordPress, PHP and WooCommerce versions it supports.',
            ],

            // ---------------- Slack ----------------
            'slack-channel' => [
                'type' => 'text', 'default' => '', 'page' => 'slack', 'group' => 'slack',
                'label' => 'Channel', 'legacy' => 'raw', 'desc' => 'Where alerts and client messages go.',
            ],
            'slack-notifications' => [
                'type' => 'multi', 'default' => [], 'page' => 'slack', 'group' => 'slack',
                'label' => 'Send alerts for', 'legacy' => 'array', 'desc' => '',
            ],
            'slack-summary' => [
                'type' => 'bool', 'default' => false, 'page' => 'slack', 'group' => 'summary',
                'label' => 'Send a daily summary', 'legacy' => 'enable', 'desc' => 'One message a day instead of an alert for every change.',
            ],
            'slack-summary-time' => [
                'type' => 'enum', 'default' => '09:00', 'page' => 'slack', 'group' => 'summary',
                'label' => 'Send at', 'requires' => 'slack-summary', 'legacy' => 'raw',
                'options' => [ '07:00' => '7:00 AM', '08:00' => '8:00 AM', '09:00' => '9:00 AM', '12:00' => '12:00 PM', '17:00' => '5:00 PM' ],
                'desc' => 'In the site\'s time zone.',
            ],

            // ---------------- Monitoring ----------------
            'kit_crm_enabled' => [
                'type' => 'bool', 'default' => false, 'page' => 'monitoring', 'group' => 'crm',
                'label' => 'Send a daily report to Built Mighty', 'legacy' => 'enable',
                'desc' => 'Sales totals, catalog size and installed plugins, so we can spot problems early.',
            ],
            'kit_crm_ga_property' => [
                'type' => 'text', 'default' => '', 'page' => 'monitoring', 'group' => 'crm',
                'label' => 'Google Analytics property ID', 'requires' => 'kit_crm_enabled', 'legacy' => 'raw', 'desc' => 'Optional. Numbers only.',
            ],
            'kit_crm_rum_enabled' => [
                'type' => 'bool', 'default' => false, 'page' => 'monitoring', 'group' => 'crm',
                'label' => 'Measure real visitor page speed', 'requires' => 'kit_crm_enabled', 'legacy' => 'enable',
                'desc' => 'Samples 1 in 10 visits and sends daily averages with the report.',
            ],

        ];

        /**
         * Filter the settings schema. New features add fields here.
         *
         * @since 6.0.0
         */
        return self::$schema = (array) apply_filters( 'kit_settings_schema', $s );

    }

    /**
     * Field definition.
     */
    public static function field( string $key ): ?array {
        $schema = self::schema();
        return $schema[ $key ] ?? null;
    }

    /**
     * Stored values (raw, typed).
     */
    public static function all(): array {
        if( self::$values === null ) {
            $stored = get_option( self::OPTION, [] );
            self::$values = is_array( $stored ) ? $stored : [];
        }
        return self::$values;
    }

    /**
     * Was this setting saved explicitly?
     */
    public static function is_set( string $key ): bool {
        return array_key_exists( $key, self::all() ) && self::applies_here( $key );
    }

    /**
     * Environment bucket a safeguard value belongs to.
     */
    public static function env_bucket(): string {
        return environment::is_kit() ? 'kit' : 'production';
    }

    /**
     * Does a saved value apply in this environment?
     *
     * Safeguards (fields with an environment default) remember where they were
     * saved. A value saved on the live store is ignored on a copy made from its
     * database, and the other way round, so a clone never carries "off" onto
     * staging or "on" onto the live store.
     */
    private static function applies_here( string $key ): bool {
        $field = self::field( $key );
        if( ! $field || empty( $field['env'] ) || ! is_array( $field['env'] ) ) return true;
        $stamps = (array) ( self::all()['_env'] ?? [] );
        return ( $stamps[ $key ] ?? '' ) === self::env_bucket();
    }

    /**
     * Resolved value: constant, saved, environment default, default.
     *
     * @param  string $key
     * @return mixed
     */
    public static function get( string $key ) {

        $field = self::field( $key );
        if( ! $field ) return null;

        // Constant override.
        if( ! empty( $field['constant'] ) && defined( $field['constant'] ) ) {
            return self::from_constant( $field, constant( $field['constant'] ) );
        }

        $values = self::all();
        if( array_key_exists( $key, $values ) && self::applies_here( $key ) ) return $values[ $key ];

        return self::default_for( $key );

    }

    /**
     * Default value, taking the environment into account.
     */
    public static function default_for( string $key ) {
        $field = self::field( $key );
        if( ! $field ) return null;
        if( ! empty( $field['env'] ) && is_array( $field['env'] ) ) {
            // Avoid recursion: environment detection reads kit_environment and kit_production_url only.
            $env = in_array( $key, [ 'kit_environment', 'kit_production_url' ], true ) ? 'production' : ( environment::is_kit() ? 'kit' : 'production' );
            if( array_key_exists( $env, $field['env'] ) ) return $field['env'][ $env ];
        }
        return $field['default'] ?? null;
    }

    /**
     * Is a switch on?
     */
    public static function is_on( string $key ): bool {
        return (bool) self::get( $key );
    }

    /**
     * Is a dependent field active (its parents are all on)?
     */
    public static function is_active( string $key ): bool {
        $field = self::field( $key );
        if( ! $field ) return false;
        if( empty( $field['requires'] ) ) return true;
        return self::is_on( $field['requires'] ) && self::is_active( $field['requires'] );
    }

    /**
     * Is the value locked by a constant?
     */
    public static function locked_by( string $key ): string {
        $field = self::field( $key );
        if( $field && ! empty( $field['constant'] ) && defined( $field['constant'] ) ) return $field['constant'];
        return '';
    }

    /**
     * Save many values. Validates against the schema.
     *
     * @param  array $changes key => value
     * @return array [ 'saved' => [key => value], 'errors' => [key => message] ]
     */
    public static function update( array $changes ): array {

        $values = self::all();
        $saved  = [];
        $errors = [];

        foreach( $changes as $key => $value ) {
            $key   = (string) $key;
            $field = self::field( $key );
            if( ! $field ) { $errors[ $key ] = 'Unknown setting.'; continue; }
            $result = self::sanitize( $field, $value );
            if( is_wp_error( $result ) ) { $errors[ $key ] = $result->get_error_message(); continue; }
            $values[ $key ] = $result;
            $saved[ $key ]  = $result;
            if( ! empty( $field['env'] ) && is_array( $field['env'] ) ) {
                $values['_env'] = (array) ( $values['_env'] ?? [] );
                $values['_env'][ $key ] = self::env_bucket();
            }
        }

        if( $saved ) self::persist( $values );

        return [ 'saved' => $saved, 'errors' => $errors ];

    }

    /**
     * Remove saved values so defaults apply again.
     */
    public static function reset_keys( array $keys ): void {
        $values = self::all();
        foreach( $keys as $key ) unset( $values[ $key ], $values['_env'][ $key ] );
        self::persist( $values );
    }

    /**
     * Write the option.
     */
    private static function persist( array $values ): void {
        self::$writing = true;
        update_option( self::OPTION, $values, true );
        self::$writing = false;
        self::$values = $values;
        environment::reset();
        do_action( 'kit_settings_saved', $values );
    }

    /**
     * Sanitize one value against its field.
     *
     * @return mixed|\WP_Error
     */
    public static function sanitize( array $field, $value ) {

        switch( $field['type'] ) {

            case 'bool':
                if( is_string( $value ) ) $value = in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on', 'enable' ], true );
                return (bool) $value;

            case 'enum':
                $value = (string) $value;
                if( ! array_key_exists( $value, (array) ( $field['options'] ?? [] ) ) ) {
                    return new \WP_Error( 'invalid', 'Choose one of the listed options.' );
                }
                return $value;

            case 'int':
                if( ! is_numeric( $value ) ) return new \WP_Error( 'invalid', 'Enter a whole number.' );
                $value = (int) $value;
                if( isset( $field['min'] ) && $value < $field['min'] ) return new \WP_Error( 'invalid', 'Enter ' . $field['min'] . ' or more.' );
                if( isset( $field['max'] ) && $value > $field['max'] ) return new \WP_Error( 'invalid', 'Enter ' . $field['max'] . ' or less.' );
                return $value;

            case 'text':
                $value = sanitize_text_field( (string) $value );
                if( ! empty( $field['constant'] ) && $field['constant'] === 'BUILT_ENDPOINT' ) {
                    $value = trim( $value, '/ ' );
                    if( $value !== '' && ! preg_match( '/^[a-z0-9][a-z0-9\-]*$/i', $value ) ) {
                        return new \WP_Error( 'invalid', 'Use letters, numbers and dashes only.' );
                    }
                    if( in_array( strtolower( $value ), [ 'wp-admin', 'wp-login', 'admin', 'login', 'dashboard' ], true ) ) {
                        return new \WP_Error( 'invalid', 'Pick something less predictable than "' . $value . '".' );
                    }
                }
                return $value;

            case 'textarea':
                return sanitize_textarea_field( (string) $value );

            case 'url':
                $value = trim( (string) $value );
                if( $value === '' ) return '';
                if( strpos( $value, '//' ) === false ) $value = 'https://' . $value;
                $host = wp_parse_url( $value, PHP_URL_HOST );
                if( ! $host || strpos( $host, '.' ) === false && $host !== 'localhost' ) {
                    return new \WP_Error( 'invalid', 'Include the full domain, like shop.example.com.' );
                }
                return esc_url_raw( untrailingslashit( $value ) );

            case 'email':
                $value = sanitize_email( (string) $value );
                if( ! is_email( $value ) ) return new \WP_Error( 'invalid', 'Enter an email address, like developers@builtmighty.com.' );
                return $value;

            case 'list':
                if( is_string( $value ) ) $value = preg_split( '/[\s,]+/', $value );
                $value = array_values( array_unique( array_filter( array_map( function( $item ) {
                    return trim( sanitize_text_field( (string) $item ) );
                }, (array) $value ), 'strlen' ) ) );
                return $value;

            case 'roles':
            case 'multi':
                $value = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $value ) ) ) );
                return $value;

        }

        return new \WP_Error( 'invalid', 'Unsupported setting.' );

    }

    /**
     * Convert a constant's value into the field's type.
     */
    private static function from_constant( array $field, $value ) {
        if( $field['type'] === 'bool' ) {
            // KIT_ACTION_SCHEDULER historically used 'enable' (block), 'disable' and 'auto'.
            if( is_string( $value ) ) {
                $v = strtolower( $value );
                if( $v === 'auto' ) return environment::is_kit();
                return in_array( $v, [ '1', 'true', 'yes', 'on', 'enable' ], true );
            }
            return (bool) $value;
        }
        return is_string( $value ) ? trim( $value, '/ ' ) : $value;
    }

    // ------------------------------------------------------------------
    // Legacy shims.
    // ------------------------------------------------------------------

    /**
     * Encode a typed value the way legacy code expects it.
     */
    public static function encode_legacy( array $field, $value ) {
        switch( $field['legacy'] ?? '' ) {
            case 'enable': return $value ? 'enable' : 'disable';
            case 'csv':    return implode( ',', (array) $value );
            case 'array':  return array_values( (array) $value );
            case 'b64':    return $value === '' ? '' : base64_encode( trailingslashit( (string) $value ) );
            default:       return is_array( $value ) ? $value : (string) $value;
        }
    }

    /**
     * Decode a legacy option value into a typed value.
     */
    public static function decode_legacy( array $field, $value ) {
        switch( $field['legacy'] ?? '' ) {
            case 'enable':
                if( $value === '' || $value === null || $value === false ) return null; // Unset: use defaults.
                return $value === 'enable';
            case 'csv':
                return self::sanitize( $field, (string) $value );
            case 'array':
                return is_array( $value ) ? self::sanitize( $field, $value ) : [];
            case 'b64':
                $value = (string) $value;
                $decoded = base64_decode( $value, true );
                if( $decoded !== false && base64_encode( $decoded ) === $value ) $value = $decoded;
                $clean = self::sanitize( $field, $value );
                return is_wp_error( $clean ) ? null : $clean;
            default:
                if( $value === '' || $value === null || $value === false ) return null;
                $clean = self::sanitize( $field, $value );
                return is_wp_error( $clean ) ? null : $clean;
        }
    }

    /**
     * pre_option_{key}: answer legacy reads from kit_settings.
     */
    public static function legacy_read( $pre, $option ) {
        $field = self::field( $option );
        if( ! $field ) return $pre;
        return self::encode_legacy( $field, self::get( $option ) );
    }

    /**
     * pre_update_option_{key}: route legacy writes into kit_settings.
     */
    public static function legacy_write( $value, $old_value, $option ) {
        if( self::$writing ) return $value;
        $field = self::field( $option );
        if( ! $field ) return $value;
        $typed = self::decode_legacy( $field, $value );
        if( $typed === null ) {
            self::reset_keys( [ $option ] );
        } else {
            self::update( [ $option => $typed ] );
        }
        // Returning the old value tells WordPress nothing changed, so the legacy row isn't written.
        return $old_value;
    }

    // ------------------------------------------------------------------
    // Migration.
    // ------------------------------------------------------------------

    /**
     * Run migrations once per version.
     */
    public static function maybe_migrate(): void {

        $installed = (string) get_option( self::DB_VERSION, '' );
        if( $installed !== '' && version_compare( $installed, self::VERSION, '>=' ) ) return;

        global $wpdb;

        // Read legacy rows directly; the pre_option shim would otherwise answer.
        $keys = array_keys( array_filter( self::schema(), function( $f ) { return ! empty( $f['legacy'] ); } ) );
        $values = self::all();

        if( $keys ) {
            $placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)", $keys ) ); // phpcs:ignore
            foreach( (array) $rows as $row ) {
                if( array_key_exists( $row->option_name, $values ) ) continue;
                $field = self::field( $row->option_name );
                if( ! empty( $field['env'] ) && is_array( $field['env'] ) ) continue;
                $typed = self::decode_legacy( $field, maybe_unserialize( $row->option_value ) );
                if( $typed !== null ) $values[ $row->option_name ] = $typed;
            }
        }

        // Multisite stored the production URL network-wide.
        if( is_multisite() && ! array_key_exists( 'kit_production_url', $values ) ) {
            $network = get_site_option( 'kit_production_url' );
            if( $network ) {
                $typed = self::decode_legacy( self::field( 'kit_production_url' ), $network );
                if( $typed ) $values['kit_production_url'] = $typed;
            }
        }

        // Legacy "default" environment means automatic.
        if( isset( $values['kit_environment'] ) && ! array_key_exists( $values['kit_environment'], (array) self::field( 'kit_environment' )['options'] ) ) {
            unset( $values['kit_environment'] );
        }

        self::persist( $values );

        // Older versions treated any site without WP_ENVIRONMENT_TYPE as production.
        // Keep that answer for this site so upgrading never turns a live store into
        // a "copy", and ask an administrator to confirm.
        environment::reset();
        if( empty( $values['kit_production_url'] ) && empty( $values['kit_environment'] )
            && environment::explicit_wp_environment() === ''
            && ! environment::is_copy_host( environment::current_host() ) ) {
            self::update( [ 'kit_production_url' => home_url() ] );
            update_option( 'kit_confirm_live_url', 1, false );
        }

        update_option( self::DB_VERSION, self::VERSION, true );

    }

}
