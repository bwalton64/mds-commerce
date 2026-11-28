<?php
/**
 * Cached license entitlement manager with 7-day offline grace window.
 *
 * @package   MDS\Commerce
 * @copyright 2026 Musty Dungeon Studios (Walton International Business Group LLC)
 * @license   GPL-3.0-or-later
 * @since     3.0.1
 */

declare(strict_types=1);

namespace MDS\Commerce\Gateway;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates Pro / Artist vs. Label / Network commercial entitlements issued by the MDS API Gateway.
 *
 * Frontend rendering (`[mds-store-chooser]`, WooCommerce Single Product Injection) never blocks on
 * live Gateway calls; entitlements are cached locally with a 7-day offline grace window.
 *
 * @since 3.0.1
 */
final class EntitlementManager {

	/**
	 * Option key caching the last verified entitlement payload.
	 *
	 * @since 3.0.1
	 * @var string
	 */
	public const OPTION_ENTITLEMENTS = 'mds_cm_entitlements';

	/**
	 * Offline grace period in seconds (7 days) when Gateway is temporarily unreachable.
	 *
	 * @since 3.0.1
	 * @var int
	 */
	public const GRACE_WINDOW_SECONDS = 604800;

	/**
	 * Activate a commercial license key against the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @param string $license_key Plaintext license key.
	 * @return array<string, mixed>|WP_Error Normalized entitlement state or error.
	 */
	public static function activate( string $license_key ): array|WP_Error {
		$clean = trim( $license_key );
		if ( '' === $clean ) {
			return new WP_Error( 'mds_cm_empty_license', __( 'Please enter a valid license key.', 'mds-commerce' ), array( 'status' => 400 ) );
		}

		$response = GatewayClient::request(
			'licenses/activate',
			'POST',
			array(
				'plugin' => 'mds-commerce',
				'site'   => home_url( '/' ),
			),
			$clean
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		GatewayClient::save_license_key( $clean );
		$normalized = self::normalize_entitlement_payload( $response );
		self::store_entitlements( $normalized );

		return $normalized;
	}

	/**
	 * Deactivate the current license key locally and notify the Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @return array<string, mixed> Cleared entitlement status.
	 */
	public static function deactivate(): array {
		$key = GatewayClient::get_license_key();
		if ( '' !== $key ) {
			GatewayClient::request(
				'licenses/deactivate',
				'POST',
				array(
					'plugin' => 'mds-commerce',
					'site'   => home_url( '/' ),
				),
				$key
			);
		}

		GatewayClient::save_license_key( '' );
		$empty = self::default_entitlements();
		self::store_entitlements( $empty );

		return $empty;
	}

	/**
	 * Refresh entitlements from the MDS API Gateway.
	 *
	 * @since 3.0.1
	 *
	 * @return array<string, mixed>|WP_Error Updated entitlements or error.
	 */
	public static function refresh(): array|WP_Error {
		$response = GatewayClient::request(
			'licenses/entitlements',
			'POST',
			array(
				'plugin' => 'mds-commerce',
				'site'   => home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$normalized = self::normalize_entitlement_payload( $response );
		self::store_entitlements( $normalized );

		return $normalized;
	}

	/**
	 * Return the cached entitlement payload (never performs a synchronous HTTP call).
	 *
	 * @since 3.0.1
	 *
	 * @return array<string, mixed> Cached entitlement record.
	 */
	public static function get_status(): array {
		$stored = is_multisite()
			? get_site_option( self::OPTION_ENTITLEMENTS, null )
			: get_option( self::OPTION_ENTITLEMENTS, null );

		if ( ! is_array( $stored ) ) {
			return self::default_entitlements();
		}

		return array_merge( self::default_entitlements(), $stored );
	}

	/**
	 * Check whether the site currently holds an active commercial entitlement (including 7-day grace window).
	 *
	 * @since 3.0.1
	 *
	 * @param int|null $now Optional Unix timestamp for deterministic testing.
	 * @return bool True when active or within grace window.
	 */
	public static function is_active( ?int $now = null ): bool {
		$status = self::get_status();
		if ( 'active' !== (string) $status['status'] ) {
			return false;
		}

		$ts         = $now ?? time();
		$expires_at = (int) ( $status['expires_at'] ?? 0 );
		if ( $expires_at > 0 && $ts > ( $expires_at + self::GRACE_WINDOW_SECONDS ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Return the active commercial tier (`none`, `pro`, or `label`).
	 *
	 * @since 3.0.1
	 *
	 * @param int|null $now Optional Unix timestamp for deterministic testing.
	 * @return string Tier slug.
	 */
	public static function get_tier( ?int $now = null ): string {
		if ( ! self::is_active( $now ) ) {
			return 'none';
		}

		$tier = sanitize_key( (string) ( self::get_status()['tier'] ?? 'pro' ) );
		return in_array( $tier, array( 'pro', 'label' ), true ) ? $tier : 'pro';
	}

	/**
	 * Return the maximum number of remote store connections permitted by the active tier (-1 = unlimited).
	 *
	 * @since 3.0.1
	 *
	 * @param int|null $now Optional Unix timestamp for deterministic testing.
	 * @return int Max remote stores allowed.
	 */
	public static function max_remote_stores( ?int $now = null ): int {
		$tier = self::get_tier( $now );
		if ( 'label' === $tier ) {
			return -1;
		}

		if ( 'pro' === $tier ) {
			$override = (int) ( self::get_status()['max_remote_stores'] ?? 3 );
			return $override > 0 ? $override : 3;
		}

		return 0;
	}

	/**
	 * Check whether the active tier permits real-time Gateway Webhook Relay.
	 *
	 * @since 3.0.1
	 *
	 * @param int|null $now Optional Unix timestamp for deterministic testing.
	 * @return bool True for Label tier.
	 */
	public static function can_use_webhooks( ?int $now = null ): bool {
		return 'label' === self::get_tier( $now );
	}

	/**
	 * Check whether the active tier permits Multisite Hub-and-Spoke Label workflows.
	 *
	 * @since 3.0.1
	 *
	 * @param int|null $now Optional Unix timestamp for deterministic testing.
	 * @return bool True for Label tier.
	 */
	public static function can_use_multisite_hub( ?int $now = null ): bool {
		return 'label' === self::get_tier( $now );
	}

	/**
	 * Normalize a Gateway entitlement response payload.
	 *
	 * @since 3.0.1
	 *
	 * @param array<string, mixed> $raw Raw Gateway JSON response.
	 * @return array<string, mixed> Normalized entitlement record.
	 */
	public static function normalize_entitlement_payload( array $raw ): array {
		$tier   = sanitize_key( (string) ( $raw['tier'] ?? 'pro' ) );
		$status = sanitize_key( (string) ( $raw['status'] ?? 'active' ) );

		return array(
			'status'            => in_array( $status, array( 'active', 'expired', 'inactive' ), true ) ? $status : 'inactive',
			'tier'              => in_array( $tier, array( 'pro', 'label' ), true ) ? $tier : 'pro',
			'max_remote_stores' => isset( $raw['max_remote_stores'] ) ? (int) $raw['max_remote_stores'] : ( 'label' === $tier ? -1 : 3 ),
			'webhook_secret'    => sanitize_text_field( (string) ( $raw['webhook_secret'] ?? '' ) ),
			'expires_at'        => isset( $raw['expires_at'] ) ? (int) $raw['expires_at'] : 0,
			'checked_at'        => time(),
			'masked_key'        => sanitize_text_field( (string) ( $raw['masked_key'] ?? '' ) ),
		);
	}

	/**
	 * Persist normalized entitlements to site/network option.
	 *
	 * @since 3.0.1
	 *
	 * @param array<string, mixed> $entitlements Entitlement record.
	 * @return void
	 */
	public static function store_entitlements( array $entitlements ): void {
		if ( is_multisite() ) {
			update_site_option( self::OPTION_ENTITLEMENTS, $entitlements );
		} else {
			update_option( self::OPTION_ENTITLEMENTS, $entitlements, false );
		}
	}

	/**
	 * Return default inactive entitlement record.
	 *
	 * @since 3.0.1
	 *
	 * @return array<string, mixed> Default inactive record.
	 */
	private static function default_entitlements(): array {
		return array(
			'status'            => 'inactive',
			'tier'              => 'none',
			'max_remote_stores' => 0,
			'webhook_secret'    => '',
			'expires_at'        => 0,
			'checked_at'        => 0,
			'masked_key'        => '',
		);
	}
}
