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
		update_option( 'seob_db_version', SEOB_DB_VERSION );

		if ( false === get_option( SEOB_Settings::GENERAL, false ) ) {
			update_option( SEOB_Settings::GENERAL, SEOB_Settings::get( SEOB_Settings::GENERAL ) );
		}
	}

	/**
	 * Spustí dbDelta při zvýšení verze DB schématu (i bez reaktivace pluginu).
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'seob_db_version' ) === SEOB_DB_VERSION ) {
			return;
		}

		self::create_tables();
		update_option( 'seob_db_version', SEOB_DB_VERSION );
	}

	public static function deactivate(): void {
		// Tabulky a nastavení zůstávají – mažou se až v uninstall.php (pokud je delete_on_uninstall zapnuto).
		wp_clear_scheduled_hook( SEOB_Redirect_Manager::CRON_HOOK );
		wp_clear_scheduled_hook( SEOB_PageSpeed_ScanRunner::CRON_HOOK );
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$audit_table       = SEOB_Database::audit_table();
		$scan_runs_table   = SEOB_Database::scan_runs_table();
		$ai_queue_table    = SEOB_Database::ai_queue_table();
		$links_table       = SEOB_Database::links_table();
		$metrics_table     = SEOB_Database::metrics_table();
		$facet_rules_table   = SEOB_Database::facet_rules_table();
		$facet_urls_table    = SEOB_Database::facet_urls_table();
		$facet_signals_table = SEOB_Database::facet_signals_table();
		$psi_runs_table      = SEOB_Database::psi_runs_table();
		$psi_results_table   = SEOB_Database::psi_results_table();
		$psi_summary_table   = SEOB_Database::psi_summary_table();

		$sql = "CREATE TABLE {$audit_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			url VARCHAR(2083) NOT NULL,
			score TINYINT UNSIGNED DEFAULT NULL,
			issues_json LONGTEXT,
			schema_json LONGTEXT,
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
		) {$charset_collate};

		CREATE TABLE {$metrics_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			module VARCHAR(30) NOT NULL,
			metric_key VARCHAR(50) NOT NULL,
			metric_value DECIMAL(10,2) DEFAULT NULL,
			recorded_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_module_key (module, metric_key, recorded_at)
		) {$charset_collate};

		CREATE TABLE {$facet_rules_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_type VARCHAR(20) NOT NULL,
			rule_key VARCHAR(100) NOT NULL,
			default_tier CHAR(1) NOT NULL,
			is_candidate TINYINT(1) NOT NULL DEFAULT 0,
			min_results SMALLINT UNSIGNED DEFAULT 5,
			max_depth TINYINT UNSIGNED DEFAULT 2,
			override_json LONGTEXT,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_rule (rule_type, rule_key)
		) {$charset_collate};

		CREATE TABLE {$facet_urls_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url_normalized VARCHAR(2083) NOT NULL,
			url_hash CHAR(32) NOT NULL,
			page_type VARCHAR(30) NOT NULL,
			dimensions_json LONGTEXT,
			tier CHAR(1) NOT NULL,
			tier_reason VARCHAR(50) DEFAULT NULL,
			score TINYINT UNSIGNED DEFAULT NULL,
			result_count SMALLINT UNSIGNED DEFAULT NULL,
			is_indexed_gsc TINYINT(1) DEFAULT NULL,
			scanned_at DATETIME DEFAULT NULL,
			promoted_at DATETIME DEFAULT NULL,
			demoted_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_url_hash (url_hash),
			KEY idx_tier (tier),
			KEY idx_page_type (page_type)
		) {$charset_collate};

		CREATE TABLE {$facet_signals_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url_hash CHAR(32) NOT NULL,
			signal_date DATE NOT NULL,
			impressions INT UNSIGNED NOT NULL DEFAULT 0,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			filter_uses INT UNSIGNED NOT NULL DEFAULT 0,
			searches INT UNSIGNED NOT NULL DEFAULT 0,
			inquiries INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_signal (url_hash, signal_date)
		) {$charset_collate};

		CREATE TABLE {$psi_runs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			started_at DATETIME NOT NULL,
			finished_at DATETIME DEFAULT NULL,
			items_total INT UNSIGNED DEFAULT 0,
			items_done INT UNSIGNED DEFAULT 0,
			status VARCHAR(10) NOT NULL DEFAULT 'running',
			PRIMARY KEY  (id)
		) {$charset_collate};

		CREATE TABLE {$psi_results_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT UNSIGNED NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			url VARCHAR(2083) NOT NULL,
			strategy VARCHAR(10) NOT NULL,
			performance_score TINYINT UNSIGNED DEFAULT NULL,
			accessibility_score TINYINT UNSIGNED DEFAULT NULL,
			best_practices_score TINYINT UNSIGNED DEFAULT NULL,
			seo_score TINYINT UNSIGNED DEFAULT NULL,
			issues_json LONGTEXT,
			error TEXT DEFAULT NULL,
			scanned_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_run (run_id),
			KEY idx_object (object_id, object_type, strategy)
		) {$charset_collate};

		CREATE TABLE {$psi_summary_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(20) NOT NULL,
			strategy VARCHAR(10) NOT NULL,
			performance_avg TINYINT UNSIGNED DEFAULT NULL,
			accessibility_avg TINYINT UNSIGNED DEFAULT NULL,
			best_practices_avg TINYINT UNSIGNED DEFAULT NULL,
			seo_avg TINYINT UNSIGNED DEFAULT NULL,
			sample_size TINYINT UNSIGNED DEFAULT 0,
			common_issues_json LONGTEXT,
			sample_object_ids_json LONGTEXT,
			PRIMARY KEY  (id),
			KEY idx_run_type (run_id, object_type, strategy)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
