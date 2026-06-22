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
		add_action( 'wp_ajax_seob_redirect_list',        [ $this, 'list_redirects' ] );
		add_action( 'wp_ajax_seob_redirect_save',        [ $this, 'save_redirect' ] );
		add_action( 'wp_ajax_seob_redirect_delete',      [ $this, 'delete_redirect' ] );
		add_action( 'wp_ajax_seob_redirect_preview_csv', [ $this, 'preview_csv' ] );
		add_action( 'wp_ajax_seob_redirect_import_csv',  [ $this, 'import_csv' ] );
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

	// ── CSV Import / Preview ─────────────────────────────────────────────────

	/**
	 * Náhled CSV bez uložení — vrátí parsované řádky pro kontrolu párování.
	 * Vrátí: { rows: [{line, source, target, valid, error}], delimiter, total }
	 */
	public function preview_csv(): void {
		$this->check_request();

		$parsed = $this->open_and_parse_csv();
		if ( is_string( $parsed ) ) {
			wp_send_json_error( [ 'message' => $parsed ], 400 );
		}

		wp_send_json_success( [
			'rows'      => array_slice( $parsed['rows'], 0, 500 ), // max 500 v náhledu
			'delimiter' => $parsed['delimiter'],
			'total'     => count( $parsed['rows'] ),
		] );
	}

	/**
	 * Hromadný import přesměrování z CSV.
	 * POST: csv_file (file), http_code (int: 301|302|307|308)
	 * Vrátí: { created, updated, skipped, errors[] }
	 */
	public function import_csv(): void {
		$this->check_request();

		// Typ přesměrování – whitelist: 301, 302, 307, 308.
		$allowed_codes = [ 301, 302, 307, 308 ];
		$http_code     = isset( $_POST['http_code'] ) ? absint( $_POST['http_code'] ) : 301;
		if ( ! in_array( $http_code, $allowed_codes, true ) ) {
			$http_code = 301;
		}

		$parsed = $this->open_and_parse_csv();
		if ( is_string( $parsed ) ) {
			wp_send_json_error( [ 'message' => $parsed ], 400 );
		}

		global $wpdb;
		$links_table = SEOB_Database::links_table();

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = [];

		foreach ( $parsed['rows'] as $r ) {
			if ( ! $r['valid'] ) {
				$errors[] = sprintf( 'Řádek %d: %s', $r['line'], $r['error'] );
				$skipped++;
				continue;
			}

			$source = $r['source'];
			$target = $r['target'];

			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$links_table} WHERE target_url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$source
				)
			);

			if ( $existing_id ) {
				$wpdb->update(
					$links_table,
					[ 'redirect_to' => $target, 'link_type' => 'internal', 'http_status' => $http_code ],
					[ 'id' => $existing_id ],
					[ '%s', '%s', '%d' ],
					[ '%d' ]
				);
				$updated++;
			} else {
				$wpdb->insert(
					$links_table,
					[ 'target_url' => $source, 'redirect_to' => $target, 'link_type' => 'internal', 'http_status' => $http_code, 'hits_404' => 0 ],
					[ '%s', '%s', '%s', '%d', '%d' ]
				);
				$created++;
			}
		}

		SEOB_Redirect_Manager::invalidate_cache();
		wp_send_json_success( compact( 'created', 'updated', 'skipped', 'errors' ) );
	}

	/**
	 * Otevře nahraný CSV soubor, parsuje řádky a vrátí pole se strukturou:
	 *   [ 'rows' => [...], 'delimiter' => ',' ]
	 * nebo string s chybovou hláškou.
	 *
	 * Každý řádek: { line, source, target, raw_source, raw_target, valid, error }
	 */
	private function open_and_parse_csv(): array|string {
		if ( empty( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['csv_file']['error'] ) {
			return __( 'Soubor nebyl nahrán.', 'seo-boost' );
		}

		$file          = $_FILES['csv_file'];
		$allowed_types = [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream' ];
		$mime          = mime_content_type( $file['tmp_name'] );

		if ( ! in_array( $mime, $allowed_types, true ) ) {
			return __( 'Nahrajte prosím soubor CSV (text/csv nebo text/plain).', 'seo-boost' );
		}

		if ( $file['size'] > 2 * 1024 * 1024 ) {
			return __( 'Soubor je příliš velký (max 2 MB).', 'seo-boost' );
		}

		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			return __( 'Soubor nelze otevřít.', 'seo-boost' );
		}

		// UTF-8 BOM strip.
		$bom = fread( $handle, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		// Auto-detekce oddělovače.
		$first_line = fgets( $handle );
		rewind( $handle );
		if ( $bom === "\xEF\xBB\xBF" ) {
			fread( $handle, 3 ); // skip BOM again
			fgets( $handle );    // skip first line (re-positioned)
			rewind( $handle );
			fread( $handle, 3 );
		}
		$delimiter = ( substr_count( $first_line, ';' ) >= substr_count( $first_line, ',' ) ) ? ';' : ',';

		// Rewind s BOM skip.
		rewind( $handle );
		$bom_check = fread( $handle, 3 );
		if ( $bom_check !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		// Detekce hlavičky: první řádek obsahuje textuální popis, ne URL/cestu.
		$first_row = fgetcsv( $handle, 2000, $delimiter );
		$header_words = [
			'source', 'zdroj', 'from', 'od', 'url', 'původní', 'puvodni', 'old',
			'target', 'cíl', 'cil', 'to', 'redirect', 'nové', 'nove', 'new', 'destination',
		];
		$is_header = $first_row && isset( $first_row[0] ) && in_array(
			strtolower( trim( $first_row[0] ) ),
			$header_words,
			true
		);
		if ( ! $is_header ) {
			// Vrátit ukazatel před první řádek.
			rewind( $handle );
			$bom_check = fread( $handle, 3 );
			if ( $bom_check !== "\xEF\xBB\xBF" ) {
				rewind( $handle );
			}
		}

		$rows     = [];
		$line_num = $is_header ? 1 : 0;

		while ( ( $row = fgetcsv( $handle, 2000, $delimiter ) ) !== false ) {
			$line_num++;

			// Přeskoč prázdné řádky.
			if ( empty( array_filter( $row, 'strlen' ) ) ) {
				continue;
			}

			if ( count( $row ) < 2 ) {
				$rows[] = [ 'line' => $line_num, 'source' => '', 'target' => '', 'raw_source' => $row[0] ?? '', 'raw_target' => '', 'valid' => false, 'error' => 'Méně než 2 sloupce.' ];
				continue;
			}

			$raw_src = trim( $row[0] );
			$raw_tgt = trim( $row[1] );
			$source  = $this->normalize_path( $raw_src );
			$target  = $this->normalize_redirect_target( $raw_tgt );

			if ( null === $source ) {
				$rows[] = [ 'line' => $line_num, 'source' => '', 'target' => $target ?? '', 'raw_source' => $raw_src, 'raw_target' => $raw_tgt, 'valid' => false, 'error' => 'Neplatná zdrojová cesta.' ];
				continue;
			}

			if ( null === $target ) {
				$rows[] = [ 'line' => $line_num, 'source' => $source, 'target' => '', 'raw_source' => $raw_src, 'raw_target' => $raw_tgt, 'valid' => false, 'error' => 'Neplatný cíl přesměrování.' ];
				continue;
			}

			if ( $source === $target ) {
				$rows[] = [ 'line' => $line_num, 'source' => $source, 'target' => $target, 'raw_source' => $raw_src, 'raw_target' => $raw_tgt, 'valid' => false, 'error' => 'Zdroj a cíl jsou stejné.' ];
				continue;
			}

			$rows[] = [ 'line' => $line_num, 'source' => $source, 'target' => $target, 'raw_source' => $raw_src, 'raw_target' => $raw_tgt, 'valid' => true, 'error' => '' ];
		}

		fclose( $handle );
		return [ 'rows' => $rows, 'delimiter' => $delimiter ];
	}

	/**
	 * Normalizuje zdrojovou cestu: extrahuje jen /path/ i z plné URL.
	 * https://reboost.cz/slovicek-pojmu/ → /slovicek-pojmu
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

		// Musí začínat lomítkem a obsahovat alespoň jeden znak cesty.
		if ( '/' === $path && '' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return null; // jen doména bez cesty – nepovoleno jako zdroj
		}

		return $path;
	}

	/**
	 * Normalizuje cíl přesměrování.
	 * Stejnodoménové URL (https://reboost.cz/nova/) převede na relativní cestu (/nova/).
	 * Cizí URL vrátí celé (ověřeno wp_validate_redirect).
	 */
	private function normalize_redirect_target( string $url ): ?string {
		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		// Relativní cesta.
		if ( '/' === substr( $url, 0, 1 ) ) {
			return rtrim( '/' . ltrim( $url, '/' ), '/' ) ?: '/';
		}

		$sanitized = esc_url_raw( $url );
		if ( '' === $sanitized ) {
			return null;
		}

		// Stejnodoménové URL → extrahuj jen cestu.
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$url_host  = strtolower( (string) wp_parse_url( $sanitized, PHP_URL_HOST ) );

		if ( $url_host && $home_host && $url_host === $home_host ) {
			$path  = wp_parse_url( $sanitized, PHP_URL_PATH ) ?? '/';
			$query = wp_parse_url( $sanitized, PHP_URL_QUERY );
			$path  = '/' . ltrim( (string) $path, '/' );
			return rtrim( $path, '/' ) . ( $query ? '?' . $query : '' ) ?: '/';
		}

		// Externí URL – ověřit přes WP.
		$validated = wp_validate_redirect( $sanitized, '' );
		return '' !== $validated ? $validated : $sanitized;
	}

	/**
	 * Ověří cíl přesměrování – musí být relativní cesta nebo URL na tomto webu.
	 * (Původní metoda pro manuální editaci jednoho přesměrování.)
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
