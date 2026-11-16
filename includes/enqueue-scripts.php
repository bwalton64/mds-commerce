<?php
/**
 * Enqueue scripts for the WooCommerce integration admin screens.
 *
 * @package MDS_Woo_Integration
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'mds_woo_enqueue_admin_scripts' );

/**
 * Enqueue WooCommerce integration scripts on their required admin screens.
 */
function mds_woo_enqueue_admin_scripts() {
	MDS_Woo_Admin_Scripts::enqueue();
}
