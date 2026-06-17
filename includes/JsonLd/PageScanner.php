<?php
/**
 * JSON-LD Page Scanner – HTTP fetch jedne URL, sestaveni seznamu URL pro scan.
 *
 * Orchestraci davek a archivaci ma na starosti SEOB_JsonLd_ScanRunner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_JsonLd_PageScanner {

	const MAX_URLS = 50;

	/**
	 * Nacte URL pres wp_remote_get a vrati vysledek validace JSON-LD.
	 */
	public static function scan_url( string $url ): array {
		$response = wp_remote_get( $url, [
			'timeout'    => 8,
			'user-agent' => 'SEOBoosterPro/1.0 (JSON-LD Validator)',
			'sslverify'  => false,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'url'          => $url,
				'status'       => 'error',
				'error'        => $response->get_error_message(),
				'schema_count' => 0,
				'schema_types' => [],
				'issues'       => [],
				'duplicates'   => [],
			];
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$html      = wp_remote_retrieve_body( $response );
		$schemas   = SEOB_JsonLd_Validator::extract_schemas( $html );

		$issues = [];
		foreach ( $schemas as $i => $schema ) {
			$result = SEOB_JsonLd_Validator::validate_schema( $schema );
			$type   = $result['type'] ?? 'unknown';

			foreach ( $result['errors'] as $err ) {
				$issues[] = [
					'schema_index' => $i,
					'type'         => $type,
					'severity'     => 'error',
					'message'      => $err,
					'fix_hint'     => self::get_fix_hint( $err, $type ),
				];
			}
			foreach ( $result['warnings'] as $warn ) {
				$issues[] = [
					'schema_index' => $i,
					'type'         => $type,
					'severity'     => 'warning',
					'message'      => $warn,
					'fix_hint'     => self::get_fix_hint( $warn, $type ),
				];
			}
		}

		$duplicates = SEOB_JsonLd_Validator::detect_duplicates( $schemas );

		$schema_types = array_values( array_unique( array_map(
			static function ( array $s ): string {
				if ( isset( $s['_parse_error'] ) ) {
					return '(chyba parsovani)';
				}
				$t = is_array( $s['@type'] ?? '' ) ? implode( ',', $s['@type'] ) : ( $s['@type'] ?? 'unknown' );
				return SEOB_JsonLd_Validator::short_type( $t );
			},
			$schemas
		) ) );

		return [
			'url'          => $url,
			'status'       => 'ok',
			'http_code'    => $http_code,
			'schema_count' => count( $schemas ),
			'schema_types' => $schema_types,
			'issues'       => $issues,
			'duplicates'   => $duplicates,
		];
	}

	/**
	 * Vraci seznam polozek k provereni.
	 * Kazda polozka: {url, post_type, post_type_label, post_id, edit_url}
	 *
	 * @return list<array{url:string, post_type:string, post_type_label:string, post_id:int, edit_url:string}>
	 */
	public static function get_scan_urls( int $limit = self::MAX_URLS ): array {
		$items = [];

		// Hlavni stranka
		$homepage_id  = (int) get_option( 'page_on_front' );
		$items[] = [
			'url'             => home_url( '/' ),
			'post_type'       => 'homepage',
			'post_type_label' => 'Hlavni stranka',
			'post_id'         => $homepage_id,
			'edit_url'        => $homepage_id > 0
				? (string) get_edit_post_link( $homepage_id, 'url' )
				: admin_url( 'options-reading.php' ),
		];

		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );

		$posts = get_posts( [
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => $limit - 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );

		$seen_urls = [ home_url( '/' ) => true ];

		foreach ( $posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( ! $permalink || isset( $seen_urls[ $permalink ] ) ) {
				continue;
			}
			$seen_urls[ $permalink ] = true;

			$post_type = get_post_type( $post_id );
			$pto       = $post_type ? get_post_type_object( $post_type ) : null;
			$label     = $pto ? $pto->labels->name : (string) $post_type;

			$items[] = [
				'url'             => $permalink,
				'post_type'       => (string) $post_type,
				'post_type_label' => $label,
				'post_id'         => (int) $post_id,
				'edit_url'        => (string) get_edit_post_link( $post_id, 'url' ),
			];
		}

		return array_slice( $items, 0, $limit );
	}

	/**
	 * Zpetna kompatibilita s HealthChecks (vraci nejnovejsi dokonceny scan).
	 */
	public static function get_last_results(): array {
		$history = SEOB_JsonLd_ScanRunner::get_history();
		if ( empty( $history ) ) {
			return [];
		}

		$latest  = $history[0];
		$results = SEOB_JsonLd_ScanRunner::get_results( (int) $latest['scan_id'] );

		return [
			'summary' => $latest,
			'results' => $results,
		];
	}

	// ── Private ───────────────────────────────────────────────────────────────

	/**
	 * Navrhne opravny postup ke konkretni chybove zprave.
	 */
	private static function get_fix_hint( string $message, string $schema_type ): string {
		// Chyba parsovani JSON
		if ( str_contains( $message, 'parsovani JSON' ) || str_contains( $message, 'parse' ) ) {
			return 'Schema obsahuje neplatny JSON. Najdete ho v sablone nebo pluginu, ktery vklada vlastni &lt;script type="application/ld+json"&gt;. Pouzijte online JSON validator (napr. jsonlint.com) k nalezeni chyby.';
		}

		// @type
		if ( str_contains( $message, '@type' ) ) {
			return 'Schema nema definovany typ (@type). Zkontrolujte plugin nebo sablonu, ktera toto schema generuje.';
		}

		// @context
		if ( str_contains( $message, '@context' ) ) {
			return 'Doplnte "@context": "https://schema.org" do JSON-LD bloku. V Rank Math se nastavuje automaticky – problém je pravdepodobne v jinem zdroji schematu.';
		}

		// Headline (Article, BlogPosting...)
		if ( str_contains( $message, 'headline' ) ) {
			return 'V editoru prispevku → panel Rank Math (vpravo nebo dole) → zalozka Schema → zvolte typ "Clanek" → Headline (nebo nechte prazdne, Rank Math pouzije titulek stranky).';
		}

		// datePublished
		if ( str_contains( $message, 'datePublished' ) ) {
			return 'Datum publikace chybi ve schematu. V Rank Math → Schema → Clanek → Datum publikace. Standardne se plni automaticky z data vydani prispevku.';
		}

		// BreadcrumbList / itemListElement
		if ( str_contains( $message, 'itemListElement' ) ) {
			return 'Drobeckova navigace (BreadcrumbList) vraci prazdne pole. Zkontrolujte nastaveni Rank Math → Drobeckova navigace → ze je zapnuta a konfigurована pro tento typ stranky.';
		}

		// FAQPage / mainEntity
		if ( str_contains( $message, 'mainEntity' ) ) {
			return 'FAQ schema vyzaduje aspon jednu otazku. Pridejte FAQ blok do obsahu stranky (napr. Gutenberg blok FAQ nebo Rank Math FAQ blok) a vyplnte otazky a odpovedi.';
		}

		// Recipe
		if ( str_contains( $message, 'recipeIngredient' ) ) {
			return 'V Rank Math → Schema → Recept → pole Ingredience. Vyplnte seznam ingredienci.';
		}
		if ( str_contains( $message, 'recipeInstructions' ) ) {
			return 'V Rank Math → Schema → Recept → pole Postup. Vyplnte kroky pripravy.';
		}

		// Event / startDate
		if ( str_contains( $message, 'startDate' ) ) {
			return 'V Rank Math → Schema → Udalost → Datum zacatku. Doplnte datum a cas konani akce.';
		}

		// VideoObject
		if ( str_contains( $message, 'thumbnailUrl' ) ) {
			return 'V Rank Math → Schema → Video → Miniatura. Zadejte URL obrazku miniatury videa.';
		}
		if ( str_contains( $message, 'uploadDate' ) ) {
			return 'V Rank Math → Schema → Video → Datum nahrani. Zadejte datum, kdy bylo video nahrano.';
		}
		if ( str_contains( $message, 'description' ) && str_contains( $schema_type, 'Video' ) ) {
			return 'V Rank Math → Schema → Video → Popis. Vyplnte strucny popis videa.';
		}

		// Organization / LocalBusiness / name
		if ( str_contains( $message, '"name"' ) && in_array( $schema_type, [ 'Organization', 'LocalBusiness', 'Person', 'Service', 'Product' ], true ) ) {
			return 'Vyplnte nazev v Rank Math → Nastaveni → Titulky a Meta → Znalostni panel → Nazev, nebo v Rank Math → Local SEO → Firma.';
		}

		// itemReviewed / reviewRating
		if ( str_contains( $message, 'itemReviewed' ) || str_contains( $message, 'reviewRating' ) ) {
			return 'V Rank Math → Schema → Recenze. Vyplnte hodnocenou polozku a hodnoceni.';
		}

		// SiteLinksSearchBox / target
		if ( str_contains( $message, 'target' ) ) {
			return 'SiteLinksSearchBox schema vyzaduje URL vzor vyhledavani (target). Zkontrolujte nastaveni tohoto schematu v pluginu nebo sablone.';
		}

		// Duplicita
		if ( $schema_type !== '' && ! str_contains( $message, 'Chybi' ) ) {
			return 'Zjistete, ktery plugin nebo sablona generuje druhe schema tohoto typu (prehlizte zdrojovy kod stranky – Ctrl+U). Jedno z nich deaktivujte v nastaveni prislusneho pluginu.';
		}

		return 'Zkontrolujte nastaveni schematu pro tuto stranku v Rank Math → Schema (panel pri editaci prispevku/stranky).';
	}
}
