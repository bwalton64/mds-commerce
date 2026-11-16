<?php
/**
 * WooCommerce integration meta boxes.
 *
 * @package Mds_WooCommerce_Integration
 */

defined( 'ABSPATH' ) || exit;

add_action( 'mds_rm_register_custom_meta_boxes', 'mds_woo_register_meta_box' );

/**
 * Register the WooCommerce integration meta box.
 *
 * @param WP_Post $post Post object.
 */
function mds_woo_register_meta_box( $post ) {
	MdsWooMetaBoxes::register( $post );
}

/**
 * Render WooCommerce product links for a release.
 *
 * @param WP_Post $post Post object.
 */
function mds_woo_meta_box_render( $post ) {
	MdsWooMetaBoxes::render( $post );
}
