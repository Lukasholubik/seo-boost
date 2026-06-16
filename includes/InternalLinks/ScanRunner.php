<?php
/**
 * Řídí běh reindexu interních odkazů: dávkové zpracování fronty příspěvků,
 * přepočet TF-IDF návrhů prolinkování a souhrn pro dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_ScanRunner {

	private const QUEUE_TTL         = HOUR_IN_SECONDS;
	private const HISTORY_LIMIT     = 10;
	private const BATCH_SIZE        = 5;
	private const SUGGESTIONS_LIMIT = 3;

	/**
	 * Založí nový reindex a připraví frontu příspěvků ke zpracování.
	 *
	 * @return array{run_id:int, items_total:int}
	 */
	public function start_scan(): array {
		global $wpdb;

		$post_ids = get_posts(
			[
				'post_type'      => SEOB_Audit_ScanRunner::get_audit_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$table = SEOB_Database::link_scans_table();

		$wpdb->insert(
			$table,
			[
				'started_at'  => current_time( 'mysql' ),
				'posts_total' => count( $post_ids ),
				'posts_done'  => 0,
				'status'      => 'running',
			],
			[ '%s', '%d', '%d', '%s' ]
		);

		$run_id = (int) $wpdb->insert_id;

		set_transient( 'seob_links_queue_' . $run_id, $post_ids, self::QUEUE_TTL );

		if ( empty( $post_ids ) ) {
			$this->finalize_scan( $run_id );
		}

		return [
			'run_id'      => $run_id,
			'items_total' => count( $post_ids ),
		];
	}

	/**
	 * Zpracuje jednu dávku příspěvků z fronty – přepočte jejich interní odkazy.
	 *
	 * @return array{done:int,total:int,finished:bool}
	 */
	public function process_batch( int $run_id, int $batch_size = self::BATCH_SIZE ): array {
		global $wpdb;

		$table = SEOB_Database::link_scans_table();

		$run = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $run_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $run ) {
			return [ 'done' => 0, 'total' => 0, 'finished' => true ];
		}

		if ( 'done' === $run->status ) {
			return [
				'done'     => (int) $run->posts_done,
				'total'    => (int) $run->posts_total,
				'finished' => true,
			];
		}

		$queue = get_transient( 'seob_links_queue_' . $run_id );

		if ( ! is_array( $queue ) ) {
			$queue = [];
		}

		$batch = array_splice( $queue, 0, max( 1, $batch_size ) );

		$extractor   = new SEOB_InternalLinks_Extractor();
		$links_table = SEOB_Database::internal_links_table();

		foreach ( $batch as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$wpdb->delete( $links_table, [ 'source_id' => $post_id ], [ '%d' ] );

			foreach ( $extractor->extract_links( $post ) as $link ) {
				$wpdb->insert(
					$links_table,
					[
						'source_id'   => $post_id,
						'source_type' => $post->post_type,
						'target_id'   => $link['target_id'],
						'link_text'   => mb_substr( $link['link_text'], 0, 255 ),
					],
					[ '%d', '%s', '%d', '%s' ]
				);
			}
		}

		$processed  = count( $batch );
		$posts_done = (int) $run->posts_done + $processed;

		set_transient( 'seob_links_queue_' . $run_id, $queue, self::QUEUE_TTL );

		$wpdb->update(
			$table,
			[ 'posts_done' => $posts_done ],
			[ 'id' => $run_id ],
			[ '%d' ],
			[ '%d' ]
		);

		$finished = empty( $queue );

		if ( $finished ) {
			$this->finalize_scan( $run_id );
			delete_transient( 'seob_links_queue_' . $run_id );
		}

		return [
			'done'     => $posts_done,
			'total'    => (int) $run->posts_total,
			'finished' => $finished,
		];
	}

	/**
	 * Přepočte TF-IDF návrhy prolinkování pro všechny indexované příspěvky
	 * a zaznamená metriky (počet osamocených stránek, průměr odkazů).
	 */
	private function finalize_scan( int $run_id ): void {
		global $wpdb;

		$table = SEOB_Database::link_scans_table();

		$post_ids = get_posts(
			[
				'post_type'      => SEOB_Audit_ScanRunner::get_audit_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$extractor = new SEOB_InternalLinks_Extractor();
		$documents = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$documents[ $post_id ] = SEOB_InternalLinks_Similarity::tokenize( $extractor->extract_text( $post ) );
		}

		$vectors = SEOB_InternalLinks_Similarity::build_tfidf( $documents );

		$links_table       = SEOB_Database::internal_links_table();
		$suggestions_table = SEOB_Database::link_suggestions_table();

		$existing_links = $wpdb->get_results( "SELECT source_id, target_id FROM {$links_table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$outlinks       = [];
		$inlink_counts  = [];

		foreach ( $existing_links as $row ) {
			$source = (int) $row['source_id'];
			$target = (int) $row['target_id'];

			$outlinks[ $source ][]    = $target;
			$inlink_counts[ $target ] = ( $inlink_counts[ $target ] ?? 0 ) + 1;
		}

		$wpdb->query( "TRUNCATE TABLE {$suggestions_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $post_ids as $post_id ) {
			$exclude = $outlinks[ $post_id ] ?? [];
			$top     = SEOB_InternalLinks_Similarity::top_similar( $vectors, $post_id, $exclude, self::SUGGESTIONS_LIMIT );
			$rank    = 1;

			foreach ( $top as $item ) {
				$wpdb->insert(
					$suggestions_table,
					[
						'source_id'    => $post_id,
						'suggested_id' => $item['id'],
						'score'        => round( $item['score'], 4 ),
						'rank_order'   => $rank,
					],
					[ '%d', '%d', '%f', '%d' ]
				);

				$rank++;
			}
		}

		$total_posts   = count( $post_ids );
		$orphans       = 0;
		$total_inlinks = 0;

		foreach ( $post_ids as $post_id ) {
			$count = $inlink_counts[ $post_id ] ?? 0;
			$total_inlinks += $count;

			if ( 0 === $count ) {
				$orphans++;
			}
		}

		$avg_inlinks = $total_posts > 0 ? $total_inlinks / $total_posts : 0;

		SEOB_Metrics::record( 'internal-links', 'orphans_count', (float) $orphans );
		SEOB_Metrics::record( 'internal-links', 'avg_inlinks', round( $avg_inlinks, 2 ) );

		$wpdb->update(
			$table,
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
	 * Smaže nejstarší dokončené běhy nad limit historie.
	 */
	private function prune_history(): void {
		global $wpdb;

		$table = SEOB_Database::link_scans_table();

		$old_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = 'done' ORDER BY id DESC LIMIT 1000 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::HISTORY_LIMIT
			)
		);

		foreach ( $old_ids as $old_id ) {
			$wpdb->delete( $table, [ 'id' => (int) $old_id ], [ '%d' ] );
		}
	}

	/**
	 * Vrátí historii dokončených reindexů (nejnovější první).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_scan_history( int $limit = self::HISTORY_LIMIT ): array {
		global $wpdb;

		$table = SEOB_Database::link_scans_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'done' ORDER BY id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Vrátí aktuálně běžící reindex (pokud existuje) – pro obnovení progress baru po znovunačtení stránky.
	 *
	 * @return array{run_id:int,done:int,total:int}|null
	 */
	public function get_active_run(): ?array {
		global $wpdb;

		$table = SEOB_Database::link_scans_table();

		$run = $wpdb->get_row(
			"SELECT * FROM {$table} WHERE status = 'running' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $run ) {
			return null;
		}

		return [
			'run_id' => (int) $run['id'],
			'done'   => (int) $run['posts_done'],
			'total'  => (int) $run['posts_total'],
		];
	}

	/**
	 * Vrátí souhrn pro dashboard – počty, osamocené stránky s návrhy a tabulku všech stránek.
	 */
	public function get_results(): array {
		global $wpdb;

		$links_table       = SEOB_Database::internal_links_table();
		$suggestions_table = SEOB_Database::link_suggestions_table();
		$scans_table       = SEOB_Database::link_scans_table();

		$last_run = $wpdb->get_row(
			"SELECT * FROM {$scans_table} WHERE status = 'done' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$post_ids = get_posts(
			[
				'post_type'      => SEOB_Audit_ScanRunner::get_audit_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$inlink_rows = $wpdb->get_results(
			"SELECT target_id, COUNT(*) AS cnt FROM {$links_table} GROUP BY target_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$outlink_rows = $wpdb->get_results(
			"SELECT source_id, COUNT(*) AS cnt FROM {$links_table} GROUP BY source_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$inlinks  = [];
		$outlinks = [];

		foreach ( $inlink_rows as $row ) {
			$inlinks[ (int) $row['target_id'] ] = (int) $row['cnt'];
		}

		foreach ( $outlink_rows as $row ) {
			$outlinks[ (int) $row['source_id'] ] = (int) $row['cnt'];
		}

		// Bulk fetch word counts pro výpočet doporučeného počtu odkazů (2–5 / 1000 slov).
		$word_counts = [];
		if ( ! empty( $post_ids ) ) {
			$wc_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			$wc_rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ({$wc_placeholders})", ...$post_ids ),
				ARRAY_A
			);
			foreach ( $wc_rows as $wc_row ) {
				$wc_plain = wp_strip_all_tags( $wc_row['post_content'] );
				$word_counts[ (int) $wc_row['ID'] ] = $wc_plain
					? count( array_filter( preg_split( '/\s+/u', trim( $wc_plain ) ) ) )
					: 0;
			}
		}

		$pages   = [];
		$orphans = [];

		foreach ( $post_ids as $post_id ) {
			$inlink_count  = $inlinks[ $post_id ] ?? 0;
			$outlink_count = $outlinks[ $post_id ] ?? 0;

			$word_count  = $word_counts[ $post_id ] ?? 0;
			$link_min    = max( 1, (int) round( $word_count / 500 ) );
			$link_max    = max( 2, (int) round( $word_count / 200 ) );
			$link_status = ( $outlink_count >= $link_min && $outlink_count <= $link_max )
				? 'ok'
				: ( $outlink_count < $link_min ? 'low' : 'high' );

			$page = [
				'id'          => $post_id,
				'title'       => get_the_title( $post_id ),
				'type'        => get_post_type( $post_id ),
				'edit_link'   => (string) get_edit_post_link( $post_id, 'raw' ),
				'view_link'   => (string) get_permalink( $post_id ),
				'inlinks'     => $inlink_count,
				'outlinks'    => $outlink_count,
				'word_count'  => $word_count,
				'link_min'    => $link_min,
				'link_max'    => $link_max,
				'link_status' => $link_status,
			];

			$pages[] = $page;

			if ( 0 === $inlink_count ) {
				$suggestions = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT suggested_id, score FROM {$suggestions_table} WHERE source_id = %d ORDER BY rank_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$post_id
					),
					ARRAY_A
				);

				$page['suggestions'] = array_map(
					static function ( $row ) {
						$id = (int) $row['suggested_id'];

						return [
							'id'        => $id,
							'title'     => get_the_title( $id ),
							'score'     => (float) $row['score'],
							'edit_link' => (string) get_edit_post_link( $id, 'raw' ),
						];
					},
					$suggestions
				);

				$orphans[] = $page;
			}
		}

		usort(
			$pages,
			static function ( $a, $b ) {
				return $a['inlinks'] <=> $b['inlinks'];
			}
		);

		// Seskupit osamocené stránky a všechny stránky podle post type.
		$orphan_groups = [];
		foreach ( $orphans as $item ) {
			$t = $item['type'];
			$orphan_groups[ $t ] ??= [
				'type'  => $t,
				'label' => self::get_post_type_label( $t ),
				'items' => [],
			];
			$orphan_groups[ $t ]['items'][] = $item;
		}
		usort( $orphan_groups, static fn( $a, $b ) => count( $b['items'] ) <=> count( $a['items'] ) );

		$page_groups = [];
		foreach ( $pages as $item ) {
			$t = $item['type'];
			$page_groups[ $t ] ??= [
				'type'             => $t,
				'label'            => self::get_post_type_label( $t ),
				'orphans_in_group' => 0,
				'items'            => [],
			];
			$page_groups[ $t ]['items'][] = $item;
			if ( 0 === $item['inlinks'] ) {
				$page_groups[ $t ]['orphans_in_group']++;
			}
		}
		usort( $page_groups, static fn( $a, $b ) => count( $b['items'] ) <=> count( $a['items'] ) );

		// Celkové hodnocení zdraví odchozích odkazů.
		$link_ok   = 0;
		$link_low  = 0;
		$link_high = 0;
		foreach ( $pages as $p ) {
			if ( 'ok' === $p['link_status'] )        { $link_ok++; }
			elseif ( 'low' === $p['link_status'] )   { $link_low++; }
			elseif ( 'high' === $p['link_status'] )  { $link_high++; }
		}
		$total_pages       = count( $pages );
		$link_health_score = $total_pages > 0 ? (int) round( $link_ok / $total_pages * 100 ) : 0;

		return [
			'run'                => $last_run,
			'posts_total'        => count( $post_ids ),
			'orphans_count'      => count( $orphans ),
			'avg_inlinks'        => count( $post_ids ) > 0 ? round( array_sum( $inlinks ) / count( $post_ids ), 2 ) : 0,
			'trends'             => [
				'orphans_count' => self::compute_trend_delta( 'orphans_count' ),
				'avg_inlinks'   => self::compute_trend_delta( 'avg_inlinks' ),
			],
			'link_health_score'  => $link_health_score,
			'link_ok_count'      => $link_ok,
			'link_low_count'     => $link_low,
			'link_high_count'    => $link_high,
			'orphans'            => $orphans,
			'pages'              => $pages,
			'orphan_groups'      => array_values( $orphan_groups ),
			'page_groups'        => array_values( $page_groups ),
		];
	}

	/**
	 * Vrátí lidsky čitelný label post type (např. "Příspěvky", "Stránky", "Slovíček pojmů").
	 */
	private static function get_post_type_label( string $post_type ): string {
		$obj = get_post_type_object( $post_type );
		if ( $obj && ! empty( $obj->labels->name ) ) {
			return $obj->labels->name;
		}
		return $post_type;
	}

	/**
	 * Spočítá rozdíl poslední dvou zaznamenaných hodnot metriky (aktuální − předchozí).
	 */
	private static function compute_trend_delta( string $metric_key ): ?float {
		$values = SEOB_Metrics::get_trend( 'internal-links', $metric_key, 2 );

		if ( count( $values ) < 2 ) {
			return null;
		}

		return round( $values[1]['value'] - $values[0]['value'], 2 );
	}
}
