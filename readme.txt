=== MDS WooCommerce Integration ===
Contributors: mustydungeon
Tags: woocommerce, multisite, music, releases, sync
Requires at least: 7.0
Tested up to: 7.x
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Extends MDS Release Manager with per-Artist multisite WooCommerce store targeting, product linking, selective drift sync, and Single Product Page block injection.

== Description ==

MDS WooCommerce Integration connects **MDS Release Manager** with one or more WooCommerce stores across a WordPress Multisite network (or single site) while keeping Store Admins in full control of their products:

* **Per-Artist WooCommerce Target Stores:** Automatically detects active WooCommerce stores across your multisite network (cached with stampede protection) and lets you assign target stores directly on each **Artist** in the Hub, with batched **Sync Artist Releases** support.
* **Accessible Store Cards & Draft Seeding:** Search existing WooCommerce products (published or draft) and link/unlink them from the Hub Release editor, or seed a new **Draft** product pre-filled with the Release's title, description, UPC/GTIN, SKU, and virtual cover art.
* **Direct Release–Product Context Resolution:** Explicitly linked products resolve directly to their linked shadow Release via `_mds_local_product_id` (100% immune to UPC or SKU changes), with unlinked products falling back to WooCommerce GTIN/UPC (`_global_unique_id`) only.
* **Release-to-Product Drift Detection & Selective Sync:** Never auto-overwrites store products on Release save. Instead, Store Cards display an **Out of sync** badge when UPC/GTIN, Cover Art, or Product Title differ, letting admins selectively push approved fields (offering **Also update SKU** only when the product's SKU still matches the previous UPC, never clobbering custom SKUs).
* **Single Product Page Block Injection:** Configure where **Release Meta**, **Release Links**, **Release Player**, or companion MDS blocks appear on Single Product pages under **Releases > Settings > WooCommerce Integration** (visible only on WooCommerce-active sites), supporting both Block Themes and Classic WooCommerce themes.

== Installation ==

1. Upload the `mds-woocommerce` folder to `/wp-content/plugins/`, or upload the release ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate **MDS Release Manager** and **MDS WooCommerce Integration** (or Network Activate on Multisite).
3. On the Hub site, open any **Artist** and select one or more auto-detected **WooCommerce Target Stores** (existing Artists are automatically backfilled from legacy network settings).
4. On any WooCommerce store site, go to **Releases > Settings > WooCommerce Integration** to customize Single Product Page block placements.

== Frequently Asked Questions ==

= Does this plugin require MDS Release Manager? =

Yes. All admin screens, REST endpoints, and synchronization hooks remain dormant unless MDS Release Manager (`2.0.0+`) is active.

= Will editing a Release automatically overwrite changes a Store Admin made to a WooCommerce product? =

No. Updating a Release never auto-overwrites linked products. If UPC/GTIN, Cover Art, or Title differ, the Release's Store Card displays an **Out of sync** checklist so you can choose exactly which fields to push.

= What happens if I change a Release's UPC after linking a product? =

Injected Single Product Page blocks continue working immediately because explicitly linked products resolve directly by their `_mds_local_product_id` link rather than SKU. You can then push the updated GTIN (and optionally SKU, if it still matched the old UPC) from the Store Card.

= Can I override the admin templates in my theme? =

Yes. Place custom template overrides inside your theme at `mds-woocommerce/<template-name>.php`.

== Changelog ==

= 2.0.0 =
* Renamed plugin slug and text domain to `mds-woocommerce` with full Composer PSR-4 architecture (`MDS\WooCommerce\`), `uninstall.php`, and `wpml-config.xml`.
* Replaced global network store checkboxes with **Per-Artist WooCommerce Target Stores** (`_mds_wc_target_stores`), cached network store auto-detection with stampede protection, and batched **Sync Artist Releases** (`POST /mds-wc/v1/artists/{id}/sync-releases`).
* Added **Direct Release–Product Context Resolution** (`mds_rm_release_context_post`) via `_mds_local_product_id` (immune to UPC/SKU changes) and dropped numeric SKU fallback for unlinked products (GTIN-only).
* Added **Release-to-Product Drift Detection & Selective Sync** (`ProductDrift`, `POST /mds-wc/v1/sync-product`) with an **Out of sync** Store Card badge, per-field checklist (UPC/GTIN, Cover Art, Title), server-side custom-SKU protection, duplicate-SKU collision checks, and `wc_product_meta_lookup` synchronization.
* Restricted the **Releases > Settings > WooCommerce Integration** tab to WooCommerce-active sites only, and updated fresh-activation default placements (**Release Meta** below title; **Release Links** with *Hide Buy Now* enabled + **Release Player** below short description).

= 1.0.1 =
* Adopted a fire-and-forget **Seed & Hand Off** architecture: replaced `save_post` product overwriting with instant REST linking/unlinking (`/link`) and a compact native `<dialog>` Create Product modal (`/generate`, `/taxonomies`) defaulting to `Draft` status.
* Replaced legacy Select2 CDN dropdowns with accessible Store Cards, keyboard-navigable live product search, and `wp.a11y.speak()` status announcements.
* Added delegated Hub-to-Spoke permission handling (`mds_wc_user_can_manage_spoke_product`) so Hub editors without spoke accounts can link or seed Draft products.
* Unified `mds_rm_wc_integrated_sites` and legacy `mds_rm_woo_sites` in `ReleaseMeta` sync targets and product-page injection.
* Migrated plugin classes to Composer PSR-4 autoloading under `MDS\WooCommerce\` (`src/`) with WordPress 7.0+ / PHP 8.0+ / WPCS compliance.

= 0.9.0 =
* Initial release extracted from MDS Release Manager.
