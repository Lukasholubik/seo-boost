<?php
/**
 * HTTP Headers Scan Runner – WP-Cron dávkové zpracování, archiv výsledků.
 *
 * Vzor: stejný jako SEOB_JsonLd_ScanRunner.
 *
 * Tok:
 *  1. start()         – připraví seznam URL, uloží stav do transientu, naplánuje cron
 *  2. process_batch() – WP-Cron volá tuto metodu: zkontroluje 1 URL, naplánuje další
 *  3. Kdykoli:        – get_active() vrátí aktuální stav (pro polling v JS)
 *                     – get_history() vrátí archiv posledních MAX_HISTORY skenů
 *                     – get_results() vrátí URL-detaily konkrétního scan_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_HttpHeaders_ScanRunner {

	const CRON_HOOK   = 'seob_http_headers_process_batch';
	const ACTIVE_KEY  = 'seob_http_headers_active_scan';
	const RESULTS_PFX = 'seob_http_headers_results_';
	const ARCHIVE_OPT = 'seob_http_headers_scan_archive';
	const BATCH_SIZE  = 1;   // 1 URL/dávka – nezahltí PHP-FPM worker pool
	const BATCH_DELAY = 3;   // sekundy pauzy mezi dávkami
	const MAX_HISTORY = 10;
	const MAX_URLS    = 50;

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'process_batch' ] );
	}

	/**
	 * Spustí nový scan.
	 *
	 * @return array{scan_id:int,total:int}
	 */
	public static function start( int $limit = self::MAX_URLS ): array {
		self::cancel();

		$urls = self::build_url_list( $limit );
		$id   = time();

		$state = [
			'scan_id'    => $id,
			'status'     => 'running',
			'total'      => count( $urls ),
			'scanned'    => 0,
			'critical'   => 0,
			'warnings'   => 0,
			'started_at' => $id,
			'queue'      => $urls,
		];

		set_transient( self::ACTIVE_KEY, $state, 2 * HOUR_IN_SECONDS );
		wp_schedule_single_event( time(), self::CRON_HOOK );
		spawn_cron();

		return [ 'scan_id' => $id, 'total' => count( $urls ) ];
	}

	/**
	 * Vrátí stav aktuálního skenu (bez fronty).
	 */
	public static function get_active(): array {
		$state = get_transient( self::ACTIVE_KEY );
		if ( ! is_array( $state ) ) {
			return [];
		}

		return array_diff_key( $state, [ 'queue' => true ] );
	}

	/**
	 * WP-Cron callback: zkontroluje BATCH_SIZE URL, naplánuje další dávku.
	 */
	public function process_batch(): void {
		$state = get_transient( self::ACTIVE_KEY );
		if ( ! is_array( $state ) || 'running' !== ( $state['status'] ?? '' ) ) {
			return;
		}

		$queue = $state['queue'] ?? [];
		$batch = array_splice( $queue, 0, self::BATCH_SIZE );

		$rkey   = self::RESULTS_PFX . $state['scan_id'];
		$saved  = get_transient( $rkey );
		$saved  = is_array( $saved ) ? $saved : [];

		foreach ( $batch as $item ) {
			$url  = is_array( $item ) ? ( $item['url'] ?? '' ) : (string) $item;
			$r    = SEOB_HttpHeaders_Checker::check( $url );

			// Přidáme metadata z fronty
			if ( is_array( $item ) ) {
				$r['post_id']         = (int) ( $item['post_id'] ?? 0 );
				$r['post_type']       = $item['post_type'] ?? '';
				$r['post_type_label'] = $item['post_type_label'] ?? '';
				$r['edit_url']        = $item['edit_url'] ?? '';
			}

			$saved[] = $r;
			$state['scanned']++;

			foreach ( $r['issues'] as $issue ) {
				if ( 'critical' === $issue['severity'] ) {
					$state['critical']++;
				} elseif ( 'warning' === $issue['severity'] ) {
					$state['warnings']++;
				}
			}
		}

		set_transient( $rkey, $saved, DAY_IN_SECONDS );

		$state['queue'] = $queue;

		if ( empty( $queue ) ) {
			$state['status']      = 'done';
			$state['finished_at'] = time();

			self::archive( $state );

			SEOB_Metrics::record( 'http-headers', 'critical_issues', $state['critical'] );
			SEOB_Metrics::record( 'http-headers', 'warning_issues',  $state['warnings'] );
		} else {
			wp_schedule_single_event( time() + self::BATCH_DELAY, self::CRON_HOOK );
		}

		set_transient( self::ACTIVE_KEY, $state, 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Archiv dokončených skenů (nejnovější první).
	 *
	 * @return list<array>
	 */
	public static function get_history(): array {
		return get_option( self::ARCHIVE_OPT, [] );
	}

	/**
	 * URL-level výsledky pro konkrétní scan_id.
	 *
	 * @return list<array>
	 */
	public static function get_results( int $scan_id ): array {
		$data = get_transient( self::RESULTS_PFX . $scan_id );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Zruší aktivní scan.
	 */
	public static function cancel(): void {
		$state = get_transient( self::ACTIVE_KEY );
		if ( is_array( $state ) ) {
			$state['status'] = 'cancelled';
			set_transient( self::ACTIVE_KEY, $state, HOUR_IN_SECONDS );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	// ── Private ───────────────────────────────────────────────────────────────

	/**
	 * Sestaví seznam URL pro scan (homepage + naposledy upravené stránky všech typů).
	 *
	 * @return list<array{url:string,post_id:int,post_type:string,post_type_label:string,edit_url:string}>
	 */
	private static function build_url_list( int $limit ): array {
		$items = [];

		// Homepage
		$home = home_url( '/' );
		$items[] = [
			'url'            => $home,
			'post_id'        => 0,
			'post_type'      => '',
			'post_type_label'=> 'Úvodní stránka',
			'edit_url'       => admin_url( 'customize.php' ),
		];

		// Veřejné post typy (stejná logika jako v Audit Dashboard)
		$post_types = SEOB_Audit_ScanRunner::get_audit_post_types();
		$post_type_labels = [];
		foreach ( $post_types as $pt ) {
			$obj = get_post_type_object( $pt );
			$post_type_labels[ $pt ] = $obj ? $obj->labels->singular_name : $pt;
		}

		$posts = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit - 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );

		foreach ( $posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( ! $permalink ) {
				continue;
			}

			$pt = get_post_type( $post_id );
			$items[] = [
				'url'            => $permalink,
				'post_id'        => (int) $post_id,
				'post_type'      => $pt,
				'post_type_label'=> $post_type_labels[ $pt ] ?? $pt,
				'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
			];
		}

		return array_slice( $items, 0, $limit );
	}

	private static function archive( array $state ): void {
		$history = get_option( self::ARCHIVE_OPT, [] );

		array_unshift( $history, [
			'scan_id'    => $state['scan_id'],
			'status'     => 'done',
			'total'      => $state['total'],
			'scanned'    => $state['scanned'],
			'critical'   => $state['critical'],
			'warnings'   => $state['warnings'],
			'started_at' => $state['started_at'],
			'finished_at'=> $state['finished_at'] ?? time(),
		] );

		update_option( self::ARCHIVE_OPT, array_slice( $history, 0, self::MAX_HISTORY ) );
	}
}
