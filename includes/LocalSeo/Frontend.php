<?php
/**
 * Local SEO – výstup LocalBusiness JSON-LD schématu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_LocalSeo_Frontend {

	public function __construct() {
		add_action( 'wp_head', [ $this, 'output_schema' ], 5 );
	}

	public function output_schema(): void {
		if ( self::has_rank_math_local_seo() ) {
			return;
		}

		$s = SEOB_Settings::get( SEOB_Settings::LOCAL_SEO );

		if ( empty( $s['business_name'] ) ) {
			return;
		}

		if ( ! $this->should_output( $s ) ) {
			return;
		}

		$schema = self::build_schema( $s );

		echo '<script type="application/ld+json">' . "\n"
			. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			. "\n</script>\n";
	}

	private function should_output( array $s ): bool {
		switch ( $s['output_on'] ?? 'homepage' ) {
			case 'all':
				return true;
			case 'contact':
				$page_id = (int) ( $s['contact_page_id'] ?? 0 );
				return is_singular() && $page_id > 0 && get_the_ID() === $page_id;
			case 'homepage':
			default:
				return is_front_page();
		}
	}

	/**
	 * Sestaví pole schématu z nastavení.
	 */
	public static function build_schema( array $s ): array {
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => ! empty( $s['business_type'] ) ? $s['business_type'] : 'LocalBusiness',
			'name'     => $s['business_name'],
			'url'      => home_url( '/' ),
		];

		if ( ! empty( $s['description'] ) ) {
			$schema['description'] = $s['description'];
		}

		if ( ! empty( $s['phone'] ) ) {
			$schema['telephone'] = $s['phone'];
		}

		if ( ! empty( $s['email'] ) ) {
			$schema['email'] = $s['email'];
		}

		// Adresa
		$has_address = ! empty( $s['address_street'] ) || ! empty( $s['address_city'] ) || ! empty( $s['address_zip'] );
		if ( $has_address ) {
			$addr = [ '@type' => 'PostalAddress' ];
			if ( ! empty( $s['address_street'] ) ) $addr['streetAddress']   = $s['address_street'];
			if ( ! empty( $s['address_city'] ) )   $addr['addressLocality'] = $s['address_city'];
			if ( ! empty( $s['address_zip'] ) )    $addr['postalCode']      = $s['address_zip'];
			$addr['addressCountry'] = ! empty( $s['address_country'] ) ? $s['address_country'] : 'CZ';
			$schema['address']      = $addr;
		}

		// GPS
		if ( ! empty( $s['lat'] ) && ! empty( $s['lng'] ) ) {
			$schema['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $s['lat'],
				'longitude' => (float) $s['lng'],
			];
		}

		// Otevírací doba
		$days_map = [
			'Mo' => 'https://schema.org/Monday',
			'Tu' => 'https://schema.org/Tuesday',
			'We' => 'https://schema.org/Wednesday',
			'Th' => 'https://schema.org/Thursday',
			'Fr' => 'https://schema.org/Friday',
			'Sa' => 'https://schema.org/Saturday',
			'Su' => 'https://schema.org/Sunday',
		];

		$hours_spec = [];
		foreach ( ( $s['opening_hours'] ?? [] ) as $day => $hours ) {
			if ( ! empty( $hours['closed'] ) ) {
				continue;
			}
			if ( empty( $hours['open'] ) || empty( $hours['close'] ) ) {
				continue;
			}
			if ( ! isset( $days_map[ $day ] ) ) {
				continue;
			}
			$hours_spec[] = [
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $days_map[ $day ],
				'opens'     => $hours['open'],
				'closes'    => $hours['close'],
			];
		}

		if ( ! empty( $hours_spec ) ) {
			$schema['openingHoursSpecification'] = $hours_spec;
		}

		// IČO, DIČ
		$identifiers = [];
		if ( ! empty( $s['ico'] ) ) {
			$identifiers[] = [ '@type' => 'PropertyValue', 'name' => 'IČO', 'value' => $s['ico'] ];
		}
		if ( ! empty( $s['dic'] ) ) {
			$identifiers[] = [ '@type' => 'PropertyValue', 'name' => 'DIČ', 'value' => $s['dic'] ];
		}
		if ( ! empty( $identifiers ) ) {
			$schema['identifier'] = $identifiers;
		}

		if ( ! empty( $s['price_range'] ) ) {
			$schema['priceRange'] = $s['price_range'];
		}

		if ( ! empty( $s['image_url'] ) ) {
			$schema['image'] = $s['image_url'];
		}

		return $schema;
	}

	/**
	 * Detekuje, zda Rank Math Local SEO modul výstup hreflang/schématu převzal.
	 */
	public static function has_rank_math_local_seo(): bool {
		if ( class_exists( 'RankMath\Modules\LocalSeo\LocalSeo' ) ) {
			return true;
		}

		$active = get_option( 'rank_math_modules', [] );
		return is_array( $active ) && in_array( 'local-seo', $active, true );
	}

	/**
	 * Vrátí seznam dostupných typů podnikání (schema.org LocalBusiness subtypes).
	 *
	 * @return array<string,string>
	 */
	public static function business_types(): array {
		return [
			'LocalBusiness'              => 'Obecná firma / živnost',
			'ProfessionalService'        => 'Profesionální služby (obecně)',
			'LegalService'               => 'Právní služby (advokát, notář)',
			'AccountingService'          => 'Účetní / daňový poradce',
			'FinancialService'           => 'Finanční služby',
			'InsuranceAgency'            => 'Pojišťovna / pojišťovací agent',
			'RealEstateAgent'            => 'Realitní kancelář',
			'MedicalBusiness'            => 'Zdravotní péče (obecně)',
			'Dentist'                    => 'Zubní lékař',
			'Physician'                  => 'Lékař / ordinace',
			'MedicalClinic'              => 'Klinika',
			'Pharmacy'                   => 'Lékárna',
			'VeterinaryCare'             => 'Veterinář',
			'HomeAndConstructionBusiness'=> 'Stavebnictví / řemesla',
			'HVACBusiness'               => 'Topení / klimatizace',
			'Plumber'                    => 'Instalatér',
			'Electrician'                => 'Elektrikář',
			'GeneralContractor'          => 'Stavební firma',
			'Locksmith'                  => 'Zámečník',
			'CleaningService'            => 'Úklidová firma',
			'MovingCompany'              => 'Stěhovací firma',
			'AutoRepair'                 => 'Autoservis',
			'AutoDealer'                 => 'Autobazar / autosalon',
			'GasStation'                 => 'Čerpací stanice',
			'Restaurant'                 => 'Restaurace',
			'CafeOrCoffeeShop'           => 'Kavárna / café',
			'Bakery'                     => 'Pekárna / cukrárna',
			'BarOrPub'                   => 'Bar / hospoda',
			'FastFoodRestaurant'         => 'Rychlé občerstvení',
			'BeautySalon'                => 'Kosmetický salon',
			'HairSalon'                  => 'Kadeřnictví',
			'NailSalon'                  => 'Nehty / manikúra',
			'HealthAndBeautyBusiness'    => 'Zdraví a krása (obecně)',
			'GroceryStore'               => 'Potraviny / obchod',
			'ClothingStore'              => 'Prodejna oblečení',
			'FurnitureStore'             => 'Prodejna nábytku',
			'ElectronicsStore'           => 'Elektro / elektronika',
			'BookStore'                  => 'Knihkupectví',
			'PetStore'                   => 'Chovatelské potřeby',
			'FlowerShop'                 => 'Květinářství',
			'SportsGoodsStore'           => 'Sportovní potřeby',
			'ShoppingCenter'             => 'Obchodní centrum',
			'Hotel'                      => 'Hotel',
			'LodgingBusiness'            => 'Ubytování (penzion, hostel)',
			'TravelAgency'               => 'Cestovní kancelář / agentura',
			'SportsActivityLocation'     => 'Sportovní centrum / tělocvična',
			'ChildCare'                  => 'Hlídání dětí / školka',
			'Library'                    => 'Knihovna',
			'Museum'                     => 'Muzeum / galerie',
		];
	}
}
