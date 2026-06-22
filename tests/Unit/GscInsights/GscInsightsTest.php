<?php

namespace SeoBoost\Tests\Unit\GscInsights;

use Brain\Monkey\Functions;
use ReflectionMethod;
use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/GscInsights/GscInsights.php';

final class GscInsightsTest extends TestCase {

	/**
	 * @dataProvider url_cases
	 */
	public function test_normalize_path_compares_urls_regardless_of_scheme_and_host( string $url, string $expected ): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$method = new ReflectionMethod( \SEOB_Gsc_Insights::class, 'normalize_path' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( null, $url ) );
	}

	public static function url_cases(): array {
		return [
			'https with trailing slash' => [ 'https://reboost-test.local/gdpr/', '/gdpr' ],
			'http without www'          => [ 'http://reboost-test.local/reference', '/reference' ],
			'https with www'            => [ 'https://www.reboost-test.local/reference/', '/reference' ],
			'homepage with slash'       => [ 'http://reboost-test.local/', '/' ],
			'homepage without slash'    => [ 'http://reboost-test.local', '/' ],
		];
	}
}
