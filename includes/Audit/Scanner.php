<?php
/**
 * Skenuje jeden příspěvek/stránku a vrací skóre + seznam SEO nálezů.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Audit_Scanner {

	private const TITLE_MAX_PX       = 580;
	private const DESCRIPTION_MAX_PX = 920;

	private const SEVERITY_PENALTY = [
		'critical'       => 15,
		'warning'        => 7,
		'recommendation' => 3,
	];

	/**
	 * Provede scan jednoho příspěvku.
	 *
	 * @return array{object_id:int,object_type:string,url:string,score:int,issues:array,content_hash:string}|null
	 */
	public function scan_post( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		$title       = get_the_title( $post );
		$description = (string) get_post_meta( $post_id, 'rank_math_description', true );
		$focus_kw    = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		$robots      = get_post_meta( $post_id, 'rank_math_robots', true );

		$content = $this->extract_content_data( $post );

		$issues = [];

		// --- Title ---------------------------------------------------------
		if ( '' === trim( $title ) ) {
			$issues[] = $this->issue( 'title_missing', 'critical' );
		} else {
			$title_px = SEOB_Pixel_Width::calculate( $title );
			if ( $title_px > self::TITLE_MAX_PX ) {
				$issues[] = $this->issue( 'title_too_long', 'warning', round( $title_px ) . 'px' );
			}
		}

		// --- Meta description ----------------------------------------------
		if ( '' === trim( $description ) ) {
			$issues[] = $this->issue( 'description_missing', 'critical' );
		} else {
			$desc_px = SEOB_Pixel_Width::calculate( $description );
			if ( $desc_px > self::DESCRIPTION_MAX_PX ) {
				$issues[] = $this->issue( 'description_too_long', 'warning', round( $desc_px ) . 'px' );
			}
		}

		// --- H1 / nadpisy ----------------------------------------------------
		// H1 se ověřuje přímo na vykresleném výstupu stránky, protože u příspěvků
		// (a u stránek s šablonou) bývá nadpis vykreslen šablonou/Theme Builderem
		// mimo `_elementor_data`/`post_content`, takže by jinak byl falešně hlášen
		// jako chybějící.
		$rendered_h1 = $this->count_rendered_h1( $post );
		$h1_count    = null !== $rendered_h1 ? $rendered_h1 : ( $content['headings'][1] ?? 0 );

		if ( 0 === $h1_count ) {
			$issues[] = $this->issue( 'h1_missing', 'critical' );
		} elseif ( $h1_count > 1 ) {
			$issues[] = $this->issue( 'h1_duplicate', 'warning', $h1_count . 'x' );
		}

		if ( $this->has_heading_gap( $content['headings'] ) ) {
			$issues[] = $this->issue( 'heading_hierarchy', 'recommendation' );
		}

		// --- Obrázky / alt -----------------------------------------------------
		if ( $content['images_total'] > 0 && $content['images_missing_alt'] > 0 ) {
			$issues[] = $this->issue(
				'missing_alt',
				'warning',
				$content['images_missing_alt'] . '/' . $content['images_total']
			);
		}

		// --- Strukturovaná data ------------------------------------------------
		// Bere v úvahu i výchozí schéma dle nastavení Rank Math pro daný typ obsahu
		// a výchozí schéma kategorie (SEOB_Schema_Helper) – ne jen vlastní meta příspěvku.
		$schema_info  = SEOB_Schema_Helper::get_effective_type( $post );
		$schema_empty = ( 'off' === $schema_info['type'] || '' === $schema_info['type'] );
		if ( $schema_empty && empty( $schema_info['is_explicit'] ) ) {
			$issues[] = $this->issue( 'schema_missing', 'warning' );
		}

		// --- Indexace ------------------------------------------------------
		if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
			$issues[] = $this->issue( 'noindex_set', 'warning' );
		}

		// --- Obsah -----------------------------------------------------------
		$thin_words = (int) SEOB_Settings::get( SEOB_Settings::AUDIT )['thin_content_words'];
		if ( $content['word_count'] < $thin_words ) {
			$issues[] = $this->issue( 'thin_content', 'warning', $content['word_count'] . ' slov' );
		}

		// --- Focus keyword ----------------------------------------------------
		if ( '' === trim( $focus_kw ) ) {
			$issues[] = $this->issue( 'focus_keyword_missing', 'recommendation' );
		}

		$score = $this->calculate_score( $issues );

		$hash_source = wp_json_encode( [
			$title,
			$description,
			$content['word_count'],
			$content['headings'],
			$h1_count,
			$content['images_total'],
			$content['images_missing_alt'],
			$focus_kw,
			$robots,
			$schema_info,
		] );

		return [
			'object_id'    => $post_id,
			'object_type'  => $post->post_type,
			'url'          => (string) get_permalink( $post ),
			'title'        => $title,
			'description'  => $description,
			'score'        => $score,
			'issues'       => $issues,
			'schema'       => $schema_info,
			'content_hash' => md5( (string) $hash_source ),
		];
	}

	private function issue( string $type, string $severity, ?string $detail = null ): array {
		return [
			'type'     => $type,
			'severity' => $severity,
			'detail'   => $detail,
		];
	}

	private function calculate_score( array $issues ): int {
		$score = 100;

		foreach ( $issues as $issue ) {
			$score -= self::SEVERITY_PENALTY[ $issue['severity'] ] ?? 0;
		}

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Spočítá unikátní H1 nadpisy ve skutečně vykresleném HTML stránky.
	 *
	 * Na rozdíl od obsahu příspěvku/Elementor dat zachytí i nadpis vložený
	 * šablonou nebo Theme Builder šablonou (typický případ u standardních
	 * WP příspěvků). Vrací `null`, pokud se stránku nepodařilo načíst –
	 * v tom případě se použije fallback na nadpisy z obsahu.
	 */
	private function count_rendered_h1( WP_Post $post ): ?int {
		$cache_key = 'seob_h1_' . $post->ID;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && ( $cached['modified'] ?? '' ) === $post->post_modified_gmt ) {
			return $cached['count'];
		}

		$url = get_permalink( $post );

		if ( ! $url ) {
			return null;
		}

		// Loopback request: posílá cookies přihlášeného uživatele, aby WP Rocket
		// nevrátil starou cache a Wordfence/firewall nebral požadavek jako bota.
		$cookies = [];
		foreach ( $_COOKIE as $name => $val ) {
			$cookies[] = new WP_Http_Cookie( [ 'name' => $name, 'value' => $val ] );
		}

		$args = [
			'timeout'   => 10,
			'sslverify' => false,
			'cookies'   => $cookies,
			'headers'   => [ 'Cache-Control' => 'no-cache' ],
		];

		// Loopback požadavky během dávkového scanu občas selžou (vytížení
		// PHP-FPM workerů / firewall), proto až 3 pokusy s krátkou prodlevou.
		$response = null;
		for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
			$response = wp_remote_get( $url, $args );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				break;
			}

			if ( $attempt < 3 ) {
				usleep( 300000 );
			}
		}

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$html = wp_remote_retrieve_body( $response );

		if ( '' === trim( $html ) ) {
			return null;
		}

		preg_match_all( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches );

		$unique = [];

		foreach ( $matches[1] as $inner ) {
			$text = trim( wp_strip_all_tags( $inner ) );

			if ( '' !== $text ) {
				$unique[ $text ] = true;
			}
		}

		$count = count( $unique );

		set_transient( $cache_key, [ 'modified' => $post->post_modified_gmt, 'count' => $count ], DAY_IN_SECONDS );

		return $count;
	}

	/**
	 * Zjistí, zda v hierarchii nadpisů chybí mezikrok (např. H2 -> H4 bez H3).
	 *
	 * @param array<int,int> $headings Počty nadpisů indexované úrovní (1-6).
	 */
	private function has_heading_gap( array $headings ): bool {
		$levels_used = [];

		foreach ( $headings as $level => $count ) {
			if ( $count > 0 ) {
				$levels_used[] = $level;
			}
		}

		sort( $levels_used );

		for ( $i = 1, $c = count( $levels_used ); $i < $c; $i++ ) {
			if ( $levels_used[ $i ] - $levels_used[ $i - 1 ] > 1 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Získá data o obsahu (nadpisy, obrázky, počet slov) – z Elementor dat,
	 * pokud existují, jinak z vykresleného `the_content`.
	 *
	 * @return array{headings:array<int,int>,images_total:int,images_missing_alt:int,word_count:int}
	 */
	private function extract_content_data( WP_Post $post ): array {
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );

		if ( ! empty( $elementor_data ) ) {
			$decoded = json_decode( (string) $elementor_data, true );

			if ( is_array( $decoded ) ) {
				return $this->extract_from_elementor( $decoded );
			}
		}

		return $this->extract_from_html( $post );
	}

	/**
	 * Projde strom Elementor widgetů a posbírá nadpisy, obrázky a textový obsah.
	 */
	private function extract_from_elementor( array $elements ): array {
		$headings = array_fill( 1, 6, 0 );
		$images_total = 0;
		$images_missing_alt = 0;
		$text_parts = [];

		$walk = function ( array $elements ) use ( &$walk, &$headings, &$images_total, &$images_missing_alt, &$text_parts ) {
			foreach ( $elements as $element ) {
				$widget_type = $element['widgetType'] ?? '';
				$settings    = $element['settings'] ?? [];

				if ( 'heading' === $widget_type ) {
					$tag = $settings['header_size'] ?? 'h2';
					$level = (int) preg_replace( '/[^0-9]/', '', $tag );
					$level = $level >= 1 && $level <= 6 ? $level : 2;
					$headings[ $level ]++;
					$text_parts[] = wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) );
				}

				if ( 'image' === $widget_type ) {
					$images_total++;
					$alt = $settings['image']['alt'] ?? '';
					if ( '' === trim( (string) $alt ) || $this->is_generic_alt( (string) $alt ) ) {
						$images_missing_alt++;
					}
				}

				if ( in_array( $widget_type, [ 'text-editor', 'heading', 'icon-box', 'button' ], true ) ) {
					$text_parts[] = wp_strip_all_tags( (string) ( $settings['editor'] ?? '' ) );
					$text_parts[] = wp_strip_all_tags( (string) ( $settings['description'] ?? '' ) );
				}

				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};

		$walk( $elements );

		$word_count = $this->count_words( implode( ' ', $text_parts ) );

		return [
			'headings'           => $headings,
			'images_total'       => $images_total,
			'images_missing_alt' => $images_missing_alt,
			'word_count'         => $word_count,
		];
	}

	/**
	 * Fallback pro klasický obsah – vykreslí `the_content` a parsuje DOM.
	 */
	private function extract_from_html( WP_Post $post ): array {
		$headings = array_fill( 1, 6, 0 );
		$images_total = 0;
		$images_missing_alt = 0;

		$raw_content = $post->post_content;
		$plain_text  = wp_strip_all_tags( strip_shortcodes( $raw_content ) );
		$word_count  = $this->count_words( $plain_text );

		if ( '' !== trim( $raw_content ) ) {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $raw_content . '</div>' );
			libxml_clear_errors();

			for ( $level = 1; $level <= 6; $level++ ) {
				$headings[ $level ] = $dom->getElementsByTagName( 'h' . $level )->length;
			}

			foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
				$images_total++;
				$alt = $img->getAttribute( 'alt' );
				if ( '' === trim( $alt ) || $this->is_generic_alt( $alt ) ) {
					$images_missing_alt++;
				}
			}
		}

		return [
			'headings'           => $headings,
			'images_total'       => $images_total,
			'images_missing_alt' => $images_missing_alt,
			'word_count'         => $word_count,
		];
	}

	private function count_words( string $text ): int {
		$text = trim( $text );

		if ( '' === $text ) {
			return 0;
		}

		return count( preg_split( '/\s+/u', $text ) ?: [] );
	}

	/**
	 * Detekuje generické alt texty typu "image1", "img_1234", "dsc_0001" apod.
	 */
	private function is_generic_alt( string $alt ): bool {
		$alt = trim( $alt );

		if ( '' === $alt ) {
			return false;
		}

		return (bool) preg_match( '/^(img|image|dsc|photo|picture|obrazek|obrázek)[\s_-]*\d*$/i', $alt );
	}
}
