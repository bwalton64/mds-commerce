# MDS Commerce

Multi-platform commerce companion plugin for **MDS Release Manager** (`MDS_RM_API_VERSION 2.0.0+`).

Evolved in place from `mds-woocommerce`, **MDS Commerce** (`3.0.0`) provides a pluggable `CommerceProviderInterface` architecture starting with **WooCommerce Local/Multisite + WPC External Variations**, a visitor-facing **Store Chooser** Gutenberg block and shortcode with coupon/promo prioritization, **Physical Media POD Provisioning Packs** (elasticStage, TrepStar, Kunaki, Diggers Factory, Qrates), and **WordPress 7.x Abilities API / MCP** integration.

## Key Features

- **Provider-Agnostic Commerce Architecture:** Namespaced `StoreKey` identifiers (`wc_local:3`, `shopify:*`, `wix:*`), `ProviderManager` (`mds_cm_register_providers`), `CredentialVault` (libsodium / OpenSSL AES-256-GCM + `wp-config.php` constant overrides), and `RateLimiter`.
- **Zero-Loss Upgrade from `mds-woocommerce`:** `UpgradeRoutine` automatically migrates `_mds_wc_target_stores`, `_mds_linked_products`, and `mds_wc_product_injection` while preserving `_mds_local_product_id` on Spoke shadow releases and retaining `mds-wc/v1` / `mds-woo/v1` REST aliases and `MDS\WooCommerce\*` class aliases.
- **WPC External Variations Bridge:** Auto-detects `wpc-external-variations` (`wpcev_url` / `wpcev_text`) per store and advertises an *External Variations Ready* capability badge alongside per-Artist Variant Mapping Strategy controls (`ask_on_seed`, `variable_product`, `separate_products`).
- **Store Chooser Block (`mds-commerce/store-chooser`) & Shortcode (`[mds-store-chooser]`):** Format-grouped visitor picker with automatic coupon/special-offer prioritization, 1-click accessible coupon copying, and theme-adaptive `--mds-theme-*` styles.
- **WordPress 7.x Abilities / MCP:** Exposes read-only `mds-commerce/list-stores`, `mds-commerce/get-release-links`, and `mds-commerce/get-pod-provisioning-pack` (`meta.mcp.public = true`).

## Development Commands

```bash
composer dump-autoload -o
composer phpcs
php tests/store-registry.php
php tests/commerce-core.php
npm run build
```
