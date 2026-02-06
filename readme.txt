=== Built Mighty Kit ===
Contributors: tylerjohnsondesign
Donate link: https://builtmighty.com
Tags: kit, sitekit, development, security, performance
Requires at least: 6.0
Tested up to: 10
Stable tag: 5.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive toolkit for Built Mighty clients and developers, providing security hardening, performance optimization, development tools, and CRM analytics.

== Description ==

The Built Mighty Kit provides a suite of tools for WordPress site management, security, and performance optimization. Features include custom login endpoints, two-factor authentication, CSS/JS asset bundling, security headers, spam protection, session management, REST API security, and CRM analytics integration.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

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
