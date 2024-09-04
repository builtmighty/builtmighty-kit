=== Built Mighty Kit ===
Contributors: tylerjohnsondesign
Donate link: https://builtmighty.com
Tags: kit, sitekit, development
Requires at least: 6.0
Tested up to: 10
Stable tag: 6.4.2
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin allows you to define a new login endpoint within wp-config to protect wp-login from bot attacks, as well as additional security and development features.

== Description ==

This plugin allows you to define a new login endpoint within wp-config to protect wp-login from bot attacks. It can also be used on development sites to stop bots from visiting.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

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
