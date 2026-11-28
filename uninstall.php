<?php
/**
 * Fired when MDS Commerce is uninstalled.
 *
 * Removes plugin-owned options and network transients while preserving
 * store products and linked release post meta.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( static function (): void {
	$local_options = array(
		'mds_cm_wc_product_injection',
		'mds_cm_migrated_version',
		'mds_cm_store_connections',
		'mds_cm_chooser_defaults',
		'mds_cm_license_key',
		'mds_cm_entitlements',
		'mds_wc_product_injection',
		'mds_wc_artist_stores_migrated',
	);

	$delete_local = static function () use ( $local_options ): void {
		foreach ( $local_options as $option_name ) {
			delete_option( $option_name );
		}
	};

	$delete_local();

	$network_transients = array(
		'mds_cm_network_stores',
		'mds_cm_network_stores_lock',
		'mds_cm_gateway_update_check',
		'mds_wc_network_stores',
		'mds_wc_network_stores_lock',
	);

	foreach ( $network_transients as $transient_name ) {
		delete_site_transient( $transient_name );
	}

	delete_site_option( 'mds_cm_network_stores_fallback' );
	delete_site_option( 'mds_cm_store_connections' );
	delete_site_option( 'mds_cm_license_key' );
	delete_site_option( 'mds_cm_entitlements' );
	delete_site_option( 'mds_wc_network_stores_fallback' );

	if ( ! is_multisite() ) {
		return;
	}

	$current_blog_id = get_current_blog_id();
	$site_ids        = get_sites(
		array(
			'network_id' => get_current_network_id(),
			'fields'     => 'ids',
			'number'     => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		$blog_id = (int) $site_id;
		if ( $blog_id === $current_blog_id ) {
			continue;
		}

		switch_to_blog( $blog_id );
		try {
			$delete_local();
		} finally {
			restore_current_blog();
		}
	}
} )();
