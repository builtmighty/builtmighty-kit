const mix = require('laravel-mix');

mix.setPublicPath('public');

// Kit admin app (Kit screens only) and the small wp-admin layer
// (dashboard widget, admin bar chip, environment banner, notices).
mix.js('assets/js/kit-admin.js', 'public/js')
   .js('assets/js/kit-wp.js', 'public/js')
   .js('assets/js/kit-login.js', 'public/js')
   .js('assets/js/kit-passkeys.js', 'public/js')
   .js('assets/js/updates.js', 'public/js')
   .sass('assets/css/kit-admin.scss', 'public/css')
   .sass('assets/css/kit-wp.scss', 'public/css')
   .sass('assets/css/kit-login.scss', 'public/css')
   .sass('assets/css/updates.scss', 'public/css')
   .options({
       processCssUrls: false
   })
   .sourceMaps();

// Enable versioning in production
if (mix.inProduction()) {
    mix.version();
}
