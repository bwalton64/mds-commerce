# MDS WooCommerce Integration

Extends **MDS Release Manager** with a **Seed & Hand Off** multisite WooCommerce workflow: Hub editors link existing WooCommerce products or seed new Draft products on integrated stores via a compact modal dialog, while Store Admins retain full ownership of store products after creation.

## Requirements

- **WordPress:** 7.0+ (Tested up to 7.x)
- **PHP:** 8.0+
- **Dependencies:** MDS Release Manager and WooCommerce (on integrated store sites)

## Architecture

This plugin uses a Composer PSR-4 architecture under the `MDS\WooCommerce\` namespace (`src/`) alongside a lightweight WordPress bootstrap (`mds-woocommerce.php`) and thin procedural template helpers (`includes/helpers.php`).

- `MDS\WooCommerce\Plugin` — Bootstraps all services on `plugins_loaded`, wires `StoreRegistry` and `MDS_RM_Cache`, and enforces two-layer delegated Hub-to-Spoke permissions.
- `MDS\WooCommerce\Support\StoreRegistry` — Auto-detects active WooCommerce stores across the network (cached in a network transient), resolves per-Artist and per-Release target stores with per-request memoization, and runs a one-time backfill for existing Hub Artists.
- `MDS\WooCommerce\Admin\Assets` — Conditionally enqueues zero-CDN admin scripts/styles (using `@wordpress/scripts` `build/*.asset.php` manifests when present, with fallback to `src/admin/`).
- `MDS\WooCommerce\Admin\MetaBoxes` — Renders the **WooCommerce Target Stores** meta box on Hub `artist` posts and WooCommerce Store Cards (including **Unassigned Store** cards) + native `<dialog>` Create Product modal on Hub `release` posts.
- `MDS\WooCommerce\Admin\SiteSettings` — Registers the **Releases -> Settings -> WooCommerce Integration** visual single-product layout tab.
- `MDS\WooCommerce\Integration\ReleaseMeta` — Scopes Release Manager shadow sync to each Artist's target stores and injects configured MDS blocks on single WooCommerce product pages (Block & Classic themes).
- `MDS\WooCommerce\Rest\RestApi` — Exposes `/wp-json/mds-wc/v1/*` endpoints (`/settings`, `/stores/refresh`, `/artists/{id}/sync-releases`, `/search`, `/link`, `/taxonomies`, `/generate`) with `mds-woo/v1` backward-compatible aliases.
- `MDS\WooCommerce\Support\TemplateRenderer` — Safely renders templates from `templates/` with theme overrides in `mds-woocommerce/`.

## Developer Setup

1. **Install PHP dependencies & generate autoloader:**
   ```bash
   composer install
   ```
2. **Install frontend build/lint dependencies and compile assets** (requires Node.js & npm):
   ```bash
   npm install
   npm run build
   ```
   During development, run `npm start` for watch mode.

## Code Quality & Testing

- **WordPress Coding Standards & PHP 8.0 compatibility:**
  ```bash
  composer phpcs
  composer phpcbf
  ```
- **JavaScript & CSS linting:**
  ```bash
  npm run lint:js
  npm run lint:css
  npm run format
  ```
- **Standalone PHP unit check:**
  ```bash
  php tests/store-registry.php
  ```

## LocalWP Environment

Configured for the LocalWP site at `C:\Users\Brian\Local Sites\bgw-dev\app\public` (`http://bgw-dev.local/`). Activate both **MDS Release Manager** and **MDS WooCommerce Integration**, then open any **Artist** on the Hub site to assign one or more auto-detected **WooCommerce Target Stores**.
