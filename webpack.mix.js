const mix = require('laravel-mix');

mix.setPublicPath('public');

mix.js('assets/js/main.js', 'public/js')
   .js('assets/js/admin.js', 'public/js')
   .js('assets/js/login-security.js', 'public/js')
    .js('assets/js/updates.js', 'public/js')
   .sass('assets/css/style.scss', 'public/css')
   .sass('assets/css/login-security.scss', 'public/css')
   .sass('assets/css/security.scss', 'public/css')
   .sass('assets/css/admin.scss', 'public/css')
   .sass('assets/css/updates.scss', 'public/css')
   .options({
       processCssUrls: false
   })
   .sourceMaps();

// Enable versioning in production
if (mix.inProduction()) {
    mix.version();
}
