<?php
/**
 * Odhad šířky textu ve výchozím SERP fontu (Arial) v pixelech.
 * Používá tabulku přibližných šířek znaků při velikosti 14px.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Pixel_Width {

	private const DEFAULT_WIDTH = 7.0;

	/** Šířky znaků (px) pro Arial 14px. */
	private static array $widths = [
		' ' => 3.9, '!' => 3.9, '"' => 5.0, '#' => 7.8, '$' => 7.8, '%' => 12.5, '&' => 9.4, "'" => 2.4,
		'(' => 4.7, ')' => 4.7, '*' => 5.5, '+' => 8.2, ',' => 3.9, '-' => 4.7, '.' => 3.9, '/' => 3.9,
		'0' => 7.8, '1' => 7.8, '2' => 7.8, '3' => 7.8, '4' => 7.8, '5' => 7.8, '6' => 7.8, '7' => 7.8, '8' => 7.8, '9' => 7.8,
		':' => 3.9, ';' => 3.9, '<' => 8.2, '=' => 8.2, '>' => 8.2, '?' => 7.0, '@' => 14.2,
		'a' => 7.0, 'b' => 7.8, 'c' => 7.0, 'd' => 7.8, 'e' => 7.0, 'f' => 3.9, 'g' => 7.8, 'h' => 7.8,
		'i' => 3.1, 'j' => 3.1, 'k' => 7.0, 'l' => 3.1, 'm' => 12.0, 'n' => 7.8, 'o' => 7.8, 'p' => 7.8,
		'q' => 7.8, 'r' => 4.7, 's' => 7.0, 't' => 3.9, 'u' => 7.8, 'v' => 7.0, 'w' => 10.2, 'x' => 7.0,
		'y' => 7.0, 'z' => 7.0,
		'A' => 9.4, 'B' => 9.4, 'C' => 10.2, 'D' => 10.2, 'E' => 9.4, 'F' => 8.6, 'G' => 10.9, 'H' => 10.2,
		'I' => 3.9, 'J' => 3.9, 'K' => 9.4, 'L' => 7.8, 'M' => 12.0, 'N' => 10.2, 'O' => 10.9, 'P' => 9.4,
		'Q' => 10.9, 'R' => 10.2, 'S' => 9.4, 'T' => 8.6, 'U' => 10.2, 'V' => 9.4, 'W' => 13.3, 'X' => 9.4,
		'Y' => 9.4, 'Z' => 8.6,
		'[' => 3.9, '\\' => 3.9, ']' => 3.9, '^' => 7.8, '_' => 6.3, '`' => 7.8, '{' => 4.7, '|' => 3.9, '}' => 4.7, '~' => 8.2,
	];

	/**
	 * Vrátí odhadovanou šířku textu v px (Arial, 14px).
	 */
	public static function calculate( string $text, int $font_size = 14 ): float {
		$text  = (string) $text;
		$scale = $font_size / 14;
		$total = 0.0;
		$len   = mb_strlen( $text );

		for ( $i = 0; $i < $len; $i++ ) {
			$char = mb_substr( $text, $i, 1 );
			$base = self::base_char( $char );
			$total += self::$widths[ $base ] ?? self::DEFAULT_WIDTH;
		}

		return round( $total * $scale, 1 );
	}

	/**
	 * Převede znak s diakritikou na základní latinku pro vyhledání v tabulce.
	 */
	private static function base_char( string $char ): string {
		$translit = remove_accents( $char );

		return '' !== $translit ? $translit : $char;
	}
}
