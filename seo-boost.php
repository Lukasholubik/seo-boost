<?php
/**
 * Plugin Name: SEO Booster Pro
 * Plugin URI:  https://grou.cz
 * Description: Audit Dashboard, Redirect Manager, JS Render Gap a dalsi SEO nastroje kompatibilni s Rank Math (Free).
 * Version:     0.9.8
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

define( 'SEOB_VERSION',     '0.9.8' );
define( 'SEOB_DB_VERSION',  '0.9.0' );
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
	'includes/Metrics/Metrics.php',
	'includes/Activator.php',
	'includes/ModuleManager.php',
	'includes/Health/HealthChecks.php',
	'includes/Health/StatusAjax.php',
	'includes/Admin/Admin.php',
	'includes/Audit/PixelWidth.php',
	'includes/Audit/Scanner.php',
	'includes/Audit/AuditScanRunner.php',
	'includes/Audit/Ajax.php',
	'includes/GscInsights/GscInsights.php',
	'includes/Redirects/RedirectManager.php',
	'includes/Redirects/Ajax.php',
	'includes/Admin/SettingsAjax.php',
	'includes/Schema/SchemaHelper.php',
	'includes/Schema/CategoryAjax.php',
	'includes/Schema/PostTypeAjax.php',
	'includes/Pdf/ReportData.php',
	'includes/Pdf/PdfRenderer.php',
	'includes/Pdf/Ajax.php',
	'includes/SmartIndexing/Helper.php',
	'includes/SmartIndexing/CatalogScanner.php',
	'includes/SmartIndexing/Frontend.php',
	'includes/SmartIndexing/Ajax.php',
	'includes/AiQueue/Crypt.php',
	'includes/AiQueue/ProviderInterface.php',
	'includes/AiQueue/OpenAiCompatibleProvider.php',
	'includes/AiQueue/Repository.php',
	'includes/AiQueue/PromptBuilder.php',
	'includes/AiQueue/Ajax.php',
	'includes/PageSpeed/Client.php',
	'includes/PageSpeed/ScanRunner.php',
	'includes/PageSpeed/Ajax.php',
	'includes/InternalLinks/Extractor.php',
	'includes/InternalLinks/Similarity.php',
	'includes/InternalLinks/ScanRunner.php',
	'includes/InternalLinks/Indexer.php',
	'includes/InternalLinks/LinkInserter.php',
	'includes/InternalLinks/MetaBox.php',
	'includes/InternalLinks/Ajax.php',
	'includes/Hreflang/Manager.php',
	'includes/Hreflang/Ajax.php',
	'includes/LocalSeo/Frontend.php',
	'includes/LocalSeo/Ajax.php',
	'includes/JsonLd/Validator.php',
	'includes/JsonLd/PageScanner.php',
	'includes/JsonLd/ScanRunner.php',
	'includes/JsonLd/Ajax.php',
	'includes/CWV/BeaconEndpoint.php',
	'includes/CWV/Aggregator.php',
	'includes/CWV/Ajax.php',
	'includes/JsRenderGap/BeaconReceiver.php',
	'includes/JsRenderGap/Comparator.php',
	'includes/JsRenderGap/ScanRunner.php',
	'includes/JsRenderGap/Ajax.php',
	'includes/HttpHeaders/Checker.php',
	'includes/HttpHeaders/ScanRunner.php',
	'includes/HttpHeaders/Ajax.php',
	'includes/ContentDecay/Analyzer.php',
	'includes/ContentDecay/Scanner.php',
	'includes/ContentDecay/Ajax.php',
	'includes/Plugin.php',
];

foreach ( $seob_files as $seob_file ) {
	require_once SEOB_PLUGIN_DIR . $seob_file;
}

register_activation_hook( __FILE__, [ 'SEOB_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SEOB_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	SEOB_Activator::maybe_upgrade();
	SEOB_Plugin::instance()->init();
} );
