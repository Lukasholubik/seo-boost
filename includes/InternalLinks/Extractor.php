<?php
/**
 * Extrahuje interní odkazy a textový obsah příspěvku pro modul Interní prolinkování.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_Extractor {

	private const IGNORED_SCHEMES = [ 'mailto', 'tel', 'javascript' ];

	/**
	 * Vrátí interní odkazy nalezené v obsahu příspěvku (bez odkazů na sebe sama).
	 *
	 * @return array<int,array{target_id:int,link_text:string}>
	 */
	public function extract_links( WP_Post $post ): array {
		$html  = $this->get_content_html( $post );
		$found = self::extract_internal_links_from_html( $html, home_url() );

		$links = [];

		foreach ( $found as $item ) {
			$url = $item['url'];

			if ( ! preg_match( '#^https?://#i', $url ) ) {
				$url = home_url( $url );
			}

			$target_id = url_to_postid( $url );

			if ( $target_id <= 0 || $target_id === $post->ID ) {
				continue;
			}

			$links[] = [
				'target_id' => $target_id,
				'link_text' => $item['link_text'],
			];
		}

		return $links;
	}

	/**
	 * Vrátí čistý text příspěvku (titulek + obsah) pro výpočet podobnosti.
	 */
	public function extract_text( WP_Post $post ): string {
		$title = get_the_title( $post );
		$html  = $this->get_content_html( $post );
		$text  = wp_strip_all_tags( strip_shortcodes( $html ) );

		return trim( $title . ' ' . $text );
	}

	/**
	 * Vrátí HTML obsah příspěvku – z Elementor dat, post_content, nebo meta polí (fallback).
	 */
	private function get_content_html( WP_Post $post ): string {
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );

		if ( ! empty( $elementor_data ) ) {
			$decoded = json_decode( (string) $elementor_data, true );

			if ( is_array( $decoded ) ) {
				return self::elementor_to_html( $decoded );
			}
		}

		if ( '' !== trim( (string) $post->post_content ) ) {
			return $post->post_content;
		}

		return $this->collect_meta_content( $post );
	}

	/**
	 * Projde strom Elementor widgetů a poskládá z textových polí a odkazů náhradní HTML.
	 */
	public static function elementor_to_html( array $elements ): string {
		$html = '';

		$walk = function ( array $elements ) use ( &$walk, &$html ) {
			foreach ( $elements as $element ) {
				$widget_type = $element['widgetType'] ?? '';
				$settings    = $element['settings'] ?? [];

				if ( in_array( $widget_type, [ 'text-editor', 'heading', 'icon-box', 'button' ], true ) ) {
					$title  = (string) ( $settings['title'] ?? '' );
					$editor = (string) ( $settings['editor'] ?? '' );

					$html .= $editor . ' ' . $title . ' ' . (string) ( $settings['description'] ?? '' );

					$link_url = $settings['link']['url'] ?? '';

					if ( '' !== trim( (string) $link_url ) ) {
						$text  = '' !== $title ? $title : $editor;
						$html .= ' <a href="' . htmlspecialchars( (string) $link_url, ENT_QUOTES ) . '">' . $text . '</a>';
					}
				}

				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$walk( $element['elements'] );
				}
			}
		};

		$walk( $elements );

		return $html;
	}

	/**
	 * Najde v HTML odkazy směřující na vlastní web (relativní URL nebo stejná domain jako $home_url).
	 * Čistá funkce – testovatelná bez WordPressu.
	 *
	 * @return array<int,array{url:string,link_text:string}>
	 */
	public static function extract_internal_links_from_html( string $html, string $home_url ): array {
		if ( '' === trim( $html ) ) {
			return [];
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		$home_host = parse_url( $home_url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$links = [];

		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			$href = trim( $a->getAttribute( 'href' ) );

			if ( '' === $href || str_starts_with( $href, '#' ) ) {
				continue;
			}

			$parts = parse_url( $href ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			if ( false === $parts ) {
				continue;
			}

			if ( isset( $parts['scheme'] ) && in_array( strtolower( $parts['scheme'] ), self::IGNORED_SCHEMES, true ) ) {
				continue;
			}

			if ( isset( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) $home_host ) ) {
				continue;
			}

			$text = trim( preg_replace( '/\s+/u', ' ', $a->textContent ) ?? '' );

			$links[] = [
				'url'       => $href,
				'link_text' => $text,
			];
		}

		return $links;
	}

	/**
	 * Poskládá náhradní HTML z textových meta polí příspěvku – pro custom post typy,
	 * které ukládají obsah do vlastních meta polí (např. JetEngine), ne do `post_content`.
	 */
	private function collect_meta_content( WP_Post $post ): string {
		$meta  = get_post_meta( $post->ID );
		$parts = [];

		foreach ( $meta as $key => $values ) {
			if ( '_' === $key[0] || 0 === strpos( $key, 'rank_math_' ) ) {
				continue;
			}

			foreach ( $values as $value ) {
				if ( is_string( $value ) && ! is_numeric( $value ) && '' !== trim( wp_strip_all_tags( $value ) ) ) {
					$parts[] = $value;
				}
			}
		}

		return implode( ' ', $parts );
	}
}
