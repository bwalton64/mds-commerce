---
trigger: always_on
description: Centralized project variables, prefixes, namespace, and LocalWP path for this plugin.
---

# Project Configuration

All global rules in `~/.gemini/config/rules/` resolve their plugin-specific tokens from this table:

| Variable                   | Value                                           |
| :------------------------- | :---------------------------------------------- |
| **Plugin Name**            | `MDS Commerce`                                  |
| **Plugin Slug / Folder**   | `mds-commerce`                                  |
| **Text Domain**            | `mds-commerce`                                  |
| **PHP Root Namespace**     | `MDS\Commerce\`                                 |
| **Global Class Prefix**    | `MDS_CM_`                                       |
| **Function / Hook Prefix** | `mds_cm_`                                       |
| **Constant Prefix**        | `MDS_CM_`                                       |
| **Minimum WordPress**      | `7.0`                                           |
| **Tested Up To WordPress** | `7.x`                                           |
| **Minimum PHP**            | `8.0`                                           |
| **LocalWP Site Path**      | `C:\Users\Brian\Local Sites\bgw-dev\app\public` |
| **LocalWP Site URL**       | `http://bgw-dev.local/`                         |
