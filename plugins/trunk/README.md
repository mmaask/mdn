# How to LIVE

1. Push to main.
2. Notify PM / Oliver Matt <oliver@modena.ee> from Modena.

# How to set up local (dev) environment

This plugin uses a very vanilla/default WordPress setup with WooCommerce and Storefront as a theme for
testing/development purposes.

Dev site @ https://dev.modena.codelight.ninja/

If you want to see changes propagated to the dev server, you will have to manually SSH in and do git pull in the plugin
folder.

1. Get all the site files from dev server `/srv/modena/app/` and create new local site using Laragon / Valet.
2. Set up appropriate local DB connection details in `wp-config.php`. You will need to make a new database beforehand.
3. There is a database dump `db.sql` in the root directory which you can import by running `wp db import db.sql`. Ensure
   you are running it in the local root directory.
4. In order for URLs to work properly locally, you will have to switch the dev URLs to your own local ones. Do this
   using WP CLI, example: `wp search-replace 'https://dev.modena.codelight.ninja' 'http://modena.test'`