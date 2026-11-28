<?php
/**
 * Private plugin updater backed by the Musty Dungeon Studios API Gateway.
 *
 * Uses WordPress's native `update_plugins_{hostname}` hook (`Update URI: https://api.mustydungeon.com/v1/updates/mds-commerce`)
 * so only sites with an active commercial license receive signed ZIP update packages.
 *
 * @package   MDS\Commerce
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @since     3.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Gateway;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates MDS Commerce with the private MDS API Gateway update server.
 *
 * @since 3.0.1
 */
final class GatewayUpdater {

	/**
	 * Transient key caching the latest update check response (6 hours).
	 *
	 * @since 3.0.1
	 * @var string
	 */
	private const CACHE_KEY = 'mds_cm_gateway_update_check';

	/**
	 * Register the native WordPress `update_plugins_api.mustydungeon.com` filter.
	 *
	 * @since 3.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'update_plugins_api.mustydungeon.com', array( self::class, 'check_update' ), 10, 4 );
	}

	/**
	 * Query the MDS API Gateway for an authenticated update package when a valid license key is present.
	 *
	 * @since 3.0.1
	 *
	 * @param array<string, mixed>|false $update      Existing update data or false.
	 * @param array<string, mixed>       $plugin_data Plugin header data.
	 * @param string                     $plugin_file Plugin basename.
	 * @param array<int, string>         $locales     Installed locales.
	 * @return array<string, mixed>|false Update metadata or false.
	 */
	public static function check_update(
		array|false $update,
		array $plugin_data,
		string $plugin_file,
		array $locales
	): array|false {
		unset( $locales );

		if ( defined( 'MDS_CM_BASENAME' ) && MDS_CM_BASENAME !== $plugin_file ) {
			return $update;
		}

		if ( '' === GatewayClient::get_license_key() ) {
			return false;
		}

		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$current_version = (string) ( $plugin_data['Version'] ?? ( defined( 'MDS_CM_VERSION' ) ? MDS_CM_VERSION : '3.0.1' ) );
		$response        = GatewayClient::request(
			'updates/mds-commerce',
			'POST',
			array(
				'version'    => $current_version,
				'wp_version' => get_bloginfo( 'version' ),
				'php'        => PHP_VERSION,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['version'] ) || empty( $response['package'] ) ) {
			return false;
		}

		if ( ! version_compare( (string) $response['version'], $current_version, '>' ) ) {
			return false;
		}

		$payload = array(
			'slug'         => 'mds-commerce',
			'version'      => sanitize_text_field( (string) $response['version'] ),
			'url'          => esc_url_raw( (string) ( $response['url'] ?? 'https://mustydungeon.com/mds-commerce' ) ),
			'package'      => esc_url_raw( (string) $response['package'] ),
			'requires'     => sanitize_text_field( (string) ( $response['requires'] ?? '7.0' ) ),
			'tested'       => sanitize_text_field( (string) ( $response['tested'] ?? '7.0' ) ),
			'requires_php' => sanitize_text_field( (string) ( $response['requires_php'] ?? '8.0' ) ),
			'icons'        => array(
				'svg' => defined( 'MDS_CM_URL' ) ? MDS_CM_URL . 'assets/icon.svg' : '',
			),
		);

		set_site_transient( self::CACHE_KEY, $payload, 6 * HOUR_IN_SECONDS );
		return $payload;
	}
}
