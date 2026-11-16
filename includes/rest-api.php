<?php
/**
 * WooCommerce integration REST API route callbacks.
 *
 * @package MdsWooCommerceIntegration
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'mds_woo_register_rest_routes' );

/**
 * Register WooCommerce integration REST routes.
 */
function mds_woo_register_rest_routes() {
	MdsWooRestApi::registerRoutes();
}

/**
 * Save WooCommerce network settings.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function mds_woo_save_network_settings( $request ) {
	return MdsWooRestApi::saveNetworkSettings( $request );
}

/**
 * Generate a WooCommerce product for a release.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|WP_Error
 */
function mds_woo_generate_api( $request ) {
	return MdsWooRestApi::generateProduct( $request );
}

/**
 * Search WooCommerce products for a release.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function mds_woo_search_api( $request ) {
	return MdsWooRestApi::searchProducts( $request );
}
