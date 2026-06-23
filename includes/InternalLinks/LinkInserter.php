<?php
/**
 * Vkládá interní odkazů do obsahu příspěvku na základě nalezených orphan stránek.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_LinkInserter {

	private int $max_links;

	/** Post typy, které Elementor/JetEngine používá jako builder – vyloučíme z kandidátů. */
	private const EXCLUDED_TYPES = [
		'attachment', 'elementor_library', 'jet-popup', 'jet-theme-core',
		'e-floating-buttons', 'revision', 'nav_menu_item', 'custom_css',
		'customize_changeset', 'user_request', 'oembed_cache',
	];

	public function __construct( int $max_links = 3 ) {
		$this->max_links = $max_links;
	}

	/**
	 * Zjistí, zda příspěvek používá Elementor builder (post_content je serializovaný JSON).
	 */
	public function is_elementor( int $post_id ): bool {
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * Najde orphan stránky, jejichž titulek se vyskytuje v obsahu příspěvku.
	 * Vrátí max. $max kandidátů ve formátu [{id, title, url}].
	 *
	 * @return array<array{id:int,title:string,url:string}>
	 */
	public function get_candidates( WP_Post $post, int $max = 20 ): array {
		global $wpdb;

		$content = $post->post_content;
		if ( empty( trim( $content ) ) ) {
			return [];
		}

		// Čistý text pro vyhledávání (bez HTML tagů)
		$plain_content = wp_strip_all_tags( $content );

		$links_table = SEOB_Database::internal_links_table();
		$excluded_in = implode( ',', array_map( fn( $t ) => "'" . esc_sql( $t ) . "'", self::EXCLUDED_TYPES ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$orphans = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title
				 FROM {$wpdb->posts} p
				 WHERE p.post_status = 'publish'
				   AND p.ID != %d
				   AND p.post_type NOT IN ({$excluded_in})
				   AND NOT EXISTS (
				       SELECT 1 FROM {$links_table} l WHERE l.target_id = p.ID
				   )
				 ORDER BY p.post_title ASC
				 LIMIT 300",
				$post->ID
			)
		);
		// phpcs:enable

		$candidates = [];
		foreach ( $orphans as $orphan ) {
			$title = trim( $orphan->post_title );
			if ( empty( $title ) || mb_strlen( $title ) < 3 ) {
				continue;
			}

			if ( false !== mb_stripos( $plain_content, $title ) ) {
				$candidates[] = [
					'id'    => (int) $orphan->ID,
					'title' => $title,
					'url'   => (string) get_permalink( $orphan->ID ),
				];
			}

			if ( count( $candidates ) >= $max ) {
				break;
			}
		}

		return $candidates;
	}

	/**
	 * Vrátí kandidáty s kontextovým výňatkem a informací, zda existují další.
	 * Používá se pro UI – uživatel uvidí kontext a rozhodne, které linky vložit.
	 *
	 * @return array{candidates:array<array{id:int,title:string,url:string,context:string}>,has_more:bool}
	 */
	public function find_with_context( WP_Post $post, int $max = 10, int $offset = 0 ): array {
		// Načteme o 1 víc než potřebujeme – pro detekci has_more
		$all      = $this->get_candidates( $post, $offset + $max + 1 );
		$has_more = count( $all ) > $offset + $max;
		$page     = array_slice( $all, $offset, $max );
		$plain    = wp_strip_all_tags( $post->post_content );

		$candidates = array_map(
			function ( $c ) use ( $plain ) {
				$c['context'] = $this->get_context_snippet( $plain, $c['title'] );
				return $c;
			},
			$page
		);

		return [ 'candidates' => $candidates, 'has_more' => $has_more ];
	}

	/**
	 * Vloží interní odkazů do příspěvku a uloží ho přes wp_update_post.
	 *
	 * @param  int[]   $only_ids  Filtr – vložit jen tato target ID (výběr uživatele). Prázdné = dle $max_links.
	 * @param  array{new_window?:bool,nofollow?:bool}  $options  Atributy vložených odkazů.
	 * @return array{inserted:list<array{id:int,title:string,url:string}>,skipped:list<array{id:int,title:string,url:string}>,message:string,new_content:string|null}|array{error:string}
	 */
	public function insert( int $post_id, array $only_ids = [], array $options = [] ): array {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return [ 'error' => __( 'Nemáte oprávnění upravit tento příspěvek.', 'seo-boost' ) ];
		}

		$candidates = $this->get_candidates( $post, 100 );

		// Filtr podle výběru uživatele
		if ( ! empty( $only_ids ) ) {
			$candidates = array_values(
				array_filter( $candidates, fn( $c ) => in_array( $c['id'], $only_ids, true ) )
			);
		}

		// Při manuálním výběru: vložit všechny zaškrtnuté, ne jen 3.
		$max_insert = ! empty( $only_ids ) ? count( $only_ids ) : $this->max_links;

		[ 'content' => $new_content, 'inserted' => $inserted ] = $this->inject_links( $post->post_content, $candidates, $options, $max_insert );

		if ( ! empty( $inserted ) ) {
			wp_update_post( [
				'ID'           => $post_id,
				'post_content' => $new_content,
			] );
		}

		$inserted_ids = array_column( $inserted, 'id' );
		$skipped      = array_values(
			array_filter( $candidates, fn( $c ) => ! in_array( $c['id'], $inserted_ids, true ) )
		);

		$message = empty( $inserted )
			? __( 'Žádné výrazy nebyly nalezeny v textu (nebo jsou již prolinkované).', 'seo-boost' )
			: sprintf(
				/* translators: %d: počet vložených odkazů */
				_n( 'Vložen %d odkaz.', 'Vloženo %d odkazů.', count( $inserted ), 'seo-boost' ),
				count( $inserted )
			);

		return [
			'inserted'    => $inserted,
			'skipped'     => $skipped,
			'message'     => $message,
			// Vrátíme nový obsah, aby JS mohl synchronizovat stav editoru (Gutenberg/Classic)
			// a zabránil přepsání změn při dalším uložení.
			'new_content' => ! empty( $inserted ) ? $new_content : null,
		];
	}

	/**
	 * Vrátí krátký kontextový výňatek kolem prvního výskytu $title v $plain_text.
	 */
	private function get_context_snippet( string $plain_text, string $title, int $ctx = 55 ): string {
		$pos = mb_stripos( $plain_text, $title );
		if ( false === $pos ) {
			return '';
		}

		$start   = max( 0, $pos - $ctx );
		$len     = ( $pos - $start ) + mb_strlen( $title ) + $ctx;
		$snippet = mb_substr( $plain_text, $start, $len );

		if ( $start > 0 ) {
			$snippet = '…' . $snippet;
		}
		if ( ( $start + $len ) < mb_strlen( $plain_text ) ) {
			$snippet .= '…';
		}

		return $snippet;
	}

	/**
	 * Vloží odkazů do HTML obsahu. Chrání existující <a> tagy a nadpisy.
	 *
	 * @param  array<array{id:int,title:string,url:string}> $candidates
	 * @param  array{new_window?:bool,nofollow?:bool}       $options      Atributy odkazů.
	 * @param  int                                          $max_override  -1 = použít $this->max_links.
	 * @return array{content:string,inserted:list<array{id:int,title:string,url:string}>}
	 */
	public function inject_links( string $content, array $candidates, array $options = [], int $max_override = -1 ): array {
		$limit      = $max_override >= 0 ? $max_override : $this->max_links;
		$new_window = ! empty( $options['new_window'] );
		$nofollow   = ! empty( $options['nofollow'] );

		$target_attr = $new_window ? ' target="_blank"' : '';
		$rel_parts   = [];
		if ( $nofollow )   { $rel_parts[] = 'nofollow'; }
		if ( $new_window ) { $rel_parts[] = 'noopener'; }
		$rel_attr = ! empty( $rel_parts ) ? ' rel="' . implode( ' ', $rel_parts ) . '"' : '';

		$inserted = [];
		$count    = 0;

		// Zpracujeme kandidáty postupně – každý snižuje zbývající limit.
		foreach ( $candidates as $c ) {
			if ( $count >= $limit ) {
				break;
			}

			$url_escaped   = esc_url( $c['url'] );
			$title_pattern = preg_quote( $c['title'], '/' );
			$inserted_flag = false;

			$content = $this->inject_single_link(
				$content,
				$title_pattern,
				$url_escaped,
				$target_attr,
				$rel_attr,
				$inserted_flag
			);

			if ( $inserted_flag ) {
				$inserted[] = $c;
				$count++;
			}
		}

		return [ 'content' => $content, 'inserted' => $inserted ];
	}

	/**
	 * Vloží jeden odkaz do obsahu, přičemž keyword hledá POUZE v textových uzlech –
	 * nikdy ne uvnitř HTML tagů nebo atributů (ochrana před vkládáním do href hodnot).
	 *
	 * Funguje: HTML se rozřeže na tagy a text části, keyword se nahradí jen v textu,
	 * přičemž se sleduje hloubka zakázaných elementů (<a>, <h1-6>, <strong>, <em>).
	 */
	private function inject_single_link(
		string $content,
		string $title_pattern,
		string $url,
		string $target_attr,
		string $rel_attr,
		bool  &$inserted_flag
	): string {
		// Splituje HTML na tagy, komentáře a text uzly.
		// Pattern: komentáře (<!-- ... -->) | tagy (<...>) | text
		// Uvozovky v atributech chrání před falešným splitem na > uvnitř hodnot.
		$parts = preg_split(
			'/(<!--[\s\S]*?-->|<(?:[^>"\']*|"[^"]*"|\'[^\']*\')*>)/i',
			$content,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( ! is_array( $parts ) ) {
			return $content;
		}

		// Zakázané elementy – v jejich obsahu keyword nenahrazujeme.
		// Zahrnuje <script> a <style> (ochrana JSON-LD a CSS) i <a>, <h1-6>.
		static $forbidden_open  = '/^<(script|style|a|h[1-6])\b/i';
		static $forbidden_close = '/^<\/(script|style|a|h[1-6])\s*>/i';

		$result          = '';
		$forbidden_depth = 0;
		$inserted_flag   = false;

		foreach ( $parts as $part ) {
			// Komentáře a HTML tagy – nikdy do nich nevkládáme keyword.
			if ( strncmp( $part, '<', 1 ) === 0 ) {
				if ( preg_match( $forbidden_open, $part ) ) {
					$forbidden_depth++;
				} elseif ( preg_match( $forbidden_close, $part ) ) {
					if ( $forbidden_depth > 0 ) {
						$forbidden_depth--;
					}
				}
				$result .= $part;
			} else {
				// Text uzel – hledej keyword jen pokud nejsme uvnitř zakázaného elementu.
				if ( $forbidden_depth > 0 || $inserted_flag ) {
					$result .= $part;
				} else {
					$new_part = preg_replace_callback(
						'/(' . $title_pattern . ')/iu',
						function ( array $m ) use ( $url, $target_attr, $rel_attr, &$inserted_flag ): string {
							if ( $inserted_flag ) {
								return $m[0];
							}
							$inserted_flag = true;
							// $url already escaped via esc_url(); title text is from a text node (not attribute).
							return '<a href="' . $url . '"' . $target_attr . $rel_attr . '>' . esc_html( $m[1] ) . '</a>';
						},
						$part
					);
					$result .= ( null !== $new_part ) ? $new_part : $part;
				}
			}
		}

		return $result;
	}
}
