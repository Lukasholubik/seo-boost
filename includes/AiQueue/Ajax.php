<?php
/**
 * AJAX endpointy pro AI schvalovací frontu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_AiQueue_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	public function __construct() {
		add_action( 'wp_ajax_seob_ai_suggest', [ $this, 'suggest' ] );
		add_action( 'wp_ajax_seob_ai_suggest_alt', [ $this, 'suggest_alt' ] );
		add_action( 'wp_ajax_seob_ai_queue_list', [ $this, 'queue_list' ] );
		add_action( 'wp_ajax_seob_ai_queue_approve', [ $this, 'queue_approve' ] );
		add_action( 'wp_ajax_seob_ai_queue_reject', [ $this, 'queue_reject' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	/**
	 * Navrhne nový title/description pro stránku a vloží návrh do fronty (status pending).
	 */
	public function suggest(): void {
		$this->check_request();

		$object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
		$field     = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';

		$allowed_fields = [
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
		];

		if ( $object_id <= 0 || ! isset( $allowed_fields[ $field ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $object_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění upravit tento obsah.', 'seo-boost' ) ], 403 );
		}

		$provider = $this->build_provider();

		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ], 400 );
		}

		$meta_field = $allowed_fields[ $field ];
		$page_title = get_the_title( $object_id );
		$excerpt    = $this->content_excerpt( $object_id );

		if ( 'title' === $field ) {
			$current = (string) get_post_meta( $object_id, 'rank_math_title', true );
			$prompt  = SEOB_AiQueue_Prompt_Builder::for_title( $page_title, $current, $excerpt );
		} else {
			$current = (string) get_post_meta( $object_id, 'rank_math_description', true );
			$prompt  = SEOB_AiQueue_Prompt_Builder::for_description( $page_title, $current, $excerpt );
		}

		$suggestion = $provider->complete( $prompt );

		if ( is_wp_error( $suggestion ) ) {
			wp_send_json_error( [ 'message' => $suggestion->get_error_message() ], 502 );
		}

		$queue_id = SEOB_AiQueue_Repository::insert( $object_id, $meta_field, $suggestion );

		wp_send_json_success( [
			'queue_id'   => $queue_id,
			'suggestion' => $suggestion,
		] );
	}

	/**
	 * Najde obrázky bez (nebo s genericky vyhlížejícím) alt textem a navrhne pro ně alt texty.
	 */
	public function suggest_alt(): void {
		$this->check_request();

		$object_id = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;

		if ( $object_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_post', $object_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění upravit tento obsah.', 'seo-boost' ) ], 403 );
		}

		$provider = $this->build_provider();

		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ], 400 );
		}

		$post = get_post( $object_id );

		if ( null === $post ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		$page_title = get_the_title( $object_id );
		$plain_text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$queued     = 0;

		if ( '' !== trim( $post->post_content ) ) {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $post->post_content . '</div>' );
			libxml_clear_errors();

			foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
				if ( $queued >= 5 ) {
					break;
				}

				$alt = $img->getAttribute( 'alt' );

				if ( '' !== trim( $alt ) && ! $this->is_generic_alt( $alt ) ) {
					continue;
				}

				$src = $img->getAttribute( 'src' );

				if ( '' === $src ) {
					continue;
				}

				$attachment_id = attachment_url_to_postid( $src );

				if ( $attachment_id <= 0 ) {
					continue;
				}

				$filename = wp_basename( $src );
				$prompt   = SEOB_AiQueue_Prompt_Builder::for_alt( $page_title, $filename, mb_substr( $plain_text, 0, 500 ) );
				$suggestion = $provider->complete( $prompt );

				if ( is_wp_error( $suggestion ) ) {
					continue;
				}

				SEOB_AiQueue_Repository::insert( $attachment_id, 'alt_text', $suggestion );
				$queued++;
			}
		}

		wp_send_json_success( [ 'queued' => $queued ] );
	}

	/**
	 * Vrátí položky fronty s daným stavem, doplněné o zobrazitelné informace.
	 */
	public function queue_list(): void {
		$this->check_request();

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : SEOB_AiQueue_Repository::STATUS_PENDING;

		$valid_statuses = [ SEOB_AiQueue_Repository::STATUS_PENDING, SEOB_AiQueue_Repository::STATUS_APPROVED, SEOB_AiQueue_Repository::STATUS_REJECTED ];

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = SEOB_AiQueue_Repository::STATUS_PENDING;
		}

		$rows  = SEOB_AiQueue_Repository::get_list( $status );
		$items = [];

		foreach ( $rows as $row ) {
			$items[] = $this->describe_item( $row );
		}

		wp_send_json_success( [ 'items' => $items ] );
	}

	/**
	 * Schválí návrh – zapíše hodnotu do skutečného pole a nastaví status approved.
	 */
	public function queue_approve(): void {
		$this->check_request();

		$item = $this->get_validated_item();

		if ( ! current_user_can( 'edit_post', (int) $item['object_id'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění upravit tento obsah.', 'seo-boost' ) ], 403 );
		}

		$field      = (string) $item['field'];
		$object_id  = (int) $item['object_id'];
		$suggestion = (string) $item['suggestion'];

		if ( 'alt_text' === $field ) {
			update_post_meta( $object_id, '_wp_attachment_image_alt', $suggestion );
		} else {
			update_post_meta( $object_id, $field, $suggestion );
		}

		SEOB_AiQueue_Repository::set_status( (int) $item['id'], SEOB_AiQueue_Repository::STATUS_APPROVED, get_current_user_id() );

		SEOB_Metrics::record( 'ai-queue', 'approved_total', (float) SEOB_AiQueue_Repository::count_by_status( SEOB_AiQueue_Repository::STATUS_APPROVED ) );

		wp_send_json_success();
	}

	/**
	 * Zamítne návrh – jen změní status, hodnotu nezapisuje.
	 */
	public function queue_reject(): void {
		$this->check_request();

		$item = $this->get_validated_item();

		SEOB_AiQueue_Repository::set_status( (int) $item['id'], SEOB_AiQueue_Repository::STATUS_REJECTED, get_current_user_id() );

		SEOB_Metrics::record( 'ai-queue', 'rejected_total', (float) SEOB_AiQueue_Repository::count_by_status( SEOB_AiQueue_Repository::STATUS_REJECTED ) );

		wp_send_json_success();
	}

	/**
	 * Načte a ověří položku fronty z `$_POST['id']`, ukončí požadavek chybou při neplatném ID.
	 *
	 * @return array<string, mixed>
	 */
	private function get_validated_item(): array {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		$item = SEOB_AiQueue_Repository::get( $id );

		if ( null === $item ) {
			wp_send_json_error( [ 'message' => __( 'Návrh nebyl nalezen.', 'seo-boost' ) ], 404 );
		}

		return $item;
	}

	/**
	 * Doplní položku fronty o lidsky čitelný popis pole, název objektu, edit link a aktuální hodnotu.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function describe_item( array $row ): array {
		$object_id = (int) $row['object_id'];
		$field     = (string) $row['field'];

		$labels = [
			'rank_math_title'       => __( 'SERP Title', 'seo-boost' ),
			'rank_math_description' => __( 'Meta description', 'seo-boost' ),
			'alt_text'               => __( 'Alt text obrázku', 'seo-boost' ),
		];

		if ( 'alt_text' === $field ) {
			$object_title  = get_the_title( $object_id );
			$edit_link     = (string) get_edit_post_link( $object_id, 'raw' );
			$current_value = (string) get_post_meta( $object_id, '_wp_attachment_image_alt', true );
			$preview_url   = (string) wp_get_attachment_image_url( $object_id, 'thumbnail' );
		} else {
			$object_title  = get_the_title( $object_id );
			$edit_link     = (string) get_edit_post_link( $object_id, 'raw' );
			$current_value = (string) get_post_meta( $object_id, $field, true );
			$preview_url   = '';
		}

		return [
			'id'            => (int) $row['id'],
			'object_id'     => $object_id,
			'field'         => $field,
			'field_label'   => $labels[ $field ] ?? $field,
			'object_title'  => $object_title,
			'edit_link'     => $edit_link,
			'preview_url'   => $preview_url,
			'current_value' => $current_value,
			'suggestion'    => (string) $row['suggestion'],
			'created_at'    => (string) $row['created_at'],
		];
	}

	/**
	 * Zkrátí obsah příspěvku na čistý text pro vložení do promptu.
	 */
	private function content_excerpt( int $object_id ): string {
		$post = get_post( $object_id );

		if ( null === $post ) {
			return '';
		}

		$plain = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

		return mb_substr( trim( $plain ), 0, 1000 );
	}

	/**
	 * Detekuje generické alt texty typu "image1", "img_1234", "dsc_0001" apod.
	 */
	private function is_generic_alt( string $alt ): bool {
		$alt = trim( $alt );

		if ( '' === $alt ) {
			return false;
		}

		return (bool) preg_match( '/^(img|image|dsc|photo|picture|obrazek|obrázek)[\s_-]*\d*$/i', $alt );
	}

	/**
	 * Sestaví AI providera z uložených nastavení (dešifruje API klíč).
	 *
	 * @return SEOB_AiQueue_Provider_Interface|WP_Error
	 */
	private function build_provider() {
		$settings = SEOB_Settings::get( SEOB_Settings::AI );

		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'seob_ai_disabled', __( 'AI asistent je vypnutý. Zapněte ho v Nastavení.', 'seo-boost' ) );
		}

		$api_key = SEOB_AiQueue_Crypt::decrypt( (string) $settings['api_key_enc'] );

		if ( '' === $settings['endpoint'] || '' === $settings['model'] || '' === $api_key ) {
			return new WP_Error( 'seob_ai_not_configured', __( 'AI asistent není nakonfigurován – doplňte endpoint, model a API klíč v Nastavení.', 'seo-boost' ) );
		}

		return new SEOB_AiQueue_OpenAi_Compatible_Provider( $settings['endpoint'], $api_key, $settings['model'], (int) $settings['max_tokens'] );
	}
}
