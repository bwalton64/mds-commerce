<?php
/**
 * Typed StoreConnection DTO describing a local or remote commerce store.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Model;

use MDS\Commerce\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable store connection metadata, capabilities, sync mode, and default promo configuration.
 *
 * @since 3.0.0
 */
final class StoreConnection {

	/**
	 * StoreKey value object.
	 *
	 * @since 3.0.0
	 * @var StoreKey
	 */
	public StoreKey $key;

	/**
	 * Human-readable store name.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $name;

	/**
	 * Public storefront base URL.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $url;

	/**
	 * Sync mode (`webhook`, `polling`, or `manual`).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $sync_mode;

	/**
	 * Capability flags map.
	 *
	 * @since 3.0.0
	 * @var array<string, bool>
	 */
	public array $capabilities;

	/**
	 * Optional store-level default promo metadata (badge, coupon_code, priority_weight).
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	public array $promo;

	/**
	 * Construct a StoreConnection DTO.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreKey             $key          StoreKey identifier.
	 * @param string               $name         Store display name.
	 * @param string               $url          Storefront URL.
	 * @param string               $sync_mode    Sync mode (`webhook`, `polling`, `manual`).
	 * @param array<string, bool>  $capabilities Provider capability flags.
	 * @param array<string, mixed> $promo        Optional store-level promo settings.
	 */
	public function __construct(
		StoreKey $key,
		string $name,
		string $url,
		string $sync_mode = 'manual',
		array $capabilities = array(),
		array $promo = array()
	) {
		$this->key          = $key;
		$this->name         = $name;
		$this->url          = $url;
		$this->sync_mode    = in_array( $sync_mode, Config::SYNC_MODES, true ) ? $sync_mode : 'manual';
		$this->capabilities = $capabilities;
		$this->promo        = $promo;
	}

	/**
	 * Export store metadata safe for REST / admin UI / MCP responses (never includes credentials).
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, mixed> Safe array representation.
	 */
	public function to_public_array(): array {
		return array(
			'store_key'    => $this->key->to_string(),
			'provider'     => $this->key->provider(),
			'store_id'     => $this->key->store_id(),
			'blog_id'      => $this->key->local_blog_id(),
			'name'         => $this->name,
			'url'          => $this->url,
			'sync_mode'    => $this->sync_mode,
			'capabilities' => $this->capabilities,
			'promo'        => $this->promo,
		);
	}
}
