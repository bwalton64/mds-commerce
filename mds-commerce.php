<?php
/**
 * Plugin Name: MDS Commerce
 * Plugin URI: https://mustydungeon.com
 * Description: Multi-platform commerce companion for MDS Release Manager with WooCommerce Multisite syncing, external POD variations, and Store Chooser blocks.
 * Version: 3.0.1
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: mds-release-manager
 * Update URI: https://github.com/bwalton64/mds-commerce
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
	add_action( 'mds_rm_loaded', array( \MDS\Commerce\Plugin::class, 'init' ) );
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
