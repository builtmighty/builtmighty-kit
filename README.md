<p align="center" style="font-size:42px !important;">🚀 Built Mighty Login</p>

## About
This plugin allows you to define a new login endpoint within wp-config to protect wp-login from bot attacks. It can also be used on development sites to stop bots from visiting. Once installed and activated, set the following within wp-config.php.

```PHP
define( 'BML_ENDPOINT', 'your-endpoint' );
```

This will redirect anyone that tries to access wp-login.php, back to the site's homepage. Only visiting /your-endpoint will work properly. To restrict access on development sites from bots, you can also define the following within wp-config.php, but with options. If you set:

```PHP
define( 'BML_ALLOWED', true );
```

Users can visit the site URL, example.com/?bml=true, and have a 24 hour cookie set to allow access to the site. You can also define allowed IPs, instead of setting the value to true, like so:

```PHP
define( 'BML_ALLOWED', [ '123.123.123.123' ] );
```

## 1.0.0
* Initial Release
