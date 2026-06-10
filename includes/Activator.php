<?php
/**
 * Aktivace/deaktivace pluginu – tvorba DB tabulek, výchozí nastavení.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Activator {

	public static function activate(): void {
		self::create_tables();

		if ( false === get_option( SEOB_Settings::GENERAL, false ) ) {
			update_option( SEOB_Settings::GENERAL, SEOB_Settings::get( SEOB_Settings::GENERAL ) );
		}
	}

	public static function deactivate(): void {
		// Tabulky a nastavení zůstávají – mažou se až v uninstall.php (pokud je delete_on_uninstall zapnuto).
		wp_clear_scheduled_hook( SEOB_Redirect_Manager::CRON_HOOK );
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$audit_table     = SEOB_Database::audit_table();
		$scan_runs_table = SEOB_Database::scan_runs_table();
		$ai_queue_table  = SEOB_Database::ai_queue_table();
		$links_table     = SEOB_Database::links_table();

		$sql = "CREATE TABLE {$audit_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			url VARCHAR(2083) NOT NULL,
			score TINYINT UNSIGNED DEFAULT NULL,
			issues_json LONGTEXT,
			content_hash CHAR(32) DEFAULT NULL,
			scanned_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_scan (scan_id),
			KEY idx_object (object_id, object_type)
		) {$charset_collate};

		CREATE TABLE {$scan_runs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			started_at DATETIME NOT NULL,
			finished_at DATETIME DEFAULT NULL,
			trigger_type VARCHAR(10) NOT NULL,
			urls_total INT UNSIGNED DEFAULT 0,
			urls_done INT UNSIGNED DEFAULT 0,
			score_avg TINYINT UNSIGNED DEFAULT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'running',
			PRIMARY KEY  (id)
		) {$charset_collate};

		CREATE TABLE {$ai_queue_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT UNSIGNED NOT NULL,
			field VARCHAR(30) NOT NULL,
			suggestion TEXT NOT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			reviewed_by BIGINT UNSIGNED DEFAULT NULL,
			reviewed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_object (object_id),
			KEY idx_status (status)
		) {$charset_collate};

		CREATE TABLE {$links_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id BIGINT UNSIGNED DEFAULT NULL,
			target_url VARCHAR(2083) NOT NULL,
			link_type VARCHAR(10) NOT NULL DEFAULT 'internal',
			http_status SMALLINT UNSIGNED DEFAULT NULL,
			last_checked DATETIME DEFAULT NULL,
			hits_404 INT UNSIGNED NOT NULL DEFAULT 0,
			redirect_to VARCHAR(2083) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_source (source_id),
			KEY idx_link_type (link_type)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
