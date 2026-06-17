<?php

namespace SeoBoost\Tests\Unit\InternalLinks;

use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/InternalLinks/Extractor.php';

final class ExtractorTest extends TestCase {

	private const HOME_URL = 'https://example.com';

	public function test_extracts_relative_internal_link(): void {
		$html = '<p>Viz <a href="/clanky/jak-na-seo/">návod na SEO</a>.</p>';

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );

		$this->assertCount( 1, $links );
		$this->assertSame( '/clanky/jak-na-seo/', $links[0]['url'] );
		$this->assertSame( 'návod na SEO', $links[0]['link_text'] );
	}

	public function test_extracts_absolute_internal_link_on_same_host(): void {
		$html = '<p><a href="https://example.com/o-nas/">o nás</a></p>';

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );

		$this->assertCount( 1, $links );
		$this->assertSame( 'https://example.com/o-nas/', $links[0]['url'] );
	}

	public function test_ignores_external_link(): void {
		$html = '<p><a href="https://jiny-web.cz/clanek">externí</a></p>';

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );

		$this->assertCount( 0, $links );
	}

	public function test_ignores_mailto_tel_and_anchor_links(): void {
		$html = '<p>'
			. '<a href="mailto:info@example.com">email</a>'
			. '<a href="tel:+420123456789">telefon</a>'
			. '<a href="#sekce">kotva</a>'
			. '</p>';

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );

		$this->assertCount( 0, $links );
	}

	public function test_ignores_empty_href(): void {
		$html = '<p><a href="">prázdný</a></p>';

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );

		$this->assertCount( 0, $links );
	}

	public function test_returns_empty_array_for_empty_html(): void {
		$this->assertSame( [], \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( '', self::HOME_URL ) );
		$this->assertSame( [], \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( '   ', self::HOME_URL ) );
	}

	public function test_collects_multiple_internal_links_with_normalized_text(): void {
		$html = '<p><a href="/a/">  první   odkaz  </a> a <a href="/b/">druhý</a></p>';

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );

		$this->assertCount( 2, $links );
		$this->assertSame( 'první odkaz', $links[0]['link_text'] );
		$this->assertSame( '/b/', $links[1]['url'] );
	}

	public function test_elementor_to_html_collects_text_and_link(): void {
		$elements = [
			[
				'widgetType' => 'heading',
				'settings'   => [
					'title' => 'Nadpis',
					'link'  => [ 'url' => '/cilova-stranka/' ],
				],
			],
			[
				'widgetType' => 'text-editor',
				'settings'   => [
					'editor' => '<p>Nějaký text.</p>',
				],
				'elements'   => [
					[
						'widgetType' => 'button',
						'settings'   => [
							'title' => 'Tlačítko',
							'link'  => [ 'url' => '/dalsi-stranka/' ],
						],
					],
				],
			],
		];

		$html = \SEOB_InternalLinks_Extractor::elementor_to_html( $elements );

		$this->assertStringContainsString( 'Nadpis', $html );
		$this->assertStringContainsString( 'Nějaký text', $html );
		$this->assertStringContainsString( 'href="/cilova-stranka/"', $html );
		$this->assertStringContainsString( 'href="/dalsi-stranka/"', $html );

		$links = \SEOB_InternalLinks_Extractor::extract_internal_links_from_html( $html, self::HOME_URL );
		$urls  = array_column( $links, 'url' );

		$this->assertContains( '/cilova-stranka/', $urls );
		$this->assertContains( '/dalsi-stranka/', $urls );
	}
}
