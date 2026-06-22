<?php
/**
 * AJAX endpointy pro výchozí schéma podle typu obsahu (stránka Nastavení).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Schema_PostType_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	/**
	 * Klíčová slova ve slugu/názvu typu obsahu => doporučený typ schématu.
	 *
	 * @var array<string,string>
	 */
	private const NAME_HINTS = [
		'produkt'  => 'product',
		'product'  => 'product',
		'eshop'    => 'product',
		'e-shop'   => 'product',
		'sluzb'    => 'service',
		'služb'    => 'service',
		'service'  => 'service',
		'landing'  => 'service',
		'akce'     => 'event',
		'událost'  => 'event',
		'udalost'  => 'event',
		'event'    => 'event',
		'kurz'     => 'course',
		'školení'  => 'course',
		'skoleni'  => 'course',
		'webinář'  => 'course',
		'webinar'  => 'course',
		'kariéra'  => 'jobposting',
		'kariera'  => 'jobposting',
		'job'      => 'jobposting',
		'prace'    => 'jobposting',
		'práce'    => 'jobposting',
		'recipe'   => 'recipe',
		'recept'   => 'recipe',
	];

	public function __construct() {
		add_action( 'wp_ajax_seob_schema_post_types_list', [ $this, 'list_post_types' ] );
		add_action( 'wp_ajax_seob_schema_post_type_save', [ $this, 'save_post_type' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	public function list_post_types(): void {
		$this->check_request();

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$current    = get_option( SEOB_Schema_Helper::POST_TYPE_OPTION );
		$current    = is_array( $current ) ? $current : [];
		$rows       = [];

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$counts = wp_count_posts( $post_type->name );

			$rows[] = [
				'post_type'  => $post_type->name,
				'name'       => $post_type->labels->name ?? $post_type->name,
				'count'      => isset( $counts->publish ) ? (int) $counts->publish : 0,
				'current'    => (string) ( $current[ $post_type->name ] ?? '' ),
				'rm_default' => SEOB_Schema_Helper::get_rank_math_post_type_default( $post_type->name ),
				'suggested'  => $this->suggest_for_post_type( $post_type ),
				'edit_url'   => admin_url( 'edit.php?post_type=' . $post_type->name ),
			];
		}

		wp_send_json_success( [
			'types'        => SEOB_Schema_Helper::TYPES,
			'descriptions' => SEOB_Schema_Helper::TYPE_DESCRIPTIONS,
			'post_types'   => $rows,
		] );
	}

	public function save_post_type(): void {
		$this->check_request();

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$value     = isset( $_POST['value'] ) ? sanitize_key( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! post_type_exists( $post_type ) || ( '' !== $value && ! isset( SEOB_Schema_Helper::TYPES[ $value ] ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		$values = get_option( SEOB_Schema_Helper::POST_TYPE_OPTION );
		$values = is_array( $values ) ? $values : [];

		if ( '' === $value ) {
			unset( $values[ $post_type ] );
		} else {
			$values[ $post_type ] = $value;
		}

		update_option( SEOB_Schema_Helper::POST_TYPE_OPTION, $values );

		wp_send_json_success( [
			'post_type'  => $post_type,
			'value'      => $value,
			'rm_default' => SEOB_Schema_Helper::get_rank_math_post_type_default( $post_type ),
		] );
	}

	/**
	 * Navrhne typ schématu na základě slugu/názvu typu obsahu, jinak rozumný
	 * výchozí odhad (pro `post` článek, pro `page` a ostatní bez návrhu).
	 */
	private function suggest_for_post_type( WP_Post_Type $post_type ): string {
		$haystack = mb_strtolower( $post_type->name . ' ' . ( $post_type->labels->name ?? '' ) );

		foreach ( self::NAME_HINTS as $needle => $type ) {
			if ( false !== mb_strpos( $haystack, $needle ) ) {
				return $type;
			}
		}

		if ( 'post' === $post_type->name ) {
			return 'article';
		}

		return 'off';
	}
}
