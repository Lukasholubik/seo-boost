<?php
/**
 * Smaže DB tabulky a options, pokud je v nastavení zapnuto delete_on_uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$general = get_option( 'seob_general_settings', [] );

if ( empty( $general['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$tables = [
	$wpdb->prefix . 'seo_booster_audit',
	$wpdb->prefix . 'seo_booster_scan_runs',
	$wpdb->prefix . 'seo_booster_ai_queue',
	$wpdb->prefix . 'seo_booster_links',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'seob_general_settings' );
delete_option( 'seob_audit_settings' );
delete_option( 'seob_redirect_settings' );
