<?php
/**
 * Central registry for built-in and third-party CommerceProviderInterface implementations.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Registry;

use MDS\Commerce\Contracts\CommerceProviderInterface;
use MDS\Commerce\Providers\Gateway\GatewayRemoteProvider;
use MDS\Commerce\Providers\WooCommerceLocal\WooCommerceLocalProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Manages registered commerce providers and exposes the `mds_cm_register_providers` extension hook.
 *
 * @since 3.0.0
 */
final class ProviderManager {

	/**
	 * Registered providers keyed by provider slug.
	 *
	 * @since 3.0.0
	 * @var array<string, CommerceProviderInterface>|null
	 */
	private static ?array $providers = null;

	/**
	 * Return all registered commerce providers keyed by slug.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, CommerceProviderInterface> Registered providers.
	 */
	public static function all(): array {
		if ( null !== self::$providers ) {
			return self::$providers;
		}

		$defaults = array(
			'wc_local'    => new WooCommerceLocalProvider(),
			'shopify'     => new GatewayRemoteProvider( 'shopify', __( 'Shopify (Admin GraphQL via Gateway)', 'mds-commerce' ) ),
			'wc_remote'   => new GatewayRemoteProvider( 'wc_remote', __( 'WooCommerce Remote (REST v3 via Gateway)', 'mds-commerce' ) ),
			'wix'         => new GatewayRemoteProvider( 'wix', __( 'Wix Stores (Catalog V3 via Gateway)', 'mds-commerce' ) ),
			'bigcartel'   => new GatewayRemoteProvider( 'bigcartel', __( 'Big Cartel (JSON:API v1 via Gateway)', 'mds-commerce' ) ),
			'squarespace' => new GatewayRemoteProvider( 'squarespace', __( 'Squarespace Commerce (via Gateway)', 'mds-commerce' ) ),
		);

		/**
		 * Filters the registered MDS Commerce providers.
		 *
		 * Third-party or companion plugins can register additional CommerceProviderInterface
		 * implementations (e.g., Shopify, Wix, Big Cartel, Squarespace) keyed by provider slug.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, CommerceProviderInterface> $providers Providers keyed by slug.
		 */
		$filtered = apply_filters( 'mds_cm_register_providers', $defaults );

		$validated = array();
		if ( is_array( $filtered ) ) {
			foreach ( $filtered as $provider ) {
				if ( $provider instanceof CommerceProviderInterface ) {
					$validated[ $provider->get_slug() ] = $provider;
				}
			}
		}

		self::$providers = $validated;
		return self::$providers;
	}

	/**
	 * Retrieve a single registered provider by slug.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Provider slug (e.g., `wc_local`).
	 * @return CommerceProviderInterface|null Provider instance or null.
	 */
	public static function get( string $slug ): ?CommerceProviderInterface {
		$all = self::all();
		return $all[ sanitize_key( $slug ) ] ?? null;
	}

	/**
	 * Reset the per-request provider cache (useful for tests).
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$providers = null;
	}
}
