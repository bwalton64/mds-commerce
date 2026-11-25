<?php
/**
 * Plugin Name: MDS WooCommerce Integration
 * Description: Extends MDS Release Manager with Network WooCommerce syncing.
 * Version: 2.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: mds-release-manager
 * Author: Musty Dungeon Studios
 * Text Domain: mds-woocommerce
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'MDS_WC_VERSION', '2.0.0' );
define( 'MDS_WC_FILE', __FILE__ );
define( 'MDS_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MDS_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'MDS_WC_BASENAME', plugin_basename( __FILE__ ) );

$mds_wc_autoloader = MDS_WC_PATH . 'vendor/autoload.php';

if ( file_exists( $mds_wc_autoloader ) ) {
	require_once $mds_wc_autoloader;
	require_once MDS_WC_PATH . 'includes/helpers.php';
	add_action( 'mds_rm_loaded', array( \MDS\WooCommerce\Plugin::class, 'init' ) );
} else {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MDS WooCommerce Integration requires Composer dependencies. Run composer install in the plugin directory.', 'mds-woocommerce' )
			);
		}
	);
}
