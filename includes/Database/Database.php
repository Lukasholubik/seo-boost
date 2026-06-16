<?php
/**
 * Helper pro názvy DB tabulek pluginu (prefix `seo_booster_`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Database {

	public static function audit_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_audit';
	}

	public static function scan_runs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_scan_runs';
	}

	public static function ai_queue_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_ai_queue';
	}

	public static function links_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_links';
	}

	public static function metrics_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_metrics';
	}

	public static function facet_rules_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_facet_rules';
	}

	public static function facet_urls_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_facet_urls';
	}

	public static function facet_signals_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_facet_signals';
	}

	public static function psi_runs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_psi_runs';
	}

	public static function psi_results_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_psi_results';
	}

	public static function psi_summary_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_psi_summary';
	}

	public static function internal_links_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_internal_links';
	}

	public static function link_suggestions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_link_suggestions';
	}

	public static function link_scans_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_booster_link_scans';
	}
}
