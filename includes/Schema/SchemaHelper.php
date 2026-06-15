<?php
/**
 * Pomocník pro práci se schématy (Rank Math rich snippet) – zjištění aktuálního
 * typu schématu, výchozích hodnot dle typu obsahu a výchozích hodnot dle kategorie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Schema_Helper {

	/**
	 * Klíč term meta pro výchozí schéma kategorie.
	 */
	const CATEGORY_META_KEY = 'seob_default_schema';

	/**
	 * Klíč options pro výchozí schéma podle typu obsahu (post type).
	 *
	 * @var string
	 */
	const POST_TYPE_OPTION = 'seob_default_schema_post_types';

	/**
	 * Podporované typy schémat (Rank Math rich snippet) a jejich české popisky.
	 *
	 * @var array<string,string>
	 */
	const TYPES = [
		'off'        => 'Běžná stránka (bez schématu / výchozí WebPage)',
		'article'    => 'Článek (Article)',
		'service'    => 'Služba (Service)',
		'product'    => 'Produkt (Product)',
		'event'      => 'Akce / událost (Event)',
		'course'     => 'Kurz (Course)',
		'jobposting' => 'Pracovní nabídka (JobPosting)',
		'book'       => 'Kniha (Book)',
		'music'      => 'Hudba (Music)',
		'recipe'     => 'Recept (Recipe)',
		'restaurant' => 'Restaurace (Restaurant)',
		'video'      => 'Video (Video)',
		'person'     => 'Osoba (Person)',
		'software'   => 'Softwarová aplikace (SoftwareApplication)',
	];

	/**
	 * Krátké popisy typů schémat – co znamenají a kdy je vhodné je použít.
	 * Zobrazují se jako nápověda u výběru typu na stránce Nastavení.
	 *
	 * @var array<string,string>
	 */
	const TYPE_DESCRIPTIONS = [
		'off'        => 'Bez specifického typu (WebPage). Vhodné pro stránky mimo hlavní obsah – kontakty, GDPR, děkovací stránky apod. Tato volba přebije i výchozí nastavení Rank Math pro daný typ obsahu/kategorii.',
		'article'    => 'Články, blogové příspěvky a aktuality. Google může zobrazit datum publikace, autora a náhledový obrázek ve výsledcích vyhledávání.',
		'service'    => 'Stránky popisující nabízenou službu (např. „Tvorba webů“, „SEO konzultace“). Umožňuje zobrazit poskytovatele, oblast působnosti a cenu.',
		'product'    => 'Produktové stránky e-shopu. Umožňuje zobrazit cenu, dostupnost a hodnocení přímo ve výsledcích (rich snippet s hvězdičkami a cenou).',
		'event'      => 'Akce, semináře a konference s konkrétním datem a místem konání. Google může zobrazit datum a místo přímo ve výsledcích.',
		'course'     => 'Online kurzy, školení a webináře. Umožňuje zobrazit poskytovatele kurzu a hodnocení.',
		'jobposting' => 'Nabídky volných pracovních pozic – umožňuje zobrazení ve vyhledávání Google Jobs.',
		'book'       => 'Stránky popisující knihu – autor, ISBN, hodnocení.',
		'music'      => 'Hudební alba, skladby nebo profily umělců.',
		'recipe'     => 'Recepty – umožňuje zobrazit dobu přípravy, kalorie a hodnocení přímo ve výsledcích vyhledávání.',
		'restaurant' => 'Stránky restaurací – otevírací doba, typ kuchyně, cenová kategorie, hodnocení.',
		'video'      => 'Stránky, kde je hlavním obsahem video – umožňuje zobrazit náhled a délku videa ve výsledcích.',
		'person'     => 'Profilové stránky osob (např. členové týmu na stránce „O nás“, autoři).',
		'software'   => 'Stránky popisující softwarovou aplikaci – umožňuje zobrazit hodnocení, cenu a podporované platformy.',
	];

	/**
	 * Brání nekonečné rekurzi – `metadata_exists()` interně spouští stejný
	 * `get_post_metadata` filtr, který sám volá `metadata_exists()`.
	 */
	private static bool $checking_existence = false;

	public function __construct() {
		add_filter( 'get_post_metadata', [ $this, 'filter_rich_snippet_meta' ], 10, 4 );
	}

	/**
	 * Pokud post nemá vlastní `rank_math_rich_snippet`, ale jeho kategorie má
	 * nastavené výchozí schéma, použije se automaticky bez nutnosti ukládat
	 * meta na každý jednotlivý příspěvek.
	 *
	 * @param mixed  $value
	 * @return mixed
	 */
	public function filter_rich_snippet_meta( $value, int $object_id, string $meta_key, bool $single ) {
		if ( 'rank_math_rich_snippet' !== $meta_key || null !== $value || self::$checking_existence ) {
			return $value;
		}

		if ( self::has_override( $object_id ) ) {
			return $value;
		}

		$post = get_post( $object_id );

		if ( ! $post ) {
			return $value;
		}

		$category_default = self::get_category_default( $post );

		if ( null !== $category_default ) {
			return $single ? $category_default : [ $category_default ];
		}

		$post_type_override = self::get_post_type_override( $post->post_type );

		if ( null !== $post_type_override ) {
			return $single ? $post_type_override : [ $post_type_override ];
		}

		return $value;
	}

	/**
	 * Zjistí, zda má příspěvek skutečně uložené vlastní `rank_math_rich_snippet`
	 * (na rozdíl od hodnoty doplněné naším `get_post_metadata` filtrem).
	 *
	 * Dočasně potlačí vlastní filtr, aby `metadata_exists()` nevracel "true"
	 * jen kvůli kategoriovému výchozímu schématu.
	 */
	public static function has_override( int $post_id ): bool {
		self::$checking_existence = true;
		$exists                   = metadata_exists( 'post', $post_id, 'rank_math_rich_snippet' );
		self::$checking_existence = false;

		return $exists;
	}

	/**
	 * Výchozí typ schématu pro daný typ obsahu nastavený v SEO Booster Pro
	 * (Nastavení → Výchozí schéma podle typu obsahu), pokud existuje.
	 */
	public static function get_post_type_override( string $post_type ): ?string {
		$values = get_option( self::POST_TYPE_OPTION );

		if ( ! is_array( $values ) || ! isset( $values[ $post_type ] ) ) {
			return null;
		}

		$value = (string) $values[ $post_type ];

		if ( ! isset( self::TYPES[ $value ] ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Výchozí typ schématu pro daný typ obsahu – primárně dle nastavení SEO Booster Pro,
	 * jinak dle nastavení Rank Math.
	 *
	 * @return array{type:string,explicit:bool} `explicit` je `true`, pokud hodnota
	 *         pochází z vlastního nastavení SEO Booster Pro (i `'off'` = záměrná
	 *         volba „běžná stránka“), `false` pokud jde jen o fallback na Rank Math.
	 */
	public static function get_post_type_default( string $post_type ): array {
		$override = self::get_post_type_override( $post_type );

		if ( null !== $override ) {
			return [ 'type' => $override, 'explicit' => true ];
		}

		return [ 'type' => self::get_rank_math_post_type_default( $post_type ), 'explicit' => false ];
	}

	/**
	 * Vrátí typ schématu, který by se použil podle globálního nastavení Rank Math
	 * pro daný typ obsahu, bez ohledu na vlastní override SEO Booster Pro.
	 */
	public static function get_rank_math_post_type_default( string $post_type ): string {
		$options = get_option( 'rank-math-options-titles' );

		return is_array( $options ) ? (string) ( $options[ "pt_{$post_type}_default_rich_snippet" ] ?? 'off' ) : 'off';
	}

	/**
	 * Výchozí schéma nastavené pro kategorii příspěvku (term meta), pokud existuje.
	 * Vrací i `'off'` (záměrná volba „běžná stránka“ pro celou kategorii).
	 */
	public static function get_category_default( WP_Post $post ): ?string {
		$terms = get_the_terms( $post->ID, 'category' );

		if ( ! is_array( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			$value = get_term_meta( $term->term_id, self::CATEGORY_META_KEY, true );

			if ( '' !== $value && isset( self::TYPES[ $value ] ) ) {
				return (string) $value;
			}
		}

		return null;
	}

	/**
	 * Zjistí efektivní typ schématu pro příspěvek a zdroj, ze kterého pochází:
	 * - `override`           – nastaveno přímo na příspěvku (Rank Math metabox)
	 * - `category_default`   – odvozeno z výchozího schématu kategorie
	 * - `post_type_default`  – odvozeno z globálního nastavení Rank Math pro daný typ obsahu
	 *
	 * @return array{type:string,source:string,is_explicit:bool}
	 */
	public static function get_effective_type( WP_Post $post ): array {
		if ( self::has_override( $post->ID ) ) {
			$value = (string) get_post_meta( $post->ID, 'rank_math_rich_snippet', true );

			return [
				'type'        => '' !== $value ? $value : 'off',
				'source'      => 'override',
				'is_explicit' => true,
			];
		}

		$category_default = self::get_category_default( $post );

		if ( null !== $category_default ) {
			return [
				'type'        => $category_default,
				'source'      => 'category_default',
				'is_explicit' => true,
			];
		}

		$post_type_default = self::get_post_type_default( $post->post_type );

		return [
			'type'        => $post_type_default['type'],
			'source'      => 'post_type_default',
			'is_explicit' => $post_type_default['explicit'],
		];
	}
}
