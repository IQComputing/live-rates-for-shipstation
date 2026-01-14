<?php
/**
 * Bootstrap PHPUnit and WordPress.
 */
namespace IQLRSS;

// ABSPATH should not be defined at this moment, so if it is, something isn't right...
if( defined( 'ABSPATH' ) ) {
	return;
}

require_once rtrim( dirname( __DIR__ ), '\\/' ) . '/vendor/autoload.php';
require_once rtrim( dirname( __DIR__ ), '\\/' ) . '/../../../wp-load.php';