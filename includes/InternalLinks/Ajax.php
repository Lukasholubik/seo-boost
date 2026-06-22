<?php
/**
 * AJAX endpointy pro modul Interní prolinkování.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_links_start', [ $this, 'start' ] );
		add_action( 'wp_ajax_seob_links_batch', [ $this, 'batch' ] );
		add_action( 'wp_ajax_seob_links_results', [ $this, 'results' ] );
		add_action( 'wp_ajax_seob_links_history', [ $this, 'history' ] );
		add_action( 'wp_ajax_seob_links_active', [ $this, 'active' ] );
		add_action( 'wp_ajax_seob_links_find',   [ $this, 'find' ] );
		add_action( 'wp_ajax_seob_links_insert', [ $this, 'insert' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	/**
	 * Založí nový reindex a vrátí jeho ID + celkový počet položek.
	 */
	public function start(): void {
		$this->check_request();

		$runner = new SEOB_InternalLinks_ScanRunner();
		$result = $runner->start_scan();

		wp_send_json_success( $result );
	}

	/**
	 * Zpracuje jednu dávku reindexu.
	 */
	public function batch(): void {
		$this->check_request();

		$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

		if ( $run_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		$runner = new SEOB_InternalLinks_ScanRunner();
		$result = $runner->process_batch( $run_id );

		wp_send_json_success( $result );
	}

	/**
	 * Vrátí souhrn pro dashboard – osamocené stránky, návrhy, tabulku stránek.
	 */
	public function results(): void {
		$this->check_request();

		$runner = new SEOB_InternalLinks_ScanRunner();

		wp_send_json_success( [ 'result' => $runner->get_results() ] );
	}

	/**
	 * Vrátí historii dokončených reindexů.
	 */
	public function history(): void {
		$this->check_request();

		$runner = new SEOB_InternalLinks_ScanRunner();

		wp_send_json_success( [ 'runs' => $runner->get_scan_history() ] );
	}

	/**
	 * Vrátí aktuálně běžící reindex (pokud existuje), aby šel obnovit progress bar po znovunačtení stránky.
	 */
	public function active(): void {
		$this->check_request();

		$runner = new SEOB_InternalLinks_ScanRunner();

		wp_send_json_success( [ 'run' => $runner->get_active_run() ] );
	}

	/**
	 * Najde kandidáty pro prolinkování a vrátí je s kontextovým výňatkem.
	 * Volá se z metaboxu při kliknutí „Najít návrhy linků".
	 */
	public function find(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný příspěvek.', 'seo-boost' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$post   = get_post( $post_id );
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		$inserter = new SEOB_InternalLinks_LinkInserter();
		$found    = $post ? $inserter->find_with_context( $post, 10, $offset ) : [ 'candidates' => [], 'has_more' => false ];

		wp_send_json_success( [
			'is_elementor' => $inserter->is_elementor( $post_id ),
			'candidates'   => $found['candidates'],
			'has_more'     => $found['has_more'],
			'offset'       => $offset,
		] );
	}

	/**
	 * Vloží interní odkazů do příspěvku (volá se z metaboxu editoru).
	 * Při Elementor stránce bez ?force=1 vrátí is_elementor=true (varování, čeká na potvrzení).
	 * Přijímá volitelný parametr target_ids (JSON pole) pro vložení jen vybraných kandidátů.
	 */
	public function insert(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný příspěvek.', 'seo-boost' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$inserter = new SEOB_InternalLinks_LinkInserter();

		// Elementor stránky: upozornit uživatele a vyžádat explicitní potvrzení (force=1)
		if ( $inserter->is_elementor( $post_id ) && empty( $_POST['force'] ) ) {
			wp_send_json_success( [ 'is_elementor' => true ] );
		}

		// Výběr konkrétních target ID (pokud uživatel odfiltroval část kandidátů)
		$only_ids = [];
		if ( ! empty( $_POST['target_ids'] ) ) {
			$decoded = json_decode( wp_unslash( sanitize_text_field( wp_unslash( $_POST['target_ids'] ) ) ), true );
			if ( is_array( $decoded ) ) {
				$only_ids = array_map( 'absint', $decoded );
				$only_ids = array_filter( $only_ids );
			}
		}

		// Atributy vložených odkazů (nofollow, new_window)
		$options = [
			'new_window' => isset( $_POST['new_window'] ) && '1' === $_POST['new_window'],
			'nofollow'   => isset( $_POST['nofollow'] )   && '1' === $_POST['nofollow'],
		];

		$result = $inserter->insert( $post_id, $only_ids, $options );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		// Čerstvé počty odkazů z DB (Indexer aktualizoval tabulku přes save_post při wp_update_post).
		if ( ! empty( $result['inserted'] ) ) {
			global $wpdb;
			$lt = SEOB_Database::internal_links_table();
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$result['fresh_inlinks']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$lt} WHERE target_id = %d", $post_id ) );
			$result['fresh_outlinks'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$lt} WHERE source_id = %d", $post_id ) );
			// phpcs:enable
		}

		$result['is_elementor'] = false;
		wp_send_json_success( $result );
	}
}
