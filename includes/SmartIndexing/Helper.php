<?php
/**
 * Sdílené pomocné funkce pro modul Chytrá indexace (M14).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_SmartIndexing_Helper {

	/**
	 * Rozparsuje seznam parametrů oddělených čárkou na čisté pole.
	 */
	public static function parse_param_list( string $csv ): array {
		$parts = array_map( 'trim', explode( ',', $csv ) );

		return array_values( array_filter( $parts, static fn( $part ) => '' !== $part ) );
	}

	/**
	 * Ověří, zda název query parametru odpovídá některému ze vzorů blacklistu
	 * (podporuje hvězdičkové wildcardy, např. `utm_*`, `session*`).
	 */
	public static function param_matches_blacklist( string $param, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( false === strpos( $pattern, '*' ) ) {
				if ( strtolower( $param ) === strtolower( $pattern ) ) {
					return true;
				}
				continue;
			}

			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i';

			if ( preg_match( $regex, $param ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Odstraní z URL všechny query parametry odpovídající blacklistu.
	 * Vrátí null, pokud žádný parametr nebyl odstraněn (URL je již čistá).
	 */
	public static function strip_blacklisted_params( string $url, array $patterns ): ?string {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['query'] ) ) {
			return null;
		}

		parse_str( $parts['query'], $query_args );

		$filtered = [];
		$removed  = false;

		foreach ( $query_args as $key => $value ) {
			if ( self::param_matches_blacklist( (string) $key, $patterns ) ) {
				$removed = true;
				continue;
			}
			$filtered[ $key ] = $value;
		}

		if ( ! $removed ) {
			return null;
		}

		$clean = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '/' );

		if ( ! empty( $filtered ) ) {
			$clean .= '?' . http_build_query( $filtered );
		}

		return $clean;
	}

	/**
	 * Normalizuje URL pro uložení/hash (bez schématu+domény, bez koncového lomítka navíc).
	 */
	public static function normalize_url( string $url ): string {
		$parts = wp_parse_url( $url );
		$path  = $parts['path'] ?? '/';
		$path  = '/' . trim( $path, '/' ) . '/';

		$normalized = $path;

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
			ksort( $query_args );
			if ( ! empty( $query_args ) ) {
				$normalized .= '?' . http_build_query( $query_args );
			}
		}

		return $normalized;
	}

	public static function url_hash( string $normalized_url ): string {
		return md5( $normalized_url );
	}
}
