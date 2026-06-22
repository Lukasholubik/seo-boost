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
		add_action( 'wp_ajax_seob_redirect_list',       [ $this, 'list_redirects' ] );
		add_action( 'wp_ajax_seob_redirect_save',       [ $this, 'save_redirect' ] );
		add_action( 'wp_ajax_seob_redirect_delete',     [ $this, 'delete_redirect' ] );
		add_action( 'wp_ajax_seob_redirect_import_csv', [ $this, 'import_csv' ] );
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
	 * Hromadný import přesměrování z CSV souboru.
	 *
	 * Formát CSV (oddělovač čárka nebo středník, volitelná hlavička):
	 *   source,target
	 *   /stara-stranka/,/nova-stranka/
	 *   /jina-stranka/,https://externi-web.cz/
	 *
	 * Vrátí: { created, updated, skipped, errors[] }
	 */
	public function import_csv(): void {
		$this->check_request();

		if ( empty( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['csv_file']['error'] ) {
			wp_send_json_error( [ 'message' => __( 'Soubor nebyl nahrán.', 'seo-boost' ) ], 400 );
		}

		$file = $_FILES['csv_file'];

		// Validace MIME – povoleny pouze text/csv a text/plain.
		$allowed_types = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ];
		$mime          = mime_content_type( $file['tmp_name'] );
		if ( ! in_array( $mime, $allowed_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Nahrajte prosím soubor CSV.', 'seo-boost' ) ], 400 );
		}

		// Maximální velikost 2 MB.
		if ( $file['size'] > 2 * 1024 * 1024 ) {
			wp_send_json_error( [ 'message' => __( 'Soubor je příliš velký (max 2 MB).', 'seo-boost' ) ], 400 );
		}

		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			wp_send_json_error( [ 'message' => __( 'Soubor nelze otevřít.', 'seo-boost' ) ], 500 );
		}

		// Auto-detekce oddělovače (čárka vs středník).
		$first_line = fgets( $handle );
		rewind( $handle );
		$delimiter = ( substr_count( $first_line, ';' ) >= substr_count( $first_line, ',' ) ) ? ';' : ',';

		// Přeskoč hlavičkový řádek pokud první buňka vypadá jako header (source/zdroj apod.).
		$first_row = str_getcsv( $first_line, $delimiter );
		$is_header = isset( $first_row[0] ) && in_array(
			strtolower( trim( $first_row[0] ) ),
			[ 'source', 'zdroj', 'from', 'od', 'url', 'target', 'cil', 'to', 'redirect' ],
			true
		);
		if ( $is_header ) {
			fgets( $handle ); // přeskoč hlavičku
		}

		global $wpdb;
		$links_table = SEOB_Database::links_table();

		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = [];
		$line_num = $is_header ? 1 : 0;

		while ( ( $row = fgetcsv( $handle, 1000, $delimiter ) ) !== false ) {
			$line_num++;

			if ( count( $row ) < 2 ) {
				$errors[] = sprintf( __( 'Řádek %d: méně než 2 sloupce (přeskočeno).', 'seo-boost' ), $line_num );
				$skipped++;
				continue;
			}

			$source = $this->normalize_path( $row[0] );
			$target = $this->validate_redirect_target( $row[1] );

			if ( null === $source ) {
				$errors[] = sprintf( __( 'Řádek %d: neplatná zdrojová cesta „%s".', 'seo-boost' ), $line_num, esc_html( substr( $row[0], 0, 60 ) ) );
				$skipped++;
				continue;
			}

			if ( null === $target ) {
				$errors[] = sprintf( __( 'Řádek %d: neplatný cíl přesměrování „%s".', 'seo-boost' ), $line_num, esc_html( substr( $row[1], 0, 60 ) ) );
				$skipped++;
				continue;
			}

			if ( $source === $target ) {
				$errors[] = sprintf( __( 'Řádek %d: zdroj a cíl jsou stejné (%s).', 'seo-boost' ), $line_num, esc_html( $source ) );
				$skipped++;
				continue;
			}

			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$links_table} WHERE target_url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$source
				)
			);

			if ( $existing_id ) {
				$wpdb->update(
					$links_table,
					[ 'redirect_to' => $target, 'link_type' => 'internal', 'http_status' => 301 ],
					[ 'id' => $existing_id ],
					[ '%s', '%s', '%d' ],
					[ '%d' ]
				);
				$updated++;
			} else {
				$wpdb->insert(
					$links_table,
					[ 'target_url' => $source, 'redirect_to' => $target, 'link_type' => 'internal', 'http_status' => 301, 'hits_404' => 0 ],
					[ '%s', '%s', '%s', '%d', '%d' ]
				);
				$created++;
			}
		}

		fclose( $handle );
		SEOB_Redirect_Manager::invalidate_cache();

		wp_send_json_success( compact( 'created', 'updated', 'skipped', 'errors' ) );
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
