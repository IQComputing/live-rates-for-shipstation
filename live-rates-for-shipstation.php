<?php
/**
 * Plugin Name: Live Rates for ShipStation
 * Plugin URI: https://iqcomputing.com/contact/
 * Description: ShipStation shipping method with live rates.
 * Version: 1.0.0
 * Author: IQComputing
 * Author URI: https://iqcomputing.com/
 * Text Domain: live-rates-for-shipstation
 * Requires Plugins: woocommerce, woocommerce-shipstation-integration
 */
namespace IQLRSS;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

class Driver {

	/**
	 * Singleton Instance
	 *
	 * @var Object \IQLRSS\Driver
	 */
	protected static $_instance = null;


	/**
	 * Plugin Version
	 *
	 * @var String
	 */
	protected static $version = '1.0.0';


	/**
	 * Plugin Slug
	 *
	 * @var String
	 */
	protected static $slug = 'iqlrss';


	/**
	 * Singleton!
	 *
	 * @return Objet \IQLRSS\Driver
	 */
	public static function instance() {
		if( is_null( static::$_instance ) ) static::$_instance = new self();
		return static::$_instance;
	}


	/**
	 * Return plugin readonly data.
	 *
	 * @param String $key
	 * @param Mixed $default
	 *
	 * @return Mixed
	 */
	public static function get( $key, $default = '' ) {
		return ( isset( static::${$key} ) ) ? static::${$key} : $default;
	}


	/**
	 * Return a ShipStation Plugin Option Value
	 *
	 * @param String $key
	 * @param Mixed $default
	 * @param Boolean $prefix - Prefix Key with plugin slug.
	 *
	 * @return Mixed
	 */
	public static function get_ss_opt( $key, $default = '', $prefix = false ) {

		if( $prefix ) $key = static::plugin_prefix( $key );
		$settings = get_option( 'woocommerce_shipstation_settings' );
		return ( isset( $settings[ $key ] ) ) ? $settings[ $key ] : $default;

	}


	/**
	 * Prefix a string with the plugin slug.
	 *
	 * @param String $str
	 * @param String $sep
	 *
	 * @return String
	 */
	public static function plugin_prefix( $str, $sep = '_' ) {

		return sprintf( '%s%s%s',
			static::$slug,
			preg_replace( '/[^-_]/', '', $sep ),
			$str
		);

	}


	/**
	 * Return a URL to an asset (JS/CSS)
	 *
	 * @param String $asset
	 *
	 * @return String $url
	 */
	public static function get_asset_url( $asset ) {

		return sprintf( '%s/core/assets/%s',
			rtrim( plugin_dir_url( __FILE__ ), '\\/' ),
			$asset
		);

	}


	/**
	 * Kick off the plugin integration.
	 */
	protected function __construct() {
		Core\Settings_Shipstation::initialize();
	}

}


/**
 * Class Autoloader
 *
 * @param String $class
 */
spl_autoload_register( function( $class ) {

	if( false === strpos( $class, 'IQLRSS\\' ) ) {
		return $class;
	}

	$class_path	= str_replace( 'IQLRSS\\', '', $class );
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
add_action( 'plugins_loaded', array( '\IQLRSS\Driver', 'instance' ), 15 );