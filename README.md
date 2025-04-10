<p align="center" style="font-size:42px !important;">🚀 Built Mighty Kit</p>

## About
This plugin is the all-around kit for Built Mighty client sites. From defining a new login endpoint for security, to protecting wp-login from bot attacks, adding two-factor authentication for admins, and disabling email sending on development sites, this plugin should provide you with the tools for a happy, healthy environment. If you have additional tools you'd like to see added, please either open an issue or contact one of the lead devs by tagging '@lead-dev-team' on Slack.

## Tools/Features
#### Security
* **Two-Factor Authentication** &mdash; *(optional)* Adds Two-Factor Authentication for `administrators` when enabled, but can be applied to other user roles. Uses email authentication by default, but also provides app-based authentication options.
* **Site Access** &mdash; *(optional)* Blocks access to the site when not logged in as an administrator, by redirecting non-logged in users to builtmighty.com.
* **Login URL** &mdash; *(optional)* Creates a custom login URL, while making the default WordPress login inaccessible. Trying to access the default login will redirect you to the homepage.
* **WordPress Version** &mdash; Removes the WordPress version from the head to better obfuscate from attackers.
* **User Enumeration** &mdash; Blocks WordPress user enumeration via the WordPress API.
* **Theme/Plugin Editor** &mdash; *(optional)* Disables the theme and plugin editors on the backend of WordPress.

#### Development Environments
* **WP Mail** &mdash; *(optional)* Blocks outgoing emails by setting the "to" email address as `developers@builtmighty.com`. Enabled by default on development and staging environments.
* **External Requests** &mdash; *(optional)* Blocks external API requests, but with the feature to allow connections to specific domains. Enabled by default on development and staging environments, but with default access to: `api.wordpress.org`, `downloads.wordpress.org`, `github.com`, `github.dev`, `github.io`, `githubusercontent.com`, `slack.com`, and `builtmighty.com`.
* **Action Scheduler** &mdash; *(optional)* Disables the Action Scheduler from running. Enabled by default on development and staging environments.

#### Misc.
* **Slack Connection** &mdash; *(optional)* Adds a Slack integration that allows both clients to contact us via their Slack channel and allows us to create WordPress settings notifications.
* **Plugin/Theme Updates** &mdash; Adds a warning message about updating themes and plugins on production sites.
* **Plugin/Theme Installs** &mdash; Adds a warning message about installing themes and plugins on production sites.
* **WordPress Heartbeat** &mdash; Adjusts WordPress' heartbeat settings for more efficiency.
* **Post Revisions** &mdash; Adjusts the number of saved post revisions for more efficiency and less bloat.
* **Dashboard Widgets** &mdash; Removes junk dashboard widgets, which slow the backend of WordPress.
* **Action Scheduler** &mdash; Adjusts the Action Scheduler log retention period to five (5) days, for less bloat.
* **Development Footer** &mdash; Adds `🔨 Proudly developed by Built Mighty` to the wp-admin footer.
* **Developer Widget** &mdash; Adds a Built Mighty developer dashboard widget, with: PHP version, MySQL version, WordPress version, WooCommerce version (if installed), enabled services, and the current Git branch.
* **Client Widget** &mdash; Adds a client dashboard widget with welcome information, as well as a Slack message form.

## Installation on All Sites
It is highly recommended that, no matter the site, the `WP_ENVIRONMENT_TYPE` variable should be set within `wp-config.php`. The plugin will automatically detect a development site via the URL, but sites can also be placed into development mode using the environment variable. Set the variable to: `local`, `development`, or `staging`, to set the plugin in development mode. Set the environment variable to `production` to set the plugin to production mode.

## Settings
To edit the settings of the plugin, once logged in, go to `/wp-admin/admin.php?page=builtmighty`.

## 4.0.4
* 🐛 Authentication method login update.

## 4.0.3
* 🐛 Widget styling tweak.

## 4.0.2
* 🐛 Fixing logic around external API requests.
* 🐛 Fixing logic around login security.

## 4.0.0 
* ✨ Restructured plugin files and methods.
* ✨ Updated admin UI and centralized settings.
* ✨ Updated dashboard widget information and output.
* ✨ Added Email Two-Factor Authentication as default.
* 🐛 Fixed login errors.

## 3.0.5
* 🔖 Bump Version of Plugin

## 3.0.4
* 🐛 Add Guard Clauses to Lock Down Logging functions

## 3.0.3
* 🐛 Disable data generation entirely.

## 3.0.2
* 🐛 Disabled Pagespeed scores because of long API loading times

## 3.0.1
* 🐛 Bug fix with error log in 2FA

## 3.0.0
* ✨ Add Slack Integration + Notifications
* ✨ Add Data API Creation
* ✨ Add 2FA for All User Roles

## 2.1.0
* ✨ Add query parameter bypass when access block is place.
* 🐛 Bug fix fatal error with failed login log when using incorrect password.
* 🐛 Bug fix custom login endpoint broken when using standard permalinks.

## 2.0.2
* 🐛 Bug fix for ActionScheduler error
* 🐛 Bug fix for environment type form always displaying
* 🐛 Bug fix for environment type logic being set

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
* 🐛 Bugfix for setup class on activation.

## 1.7.0
* ✨ Added support for Codespaces.
* 🐛 Fixed some small bugs.

## 1.6.0
* ✨ Adds plugin/theme update warnings for production sites.

## 1.5.0
* ✨ Feature - Add WP_ENVIRONMENT_TYPE logic.

## 1.4.0
* 🐛 Bugfix - Password protected page redirect.

## 1.3.0
* ✨ Add dev site checklist
* 🐛 Bugfix Jira projects/users loading

## 1.2.0
* ✨ Added an admin panel for Jira settings.
* ✨ Added a dashboard widget for Built Mighty developers.
* ✨ Added a dashboard widget for Built Mighty clients.
* ✨ Added a setup class for development environments.
* ✨ Added a speed class for production sites.
* ✨ Added a security class for production sites.

## 1.1.0
* ✨ Added email disabling functions.
* ✨ Added Action Scheduler disabling functions.
* ⚡️ Updated method for access restrictions/access.
* ⚡️ Updated documentation.
* ♻️ Refactored plugin structure.
