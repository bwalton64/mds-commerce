<?php

/**
 * Plugin Name: MDS Release Manager WooCommerce Integration
 * Description: Extends MDS Release Manager with Network WooCommerce syncing.
 * Version: 0.9.0
 * Requires PHP: 7.4
 * Author: Musty Dungeon Studios
 *
 * @category Core
 * @package  MDS_WooCommerce_Integration
 * @author   Musty Dungeon Studios <mds@briangwalton.com>
 * @license  ©2026 Walton International Business Group LLC
 * @link     https://mustydungeon.com
 */

defined('ABSPATH') || exit;
define('MDS_WOO_VERSION', '0.9.0');
define('MDS_WOO_PATH', plugin_dir_path(__FILE__));
define('MDS_WOO_URL', plugin_dir_url(__FILE__));
require_once MDS_WOO_PATH . 'includes/class-mds-woo-rest-api.php';
require_once MDS_WOO_PATH . 'includes/class-mds-woo-admin-scripts.php';
require_once MDS_WOO_PATH . 'includes/class-mds-woo-meta-boxes.php';
require_once MDS_WOO_PATH . 'includes/class-mds-woo-save-handler.php';
require_once MDS_WOO_PATH . 'includes/class-mds-woo-release-meta.php';
require_once MDS_WOO_PATH . 'includes/helpers.php';
require_once MDS_WOO_PATH . 'includes/admin-settings.php';
require_once MDS_WOO_PATH . 'includes/rest-api.php';
require_once MDS_WOO_PATH . 'includes/enqueue-scripts.php';
require_once MDS_WOO_PATH . 'includes/meta-boxes.php';
require_once MDS_WOO_PATH . 'includes/save-handlers.php';
add_action( 'init', array( 'MDS_Woo_Release_Meta', 'register' ) );
