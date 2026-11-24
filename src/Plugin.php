<?php
/**
 * Central plugin bootstrap and service registrar.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce;

use MDS\WooCommerce\Admin\Assets;
use MDS\WooCommerce\Admin\MetaBoxes;
use MDS\WooCommerce\Admin\NetworkSettings;
use MDS\WooCommerce\Admin\SiteSettings;
use MDS\WooCommerce\Integration\ReleaseMeta;
use MDS\WooCommerce\Rest\RestApi;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates hook registration and shared plugin environment checks.
 *
 * @since 1.0.1
 */
final class Plugin {

	/**
	 * Register all plugin service hooks.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( ReleaseMeta::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( Assets::class, 'enqueue' ) );
		add_action( 'mds_rm_register_custom_meta_boxes', array( MetaBoxes::class, 'register' ) );
		add_action( 'rest_api_init', array( RestApi::class, 'register_routes' ) );
		add_action( 'mds_rm_after_cache_purge', array( self::class, 'on_after_cache_purge' ), 10, 4 );

		NetworkSettings::register();
		SiteSettings::register();
	}

	/**
	 * Request a coalesced cache purge through MDS Release Manager's centralized cache manager.
	 *
	 * Safely becomes a no-op when paired with an older Release Manager version without MDS_RM_Cache.
	 *
	 * @since 1.0.1
	 *
	 * @param string $context Trigger context label (e.g., 'woocommerce_settings', 'woocommerce_link').
	 * @param int    $blog_id Optional target multisite blog ID (0 for current site).
	 * @return void
	 */
	public static function request_cache_purge( string $context, int $blog_id = 0 ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$args = $blog_id > 0 ? array( 'blog_id' => $blog_id ) : array();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core Release Manager cache purge action.
		do_action( 'mds_rm_purge_cache', $context, $args );
	}

	/**
	 * Clear WooCommerce product transients after a manual or WooCommerce-settings cache purge.
	 *
	 * @since 1.0.1
	 *
	 * @param array<int, string> $purged  Labels of cache adapters purged.
	 * @param array<int, string> $skipped Slugs of cache adapters skipped.
	 * @param int                $blog_id Site ID that was purged.
	 * @param string             $context Trigger context.
	 * @return void
	 */
	public static function on_after_cache_purge( array $purged, array $skipped, int $blog_id, string $context ): void {
		unset( $purged, $skipped, $blog_id );

		if (
			in_array( $context, array( 'manual', 'woocommerce_settings' ), true )
			&& function_exists( 'wc_delete_product_transients' )
		) {
			wc_delete_product_transients();
		}
	}

	/**
	 * Check whether the MDS Release Manager parent plugin is active.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when MDS Release Manager is active on the current site or network.
	 */
	public static function is_active(): bool {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress filter.
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );

		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}

		if ( in_array( 'mds-release-manager/mds-release-manager.php', $active_plugins, true ) ) {
			return true;
		}

		if ( ! is_multisite() ) {
			return false;
		}

		$sitewide = get_site_option( 'active_sitewide_plugins', array() );

		return is_array( $sitewide ) && array_key_exists( 'mds-release-manager/mds-release-manager.php', $sitewide );
	}

	/**
	 * Check whether WooCommerce is active on the current site or network.
	 *
	 * @since 1.0.1
	 *
	 * @return bool True when WooCommerce is active.
	 */
	public static function is_woocommerce_active_on_current_site(): bool {
		$active_plugins = get_option( 'active_plugins', array() );

		if ( is_array( $active_plugins ) && in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
			return true;
		}

		if ( ! is_multisite() ) {
			return false;
		}

		$sitewide = get_site_option( 'active_sitewide_plugins', array() );

		return is_array( $sitewide ) && array_key_exists( 'woocommerce/woocommerce.php', $sitewide );
	}

	/**
	 * Return all configured WooCommerce integrated site IDs, merging legacy site options.
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, int> Deduplicated list of positive site IDs.
	 */
	public static function get_integrated_sites(): array {
		$current = (array) get_site_option( 'mds_rm_wc_integrated_sites', array() );
		$legacy  = (array) get_site_option( 'mds_rm_woo_sites', get_option( 'mds_rm_woo_sites', array() ) );

		$merged = array_filter( array_map( 'absint', array_merge( $current, $legacy ) ) );

		return array_values( array_unique( $merged ) );
	}

	/**
	 * Determine whether the current Hub user may link or seed products on an integrated store.
	 *
	 * Allows Hub Release editors who do not hold separate spoke-site accounts to link or
	 * seed draft products on network-approved WooCommerce stores.
	 *
	 * @since 1.0.1
	 *
	 * @param int $release_id Release post ID on the hub site.
	 * @param int $store_id   Integrated WooCommerce store site ID.
	 * @return bool True when permitted.
	 */
	public static function user_can_manage_spoke_product( int $release_id, int $store_id ): bool {
		$release = get_post( $release_id );
		if ( ! $release || 'release' !== $release->post_type || ! current_user_can( 'edit_post', $release_id ) ) {
			return false;
		}

		if ( ! RestApi::is_integrated_store( $store_id ) ) {
			return false;
		}

		/**
		 * Filters whether the current Hub user can link or seed a WooCommerce product on an integrated store.
		 *
		 * @since 1.0.1
		 *
		 * @param bool $can_manage Whether the user is allowed to manage the link/seed action.
		 * @param int  $release_id Release post ID on the hub site.
		 * @param int  $store_id   Target integrated store site ID.
		 */
		return (bool) apply_filters( 'mds_wc_user_can_manage_spoke_product', true, $release_id, $store_id );
	}
}
