# MDS Commerce Admin Tools — AI Enhancement & Automation Audit

**Topic:** `commerce-admin-tools`  
**Scope:** Evaluating where AI genuinely improves the `mds-commerce` admin experience versus where deterministic WordPress UI/PHP is faster, cheaper, and safer.  
**Date:** 2026-11-26

---

## 1. Executive Verdict

**90% of `mds-commerce` admin tools should be 100% deterministic.** Connection testing, credential masking, webhook verification, `wpcev_url` variation editing, coupon expiration logic, drift comparison, and POD field copying must be instant, predictable, and zero-cost.

**AI adds genuine admin leverage in 3 optional, human-in-the-loop touchpoints—all powered by WordPress 7.0 Core Connectors or MCP (zero duplicate API key screens):**
1. **Browser-Agent POD Portal Autofill (via `mds-commerce/get-pod-provisioning-pack` MCP Ability):** Inside the POD Provisioning Drawer, alongside the deterministic 1-click Copy buttons, include a **"Copy MCP Prompt for AI Agent"** button (e.g., *"Use `mds-commerce/get-pod-provisioning-pack` for Release #42 (elasticStage) and fill the open release creation form for my review"*). This turns the browser Same-Origin limitation into a 1-click agentic workflow with zero extra plugin API cost.
2. **One-Click "Draft Product Copy" Button in Create Product Modal (Opt-in):** When a WordPress 7.0 AI Connector (or MDS shared BYO key) is configured, let admins click **"Draft Store Copy"** inside the Create Product `<dialog>` to generate platform-tailored short/full descriptions from verified Release metadata—always editable before clicking *Create & Link Product*.
3. **AI-Assisted Legacy Store Matching in Catalog Audit (Tier 2, Opt-in):** When onboarding an existing Shopify/Wix/WooCommerce store that lacks UPC/GTIN codes on older products, offer an optional **"Suggest Matches"** pass (deterministic title similarity first → LLM tie-breaker only for ambiguous items) rendered in a review table with 1-click **Approve Link** / **Dismiss**.

---

## 2. Use-Case Feasibility Matrix

*Unit economics based on verified 2025–2026 rates: GPT-4o-mini (\$0.15/1M in, \$0.60/1M out) and Gemini 2.5 Flash (\$0.30/1M in, \$2.50/1M out).*

| Admin Tool Task | AI vs. Deterministic | Recommended Model / Mechanism | Est. Cost / 1k Ops | Risk & Guardrail | Verdict |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Store Connection Wizard, Test Connection & Webhook Check** | **Deterministic** | Pure PHP + `wp_remote_request()` | \$0.00 | Deterministic HTTP status, latency, and masked secret handling | **Keep Deterministic** |
| **Promo Badge, Coupon Code, Expiry & Priority Editor** | **Deterministic** | Pure PHP + JS UI | \$0.00 | Exact UTC timestamp & weight sorting | **Keep Deterministic** |
| **WPC External Variations (`wpcev_url` / `wpcev_text`) Editor** | **Deterministic** | Pure PHP (`ExternalVariationsBridge`) | \$0.00 | URL validation + exact variation meta writes | **Keep Deterministic** |
| **POD Provisioning Drawer (1-Click Copy + 3000×3000 Art QA)** | **Deterministic + MCP Prompt Helper** | `PodProvisioningProvider` + `mds-commerce/get-pod-provisioning-pack` | \$0.00 (plugin cost) | Human reviews browser agent before clicking Submit on external portal | **Build Now (Tier 1)** |
| **"Draft Store Copy" in Create Product Modal** | **AI (Opt-in)** | WP 7.0 Core AI Connector (GPT-4o-mini / Gemini 2.5 Flash) | \$0.27–\$0.93 | Grounded strictly on Release JSON; populates editable `<textarea>` fields; hidden when no AI Connector is configured | **Optional Feature (Tier 2)** |
| **Fuzzy Legacy Store Matching (Catalog Workbench)** | **Hybrid (Deterministic → AI Tie-Breaker)** | WP 7.0 Core AI Connector (GPT-4o-mini / Gemini 2.5 Flash) | \$0.21–\$0.62 | Never auto-links; requires human click in `DataViews` approval row | **Optional Feature (Tier 2)** |

---

## 3. Guardrails, Privacy & Zero-Key Fallback Architecture

1. **Zero-Key Default:** Every Tier 1 admin tool works 100% out of the box with no AI key. AI buttons only appear if WordPress 7.0 Core AI Connectors (or an explicit `mds_ai_generate_text` filter) reports an active provider.
2. **Never Send Secrets or PII:** Prompts receive *only* public catalog fields (title, artist, release date, label, formats, tracklist, excerpt). Store credentials, webhook secrets, customer emails, and internal URLs are stripped before any prompt is constructed.
3. **Human-in-the-Loop Only:** AI never publishes products, overwrites live store descriptions, or submits external POD orders autonomously. Every output lands in an editable form field or diff preview requiring explicit human confirmation.

---

## 4. WordPress Abilities / MCP Opportunities for Admin Tools

| Ability Name | Mode | Status | Admin Tool Synergy |
| :--- | :--- | :--- | :--- |
| `mds-commerce/list-stores` | Read-only | **Built (v3.0.0)** | Lets agents inspect connected stores & capability flags (`supports_external_variations`). |
| `mds-commerce/get-release-links` | Read-only | **Built (v3.0.0)** | Lets agents audit which releases have active links or expiring promos. |
| `mds-commerce/get-pod-provisioning-pack` | Read-only | **Built (v3.0.0)** | Powers the POD Provisioning Drawer's **"Copy MCP Prompt for AI Agent"** workflow for elasticStage/TrepStar/Kunaki/Bandcamp form-filling. |
| `mds-commerce/check-drift` | Read-only | **Planned (Tier 2)** | Lets agents summarize out-of-sync catalog items across all connected stores. |

---

## 5. Recommended Phased Rollout

1. **Tier 1 (Zero-AI-Dependency Admin Tools):** Ship Store Connections Manager, inline Promo/Coupon + External Variations editors on Release Store Cards, and the POD Provisioning Drawer (with the zero-cost MCP Prompt helper).
2. **Tier 2 (Opt-in WP 7.0 AI Connector Enhancements):** Add the Catalog Drift Workbench (`@wordpress/dataviews/wp`), optional "Draft Store Copy" button in Create Product modal, and optional AI-assisted legacy product matching—reusing WordPress 7.0 Core Connectors.
