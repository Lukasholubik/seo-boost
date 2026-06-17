<?php
/**
 * Denní agregace surových CWV dat na p75 hodnoty + rotace starých dat.
 * Spouštěno WP-Cronem každý den.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SEOB_CWV_Aggregator {

	public const CRON_HOOK = 'seob_cwv_aggregate';

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Spustit dnes v 03:00 UTC (nízká zátěž serveru)
			$next_run = strtotime( 'tomorrow 03:00:00 UTC' );
			wp_schedule_event( $next_run, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback: agreguje data za předchozí den + rotuje stará data.
	 */
	public function run(): void {
		$cfg           = SEOB_Settings::get( SEOB_Settings::CWV );
		$raw_retention = max( 7, (int) ( $cfg['raw_retention_days'] ?? 90 ) );
		$day_retention = max( 30, (int) ( $cfg['daily_retention_days'] ?? 365 ) );

		$this->aggregate_day( gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );
		$this->rotate( $raw_retention, $day_retention );
		update_option( 'seob_cwv_last_aggregation', time() );
	}

	/**
	 * Manuální agregace: zpracuje všechny dny které mají surová data
	 * (včetně dnešního). Použije se při kliknutí "Spustit agregaci nyní".
	 */
	public function run_all_pending(): void {
		global $wpdb;
		$cfg           = SEOB_Settings::get( SEOB_Settings::CWV );
		$raw_retention = max( 7, (int) ( $cfg['raw_retention_days'] ?? 90 ) );
		$day_retention = max( 30, (int) ( $cfg['daily_retention_days'] ?? 365 ) );
		$raw_table     = SEOB_Database::cwv_raw_table();

		// Najdi všechny dny s daty (zahrnuje dnešek i historii)
		$days = $wpdb->get_col( "SELECT DISTINCT DATE(recorded_at) FROM {$raw_table} ORDER BY DATE(recorded_at) ASC" );
		foreach ( $days as $day ) {
			$this->aggregate_day( $day );
		}

		$this->rotate( $raw_retention, $day_retention );
		update_option( 'seob_cwv_last_aggregation', time() );
	}

	/**
	 * Agreguje p75 per path/metric/device pro jeden konkrétní den.
	 */
	private function aggregate_day( string $day ): void {
		global $wpdb;
		$raw_table   = SEOB_Database::cwv_raw_table();
		$daily_table = SEOB_Database::cwv_daily_table();
		$metrics     = [ 'LCP', 'INP', 'CLS', 'FCP', 'TTFB' ];
		$devices     = [ 'mobile', 'desktop' ];

		foreach ( $metrics as $metric ) {
			foreach ( $devices as $device ) {
				$path_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT url_hash, path, value FROM {$raw_table}
						 WHERE metric = %s AND device = %s
						   AND DATE(recorded_at) = %s
						 ORDER BY url_hash, value ASC",
						$metric,
						$device,
						$day
					)
				);

				if ( empty( $path_rows ) ) {
					continue;
				}

				// Seskupit per URL hash
				$by_path = [];
				foreach ( $path_rows as $row ) {
					$by_path[ $row->url_hash ] = [
						'path'   => $row->path,
						'values' => array_merge( $by_path[ $row->url_hash ]['values'] ?? [], [ (float) $row->value ] ),
					];
				}

				foreach ( $by_path as $url_hash => $data ) {
					$wpdb->replace(
						$daily_table,
						[
							'day'          => $day,
							'url_hash'     => $url_hash,
							'path'         => $data['path'],
							'metric'       => $metric,
							'device'       => $device,
							'p75'          => self::percentile_75( $data['values'] ),
							'sample_count' => count( $data['values'] ),
						],
						[ '%s', '%s', '%s', '%s', '%s', '%f', '%d' ]
					);
				}

				// Celkový p75 pro '*' (všechny URL dohromady)
				$all_values = array_column( $path_rows, 'value' );
				sort( $all_values );
				$wpdb->replace(
					$daily_table,
					[
						'day'          => $day,
						'url_hash'     => '*',
						'path'         => '*',
						'metric'       => $metric,
						'device'       => $device,
						'p75'          => self::percentile_75( $all_values ),
						'sample_count' => count( $all_values ),
					],
					[ '%s', '%s', '%s', '%s', '%s', '%f', '%d' ]
				);
			}
		}
	}

	private function rotate( int $raw_days, int $day_days ): void {
		global $wpdb;
		$raw_table   = SEOB_Database::cwv_raw_table();
		$daily_table = SEOB_Database::cwv_daily_table();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$raw_table} WHERE recorded_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$raw_days} days" ) )
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$daily_table} WHERE day < %s",
				gmdate( 'Y-m-d', strtotime( "-{$day_days} days" ) )
			)
		);
	}

	// ── p75 výpočet ──────────────────────────────────────────────────────────

	private static function percentile_75( array $sorted_values ): float {
		$n = count( $sorted_values );
		if ( $n === 0 ) return 0.0;
		if ( $n === 1 ) return (float) $sorted_values[0];

		sort( $sorted_values );
		$index = (int) ceil( 0.75 * $n ) - 1;
		return (float) $sorted_values[ max( 0, min( $index, $n - 1 ) ) ];
	}
}
