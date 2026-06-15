<?php

namespace SeoBoost\Tests\Unit\PageSpeed;

use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/PageSpeed/ScanRunner.php';

final class ScanRunnerAggregationTest extends TestCase {

	public function test_aggregate_group_computes_averages_ignoring_nulls(): void {
		$rows = [
			$this->row( 1, 80, 90, 95, 70, [] ),
			$this->row( 2, 60, null, 85, 90, [] ),
		];

		$summary = \SEOB_PageSpeed_ScanRunner::aggregate_group( $rows );

		$this->assertSame( 70, $summary['performance_avg'] );
		$this->assertSame( 90, $summary['accessibility_avg'] );
		$this->assertSame( 90, $summary['best_practices_avg'] );
		$this->assertSame( 80, $summary['seo_avg'] );
	}

	public function test_aggregate_group_counts_sample_size_by_unique_object_id(): void {
		$rows = [
			$this->row( 1, 80, 90, 95, 70, [] ),
			$this->row( 1, 82, 91, 96, 72, [] ),
			$this->row( 2, 60, 70, 85, 90, [] ),
		];

		$summary = \SEOB_PageSpeed_ScanRunner::aggregate_group( $rows );

		$this->assertSame( 2, $summary['sample_size'] );
		$this->assertSame( [ 1, 2 ], $summary['sample_object_ids'] );
	}

	public function test_aggregate_group_orders_common_issues_by_frequency(): void {
		$rows = [
			$this->row( 1, 80, 90, 95, 70, [
				[ 'id' => 'meta-description', 'title' => 'Document has a meta description', 'description' => 'desc' ],
				[ 'id' => 'image-alt', 'title' => 'Image elements have alt attributes', 'description' => 'desc2' ],
			] ),
			$this->row( 2, 60, 70, 85, 90, [
				[ 'id' => 'meta-description', 'title' => 'Document has a meta description', 'description' => 'desc' ],
			] ),
		];

		$summary = \SEOB_PageSpeed_ScanRunner::aggregate_group( $rows );

		$this->assertSame( 'meta-description', $summary['common_issues'][0]['id'] );
		$this->assertSame( 2, $summary['common_issues'][0]['count'] );
		$this->assertSame( 'image-alt', $summary['common_issues'][1]['id'] );
		$this->assertSame( 1, $summary['common_issues'][1]['count'] );
	}

	public function test_compute_deltas_returns_differences_to_previous_run(): void {
		$row  = [ 'performance_avg' => 80, 'accessibility_avg' => 90, 'best_practices_avg' => 95, 'seo_avg' => 70 ];
		$prev = [ 'performance_avg' => 70, 'accessibility_avg' => 90, 'best_practices_avg' => 90, 'seo_avg' => 80 ];

		$deltas = \SEOB_PageSpeed_ScanRunner::compute_deltas( $row, $prev );

		$this->assertSame( 10, $deltas['performance_avg'] );
		$this->assertSame( 0, $deltas['accessibility_avg'] );
		$this->assertSame( 5, $deltas['best_practices_avg'] );
		$this->assertSame( -10, $deltas['seo_avg'] );
	}

	public function test_compute_deltas_returns_null_without_previous_run(): void {
		$row = [ 'performance_avg' => 80, 'accessibility_avg' => 90, 'best_practices_avg' => 95, 'seo_avg' => 70 ];

		$deltas = \SEOB_PageSpeed_ScanRunner::compute_deltas( $row, null );

		$this->assertNull( $deltas['performance_avg'] );
		$this->assertNull( $deltas['accessibility_avg'] );
		$this->assertNull( $deltas['best_practices_avg'] );
		$this->assertNull( $deltas['seo_avg'] );
	}

	public function test_compute_deltas_returns_null_when_score_missing_on_either_side(): void {
		$row  = [ 'performance_avg' => null, 'accessibility_avg' => 90, 'best_practices_avg' => 95, 'seo_avg' => 70 ];
		$prev = [ 'performance_avg' => 70, 'accessibility_avg' => null, 'best_practices_avg' => 90, 'seo_avg' => 80 ];

		$deltas = \SEOB_PageSpeed_ScanRunner::compute_deltas( $row, $prev );

		$this->assertNull( $deltas['performance_avg'] );
		$this->assertNull( $deltas['accessibility_avg'] );
		$this->assertSame( 5, $deltas['best_practices_avg'] );
		$this->assertSame( -10, $deltas['seo_avg'] );
	}

	/**
	 * @param array<int,array<string,mixed>> $issues
	 * @return array<string,mixed>
	 */
	private function row( int $object_id, ?int $performance, ?int $accessibility, ?int $best_practices, ?int $seo, array $issues ): array {
		return [
			'object_id'            => $object_id,
			'performance_score'    => $performance,
			'accessibility_score'  => $accessibility,
			'best_practices_score' => $best_practices,
			'seo_score'            => $seo,
			'issues_json'          => wp_json_encode( $issues ),
		];
	}
}
