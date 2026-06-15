<?php
/**
 * AJAX endpointy pro výchozí schéma kategorií (stránka Nastavení).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Schema_Category_Ajax {

	const NONCE_ACTION = 'seob_admin_nonce';

	/**
	 * Klíčová slova v názvu kategorie => doporučený typ schématu.
	 *
	 * @var array<string,string>
	 */
	private const NAME_HINTS = [
		'produkt'  => 'product',
		'eshop'    => 'product',
		'e-shop'   => 'product',
		'služ'     => 'service',
		'sluz'     => 'service',
		'service'  => 'service',
		'akce'     => 'event',
		'událost'  => 'event',
		'event'    => 'event',
		'kurz'     => 'course',
		'školení'  => 'course',
		'webinář'  => 'course',
		'práce'    => 'jobposting',
		'kariéra'  => 'jobposting',
	];

	public function __construct() {
		add_action( 'wp_ajax_seob_schema_categories_list', [ $this, 'list_categories' ] );
		add_action( 'wp_ajax_seob_schema_category_save', [ $this, 'save_category' ] );
	}

	private function check_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nemáte oprávnění.', 'seo-boost' ) ], 403 );
		}
	}

	public function list_categories(): void {
		$this->check_request();

		$categories = get_categories( [ 'hide_empty' => false ] );
		$rows       = [];

		foreach ( $categories as $category ) {
			$current = (string) get_term_meta( $category->term_id, SEOB_Schema_Helper::CATEGORY_META_KEY, true );

			$rows[] = [
				'term_id'   => $category->term_id,
				'name'      => $category->name,
				'count'     => (int) $category->count,
				'current'   => $current,
				'suggested' => $this->suggest_for_category( $category->name ),
				'edit_url'  => admin_url( 'edit.php?category_name=' . $category->slug ),
			];
		}

		wp_send_json_success( [
			'types'        => SEOB_Schema_Helper::TYPES,
			'descriptions' => SEOB_Schema_Helper::TYPE_DESCRIPTIONS,
			'categories'   => $rows,
		] );
	}

	public function save_category(): void {
		$this->check_request();

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$value   = isset( $_POST['value'] ) ? sanitize_key( wp_unslash( $_POST['value'] ) ) : '';

		if ( $term_id <= 0 || ! get_term( $term_id, 'category' ) || ( '' !== $value && ! isset( SEOB_Schema_Helper::TYPES[ $value ] ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Neplatný požadavek.', 'seo-boost' ) ], 400 );
		}

		if ( '' === $value ) {
			delete_term_meta( $term_id, SEOB_Schema_Helper::CATEGORY_META_KEY );
		} else {
			update_term_meta( $term_id, SEOB_Schema_Helper::CATEGORY_META_KEY, $value );
		}

		wp_send_json_success( [ 'term_id' => $term_id, 'value' => $value ] );
	}

	/**
	 * Navrhne typ schématu na základě klíčových slov v názvu kategorie,
	 * jinak výchozí "article" (běžné pro blogové kategorie).
	 */
	private function suggest_for_category( string $name ): string {
		$name = mb_strtolower( $name );

		foreach ( self::NAME_HINTS as $needle => $type ) {
			if ( false !== mb_strpos( $name, $needle ) ) {
				return $type;
			}
		}

		return 'article';
	}
}
