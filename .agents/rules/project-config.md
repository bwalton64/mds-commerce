---
trigger: always_on
description: Centralized project variables, prefixes, namespace, and LocalWP path for this plugin.
---

# Project Configuration

All global rules in `~/.gemini/config/rules/` resolve their plugin-specific tokens from this table:

| Variable                   | Value                                           |
| :------------------------- | :---------------------------------------------- |
| **Plugin Name**            | `MDS WooCommerce Integration`                   |
| **Plugin Slug / Folder**   | `mds-woocommerce-integration`                   |
| **Text Domain**            | `mds-woocommerce-integration`                   |
| **PHP Root Namespace**     | `MDS\WooCommerce\`                              |
| **Global Class Prefix**    | `MDS_WC_`                                       |
| **Function / Hook Prefix** | `mds_wc_`                                       |
| **Constant Prefix**        | `MDS_WC_`                                       |
| **Minimum WordPress**      | `7.0`                                           |
| **Tested Up To WordPress** | `7.x`                                           |
| **Minimum PHP**            | `8.0`                                           |
| **LocalWP Site Path**      | `C:\Users\Brian\Local Sites\bgw-dev\app\public` |
| **LocalWP Site URL**       | `http://bgw-dev.local/`                         |
