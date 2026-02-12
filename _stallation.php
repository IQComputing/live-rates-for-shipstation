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
	 * Uninstall Plugin
	 */
	public static function uninstall() {

		// Grab Settings
		$settings = get_option( 'woocommerce_shipstation_settings' );

		// Check for a Full Uninstall
		if( isset( $settings['iqlrss_uninstall_full'] ) && $settings['iqlrss_uninstall_full'] ) {

			// Normalize ShipStation Settings by removing our keys.
			foreach( $settings as $key => $val ) {
				if( is_numeric( $key ) ) continue;
				if( 0 === strpos( $key, 'iqlrss_' ) ) {
					unset( $settings[ $key ] );
				}
			}
			update_option( 'woocommerce_shipstation_settings', $settings );

			// Grab IQLRSS Specific Shipping Methods and remove them.
			if( class_exists( '\WC_Shipping_Zones' ) ) {

				foreach( \WC_Shipping_Zones::get_zones() as $zone_arr ) {

					$iqlrss_methods = array_filter( $zone_arr['shipping_methods'], fn( $m ) => false !== strpos( $m->id, 'iqlrss_shipstation' ) );
					if( ! empty( $iqlrss_methods ) ) {
						foreach( $iqlrss_methods as $m ) {
							( new \WC_Shipping_Zone( $zone_arr['id'] ) )->delete_shipping_method( $m->instance_id );
						}
					}
				}
			}

		}

		// Always Clear Cache
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