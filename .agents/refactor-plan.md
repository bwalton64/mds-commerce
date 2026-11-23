# MDS WooCommerce Integration — Refactoring Plan

> **Audit Scope:** Full read-only audit of `mds-woocommerce-integration` against `.agents/rules/project-config.md` and all active global WordPress rules (`wp-general.md`, `wp-php-wpcs.md`, `wp-code-docs.md`, `wp-frontend-scripts.md`, `wp-accessibility-508.md`, `wp-release-versioning.md`, `wp-testing-localwp.md`), plus cross-plugin contract verification against `mds-release-manager`.
>
> **Status:** No source files have been modified. Review each phase and confirm decisions on the flagged backward-compatibility items in Phase 4 before execution.

---

## Target Configuration Summary

Authoritative values resolved from `.agents/rules/project-config.md`:

| Variable | Target Value | Current State in Repo |
| :--- | :--- | :--- |
| **Plugin Name** | `MDS WooCommerce Integration` | `MDS Release Manager WooCommerce Integration` (`mds-woocommerce-integration.php`) |
| **Plugin Slug / Folder** | `mds-woocommerce-integration` | `mds-woocommerce-integration` (matches) |
| **Text Domain** | `mds-woocommerce-integration` | `mds-woocommerce-integration` in PHP calls, but `phpcs.xml.dist` has `mds-bandcamp-integration` and many strings are untranslated |
| **PHP Root Namespace** | `MDS\WooCommerce\` | Configured in `composer.json` & `phpcs.xml.dist`, but `src/` does not exist yet (all classes are legacy global `MDS_Woo_*` / `MdsWoo*`) |
| **Global Class Prefix** | `MDS_WC_` | Mixed legacy global classes: `MDS_Woo_*` and `MdsWoo*` |
| **Function / Hook Prefix** | `mds_wc_` | `mds_woo_` across `includes/` and `templates/` |
| **Constant Prefix** | `MDS_WC_` | `MDS_WOO_` (`MDS_WOO_VERSION`, `MDS_WOO_PATH`, `MDS_WOO_URL`) |
| **Minimum WordPress** | `7.0` | Configured in `phpcs.xml.dist`; missing from plugin header and `readme.txt` |
| **Tested Up To WordPress** | `7.x` | Missing (`readme.txt` does not exist yet) |
| **Minimum PHP** | `8.0` | `>=8.0` in `composer.json` and `8.0-` in `phpcs.xml.dist`, but `7.4` in `mds-woocommerce-integration.php` |
| **Version** | Reconcile (`1.0.1` or `1.0.0` / `0.9.0`) | Mismatch: `composer.json` is `1.0.1`, while `mds-woocommerce-integration.php` is `0.9.0` |
| **LocalWP Site Path** | `C:\Users\Brian\Local Sites\bgw-dev\app\public` | N/A |
| **LocalWP Site URL** | `http://bgw-dev.local/` | N/A |

> **Note on Bandcamp Template Leftovers:** Both the refactoring prompt (`mds_bc_` / `MDS_BC_` / `mds-bandcamp-integration`) and `phpcs.xml.dist` (lines 6 and 49) contain leftover Bandcamp identifiers. This plan aligns strictly with `.agents/rules/project-config.md` (`MDS\WooCommerce\`, `MDS_WC_`, `mds_wc_`, `mds-woocommerce-integration`).

---

## Phase 1: Composer, Linters, Build Tooling & Repository Hygiene

Fix existing linter mismatches, align version numbers, add missing build/ignore config, and normalize line endings before moving PHP or JS/CSS files.

- [ ] **1.1 Verify & update `composer.json`**
  - Already configured with `"name": "mds/woocommerce-integration"`, `"type": "wordpress-plugin"`, `"require": { "php": ">=8.0" }`, PSR-4 `"MDS\\WooCommerce\\": "src/"`, WPCS (`^3.1`), `PHPCompatibilityWP` (`^2.1`), and `phpcs` / `phpcbf` scripts.
  - Reconcile `"version": "1.0.1"` with `mds-woocommerce-integration.php` (`0.9.0`) and upcoming `readme.txt`, `changelog.md`, and `package.json` per the 5-file version synchronization rule.
  - Optionally add `"files": ["includes/helpers.php"]` under `"autoload"` if keeping thin procedural helper wrappers (`mds_wc_is_active()`, `mds_wc_render_template()`).
  - Convert line endings from CRLF to LF (`\n`).
  - Run `composer install` and `composer dump-autoload` once `src/` is populated.
- [ ] **1.2 Fix copy-paste leftovers in `phpcs.xml.dist`**
  - Line 6 `<description>`: change `MDS Bandcamp Integration` -> `MDS WooCommerce Integration`.
  - Line 49 `WordPress.WP.I18n` `<element value="mds-bandcamp-integration"/>` -> `<element value="mds-woocommerce-integration"/>`.
  - Verify `WordPress.NamingConventions.PrefixAllGlobals` prefixes (`mds_wc_`, `MDS_WC_`, `MDS\WooCommerce`) pass cleanly under WPCS 3.1 when scanning `src/`, `includes/`, and `templates/`.
  - Convert line endings from CRLF to LF (`\n`).
- [ ] **1.3 Create `package.json` (and `@wordpress/scripts` pipeline)**
  - Add `"name": "mds-woocommerce-integration"` and synchronized `"version"`.
  - Add `@wordpress/scripts` in `devDependencies` with scripts: `build`, `start`, `lint:js`, `lint:css`, `format`.
  - *Block Metadata Note:* No `block.json` exists because this plugin currently registers no Gutenberg blocks (only admin screens, meta boxes, and WooCommerce product hooks). If blocks are added later, author them under `src/blocks/<block-name>/block.json` with `"apiVersion": 3` and `"textdomain": "mds-woocommerce-integration"`.
- [ ] **1.4 Create `.gitignore`**
  - Exclude `/vendor/`, `/node_modules/`, `/build/`, `.env`, and local debug logs.
- [ ] **1.5 Normalize CRLF (`\r\n`) files to LF (`\n`)**
  - Currently CRLF: `composer.json`, `phpcs.xml.dist`, `includes/class-mds-woo-release-meta.php`, `tests/settings-tab.php`, `.agents/rules/project-config.md`, and all 8 files in `templates/*.php`.
- [ ] **1.6 Update main plugin header & bootstrap (`mds-woocommerce-integration.php`)**
  - Update `Plugin Name:` from `MDS Release Manager WooCommerce Integration` to `MDS WooCommerce Integration`.
  - Update `Requires PHP:` from `7.4` to `8.0`.
  - Add `Requires at least: 7.0`, `Text Domain: mds-woocommerce-integration`, and (optionally) `Requires Plugins: mds-release-manager`.
  - Synchronize `Version:` header and `MDS_WC_VERSION` constant with `composer.json`.
  - Update `@package` to `MDS\WooCommerce` and format file with WPCS spacing/tabs.
  - Require `vendor/autoload.php` with a `file_exists()` guard (and an `admin_notices` fallback if missing).
  - Replace the 11 manual `require_once` lines and line 32 `add_action( 'init', array( 'MDS_Woo_Release_Meta', 'register' ) )` with `add_action( 'plugins_loaded', array( \MDS\WooCommerce\Plugin::class, 'init' ) );`.

---

## Phase 2: PHP Classes, PSR-4 Namespacing & Autoloading

Migrate all 5 legacy classes and 6 procedural wrapper/feature files from `includes/` into PSR-4 namespaced classes under `src/` with `declare(strict_types=1);`, PHP 8.0 native type declarations (no PHP 8.1+ syntax), complete PHPDoc (`@package MDS\WooCommerce`, `@since`, `@param`, `@return`), explicit `use` imports, and strict WPCS formatting (tabs, K&R braces, Yoda conditions, `snake_case` methods).

### Class Migration Map

| Current File | Current Symbol(s) | Target PSR-4 File | Target Class (`MDS\WooCommerce\`) | Key Refactoring Details |
| :--- | :--- | :--- | :--- | :--- |
| *(New)* | *(New)* | `src/Plugin.php` | `MDS\WooCommerce\Plugin` | Central bootstrap/registrar hooked on `plugins_loaded`. Registers `Assets`, `MetaBoxes`, `NetworkSettings`, `ReleaseMeta` (on `init`), `RestApi`, and `SaveHandler`. Centralizes `is_active()` and `is_woocommerce_active_on_current_site()` (deduplicating the copy-pasted method currently in both `MdsWooRestApi` and `MdsWooSaveHandler`). |
| `includes/class-mds-woo-admin-scripts.php` + `includes/enqueue-scripts.php` | `MDS_Woo_Admin_Scripts`, `mds_woo_enqueue_admin_scripts()` | `src/Admin/Assets.php` | `MDS\WooCommerce\Admin\Assets` | Hook `[ Assets::class, 'enqueue' ]` on `admin_enqueue_scripts`. Switch to `MDS_WC_PATH` / `MDS_WC_URL`, new handles, `.asset.php` manifests, `wp_add_inline_script()` + `wp_json_encode()` (replacing `wp_localize_script()`), and `wp_set_script_translations()`. |
| `includes/class-mds-woo-meta-boxes.php` + `includes/meta-boxes.php` | `MdsWooMetaBoxes`, `mds_woo_register_meta_box()`, `mds_woo_meta_box_render()` | `src/Admin/MetaBoxes.php` | `MDS\WooCommerce\Admin\MetaBoxes` | Hook `[ MetaBoxes::class, 'register' ]` on `mds_rm_register_custom_meta_boxes`. Convert 4-space PSR-2 & camelCase (`getIntegratedSiteData`, `getProductDetails`) to WPCS tabs + `snake_case`. Replace `'mds_woo_meta_box_render'` in `add_meta_box()` with `[ self::class, 'render' ]`. Replace `array( 'MdsWooRestApi', 'isIntegratedStore' )` with `[ RestApi::class, 'is_integrated_store' ]`. Import `WP_Post` and `MDS\WooCommerce\Rest\RestApi`. |
| `includes/admin-settings.php` | `mds_woo_render_network_settings()`, `mds_woo_network_settings()`, `mds_woo_network_settings_tab()`, `mds_woo_render_network_settings_tab()` | `src/Admin/NetworkSettings.php` | `MDS\WooCommerce\Admin\NetworkSettings` | Encapsulate all 4 procedural functions into methods; hook `[ NetworkSettings::class, 'filter_network_settings' ]` and `[ NetworkSettings::class, 'register_tab' ]`; set tab `'render'` callback to `[ self::class, 'render_tab' ]`. Remove stray duplicate DocBlock (lines 17–25). Reference `\MDS_RM_Network_Admin` explicitly. |
| `includes/class-mds-woo-release-meta.php` | `MDS_Woo_Release_Meta` | `src/Integration/ReleaseMeta.php` | `MDS\WooCommerce\Integration\ReleaseMeta` | Hook `[ ReleaseMeta::class, 'register' ]` on `init`. Add `declare(strict_types=1);`, native PHP 8.0 types, complete PHPDoc, and `use WC_Product;`. Reference `\MDS_RM_Release_Meta` explicitly. Update `mds_woo_is_active()` -> `Plugin::is_active()`. |
| `includes/class-mds-woo-rest-api.php` + `includes/rest-api.php` | `MdsWooRestApi`, `mds_woo_register_rest_routes()`, `mds_woo_save_network_settings()`, `mds_woo_generate_api()`, `mds_woo_search_api()` | `src/Rest/RestApi.php` | `MDS\WooCommerce\Rest\RestApi` | Hook `[ RestApi::class, 'register_routes' ]` on `rest_api_init`. Convert 4-space PSR-2 & camelCase (`registerRoutes`, `saveNetworkSettings`, `generateProduct`, `searchProducts`, `isIntegratedStore`, etc.) to WPCS tabs + `snake_case`. Replace string callbacks (`'mds_woo_generate_api'`, `'mds_woo_search_api'`, `'mds_woo_save_network_settings'`) with `[ self::class, '...' ]`. Import `WP_REST_Request`, `WP_REST_Response`, `WP_Error`. Add REST `args` validation schemas. |
| `includes/class-mds-woo-save-handler.php` + `includes/save-handlers.php` | `MdsWooSaveHandler`, `mds_woo_save_meta()` | `src/Sync/SaveHandler.php` | `MDS\WooCommerce\Sync\SaveHandler` | Hook `[ SaveHandler::class, 'save' ]` on `mds_rm_save_custom_meta` (priority 10, 2 args). Convert 4-space PSR-2 & camelCase (`saveSubmittedLinks`, `synchronizeLinkedProducts`, `canEditProductOnStore`, `synchronizeProduct`, `synchronizeProductThumbnail`) to WPCS tabs + `snake_case`. Update `MdsWooRestApi::isIntegratedStore()` -> `RestApi::is_integrated_store()`. |
| `includes/helpers.php` | `mds_woo_is_active()`, `mds_woo_render_template()` | `src/Support/TemplateRenderer.php` + `includes/helpers.php` | `MDS\WooCommerce\Support\TemplateRenderer` | Move template path validation & `locate_template()` logic into `TemplateRenderer::render()`. Keep thin `mds_wc_is_active()` and `mds_wc_render_template()` procedural wrappers in `includes/helpers.php` for templates and external callers. |

### Phase 2 Checklist

- [ ] **2.1 Create `src/` PSR-4 classes** per the migration map above.
- [ ] **2.2 Global class fallback verification**
  - None of the 5 existing classes extend legacy non-namespaced WordPress/WooCommerce classes (e.g., `WP_Widget`), so **no `MDS_WC_*` global classes are required**. All classes move cleanly into `MDS\WooCommerce\`.
- [ ] **2.3 Remove redundant legacy files after migration**
  - Delete `includes/class-mds-woo-admin-scripts.php`, `includes/class-mds-woo-meta-boxes.php`, `includes/class-mds-woo-release-meta.php`, `includes/class-mds-woo-rest-api.php`, `includes/class-mds-woo-save-handler.php`, `includes/admin-settings.php`, `includes/enqueue-scripts.php`, `includes/meta-boxes.php`, `includes/rest-api.php`, `includes/save-handlers.php`.
- [ ] **2.4 Standardize PHPDoc & PHP 8.0 compliance across all PHP files**
  - Unify `@package` tags (`MDS_WooCommerce_Integration`, `MDS_Woo_Integration`, `MdsWooCommerceIntegration`, `Mds_WooCommerce_Integration`) -> `@package MDS\WooCommerce`.
  - Add `@since` tags matching the reconciled plugin version (or `NEXT_VERSION` for unreleased additions).
  - Verify zero PHP 8.1+ syntax (`enum`, `readonly`, `$fn(...)`, `A&B`, `never`).

---

## Phase 3: Functions, Hooks, Constants, Assets & Text Domain

### 3.1 Constants (`mds-woocommerce-integration.php` & all references)
- [ ] `MDS_WOO_VERSION` -> `MDS_WC_VERSION`
- [ ] `MDS_WOO_PATH` -> `MDS_WC_PATH`
- [ ] `MDS_WOO_URL` -> `MDS_WC_URL`
- [ ] *(Optional)* Define `MDS_WC_FILE` (`__FILE__`)

### 3.2 Procedural Functions (`includes/helpers.php` & `templates/*.php`)
- [ ] `mds_woo_is_active()` -> `mds_wc_is_active()` (thin wrapper calling `Plugin::is_active()`)
- [ ] `mds_woo_render_template()` -> `mds_wc_render_template()` (thin wrapper calling `TemplateRenderer::render()`)
  - Update all 6 template invocations:
    - `templates/network-settings-section.php` (line 19)
    - `templates/product-linker-site.php` (lines 20, 32, 39)
    - `templates/product-linker-wrapper.php` (lines 16, 22)
- [ ] Retire the 12 internal procedural wrappers (`mds_woo_enqueue_admin_scripts`, `mds_woo_register_meta_box`, `mds_woo_meta_box_render`, `mds_woo_register_rest_routes`, `mds_woo_save_network_settings`, `mds_woo_generate_api`, `mds_woo_search_api`, `mds_woo_save_meta`, `mds_woo_render_network_settings`, `mds_woo_network_settings`, `mds_woo_network_settings_tab`, `mds_woo_render_network_settings_tab`) once replaced by PSR-4 class callbacks.

### 3.3 Hooks (Owned vs. External — Preserve Parent Plugin Contracts)
- [ ] **Keep all external `mds-release-manager` hooks unchanged:**
  - `mds_rm_network_settings_fields` (filter)
  - `mds_rm_network_settings_tabs` (filter)
  - `mds_rm_register_custom_meta_boxes` (action)
  - `mds_rm_save_custom_meta` (action)
  - `mds_rm_sync_target_sites` (filter)
  - `mds_rm_after_shadow_meta_sync` (action)
  - `mds_rm_release_meta_context_upc` (filter)
  - `mds_rm_after_buy_now_field` (action)
- [ ] **Keep all external `mds-release-manager` & WooCommerce symbols unchanged:**
  - `MDS_RM_Release_Meta::render()`, `MDS_RM_Network_Admin::extension_tabs()`, `mds_rm_get_or_create_virtual_attachment()`
  - Screen ID `'settings_page_mds-release-network-settings-network'`, post type `'release'`, WooCommerce hooks `woocommerce_single_product_summary` / `woocommerce_after_single_product_summary`.
- [ ] **Any new custom hooks** introduced by this plugin must use literal `mds_wc_` hook names and include complete PHPDoc blocks (`@since`, `@param`).

### 3.4 Script/Style Handles, Inline Data, CSS Classes & Build Pipeline
- [ ] Move frontend sources into `src/` and compile to `build/` via `@wordpress/scripts` (with generated `.asset.php` manifests), updating `src/Admin/Assets.php` to load from `MDS_WC_PATH . 'build/'`:
  - `js/network-settings.js` -> `src/admin/network-settings.js`
  - `js/product-linker.js` -> `src/admin/product-linker.js`
  - `css/admin.css` -> `src/admin/admin.css`
- [ ] Rename script and style handles in `src/Admin/Assets.php`:
  - `'mds-woo-network-settings'` -> `'mds_wc_network_settings'`
  - `'mds-woo-product-linker'` -> `'mds_wc_product_linker'`
  - `'mds-select2'` -> `'mds_wc_select2'`
  - `'mds-select2-css'` -> `'mds_wc_select2_css'`
  - `'mds-woo-admin'` -> `'mds_wc_admin'`
- [ ] Replace `wp_localize_script()` with `wp_add_inline_script( $handle, 'const ... = ' . wp_json_encode( $data ) . ';', 'before' )` and call `wp_set_script_translations( $handle, 'mds-woocommerce-integration' )`:
  - `mdsWooNetworkSettings` -> `mdsWcNetworkSettings`
  - `mdsWooProductLinker` -> `mdsWcProductLinker`
- [ ] Rename `mds-woo-*` CSS classes & form ID across CSS, templates, `NetworkSettings.php`, JS, and `tests/settings-tab.php`:
  - `#mds-woo-network-settings-form` -> `#mds-wc-network-settings-form` (keep `#mds-network-settings-form` fallback in `network-settings.js` for legacy Release Manager)
  - `.mds-woo-integrated-site-checkbox` -> `.mds-wc-integrated-site-checkbox`
  - `.mds-woo-linker-select` -> `.mds-wc-linker-select`
  - `.mds-woo-linker-site` -> `.mds-wc-linker-site`
  - `.mds-woo-generate-button` -> `.mds-wc-generate-button` (merge with existing `.mds-wc-generate-btn`)
  - `.mds-woo-network-settings` -> `.mds-wc-network-settings`
  - `.mds-woo-no-integrated-sites` -> `.mds-wc-no-integrated-sites`
  - *(Already aligned)*: `.mds-wc-linker-wrapper`, `.mds-wc-select2`, `.mds-wc-generate-btn`
- [ ] Review external CDN fallback (`https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/...` in `Assets.php` lines 85–86): consider bundling locally or relying on WooCommerce's `selectWoo` to avoid external CDN calls in `wp-admin`.

### 3.5 Internationalization (`mds-woocommerce-integration`) & Section 508 Accessibility
- [ ] Wrap all untranslated English strings with literal text domain `'mds-woocommerce-integration'`:
  - `src/Admin/MetaBoxes.php`: `'WooCommerce Integration'`
  - `src/Rest/RestApi.php`: all 6 `WP_Error` messages (`Missing Store ID or Release ID`, `You are not allowed to edit this release.`, `The selected store is not integrated.`, `WooCommerce is not active on the selected site.`, `You are not allowed to create products on the selected site.`, `Failed to create product.`)
  - `templates/network-settings-section.php`: `WooCommerce Sites`, `Select sites with WooCommerce active for product linking.`
  - `templates/product-linker-empty.php`: `No WooCommerce Integrated Sites configured in Network Settings.`
  - `templates/product-linker-generate-button.php`: `Generate New`
  - `templates/product-linker-search-option.php`: `Search existing products...`
  - `templates/product-linker-site.php`: `Store ID: %d` (via `sprintf()` + `esc_html__()`)
- [ ] Remediate Section 508 / WCAG 2.1 AA issues:
  - `templates/product-linker-site.php`: replace `<strong>Store ID: ...</strong>` with an explicit `<label for="mds-wc-linked-product-{store_id}">` matching `<select id="mds-wc-linked-product-{store_id}">`.
  - `js/network-settings.js` & `js/product-linker.js`: replace `window.alert()` calls with accessible inline WordPress admin notices and `@wordpress/a11y` `speak()` (or `wp.a11y.speak()`) announcements.

---

## Phase 4: Data & Backward-Compatibility Risk Register

> **CRITICAL:** The keys below persist live data on `http://bgw-dev.local/` or couple directly with `mds-release-manager`. **Do not rename any HIGH-risk key without an explicit migration routine and sign-off.**

| Key / Identifier | Type | Risk | Locations Used | Impact on `http://bgw-dev.local/` if Renamed | Recommended Strategy |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `mds_rm_wc_integrated_sites` | Network Site Option (`wp_sitemeta`) | **HIGH** | `admin-settings.php`, `class-mds-woo-meta-boxes.php`, `class-mds-woo-rest-api.php`, `templates/network-settings-site.php`, `tests/settings-tab.php`, **and `mds-release-manager/tests/uninstall.php` (lines 90, 106, 126)** | All configured WooCommerce store IDs disappear on `bgw-dev.local`; breaks `mds-release-manager` uninstall test fixture. | **Keep as `mds_rm_wc_integrated_sites`** (zero breakage), OR implement an idempotent one-time migration (`mds_wc_db_version`) to `mds_wc_integrated_sites` with read fallback and update `mds-release-manager/tests/uninstall.php`. |
| `mds_rm_woo_sites` | Network Site Option / Option | **HIGH** | `class-mds-woo-release-meta.php` (`sync_targets()`, `inject()`), documented in `mds-release-manager/.agents/technical-blueprint.md` (line 303) | Breaks multisite spoke sync targeting and automatic product page metadata injection on `bgw-dev.local`. *(Note: read-only in this plugin — separate from `mds_rm_wc_integrated_sites`.)* | **Keep as legacy read fallback**; decide whether `ReleaseMeta` should also read `mds_rm_wc_integrated_sites` or migrate `mds_rm_woo_sites` into it. |
| `mds_rm_woo_injection` | Theme Mod (`theme_mods_*`) | **HIGH** | `class-mds-woo-release-meta.php` (`register()`) | Resets Customizer placement setting for Release Meta injection on WooCommerce product pages. | **Keep as `mds_rm_woo_injection`** unless Customizer controls are migrated simultaneously. |
| `_mds_linked_products` | Post Meta (`release` CPT) | **HIGH** | `class-mds-woo-meta-boxes.php`, `class-mds-woo-release-meta.php`, `class-mds-woo-rest-api.php`, `class-mds-woo-save-handler.php` | Orphans every existing Release <-> WooCommerce Product link across all releases on `bgw-dev.local`. | **Keep as `_mds_linked_products`**, OR add a one-time idempotent migration copying `_mds_linked_products` -> `_mds_wc_linked_products` with dual-read fallback. |
| `_mds_local_product_id` | Post Meta (`release` & shadow CPT) | **HIGH** | `class-mds-woo-release-meta.php`, `class-mds-woo-rest-api.php`, `class-mds-woo-save-handler.php` | Breaks local product ID resolution on synced spoke sites and any queries reading it. | **Keep as `_mds_local_product_id`**, OR migrate with dual-read fallback. |
| `mds-woo/v1` (`/generate`, `/search`, `/network-settings`) | REST API Namespace & Routes | **MEDIUM** | `class-mds-woo-rest-api.php`, `class-mds-woo-admin-scripts.php` | Breaks cached admin JS or any external client calling `/wp-json/mds-woo/v1/*`. | Switch primary namespace to `mds-wc/v1`; optionally keep deprecated `mds-woo/v1` alias routes for one release cycle. |
| `mds_rm_linked_product[store_id]` | HTML Form Input Name | **LOW** | `templates/product-linker-site.php`, `class-mds-woo-save-handler.php` | Safe to rename to `mds_wc_linked_product` when template and `SaveHandler` are updated together (optional fallback read of `mds_rm_linked_product`). | Rename to `mds_wc_linked_product` in lockstep. |
| `mds_woo_settings` | Meta Box ID | **LOW** | `class-mds-woo-meta-boxes.php` | Only resets per-user meta box sort/collapsed state in `wp_usermeta`. | Rename to `mds_wc_settings`. |
| `#mds_use_linked_product_btn` | DOM Button ID | **LOW–MEDIUM** | `class-mds-woo-release-meta.php` (`buy_now_button()`) | May be targeted by scripts expecting that exact ID on the Release Buy Now field. | Verify no external JS targets it before renaming; if kept for parent plugin compatibility, document explicitly. |
| `mds-woocommerce-integration/` | Theme Template Override Dir | **NONE** | `includes/helpers.php` (`locate_template()`) | Already matches `{{PLUGIN_SLUG}}`. | Keep unchanged. |
| `upc_ean`, `_sku`, `_visibility` | External Post Meta | **DO NOT CHANGE** | `class-mds-woo-rest-api.php`, `class-mds-woo-save-handler.php` | Owned by `mds-release-manager` and WooCommerce. | Do not rename. |
| **Transients / Shortcodes / Blocks / Custom DB Tables** | N/A | **NONE** | None exist in this repository | No transients, shortcodes, blocks, or custom tables exist in the plugin. | N/A |

---

## Phase 5: Tests, Verification & Documentation Wrap-Up

- [ ] **5.1 Update standalone test (`tests/settings-tab.php`)**
  - Load `vendor/autoload.php` and exercise `MDS\WooCommerce\Admin\NetworkSettings` and `MDS\WooCommerce\Rest\RestApi::save_network_settings()`.
  - Update expected form ID (`#mds-wc-network-settings-form`) and option key (per Phase 4 decision).
  - Normalize file to LF (`\n`).
- [ ] **5.2 Run automated linters & tests**
  - Run `composer install` and `composer phpcs` (`vendor/bin/phpcs`) — resolve all WPCS and `PHPCompatibilityWP` errors/warnings.
  - Run `npm install`, `npm run build`, `npm run lint:js`, `npm run lint:css` (once `@wordpress/scripts` pipeline is configured).
  - Run `tests/settings-tab.php` using the LocalWP PHP binary.
- [ ] **5.3 Live verification on `http://bgw-dev.local/`**
  - Verify plugin activates cleanly alongside `mds-release-manager` and WooCommerce with zero PHP notices/warnings.
  - Verify Network Settings -> WooCommerce tab loads, saves integrated sites via REST, and announces status accessibly.
  - Verify Release editor -> WooCommerce Integration meta box renders linked products, searches products, generates a new product, and syncs title/SKU/thumbnail on save.
  - Verify single WooCommerce product page injects `MDS_RM_Release_Meta` output according to `mds_rm_woo_injection`.
- [ ] **5.4 Complete required documentation files**
  - Populate `.agents/technical-blueprint.md` (currently empty) with architecture overview, directory tree, PSR-4 namespaces, REST routes, hook contracts, and option/meta schema.
  - Create `README.MD` with developer setup (`composer install`, `npm install`, `npm run build`), LocalWP workflow, and architecture overview.
  - Create `readme.txt` with standard WordPress.org headers (`Requires at least: 7.0`, `Tested up to: 7.x`, `Requires PHP: 8.0`, synchronized `Stable tag`).
  - Create `changelog.md` with Keep a Changelog entries under `## [Unreleased]`.
  - Verify 5-file version synchronization (`mds-woocommerce-integration.php`, `readme.txt`, `changelog.md`, `package.json`, `composer.json`) before tagging a release.
