# Code Review Report — MDS WooCommerce Integration

- **Date:** 2026-11-25
- **Scope:** Full repository (`mds-woocommerce.php`, `includes/`, `src/`, `templates/`, `wpml-config.xml`, `composer.json`, `package.json`; excluding `vendor/`, `node_modules/`, `build/`)
- **Summary:** 0 Critical | 1 High | 3 Medium | 2 Low

## Executive Summary

**MDS WooCommerce Integration** is in strong overall shape: PHP 8.0 compatibility is clean (zero PHP 8.1+ syntax leakage), every `switch_to_blog()` call is properly wrapped in `try / finally { restore_current_blog(); }`, all REST endpoints define explicit `permission_callback`s, and the codebase passes WPCS (`17 / 17`). The top priority is fixing a concurrency edge case in `StoreRegistry::get_available_stores()` where a transient stampede lock returns an empty store list (`[]`) that can silently clear an Artist's `_mds_wc_target_stores` on concurrent save, followed by adding `uninstall.php`, scoping admin asset enqueues to `post` screens on the Hub, and removing one orphaned legacy template.

## Findings Checklist

### Critical / High Priority

- [x] **CR-001** `[Runaway State / Concurrency]` — `StoreRegistry` stampede lock returns `[]`, risking silent data loss on concurrent Artist save
  - **Location:** `src/Support/StoreRegistry.php:194-256`
  - **Issue:** When `mds_wc_network_stores` is missing and `mds_wc_network_stores_lock` is held by a concurrent request, `get_available_stores()` immediately returns `array()`. Callers cannot distinguish "lock held" from "zero WooCommerce stores exist": if `MetaBoxes::save_artist_stores()` runs during that window, `sanitize_store_ids()` intersects submitted store IDs against `[]` and overwrites `_mds_wc_target_stores` with `[]`. In addition, if an exception occurs inside the `foreach ( $sites )` loop, `mds_wc_network_stores_lock` is not deleted in a `finally` block (leaving the lock stuck for 30 seconds).
  - **Recommended Fix:** Wrap the rebuild in `try { ... } finally { delete_site_transient( self::NETWORK_TRANSIENT_KEY . '_lock' ); }`. Keep a longer-lived fallback copy (or brief retry loop with `usleep( 100000 )` up to 3 attempts) so concurrent callers receive the last-known store list rather than an empty array.

### Medium / Low Priority (Storage Parity, Performance, Security & Cleanup)

- [x] **CR-002** `[Storage Parity]` — Missing `uninstall.php` and stale `wpml-config.xml` option key
  - **Location:** Root (`uninstall.php` missing) and `wpml-config.xml:3`
  - **Issue:** No `uninstall.php` exists to clean up `mds_wc_product_injection`, `mds_wc_artist_stores_migrated`, `mds_wc_network_stores`, or `mds_wc_network_stores_lock` when the plugin is uninstalled. Separately, `wpml-config.xml` still registers the retired `mds_rm_wc_integrated_sites` network option (which holds non-translatable integer blog IDs in `wp_sitemeta`).
  - **Recommended Fix:** Add `uninstall.php` (inside a static closure, guarded by `WP_UNINSTALL_PLUGIN`) to delete plugin options/transients on uninstall, and remove `mds_rm_wc_integrated_sites` from `wpml-config.xml`.

- [x] **CR-003** `[Performance]` — Admin assets enqueue on CPT list tables and Spoke screens; `get_preview_products()` missing query flags
  - **Location:** `src/Admin/Assets.php:48-61` and `src/Admin/SiteSettings.php:564-572`
  - **Issue:** `Assets::enqueue()` checks `'release' === $screen->post_type` and `'artist' === $screen->post_type` without checking `'post' === $screen->base`, loading `product-linker.js`, `artist-stores.js`, and `admin.css` on list tables (`edit.php`) where no meta boxes exist. `enqueue_product_linker_script()` also omits the Hub check (`ExtensionApi::is_hub()`), so it loads on Spoke release screens. In `SiteSettings::get_preview_products()`, `get_posts()` omits `'no_found_rows' => true` and `'update_post_term_cache' => false`.
  - **Recommended Fix:** Require `'post' === $screen->base` (and `ExtensionApi::is_hub()` for `release`/`artist` screens) in `Assets::enqueue()`, and add `'no_found_rows' => true` + `'update_post_term_cache' => false` to `SiteSettings::get_preview_products()`.

- [x] **CR-004** `[Compatibility / i18n]` — Missing `declare(strict_types=1);`, `MDS_WC_BASENAME`, and `load_plugin_textdomain()`
  - **Location:** `mds-woocommerce.php:1-43` and `includes/helpers.php:1-40`
  - **Issue:** Neither bootstrap file declares `declare(strict_types=1);`, `MDS_WC_BASENAME` is not defined, and `load_plugin_textdomain( 'mds-woocommerce', ... )` is never called on `init`, preventing bundled `/languages/*.mo` files from loading on private/non-WordPress.org deployments.
  - **Recommended Fix:** Add `declare(strict_types=1);`, define `MDS_WC_BASENAME`, and hook `load_plugin_textdomain()` onto `init` inside `Plugin::init()`.

- [x] **CR-005** `[Orphaned Code]` — Orphaned `network-settings-site.php` template and unused `Config.php` symbols
  - **Location:** `templates/network-settings-site.php`, `src/Config.php:28,54-76`
  - **Issue:** `templates/network-settings-site.php` is left over from the retired `NetworkSettings` tab and has zero references across the repository. In `src/Config.php`, `OPTION_SETTINGS` still points to the retired `'mds_rm_wc_integrated_sites'` key, `get_default_labels()['network_tab']` references the retired tab, and neither `Config::transient_key()` nor `Config::get_default_labels()` is referenced in `src/`.
  - **Recommended Fix:** Delete `templates/network-settings-site.php` and update `src/Config.php` constants to match active keys (`mds_wc_product_injection`, `_mds_wc_target_stores`, `mds_wc_network_stores`) or wire them into `SiteSettings` / `StoreRegistry`.

- [x] **CR-006** `[Security / Hardening]` — `POST /mds-wc/v1/stores/refresh` lacks Hub-only guard
  - **Location:** `src/Rest/RestApi.php:154-164`
  - **Issue:** The `/stores/refresh` permission callback only checks `current_user_can( 'edit_posts' )`, allowing users on Spoke sites (not just the Hub) to flush the network-wide `mds_wc_network_stores` transient and trigger a full multisite `switch_to_blog()` scan.
  - **Recommended Fix:** Restrict `/stores/refresh` to Hub requests (`ExtensionApi::is_hub()`) and `current_user_can( 'edit_posts' )` (matching `/artists/{id}/sync-releases`).
