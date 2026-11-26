=== MDS Commerce ===
Contributors: mustydungeon
Tags: music, record-label, woocommerce, ecommerce, discography
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-platform commerce companion for MDS Release Manager with WooCommerce Multisite syncing, WPC External Variations POD support, and Store Chooser blocks.

== Description ==

MDS Commerce extends MDS Release Manager with pluggable storefront integrations, per-Artist store targeting and variant strategies, WPC External Variations support for physical print-on-demand manufacturers (elasticStage, TrepStar, Kunaki, Diggers Factory, Qrates), a format-grouped Store Chooser Gutenberg block (`[mds-store-chooser]`) with coupon/promo prioritization, and WordPress 7.x Abilities API / MCP endpoints.

== Changelog ==

= 3.0.0 =
* Renamed in place from MDS WooCommerce Integration (`mds-woocommerce`) to MDS Commerce (`mds-commerce`) under `MDS\Commerce\` with zero-loss `UpgradeRoutine` migration and backward-compatible REST/class/helper shims.
* Added pluggable `CommerceProviderInterface`, `ProviderManager`, `StoreKey`, `CredentialVault` (libsodium + OpenSSL AES-256-GCM), and `RateLimiter`.
* Added `ExternalVariationsBridge` (`wpc-external-variations` `wpcev_url` / `wpcev_text` detection and read/write helpers) and per-Artist Variant Mapping Strategy controls.
* Added `mds-commerce/store-chooser` Gutenberg block and `[mds-store-chooser]` shortcode grouped by Media Format with coupon/promo auto-boost and 1-click accessible coupon copy.
* Added `PodProvisioningProvider`, `GET /mds-commerce/v1/pod-provisioning-pack`, and WordPress 7.x Abilities (`mds-commerce/list-stores`, `mds-commerce/get-release-links`, `mds-commerce/get-pod-provisioning-pack`).
