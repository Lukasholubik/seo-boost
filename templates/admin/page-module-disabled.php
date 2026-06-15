<?php
/**
 * Fallback stránka pro vypnutý modul (zobrazí se, pokud na ni uživatel
 * doputuje přímou URL, např. přes záložku v prohlížeči).
 *
 * @var string $seob_module_label Název modulu pro zobrazení.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap seob-wrap">
	<h1><?php esc_html_e( 'SEO Booster Pro', 'seo-boost' ); ?></h1>

	<div class="notice notice-warning">
		<p>
			<?php
			printf(
				/* translators: 1: název modulu, 2: odkaz na Stav systému, 3: odkaz na Nastavení */
				esc_html__( 'Modul „%1$s“ je vypnutý. Zapnout ho můžete na stránce %2$s (tlačítko „Zapnout“) nebo v %3$s → Moduly.', 'seo-boost' ),
				esc_html( $seob_module_label ?? '' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=seob-status' ) ) . '">' . esc_html__( 'Stav systému', 'seo-boost' ) . '</a>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=seob-settings' ) ) . '">' . esc_html__( 'Nastavení', 'seo-boost' ) . '</a>'
			);
			?>
		</p>
	</div>
</div>
