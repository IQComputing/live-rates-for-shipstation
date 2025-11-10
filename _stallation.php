<?php
/**
 * Installation, Uninstallation, and Deactivation hooks.
 */
namespace IQLRSS;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

Class Stallation {

	/**
	 * Deactivate Plugin
	 */
	public static function deactivate() {
		\IQLRSS\Driver::clear_cache();
	}


	/**
	 * Unintsall Plugin
	 */
	public static function uninstall() {

		// Normalize ShipStation Settings by removing our keys.
		$settings = get_option( 'woocommerce_shipstation_settings' );
		foreach( $settings as $key => $val ) {
			if( is_numeric( $key ) ) continue;
			if( 0 === strpos( $key, 'iqlrss_' ) ) {
				unset( $settings[ $key ] );
			}
		}
		update_option( 'woocommerce_shipstation_settings', $settings );

		// Clear Cache
		\IQLRSS\Driver::clear_cache();

	}

}