<?php
/**
 * Šifrování API klíče AI providera (AES-256-CBC, klíč odvozený z wp_salt).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_AiQueue_Crypt {

	private const CIPHER = 'aes-256-cbc';

	/**
	 * Zašifruje text do base64 (IV + ciphertext). Prázdný vstup vrátí prázdný řetězec.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		$iv         = random_bytes( openssl_cipher_iv_length( self::CIPHER ) );
		$ciphertext = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Dešifruje text zašifrovaný metodou encrypt(). Prázdný/neplatný vstup vrátí prázdný řetězec.
	 */
	public static function decrypt( string $encoded ): string {
		if ( '' === $encoded ) {
			return '';
		}

		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );

		$plain = openssl_decrypt( $ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	private static function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
