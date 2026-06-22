<?php
/**
 * Minimální loader pro vendorovaný TCPDF (bez Composeru).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TCPDF' ) ) {
	require_once __DIR__ . '/tcpdf.php';
}
