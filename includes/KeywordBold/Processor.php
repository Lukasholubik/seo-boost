<?php
/**
 * KeywordBold – jádro logiky zvýrazňování klíčových slov v obsahu.
 *
 * Obalí první N výskytů focus KW tagem <strong data-seob-bold="1">.
 * Přeskočí výskyty v nadpisech, odkazech, existujících <strong> a <em>.
 * Undo odstraní pouze tagy označené data-seob-bold="1".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_KeywordBold_Processor {

	/** Marker atribut pro identifikaci našich <strong> při undo. */
	const BOLD_ATTR = 'data-seob-bold="1"';

	/**
	 * Čte Focus KW z Rank Math meta.
	 * Vrátí pole klíčových slov (primary jako první).
	 *
	 * @return string[]
	 */
	public static function get_keywords( int $post_id, bool $include_secondary = true ): array {
		$raw = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );

		if ( '' === $raw ) {
			return [];
		}

		$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

		if ( ! $include_secondary ) {
			return array_slice( $parts, 0, 1 );
		}

		return array_values( $parts );
	}

	/**
	 * Provede náhled zvýraznění – vrátí info bez uložení do DB.
	 *
	 * @return array{keywords: string[], occurrences: int, already_bolded: bool, content_preview: string}
	 */
	public static function preview( int $post_id, array $options = [] ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [ 'keywords' => [], 'occurrences' => 0, 'already_bolded' => false, 'content_preview' => '' ];
		}

		$keywords        = ! empty( $options['keywords'] )
			? $options['keywords']
			: self::get_keywords( $post_id, $options['use_secondary'] ?? true );
		$max_occurrences = max( 1, min( 5, (int) ( $options['max_occurrences'] ?? 1 ) ) );

		if ( empty( $keywords ) ) {
			return [ 'keywords' => [], 'occurrences' => 0, 'already_bolded' => false, 'content_preview' => '' ];
		}

		$already = self::has_our_bold( $post->post_content );
		[ $new_content, $count ] = self::process_content( $post->post_content, $keywords, $max_occurrences );

		return [
			'keywords'        => $keywords,
			'occurrences'     => $count,
			'already_bolded'  => $already,
			'content_preview' => wp_trim_words( wp_strip_all_tags( $new_content ), 40 ),
		];
	}

	/**
	 * Aplikuje zvýraznění na jeden příspěvek a uloží.
	 *
	 * @return array{success: bool, occurrences: int, keywords: string[], message: string}
	 */
	public static function bold_post( int $post_id, array $options = [] ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [ 'success' => false, 'occurrences' => 0, 'keywords' => [], 'message' => 'Post nenalezen.' ];
		}

		$keywords        = ! empty( $options['keywords'] )
			? $options['keywords']
			: self::get_keywords( $post_id, $options['use_secondary'] ?? true );
		$max_occurrences = max( 1, min( 5, (int) ( $options['max_occurrences'] ?? 1 ) ) );
		$overwrite       = (bool) ( $options['overwrite'] ?? false );

		if ( empty( $keywords ) ) {
			return [ 'success' => false, 'occurrences' => 0, 'keywords' => [], 'message' => 'Žádné Focus KW nenalezeno (Rank Math).' ];
		}

		// Pokud již zvýrazněno a overwrite=false, přeskočit.
		if ( self::has_our_bold( $post->post_content ) && ! $overwrite ) {
			return [ 'success' => false, 'occurrences' => 0, 'keywords' => $keywords, 'message' => 'Již zvýrazněno. Použij přepsat.' ];
		}

		// Pokud overwrite, nejdřív odstraň staré zvýraznění.
		$content = $overwrite
			? self::remove_bold_from_content( $post->post_content )
			: $post->post_content;

		[ $new_content, $count ] = self::process_content( $content, $keywords, $max_occurrences );

		if ( $count === 0 ) {
			return [ 'success' => false, 'occurrences' => 0, 'keywords' => $keywords, 'message' => 'KW v obsahu nenalezeno.' ];
		}

		if ( $new_content === $content ) {
			return [ 'success' => false, 'occurrences' => 0, 'keywords' => $keywords, 'message' => 'Obsah se nezměnil.' ];
		}

		$result = wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ] );

		if ( is_wp_error( $result ) || $result === 0 ) {
			return [ 'success' => false, 'occurrences' => 0, 'keywords' => $keywords, 'message' => 'Chyba při uložení.' ];
		}

		update_post_meta( $post_id, '_seob_kw_bold_applied', wp_json_encode( [ 'keywords' => $keywords, 'count' => $count, 'ts' => time() ] ) );

		return [ 'success' => true, 'occurrences' => $count, 'keywords' => $keywords, 'message' => "Zvýrazněno $count×." ];
	}

	/**
	 * Odstraní naše zvýraznění z jednoho příspěvku.
	 */
	public static function undo_post( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! self::has_our_bold( $post->post_content ) ) {
			return true; // Nic k odstranění.
		}

		$clean = self::remove_bold_from_content( $post->post_content );

		$result = wp_update_post( [ 'ID' => $post_id, 'post_content' => $clean ] );

		if ( is_wp_error( $result ) || $result === 0 ) {
			return false;
		}

		delete_post_meta( $post_id, '_seob_kw_bold_applied' );
		return true;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Zpracuje post_content – najde KW a obalí <strong>.
	 * Pracuje na úrovni Gutenberg bloků (<!-- wp:paragraph -->, wp:list, wp:quote).
	 * Přeskočí bloky nadpisů (wp:heading) a HTML tagy.
	 *
	 * @return array{0: string, 1: int}  [new_content, total_count]
	 */
	public static function process_content( string $content, array $keywords, int $max_per_keyword = 1 ): array {
		$total_count = 0;

		// Sleduj kolikrát bylo každé KW již použito PŘES VŠECHNY BLOKY.
		// Klíč = kw, hodnota = počet použití. Sdíleno přes &reference.
		$kw_used = array_fill_keys( $keywords, 0 );

		// Zpracuj každý blok zvlášť – zachová Gutenberg strukturu.
		// KRITICKÉ: preg_replace_callback může vrátit null při PCRE chybě.
		// Null nesmí projít přes (string) cast – (string)null = "" → wp_update_post("") smaže obsah.
		$raw_result  = preg_replace_callback(
			'/(<!-- wp:(?!heading)[^\s>]*[^>]* -->)([\s\S]*?)(<!-- \/wp:(?!heading)[^\s>]*[^>]* -->)/i',
			static function ( array $m ) use ( $keywords, $max_per_keyword, &$total_count, &$kw_used ): string {
				$open = $m[1];
				$body = $m[2];
				$close = $m[3];

				foreach ( $keywords as $kw ) {
					if ( '' === trim( $kw ) ) {
						continue;
					}
					// Kolik výskytů zbývá pro toto KW v tomto a dalších blocích.
					$remaining = $max_per_keyword - ( $kw_used[ $kw ] ?? 0 );
					if ( $remaining <= 0 ) {
						continue;
					}
					[ $body, $count ] = SEOB_KeywordBold_Processor::bold_in_html( $body, $kw, $remaining );
					$kw_used[ $kw ] = ( $kw_used[ $kw ] ?? 0 ) + $count;
					$total_count    += $count;
				}

				return $open . $body . $close;
			},
			$content
		);

		// Null = PCRE selhání → vrátíme původní content, nezapisujeme nic.
		if ( null === $raw_result ) {
			return [ $content, 0 ];
		}
		$new_content = $raw_result;

		// Fallback pro classic editor (žádné block komentáře).
		if ( $total_count === 0 && strpos( $content, '<!-- wp:' ) === false ) {
			foreach ( $keywords as $kw ) {
				[ $modified, $count ] = self::bold_in_html( $content, $kw, $max_per_keyword );
				if ( $count > 0 ) {
					$new_content  = $modified;
					$total_count += $count;
				}
			}
		}

		return [ $new_content, $total_count ];
	}

	/**
	 * Obalí první $max výskytů $keyword v HTML fragmentu <strong data-seob-bold="1">.
	 *
	 * Funguje tak, že rozseká HTML na části: HTML tagy a text nody.
	 * KW hledá POUZE v textových částech – ne uvnitř tagů, atributů ani
	 * zakázaných elementů (h1-6, a, em, strong).
	 *
	 * @return array{0: string, 1: int}  [new_html, count]
	 */
	public static function bold_in_html( string $html, string $keyword, int $max = 1 ): array {
		$count  = 0;
		$kw_esc = preg_quote( $keyword, '/' );

		// Zakázané tagy – uvnitř nich keyword neobalíme.
		$forbidden_open  = [];
		$forbidden_depth = 0;

		// Rozdělení na HTML tagy a text nody.
		// Každý sudý prvek je text node, každý lichý je HTML tag (nebo komentář).
		$parts = preg_split( '/(<[^>]+>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return [ $html, 0 ];
		}

		$result = '';

		foreach ( $parts as $part ) {
			if ( strncmp( $part, '<', 1 ) === 0 ) {
				// Je to HTML tag – sleduj kontext zakázaných elementů.
				if ( preg_match( '/^<(h[1-6]|a|em|strong)(?:\s|\/|>)/i', $part, $tm ) ) {
					$forbidden_depth++;
				} elseif ( preg_match( '/^<\/(h[1-6]|a|em|strong)>/i', $part ) ) {
					if ( $forbidden_depth > 0 ) {
						$forbidden_depth--;
					}
				}
				$result .= $part;
			} else {
				// Je to text node – hledej KW jen pokud nejsme uvnitř zakázaného tagu.
				if ( $forbidden_depth > 0 || $count >= $max ) {
					$result .= $part;
				} else {
					$new_part = (string) preg_replace_callback(
						'/(' . $kw_esc . ')/iu',
						static function ( array $m ) use ( &$count, $max ): string {
							if ( $count >= $max ) {
								return $m[0];
							}
							$count++;
							return '<strong ' . SEOB_KeywordBold_Processor::BOLD_ATTR . '>' . $m[0] . '</strong>';
						},
						$part
					);
					$result .= $new_part;
				}
			}
		}

		return [ $result, $count ];
	}

	/**
	 * Vrátí true pokud obsah obsahuje naše zvýraznění.
	 */
	public static function has_our_bold( string $content ): bool {
		return strpos( $content, self::BOLD_ATTR ) !== false;
	}

	/**
	 * Odstraní všechny naše <strong data-seob-bold="1"> tagy z obsahu.
	 */
	public static function remove_bold_from_content( string $content ): string {
		return (string) preg_replace(
			'/<strong\s+' . preg_quote( self::BOLD_ATTR, '/' ) . '>(.*?)<\/strong>/is',
			'$1',
			$content
		);
	}
}
