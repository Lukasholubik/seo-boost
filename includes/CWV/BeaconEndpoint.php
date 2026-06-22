<?php
/**
 * REST endpoint pro příjem Core Web Vitals beaconů z frontendu.
 * Veřejný endpoint – žádná autentizace, anonymní data (žádné PII).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_CWV_BeaconEndpoint {

	private const NAMESPACE    = 'seo-booster/v1';
	private const ROUTE        = '/cwv';
	private const RATE_TRANSIENT_TTL = 60; // seconds
	private const RATE_MAX_PER_MINUTE = 30;

	private const VALID_METRICS = [ 'LCP', 'INP', 'CLS', 'FCP', 'TTFB' ];
	private const VALID_RATINGS = [ 'good', 'needs-improvement', 'poor' ];
	private const VALID_DEVICES = [ 'mobile', 'desktop' ];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'metric' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => fn( $v ) => in_array( $v, self::VALID_METRICS, true ),
				],
				'value' => [
					'required'          => true,
					'sanitize_callback' => fn( $v ) => (float) $v,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v >= 0 && $v < 60000,
				],
				'rating' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => fn( $v ) => in_array( $v, self::VALID_RATINGS, true ),
				],
				'path' => [
					'required'          => true,
					'sanitize_callback' => static function ( string $v ): string {
						// Ponechat jen pathname – žádné query params (které by mohly obsahovat PII)
						$v = preg_replace( '/\?.*/', '', $v );
						return mb_substr( sanitize_text_field( $v ), 0, 500 );
					},
					'validate_callback' => fn( $v ) => is_string( $v ) && strlen( $v ) > 0,
				],
				'device' => [
					'required'          => false,
					'default'           => 'desktop',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => fn( $v ) => in_array( $v, self::VALID_DEVICES, true ),
				],
				'lcp_element' => [
					'required'          => false,
					'default'           => null,
					'sanitize_callback' => static fn( $v ) => $v ? mb_substr( sanitize_text_field( (string) $v ), 0, 300 ) : null,
				],
			],
		] );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		// Rate limit – jednoduchý IP hash (ne raw IP – GDPR)
		$ip_hash = substr( md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ), 0, 16 );
		$rate_key = 'seob_cwv_rl_' . $ip_hash;
		$hits     = (int) get_transient( $rate_key );

		if ( $hits >= self::RATE_MAX_PER_MINUTE ) {
			return new WP_REST_Response( [ 'ok' => false, 'error' => 'rate_limit' ], 429 );
		}

		set_transient( $rate_key, $hits + 1, self::RATE_TRANSIENT_TTL );

		$metric      = $request->get_param( 'metric' );
		$value       = (float) $request->get_param( 'value' );
		$rating      = $request->get_param( 'rating' );
		$path        = $request->get_param( 'path' );
		$device      = $request->get_param( 'device' );
		$lcp_element = $request->get_param( 'lcp_element' );

		global $wpdb;
		$table = SEOB_Database::cwv_raw_table();

		$wpdb->insert(
			$table,
			[
				'url_hash'    => md5( $path ),
				'path'        => $path,
				'metric'      => $metric,
				'value'       => $value,
				'rating'      => $rating,
				'device'      => $device,
				'lcp_element' => $metric === 'LCP' ? $lcp_element : null,
				'recorded_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ]
		);

		return new WP_REST_Response( [ 'ok' => true ], 201 );
	}
}
