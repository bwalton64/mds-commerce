# Refactor Plan: `mds-woocommerce` (`2.0.3`) → `mds-commerce` (`3.0.0`)

**Source Plugin:** `mds-woocommerce` (`MDS\WooCommerce\`, `MDS_WC_*`, `mds_wc_*`)  
**Target Plugin:** `mds-commerce` (`MDS\Commerce\`, `MDS_CM_*`, `mds_cm_*`)  
**Rename Strategy:** In-place rename (preserving Git history)  
**Target Compatibility:** WordPress `7.0+` (Tested up to `7.x`) · PHP `8.0+` · Multisite Hub-and-Spoke · `MDS Release Manager` (`MDS_RM_API_VERSION = 2.0.0+`)  
**LocalWP Test Site:** `http://bgw-dev.local/` (`C:\Users\Brian\Local Sites\bgw-dev\app\public`)  
**Research Basis:** `.agents/research/commerce-platforms-*.md` & `physical-pod-and-store-chooser.md` (in `mds-release-manager`)

---

## Live Data & Backward-Compatibility Risk Register

Before executing any phase, observe these non-negotiable live-data guardrails for `http://bgw-dev.local/` and existing Multisite installs:

| Stored Entity | Legacy Key (`mds-woocommerce`) | Target Key (`mds-commerce`) | Migration & Safety Strategy |
| :--- | :--- | :--- | :--- |
| **Hub Artist Target Stores** | `_mds_wc_target_stores` (`int[]`) | `_mds_commerce_target_stores` (`StoreKey[]`, e.g. `wc_local:3`) | Idempotent `UpgradeRoutine` converts `int` blog IDs → `wc_local:{id}` and retains read-fallback if not yet migrated. |
| **Hub Release Linked Products** | `_mds_linked_products` (`array<int, array>`) | `_mds_commerce_links` (`array<string, array>`) | Idempotent `UpgradeRoutine` re-keys entries under `wc_local:{blog_id}` and adds `provider`, `format`, `promo_badge`, `coupon_code`, `promo_expires_gmt`, `priority_weight`. |
| **Spoke Shadow Release Link** | `_mds_local_product_id` (`int`) | `_mds_local_product_id` (**UNCHANGED**) | **Do not rename.** Preserves `mds_rm_release_context_post` and Single Product Page Injection on all live Spokes without requiring an immediate network-wide resync. |
| **WooCommerce Injection Settings** | `mds_wc_product_injection` | `mds_cm_wc_product_injection` | Copy on upgrade; read fallback to `mds_wc_product_injection` for one major version. |
| **Network Store Cache** | `mds_wc_network_stores` / `_lock` / `_fallback` | `mds_cm_network_stores` / `_lock` / `_fallback` | Delete legacy transients on upgrade; rebuild cleanly via `StoreRegistry`. |
| **REST API Namespace** | `mds-wc/v1`, `mds-woo/v1` | `mds-commerce/v1` | Retain `mds-wc/v1` and `mds-woo/v1` as route aliases for one major cycle. |
| **Global PHP Functions** | `mds_wc_is_active()`, `mds_wc_render_template()` | `mds_cm_is_active()`, `mds_cm_render_template()` | Keep `mds_wc_*` shims in `includes/helpers.php` delegating to `mds_cm_*`. |
| **Legacy Class Names** | `MDS\WooCommerce\*` | `MDS\Commerce\*` | Provide lazy `class_alias()` shims in `includes/legacy-class-aliases.php` (loaded from `mds-commerce.php`, never `composer.json` `"files"`). |

---

## Phase 0: Pre-Flight, GitHub Rename & Folder Transition

- [x] Verify clean `git status` in `mds-woocommerce` and tag current release baseline (`v2.0.3`) before renaming.
- [x] Deactivate `MDS WooCommerce Integration` on `http://bgw-dev.local/` (or prepare to reactivate immediately after folder rename).
- [x] Rename GitHub repository `bwalton64/mds-woocommerce` → `bwalton64/mds-commerce` in GitHub Settings → General.
- [ ] Rename local plugin directory `wp-content/plugins/mds-woocommerce` → `wp-content/plugins/mds-commerce`.
- [x] Update local Git remote: `git remote set-url origin https://github.com/bwalton64/mds-commerce.git` and verify with `git remote -v`.
- [x] Rename main bootstrap file via `git mv mds-woocommerce.php mds-commerce.php`.

---

## Phase 1: Project Config, Composer, WPCS & Build Tooling

- [x] Update `.agents/rules/project-config.md` tokens:
  - Plugin Name: `MDS Commerce`
  - Plugin Slug / Folder / Text Domain: `mds-commerce`
  - PHP Root Namespace: `MDS\Commerce\`
  - Global Class Prefix / Constant Prefix: `MDS_CM_`
  - Function / Hook Prefix: `mds_cm_`
- [x] Update `mds-commerce.php` plugin headers (`Plugin Name: MDS Commerce`, `Version: 3.0.0`, `Update URI: https://github.com/bwalton64/mds-commerce`, `Text Domain: mds-commerce`, `Requires at least: 7.0`, `Requires PHP: 8.0`, `Requires Plugins: mds-release-manager`).
- [x] Replace `MDS_WC_*` constants in `mds-commerce.php` with `MDS_CM_VERSION`, `MDS_CM_FILE`, `MDS_CM_PATH`, `MDS_CM_URL`, `MDS_CM_BASENAME` (optionally defining legacy `MDS_WC_*` aliases for backward compatibility).
- [x] Update `composer.json`:
  - `"name": "mds/commerce"`, `"version": "3.0.0"`
  - PSR-4 `"MDS\\Commerce\\": "src/"`
  - Keep `autoload.files` empty (never load ABSPATH-guarded files from Composer).
  - Run `composer dump-autoload -o`.
- [x] Update `phpcs.xml.dist`:
  - Ruleset name/description → `MDS Commerce`
  - `WordPress.WP.I18n` `text_domain` → `mds-commerce`
  - `WordPress.NamingConventions.PrefixAllGlobals` prefixes → `mds_cm_`, `MDS_CM_`, `MDS\Commerce` (plus temporary `mds_wc_` / `MDS_WC_` only where legacy shim files require it, or scoped `phpcs:ignore` on shims).
- [x] Update `package.json`:
  - `"name": "mds-commerce"`, `"version": "3.0.0"`
  - Add new Gutenberg block build entry (`blocks/store-chooser/block.json`) alongside admin scripts/styles.
- [x] Update `wpml-config.xml` to include `mds_cm_wc_product_injection` and custom fields (`_mds_commerce_links`, `_mds_commerce_target_stores`, `_mds_commerce_variant_strategy`).
- [x] Update `uninstall.php` to clean both `mds_cm_*` and legacy `mds_wc_*` options/transients across single-site and all Multisite blogs inside `try/finally`, while preserving products and release links.

---

## Phase 2: Core Namespace Migration & Provider Architecture (`src/`)

- [x] Create typed core contracts & models under `MDS\Commerce\`:
  - `src/Contracts/CommerceProviderInterface.php` (`get_slug`, `get_label`, `get_capabilities`, `test_connection`, `search_products`, `get_product`, `create_draft_product`, `sync_fields`)
  - `src/Contracts/EmbeddableProviderInterface.php` (for future inline checkout embeds like Shopify Web Components)
  - `src/Contracts/ExportPrepProviderInterface.php` (for Bandcamp/Discogs/POD provisioning packs)
  - `src/Model/StoreKey.php` (immutable value object parsing/formatting `{provider}:{id}`, e.g., `wc_local:3`)
  - `src/Model/StoreConnection.php` (typed DTO with `sync_mode`: `webhook` | `polling` | `manual`, capabilities, and promo defaults)
  - `src/Model/NormalizedProduct.php` & `src/Model/DriftReport.php`
- [x] Create core infrastructure services:
  - `src/Security/CredentialVault.php` (`sodium_crypto_secretbox` encryption + `wp-config.php` constant overrides for upcoming remote providers)
  - `src/Registry/ProviderManager.php` (registers built-in providers + `mds_cm_register_providers` filter)
  - `src/Registry/StoreRegistry.php` (combines auto-detected local WooCommerce Multisite stores + saved remote connections; per-Artist store scoping & variant strategy)
  - `src/Sync/DriftEngine.php` & `src/Sync/RateLimiter.php` (provider-agnostic drift diff/sync + per-store transient cooldowns + Action Scheduler detect-and-use with resumable REST batch fallback)
  - `src/Sync/UpgradeRoutine.php` (idempotent one-time migration from `_mds_wc_target_stores`, `_mds_linked_products`, and `mds_wc_product_injection`)
- [x] Refactor existing `MDS\WooCommerce\*` classes into `MDS\Commerce\`:
  - Move `src/Config.php` → `MDS\Commerce\Config` (`mds_cm_*` constants, `REST_NAMESPACE = 'mds-commerce/v1'`)
  - Move `src/Plugin.php` → `MDS\Commerce\Plugin` (update `ExtensionApi::register_github_updater()` to `slug => 'mds-commerce'`, `repo => 'bwalton64/mds-commerce'`, `token_constant => 'MDS_CM_GITHUB_TOKEN'`, load textdomain `mds-commerce`)
  - Extract local WooCommerce logic into `src/Providers/WooCommerceLocal/`:
    - `WooCommerceLocalProvider.php` (implements `CommerceProviderInterface`)
    - `ProductDrift.php` (migrated from `src/Support/ProductDrift.php`)
    - `ReleaseMeta.php` (migrated from `src/Integration/ReleaseMeta.php`, preserving `_mds_local_product_id` and Single Product Page Injection)
    - `ExternalVariationsBridge.php` (detects `WPCleverWpcev` / `WPCEV_VERSION`; reads/writes `wpcev_url` and `wpcev_text` on `product_variation` posts; exposes `supports_external_variations` capability flag)
- [x] Update `includes/helpers.php` (`mds_cm_is_active`, `mds_cm_render_template` + `mds_wc_*` wrappers) and create `includes/legacy-class-aliases.php` for backward compatibility.

---

## Phase 3: Per-Artist Variant Strategy, WPC External Variations & POD Provisioning Foundation

- [x] Extend the **Artist CPT Target Stores Meta Box** (`src/Admin/MetaBoxes.php`, `src/Admin/artist-stores.js`):
  - Display auto-detected store capability badges (e.g., `External Variations Ready` when `wpc-external-variations` is active on a WooCommerce store).
  - Add per-Artist **Variant Mapping Strategy** selector (`variable_product`, `separate_products`, `ask_on_seed`).
  - Support per-store **Sync Mode** indicator and manual **"Sync Now"** / **"Refresh Stores"** controls.
- [x] Extend the **Release CPT Store Cards & Create Product Modal** (`src/Admin/product-linker.js`, `src/Rest/RestApi.php`):
  - When seeding a WooCommerce Variable Product on a store with `supports_external_variations: true`, allow mapping individual format variations (Vinyl, CD, Cassette, Digital) to:
    - **Local WooCommerce Variation** (standard cart checkout; optional TrepStar/Kunaki SKU mapping)
    - **External Variation (`wpcev_url` + `wpcev_text`)** for hosted POD manufacturers (`elasticStage`, `Diggers Factory`, `Qrates`, or `TrepStar` hosted link)
  - Add per-link **Promo & Coupon Prioritization** fields (`promo_badge`, `coupon_code`, `promo_expires_gmt`, `priority_weight`).
  - Add a **POD Provisioning Assistant Drawer** (1-click copy fields + pre-formatted tracklist/metadata + direct portal launch link) for manufacturers without write APIs.

---

## Phase 4: New `mds-commerce/store-chooser` Block, Shortcode & Abilities API

- [x] Register new Gutenberg Block `blocks/store-chooser/block.json` (API v3, category `mds-release-manager`, name `mds-commerce/store-chooser`) and shortcode `[mds-store-chooser]`:
  - Default layout: **Grouped by Media Format** (Vinyl, CD, Cassette, Digital), listing available stores under each format with price/availability badges.
  - Implement **Promo & Coupon Prioritization**: automatically boost stores with active coupons/special offers (`promo_badge` + 1-click accessible Copy Coupon Code button + expiration check) above standard listings, with manual `priority_weight` override.
  - Inherit `MDS Release Manager`'s `--mds-theme-*` light/dark CSS custom properties and enforce Section 508 WCAG 2.1 AA (`wp.a11y.speak()` announcements on coupon copy and format tab switch).
- [x] Update `src/Rest/RestApi.php`:
  - Primary namespace `mds-commerce/v1` + legacy aliases `mds-wc/v1` and `mds-woo/v1`.
  - Add endpoints for Store Connections management, Sync Mode (`webhook` / `polling` / `manual`), and POD Provisioning Pack JSON.
- [x] Add `src/Api/Abilities.php` registering WordPress 7.x Abilities (`meta.mcp.public = true`):
  - `mds-commerce/list-stores` (read-only)
  - `mds-commerce/get-release-links` (read-only)
  - `mds-commerce/check-drift` (read-only)
  - `mds-commerce/get-pod-provisioning-pack` (read-only — feeds verified release metadata, tracklist, and artwork URLs to browser-capable AI agents for portal form-filling)
- [ ] Coordinate companion free-tier simplification in `mds-release-manager` (separate targeted PR/commit):
  - Add `artist_default_store_url` (+ optional label) on Artist CPT as the zero-config free-tier fallback for `[release-links]` Buy Now buttons.

---

## Phase 5: Verification, Tests & Documentation Synchronization

- [x] Migrate & expand standalone PHP tests (`tests/store-registry.php` → add `tests/store-key.php`, `tests/upgrade-routine.php`, `tests/external-variations.php`, `tests/store-chooser-priority.php`).
- [x] Run `wp-lint` (`composer phpcs`, PHP 8.0 syntax check, `npm run build`, `npm run lint:js`, `npm run lint:css`, Section 508 audit).
- [x] Run `wp-test` (standalone PHP test suite + live LocalWP Multisite boot, `UpgradeRoutine` verification on `http://bgw-dev.local/`, REST `mds-commerce/v1` + legacy `mds-wc/v1` smoke checks, and `[mds-store-chooser]` frontend render check).
- [x] Run `wp-docs` and `wp-man` to regenerate `.agents/technical-blueprint.md`, `README.md`, `readme.txt`, `USER-MANUAL.md`, and append `3.0.0` entry to `changelog.md` with `LF` line endings.
