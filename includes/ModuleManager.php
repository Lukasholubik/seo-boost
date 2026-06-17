<?php
/**
 * Registr modulů pluginu – závislosti, inicializace, detekce Rank Math.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Module_Manager {

	const MODULES = [
		'audit'     => [
			'label'       => 'Audit Dashboard',
			'description' => 'Skenuje obsah webu a hlídá SEO problémy (title, popisky, schema, thin content...).',
			'classes'     => [ 'SEOB_Audit_Ajax', 'SEOB_Schema_Category_Ajax', 'SEOB_Schema_PostType_Ajax' ],
			'depends_on'  => [],
		],
		'redirects' => [
			'label'       => 'Redirect Manager',
			'description' => 'Loguje 404 požadavky a umožňuje vytvářet 301 přesměrování.',
			'classes'     => [ 'SEOB_Redirects_Ajax', 'SEOB_Redirect_Manager' ],
			'depends_on'  => [],
		],
		'pdf'       => [
			'label'       => 'Export PDF reportu',
			'description' => 'Generuje PDF report z výsledků auditu pro klienty.',
			'classes'     => [ 'SEOB_Pdf_Ajax' ],
			'depends_on'  => [ 'audit' ],
		],
		'smart-indexing' => [
			'label'       => 'Chytrá indexace',
			'description' => 'Řídí indexaci katalogových/filtrovaných stránek a kombinací obor × lokalita, chrání před index bloatem.',
			'classes'     => [ 'SEOB_SmartIndexing_Ajax', 'SEOB_SmartIndexing_Frontend' ],
			'depends_on'  => [],
		],
		'gsc-insights' => [
			'label'       => 'Search Console statistiky (Rank Math)',
			'description' => 'Doplní Audit Dashboard o zobrazení, kliky, CTR a pozici z dat Search Console, pokud má Rank Math připojený svůj modul Analytics.',
			'classes'     => [],
			'depends_on'  => [ 'audit' ],
		],
		'ai-queue' => [
			'label'       => 'AI schvalovací fronta',
			'description' => 'AI návrhy titulků, popisků a alt textů procházejí schvalovací frontou – nic se neuloží bez potvrzení administrátorem.',
			'classes'     => [ 'SEOB_AiQueue_Ajax' ],
			'depends_on'  => [ 'audit' ],
		],
		'pagespeed' => [
			'label'       => 'PageSpeed Insights (Lighthouse)',
			'description' => 'Pravidelně testuje vzorek stránek přes Google PageSpeed Insights/Lighthouse a shrnuje SEO doporučení podle typu obsahu.',
			'classes'     => [ 'SEOB_PageSpeed_Ajax' ],
			'depends_on'  => [],
		],
		'internal-links' => [
			'label'       => 'Interní prolinkování',
			'description' => 'Indexuje interní odkazy, hledá osamocené (orphan) stránky a navrhuje prolinkování na základě podobnosti obsahu.',
			'classes'     => [ 'SEOB_InternalLinks_Ajax', 'SEOB_InternalLinks_Indexer', 'SEOB_InternalLinks_MetaBox' ],
			'depends_on'  => [],
		],
		'hreflang' => [
			'label'       => 'Hreflang Manager',
			'description' => 'Spravuje hreflang skupiny a vkládá <link rel="alternate" hreflang> tagy pro vícejazyčné weby.',
			'classes'     => [ 'SEOB_Hreflang_Ajax', 'SEOB_Hreflang_Manager' ],
			'depends_on'  => [],
		],
		'local-seo' => [
			'label'       => 'Local SEO (CZ)',
			'description' => 'Vkládá LocalBusiness JSON-LD schéma s IČO/DIČ, GPS souřadnicemi a otevírací dobou. Součástí je NAP scanner pro konzistenci kontaktních údajů.',
			'classes'     => [ 'SEOB_LocalSeo_Ajax', 'SEOB_LocalSeo_Frontend' ],
			'depends_on'  => [],
		],
		'json-ld' => [
			'label'       => 'JSON-LD Validátor',
			'description' => 'Extrahuje strukturovaná data z renderovaných stránek, validuje je vůči schema.org a detekuje duplicitní schémata, která mohou potlačit rich snippety.',
			'classes'     => [ 'SEOB_JsonLd_ScanRunner', 'SEOB_JsonLd_Ajax' ],
			'depends_on'  => [],
		],
		'cwv-rum' => [
			'label'       => 'Core Web Vitals (RUM)',
			'description' => 'Měří LCP, INP, CLS, FCP, TTFB od reálných uživatelů. Anonymní beacon (bez cookies), denní p75 agregace, graf trendu a tabulka nejhorších URL.',
			'classes'     => [ 'SEOB_CWV_BeaconEndpoint', 'SEOB_CWV_Aggregator', 'SEOB_CWV_Ajax' ],
			'depends_on'  => [],
		],
		'js-render-gap' => [
			'label'       => 'JS Render Gap',
			'description' => 'Detekuje obsah skrytý Googlu bez JS renderování. Porovnává raw HTML (jak ho crawluje Google) s DOM zachycenym od reálných návštěvníků.',
			'classes'     => [ 'SEOB_JsGap_BeaconReceiver', 'SEOB_JsGap_ScanRunner', 'SEOB_JsGap_Ajax' ],
			'depends_on'  => [],
		],
		'http-headers' => [
			'label'       => 'HTTP Hlavičky & Bezpečnost',
			'description' => 'Skenuje HTTP odpovědi stránek – bezpečnostní hlavičky (HSTS, X-Frame-Options...), x-robots-tag, HTTPS a cache hlavičky.',
			'classes'     => [ 'SEOB_HttpHeaders_ScanRunner', 'SEOB_HttpHeaders_Ajax' ],
			'depends_on'  => [],
		],
	];

	/**
	 * Je modul zapnutý a má splněné závislosti?
	 */
	public static function is_active( string $module_id ): bool {
		if ( ! isset( self::MODULES[ $module_id ] ) ) {
			return false;
		}

		$modules = SEOB_Settings::get( SEOB_Settings::GENERAL )['modules'];

		if ( empty( $modules[ $module_id ] ) ) {
			return false;
		}

		foreach ( self::MODULES[ $module_id ]['depends_on'] as $dependency ) {
			if ( empty( $modules[ $dependency ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Inicializuje třídy aktivních modulů (nahrazuje ad-hoc bloky v Plugin.php).
	 */
	public static function init_active(): void {
		foreach ( self::MODULES as $module_id => $module ) {
			if ( ! self::is_active( $module_id ) ) {
				continue;
			}

			foreach ( $module['classes'] as $class ) {
				new $class();
			}
		}
	}

	/**
	 * Vrátí registr modulů obohacený o stav (active, dependency_ok).
	 */
	public static function get_modules(): array {
		$modules = SEOB_Settings::get( SEOB_Settings::GENERAL )['modules'];
		$result  = [];

		foreach ( self::MODULES as $module_id => $module ) {
			$enabled        = ! empty( $modules[ $module_id ] );
			$dependency_ok  = true;

			foreach ( $module['depends_on'] as $dependency ) {
				if ( empty( $modules[ $dependency ] ) ) {
					$dependency_ok = false;
					break;
				}
			}

			$result[ $module_id ] = array_merge(
				$module,
				[
					'id'             => $module_id,
					'enabled'        => $enabled,
					'dependency_ok'  => $dependency_ok,
					'active'         => $enabled && $dependency_ok,
				]
			);
		}

		return $result;
	}

	/**
	 * Detekce aktivního Rank Math.
	 */
	public static function is_rank_math_active(): bool {
		return class_exists( 'RankMath' );
	}
}
