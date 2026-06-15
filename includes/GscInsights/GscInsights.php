<?php
/**
 * Čtení dat Google Search Console z vlastní tabulky Rank Math
 * (`wp_rank_math_analytics_gsc`) – žádné vlastní API/OAuth, jen čtení.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Gsc_Insights {

	const TABLE         = 'rank_math_analytics_gsc';
	const LOOKBACK_DAYS = 28;

	/**
	 * Existuje tabulka Rank Math s GSC daty?
	 */
	public static function is_table_available(): bool {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$table
			)
		);

		return (int) $found > 0;
	}

	/**
	 * Celkový souhrn za posledních `LOOKBACK_DAYS` dní (pro health check).
	 *
	 * @return array{clicks:int,impressions:int,ctr:float,avg_position:float,last_date:string}|null
	 */
	public static function get_summary(): ?array {
		global $wpdb;

		if ( ! self::is_table_available() ) {
			return null;
		}

		$table = $wpdb->prefix . self::TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS avg_position, MAX(created) AS last_date
				FROM {$table} WHERE created >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d 00:00:00', strtotime( '-' . self::LOOKBACK_DAYS . ' days' ) )
			),
			ARRAY_A
		);

		if ( null === $row || null === $row['last_date'] || (int) $row['impressions'] === 0 ) {
			return null;
		}

		$clicks      = (int) $row['clicks'];
		$impressions = (int) $row['impressions'];

		return [
			'clicks'       => $clicks,
			'impressions'  => $impressions,
			'ctr'          => $impressions > 0 ? round( $clicks / $impressions * 100, 2 ) : 0.0,
			'avg_position' => round( (float) $row['avg_position'], 1 ),
			'last_date'    => (string) $row['last_date'],
		];
	}

	/**
	 * Doplní do řádků auditu klíč `gsc` s metrikami Search Console za
	 * posledních `LOOKBACK_DAYS` dní (nebo `null`, pokud pro danou stránku
	 * data nejsou).
	 *
	 * @param array<int, array<string, mixed>> $rows
	 */
	public static function attach_metrics( array &$rows ): void {
		if ( ! self::is_table_available() ) {
			foreach ( $rows as &$row ) {
				$row['gsc'] = null;
			}
			unset( $row );
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS avg_position
				FROM {$table} WHERE created >= %s GROUP BY page", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d 00:00:00', strtotime( '-' . self::LOOKBACK_DAYS . ' days' ) )
			),
			ARRAY_A
		);

		$by_path = [];

		foreach ( $results as $result ) {
			$path   = self::normalize_path( (string) $result['page'] );
			$clicks = (int) $result['clicks'];
			$impressions = (int) $result['impressions'];

			$by_path[ $path ] = [
				'clicks'       => $clicks,
				'impressions'  => $impressions,
				'ctr'          => $impressions > 0 ? round( $clicks / $impressions * 100, 2 ) : 0.0,
				'avg_position' => round( (float) $result['avg_position'], 1 ),
			];
		}

		foreach ( $rows as &$row ) {
			$path       = self::normalize_path( (string) ( $row['url'] ?? '' ) );
			$row['gsc'] = $by_path[ $path ] ?? null;
		}
		unset( $row );
	}

	/**
	 * Doplní do řádků auditu klíč `gsc_queries` – seznam klíčových slov
	 * (dotazů), na které stránka za posledních `LOOKBACK_DAYS` dní cílila ve
	 * vyhledávání, seřazený podle kliků (sestupně), max. `$limit` na stránku.
	 * `null`, pokud pro danou stránku žádné dotazy nejsou.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 */
	public static function attach_queries( array &$rows, int $limit = 10 ): void {
		if ( ! self::is_table_available() ) {
			foreach ( $rows as &$row ) {
				$row['gsc_queries'] = null;
			}
			unset( $row );
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page, query, SUM(clicks) AS clicks, SUM(impressions) AS impressions, AVG(position) AS avg_position
				FROM {$table} WHERE created >= %s GROUP BY page, query ORDER BY clicks DESC, impressions DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d 00:00:00', strtotime( '-' . self::LOOKBACK_DAYS . ' days' ) )
			),
			ARRAY_A
		);

		$by_path = [];

		foreach ( $results as $result ) {
			$path = self::normalize_path( (string) $result['page'] );

			if ( ! isset( $by_path[ $path ] ) ) {
				$by_path[ $path ] = [];
			}

			if ( count( $by_path[ $path ] ) >= $limit ) {
				continue;
			}

			$by_path[ $path ][] = [
				'query'        => (string) $result['query'],
				'clicks'       => (int) $result['clicks'],
				'impressions'  => (int) $result['impressions'],
				'avg_position' => round( (float) $result['avg_position'], 1 ),
			];
		}

		foreach ( $rows as &$row ) {
			$path              = self::normalize_path( (string) ( $row['url'] ?? '' ) );
			$row['gsc_queries'] = $by_path[ $path ] ?? null;
		}
		unset( $row );
	}

	/**
	 * Normalizuje URL na cestu bez domény a koncového lomítka, pro porovnání
	 * URL z auditu (`home_url()`) s URL z GSC (může mít jiný host/schéma/www).
	 */
	private static function normalize_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );

		return $path;
	}
}
