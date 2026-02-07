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
function do_action( $action, ...$args ) { return null; }
function apply_filters( $filter, $return, ...$args ) { return $return; }
function esc_attr( $text ) { return $text; }
function esc_html__( $string, $textdomain = '' ) {
    if( empty( $textdomain ) ) throw new \Exception( 'esc_html__() missing a textdomain.', 500 );
    return $string;
}
function esc_html_e( $string, $textdomain = '' ) {
    if( empty( $textdomain ) ) throw new \Exception( 'esc_html_e() missing a textdomain.', 500 );
    return $string;
}
function esc_attr__( $string, $textdomain = '' ) {
    if( empty( $textdomain ) ) throw new \Exception( 'esc_attr__() missing a textdomain.', 500 );
    return $string;
}
function esc_attr_e( $string, $textdomain = '' ) {
    if( empty( $textdomain ) ) throw new \Exception( 'esc_attr_e() missing a textdomain.', 500 );
    return $string;
}



/**------------------------------------------------------------------------------------------------ **/
/** :: Ipsum Functions :: **/
/**------------------------------------------------------------------------------------------------ **/
function wp_cache_get( $key, $group = '', $force = false, $found = null ) { return null; }
function wp_cache_set( $key, $data, $group = '', $expires = 0 ) { return true; }
function get_transient( $string ) { return array(); }
function set_transient( $transient, $value, $expiration ) { return true; }
function register_deactivation_hook( $file, $callback ) { /* Do thing */ };
function register_activation_hook( $file, $callback ) { /* Do thing */ };
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { /* Do thing */ };
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { /* Do thing */ };