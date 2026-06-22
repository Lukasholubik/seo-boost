<?php

namespace SeoBoost\Tests\Unit\Hreflang;

use Brain\Monkey\Functions;
use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/Database/Database.php';
require_once SEOB_PLUGIN_DIR . 'includes/Hreflang/Manager.php';

final class ManagerTest extends TestCase {

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Vytvoří jednoduchý mock $wpdb který vrátí null group_id (post v žádné skupině).
	 */
	private function mockWpdbNoGroup(): object {
		return new class {
			public string $prefix = 'wp_';

			public function prepare( string $sql, ...$args ): string {
				return $sql;
			}

			public function get_var( string $sql ): ?string {
				return null;
			}

			public function get_results( string $sql, string $output = '' ): array {
				return [];
			}
		};
	}

	/**
	 * Vytvoří mock $wpdb který vrátí zadané members pro libovolný group_id.
	 */
	private function mockWpdbWithMembers( array $members ): object {
		return new class( $members ) {
			public string $prefix = 'wp_';
			private array $members;

			public function __construct( array $members ) {
				$this->members = $members;
			}

			public function prepare( string $sql, ...$args ): string {
				return $sql;
			}

			public function get_var( string $sql ): string {
				return '42';
			}

			public function get_results( string $sql, string $output = '' ): array {
				return $this->members;
			}
		};
	}

	// ── Conflict detection ────────────────────────────────────────────────────

	public function test_no_conflict_in_clean_test_environment(): void {
		// Ani RankMathPro ani WPSEO_Premium nejsou v testovacím prostředí definovány.
		$this->assertFalse( \SEOB_Hreflang_Manager::has_conflict() );
	}

	// ── Multilingual detection ────────────────────────────────────────────────

	public function test_not_multilingual_in_clean_test_environment(): void {
		$result = \SEOB_Hreflang_Manager::detect_multilingual();

		$this->assertFalse( $result['active'] );
		$this->assertSame( '', $result['plugin'] );
	}

	// ── get_tags_for_post ─────────────────────────────────────────────────────

	public function test_get_tags_returns_empty_array_when_post_has_no_group(): void {
		global $wpdb;
		$wpdb = $this->mockWpdbNoGroup();

		$tags = \SEOB_Hreflang_Manager::get_tags_for_post( 999 );

		$this->assertSame( [], $tags );
	}

	public function test_get_tags_returns_one_tag_per_member(): void {
		global $wpdb;
		$wpdb = $this->mockWpdbWithMembers( [
			[ 'page_id' => '10', 'locale' => 'cs', 'is_x_default' => '0' ],
			[ 'page_id' => '11', 'locale' => 'en', 'is_x_default' => '0' ],
		] );

		Functions\when( 'get_permalink' )->alias( function ( int $id ): string {
			return $id === 10 ? 'https://example.com/' : 'https://example.com/en/';
		} );

		$tags = \SEOB_Hreflang_Manager::get_tags_for_post( 10 );

		$this->assertCount( 2, $tags );
		$this->assertSame( 'cs', $tags[0]['hreflang'] );
		$this->assertSame( 'https://example.com/', $tags[0]['url'] );
		$this->assertSame( 'en', $tags[1]['hreflang'] );
		$this->assertSame( 'https://example.com/en/', $tags[1]['url'] );
	}

	public function test_get_tags_adds_x_default_after_marked_member(): void {
		global $wpdb;
		$wpdb = $this->mockWpdbWithMembers( [
			[ 'page_id' => '10', 'locale' => 'cs', 'is_x_default' => '1' ],
			[ 'page_id' => '11', 'locale' => 'en', 'is_x_default' => '0' ],
		] );

		Functions\when( 'get_permalink' )->alias( function ( int $id ): string {
			return $id === 10 ? 'https://example.com/' : 'https://example.com/en/';
		} );

		$tags = \SEOB_Hreflang_Manager::get_tags_for_post( 10 );

		// 2 members + 1 x-default = 3 tagů
		$this->assertCount( 3, $tags );

		$hreflangs = array_column( $tags, 'hreflang' );
		$this->assertContains( 'cs', $hreflangs );
		$this->assertContains( 'x-default', $hreflangs );
		$this->assertContains( 'en', $hreflangs );
	}

	public function test_x_default_url_matches_marked_member_url(): void {
		global $wpdb;
		$wpdb = $this->mockWpdbWithMembers( [
			[ 'page_id' => '10', 'locale' => 'cs', 'is_x_default' => '0' ],
			[ 'page_id' => '11', 'locale' => 'en', 'is_x_default' => '1' ],
		] );

		Functions\when( 'get_permalink' )->alias( function ( int $id ): string {
			return $id === 10 ? 'https://example.com/' : 'https://example.com/en/';
		} );

		$tags = \SEOB_Hreflang_Manager::get_tags_for_post( 11 );

		$x_default = array_filter( $tags, fn( $t ) => $t['hreflang'] === 'x-default' );
		$x_default = array_values( $x_default );

		$this->assertCount( 1, $x_default );
		$this->assertSame( 'https://example.com/en/', $x_default[0]['url'] );
	}

	public function test_get_tags_skips_member_with_no_permalink(): void {
		global $wpdb;
		$wpdb = $this->mockWpdbWithMembers( [
			[ 'page_id' => '10', 'locale' => 'cs', 'is_x_default' => '0' ],
			[ 'page_id' => '99', 'locale' => 'en', 'is_x_default' => '0' ],
		] );

		Functions\when( 'get_permalink' )->alias( function ( int $id ) {
			return $id === 99 ? false : 'https://example.com/';
		} );

		$tags = \SEOB_Hreflang_Manager::get_tags_for_post( 10 );

		// Stránka ID 99 nemá permalink → přeskočena
		$this->assertCount( 1, $tags );
		$this->assertSame( 'cs', $tags[0]['hreflang'] );
	}

	public function test_get_tags_with_three_languages(): void {
		global $wpdb;
		$wpdb = $this->mockWpdbWithMembers( [
			[ 'page_id' => '1', 'locale' => 'cs', 'is_x_default' => '0' ],
			[ 'page_id' => '2', 'locale' => 'en', 'is_x_default' => '1' ],
			[ 'page_id' => '3', 'locale' => 'de', 'is_x_default' => '0' ],
		] );

		Functions\when( 'get_permalink' )->alias( function ( int $id ): string {
			return match( $id ) {
				1 => 'https://example.com/',
				2 => 'https://example.com/en/',
				3 => 'https://example.com/de/',
				default => ''
			};
		} );

		$tags = \SEOB_Hreflang_Manager::get_tags_for_post( 1 );

		// 3 jazyky + 1 x-default = 4 tagy
		$this->assertCount( 4, $tags );

		$hreflangs = array_column( $tags, 'hreflang' );
		$this->assertContains( 'cs', $hreflangs );
		$this->assertContains( 'en', $hreflangs );
		$this->assertContains( 'de', $hreflangs );
		$this->assertContains( 'x-default', $hreflangs );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		parent::tearDown();
	}
}
