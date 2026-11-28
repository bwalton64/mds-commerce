<?php
/**
 * Thin HTTP client for the Musty Dungeon Studios API Gateway.
 *
 * @package   MDS\Commerce
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @since     3.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Gateway;

use MDS\Commerce\Security\CredentialVault;
use MDS\Commerce\Sync\RateLimiter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends authenticated JSON requests to the MDS API Gateway with cooldown circuit-breaking.
 *
 * @since 3.0.1
 */
final class GatewayClient {

	/**
	 * Default MDS API Gateway base URL.
	 *
	 * @since 3.0.1
	 * @var string
	 */
	public const DEFAULT_BASE_URL = 'https://api.mustydungeon.com/v1/';

	/**
	 * Option key storing the encrypted commercial license key.
	 *
	 * @since 3.0.1
	 * @var string
	 */
	public const OPTION_LICENSE_KEY = 'mds_cm_license_key';

	/**
	 * Resolve the configured API Gateway base URL (supports `MDS_CM_GATEWAY_URL` constant override).
	 *
	 * @since 3.0.1
	 *
	 * @return string Normalized trailing-slashed base URL.
	 */
	public static function get_base_url(): string {
		$url = defined( 'MDS_CM_GATEWAY_URL' ) && is_string( MDS_CM_GATEWAY_URL )
			? trim( MDS_CM_GATEWAY_URL )
			: self::DEFAULT_BASE_URL;

		$clean = esc_url_raw( $url );
		if ( '' === $clean ) {
			$clean = self::DEFAULT_BASE_URL;
		}

		return trailingslashit( $clean );
	}

	/**
	 * Resolve the active license key from `MDS_CM_LICENSE_KEY` constant or encrypted site/network option.
	 *
	 * @since 3.0.1
	 *
	 * @return string Decrypted license key or empty string.
	 */
	public static function get_license_key(): string {
		$stored = is_multisite()
			? (string) get_site_option( self::OPTION_LICENSE_KEY, '' )
			: (string) get_option( self::OPTION_LICENSE_KEY, '' );

		return CredentialVault::resolve_secret( 'MDS_CM_LICENSE_KEY', $stored );
	}

	/**
	 * Persist an encrypted license key to site/network options.
	 *
	 * @since 3.0.1
	 *
	 * @param string $plaintext_key Raw license key (empty string clears stored key).
	 * @return void
	 */
	public static function save_license_key( string $plaintext_key ): void {
		$clean = trim( $plaintext_key );
		$value = '' !== $clean ? CredentialVault::encrypt( $clean ) : '';

		if ( is_multisite() ) {
			update_site_option( self::OPTION_LICENSE_KEY, $value );
		} else {
			update_option( self::OPTION_LICENSE_KEY, $value, false );
		}
	}

	/**
	 * Execute a JSON request against the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param string               $endpoint    Relative endpoint path (e.g., `commerce/products/search`).
	 * @param string               $method      HTTP method (`GET`, `POST`, `PATCH`, `DELETE`).
	 * @param array<string, mixed> $body        Optional JSON payload.
	 * @param string|null          $license_key Optional explicit license key override.
	 * @return array<string, mixed>|WP_Error Decoded JSON response array or WP_Error.
	 */
	public static function request(
		string $endpoint,
		string $method = 'POST',
		array $body = array(),
		?string $license_key = null
	): array|WP_Error {
		$cooldown_key = 'gateway:mds';
		$remaining    = RateLimiter::get_remaining_seconds( $cooldown_key );
		if ( $remaining > 0 ) {
			return new WP_Error(
				'mds_cm_gateway_throttled',
				sprintf(
					/* translators: %d: Cooldown seconds remaining. */
					__( 'MDS API Gateway is cooling down. Retry in %d seconds.', 'mds-commerce' ),
					$remaining
				),
				array( 'status' => 429 )
			);
		}

		$token = null !== $license_key ? trim( $license_key ) : self::get_license_key();
		if ( '' === $token ) {
			return new WP_Error(
				'mds_cm_missing_license',
				__( 'An active MDS Commerce license key is required to use the MDS API Gateway.', 'mds-commerce' ),
				array( 'status' => 401 )
			);
		}

		$url  = self::get_base_url() . ltrim( $endpoint, '/' );
		$args = array(
			'method'     => strtoupper( $method ),
			'timeout'    => 15,
			'user-agent' => 'MDS-Commerce/' . ( defined( 'MDS_CM_VERSION' ) ? MDS_CM_VERSION : '3.0.1' ) . '; ' . home_url( '/' ),
			'headers'    => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'X-MDS-Site'    => home_url( '/' ),
				'X-MDS-Plugin'  => 'mds-commerce/' . ( defined( 'MDS_CM_VERSION' ) ? MDS_CM_VERSION : '3.0.1' ),
			),
		);

		if ( ! empty( $body ) && in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $status ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			RateLimiter::set_cooldown( $cooldown_key, $retry_after > 0 ? $retry_after : 60, 'HTTP 429 from MDS API Gateway' );
			return new WP_Error(
				'mds_cm_gateway_throttled',
				__( 'MDS API Gateway rate limit reached. Please retry shortly.', 'mds-commerce' ),
				array( 'status' => 429 )
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $decoded ) && ! empty( $decoded['message'] )
				? (string) $decoded['message']
				: sprintf(
					/* translators: %d: HTTP status code. */
					__( 'MDS API Gateway returned HTTP %d.', 'mds-commerce' ),
					$status
				);

			return new WP_Error( 'mds_cm_gateway_error', $message, array( 'status' => $status ) );
		}

		return is_array( $decoded ) ? $decoded : array();
	}
}
