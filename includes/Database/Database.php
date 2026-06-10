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
}
