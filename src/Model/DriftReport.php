<?php
/**
 * Typed DriftReport DTO representing differences between a Hub Release and a linked store product.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates field-level drift findings for admin review and selective synchronization.
 *
 * @since 3.0.0
 */
final class DriftReport {

	/**
	 * Drift item entries.
	 *
	 * @since 3.0.0
	 * @var array<int, array<string, mixed>>
	 */
	public array $items;

	/**
	 * Construct a DriftReport.
	 *
	 * @since 3.0.0
	 *
	 * @param array<int, array<string, mixed>> $items Drift diff items.
	 */
	public function __construct( array $items = array() ) {
		$this->items = array_values( $items );
	}

	/**
	 * Check whether any drift was detected.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True when at least one field differs.
	 */
	public function has_drift(): bool {
		return count( $this->items ) > 0;
	}

	/**
	 * Convert to legacy/REST array shape.
	 *
	 * @since 3.0.0
	 *
	 * @return array{has_drift: bool, count: int, items: array<int, array<string, mixed>>}
	 */
	public function to_array(): array {
		return array(
			'has_drift' => $this->has_drift(),
			'count'     => count( $this->items ),
			'items'     => $this->items,
		);
	}
}
