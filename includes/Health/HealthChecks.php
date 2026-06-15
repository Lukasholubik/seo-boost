<?php
/**
 * Health checky jednotlivých modulů – stav, poslední běh, krok k nápravě.
 * Integrace do WP Site Health (filtr site_status_tests).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Health_Checks {

	/**
	 * Zaregistruje testy do WP Site Health (Nástroje → Stav webu).
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', [ __CLASS__, 'add_site_health_tests' ] );
	}

	public static function add_site_health_tests( array $tests ): array {
		foreach ( SEOB_Module_Manager::get_modules() as $module_id => $module ) {
			if ( empty( $module['active'] ) ) {
				continue;
			}

			$tests['direct'][ 'seob_module_' . $module_id ] = [
				'label' => sprintf( 'SEO Booster Pro – %s', $module['label'] ),
				'test'  => static function () use ( $module_id, $module ) {
					return self::site_health_result( $module_id, $module );
				},
			];
		}

		return $tests;
	}

	private static function site_health_result( string $module_id, array $module ): array {
		$checks = self::get_checks( $module_id );

		$worst = 'good';
		$descriptions = [];

		foreach ( $checks as $check ) {
			$descriptions[] = '<p>' . esc_html( $check['message'] ) . '</p>';

			if ( 'critical' === $check['status'] ) {
				$worst = 'critical';
			} elseif ( 'warning' === $check['status'] && 'critical' !== $worst ) {
				$worst = 'warning';
			}
		}

		$status_map = [
			'good'     => 'good',
			'warning'  => 'recommended',
			'critical' => 'critical',
		];

		$badge_color = 'critical' === $worst ? 'red' : ( 'warning' === $worst ? 'orange' : 'green' );

		return [
			'label'       => sprintf( 'SEO Booster Pro – %s je v pořádku', $module['label'] ),
			'status'      => $status_map[ $worst ],
			'badge'       => [
				'label' => 'SEO Booster Pro',
				'color' => $badge_color,
			],
			'description' => implode( '', $descriptions ),
			'actions'     => '<p><a href="' . esc_url( admin_url( 'admin.php?page=seob-status' ) ) . '">' . esc_html__( 'Zobrazit Stav systému', 'seo-boost' ) . '</a></p>',
			'test'        => 'seob_module_' . $module_id,
		];
	}

	/**
	 * Vrátí health checky pro daný modul.
	 *
	 * @return array<int, array{id:string,label:string,status:string,message:string,action_label:?string,action_url:?string}>
	 */
	public static function get_checks( string $module_id ): array {
		switch ( $module_id ) {
			case 'audit':
				return self::audit_checks();
			case 'redirects':
				return self::redirects_checks();
			case 'pdf':
				return self::pdf_checks();
			case 'smart-indexing':
				return self::smart_indexing_checks();
			case 'gsc-insights':
				return self::gsc_checks();
			case 'ai-queue':
				return self::ai_queue_checks();
			default:
				return [];
		}
	}

	/**
	 * Obecné informační kontroly (nezávislé na modulech).
	 */
	public static function get_general_checks(): array {
		$rank_math = SEOB_Module_Manager::is_rank_math_active();

		return [
			[
				'id'           => 'rank_math_detected',
				'label'        => 'Rank Math',
				'status'       => 'good',
				'message'      => $rank_math
					? 'Rank Math je aktivní – schéma a meta data se kombinují s jeho nastavením.'
					: 'Rank Math není aktivní – výchozí hodnoty schématu od Rank Math se nepoužívají.',
				'action_label' => null,
				'action_url'   => null,
			],
		];
	}

	private static function audit_checks(): array {
		global $wpdb;

		$checks          = [];
		$scan_runs_table = SEOB_Database::scan_runs_table();

		$last_done = $wpdb->get_row(
			"SELECT finished_at FROM {$scan_runs_table} WHERE status = 'done' ORDER BY finished_at DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( null === $last_done ) {
			$checks[] = [
				'id'           => 'audit_last_scan',
				'label'        => 'Poslední scan',
				'status'       => 'critical',
				'message'      => 'Zatím nebyl dokončen žádný scan.',
				'action_label' => 'Spustit scan',
				'action_url'   => admin_url( 'admin.php?page=seo-boost' ),
			];
		} else {
			$age_days = ( time() - strtotime( $last_done->finished_at ) ) / DAY_IN_SECONDS;

			if ( $age_days < 7 ) {
				$status  = 'good';
				$message = sprintf( 'Poslední scan dokončen %s.', mysql2date( 'j. n. Y H:i', $last_done->finished_at ) );
			} elseif ( $age_days < 30 ) {
				$status  = 'warning';
				$message = sprintf( 'Poslední scan je starší než týden (%s).', mysql2date( 'j. n. Y H:i', $last_done->finished_at ) );
			} else {
				$status  = 'critical';
				$message = sprintf( 'Poslední scan je starší než měsíc (%s).', mysql2date( 'j. n. Y H:i', $last_done->finished_at ) );
			}

			$checks[] = [
				'id'           => 'audit_last_scan',
				'label'        => 'Poslední scan',
				'status'       => $status,
				'message'      => $message,
				'action_label' => 'good' === $status ? null : 'Spustit nový scan',
				'action_url'   => 'good' === $status ? null : admin_url( 'admin.php?page=seo-boost' ),
			];
		}

		$stuck = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, started_at FROM {$scan_runs_table} WHERE status = 'running' AND started_at < %s ORDER BY started_at ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS )
			)
		);

		if ( null !== $stuck ) {
			$checks[] = [
				'id'           => 'audit_stuck_scan',
				'label'        => 'Zaseklý scan',
				'status'       => 'critical',
				'message'      => sprintf( 'Scan #%d běží déle než 2 hodiny (od %s) – pravděpodobně se zasekl.', (int) $stuck->id, mysql2date( 'j. n. Y H:i', $stuck->started_at ) ),
				'action_label' => 'Spustit nový scan',
				'action_url'   => admin_url( 'admin.php?page=seo-boost' ),
			];
		}

		return $checks;
	}

	private static function redirects_checks(): array {
		$checks = [];

		$next_cron = wp_next_scheduled( SEOB_Redirect_Manager::CRON_HOOK );

		$checks[] = [
			'id'           => 'redirects_cron',
			'label'        => 'Úklid 404 logů (cron)',
			'status'       => $next_cron ? 'good' : 'critical',
			'message'      => $next_cron
				? sprintf( 'Denní úklid starých 404 záznamů je naplánován na %s.', wp_date( 'j. n. Y H:i', $next_cron ) )
				: 'Denní úklid starých 404 záznamů není naplánován.',
			'action_label' => $next_cron ? null : 'Otevřít Nastavení',
			'action_url'   => $next_cron ? null : admin_url( 'admin.php?page=seob-settings' ),
		];

		$unresolved = SEOB_Metrics::get_latest( 'redirects', 'unresolved_404_count' );

		if ( null === $unresolved ) {
			$checks[] = [
				'id'           => 'redirects_unresolved_404',
				'label'        => 'Nevyřešené 404',
				'status'       => 'good',
				'message'      => 'Zatím nebyl spočítán žádný úklid 404 logů.',
				'action_label' => null,
				'action_url'   => null,
			];
		} else {
			$checks[] = [
				'id'           => 'redirects_unresolved_404',
				'label'        => 'Nevyřešené 404',
				'status'       => $unresolved > 0 ? 'warning' : 'good',
				'message'      => $unresolved > 0
					? sprintf( 'Eviduje se %d nevyřešených 404 stránek.', (int) $unresolved )
					: 'Žádné nevyřešené 404 stránky.',
				'action_label' => $unresolved > 0 ? 'Zobrazit přesměrování' : null,
				'action_url'   => $unresolved > 0 ? admin_url( 'admin.php?page=seob-redirects' ) : null,
			];
		}

		return $checks;
	}

	private static function smart_indexing_checks(): array {
		global $wpdb;

		$checks   = [];
		$settings = SEOB_Settings::get( SEOB_Settings::SMART_INDEXING );

		$mapped = '' !== $settings['company_post_type'] || '' !== $settings['category_taxonomy'];

		$checks[] = [
			'id'           => 'smart_indexing_mapping',
			'label'        => 'Mapování katalogu',
			'status'       => $mapped ? 'good' : 'warning',
			'message'      => $mapped
				? 'Mapování typu obsahu / taxonomií pro chytrou indexaci je nastaveno.'
				: 'Není nastaveno mapování (detail firmy / obor / lokalita) – analýza nemá co vyhodnocovat.',
			'action_label' => $mapped ? null : 'Otevřít Chytrou indexaci',
			'action_url'   => $mapped ? null : admin_url( 'admin.php?page=seob-smart-indexing' ),
		];

		$checks[] = [
			'id'           => 'smart_indexing_mode',
			'label'        => 'Režim',
			'status'       => 'dry_run' === $settings['mode'] ? 'warning' : 'good',
			'message'      => 'dry_run' === $settings['mode']
				? 'Modul běží v režimu Dry-run – návrhy se zobrazují, ale canonical/noindex se na frontend nepromítají.'
				: sprintf( 'Modul běží v aktivním režimu (%s) – schválené stránky ovlivňují canonical/robots.', $settings['mode'] ),
			'action_label' => null,
			'action_url'   => null,
		];

		$table     = SEOB_Database::facet_urls_table();
		$last_scan = $wpdb->get_var( "SELECT MAX(scanned_at) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$checks[] = [
			'id'           => 'smart_indexing_last_scan',
			'label'        => 'Poslední analýza',
			'status'       => $last_scan ? 'good' : 'warning',
			'message'      => $last_scan
				? sprintf( 'Poslední analýza proběhla %s.', mysql2date( 'j. n. Y H:i', $last_scan ) )
				: 'Zatím nebyla spuštěna žádná analýza katalogových kombinací.',
			'action_label' => 'Otevřít Chytrou indexaci',
			'action_url'   => admin_url( 'admin.php?page=seob-smart-indexing' ),
		];

		return $checks;
	}

	private static function gsc_checks(): array {
		if ( ! SEOB_Module_Manager::is_rank_math_active() ) {
			return [
				[
					'id'           => 'gsc_rank_math_missing',
					'label'        => 'Rank Math',
					'status'       => 'critical',
					'message'      => 'Rank Math nebyl nalezen – Search Console statistiky vyžadují aktivní plugin Rank Math (Free i Pro).',
					'action_label' => null,
					'action_url'   => null,
				],
			];
		}

		$summary = SEOB_Gsc_Insights::get_summary();

		if ( null === $summary ) {
			return [
				[
					'id'           => 'gsc_not_connected',
					'label'        => 'Připojení Search Console',
					'status'       => 'warning',
					'message'      => 'Rank Math zatím nemá data ze Search Console (modul Analytics nepřipojen nebo bez dat za posledních 28 dní) – sloupce Search Console v Audit Dashboardu jsou skryté.',
					'action_label' => 'Připojit Search Console v Rank Math',
					'action_url'   => admin_url( 'admin.php?page=rank-math-options-general' ),
				],
			];
		}

		return [
			[
				'id'           => 'gsc_connected',
				'label'        => 'Search Console (Rank Math)',
				'status'       => 'good',
				'message'      => sprintf(
					'Za posledních 28 dní: %d zobrazení, %d kliků, CTR %s %%, průměrná pozice %s.',
					$summary['impressions'],
					$summary['clicks'],
					number_format_i18n( $summary['ctr'], 2 ),
					number_format_i18n( $summary['avg_position'], 1 )
				),
				'action_label' => null,
				'action_url'   => null,
			],
		];
	}

	private static function ai_queue_checks(): array {
		$settings = SEOB_Settings::get( SEOB_Settings::AI );

		if ( empty( $settings['enabled'] ) || '' === $settings['api_key_enc'] ) {
			return [
				[
					'id'           => 'ai_queue_configured',
					'label'        => 'AI asistent',
					'status'       => 'critical',
					'message'      => 'AI asistent je zapnutý, ale chybí API klíč.',
					'action_label' => 'Otevřít Nastavení',
					'action_url'   => admin_url( 'admin.php?page=seob-settings' ),
				],
			];
		}

		$pending = SEOB_AiQueue_Repository::count_by_status( SEOB_AiQueue_Repository::STATUS_PENDING );

		if ( $pending > 0 ) {
			return [
				[
					'id'           => 'ai_queue_pending',
					'label'        => 'AI fronta',
					'status'       => 'warning',
					'message'      => sprintf( '%d návrhů čeká na schválení.', $pending ),
					'action_label' => 'Zobrazit AI frontu',
					'action_url'   => admin_url( 'admin.php?page=seob-ai-queue' ),
				],
			];
		}

		return [
			[
				'id'           => 'ai_queue_pending',
				'label'        => 'AI fronta',
				'status'       => 'good',
				'message'      => 'Žádné návrhy nečekají na schválení.',
				'action_label' => null,
				'action_url'   => null,
			],
		];
	}

	private static function pdf_checks(): array {
		$loader_exists = file_exists( SEOB_PLUGIN_DIR . 'vendor/tcpdf/seob-tcpdf-loader.php' );

		return [
			[
				'id'           => 'pdf_tcpdf_available',
				'label'        => 'Knihovna TCPDF',
				'status'       => $loader_exists ? 'good' : 'critical',
				'message'      => $loader_exists
					? 'Knihovna TCPDF je dostupná, export PDF reportů funguje.'
					: 'Chybí vendor/tcpdf – export PDF reportů nebude fungovat. Zkontrolujte instalaci pluginu.',
				'action_label' => null,
				'action_url'   => null,
			],
		];
	}
}
