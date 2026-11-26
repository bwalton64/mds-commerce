<?php
/**
 * Lazy class_alias() shims for legacy MDS\WooCommerce\* class names.
 *
 * Loaded directly from mds-commerce.php (never composer.json autoload.files)
 * so companion plugins referencing MDS\WooCommerce\* continue working seamlessly.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$legacy_map = array(
			'MDS\\WooCommerce\\Config'                    => \MDS\Commerce\Config::class,
			'MDS\\WooCommerce\\Plugin'                    => \MDS\Commerce\Plugin::class,
			'MDS\\WooCommerce\\Admin\\Assets'             => \MDS\Commerce\Admin\Assets::class,
			'MDS\\WooCommerce\\Admin\\MetaBoxes'          => \MDS\Commerce\Admin\MetaBoxes::class,
			'MDS\\WooCommerce\\Admin\\SiteSettings'       => \MDS\Commerce\Admin\SiteSettings::class,
			'MDS\\WooCommerce\\Integration\\ReleaseMeta'  => \MDS\Commerce\Integration\ReleaseMeta::class,
			'MDS\\WooCommerce\\Rest\\RestApi'             => \MDS\Commerce\Rest\RestApi::class,
			'MDS\\WooCommerce\\Support\\ProductDrift'     => \MDS\Commerce\Support\ProductDrift::class,
			'MDS\\WooCommerce\\Support\\StoreRegistry'    => \MDS\Commerce\Support\StoreRegistry::class,
			'MDS\\WooCommerce\\Support\\TemplateRenderer' => \MDS\Commerce\Support\TemplateRenderer::class,
		);

		if ( isset( $legacy_map[ $class_name ] ) && class_exists( $legacy_map[ $class_name ] ) ) {
			class_alias( $legacy_map[ $class_name ], $class_name );
		}
	}
);
