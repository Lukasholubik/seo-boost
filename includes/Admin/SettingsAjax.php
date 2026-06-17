<?php
/**
 * AJAX endpoint pro uložení nastavení pluginu (stránka Nastavení).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Settings_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_seob_save_pdf_settings', [ $this, 'save_pdf_settings' ] );
		add_action( 'wp_ajax_seob_save_ai_settings', [ $this, 'save_ai_settings' ] );
		add_action( 'wp_ajax_seob_save_pagespeed_settings', [ $this, 'save_pagespeed_settings' ] );
	}

	public function save_settings(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$general = [
			'debug'               => $this->bool_field( 'debug' ),
			'delete_on_uninstall' => $this->bool_field( 'delete_on_uninstall' ),
			'modules'             => [
				'audit'          => $this->bool_field( 'modules_audit' ),
				'redirects'      => $this->bool_field( 'modules_redirects' ),
				'pdf'            => $this->bool_field( 'modules_pdf' ),
				'smart-indexing' => $this->bool_field( 'modules_smart_indexing' ),
				'gsc-insights'   => $this->bool_field( 'modules_gsc_insights' ),
				'ai-queue'       => $this->bool_field( 'modules_ai_queue' ),
				'pagespeed'      => $this->bool_field( 'modules_pagespeed' ),
				'internal-links' => $this->bool_field( 'modules_internal_links' ),
				'hreflang'       => $this->bool_field( 'modules_hreflang' ),
				'local-seo'      => $this->bool_field( 'modules_local_seo' ),
				'json-ld'        => $this->bool_field( 'modules_json_ld' ),
				'cwv-rum'        => $this->bool_field( 'modules_cwv_rum' ),
				'js-render-gap'  => $this->bool_field( 'modules_js_render_gap' ),
			],
		];

		$audit = [
			'cron_enabled'       => $this->bool_field( 'cron_enabled' ),
			'batch_size'         => $this->int_field( 'batch_size', 1, 100, 20 ),
			'thin_content_words' => $this->int_field( 'thin_content_words', 50, 2000, 300 ),
			'history_limit'      => $this->int_field( 'history_limit', 1, 200, 20 ),
		];

		$redirect = [
			'log_404'            => $this->bool_field( 'log_404' ),
			'log_retention_days' => $this->int_field( 'log_retention_days', 1, 365, 30 ),
		];

		SEOB_Settings::update( SEOB_Settings::GENERAL, $general );
		SEOB_Settings::update( SEOB_Settings::AUDIT, $audit );
		SEOB_Settings::update( SEOB_Settings::REDIRECT, $redirect );

		wp_send_json_success( [
			'general'  => $general,
			'audit'    => $audit,
			'redirect' => $redirect,
		] );
	}

	/**
	 * Uloží nastavení PDF reportu (stránka „Export – nastavení“).
	 */
	public function save_pdf_settings(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$pdf = $this->pdf_settings();

		SEOB_Settings::update( SEOB_Settings::PDF, $pdf );

		wp_send_json_success( [ 'pdf' => $pdf ] );
	}

	/**
	 * Uloží nastavení AI asistenta (endpoint, model, API klíč – šifrovaně).
	 */
	public function save_ai_settings(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$defaults = SEOB_Settings::get( SEOB_Settings::AI );

		$api_key_enc = $defaults['api_key_enc'];
		$api_key     = $this->text_field( 'ai_api_key' );

		if ( '' !== $api_key ) {
			$api_key_enc = SEOB_AiQueue_Crypt::encrypt( $api_key );
		}

		$ai = [
			'enabled'     => $this->bool_field( 'ai_enabled' ),
			'endpoint'    => esc_url_raw( $this->text_field( 'ai_endpoint' ) ),
			'model'       => $this->text_field( 'ai_model' ),
			'max_tokens'  => $this->int_field( 'ai_max_tokens', 1, 4000, (int) $defaults['max_tokens'] ),
			'api_key_enc' => $api_key_enc,
		];

		SEOB_Settings::update( SEOB_Settings::AI, $ai );

		wp_send_json_success( [
			'enabled'     => $ai['enabled'],
			'endpoint'    => $ai['endpoint'],
			'model'       => $ai['model'],
			'max_tokens'  => $ai['max_tokens'],
			'has_api_key' => '' !== $ai['api_key_enc'],
		] );
	}

	/**
	 * Uloží nastavení PageSpeed Insights (zapnutí modulu, API klíč – šifrovaně).
	 */
	public function save_pagespeed_settings(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}

		$defaults = SEOB_Settings::get( SEOB_Settings::PAGESPEED );

		$api_key_enc = $defaults['api_key_enc'];
		$api_key     = $this->text_field( 'pagespeed_api_key' );

		if ( '' !== $api_key ) {
			$api_key_enc = SEOB_AiQueue_Crypt::encrypt( $api_key );
		}

		$pagespeed = [
			'enabled'     => $this->bool_field( 'pagespeed_enabled' ),
			'api_key_enc' => $api_key_enc,
		];

		SEOB_Settings::update( SEOB_Settings::PAGESPEED, $pagespeed );

		wp_send_json_success( [
			'enabled'     => $pagespeed['enabled'],
			'has_api_key' => '' !== $pagespeed['api_key_enc'],
		] );
	}

	/**
	 * Sestaví hodnotu skupiny `seob_pdf_settings` z odeslaného formuláře.
	 */
	private function pdf_settings(): array {
		$defaults = SEOB_Settings::get( SEOB_Settings::PDF );

		$issue_texts = [];

		foreach ( $defaults['issue_texts'] as $issue_type => $texts ) {
			$issue_texts[ $issue_type ] = [
				'impact'  => $this->kses_field( "pdf_issue_{$issue_type}_impact" ),
				'benefit' => $this->kses_field( "pdf_issue_{$issue_type}_benefit" ),
			];
		}

		$offer_templates = [];

		foreach ( $defaults['offer_templates'] as $offer_key => $offer ) {
			$offer_templates[ $offer_key ] = [
				'name' => $this->text_field( "pdf_offer_{$offer_key}_name" ),
				'body' => $this->kses_field( "pdf_offer_{$offer_key}_body" ),
			];
		}

		$logo_id  = $this->int_field( 'pdf_company_logo_id', 0, PHP_INT_MAX, (int) $defaults['company']['logo_id'] );
		$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		$accent_color = $this->text_field( 'pdf_company_accent_color' );

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $accent_color ) ) {
			$accent_color = $defaults['company']['accent_color'];
		}

		return [
			'issue_texts'     => $issue_texts,
			'offer_templates' => $offer_templates,
			'company'         => [
				'name'           => $this->text_field( 'pdf_company_name' ),
				'contact_person' => $this->text_field( 'pdf_company_contact_person' ),
				'ico'            => $this->text_field( 'pdf_company_ico' ),
				'contact'        => $this->kses_field( 'pdf_company_contact' ),
				'footer_text'    => $this->kses_field( 'pdf_company_footer' ),
				'logo_id'        => $logo_id,
				'logo_url'       => $logo_url,
				'accent_color'   => $accent_color,
			],
			'report' => [
				'detailed_pages_limit' => $this->int_field( 'pdf_report_detailed_pages_limit', 1, 100, (int) $defaults['report']['detailed_pages_limit'] ),
			],
		];
	}

	private function bool_field( string $name ): int {
		return isset( $_POST[ $name ] ) && '1' === $_POST[ $name ] ? 1 : 0;
	}

	private function int_field( string $name, int $min, int $max, int $default ): int {
		$value = isset( $_POST[ $name ] ) ? absint( $_POST[ $name ] ) : $default;

		return max( $min, min( $max, $value ) );
	}

	private function text_field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	private function kses_field( string $name ): string {
		return isset( $_POST[ $name ] ) ? wp_kses_post( wp_unslash( $_POST[ $name ] ) ) : '';
	}
}
