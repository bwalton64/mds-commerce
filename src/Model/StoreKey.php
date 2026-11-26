<?php
/**
 * Immutable StoreKey value object (`{provider}:{id}`).
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a normalized, provider-namespaced store identifier (e.g. `wc_local:3`, `shopify:abc123`).
 *
 * @since 3.0.0
 */
final class StoreKey {

	/**
	 * Provider slug (e.g., `wc_local`, `shopify`, `wix`).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $provider;

	/**
	 * Provider-scoped store identifier (e.g., Multisite blog ID or connection slug).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $store_id;

	/**
	 * Construct a StoreKey.
	 *
	 * @since 3.0.0
	 *
	 * @param string $provider Provider slug.
	 * @param string $store_id Store identifier within the provider.
	 */
	public function __construct( string $provider, string $store_id ) {
		$this->provider = sanitize_key( $provider );
		$this->store_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $store_id ) ?? '';
	}

	/**
	 * Parse a raw key string or legacy integer blog ID into a StoreKey.
	 *
	 * Legacy positive integers are automatically interpreted as `wc_local:{blog_id}`.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $raw Raw StoreKey string or legacy blog ID.
	 * @return self|null Valid StoreKey or null if invalid.
	 */
	public static function parse( int|string $raw ): ?self {
		if ( is_int( $raw ) || ( is_string( $raw ) && ctype_digit( trim( $raw ) ) ) ) {
			$blog_id = (int) $raw;
			return $blog_id > 0 ? new self( 'wc_local', (string) $blog_id ) : null;
		}

		$trimmed = trim( $raw );
		if ( '' === $trimmed || ! str_contains( $trimmed, ':' ) ) {
			return null;
		}

		list( $provider, $id ) = explode( ':', $trimmed, 2 );
		$key                   = new self( $provider, $id );

		return $key->is_valid() ? $key : null;
	}

	/**
	 * Build a StoreKey for a local Multisite WooCommerce blog ID.
	 *
	 * @since 3.0.0
	 *
	 * @param int $blog_id Positive Multisite blog ID.
	 * @return self Local WooCommerce StoreKey.
	 */
	public static function for_local_wc( int $blog_id ): self {
		return new self( 'wc_local', (string) max( 0, $blog_id ) );
	}

	/**
	 * Check whether both provider and store ID segments are non-empty.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True when valid.
	 */
	public function is_valid(): bool {
		return '' !== $this->provider && '' !== $this->store_id && '0' !== $this->store_id;
	}

	/**
	 * Return the provider slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider slug.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * Return the store identifier segment.
	 *
	 * @since 3.0.0
	 *
	 * @return string Store identifier.
	 */
	public function store_id(): string {
		return $this->store_id;
	}

	/**
	 * Return the local Multisite blog ID when this key points to `wc_local`, or 0 otherwise.
	 *
	 * @since 3.0.0
	 *
	 * @return int Positive blog ID or 0.
	 */
	public function local_blog_id(): int {
		if ( 'wc_local' !== $this->provider || ! ctype_digit( $this->store_id ) ) {
			return 0;
		}

		return (int) $this->store_id;
	}

	/**
	 * Return canonical `{provider}:{id}` string representation.
	 *
	 * @since 3.0.0
	 *
	 * @return string Canonical key.
	 */
	public function to_string(): string {
		return $this->provider . ':' . $this->store_id;
	}

	/**
	 * String cast helper.
	 *
	 * @since 3.0.0
	 *
	 * @return string Canonical key.
	 */
	public function __toString(): string {
		return $this->to_string();
	}
}
