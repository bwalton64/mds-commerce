<?php
/**
 * Print-on-Demand & Manual-Portal Provisioning Pack Builder.
 *
 * Generates structured release payloads (title, artist, UPC, release date, label,
 * cover art URL/dimensions, formats, and portal links) for elasticStage, TrepStar,
 * Kunaki, Diggers Factory, Qrates, and Bandcamp—usable by both the in-admin
 * 1-click Copy Drawer and browser-capable AI agents via WordPress Abilities / MCP.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Providers\Prep;

use MDS\Commerce\Contracts\ExportPrepProviderInterface;
use MDS\Commerce\Providers\WooCommerceLocal\ExternalVariationsBridge;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Builds verified, deterministic provisioning packs for manual-portal POD manufacturers.
 *
 * @since 3.0.0
 */
final class PodProvisioningProvider implements ExportPrepProviderInterface {

	/**
	 * Build a structured provisioning/export payload for a Hub Release.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post              $release Hub Release post object.
	 * @param array<string, mixed> $options Channel-specific export options.
	 * @return array<string, mixed> Structured provisioning payload.
	 */
	public function build_provisioning_pack( WP_Post $release, array $options = array() ): array {
		$release_id = (int) $release->ID;
		$channel    = sanitize_key( (string) ( $options['channel'] ?? 'elasticstage' ) );
		$presets    = ExternalVariationsBridge::get_pod_presets();
		$preset     = $presets[ $channel ] ?? array(
			'label'          => ucfirst( $channel ),
			'formats'        => array( 'Vinyl', 'CD' ),
			'model'          => 'external_checkout',
			'portal_url'     => '',
			'default_button' => __( 'Order Physical Release', 'mds-commerce' ),
		);

		$upc          = (string) get_post_meta( $release_id, 'upc_ean', true );
		$release_date = (string) get_post_meta( $release_id, 'release_date', true );
		$record_label = (string) get_post_meta( $release_id, 'record_label', true );
		$distributor  = (string) get_post_meta( $release_id, 'distributor', true );
		$artist_uuid  = (string) get_post_meta( $release_id, 'artist_id', true );
		$artist_name  = self::resolve_artist_name( $artist_uuid );

		$thumb_id   = (int) get_post_thumbnail_id( $release_id );
		$image_url  = $thumb_id > 0 ? (string) wp_get_attachment_image_url( $thumb_id, 'full' ) : '';
		$image_meta = $thumb_id > 0 ? wp_get_attachment_metadata( $thumb_id ) : array();
		$width      = is_array( $image_meta ) ? (int) ( $image_meta['width'] ?? 0 ) : 0;
		$height     = is_array( $image_meta ) ? (int) ( $image_meta['height'] ?? 0 ) : 0;

		$formats = wp_get_post_terms( $release_id, 'release_media_format', array( 'fields' => 'names' ) );
		if ( ! is_array( $formats ) || is_wp_error( $formats ) ) {
			$formats = array();
		}

		$types = wp_get_post_terms( $release_id, 'release_type', array( 'fields' => 'names' ) );
		if ( ! is_array( $types ) || is_wp_error( $types ) ) {
			$types = array();
		}

		$pack = array(
			'schema_version' => '1.0',
			'channel'        => $channel,
			'channel_label'  => (string) $preset['label'],
			'portal_url'     => (string) $preset['portal_url'],
			'model'          => (string) $preset['model'],
			'release_id'     => $release_id,
			'fields'         => array(
				'title'            => (string) $release->post_title,
				'artist'           => $artist_name,
				'upc_ean'          => trim( $upc ),
				'suggested_sku'    => '' !== trim( $upc ) ? trim( $upc ) : 'MDS-' . $release_id,
				'release_date'     => trim( $release_date ),
				'record_label'     => trim( $record_label ),
				'distributor'      => trim( $distributor ),
				'release_types'    => array_values( array_map( 'strval', $types ) ),
				'media_formats'    => array_values( array_map( 'strval', $formats ) ),
				'short_summary'    => wp_strip_all_tags( (string) $release->post_excerpt ),
				'full_description' => wp_strip_all_tags( (string) $release->post_content ),
			),
			'artwork'        => array(
				'url'         => $image_url,
				'width'       => $width,
				'height'      => $height,
				'is_square'   => $width > 0 && $width === $height,
				'print_ready' => $width >= 3000 && $height >= 3000,
			),
		);

		/**
		 * Filters the generated POD / manual-portal provisioning pack for a Release.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, mixed> $pack    Provisioning pack payload.
		 * @param WP_Post              $release Hub Release post object.
		 * @param string               $channel Target channel slug.
		 */
		return (array) apply_filters( 'mds_cm_pod_provisioning_pack', $pack, $release, $channel );
	}

	/**
	 * Resolve the Artist display name from `_mds_artist_uuid`.
	 *
	 * @since 3.0.0
	 *
	 * @param string $artist_uuid Artist UUID meta value.
	 * @return string Artist title or empty string.
	 */
	private static function resolve_artist_name( string $artist_uuid ): string {
		if ( '' === $artist_uuid || ! function_exists( 'get_posts' ) ) {
			return '';
		}

		$artists = get_posts(
			array(
				'post_type'      => 'artist',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_mds_artist_uuid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Targeted UUID lookup.
				'meta_value'     => $artist_uuid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Targeted UUID lookup.
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $artists ) && $artists[0] instanceof WP_Post ) {
			return (string) $artists[0]->post_title;
		}

		return '';
	}
}
