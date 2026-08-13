<?php
/**
 * General helper functions.
 */

if( ! defined( 'ABSPATH' ) ) {
    return;
}


/**
 * Convert a WooCommerce unit term to a
 * ShipStation unit term.
 *
 * @param String $unit
 *
 * @return String $term
 */
function iqlrss_convert_unit_term( $unit, $type = 'singular' ) {

    $known = array(
        'singular' => array(
            'kg'	=> esc_html__( 'kilogram', 'live-rates-for-shipstation' ),
            'g'		=> esc_html__( 'gram', 'live-rates-for-shipstation' ),
            'lbs'	=> esc_html__( 'pound', 'live-rates-for-shipstation' ),
            'oz'	=> esc_html__( 'ounce', 'live-rates-for-shipstation' ),
            'cm'	=> esc_html__( 'centimeter', 'live-rates-for-shipstation' ),
            'in'	=> esc_html__( 'inch', 'live-rates-for-shipstation' ),
        ),
        'plural' => array(
            'kg'	=> esc_html__( 'kilograms', 'live-rates-for-shipstation' ),
            'g'		=> esc_html__( 'grams', 'live-rates-for-shipstation' ),
            'lbs'	=> esc_html__( 'pounds', 'live-rates-for-shipstation' ),
            'oz'	=> esc_html__( 'ounces', 'live-rates-for-shipstation' ),
            'cm'	=> esc_html__( 'centimeters', 'live-rates-for-shipstation' ),
            'in'	=> esc_html__( 'inches', 'live-rates-for-shipstation' ),
        ),
    );

    return ( isset( $known[ $type ] ) && isset( $known[ $type ][ $unit ] ) ) ? $known[ $type ][ $unit ] : $unit;

}