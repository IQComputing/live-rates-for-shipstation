<?php
/**
 * Logging trait. Could be expanded to more than WC_Logger.
 */
namespace IQLRSS\Core\Traits;

if( ! defined( 'ABSPATH' ) ) {
    return;
}

trait Logger {

    /**
	 * Log error in WooCommerce
	 * Passthru method - log what's given and give it back.
	 * Could make a good Trait
	 *
	 * @param Mixed $error 		- String or WP_Error
	 * @param String $level 	- WooCommerce level (debug|info|notice|warning|error|critical|alert|emergency)
	 * @param Array $context
	 *
	 * @return Mixed - Return the error back.
	 */
	protected function log( $error, $level = 'info', $context = array() ) {

		if( ! \IQLRSS\Driver::get_ss_opt( 'logging_enabled', 0, true ) ) {
			return $error;
		}

		$error_msg = esc_html__( 'Unepxected data type found when logging error.', 'live-rates-for-shipstation' );
		if( is_wp_error( $error ) ) {
			$error_msg = sprintf( '(%s) %s', $error->get_error_code(), $error->get_error_message() );
		} else if( is_string( $error ) ) {
			$error_msg = $error;
		}

		if( class_exists( '\WC_Logger' ) ) {

			/**
			 * The WC_Logger does not handle double quotes well.
			 * This will convert double quotes to faux: " -> ''
			 */
			array_walk_recursive( $context, function( &$val ) {
				$val = ( is_string( $val ) ) ? str_replace( '"', "''", $val ) : $val;
			} );

            $logger = \wc_get_logger();
            $logger->log( $level, $error_msg, array_merge( $context, array( 'source' => 'live-rates-for-shipstation' ) ) );

		}

		return $error;

	}

}