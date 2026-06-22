<?php
/**
 * AJAX handlery pro Local SEO modul.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_LocalSeo_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_seob_local_seo_save',    [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_seob_local_seo_preview',  [ $this, 'preview_schema' ] );
		add_action( 'wp_ajax_seob_local_seo_nap_scan', [ $this, 'nap_scan' ] );
	}

	private function check_nonce(): void {
		if ( ! check_ajax_referer( 'seob_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Neplatný token.' ], 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nedostatečná oprávnění.' ], 403 );
		}
	}

	// ── Uložení nastavení ─────────────────────────────────────────────────────

	public function save_settings(): void {
		$this->check_nonce();

		$hours = [];
		$days  = [ 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ];

		foreach ( $days as $day ) {
			$key = strtolower( $day );
			$hours[ $day ] = [
				'open'   => sanitize_text_field( wp_unslash( $_POST[ "hours_{$key}_open" ]  ?? '' ) ),
				'close'  => sanitize_text_field( wp_unslash( $_POST[ "hours_{$key}_close" ] ?? '' ) ),
				'closed' => isset( $_POST[ "hours_{$key}_closed" ] ) ? 1 : 0,
			];
		}

		$s = [
			'business_name'    => sanitize_text_field( wp_unslash( $_POST['business_name']    ?? '' ) ),
			'business_type'    => sanitize_text_field( wp_unslash( $_POST['business_type']    ?? 'LocalBusiness' ) ),
			'description'      => sanitize_textarea_field( wp_unslash( $_POST['description']  ?? '' ) ),
			'phone'            => sanitize_text_field( wp_unslash( $_POST['phone']            ?? '' ) ),
			'email'            => sanitize_email( wp_unslash( $_POST['email']                 ?? '' ) ),
			'address_street'   => sanitize_text_field( wp_unslash( $_POST['address_street']   ?? '' ) ),
			'address_city'     => sanitize_text_field( wp_unslash( $_POST['address_city']     ?? '' ) ),
			'address_zip'      => sanitize_text_field( wp_unslash( $_POST['address_zip']      ?? '' ) ),
			'address_country'  => sanitize_text_field( wp_unslash( $_POST['address_country']  ?? 'CZ' ) ),
			'lat'              => sanitize_text_field( wp_unslash( $_POST['lat']              ?? '' ) ),
			'lng'              => sanitize_text_field( wp_unslash( $_POST['lng']              ?? '' ) ),
			'ico'              => sanitize_text_field( wp_unslash( $_POST['ico']              ?? '' ) ),
			'dic'              => sanitize_text_field( wp_unslash( $_POST['dic']              ?? '' ) ),
			'price_range'      => sanitize_text_field( wp_unslash( $_POST['price_range']      ?? '' ) ),
			'image_url'        => esc_url_raw( wp_unslash( $_POST['image_url']               ?? '' ) ),
			'image_id'         => absint( $_POST['image_id'] ?? 0 ),
			'output_on'        => in_array( $_POST['output_on'] ?? '', [ 'homepage', 'all', 'contact' ], true )
								   ? sanitize_key( $_POST['output_on'] ) : 'homepage',
			'contact_page_id'  => absint( $_POST['contact_page_id'] ?? 0 ),
			'opening_hours'    => $hours,
		];

		SEOB_Settings::update( SEOB_Settings::LOCAL_SEO, $s );

		wp_send_json_success( [ 'message' => 'Nastavení uloženo.' ] );
	}

	// ── Náhled JSON-LD ───────────────────────────────────────────────────────

	public function preview_schema(): void {
		$this->check_nonce();

		$s = SEOB_Settings::get( SEOB_Settings::LOCAL_SEO );

		if ( empty( $s['business_name'] ) ) {
			wp_send_json_error( [ 'message' => 'Nejprve vyplňte název firmy a uložte nastavení.' ] );
		}

		$schema = SEOB_LocalSeo_Frontend::build_schema( $s );
		$json   = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		wp_send_json_success( [ 'json' => $json ] );
	}

	// ── NAP scan ─────────────────────────────────────────────────────────────

	public function nap_scan(): void {
		$this->check_nonce();

		$s = SEOB_Settings::get( SEOB_Settings::LOCAL_SEO );

		$phone   = trim( $s['phone'] ?? '' );
		$city    = trim( $s['address_city'] ?? '' );
		$name    = trim( $s['business_name'] ?? '' );

		if ( empty( $phone ) && empty( $city ) && empty( $name ) ) {
			wp_send_json_error( [ 'message' => 'Nejprve nastavte název firmy, telefon nebo město.' ] );
		}

		global $wpdb;

		$posts = $wpdb->get_results(
			"SELECT ID, post_title, post_content, post_type
			 FROM {$wpdb->posts}
			 WHERE post_status = 'publish'
			   AND post_type IN ('post', 'page')
			 ORDER BY post_type ASC, post_title ASC
			 LIMIT 200",
			ARRAY_A
		);

		$results  = [];
		$phone_stripped = $this->strip_phone( $phone );

		foreach ( $posts as $post ) {
			$plain   = wp_strip_all_tags( $post['post_content'] );
			$matches = [];

			// Telefon
			if ( ! empty( $phone ) ) {
				$found = $this->find_phone_occurrences( $plain, $phone_stripped );
				foreach ( $found as $variant ) {
					$matches[] = [ 'type' => 'phone', 'value' => $variant, 'ok' => $variant === $phone ];
				}
			}

			// Město
			if ( ! empty( $city ) && mb_stripos( $plain, $city ) !== false ) {
				$matches[] = [ 'type' => 'city', 'value' => $city, 'ok' => true ];
			}

			// Název firmy
			if ( ! empty( $name ) ) {
				if ( mb_stripos( $plain, $name ) !== false ) {
					$matches[] = [ 'type' => 'name', 'value' => $name, 'ok' => true ];
				}
			}

			if ( ! empty( $matches ) ) {
				$has_issue = false;
				foreach ( $matches as $m ) {
					if ( ! $m['ok'] ) {
						$has_issue = true;
						break;
					}
				}

				$results[] = [
					'id'        => (int) $post['ID'],
					'title'     => $post['post_title'],
					'edit_link' => get_edit_post_link( (int) $post['ID'], 'raw' ),
					'view_link' => get_permalink( (int) $post['ID'] ),
					'matches'   => $matches,
					'has_issue' => $has_issue,
				];
			}
		}

		$issues = array_filter( $results, fn( $r ) => $r['has_issue'] );

		wp_send_json_success( [
			'results'     => $results,
			'total'       => count( $results ),
			'issue_count' => count( $issues ),
		] );
	}

	/**
	 * Odstraní z telefonního čísla vše kromě číslic a znaménka +.
	 */
	private function strip_phone( string $phone ): string {
		return preg_replace( '/[^\d+]/', '', $phone );
	}

	/**
	 * Najde v textu všechny výskyty čísla (ve variantách formátování).
	 *
	 * @return string[]
	 */
	private function find_phone_occurrences( string $text, string $stripped_phone ): array {
		if ( empty( $stripped_phone ) ) {
			return [];
		}

		// Vyhledá libovolnou sekvenci číslic a oddělovačů (\s-.) která se po strippingu rovná
		preg_match_all( '/[\+\d][\d\s\-\.]{6,18}[\d]/', $text, $m );

		$found = [];
		foreach ( $m[0] as $raw ) {
			if ( $this->strip_phone( $raw ) === $stripped_phone ) {
				$found[] = trim( $raw );
			}
		}

		return array_unique( $found );
	}
}
