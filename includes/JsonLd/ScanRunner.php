<?php
/**
 * JSON-LD Scan Runner – server-side davkove zpracovani, archiv vysledku.
 *
 * Tok:
 *  1. start()         – pripravy seznam URL, ulozi stav do transienta, naplanova cron
 *  2. process_batch() – WP-Cron vola tuto metodu, naskenuje BATCH_SIZE URL,
 *                       aktualizuje stav, naplanova dalsi davku (nebo uzavre scan)
 *  3. Kdykoli:        – get_active() vraci aktualni stav (pro polling v JS)
 *                     – get_history() vraci archiv posledních MAX_HISTORY scanu
 *                     – get_results() vraci URL-detaily konkretniho scanu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_JsonLd_ScanRunner {

	const CRON_HOOK    = 'seob_jsonld_process_batch';
	const ACTIVE_KEY   = 'seob_jsonld_active_scan';
	const RESULTS_PFX  = 'seob_jsonld_results_';
	const ARCHIVE_OPT  = 'seob_jsonld_scan_archive';
	const BATCH_SIZE   = 1;   // 1 URL/davka – nezahlti PHP-FPM worker pool
	const BATCH_DELAY  = 3;   // sekundy pauzy mezi davkami
	const MAX_HISTORY  = 10;
	const MAX_URLS     = 50;

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'process_batch' ] );
	}

	/**
	 * Spusti novy scan: uklidi predchozi, sestavi frontu URL, naplanova prvni davku.
	 */
	public static function start( int $limit = self::MAX_URLS ): array {
		self::cancel();

		$urls  = SEOB_JsonLd_PageScanner::get_scan_urls( $limit );
		$id    = time();
		$state = [
			'scan_id'       => $id,
			'status'        => 'running',
			'total'         => count( $urls ),
			'scanned'       => 0,
			'invalid'       => 0,
			'duplicates'    => 0,
			'total_schemas' => 0,
			'started_at'    => $id,
			'queue'         => $urls,
		];

		set_transient( self::ACTIVE_KEY, $state, 2 * HOUR_IN_SECONDS );
		wp_schedule_single_event( time(), self::CRON_HOOK );

		// Okamzite spustit prvni davku – nepocekame na nahodny page load.
		// spawn_cron() pouziva cron_request filter (sslverify=false) nastaveny v Plugin.php.
		spawn_cron();

		return [ 'scan_id' => $id, 'total' => count( $urls ) ];
	}

	/**
	 * Vraci aktualni stav probihajiciho scanu (bez fronty URL, aby payload byl maly).
	 */
	public static function get_active(): array {
		$state = get_transient( self::ACTIVE_KEY );
		if ( ! is_array( $state ) ) {
			return [];
		}

		// Vrat jen summary (bez fronty – ta muze byt velka)
		return array_diff_key( $state, [ 'queue' => true ] );
	}

	/**
	 * WP-Cron callback: naskenuje BATCH_SIZE URL a naplanova dalsi davku.
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
			// Zpetna kompatibilita: stary format = retezec URL, novy = pole s metadaty
			$url = is_array( $item ) ? ( $item['url'] ?? '' ) : (string) $item;

			$r = SEOB_JsonLd_PageScanner::scan_url( $url );

			// Pridat metadata post typu a odkaz na editor
			if ( is_array( $item ) ) {
				$r['post_type']       = $item['post_type'] ?? '';
				$r['post_type_label'] = $item['post_type_label'] ?? '';
				$r['post_id']         = (int) ( $item['post_id'] ?? 0 );
				$r['edit_url']        = $item['edit_url'] ?? '';
			}

			$saved[]                 = $r;
			$state['scanned']       += 1;
			$state['total_schemas'] += (int) ( $r['schema_count'] ?? 0 );

			$has_errors = ! empty( array_filter(
				$r['issues'] ?? [],
				static fn ( $i ) => ( $i['severity'] ?? '' ) === 'error'
			) );

			if ( $has_errors )                 { $state['invalid']++; }
			if ( ! empty( $r['duplicates'] ) ) { $state['duplicates']++; }
		}

		set_transient( $rkey, $saved, DAY_IN_SECONDS );

		$state['queue'] = $queue;

		if ( empty( $queue ) ) {
			$state['status']      = 'done';
			$state['finished_at'] = time();

			self::archive( $state );

			SEOB_Metrics::record( 'json-ld', 'invalid_schemas',   $state['invalid'] );
			SEOB_Metrics::record( 'json-ld', 'duplicate_schemas', $state['duplicates'] );
			SEOB_Metrics::record( 'json-ld', 'total_schemas',     $state['total_schemas'] );
		} else {
			// Pauza BATCH_DELAY sekund – zamezi saturaci PHP-FPM worker poolu
			wp_schedule_single_event( time() + self::BATCH_DELAY, self::CRON_HOOK );
		}

		set_transient( self::ACTIVE_KEY, $state, 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Vraci archiv dokoncených skenů (nejnovejsi prvni).
	 *
	 * @return list<array>
	 */
	public static function get_history(): array {
		return get_option( self::ARCHIVE_OPT, [] );
	}

	/**
	 * Vraci URL-uroven vysledky pro konkretni scan_id.
	 *
	 * @return list<array>
	 */
	public static function get_results( int $scan_id ): array {
		$data = get_transient( self::RESULTS_PFX . $scan_id );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Zrusi aktivni scan a smaze naplánovany cron.
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

	private static function archive( array $state ): void {
		$history = get_option( self::ARCHIVE_OPT, [] );

		array_unshift( $history, [
			'scan_id'       => $state['scan_id'],
			'status'        => 'done',
			'total'         => $state['total'],
			'scanned'       => $state['scanned'],
			'invalid'       => $state['invalid'],
			'duplicates'    => $state['duplicates'],
			'total_schemas' => $state['total_schemas'],
			'started_at'    => $state['started_at'],
			'finished_at'   => $state['finished_at'] ?? time(),
		] );

		update_option( self::ARCHIVE_OPT, array_slice( $history, 0, self::MAX_HISTORY ) );
	}
}
