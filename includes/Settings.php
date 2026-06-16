<?php
/**
 * Jediný přístupový bod pro čtení/zápis nastavení pluginu (option prefix `seob_`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Settings {

	const GENERAL        = 'seob_general_settings';
	const AUDIT          = 'seob_audit_settings';
	const REDIRECT       = 'seob_redirect_settings';
	const PDF            = 'seob_pdf_settings';
	const SMART_INDEXING = 'seob_smart_indexing_settings';
	const AI             = 'seob_ai_settings';
	const PAGESPEED      = 'seob_pagespeed_settings';

	/**
	 * Výchozí hodnoty pro jednotlivé option klíče.
	 */
	private static function defaults( string $option ): array {
		switch ( $option ) {
			case self::GENERAL:
				return [
					'debug'               => 0,
					'delete_on_uninstall' => 0,
					'modules'             => [
						'audit'          => 1,
						'redirects'      => 1,
						'pdf'            => 1,
						'smart-indexing' => 0,
						'gsc-insights'   => 1,
						'ai-queue'       => 0,
						'pagespeed'      => 0,
						'internal-links' => 0,
					],
				];

			case self::AUDIT:
				return [
					'cron_enabled'   => 1,
					'batch_size'     => 20,
					'thin_content_words' => 300,
					'history_limit'  => 20,
				];

			case self::REDIRECT:
				return [
					'log_404'       => 1,
					'log_retention_days' => 30,
				];

			case self::PDF:
				return self::pdf_defaults();

			case self::AI:
				return [
					'enabled'     => 0,
					'endpoint'    => '',
					'model'       => '',
					'max_tokens'  => 300,
					'api_key_enc' => '',
				];

			case self::PAGESPEED:
				return [
					'enabled'     => 0,
					'api_key_enc' => '',
				];

			case self::SMART_INDEXING:
				return [
					'profile'                => 'catalog',
					'mode'                   => 'dry_run',
					'company_post_type'      => '',
					'category_taxonomy'      => '',
					'location_taxonomy'      => '',
					'service_taxonomy'       => '',
					'min_companies'          => 5,
					'completeness_threshold' => 60,
					'max_depth'              => 2,
					'blacklist_params'       => 'sort, order, orderby, open_now, rating, verified, has_phone, has_website, accepts_requests, price_level, distance, radius, map_bounds, lat, lng, page_size, view, utm_source, utm_medium, utm_campaign, utm_term, utm_content, fbclid, gclid, sessionid',
				];

			default:
				return [];
		}
	}

	/**
	 * Výchozí texty pro PDF report – dopad nálezu, přínos opravy a obchodní
	 * nabídky dle celkového skóre. Vše editovatelné v Nastavení.
	 */
	private static function pdf_defaults(): array {
		return [
			'issue_texts' => [
				'title_missing' => [
					'impact'  => 'Stránka se ve výsledcích vyhledávání zobrazuje s automaticky generovaným nebo neúplným titulkem, což snižuje míru prokliku (CTR) a může vést k nižšímu umístění ve vyhledávání.',
					'benefit' => 'Doplněním optimalizovaného titulku zvýšíte atraktivitu výpisu ve vyhledávání, zlepšíte CTR a posílíte relevanci stránky pro cílová klíčová slova.',
				],
				'title_too_long' => [
					'impact'  => 'Příliš dlouhý titulek se ve výsledcích vyhledávání ořízne a uživatel nevidí celé sdělení, což snižuje srozumitelnost a důvěryhodnost výpisu.',
					'benefit' => 'Zkrácením titulku na doporučenou délku zajistíte jeho kompletní zobrazení ve výsledcích vyhledávání a zvýšíte šanci na proklik.',
				],
				'description_missing' => [
					'impact'  => 'Bez meta description si Google sám vybere úryvek textu ze stránky, který často nepůsobí přesvědčivě a nemotivuje k prokliku.',
					'benefit' => 'Vlastní popisek umožní zdůraznit klíčové výhody stránky či produktu a zvýší míru prokliku z výsledků vyhledávání.',
				],
				'description_too_long' => [
					'impact'  => 'Dlouhý popisek se ve výsledcích vyhledávání ořízne, takže se k uživateli nemusí dostat nejdůležitější část sdělení, např. výzva k akci.',
					'benefit' => 'Úprava délky popisku zajistí, že se zobrazí celé klíčové sdělení včetně výzvy k akci.',
				],
				'duplicate_title' => [
					'impact'  => 'Stejný titulek na více stránkách znesnadňuje vyhledávačům i uživatelům rozlišit, která stránka je pro daný dotaz relevantní, a může snižovat viditelnost obou stránek.',
					'benefit' => 'Unikátní titulky pro každou stránku zlepší orientaci ve výsledcích vyhledávání a posílí šance na lepší umístění obou stránek.',
				],
				'duplicate_description' => [
					'impact'  => 'Duplicitní popisky snižují jedinečnost jednotlivých stránek v očích vyhledávačů a mohou vést k tomu, že si Google vybere vlastní, méně atraktivní text.',
					'benefit' => 'Jedinečný popisek pro každou stránku zvýrazní její konkrétní přínos a zlepší míru prokliku.',
				],
				'h1_missing' => [
					'impact'  => 'Chybějící hlavní nadpis H1 ztěžuje vyhledávačům i uživatelům rychlé pochopení tématu stránky a může negativně ovlivnit hodnocení relevance.',
					'benefit' => 'Doplnění výstižného H1 nadpisu zlepší srozumitelnost obsahu pro uživatele i vyhledávače a posílí SEO relevanci stránky.',
				],
				'h1_duplicate' => [
					'impact'  => 'Více hlavních nadpisů H1 na jedné stránce oslabuje signál o tom, co je hlavním tématem stránky, a může mást vyhledávače i uživatele.',
					'benefit' => 'Ponechání jednoho jasného H1 posílí tematickou strukturu stránky a zlepší její srozumitelnost.',
				],
				'heading_hierarchy' => [
					'impact'  => 'Přeskočená úroveň nadpisů (např. z H2 rovnou na H4) narušuje logickou strukturu obsahu, ztěžuje orientaci uživatelům se čtečkami obrazovky a může mírně snížit srozumitelnost pro vyhledávače.',
					'benefit' => 'Logická hierarchie nadpisů zlepší přístupnost obsahu a usnadní vyhledávačům pochopit strukturu a důležitost jednotlivých částí stránky.',
				],
				'missing_alt' => [
					'impact'  => 'Obrázky bez alt textu jsou pro vyhledávače neviditelné a nepřístupné pro uživatele se čtečkami obrazovky – stránka tak přichází o dodatečnou viditelnost v obrazovém vyhledávání a snižuje přístupnost webu.',
					'benefit' => 'Doplnění popisných alt textů zlepší přístupnost webu, otevře možnost umístění v Google obrázcích a posílí celkovou SEO relevanci stránky.',
				],
				'schema_missing' => [
					'impact'  => 'Bez strukturovaných dat (schema) nemůže Google zobrazit rozšířené výsledky, např. recenze, ceny nebo FAQ, což snižuje viditelnost a atraktivitu výpisu oproti konkurenci.',
					'benefit' => 'Nastavení strukturovaných dat umožní zobrazení obohacených výsledků ve vyhledávání, což zvyšuje důvěryhodnost a míru prokliku.',
				],
				'noindex_set' => [
					'impact'  => 'Stránka je nastavena tak, aby ji vyhledávače neindexovaly – i kvalitní obsah je tak pro nové návštěvníky z vyhledávání prakticky neviditelný.',
					'benefit' => 'Odstraněním nastavení noindex, pokud nebylo zvoleno záměrně, stránka začne být indexována a může přivádět organickou návštěvnost.',
				],
				'thin_content' => [
					'impact'  => 'Stránka obsahuje málo textového obsahu, což vyhledávače mohou vyhodnotit jako nízkou hodnotu pro uživatele a snížit její umístění ve výsledcích.',
					'benefit' => 'Rozšíření obsahu o relevantní informace zvýší hodnotu stránky pro uživatele i vyhledávače a podpoří lepší umístění ve výsledcích.',
				],
				'focus_keyword_missing' => [
					'impact'  => 'Bez nastaveného klíčového slova chybí jasný cíl optimalizace stránky, což ztěžuje měření a další zlepšování její viditelnosti pro konkrétní dotazy.',
					'benefit' => 'Nastavení klíčového slova umožní cílenou optimalizaci obsahu i sledování pozic ve vyhledávání pro konkrétní téma.',
				],
			],
			'offer_templates' => [
				'maintenance' => [
					'name' => 'Údržba SEO (skóre 85+)',
					'body' => "Web {site_name} dosahuje při SEO auditu velmi dobrého průměrného skóre {score}/100. Nalezené nedostatky jsou převážně drobného charakteru ({critical_count} kritických, {warning_count} varování) a jejich odstranění zabere řádově hodiny.\n\nDoporučujeme balíček Údržba SEO – pravidelnou měsíční kontrolu a drobné opravy, abychom udrželi web v aktuálně výborné kondici a včas reagovali na nové nálezy.",
				],
				'standard' => [
					'name' => 'Standardní SEO optimalizace (skóre 60–84)',
					'body' => "Web {site_name} dosáhl při SEO auditu průměrného skóre {score}/100. Bylo identifikováno {critical_count} kritických nálezů a {warning_count} varování, které doporučujeme postupně odstranit.\n\nDoporučujeme Standardní balíček SEO optimalizace – systematickou opravu nalezených nedostatků (title, description, nadpisy, alt texty, strukturovaná data) s následnou kontrolou výsledků.",
				],
				'comprehensive' => [
					'name' => 'Komplexní SEO balíček (skóre pod 60)',
					'body' => "Web {site_name} dosáhl při SEO auditu skóre {score}/100, což ukazuje na zásadní rezervy v technickém i obsahovém SEO. Nalezli jsme {critical_count} kritických problémů a {warning_count} varování, které významně omezují viditelnost webu ve vyhledávačích.\n\nDoporučujeme Komplexní SEO balíček – kompletní revizi a přepracování klíčových SEO prvků napříč webem, nastavení strukturovaných dat, optimalizaci obsahu a následný monitoring zlepšení.",
				],
			],
			'company' => [
				'name'           => '',
				'contact_person' => '',
				'ico'            => '',
				'contact'        => '',
				'footer_text'    => '',
				'logo_id'        => 0,
				'logo_url'       => '',
				'accent_color'   => '#2271b1',
			],
			'report' => [
				'detailed_pages_limit' => 12,
			],
		];
	}

	/**
	 * Vrátí nastavení sloučené s výchozími hodnotami.
	 */
	public static function get( string $option ): array {
		$stored = get_option( $option, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_replace_recursive( self::defaults( $option ), $stored );
	}

	/**
	 * Uloží nastavení (přepíše celé pole pro daný option klíč).
	 */
	public static function update( string $option, array $value ): bool {
		return update_option( $option, $value );
	}
}
