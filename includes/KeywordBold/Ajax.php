<?php
/**
 * KeywordBold – AJAX handlery pro admin stránku a metabox.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_KeywordBold_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_kwbold_preview_post',  [ $this, 'ajax_preview_post' ] );
		add_action( 'wp_ajax_seob_kwbold_bold_post',     [ $this, 'ajax_bold_post' ] );
		add_action( 'wp_ajax_seob_kwbold_undo_post',     [ $this, 'ajax_undo_post' ] );
		add_action( 'wp_ajax_seob_kwbold_batch',         [ $this, 'ajax_batch' ] );
		add_action( 'wp_ajax_seob_kwbold_batch_preview', [ $this, 'ajax_batch_preview' ] );
		add_action( 'wp_ajax_seob_kwbold_batch_undo',    [ $this, 'ajax_batch_undo' ] );
	}

	private function check(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nedostatečná oprávnění.' ], 403 );
			return;
		}
	}

	// ── Náhled jednoho příspěvku ──────────────────────────────────────────────

	public function ajax_preview_post(): void {
		$this->check();

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => 'Chybí post_id.' ], 400 );
			return;
		}

		$options  = $this->parse_options();
		$result   = SEOB_KeywordBold_Processor::preview( $post_id, $options );
		$post     = get_post( $post_id );
		$is_bolded = SEOB_KeywordBold_Processor::has_our_bold( $post ? $post->post_content : '' );
		$meta     = get_post_meta( $post_id, '_seob_kw_bold_applied', true );

		wp_send_json_success( array_merge( $result, [
			'post_title'    => $post ? get_the_title( $post ) : '',
			'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			'is_bolded'     => $is_bolded,
			'applied_meta'  => $meta ? json_decode( $meta, true ) : null,
		] ) );
	}

	// ── Zvýraznění jednoho příspěvku ──────────────────────────────────────────

	public function ajax_bold_post(): void {
		$this->check();

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => 'Chybí post_id.' ], 400 );
			return;
		}

		$options = $this->parse_options();
		$result  = SEOB_KeywordBold_Processor::bold_post( $post_id, $options );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	// ── Undo jednoho příspěvku ────────────────────────────────────────────────

	public function ajax_undo_post(): void {
		$this->check();

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => 'Chybí post_id.' ], 400 );
			return;
		}

		$ok = SEOB_KeywordBold_Processor::undo_post( $post_id );
		if ( $ok ) {
			wp_send_json_success( [ 'message' => 'Zvýraznění odstraněno.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Chyba při odstraňování.' ] );
		}
	}

	// ── Náhled batch (prvních 5 postů) ────────────────────────────────────────

	public function ajax_batch_preview(): void {
		$this->check();

		$post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
		$only_new  = ! empty( $_POST['only_new'] );
		$limit     = min( 50, max( 5, absint( $_POST['preview_limit'] ?? 20 ) ) );
		$offset    = absint( $_POST['preview_offset'] ?? 0 );
		$options   = $this->parse_options();

		$posts = $this->get_posts( $post_type, $limit, $offset, $only_new );
		$items = [];

		foreach ( $posts as $post_id ) {
			$result = SEOB_KeywordBold_Processor::preview( (int) $post_id, $options );
			$items[] = [
				'id'            => $post_id,
				'title'         => get_the_title( $post_id ),
				'keywords'      => $result['keywords'],
				'occurrences'   => $result['occurrences'],
				'already_bolded'=> $result['already_bolded'],
				'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			];
		}

		$total = $this->count_posts( $post_type, $only_new );

		wp_send_json_success( [
			'items'        => $items,
			'total'        => $total,
			'offset'       => $offset,
			'next_offset'  => $offset + count( $posts ),
			'has_more'     => ( $offset + count( $posts ) ) < $total,
		] );
	}

	// ── Batch zpracování ──────────────────────────────────────────────────────

	public function ajax_batch(): void {
		$this->check();

		$post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
		$offset    = absint( $_POST['offset'] ?? 0 );
		$batch_sz  = min( 20, max( 1, absint( $_POST['batch_size'] ?? 10 ) ) );
		$only_new  = ! empty( $_POST['only_new'] );
		$options   = $this->parse_options();

		$posts   = $this->get_posts( $post_type, $batch_sz, $offset, $only_new );
		$results = [];

		foreach ( $posts as $post_id ) {
			$r = SEOB_KeywordBold_Processor::bold_post( (int) $post_id, $options );
			$results[] = [
				'id'          => $post_id,
				'title'       => get_the_title( $post_id ),
				'success'     => $r['success'],
				'occurrences' => $r['occurrences'],
				'message'     => $r['message'],
				'keywords'    => $r['keywords'],
			];
		}

		$total      = $this->count_posts( $post_type, $only_new );
		$next_offset = $offset + count( $posts );
		$done        = $next_offset >= $total;

		wp_send_json_success( [
			'results'     => $results,
			'offset'      => $offset,
			'next_offset' => $next_offset,
			'total'       => $total,
			'done'        => $done,
		] );
	}

	// ── Batch undo ───────────────────────────────────────────────────────────

	/**
	 * Hromadně odstraní zvýraznění ze všech postů daného post_type.
	 * POST: post_type, offset, batch_size
	 */
	public function ajax_batch_undo(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Nedostatečná oprávnění.' ], 403 );
			return;
		}

		$post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
		$offset    = absint( $_POST['offset'] ?? 0 );
		$batch_sz  = min( 50, max( 1, absint( $_POST['batch_size'] ?? 20 ) ) );

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => $batch_sz,
			'offset'         => $offset,
			'fields'         => 'ids',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'     => '_seob_kw_bold_applied',
					'compare' => 'EXISTS',
				],
			],
		] );

		$undone = 0;
		wp_suspend_cache_invalidation( true );

		foreach ( $posts as $post_id ) {
			if ( SEOB_KeywordBold_Processor::undo_post( (int) $post_id ) ) {
				$undone++;
			}
		}

		wp_suspend_cache_invalidation( false );

		$total      = (int) ( new WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => '_seob_kw_bold_applied', 'compare' => 'EXISTS' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
		] ) )->found_posts;

		$next_offset = $offset + count( $posts );
		$done        = count( $posts ) < $batch_sz;

		wp_send_json_success( [
			'undone'      => $undone,
			'offset'      => $offset,
			'next_offset' => $next_offset,
			'total'       => $total + $undone, // celkový počet před touto dávkou
			'done'        => $done,
		] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function parse_options(): array {
		$max = absint( $_POST['max_occurrences'] ?? 1 );
		return [
			'max_occurrences' => max( 1, min( 5, $max ) ),
			'use_secondary'   => ! empty( $_POST['use_secondary'] ),
			'overwrite'       => ! empty( $_POST['overwrite'] ),
			'keywords'        => $this->parse_custom_keywords(),
		];
	}

	private function parse_custom_keywords(): array {
		$raw = sanitize_text_field( wp_unslash( $_POST['custom_keywords'] ?? '' ) );
		if ( '' === $raw ) {
			return [];
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	private function get_posts( string $post_type, int $limit, int $offset = 0, bool $only_new = false ): array {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( $only_new ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'     => '_seob_kw_bold_applied',
					'compare' => 'NOT EXISTS',
				],
			];
		}
		return get_posts( $args );
	}

	private function count_posts( string $post_type, bool $only_new = false ): int {
		if ( $only_new ) {
			$q = new WP_Query( [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
					[ 'key' => '_seob_kw_bold_applied', 'compare' => 'NOT EXISTS' ],
				],
			] );
			return (int) $q->found_posts;
		}
		$counts = wp_count_posts( $post_type );
		return (int) ( $counts->publish ?? 0 );
	}
}
