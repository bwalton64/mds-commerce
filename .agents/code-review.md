# Code Review — MDS WooCommerce Integration (`mds-woocommerce`)

- **Date:** 2026-11-25
- **Scope:** Full plugin repository (18 PHP files, 3 JS files; excluding `vendor/`, `tests/`)
- **Summary:** `0 Critical | 0 High | 2 Medium | 1 Low`

## Executive Summary

`mds-woocommerce` is in strong release shape after its recent hardening pass (`StoreRegistry` stampede lock + stale-while-revalidate fallback, `uninstall.php`, `wpml-config.xml`, Hub screen scoping, direct `mds_rm_release_context_post` resolution, and `ProductDrift`). No **Critical** or **High** blockers were found. Two **Medium** edge cases were identified in the new `ProductDrift::apply()` / `POST /sync-product` cross-blog fallback path (when WooCommerce is not loaded on the Hub during `switch_to_blog()`), plus one **Low** UX polish item for title typography comparison.

## Findings Checklist

### Critical / High Priority

- *None.*

### Medium / Low Priority

- [x] **CR-001 [Medium] — Direct `_sku` / `_global_unique_id` meta fallback in `ProductDrift::apply()` bypasses WooCommerce lookup table & duplicate SKU check**
  - **Location:** `src/Support/ProductDrift.php:225-245`
  - **Evidence:** When `POST /mds-wc/v1/sync-product` runs from the Hub (where WooCommerce is not active), `switch_to_blog( $store_id )` switches DB tables without loading WooCommerce classes, so `ProductDrift::apply()` writes `_sku` and `_global_unique_id` via `update_post_meta()`. This neither checks whether another product on the spoke already owns that `_sku` nor updates `{$wpdb->prefix}wc_product_meta_lookup`, which WooCommerce admin SKU search and REST product queries read.
  - **Fix:** In the non-WC fallback branch, check for an existing non-trashed product with the same `_sku` before updating `_sku` (adding a warning on collision instead of creating duplicate SKUs), and update the `sku` / `global_unique_id` columns in `{$wpdb->prefix}wc_product_meta_lookup` if that table exists.

- [x] **CR-002 [Medium] — `POST /sync-product` trusts client-supplied `sku` field without re-verifying that SKU still matched the old UPC**
  - **Location:** `src/Rest/RestApi.php:668-680` & `src/Support/ProductDrift.php:239-243`
  - **Evidence:** The UI only shows the "Also update SKU" checkbox when `sku_option.available` is true, but the REST endpoint accepts `'sku'` in `fields` unconditionally without verifying server-side that the product's current SKU is numeric/matching the previous GTIN.
  - **Fix:** Re-check the `sku_matches_old_upc` condition server-side (or require an explicit `force_sku` flag) before overwriting a custom non-UPC SKU.

- [x] **CR-003 [Low] — Title drift comparison flags typographic ellipsis differences (`...` vs `…`)**
  - **Location:** `src/Support/ProductDrift.php:129-140`
  - **Evidence:** Live testing showed `"What the .... (Gold)"` vs `"What the …. (Gold)"` flagged as title drift when WordPress/WooCommerce typographic formatting (`wptexturize` or user input) converted `...` to `…`.
  - **Fix:** Optionally normalize Unicode ellipsis (`…` → `...`) and curly quotes when comparing titles for drift (while still syncing the exact Release title if genuinely different).
