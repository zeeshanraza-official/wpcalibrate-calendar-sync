<?php
/**
 * Encrypted Secret Storage Service.
 *
 * @package WPCalibrate\CalendarSync
 */

declare(strict_types=1);

namespace WPCalibrate\CalendarSync\Auth;

use Exception;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecretStorage
 *
 * Manages authenticated encryption at rest for sensitive OAuth credentials
 * using Sodium (preferred) or OpenSSL AES-256-GCM (fallback).
 */
class SecretStorage {

	/**
	 * Option name for storing encrypted secrets.
	 */
	public const OPTION_NAME = 'wpcs_secrets';

	/**
	 * Context string for key derivation.
	 */
	private const KDF_CONTEXT = 'wpcs_calendar_sync_encryption_v1';

	/**
	 * OpenSSL cipher algorithm.
	 */
	private const OPENSSL_CIPHER = 'aes-256-gcm';

	/**
	 * OpenSSL tag length in bytes (128 bits).
	 */
	private const OPENSSL_TAG_LENGTH = 16;

	/**
	 * OpenSSL IV length in bytes (96 bits recommended for GCM).
	 */
	private const OPENSSL_IV_LENGTH = 12;

	/**
	 * Check if a supported authenticated encryption engine is available on this server.
	 *
	 * @return bool
	 */
	public function is_encryption_available(): bool {
		return 'none' !== $this->get_encryption_engine();
	}

	/**
	 * Detect available encryption engine.
	 *
	 * @return string 'sodium', 'openssl', or 'none'.
	 */
	public function get_encryption_engine(): string {
		if ( extension_loaded( 'sodium' ) && function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return 'sodium';
		}

		if ( extension_loaded( 'openssl' ) && function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) && in_array( self::OPENSSL_CIPHER, openssl_get_cipher_methods(), true ) ) {
			return 'openssl';
		}

		return 'none';
	}

	/**
	 * Derive a 256-bit encryption key using WordPress salts and HKDF.
	 *
	 * @return string Binary 32-byte key.
	 */
	private function derive_key(): string {
		$salt1 = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
		$salt2 = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '';
		$salt3 = defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : '';

		$material = $salt1 . $salt2 . $salt3 . ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' );
		if ( empty( $material ) ) {
			// Fallback to site URL and DB name if salts are unexpectedly unconfigured.
			$material = get_site_url() . ( defined( 'DB_NAME' ) ? DB_NAME : 'wpcs_fallback_seed' );
		}

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $material, 32, self::KDF_CONTEXT, 'wpcs_salt_v1' );
		}

		return substr( hash_hmac( 'sha256', self::KDF_CONTEXT, $material, true ), 0, 32 );
	}

	/**
	 * Encrypt a plaintext string using the best available engine.
	 *
	 * @param string $plaintext Data to encrypt.
	 * @return string Base64 encoded payload: engine:data.
	 * @throws RuntimeException When encryption is unavailable or fails.
	 */
	public function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$engine = $this->get_encryption_engine();
		$key    = $this->derive_key();

		if ( 'sodium' === $engine ) {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			$payload    = 'sod:' . base64_encode( $nonce . $ciphertext );
			return $payload;
		}

		if ( 'openssl' === $engine ) {
			$iv         = random_bytes( self::OPENSSL_IV_LENGTH );
			$tag        = '';
			$ciphertext = openssl_encrypt(
				$plaintext,
				self::OPENSSL_CIPHER,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag,
				self::KDF_CONTEXT,
				self::OPENSSL_TAG_LENGTH
			);

			if ( false === $ciphertext ) {
				throw new RuntimeException( 'OpenSSL encryption failed.' );
			}

			// Format: openssl:iv(12):tag(16):ciphertext
			$payload = 'ossl:' . base64_encode( $iv . $tag . $ciphertext );
			return $payload;
		}

		throw new RuntimeException( 'No authenticated encryption engine (Sodium or OpenSSL AES-256-GCM) is available.' );
	}

	/**
	 * Decrypt an encrypted payload string.
	 *
	 * @param string $encrypted_payload Formatted ciphertext.
	 * @return string Decrypted plaintext.
	 * @throws RuntimeException If decryption fails or payload is invalid.
	 */
	public function decrypt( string $encrypted_payload ): string {
		if ( '' === $encrypted_payload ) {
			return '';
		}

		$parts = explode( ':', $encrypted_payload, 2 );
		if ( count( $parts ) !== 2 ) {
			throw new RuntimeException( 'Invalid secret format.' );
		}

		list( $engine, $data_b64 ) = $parts;
		$raw = base64_decode( $data_b64, true );
		if ( false === $raw ) {
			throw new RuntimeException( 'Base64 decode failed for encrypted secret.' );
		}

		$key = $this->derive_key();

		if ( 'sod' === $engine ) {
			if ( ! extension_loaded( 'sodium' ) ) {
				throw new RuntimeException( 'Sodium extension required to decrypt this secret.' );
			}
			$nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( strlen( $raw ) < $nonce_len ) {
				throw new RuntimeException( 'Sodium ciphertext payload is too short.' );
			}
			$nonce      = substr( $raw, 0, $nonce_len );
			$ciphertext = substr( $raw, $nonce_len );
			$decrypted  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
			if ( false === $decrypted ) {
				throw new RuntimeException( 'Sodium decryption failed. Authentication tag mismatch.' );
			}
			return $decrypted;
		}

		if ( 'ossl' === $engine ) {
			if ( ! extension_loaded( 'openssl' ) ) {
				throw new RuntimeException( 'OpenSSL extension required to decrypt this secret.' );
			}
			$min_len = self::OPENSSL_IV_LENGTH + self::OPENSSL_TAG_LENGTH;
			if ( strlen( $raw ) < $min_len ) {
				throw new RuntimeException( 'OpenSSL ciphertext payload is too short.' );
			}

			$iv         = substr( $raw, 0, self::OPENSSL_IV_LENGTH );
			$tag        = substr( $raw, self::OPENSSL_IV_LENGTH, self::OPENSSL_TAG_LENGTH );
			$ciphertext = substr( $raw, $min_len );

			$decrypted = openssl_decrypt(
				$ciphertext,
				self::OPENSSL_CIPHER,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag,
				self::KDF_CONTEXT
			);

			if ( false === $decrypted ) {
				throw new RuntimeException( 'OpenSSL decryption failed. Authentication tag mismatch.' );
			}

			return $decrypted;
		}

		throw new RuntimeException( 'Unrecognized encryption engine in payload: ' . sanitize_text_field( $engine ) );
	}

	/**
	 * Store a secret by key.
	 *
	 * @param string $key   Identifier (e.g., 'client_secret', 'refresh_token').
	 * @param string $value Plaintext secret.
	 * @return bool True on success.
	 * @throws RuntimeException If encryption is unavailable.
	 */
	public function store_secret( string $key, string $value ): bool {
		if ( ! $this->is_encryption_available() ) {
			throw new RuntimeException( 'Cannot store secret: No supported encryption engine available on server.' );
		}

		$encrypted = $this->encrypt( $value );
		$secrets   = $this->get_all_raw();
		$secrets[ $key ] = $encrypted;

		return update_option( self::OPTION_NAME, $secrets, false );
	}

	/**
	 * Retrieve and decrypt a secret by key.
	 *
	 * @param string $key Secret identifier.
	 * @return string|null Decrypted secret or null if not found.
	 */
	public function get_secret( string $key ): ?string {
		$secrets = $this->get_all_raw();
		if ( ! isset( $secrets[ $key ] ) || '' === $secrets[ $key ] ) {
			return null;
		}

		try {
			return $this->decrypt( (string) $secrets[ $key ] );
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Check if a secret exists without decrypting it.
	 *
	 * @param string $key Secret key.
	 * @return bool
	 */
	public function has_secret( string $key ): bool {
		$secrets = $this->get_all_raw();
		return ! empty( $secrets[ $key ] );
	}

	/**
	 * Delete a stored secret.
	 *
	 * @param string $key Secret key.
	 * @return bool
	 */
	public function delete_secret( string $key ): bool {
		$secrets = $this->get_all_raw();
		if ( isset( $secrets[ $key ] ) ) {
			unset( $secrets[ $key ] );
			return update_option( self::OPTION_NAME, $secrets, false );
		}
		return true;
	}

	/**
	 * Clear all stored secrets.
	 *
	 * @return void
	 */
	public function clear_all_secrets(): void {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Fetch raw encrypted secrets array.
	 *
	 * @return array<string, string>
	 */
	private function get_all_raw(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		return is_array( $stored ) ? $stored : array();
	}
}
