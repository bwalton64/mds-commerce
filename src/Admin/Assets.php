<?php
/**
 * WooCommerce integration admin assets.
 *
 * @package MDS\WooCommerce
 * @since   1.0.1
 */

declare(strict_types=1);

namespace MDS\WooCommerce\Admin;

use MDS\WooCommerce\Plugin;
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
		$built_file = MDS_WC_PATH . 'build/' . $entry . '.' . $extension;
		$asset_file = MDS_WC_PATH . 'build/' . $entry . '.asset.php';

		if ( file_exists( $built_file ) && file_exists( $asset_file ) ) {
			$manifest = require $asset_file;
			$deps     = is_array( $manifest['dependencies'] ?? null ) ? $manifest['dependencies'] : array();
			$version  = isset( $manifest['version'] ) ? (string) $manifest['version'] : MDS_WC_VERSION;

			return array(
				'url'          => MDS_WC_URL . 'build/' . $entry . '.' . $extension,
				'dependencies' => array_values( array_unique( array_merge( $default_deps, $deps ) ) ),
				'version'      => $version,
			);
		}

		$fallback_path = MDS_WC_PATH . $fallback_rel;
		$version       = file_exists( $fallback_path ) ? (string) filemtime( $fallback_path ) : MDS_WC_VERSION;

		return array(
			'url'          => MDS_WC_URL . $fallback_rel,
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
			'src/admin/artist-stores.js',
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
		wp_set_script_translations( $handle, 'mds-woocommerce' );

		global $post;
		$artist_id = ( $post instanceof WP_Post && 'artist' === $post->post_type ) ? (int) $post->ID : 0;

		$config = array(
			'refreshUrl' => esc_url_raw( rest_url( 'mds-wc/v1/stores/refresh' ) ),
			'syncUrl'    => $artist_id > 0
				? esc_url_raw( rest_url( 'mds-wc/v1/artists/' . $artist_id . '/sync-releases' ) )
				: '',
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'messages'   => array(
				'refreshing'   => __( 'Refreshing…', 'mds-woocommerce' ),
				'refreshed'    => __( 'WooCommerce store list refreshed.', 'mds-woocommerce' ),
				'refreshError' => __( 'Unable to refresh WooCommerce stores.', 'mds-woocommerce' ),
				'noStores'     => __( 'No active WooCommerce stores were detected on this network.', 'mds-woocommerce' ),
				'syncing'      => __( 'Syncing Releases…', 'mds-woocommerce' ),
				/* translators: %d: Number of releases synchronized. */
				'syncComplete' => __( 'Synced Artist and %d release(s) to selected stores.', 'mds-woocommerce' ),
				/* translators: 1: Number of releases synchronized, 2: Number of errors. */
				'syncPartial'  => __( 'Synced %1$d release(s) with %2$d error(s).', 'mds-woocommerce' ),
				'syncError'    => __( 'Unable to synchronize Artist releases.', 'mds-woocommerce' ),
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
			'src/admin/site-settings.js',
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
		wp_set_script_translations( $handle, 'mds-woocommerce' );

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
				'save'              => __( 'Save Settings', 'mds-woocommerce' ),
				'saving'            => __( 'Saving…', 'mds-woocommerce' ),
				'saved'             => __( 'WooCommerce integration settings saved.', 'mds-woocommerce' ),
				'saveError'         => __( 'Unable to save WooCommerce integration settings.', 'mds-woocommerce' ),
				'enabled'           => __( 'Enabled', 'mds-woocommerce' ),
				'remove'            => __( 'Remove block', 'mds-woocommerce' ),
				'moveUp'            => __( 'Move up', 'mds-woocommerce' ),
				'moveDown'          => __( 'Move down', 'mds-woocommerce' ),
				'hideBuyNow'        => __( 'Hide Buy Now icon on product pages', 'mds-woocommerce' ),
				'customPlaceholder' => __( '[release-links show_audio="1"]', 'mds-woocommerce' ),
				'customLabel'       => __( 'Custom Shortcode or Block Markup', 'mds-woocommerce' ),
				'emptyList'         => __( 'No blocks added yet. Select an MDS block above and click "+ Add Block".', 'mds-woocommerce' ),
				/* translators: 1: Block name, 2: Placement slot name. */
				'movedTo'           => __( 'Moved %1$s to %2$s.', 'mds-woocommerce' ),
				/* translators: %s: Block name. */
				'addedBlock'        => __( 'Added %s to product layout.', 'mds-woocommerce' ),
				/* translators: %s: Block name. */
				'removedBlock'      => __( 'Removed %s from product layout.', 'mds-woocommerce' ),
				'previewUpdated'    => __( 'Live preview updated.', 'mds-woocommerce' ),
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
			'src/admin/product-linker.js',
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
		wp_set_script_translations( $handle, 'mds-woocommerce' );

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
			'searchUrl'     => esc_url_raw( rest_url( 'mds-wc/v1/search' ) ),
			'linkUrl'       => esc_url_raw( rest_url( 'mds-wc/v1/link' ) ),
			'generateUrl'   => esc_url_raw( rest_url( 'mds-wc/v1/generate' ) ),
			'taxonomiesUrl' => esc_url_raw( rest_url( 'mds-wc/v1/taxonomies' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'release'       => $release_seed,
			'messages'      => array(
				'searching'          => __( 'Searching store products…', 'mds-woocommerce' ),
				'noResults'          => __( 'No matching products found on this store.', 'mds-woocommerce' ),
				'searchError'        => __( 'Unable to search products on this store.', 'mds-woocommerce' ),
				'linking'            => __( 'Linking product…', 'mds-woocommerce' ),
				'linked'             => __( 'Product linked and synced to spokes.', 'mds-woocommerce' ),
				'linkError'          => __( 'Unable to link the selected product.', 'mds-woocommerce' ),
				'unlinking'          => __( 'Unlinking…', 'mds-woocommerce' ),
				'unlinked'           => __( 'Product unlinked from this store.', 'mds-woocommerce' ),
				'unlinkError'        => __( 'Unable to unlink this product.', 'mds-woocommerce' ),
				'confirmUnlink'      => __( 'Unlink this product from the release? The product in the store will not be deleted.', 'mds-woocommerce' ),
				'loadingTaxonomies'  => __( 'Loading store categories…', 'mds-woocommerce' ),
				'noCategories'       => __( 'No categories found on this store.', 'mds-woocommerce' ),
				'creating'           => __( 'Creating product…', 'mds-woocommerce' ),
				'createSubmit'       => __( 'Create & Link Product', 'mds-woocommerce' ),
				'created'            => __( 'Product created and linked to this release.', 'mds-woocommerce' ),
				'createError'        => __( 'Unable to create product on this store.', 'mds-woocommerce' ),
				'viewInStore'        => __( 'View in Store', 'mds-woocommerce' ),
				'editInStore'        => __( 'Edit in Store', 'mds-woocommerce' ),
				'changeLink'         => __( 'Change Link', 'mds-woocommerce' ),
				'unlink'             => __( 'Unlink', 'mds-woocommerce' ),
				'statusPublished'    => __( 'Published', 'mds-woocommerce' ),
				'statusDraftPending' => __( 'Draft — Pending Store Review', 'mds-woocommerce' ),
				'statusLinked'       => __( 'Linked', 'mds-woocommerce' ),
				'statusUnlinked'     => __( 'Not Linked', 'mds-woocommerce' ),
				'searchPlaceholder'  => __( 'Search existing store products…', 'mds-woocommerce' ),
				'createNew'          => __( 'Create Product', 'mds-woocommerce' ),
				'cancel'             => __( 'Cancel', 'mds-woocommerce' ),
				'skuPrefix'          => __( 'SKU:', 'mds-woocommerce' ),
				'allFormatLabel'     => __( 'All', 'mds-woocommerce' ),
				'buyNowAdded'        => __( 'Added linked WooCommerce product URL to Buy Now formats.', 'mds-woocommerce' ),
				'buyNowExists'       => __( 'All linked published WooCommerce product URLs are already listed in Buy Now.', 'mds-woocommerce' ),
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
			'src/admin/admin.css',
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
