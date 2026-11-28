=== MDS Commerce ===
Contributors: mustydungeon
Tags: music, record-label, woocommerce, shopify, ecommerce, discography
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 3.0.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Paid multi-platform commerce add-on for MDS Release Manager (WooCommerce, Shopify, Wix, POD & Store Chooser) powered by the MDS API Gateway.

== Description ==

MDS Commerce is an official paid commercial extension for MDS Release Manager developed by Musty Dungeon Studios, a wholly owned subsidiary of Walton International Business Group LLC. It adds WooCommerce Multisite syncing, WPC External Variations POD support (elasticStage, TrepStar, Kunaki, Diggers Factory, Qrates), Gateway-backed remote storefront connectors (Shopify, Remote WooCommerce, Wix, Big Cartel, Squarespace), HMAC-signed Webhook Relay, the format-grouped Store Chooser block (`[mds-store-chooser]`) with coupon/promo prioritization, a Catalog Audit Workbench, and WordPress 7.x Abilities API / MCP endpoints.

== Changelog ==

= 3.0.1 =
* Added MDS API Gateway integration (`GatewayClient`, `EntitlementManager` with 7-day offline grace window, `GatewayUpdater`, and `GatewayRemoteProvider` for Shopify, Remote WooCommerce, Wix, Big Cartel, and Squarespace).
* Added HMAC-SHA256 signed `POST /mds-commerce/v1/webhooks/gateway` receiver and License activation/refresh/deactivation endpoints + `License & API Gateway` settings panel.
* Added `Releases -> Commerce Audit` Hub Catalog Workbench (`AuditWorkbench`), official `assets/icon.svg`, `LICENSE` (GPL-3.0-or-later), `COMMERCIAL-LICENSE.md`, and private release ZIP GitHub Actions workflow.
