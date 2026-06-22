<?php
/**
 * Postranní metabox v editoru – počet interních odkazů a návrhy prolinkování pro daný příspěvek.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_MetaBox {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, SEOB_Audit_ScanRunner::get_audit_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'seob-metabox-internal-links',
			SEOB_PLUGIN_URL . 'assets/admin/js/metabox-internal-links.js',
			[],
			SEOB_VERSION,
			true
		);

		wp_localize_script(
			'seob-metabox-internal-links',
			'seobLinksMetabox',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'seob_admin_nonce' ),
				'i18n'    => [
					'find'            => __( 'Najít návrhy linků', 'seo-boost' ),
					'finding'         => __( 'Hledám…', 'seo-boost' ),
					'inserting'       => __( 'Vkládám…', 'seo-boost' ),
					'insertSelected'  => __( 'Vložit vybrané (%d)', 'seo-boost' ),
					'elementorWarn'   => __( 'Pozor: tato stránka používá Elementor. Vložení může ovlivnit layout.', 'seo-boost' ),
					'insertConfirm'   => __( 'Potvrdit vložení (Elementor)', 'seo-boost' ),
					'reload'          => __( 'Uloženo. Reloadujte editor pro zobrazení změn v obsahu.', 'seo-boost' ),
					'noMatch'         => __( 'V obsahu článku nebyly nalezeny žádné klíčové výrazy orphan stránek.', 'seo-boost' ),
					'selectAll'       => __( 'Vybrat vše', 'seo-boost' ),
					'deselectAll'     => __( 'Zrušit výběr', 'seo-boost' ),
					'context'         => __( 'Kontext:', 'seo-boost' ),
					'loadMore'        => __( 'Načíst dalších 10', 'seo-boost' ),
				],
			]
		);
	}

	public function register(): void {
		foreach ( SEOB_Audit_ScanRunner::get_audit_post_types() as $post_type ) {
			add_meta_box(
				'seob-internal-links',
				__( 'Interní prolinkování', 'seo-boost' ),
				[ $this, 'render' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render( WP_Post $post ): void {
		global $wpdb;

		$links_table = SEOB_Database::internal_links_table();

		$inlinks = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$links_table} WHERE target_id = %d", $post->ID ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$outlinks = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$links_table} WHERE source_id = %d", $post->ID ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Počet slov → doporučený rozsah (2–5 odkazů na 1000 slov).
		$plain_text = wp_strip_all_tags( $post->post_content );
		$word_count = $plain_text ? count( array_filter( preg_split( '/\s+/u', trim( $plain_text ) ) ) ) : 0;
		$min_links  = max( 1, (int) round( $word_count / 500 ) );
		$max_links  = max( 2, (int) round( $word_count / 200 ) );

		[ $status_color, $status_text ] = $this->compute_link_status( $outlinks, $min_links, $max_links, $word_count );

		?>
		<p id="seob-metabox-counts"
			data-link-min="<?php echo esc_attr( (string) $min_links ); ?>"
			data-link-max="<?php echo esc_attr( (string) $max_links ); ?>"
			data-word-count="<?php echo esc_attr( (string) $word_count ); ?>">
			<?php esc_html_e( 'Příchozí:', 'seo-boost' ); ?>
			<strong id="seob-metabox-inlinks"><?php echo esc_html( (string) $inlinks ); ?></strong>
			&nbsp;·&nbsp;
			<?php esc_html_e( 'Odchozí:', 'seo-boost' ); ?>
			<strong id="seob-metabox-outlinks"><?php echo esc_html( (string) $outlinks ); ?></strong>
			<span id="seob-metabox-link-status"
				style="display:block;margin-top:4px;font-size:12px;color:<?php echo esc_attr( $status_color ); ?>">
				<?php echo esc_html( $status_text ); ?>
			</span>
		</p>

		<?php if ( 0 === $inlinks ) : ?>
			<p class="description" style="margin-top:0"><?php esc_html_e( 'Na tuto stránku zatím nikde neukazuje interní odkaz.', 'seo-boost' ); ?></p>
		<?php endif; ?>

		<hr style="margin:10px 0">

		<div style="font-size:12px;margin-bottom:8px">
			<label style="display:flex;align-items:center;gap:5px;margin-bottom:5px;cursor:pointer">
				<input type="checkbox" id="seob-opt-new-window" checked>
				<?php esc_html_e( 'Otevřít v novém okně (target="_blank")', 'seo-boost' ); ?>
			</label>
			<label style="display:flex;align-items:center;gap:5px;cursor:pointer">
				<input type="checkbox" id="seob-opt-nofollow">
				<?php esc_html_e( 'Označit jako nofollow', 'seo-boost' ); ?>
			</label>
		</div>

		<button type="button" id="seob-links-find-btn" class="button button-primary"
			style="width:100%;margin-bottom:6px"
			data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
			<?php esc_html_e( 'Najít návrhy linků', 'seo-boost' ); ?>
		</button>
		<div id="seob-links-candidates" style="display:none;margin-top:4px"></div>
		<p id="seob-links-insert-result" class="description" style="display:none;margin-top:4px"></p>
		<?php
	}

	/**
	 * Vrátí [css_barva, text] indikátoru pro aktuální počet odchozích odkazů.
	 *
	 * @return array{0:string,1:string}
	 */
	private function compute_link_status( int $outlinks, int $min, int $max, int $word_count ): array {
		if ( $outlinks >= $min && $outlinks <= $max ) {
			return [
				'#00a32a',
				sprintf( '✓ V pořádku (doporučeno %d–%d pro ~%d slov)', $min, $max, $word_count ),
			];
		}

		if ( $outlinks < $min ) {
			$deficit = $min - $outlinks;
			$noun    = 1 === $deficit ? 'odkaz' : 'odkazů';
			return [
				'#d63638',
				sprintf( '↑ Chybí %d %s (doporučeno %d–%d pro ~%d slov)', $deficit, $noun, $min, $max, $word_count ),
			];
		}

		$surplus = $outlinks - $max;
		$noun    = 1 === $surplus ? 'odkaz' : 'odkazů';
		return [
			'#996800',
			sprintf( '↓ Přebývá %d %s (max. %d pro ~%d slov)', $surplus, $noun, $max, $word_count ),
		];
	}
}
