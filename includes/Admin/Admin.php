<?php
/**
 * Registrace admin stránek pluginu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Admin {

	const CAPABILITY = 'manage_options';
	const MENU_SLUG  = 'seo-boost';
	const MENU_POSITION = 32;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menus(): void {
		add_menu_page(
			'SEO Booster Pro',
			'SEO Booster',
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'page_dashboard' ],
			'dashicons-chart-area',
			self::MENU_POSITION
		);

		add_action( 'admin_menu', static function () {
			grou_register_admin_menu_group( SEOB_Admin::MENU_POSITION );
		}, 999 );

		add_action( 'admin_head', static function () {
			grou_output_admin_group_css();
		} );

		if ( SEOB_Module_Manager::is_active( 'audit' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Audit Dashboard', 'Audit Dashboard', self::CAPABILITY, self::MENU_SLUG, [ $this, 'page_dashboard' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'smart-indexing' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Chytrá indexace', 'Chytrá indexace', self::CAPABILITY, 'seob-smart-indexing', [ $this, 'page_smart_indexing' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'redirects' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Přesměrování', 'Přesměrování', self::CAPABILITY, 'seob-redirects', [ $this, 'page_redirects' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'pdf' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Export reportu', 'Export reportu', self::CAPABILITY, 'seob-report', [ $this, 'page_report' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'ai-queue' ) ) {
			add_submenu_page( self::MENU_SLUG, 'AI fronta', 'AI fronta', self::CAPABILITY, 'seob-ai-queue', [ $this, 'page_ai_queue' ] );
		}

		// Stav systému a Nastavení zůstávají vždy dostupné – odsud se moduly znovu zapínají.
		add_submenu_page( self::MENU_SLUG, 'Stav systému', 'Stav systému', self::CAPABILITY, 'seob-status',   [ $this, 'page_status' ] );
		add_submenu_page( self::MENU_SLUG, 'Nastavení',    'Nastavení',    self::CAPABILITY, 'seob-settings', [ $this, 'page_settings' ] );

		if ( SEOB_Module_Manager::is_active( 'pdf' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Export – nastavení', 'Export – nastavení', self::CAPABILITY, 'seob-pdf-settings', [ $this, 'page_pdf_settings' ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, self::MENU_SLUG ) === false && strpos( $hook, 'seob-' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'seob-admin',
			SEOB_PLUGIN_URL . 'assets/admin/css/admin.css',
			[],
			SEOB_VERSION
		);

		$shared_data = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'seob_admin_nonce' ),
		];

		if ( str_ends_with( $hook, '_page_seob-settings' ) ) {
			wp_enqueue_script(
				'seob-settings',
				SEOB_PLUGIN_URL . 'assets/admin/js/settings.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-settings', 'seobData', $shared_data );

			wp_enqueue_script(
				'seob-schema-defaults',
				SEOB_PLUGIN_URL . 'assets/admin/js/schema-defaults.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-schema-defaults', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-pdf-settings' ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'seob-pdf-settings',
				SEOB_PLUGIN_URL . 'assets/admin/js/pdf-settings.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-pdf-settings', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-smart-indexing' ) ) {
			wp_enqueue_script(
				'seob-smart-indexing',
				SEOB_PLUGIN_URL . 'assets/admin/js/smart-indexing.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-smart-indexing', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-ai-queue' ) ) {
			wp_enqueue_script(
				'seob-ai-queue',
				SEOB_PLUGIN_URL . 'assets/admin/js/ai-queue.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-ai-queue', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-redirects' ) ) {
			wp_enqueue_script(
				'seob-redirects',
				SEOB_PLUGIN_URL . 'assets/admin/js/redirects.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-redirects', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-status' ) ) {
			wp_enqueue_script(
				'seob-status-chart',
				SEOB_PLUGIN_URL . 'assets/admin/js/status-chart.js',
				[],
				SEOB_VERSION,
				true
			);

			wp_enqueue_script(
				'seob-status-page',
				SEOB_PLUGIN_URL . 'assets/admin/js/status-page.js',
				[ 'seob-status-chart' ],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-status-page', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-report' ) ) {
			wp_enqueue_script(
				'seob-report',
				SEOB_PLUGIN_URL . 'assets/admin/js/report.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script(
				'seob-report',
				'seobData',
				array_merge( $shared_data, [ 'scanId' => isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : 0 ] )
			);

			return;
		}

		if ( 'toplevel_page_' . self::MENU_SLUG === $hook && SEOB_Module_Manager::is_active( 'audit' ) ) {
			wp_enqueue_script(
				'seob-audit-dashboard',
				SEOB_PLUGIN_URL . 'assets/admin/js/audit-dashboard.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script(
				'seob-audit-dashboard',
				'seobData',
				array_merge(
					$shared_data,
					[
						'schemaTypes'   => SEOB_Schema_Helper::TYPES,
						'reportUrl'     => admin_url( 'admin.php?page=seob-report' ),
						'aiQueueActive' => SEOB_Module_Manager::is_active( 'ai-queue' ),
						'aiQueueUrl'    => admin_url( 'admin.php?page=seob-ai-queue' ),
					]
				)
			);
		}
	}

	public function page_dashboard(): void {
		if ( ! SEOB_Module_Manager::is_active( 'audit' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['audit']['label'] );
			return;
		}

		$this->render_template( 'page-dashboard.php' );
	}

	public function page_smart_indexing(): void {
		$this->render_template( 'page-smart-indexing.php' );
	}

	public function page_redirects(): void {
		if ( ! SEOB_Module_Manager::is_active( 'redirects' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['redirects']['label'] );
			return;
		}

		$this->render_template( 'page-redirects.php' );
	}

	public function page_report(): void {
		if ( ! SEOB_Module_Manager::is_active( 'pdf' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['pdf']['label'] );
			return;
		}

		$this->render_template( 'page-report.php' );
	}

	public function page_status(): void {
		$this->render_template( 'page-status.php' );
	}

	public function page_settings(): void {
		$this->render_template( 'page-settings.php' );
	}

	public function page_ai_queue(): void {
		if ( ! SEOB_Module_Manager::is_active( 'ai-queue' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['ai-queue']['label'] );
			return;
		}

		$this->render_template( 'page-ai-queue.php' );
	}

	public function page_pdf_settings(): void {
		if ( ! SEOB_Module_Manager::is_active( 'pdf' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['pdf']['label'] );
			return;
		}

		$this->render_template( 'page-pdf-settings.php' );
	}

	private function render_disabled_module( string $module_label ): void {
		$seob_module_label = $module_label;
		$this->render_template( 'page-module-disabled.php' );
	}

	private function render_template( string $template ): void {
		$path = SEOB_PLUGIN_DIR . 'templates/admin/' . $template;

		if ( file_exists( $path ) ) {
			require $path;
		}
	}
}
