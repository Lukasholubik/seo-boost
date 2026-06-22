<?php
/**
 * Sestavení promptů pro AI – čisté funkce bez side-effektů.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOB_AiQueue_Prompt_Builder {

	/**
	 * Prompt pro návrh SERP title (cca 60 znaků).
	 */
	public static function for_title( string $page_title, string $current_title, string $content_excerpt ): string {
		return sprintf(
			"Jsi SEO specialista. Pro stránku s názvem \"%s\" navrhni nový SERP title (meta title) v češtině, dlouhý maximálně 60 znaků, výstižný a lákavý pro prokliknutí.\n\nSoučasný title: \"%s\"\nÚryvek obsahu stránky: \"%s\"\n\nOdpověz pouze samotným navrhovaným titulkem, bez uvozovek, bez vysvětlení.",
			$page_title,
			'' !== $current_title ? $current_title : '(není nastaven)',
			$content_excerpt
		);
	}

	/**
	 * Prompt pro návrh meta description (cca 155 znaků).
	 */
	public static function for_description( string $page_title, string $current_description, string $content_excerpt ): string {
		return sprintf(
			"Jsi SEO specialista. Pro stránku s názvem \"%s\" navrhni novou meta description v češtině, dlouhou maximálně 155 znaků, která shrne obsah stránky a motivuje k prokliknutí.\n\nSoučasná description: \"%s\"\nÚryvek obsahu stránky: \"%s\"\n\nOdpověz pouze samotnou navrhovanou description, bez uvozovek, bez vysvětlení.",
			$page_title,
			'' !== $current_description ? $current_description : '(není nastavena)',
			$content_excerpt
		);
	}

	/**
	 * Prompt pro návrh alt textu obrázku (cca 125 znaků).
	 */
	public static function for_alt( string $page_title, string $image_filename, string $surrounding_text ): string {
		return sprintf(
			"Jsi SEO specialista. Na stránce s názvem \"%s\" se nachází obrázek s názvem souboru \"%s\". Navrhni výstižný alt text v češtině, dlouhý maximálně 125 znaků, popisující obsah obrázku v kontextu stránky.\n\nOkolní text stránky: \"%s\"\n\nOdpověz pouze samotným navrhovaným alt textem, bez uvozovek, bez vysvětlení.",
			$page_title,
			$image_filename,
			$surrounding_text
		);
	}
}
