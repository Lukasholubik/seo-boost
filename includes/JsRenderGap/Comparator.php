<?php
/**
 * Porovná rendered DOM snapshot s raw HTML a vypočítá gap skóre.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_JsGap_Comparator {

	/**
	 * Analyzuje snapshot vůči raw (pre-JS) datům stránky.
	 * Používá WordPress API místo HTTP requestu, aby nedošlo k deadlocku
	 * na lokálních serverech s jedním PHP workerem (Local by Flywheel apod.).
	 */
	public static function analyze( array $snapshot ): ?array {
		$path = $snapshot['path'] ?? '';
		if ( $path === '' ) return null;

		$raw = self::get_raw_via_wp( $path );
		if ( null === $raw ) return null;

		$issues = self::compare( $snapshot, $raw );
		$score  = self::calc_score( $issues );

		return [
			'gap_score'         => $score,
			'issues_json'       => wp_json_encode( $issues ),
			'raw_title'         => $raw['title'],
			'raw_h1'            => $raw['h1s'][0] ?? '',
			'raw_meta_desc'     => $raw['meta_desc'],
			'raw_json_ld_count' => $raw['json_ld_count'],
			'raw_text_len'      => $raw['text_len'],
			'raw_links_count'   => $raw['links_count'],
		];
	}

	/**
	 * Získá "raw" (pre-JS) metadata stránky přes WordPress API – bez HTTP requestu.
	 * Pro standardní WP stránky/příspěvky a Rank Math.
	 */
	private static function get_raw_via_wp( string $path ): ?array {
		$url     = home_url( $path );
		$post_id = url_to_postid( $url );

		// url_to_postid() vrátí 0 pro homepage když je nastavena jako statická stránka –
		// použij page_on_front přímo, aby nedošlo k rekurzivnímu volání
		if ( $post_id === 0 && get_option( 'show_on_front' ) === 'page' && rtrim( $path, '/' ) === '' ) {
			$post_id = (int) get_option( 'page_on_front' );
		}

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_status !== 'publish' ) return null;

			// Title: Rank Math / Yoast nastavují title server-side, výsledek = to co vidí crawler
			$raw_title = trim( get_post_meta( $post_id, 'rank_math_title', true ) ?: '' );
			if ( $raw_title === '' ) {
				$raw_title = get_the_title( $post_id ) . ' – ' . get_bloginfo( 'name' );
			} else {
				// Nahraď Rank Math proměnné základními hodnotami
				$raw_title = str_replace(
					[ '%title%', '%sitename%', '%sep%' ],
					[ get_the_title( $post_id ), get_bloginfo( 'name' ), '–' ],
					$raw_title
				);
			}

			// H1: v standard WP tématech = titulek příspěvku
			$raw_h1 = get_the_title( $post_id );

			// Meta description: Rank Math → Yoast → post_excerpt (bez filter hooků)
			$raw_meta_desc = trim( get_post_meta( $post_id, 'rank_math_description', true ) ?: '' );
			if ( $raw_meta_desc === '' ) {
				$raw_meta_desc = trim( get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ?: '' );
			}
			if ( $raw_meta_desc === '' ) {
				// get_post_field místo get_the_excerpt() – nespouští hooky třetích stran
				$raw_meta_desc = trim( get_post_field( 'post_excerpt', $post_id ) );
			}

			// JSON-LD: počítáme <script> TAGY (ne schema objekty uvnitř).
			// Rank Math vždy outputuje JEDEN <script type="application/ld+json"> tag
			// s @graph bez ohledu na to, kolik schema objektů je uloženo v DB.
			// Porovnávat count(get_schemas()) vs beacon tag count jsou různé jednotky → false positives.
			$raw_json_ld = 0;
			if ( class_exists( '\RankMath\Schema\DB' ) ) {
				$schemas = \RankMath\Schema\DB::get_schemas( $post_id );
				if ( is_array( $schemas ) && ! empty( $schemas ) ) {
					$raw_json_ld = 1; // Rank Math = vždy 1 script tag (@graph)
				}
			}
			if ( $raw_json_ld === 0 && class_exists( 'RankMath' ) ) {
				// Rank Math generuje globální schémata (WebSite, WebPage) na každé stránce
				$raw_json_ld = 1;
			}

			// Text length: obsah příspěvku bez tagů
			$content  = get_post_field( 'post_content', $post_id );
			$raw_text = mb_strlen( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $content ) ) );

			// Interní odkazy v obsahu
			$raw_links = substr_count( $content, '<a ' );

			return [
				'title'         => $raw_title,
				'h1s'           => [ $raw_h1 ],
				'meta_desc'     => $raw_meta_desc,
				'json_ld_count' => $raw_json_ld,
				'text_len'      => $raw_text,
				'links_count'   => $raw_links,
			];
		}

		// Archiv, vyhledávání apod. – základní fallback, gap score bude 0
		return [
			'title'         => get_bloginfo( 'name' ),
			'h1s'           => [],
			'meta_desc'     => get_bloginfo( 'description' ),
			'json_ld_count' => 0,
			'text_len'      => 0,
			'links_count'   => 0,
		];
	}

	// ── HTML parser ───────────────────────────────────────────────────────────

	private static function parse_raw_html( string $html ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		// Title
		$title_nodes = $xpath->query( '//title' );
		$title = $title_nodes->length > 0 ? trim( $title_nodes->item(0)->textContent ) : '';

		// H1
		$h1_nodes = $xpath->query( '//h1' );
		$h1s = [];
		foreach ( $h1_nodes as $node ) {
			$text = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
			if ( $text !== '' ) $h1s[] = $text;
		}

		// Meta description
		$meta_nodes = $xpath->query( '//meta[@name="description"]' );
		$meta_desc  = $meta_nodes->length > 0
			? trim( (string) $meta_nodes->item(0)->getAttribute( 'content' ) )
			: '';

		// JSON-LD count
		$json_ld_nodes = $xpath->query( '//script[@type="application/ld+json"]' );
		$json_ld_count = $json_ld_nodes->length;

		// Body text length (approximation: strip tags)
		$body_nodes = $xpath->query( '//body' );
		$body_text  = $body_nodes->length > 0 ? strip_tags( $body_nodes->item(0)->textContent ) : '';
		$text_len   = mb_strlen( preg_replace( '/\s+/', ' ', trim( $body_text ) ) );

		// Links
		$link_nodes  = $xpath->query( '//a[@href]' );
		$links_count = $link_nodes->length;

		return compact( 'title', 'h1s', 'meta_desc', 'json_ld_count', 'text_len', 'links_count' );
	}

	// ── Comparison ────────────────────────────────────────────────────────────

	private static function compare( array $snap, array $raw ): array {
		$issues = [];

		$rendered_h1 = trim( explode( ' | ', $snap['h1'] ?? '' )[0] );
		$raw_h1      = $raw['h1s'][0] ?? '';

		// H1 zcela chybí v raw HTML
		if ( $rendered_h1 !== '' && $raw_h1 === '' ) {
			$issues[] = [
				'type'     => 'h1_missing_in_raw',
				'severity' => 'critical',
				'rendered' => $rendered_h1,
				'raw'      => '',
				'message'  => 'H1 "' . mb_substr( $rendered_h1, 0, 80 ) . '" je v rendered DOM, ale v raw HTML chybí – Google ho nemusí vidět bez JS renderování.',
			];
		} elseif ( $rendered_h1 !== '' && $raw_h1 !== '' && self::similarity( $rendered_h1, $raw_h1 ) < 0.6 ) {
			$issues[] = [
				'type'     => 'h1_mismatch',
				'severity' => 'warning',
				'rendered' => $rendered_h1,
				'raw'      => $raw_h1,
				'message'  => 'H1 v rendered DOM se liší od H1 v raw HTML – obsah je pravděpodobně injektován JavaScriptem.',
			];
		}

		// Title mismatch
		$rendered_title = $snap['title'] ?? '';
		if ( $rendered_title !== '' && $raw['title'] !== '' ) {
			if ( self::similarity( $rendered_title, $raw['title'] ) < 0.7 ) {
				$issues[] = [
					'type'     => 'title_mismatch',
					'severity' => 'warning',
					'rendered' => $rendered_title,
					'raw'      => $raw['title'],
					'message'  => 'Title tag v rendered DOM se liší od raw HTML verze.',
				];
			}
		}

		// Meta description chybí v raw
		$rendered_meta = $snap['meta_desc'] ?? '';
		if ( $rendered_meta !== '' && $raw['meta_desc'] === '' ) {
			$issues[] = [
				'type'     => 'meta_desc_missing_in_raw',
				'severity' => 'warning',
				'rendered' => $rendered_meta,
				'raw'      => '',
				'message'  => 'Meta description je nastavena v rendered DOM, ale v raw HTML chybí.',
			];
		}

		// JSON-LD gap: rendered má více <script> tagů než raw HTML.
		// raw_jsonld = 0 → JSON-LD úplně chybí (kritické).
		// raw_jsonld > 0 → Rank Math/Yoast funguje, extra blok(y) přidány JavaScriptem.
		$rendered_jsonld = (int) ( $snap['json_ld_count'] ?? 0 );
		$raw_jsonld      = (int) $raw['json_ld_count'];
		if ( $rendered_jsonld > $raw_jsonld && $rendered_jsonld > 0 ) {
			$diff     = $rendered_jsonld - $raw_jsonld;
			$severity = $raw_jsonld === 0 ? 'critical' : 'warning';
			if ( $raw_jsonld === 0 ) {
				$msg = sprintf(
					'JSON-LD zcela chybí v raw HTML, ale beacon nalezl %d blok(ů) v rendered DOM – strukturovaná data jsou vkládána JavaScriptem a Google je nemusí vidět.',
					$rendered_jsonld
				);
			} else {
				$msg = sprintf(
					'Beacon nalezl %d JSON-LD bloků v rendered DOM, ale v raw HTML jen %d. ' .
					'Rank Math / Yoast SEO vkládá JSON-LD staticky (správně). ' .
					'Extra %s byl pravděpodobně přidán JavaScriptem (Elementor Schema widget, GTM nebo jiný plugin) – Google ho nemusí vidět.',
					$rendered_jsonld,
					$raw_jsonld,
					$diff === 1 ? '1 blok' : "{$diff} bloky"
				);
			}
			$issues[] = [
				'type'     => 'json_ld_gap',
				'severity' => $severity,
				'rendered' => $rendered_jsonld,
				'raw'      => $raw_jsonld,
				'message'  => $msg,
			];
		}

		// Text ratio
		$rendered_text = (int) ( $snap['text_len'] ?? 0 );
		$raw_text      = (int) $raw['text_len'];
		if ( $rendered_text > 0 && $raw_text > 0 ) {
			$ratio = $rendered_text / $raw_text;
			if ( $ratio > 2.0 ) {
				$issues[] = [
					'type'     => 'text_ratio_critical',
					'severity' => 'critical',
					'rendered' => $rendered_text,
					'raw'      => $raw_text,
					'message'  => sprintf( 'Rendered DOM obsahuje %.0f×× více textu než raw HTML (%d vs %d znaků) – velký obsah je generován JavaScriptem.', $ratio, $rendered_text, $raw_text ),
				];
			} elseif ( $ratio > 1.5 ) {
				$issues[] = [
					'type'     => 'text_ratio_warning',
					'severity' => 'warning',
					'rendered' => $rendered_text,
					'raw'      => $raw_text,
					'message'  => sprintf( 'Rendered DOM obsahuje %.1f× více textu než raw HTML – část obsahu je generována JavaScriptem.', $ratio ),
				];
			}
		}

		return $issues;
	}

	// ── Gap score ─────────────────────────────────────────────────────────────

	private static function calc_score( array $issues ): int {
		$points = [
			'h1_missing_in_raw'       => 35,
			'h1_mismatch'             => 20,
			'title_mismatch'          => 15,
			'meta_desc_missing_in_raw'=> 15,
			'json_ld_gap'             => 20,
			'text_ratio_critical'     => 20,
			'text_ratio_warning'      => 10,
		];
		$score = 0;
		foreach ( $issues as $issue ) {
			$score += $points[ $issue['type'] ] ?? 5;
		}
		return min( 100, $score );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function similarity( string $a, string $b ): float {
		$a = mb_strtolower( trim( $a ) );
		$b = mb_strtolower( trim( $b ) );
		if ( $a === $b ) return 1.0;
		if ( $a === '' || $b === '' ) return 0.0;
		similar_text( $a, $b, $pct );
		return (float) ( $pct / 100 );
	}
}
