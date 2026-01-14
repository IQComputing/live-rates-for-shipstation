<?php
/**
 * IQLRSS Class Autoloader
 *
 * @param String $class
 */
namespace IQLRSS;

spl_autoload_register( function( $class ) {

	if( false === strpos( $class, __NAMESPACE__ . '\\' ) ) {
		return $class;
	}

	$class_path	= str_replace( __NAMESPACE__ . '\\', '', $class );
	$class_path	= str_replace( '_', '-', strtolower( $class_path ) );
	$class_path	= str_replace( '\\', '/', $class_path );
	$file_path	= wp_normalize_path( sprintf( '%s/%s',
		rtrim( plugin_dir_path( __FILE__ ), '\\/' ),
		$class_path . '.php'
	) );

	if( file_exists( $file_path ) ) {
		require_once $file_path;
	}

} );