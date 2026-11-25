# MDS WooCommerce Integration

Companion plugin for **MDS Release Manager** that connects your Hub music catalog with WooCommerce stores across single-site and WordPress Multisite networks.

- **Version:** `2.0.0`
- **Requires:** WordPress `7.0+` (Tested up to `7.x`) · PHP `8.0+` · **MDS Release Manager `2.0.0+`** · WooCommerce
- **License:** GPL-2.0-or-later

> End-user and site-admin guide: [USER-MANUAL.md](USER-MANUAL.md)  
> Version history: [changelog.md](changelog.md)  
> Technical architecture & contracts: [.agents/technical-blueprint.md](.agents/technical-blueprint.md)

---

## Highlights

- **Per-Artist WooCommerce Target Stores:** Assign target WooCommerce stores directly on each Hub **Artist** (`_mds_wc_target_stores`) with auto-detected, cached store discovery and batched **Sync Artist Releases**.
- **Accessible Store Cards & Draft Seeding:** Search existing products, link/unlink instantly via REST, or seed new **Draft** WooCommerce products with virtual cover art without duplicating media files.
- **Direct Release–Product Link Resolution:** Linked products resolve directly to their shadow Release via `_mds_local_product_id` (`mds_rm_release_context_post`) — immune to UPC/SKU changes — with unlinked products falling back to WooCommerce GTIN/UPC only.
- **Drift Detection & Selective Sync:** Store Cards detect **UPC/GTIN**, **Cover Art**, and **Product Title** differences without auto-overwriting Store Admin edits, letting admins selectively push approved fields with built-in custom-SKU protection and `wc_product_meta_lookup` synchronization.
- **Single Product Page Block Injection:** Visual drag-and-drop placement designer under **Releases → Settings → WooCommerce Integration** (WooCommerce-active sites only) supporting Block Themes and Classic WooCommerce themes.

---

## Local Development

```bash
# Install PHP dependencies and WPCS 3 / PHPCompatibilityWP
composer install

# Run WPCS & PHP 8.0 compatibility checks
composer phpcs
composer phpcbf

# Run standalone PHP unit tests
php tests/store-registry.php

# Optional JS/CSS build & lint (Node 22+)
npm install
npm run lint:js
npm run lint:css
npm run build
```
