<?php
/**
 * Unified NormalizedProduct DTO shared across all commerce providers.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Provider-agnostic product summary used for linking, drift detection, and Store Chooser rendering.
 *
 * @since 3.0.0
 */
final class NormalizedProduct {

	/**
	 * Remote product ID.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $remote_id;

	/**
	 * Product title.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $title;

	/**
	 * Primary SKU.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $sku;

	/**
	 * UPC / EAN / GTIN barcode.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $gtin;

	/**
	 * Formatted display price string.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $price;

	/**
	 * Product status (`publish`, `draft`, `active`, `archived`).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $status;

	/**
	 * Stock availability (`instock`, `outofstock`, `onbackorder`, `ondemand`).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $stock_status;

	/**
	 * Public storefront URL.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $permalink;

	/**
	 * Admin edit URL on the target store (if available).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $edit_url;

	/**
	 * Product cover image URL.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public string $image_url;

	/**
	 * Extra provider-specific metadata (e.g., `thumbnail_id`, `hub_media_id`, `variations`).
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	public array $meta;

	/**
	 * Construct a NormalizedProduct DTO.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $data Associative product fields.
	 */
	public function __construct( array $data ) {
		$this->remote_id    = (string) ( $data['remote_id'] ?? $data['id'] ?? '' );
		$this->title        = (string) ( $data['title'] ?? '' );
		$this->sku          = (string) ( $data['sku'] ?? '' );
		$this->gtin         = (string) ( $data['gtin'] ?? '' );
		$this->price        = (string) ( $data['price'] ?? '' );
		$this->status       = (string) ( $data['status'] ?? 'draft' );
		$this->stock_status = (string) ( $data['stock_status'] ?? 'instock' );
		$this->permalink    = (string) ( $data['permalink'] ?? '' );
		$this->edit_url     = (string) ( $data['edit_url'] ?? '' );
		$this->image_url    = (string) ( $data['image_url'] ?? $data['thumb'] ?? '' );
		$this->meta         = is_array( $data['meta'] ?? null ) ? $data['meta'] : array();
	}

	/**
	 * Convert to array payload compatible with REST responses and drift comparison.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, mixed> Array representation.
	 */
	public function to_array(): array {
		return array_merge(
			$this->meta,
			array(
				'id'           => ctype_digit( $this->remote_id ) ? (int) $this->remote_id : $this->remote_id,
				'remote_id'    => $this->remote_id,
				'title'        => $this->title,
				'sku'          => $this->sku,
				'gtin'         => $this->gtin,
				'price'        => $this->price,
				'status'       => $this->status,
				'stock_status' => $this->stock_status,
				'permalink'    => $this->permalink,
				'edit_url'     => $this->edit_url,
				'image_url'    => $this->image_url,
				'thumb'        => $this->image_url,
			)
		);
	}
}
