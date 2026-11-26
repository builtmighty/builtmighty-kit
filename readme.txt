=== Built Mighty Kit ===
Contributors: tylerjohnsondesign
Donate link: https://builtmighty.com
Tags: kit, sitekit, development, security, performance
Requires at least: 6.2
Tested up to: 10
Stable tag: 6.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps staging copies from emailing customers, charging cards or calling outside services, and hardens the live store with two-factor sign-in, passkeys, security headers and speed settings.

== Description ==

The Built Mighty Kit provides a suite of tools for WordPress site management, security, and performance optimization. Features include custom login endpoints, two-factor authentication, CSS/JS asset bundling, security headers, spam protection, session management, REST API security, and CRM analytics integration.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 6.0.0 =
* New Kit admin: grouped pages with search (Ctrl K), switches named for what they do, related fields that open in place, a save bar with Discard and Undo, and an environment layer (striped copies, solid live store) on every screen.
* New Overview: what this copy is protected from, or, on the live store, a hardening checklist; recent activity and weekly totals.
* New Activity page: sign-ins, two-factor events, REST API changes, spam, admin changes, held webhooks and blocked requests in one searchable log, with CSV export. Stored in its own table instead of options.
* New Health page and Site Health checks: debug output, HTTPS and HSTS, two-factor coverage, an "admin" username, open registration, default salts, PHP version, outdated and inactive plugins, file editing and more.
* New safeguards for copies: payments forced to test mode (Stripe, WooPayments, PayPal; other card gateways hidden), WooCommerce Subscriptions renewals held, WooCommerce webhooks paused, and search engines kept out. All work through read-time filters; no store settings are changed.
* New: every redirected email is logged and can be previewed from Activity.
* New: passkeys (Face ID, Touch ID, Windows Hello, security keys) as a second factor.
* New: "Trust this browser for 30 days" at sign-in, with a list of trusted browsers to revoke in the profile.
* New: admin bar environment chip with a safeguards menu, striped banner on copies, and a redesigned dashboard widget.
* Rebuilt two-factor sign-in: the code step now comes after the password, so nobody can trigger code emails or lockouts without it. Authenticator codes can't be reused, email codes are stored hashed, secrets use libsodium with a dedicated key, and backup codes are shown after setup.
* Fixed: copies without WP_ENVIRONMENT_TYPE were treated as production. Kit now never treats an unidentified site as live and never writes during detection. Upgrading sites keep their current answer and are asked to confirm the live URL.
* Fixed: email and outside-request blocking were off by default on copies, and the email check read the wrong setting. Cc and Bcc are now removed from redirected email.
* Fixed: pausing Action Scheduler crashed wp-admin pages with a 503.
* Fixed: allowed outside hosts now include their subdomains (api.github.com, hooks.slack.com).
* Fixed: the REST API "require sign-in" check could be bypassed with a crafted URL.
* Fixed: the custom login address turned every 404 into a 200, broke password reset links and revealed itself through wp-admin redirects.
* Fixed: Slack received WooCommerce settings values (including gateway secrets); the daily summary log was publicly readable; disconnecting Slack and the dashboard message form had no CSRF protection.
* Fixed: My Account two-factor reset and setup could be triggered by a link.
* Fixed: session limits kept one session fewer than configured; idle timeout wrote to the database on every request and was kept alive by open tabs.
* Fixed: spam IP blocking ignored its threshold, and spam checks rejected admin replies and REST comments.
* Fixed: WP-CLI renewal processing could loop forever, remove-customers never worked, order commands didn't support HPOS, and tfa setup always failed.
* Performance: features load only where they're needed; asset detection no longer writes on every visit; bundles rebuild in the background and JavaScript is no longer run through a regex minifier; real-visitor speed data is sampled and stored as fixed-size histograms, and now sent with the daily report; plugin freshness lookups cache misses; the CRM report reads WooCommerce analytics tables instead of loading every order; the 2-year backfill runs in the background.
* Settings now live in one autoloaded option (kit_settings). Old option names still read and write through it.
* Removed: dark mode and the per-user color option, and empty front-end assets that loaded on every page.

= 5.0.2 =
* Fixed user enumeration protection incorrectly blocking /wp/v2/users endpoints for authenticated editors, preventing post author from being editable in the Gutenberg block editor.

= 5.0.0 =
* Added CSS/JS Asset Bundler — auto-detects enqueued assets, allows selective bundling/minification with admin UI, 24-hour auto-rebuild, and admin bar rebuild button.
* Added CRM Analytics integration with RUM (Real User Monitoring) and WooCommerce event tracking.
* Added Speed Optimization tab with dedicated settings (Disable Cart Fragments, WC Scripts, jQuery Migrate, Query Strings, WC Admin, Marketing Hub, Head Cleanup, DNS Prefetch, Password Meter).
* Added Security Headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS, CSP).
* Added Login Activity logging with new IP alerts.
* Added Session Management (concurrent session limits, idle timeout, logout on password change).
* Added REST API Security (authentication requirement, rate limiting, logging).
* Added Spam Protection (honeypot, time-based checks, spam IP blocking, disable comments/pingbacks).
* Improved 2FA — switched to BaconQrCode, added encrypted secret storage, backup codes, 8-digit TOTP codes, and CSRF nonce verification.
* Improved kit mode detection with production URL comparison and host suffix matching.
* Updated admin menu with custom Built Mighty icon.
* Fixed XSS vulnerabilities in security setup views.
* Fixed insecure cookie settings in block-access.
* Added rate limiting to login security.
* Fixed developer widget panels not displaying content.
* Removed endroid/qr-code dependency (17MB size reduction).

= 4.4.0 =
* Added updated kit mode detection.
* Added updated kit mode fields.
* Added production URL kit field for kit mode detection.
* Fixed bug with blocking of Action Scheduler.

= 4.3.0 =
* Added plugin stale/outdated/recent detection based on last update, tested up to WooCommerce version, PHP version, and WordPress version.

= 4.2.1 =
* Fix some deprecation warnings.

= 4.2.0 =
* Added admin dark/light color options.

= 4.1.0 =
* Added active site monitoring.

= 4.0.4 =
* Update 2FA authentication method to detect login.

= 4.0.3 =
* Widget styling tweak.

= 4.0.2 =
* Updated logic around external API requests and login security.

= 4.0.0 =
* Restructured plugin for generalized templating.
* Fixed issues with login.
* Globalized settings page with updated UI.

= 3.0.3 =
* Disabled all data generation.

= 3.0.2 =
* Disabled Pagespeed scores because of long API loading times

= 3.0.1 =
* Bug fix with error log in 2FA

= 3.0.0 =
* Add Slack Integration + Notifications
* Add Data API Creation
* Add 2FA for All User Roles

= 2.1.0 =
* Add query parameter bypass when access block is place.
* Bug fix fatal error with failed login log when using incorrect password.
* Bug fix custom login endpoint broken when using standard permalinks.

= 2.0.2 =
* Fixed ActionScheduler error
* Fixed environment type form always displaying
* Fixed environment type logic being set

= 2.0.1 =
* Fixed ActionScheduler bug where it was still running.

= 2.0.0 =
* Added 2FA for admins.
* Fixed 404 on custom login page if already logged in.
* Fixed redirect to custom login page if using WooCommerce form, which reveals custom login.
* Fixed 404 on custom login page if query parameters are included in URL.

= 1.7.1 =
* Bugfix for setup class on activation.

= 1.7.0 =
* Added support for Codespaces.
* Fixed some small bugs.

= 1.6.0 =
* Adds plugin/theme update warnings for production sites.

= 1.5.0 =
* Feature - Add WP_ENVIRONMENT_TYPE logic.

= 1.4.0 =
* Bugfix - Password protected page redirect.

= 1.3.0 =
* Add dev site checklist
* Bugfix Jira projects/users loading

= 1.2.0 = 
* Add Jira panels + settings

= 1.1.0 =
* Full refactor

= 1.0.0 =
* Initial launch
