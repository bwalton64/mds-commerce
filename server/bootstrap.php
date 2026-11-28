<?php
/**
 * MDS Commerce — Proprietary Gateway Server Module Bootstrap.
 *
 * Auto-loaded by `MDS\CloudGateway\Core\ProxyRouter::discover_modules()` in Dev
 * (`../mds-commerce/server/bootstrap.php`) and deployed to `mds-cloud-gateway/services/mds-commerce/`
 * in SQA/Prod. Strictly excluded from customer plugin ZIPs via `.distignore`.
 *
 * @package   MDS\Commerce\Server
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   Proprietary — Musty Dungeon Studios Commercial Server Module
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $router ) && is_object( $router ) && method_exists( $router, 'register_service' ) ) {
	// Centralized Physical POD specification rulebooks (updatable without plugin patches).
	$router->register_service(
		'commerce-pod-specs',
		static function ( array $payload ): array {
			$vendor = sanitize_key( (string) ( $payload['vendor'] ?? '' ) );
			$specs  = array(
				'elasticstage'    => array(
					'name'        => 'elasticStage',
					'formats'     => array( 'vinyl_12', 'cd' ),
					'audio_spec'  => 'WAV 16/24-bit 44.1/48kHz',
					'cover_px'    => '3600x3600 @ 300 DPI',
					'portal_url'  => 'https://elasticstage.com',
				),
				'kunaki'          => array(
					'name'        => 'Kunaki',
					'formats'     => array( 'cd', 'vinyl_12' ),
					'audio_spec'  => 'WAV 16-bit 44.1kHz stereo',
					'cover_px'    => '1417x1417 (CD) / 3675x3675 (Vinyl)',
					'portal_url'  => 'https://kunaki.com',
				),
				'trepstar'        => array(
					'name'        => 'TrepStar',
					'formats'     => array( 'cd', 'dvd' ),
					'audio_spec'  => 'WAV 16-bit 44.1kHz',
					'cover_px'    => '1425x1425 @ 300 DPI',
					'portal_url'  => 'https://www.trepstar.com',
				),
				'diggers_factory' => array(
					'name'        => 'Diggers Factory',
					'formats'     => array( 'vinyl_12', 'cassette', 'cd' ),
					'audio_spec'  => 'WAV 24-bit 44.1/96kHz',
					'cover_px'    => '3720x3720 CMYK PDF @ 300 DPI',
					'portal_url'  => 'https://www.diggersfactory.com',
				),
				'qrates'          => array(
					'name'        => 'Qrates',
					'formats'     => array( 'vinyl_12', 'vinyl_7', 'cassette' ),
					'audio_spec'  => 'WAV 16/24-bit 44.1/48kHz',
					'cover_px'    => '3700x3700 CMYK @ 300 DPI',
					'portal_url'  => 'https://qrates.com',
				),
			);

			if ( '' !== $vendor ) {
				return isset( $specs[ $vendor ] )
					? array( 'ok' => true, 'vendor' => $vendor, 'spec' => $specs[ $vendor ] )
					: array( 'ok' => false, 'code' => 'vendor_not_found', 'error' => 'Unknown POD vendor.' );
			}

			return array(
				'ok'      => true,
				'vendors' => $specs,
			);
		},
		'pro',
		0
	);

	// Remote commerce store sync relay seam (Shopify, Bandcamp Merch).
	$router->register_service(
		'commerce-store-sync',
		static function ( array $payload ): array {
			$platform = sanitize_key( (string) ( $payload['platform'] ?? '' ) );
			if ( ! in_array( $platform, array( 'shopify', 'bandcamp_merch' ), true ) ) {
				return array( 'ok' => false, 'code' => 'unsupported_platform', 'error' => 'Unsupported remote commerce platform.' );
			}
			return array(
				'ok'       => true,
				'platform' => $platform,
				'status'   => 'ready',
			);
		},
		'pro',
		1
	);
}
