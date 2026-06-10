<?php
/**
 * AJAX endpointy pro Redirect Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Redirects_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_redirect_list', [ $this, 'list_redirects' ] );
		add_action( 'wp_ajax_seob_redirect_save', [ $this, 'save_redirect' ] );
		add_action( 'wp_ajax_seob_redirect_delete', [ $this, 'delete_redirect' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	public function list_redirects(): void {
		$this->check_request();

		global $wpdb;

		$links_table = SEOB_Database::links_table();

		$redirects = $wpdb->get_results(
			"SELECT * FROM {$links_table} WHERE redirect_to IS NOT NULL AND redirect_to != '' ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$not_found = $wpdb->get_results(
			"SELECT * FROM {$links_table} WHERE (redirect_to IS NULL OR redirect_to = '') AND hits_404 > 0 ORDER BY hits_404 DESC, last_checked DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		wp_send_json_success( [
			'redirects' => $redirects,
			'not_found' => $not_found,
		] );
	}

	public function save_redirect(): void {
		$this->check_request();

		$target_url  = isset( $_POST['target_url'] ) ? wp_unslash( $_POST['target_url'] ) : '';
		$redirect_to = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '';
		$id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$target_path = $this->normalize_path( $target_url );

		if ( null === $target_path ) {
			wp_send_json_error( [ 'message' => __( 'Zadejte platnou cestu (např. /stara-stranka/).', 'seo-boost' ) ], 400 );
		}

		$redirect_target = $this->validate_redirect_target( $redirect_to );

		if ( null === $redirect_target ) {
			wp_send_json_error( [ 'message' => __( 'Cílová adresa není platná nebo míří mimo tento web.', 'seo-boost' ) ], 400 );
		}

		if ( $redirect_target === $target_path ) {
			wp_send_json_error( [ 'message' => __( 'Cíl přesměrování nemůže být stejný jako zdroj.', 'seo-boost' ) ], 400 );
		}

		global $wpdb;
		$links_table = SEOB_Database::links_table();

		$existing_id = $id;

		if ( ! $existing_id ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$links_table} WHERE target_url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$target_path
				)
			);
		}

		if ( $existing_id ) {
			$wpdb->update(
				$links_table,
				[
					'target_url'  => $target_path,
					'redirect_to' => $redirect_target,
					'link_type'   => 'internal',
					'http_status' => 301,
				],
				[ 'id' => $existing_id ],
				[ '%s', '%s', '%s', '%d' ],
				[ '%d' ]
			);
		} else {
			$wpdb->insert(
				$links_table,
				[
					'target_url'  => $target_path,
					'redirect_to' => $redirect_target,
					'link_type'   => 'internal',
					'http_status' => 301,
					'hits_404'    => 0,
				],
				[ '%s', '%s', '%s', '%d', '%d' ]
			);
		}

		SEOB_Redirect_Manager::invalidate_cache();

		wp_send_json_success( [
			'target_url'  => $target_path,
			'redirect_to' => $redirect_target,
		] );
	}

	public function delete_redirect(): void {
		$this->check_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Neplatné ID.', 'seo-boost' ) ], 400 );
		}

		global $wpdb;

		$wpdb->delete( SEOB_Database::links_table(), [ 'id' => $id ], [ '%d' ] );

		SEOB_Redirect_Manager::invalidate_cache();

		wp_send_json_success();
	}

	/**
	 * Normalizuje vstupní cestu na tvar "/cesta" bez koncového lomítka.
	 */
	private function normalize_path( string $url ): ?string {
		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? $path : $url;
		$path = '/' . ltrim( $path, '/' );

		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}

		return $path;
	}

	/**
	 * Ověří cíl přesměrování – musí být relativní cesta nebo URL na tomto webu.
	 */
	private function validate_redirect_target( string $url ): ?string {
		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		// Relativní cesta – normalizuj a povol.
		if ( '/' === substr( $url, 0, 1 ) ) {
			$path = '/' . ltrim( $url, '/' );

			if ( strlen( $path ) > 1 ) {
				$path = rtrim( $path, '/' );
			}

			return $path;
		}

		$sanitized = esc_url_raw( $url );

		if ( '' === $sanitized ) {
			return null;
		}

		$validated = wp_validate_redirect( $sanitized, '' );

		return '' !== $validated ? $validated : null;
	}
}
