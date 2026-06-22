<?php
/**
 * Hreflang Manager – výstup <link rel="alternate" hreflang> tagů.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Hreflang_Manager {

	public function __construct() {
		add_action( 'wp_head', [ $this, 'output_hreflang' ], 2 );
	}

	public function output_hreflang(): void {
		if ( self::has_conflict() ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		foreach ( self::get_tags_for_post( $post_id ) as $tag ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $tag['hreflang'] ),
				esc_url( $tag['url'] )
			);
		}
	}

	/**
	 * @return array<int, array{hreflang: string, url: string}>
	 */
	public static function get_tags_for_post( int $post_id ): array {
		global $wpdb;

		$members_table = SEOB_Database::hreflang_members_table();

		$group_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT group_id FROM {$members_table} WHERE page_id = %d LIMIT 1",
			$post_id
		) );

		if ( ! $group_id ) {
			return [];
		}

		$members = $wpdb->get_results( $wpdb->prepare(
			"SELECT page_id, locale, is_x_default FROM {$members_table} WHERE group_id = %d ORDER BY id ASC",
			(int) $group_id
		), ARRAY_A );

		$tags = [];

		foreach ( $members as $member ) {
			$url = get_permalink( (int) $member['page_id'] );
			if ( ! $url ) {
				continue;
			}

			$tags[] = [
				'hreflang' => $member['locale'],
				'url'      => $url,
			];

			if ( (int) $member['is_x_default'] ) {
				$tags[] = [
					'hreflang' => 'x-default',
					'url'      => $url,
				];
			}
		}

		return $tags;
	}

	/**
	 * Detekce konfliktu s jiným pluginem, který spravuje hreflang.
	 */
	public static function has_conflict(): bool {
		if ( defined( 'RANK_MATH_PRO_FILE' ) || class_exists( 'RankMathPro' ) ) {
			return true;
		}

		if ( class_exists( 'WPSEO_Premium' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @return array{active: bool, plugin: string}
	 */
	public static function detect_multilingual(): array {
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return [ 'active' => true, 'plugin' => 'WPML' ];
		}

		if ( function_exists( 'pll_languages_list' ) ) {
			return [ 'active' => true, 'plugin' => 'Polylang' ];
		}

		if ( class_exists( 'TRP_Translate_Press' ) ) {
			return [ 'active' => true, 'plugin' => 'TranslatePress' ];
		}

		return [ 'active' => false, 'plugin' => '' ];
	}
}
