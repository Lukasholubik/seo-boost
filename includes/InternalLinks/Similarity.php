<?php
/**
 * Lokální TF-IDF + kosinová podobnost pro návrhy interního prolinkování.
 * Čisté funkce – žádná DB ani WordPress API, plně testovatelné.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_InternalLinks_Similarity {

	/**
	 * Krátký český + anglický stoplist – běžná slova bez výpovědní hodnoty pro podobnost obsahu.
	 */
	private const STOPWORDS = [
		'a', 'aby', 'ale', 'and', 'ani', 'ano', 'jak', 'jako', 'jde', 'jeho', 'jej', 'její',
		'jejich', 'jen', 'jenž', 'jeste', 'ještě', 'jiz', 'již', 'jsem', 'jsi', 'jsme', 'jsou',
		'jste', 'kam', 'kde', 'kdo', 'kdy', 'kdyz', 'když', 'ktera', 'která', 'ktere', 'které',
		'kteri', 'kteří', 'ktery', 'který', 'ma', 'má', 'mate', 'máte', 'me', 'mě', 'mezi',
		'mit', 'mít', 'mu', 'muj', 'můj', 'muze', 'může', 'na', 'nad', 'nam', 'nám', 'nas',
		'nás', 'nebo', 'nez', 'než', 'nic', 'ní', 'no', 'nove', 'nový', 'nás', 'od', 'pak',
		'po', 'pod', 'pokud', 'pro', 'proc', 'proč', 'proto', 'pred', 'před', 'pri', 'při',
		'protoze', 'protože', 'se', 'si', 'sice', 'so', 'svych', 'svým', 'ta', 'tak', 'take',
		'také', 'takze', 'takže', 'tam', 'te', 'tedy', 'tema', 'tento', 'the', 'tim', 'tímto',
		'to', 'tohle', 'toho', 'tom', 'tomto', 'tomu', 'tuto', 'tvuj', 'tyto', 'uz', 'už', 'vam',
		'vám', 'vas', 'váš', 'vsak', 'však', 'vsechny', 'všechny', 'vy', 'z', 'za', 'zda', 'ze',
		'že', 'for', 'with', 'this', 'that', 'from', 'are', 'was', 'were', 'have', 'has',
	];

	/**
	 * Rozdělí text na tokeny (slova bez diakritiky, min. 3 znaky, bez stopslov).
	 *
	 * @return array<int,string>
	 */
	public static function tokenize( string $text ): array {
		$text = mb_strtolower( $text, 'UTF-8' );
		$text = self::remove_diacritics( $text );

		preg_match_all( '/[a-z0-9]+/u', $text, $matches );

		$tokens = [];

		foreach ( $matches[0] as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) < 3 ) {
				continue;
			}

			if ( in_array( $word, self::STOPWORDS, true ) ) {
				continue;
			}

			$tokens[] = $word;
		}

		return $tokens;
	}

	/**
	 * Odstraní diakritiku z textu (transliterace běžných českých znaků).
	 */
	private static function remove_diacritics( string $text ): string {
		$from = [ 'á', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ř', 'š', 'ť', 'ú', 'ů', 'ý', 'ž' ];
		$to   = [ 'a', 'c', 'd', 'e', 'e', 'i', 'n', 'o', 'r', 's', 't', 'u', 'u', 'y', 'z' ];

		return str_replace( $from, $to, $text );
	}

	/**
	 * Sestaví TF-IDF vektory pro sadu dokumentů.
	 *
	 * @param array<int,array<int,string>> $documents Mapa id příspěvku => seznam tokenů.
	 *
	 * @return array<int,array<string,float>> Mapa id příspěvku => sparse vektor (term => váha).
	 */
	public static function build_tfidf( array $documents ): array {
		$doc_count = count( $documents );

		if ( 0 === $doc_count ) {
			return [];
		}

		$document_frequency = [];

		foreach ( $documents as $tokens ) {
			foreach ( array_unique( $tokens ) as $term ) {
				$document_frequency[ $term ] = ( $document_frequency[ $term ] ?? 0 ) + 1;
			}
		}

		$vectors = [];

		foreach ( $documents as $id => $tokens ) {
			$total_terms = count( $tokens );

			if ( 0 === $total_terms ) {
				$vectors[ $id ] = [];
				continue;
			}

			$term_counts = array_count_values( $tokens );
			$vector      = [];

			foreach ( $term_counts as $term => $count ) {
				$tf  = $count / $total_terms;
				$idf = log( ( 1 + $doc_count ) / ( 1 + $document_frequency[ $term ] ) ) + 1;

				$vector[ $term ] = $tf * $idf;
			}

			$vectors[ $id ] = $vector;
		}

		return $vectors;
	}

	/**
	 * Spočítá kosinovou podobnost dvou sparse vektorů.
	 *
	 * @param array<string,float> $a
	 * @param array<string,float> $b
	 */
	public static function cosine( array $a, array $b ): float {
		if ( empty( $a ) || empty( $b ) ) {
			return 0.0;
		}

		$dot     = 0.0;
		$norm_a  = 0.0;
		$norm_b  = 0.0;

		foreach ( $a as $term => $weight ) {
			$norm_a += $weight * $weight;

			if ( isset( $b[ $term ] ) ) {
				$dot += $weight * $b[ $term ];
			}
		}

		foreach ( $b as $weight ) {
			$norm_b += $weight * $weight;
		}

		if ( 0.0 === $norm_a || 0.0 === $norm_b ) {
			return 0.0;
		}

		return $dot / ( sqrt( $norm_a ) * sqrt( $norm_b ) );
	}

	/**
	 * Vrátí nejpodobnější dokumenty k danému zdroji, seřazené podle skóre sestupně.
	 *
	 * @param array<int,array<string,float>> $vectors    Mapa id => TF-IDF vektor.
	 * @param array<int,int>                 $exclude_ids ID, která se nemají nabízet (např. už odkazované).
	 *
	 * @return array<int,array{id:int,score:float}>
	 */
	public static function top_similar( array $vectors, int $source_id, array $exclude_ids, int $limit = 3 ): array {
		if ( ! isset( $vectors[ $source_id ] ) ) {
			return [];
		}

		$source_vector = $vectors[ $source_id ];
		$exclude       = array_flip( $exclude_ids );
		$results       = [];

		foreach ( $vectors as $id => $vector ) {
			if ( $id === $source_id || isset( $exclude[ $id ] ) ) {
				continue;
			}

			$score = self::cosine( $source_vector, $vector );

			if ( $score <= 0.0 ) {
				continue;
			}

			$results[] = [ 'id' => $id, 'score' => $score ];
		}

		usort(
			$results,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $results, 0, $limit );
	}
}
