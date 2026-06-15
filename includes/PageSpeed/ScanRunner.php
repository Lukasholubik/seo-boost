<?php
/**
 * Řídí běh analýzy PageSpeed Insights: výběr vzorku, dávkové dotazy na PSI API,
 * agregace výsledků podle typu obsahu a strategie (mobile/desktop).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_PageSpeed_ScanRunner {

	private const QUEUE_TTL     = HOUR_IN_SECONDS;
	private const SAMPLE_SIZE   = 5;
	private const STRATEGIES    = [ 'mobile', 'desktop' ];
	private const HISTORY_LIMIT = 10;
	private const COMMON_ISSUES_LIMIT = 10;

	/**
	 * WP-Cron hook, který dokončí běh na pozadí, i když uživatel zavře stránku.
	 * Spustí se při dalším návštěvníkovi webu (WP pseudo-cron) – ne okamžitě,
	 * ale bez nutnosti mít otevřenou administraci.
	 */
	const CRON_HOOK = 'seob_psi_process_batch';

	/**
	 * Založí nový běh analýzy a připraví frontu položek (post × strategie) ke zpracování.
	 *
	 * @return array{run_id:int, items_total:int, queue:array<int,array<string,mixed>>}
	 */
	public function start_scan(): array {
		global $wpdb;

		$queue = [];

		foreach ( $this->get_sample_post_types() as $post_type ) {
			$post_ids = get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => self::SAMPLE_SIZE,
					'orderby'        => 'rand',
					'fields'         => 'ids',
				]
			);

			foreach ( $post_ids as $post_id ) {
				$url = get_permalink( $post_id );

				if ( ! is_string( $url ) || '' === $url ) {
					continue;
				}

				foreach ( self::STRATEGIES as $strategy ) {
					$queue[] = [
						'object_id'   => (int) $post_id,
						'object_type' => $post_type,
						'url'         => $url,
						'strategy'    => $strategy,
					];
				}
			}
		}

		$psi_runs_table = SEOB_Database::psi_runs_table();

		$wpdb->insert(
			$psi_runs_table,
			[
				'started_at'  => current_time( 'mysql' ),
				'items_total' => count( $queue ),
				'items_done'  => 0,
				'status'      => 'running',
			],
			[ '%s', '%d', '%d', '%s' ]
		);

		$run_id = (int) $wpdb->insert_id;

		set_transient( 'seob_psi_queue_' . $run_id, $queue, self::QUEUE_TTL );

		if ( empty( $queue ) ) {
			$this->finalize_scan( $run_id );
		} else {
			self::schedule_next( $run_id );
		}

		return [
			'run_id'      => $run_id,
			'items_total' => count( $queue ),
			'queue'       => $queue,
		];
	}

	/**
	 * Naplánuje (nebo přeplánuje) zpracování další dávky přes WP-Cron, aby
	 * běh dokončil i bez otevřené stránky administrace.
	 */
	private static function schedule_next( int $run_id, int $delay = 5 ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK, [ $run_id ] ) ) {
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK, [ $run_id ] );
		}
	}

	/**
	 * WP-Cron callback – zpracuje jednu dávku a naplánuje další, dokud běh neskončí.
	 */
	public function process_batch_cron( int $run_id ): void {
		$result = $this->process_batch( $run_id, 1 );

		if ( ! $result['finished'] ) {
			self::schedule_next( $run_id, 10 );
		}
	}

	/**
	 * Zpracuje jednu dávku položek z fronty.
	 *
	 * @return array{done:int,total:int,finished:bool,items:array<int,array{url:string,strategy:string,error:?string}>}
	 */
	public function process_batch( int $run_id, int $batch_size = 1 ): array {
		global $wpdb;

		$psi_runs_table    = SEOB_Database::psi_runs_table();
		$psi_results_table = SEOB_Database::psi_results_table();

		$run = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$psi_runs_table} WHERE id = %d", $run_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $run ) {
			return [ 'done' => 0, 'total' => 0, 'finished' => true, 'items' => [] ];
		}

		if ( 'done' === $run->status ) {
			return [
				'done'     => (int) $run->items_done,
				'total'    => (int) $run->items_total,
				'finished' => true,
				'items'    => [],
			];
		}

		// Zámek proti souběhu JS pollingu a WP-Cron callbacku na stejném běhu.
		$lock_key = 'seob_psi_lock_' . $run_id;

		if ( get_transient( $lock_key ) ) {
			return [
				'done'     => (int) $run->items_done,
				'total'    => (int) $run->items_total,
				'finished' => 'done' === $run->status,
				'items'    => [],
			];
		}

		set_transient( $lock_key, 1, 2 * MINUTE_IN_SECONDS );

		$queue = get_transient( 'seob_psi_queue_' . $run_id );

		if ( ! is_array( $queue ) ) {
			$queue = [];
		}

		$batch = array_splice( $queue, 0, max( 1, $batch_size ) );

		$api_key = SEOB_AiQueue_Crypt::decrypt( SEOB_Settings::get( SEOB_Settings::PAGESPEED )['api_key_enc'] );

		set_time_limit( 90 );

		$processed_items = [];

		foreach ( $batch as $item ) {
			$result = SEOB_PageSpeed_Client::analyze( $item['url'], $item['strategy'], $api_key );

			$row = [
				'run_id'      => $run_id,
				'object_id'   => $item['object_id'],
				'object_type' => $item['object_type'],
				'url'         => $item['url'],
				'strategy'    => $item['strategy'],
				'scanned_at'  => current_time( 'mysql' ),
			];

			if ( is_wp_error( $result ) ) {
				$row['performance_score']    = null;
				$row['accessibility_score']  = null;
				$row['best_practices_score'] = null;
				$row['seo_score']            = null;
				$row['issues_json']          = null;
				$row['error']                = $result->get_error_message();
			} else {
				$row['performance_score']    = $result['performance_score'];
				$row['accessibility_score']  = $result['accessibility_score'];
				$row['best_practices_score'] = $result['best_practices_score'];
				$row['seo_score']            = $result['seo_score'];
				$row['issues_json']          = wp_json_encode( $result['issues'] );
				$row['error']                = null;
			}

			$wpdb->insert( $psi_results_table, $row );

			$processed_items[] = [
				'url'      => $item['url'],
				'strategy' => $item['strategy'],
				'error'    => $row['error'],
			];
		}

		$processed  = count( $batch );
		$items_done = (int) $run->items_done + $processed;

		set_transient( 'seob_psi_queue_' . $run_id, $queue, self::QUEUE_TTL );

		$wpdb->update(
			$psi_runs_table,
			[ 'items_done' => $items_done ],
			[ 'id' => $run_id ],
			[ '%d' ],
			[ '%d' ]
		);

		$finished = empty( $queue );

		if ( $finished ) {
			$this->finalize_scan( $run_id );
			delete_transient( 'seob_psi_queue_' . $run_id );
		}

		delete_transient( $lock_key );

		return [
			'done'     => $items_done,
			'total'    => (int) $run->items_total,
			'finished' => $finished,
			'items'    => $processed_items,
		];
	}

	/**
	 * Spočítá souhrny podle typu obsahu a strategie, uloží je a uzavře běh.
	 */
	private function finalize_scan( int $run_id ): void {
		global $wpdb;

		$psi_results_table = SEOB_Database::psi_results_table();
		$psi_summary_table = SEOB_Database::psi_summary_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$psi_results_table} WHERE run_id = %d", $run_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$groups = [];

		foreach ( $rows as $row ) {
			$key             = $row['object_type'] . '|' . $row['strategy'];
			$groups[ $key ]['object_type'] = $row['object_type'];
			$groups[ $key ]['strategy']    = $row['strategy'];
			$groups[ $key ]['rows'][]      = $row;
		}

		$seo_avgs = [
			'mobile'  => [],
			'desktop' => [],
		];

		foreach ( $groups as $group ) {
			$summary = self::aggregate_group( $group['rows'] );

			$wpdb->insert(
				$psi_summary_table,
				[
					'run_id'                 => $run_id,
					'object_type'            => $group['object_type'],
					'strategy'               => $group['strategy'],
					'performance_avg'        => $summary['performance_avg'],
					'accessibility_avg'      => $summary['accessibility_avg'],
					'best_practices_avg'     => $summary['best_practices_avg'],
					'seo_avg'                => $summary['seo_avg'],
					'sample_size'            => $summary['sample_size'],
					'common_issues_json'     => wp_json_encode( $summary['common_issues'] ),
					'sample_object_ids_json' => wp_json_encode( $summary['sample_object_ids'] ),
				]
			);

			if ( null !== $summary['seo_avg'] && isset( $seo_avgs[ $group['strategy'] ] ) ) {
				$seo_avgs[ $group['strategy'] ][] = $summary['seo_avg'];
			}
		}

		foreach ( $seo_avgs as $strategy => $values ) {
			if ( empty( $values ) ) {
				continue;
			}

			SEOB_Metrics::record( 'pagespeed', 'seo_avg_' . $strategy, round( array_sum( $values ) / count( $values ), 1 ) );
		}

		$wpdb->update(
			SEOB_Database::psi_runs_table(),
			[
				'finished_at' => current_time( 'mysql' ),
				'status'      => 'done',
			],
			[ 'id' => $run_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		$this->prune_history();
	}

	/**
	 * Spočítá průměrná skóre, velikost vzorku a nejčastější SEO nálezy pro skupinu výsledků
	 * se stejným typem obsahu a strategií. Čistá funkce – testovatelná bez DB.
	 *
	 * @param array<int,array<string,mixed>> $rows Řádky z psi_results (asociativní pole).
	 *
	 * @return array{performance_avg:?int,accessibility_avg:?int,best_practices_avg:?int,seo_avg:?int,sample_size:int,common_issues:array,sample_object_ids:array}
	 */
	public static function aggregate_group( array $rows ): array {
		$averages = [];

		foreach ( [ 'performance_score', 'accessibility_score', 'best_practices_score', 'seo_score' ] as $field ) {
			$values = [];

			foreach ( $rows as $row ) {
				if ( isset( $row[ $field ] ) && null !== $row[ $field ] ) {
					$values[] = (int) $row[ $field ];
				}
			}

			$averages[ $field ] = empty( $values ) ? null : (int) round( array_sum( $values ) / count( $values ) );
		}

		$issue_counts = [];

		foreach ( $rows as $row ) {
			$issues = json_decode( (string) ( $row['issues_json'] ?? '' ), true );

			if ( ! is_array( $issues ) ) {
				continue;
			}

			foreach ( $issues as $issue ) {
				if ( ! isset( $issue['id'] ) ) {
					continue;
				}

				$id = $issue['id'];

				if ( ! isset( $issue_counts[ $id ] ) ) {
					$issue_counts[ $id ] = [
						'id'          => $id,
						'title'       => $issue['title'] ?? '',
						'description' => $issue['description'] ?? '',
						'count'       => 0,
					];
				}

				$issue_counts[ $id ]['count']++;
			}
		}

		usort(
			$issue_counts,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		$sample_object_ids = [];

		foreach ( $rows as $row ) {
			$object_id = (int) ( $row['object_id'] ?? 0 );

			if ( $object_id > 0 && ! in_array( $object_id, $sample_object_ids, true ) ) {
				$sample_object_ids[] = $object_id;
			}
		}

		return [
			'performance_avg'    => $averages['performance_score'],
			'accessibility_avg'  => $averages['accessibility_score'],
			'best_practices_avg' => $averages['best_practices_score'],
			'seo_avg'            => $averages['seo_score'],
			'sample_size'        => count( $sample_object_ids ),
			'common_issues'      => array_slice( $issue_counts, 0, self::COMMON_ISSUES_LIMIT ),
			'sample_object_ids'  => $sample_object_ids,
		];
	}

	/**
	 * Smaže nejstarší dokončené běhy nad limit historie.
	 */
	private function prune_history(): void {
		global $wpdb;

		$psi_runs_table = SEOB_Database::psi_runs_table();

		$old_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$psi_runs_table} WHERE status = 'done' ORDER BY id DESC LIMIT 1000 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::HISTORY_LIMIT
			)
		);

		foreach ( $old_ids as $old_id ) {
			$this->delete_run( (int) $old_id );
		}
	}

	/**
	 * Smaže běh analýzy a všechny jeho výsledky/souhrny.
	 */
	public function delete_run( int $run_id ): bool {
		global $wpdb;

		if ( $run_id <= 0 ) {
			return false;
		}

		$wpdb->delete( SEOB_Database::psi_results_table(), [ 'run_id' => $run_id ], [ '%d' ] );
		$wpdb->delete( SEOB_Database::psi_summary_table(), [ 'run_id' => $run_id ], [ '%d' ] );
		$wpdb->delete( SEOB_Database::psi_runs_table(), [ 'id' => $run_id ], [ '%d' ] );

		return true;
	}

	/**
	 * Vrátí historii dokončených běhů (nejnovější první) pro výběr v dashboardu.
	 *
	 * @return array<int,array{id:int,started_at:string,finished_at:?string,items_total:int}>
	 */
	public function get_run_history( int $limit = self::HISTORY_LIMIT ): array {
		global $wpdb;

		$psi_runs_table = SEOB_Database::psi_runs_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, started_at, finished_at, items_total FROM {$psi_runs_table} WHERE status = 'done' ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Vrátí aktuálně běžící scan (pokud existuje) – pro obnovení progress baru po znovunačtení stránky.
	 *
	 * @return array{run_id:int,done:int,total:int,queue:array<int,array<string,mixed>>}|null
	 */
	public function get_active_run(): ?array {
		global $wpdb;

		$psi_runs_table = SEOB_Database::psi_runs_table();

		$run = $wpdb->get_row(
			"SELECT * FROM {$psi_runs_table} WHERE status = 'running' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $run ) {
			return null;
		}

		$queue = get_transient( 'seob_psi_queue_' . $run['id'] );

		return [
			'run_id' => (int) $run['id'],
			'done'   => (int) $run['items_done'],
			'total'  => (int) $run['items_total'],
			'queue'  => is_array( $queue ) ? $queue : [],
		];
	}

	/**
	 * Vrátí daný dokončený běh (nebo poslední dokončený) + souhrny podle typu obsahu se vzorkem stránek
	 * a porovnání (delta) oproti předchozímu dokončenému běhu.
	 */
	public function get_results( ?int $run_id = null ): ?array {
		global $wpdb;

		$psi_runs_table    = SEOB_Database::psi_runs_table();
		$psi_summary_table = SEOB_Database::psi_summary_table();

		if ( $run_id ) {
			$run = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$psi_runs_table} WHERE id = %d AND status = 'done'", $run_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		} else {
			$run = $wpdb->get_row(
				"SELECT * FROM {$psi_runs_table} WHERE status = 'done' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		}

		if ( ! $run ) {
			return null;
		}

		$summary_rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$psi_summary_table} WHERE run_id = %d ORDER BY object_type, strategy", $run['id'] ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$previous_run = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$psi_runs_table} WHERE status = 'done' AND id < %d ORDER BY id DESC LIMIT 1", $run['id'] ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$previous_summary = [];
		$previous_rows    = [];

		if ( $previous_run ) {
			$previous_rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$psi_summary_table} WHERE run_id = %d", $previous_run['id'] ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);

			foreach ( $previous_rows as $prev_row ) {
				$previous_summary[ $prev_row['object_type'] . '|' . $prev_row['strategy'] ] = $prev_row;
			}
		}

		$groups = [];

		foreach ( $summary_rows as $row ) {
			$common_issues     = json_decode( (string) $row['common_issues_json'], true );
			$sample_object_ids = json_decode( (string) $row['sample_object_ids_json'], true );

			$row['common_issues'] = is_array( $common_issues ) ? $common_issues : [];
			$row['samples']       = [];

			foreach ( ( is_array( $sample_object_ids ) ? $sample_object_ids : [] ) as $object_id ) {
				$row['samples'][] = [
					'object_id' => (int) $object_id,
					'title'     => get_the_title( (int) $object_id ),
					'edit_link' => (string) get_edit_post_link( (int) $object_id, 'raw' ),
					'view_link' => (string) get_permalink( (int) $object_id ),
				];
			}

			unset( $row['common_issues_json'], $row['sample_object_ids_json'] );

			$post_type_object     = get_post_type_object( $row['object_type'] );
			$row['object_type_label'] = $post_type_object ? $post_type_object->labels->name : $row['object_type'];

			$prev = $previous_summary[ $row['object_type'] . '|' . $row['strategy'] ] ?? null;
			$row['deltas'] = self::compute_deltas( $row, $prev );

			$groups[ $row['object_type'] ][] = $row;
		}

		$overall = [];

		foreach ( self::STRATEGIES as $strategy ) {
			$rows_for_strategy      = array_values( array_filter( $summary_rows, fn( $row ) => $row['strategy'] === $strategy ) );
			$prev_rows_for_strategy = array_values( array_filter( $previous_rows, fn( $row ) => $row['strategy'] === $strategy ) );

			$overall[ $strategy ]            = self::compute_overall_scores( $rows_for_strategy );
			$overall[ $strategy ]['deltas']  = self::compute_deltas(
				$overall[ $strategy ],
				$previous_run ? self::compute_overall_scores( $prev_rows_for_strategy ) : null
			);
		}

		return [
			'run'           => $run,
			'previous_run'  => $previous_run ? (int) $previous_run['id'] : null,
			'groups'        => $groups,
			'overall'       => $overall,
		];
	}

	/**
	 * Spočítá souhrnné (vážené) průměry skóre přes všechny typy obsahu pro danou strategii.
	 * Čistá funkce – testovatelná bez DB.
	 *
	 * @param array<int,array<string,mixed>> $rows Řádky psi_summary pro jednu strategii.
	 *
	 * @return array{performance_avg:?int,accessibility_avg:?int,best_practices_avg:?int,seo_avg:?int,sample_size:int}
	 */
	public static function compute_overall_scores( array $rows ): array {
		$result = [];

		foreach ( [ 'performance_avg', 'accessibility_avg', 'best_practices_avg', 'seo_avg' ] as $field ) {
			$sum    = 0;
			$weight = 0;

			foreach ( $rows as $row ) {
				if ( null === $row[ $field ] ) {
					continue;
				}

				$item_weight = max( 1, (int) ( $row['sample_size'] ?? 1 ) );
				$sum        += (int) $row[ $field ] * $item_weight;
				$weight     += $item_weight;
			}

			$result[ $field ] = $weight > 0 ? (int) round( $sum / $weight ) : null;
		}

		$result['sample_size'] = array_sum( array_map( fn( $row ) => (int) ( $row['sample_size'] ?? 0 ), $rows ) );

		return $result;
	}

	/**
	 * Spočítá rozdíly skóre oproti předchozímu běhu (pro stejný typ obsahu + strategii).
	 * Čistá funkce – testovatelná bez DB.
	 *
	 * @param array<string,mixed>      $row  Aktuální souhrnný řádek.
	 * @param array<string,mixed>|null $prev Souhrnný řádek z předchozího běhu (nebo null).
	 *
	 * @return array{performance_avg:?int,accessibility_avg:?int,best_practices_avg:?int,seo_avg:?int}
	 */
	public static function compute_deltas( array $row, ?array $prev ): array {
		$deltas = [];

		foreach ( [ 'performance_avg', 'accessibility_avg', 'best_practices_avg', 'seo_avg' ] as $field ) {
			if ( null === $prev || null === $row[ $field ] || null === $prev[ $field ] ) {
				$deltas[ $field ] = null;
				continue;
			}

			$deltas[ $field ] = (int) $row[ $field ] - (int) $prev[ $field ];
		}

		return $deltas;
	}

	/**
	 * Vrátí veřejné post types (kromě přílohy) s alespoň jednou publikovanou položkou.
	 *
	 * @return array<int,string>
	 */
	private function get_sample_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );

		$result = [];

		foreach ( $post_types as $post_type ) {
			$counts = wp_count_posts( $post_type );

			if ( ! empty( $counts->publish ) ) {
				$result[] = $post_type;
			}
		}

		return $result;
	}
}
