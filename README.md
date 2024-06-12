<p align="center" style="font-size:42px !important;">🚀 Built Mighty Kit</p>

## About
This plugin is the all around kit for Built Mighty client sites. From defining a new login endpoint for security, to protect wp-login from bot attacks, to adding two-factor authentication for admins, to disabling email sending on development sites, this plugin should provide you with the tools for a happy, healthy environment. If you have additional tools you'd like to see added, please either open an issue or contact one of the lead devs by tagging '@lead-dev-team' on Slack.

## Tools/Features
#### Security
* (optional) 2FA authentication for admin logins.
* (optional) 2FA for sensitive settings within wp-admin.
* (optional) Failed admin login log.
* (optional) Failed 2FA log.
* (optional) Blocks access to the site if not logged in.
* (optional) Blocks all non-approved IPs from accessing wp-admin.
* (optional) Creates a custom login URL and blocks access to default login.
* Removes WordPress version from the head.
* Disables login errors.
* Prevents user enumeration via API.

#### Development Environments
* (optional) Blocks outgoing emails by setting the "to" email as "developers@builtmighty.com".
* (optional) Disables possibly problematic WordPress plugins.
* (optional) Adds WP_HTTP_BLOCK_EXTERNAL to wp-config to block external API requests.
* (optional) Disables indexing for development environment sites.
* (optional) Adds tool to update all user emails to "user-randomstring@builtmighty.com".
* (optional) Adds tool to reset all updated user emails back to their original state.
* (optional) Adds CLI tool to remove all WooCommerce customer data.
* (optional) Adds CLI tool to remove all WooCommerce order data.

#### Misc.
* Disables theme/plugin file editor.
* Adds update plugin/theme warning message on production sites.
* Adds install plugin/theme warning message on production sites.
* Adjusts WordPress' heartbeat settings.
* Adjusts WordPress' post revisions.
* Removes junk dashboard widgets.
* Sets action scheduler retention period to five (5) days.
* Adds "🔨 Proudly developed by Built Mighty" to wp-admin footer.
* Adds a Built Mighty developer dashboard widget with: PHP version, MySQL version, WordPress verison, and current Git branch.
* Adds a Built Mighty developer checklist to development environments, to ensure proper development environment security.
* Adds a Built Mighty client dashboard widget with project manager information, contact form, and Jira issue creation form.

## Installation on All Sites
It is highly recommended that no matter the site, the WP_ENVIRONMENT_TYPE variable should be set within wp-config.php. The plugin will automatically detect a development site via the URL, but sites can also be placed into development mode using the environment variable. Set the variable to: `local`, `development`, or `staging`, to set the plugin in development mode. Set the environment variable to `production`, to set the plugin to production mode.

## Constants
The following constants can be set within wp-config.php for each site, in order to enable/disable some optional features/tools.

`define( 'WP_ENVIRONMENT_TYPE', 'development' );`
*(options: local, development, staging, production)*
This variable should be set on **all** sites. It greatly determines how the plugin operates and what options are available. It also is a global, WordPress constant that can be used by other plugins.

`define( 'BUILT_ENDPOINT', 'access' );`
This variable sets the custom login endpoint for the site. If this is not set, the feature will not be enable. If set, the new login will be the second parameter and any attempts to access wp-admin or wp-login.php, while not logged in, will send the user to the home page.

`define( 'BUILT_ACCESS', true );`
This constant disables access to the site, unless the user is logged in. The login URL is still accessible, either the custom or default, but not other URLs are accessible.

`define( 'BUILT_2FA', true );`
If this is set to true, then two factor authentication is enabled for all administrators. Upon logging in initially, all admins will be forced to set up two factor authentication.

`define( 'BUILT_2FA_SETTINGS', true );`
This variable turns on 2FA for all sensitive settings on the site, which means admins with two factor authentication set up must provide a code in order to view the settings. Additional blocked/sensitive settings can be set within the Built Mighty menu item, which is only accessible by users with @builtmighty.com or @littlerhino.io email addresses.

`define( 'BUILT_LOCKDOWN', true );`
If set to true, then only approved IPs (approved by 2FA, other admins, or CLI commands) can access the site.

## 2.0.0
* ✨ Added 2FA for admins.
* ✨ Added 2FA for sensitive settings.
* ✨ Added dynamic settings for 2FA sensitive settings.
* ✨ Added logging for failed admin logins.
* ✨ Added logging for failed 2FA logins.
* ✨ Added IP approval system for admin access.
* ✨ Added disabling of theme/plugin editing on all sites.
* ✨ Added WP CLI commands for security features: 2FA setup, 2FA reset, IP approval and IP removal.
* ✨ Added WP CLI commands for core features: disabling exernal API requests, disabling indexing, disabling plugins, updating emails, resetting emails, cleaning customer data, and removing order data.
* ⚡️ Updated namespacing to make more sense.
* ⚡️ Updated update/install plugin/theme wording.
* ♻️ Refactored class layouts to make more sense.
* ♻️ Refactored all assets to make more sense.
* ♻️ Refactored methods for updating wp-config variables.
* 🐛 Fixed update/install themes/plugins message so that it only displays on production sites.
* 🐛 Fixed loading custom login page with query parameters, so that it not longer 404s.
* 🐛 Fixed loading custom login page while logged in, so it now redirects to homepage.
* 🐛 Fixed wp-config updates on setup not being added, so that they are now added.

## 1.7.1
* Bugfix for setup class on activation.

## 1.7.0
* Added support for Codespaces.
* Fixed some small bugs.

## 1.6.0
* Adds plugin/theme update warnings for production sites.

## 1.5.0
* Feature - Add WP_ENVIRONMENT_TYPE logic.

## 1.4.0
* Bugfix - Password protected page redirect.

## 1.3.0
* Add dev site checklist
* Bugfix Jira projects/users loading

## 1.2.0
* Added an admin panel for Jira settings.
* Added a dashboard widget for Built Mighty developers.
* Added a dashboard widget for Built Mighty clients.
* Added a setup class for development environments.
* Added a speed class for production sites.
* Added a security class for production sites.


## 1.1.0
* Added email disabling functions.
* Added Action Scheduler disabling functions.
* Updated method for access restrictions/access.
* Updated documentation.
* Refactored plugin structure.
