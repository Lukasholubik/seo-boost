<?php

namespace SeoBoost\Tests\Unit\LocalSeo;

use Brain\Monkey\Functions;
use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/LocalSeo/Frontend.php';

final class FrontendTest extends TestCase {

	// ── Conflict detection ─────────────────────────────────────────────────

	public function test_no_rank_math_local_seo_in_clean_env(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->assertFalse( \SEOB_LocalSeo_Frontend::has_rank_math_local_seo() );
	}

	public function test_rank_math_local_seo_detected_via_option(): void {
		Functions\when( 'get_option' )->justReturn( [ 'local-seo' ] );

		$this->assertTrue( \SEOB_LocalSeo_Frontend::has_rank_math_local_seo() );
	}

	// ── build_schema – minimální data ─────────────────────────────────────

	public function test_build_schema_minimal(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name' => 'ACME s.r.o.',
			'business_type' => 'LocalBusiness',
		] );

		$this->assertSame( 'https://schema.org', $schema['@context'] );
		$this->assertSame( 'LocalBusiness', $schema['@type'] );
		$this->assertSame( 'ACME s.r.o.', $schema['name'] );
		$this->assertSame( 'https://example.cz/', $schema['url'] );
		$this->assertArrayNotHasKey( 'telephone', $schema );
		$this->assertArrayNotHasKey( 'address', $schema );
		$this->assertArrayNotHasKey( 'geo', $schema );
	}

	// ── build_schema – plná adresa ─────────────────────────────────────────

	public function test_build_schema_with_address(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name'   => 'ACME',
			'business_type'   => 'Restaurant',
			'address_street'  => 'Hlavní 1',
			'address_city'    => 'Praha',
			'address_zip'     => '110 00',
			'address_country' => 'CZ',
		] );

		$this->assertArrayHasKey( 'address', $schema );
		$this->assertSame( 'PostalAddress', $schema['address']['@type'] );
		$this->assertSame( 'Hlavní 1', $schema['address']['streetAddress'] );
		$this->assertSame( 'Praha', $schema['address']['addressLocality'] );
		$this->assertSame( '110 00', $schema['address']['postalCode'] );
		$this->assertSame( 'CZ', $schema['address']['addressCountry'] );
	}

	// ── build_schema – GPS ────────────────────────────────────────────────

	public function test_build_schema_with_gps(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name' => 'ACME',
			'lat' => '50.075200',
			'lng' => '14.437800',
		] );

		$this->assertArrayHasKey( 'geo', $schema );
		$this->assertSame( 'GeoCoordinates', $schema['geo']['@type'] );
		$this->assertEqualsWithDelta( 50.0752, $schema['geo']['latitude'], 0.0001 );
		$this->assertEqualsWithDelta( 14.4378, $schema['geo']['longitude'], 0.0001 );
	}

	// ── build_schema – IČO / DIČ ─────────────────────────────────────────

	public function test_build_schema_with_ico_dic(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name' => 'ACME',
			'ico'           => '12345678',
			'dic'           => 'CZ12345678',
		] );

		$this->assertArrayHasKey( 'identifier', $schema );
		$this->assertCount( 2, $schema['identifier'] );

		$names = array_column( $schema['identifier'], 'name' );
		$this->assertContains( 'IČO', $names );
		$this->assertContains( 'DIČ', $names );

		$values = array_column( $schema['identifier'], 'value' );
		$this->assertContains( '12345678', $values );
		$this->assertContains( 'CZ12345678', $values );
	}

	// ── build_schema – otevírací doba ─────────────────────────────────────

	public function test_build_schema_opening_hours_excludes_closed_days(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name' => 'ACME',
			'opening_hours' => [
				'Mo' => [ 'open' => '09:00', 'close' => '17:00', 'closed' => 0 ],
				'Tu' => [ 'open' => '09:00', 'close' => '17:00', 'closed' => 0 ],
				'Sa' => [ 'open' => '',      'close' => '',      'closed' => 1 ],
				'Su' => [ 'open' => '',      'close' => '',      'closed' => 1 ],
			],
		] );

		$this->assertArrayHasKey( 'openingHoursSpecification', $schema );
		$this->assertCount( 2, $schema['openingHoursSpecification'] );

		$days = array_column( $schema['openingHoursSpecification'], 'dayOfWeek' );
		$this->assertContains( 'https://schema.org/Monday', $days );
		$this->assertContains( 'https://schema.org/Tuesday', $days );
		$this->assertNotContains( 'https://schema.org/Saturday', $days );
	}

	public function test_build_schema_skips_hours_row_with_empty_times(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name' => 'ACME',
			'opening_hours' => [
				'Mo' => [ 'open' => '', 'close' => '', 'closed' => 0 ],
				'Tu' => [ 'open' => '09:00', 'close' => '17:00', 'closed' => 0 ],
			],
		] );

		// Pondělí bez časů (closed=0 ale prázdné časy) se přeskočí
		$this->assertCount( 1, $schema['openingHoursSpecification'] );
		$this->assertSame( 'https://schema.org/Tuesday', $schema['openingHoursSpecification'][0]['dayOfWeek'] );
	}

	// ── build_schema – business_type výchozí hodnota ─────────────────────

	public function test_build_schema_defaults_type_to_local_business(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name' => 'ACME',
			'business_type' => '',
		] );

		$this->assertSame( 'LocalBusiness', $schema['@type'] );
	}

	// ── build_schema – address bez části polí ─────────────────────────────

	public function test_build_schema_partial_address_sets_default_country(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.cz/' );

		$schema = \SEOB_LocalSeo_Frontend::build_schema( [
			'business_name'   => 'ACME',
			'address_city'    => 'Brno',
			'address_country' => '',
		] );

		$this->assertSame( 'CZ', $schema['address']['addressCountry'] );
		$this->assertArrayNotHasKey( 'streetAddress', $schema['address'] );
	}

	// ── business_types() ──────────────────────────────────────────────────

	public function test_business_types_returns_non_empty_array(): void {
		$types = \SEOB_LocalSeo_Frontend::business_types();

		$this->assertIsArray( $types );
		$this->assertNotEmpty( $types );
		$this->assertArrayHasKey( 'LocalBusiness', $types );
		$this->assertArrayHasKey( 'Restaurant', $types );
		$this->assertArrayHasKey( 'Dentist', $types );
	}
}
