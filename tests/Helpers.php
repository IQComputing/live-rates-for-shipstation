<?php
/**
 * Make a mockery of
 *
 * @param String $of - What to make a mockery of?
 * @param String $kind - What kind of mockery?
 *
 * @return Object.
 */
function make_mockery( $of, $kind = '' ) {
    require_once sprintf( 'Mockeries/%s.php', ( $kind ) ? $kind : $of );
    return new ( '\IQLRSS\Tests\Mockeries\\' . $of )();
}


/**
 * Return an array of data.
 *
 * @param String $what
 *
 * @return String $data
 */
function get_data( $what ) {
    return json_decode( file_get_contents( sprintf( __DIR__ . '/Data/%s.json', $what ) ), true );
}