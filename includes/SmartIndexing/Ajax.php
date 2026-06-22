<?php
/**
 * AJAX endpointy pro modul Chytrá indexace (M14).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_SmartIndexing_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_smart_index_options', [ $this, 'options' ] );
		add_action( 'wp_ajax_seob_smart_index_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_seob_smart_index_scan', [ $this, 'scan' ] );
		add_action( 'wp_ajax_seob_smart_index_results', [ $this, 'results' ] );
		add_action( 'wp_ajax_seob_smart_index_approve', [ $this, 'approve' ] );
		add_action( 'wp_ajax_seob_smart_index_demote', [ $this, 'demote' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	/**
	 * Vrátí veřejné typy obsahu a taxonomie pro mapování v nastavení.
	 */
	public function options(): void {
		$this->check_request();

		$post_types = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $post_type ) {
			$post_types[] = [
				'value' => $post_type->name,
				'label' => $post_type->labels->name,
			];
		}

		$taxonomies = [];
		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy ) {
			$taxonomies[] = [
				'value' => $taxonomy->name,
				'label' => $taxonomy->labels->name,
			];
		}

		wp_send_json_success( [
			'post_types' => $post_types,
			'taxonomies' => $taxonomies,
		] );
	}

	public function save_settings(): void {
		$this->check_request();

		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'dry_run';
		$profile = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : 'catalog';

		$settings = [
			'profile'                => in_array( $profile, [ 'catalog', 'eshop' ], true ) ? $profile : 'catalog',
			'mode'                   => in_array( $mode, [ 'dry_run', 'semi_auto', 'auto' ], true ) ? $mode : 'dry_run',
			'company_post_type'      => $this->key_field( 'company_post_type' ),
			'category_taxonomy'      => $this->key_field( 'category_taxonomy' ),
			'location_taxonomy'      => $this->key_field( 'location_taxonomy' ),
			'service_taxonomy'       => $this->key_field( 'service_taxonomy' ),
			'min_companies'          => $this->int_field( 'min_companies', 1, 1000, 5 ),
			'completeness_threshold' => $this->int_field( 'completeness_threshold', 0, 100, 60 ),
			'max_depth'              => $this->int_field( 'max_depth', 1, 4, 2 ),
			'blacklist_params'       => $this->text_field( 'blacklist_params' ),
		];

		SEOB_Settings::update( SEOB_Settings::SMART_INDEXING, $settings );

		wp_send_json_success( [ 'settings' => $settings ] );
	}

	/**
	 * Spustí analýzu katalogových kombinací (dry-run – jen zapíše návrhy do DB).
	 */
	public function scan(): void {
		$this->check_request();

		$scanner = new SEOB_SmartIndexing_Catalog_Scanner();
		$results = $scanner->run_scan();

		wp_send_json_success( [
			'scanned' => count( $results ),
			'results' => $this->fetch_results(),
		] );
	}

	public function results(): void {
		$this->check_request();

		wp_send_json_success( [ 'results' => $this->fetch_results() ] );
	}

	/**
	 * Schválí kandidáta → Tier A (povýšení), uloženo jako ruční rozhodnutí.
	 */
	public function approve(): void {
		$this->check_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný záznam.', 'seo-boost' ) ], 400 );
		}

		global $wpdb;
		$table = SEOB_Database::facet_urls_table();

		$wpdb->update(
			$table,
			[
				'tier'        => 'A',
				'tier_reason' => 'approved_manual',
				'promoted_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);

		wp_send_json_success( [ 'results' => $this->fetch_results() ] );
	}

	/**
	 * Vrátí kandidáta/Tier A zpět do Tier B (noindex), uloženo jako ruční rozhodnutí.
	 */
	public function demote(): void {
		$this->check_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný záznam.', 'seo-boost' ) ], 400 );
		}

		global $wpdb;
		$table = SEOB_Database::facet_urls_table();

		$wpdb->update(
			$table,
			[
				'tier'        => 'B',
				'tier_reason' => 'demoted_manual',
				'demoted_at'  => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);

		wp_send_json_success( [ 'results' => $this->fetch_results() ] );
	}

	private function fetch_results(): array {
		global $wpdb;
		$table = SEOB_Database::facet_urls_table();

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY tier ASC, score DESC, id ASC LIMIT 500" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$results = [];

		foreach ( $rows as $row ) {
			$results[] = [
				'id'             => (int) $row->id,
				'url'            => $row->url_normalized,
				'page_type'      => $row->page_type,
				'dimensions'     => json_decode( $row->dimensions_json, true ),
				'tier'           => $row->tier,
				'tier_reason'    => $row->tier_reason,
				'score'          => null !== $row->score ? (int) $row->score : null,
				'result_count'   => null !== $row->result_count ? (int) $row->result_count : null,
				'scanned_at'     => $row->scanned_at,
			];
		}

		return $results;
	}

	private function key_field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_key( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	private function text_field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	private function int_field( string $name, int $min, int $max, int $default ): int {
		$value = isset( $_POST[ $name ] ) ? absint( $_POST[ $name ] ) : $default;

		return max( $min, min( $max, $value ) );
	}
}
