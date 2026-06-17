<?php
/**
 * Hreflang Manager – admin stránka.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro – Hreflang Manager', 'seo-boost' ); ?></h1>

	<p>
		<?php esc_html_e( 'Spravujte hreflang skupiny – každá skupina představuje jeden dokument ve více jazykových verzích. Tagy se automaticky vkládají do <head> všech stránek zahrnutých ve skupině.', 'seo-boost' ); ?>
	</p>

	<details class="seob-schema-help" style="margin-bottom:20px">
		<summary><?php esc_html_e( 'Proč hreflang používat a jak s modulem pracovat (klikněte pro zobrazení)', 'seo-boost' ); ?></summary>
		<div class="seob-schema-help-body">

			<h3><?php esc_html_e( 'Proč hreflang?', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Hreflang tagy říkají Googlu, že existuje více jazykových verzí jednoho dokumentu – a která verze je pro který jazyk nebo region. Bez nich Google indexuje jen jednu verzi (nebo si vybere špatnou), zobrazuje český obsah anglickým uživatelům a naopak. Správné hreflang pokrytí je základní technická podmínka vícejazyčného SEO.', 'seo-boost' ); ?>
			</p>

			<h3><?php esc_html_e( 'Kdy modul použít (a kdy ne)', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat striped seob-audit-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Situace', 'seo-boost' ); ?></th>
						<th><?php esc_html_e( 'Doporučení', 'seo-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Web má obsah ve více jazycích, každý jazyk na vlastní URL (/en/, /de/…)', 'seo-boost' ); ?></td>
						<td style="color:#00a32a"><?php esc_html_e( '✓ Modul použijte', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Rank Math Free – hreflang funkce v bezplatné verzi není', 'seo-boost' ); ?></td>
						<td style="color:#00a32a"><?php esc_html_e( '✓ Modul použijte jako doplněk', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Rank Math Pro nebo Yoast Premium – ty hreflang řeší samy', 'seo-boost' ); ?></td>
						<td style="color:#996800"><?php esc_html_e( '⚠ Modul tagy sám nevloží (detekuje konflikt), skupiny ale lze udržovat jako zálohu dat', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Jednojazyčný web', 'seo-boost' ); ?></td>
						<td style="color:#646970"><?php esc_html_e( '– Modul není potřeba', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3 style="margin-top:20px"><?php esc_html_e( 'Jak skupiny fungují', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( '1 skupina = 1 dokument ve více jazykových verzích. Každý člen skupiny je WordPress stránka/příspěvek s přiřazeným locale kódem. Google vyžaduje, aby hreflang byl reciproční – všechny verze musí odkazovat na všechny ostatní. Tento modul reciprocitu garantuje automaticky: stačí přidat všechny verze do jedné skupiny a tagy se generují správně.', 'seo-boost' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Příklad výstupu pro skupinu s českou, anglickou a slovenskou verzí:', 'seo-boost' ); ?>
			</p>
			<pre style="background:#f6f7f7;padding:10px 14px;font-size:12px;overflow-x:auto;border:1px solid #dcdcde;border-radius:3px">&lt;link rel="alternate" hreflang="cs" href="https://example.com/" /&gt;
&lt;link rel="alternate" hreflang="x-default" href="https://example.com/" /&gt;
&lt;link rel="alternate" hreflang="en" href="https://example.com/en/" /&gt;
&lt;link rel="alternate" hreflang="sk" href="https://example.com/sk/" /&gt;</pre>

			<h3><?php esc_html_e( 'Pole x-default', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Označte verzi, která se zobrazí uživatelům bez odpovídajícího jazyka (typicky anglická verze nebo hlavní jazyková mutace). Ve skupině může být x-default pouze jedna stránka.', 'seo-boost' ); ?>
			</p>

			<h3><?php esc_html_e( 'Locale kódy (BCP 47)', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat striped seob-audit-table" style="max-width:480px">
				<thead><tr><th><?php esc_html_e( 'Jazyk', 'seo-boost' ); ?></th><th><?php esc_html_e( 'Kód', 'seo-boost' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Čeština', 'seo-boost' ); ?></td><td><code>cs</code></td></tr>
					<tr><td><?php esc_html_e( 'Slovenština', 'seo-boost' ); ?></td><td><code>sk</code></td></tr>
					<tr><td><?php esc_html_e( 'Angličtina (obecná)', 'seo-boost' ); ?></td><td><code>en</code></td></tr>
					<tr><td><?php esc_html_e( 'Angličtina – USA', 'seo-boost' ); ?></td><td><code>en-US</code></td></tr>
					<tr><td><?php esc_html_e( 'Angličtina – UK', 'seo-boost' ); ?></td><td><code>en-GB</code></td></tr>
					<tr><td><?php esc_html_e( 'Němčina', 'seo-boost' ); ?></td><td><code>de</code></td></tr>
					<tr><td><?php esc_html_e( 'Francouzština', 'seo-boost' ); ?></td><td><code>fr</code></td></tr>
					<tr><td><?php esc_html_e( 'Polština', 'seo-boost' ); ?></td><td><code>pl</code></td></tr>
				</tbody>
			</table>

			<h3 style="margin-top:20px"><?php esc_html_e( 'Validátor', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Tlačítko „Spustit validaci" zkontroluje dvě kritické chyby:', 'seo-boost' ); ?>
			</p>
			<ul style="list-style:disc;padding-left:20px">
				<li><?php esc_html_e( 'Stránka je zařazena do více skupin – Google dostane protichůdné signály, výsledek je nepředvídatelný.', 'seo-boost' ); ?></li>
				<li><?php esc_html_e( 'Stránka ve skupině není publikovaná (draft, koš…) – hreflang odkaz na nepřístupnou URL je chyba indexace.', 'seo-boost' ); ?></li>
			</ul>
			<p class="description"><?php esc_html_e( 'Validaci spusťte vždy po přidání nových jazykových verzí nebo po změně struktury webu.', 'seo-boost' ); ?></p>

			<h3><?php esc_html_e( 'Automatické mapování skupin (připravováno)', 'seo-boost' ); ?></h3>
			<p>
				<?php esc_html_e( 'Aktuálně se skupiny vytvářejí ručně. Do budoucí verze modulu je naplánována funkce „Automaticky detekovat skupiny", která projde stránky webu a navrhne mapování – uživatel návrhy potvrdí nebo upraví a teprve pak se uloží. Žádné skupiny se nevytvoří automaticky bez schválení.', 'seo-boost' ); ?>
			</p>
			<p class="description"><?php esc_html_e( 'Plánované strategie detekce (od nejspolehlivější po nejméně spolehlivou):', 'seo-boost' ); ?></p>
			<ol style="padding-left:20px;margin-top:4px">
				<li>
					<strong><?php esc_html_e( 'Polylang integrace', 'seo-boost' ); ?></strong>
					<?php esc_html_e( '– pokud web používá Polylang, přečte se jeho interní překladové mapování (pll_get_post_translations). Skupiny lze vygenerovat jedním kliknutím s přesností 100 %, protože data jsou přímo z překladového pluginu.', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'WPML integrace', 'seo-boost' ); ?></strong>
					<?php esc_html_e( '– stejný princip jako Polylang, ale přes WPML API (icl_object_id).', 'seo-boost' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Shoda URL slugu', 'seo-boost' ); ?></strong>
					<?php esc_html_e( '– bez jazykového pluginu algoritmus porovná slugy stránek v různých jazykových adresářích. Příklad: /o-nas/ a /en/about-us/ mají různé slugy, ale /cs/kontakt/ a /en/contact/ se snadno spárují přes strukturu URL. Spolehlivost závisí na konzistenci pojmenování stránek.', 'seo-boost' ); ?>
				</li>
			</ol>
			<p class="description" style="margin-top:8px">
				<?php esc_html_e( 'Pro tuto funkci je potřeba mít vícejazyčný web s reálnými daty. Ručně vytvořené skupiny zůstanou po implementaci auto-mapování nedotčeny.', 'seo-boost' ); ?>
			</p>

			<h3 style="margin-top:20px"><?php esc_html_e( 'Časté problémy', 'seo-boost' ); ?></h3>
			<table class="wp-list-table widefat striped seob-audit-table">
				<thead><tr><th><?php esc_html_e( 'Problém', 'seo-boost' ); ?></th><th><?php esc_html_e( 'Řešení', 'seo-boost' ); ?></th></tr></thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Tagy se nevkládají do stránky', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Zkontrolujte žlutý banner (konflikt s jiným pluginem). Hreflang se vkládá jen na singulární stránky – homepage musí být nastavena jako Statická stránka (Nastavení → Čtení).', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'GSC hlásí „Alternate page with proper canonical tag"', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Každá stránka musí mít canonical odkazující sama na sebe (ne na jinou jazykovou verzi). Canonical nastavuje Rank Math nebo Yoast na záložce příspěvku.', 'seo-boost' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Chci jednu verzi dočasně vyřadit', 'seo-boost' ); ?></td>
						<td><?php esc_html_e( 'Klikněte Upravit skupinu a odeberte daný řádek. Stránka zůstane zachována v WordPressu, jen vypadne z hreflang výstupu.', 'seo-boost' ); ?></td>
					</tr>
				</tbody>
			</table>

		</div>
	</details>

	<div id="seob-hreflang-status" style="display:none;margin-bottom:16px"></div>

	<div class="seob-toolbar">
		<button type="button" id="seob-hreflang-add" class="button button-primary">
			+ <?php esc_html_e( 'Nová skupina', 'seo-boost' ); ?>
		</button>
		<button type="button" id="seob-hreflang-validate" class="button">
			<?php esc_html_e( 'Spustit validaci', 'seo-boost' ); ?>
		</button>
	</div>

	<div id="seob-hreflang-validation-result" style="display:none;margin:12px 0;padding:12px 16px;border-left:4px solid #dcdcde;background:#f6f7f7"></div>

	<div id="seob-hreflang-groups" style="margin-top:16px">
		<p class="description"><?php esc_html_e( 'Načítám skupiny…', 'seo-boost' ); ?></p>
	</div>
</div>

<div id="seob-hreflang-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
	<div style="background:#fff;border-radius:4px;width:660px;max-width:90vw;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 4px 24px rgba(0,0,0,.2)">
		<div style="padding:16px 20px;border-bottom:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center">
			<h2 id="seob-modal-title" style="margin:0;font-size:16px"></h2>
			<button type="button" id="seob-modal-close" class="button-link" style="font-size:22px;line-height:1;color:#3c434a;text-decoration:none">&times;</button>
		</div>
		<div style="padding:16px 20px;overflow-y:auto;flex:1">
			<input type="hidden" id="seob-modal-group-id" value="">

			<table class="form-table" style="margin:0 0 16px">
				<tr>
					<th style="padding:8px 0;width:140px">
						<label for="seob-modal-name"><?php esc_html_e( 'Název skupiny', 'seo-boost' ); ?></label>
					</th>
					<td style="padding:8px 0">
						<input type="text" id="seob-modal-name" class="regular-text" placeholder="<?php esc_attr_e( 'např. Hlavní stránka', 'seo-boost' ); ?>">
					</td>
				</tr>
			</table>

			<h3 style="margin:0 0 4px;font-size:14px"><?php esc_html_e( 'Jazykové verze', 'seo-boost' ); ?></h3>
			<p class="description" style="margin:0 0 10px">
				<?php esc_html_e( 'Každý řádek = jedna jazyková verze. Locale ve formátu BCP 47: cs, en, en-US, de…', 'seo-boost' ); ?>
			</p>

			<table class="wp-list-table widefat striped" id="seob-modal-members-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Stránka', 'seo-boost' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Locale', 'seo-boost' ); ?></th>
						<th style="width:80px;text-align:center"><?php esc_html_e( 'x-default', 'seo-boost' ); ?></th>
						<th style="width:30px"></th>
					</tr>
				</thead>
				<tbody id="seob-modal-members"></tbody>
			</table>

			<button type="button" id="seob-modal-add-member" class="button" style="margin-top:8px">
				+ <?php esc_html_e( 'Přidat verzi', 'seo-boost' ); ?>
			</button>
		</div>
		<div style="padding:12px 20px;border-top:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center">
			<span id="seob-modal-error" style="color:#d63638;font-size:13px"></span>
			<div style="display:flex;gap:8px">
				<button type="button" id="seob-modal-cancel" class="button"><?php esc_html_e( 'Zrušit', 'seo-boost' ); ?></button>
				<button type="button" id="seob-modal-save" class="button button-primary"><?php esc_html_e( 'Uložit skupinu', 'seo-boost' ); ?></button>
			</div>
		</div>
	</div>
</div>
