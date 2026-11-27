# MDS Commerce Admin Tools — WordPress 7.x Core Capabilities & Interoperability

**Topic:** `commerce-admin-tools`  
**Target Environment:** WordPress `7.0+` (May 2026+) · PHP `8.0+` · `@wordpress/scripts` v30+ · Multisite Hub-and-Spoke  
**Date:** 2026-11-26

---

## 1. Feature Goal & Constraints

Build native, fast, Section 508-compliant WordPress 7.x admin tools for `mds-commerce` (Store Connections, Promo/Coupon Prioritization, WPC External Variations mapping, POD Provisioning Drawer, and Catalog Drift Workbench) without introducing third-party UI frameworks (no Bootstrap, Tailwind, or external React table libraries) and without duplicating WordPress 7.0 core screens.

---

## 2. Native WordPress 7.x Core Capabilities

| Capability | WP Version | Fit | Implementation Notes for `mds-commerce` Admin Tools |
| :--- | :--- | :--- | :--- |
| **`@wordpress/dataviews/wp`** | WP 6.8–7.0+ | **Essential** | Powers the **Store Connections** table and **Catalog Drift & Sync Log Workbench** with built-in search, column toggles, filters (`Provider`, `Sync Mode`, `Drift Status`), sorting, table/grid layout switching, and bulk actions—zero custom table code. |
| **`@wordpress/components`** (`Modal`, `TabPanel`, `PanelBody`, `Notice`, `SnackbarList`, `ComboboxControl`, `DateTimePicker`, `ToggleControl`) | Core 7.0+ | **Essential** | Use for the Store Connection Wizard, Promo/Coupon Expiration picker, and non-blocking toast notifications (`SnackbarList`) instead of hand-rolling CSS modals or date pickers. |
| **Native HTML `<dialog>` + Vanilla JS** | Existing `mds-commerce` pattern | **High (Contextual Meta Boxes)** | Existing Release Store Cards (`product-linker.js`) and Artist Target Stores (`artist-stores.js`) are lightweight vanilla JS + PHP templates. Extending the Release meta box (Promo/Coupon inline editor + POD Provisioning `<dialog>` drawer) in the same lightweight style avoids loading full React bundles on the Classic/Block post edit screen unless needed. |
| **`wp.apiFetch` + `mds-commerce/v1` REST** | Core | **Essential** | All admin mutations (save/test store connection, verify webhook, save link promo/coupon, update variation `wpcev_url`, fetch POD pack, retry failed sync) flow through authenticated `mds-commerce/v1` REST endpoints with `X-WP-Nonce`. |
| **WordPress 7.0 Settings → Connectors** | WP 7.0+ | **Reuse (Do Not Build)** | WordPress 7.0 provides a unified core **Connectors** screen for AI provider credentials (Gemini, OpenAI, Anthropic, Ollama). Any future optional AI copy/matching tool should consume core Connectors rather than adding another API key field. |
| **WordPress 7.0 Tools → Abilities Explorer** | WP 7.0+ | **Reuse (Do Not Build)** | Admins can already inspect, audit schemas, and test `mds-commerce/list-stores`, `mds-commerce/get-release-links`, and `mds-commerce/get-pod-provisioning-pack` natively in **Tools → Abilities Explorer**. |
| **`Action Scheduler` (Detect-and-Use) + Resumable REST Fallback** | Bundled w/ WC | **High** | Bulk drift scans ("Check All Releases") and scheduled polling enqueue async actions via `as_enqueue_async_action()` when WooCommerce is active, falling back to cursor-paged REST batches (matching `POST /artists/{id}/sync-releases`). |
| **`wp.a11y.speak()` & `wp_set_script_translations()`** | Core | **Essential** | Full WCAG 2.1 AA live announcements for connection tests, copy-to-clipboard actions, and bulk sync progress; full JS i18n under `mds-commerce`. |

---

## 3. Ecosystem Interoperability & Collision Avoidance

| Integration Point | Collision Risk | Safe Native Pattern |
| :--- | :--- | :--- |
| **`MDS Release Manager` Settings Tabs (`mds_rm_site_settings_tabs` / `mds_rm_network_settings_tabs`)** | Cluttering top-level WP Admin sidebar with a competing menu. | Register `Commerce` under `mds_rm_site_settings_tabs` (and `mds_rm_network_settings_tabs` on Multisite Hub) so all MDS settings stay consolidated under **Releases → Settings**. Reserve a submenu (`Releases → Commerce Audit`) only for the Tier 2 full-width `DataViews` workbench. |
| **`wpc-external-variations` Admin UI** | Editing `wpcev_url` / `wpcev_text` from the Hub could conflict if a store admin edits the variation on the Spoke. | Always read live `wpcev_url` / `wpcev_text` via `ExternalVariationsBridge::get_variation_link()` when opening the Hub editor and write atomically via `ExternalVariationsBridge::set_variation_link()`—never cache stale variation URLs indefinitely. |
| **Spoke Sites (Read-Only Discipline)** | Exposing Store Connections or Credential forms on Spoke sites leaks Hub architecture. | Gate Store Connections, Artist Target Stores, Release Store Cards, and POD Provisioning strictly to `ExtensionApi::is_hub()`. On WooCommerce-active Spoke sites, show *only* the Single Product Page Injection settings tab. |
| **Secrets in REST Responses** | Returning decrypted OAuth/API secrets in `GET /mds-commerce/v1/stores` exposes keys to browser dev tools. | Never return raw secrets in any `GET` response. Return only `has_secret: true`, `secret_source: 'wp_config' | 'vault'`, and a masked preview (e.g., `••••1234`). |

---

## 4. Recommended Native Architecture for Admin Tools

### New REST Endpoints (`mds-commerce/v1`)
| Endpoint | Permission | Purpose |
| :--- | :--- | :--- |
| `GET /stores` | `edit_posts` | List all local auto-detected + saved remote `StoreConnection` DTOs (masked secrets, capability flags, cooldown status). |
| `POST /stores` / `DELETE /stores/{store_key}` | `manage_options` (Hub) | Create/update/delete a remote store connection (encrypts credentials via `CredentialVault`, saves sync mode & store promo defaults). |
| `POST /stores/{store_key}/test` | `manage_options` (Hub) | Runs `CommerceProviderInterface::test_connection()` + optional webhook reachability probe; returns latency, API version, and detected capabilities. |
| `POST /links/promo` | `edit_post` | Saves per-release `promo_badge`, `coupon_code`, `promo_expires_gmt`, `priority_weight`, and `format` on `_mds_commerce_links`. |
| `GET /variations` / `POST /variations` | `edit_post` + store access | Reads/updates WooCommerce Variable product format variations and `wpc-external-variations` (`wpcev_url`, `wpcev_text`) from the Hub. |
| `GET /audit/drift` | `edit_posts` (Hub) | Paginated dataset for the Tier 2 `DataViews` Catalog Drift & Sync Log Workbench. |

### Asset Strategy
- **Settings / Workbench Pages (React + `@wordpress/dataviews/wp`):** Compiled via `@wordpress/scripts` (`src/Admin/commerce-settings.js`), loaded *only* on `release_page_mds-release-settings` (when Commerce tab is active) and `release_page_mds-commerce-audit`.
- **Release & Artist Edit Screens (Lightweight Vanilla JS + `<dialog>`):** Extend existing `product-linker.js` and `artist-stores.js` so post-editor load times stay sub-50ms with zero React overhead.

---

## 5. Build-vs-Reuse Decisions & Open Questions

- **Reuse:** `@wordpress/dataviews/wp`, `@wordpress/components`, WordPress 7.0 `Settings → Connectors`, `Tools → Abilities Explorer`, `Action Scheduler`, and `MDS Release Manager`'s settings tab filters.
- **Build:** The thin `mds-commerce/v1` Store Connections CRUD + Test Connection endpoints, the inline Promo/Coupon & External Variations controls on Release Store Cards, and the POD Provisioning `<dialog>` Drawer.
