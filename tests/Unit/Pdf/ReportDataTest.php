<?php

namespace SeoBoost\Tests\Unit\Pdf;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once SEOB_PLUGIN_DIR . 'includes/Pdf/ReportData.php';

final class ReportDataTest extends TestCase {

	public function test_issue_labels_contains_known_issue_types(): void {
		$labels = \SEOB_Pdf_Report_Data::issue_labels();

		$this->assertArrayHasKey( 'title_missing', $labels );
		$this->assertArrayHasKey( 'description_missing', $labels );
		$this->assertSame( 'Chybí meta description', $labels['description_missing'] );
	}

	/**
	 * @dataProvider logo_dimension_cases
	 */
	public function test_logo_dimensions_mm_never_upscales_beyond_native_size( string $fixture, float $max_w, float $max_h, array $expected ): void {
		$method = new ReflectionMethod( \SEOB_Pdf_Report_Data::class, 'logo_dimensions_mm' );
		$method->setAccessible( true );

		$result = $method->invoke( null, __DIR__ . '/../../fixtures/' . $fixture, $max_w, $max_h );

		$this->assertEqualsWithDelta( $expected[0], $result[0], 0.05 );
		$this->assertEqualsWithDelta( $expected[1], $result[1], 0.05 );
	}

	public static function logo_dimension_cases(): array {
		return [
			'small logo fits inside max box without upscaling' => [ 'logo-188x60.png', 50.0, 14.0, [ 31.8, 10.2 ] ],
			'small logo capped by narrower max box'             => [ 'logo-188x60.png', 20.0, 18.0, [ 20.0, 6.4 ] ],
			'large logo scaled down to fit max box'             => [ 'logo-1000x300.png', 35.0, 18.0, [ 35.0, 10.5 ] ],
		];
	}

	public function test_logo_dimensions_mm_falls_back_to_max_box_when_file_missing(): void {
		$method = new ReflectionMethod( \SEOB_Pdf_Report_Data::class, 'logo_dimensions_mm' );
		$method->setAccessible( true );

		$result = $method->invoke( null, __DIR__ . '/does-not-exist.png', 35.0, 18.0 );

		$this->assertSame( [ 35.0, 18.0 ], $result );
	}
}
