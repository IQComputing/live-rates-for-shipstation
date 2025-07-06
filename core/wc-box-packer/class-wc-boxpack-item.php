<?php
/**
 * Box Packing class found in woocommerce-shipping-ups
 * Updated by IQComputing because many of these methods
 * have the wrong return documentation.
 *
 * @version 2.0.1
 * @author WooThemes / Mike Jolley
 */
namespace IQLRSS\Core\WC_Box_Packer;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * WC_Boxpack_Item class.
 */
class WC_Boxpack_Item {

	public $weight;
	public $height;
	public $width;
	public $length;
	public $volume;
	public $value;
	public $meta;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( $length, $width, $height, $weight, $value = '', $meta = array() ) {
		$dimensions = array( $length, $width, $height );

		sort( $dimensions );

		$this->length = floatval( $dimensions[2] );
		$this->width  = floatval( $dimensions[1] );
		$this->height = floatval( $dimensions[0] );

		$this->volume = floatval( $width * $height * $length );
		$this->weight = floatval( $weight );
		$this->value  = $value;
		$this->meta   = $meta;
	}

	/**
	 * The item volume set during initialization.
	 * W * H * L = V
	 *
	 * @access public
	 * @return float
	 */
	function get_volume() {
		return $this->volume;
	}

	/**
	 * The item height set during initialization.
	 *
	 * @access public
	 * @return float
	 */
	function get_height() {
		return $this->height;
	}

	/**
	 * The item width set during initialization.
	 *
	 * @access public
	 * @return float
	 */
	function get_width() {
		return $this->width;
	}

	/**
	 * The item length set during initialization.
	 *
	 * @access public
	 * @return float
	 */
	function get_length() {
		return $this->length;
	}

	/**
	 * The item weight set during initialization.
	 *
	 * @access public
	 * @return float
	 */
	function get_weight() {
		return $this->weight;
	}

	/**
	 * Almost always the Price but is arbitrary.
	 *
	 * @access public
	 * @return float
	 */
	function get_value() {
		return $this->value;
	}

	/**
	 * get_meta function.
	 *
	 * @access public
	 * @return mixed
	 */
	function get_meta( $key = '' ) {
		if ( $key ) {
			if ( isset( $this->meta[ $key ] ) ) {
				return $this->meta[ $key ];
			} else {
				return null;
			}
		} else {
			return array_filter( (array) $this->meta );
		}
	}
}
