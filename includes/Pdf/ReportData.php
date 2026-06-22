<?php
/**
 * Připraví data pro PDF report (obchodní materiál) z výsledků jednoho scanu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Pdf_Report_Data {

	/**
	 * České popisky typů nálezů – stejné jako ISSUE_LABELS v audit-dashboard.js.
	 *
	 * @var array<string,string>
	 */
	private const ISSUE_LABELS = [
		'title_missing'         => 'Chybí SERP title',
		'title_too_long'        => 'Title je příliš dlouhý',
		'description_missing'   => 'Chybí meta description',
		'description_too_long'  => 'Description je příliš dlouhý',
		'duplicate_title'       => 'Duplicitní title (shoduje se s jinou stránkou)',
		'duplicate_description' => 'Duplicitní meta description',
		'h1_missing'            => 'Chybí nadpis H1',
		'h1_duplicate'          => 'Více než jeden H1',
		'heading_hierarchy'     => 'Přeskočená úroveň nadpisů (např. H2 → H4)',
		'missing_alt'           => 'Obrázky bez alt textu',
		'schema_missing'        => 'Chybí strukturovaná data (schema)',
		'noindex_set'           => 'Stránka je nastavena jako noindex',
		'thin_content'          => 'Málo obsahu (thin content)',
		'focus_keyword_missing' => 'Není nastaveno klíčové slovo (focus keyword)',
	];

	/**
	 * @var array<string,string>
	 */
	private const SEVERITY_LABELS = [
		'critical'       => 'Kritické',
		'warning'        => 'Varování',
		'recommendation' => 'Doporučení',
	];

	/**
	 * Pořadí závažnosti pro řazení (nižší = závažnější).
	 *
	 * @var array<string,int>
	 */
	private const SEVERITY_ORDER = [
		'critical'       => 0,
		'warning'        => 1,
		'recommendation' => 2,
	];

	/**
	 * Typy nálezů ovlivňující viditelnost ve výsledcích vyhledávání (SERP) –
	 * používá se pro orientační odhad dopadu na návštěvnost.
	 *
	 * @var string[]
	 */
	private const SERP_ISSUE_TYPES = [
		'title_missing',
		'title_too_long',
		'description_missing',
		'description_too_long',
		'duplicate_title',
		'duplicate_description',
	];

	/**
	 * Vrátí české popisky typů nálezů (pro stránku Nastavení).
	 *
	 * @return array<string,string>
	 */
	public static function issue_labels(): array {
		return self::ISSUE_LABELS;
	}

	/**
	 * Odstraní znaky, které font DejaVu Sans v TCPDF nezná (typicky emoji
	 * z rozšířených rovin Unicode), aby se v PDF nezobrazovaly "tofu" znaky.
	 */
	private static function sanitize_text( string $text ): string {
		$clean = preg_replace( '/[\x{10000}-\x{10FFFF}]/u', '', $text );

		return trim( (string) $clean );
	}

	/**
	 * Spočítá rozměry loga v mm tak, aby se nezvětšovalo nad svou přirozenou
	 * velikost (předpoklad ~150 DPI) – zabraňuje rozmazání po roztažení na
	 * velikost ohraničujícího boxu, max. rozměry navíc nikdy nepřekročí.
	 *
	 * @return array{0:float,1:float} [šířka, výška] v mm.
	 */
	private static function logo_dimensions_mm( string $path, float $max_w_mm, float $max_h_mm, float $dpi = 150.0 ): array {
		$size = @getimagesize( $path );

		if ( ! $size || empty( $size[0] ) || empty( $size[1] ) ) {
			return [ $max_w_mm, $max_h_mm ];
		}

		$w_mm = $size[0] / $dpi * 25.4;
		$h_mm = $size[1] / $dpi * 25.4;

		$scale = min( 1, $max_w_mm / $w_mm, $max_h_mm / $h_mm );

		return [ round( $w_mm * $scale, 1 ), round( $h_mm * $scale, 1 ) ];
	}

	/**
	 * Sestaví kompletní data pro report daného scanu.
	 *
	 * @return array|null Null pokud scan neexistuje.
	 */
	public function build( ?int $scan_id = null ): ?array {
		$runner  = new SEOB_Audit_ScanRunner();
		$results = $runner->get_results( $scan_id );

		if ( null === $results['summary'] ) {
			return null;
		}

		$pdf_settings = SEOB_Settings::get( SEOB_Settings::PDF );
		$issue_texts  = $pdf_settings['issue_texts'];

		$rows = [];

		foreach ( $results['rows'] as $row ) {
			$issues = [];

			foreach ( $row['issues'] as $issue ) {
				$type  = $issue['type'];
				$texts = $issue_texts[ $type ] ?? [ 'impact' => '', 'benefit' => '' ];

				$issues[] = [
					'type'     => $type,
					'severity' => $issue['severity'],
					'label'    => self::ISSUE_LABELS[ $type ] ?? $type,
					'severity_label' => self::SEVERITY_LABELS[ $issue['severity'] ] ?? $issue['severity'],
					'detail'   => $issue['detail'] ?? null,
					'impact'   => $texts['impact'],
					'benefit'  => $texts['benefit'],
				];
			}

			$rows[] = [
				'object_id' => (int) $row['object_id'],
				'url'       => $row['url'],
				'title'     => $row['title'],
				'score'     => (int) $row['score'],
				'issues'    => $issues,
			];
		}

		$summary = $results['summary'];
		$counts  = $summary['counts'];

		$site_name = self::sanitize_text( get_bloginfo( 'name' ) );
		$score_avg = (int) $summary['score_avg'];
		$urls_total = (int) $summary['urls_total'];

		$rows_with_issues = array_values( array_filter( $rows, static function ( array $row ): bool {
			return ! empty( $row['issues'] );
		} ) );

		usort( $rows_with_issues, static function ( array $a, array $b ): int {
			if ( $a['score'] !== $b['score'] ) {
				return $a['score'] <=> $b['score'];
			}

			return count( $b['issues'] ) <=> count( $a['issues'] );
		} );

		$limit = max( 1, (int) ( $pdf_settings['report']['detailed_pages_limit'] ?? 12 ) );

		$detailed_rows  = array_slice( $rows_with_issues, 0, $limit );
		$remaining_rows = array_slice( $rows_with_issues, $limit );

		$company = $pdf_settings['company'];

		$logo_id = ! empty( $company['logo_id'] ) ? (int) $company['logo_id'] : (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$logo_path = get_attached_file( $logo_id );

			if ( $logo_path && file_exists( $logo_path ) ) {
				$company['logo_path'] = $logo_path;

				[ $company['logo_header_w'], $company['logo_header_h'] ] = self::logo_dimensions_mm( $logo_path, 50, 14 );
				[ $company['logo_issuer_w'], $company['logo_issuer_h'] ] = self::logo_dimensions_mm( $logo_path, 35, 18 );
			}
		}

		return [
			'site_name'    => $site_name,
			'generated_at' => current_time( 'd.m.Y' ),
			'scan'         => [
				'id'          => (int) $summary['id'],
				'started_at'  => $summary['started_at'],
				'finished_at' => $summary['finished_at'],
				'urls_total'  => $urls_total,
				'score_avg'   => $score_avg,
			],
			'counts'           => $counts,
			'rows'             => $rows,
			'pages_with_issues_count' => count( $rows_with_issues ),
			'pages_ok_count'          => max( 0, $urls_total - count( $rows_with_issues ) ),
			'detailed_rows'    => $detailed_rows,
			'remaining_count'  => count( $remaining_rows ),
			'issue_summary'    => $this->build_issue_summary( $rows_with_issues ),
			'intro_summary'    => $this->build_intro_summary( $site_name, $score_avg, $counts, $urls_total ),
			'offer_suggestion' => $this->suggest_offer( $score_avg ),
			'offer_templates'  => $this->resolve_offer_templates( $pdf_settings['offer_templates'], $site_name, $score_avg, $counts ),
			'company'          => $company,
			'serp_affected_pages' => $this->count_serp_affected_pages( $rows_with_issues ),
		];
	}

	/**
	 * Seskupí nálezy podle typu napříč všemi stránkami – pro stránky nad limitem
	 * detailního výpisu slouží jako souhrnný přehled s odkazem na konkrétní URL.
	 *
	 * @param array[] $rows_with_issues
	 * @return array[]
	 */
	private function build_issue_summary( array $rows_with_issues ): array {
		$groups = [];

		foreach ( $rows_with_issues as $row ) {
			foreach ( $row['issues'] as $issue ) {
				$type = $issue['type'];

				if ( ! isset( $groups[ $type ] ) ) {
					$groups[ $type ] = [
						'type'           => $type,
						'label'          => $issue['label'],
						'severity'       => $issue['severity'],
						'severity_label' => $issue['severity_label'],
						'impact'         => $issue['impact'],
						'benefit'        => $issue['benefit'],
						'count'          => 0,
						'pages'          => [],
					];
				}

				++$groups[ $type ]['count'];
				$groups[ $type ]['pages'][] = [
					'title' => $row['title'] ?: $row['url'],
					'url'   => $row['url'],
				];
			}
		}

		$groups = array_values( $groups );

		usort( $groups, static function ( array $a, array $b ): int {
			$order_a = self::SEVERITY_ORDER[ $a['severity'] ] ?? 99;
			$order_b = self::SEVERITY_ORDER[ $b['severity'] ] ?? 99;

			if ( $order_a !== $order_b ) {
				return $order_a <=> $order_b;
			}

			return $b['count'] <=> $a['count'];
		} );

		return $groups;
	}

	/**
	 * Počet stránek, které mají alespoň jeden nález ovlivňující viditelnost v SERP.
	 *
	 * @param array[] $rows_with_issues
	 */
	private function count_serp_affected_pages( array $rows_with_issues ): int {
		$count = 0;

		foreach ( $rows_with_issues as $row ) {
			foreach ( $row['issues'] as $issue ) {
				if ( in_array( $issue['type'], self::SERP_ISSUE_TYPES, true ) ) {
					++$count;
					continue 2;
				}
			}
		}

		return $count;
	}

	/**
	 * Spočítá orientační odhad dopadu zlepšení SEO na návštěvnost, konverze a tržby
	 * na základě dat zadaných klientem (volitelné). Výsledek je vždy jen odhad.
	 *
	 * @param array{urls_total:int,serp_affected_pages:int} $data Data reportu (po build()).
	 * @param array{monthly_visits?:mixed,conversion_rate?:mixed,avg_value?:mixed} $business Vstupní hodnoty klienta.
	 */
	public function compute_business_impact( array $data, array $business ): ?array {
		$monthly_visits  = (float) ( $business['monthly_visits'] ?? 0 );
		$conversion_rate = (float) ( $business['conversion_rate'] ?? 0 );
		$avg_value       = (float) ( $business['avg_value'] ?? 0 );

		if ( $monthly_visits <= 0 ) {
			return null;
		}

		$urls_total          = max( 1, (int) $data['scan']['urls_total'] );
		$serp_affected_pages = (int) $data['serp_affected_pages'];

		$ctr_uplift_percent = 0;

		if ( $serp_affected_pages > 0 ) {
			$ctr_uplift_percent = (int) round( ( $serp_affected_pages / $urls_total ) * 25 );
			$ctr_uplift_percent = max( 1, min( 20, $ctr_uplift_percent ) );
		}

		$additional_visits      = (int) round( $monthly_visits * $ctr_uplift_percent / 100 );
		$additional_conversions = $conversion_rate > 0 ? (int) round( $additional_visits * $conversion_rate / 100 ) : 0;
		$additional_revenue     = $avg_value > 0 ? (int) round( $additional_conversions * $avg_value ) : 0;

		return [
			'monthly_visits'         => (int) $monthly_visits,
			'conversion_rate'        => $conversion_rate,
			'avg_value'              => $avg_value,
			'serp_affected_pages'    => $serp_affected_pages,
			'ctr_uplift_percent'     => $ctr_uplift_percent,
			'additional_visits'      => $additional_visits,
			'additional_conversions' => $additional_conversions,
			'additional_revenue'     => $additional_revenue,
		];
	}

	/**
	 * Vygeneruje úvodní shrnutí auditu (editovatelné na stránce reportu).
	 */
	private function build_intro_summary( string $site_name, int $score_avg, array $counts, int $urls_total ): string {
		return sprintf(
			'Provedli jsme SEO audit %1$d publikovaných stránek webu %2$s. Celkové průměrné skóre je %3$d/100. ' .
			'Bylo identifikováno %4$d kritických nálezů, %5$d varování a %6$d doporučení ke zlepšení. ' .
			'V následujícím přehledu najdete detailní popis jednotlivých nálezů, jejich dopad a doporučená opatření.',
			$urls_total,
			$site_name,
			$score_avg,
			(int) $counts['critical'],
			(int) $counts['warning'],
			(int) $counts['recommendation']
		);
	}

	/**
	 * Navrhne klíč šablony nabídky podle průměrného skóre.
	 */
	private function suggest_offer( int $score_avg ): string {
		if ( $score_avg >= 85 ) {
			return 'maintenance';
		}

		if ( $score_avg >= 60 ) {
			return 'standard';
		}

		return 'comprehensive';
	}

	/**
	 * Nahradí placeholdery ve všech šablonách nabídky konkrétními hodnotami.
	 */
	private function resolve_offer_templates( array $templates, string $site_name, int $score_avg, array $counts ): array {
		$replacements = [
			'{site_name}'      => $site_name,
			'{score}'          => (string) $score_avg,
			'{critical_count}' => (string) (int) $counts['critical'],
			'{warning_count}'  => (string) (int) $counts['warning'],
		];

		$resolved = [];

		foreach ( $templates as $key => $template ) {
			$resolved[ $key ] = [
				'name' => $template['name'],
				'body' => strtr( $template['body'], $replacements ),
			];
		}

		return $resolved;
	}
}
