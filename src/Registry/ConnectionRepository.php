<?php
/**
 * Persistence layer for remote Store Connections and Store Chooser global defaults.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Registry;

use MDS\Commerce\Config;
use MDS\Commerce\Model\StoreKey;
use MDS\Commerce\Security\CredentialVault;

defined( 'ABSPATH' ) || exit;

/**
 * Manages encrypted remote store connections and site-wide Store Chooser defaults.
 *
 * @since 3.0.0
 */
final class ConnectionRepository {

	/**
	 * Option key for Store Chooser global defaults.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const OPTION_CHOOSER_DEFAULTS = 'mds_cm_chooser_defaults';

	/**
	 * Supported remote provider slugs for the Connection Wizard.
	 *
	 * @since 3.0.0
	 * @var array<string, array{label: string, auth_type: string, docs_hint: string}>
	 */
	public static function get_supported_remote_providers(): array {
		return array(
			'shopify'     => array(
				'label'     => __( 'Shopify (Admin GraphQL)', 'mds-commerce' ),
				'auth_type' => 'access_token',
				'docs_hint' => __( 'Use a Shopify Custom App Admin API access token (shpat_...) or define a wp-config.php constant.', 'mds-commerce' ),
			),
			'wc_remote'   => array(
				'label'     => __( 'WooCommerce (Remote REST v3)', 'mds-commerce' ),
				'auth_type' => 'key_secret',
				'docs_hint' => __( 'Generate a Consumer Key & Secret under WooCommerce → Settings → Advanced → REST API.', 'mds-commerce' ),
			),
			'wix'         => array(
				'label'     => __( 'Wix Stores (Catalog V3)', 'mds-commerce' ),
				'auth_type' => 'api_key_site',
				'docs_hint' => __( 'Supply your Wix API Key and Wix Site ID from the Wix Headless / Developer dashboard.', 'mds-commerce' ),
			),
			'bigcartel'   => array(
				'label'     => __( 'Big Cartel (JSON:API v1)', 'mds-commerce' ),
				'auth_type' => 'oauth_token',
				'docs_hint' => __( 'Connect via Big Cartel OAuth access token or store subdomain.', 'mds-commerce' ),
			),
			'squarespace' => array(
				'label'     => __( 'Squarespace Commerce', 'mds-commerce' ),
				'auth_type' => 'access_token',
				'docs_hint' => __( 'Generate a Commerce API Key under Settings → Developer Tools → Developer API Keys.', 'mds-commerce' ),
			),
			'external'    => array(
				'label'     => __( 'External / POD Storefront (Bandcamp, elasticStage, Discogs, etc.)', 'mds-commerce' ),
				'auth_type' => 'none',
				'docs_hint' => __( 'Link-only or manual-prep storefront with optional promo badge & coupon priority.', 'mds-commerce' ),
			),
		);
	}

	/**
	 * Retrieve all raw saved remote connections keyed by canonical StoreKey string.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, array<string, mixed>> Saved connections.
	 */
	public static function get_all_raw(): array {
		$stored = is_multisite()
			? get_site_option( Config::OPTION_STORE_CONNECTIONS, array() )
			: get_option( Config::OPTION_STORE_CONNECTIONS, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Retrieve all saved remote connections formatted safely for admin UI / REST (never exposes raw secrets).
	 *
	 * @since 3.0.0
	 *
	 * @return array<int, array<string, mixed>> Safe connection summaries.
	 */
	public static function get_all_safe(): array {
		$raw    = self::get_all_raw();
		$output = array();

		foreach ( $raw as $store_key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$output[] = self::to_safe_array( (string) $store_key, $entry );
		}

		return $output;
	}

	/**
	 * Save (create or update) a remote store connection with encrypted credentials.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $input Raw connection input.
	 * @return array<string, mixed> Saved safe connection summary.
	 */
	public static function save( array $input ): array {
		$all      = self::get_all_raw();
		$provider = sanitize_key( (string) ( $input['provider'] ?? 'external' ) );
		$allowed  = array_keys( self::get_supported_remote_providers() );
		if ( ! in_array( $provider, $allowed, true ) ) {
			$provider = 'external';
		}

		$raw_id = sanitize_key( (string) ( $input['store_id'] ?? '' ) );
		if ( '' === $raw_id ) {
			$raw_id = 'store_' . substr( md5( uniqid( $provider, true ) ), 0, 8 );
		}

		$store_key_obj = new StoreKey( $provider, $raw_id );
		$store_key     = $store_key_obj->to_string();
		$existing      = isset( $all[ $store_key ] ) && is_array( $all[ $store_key ] ) ? $all[ $store_key ] : array();

		$sync_mode = sanitize_key( (string) ( $input['sync_mode'] ?? $existing['sync_mode'] ?? 'manual' ) );
		if ( ! in_array( $sync_mode, Config::SYNC_MODES, true ) ) {
			$sync_mode = 'manual';
		}

		$secret_const = preg_replace( '/[^A-Z0-9_]/', '', strtoupper( (string) ( $input['secret_constant'] ?? $existing['secret_constant'] ?? '' ) ) ) ?? '';
		$new_secret   = isset( $input['api_secret'] ) && is_scalar( $input['api_secret'] )
			? trim( (string) $input['api_secret'] )
			: '';

		$encrypted_secret = '' !== $new_secret
			? CredentialVault::encrypt( $new_secret )
			: (string) ( $existing['encrypted_secret'] ?? '' );

		$record = array(
			'store_key'        => $store_key,
			'provider'         => $provider,
			'store_id'         => $raw_id,
			'name'             => sanitize_text_field( (string) ( $input['name'] ?? $existing['name'] ?? ucfirst( $provider ) ) ),
			'url'              => esc_url_raw( trim( (string) ( $input['url'] ?? $existing['url'] ?? '' ) ) ),
			'api_key'          => sanitize_text_field( trim( (string) ( $input['api_key'] ?? $existing['api_key'] ?? '' ) ) ),
			'secret_constant'  => $secret_const,
			'encrypted_secret' => $encrypted_secret,
			'sync_mode'        => $sync_mode,
			'poll_interval'    => sanitize_key( (string) ( $input['poll_interval'] ?? $existing['poll_interval'] ?? 'hourly' ) ),
			'promo'            => array(
				'promo_badge'       => sanitize_text_field( (string) ( $input['promo_badge'] ?? $existing['promo']['promo_badge'] ?? '' ) ),
				'coupon_code'       => sanitize_text_field( (string) ( $input['coupon_code'] ?? $existing['promo']['coupon_code'] ?? '' ) ),
				'promo_expires_gmt' => sanitize_text_field( (string) ( $input['promo_expires_gmt'] ?? $existing['promo']['promo_expires_gmt'] ?? '' ) ),
				'priority_weight'   => (int) ( $input['priority_weight'] ?? $existing['promo']['priority_weight'] ?? 0 ),
			),
			'updated_gmt'      => gmdate( 'Y-m-d H:i:s' ),
		);

		$all[ $store_key ] = $record;
		self::persist_all( $all );

		return self::to_safe_array( $store_key, $record );
	}

	/**
	 * Delete a saved remote store connection.
	 *
	 * @since 3.0.0
	 *
	 * @param string $store_key Canonical StoreKey string.
	 * @return bool True when deleted, false when not found.
	 */
	public static function delete( string $store_key ): bool {
		$all = self::get_all_raw();
		if ( ! isset( $all[ $store_key ] ) ) {
			return false;
		}

		unset( $all[ $store_key ] );
		self::persist_all( $all );
		return true;
	}

	/**
	 * Retrieve Store Chooser global defaults.
	 *
	 * @since 3.0.0
	 *
	 * @return array{group_by: string, layout: string, prioritize_promos: bool, heading: string}
	 */
	public static function get_chooser_defaults(): array {
		$stored = get_option( self::OPTION_CHOOSER_DEFAULTS, array() );
		$data   = is_array( $stored ) ? $stored : array();

		$group_by = in_array( (string) ( $data['group_by'] ?? 'format' ), array( 'format', 'store', 'none' ), true )
			? (string) $data['group_by']
			: 'format';
		$layout   = in_array( (string) ( $data['layout'] ?? 'cards' ), array( 'cards', 'list' ), true )
			? (string) $data['layout']
			: 'cards';

		return array(
			'group_by'          => $group_by,
			'layout'            => $layout,
			'prioritize_promos' => array_key_exists( 'prioritize_promos', $data ) ? (bool) $data['prioritize_promos'] : true,
			'heading'           => sanitize_text_field( (string) ( $data['heading'] ?? '' ) ),
		);
	}

	/**
	 * Persist Store Chooser global defaults.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array{group_by: string, layout: string, prioritize_promos: bool, heading: string} Saved defaults.
	 */
	public static function save_chooser_defaults( array $input ): array {
		$group_by = in_array( (string) ( $input['group_by'] ?? 'format' ), array( 'format', 'store', 'none' ), true )
			? (string) $input['group_by']
			: 'format';
		$layout   = in_array( (string) ( $input['layout'] ?? 'cards' ), array( 'cards', 'list' ), true )
			? (string) $input['layout']
			: 'cards';

		$sanitized = array(
			'group_by'          => $group_by,
			'layout'            => $layout,
			'prioritize_promos' => ! empty( $input['prioritize_promos'] ),
			'heading'           => sanitize_text_field( (string) ( $input['heading'] ?? '' ) ),
		);

		update_option( self::OPTION_CHOOSER_DEFAULTS, $sanitized, false );
		return $sanitized;
	}

	/**
	 * Convert a raw connection record into a secret-safe array for REST and UI rendering.
	 *
	 * @since 3.0.0
	 *
	 * @param string               $store_key Canonical StoreKey string.
	 * @param array<string, mixed> $record    Raw stored record.
	 * @return array<string, mixed> Secret-safe representation.
	 */
	public static function to_safe_array( string $store_key, array $record ): array {
		$const_name = (string) ( $record['secret_constant'] ?? '' );
		$from_const = '' !== $const_name && defined( $const_name );
		$has_vault  = ! empty( $record['encrypted_secret'] );

		return array(
			'store_key'       => $store_key,
			'provider'        => (string) ( $record['provider'] ?? 'external' ),
			'store_id'        => (string) ( $record['store_id'] ?? '' ),
			'name'            => (string) ( $record['name'] ?? '' ),
			'url'             => (string) ( $record['url'] ?? '' ),
			'api_key'         => (string) ( $record['api_key'] ?? '' ),
			'secret_constant' => $const_name,
			'has_secret'      => $from_const || $has_vault,
			'secret_source'   => $from_const ? 'wp_config' : ( $has_vault ? 'vault' : 'none' ),
			'sync_mode'       => (string) ( $record['sync_mode'] ?? 'manual' ),
			'poll_interval'   => (string) ( $record['poll_interval'] ?? 'hourly' ),
			'promo'           => is_array( $record['promo'] ?? null ) ? $record['promo'] : array(),
			'updated_gmt'     => (string) ( $record['updated_gmt'] ?? '' ),
		);
	}

	/**
	 * Persist the connections map to site option or network site option.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, array<string, mixed>> $all Connections map.
	 * @return void
	 */
	private static function persist_all( array $all ): void {
		if ( is_multisite() ) {
			update_site_option( Config::OPTION_STORE_CONNECTIONS, $all );
		} else {
			update_option( Config::OPTION_STORE_CONNECTIONS, $all, false );
		}
	}
}
