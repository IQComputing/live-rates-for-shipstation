<?php
/**
 * Bootstrap PHPUnit
 */
namespace IQLRSS;

// ABSPATH should not be defined at this moment, so if it is, something isn't right...
if( defined( 'ABSPATH' ) ) {
	return;
}

require_once rtrim( dirname( __DIR__ ), '\\/' ) . '/_autoload.php';
require_once rtrim( dirname( __DIR__ ), '\\/' ) . '/vendor/autoload.php';
require_once 'Mockeries/WordPress.php';
require_once 'Mockeries/WooCommerce.php';
require_once 'Helpers.php';