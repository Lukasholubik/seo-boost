<?php
/**
 * Řídí běh scanu: založení běhu, dávkové zpracování fronty URL, finalizace.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Audit_ScanRunner {

	private const QUEUE_TTL = HOUR_IN_SECONDS;

	/**
	 * Založí nový běh scanu a připraví frontu URL ke zpracování.
	 *
	 * @return array{scan_id:int,urls_total:int}
	 */
	public function start_scan( string $trigger_type = 'manual' ): array {
		global $wpdb;

		$post_ids = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		$scan_runs_table = SEOB_Database::scan_runs_table();

		$wpdb->insert(
			$scan_runs_table,
			[
				'started_at'   => current_time( 'mysql' ),
				'trigger_type' => substr( $trigger_type, 0, 10 ),
				'urls_total'   => count( $post_ids ),
				'urls_done'    => 0,
				'status'       => 'running',
			],
			[ '%s', '%s', '%d', '%d', '%s' ]
		);

		$scan_id = (int) $wpdb->insert_id;

		set_transient( 'seob_scan_queue_' . $scan_id, $post_ids, self::QUEUE_TTL );

		return [
			'scan_id'    => $scan_id,
			'urls_total' => count( $post_ids ),
		];
	}

	/**
	 * Zpracuje jednu dávku URL z fronty.
	 *
	 * @return array{done:int,total:int,finished:bool,score_avg:?int}
	 */
	public function process_batch( int $scan_id, int $batch_size ): array {
		global $wpdb;

		$scan_runs_table = SEOB_Database::scan_runs_table();
		$audit_table     = SEOB_Database::audit_table();

		$run = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$scan_runs_table} WHERE id = %d", $scan_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $run ) {
			return [ 'done' => 0, 'total' => 0, 'finished' => true, 'score_avg' => null ];
		}

		$queue = get_transient( 'seob_scan_queue_' . $scan_id );

		if ( ! is_array( $queue ) ) {
			$queue = [];
		}

		$batch = array_splice( $queue, 0, max( 1, $batch_size ) );

		$scanner = new SEOB_Audit_Scanner();

		foreach ( $batch as $post_id ) {
			$result = $scanner->scan_post( (int) $post_id );

			if ( null === $result ) {
				continue;
			}

			$wpdb->delete(
				$audit_table,
				[ 'scan_id' => $scan_id, 'object_id' => $result['object_id'] ],
				[ '%d', '%d' ]
			);

			$wpdb->insert(
				$audit_table,
				[
					'scan_id'      => $scan_id,
					'object_id'    => $result['object_id'],
					'object_type'  => $result['object_type'],
					'url'          => $result['url'],
					'score'        => $result['score'],
					'issues_json'  => wp_json_encode( $result['issues'] ),
					'content_hash' => $result['content_hash'],
					'scanned_at'   => current_time( 'mysql' ),
				],
				[ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
			);
		}

		$processed = count( $batch );
		$urls_done = (int) $run->urls_done + $processed;

		set_transient( 'seob_scan_queue_' . $scan_id, $queue, self::QUEUE_TTL );

		$wpdb->update(
			$scan_runs_table,
			[ 'urls_done' => $urls_done ],
			[ 'id' => $scan_id ],
			[ '%d' ],
			[ '%d' ]
		);

		$finished   = empty( $queue );
		$score_avg  = null;

		if ( $finished ) {
			$score_avg = $this->finalize_scan( $scan_id );
			delete_transient( 'seob_scan_queue_' . $scan_id );
		}

		return [
			'done'      => $urls_done,
			'total'     => (int) $run->urls_total,
			'finished'  => $finished,
			'score_avg' => $score_avg,
		];
	}

	/**
	 * Doplní nálezy o duplicitní title/description napříč scanem,
	 * dopočítá průměrné skóre a uzavře běh.
	 */
	private function finalize_scan( int $scan_id ): int {
		global $wpdb;

		$audit_table = SEOB_Database::audit_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, object_id, score, issues_json FROM {$audit_table} WHERE scan_id = %d", $scan_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$titles       = [];
		$descriptions = [];

		foreach ( $rows as $row ) {
			$title = get_the_title( (int) $row->object_id );
			$desc  = (string) get_post_meta( (int) $row->object_id, 'rank_math_description', true );

			if ( '' !== trim( $title ) ) {
				$titles[ $title ][] = $row;
			}

			if ( '' !== trim( $desc ) ) {
				$descriptions[ $desc ][] = $row;
			}
		}

		$updates = [];

		foreach ( [ 'duplicate_title' => $titles, 'duplicate_description' => $descriptions ] as $issue_type => $groups ) {
			foreach ( $groups as $group_rows ) {
				if ( count( $group_rows ) < 2 ) {
					continue;
				}

				foreach ( $group_rows as $row ) {
					$updates[ $row->id ]['issues'][] = [
						'type'     => $issue_type,
						'severity' => 'warning',
						'detail'   => null,
					];
				}
			}
		}

		foreach ( $rows as $row ) {
			if ( empty( $updates[ $row->id ] ) ) {
				continue;
			}

			$issues    = json_decode( (string) $row->issues_json, true );
			$issues    = is_array( $issues ) ? $issues : [];
			$new_score = (int) $row->score;

			foreach ( $updates[ $row->id ]['issues'] as $extra_issue ) {
				$issues[]   = $extra_issue;
				$new_score -= 7;
			}

			$new_score = max( 0, min( 100, $new_score ) );

			$wpdb->update(
				$audit_table,
				[
					'issues_json' => wp_json_encode( $issues ),
					'score'       => $new_score,
				],
				[ 'id' => $row->id ],
				[ '%s', '%d' ],
				[ '%d' ]
			);

			$row->score = $new_score;
		}

		$score_avg = 0;

		if ( count( $rows ) > 0 ) {
			$sum = 0;
			foreach ( $rows as $row ) {
				$sum += (int) $row->score;
			}
			$score_avg = (int) round( $sum / count( $rows ) );
		}

		$wpdb->update(
			SEOB_Database::scan_runs_table(),
			[
				'finished_at' => current_time( 'mysql' ),
				'status'      => 'done',
				'score_avg'   => $score_avg,
			],
			[ 'id' => $scan_id ],
			[ '%s', '%s', '%d' ],
			[ '%d' ]
		);

		return $score_avg;
	}

	/**
	 * Vrátí poslední běh scanu (libovolný stav).
	 */
	public function get_latest_scan(): ?array {
		global $wpdb;

		$scan_runs_table = SEOB_Database::scan_runs_table();

		$row = $wpdb->get_row(
			"SELECT * FROM {$scan_runs_table} ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Vrátí výsledky daného scanu (nebo posledního dokončeného) pro dashboard.
	 *
	 * @return array{summary:array,rows:array}
	 */
	public function get_results( ?int $scan_id = null ): array {
		global $wpdb;

		$audit_table     = SEOB_Database::audit_table();
		$scan_runs_table = SEOB_Database::scan_runs_table();

		if ( null === $scan_id ) {
			$scan_id = (int) $wpdb->get_var(
				"SELECT id FROM {$scan_runs_table} WHERE status = 'done' ORDER BY id DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		if ( ! $scan_id ) {
			return [ 'summary' => null, 'rows' => [] ];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$audit_table} WHERE scan_id = %d ORDER BY score ASC", $scan_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$counts = [ 'critical' => 0, 'warning' => 0, 'recommendation' => 0 ];

		foreach ( $rows as &$row ) {
			$issues = json_decode( (string) $row['issues_json'], true );
			$row['issues'] = is_array( $issues ) ? $issues : [];

			foreach ( $row['issues'] as $issue ) {
				if ( isset( $counts[ $issue['severity'] ] ) ) {
					$counts[ $issue['severity'] ]++;
				}
			}

			$row['title']     = get_the_title( (int) $row['object_id'] );
			$row['edit_link'] = (string) get_edit_post_link( (int) $row['object_id'], 'raw' );
		}
		unset( $row );

		$run = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$scan_runs_table} WHERE id = %d", $scan_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return [
			'summary' => array_merge( (array) $run, [ 'counts' => $counts ] ),
			'rows'    => $rows,
		];
	}
}
