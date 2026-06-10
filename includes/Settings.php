<?php
/**
 * Jediný přístupový bod pro čtení/zápis nastavení pluginu (option prefix `seob_`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Settings {

	const GENERAL  = 'seob_general_settings';
	const AUDIT    = 'seob_audit_settings';
	const REDIRECT = 'seob_redirect_settings';

	/**
	 * Výchozí hodnoty pro jednotlivé option klíče.
	 */
	private static function defaults( string $option ): array {
		switch ( $option ) {
			case self::GENERAL:
				return [
					'debug'               => 0,
					'delete_on_uninstall' => 0,
					'modules'             => [
						'audit'     => 1,
						'redirects' => 1,
					],
				];

			case self::AUDIT:
				return [
					'cron_enabled'   => 1,
					'batch_size'     => 20,
					'thin_content_words' => 300,
				];

			case self::REDIRECT:
				return [
					'log_404'       => 1,
					'log_retention_days' => 30,
				];

			default:
				return [];
		}
	}

	/**
	 * Vrátí nastavení sloučené s výchozími hodnotami.
	 */
	public static function get( string $option ): array {
		$stored = get_option( $option, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_replace_recursive( self::defaults( $option ), $stored );
	}

	/**
	 * Uloží nastavení (přepíše celé pole pro daný option klíč).
	 */
	public static function update( string $option, array $value ): bool {
		return update_option( $option, $value );
	}
}
