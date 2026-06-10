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

		add_submenu_page( self::MENU_SLUG, 'Audit Dashboard', 'Audit Dashboard', self::CAPABILITY, self::MENU_SLUG, [ $this, 'page_dashboard' ] );
		add_submenu_page( self::MENU_SLUG, 'Přesměrování',    'Přesměrování',    self::CAPABILITY, 'seob-redirects', [ $this, 'page_redirects' ] );
		add_submenu_page( self::MENU_SLUG, 'Nastavení',       'Nastavení',       self::CAPABILITY, 'seob-settings',  [ $this, 'page_settings' ] );
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

		if ( 'seo-boost_page_seob-settings' === $hook ) {
			wp_enqueue_script(
				'seob-settings',
				SEOB_PLUGIN_URL . 'assets/admin/js/settings.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-settings', 'seobData', $shared_data );

			return;
		}

		if ( 'seo-boost_page_seob-redirects' === $hook ) {
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

		if ( 'toplevel_page_' . self::MENU_SLUG === $hook ) {
			wp_enqueue_script(
				'seob-audit-dashboard',
				SEOB_PLUGIN_URL . 'assets/admin/js/audit-dashboard.js',
				[],
				SEOB_VERSION,
				true
			);
			wp_localize_script( 'seob-audit-dashboard', 'seobData', $shared_data );
		}
	}

	public function page_dashboard(): void {
		$this->render_template( 'page-dashboard.php' );
	}

	public function page_redirects(): void {
		$this->render_template( 'page-redirects.php' );
	}

	public function page_settings(): void {
		$this->render_template( 'page-settings.php' );
	}

	private function render_template( string $template ): void {
		$path = SEOB_PLUGIN_DIR . 'templates/admin/' . $template;

		if ( file_exists( $path ) ) {
			require $path;
		}
	}
}
