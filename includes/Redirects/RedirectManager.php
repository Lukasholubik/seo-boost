<?php
/**
 * Zachytává požadavky a aplikuje uložená přesměrování.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Redirect_Manager {

	const CRON_HOOK = 'seob_redirects_cleanup';

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
		add_action( self::CRON_HOOK, [ $this, 'cleanup_old_logs' ] );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Hlavní handler – aplikuje aktivní redirect, jinak loguje 404.
	 */
	public function handle_request(): void {
		if ( is_admin() ) {
			return;
		}

		$settings = SEOB_Settings::get( SEOB_Settings::REDIRECT );
		$path     = $this->normalize_path( $_SERVER['REQUEST_URI'] ?? '/' );

		$redirect = $this->find_redirect( $path );

		if ( null !== $redirect ) {
			wp_safe_redirect( $redirect['url'], $redirect['code'] );
			exit;
		}

		if ( is_404() && ! empty( $settings['log_404'] ) ) {
			$this->log_404( $path );
		}
	}

	/**
	 * Normalizuje cestu z REQUEST_URI (bez query stringu, bez koncového lomítka).
	 */
	private function normalize_path( string $request_uri ): string {
		$path = (string) ( wp_parse_url( $request_uri, PHP_URL_PATH ) ?? '/' );
		$path = rawurldecode( $path );

		if ( '' === $path ) {
			$path = '/';
		}

		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}

		return $path;
	}

	const CACHE_KEY = 'seob_redirects_map';

	/**
	 * Najde aktivní přesměrování pro danou cestu.
	 * Vrátí ['url' => string, 'code' => int] nebo null.
	 *
	 * @return array{url: string, code: int}|null
	 */
	private function find_redirect( string $path ): ?array {
		$map = get_transient( self::CACHE_KEY );

		if ( ! is_array( $map ) ) {
			$map = $this->build_redirect_map();
			set_transient( self::CACHE_KEY, $map, 12 * HOUR_IN_SECONDS );
		}

		return $map[ $path ] ?? null;
	}

	/**
	 * Načte všechna aktivní přesměrování z DB do mapy target_url => [url, code].
	 * Načítá i http_status aby byl respektován typ přesměrování (301/302/307/308).
	 */
	private function build_redirect_map(): array {
		global $wpdb;

		$links_table = SEOB_Database::links_table();

		$rows = $wpdb->get_results(
			"SELECT target_url, redirect_to, http_status FROM {$links_table} WHERE redirect_to IS NOT NULL AND redirect_to != ''" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$map = [];

		foreach ( $rows as $row ) {
			$code = (int) $row->http_status;
			if ( ! in_array( $code, [ 301, 302, 307, 308 ], true ) ) {
				$code = 301;
			}
			$map[ $row->target_url ] = [
				'url'  => $row->redirect_to,
				'code' => $code,
			];
		}

		return $map;
	}

	/**
	 * Zneplatní cache mapy přesměrování (volá se po uložení/smazání z admin UI).
	 */
	public static function invalidate_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Zaloguje 404 výskyt – nový řádek nebo navýšení čítače u existujícího.
	 */
	private function log_404( string $path ): void {
		global $wpdb;

		$links_table = SEOB_Database::links_table();

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$links_table} WHERE target_url = %s AND link_type = 'internal' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$path
			)
		);

		if ( $existing_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$links_table} SET hits_404 = hits_404 + 1, http_status = 404, last_checked = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					current_time( 'mysql' ),
					$existing_id
				)
			);

			return;
		}

		$wpdb->insert(
			$links_table,
			[
				'source_id'    => null,
				'target_url'   => $path,
				'link_type'    => 'internal',
				'http_status'  => 404,
				'last_checked' => current_time( 'mysql' ),
				'hits_404'     => 1,
			],
			[ '%d', '%s', '%s', '%d', '%s', '%d' ]
		);
	}

	/**
	 * Smaže staré 404 záznamy bez nastaveného přesměrování dle retence.
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		$settings = SEOB_Settings::get( SEOB_Settings::REDIRECT );
		$days     = max( 1, (int) $settings['log_retention_days'] );

		$links_table = SEOB_Database::links_table();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$links_table} WHERE redirect_to IS NULL AND last_checked < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);

		$unresolved = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$links_table} WHERE redirect_to IS NULL AND hits_404 > 0" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		SEOB_Metrics::record( 'redirects', 'unresolved_404_count', $unresolved );
	}
}
