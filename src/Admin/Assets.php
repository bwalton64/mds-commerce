<?php
/**
 * WooCommerce integration admin assets.
 *
 * @package MDS\Commerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Admin;

use MDS\Commerce\Plugin;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues WooCommerce integration admin scripts and styles conditionally.
 *
 * @since 1.0.1
 */
final class Assets {

	/**
	 * Enqueue scripts and styles for the current admin screen.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! Plugin::is_active() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_wc_settings  = 'release_page_mds-release-settings' === $screen->id && Plugin::is_woocommerce_active_on_current_site();
		$is_hub          = ! class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) || \MDS\ReleaseManager\Api\ExtensionApi::is_hub();
		$is_release_edit = 'post' === $screen->base && 'release' === $screen->post_type && $is_hub;
		$is_artist_edit  = 'post' === $screen->base && 'artist' === $screen->post_type && is_multisite() && $is_hub;

		if ( $is_wc_settings ) {
			self::enqueue_site_settings_script();
		}

		if ( $is_release_edit ) {
			self::enqueue_product_linker_script();
		}

		if ( $is_artist_edit ) {
			self::enqueue_artist_stores_script();
		}

		if ( $is_release_edit || $is_artist_edit || $is_wc_settings ) {
			self::enqueue_admin_styles();
		}
	}

	/**
	 * Resolve a built asset entry or fall back to the uncompiled source file.
	 *
	 * @since 1.0.1
	 *
	 * @param string            $entry        Built asset base filename without extension.
	 * @param string            $fallback_rel Relative path to the uncompiled source file.
	 * @param array<int,string> $default_deps Default script/style dependencies.
	 * @param string            $extension    Asset extension ('js' or 'css').
	 * @return array{url: string, dependencies: array<int, string>, version: string}
	 */
	private static function resolve_asset(
		string $entry,
		string $fallback_rel,
		array $default_deps,
		string $extension = 'js'
	): array {
		$built_file = MDS_CM_PATH . 'build/' . $entry . '.' . $extension;
		$asset_file = MDS_CM_PATH . 'build/' . $entry . '.asset.php';

		if ( file_exists( $built_file ) && file_exists( $asset_file ) ) {
			$manifest = require $asset_file;
			$deps     = is_array( $manifest['dependencies'] ?? null ) ? $manifest['dependencies'] : array();
			$version  = isset( $manifest['version'] ) ? (string) $manifest['version'] : MDS_CM_VERSION;

			return array(
				'url'          => MDS_CM_URL . 'build/' . $entry . '.' . $extension,
				'dependencies' => array_values( array_unique( array_merge( $default_deps, $deps ) ) ),
				'version'      => $version,
			);
		}

		$fallback_path = MDS_CM_PATH . $fallback_rel;
		$version       = file_exists( $fallback_path ) ? (string) filemtime( $fallback_path ) : MDS_CM_VERSION;

		return array(
			'url'          => MDS_CM_URL . $fallback_rel,
			'dependencies' => $default_deps,
			'version'      => $version,
		);
	}

	/**
	 * Enqueue the Artist CPT WooCommerce Target Stores script and its REST configuration.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private static function enqueue_artist_stores_script(): void {
		if ( class_exists( \MDS\ReleaseManager\Api\ExtensionApi::class ) && ! \MDS\ReleaseManager\Api\ExtensionApi::is_hub() ) {
			return;
		}

		$handle = 'mds_wc_artist_stores';
		$asset  = self::resolve_asset(
			'artist-stores',
			'src/Admin/artist-stores.js',
			array( 'wp-a11y' ),
			'js'
		);

		wp_register_script(
			$handle,
			$asset['url'],
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_script( $handle );
		wp_set_script_translations( $handle, 'mds-commerce' );

		global $post;
		$artist_id = ( $post instanceof WP_Post && 'artist' === $post->post_type ) ? (int) $post->ID : 0;

		$config = array(
			'refreshUrl' => esc_url_raw( rest_url( 'mds-wc/v1/stores/refresh' ) ),
			'syncUrl'    => $artist_id > 0
				? esc_url_raw( rest_url( 'mds-wc/v1/artists/' . $artist_id . '/sync-releases' ) )
				: '',
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'messages'   => array(
				'refreshing'   => __( 'Refreshing…', 'mds-commerce' ),
				'refreshed'    => __( 'WooCommerce store list refreshed.', 'mds-commerce' ),
				'refreshError' => __( 'Unable to refresh WooCommerce stores.', 'mds-commerce' ),
				'noStores'     => __( 'No active WooCommerce stores were detected on this network.', 'mds-commerce' ),
				'syncing'      => __( 'Syncing Releases…', 'mds-commerce' ),
				/* translators: %d: Number of releases synchronized. */
				'syncComplete' => __( 'Synced Artist and %d release(s) to selected stores.', 'mds-commerce' ),
				/* translators: 1: Number of releases synchronized, 2: Number of errors. */
				'syncPartial'  => __( 'Synced %1$d release(s) with %2$d error(s).', 'mds-commerce' ),
				'syncError'    => __( 'Unable to synchronize Artist releases.', 'mds-commerce' ),
			),
		);

		wp_add_inline_script(
			$handle,
			'window.mdsWcArtistStores = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue the per-site settings script for Releases -> Settings -> WooCommerce Integration.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	private static function enqueue_site_settings_script(): void {
		$handle = 'mds_wc_site_settings';
		$asset  = self::resolve_asset(
			'site-settings',
			'src/Admin/site-settings.js',
			array( 'wp-a11y' ),
			'js'
		);

		wp_register_script(
			$handle,
			$asset['url'],
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_script( $handle );
		wp_set_script_translations( $handle, 'mds-commerce' );

		$settings = SiteSettings::get_settings();
		$config   = array(
			'url'             => esc_url_raw( rest_url( 'mds-wc/v1/settings' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'previewNonce'    => wp_create_nonce( SiteSettings::PREVIEW_NONCE_ACTION ),
			'slotOrder'       => array_keys( SiteSettings::get_active_slots() ),
			'slotLabels'      => SiteSettings::get_placement_choices(),
			'availableBlocks' => SiteSettings::get_available_blocks(),
			'items'           => $settings['items'],
			'customBlockKey'  => SiteSettings::CUSTOM_BLOCK_KEY,
			'messages'        => array(
				'save'              => __( 'Save Settings', 'mds-commerce' ),
				'saving'            => __( 'Saving…', 'mds-commerce' ),
				'saved'             => __( 'WooCommerce integration settings saved.', 'mds-commerce' ),
				'saveError'         => __( 'Unable to save WooCommerce integration settings.', 'mds-commerce' ),
				'enabled'           => __( 'Enabled', 'mds-commerce' ),
				'remove'            => __( 'Remove block', 'mds-commerce' ),
				'moveUp'            => __( 'Move up', 'mds-commerce' ),
				'moveDown'          => __( 'Move down', 'mds-commerce' ),
				'hideBuyNow'        => __( 'Hide Buy Now icon on product pages', 'mds-commerce' ),
				'customPlaceholder' => __( '[release-links show_audio="1"]', 'mds-commerce' ),
				'customLabel'       => __( 'Custom Shortcode or Block Markup', 'mds-commerce' ),
				'emptyList'         => __( 'No blocks added yet. Select an MDS block above and click "+ Add Block".', 'mds-commerce' ),
				/* translators: 1: Block name, 2: Placement slot name. */
				'movedTo'           => __( 'Moved %1$s to %2$s.', 'mds-commerce' ),
				/* translators: %s: Block name. */
				'addedBlock'        => __( 'Added %s to product layout.', 'mds-commerce' ),
				/* translators: %s: Block name. */
				'removedBlock'      => __( 'Removed %s from product layout.', 'mds-commerce' ),
				'previewUpdated'    => __( 'Live preview updated.', 'mds-commerce' ),
			),
		);

		wp_add_inline_script(
			$handle,
			'window.mdsWcSiteSettings = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue the release product linker script, REST endpoints, and initial release seed data.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	private static function enqueue_product_linker_script(): void {
		$handle = 'mds_wc_product_linker';
		$asset  = self::resolve_asset(
			'product-linker',
			'src/Admin/product-linker.js',
			array( 'wp-a11y' ),
			'js'
		);

		wp_register_script(
			$handle,
			$asset['url'],
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_script( $handle );
		wp_set_script_translations( $handle, 'mds-commerce' );

		global $post;
		$release_seed = array(
			'id'           => 0,
			'title'        => '',
			'excerpt'      => '',
			'content'      => '',
			'upc'          => '',
			'thumbnailUrl' => '',
		);

		if ( $post instanceof WP_Post && 'release' === $post->post_type ) {
			$upc_value    = get_post_meta( $post->ID, 'upc_ean', true );
			$thumb_url    = get_the_post_thumbnail_url( $post->ID, 'medium' );
			$release_seed = array(
				'id'           => (int) $post->ID,
				'title'        => (string) $post->post_title,
				'excerpt'      => (string) $post->post_excerpt,
				'content'      => (string) $post->post_content,
				'upc'          => is_scalar( $upc_value ) ? (string) $upc_value : '',
				'thumbnailUrl' => is_string( $thumb_url ) ? $thumb_url : '',
			);
		}

		$config = array(
			'searchUrl'      => esc_url_raw( rest_url( 'mds-wc/v1/search' ) ),
			'linkUrl'        => esc_url_raw( rest_url( 'mds-wc/v1/link' ) ),
			'generateUrl'    => esc_url_raw( rest_url( 'mds-wc/v1/generate' ) ),
			'taxonomiesUrl'  => esc_url_raw( rest_url( 'mds-wc/v1/taxonomies' ) ),
			'syncProductUrl' => esc_url_raw( rest_url( 'mds-wc/v1/sync-product' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'release'        => $release_seed,
			'messages'       => array(
				'searching'          => __( 'Searching store products…', 'mds-commerce' ),
				'noResults'          => __( 'No matching products found on this store.', 'mds-commerce' ),
				'searchError'        => __( 'Unable to search products on this store.', 'mds-commerce' ),
				'linking'            => __( 'Linking product…', 'mds-commerce' ),
				'linked'             => __( 'Product linked and synced to spokes.', 'mds-commerce' ),
				'linkError'          => __( 'Unable to link the selected product.', 'mds-commerce' ),
				'unlinking'          => __( 'Unlinking…', 'mds-commerce' ),
				'unlinked'           => __( 'Product unlinked from this store.', 'mds-commerce' ),
				'unlinkError'        => __( 'Unable to unlink this product.', 'mds-commerce' ),
				'confirmUnlink'      => __( 'Unlink this product from the release? The product in the store will not be deleted.', 'mds-commerce' ),
				'loadingTaxonomies'  => __( 'Loading store categories…', 'mds-commerce' ),
				'noCategories'       => __( 'No categories found on this store.', 'mds-commerce' ),
				'creating'           => __( 'Creating product…', 'mds-commerce' ),
				'createSubmit'       => __( 'Create & Link Product', 'mds-commerce' ),
				'created'            => __( 'Product created and linked to this release.', 'mds-commerce' ),
				'createError'        => __( 'Unable to create product on this store.', 'mds-commerce' ),
				'viewInStore'        => __( 'View in Store', 'mds-commerce' ),
				'editInStore'        => __( 'Edit in Store', 'mds-commerce' ),
				'changeLink'         => __( 'Change Link', 'mds-commerce' ),
				'unlink'             => __( 'Unlink', 'mds-commerce' ),
				'statusPublished'    => __( 'Published', 'mds-commerce' ),
				'statusDraftPending' => __( 'Draft — Pending Store Review', 'mds-commerce' ),
				'statusLinked'       => __( 'Linked', 'mds-commerce' ),
				'statusUnlinked'     => __( 'Not Linked', 'mds-commerce' ),
				'searchPlaceholder'  => __( 'Search existing store products…', 'mds-commerce' ),
				'createNew'          => __( 'Create Product', 'mds-commerce' ),
				'cancel'             => __( 'Cancel', 'mds-commerce' ),
				'skuPrefix'          => __( 'SKU:', 'mds-commerce' ),
				'gtinPrefix'         => __( 'GTIN:', 'mds-commerce' ),
				'allFormatLabel'     => __( 'All', 'mds-commerce' ),
				'buyNowAdded'        => __( 'Added linked WooCommerce product URL to Buy Now formats.', 'mds-commerce' ),
				'buyNowExists'       => __( 'All linked published WooCommerce product URLs are already listed in Buy Now.', 'mds-commerce' ),
				'outOfSync'          => __( 'Out of sync', 'mds-commerce' ),
				'driftHeading'       => __( 'Product differs from this Release', 'mds-commerce' ),
				'driftSyncBtn'       => __( 'Update Product', 'mds-commerce' ),
				'driftSyncing'       => __( 'Updating product…', 'mds-commerce' ),
				'driftSynced'        => __( 'Selected fields updated on the WooCommerce product.', 'mds-commerce' ),
				'driftSyncError'     => __( 'Unable to update the linked WooCommerce product.', 'mds-commerce' ),
				'driftSelectOne'     => __( 'Select at least one field to update.', 'mds-commerce' ),
				/* translators: 1: Current SKU value, 2: New UPC value. */
				'driftSkuOption'     => __( 'Also update SKU (%1$s → %2$s)', 'mds-commerce' ),
			),
		);

		wp_add_inline_script(
			$handle,
			'window.mdsWcProductLinker = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue admin styles for the release linker and network settings.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	private static function enqueue_admin_styles(): void {
		$asset = self::resolve_asset(
			'admin',
			'src/Admin/admin.css',
			array(),
			'css'
		);

		wp_register_style(
			'mds_wc_admin',
			$asset['url'],
			array(),
			$asset['version']
		);
		wp_enqueue_style( 'mds_wc_admin' );
	}
}
