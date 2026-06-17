<?php

namespace SeoBoost\Tests\Unit\JsonLd;

use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/JsonLd/Validator.php';

final class ValidatorTest extends TestCase {

	// ── extract_schemas ───────────────────────────────────────────────────────

	public function test_extract_schemas_single_object(): void {
		$html    = '<script type="application/ld+json">{"@type":"Organization","name":"ACME"}</script>';
		$schemas = \SEOB_JsonLd_Validator::extract_schemas( $html );

		$this->assertCount( 1, $schemas );
		$this->assertSame( 'Organization', $schemas[0]['@type'] );
	}

	public function test_extract_schemas_graph(): void {
		$json = json_encode( [
			'@context' => 'https://schema.org',
			'@graph'   => [
				[ '@type' => 'WebSite',      'name' => 'Site' ],
				[ '@type' => 'Organization', 'name' => 'Org' ],
			],
		] );
		$html    = '<script type="application/ld+json">' . $json . '</script>';
		$schemas = \SEOB_JsonLd_Validator::extract_schemas( $html );

		$this->assertCount( 2, $schemas );
		$this->assertSame( 'WebSite',      $schemas[0]['@type'] );
		$this->assertSame( 'Organization', $schemas[1]['@type'] );
	}

	public function test_extract_schemas_invalid_json_returns_parse_error(): void {
		$html    = '<script type="application/ld+json">{ invalid json }</script>';
		$schemas = \SEOB_JsonLd_Validator::extract_schemas( $html );

		$this->assertCount( 1, $schemas );
		$this->assertArrayHasKey( '_parse_error', $schemas[0] );
	}

	public function test_extract_schemas_multiple_blocks(): void {
		$html = '<script type="application/ld+json">{"@type":"Article","headline":"A"}</script>'
			. '<script type="application/ld+json">{"@type":"BreadcrumbList","itemListElement":[]}</script>';

		$schemas = \SEOB_JsonLd_Validator::extract_schemas( $html );
		$this->assertCount( 2, $schemas );
	}

	public function test_extract_schemas_empty_html(): void {
		$schemas = \SEOB_JsonLd_Validator::extract_schemas( '<html><body>No schema here</body></html>' );
		$this->assertSame( [], $schemas );
	}

	// ── validate_schema ───────────────────────────────────────────────────────

	public function test_validate_schema_missing_type(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [ 'name' => 'Test' ] );
		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_validate_schema_article_without_headline(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@context' => 'https://schema.org',
			'@type'    => 'Article',
		] );

		$this->assertFalse( $result['valid'] );
		$found = array_filter( $result['errors'], static fn ( $e ) => str_contains( $e, 'headline' ) );
		$this->assertNotEmpty( $found );
	}

	public function test_validate_schema_article_valid(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@context' => 'https://schema.org',
			'@type'    => 'Article',
			'headline' => 'Testovaci clanek',
		] );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_validate_schema_organization_valid(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => 'Test s.r.o.',
		] );

		$this->assertTrue( $result['valid'] );
	}

	public function test_validate_schema_breadcrumb_empty_list(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => [],
		] );

		$this->assertFalse( $result['valid'] );
	}

	public function test_validate_schema_breadcrumb_valid(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => [
				[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://example.com/' ],
			],
		] );

		$this->assertTrue( $result['valid'] );
	}

	public function test_validate_schema_unknown_type_passes(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@context' => 'https://schema.org',
			'@type'    => 'SomeExoticType',
		] );

		// Unknown type: no required props known – treat as valid (warn only)
		$this->assertTrue( $result['valid'] );
	}

	public function test_validate_schema_parse_error(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'_parse_error' => 'Syntax error',
			'_raw_excerpt' => '{ bad',
		] );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'Syntax error', $result['errors'][0] );
	}

	public function test_validate_schema_missing_context_gives_warning(): void {
		$result = \SEOB_JsonLd_Validator::validate_schema( [
			'@type' => 'Organization',
			'name'  => 'Test',
		] );

		$this->assertTrue( $result['valid'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	// ── detect_duplicates ─────────────────────────────────────────────────────

	public function test_detect_duplicates_no_duplicates(): void {
		$schemas = [
			[ '@type' => 'Article',      'headline' => 'Test' ],
			[ '@type' => 'Organization', 'name'     => 'Firm' ],
		];

		$dupes = \SEOB_JsonLd_Validator::detect_duplicates( $schemas );
		$this->assertSame( [], $dupes );
	}

	public function test_detect_duplicates_exact_duplicate(): void {
		$schema  = [ '@type' => 'Organization', 'name' => 'ACME' ];
		$schemas = [ $schema, $schema ];

		$dupes = \SEOB_JsonLd_Validator::detect_duplicates( $schemas );
		$this->assertCount( 1, $dupes );
		$this->assertSame( 'Organization', $dupes[0]['type'] );
		$this->assertTrue( $dupes[0]['exact'] );
	}

	public function test_detect_duplicates_same_type_different_content(): void {
		$schemas = [
			[ '@type' => 'Article', 'headline' => 'Clanek A' ],
			[ '@type' => 'Article', 'headline' => 'Clanek B' ],
		];

		$dupes = \SEOB_JsonLd_Validator::detect_duplicates( $schemas );
		$this->assertCount( 1, $dupes );
		$this->assertSame( 'Article', $dupes[0]['type'] );
		$this->assertFalse( $dupes[0]['exact'] );
	}

	public function test_detect_duplicates_skips_parse_errors(): void {
		$schemas = [
			[ '_parse_error' => 'bad JSON' ],
			[ '_parse_error' => 'bad JSON' ],
			[ '@type' => 'Organization', 'name' => 'OK' ],
		];

		$dupes = \SEOB_JsonLd_Validator::detect_duplicates( $schemas );
		$this->assertSame( [], $dupes );
	}

	// ── self_test ─────────────────────────────────────────────────────────────

	public function test_self_test_returns_true(): void {
		$this->assertTrue( \SEOB_JsonLd_Validator::self_test() );
	}

	// ── short_type ────────────────────────────────────────────────────────────

	public function test_short_type_strips_url_prefix(): void {
		$this->assertSame( 'Article', \SEOB_JsonLd_Validator::short_type( 'https://schema.org/Article' ) );
	}

	public function test_short_type_returns_plain_type(): void {
		$this->assertSame( 'Organization', \SEOB_JsonLd_Validator::short_type( 'Organization' ) );
	}
}
