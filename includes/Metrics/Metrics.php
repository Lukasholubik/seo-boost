<?php
/**
 * Ukládání a čtení provozních metrik modulů (tabulka seo_booster_metrics).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Metrics {

	/**
	 * Zapíše novou hodnotu metriky.
	 */
	public static function record( string $module, string $key, float $value ): void {
		global $wpdb;

		$wpdb->insert(
			SEOB_Database::metrics_table(),
			[
				'module'       => $module,
				'metric_key'   => $key,
				'metric_value' => $value,
				'recorded_at'  => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%f', '%s' ]
		);
	}

	/**
	 * Vrátí posledních $limit záznamů metriky, vzestupně podle času.
	 *
	 * @return array<int, array{recorded_at:string, value:float}>
	 */
	public static function get_trend( string $module, string $key, int $limit = 30 ): array {
		global $wpdb;

		$table = SEOB_Database::metrics_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT recorded_at, metric_value FROM {$table} WHERE module = %s AND metric_key = %s ORDER BY recorded_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$module,
				$key,
				$limit
			)
		);

		$rows = array_reverse( $rows );

		return array_map(
			static function ( $row ) {
				return [
					'recorded_at' => $row->recorded_at,
					'value'       => (float) $row->metric_value,
				];
			},
			$rows
		);
	}

	/**
	 * Vrátí poslední zaznamenanou hodnotu metriky, nebo null pokud žádná neexistuje.
	 */
	public static function get_latest( string $module, string $key ): ?float {
		global $wpdb;

		$table = SEOB_Database::metrics_table();

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT metric_value FROM {$table} WHERE module = %s AND metric_key = %s ORDER BY recorded_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$module,
				$key
			)
		);

		return null === $value ? null : (float) $value;
	}
}
