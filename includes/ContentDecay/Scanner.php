<?php
/**
 * Content Decay Scanner – synchronní scan všech příspěvků (žádný WP-Cron, čisté DB queries).
 *
 * Výsledky jsou uloženy v wp_options jako poslední scan + archiv posledních 10 skenů.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_ContentDecay_Scanner {

	const RESULTS_KEY  = 'seob_decay_results';
	const ARCHIVE_KEY  = 'seob_decay_archive';
	const MAX_HISTORY  = 10;
	const MAX_POSTS    = 200;

	/**
	 * Spustí kompletní scan. Načte data pro všechny posty naráz (bulk),
	 * zavolá Analyzer pro každý z nich, uloží výsledky.
	 *
	 * @return array  Kompletní scan result.
	 */
	public static function run(): array {
		$post_ids = self::get_post_ids();

		$gsc_data = self::get_gsc_bulk( $post_ids );

		$results = [];
		foreach ( $post_ids as $post_id ) {
			$result = SEOB_ContentDecay_Analyzer::analyze( $post_id, $gsc_data[ $post_id ] ?? null );
			if ( ! empty( $result ) ) {
				$results[] = $result;
			}
		}

		usort( $results, static fn( $a, $b ) => $b['decay_score'] - $a['decay_score'] );

		$scan = [
			'scan_id'    => time(),
			'total'      => count( $results ),
			'decaying'   => count( array_filter( $results, static fn( $r ) => $r['decay_label'] === 'decaying' ) ),
			'stale'      => count( array_filter( $results, static fn( $r ) => $r['decay_label'] === 'stale' ) ),
			'aging'      => count( array_filter( $results, static fn( $r ) => $r['decay_label'] === 'aging' ) ),
			'fresh'      => count( array_filter( $results, static fn( $r ) => $r['decay_label'] === 'fresh' ) ),
			'gsc_available' => self::is_gsc_available(),
			'scanned_at' => time(),
			'results'    => $results,
		];

		update_option( self::RESULTS_KEY, $scan, false );
		self::archive( $scan );

		return $scan;
	}

	/**
	 * Vrátí výsledky posledního scanu nebo null.
	 */
	public static function get_last(): ?array {
		$data = get_option( self::RESULTS_KEY, null );
		return is_array( $data ) && ! empty( $data['scan_id'] ) ? $data : null;
	}

	/**
	 * Vrátí archiv posledních skenů (bez detail výsledků – jen summary).
	 */
	public static function get_history(): array {
		return (array) get_option( self::ARCHIVE_KEY, [] );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private static function get_post_ids(): array {
		$post_types = method_exists( 'SEOB_Audit_ScanRunner', 'get_audit_post_types' )
			? SEOB_Audit_ScanRunner::get_audit_post_types()
			: [ 'post', 'page' ];

		return get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_POSTS,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );
	}

	private static function is_gsc_available(): bool {
		return class_exists( 'SEOB_Gsc_Insights' ) && SEOB_Gsc_Insights::is_table_available();
	}

	/**
	 * Načte GSC data pro všechny posty naráz – 2 SQL queries (period A + period B).
	 *
	 * @param int[] $post_ids
	 * @return array  [ post_id => gsc_data_array ]
	 */
	private static function get_gsc_bulk( array $post_ids ): array {
		if ( ! self::is_gsc_available() || empty( $post_ids ) ) {
			return [];
		}

		global $wpdb;
		$table = $wpdb->prefix . SEOB_Gsc_Insights::TABLE;

		$period_a_start = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$period_b_start = gmdate( 'Y-m-d', strtotime( '-60 days' ) );
		$period_b_end   = gmdate( 'Y-m-d', strtotime( '-31 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rows_a = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS avg_position
				 FROM {$table} WHERE created >= %s GROUP BY page",
				$period_a_start . ' 00:00:00'
			),
			ARRAY_A
		);

		$rows_b = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS avg_position
				 FROM {$table} WHERE created >= %s AND created <= %s GROUP BY page",
				$period_b_start . ' 00:00:00',
				$period_b_end . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable

		$a_by_path = self::rows_to_path_map( $rows_a );
		$b_by_path = self::rows_to_path_map( $rows_b );

		$result = [];
		foreach ( $post_ids as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( ! $permalink ) {
				continue;
			}

			$path = self::normalize_path( $permalink );
			$a    = $a_by_path[ $path ] ?? null;
			$b    = $b_by_path[ $path ] ?? null;

			$result[ $post_id ] = [
				'current_clicks'      => $a ? (int) $a['clicks'] : 0,
				'previous_clicks'     => $b ? (int) $b['clicks'] : 0,
				'current_impressions' => $a ? (int) $a['impressions'] : 0,
				'previous_impressions' => $b ? (int) $b['impressions'] : 0,
				'current_position'    => $a ? round( (float) $a['avg_position'], 1 ) : 0.0,
				'previous_position'   => $b ? round( (float) $b['avg_position'], 1 ) : 0.0,
			];
		}

		return $result;
	}

	private static function rows_to_path_map( array $rows ): array {
		$map = [];
		foreach ( $rows as $row ) {
			if ( isset( $row['page'] ) ) {
				$map[ self::normalize_path( $row['page'] ) ] = $row;
			}
		}
		return $map;
	}

	private static function normalize_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '/' . trim( $path, '/' );
	}

	private static function archive( array $scan ): void {
		$history = self::get_history();
		array_unshift( $history, [
			'scan_id'    => $scan['scan_id'],
			'total'      => $scan['total'],
			'decaying'   => $scan['decaying'],
			'stale'      => $scan['stale'],
			'scanned_at' => $scan['scanned_at'],
		] );
		update_option( self::ARCHIVE_KEY, array_slice( $history, 0, self::MAX_HISTORY ) );
	}
}
