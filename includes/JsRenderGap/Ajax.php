<?php
/**
 * Admin AJAX handlery pro JS Render Gap modul.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_JsGap_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_seob_jsgap_stats',        [ $this, 'ajax_stats' ] );
		add_action( 'wp_ajax_seob_jsgap_results',      [ $this, 'ajax_results' ] );
		add_action( 'wp_ajax_seob_jsgap_analyze_one',  [ $this, 'ajax_analyze_one' ] );
		add_action( 'wp_ajax_seob_jsgap_run_scan',     [ $this, 'ajax_run_scan' ] );
		add_action( 'wp_ajax_seob_jsgap_scan_status',  [ $this, 'ajax_scan_status' ] );
	}

	// ── Statistiky ────────────────────────────────────────────────────────────

	public function ajax_stats(): void {
		$this->auth();
		global $wpdb;

		$snap_table   = SEOB_Database::js_gap_snapshots_table();
		$result_table = SEOB_Database::js_gap_results_table();

		$total_snaps    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$snap_table}" );
		$total_analyzed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$result_table}" );
		$critical       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$result_table} WHERE gap_score >= 50" );
		$warning        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$result_table} WHERE gap_score >= 20 AND gap_score < 50" );
		$ok             = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$result_table} WHERE gap_score < 20" );
		$avg_score      = (float) $wpdb->get_var( "SELECT AVG(gap_score) FROM {$result_table}" );
		$last_snap      = $wpdb->get_var( "SELECT MAX(received_at) FROM {$snap_table}" );
		$last_analyzed  = $wpdb->get_var( "SELECT MAX(analyzed_at) FROM {$result_table}" );
		$snaps_24h      = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$snap_table} WHERE received_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ) )
		);

		wp_send_json_success( compact(
			'total_snaps', 'total_analyzed', 'critical', 'warning', 'ok',
			'avg_score', 'last_snap', 'last_analyzed', 'snaps_24h'
		) );
	}

	// ── Výsledky ─────────────────────────────────────────────────────────────

	public function ajax_results(): void {
		$this->auth();
		global $wpdb;

		$page   = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$limit  = 20;
		$offset = ( $page - 1 ) * $limit;
		$filter = sanitize_key( $_POST['filter'] ?? '' ); // critical | warning | ok | all

		$where = match ( $filter ) {
			'critical' => 'WHERE gap_score >= 50',
			'warning'  => 'WHERE gap_score >= 20 AND gap_score < 50',
			'ok'       => 'WHERE gap_score < 20',
			default    => '',
		};

		$t = SEOB_Database::js_gap_results_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url_hash, path, gap_score, issues_json,
				        rendered_h1, raw_h1,
				        rendered_json_ld_count, raw_json_ld_count,
				        rendered_text_len, raw_text_len,
				        analyzed_at
				 FROM {$t} {$where}
				 ORDER BY gap_score DESC
				 LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$where}" );

		// Decode issues
		foreach ( $rows as &$row ) {
			$row['issues'] = json_decode( $row['issues_json'] ?? '[]', true ) ?: [];
			unset( $row['issues_json'] );
		}
		unset( $row );

		wp_send_json_success( [
			'rows'  => $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $limit ),
			'page'  => $page,
		] );
	}

	// ── On-demand analýza jedné URL ───────────────────────────────────────────

	public function ajax_analyze_one(): void {
		$this->auth();
		$url_hash = sanitize_key( $_POST['url_hash'] ?? '' );
		if ( ! $url_hash ) wp_send_json_error( [ 'message' => 'Chybí url_hash.' ] );

		$result = SEOB_JsGap_ScanRunner::analyze_one( $url_hash );
		if ( null === $result ) {
			wp_send_json_error( [ 'message' => 'Snapshot nenalezen nebo raw fetch selhal.' ] );
		}
		$result['issues'] = json_decode( $result['issues_json'] ?? '[]', true ) ?: [];
		wp_send_json_success( $result );
	}

	// ── Synchronní dávkové zpracování (voláno přímo z UI) ────────────────────

	public function ajax_run_scan(): void {
		$this->auth();
		global $wpdb;

		$snap_table   = SEOB_Database::js_gap_snapshots_table();
		$result_table = SEOB_Database::js_gap_results_table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$snap_table}" );

		if ( $total === 0 ) {
			wp_send_json_success( [
				'processed' => 0,
				'total'     => 0,
				'analyzed'  => 0,
				'remaining' => 0,
				'percent'   => 100,
				'done'      => true,
				'message'   => 'Žádné snapshoty k analýze. Navštivte nejprve několik stránek webu.',
			] );
		}

		// Zpracuj dávku 3 URL synchronně
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM {$snap_table} s
				 LEFT JOIN {$result_table} r ON s.url_hash = r.url_hash
				 WHERE r.url_hash IS NULL
				    OR r.analyzed_at < %s
				 ORDER BY s.received_at DESC
				 LIMIT 3",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			),
			ARRAY_A
		);

		$processed = 0;
		foreach ( $rows as $snap ) {
			$result = SEOB_JsGap_Comparator::analyze( $snap );

			// Uložit i při null – prázdný placeholder zabrání nekonečné smyčce
			// (URL by jinak zůstala v unanalyzed listu navždy)
			$wpdb->replace( $result_table, array_merge(
				[
					'url_hash'               => $snap['url_hash'],
					'path'                   => $snap['path'],
					'rendered_title'         => $snap['title'] ?? '',
					'rendered_h1'            => $snap['h1'] ?? '',
					'rendered_meta_desc'     => $snap['meta_desc'] ?? '',
					'rendered_json_ld_count' => (int) ( $snap['json_ld_count'] ?? 0 ),
					'rendered_text_len'      => (int) ( $snap['text_len'] ?? 0 ),
					'rendered_links_count'   => (int) ( $snap['links_count'] ?? 0 ),
					'analyzed_at'            => current_time( 'mysql', true ),
				],
				$result ?? [
					'gap_score'   => 0,
					'issues_json' => '[]',
					'raw_title'   => '',
					'raw_h1'      => '',
					'raw_meta_desc'     => '',
					'raw_json_ld_count' => 0,
					'raw_text_len'      => 0,
					'raw_links_count'   => 0,
				]
			) );
			$processed++;
		}

		if ( $processed > 0 ) {
			SEOB_JsGap_ScanRunner::record_metrics();
		}

		$analyzed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$result_table}" );
		$remaining = max( 0, $total - $analyzed );
		$percent   = (int) round( ( $analyzed / $total ) * 100 );

		wp_send_json_success( [
			'processed' => $processed,
			'total'     => $total,
			'analyzed'  => $analyzed,
			'remaining' => $remaining,
			'percent'   => min( 100, $percent ),
			'done'      => $remaining === 0,
		] );
	}

	// ── Auth ──────────────────────────────────────────────────────────────────

	private function auth(): void {
		check_ajax_referer( 'seob_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );
	}
}
