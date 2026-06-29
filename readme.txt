=== Excellink Product Feeds ===
Contributors: excellink
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate Google Shopping & Facebook Catalog XML feeds from WooCommerce products.

== Description ==

Excellink Product Feeds automatically generates a single XML product feed compatible with both Google Merchant Center (Google Shopping) and Facebook Catalog Manager.

**Features:**

* Single XML feed works for both Google Shopping and Facebook Catalog
* Automatic feed regeneration via WP-Cron (hourly, twice-daily, daily, or weekly)
* Manual one-click feed regeneration from the admin
* Google product taxonomy category mapping
* Image sitemap generation for improved Google image crawling
* Built-in rate limiting and health monitoring
* Import/export settings for easy site migration
* Detailed activity logs

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/excellink-product-feeds/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Ensure WooCommerce is installed and active.
4. Go to **Product Feeds → Settings** to configure your feed.
5. Click **Regenerate Feed Now** to generate your first feed.
6. Copy the feed URL and paste it into Google Merchant Center and/or Facebook Catalog Manager.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. The plugin requires WooCommerce 7.0 or later to be installed and active.

= How often is the feed updated? =

You can set the schedule to hourly, twice-daily, daily, or weekly from the Settings screen. You can also regenerate the feed manually at any time.

= Where do I submit the feed URL? =

* **Google:** Merchant Center → Products → Feeds → Add Feed → Scheduled Fetch
* **Facebook / Instagram:** Commerce Manager → Catalog → Data Sources → Use a URL → Google Shopping feed format

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
