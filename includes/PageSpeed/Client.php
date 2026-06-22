<?php
/**
 * Klient pro Google PageSpeed Insights API v5 (Lighthouse data).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_PageSpeed_Client {

	private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/**
	 * Zavolá PSI API pro danou URL a strategii a vrátí zpracovaný výsledek.
	 *
	 * @return array|WP_Error {performance_score,accessibility_score,best_practices_score,seo_score,issues}
	 */
	public static function analyze( string $url, string $strategy, string $api_key ) {
		if ( '' === $api_key ) {
			return new WP_Error( 'seob_psi_not_configured', __( 'PageSpeed Insights API klíč není nastaven.', 'seo-boost' ) );
		}

		$query = [
			'url'      => $url,
			'key'      => $api_key,
			'strategy' => $strategy,
			'category' => [ 'performance', 'accessibility', 'best-practices', 'seo' ],
		];

		$request_url = add_query_arg( $query, self::API_URL );

		$response = wp_remote_get(
			$request_url,
			[
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['error']['message'] ) && is_string( $body['error']['message'] )
				? $body['error']['message']
				: sprintf( /* translators: %d: HTTP status code */ __( 'PageSpeed Insights požadavek selhal (HTTP %d).', 'seo-boost' ), $code );

			return new WP_Error( 'seob_psi_request_failed', $message );
		}

		return self::parse_response( $body );
	}

	/**
	 * Zpracuje JSON odpověď PSI API na strukturu pro uložení do DB.
	 *
	 * @return array|WP_Error
	 */
	public static function parse_response( array $body ) {
		$lighthouse = $body['lighthouseResult'] ?? null;

		if ( ! is_array( $lighthouse ) ) {
			return new WP_Error( 'seob_psi_invalid_response', __( 'PageSpeed Insights vrátila neočekávanou odpověď.', 'seo-boost' ) );
		}

		$categories = $lighthouse['categories'] ?? [];
		$audits     = $lighthouse['audits'] ?? [];

		$scores = [
			'performance_score'    => self::category_score( $categories, 'performance' ),
			'accessibility_score'  => self::category_score( $categories, 'accessibility' ),
			'best_practices_score' => self::category_score( $categories, 'best-practices' ),
			'seo_score'            => self::category_score( $categories, 'seo' ),
		];

		$issues = [];

		$seo_audit_refs = $categories['seo']['auditRefs'] ?? [];

		if ( is_array( $seo_audit_refs ) ) {
			foreach ( $seo_audit_refs as $ref ) {
				$audit_id = $ref['id'] ?? '';

				if ( '' === $audit_id || ! isset( $audits[ $audit_id ] ) ) {
					continue;
				}

				$audit = $audits[ $audit_id ];

				if ( ! is_array( $audit ) ) {
					continue;
				}

				$score              = $audit['score'] ?? null;
				$score_display_mode = $audit['scoreDisplayMode'] ?? '';

				if ( 'notApplicable' === $score_display_mode || 'informative' === $score_display_mode ) {
					continue;
				}

				if ( null === $score || ( is_numeric( $score ) && (float) $score >= 1 ) ) {
					continue;
				}

				$issues[] = [
					'id'           => $audit_id,
					'title'        => is_string( $audit['title'] ?? null ) ? $audit['title'] : '',
					'description'  => is_string( $audit['description'] ?? null ) ? $audit['description'] : '',
					'score'        => is_numeric( $score ) ? (float) $score : null,
					'displayValue' => is_string( $audit['displayValue'] ?? null ) ? $audit['displayValue'] : '',
				];
			}
		}

		return array_merge( $scores, [ 'issues' => $issues ] );
	}

	/**
	 * Vrátí skóre kategorie v rozsahu 0-100, nebo null pokud chybí.
	 */
	private static function category_score( array $categories, string $key ): ?int {
		$score = $categories[ $key ]['score'] ?? null;

		if ( ! is_numeric( $score ) ) {
			return null;
		}

		return (int) round( (float) $score * 100 );
	}
}
