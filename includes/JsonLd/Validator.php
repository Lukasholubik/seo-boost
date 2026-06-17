<?php
/**
 * JSON-LD Validator - extrakce, validace a detekce duplicit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_JsonLd_Validator {

	/**
	 * Povinna pole pro bezne schema.org typy.
	 * Prazdne pole = alespon @type musi existovat.
	 */
	private static array $required_props = [
		'Article'              => [ 'headline' ],
		'BlogPosting'          => [ 'headline' ],
		'NewsArticle'          => [ 'headline', 'datePublished' ],
		'TechArticle'          => [ 'headline' ],
		'Product'              => [ 'name' ],
		'Organization'         => [ 'name' ],
		'LocalBusiness'        => [ 'name' ],
		'Restaurant'           => [ 'name' ],
		'Hotel'                => [ 'name' ],
		'WebSite'              => [],
		'WebPage'              => [],
		'CollectionPage'       => [],
		'ContactPage'          => [],
		'AboutPage'            => [],
		'BreadcrumbList'       => [ 'itemListElement' ],
		'FAQPage'              => [ 'mainEntity' ],
		'HowTo'                => [ 'name', 'step' ],
		'Event'                => [ 'name', 'startDate' ],
		'Recipe'               => [ 'name', 'recipeIngredient', 'recipeInstructions' ],
		'Review'               => [ 'itemReviewed', 'reviewRating' ],
		'AggregateRating'      => [ 'ratingValue', 'ratingCount' ],
		'Person'               => [ 'name' ],
		'VideoObject'          => [ 'name', 'description', 'thumbnailUrl', 'uploadDate' ],
		'ImageObject'          => [],
		'SiteLinksSearchBox'   => [ 'target' ],
		'ItemList'             => [ 'itemListElement' ],
		'Service'              => [ 'name' ],
		'ProfessionalService'  => [ 'name' ],
		'LegalService'         => [ 'name' ],
	];

	/**
	 * Extrahuje vsechny JSON-LD bloky z HTML stranky.
	 * Zpracovava jak jednotlive objekty, tak @graph pole.
	 *
	 * @return list<array>
	 */
	public static function extract_schemas( string $html ): array {
		$schemas = [];

		if ( ! preg_match_all(
			'/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
			$html,
			$matches
		) ) {
			return $schemas;
		}

		foreach ( $matches[1] as $raw ) {
			$raw     = trim( $raw );
			$decoded = json_decode( $raw, true );

			if ( null === $decoded ) {
				$schemas[] = [
					'_parse_error' => json_last_error_msg(),
					'_raw_excerpt' => substr( $raw, 0, 300 ),
				];
				continue;
			}

			if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
				// Polozky v @graph dedí @context z parent kontejneru – nepridavat false-positive varování
				$parent_ctx = $decoded['@context'] ?? null;
				foreach ( $decoded['@graph'] as $item ) {
					if ( is_array( $item ) ) {
						if ( $parent_ctx !== null && empty( $item['@context'] ) ) {
							$item['@context'] = $parent_ctx;
						}
						$schemas[] = $item;
					}
				}
			} else {
				$schemas[] = $decoded;
			}
		}

		return $schemas;
	}

	/**
	 * Validuje jedno schema.
	 *
	 * @param  array $schema Dekodovane schema
	 * @return array{valid: bool, type: string, errors: list<string>, warnings: list<string>}
	 */
	public static function validate_schema( array $schema ): array {
		$errors   = [];
		$warnings = [];

		if ( isset( $schema['_parse_error'] ) ) {
			return [
				'valid'    => false,
				'type'     => 'unknown',
				'errors'   => [ 'Chyba parsovani JSON: ' . $schema['_parse_error'] ],
				'warnings' => [],
			];
		}

		if ( empty( $schema['@type'] ) ) {
			$errors[] = 'Chybi povinny klic @type';
			return [ 'valid' => false, 'type' => 'unknown', 'errors' => $errors, 'warnings' => $warnings ];
		}

		$raw_type   = is_array( $schema['@type'] ) ? $schema['@type'][0] : $schema['@type'];
		$short_type = self::short_type( $raw_type );

		// Chybi @context
		if ( empty( $schema['@context'] ) ) {
			$warnings[] = 'Chybi klic @context (doporuceno: "https://schema.org")';
		}

		// Povinne vlastnosti pro dany typ
		$required = self::$required_props[ $short_type ] ?? null;

		if ( null !== $required ) {
			foreach ( $required as $prop ) {
				$val = $schema[ $prop ] ?? null;
				if ( null === $val || '' === $val || [] === $val ) {
					$errors[] = sprintf( 'Chybi povinna vlastnost "%s" pro typ %s', $prop, $short_type );
				}
			}
		}

		// BreadcrumbList – alespon 1 polozka v itemListElement
		if ( 'BreadcrumbList' === $short_type ) {
			$items = $schema['itemListElement'] ?? [];
			if ( ! is_array( $items ) || count( $items ) === 0 ) {
				$errors[] = 'itemListElement musi byt neprazdne pole';
			}
		}

		// FAQPage – mainEntity musi byt pole
		if ( 'FAQPage' === $short_type ) {
			$entities = $schema['mainEntity'] ?? [];
			if ( ! is_array( $entities ) || count( $entities ) === 0 ) {
				$errors[] = 'mainEntity musi byt neprazdne pole otazek';
			}
		}

		return [
			'valid'    => empty( $errors ),
			'type'     => $short_type,
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Detekuje duplicitni schemata (stejny @type ve vice blocich).
	 *
	 * @param  list<array> $schemas
	 * @return list<array{type: string, count: int, exact: bool, indices: list<int>}>
	 */
	public static function detect_duplicates( array $schemas ): array {
		$by_type = [];

		foreach ( $schemas as $i => $schema ) {
			if ( isset( $schema['_parse_error'] ) ) {
				continue;
			}
			$raw_type   = is_array( $schema['@type'] ?? '' ) ? implode( ',', $schema['@type'] ) : ( $schema['@type'] ?? 'unknown' );
			$short_type = self::short_type( $raw_type );
			$by_type[ $short_type ][] = $i;
		}

		$duplicates = [];

		foreach ( $by_type as $type => $indices ) {
			if ( count( $indices ) <= 1 ) {
				continue;
			}

			$fps = array_map(
				static fn ( int $i ) => md5( (string) json_encode( $schemas[ $i ] ) ),
				$indices
			);
			$unique_fps = array_unique( $fps );

			$duplicates[] = [
				'type'    => $type,
				'count'   => count( $indices ),
				'exact'   => count( $unique_fps ) < count( $indices ),
				'indices' => $indices,
			];
		}

		return $duplicates;
	}

	/**
	 * Self-test: validuje known-good JSON-LD a vraci true, pokud validator funguje.
	 */
	public static function self_test(): bool {
		$json    = '{"@context":"https://schema.org","@type":"Organization","name":"Test"}';
		$schemas = self::extract_schemas( '<script type="application/ld+json">' . $json . '</script>' );

		if ( empty( $schemas ) ) {
			return false;
		}

		$result = self::validate_schema( $schemas[0] );

		return true === $result['valid'];
	}

	/**
	 * Vraci kratky nazev typu (odstrni URL prefix).
	 * Napr. "https://schema.org/Article" => "Article".
	 */
	public static function short_type( string $type ): string {
		if ( strpos( $type, '/' ) !== false ) {
			return substr( $type, strrpos( $type, '/' ) + 1 );
		}
		return $type;
	}
}
