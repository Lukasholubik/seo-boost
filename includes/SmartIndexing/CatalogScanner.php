<?php
/**
 * Analýza katalogových kombinací (obor / služba / lokalita) a detailů firem
 * pro modul Chytrá indexace (M14, profil KATALOG).
 *
 * Bez napojení na Search Console (M7) se skóre počítá jen ze strukturálních
 * signálů (počet firem, úplnost profilu) – plný skórovací model z kap. 4.3
 * zadání přijde ve druhé iteraci.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_SmartIndexing_Catalog_Scanner {

	const MAX_COMBOS = 500;

	public function run_scan(): array {
		$settings = SEOB_Settings::get( SEOB_Settings::SMART_INDEXING );
		$results  = [];

		if ( $settings['company_post_type'] && post_type_exists( $settings['company_post_type'] ) ) {
			$results = array_merge( $results, $this->scan_company_details( $settings ) );
		}

		if ( $settings['category_taxonomy'] && taxonomy_exists( $settings['category_taxonomy'] ) ) {
			$results = array_merge( $results, $this->scan_categories( $settings ) );

			if ( $settings['location_taxonomy'] && taxonomy_exists( $settings['location_taxonomy'] ) ) {
				$results = array_merge( $results, $this->scan_category_location( $settings ) );
			}

			if ( $settings['service_taxonomy'] && taxonomy_exists( $settings['service_taxonomy'] ) && $settings['location_taxonomy'] ) {
				$results = array_merge( $results, $this->scan_service_location( $settings ) );
			}
		}

		$this->store_results( $results );

		return $results;
	}

	/**
	 * Detaily firem: index, pokud profil splňuje práh úplnosti, jinak noindex.
	 */
	private function scan_company_details( array $settings ): array {
		$results = [];

		$post_ids = get_posts( [
			'post_type'      => $settings['company_post_type'],
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'fields'         => 'ids',
		] );

		foreach ( $post_ids as $post_id ) {
			$score  = $this->company_completeness( (int) $post_id, $settings );
			$tier   = $score >= (int) $settings['completeness_threshold'] ? 'A' : 'B';
			$reason = 'A' === $tier ? 'rule_complete_profile' : 'rule_thin_profile';

			$results[] = [
				'url'          => get_permalink( $post_id ),
				'page_type'    => 'company_detail',
				'dimensions'   => [ 'post_id' => (int) $post_id ],
				'tier'         => $tier,
				'tier_reason'  => $reason,
				'score'        => $score,
				'result_count' => null,
			];
		}

		return $results;
	}

	/**
	 * Procentuální úplnost profilu firmy (0-100): obsah, foto, perex,
	 * obor a lokalita (pokud jsou taxonomie nakonfigurované).
	 */
	private function company_completeness( int $post_id, array $settings ): int {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return 0;
		}

		$checks = 0;
		$passed = 0;

		++$checks;
		if ( str_word_count( wp_strip_all_tags( $post->post_content ) ) >= 50 ) {
			++$passed;
		}

		++$checks;
		if ( has_post_thumbnail( $post_id ) ) {
			++$passed;
		}

		++$checks;
		if ( '' !== trim( $post->post_excerpt ) ) {
			++$passed;
		}

		if ( $settings['category_taxonomy'] && taxonomy_exists( $settings['category_taxonomy'] ) ) {
			++$checks;
			if ( ! empty( get_the_terms( $post_id, $settings['category_taxonomy'] ) ) ) {
				++$passed;
			}
		}

		if ( $settings['location_taxonomy'] && taxonomy_exists( $settings['location_taxonomy'] ) ) {
			++$checks;
			if ( ! empty( get_the_terms( $post_id, $settings['location_taxonomy'] ) ) ) {
				++$passed;
			}
		}

		return $checks > 0 ? (int) round( $passed / $checks * 100 ) : 0;
	}

	/**
	 * Hlavní stránky oborů – vždy index (Tier A).
	 */
	private function scan_categories( array $settings ): array {
		$results = [];
		$terms   = get_terms( [ 'taxonomy' => $settings['category_taxonomy'], 'hide_empty' => false ] );

		if ( is_wp_error( $terms ) ) {
			return $results;
		}

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );

			if ( is_wp_error( $link ) ) {
				continue;
			}

			$count = $this->count_posts_for_terms( $settings, [ $settings['category_taxonomy'] => [ $term->slug ] ] );

			$results[] = [
				'url'          => $link,
				'page_type'    => 'category',
				'dimensions'   => [ 'category' => $term->slug ],
				'tier'         => 'A',
				'tier_reason'  => 'rule_category',
				'score'        => 100,
				'result_count' => $count,
			];
		}

		return $results;
	}

	/**
	 * Kombinace obor × lokalita – index při dosažení min. počtu firem,
	 * jinak kandidát/noindex (kap. 4.2).
	 */
	private function scan_category_location( array $settings ): array {
		$results   = [];
		$cat_terms = get_terms( [ 'taxonomy' => $settings['category_taxonomy'], 'hide_empty' => false ] );
		$loc_terms = get_terms( [ 'taxonomy' => $settings['location_taxonomy'], 'hide_empty' => false ] );

		if ( is_wp_error( $cat_terms ) || is_wp_error( $loc_terms ) ) {
			return $results;
		}

		$combos = 0;

		foreach ( $cat_terms as $cat ) {
			foreach ( $loc_terms as $loc ) {
				if ( ++$combos > self::MAX_COMBOS ) {
					return $results;
				}

				$count = $this->count_posts_for_terms( $settings, [
					$settings['category_taxonomy'] => [ $cat->slug ],
					$settings['location_taxonomy'] => [ $loc->slug ],
				] );

				if ( 0 === $count ) {
					continue;
				}

				[ $tier, $reason ] = $this->classify_count( $count, (int) $settings['min_companies'] );

				$results[] = [
					'url'          => home_url( '/' . $cat->slug . '/' . $loc->slug . '/' ),
					'page_type'    => 'category_city',
					'dimensions'   => [ 'category' => $cat->slug, 'location' => $loc->slug ],
					'tier'         => $tier,
					'tier_reason'  => $reason,
					'score'        => min( 100, (int) round( $count / max( 1, (int) $settings['min_companies'] ) * 100 ) ),
					'result_count' => $count,
				];
			}
		}

		return $results;
	}

	/**
	 * Kombinace služba × lokalita – vždy kandidát k ručnímu schválení (kap. 4.7).
	 */
	private function scan_service_location( array $settings ): array {
		$results   = [];
		$svc_terms = get_terms( [ 'taxonomy' => $settings['service_taxonomy'], 'hide_empty' => false ] );
		$loc_terms = get_terms( [ 'taxonomy' => $settings['location_taxonomy'], 'hide_empty' => false ] );

		if ( is_wp_error( $svc_terms ) || is_wp_error( $loc_terms ) ) {
			return $results;
		}

		$combos = 0;

		foreach ( $svc_terms as $svc ) {
			foreach ( $loc_terms as $loc ) {
				if ( ++$combos > self::MAX_COMBOS ) {
					return $results;
				}

				$count = $this->count_posts_for_terms( $settings, [
					$settings['service_taxonomy']  => [ $svc->slug ],
					$settings['location_taxonomy'] => [ $loc->slug ],
				] );

				if ( 0 === $count ) {
					continue;
				}

				$results[] = [
					'url'          => home_url( '/' . $svc->slug . '/' . $loc->slug . '/' ),
					'page_type'    => 'service_city',
					'dimensions'   => [ 'service' => $svc->slug, 'location' => $loc->slug ],
					'tier'         => 'B',
					'tier_reason'  => 'candidate_manual_review',
					'score'        => min( 100, (int) round( $count / max( 1, (int) $settings['min_companies'] ) * 100 ) ),
					'result_count' => $count,
				];
			}
		}

		return $results;
	}

	private function classify_count( int $count, int $min_companies ): array {
		if ( $count >= $min_companies ) {
			return [ 'A', 'rule_min_companies' ];
		}

		if ( $count >= 3 ) {
			return [ 'B', 'candidate' ];
		}

		return [ 'B', 'too_few' ];
	}

	private function count_posts_for_terms( array $settings, array $terms_by_taxonomy ): int {
		$tax_query = [];

		foreach ( $terms_by_taxonomy as $taxonomy => $slugs ) {
			$tax_query[] = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
			];
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		$query = new WP_Query( [
			'post_type'      => $settings['company_post_type'] ?: 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => $tax_query, // phpcs:ignore Slow_DB_Query
			'no_found_rows'  => false,
		] );

		return (int) $query->found_posts;
	}

	/**
	 * Uloží/aktualizuje výsledky v `facet_urls`. Ruční rozhodnutí
	 * (schválení/odmítnutí) se při novém scanu nepřepisují.
	 */
	private function store_results( array $results ): void {
		global $wpdb;

		$table = SEOB_Database::facet_urls_table();
		$now   = current_time( 'mysql' );

		foreach ( $results as $result ) {
			if ( empty( $result['url'] ) || is_wp_error( $result['url'] ) ) {
				continue;
			}

			$normalized = SEOB_SmartIndexing_Helper::normalize_url( $result['url'] );
			$hash       = SEOB_SmartIndexing_Helper::url_hash( $normalized );

			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, tier_reason FROM {$table} WHERE url_hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$data = [
				'url_normalized'  => $normalized,
				'url_hash'        => $hash,
				'page_type'       => $result['page_type'],
				'dimensions_json' => wp_json_encode( $result['dimensions'] ),
				'score'           => $result['score'],
				'result_count'    => $result['result_count'],
				'scanned_at'      => $now,
			];

			$manual = $existing && in_array( $existing->tier_reason, [ 'approved_manual', 'demoted_manual' ], true );

			if ( ! $manual ) {
				$data['tier']        = $result['tier'];
				$data['tier_reason'] = $result['tier_reason'];
			}

			if ( $existing ) {
				$wpdb->update( $table, $data, [ 'id' => $existing->id ] );
			} else {
				$data['tier']        = $result['tier'];
				$data['tier_reason'] = $result['tier_reason'];
				$wpdb->insert( $table, $data );
			}
		}
	}
}
