<?php

namespace SeoBoost\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Základní třída pro unit testy – kolem každého testu nastaví a uklidí
 * Brain Monkey (mockování WP funkcí jako wp_parse_url, get_option apod.).
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
