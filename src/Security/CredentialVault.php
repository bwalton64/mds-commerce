<?php
/**
 * Encrypted credential vault with wp-config.php constant overrides.
 *
 * @package MDS\Commerce
 * @since   3.0.0
 */

declare(strict_types=1);

namespace MDS\Commerce\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts store API credentials at rest using PHP libsodium
 * (with AES-256-GCM OpenSSL fallback) while allowing zero-DB secret overrides via wp-config.php constants.
 *
 * @since 3.0.0
 */
final class CredentialVault {

	/**
	 * Prefix marking a libsodium encrypted payload.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const SODIUM_PREFIX = 'mds_sodium_v1:';

	/**
	 * Prefix marking an OpenSSL AES-256-GCM encrypted payload.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const OPENSSL_PREFIX = 'mds_openssl_v1:';

	/**
	 * Resolve a secret from an optional wp-config.php constant first, falling back to decrypting stored ciphertext.
	 *
	 * @since 3.0.0
	 *
	 * @param string $constant_name Optional PHP constant name (e.g., `MDS_CM_SHOPIFY_TOKEN_MAIN`).
	 * @param string $stored_value  Stored ciphertext or plaintext fallback.
	 * @return string Decrypted secret string, or empty string on failure.
	 */
	public static function resolve_secret( string $constant_name, string $stored_value = '' ): string {
		$clean_const = preg_replace( '/[^A-Z0-9_]/', '', strtoupper( $constant_name ) ) ?? '';
		if ( '' !== $clean_const && defined( $clean_const ) ) {
			$value = constant( $clean_const );
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		if ( '' === $stored_value ) {
			return '';
		}

		return self::decrypt( $stored_value );
	}

	/**
	 * Encrypt a plaintext secret using libsodium or OpenSSL AES-256-GCM.
	 *
	 * @since 3.0.0
	 *
	 * @param string $plaintext Plaintext secret.
	 * @return string Encrypted string, or empty string when plaintext is empty.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key = self::derive_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard binary-to-ASCII transport for ciphertext.
			return self::SODIUM_PREFIX . base64_encode( $nonce . $box );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv         = random_bytes( 12 );
			$tag        = '';
			$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $ciphertext ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard binary-to-ASCII transport for ciphertext.
				return self::OPENSSL_PREFIX . base64_encode( $iv . $tag . $ciphertext );
			}
		}

		return $plaintext;
	}

	/**
	 * Decrypt a previously encrypted secret.
	 *
	 * @since 3.0.0
	 *
	 * @param string $ciphertext Stored ciphertext.
	 * @return string Decrypted plaintext, or empty string if decryption fails.
	 */
	public static function decrypt( string $ciphertext ): string {
		if ( '' === $ciphertext ) {
			return '';
		}

		if ( str_starts_with( $ciphertext, self::SODIUM_PREFIX ) ) {
			if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
				return '';
			}

			$encoded = substr( $ciphertext, strlen( self::SODIUM_PREFIX ) );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Standard ASCII-to-binary decode for ciphertext.
			$raw = base64_decode( $encoded, true );
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}

			$nonce     = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$encrypted = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$opened    = sodium_crypto_secretbox_open( $encrypted, $nonce, self::derive_key() );

			return false === $opened ? '' : $opened;
		}

		if ( str_starts_with( $ciphertext, self::OPENSSL_PREFIX ) ) {
			if ( ! function_exists( 'openssl_decrypt' ) ) {
				return '';
			}

			$encoded = substr( $ciphertext, strlen( self::OPENSSL_PREFIX ) );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Standard ASCII-to-binary decode for ciphertext.
			$raw = base64_decode( $encoded, true );
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return '';
			}

			$iv        = substr( $raw, 0, 12 );
			$tag       = substr( $raw, 12, 16 );
			$encrypted = substr( $raw, 28 );
			$opened    = openssl_decrypt( $encrypted, 'aes-256-gcm', self::derive_key(), OPENSSL_RAW_DATA, $iv, $tag );

			return false === $opened ? '' : $opened;
		}

		return $ciphertext;
	}

	/**
	 * Derive a 32-byte symmetric encryption key from WordPress auth salt.
	 *
	 * @since 3.0.0
	 *
	 * @return string 32-byte binary key.
	 */
	private static function derive_key(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : 'mds-commerce-fallback-key' );
		return hash( 'sha256', 'mds_commerce_vault|' . $salt, true );
	}
}
