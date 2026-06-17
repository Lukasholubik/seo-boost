<?php
/**
 * Orchestrátor – inicializuje všechny moduly pluginu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Plugin {

	private static ?SEOB_Plugin $instance = null;

	public static function instance(): SEOB_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		load_plugin_textdomain( 'seo-boost', false, dirname( plugin_basename( SEOB_PLUGIN_FILE ) ) . '/languages' );

		// Oprava WP-Cron spawn v prostredich se self-signed SSL (napr. Local by Flywheel).
		// spawn_cron() pouziva wp_remote_post na wp-cron.php – bez tohoto filtru selze
		// kvuli neoveritelnym certifikatom a vsechny background scany se zaseknou.
		add_filter( 'cron_request', static function ( array $req ): array {
			$req['args']['sslverify'] = false;
			return $req;
		} );

		add_filter( 'cron_schedules', static function ( array $schedules ): array {
			if ( ! isset( $schedules['weekly'] ) ) {
				$schedules['weekly'] = [
					'interval' => WEEK_IN_SECONDS,
					'display'  => 'Jednou týdně',
				];
			}
			return $schedules;
		} );

		new SEOB_Admin();
		new SEOB_Settings_Ajax();
		new SEOB_Schema_Helper();
		new SEOB_Status_Ajax();

		SEOB_Module_Manager::init_active();
		SEOB_Health_Checks::register();

		// JS Render Gap – frontend beacon + cron
		if ( SEOB_Module_Manager::is_active( 'js-render-gap' ) ) {
			add_action( 'wp_enqueue_scripts', static function () {
				if ( is_admin() ) return;

				wp_enqueue_script(
					'seob-js-render-gap-beacon',
					SEOB_PLUGIN_URL . 'assets/js/js-render-gap-beacon.js',
					[],
					SEOB_VERSION,
					true
				);

				wp_add_inline_script(
					'seob-js-render-gap-beacon',
					'var seobJsGap = ' . wp_json_encode( [
						'endpoint' => rest_url( 'seo-booster/v1/js-gap' ),
						'nonce'    => wp_create_nonce( 'wp_rest' ),
					] ) . ';',
					'before'
				);
			} );

			add_action( SEOB_JsGap_ScanRunner::CRON_HOOK, [ 'SEOB_JsGap_ScanRunner', 'run_batch' ] );
			SEOB_JsGap_ScanRunner::schedule();
		}

		// Frontend beacon + cron scheduling pro aktivní CWV modul
		if ( SEOB_Module_Manager::is_active( 'cwv-rum' ) ) {
			add_action( 'wp_enqueue_scripts', static function () {
				if ( is_admin() ) return;

				wp_enqueue_script(
					'seob-web-vitals',
					SEOB_PLUGIN_URL . 'assets/js/vendor/web-vitals.iife.min.js',
					[],
					'4.2.4',
					true
				);

				wp_enqueue_script(
					'seob-cwv-beacon',
					SEOB_PLUGIN_URL . 'assets/js/cwv-beacon.js',
					[ 'seob-web-vitals' ],
					SEOB_VERSION,
					true
				);

				wp_add_inline_script(
					'seob-cwv-beacon',
					'var seobCwv = ' . wp_json_encode( [
						'endpoint' => rest_url( 'seo-booster/v1/cwv' ),
					] ) . ';',
					'before'
				);
			} );

			SEOB_CWV_Aggregator::schedule();
		}
	}
}
