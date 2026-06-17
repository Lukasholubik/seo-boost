<?php

namespace SeoBoost\Tests\Unit\InternalLinks;

use SeoBoost\Tests\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/InternalLinks/Similarity.php';

final class SimilarityTest extends TestCase {

	public function test_tokenize_lowercases_removes_diacritics_and_short_words_and_stopwords(): void {
		$tokens = \SEOB_InternalLinks_Similarity::tokenize( 'Příliš žluťoučký kůň a pes na louce.' );

		$this->assertContains( 'prilis', $tokens );
		$this->assertContains( 'zlutoucky', $tokens );
		$this->assertContains( 'kun', $tokens );
		$this->assertContains( 'louce', $tokens );

		// Stopslova ("a", "na") a krátká slova ("kůň" → "kun" má 3 znaky, ok; "pes" má 3 znaky, ok).
		$this->assertNotContains( 'a', $tokens );
		$this->assertNotContains( 'na', $tokens );
	}

	public function test_tokenize_filters_words_shorter_than_three_chars(): void {
		$tokens = \SEOB_InternalLinks_Similarity::tokenize( 'ok je to fajn web' );

		$this->assertNotContains( 'ok', $tokens );
		$this->assertNotContains( 'to', $tokens );
		$this->assertContains( 'fajn', $tokens );
		$this->assertContains( 'web', $tokens );
	}

	public function test_build_tfidf_returns_empty_for_no_documents(): void {
		$this->assertSame( [], \SEOB_InternalLinks_Similarity::build_tfidf( [] ) );
	}

	public function test_build_tfidf_assigns_higher_weight_to_rare_terms(): void {
		$documents = [
			1 => [ 'kočka', 'pes', 'zvíře', 'zvíře' ],
			2 => [ 'kočka', 'auto', 'silnice' ],
			3 => [ 'kočka', 'pes', 'kosa' ],
		];

		$vectors = \SEOB_InternalLinks_Similarity::build_tfidf( $documents );

		// "kočka" je ve všech 3 dokumentech (nízká IDF), "zvíře" jen v jednom (vysoká IDF).
		$this->assertGreaterThan( $vectors[1]['kočka'], $vectors[1]['zvíře'] );
	}

	public function test_cosine_similarity_of_identical_vectors_is_one(): void {
		$vector = [ 'seo' => 0.5, 'web' => 0.5 ];

		$this->assertEqualsWithDelta( 1.0, \SEOB_InternalLinks_Similarity::cosine( $vector, $vector ), 0.0001 );
	}

	public function test_cosine_similarity_of_disjoint_vectors_is_zero(): void {
		$a = [ 'seo' => 1.0 ];
		$b = [ 'marketing' => 1.0 ];

		$this->assertSame( 0.0, \SEOB_InternalLinks_Similarity::cosine( $a, $b ) );
	}

	public function test_cosine_similarity_returns_zero_for_empty_vector(): void {
		$this->assertSame( 0.0, \SEOB_InternalLinks_Similarity::cosine( [], [ 'seo' => 1.0 ] ) );
		$this->assertSame( 0.0, \SEOB_InternalLinks_Similarity::cosine( [ 'seo' => 1.0 ], [] ) );
	}

	public function test_top_similar_orders_by_score_descending_and_respects_limit(): void {
		$documents = [
			1 => \SEOB_InternalLinks_Similarity::tokenize( 'kávovar recenze test domácnost' ),
			2 => \SEOB_InternalLinks_Similarity::tokenize( 'kávovar test výhody domácí použití' ),
			3 => \SEOB_InternalLinks_Similarity::tokenize( 'pneumatiky zima výměna auto servis' ),
			4 => \SEOB_InternalLinks_Similarity::tokenize( 'kávovar nejlepší volba kancelář' ),
		];

		$vectors = \SEOB_InternalLinks_Similarity::build_tfidf( $documents );

		$top = \SEOB_InternalLinks_Similarity::top_similar( $vectors, 1, [], 3 );

		$ids = array_column( $top, 'id' );

		$this->assertNotContains( 1, $ids, 'zdrojový dokument se nesmí nabízet sám sobě' );
		$this->assertNotContains( 3, $ids, 'dokument bez společných slov se nemá nabízet' );
		// Dokument 2 (sdílí "kávovar" i "test") by měl být podobnější než dokument 4 (sdílí jen "kávovar").
		$this->assertSame( [ 2, 4 ], $ids );
	}

	public function test_top_similar_respects_exclude_ids(): void {
		$documents = [
			1 => \SEOB_InternalLinks_Similarity::tokenize( 'kávovar recenze test domácnost' ),
			2 => \SEOB_InternalLinks_Similarity::tokenize( 'kávovar test výhody domácí použití' ),
			4 => \SEOB_InternalLinks_Similarity::tokenize( 'kávovar nejlepší volba kancelář' ),
		];

		$vectors = \SEOB_InternalLinks_Similarity::build_tfidf( $documents );

		$top = \SEOB_InternalLinks_Similarity::top_similar( $vectors, 1, [ 2 ], 3 );
		$ids = array_column( $top, 'id' );

		$this->assertNotContains( 2, $ids );
		$this->assertContains( 4, $ids );
	}

	public function test_top_similar_returns_empty_array_for_unknown_source(): void {
		$vectors = [ 1 => [ 'seo' => 1.0 ] ];

		$this->assertSame( [], \SEOB_InternalLinks_Similarity::top_similar( $vectors, 999, [], 3 ) );
	}
}
