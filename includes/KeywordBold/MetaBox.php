<?php
/**
 * KeywordBold – metabox v editoru příspěvků (Gutenberg i Classic Editor).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_KeywordBold_MetaBox {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function register(): void {
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'seob-keyword-bold',
				'🔡 ' . __( 'Zvýraznění KW (SEO Boost)', 'seo-boost' ),
				[ $this, 'render' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render( WP_Post $post ): void {
		$keywords  = SEOB_KeywordBold_Processor::get_keywords( $post->ID, true );
		$is_bolded = SEOB_KeywordBold_Processor::has_our_bold( $post->post_content );
		$meta      = get_post_meta( $post->ID, '_seob_kw_bold_applied', true );
		$applied   = $meta ? json_decode( $meta, true ) : null;
		$nonce     = wp_create_nonce( 'seob_admin_nonce' );
		?>
		<div id="seob-kwbold-metabox" style="font-size:13px">

			<?php if ( ! empty( $keywords ) ) : ?>
				<p style="margin:0 0 6px">
					<strong><?php esc_html_e( 'Focus KW (Rank Math):', 'seo-boost' ); ?></strong><br>
					<?php foreach ( $keywords as $i => $kw ) : ?>
						<span style="display:inline-block;background:<?php echo $i === 0 ? '#e8f5e9' : '#f3f4f6'; ?>;border:1px solid #ccc;border-radius:3px;padding:1px 6px;margin:2px 2px 2px 0;font-size:12px">
							<?php echo esc_html( $kw ); ?>
						</span>
					<?php endforeach; ?>
				</p>
			<?php else : ?>
				<p style="color:#888;margin:0 0 8px;font-size:12px">
					<?php esc_html_e( 'Žádné Focus KW (nastav v Rank Math).', 'seo-boost' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $is_bolded && $applied ) : ?>
				<p style="margin:0 0 6px;color:#2d7738;font-size:12px">
					✓ <?php printf( esc_html__( 'Zvýrazněno %d×', 'seo-boost' ), (int) ( $applied['count'] ?? 0 ) ); ?>
				</p>
			<?php endif; ?>

			<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">

				<label style="font-size:12px">
					<?php esc_html_e( 'Max. výskytů:', 'seo-boost' ); ?>
					<select id="seob-kwbold-max" style="margin-left:6px;font-size:12px">
						<option value="1" selected>1× (doporučeno)</option>
						<option value="2">2×</option>
						<option value="3">3×</option>
					</select>
				</label>

				<label style="font-size:12px;display:flex;align-items:center;gap:4px">
					<input type="checkbox" id="seob-kwbold-secondary">
					<?php esc_html_e( 'Zahrnout sekundární KW', 'seo-boost' ); ?>
				</label>

				<label style="font-size:12px">
					<?php esc_html_e( 'Vlastní KW (přebije RM):', 'seo-boost' ); ?><br>
					<input type="text" id="seob-kwbold-custom" placeholder="kw1, kw2" style="width:100%;margin-top:3px;font-size:12px">
				</label>

				<button type="button" id="seob-kwbold-preview" class="button" style="font-size:12px;width:100%">
					🔍 <?php esc_html_e( 'Náhled', 'seo-boost' ); ?>
				</button>

				<button type="button" id="seob-kwbold-apply" class="button button-primary" style="font-size:12px;width:100%">
					✦ <?php esc_html_e( 'Zvýraznit KW', 'seo-boost' ); ?>
				</button>

				<?php if ( $is_bolded ) : ?>
				<button type="button" id="seob-kwbold-undo" class="button" style="font-size:12px;width:100%;color:#b32d2e;border-color:#b32d2e">
					↺ <?php esc_html_e( 'Odebrat zvýraznění', 'seo-boost' ); ?>
				</button>
				<?php endif; ?>

			</div>

			<div id="seob-kwbold-status" style="margin-top:8px;font-size:12px;display:none"></div>

			<script>
			(function(){
				var postId  = <?php echo (int) $post->ID; ?>;
				var nonce   = '<?php echo esc_js( $nonce ); ?>';
				var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
				var status  = document.getElementById('seob-kwbold-status');

				function getOpts() {
					return {
						post_id:         postId,
						nonce:           nonce,
						max_occurrences: document.getElementById('seob-kwbold-max').value,
						use_secondary:   document.getElementById('seob-kwbold-secondary').checked ? '1' : '',
						custom_keywords: document.getElementById('seob-kwbold-custom').value.trim(),
						overwrite:       '1',
					};
				}

				function req(action, extra, cb) {
					var fd = new FormData();
					fd.append('action', action);
					Object.keys(extra).forEach(function(k){ fd.append(k, extra[k]); });
					fetch(ajaxUrl, {method:'POST', credentials:'same-origin', body:fd})
						.then(function(r){ return r.json(); })
						.then(cb)
						.catch(function(){ showStatus('Chyba sítě.', false); });
				}

				function showStatus(msg, ok) {
					status.textContent = msg;
					status.style.color = ok ? '#2d7738' : '#b32d2e';
					status.style.display = '';
				}

				var prevBtn = document.getElementById('seob-kwbold-preview');
				if (prevBtn) {
					prevBtn.addEventListener('click', function(){
						prevBtn.disabled = true;
						req('seob_kwbold_preview_post', getOpts(), function(d){
							prevBtn.disabled = false;
							if (d.success) {
								var r = d.data;
								if (r.occurrences > 0) {
									showStatus('Nalezeno ' + r.occurrences + '× "' + (r.keywords[0] || '') + '"', true);
								} else {
									showStatus('KW nenalezeno v obsahu.', false);
								}
							} else {
								showStatus(d.data && d.data.message ? d.data.message : 'Chyba.', false);
							}
						});
					});
				}

				var applyBtn = document.getElementById('seob-kwbold-apply');
				if (applyBtn) {
					applyBtn.addEventListener('click', function(){
						applyBtn.disabled = true;
						req('seob_kwbold_bold_post', getOpts(), function(d){
							applyBtn.disabled = false;
							if (d.success) {
								showStatus('✓ ' + d.data.message + ' Uložte příspěvek pro zobrazení.', true);
							} else {
								showStatus('✗ ' + (d.data && d.data.message ? d.data.message : 'Chyba.'), false);
							}
						});
					});
				}

				var undoBtn = document.getElementById('seob-kwbold-undo');
				if (undoBtn) {
					undoBtn.addEventListener('click', function(){
						if (!confirm('Odebrat zvýraznění klíčových slov?')) { return; }
						undoBtn.disabled = true;
						req('seob_kwbold_undo_post', {post_id: postId, nonce: nonce}, function(d){
							undoBtn.disabled = false;
							if (d.success) {
								showStatus('✓ Zvýraznění odebráno. Uložte příspěvek.', true);
								undoBtn.style.display = 'none';
							} else {
								showStatus('✗ ' + (d.data && d.data.message ? d.data.message : 'Chyba.'), false);
							}
						});
					});
				}
			}());
			</script>
		</div>
		<?php
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		// Žádné extra assety potřeba – JS je inline v render().
	}
}
