<p align="center" style="font-size:42px !important;">🚀 Built Mighty Kit</p>

# Installation
On initial plugin installation and activation, nothing will happen until constants are defined within wp-config, except for the email/action scheduler settings on builtmighty.com or mightyrhino.net development sites.

## Note
In order for the login portion of the plugin to work, the WordPress site must be using some form of "pretty" permalinks.

## About
This plugin allows you to define a new login endpoint within wp-config to protect wp-login from bot attacks. It can also be used on development sites to stop bots from visiting. Additionally, it disables email sends by settings the "to" email address to developers@builtmighty.com, on Built Mighty development sites. It also disables the Action Scheduler queue, if WooCommerce is active.

### Login Endpoint

To define a new login endpoint, and conversely redirect users back to the home URL, if they visit wp-login.php, set the following within wp-config.

```PHP
define( 'BUILT_ENDPOINT', 'your-endpoint' );
```

### Access

If you want to block access to the site for non-logged in users, which will redirect them to builtmighty.com, you can set the following within wp-config.

```PHP
define( 'BUILT_ACCESS', true );
```

### Emails

By default, if this plugin is active on a site that's either on a builtmighty.com or mightyrhino.net URL, emails will automatically be set to the developers email account. If you would like to enable email on these sites, set the following within wp-config.

```PHP
define( 'BUILT_ENABLE_EMAIL', true );
```

If you would like to disable email on a site that isn't builtmighty.com or mightyrhino.net, you can also define that within wp-config.

```PHP
define( 'BUILT_DISABLE_EMAIL', true );
```

### Action Scheduler

Like email disabling, by default, the Action Scheduler queue is disabled on a site that's either on a builtmighty.com or mightyrhino.net URL. If you would like to enable the Action Scheduler on these sites, set the following within wp-config.

```PHP
define( 'BUILT_ENABLE_AS', true );
```

And if you would like to disable the Action Scheduler on a site that isn't builtmighty.com or mightyrhino.net, you can also define that within wp-config.

```PHP
define( 'BUILT_DIABLE_AS', true );
```

## 1.1.0

* Added email disabling functions.
* Added Action Scheduler disabling functions.
* Updated method for access restrictions/access.
* Updated documentation.
* Refactored plugin structure.