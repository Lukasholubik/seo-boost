<?php

namespace SeoBoost\Tests\Unit\PageSpeed;

use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/PageSpeed/Client.php';

final class ClientTest extends TestCase {

	public function test_parse_response_converts_category_scores_to_0_100(): void {
		$result = \SEOB_PageSpeed_Client::parse_response( $this->fixture() );

		$this->assertSame( 87, $result['performance_score'] );
		$this->assertSame( 91, $result['accessibility_score'] );
		$this->assertSame( 96, $result['best_practices_score'] );
		$this->assertSame( 80, $result['seo_score'] );
	}

	public function test_parse_response_extracts_failing_seo_issues_only(): void {
		$result = \SEOB_PageSpeed_Client::parse_response( $this->fixture() );

		$ids = array_column( $result['issues'], 'id' );

		$this->assertContains( 'meta-description', $ids );
		$this->assertNotContains( 'document-title', $ids, 'Audity se score 1 (OK) se nemají objevit v issues.' );
		$this->assertNotContains( 'viewport', $ids, 'Audity s scoreDisplayMode "notApplicable" se nemají objevit v issues.' );
		$this->assertNotContains( 'final-screenshot', $ids, 'Informativní audity (scoreDisplayMode "informative") se nemají objevit v issues.' );
	}

	public function test_parse_response_includes_title_and_description_for_issues(): void {
		$result = \SEOB_PageSpeed_Client::parse_response( $this->fixture() );

		$issue = current( array_filter( $result['issues'], static function ( $issue ) {
			return 'meta-description' === $issue['id'];
		} ) );

		$this->assertSame( 'Document has a meta description', $issue['title'] );
		$this->assertSame( 'Meta descriptions may be included in search results...', $issue['description'] );
	}

	public function test_parse_response_returns_wp_error_for_missing_lighthouse_result(): void {
		$result = \SEOB_PageSpeed_Client::parse_response( [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function fixture(): array {
		return [
			'lighthouseResult' => [
				'categories' => [
					'performance'    => [ 'score' => 0.87 ],
					'accessibility'  => [ 'score' => 0.91 ],
					'best-practices' => [ 'score' => 0.96 ],
					'seo'            => [
						'score'     => 0.80,
						'auditRefs' => [
							[ 'id' => 'document-title' ],
							[ 'id' => 'meta-description' ],
							[ 'id' => 'viewport' ],
							[ 'id' => 'final-screenshot' ],
						],
					],
				],
				'audits' => [
					'document-title' => [
						'title'             => 'Document has a <title> element',
						'description'       => 'The title gives screen reader users an overview of the page...',
						'score'             => 1,
						'scoreDisplayMode'  => 'binary',
					],
					'meta-description' => [
						'title'             => 'Document has a meta description',
						'description'       => 'Meta descriptions may be included in search results...',
						'score'             => 0,
						'scoreDisplayMode'  => 'binary',
						'displayValue'      => '',
					],
					'viewport' => [
						'title'             => 'Has a `<meta name="viewport">` tag',
						'description'       => 'A `<meta name="viewport">`...',
						'score'             => null,
						'scoreDisplayMode'  => 'notApplicable',
					],
					'final-screenshot' => [
						'title'             => 'Final Screenshot',
						'description'       => 'The final screenshot...',
						'score'             => null,
						'scoreDisplayMode'  => 'informative',
					],
				],
			],
		];
	}
}
