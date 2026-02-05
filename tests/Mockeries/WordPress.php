<?php
/**
 * WordPress specific mocks.
 *
 * :: Functions
 * :: Ipsum Functions
 */
/**------------------------------------------------------------------------------------------------ **/
/** :: Functions :: **/
/**------------------------------------------------------------------------------------------------ **/
function absint( $data ) { return intval( $data ); }
function is_wp_error( $obj ) { return ( is_object( $obj ) && ( is_a( $obj, 'WP_Error' ) || is_a( $obj, 'Exception' ) ) ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function get_option( $key, $default = '' ) { return get_data( 'Options' )[ $key ] ?? $default; }
function wp_normalize_path( $path ) { return str_replace( array( '/', '\\', '\\\\', ), '/', $path ); }
function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '\\/' ) . '/'; }
function maybe_unserialize( $data ) { return ( is_string( $data ) ) ? @unserialize( trim( $data ) ) : $data; }



/**------------------------------------------------------------------------------------------------ **/
/** :: Ipsum Functions :: **/
/**------------------------------------------------------------------------------------------------ **/
function wp_cache_get( $key, $group = '', $force = false, $found = null ) { return null; }
function wp_cache_set( $key, $data, $group = '', $expires = 0 ) { return true; }
function apply_filters( $filter, $return, ...$args ) { return $return; }
function do_action( $action, ...$args ) { return null; }
function get_transient( $string ) { return array(); }
function set_transient( $transient, $value, $expiration ) { return true; }
function register_deactivation_hook( $file, $callback ) { /* Do thing */ };
function register_activation_hook( $file, $callback ) { /* Do thing */ };

function esc_html__( $string, $textdomain = '' ) {
    if( empty( $domain ) ) throw new \Exception( 'esc_html__() missing a textdomain.', 500 );
    return $string;
}

function esc_html_e( $string, $textdomain = '' ) {
    if( empty( $domain ) ) throw new \Exception( 'esc_html_e() missing a textdomain.', 500 );
    return $string;
}