<?php
/**
 * Standalone checks for StoreRegistry, per-Artist sync targeting,
 * one-time legacy backfill, and two-layer unlink authorization.
 *
 * Run with PHP: php tests/store-registry.php
 *
 * @package MDS\WooCommerce
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MDS_WC_PATH', dirname( __DIR__ ) . '/' );
define( 'MDS_WC_URL', 'http://example.test/wp-content/plugins/mds-woocommerce/' );
define( 'MDS_WC_VERSION', '1.1.0' );

$GLOBALS['mds_test_multisite']        = true;
$GLOBALS['mds_test_current_blog']     = 1;
$GLOBALS['mds_test_blog_stack']       = array();
$GLOBALS['mds_test_site_transients']  = array();
$GLOBALS['mds_test_site_options']     = array(
	'active_sitewide_plugins'    => array( 'mds-release-manager/mds-release-manager.php' => 1 ),
	'mds_rm_wc_integrated_sites' => array( 2 ),
	'mds_rm_woo_sites'           => array(),
);
$GLOBALS['mds_test_blog_options']     = array(
	1 => array( 'active_plugins' => array() ),
	2 => array( 'active_plugins' => array( 'woocommerce/woocommerce.php' ) ),
	3 => array( 'active_plugins' => array( 'woocommerce/woocommerce.php' ) ),
	4 => array( 'active_plugins' => array() ),
);
$GLOBALS['mds_test_sites']            = array(
	(object) array( 'blog_id' => 1, 'blogname' => 'Hub', 'siteurl' => 'http://bgw-dev.local' ),
	(object) array( 'blog_id' => 2, 'blogname' => 'Store A', 'siteurl' => 'http://store.bgw-dev.local' ),
	(object) array( 'blog_id' => 3, 'blogname' => 'Store B', 'siteurl' => 'http://store2.bgw-dev.local' ),
	(object) array( 'blog_id' => 4, 'blogname' => 'Blog Only', 'siteurl' => 'http://blog.bgw-dev.local' ),
);
$GLOBALS['mds_test_posts']            = array(
	10 => (object) array( 'ID' => 10, 'post_type' => 'artist', 'post_status' => 'publish', 'post_title' => 'Artist One' ),
	20 => (object) array( 'ID' => 20, 'post_type' => 'release', 'post_status' => 'publish', 'post_title' => 'Release One' ),
	30 => (object) array( 'ID' => 30, 'post_type' => 'artist', 'post_status' => 'publish', 'post_title' => 'Artist Legacy' ),
);
$GLOBALS['mds_test_post_meta']        = array(
	10 => array(
		'_mds_artist_uuid'      => 'uuid-artist-10',
		'_mds_wc_target_stores' => array( 2 ),
	),
	20 => array(
		'artist_id'            => 'uuid-artist-10',
		'_mds_linked_products' => array( 3 => 999 ),
	),
	30 => array(
		'_mds_artist_uuid' => 'uuid-artist-30',
	),
);
$GLOBALS['mds_test_get_posts_calls']  = 0;

class MDS_RM_Sync {
	public static function is_hub(): bool {
		return 1 === $GLOBALS['mds_test_current_blog'];
	}
}

function is_multisite(): bool {
	return (bool) $GLOBALS['mds_test_multisite'];
}
function get_current_blog_id(): int {
	return (int) $GLOBALS['mds_test_current_blog'];
}
function switch_to_blog( int $blog_id ): bool {
	$GLOBALS['mds_test_blog_stack'][] = $GLOBALS['mds_test_current_blog'];
	$GLOBALS['mds_test_current_blog'] = $blog_id;
	return true;
}
function restore_current_blog(): bool {
	$GLOBALS['mds_test_current_blog'] = (int) array_pop( $GLOBALS['mds_test_blog_stack'] );
	return true;
}
function get_sites( array $args = array() ): array {
	unset( $args );
	return $GLOBALS['mds_test_sites'];
}
function get_site_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['mds_test_site_options'][ $key ] ?? $default;
}
function get_option( string $key, mixed $default = false ): mixed {
	$blog = $GLOBALS['mds_test_current_blog'];
	return $GLOBALS['mds_test_blog_options'][ $blog ][ $key ] ?? $default;
}
function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
	unset( $autoload );
	$blog                                             = $GLOBALS['mds_test_current_blog'];
	$GLOBALS['mds_test_blog_options'][ $blog ][ $key ] = $value;
	return true;
}
function get_site_transient( string $key ): mixed {
	return $GLOBALS['mds_test_site_transients'][ $key ] ?? false;
}
function set_site_transient( string $key, mixed $value, int $expiration = 0 ): bool {
	unset( $expiration );
	$GLOBALS['mds_test_site_transients'][ $key ] = $value;
	return true;
}
function delete_site_transient( string $key ): bool {
	unset( $GLOBALS['mds_test_site_transients'][ $key ] );
	return true;
}
function get_post( int $post_id ): ?object {
	return $GLOBALS['mds_test_posts'][ $post_id ] ?? null;
}
function get_post_type( int $post_id ): string|false {
	return isset( $GLOBALS['mds_test_posts'][ $post_id ] ) ? $GLOBALS['mds_test_posts'][ $post_id ]->post_type : false;
}
function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
	unset( $single );
	return $GLOBALS['mds_test_post_meta'][ $post_id ][ $key ] ?? '';
}
function update_post_meta( int $post_id, string $key, mixed $value ): bool {
	$GLOBALS['mds_test_post_meta'][ $post_id ][ $key ] = $value;
	return true;
}
function get_posts( array $args = array() ): array {
	++$GLOBALS['mds_test_get_posts_calls'];
	$results = array();
	foreach ( $GLOBALS['mds_test_posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
			if ( ( $GLOBALS['mds_test_post_meta'][ $id ][ $args['meta_key'] ] ?? '' ) !== $args['meta_value'] ) {
				continue;
			}
		}
		if ( isset( $args['meta_query'][0]['compare'] ) && 'NOT EXISTS' === $args['meta_query'][0]['compare'] ) {
			$k = $args['meta_query'][0]['key'];
			if ( array_key_exists( $k, $GLOBALS['mds_test_post_meta'][ $id ] ?? array() ) ) {
				continue;
			}
		}
		$results[] = $id;
	}
	return $results;
}
function absint( mixed $value ): int {
	return abs( (int) $value );
}
function current_user_can( string $cap, mixed ...$args ): bool {
	unset( $cap, $args );
	return true;
}
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
	unset( $hook, $args );
	return $value;
}
function __( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return $text;
}

require dirname( __DIR__ ) . '/vendor/autoload.php';

use MDS\WooCommerce\Integration\ReleaseMeta;
use MDS\WooCommerce\Plugin;
use MDS\WooCommerce\Support\StoreRegistry;

function assert_same( mixed $expected, mixed $actual, string $label ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL [{$label}]: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

// 1. Auto-discovery finds only sites with WooCommerce active (2 and 3, not 1 or 4).
$stores = StoreRegistry::get_available_stores();
assert_same( array( 2, 3 ), array_keys( $stores ), 'auto-detected WC stores' );
assert_same( true, StoreRegistry::is_woocommerce_store( 2 ), 'store 2 is WC' );
assert_same( false, StoreRegistry::is_woocommerce_store( 4 ), 'site 4 is not WC' );

// 2. Per-Artist and per-Release sync targeting with UUID memoization.
assert_same( array( 2 ), ReleaseMeta::sync_targets( array(), 10, 'artist' ), 'artist 10 targets store 2' );
$calls_before = $GLOBALS['mds_test_get_posts_calls'];
assert_same( array( 2 ), ReleaseMeta::sync_targets( array(), 20, 'release' ), 'release 20 inherits artist 10 store 2' );
assert_same( array( 2 ), ReleaseMeta::sync_targets( array(), 20, 'release' ), 'second lookup is memoized' );
assert_same( $calls_before + 1, $GLOBALS['mds_test_get_posts_calls'], 'UUID lookup runs only once per request' );

// 3. Two-layer security: store 2 allowed, unassigned store 3 denied for link, allowed for unlink, non-WC store 4 denied.
assert_same( true, Plugin::user_can_manage_spoke_product( 20, 2, false ), 'assigned store 2 allowed' );
assert_same( false, Plugin::user_can_manage_spoke_product( 20, 3, false ), 'unassigned store 3 denied for link' );
assert_same( true, Plugin::user_can_manage_spoke_product( 20, 3, true ), 'unassigned store 3 allowed for unlink of existing link' );
assert_same( false, Plugin::user_can_manage_spoke_product( 20, 4, true ), 'non-WC site 4 always denied' );

// 4. One-time idempotent backfill populates legacy Artist 30 without overwriting Artist 10.
StoreRegistry::maybe_backfill_artist_stores();
assert_same( array( 2 ), $GLOBALS['mds_test_post_meta'][30]['_mds_wc_target_stores'], 'artist 30 backfilled with store 2' );
assert_same( '1', $GLOBALS['mds_test_blog_options'][1][ StoreRegistry::MIGRATION_OPTION_KEY ], 'migration option marked complete' );

echo "StoreRegistry and per-Artist WooCommerce target checks passed.\n";
