<?php
/**
 * Admin stranka – JSON-LD Validator (M3).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active     = SEOB_JsonLd_ScanRunner::get_active();
$is_running = ! empty( $active ) && ( $active['status'] ?? '' ) === 'running';
$history    = SEOB_JsonLd_ScanRunner::get_history();
$view_id    = isset( $_GET['scan_id'] ) ? (int) $_GET['scan_id'] : ( $history[0]['scan_id'] ?? 0 );
$view_scan  = null;
foreach ( $history as $h ) {
	if ( (int) $h['scan_id'] === $view_id ) {
		$view_scan = $h;
		break;
	}
}
$results   = $view_id ? SEOB_JsonLd_ScanRunner::get_results( $view_id ) : [];
$self_test = SEOB_JsonLd_Validator::self_test();

$scan_pct = 0;
if ( $is_running && ( $active['total'] ?? 0 ) > 0 ) {
	$scan_pct = (int) round( $active['scanned'] / $active['total'] * 100 );
}

// Skupiny: post_type_label => [ rows ]
$grouped = [];
foreach ( $results as $row ) {
	$label = $row['post_type_label'] ?? 'Ostatni';
	if ( ( $row['post_type'] ?? '' ) === 'homepage' ) {
		$label = 'Hlavni stranka';
	}
	$grouped[ $label ][] = $row;
}
// Razeni: Hlavni stranka prvni, pak abecedne
uksort( $grouped, static function ( string $a, string $b ): int {
	if ( $a === 'Hlavni stranka' ) { return -1; }
	if ( $b === 'Hlavni stranka' ) { return 1; }
	return strcmp( $a, $b );
} );
?>
<div class="wrap seob-wrap" id="seob-jld-page"
	data-running="<?php echo $is_running ? '1' : '0'; ?>"
	data-scan-id="<?php echo (int) ( $active['scan_id'] ?? 0 ); ?>">

	<h1>JSON-LD Validator</h1>
	<p class="description">Prohledá stránky webu, extrahuje <code>application/ld+json</code> bloky z renderovaného HTML a ověří jejich platnost. Scan běží na serveru – můžete odejít a vrátit se.</p>

	<?php if ( ! $self_test ) : ?>
		<div class="notice notice-error"><p><strong>Self-test validátoru selhal.</strong> Zkontrolujte PHP verzi (min. 8.0) a error log.</p></div>
	<?php endif; ?>

	<!-- ── Ovládání scanu ──────────────────────────────────────────────── -->
	<div class="seob-card" style="margin-top:16px;">

		<?php if ( $is_running ) : ?>
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<span style="font-weight:600;color:#2271b1;">Probíhá scan…</span>
				<button id="seob-jld-cancel" class="button">Zrušit scan</button>
			</div>
		<?php else : ?>
			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<button id="seob-jld-start" class="button button-primary">Spustit nový scan</button>
				<span id="seob-jld-start-msg" style="color:#666;font-size:13px;"></span>
			</div>
		<?php endif; ?>

		<!-- Progress bar -->
		<div id="seob-jld-progress" style="margin-top:14px;<?php echo $is_running ? '' : 'display:none;'; ?>">
			<div style="background:#e0e0e0;border-radius:4px;height:20px;overflow:hidden;max-width:520px;position:relative;">
				<div id="seob-jld-bar" style="background:#2271b1;height:100%;width:<?php echo $scan_pct; ?>%;transition:width 0.3s ease;"></div>
				<span id="seob-jld-bar-pct" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#fff;text-shadow:0 0 3px rgba(0,0,0,.5);">
					<?php echo $scan_pct; ?>%
				</span>
			</div>
			<div id="seob-jld-progress-label" style="margin-top:5px;font-size:12px;color:#555;">
				<?php if ( $is_running ) : ?>
					<?php echo (int) $active['scanned']; ?> / <?php echo (int) $active['total']; ?> stránek
				<?php endif; ?>
			</div>
		</div>

	</div>

	<!-- ── Archiv skenů ───────────────────────────────────────────────── -->
	<div class="seob-card" style="margin-top:16px;">
		<h2 style="margin-top:0;">Archiv skenů</h2>

		<?php if ( empty( $history ) ) : ?>
			<p style="color:#666;">Zatím nebyl dokončen žádný scan. Klikněte na „Spustit nový scan" výše.</p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:0;">
				<thead>
					<tr>
						<th>Datum a čas</th>
						<th>Stránek</th>
						<th>Schémat</th>
						<th style="color:#d63638;">Chyby</th>
						<th style="color:#dba617;">Duplikáty</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $h ) :
						$is_active_view = (int) $h['scan_id'] === $view_id;
					?>
						<tr style="<?php echo $is_active_view ? 'background:#f0f6fc;font-weight:600;' : ''; ?>">
							<td><?php echo esc_html( date_i18n( 'j. n. Y H:i', (int) $h['started_at'] ) ); ?></td>
							<td><?php echo (int) $h['scanned']; ?></td>
							<td><?php echo (int) $h['total_schemas']; ?></td>
							<td style="color:<?php echo (int) $h['invalid'] > 0 ? '#d63638' : '#00a32a'; ?>;">
								<?php echo (int) $h['invalid'] > 0 ? '&#10060; ' . (int) $h['invalid'] : '&#10003; 0'; ?>
							</td>
							<td style="color:<?php echo (int) $h['duplicates'] > 0 ? '#dba617' : '#00a32a'; ?>;">
								<?php echo (int) $h['duplicates'] > 0 ? '&#9888; ' . (int) $h['duplicates'] : '&#10003; 0'; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=seob-json-ld&scan_id=' . (int) $h['scan_id'] ) ); ?>"
								   class="button button-small">
									<?php echo $is_active_view ? 'Zobrazeno' : 'Zobrazit'; ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- ── Výsledky vybraného scanu ────────────────────────────────────── -->
	<?php if ( $view_scan ) : ?>
		<div class="seob-card" style="margin-top:16px;">
			<h2 style="margin-top:0;">
				Výsledky scanu – <?php echo esc_html( date_i18n( 'j. n. Y H:i', (int) $view_scan['started_at'] ) ); ?>
			</h2>

			<?php if ( empty( $results ) ) : ?>
				<p style="color:#666;">Detailní výsledky pro tento scan nejsou k dispozici (mohly expirovat po 24 hodinách).</p>
			<?php elseif ( empty( $grouped ) ) : ?>
				<p style="color:#666;">Scan neobsahuje žádné záznamy.</p>
			<?php else : ?>

				<?php foreach ( $grouped as $group_label => $group_rows ) :

					// Pocty pro skupinu
					$g_total   = count( $group_rows );
					$g_errors  = 0;
					$g_dupes   = 0;
					$g_ok      = 0;
					foreach ( $group_rows as $gr ) {
						$ec = count( array_filter( $gr['issues'] ?? [], static fn ( $i ) => $i['severity'] === 'error' ) );
						if ( ( $gr['status'] ?? '' ) === 'error' || $ec > 0 || ! empty( $gr['duplicates'] ) ) {
							if ( $ec > 0 || ( $gr['status'] ?? '' ) === 'error' ) { $g_errors++; }
							elseif ( ! empty( $gr['duplicates'] ) ) { $g_dupes++; }
						} else {
							$g_ok++;
						}
					}
				?>

					<!-- Hlavicka skupiny -->
					<div style="margin-top:20px;margin-bottom:6px;display:flex;align-items:center;gap:10px;border-bottom:2px solid #c3c4c7;padding-bottom:6px;">
						<h3 style="margin:0;font-size:14px;color:#1d2327;">
							<?php echo esc_html( $group_label ); ?>
							<span style="font-weight:400;color:#646970;font-size:12px;margin-left:6px;">(<?php echo $g_total; ?> stránek)</span>
						</h3>
						<?php if ( $g_errors > 0 ) : ?>
							<span style="background:#d63638;color:#fff;padding:1px 8px;border-radius:10px;font-size:11px;">&#10060; <?php echo $g_errors; ?> chyb</span>
						<?php endif; ?>
						<?php if ( $g_dupes > 0 ) : ?>
							<span style="background:#dba617;color:#fff;padding:1px 8px;border-radius:10px;font-size:11px;">&#9888; <?php echo $g_dupes; ?> duplicit</span>
						<?php endif; ?>
						<?php if ( $g_ok === $g_total ) : ?>
							<span style="color:#00a32a;font-size:12px;">&#10003; Vše v pořádku</span>
						<?php endif; ?>
					</div>

					<table class="widefat striped" style="margin-bottom:8px;">
						<thead>
							<tr>
								<th style="width:35%">Stránka</th>
								<th>Schémata</th>
								<th>Stav</th>
								<th>Duplikáty</th>
								<th style="width:110px;text-align:right;">Akce</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $group_rows as $row ) :
								$err_count  = count( array_filter( $row['issues'] ?? [], static fn ( $i ) => $i['severity'] === 'error' ) );
								$warn_count = count( array_filter( $row['issues'] ?? [], static fn ( $i ) => $i['severity'] === 'warning' ) );
								$has_dupes  = ! empty( $row['duplicates'] );
								$is_err_row = ( $row['status'] ?? '' ) === 'error';
								$has_issues = ! $is_err_row && ! empty( $row['issues'] );
								$has_dupes_detail = ! $is_err_row && $has_dupes;
								$edit_url   = $row['edit_url'] ?? '';
								$post_id    = (int) ( $row['post_id'] ?? 0 );

								// Kratsi zobrazeni URL – jen cesta
								$url_display = $row['url'];
								$parsed      = wp_parse_url( $row['url'] );
								if ( ! empty( $parsed['path'] ) && strlen( $parsed['path'] ) > 1 ) {
									$url_display = rtrim( $parsed['path'], '/' );
								} elseif ( ! empty( $parsed['host'] ) ) {
									$url_display = $parsed['host'];
								}
							?>
								<tr>
									<td>
										<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"
										   title="<?php echo esc_attr( $row['url'] ); ?>" style="word-break:break-all;">
											<?php echo esc_html( $url_display ); ?>
										</a>
									</td>
									<td>
										<?php if ( $is_err_row ) : ?>
											<em style="color:#999;">—</em>
										<?php else : ?>
											<?php echo (int) $row['schema_count']; ?>
											<?php if ( ! empty( $row['schema_types'] ) ) : ?>
												<br><small style="color:#666;"><?php echo esc_html( implode( ', ', $row['schema_types'] ) ); ?></small>
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $is_err_row ) : ?>
											<span style="color:#d63638;" title="<?php echo esc_attr( $row['error'] ?? '' ); ?>">&#10060; Chyba načtení</span>
										<?php elseif ( $err_count > 0 ) : ?>
											<span style="color:#d63638;">&#10060; <?php echo $err_count; ?> <?php echo $err_count === 1 ? 'chyba' : 'chyby'; ?></span>
										<?php elseif ( $warn_count > 0 ) : ?>
											<span style="color:#dba617;">&#9888; <?php echo $warn_count; ?> varování</span>
										<?php else : ?>
											<span style="color:#00a32a;">&#10003; OK</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $has_dupes ) :
											foreach ( $row['duplicates'] as $d ) : ?>
												<span style="color:<?php echo $d['exact'] ? '#d63638' : '#dba617'; ?>;font-size:12px;">
													<?php echo $d['exact'] ? '&#10060;' : '&#9888;'; ?>
													<?php echo esc_html( $d['type'] ); ?> ×<?php echo (int) $d['count']; ?>
												</span><br>
											<?php endforeach;
										else : ?>
											<span style="color:#00a32a;">&#10003;</span>
										<?php endif; ?>
									</td>
									<td style="text-align:right;white-space:nowrap;">
										<?php if ( $edit_url ) : ?>
											<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener"
											   class="button button-small"
											   title="Otevrit editor WordPress pro tuto stranku">
												Upravit
											</a>
										<?php endif; ?>
									</td>
								</tr>

								<?php if ( $has_issues || $has_dupes_detail ) : ?>
									<tr style="background:#fefefe;">
										<td colspan="5" style="padding:8px 8px 8px 24px;border-top:1px dashed #ddd;">

											<?php if ( $has_issues ) : ?>
												<ul style="margin:0 0 6px 0;padding:0;list-style:none;">
													<?php foreach ( $row['issues'] as $issue ) :
														$is_issue_err = $issue['severity'] === 'error';
														$hint         = $issue['fix_hint'] ?? '';
													?>
														<li style="margin:0 0 8px 0;padding:8px 10px;border-radius:4px;background:<?php echo $is_issue_err ? '#fff0f0' : '#fffbee'; ?>;border-left:3px solid <?php echo $is_issue_err ? '#d63638' : '#dba617'; ?>;">
															<div style="display:flex;align-items:flex-start;gap:6px;flex-wrap:wrap;">
																<strong style="color:<?php echo $is_issue_err ? '#d63638' : '#dba617'; ?>;min-width:60px;">
																	<?php echo $is_issue_err ? '&#10060; Chyba' : '&#9888; Varování'; ?>
																</strong>
																<span>
																	<strong><?php echo esc_html( $issue['type'] ); ?></strong>:
																	<?php echo esc_html( $issue['message'] ); ?>
																</span>
															</div>
															<?php if ( $hint ) : ?>
																<div style="margin-top:5px;padding-left:66px;color:#50575e;font-size:12px;">
																	<strong>Jak opravit:</strong>
																	<?php echo wp_kses( $hint, [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ); ?>
																	<?php if ( $edit_url ) : ?>
																		&nbsp;&rarr;&nbsp;<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener"
																		style="color:#2271b1;font-weight:600;">Otevřít editor</a>
																	<?php endif; ?>
																</div>
															<?php endif; ?>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>

											<?php if ( $has_dupes_detail ) : ?>
												<ul style="margin:0;padding:0;list-style:none;">
													<?php foreach ( $row['duplicates'] as $d ) :
														$is_exact = $d['exact'];
													?>
														<li style="margin:0 0 8px 0;padding:8px 10px;border-radius:4px;background:<?php echo $is_exact ? '#fff0f0' : '#fffbee'; ?>;border-left:3px solid <?php echo $is_exact ? '#d63638' : '#dba617'; ?>;">
															<div style="display:flex;align-items:flex-start;gap:6px;">
																<strong style="color:<?php echo $is_exact ? '#d63638' : '#dba617'; ?>;min-width:60px;">
																	<?php echo $is_exact ? '&#10060; Duplicita' : '&#9888; Duplicita'; ?>
																</strong>
																<span>
																	Typ <strong><?php echo esc_html( $d['type'] ); ?></strong> se
																	vyskytuje <?php echo (int) $d['count']; ?>&times; na téhle stránce
																	<?php if ( $is_exact ) : ?>
																		(identický obsah – jedno schema odstraňte)
																	<?php else : ?>
																		(různý obsah – může způsobit konflikt)
																	<?php endif; ?>
																</span>
															</div>
															<div style="margin-top:5px;padding-left:66px;color:#50575e;font-size:12px;">
																<strong>Jak opravit:</strong>
																Prohlédněte zdrojový kód stránky (Ctrl+U) a zjistěte, které pluginy nebo šablona generují schema typu <strong><?php echo esc_html( $d['type'] ); ?></strong>.
																Jeden z nich deaktivujte – obvykle buď Rank Math, nebo šablona/jiný plugin.
																<?php if ( $edit_url ) : ?>
																	&nbsp;&rarr;&nbsp;<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener"
																	style="color:#2271b1;font-weight:600;">Otevřít editor</a>
																<?php endif; ?>
															</div>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>

										</td>
									</tr>
								<?php endif; ?>

							<?php endforeach; ?>
						</tbody>
					</table>

				<?php endforeach; ?>

			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- ── Validátor jedné URL ─────────────────────────────────────────── -->
	<div class="seob-card" style="margin-top:16px;">
		<h2 style="margin-top:0;">Validovat konkrétní URL</h2>
		<p class="description" style="margin-bottom:10px;">Otestujte jednu URL bez spuštění celého scanu – vhodné pro ověření po opravě.</p>
		<div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
			<input type="url" id="seob-jld-single-url" class="regular-text"
			       placeholder="https://example.com/stranka/" style="flex:1;min-width:260px;">
			<button id="seob-jld-scan-single" class="button">Validovat</button>
		</div>
		<div id="seob-jld-single-result" style="margin-top:12px;display:none;"></div>
	</div>

	<!-- ── Dokumentace ─────────────────────────────────────────────────── -->
	<details style="margin-top:24px;">
		<summary style="cursor:pointer;font-weight:600;font-size:14px;padding:8px 0;">Jak JSON-LD Validator funguje a proč je důležitý</summary>
		<div style="padding:16px 0;max-width:860px;">

			<h3>Proč se JSON-LD schémata validují?</h3>
			<p>Google používá strukturovaná data (JSON-LD) k zobrazení rich snippetů – hvězdiček, FAQ boxů, videí apod. ve výsledcích vyhledávání. Nevalidní schéma (chybějící povinná vlastnost) rich snippet nezíská. Duplicitní schéma stejného typu Google může ignorovat celé.</p>

			<h3>Jak opravit nalezené problémy?</h3>
			<p>Každá chyba ve výsledcích scanu obsahuje pole <strong>Jak opravit</strong> – konkrétní krok, kde v administraci problém vyřešit. Tlačítko <strong>Upravit</strong> otevře WordPress editor přímo pro danou stránku.</p>
			<p>Nejčastější opravy:</p>
			<table class="widefat striped">
				<thead><tr><th>Chyba</th><th>Kde opravit</th></tr></thead>
				<tbody>
					<tr><td>Article: chybí headline</td><td>Editor → Rank Math → Schema → Článek → Headline</td></tr>
					<tr><td>BreadcrumbList: prázdné pole</td><td>Rank Math → Drobečková navigace (zkontrolujte zapnutí)</td></tr>
					<tr><td>FAQPage: prázdné mainEntity</td><td>Přidejte FAQ blok s otázkami do obsahu stránky</td></tr>
					<tr><td>Duplicitní schema</td><td>Prohlédněte zdrojový kód (Ctrl+U), zjistěte zdroj, jeden deaktivujte</td></tr>
					<tr><td>Chyba parsování JSON</td><td>Ověřte vlastní JSON-LD kód na jsonlint.com</td></tr>
				</tbody>
			</table>

			<h3>Jak scan funguje na pozadí?</h3>
			<p>Scan probíhá na serveru (WP-Cron) – nezávisle na prohlížeči. Zpracovává 1 stránku každé 3 sekundy, aby nezatížil server. 50 stránek ≈ 2,5 minuty. Výsledky jsou seskupeny dle typu obsahu (Příspěvky, Stránky, vlastní typy).</p>
		</div>
	</details>

</div>
