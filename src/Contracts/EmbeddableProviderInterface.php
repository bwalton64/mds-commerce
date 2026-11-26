<?php
/**
 * Optional contract for providers that support inline frontend checkout embeds.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Contracts;

use MDS\Commerce\Model\StoreConnection;

defined( 'ABSPATH' ) || exit;

/**
 * Implemented by providers that can render an inline checkout component (e.g., Shopify Storefront Web Components).
 *
 * @since 3.0.0
 */
interface EmbeddableProviderInterface {

	/**
	 * Render inline checkout markup for a linked remote product.
	 *
	 * @since 3.0.0
	 *
	 * @param StoreConnection      $store     Store connection.
	 * @param string               $remote_id Remote product identifier or handle.
	 * @param array<string, mixed> $options   Optional rendering attributes.
	 * @return string Escaped HTML embed markup.
	 */
	public function render_embed( StoreConnection $store, string $remote_id, array $options = array() ): string;
}
