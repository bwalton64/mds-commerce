<?php
/**
 * WooCommerce integration admin assets.
 *
 * @package MDS_WooCommerce_Integration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues WooCommerce integration admin assets.
 */
final class MDS_Woo_Admin_Scripts {

	/**
	 * Enqueue scripts for the current admin screen.
	 */
	public static function enqueue() {
		if ( ! mds_woo_is_active() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( 'settings_page_mds-release-network-settings-network' === $screen->id ) {
			self::enqueue_network_settings_script();
		}

		if ( 'release' === $screen->post_type ) {
			self::enqueue_product_linker_script();
		}

		if (
			'release' === $screen->post_type
			|| 'settings_page_mds-release-network-settings-network' === $screen->id
		) {
			self::enqueue_admin_styles();
		}
	}

	/**
	 * Enqueue the network settings script and its REST configuration.
	 */
	private static function enqueue_network_settings_script() {
		$network_settings_handle = 'mds-woo-network-settings';
		wp_enqueue_script(
			$network_settings_handle,
			MDS_WOO_URL . 'js/network-settings.js',
			array( 'jquery' ),
			filemtime( MDS_WOO_PATH . 'js/network-settings.js' ),
			true
		);
		wp_localize_script(
			$network_settings_handle,
			'mdsWooNetworkSettings',
			array(
				'url'      => esc_url_raw( rest_url( 'mds-woo/v1/network-settings' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'messages' => array(
					'saved'     => __( 'WooCommerce network settings saved.', 'mds-woocommerce-integration' ),
					'saveError' => __( 'Unable to save WooCommerce network settings.', 'mds-woocommerce-integration' ),
				),
			)
		);
	}

	/**
	 * Enqueue the release product linker and its REST configuration.
	 */
	private static function enqueue_product_linker_script() {
		$product_linker_handle = 'mds-woo-product-linker';
		wp_enqueue_script(
			$product_linker_handle,
			MDS_WOO_URL . 'js/product-linker.js',
			array( 'jquery' ),
			filemtime( MDS_WOO_PATH . 'js/product-linker.js' ),
			true
		);
		wp_localize_script(
			$product_linker_handle,
			'mdsWooProductLinker',
			array(
				'searchUrl'   => esc_url_raw( rest_url( 'mds-woo/v1/search' ) ),
				'generateUrl' => esc_url_raw( rest_url( 'mds-woo/v1/generate' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'messages'    => array(
					'generating'    => __( 'Generating…', 'mds-woocommerce-integration' ),
					'generate'      => __( 'Generate New', 'mds-woocommerce-integration' ),
					'generateError' => __( 'Unable to generate a product.', 'mds-woocommerce-integration' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin styles for the release linker and network settings.
	 */
	private static function enqueue_admin_styles() {
		wp_enqueue_style(
			'mds-woo-admin',
			MDS_WOO_URL . 'css/admin.css',
			array(),
			filemtime( MDS_WOO_PATH . 'css/admin.css' )
		);
	}
}
