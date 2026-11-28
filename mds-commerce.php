<?php
/**
 * Plugin Name: MDS Commerce
 * Plugin URI: https://mustydungeon.com/mds-commerce
 * Description: Paid multi-platform commerce add-on for MDS Release Manager (WooCommerce, Shopify, Wix, POD & Store Chooser) powered by the MDS API Gateway.
 * Version: 3.0.1
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: mds-release-manager
 * Update URI: https://api.mustydungeon.com
 * Author: Musty Dungeon Studios
 * Author URI: https://mustydungeon.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: mds-commerce
 *
 * @package   MDS\Commerce
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @since     1.0.1
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'MDS_CM_VERSION', '3.0.1' );
define( 'MDS_CM_FILE', __FILE__ );
define( 'MDS_CM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MDS_CM_URL', plugin_dir_url( __FILE__ ) );
define( 'MDS_CM_BASENAME', plugin_basename( __FILE__ ) );

// Legacy constants retained for backward compatibility with companion snippets.
if ( ! defined( 'MDS_WC_VERSION' ) ) {
	define( 'MDS_WC_VERSION', MDS_CM_VERSION );
}
if ( ! defined( 'MDS_WC_FILE' ) ) {
	define( 'MDS_WC_FILE', MDS_CM_FILE );
}
if ( ! defined( 'MDS_WC_PATH' ) ) {
	define( 'MDS_WC_PATH', MDS_CM_PATH );
}
if ( ! defined( 'MDS_WC_URL' ) ) {
	define( 'MDS_WC_URL', MDS_CM_URL );
}
if ( ! defined( 'MDS_WC_BASENAME' ) ) {
	define( 'MDS_WC_BASENAME', MDS_CM_BASENAME );
}

$mds_cm_autoloader = MDS_CM_PATH . 'vendor/autoload.php';

if ( file_exists( $mds_cm_autoloader ) ) {
	require_once $mds_cm_autoloader;
	require_once MDS_CM_PATH . 'includes/helpers.php';
	require_once MDS_CM_PATH . 'includes/legacy-class-aliases.php';
	add_action(
		'mds_rm_loaded',
		static function (): void {
			$mds_cm_gw_sdk = dirname( untrailingslashit( MDS_CM_PATH ) ) . '/mds-cloud-gateway/sdk/GatewayClient.php';
			if ( ! class_exists( \MDS\GatewayClient\GatewayClient::class ) && is_readable( $mds_cm_gw_sdk ) ) {
				require_once $mds_cm_gw_sdk;
			}
			if ( class_exists( \MDS\GatewayClient\GatewayClient::class ) ) {
				\MDS\GatewayClient\GatewayClient::boot_companion( 'mds-commerce', MDS_CM_BASENAME, MDS_CM_VERSION, MDS_CM_PATH );
			}
			\MDS\Commerce\Plugin::init();
		}
	);
} else {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MDS Commerce requires Composer dependencies. Run composer install in the plugin directory.', 'mds-commerce' )
			);
		}
	);
}
