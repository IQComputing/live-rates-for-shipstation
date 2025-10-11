<?php
/**
 * Plugin Name: Live Rates for ShipStation
 * Plugin URI: https://iqcomputing.com/contact/
 * Description: ShipStation shipping method with live rates.
 * Version: 1.0.8
 * Requries at least: 5.9
 * Author: IQComputing
 * Author URI: https://iqcomputing.com/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: live-rates-for-shipstation
 * Requires Plugins: woocommerce, woocommerce-shipstation-integration
 *
 * @notes ShipStation does not make it easy or obvious how to update / create a Shipment for an Order.
 * 		The shipment create endpoint keeps coming back successful, but nothing on the ShipStation side
 * 		appears to change.
 * 		The v1 API update Order endpoint also doesn't seem to allow Shipment updates, but is required
 * 		to get the OrderID, required for any kind of create/update endpoints.
 *
 * @todo Look at preventing ship_estimate checks on ajax add_to_cart. Prefer Cart or Checkout pages.
 * @todo When the v2 API key is validated, recreate the carrier Select2 and populate.
 * @todo Add warehosue locations to Shipping Zone packages.
 * @todo Look into updating warehouses through Edit Order > Order Items.
 */
namespace IQLRSS;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

class Driver {

	/**
	 * Plugin Version
	 *
	 * @var String
	 */
	protected static $version = '1.0.8';


	/**
	 * Plugin Slug
	 *
	 * @var String
	 */
	protected static $slug = 'iqlrss';


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
	 * @param Boolean $skip_prefix - Skip Plugin Prefix and return a core ShipStation setting value.
	 *
	 * @return Mixed
	 */
	public static function get_ss_opt( $key, $default = '', $skip_prefix = false ) {

		if( ! $skip_prefix ) $key = static::plugin_prefix( $key );
		$settings = get_option( 'woocommerce_shipstation_settings' );
		return ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) ? maybe_unserialize( $settings[ $key ] ) : $default;

	}


	/**
	 * Set a ShipStation Plugin Option Value
	 *
	 * @todo Move out of ShipStation for WooCommerce options.
	 * @todo Create separate integration page.
	 *
	 * @param String $key
	 * @param Mixed $value
	 *
	 * @return Mixed
	 */
	public static function set_ss_opt( $key, $value ) {

		$key = static::plugin_prefix( $key );
		$settings = get_option( 'woocommerce_shipstation_settings' );

		if( is_bool( $value ) ) {
			$settings[ $key ] = boolval( $value );
		} else if( is_string( $value ) || is_numeric( $value ) ) {
			$settings[ $key ] = sanitize_text_field( $value );
		}

		update_option( 'woocommerce_shipstation_settings', $settings );

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
	 * Initialize the core controllers
	 * Vroom!
	 *
	 * @return void
	 */
	public static function drive() {
		Core\Settings_Shipstation::initialize();
	}

}


/**
 * Class Autoloader
 *
 * @param String $class
 */
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
add_action( 'plugins_loaded', array( '\IQLRSS\Driver', 'drive' ), 8 );


/**
 * Activate, Deactivate, and Uninstall Hooks
 */
require_once rtrim( __DIR__, '\\/' ) . '/_stallation.php';
register_deactivation_hook( __FILE__, array( '\IQLRSS\Stallation', 'deactivate' ) );
register_activation_hook( 	__FILE__, array( '\IQLRSS\Stallation', 'uninstall' ) );