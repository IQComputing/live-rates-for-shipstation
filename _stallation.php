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


	/**
	 * Transition the old plugin version to the current plugin verison.
	 * This may trigger additional actions.
	 *
	 * @param String $version
	 *
	 * @return void
	 */
	public static function transversion( $version ) {

		$found_version = \IQLRSS\Driver::get_opt( 'version', '1.0.0' );
		if( 0 == version_compare( $version, $found_version ) ) {
			return;
		}

		\IQLRSS\Driver::set_opt( 'version', $version );
		flush_rewrite_rules();

	}

}