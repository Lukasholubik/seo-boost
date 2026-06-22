<?php
/**
 * Admin stránka – Content Decay Monitor (M10)
 *
 * @var string $nonce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap" id="seob-content-decay-page">
	<h1>Content Decay Monitor</h1>

	<p class="seob-intro">
		Identifikuje stránky, jejichž obsah stárne nebo ztrácí organickou návštěvnost.
		Scan probíhá lokálně – čte data z databáze (žádné HTTP requesty).
		<?php if ( ! SEOB_Gsc_Insights::is_table_available() ) : ?>
			<span class="seob-notice-inline seob-notice-warning">
				GSC data nejsou k dispozici – propojte Rank Math Analytics s Google Search Console pro detekci poklesu klíků.
			</span>
		<?php endif; ?>
	</p>

	<div class="seob-action-bar">
		<button id="seob-decay-scan-btn" class="button button-primary">Spustit scan</button>
		<span id="seob-decay-scan-status"></span>
	</div>

	<!-- Summary karty -->
	<div class="seob-decay-summary" id="seob-decay-summary" style="display:none;">
		<div class="seob-decay-card seob-decay-decaying">
			<span class="seob-decay-count" id="cnt-decaying">0</span>
			<span class="seob-decay-label">Chřadnoucí</span>
			<span class="seob-decay-sub">skóre ≥ 61</span>
		</div>
		<div class="seob-decay-card seob-decay-stale">
			<span class="seob-decay-count" id="cnt-stale">0</span>
			<span class="seob-decay-label">Stagnující</span>
			<span class="seob-decay-sub">skóre 41–60</span>
		</div>
		<div class="seob-decay-card seob-decay-aging">
			<span class="seob-decay-count" id="cnt-aging">0</span>
			<span class="seob-decay-label">Stárnoucí</span>
			<span class="seob-decay-sub">skóre 21–40</span>
		</div>
		<div class="seob-decay-card seob-decay-fresh">
			<span class="seob-decay-count" id="cnt-fresh">0</span>
			<span class="seob-decay-label">Čerstvé</span>
			<span class="seob-decay-sub">skóre 0–20</span>
		</div>
	</div>

	<!-- Filtr -->
	<div class="seob-decay-filters" id="seob-decay-filters" style="display:none;">
		<button class="button seob-filter-btn active" data-filter="all">Vše</button>
		<button class="button seob-filter-btn" data-filter="decaying">Chřadnoucí</button>
		<button class="button seob-filter-btn" data-filter="stale">Stagnující</button>
		<button class="button seob-filter-btn" data-filter="aging">Stárnoucí</button>
		<button class="button seob-filter-btn" data-filter="fresh">Čerstvé</button>
		<span id="seob-decay-scanned-at" class="seob-scan-date"></span>
	</div>

	<!-- Výsledky -->
	<div id="seob-decay-results-wrap" style="display:none;">
		<table class="widefat striped seob-decay-table" id="seob-decay-table">
			<thead>
				<tr>
					<th>Stránka</th>
					<th>Decay skóre</th>
					<th>Věk obsahu</th>
					<th>Kliky (30d)</th>
					<th>Trend kliků</th>
					<th>Avg. pozice</th>
					<th>Signály</th>
					<th></th>
				</tr>
			</thead>
			<tbody id="seob-decay-tbody">
				<tr><td colspan="8">Načítám…</td></tr>
			</tbody>
		</table>
	</div>

	<!-- Dokumentace -->
	<details class="seob-docs" style="margin-top:2em;">
		<summary><strong>Jak funguje Content Decay a jak opravit problémy</strong></summary>

		<div class="seob-docs-content">

			<h3>Co je Content Decay?</h3>
			<p>Content Decay (úpadek obsahu) nastává, když stránka postupně ztrácí organickou návštěvnost a pozice v Googlu – přesto, že se nic záměrně nezměnilo. Příčiny: obsah je zastaralý, konkurence vydala lepší stránky, stránka neodpovídá aktuálním záměrům vyhledávačů.</p>

			<h3>Decay skóre 0–100</h3>
			<p>Vyšší skóre = větší riziko úpadku. Skóre se skládá z těchto signálů:</p>
			<table class="widefat">
				<thead><tr><th>Signál</th><th>Body</th><th>Zdroj dat</th></tr></thead>
				<tbody>
					<tr><td>Obsah nezměněn &gt; 24 měsíců</td><td>+35</td><td>wp_posts.post_modified</td></tr>
					<tr><td>Obsah nezměněn 12–24 měsíců</td><td>+20</td><td>wp_posts.post_modified</td></tr>
					<tr><td>Obsah nezměněn 6–12 měsíců</td><td>+8</td><td>wp_posts.post_modified</td></tr>
					<tr><td>Pokles kliků z GSC &gt; 50%</td><td>+40</td><td>Rank Math Analytics GSC</td></tr>
					<tr><td>Pokles kliků 25–50%</td><td>+25</td><td>Rank Math Analytics GSC</td></tr>
					<tr><td>Pokles kliků 10–25%</td><td>+10</td><td>Rank Math Analytics GSC</td></tr>
					<tr><td>Pozice klesla &gt; 10 míst</td><td>+20</td><td>Rank Math Analytics GSC</td></tr>
					<tr><td>Pozice klesla 5–10 míst</td><td>+8</td><td>Rank Math Analytics GSC</td></tr>
					<tr><td>Stará letní zmínka v textu</td><td>+7</td><td>Obsah příspěvku</td></tr>
					<tr><td>Tenký obsah (&lt; 150 slov)</td><td>+15</td><td>Obsah příspěvku</td></tr>
					<tr><td>Nízký obsah (150–300 slov)</td><td>+4</td><td>Obsah příspěvku</td></tr>
				</tbody>
			</table>

			<h3>Jak opravit chřadnoucí stránky</h3>

			<h4>1. Stárnoucí obsah (věk &gt; 12 měsíců)</h4>
			<ul>
				<li>Aktualizujte fakta, statistiky a příklady v textu.</li>
				<li>Přidejte nové sekce s aktuálními informacemi nebo doplňujícím obsahem.</li>
				<li>Po uložení úprav WP automaticky aktualizuje <code>post_modified</code>.</li>
			</ul>

			<h4>2. Pokles organické návštěvnosti</h4>
			<ul>
				<li>Zkontrolujte v GSC → Search Results → filtry URL, jaká klíčová slova klesla.</li>
				<li>Porovnejte svou stránku s konkurencí v TOP 3 pro daný dotaz.</li>
				<li>Rozšiřte stránku o chybějící témata, FAQ sekci, nebo přepište H1/meta.</li>
			</ul>

			<h4>3. Stará letní zmínka</h4>
			<ul>
				<li>Vyhledejte v textu letopočty a ověřte, zda informace stále platí.</li>
				<li>Aktualizujte roky nebo smažte zastaralé data-vázané informace.</li>
			</ul>

			<h4>4. Tenký obsah</h4>
			<ul>
				<li>Přidejte více textu, příkladů, vizuálů nebo FAQ.</li>
				<li>Doporučený rozsah pro informační stránky: 600–1500 slov.</li>
			</ul>

		</div>
	</details>

</div><!-- .wrap -->

<style>
.seob-decay-summary { display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap; }
.seob-decay-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px 20px; min-width: 140px; text-align: center; border-top-width: 4px; }
.seob-decay-decaying { border-top-color: #dc3232; }
.seob-decay-stale    { border-top-color: #f56e28; }
.seob-decay-aging    { border-top-color: #f0b849; }
.seob-decay-fresh    { border-top-color: #46b450; }
.seob-decay-count    { display: block; font-size: 2em; font-weight: 700; }
.seob-decay-label    { display: block; font-weight: 600; }
.seob-decay-sub      { display: block; font-size: 0.8em; color: #777; }
.seob-decay-filters  { margin: 12px 0 8px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.seob-filter-btn.active { background: #2271b1; color: #fff; border-color: #2271b1; }
.seob-scan-date      { margin-left: auto; color: #777; font-size: 0.85em; }
.seob-decay-table td, .seob-decay-table th { vertical-align: middle; }
.seob-decay-badge    { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.8em; font-weight: 600; }
.badge-decaying { background: #fbeaea; color: #dc3232; }
.badge-stale    { background: #fef3ec; color: #f56e28; }
.badge-aging    { background: #fef9ec; color: #b88700; }
.badge-fresh    { background: #edfaee; color: #2a7d2a; }
.seob-score-bar { width: 80px; height: 8px; background: #eee; border-radius: 4px; display: inline-block; vertical-align: middle; margin-right: 6px; }
.seob-score-fill { height: 100%; border-radius: 4px; }
.fill-decaying { background: #dc3232; }
.fill-stale    { background: #f56e28; }
.fill-aging    { background: #f0b849; }
.fill-fresh    { background: #46b450; }
.seob-signals-list { list-style: none; margin: 0; padding: 0; font-size: 0.82em; }
.seob-signals-list li { margin-bottom: 2px; }
.sig-critical { color: #dc3232; }
.sig-warning  { color: #996600; }
.sig-info     { color: #555; }
.seob-notice-inline { background: #fff8e1; border-left: 3px solid #f0b849; padding: 6px 10px; display: inline-block; margin-top: 6px; font-size: 0.9em; }
.seob-docs-content { padding: 16px 20px; border: 1px solid #ddd; border-top: none; background: #fafafa; }
.seob-action-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
</style>

<input type="hidden" id="seob-decay-nonce" value="<?php echo esc_attr( $nonce ); ?>">
