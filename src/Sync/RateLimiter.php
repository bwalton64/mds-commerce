<?php
/**
 * Per-store rate-limit cooldown circuit breaker.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Sync;

use MDS\Commerce\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks per-store HTTP 429 / Retry-After cooldown windows in transients
 * so temporary upstream throttling never blocks Release Editor saves.
 *
 * @since 3.0.0
 */
final class RateLimiter {

	/**
	 * Record a cooldown window for a given store key.
	 *
	 * @since 3.0.0
	 *
	 * @param string $store_key       Canonical StoreKey string.
	 * @param int    $retry_after_sec Cooldown duration in seconds (clamped 5–3600).
	 * @param string $reason          Optional diagnostic message.
	 * @return void
	 */
	public static function set_cooldown( string $store_key, int $retry_after_sec, string $reason = '' ): void {
		$ttl = max( 5, min( 3600, $retry_after_sec ) );
		set_site_transient(
			self::transient_key( $store_key ),
			array(
				'until'  => time() + $ttl,
				'reason' => sanitize_text_field( $reason ),
			),
			$ttl
		);
	}

	/**
	 * Return remaining cooldown seconds for a store key, or 0 when ready.
	 *
	 * @since 3.0.0
	 *
	 * @param string $store_key Canonical StoreKey string.
	 * @return int Remaining seconds (0 if not throttled).
	 */
	public static function get_remaining_seconds( string $store_key ): int {
		$data = get_site_transient( self::transient_key( $store_key ) );
		if ( ! is_array( $data ) || empty( $data['until'] ) ) {
			return 0;
		}

		$remaining = (int) $data['until'] - time();
		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Check whether a store key is currently in cooldown.
	 *
	 * @since 3.0.0
	 *
	 * @param string $store_key Canonical StoreKey string.
	 * @return bool True when throttled.
	 */
	public static function is_throttled( string $store_key ): bool {
		return self::get_remaining_seconds( $store_key ) > 0;
	}

	/**
	 * Clear any active cooldown for a store key.
	 *
	 * @since 3.0.0
	 *
	 * @param string $store_key Canonical StoreKey string.
	 * @return void
	 */
	public static function clear( string $store_key ): void {
		delete_site_transient( self::transient_key( $store_key ) );
	}

	/**
	 * Build a safe transient key for a StoreKey cooldown.
	 *
	 * @since 3.0.0
	 *
	 * @param string $store_key Canonical StoreKey string.
	 * @return string Transient key.
	 */
	private static function transient_key( string $store_key ): string {
		return Config::TRANSIENT_PREFIX . 'cooldown_' . md5( $store_key );
	}
}
