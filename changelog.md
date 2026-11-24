# Changelog

All notable changes to **MDS WooCommerce Integration** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Per-Artist WooCommerce Target Stores** (`MDS\WooCommerce\Support\StoreRegistry`, `templates/artist-stores-box.php`, `src/Admin/artist-stores.js`): moved WooCommerce target-store assignment from Network Settings into a multi-select **WooCommerce Target Stores** meta box on the Hub **Artist** edit screen (`_mds_wc_target_stores` post meta).
- **Cached Network WooCommerce Store Auto-Detection** (`StoreRegistry::get_available_stores()`): automatically discovers active WooCommerce sites across the multisite network (zero blog switches when WooCommerce is network-activated), caches results in the `mds_wc_network_stores` network transient (1-hour TTL), and invalidates automatically on WooCommerce activation/deactivation and site lifecycle hooks.
- **Batched Artist Release Sync & Store Refresh REST Endpoints**: added `POST /mds-wc/v1/artists/{id}/sync-releases` (cursor-paged 10 releases per batch with `wp.a11y.speak()` status updates) and `POST /mds-wc/v1/stores/refresh`.
- **Unassigned Store Cards**: existing `_mds_linked_products` entries for stores later unchecked on an Artist remain visible on Release Store Cards with an **Unassigned Store** badge and working **Unlink** action, while blocking new product links/creates on unassigned stores.
- **One-Time Idempotent Artist Backfill** (`StoreRegistry::maybe_backfill_artist_stores()`): automatically copies legacy `mds_rm_wc_integrated_sites` / `mds_rm_woo_sites` values onto existing Hub Artists (`mds_wc_artist_stores_migrated`) without destroying legacy `wp_sitemeta` options.
- Standalone PHP unit test suite in `tests/store-registry.php`.

### Changed
- Scoped `mds_rm_sync_target_sites` (`ReleaseMeta::sync_targets()`) per Artist/Release using request-memoized UUID-to-Artist lookups to eliminate N+1 queries during batch syncs and prevent broadcasting Releases to unrelated WooCommerce stores.
- Memoized frontend single-product injection eligibility (`ReleaseMeta::is_eligible_store_site()`) per request using `Plugin::is_woocommerce_active_on_current_site()`, eliminating repeated `get_site_option()` calls inside `render_block`.
- Added `'no_found_rows' => true` and `'update_post_term_cache' => false` to `RestApi::search_products()` and enforced two-layer security in `Plugin::user_can_manage_spoke_product()` (active WC store + Artist assignment, or existing link for `DELETE /link`).
- Wired `do_action( 'mds_rm_purge_cache', ... )` into WooCommerce product link/unlink/create (`woocommerce_link`), Artist store assignment changes (`woocommerce_artist_stores`), and Artist batch syncs (`woocommerce_artist_sync`) so Release Manager's `MDS_RM_Cache` coalesces and flushes affected Hub and Spoke store caches automatically.

### Removed
- Retired the **Network Admin -> Settings -> Release Manager -> WooCommerce** tab (`src/Admin/NetworkSettings.php`, `src/Admin/network-settings.js`, `templates/network-settings-*.php`, `POST /mds-wc/v1/network-settings`, and `tests/settings-tab.php`).

## [1.0.1] - 2026-11-23

### Added
- Composer PSR-4 architecture under `MDS\WooCommerce\` (`src/`) with centralized `Plugin` service registrar.
- Fire-and-forget **Seed & Hand Off** REST endpoints: `POST /mds-wc/v1/link`, `DELETE /mds-wc/v1/link`, and `GET /mds-wc/v1/taxonomies` (plus `mds-woo/v1` backward-compatible aliases).
- Ergonomic WooCommerce Store Cards in the Release editor with status badges (`Published`, `Draft — Pending Store Review`), `View in Store` / `Edit in Store` links, accessible live-search combobox, and a native `<dialog>` Create Product modal pre-seeded with Release title, excerpt (Short Description), full description, UPC/SKU, and featured image.
- **WooCommerce Integration** site settings tab under **Releases -> Settings** (`SiteSettings`, `mds_rm_site_settings_tabs`, `POST /mds-wc/v1/settings`, `mds_wc_product_injection`) mirroring the Bandcamp Integration tab pattern with **dynamic MDS block auto-discovery** (`WP_Block_Type_Registry` `mds-release-manager` category — automatically listing Core Release Manager blocks, active Bandcamp blocks, and any future MDS extension blocks, plus a Custom Shortcode fallback and `mds_wc_injectable_blocks` filter), 9 single-product placement slots (including *Below Product Title*, *Below Product Gallery*, and *Above Product Description / Tabs*), a clean 2-column **Visual Product Page Blueprint** with **Enabled toggles + Up/Down (`↑`/`↓`) steppers** and **Gutenberg-style blue insertion line Drag & Drop**, and a collapsible **Live Preview** panel (closed initially) with product selector, Desktop/Tablet/Mobile viewport toggles, and nonce-verified unsaved placement previews.
- Dual **Block Theme (`render_block`) + Classic WooCommerce theme** single-product injection in `ReleaseMeta`, fixing missing `[release-meta]` and `[release-links]` output on FSE themes like Twenty Twenty-Five (defaulting Release Links to **Below Product Description / Tabs**).
- Automatic seeding of the `all` (`All` — *"All supported media formats."*, icon `fas fa-boxes-stacked`) `release_media_format` term on the Hub and live wiring of `#mds_use_linked_product_btn` into the Release Manager Multi-Format Buy Now repeater using the store name and linked published product URL.
- Delegated Hub-to-Spoke permission check (`Plugin::user_can_manage_spoke_product()` and `mds_wc_user_can_manage_spoke_product` filter) so Hub editors without spoke-site accounts can link or seed Draft products.
- Centralized Release Manager cache integration (`Plugin::request_cache_purge()` firing `mds_rm_purge_cache` with multisite `blog_id` support on site settings saves, network store toggles, and product link/unlink/generate actions, plus `mds_rm_after_cache_purge` clearing WooCommerce product transients on `manual` and `woocommerce_settings` purges).
- `WC_Product_Simple` CRUD seeding with `upc_ean` written to both SKU (`_sku`) and WooCommerce's native GTIN, UPC, EAN, or ISBN field (`_global_unique_id`), `MDS_RM_Virtual_Media` thumbnail replication, audit meta (`_mds_wc_seeded_*`), and WooCommerce transient/cache clearing.
- `@wordpress/scripts` configuration (`package.json`), `.gitignore`, and full documentation (`README.MD`, `readme.txt`, `changelog.md`, `.agents/technical-blueprint.md`).

### Changed
- Migrated all legacy `MDS_Woo_*` / `MdsWoo*` classes and procedural wrapper files into strict-typed (`declare(strict_types=1);`), PHP 8.0-compatible PSR-4 classes under `src/`.
- Unified `mds_rm_wc_integrated_sites` and legacy `mds_rm_woo_sites` via `Plugin::get_integrated_sites()` so configured stores always participate in shadow broadcasts and product-page Release Meta injection.
- Removed the restrictive `product_tag = release` search filter so all existing published and draft store products are searchable.
- Renamed plugin constants (`MDS_WOO_*` -> `MDS_WC_*`), procedural helpers (`mds_woo_*` -> `mds_wc_*`), meta box ID (`mds_woo_settings` -> `mds_wc_settings`), script/style handles, CSS classes (`mds-woo-*` -> `mds-wc-*`), and inline config globals (`wp_add_inline_script()` replacing `wp_localize_script()`).
- Updated plugin headers and `phpcs.xml.dist` for WordPress 7.0+, PHP 8.0+, text domain `mds-woocommerce-integration`, and WooCommerce product capabilities.
- Improved Section 508 / WCAG 2.1 AA accessibility with native `<dialog>` focus management, explicit form labels, ARIA combobox/listbox semantics, inline admin notices, and `wp.a11y.speak()` announcements.
- Preserved legacy persisted keys (`mds_rm_wc_integrated_sites`, `mds_rm_woo_sites`, `mds_rm_woo_injection`, `_mds_linked_products`, `_mds_local_product_id`) for zero-breakage upgrades.

### Removed
- `SaveHandler` (`mds_rm_save_custom_meta` overwrite hook) — Store Admins now retain full ownership of WooCommerce products after creation.
- External `cdnjs.cloudflare.com` Select2 CDN dependency.
- Legacy `includes/class-mds-woo-*.php` classes, redundant procedural wrapper files, and unmigrated root `js/` and `css/` directories.

## [0.9.0]

### Added
- Initial standalone WooCommerce network integration extracted from MDS Release Manager.
