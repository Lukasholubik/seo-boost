<?php
/**
 * TCPDF dokument s opakujícím se "hlavičkovým papírem" (logo, akcentová barva,
 * patička s firemními údaji a číslem stránky) na každé stránce PDF reportu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_Pdf_Document extends TCPDF {

	public string $brand_logo_path    = '';
	public float  $brand_logo_w       = 0;
	public float  $brand_logo_h       = 14;
	public string $brand_accent_color = '#2271b1';
	public string $brand_site_name    = '';
	public string $brand_company_name = '';

	/**
	 * Záhlaví stránky – logo vlevo, název webu vpravo, akcentová linka pod nimi.
	 */
	public function Header(): void {
		$y = 10;

		if ( $this->brand_logo_path && file_exists( $this->brand_logo_path ) ) {
			$this->Image( $this->brand_logo_path, 15, $y, $this->brand_logo_w, $this->brand_logo_h );
		}

		$this->SetFont( 'dejavusans', '', 8 );
		$this->SetTextColor( 100, 100, 100 );
		$this->SetXY( 15, $y + 2 );
		$this->Cell( 180, 6, $this->brand_site_name, 0, 0, 'R' );

		[ $r, $g, $b ] = self::hex_to_rgb( $this->brand_accent_color );
		$this->SetDrawColor( $r, $g, $b );
		$this->SetLineWidth( 0.6 );
		$this->Line( 15, 26, 195, 26 );
	}

	/**
	 * Patička stránky – akcentová linka, firemní údaje a číslo stránky.
	 */
	public function Footer(): void {
		[ $r, $g, $b ] = self::hex_to_rgb( $this->brand_accent_color );

		$this->SetY( -15 );
		$this->SetDrawColor( $r, $g, $b );
		$this->SetLineWidth( 0.3 );
		$this->Line( 15, $this->GetY(), 195, $this->GetY() );

		$this->SetY( -12 );
		$this->SetFont( 'dejavusans', '', 8 );
		$this->SetTextColor( 100, 100, 100 );
		$this->Cell( 90, 10, $this->brand_company_name, 0, 0, 'L' );
		$this->Cell( 90, 10, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R' );
	}

	/**
	 * @return int[] [r, g, b]
	 */
	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return [ 34, 113, 177 ];
		}

		return [
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		];
	}
}
