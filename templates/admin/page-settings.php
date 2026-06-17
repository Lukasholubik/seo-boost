<?php
/**
 * Obecné nastavení pluginu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$general  = SEOB_Settings::get( SEOB_Settings::GENERAL );
$audit    = SEOB_Settings::get( SEOB_Settings::AUDIT );
$redirect = SEOB_Settings::get( SEOB_Settings::REDIRECT );
$ai       = SEOB_Settings::get( SEOB_Settings::AI );
$pagespeed = SEOB_Settings::get( SEOB_Settings::PAGESPEED );
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Nastavení', 'seo-boost' ); ?></h1>

	<form id="seob-settings-form">
		<h2><?php esc_html_e( 'Obecné', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Moduly', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="modules_audit" value="1" <?php checked( ! empty( $general['modules']['audit'] ) ); ?>>
						<?php esc_html_e( 'Audit Dashboard', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_redirects" value="1" <?php checked( ! empty( $general['modules']['redirects'] ) ); ?>>
						<?php esc_html_e( 'Redirect Manager (404 log + přesměrování)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_pdf" value="1" <?php checked( ! empty( $general['modules']['pdf'] ) ); ?>>
						<?php esc_html_e( 'Export PDF reportu', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_smart_indexing" value="1" <?php checked( ! empty( $general['modules']['smart-indexing'] ) ); ?>>
						<?php esc_html_e( 'Chytrá indexace (katalogové a filtrované stránky)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_gsc_insights" value="1" <?php checked( ! empty( $general['modules']['gsc-insights'] ) ); ?>>
						<?php esc_html_e( 'Search Console statistiky v Audit Dashboardu (z dat Rank Math)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_ai_queue" value="1" <?php checked( ! empty( $general['modules']['ai-queue'] ) ); ?>>
						<?php esc_html_e( 'AI schvalovací fronta (návrhy title/description/alt textů ke schválení)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_pagespeed" value="1" <?php checked( ! empty( $general['modules']['pagespeed'] ) ); ?>>
						<?php esc_html_e( 'PageSpeed Insights (Lighthouse skóre a SEO doporučení pro vzorek stránek)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_internal_links" value="1" <?php checked( ! empty( $general['modules']['internal-links'] ) ); ?>>
						<?php esc_html_e( 'Interní prolinkování (link graf, orphan stránky, návrhy prolinkování)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_hreflang" value="1" <?php checked( ! empty( $general['modules']['hreflang'] ) ); ?>>
						<?php esc_html_e( 'Hreflang Manager (vícejazyčné weby – správa <link rel="alternate" hreflang>)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_local_seo" value="1" <?php checked( ! empty( $general['modules']['local-seo'] ) ); ?>>
						<?php esc_html_e( 'Local SEO CZ (LocalBusiness JSON-LD schéma s IČO/DIČ, GPS, otevírací dobou + NAP scanner)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_json_ld" value="1" <?php checked( ! empty( $general['modules']['json-ld'] ) ); ?>>
						<?php esc_html_e( 'JSON-LD Validátor (extrahuje a validuje strukturovaná data, detekuje duplicitní schémata)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_cwv_rum" value="1" <?php checked( ! empty( $general['modules']['cwv-rum'] ) ); ?>>
						<?php esc_html_e( 'Core Web Vitals (RUM) – měří LCP, INP, CLS od reálných návštěvníků, anonymní beacon bez cookies', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_js_render_gap" value="1" <?php checked( ! empty( $general['modules']['js-render-gap'] ) ); ?>>
						<?php esc_html_e( 'JS Render Gap – detekuje obsah skrytý Googlu bez JS renderování (porovnání raw HTML vs. rendered DOM)', 'seo-boost' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="modules_http_headers" value="1" <?php checked( ! empty( $general['modules']['http-headers'] ) ); ?>>
						<?php esc_html_e( 'HTTP Hlavičky & Bezpečnost – kontroluje x-robots-tag, HTTPS, HSTS, X-Frame-Options a cache hlavičky', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ladicí režim', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="debug" value="1" <?php checked( ! empty( $general['debug'] ) ); ?>>
						<?php esc_html_e( 'Zapnout debug log', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Odinstalace', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $general['delete_on_uninstall'] ) ); ?>>
						<?php esc_html_e( 'Při odinstalaci smazat všechna data pluginu (DB tabulky a nastavení)', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Audit Dashboard', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="seob-batch-size"><?php esc_html_e( 'Velikost dávky', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-batch-size" name="batch_size" min="1" max="100" value="<?php echo esc_attr( (int) $audit['batch_size'] ); ?>">
					<p class="description"><?php esc_html_e( 'Počet URL zpracovaných v jedné dávce při spuštění scanu.', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-thin-content"><?php esc_html_e( 'Hranice thin content', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-thin-content" name="thin_content_words" min="50" max="2000" step="10" value="<?php echo esc_attr( (int) $audit['thin_content_words'] ); ?>">
					<p class="description"><?php esc_html_e( 'Stránky s menším počtem slov budou označeny jako "thin content".', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-history-limit"><?php esc_html_e( 'Historie scanů', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-history-limit" name="history_limit" min="1" max="200" value="<?php echo esc_attr( (int) $audit['history_limit'] ); ?>">
					<p class="description"><?php esc_html_e( 'Maximální počet uložených dokončených scanů. Starší scany se po dokončení nového scanu automaticky smažou (snižuje zabrané místo v databázi).', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Noční scan (cron)', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cron_enabled" value="1" <?php checked( ! empty( $audit['cron_enabled'] ) ); ?>>
						<?php esc_html_e( 'Spouštět scan automaticky každou noc', 'seo-boost' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Poznámka: automatický noční scan zatím není implementován, nastavení se uloží pro budoucí verzi.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Přesměrování', 'seo-boost' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( '404 log', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="log_404" value="1" <?php checked( ! empty( $redirect['log_404'] ) ); ?>>
						<?php esc_html_e( 'Zaznamenávat 404 chyby', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-log-retention"><?php esc_html_e( 'Retence 404 logu', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-log-retention" name="log_retention_days" min="1" max="365" value="<?php echo esc_attr( (int) $redirect['log_retention_days'] ); ?>">
					<p class="description"><?php esc_html_e( 'Počet dní, po které se uchovávají 404 záznamy bez nastaveného přesměrování.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'AI asistent', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'AI návrhy (title, description, alt texty) se nikdy neukládají automaticky – po vygenerování čekají ke schválení v "AI frontě". Lze použít libovolné OpenAI-compatible API, např. zdarma dostupný Google Gemini nebo Groq endpoint – podrobnosti v dokumentaci modulu.', 'seo-boost' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'AI asistent', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ai_enabled" value="1" <?php checked( ! empty( $ai['enabled'] ) ); ?>>
						<?php esc_html_e( 'Zapnout generování AI návrhů', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-ai-endpoint"><?php esc_html_e( 'API endpoint', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="seob-ai-endpoint" name="ai_endpoint" class="regular-text" value="<?php echo esc_attr( $ai['endpoint'] ); ?>" placeholder="https://generativelanguage.googleapis.com/v1beta/openai/">
					<p class="description"><?php esc_html_e( 'Base URL OpenAI-compatible API (bez /chat/completions na konci).', 'seo-boost' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-ai-model"><?php esc_html_e( 'Model', 'seo-boost' ); ?></label></th>
				<td>
					<input type="text" id="seob-ai-model" name="ai_model" class="regular-text" value="<?php echo esc_attr( $ai['model'] ); ?>" placeholder="gemini-2.0-flash">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-ai-max-tokens"><?php esc_html_e( 'Max. tokenů odpovědi', 'seo-boost' ); ?></label></th>
				<td>
					<input type="number" id="seob-ai-max-tokens" name="ai_max_tokens" min="1" max="4000" value="<?php echo esc_attr( (int) $ai['max_tokens'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-ai-api-key"><?php esc_html_e( 'API klíč', 'seo-boost' ); ?></label></th>
				<td>
					<input type="password" id="seob-ai-api-key" name="ai_api_key" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' !== $ai['api_key_enc'] ? esc_attr__( '•••••••• (vyplňte jen pro změnu)', 'seo-boost' ) : ''; ?>">
					<p class="description"><?php esc_html_e( 'Klíč je v databázi uložen šifrovaně. Pole ponechte prázdné, pokud nechcete uložený klíč měnit.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'PageSpeed Insights (Lighthouse)', 'seo-boost' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %s: odkaz na dokumentaci modulu */
				esc_html__( 'Vyžaduje bezplatný API klíč Google PageSpeed Insights. Návod na jeho vytvoření najdete v %s.', 'seo-boost' ),
				'<a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noopener">' . esc_html__( 'dokumentaci modulu', 'seo-boost' ) . '</a>'
			);
			?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'PageSpeed Insights', 'seo-boost' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="pagespeed_enabled" value="1" <?php checked( ! empty( $pagespeed['enabled'] ) ); ?>>
						<?php esc_html_e( 'Zapnout analýzu PageSpeed Insights', 'seo-boost' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seob-pagespeed-api-key"><?php esc_html_e( 'API klíč', 'seo-boost' ); ?></label></th>
				<td>
					<input type="password" id="seob-pagespeed-api-key" name="pagespeed_api_key" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' !== $pagespeed['api_key_enc'] ? esc_attr__( '•••••••• (vyplňte jen pro změnu)', 'seo-boost' ) : ''; ?>">
					<p class="description"><?php esc_html_e( 'Klíč je v databázi uložen šifrovaně. Pole ponechte prázdné, pokud nechcete uložený klíč měnit.', 'seo-boost' ); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" id="seob-save-settings" class="button button-primary"><?php esc_html_e( 'Uložit nastavení', 'seo-boost' ); ?></button>
			<span id="seob-settings-status" class="seob-save-status"></span>
		</p>
	</form>

	<p class="description">
		<?php
		printf(
			/* translators: %s: odkaz na stránku Export – nastavení */
			esc_html__( 'Texty nálezů, obchodní nabídky a firemní údaje pro PDF report najdete na stránce %s.', 'seo-boost' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=seob-pdf-settings' ) ) . '">' . esc_html__( 'Export – nastavení', 'seo-boost' ) . '</a>'
		);
		?>
	</p>

	<h2><?php esc_html_e( 'Schéma (strukturovaná data)', 'seo-boost' ); ?></h2>
	<details class="seob-schema-help">
		<summary><?php esc_html_e( 'Jak schéma funguje a kdy který typ použít (klikněte pro zobrazení)', 'seo-boost' ); ?></summary>
		<div class="seob-schema-help-body">
			<p>
				<?php esc_html_e( 'Schéma (strukturovaná data) říká vyhledávačům, o jaký druh obsahu na stránce jde – díky tomu se ve výsledcích vyhledávání mohou zobrazit dodatečné informace (cena, hodnocení, datum akce apod.). Pro každý příspěvek se použije první nalezené pravidlo v tomto pořadí:', 'seo-boost' ); ?>
			</p>
			<ol>
				<li><?php esc_html_e( 'Vlastní nastavení přímo u příspěvku (záložka "Rich Snippet" v Rank Math) – má vždy přednost.', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Výchozí schéma podle kategorie (sekce níže) – pokud příspěvek patří do kategorie s nastaveným schématem.', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Výchozí schéma podle typu obsahu (sekce níže) – obecnější nastavení pro celý typ obsahu (Příspěvky, Stránky, vlastní typy).', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Pokud nic z výše uvedeného není nastaveno, použije se globální nastavení Rank Math, případně žádné (WebPage).', 'seo-boost' ); ?></li>
			</ol>
			<p class="description">
				<?php esc_html_e( 'U názvu typu obsahu nebo kategorie níže klikněte na proklik (otevře se seznam příspěvků v novém okně) a u výběru schématu na ikonu nápovědy – zobrazí se popis daného typu a kdy je vhodné jej použít.', 'seo-boost' ); ?>
			</p>
			<table class="wp-list-table widefat fixed striped seob-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Typ schématu', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Kdy použít', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( SEOB_Schema_Helper::TYPES as $seob_type_key => $seob_type_label ) : ?>
						<tr>
							<td><?php echo esc_html( $seob_type_label ); ?></td>
							<td><?php echo esc_html( SEOB_Schema_Helper::TYPE_DESCRIPTIONS[ $seob_type_key ] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</details>

	<h2><?php esc_html_e( 'Výchozí schéma podle typu obsahu', 'seo-boost' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Pro příspěvky daného typu obsahu (např. Příspěvky, Stránky, nebo vlastní typy jako Slovníček pojmů, Landing page apod.), které nemají vlastní nastavení schématu ani schéma podle kategorie, se automaticky použije zvolený typ. Toto je obecnější nastavení – schéma podle kategorie (níže) má přednost.', 'seo-boost' ); ?>
	</p>
	<div id="seob-posttype-progress" class="seob-progress">
		<div class="seob-progress-bar"><div id="seob-posttype-progress-fill" class="seob-progress-fill"></div></div>
		<span id="seob-posttype-progress-text" class="seob-progress-text"></span>
	</div>

	<p>
		<button type="button" id="seob-posttype-save-all" class="button" disabled><?php esc_html_e( 'Uložit vše', 'seo-boost' ); ?></button>
		<span id="seob-posttype-save-all-status" class="seob-save-status"></span>
	</p>

	<table class="wp-list-table widefat fixed striped seob-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Typ obsahu', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Počet příspěvků', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Schéma', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Doporučeno', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-posttype-body">
			<tr class="seob-empty-row">
				<td colspan="5"><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></td>
			</tr>
		</tbody>
	</table>

	<template id="seob-posttype-row-template">
		<tr>
			<td class="seob-posttype-name"></td>
			<td class="seob-posttype-count"></td>
			<td><select class="seob-posttype-select"></select></td>
			<td class="seob-posttype-suggested"></td>
			<td>
				<button type="button" class="button button-primary seob-posttype-save"><?php esc_html_e( 'Uložit', 'seo-boost' ); ?></button>
				<button type="button" class="button seob-posttype-reset" title="<?php esc_attr_e( 'Zrušit vlastní nastavení a vrátit se k výchozímu chování (Rank Math)', 'seo-boost' ); ?>"><?php esc_html_e( 'Resetovat', 'seo-boost' ); ?></button>
				<span class="seob-save-status"></span>
			</td>
		</tr>
	</template>

	<h2><?php esc_html_e( 'Výchozí schéma podle kategorie', 'seo-boost' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Pro příspěvky v dané kategorii, které nemají vlastní nastavení schématu, se automaticky použije zvolený typ místo výchozího nastavení Rank Math pro daný typ obsahu. Není potřeba nic ukládat ručně u jednotlivých příspěvků.', 'seo-boost' ); ?>
	</p>
	<div id="seob-schema-progress" class="seob-progress">
		<div class="seob-progress-bar"><div id="seob-schema-progress-fill" class="seob-progress-fill"></div></div>
		<span id="seob-schema-progress-text" class="seob-progress-text"></span>
	</div>

	<p>
		<button type="button" id="seob-schema-cat-save-all" class="button" disabled><?php esc_html_e( 'Uložit vše', 'seo-boost' ); ?></button>
		<span id="seob-schema-cat-save-all-status" class="seob-save-status"></span>
	</p>

	<table class="wp-list-table widefat fixed striped seob-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Kategorie', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Počet příspěvků', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Schéma', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Doporučeno', 'seo-boost' ); ?></th>
				<th><?php esc_html_e( 'Akce', 'seo-boost' ); ?></th>
			</tr>
		</thead>
		<tbody id="seob-schema-categories-body">
			<tr class="seob-empty-row">
				<td colspan="5"><?php esc_html_e( 'Načítám…', 'seo-boost' ); ?></td>
			</tr>
		</tbody>
	</table>

	<template id="seob-schema-category-row-template">
		<tr>
			<td class="seob-schema-cat-name"></td>
			<td class="seob-schema-cat-count"></td>
			<td><select class="seob-schema-cat-select"></select></td>
			<td class="seob-schema-cat-suggested"></td>
			<td>
				<button type="button" class="button button-primary seob-schema-cat-save"><?php esc_html_e( 'Uložit', 'seo-boost' ); ?></button>
				<button type="button" class="button seob-schema-cat-reset" title="<?php esc_attr_e( 'Zrušit vlastní nastavení a vrátit se k výchozímu chování (Rank Math / typ obsahu)', 'seo-boost' ); ?>"><?php esc_html_e( 'Resetovat', 'seo-boost' ); ?></button>
				<span class="seob-save-status"></span>
			</td>
		</tr>
	</template>
</div>
