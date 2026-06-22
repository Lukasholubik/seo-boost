<?php
/**
 * AJAX handlery pro CWV RUM dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_CWV_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_seob_cwv_dashboard',        [ $this, 'ajax_dashboard' ] );
		add_action( 'wp_ajax_seob_cwv_worst_urls',       [ $this, 'ajax_worst_urls' ] );
		add_action( 'wp_ajax_seob_cwv_save_settings',    [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_seob_cwv_run_aggregation',  [ $this, 'ajax_run_aggregation' ] );
	}

	// ── Dashboard: p75 trend per day ─────────────────────────────────────────

	public function ajax_dashboard(): void {
		check_ajax_referer( 'seob_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		$metric = sanitize_key( $_POST['metric'] ?? 'LCP' );
		$device = sanitize_key( $_POST['device'] ?? '' ); // '' = oba
		$days   = min( 365, max( 7, (int) ( $_POST['days'] ?? 30 ) ) );

		global $wpdb;
		$daily_table = SEOB_Database::cwv_daily_table();

		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$device_clause = $device ? $wpdb->prepare( 'AND device = %s', $device ) : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, device, p75, sample_count
				 FROM {$daily_table}
				 WHERE url_hash = '*' AND metric = %s
				   AND day >= %s
				   {$device_clause}
				 ORDER BY day ASC, device ASC",
				$metric,
				$since
			)
		);

		// Sestavit labels + series
		$labels         = [];
		$data_mobile    = [];
		$data_desktop   = [];
		$all_days       = [];

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$all_days[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
		}

		$indexed = [];
		foreach ( $rows as $row ) {
			$indexed[ $row->day ][ $row->device ] = [
				'p75'     => (float) $row->p75,
				'samples' => (int) $row->sample_count,
			];
		}

		foreach ( $all_days as $day ) {
			$labels[]       = substr( $day, 5 ); // MM-DD
			$data_mobile[]  = $indexed[ $day ]['mobile']['p75']  ?? null;
			$data_desktop[] = $indexed[ $day ]['desktop']['p75'] ?? null;
		}

		// Statistika: počet vzorků za posledních 24h
		$raw_table = SEOB_Database::cwv_raw_table();
		$since_24h = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$samples_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$raw_table} WHERE recorded_at >= %s",
				$since_24h
			)
		);

		$last_agg = (int) get_option( 'seob_cwv_last_aggregation', 0 );

		wp_send_json_success( [
			'labels'         => $labels,
			'mobile'         => $data_mobile,
			'desktop'        => $data_desktop,
			'metric'         => $metric,
			'samples_24h'    => $samples_24h,
			'last_agg'       => $last_agg ? date_i18n( 'Y-m-d H:i', $last_agg ) : null,
		] );
	}

	// ── Worst URLs ───────────────────────────────────────────────────────────

	public function ajax_worst_urls(): void {
		check_ajax_referer( 'seob_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		$metric = sanitize_key( $_POST['metric'] ?? 'LCP' );
		$device = sanitize_key( $_POST['device'] ?? '' );
		$days   = min( 90, max( 7, (int) ( $_POST['days'] ?? 30 ) ) );
		$limit  = min( 50, max( 5, (int) ( $_POST['limit'] ?? 20 ) ) );

		global $wpdb;
		$daily_table = SEOB_Database::cwv_daily_table();
		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Pokud není zadáno zařízení, zobraz nejhorší URL přes všechna zařízení
		$device_clause = $device ? $wpdb->prepare( 'AND device = %s', $device ) : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT path, MAX(p75) AS avg_p75, SUM(sample_count) AS total_samples,
				        MAX(CASE WHEN p75 > %f THEN 'poor'
				                 WHEN p75 > %f THEN 'needs-improvement'
				                 ELSE 'good' END) AS rating
				 FROM {$daily_table}
				 WHERE metric = %s
				   AND day >= %s AND url_hash != '*'
				   {$device_clause}
				 GROUP BY url_hash, path
				 HAVING total_samples >= 1
				 ORDER BY avg_p75 DESC
				 LIMIT %d",
				self::poor_threshold( $metric ),
				self::ni_threshold( $metric ),
				$metric,
				$since,
				$limit
			)
		);

		$result = [];
		foreach ( $rows as $row ) {
			$result[] = [
				'path'    => $row->path,
				'p75'     => round( (float) $row->avg_p75, 2 ),
				'samples' => (int) $row->total_samples,
				'rating'  => $row->rating,
			];
		}

		wp_send_json_success( [ 'urls' => $result, 'metric' => $metric, 'device' => $device ] );
	}

	// ── Uložit nastavení ─────────────────────────────────────────────────────

	public function ajax_save_settings(): void {
		check_ajax_referer( 'seob_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		$data = $_POST['data'] ?? [];
		if ( is_string( $data ) ) $data = json_decode( wp_unslash( $data ), true ) ?? [];

		$current = SEOB_Settings::get( SEOB_Settings::CWV );
		$updated = array_merge( $current, [
			'raw_retention_days'   => max( 7, min( 365, (int) ( $data['raw_retention_days']   ?? 90 ) ) ),
			'daily_retention_days' => max( 30, min( 730, (int) ( $data['daily_retention_days'] ?? 365 ) ) ),
		] );
		SEOB_Settings::update( SEOB_Settings::CWV, $updated );
		wp_send_json_success( [ 'message' => 'Nastavení uloženo.' ] );
	}

	// ── Manuální spuštění agregace (synchronně) ──────────────────────────────

	public function ajax_run_aggregation(): void {
		check_ajax_referer( 'seob_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( '', 403 );

		( new SEOB_CWV_Aggregator() )->run_all_pending();
		wp_send_json_success( [ 'message' => 'Agregace dokončena. Grafy jsou aktuální.' ] );
	}

	// ── Prahové hodnoty metriky (pro "poor") ─────────────────────────────────

	private static function poor_threshold( string $metric ): float {
		return match ( $metric ) {
			'LCP'  => 4000.0,
			'INP'  => 500.0,
			'CLS'  => 0.25,
			'FCP'  => 3000.0,
			'TTFB' => 1800.0,
			default => PHP_FLOAT_MAX,
		};
	}

	private static function ni_threshold( string $metric ): float {
		return match ( $metric ) {
			'LCP'  => 2500.0,
			'INP'  => 200.0,
			'CLS'  => 0.1,
			'FCP'  => 1800.0,
			'TTFB' => 800.0,
			default => PHP_FLOAT_MAX,
		};
	}
}
