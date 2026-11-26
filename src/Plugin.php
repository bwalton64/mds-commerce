<?php
/**
 * Central plugin bootstrap and service registrar for MDS Commerce.
 *
 * @package MDS\Commerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce;

use MDS\Commerce\Admin\Assets;
use MDS\Commerce\Admin\MetaBoxes;
use MDS\Commerce\Admin\SiteSettings;
use MDS\Commerce\Api\Abilities;
use MDS\Commerce\Blocks\StoreChooser;
use MDS\Commerce\Integration\ReleaseMeta;
use MDS\Commerce\Rest\RestApi;
use MDS\Commerce\Support\StoreRegistry;
use MDS\Commerce\Sync\UpgradeRoutine;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates hook registration, schema upgrades, and shared plugin environment checks.
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
		add_action( 'init', array( self::class, 'load_textdomain' ) );
		add_action( 'init', array( ReleaseMeta::class, 'register' ) );
		add_action( 'init', array( StoreChooser::class, 'register' ) );
		add_action( 'admin_init', array( UpgradeRoutine::class, 'maybe_upgrade' ), 5 );
		add_action( 'admin_enqueue_scripts', array( Assets::class, 'enqueue' ) );
		add_action( 'mds_rm_register_custom_meta_boxes', array( MetaBoxes::class, 'register' ) );
		add_action( 'mds_rm_save_artist_meta', array( MetaBoxes::class, 'save_artist_stores_hook' ), 10, 2 );
		add_action( 'rest_api_init', array( RestApi::class, 'register_routes' ) );
		add_action( 'mds_rm_after_cache_purge', array( self::class, 'on_after_cache_purge' ), 10, 4 );

		StoreRegistry::register_hooks();
		SiteSettings::register();
		Abilities::register();

		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) && defined( 'MDS_CM_BASENAME' ) ) {
			\MDS\ReleaseManager\Api\ExtensionApi::register_github_updater(
				array(
					'basename'       => MDS_CM_BASENAME,
					'slug'           => 'mds-commerce',
					'repo'           => 'bwalton64/mds-commerce',
					'version'        => MDS_CM_VERSION,
					'name'           => 'MDS Commerce',
					'author'         => 'Musty Dungeon Studios',
					'homepage'       => 'https://mustydungeon.com',
					'token_constant' => 'MDS_CM_GITHUB_TOKEN',
					'requires'       => '7.0',
					'tested'         => '7.0',
					'requires_php'   => '8.0',
				)
			);
		}
	}

	/**
	 * Load the plugin text domain on `init` per WordPress 7.x guidelines.
	 *
	 * @since 2.1.0
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		if ( defined( 'MDS_CM_BASENAME' ) ) {
			load_plugin_textdomain( 'mds-commerce', false, dirname( MDS_CM_BASENAME ) . '/languages' );
		}
	}

	/**
	 * Request a coalesced cache purge through MDS Release Manager's centralized cache manager.
	 *
	 * @since 1.0.1
	 *
	 * @param string $context Trigger context label (e.g., 'commerce_settings', 'woocommerce_link').
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
	 * Clear WooCommerce product transients after a manual or commerce-settings cache purge.
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
			in_array( $context, array( 'manual', 'woocommerce_settings', 'commerce_settings' ), true )
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
	 * Return all active WooCommerce store IDs across the network.
	 *
	 * Retained as a compatibility wrapper around StoreRegistry::get_available_stores().
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, int> Deduplicated list of positive site IDs.
	 */
	public static function get_integrated_sites(): array {
		return array_keys( StoreRegistry::get_available_stores() );
	}

	/**
	 * Determine whether the current Hub user may link or seed products on a store for a release.
	 *
	 * @since 1.0.1
	 *
	 * @param int  $release_id          Release post ID on the hub site.
	 * @param int  $store_id            Target WooCommerce store site ID.
	 * @param bool $allow_existing_link Whether to allow legacy linked stores during unlink.
	 * @return bool True when permitted.
	 */
	public static function user_can_manage_spoke_product(
		int $release_id,
		int $store_id,
		bool $allow_existing_link = false
	): bool {
		$release = get_post( $release_id );
		if ( ! $release || 'release' !== $release->post_type || ! current_user_can( 'edit_post', $release_id ) ) {
			return false;
		}

		if ( ! StoreRegistry::is_woocommerce_store( $store_id ) ) {
			return false;
		}

		$artist_stores = StoreRegistry::get_release_target_stores( $release_id );
		$is_allowed    = in_array( $store_id, $artist_stores, true );

		if ( ! $is_allowed && $allow_existing_link ) {
			$linked     = get_post_meta( $release_id, '_mds_linked_products', true );
			$is_allowed = is_array( $linked ) && isset( $linked[ $store_id ] );
		}

		if ( ! $is_allowed ) {
			return false;
		}

		/**
		 * Filters whether the current Hub user can manage a store product for a release.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $can_manage Whether the user is allowed to manage the link/seed action.
		 * @param int  $release_id Release post ID on the hub site.
		 * @param int  $store_id   Target store site ID.
		 */
		$allowed = (bool) apply_filters( 'mds_cm_user_can_manage_store', true, $release_id, $store_id );

		/**
		 * Legacy filter retained for backward compatibility with MDS WooCommerce hooks.
		 *
		 * @since 1.0.1
		 *
		 * @param bool $allowed    Whether the user is allowed to manage the link/seed action.
		 * @param int  $release_id Release post ID on the hub site.
		 * @param int  $store_id   Target WooCommerce store site ID.
		 */
		return (bool) apply_filters( 'mds_wc_user_can_manage_spoke_product', $allowed, $release_id, $store_id );
	}
}
