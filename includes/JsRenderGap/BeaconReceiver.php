<?php
/**
 * REST endpoint: příjem DOM snapshotů z frontend beaconu.
 * POST /wp-json/seo-booster/v1/js-gap
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_JsGap_BeaconReceiver {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'seo-booster/v1', '/js-gap', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true',
		] );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$body    = $request->get_body();
		$decoded = json_decode( $body, true );

		if ( empty( $decoded ) || empty( $decoded['payload'] ) ) {
			return new WP_REST_Response( null, 204 );
		}

		// Nonce validace (volitelné – beacon je anonymní, ale s nonce)
		// Nepožadujeme nonce jako mandatory – crawler může odeslat bez kontextu
		$payload = json_decode( $decoded['payload'], true );
		if ( ! is_array( $payload ) || empty( $payload['path'] ) ) {
			return new WP_REST_Response( null, 204 );
		}

		$path     = sanitize_text_field( $payload['path'] );
		$url_hash = md5( $path );

		// Rate limit: 1 snapshot per URL per 24h per IP hash
		$ip_hash       = md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		$rl_key        = 'seob_jsgap_rl_' . $url_hash . '_' . $ip_hash;
		if ( get_transient( $rl_key ) ) {
			return new WP_REST_Response( null, 204 );
		}
		set_transient( $rl_key, 1, DAY_IN_SECONDS );

		global $wpdb;
		$snap_table = SEOB_Database::js_gap_snapshots_table();

		// Sanitize headings array – každý item jako text (ochrana před XSS v admin UI)
		$raw_headings = array_slice( (array) ( $payload['headings'] ?? [] ), 0, 30 );
		$headings     = array_map( static fn( $h ) => mb_substr( sanitize_text_field( (string) $h ), 0, 200 ), $raw_headings );

		$wpdb->replace( $snap_table, [
			'url_hash'         => $url_hash,
			'path'             => $path,
			'title'            => mb_substr( sanitize_text_field( $payload['title'] ?? '' ), 0, 300 ),
			'h1'               => mb_substr( sanitize_text_field( implode( ' | ', (array) ( $payload['h1'] ?? [] ) ) ), 0, 300 ),
			'headings_json'    => wp_json_encode( array_values( $headings ) ),
			'meta_desc'        => mb_substr( sanitize_text_field( $payload['meta_desc'] ?? '' ), 0, 500 ),
			'json_ld_count'    => max( 0, min( 100, (int) ( $payload['json_ld_count'] ?? 0 ) ) ),
			'text_len'         => max( 0, min( 1000000, (int) ( $payload['text_len'] ?? 0 ) ) ),
			'links_count'      => max( 0, min( 10000, (int) ( $payload['links_count'] ?? 0 ) ) ),
			'received_at'      => current_time( 'mysql', true ),
		] );

		return new WP_REST_Response( null, 204 );
	}
}
