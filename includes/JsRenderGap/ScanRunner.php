<?php
/**
 * Dávkové porovnání snapshotů s raw HTML – cron (1× týdně) + on-demand.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_JsGap_ScanRunner {

	const CRON_HOOK        = 'seob_js_gap_scan';
	const BATCH_SIZE       = 10;
	const RUNNING_TRANSIENT = 'seob_jsgap_running';

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Pondělí 03:30 UTC
			$next_monday = strtotime( 'next monday 03:30:00 UTC' );
			wp_schedule_event( $next_monday, 'weekly', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback – zpracuje dávku nevyanalyzovaných snapshotů.
	 */
	public static function run_batch(): void {
		set_transient( self::RUNNING_TRANSIENT, true, 5 * MINUTE_IN_SECONDS );
		global $wpdb;
		$snap_table   = SEOB_Database::js_gap_snapshots_table();
		$result_table = SEOB_Database::js_gap_results_table();

		// Vezmi snapshoty bez výsledku nebo s výsledkem starším než 7 dní
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM {$snap_table} s
				 LEFT JOIN {$result_table} r ON s.url_hash = r.url_hash
				 WHERE r.url_hash IS NULL
				    OR r.analyzed_at < %s
				 ORDER BY s.received_at DESC
				 LIMIT %d",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
				self::BATCH_SIZE
			),
			ARRAY_A
		);

		foreach ( $rows as $snap ) {
			$result = SEOB_JsGap_Comparator::analyze( $snap );
			if ( null === $result ) continue;

			$wpdb->replace( $result_table, array_merge(
				[
					'url_hash'    => $snap['url_hash'],
					'path'        => $snap['path'],
					'rendered_title'      => $snap['title'],
					'rendered_h1'         => $snap['h1'],
					'rendered_meta_desc'  => $snap['meta_desc'],
					'rendered_json_ld_count' => (int) $snap['json_ld_count'],
					'rendered_text_len'   => (int) $snap['text_len'],
					'rendered_links_count'=> (int) $snap['links_count'],
					'analyzed_at'         => current_time( 'mysql', true ),
				],
				$result
			) );
		}

		delete_transient( self::RUNNING_TRANSIENT );
		self::record_metrics();
	}

	/**
	 * On-demand analýza jedné URL ze snapshotů.
	 */
	public static function analyze_one( string $url_hash ): ?array {
		global $wpdb;
		$snap_table   = SEOB_Database::js_gap_snapshots_table();
		$result_table = SEOB_Database::js_gap_results_table();

		$snap = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$snap_table} WHERE url_hash = %s", $url_hash ),
			ARRAY_A
		);
		if ( ! $snap ) return null;

		$result = SEOB_JsGap_Comparator::analyze( $snap );
		if ( null === $result ) return null;

		$row = array_merge(
			[
				'url_hash'               => $snap['url_hash'],
				'path'                   => $snap['path'],
				'rendered_title'         => $snap['title'],
				'rendered_h1'            => $snap['h1'],
				'rendered_meta_desc'     => $snap['meta_desc'],
				'rendered_json_ld_count' => (int) $snap['json_ld_count'],
				'rendered_text_len'      => (int) $snap['text_len'],
				'rendered_links_count'   => (int) $snap['links_count'],
				'analyzed_at'            => current_time( 'mysql', true ),
			],
			$result
		);
		$wpdb->replace( $result_table, $row );

		return $row;
	}

	public static function record_metrics(): void {
		global $wpdb;
		$t = SEOB_Database::js_gap_results_table();

		$pages_with_gap = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE gap_score > 20" );
		$avg_gap        = (float) $wpdb->get_var( "SELECT AVG(gap_score) FROM {$t}" );

		SEOB_Metrics::record( 'js-render-gap', 'pages_with_gap', (float) $pages_with_gap );
		SEOB_Metrics::record( 'js-render-gap', 'avg_gap_score',  round( $avg_gap, 1 ) );
	}
}
