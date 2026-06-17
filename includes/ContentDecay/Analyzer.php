<?php
/**
 * Content Decay Analyzer – vyhodnotí decay signály pro jeden příspěvek.
 *
 * Vstup:  post_id + předem načtená GSC data (aby Scanner mohl dělat bulk queries).
 * Výstup: strukturovaný výsledek s decay_score 0–100 a seznamem signálů.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_ContentDecay_Analyzer {

	/**
	 * Analyzuj jeden příspěvek.
	 *
	 * @param int        $post_id
	 * @param array|null $gsc  { current_clicks, previous_clicks, current_position, previous_position }
	 * @return array
	 */
	public static function analyze( int $post_id, ?array $gsc ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$signals   = [];
		$score     = 0;
		$days_old  = (int) round( ( time() - strtotime( $post->post_modified ) ) / DAY_IN_SECONDS );
		$months_old = $days_old / 30;

		// ── 1. Věk obsahu ────────────────────────────────────────────────────────

		if ( $months_old > 24 ) {
			$signals[] = [
				'type'     => 'age_critical',
				'severity' => 'critical',
				'label'    => sprintf( 'Nezměněno %.0f měs.', $months_old ),
				'message'  => sprintf( 'Obsah nebyl upraven %.0f měsíců (od %s). Google preferuje čerstvý obsah.', $months_old, mysql2date( 'j. n. Y', $post->post_modified ) ),
			];
			$score += 35;
		} elseif ( $months_old > 12 ) {
			$signals[] = [
				'type'     => 'age_high',
				'severity' => 'warning',
				'label'    => sprintf( 'Nezměněno %.0f měs.', $months_old ),
				'message'  => sprintf( 'Obsah nebyl upraven %.0f měsíců (od %s).', $months_old, mysql2date( 'j. n. Y', $post->post_modified ) ),
			];
			$score += 20;
		} elseif ( $months_old > 6 ) {
			$signals[] = [
				'type'     => 'age_medium',
				'severity' => 'info',
				'label'    => sprintf( 'Nezměněno %.0f měs.', $months_old ),
				'message'  => sprintf( 'Obsah nebyl upraven %.0f měsíců. Zvažte refresh.', $months_old ),
			];
			$score += 8;
		}

		// ── 2. Pokles návštěvnosti (GSC) ─────────────────────────────────────────

		if ( $gsc && $gsc['previous_clicks'] >= 5 ) {
			$drop = ( $gsc['previous_clicks'] - $gsc['current_clicks'] ) / $gsc['previous_clicks'];

			if ( $drop > 0.5 ) {
				$signals[] = [
					'type'     => 'traffic_drop_critical',
					'severity' => 'critical',
					'label'    => sprintf( 'Kliky −%.0f%%', $drop * 100 ),
					'message'  => sprintf( 'Organické kliky: %d → %d (pokles %.0f%% za posledních 30 dní vs. předchozích 30 dní).', $gsc['previous_clicks'], $gsc['current_clicks'], $drop * 100 ),
				];
				$score += 40;
			} elseif ( $drop > 0.25 ) {
				$signals[] = [
					'type'     => 'traffic_drop_high',
					'severity' => 'warning',
					'label'    => sprintf( 'Kliky −%.0f%%', $drop * 100 ),
					'message'  => sprintf( 'Organické kliky: %d → %d (pokles %.0f%%).', $gsc['previous_clicks'], $gsc['current_clicks'], $drop * 100 ),
				];
				$score += 25;
			} elseif ( $drop > 0.1 ) {
				$signals[] = [
					'type'     => 'traffic_drop_low',
					'severity' => 'info',
					'label'    => sprintf( 'Kliky −%.0f%%', $drop * 100 ),
					'message'  => sprintf( 'Mírný pokles kliků: %d → %d (%.0f%%).', $gsc['previous_clicks'], $gsc['current_clicks'], $drop * 100 ),
				];
				$score += 10;
			}
		}

		// ── 3. Pokles pozice (GSC) ────────────────────────────────────────────────

		if ( $gsc && $gsc['previous_position'] > 0 && $gsc['current_position'] > 0 ) {
			$pos_drop = $gsc['current_position'] - $gsc['previous_position']; // kladné = horší

			if ( $pos_drop > 10 ) {
				$signals[] = [
					'type'     => 'position_drop',
					'severity' => 'warning',
					'label'    => sprintf( 'Pozice −%.0f míst', $pos_drop ),
					'message'  => sprintf( 'Průměrná pozice v GSC: %.1f → %.1f (pokles o %.0f míst).', $gsc['previous_position'], $gsc['current_position'], $pos_drop ),
				];
				$score += 20;
			} elseif ( $pos_drop > 5 ) {
				$signals[] = [
					'type'     => 'position_slip',
					'severity' => 'info',
					'label'    => sprintf( 'Pozice −%.0f míst', $pos_drop ),
					'message'  => sprintf( 'Průměrná pozice: %.1f → %.1f.', $gsc['previous_position'], $gsc['current_position'] ),
				];
				$score += 8;
			}
		}

		// ── 4. Stará letní zmínka ─────────────────────────────────────────────────

		$content_text = $post->post_title . ' ' . $post->post_content;
		$current_year = (int) gmdate( 'Y' );
		preg_match_all( '/\b(20\d{2})\b/', $content_text, $year_matches );
		$old_years = array_unique( array_filter( $year_matches[1] ?? [], static fn( $y ) => (int) $y <= $current_year - 2 ) );

		if ( ! empty( $old_years ) ) {
			sort( $old_years );
			$signals[] = [
				'type'     => 'old_year_mention',
				'severity' => 'info',
				'label'    => 'Stará letní zmínka',
				'message'  => sprintf( 'Obsah zmiňuje roky: %s. Zkontrolujte, zda jsou informace stále aktuální.', implode( ', ', $old_years ) ),
			];
			$score += 7;
		}

		// ── 5. Tenký obsah ────────────────────────────────────────────────────────

		$word_count = self::count_words( $post );

		if ( $word_count > 0 && $word_count < 150 ) {
			$signals[] = [
				'type'     => 'thin_content',
				'severity' => 'warning',
				'label'    => sprintf( 'Málo obsahu (%d slov)', $word_count ),
				'message'  => 'Stránky s méně než 150 slovy jsou náchylnější k content decay – Google je snáze nahradí konkurencí.',
			];
			$score += 15;
		} elseif ( $word_count > 0 && $word_count < 300 ) {
			$signals[] = [
				'type'     => 'low_content',
				'severity' => 'info',
				'label'    => sprintf( 'Nízký počet slov (%d)', $word_count ),
				'message'  => 'Zvažte rozšíření obsahu pro posílení relevance.',
			];
			$score += 4;
		}

		$score = min( 100, $score );
		$label = self::decay_label( $score );

		return [
			'post_id'       => $post_id,
			'title'         => $post->post_title,
			'path'          => (string) wp_parse_url( (string) get_permalink( $post_id ), PHP_URL_PATH ),
			'post_type'     => $post->post_type,
			'modified_date' => $post->post_modified,
			'days_old'      => $days_old,
			'word_count'    => $word_count,
			'edit_url'      => (string) get_edit_post_link( $post_id, 'raw' ),
			'gsc'           => $gsc,
			'decay_score'   => $score,
			'decay_label'   => $label,
			'signals'       => $signals,
		];
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function decay_label( int $score ): string {
		if ( $score >= 61 ) return 'decaying';
		if ( $score >= 41 ) return 'stale';
		if ( $score >= 21 ) return 'aging';
		return 'fresh';
	}

	private static function count_words( WP_Post $post ): int {
		$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

		if ( mb_strlen( trim( $text ) ) < 20 ) {
			// Fallback: textová meta pole (JetEngine CPT, ACF…)
			$meta_text = '';
			foreach ( get_post_meta( $post->ID ) as $key => $values ) {
				if ( str_starts_with( $key, '_' ) ) {
					continue;
				}
				foreach ( $values as $val ) {
					if ( is_string( $val ) && strlen( $val ) > 20 && ! is_serialized( $val ) ) {
						$meta_text .= ' ' . wp_strip_all_tags( $val );
					}
				}
			}
			$text = $meta_text;
		}

		$text = preg_replace( '/\s+/', ' ', trim( $text ) );
		return $text ? str_word_count( $text ) : 0;
	}
}
