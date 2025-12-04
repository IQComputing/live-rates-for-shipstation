<?php
/**
 * Plugin Name: Live Rates for ShipStation
 * Plugin URI: https://iqcomputing.com/contact/
 * Description: ShipStation shipping method with live rates.
 * Version: 1.1.1
 * Requries at least: 6.2
 * Author: IQComputing
 * Author URI: https://iqcomputing.com/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: live-rates-for-shipstation
 * Requires Plugins: woocommerce, woocommerce-shipstation-integration
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
	protected static $version = '1.1.1';


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
	 * @return void
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
	 * Return a ShipStation Plugin Option Value
	 *
	 * @param String $key
	 * @param Mixed $default
	 * @param Boolean $skip_prefix - Skip Plugin Prefix and return a core ShipStation setting value.
	 *
	 * @return Mixed
	 */
	public static function get_opt( $key, $default = '' ) {
		$settings = get_option( static::plugin_prefix( 'plugin' ) );
		return ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) ? maybe_unserialize( $settings[ $key ] ) : $default;
	}


	/**
	 * Set a plugin option.
	 *
	 * @param String $key
	 * @param Mixed $value
	 *
	 * @return void
	 */
	public static function set_opt( $key, $value ) {

		$option 	= static::plugin_prefix( 'plugin' );
		$settings 	= get_option( $option, array() );

		if( is_bool( $value ) ) {
			$settings[ $key ] = boolval( $value );
		} else if( is_string( $value ) || is_numeric( $value ) ) {
			$settings[ $key ] = sanitize_text_field( $value );
		}

		update_option( $option, $settings );

	}


	/**
	 * Clear the Plugin API cache.
	 *
	 * @return void
	 */
	public static function clear_cache() {

		global $wpdb;

		/**
		 * The API Class creates various transients to cache carrier services.
		 * These transients are not tracked but generated based on the responses carrier codes.
		 * All these transients are prefixed with our plugins unique string slug.
		 * The first WHERE ensures only `_transient_` and the 2nd ensures only our plugins transients.
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s AND option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
			$wpdb->options,
			$wpdb->esc_like( '_transient_' ) . '%',
			'%' . $wpdb->esc_like( '_' . static::get( 'slug' ) . '_' ) . '%'
		) );

		// Set transient to clear any WC_Session caches if they are found.
		$expires = absint( apply_filters( 'wc_session_expiration', DAY_IN_SECONDS * 2 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		set_transient( static::plugin_prefix( 'wcs_timeout' ), time(), $expires );

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
	 * Return a URL to an asset (JS/CSS usually)
	 *
	 * @param String $asset
	 *
	 * @return String
	 */
	public static function get_asset_url( $asset ) {

		return sprintf( '%s/core/assets/%s',
			rtrim( plugin_dir_url( __FILE__ ), '\\/' ),
			$asset
		);

	}


	/**
	 * Return a path to an asset.
	 *
	 * @param String $asset
	 *
	 * @return String
	 */
	public static function get_asset_path( $asset ) {

		return sprintf( '%s/core/assets/%s',
			rtrim( plugin_dir_path( __FILE__ ), '\\/' ),
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

		// Run any version transition actions.
		Stallation::transversion( static::$version );

		// Load core controllers.
		Core\Rest_Router::initialize();
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