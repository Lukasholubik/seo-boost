<?php
/**
 * Frontend integrace Chytré indexace s Rank Math – canonical pro
 * blokované parametry a noindex pro stránky v Tier B.
 *
 * V režimu "dry_run" se filtry vůbec nezapojují (modul jen sbírá data).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_SmartIndexing_Frontend {

	private array $settings;

	public function __construct() {
		$this->settings = SEOB_Settings::get( SEOB_Settings::SMART_INDEXING );

		if ( 'dry_run' === $this->settings['mode'] ) {
			return;
		}

		if ( ! SEOB_Module_Manager::is_rank_math_active() ) {
			return;
		}

		add_filter( 'rank_math/frontend/canonical', [ $this, 'filter_canonical' ] );
		add_filter( 'rank_math/frontend/robots', [ $this, 'filter_robots' ] );
	}

	/**
	 * Tier C (utility/technické parametry) – canonical na čistou URL bez
	 * blokovaných parametrů. Nikdy se nekombinuje s noindexem.
	 */
	public function filter_canonical( $canonical ) {
		if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $canonical;
		}

		$patterns = SEOB_SmartIndexing_Helper::parse_param_list( $this->settings['blacklist_params'] );

		if ( empty( $patterns ) ) {
			return $canonical;
		}

		$current = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$clean = SEOB_SmartIndexing_Helper::strip_blacklisted_params( $current, $patterns );

		return $clean ?? $canonical;
	}

	/**
	 * Tier B – stránky uložené v `facet_urls` jako noindex,follow.
	 */
	public function filter_robots( $robots ) {
		if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $robots;
		}

		// Stránky s blokovaným parametrem řeší canonical výše – zde se nekombinuje noindex.
		$patterns = SEOB_SmartIndexing_Helper::parse_param_list( $this->settings['blacklist_params'] );
		$current  = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! empty( $patterns ) && null !== SEOB_SmartIndexing_Helper::strip_blacklisted_params( $current, $patterns ) ) {
			return $robots;
		}

		global $wpdb;

		$normalized = SEOB_SmartIndexing_Helper::normalize_url( $current );
		$hash       = SEOB_SmartIndexing_Helper::url_hash( $normalized );
		$table      = SEOB_Database::facet_urls_table();

		$tier = $wpdb->get_var( $wpdb->prepare( "SELECT tier FROM {$table} WHERE url_hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 'B' === $tier ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}

		return $robots;
	}
}
