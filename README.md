<p align="center" style="font-size:42px !important;">🚀 Built Mighty Kit</p>

## About
This plugin is the all around kit for both development sites and production sites. From defining a new login endpoint within wp-config, to protect wp-login from bot attacks, to disabling emails from development sites. This plugin should provide you with the tools for a happy, healthy environment. If you have additional tools you'd like to see add, please either open an issue or contact one of the lead devs by taggin '@lead-dev-team' within Slack.

## Installation on All Sites
It is highly recommended that no matter the site, the WP_ENVIRONMENT_TYPE variable is set within wp-config.php. The plugin will automatically detect a development site via the URL, but sites can also be placed into development mode using the environment variable. Set the variable to: local, development, or staging, to set the plugin in development mode. Set the environment variable to production, to set the plugin to production mode.

<details>
  <summary>Installation on a Development Site</summary>
  
  ### What is a development site?
  Development sites are detected automatically by the plugin, by parsing whether or not the URL is a builtmighty.com or mightyrhino.net URL. Development sites can also be defined by setting WP_ENVIRONMENT_TYPE to either: local, development, or staging.

  If a development site is detected, the following takes place:

  1. On activation:
     * Disables external connections via WP_HTTP_BLOCK_EXTERNAL.
     * Disables indexing for SEO purposes.
     * Disables bad plugins within development environments.
     * Updates customer emails from user@email.com to user.RANDOMSTRING@builtmighty.com.
  2. While running, the plugin does the following:
     * Adds a dashboard development widget with: server data, GitHub repo data, as well as a list of any disabled plugins.
     * Disables the Action Scheduler.
     * Disables emails by setting the 'to' address to developers@builtmighty.com.
     * Disables access to the default WordPress admin when `BUILT_ENDPOINT` is set. Example: `define( 'BUILT_ENDPOINT', 'access' );`.
     * Disables access to WordPress, for non-logged in users when `BUILT_ACCESS` is set to true. Example: `define( 'BUILT_ACCESS', true );`.

  The plugin also does some other items, which it also does on production sites as well.

</details>

<details>
  <summary>Installation on a Production Site</summary>
  
  ### What is a production site?
  Production sites are any live, on the web, available sites for customers. For production sites, the plugin does the following:

  1. Access:
     * Disables access to the default WordPress admin when `BUILT_ENDPOINT` is set. Example: `define( 'BUILT_ENDPOINT', 'access' );`.
  2. While running, the plugin does the following:
     * Adds a dashboard development widget for Built Mighty developers with: server data, GitHub repo data, as well as a list of any disabled plugins.
     * Adds a dashboard widget for Built Mighty clients with: a welcome message, a Jira issue creation form, project manager contact form, and GitHub repo data. 
     * Adds some security enhancements like: diables XML-RPC, removes WordPress version numbers, removes specific login errors, and removes user enumeration.
     * Adds some speed enhancements like: dequeues emojis, updates heartbeat timing, updates post revisions, changes action scheduler retention period, and removes junk dashboard widgets.
  
</details>

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