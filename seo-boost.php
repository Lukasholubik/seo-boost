<?php
/**
 * Plugin Name: SEO Booster Pro
 * Plugin URI:  https://grou.cz
 * Description: Audit Dashboard, Redirect Manager a další SEO nástroje kompatibilní s Rank Math (Free).
 * Version:     0.1.0
 * Author:      Lukáš Holubík
 * Text Domain: seo-boost
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOB_VERSION',     '0.1.0' );
define( 'SEOB_PLUGIN_FILE', __FILE__ );
define( 'SEOB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SEOB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SEOB_PLUGIN_SLUG', 'seo-boost' );

// ── Auto-updater via GitHub ───────────────────────────────────────────────
$seob_updater_file = SEOB_PLUGIN_DIR . 'vendor/plugin-update-checker/load-v5p5.php';
if ( file_exists( $seob_updater_file ) ) {
	require_once $seob_updater_file;

	$seob_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Lukasholubik/seo-boost/',
		SEOB_PLUGIN_FILE,
		'seo-boost'
	);

	// Používat GitHub Releases – umožňuje rollback na starší verzi
}

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', static function () {
		echo '<div class="notice notice-error"><p><strong>SEO Booster Pro:</strong> Vyžaduje PHP 8.0 nebo vyšší. Aktuální verze: ' . esc_html( PHP_VERSION ) . '.</p></div>';
	} );
	return;
}

$seob_files = [
	'includes/grou-admin-group.php',
	'includes/Settings.php',
	'includes/Database/Database.php',
	'includes/Activator.php',
	'includes/Admin/Admin.php',
	'includes/Audit/PixelWidth.php',
	'includes/Audit/Scanner.php',
	'includes/Audit/ScanRunner.php',
	'includes/Audit/Ajax.php',
	'includes/Redirects/RedirectManager.php',
	'includes/Redirects/Ajax.php',
	'includes/Admin/SettingsAjax.php',
	'includes/Plugin.php',
];

foreach ( $seob_files as $seob_file ) {
	require_once SEOB_PLUGIN_DIR . $seob_file;
}

register_activation_hook( __FILE__, [ 'SEOB_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SEOB_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	SEOB_Plugin::instance()->init();
} );
