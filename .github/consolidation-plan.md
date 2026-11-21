# MDS WooCommerce Integration — Structure Consolidation Plan

**Status:** Proposed; implementation not started.

## 1. Purpose

Improve the plugin's structure, naming, and admin templates without changing how stores are selected, products are linked or generated, or Release metadata appears on product pages. This is the first planned consolidation, not recovery from a failed effort. The working tree already has PHP views and a Release Meta integration class that need validation before further changes.

Keep one class per file, group by responsibility, and make small, reversible commits. Keep thin procedural entrypoints where they provide compatibility or clarity; do not convert every function to a class.

## 2. Working decisions

| Area | Proposed policy |
| --- | --- |
| Baseline | Preserve current edits. Record committed `HEAD` separately from modified, deleted and untracked files. Establish an agreed, reproducible baseline without blanket-staging or reverting anything. |
| Compatibility | Keep the current PHP 7.4 declaration pending validation. The entrypoint has no WordPress minimum header; verify WordPress 6.3 as a candidate, the actual Release Manager/WooCommerce version requirements, and current stable versions. Distinguish bootstrap compatibility from a fully functional stack; no floor increase without approval. |
| Architecture | Keep `MDS_Woo_` classes and public `mds_woo_*` names, network-aware REST settings, and existing product/Release contracts. Do not introduce a block or move to Store API merely to match the template. |
| Distribution | Target WordPress.org readiness, not submission. Document which dependencies must be active on the hub and on stores; only declare dependencies in plugin metadata after validating installability and activation behavior. |
| Test environment | Use an isolated hub and at least two controlled store contexts, including a non-integrated or inactive store. Use fixture products and never write production product, inventory, order or media data. |

## 3. Target structure

| Location | Responsibility |
| --- | --- |
| `mds-woocommerce-integration.php`, `includes/` bootstrap | Header, constants, loader, dependency/lifecycle hooks and compatibility entrypoints. |
| `includes/admin/` and `includes/admin/views/` | Network settings, Release product linker/meta box, admin scripts and plugin-owned PHP views. |
| `includes/api/` | Existing REST route registration, authorization and request handling. |
| `includes/commerce/` | Product lookup, generation, linking and save behavior; keep the current service boundaries unless Phase 0 identifies a concrete reason to split them. |
| `includes/integration/` | Hub/spoke hooks, shadow-release product mapping, and product-page Release metadata integration. |
| `css/`, `js/`, `tests/`, `languages/` | Existing enqueued assets, repeatable integration and UI tests, and localization. |

## 4. Findings to validate

- Working-tree PHP templates replace the tracked HTML views. The helper still looks for a theme override under `mds-woocommerce-integration/`; inventory deployed overrides before moving admin views to plugin-only loading. Any intentional retirement of a live override needs an explicit compatibility decision.
- Concerns are split between classes and procedural hook wrappers. Trace all callbacks and consumers before relocating files; preserve the callable procedural entrypoints if used elsewhere.
- Network settings and product mapping read both current and legacy options. Snapshot precedence and verify missing, empty, mixed, and already-configured values before suggesting any migration.
- Generate and save paths touch multiple site contexts, SKU, product metadata and linked IDs. Baseline site restoration on success and failure, store eligibility and capability decisions, duplicate SKU behavior, and whether writes use WooCommerce CRUD or direct metadata. Changing these semantics is not incidental cleanup.

## 5. Frozen interfaces

- `mds-woo/v1/generate`, `/search`, and `/network-settings`: preserve methods, request/response shapes, authorization and the JavaScript consumers.
- `mds_rm_register_custom_meta_boxes`, `mds_rm_save_custom_meta`, `mds_rm_network_settings_fields`, `mds_rm_sync_target_sites`, `mds_rm_after_shadow_meta_sync`, `mds_rm_release_meta_context_upc`, and `mds_rm_after_buy_now_field`: preserve names, priorities, accepted arguments and behavior.
- Preserve `MDS_RM_Release_Meta::render()` and virtual attachment integration, current/legacy site options (`mds_rm_wc_integrated_sites`, `mds_rm_woo_sites`), `_mds_linked_products`, `_mds_local_product_id`, SKU/UPC text semantics, and the `mds_rm_woo_injection` placement setting. Inventory procedural callables, handles and DOM contracts used by the network settings and product linker.

## 6. Verification gate

After each change: lint touched PHP/JS, confirm activation in applicable site/network combinations, compare hook/REST inventories and check focal product or settings regressions. Assert the original blog context is restored and only intended fixtures changed for all tested operations. Record causes of failures, keep one reversible commit per logical change, and do not commit a failing step. Run WPCS and the broader applicable suite at phase boundaries; validate the staged package in Phase 8.

## 7. Phases

| Phase | Work and exit gate |
| --- | --- |
| **0 — Baseline** | Record committed and working states, registered hooks/routes, option precedence, link maps and fixtures. Seed an isolated hub, integrated store and excluded or inactive store. Exercise network settings save, product search/create/link/relink/unlink, shadow sync, product metadata placement, and optional media mapping. Include Release Manager or WooCommerce missing, and site/network activation. **Gate:** repeatable results with tracked fixture IDs and documented pre-existing findings. |
| **1 — Compliance metadata** | Review plugin header, PHP/WordPress/WooCommerce matrix, dependency notices and directory readme/license. **Gate:** tested floors and supported configurations are documented without raising a floor implicitly. |
| **2 — Naming** | Normalize private helpers and callbacks only where useful. Document public wrappers and avoid breaking extension callers. **Gate:** identical hooks, REST registrations and public wrappers. |
| **3 — Responsibility boundaries** | Trace product search/generation, release saves, and shadow mapping; only split classes that mix independently testable responsibilities. Do not change SKU behavior, sync order, or the storage model during a move. **Gate:** fixtures confirm equivalent writes and context restoration on failures. |
| **4 — Functional folders** | Move classes and wrappers in small steps and update bootstrap and asset paths together. **Gate:** all dependency/activation permutations and admin assets load without regressions. |
| **5 — REST and admin asset contracts** | Instead of a block migration, verify native REST registration, network settings JS, linker selectors, capability/nonce enforcement and localized script data. **Gate:** same authorized workflows and no product writes for denied or excluded contexts. |
| **6 — Template hardening** | Finish validating PHP views for settings and product linker. Move admin-only views to plugin-owned loading after resolving override compatibility; preserve rendering and JS contracts. Localize, escape by output context, and move static styles to the admin stylesheet. **Gate:** network settings and Release editor work across permitted roles. |
| **7 — Types and docs** | Normalize internal types/docblocks within the verified floor. Document store eligibility, option precedence, placement/disabled behavior, and the operational test recipe. **Gate:** supported compatibility matrix passes or untested configurations are marked. |
| **8 — Distribution and handoff** | Audit WPCS, Plugin Check, license and ZIP contents. Rerun hub/spoke regressions for existing/new products, SKU leading zeroes, duplicate/missing SKU, option persistence, failure recovery, and media mapping if enabled. Verify deactivate/reinstall retention on fixtures. **Gate:** reports, package and rollback steps recorded; no submission or production change. |

## 8. Non-goals

No new storefront workflow, checkout/order/inventory logic, block, sync redesign, storage migration, automatic unlinking, destructive uninstall, Release Manager API change, production deployment, or directory submission. Any pre-existing issue requiring a behavior change gets a separate decision and regression test.
