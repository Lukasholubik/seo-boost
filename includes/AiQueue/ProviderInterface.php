<?php
/**
 * Rozhraní pro AI providery (vyměnitelný adaptér).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SEOB_AiQueue_Provider_Interface {

	/**
	 * Odešle prompt AI modelu a vrátí čistý textový výstup, nebo WP_Error při chybě.
	 *
	 * @return string|WP_Error
	 */
	public function complete( string $prompt );
}
