=== MDS WooCommerce Integration ===
Contributors: mustydungeon
Tags: woocommerce, multisite, music, releases, sync
Requires at least: 7.0
Tested up to: 7.x
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends MDS Release Manager with multisite WooCommerce product linking, Draft product seeding, and Release Meta injection.

== Description ==

MDS WooCommerce Integration connects MDS Release Manager with WooCommerce stores across a WordPress multisite network using a clean **Seed & Hand Off** workflow:

* Configure which WooCommerce sites participate in product linking from the Release Manager Network Settings tab.
* Search existing WooCommerce products (published or draft) and link or unlink them directly from Store Cards in the Release editor.
* Seed new **Draft** WooCommerce products from a compact modal pre-filled with the Release's title, description, UPC/SKU, and featured artwork—plus optional short description, price, categories, and tags—without overwriting Store Admin edits later.
* Automatically broadcast product links during hub-and-spoke sync and inject MDS Release Meta output on single WooCommerce product pages.

== Installation ==

1. Upload the `mds-woocommerce-integration` folder to `/wp-content/plugins/`.
2. Run `composer install` inside the plugin directory if installing from source.
3. Activate **MDS Release Manager** and **MDS WooCommerce Integration** through the WordPress Plugins screen (or Network Activate on multisite).
4. Configure integrated WooCommerce stores in **Network Admin -> Settings -> Release Manager -> WooCommerce**.

== Frequently Asked Questions ==

= Does this plugin require MDS Release Manager? =

Yes. All admin screens, REST endpoints, and synchronization hooks remain dormant unless MDS Release Manager is active.

= Will editing a Release overwrite changes a Store Admin made to a WooCommerce product? =

No. Once a product is created or linked, the Store Admin owns the product. Updating a Release never overwrites the store product.

= Can I override the admin templates in my theme? =

Yes. Place custom template overrides inside your theme at `mds-woocommerce-integration/<template-name>.php`.

== Changelog ==

= 1.0.1 =
* Adopted a fire-and-forget **Seed & Hand Off** architecture: replaced `save_post` product overwriting with instant REST linking/unlinking (`/link`) and a compact native `<dialog>` Create Product modal (`/generate`, `/taxonomies`) defaulting to `Draft` status.
* Replaced legacy Select2 CDN dropdowns with accessible Store Cards, keyboard-navigable live product search, and `wp.a11y.speak()` status announcements.
* Added delegated Hub-to-Spoke permission handling (`mds_wc_user_can_manage_spoke_product`) so Hub editors without spoke accounts can link or seed Draft products.
* Unified `mds_rm_wc_integrated_sites` and legacy `mds_rm_woo_sites` in `ReleaseMeta` sync targets and product-page injection.
* Migrated plugin classes to Composer PSR-4 autoloading under `MDS\WooCommerce\` (`src/`) with WordPress 7.0+ / PHP 8.0+ / WPCS compliance.

= 0.9.0 =
* Initial release extracted from MDS Release Manager.
