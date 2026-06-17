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

		if ( SEOB_Module_Manager::is_active( 'pagespeed' ) ) {
			add_submenu_page( self::MENU_SLUG, 'PageSpeed Insights', 'PageSpeed Insights', self::CAPABILITY, 'seob-pagespeed', [ $this, 'page_pagespeed' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'internal-links' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Interní prolinkování', 'Interní prolinkování', self::CAPABILITY, 'seob-internal-links', [ $this, 'page_internal_links' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'hreflang' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Hreflang Manager', 'Hreflang Manager', self::CAPABILITY, 'seob-hreflang', [ $this, 'page_hreflang' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'local-seo' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Local SEO (CZ)', 'Local SEO (CZ)', self::CAPABILITY, 'seob-local-seo', [ $this, 'page_local_seo' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'json-ld' ) ) {
			add_submenu_page( self::MENU_SLUG, 'JSON-LD Validátor', 'JSON-LD Validátor', self::CAPABILITY, 'seob-json-ld', [ $this, 'page_json_ld' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'cwv-rum' ) ) {
			add_submenu_page( self::MENU_SLUG, 'Core Web Vitals (RUM)', 'CWV / RUM', self::CAPABILITY, 'seob-cwv', [ $this, 'page_cwv' ] );
		}

		if ( SEOB_Module_Manager::is_active( 'js-render-gap' ) ) {
			add_submenu_page( self::MENU_SLUG, 'JS Render Gap', 'JS Render Gap', self::CAPABILITY, 'seob-js-render-gap', [ $this, 'page_js_render_gap' ] );
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

		if ( str_ends_with( $hook, '_page_seob-pagespeed' ) ) {
			wp_enqueue_script(
				'seob-pagespeed',
				SEOB_PLUGIN_URL . 'assets/admin/js/pagespeed.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-pagespeed', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-internal-links' ) ) {
			wp_enqueue_script(
				'seob-internal-links',
				SEOB_PLUGIN_URL . 'assets/admin/js/internal-links.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-internal-links', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-hreflang' ) ) {
			wp_enqueue_script(
				'seob-hreflang',
				SEOB_PLUGIN_URL . 'assets/admin/js/hreflang.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-hreflang', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-local-seo' ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'seob-local-seo',
				SEOB_PLUGIN_URL . 'assets/admin/js/local-seo.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-local-seo', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-json-ld' ) ) {
			wp_enqueue_script(
				'seob-json-ld',
				SEOB_PLUGIN_URL . 'assets/admin/js/json-ld.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-json-ld', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-js-render-gap' ) ) {
			wp_enqueue_script(
				'seob-js-render-gap',
				SEOB_PLUGIN_URL . 'assets/admin/js/js-render-gap.js',
				[ 'jquery' ],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-js-render-gap', 'seobData', $shared_data );

			return;
		}

		if ( str_ends_with( $hook, '_page_seob-cwv' ) ) {
			// Chart.js pro CWV dashboard
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				[],
				'4.4.0',
				true
			);
			wp_enqueue_script(
				'seob-cwv-dashboard',
				SEOB_PLUGIN_URL . 'assets/admin/js/cwv-dashboard.js',
				[ 'chartjs' ],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-cwv-dashboard', 'seobData', $shared_data );

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
						'schemaTypes'    => SEOB_Schema_Helper::TYPES,
						'reportUrl'      => admin_url( 'admin.php?page=seob-report' ),
						'aiQueueActive'  => SEOB_Module_Manager::is_active( 'ai-queue' ),
						'aiQueueUrl'     => admin_url( 'admin.php?page=seob-ai-queue' ),
						'jsonLdActive'   => SEOB_Module_Manager::is_active( 'json-ld' ),
						'jsonLdUrl'      => admin_url( 'admin.php?page=seob-json-ld' ),
						'postTypeLabels' => self::get_audit_post_type_labels(),
					]
				)
			);
		}
	}

	/**
	 * Vrátí mapu post_type => název (plurál) pro všechny post typy
	 * zahrnuté do audit scanu na tomto webu.
	 *
	 * @return array<string,string>
	 */
	private static function get_audit_post_type_labels(): array {
		$labels = [];

		foreach ( SEOB_Audit_ScanRunner::get_audit_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( $object ) {
				$labels[ $post_type ] = $object->labels->name;
			}
		}

		return $labels;
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

	public function page_pagespeed(): void {
		if ( ! SEOB_Module_Manager::is_active( 'pagespeed' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['pagespeed']['label'] );
			return;
		}

		$this->render_template( 'page-pagespeed.php' );
	}

	public function page_internal_links(): void {
		if ( ! SEOB_Module_Manager::is_active( 'internal-links' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['internal-links']['label'] );
			return;
		}

		$this->render_template( 'page-internal-links.php' );
	}

	public function page_hreflang(): void {
		if ( ! SEOB_Module_Manager::is_active( 'hreflang' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['hreflang']['label'] );
			return;
		}

		$this->render_template( 'page-hreflang.php' );
	}

	public function page_local_seo(): void {
		if ( ! SEOB_Module_Manager::is_active( 'local-seo' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['local-seo']['label'] );
			return;
		}

		$this->render_template( 'page-local-seo.php' );
	}

	public function page_json_ld(): void {
		if ( ! SEOB_Module_Manager::is_active( 'json-ld' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['json-ld']['label'] );
			return;
		}

		$this->render_template( 'page-json-ld.php' );
	}

	public function page_pdf_settings(): void {
		if ( ! SEOB_Module_Manager::is_active( 'pdf' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['pdf']['label'] );
			return;
		}

		$this->render_template( 'page-pdf-settings.php' );
	}

	public function page_cwv(): void {
		if ( ! SEOB_Module_Manager::is_active( 'cwv-rum' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['cwv-rum']['label'] );
			return;
		}

		$this->render_template( 'page-cwv.php' );
	}

	public function page_js_render_gap(): void {
		if ( ! SEOB_Module_Manager::is_active( 'js-render-gap' ) ) {
			$this->render_disabled_module( SEOB_Module_Manager::MODULES['js-render-gap']['label'] );
			return;
		}

		$this->render_template( 'page-js-render-gap.php' );
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
