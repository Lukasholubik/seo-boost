<?php
/**
 * PHPUnit bootstrap – načte composer autoload a definuje minimum konstant,
 * které potřebují soubory pluginu při require_once (guard `! defined( 'ABSPATH' )`).
 */

require_once __DIR__ . '/../vendor-dev/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'SEOB_PLUGIN_DIR' ) ) {
	define( 'SEOB_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
