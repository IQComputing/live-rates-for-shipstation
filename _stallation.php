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

		$settings = new Core\Settings_Shipstation();
		$settings->clear_cache();

	}


	/**
	 * Unintsall Plugin
	 */
	public static function uninstall() {

		$settings = new Core\Settings_Shipstation();
		$settings->clear_cache();

		$settings = get_option( 'woocommerce_shipstation_settings' );
		foreach( $settings as $key => $val ) {
			if( is_numeric( $key ) ) continue;
			if( 0 === strpos( $key, 'iqlrss_' ) ) {
				unset( $settings[ $key ] );
			}
		}
		update_option( 'woocommerce_shipstation_settings', $settings );

	}

}