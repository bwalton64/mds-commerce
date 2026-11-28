# MDS Commerce

**Paid multi-platform commerce add-on for MDS Release Manager (`MDS_RM_API_VERSION 2.0.0+`), powered by the MDS API Gateway.**

Evolved in place from `mds-woocommerce`, **MDS Commerce** provides a pluggable `CommerceProviderInterface` architecture covering local/Multisite **WooCommerce + WPC External Variations**, Gateway-backed remote storefronts (**Shopify**, **WooCommerce Remote**, **Wix**, **Big Cartel**, **Squarespace**), HMAC-signed **Webhook Relay**, a visitor-facing **Store Chooser** Gutenberg block and shortcode with coupon/promo prioritization, **Physical Media POD Provisioning Packs** (elasticStage, TrepStar, Kunaki, Diggers Factory, Qrates, Bandcamp), a **Catalog Audit Workbench**, and **WordPress 7.x Abilities / MCP** endpoints.

## Key Features

- **MDS API Gateway & Commercial Entitlements (`Pro` / `Label`):** Protects remote OAuth brokers, platform connectors, Webhook Relay, and private authenticated updates (`https://api.mustydungeon.com/v1/updates/mds-commerce`) behind the MDS API Gateway with a 7-day offline grace window and 0ms local frontend rendering.
- **Unified Hub Admin Tools (`Releases → Settings → Commerce` & `Releases → Commerce Audit`):** Manage Store Connections, Store Chooser Defaults, License & API Gateway, migrated WooCommerce Product Injection, per-release Promo/Coupon settings, Hub-side WPC External Variations (`wpcev_url` / `wpcev_text`), and POD Prep Packs.
- **Zero-Loss Upgrade from `mds-woocommerce`:** `UpgradeRoutine` automatically migrates legacy options and post meta while preserving `_mds_local_product_id` unchanged on Spoke shadow releases.

## Copyright & Licensing

Copyright (c) 2026 Musty Dungeon Studios, a wholly owned subsidiary of Walton International Business Group LLC. All rights reserved.

WordPress PHP integration code is licensed under **GPL-3.0-or-later** (`LICENSE`). The MDS API Gateway, proprietary connectors, brand assets (`assets/icon.svg`), and commercial distribution/update entitlements are governed by `COMMERCIAL-LICENSE.md`.
