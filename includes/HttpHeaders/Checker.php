<?php
/**
 * HTTP Headers Checker – analyzes response headers of one URL.
 *
 * Returns structured result with issues grouped by severity:
 *   critical  – blocks indexing or causes major SEO problems
 *   warning   – security/SEO best practice missing
 *   info      – minor optimization opportunities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_HttpHeaders_Checker {

	/**
	 * Fetch headers for $url and return analysis result.
	 *
	 * @return array{
	 *   url: string,
	 *   status_code: int,
	 *   redirect_url: string,
	 *   headers: array<string,string>,
	 *   issues: list<array{type:string,severity:string,label:string,message:string}>,
	 *   score: int,
	 *   checked_at: int,
	 *   error: string
	 * }
	 */
	public static function check( string $url ): array {
		$result = [
			'url'          => $url,
			'status_code'  => 0,
			'redirect_url' => '',
			'headers'      => [],
			'issues'       => [],
			'score'        => 100,
			'checked_at'   => time(),
			'error'        => '',
		];

		// HEAD request – lightweight, no body
		$response = wp_remote_head( $url, [
			'timeout'     => 10,
			'redirection' => 0,          // Nesleduj přesměrování automaticky – chceme vidět status
			'sslverify'   => false,
			'user-agent'  => 'SEOBoosterPro/1.0 (WordPress; header-check)',
		] );

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$headers_raw = wp_remote_retrieve_headers( $response );

		// Normalizuj hlavičky na lowercase klíče → string hodnoty
		$headers = [];
		if ( is_object( $headers_raw ) && method_exists( $headers_raw, 'getAll' ) ) {
			foreach ( $headers_raw->getAll() as $key => $val ) {
				$headers[ strtolower( $key ) ] = is_array( $val ) ? implode( ', ', $val ) : (string) $val;
			}
		} elseif ( is_array( $headers_raw ) ) {
			foreach ( $headers_raw as $key => $val ) {
				$headers[ strtolower( $key ) ] = is_array( $val ) ? implode( ', ', $val ) : (string) $val;
			}
		}

		$result['status_code'] = (int) $status_code;
		$result['headers']     = $headers;

		// Redirect URL (Location header při 3xx)
		if ( $status_code >= 300 && $status_code < 400 ) {
			$result['redirect_url'] = $headers['location'] ?? '';
		}

		$result['issues'] = self::analyze( $url, $status_code, $headers );
		$result['score']  = self::calc_score( $result['issues'] );

		return $result;
	}

	/**
	 * Analyze headers and return list of issues.
	 *
	 * @return list<array{type:string,severity:string,label:string,message:string}>
	 */
	public static function analyze( string $url, int $status_code, array $headers ): array {
		$issues = [];

		// ── 1. HTTP status ──────────────────────────────────────────────────────

		if ( $status_code >= 300 && $status_code < 400 ) {
			$location = $headers['location'] ?? '';
			// Přesměrování na HTTP z HTTPS nebo naopak je OK; řetěz 301→301 je problém.
			// Zde jen hlásíme přesměrování jako info – ScanRunner detekuje chain.
			$issues[] = [
				'type'     => 'redirect',
				'severity' => 'info',
				'label'    => 'Přesměrování ' . $status_code,
				'message'  => "URL vrací {$status_code} a přesměrovává na: {$location}",
			];
		} elseif ( $status_code >= 400 ) {
			$issues[] = [
				'type'     => 'http_error',
				'severity' => 'critical',
				'label'    => 'HTTP chyba ' . $status_code,
				'message'  => "URL vrací HTTP {$status_code} – stránka není dostupná nebo existuje.",
			];
		} elseif ( $status_code !== 200 && $status_code !== 0 ) {
			$issues[] = [
				'type'     => 'unexpected_status',
				'severity' => 'warning',
				'label'    => 'Neočekávaný status ' . $status_code,
				'message'  => "URL vrací HTTP {$status_code} místo 200.",
			];
		}

		// ── 2. X-Robots-Tag – kritické pro indexaci ─────────────────────────────

		$x_robots = strtolower( $headers['x-robots-tag'] ?? '' );
		if ( str_contains( $x_robots, 'noindex' ) ) {
			$issues[] = [
				'type'     => 'x_robots_noindex',
				'severity' => 'critical',
				'label'    => 'X-Robots-Tag: noindex',
				'message'  => 'Server posílá hlavičku X-Robots-Tag: noindex – Google tuto stránku NEindexuje! Zkontrolujte .htaccess, wp-config.php nebo konfigurace serveru.',
			];
		}
		if ( str_contains( $x_robots, 'nosnippet' ) ) {
			$issues[] = [
				'type'     => 'x_robots_nosnippet',
				'severity' => 'warning',
				'label'    => 'X-Robots-Tag: nosnippet',
				'message'  => 'Server zakazuje snippety ve výsledcích vyhledávání přes HTTP hlavičku.',
			];
		}

		// ── 3. HTTPS ────────────────────────────────────────────────────────────

		$is_https = str_starts_with( $url, 'https://' );
		if ( ! $is_https ) {
			$issues[] = [
				'type'     => 'no_https',
				'severity' => 'critical',
				'label'    => 'Stránka neslouží přes HTTPS',
				'message'  => 'Google od roku 2018 upřednostňuje HTTPS. Přesměrujte veškerý HTTP provoz na HTTPS.',
			];
		}

		// ── 4. HSTS (jen na HTTPS) ───────────────────────────────────────────────

		if ( $is_https && ! isset( $headers['strict-transport-security'] ) ) {
			$issues[] = [
				'type'     => 'missing_hsts',
				'severity' => 'warning',
				'label'    => 'Chybí Strict-Transport-Security',
				'message'  => 'HSTS hlavička chybí. Přidejte: Strict-Transport-Security: max-age=31536000; includeSubDomains',
			];
		}

		// ── 5. Bezpečnostní hlavičky ─────────────────────────────────────────────

		if ( ! isset( $headers['x-content-type-options'] ) ) {
			$issues[] = [
				'type'     => 'missing_xcto',
				'severity' => 'warning',
				'label'    => 'Chybí X-Content-Type-Options',
				'message'  => 'Přidejte: X-Content-Type-Options: nosniff – zabrání MIME sniffing útokům.',
			];
		} elseif ( strtolower( $headers['x-content-type-options'] ) !== 'nosniff' ) {
			$issues[] = [
				'type'     => 'invalid_xcto',
				'severity' => 'warning',
				'label'    => 'X-Content-Type-Options má nesprávnou hodnotu',
				'message'  => 'Hlavička musí mít hodnotu "nosniff". Aktuální: ' . esc_html( $headers['x-content-type-options'] ),
			];
		}

		if ( ! isset( $headers['x-frame-options'] ) ) {
			$issues[] = [
				'type'     => 'missing_xfo',
				'severity' => 'warning',
				'label'    => 'Chybí X-Frame-Options',
				'message'  => 'Přidejte: X-Frame-Options: SAMEORIGIN – zabrání clickjacking útokům.',
			];
		}

		if ( ! isset( $headers['referrer-policy'] ) ) {
			$issues[] = [
				'type'     => 'missing_rp',
				'severity' => 'info',
				'label'    => 'Chybí Referrer-Policy',
				'message'  => 'Doporučeno: Referrer-Policy: strict-origin-when-cross-origin – řídí, jaká data se předávají při navigaci.',
			];
		}

		// ── 6. Cache hlavičky (SEO i výkon) ─────────────────────────────────────

		$has_cache = isset( $headers['cache-control'] ) || isset( $headers['expires'] );
		if ( ! $has_cache && $status_code === 200 ) {
			$issues[] = [
				'type'     => 'missing_cache',
				'severity' => 'info',
				'label'    => 'Chybí Cache-Control / Expires',
				'message'  => 'Stránka neposílá cache hlavičky. Přidejte Cache-Control: public, max-age=3600 pro lepší výkon (Google Page Experience).',
			];
		}

		// ── 7. Content-Type ──────────────────────────────────────────────────────

		$ct = $headers['content-type'] ?? '';
		if ( $status_code === 200 && ! empty( $ct ) && ! str_contains( strtolower( $ct ), 'utf-8' ) && ! str_contains( strtolower( $ct ), 'utf8' ) ) {
			$issues[] = [
				'type'     => 'charset_missing',
				'severity' => 'info',
				'label'    => 'Content-Type bez charset=utf-8',
				'message'  => 'Aktuální Content-Type: ' . esc_html( $ct ) . ' – přidejte ; charset=UTF-8 pro správnou interpretaci textu.',
			];
		}

		return $issues;
	}

	/**
	 * Score 0–100 based on issues found. Starts at 100, deducts per issue.
	 */
	private static function calc_score( array $issues ): int {
		$deductions = [
			'critical' => 30,
			'warning'  => 10,
			'info'     => 3,
		];

		$score = 100;
		foreach ( $issues as $issue ) {
			$score -= $deductions[ $issue['severity'] ] ?? 0;
		}

		return max( 0, $score );
	}
}
