<?php
/**
 * Optional contract for manual-portal prep & CSV export providers (Bandcamp, Discogs, POD).
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Contracts;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Implemented by channels that generate provisioning payloads or CSV exports rather than calling write APIs.
 *
 * @since 3.0.0
 */
interface ExportPrepProviderInterface {

	/**
	 * Build a structured provisioning/export payload for a Hub Release.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post              $release Hub Release post object.
	 * @param array<string, mixed> $options Channel-specific export options.
	 * @return array<string, mixed> Structured provisioning payload.
	 */
	public function build_provisioning_pack( WP_Post $release, array $options = array() ): array;
}
