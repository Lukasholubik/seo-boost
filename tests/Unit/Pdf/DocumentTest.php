<?php

namespace SeoBoost\Tests\Unit\Pdf;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once SEOB_PLUGIN_DIR . 'vendor/tcpdf/seob-tcpdf-loader.php';
require_once SEOB_PLUGIN_DIR . 'includes/Pdf/Document.php';

final class DocumentTest extends TestCase {

	/**
	 * @dataProvider hex_color_cases
	 */
	public function test_hex_to_rgb_converts_valid_hex_colors( string $hex, array $expected ): void {
		$method = new ReflectionMethod( \SEOB_Pdf_Document::class, 'hex_to_rgb' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( null, $hex ) );
	}

	public static function hex_color_cases(): array {
		return [
			'default accent color' => [ '#2271b1', [ 34, 113, 177 ] ],
			'black'                => [ '#000000', [ 0, 0, 0 ] ],
			'white'                => [ '#ffffff', [ 255, 255, 255 ] ],
		];
	}

	/**
	 * @dataProvider invalid_hex_color_cases
	 */
	public function test_hex_to_rgb_falls_back_to_default_for_invalid_input( string $hex ): void {
		$method = new ReflectionMethod( \SEOB_Pdf_Document::class, 'hex_to_rgb' );
		$method->setAccessible( true );

		$this->assertSame( [ 34, 113, 177 ], $method->invoke( null, $hex ) );
	}

	public static function invalid_hex_color_cases(): array {
		return [
			'too short'      => [ '#fff' ],
			'too long'       => [ '#2271b1aa' ],
			'not hex digits' => [ '#zzzzzz' ],
			'empty string'   => [ '' ],
		];
	}
}
