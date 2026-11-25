<?php
/**
 * Fired when MDS WooCommerce Integration is uninstalled.
 *
 * Removes plugin-owned options and network transients while preserving
 * WooCommerce products and linked release post meta.
 *
 * @package MDS\WooCommerce
 * @since   2.1.0
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( static function (): void {
	$local_options = array(
		'mds_wc_product_injection',
		'mds_wc_artist_stores_migrated',
	);

	$delete_local = static function () use ( $local_options ): void {
		foreach ( $local_options as $option_name ) {
			delete_option( $option_name );
		}
	};

	$delete_local();

	delete_site_transient( 'mds_wc_network_stores' );
	delete_site_transient( 'mds_wc_network_stores_lock' );
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
