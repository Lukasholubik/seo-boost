<?php
/**
 * Porovná rendered DOM snapshot s raw HTML a vypočítá gap skóre.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_JsGap_Comparator {

	/**
	 * Stáhne raw HTML pro danou cestu a porovná ji se snapshotem.
	 * Vrátí pole [ 'gap_score', 'issues', 'raw_*' ].
	 */
	public static function analyze( array $snapshot ): ?array {
		$path     = $snapshot['path'] ?? '';
		$site_url = trailingslashit( home_url() );
		$url      = $site_url . ltrim( $path, '/' );

		$response = wp_remote_get( $url, [
			'timeout'   => 8,
			'sslverify' => false,
			'headers'   => [ 'User-Agent' => 'Mozilla/5.0 (compatible; SEO-Booster-RenderGap/1.0)' ],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$raw_html = wp_remote_retrieve_body( $response );
		$raw      = self::parse_raw_html( $raw_html );
		$issues   = self::compare( $snapshot, $raw );
		$score    = self::calc_score( $issues );

		return [
			'gap_score'       => $score,
			'issues_json'     => wp_json_encode( $issues ),
			'raw_title'       => $raw['title'],
			'raw_h1'          => $raw['h1'],
			'raw_meta_desc'   => $raw['meta_desc'],
			'raw_json_ld_count' => $raw['json_ld_count'],
			'raw_text_len'    => $raw['text_len'],
			'raw_links_count' => $raw['links_count'],
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

		// JSON-LD gap
		$rendered_jsonld = (int) ( $snap['json_ld_count'] ?? 0 );
		$raw_jsonld      = (int) $raw['json_ld_count'];
		if ( $rendered_jsonld > $raw_jsonld && $rendered_jsonld > 0 ) {
			$diff = $rendered_jsonld - $raw_jsonld;
			$issues[] = [
				'type'     => 'json_ld_gap',
				'severity' => $diff >= $rendered_jsonld ? 'critical' : 'warning',
				'rendered' => $rendered_jsonld,
				'raw'      => $raw_jsonld,
				'message'  => sprintf( '%d JSON-LD blok(ů) přítomno v rendered DOM, ale jen %d v raw HTML – strukturovaná data chybí pro crawlery bez JS.', $rendered_jsonld, $raw_jsonld ),
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
