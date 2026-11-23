# MDS WooCommerce Integration

Extends **MDS Release Manager** with a **Seed & Hand Off** multisite WooCommerce workflow: Hub editors link existing WooCommerce products or seed new Draft products on integrated stores via a compact modal dialog, while Store Admins retain full ownership of store products after creation.

## Requirements

- **WordPress:** 7.0+ (Tested up to 7.x)
- **PHP:** 8.0+
- **Dependencies:** MDS Release Manager and WooCommerce (on integrated store sites)

## Architecture

This plugin uses a Composer PSR-4 architecture under the `MDS\WooCommerce\` namespace (`src/`) alongside a lightweight WordPress bootstrap (`mds-woocommerce-integration.php`) and thin procedural template helpers (`includes/helpers.php`).

- `MDS\WooCommerce\Plugin` — Bootstraps all services on `plugins_loaded`, merges current/legacy integrated store lists, and enforces delegated Hub-to-Spoke permissions.
- `MDS\WooCommerce\Admin\Assets` — Conditionally enqueues zero-CDN admin scripts/styles (using `@wordpress/scripts` `build/*.asset.php` manifests when present, with fallback to `src/admin/`).
- `MDS\WooCommerce\Admin\MetaBoxes` — Renders ergonomic WooCommerce Store Cards and the native `<dialog>` Create Product modal on `release` posts.
- `MDS\WooCommerce\Admin\NetworkSettings` — Registers the WooCommerce tab in Release Manager Network Settings.
- `MDS\WooCommerce\Integration\ReleaseMeta` — Hooks into Release Manager shadow sync and WooCommerce product summary templates.
- `MDS\WooCommerce\Rest\RestApi` — Exposes `/wp-json/mds-wc/v1/*` endpoints (`/network-settings`, `/search`, `/link`, `/taxonomies`, `/generate`) with `mds-woo/v1` backward-compatible aliases.
- `MDS\WooCommerce\Support\TemplateRenderer` — Safely renders templates from `templates/` with theme overrides in `mds-woocommerce-integration/`.

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
  php tests/settings-tab.php
  ```

## LocalWP Environment

Configured for the LocalWP site at `C:\Users\Brian\Local Sites\bgw-dev\app\public` (`http://bgw-dev.local/`). Activate both **MDS Release Manager** and **MDS WooCommerce Integration**, then configure integrated WooCommerce sites under **Network Admin -> Settings -> Release Manager -> WooCommerce**.
