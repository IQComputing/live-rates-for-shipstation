<?php
/**
 * WooCommerce specific mockeries.
 *
 * :: Shipping Method
 * :: Shipping Zone
 * :: Product
 * :: Countries
 * :: Full Functions
 */
/**------------------------------------------------------------------------------------------------ **/
/** :: Shipping Method :: **/
/**------------------------------------------------------------------------------------------------ **/
if( ! class_exists( 'WC_Shipping_Method' ) ) {
class WC_Shipping_Method {
    function get_option( $key, $default = '' ) {
        switch( $key ) {
            case 'services': return get_data( ucwords( $key ) ); break;
        }
    }
}
}



/**------------------------------------------------------------------------------------------------ **/
/** :: Shipping Zone :: **/
/**------------------------------------------------------------------------------------------------ **/
if( ! class_exists( 'WC_Shipping_Zones' ) ) {
class WC_Shipping_Zones {
    function get_shipping_method() { return new WC_Shipping_Method(); }
}
}



/**------------------------------------------------------------------------------------------------ **/
/** :: Product :: **/
/**------------------------------------------------------------------------------------------------ **/
if( ! class_exists( 'WC_Product' ) ) {
class WC_Product {
    protected $post_type = 'product';
	protected $product_type = 'simple';
	protected $data = array(
		'name'               => 'Mock Product',
		'slug'               => 'mock-product',
		'id'				 => 0,
		'status'             => 'publish',
		'sku'                => 'MOCK',
		'price'              => '1.00',
		'weight'             => '10',
		'length'             => '5',
		'width'              => '5',
		'height'             => '5',
	);

	public function __construct( $data = array() ) { $this->data = $data; }
	public function __call( $name, $args = array() ) { return $this->data[ str_replace( 'get_', '', $name ) ]; }
	public function set( $key, $val ) { $this->data[ $key ] = $val; }
	public function needs_shipping() { return true; }

}
}



/**------------------------------------------------------------------------------------------------ **/
/** :: Countries :: **/
/**------------------------------------------------------------------------------------------------ **/
class WC_Countries {
	public function __call( $method, $args = array() ) {
		switch( $method ) {
			case 'get_base_county': return 'WCBaseCountry';
			case 'get_base_postcode': return 'WCBasePostcode';
			case 'get_base_city': return 'WCBaseCity';
			case 'get_base_state': return 'WCBaseState';
		}
		return 'WCBaseUnknown';
	}
}



/**------------------------------------------------------------------------------------------------ **/
/** :: Full Functions :: **/
/**------------------------------------------------------------------------------------------------ **/
/**
 * WooCommerce WC()
 *
 * @return Object (or something?)
 */
function WC() {
	$obj = new stdClass();
	$obj->countries = new WC_Countries();
	return $obj;
}


/**
 * Return an array of WC_Products
 *
 * @param Array $args
 *
 * @return Array
 */
function wc_get_products( $args = array() ) {
	$products = array();
	$data 	  = get_data( 'Products' );
	foreach( $data as $p ) $products[] = new \WC_Product( $p );
	return $products;
}

/**
 * @link https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/wc-formatting-functions.php#L119
 *
 * Normalise dimensions, unify to cm then convert to wanted unit value.
 *
 * Usage:
 * wc_get_dimension( 55, 'in' );
 * wc_get_dimension( 55, 'in', 'm' );
 *
 * @param int|float $dimension    Dimension.
 * @param string    $to_unit      Unit to convert to.
 *                                Options: 'in', 'mm', 'cm', 'm'.
 * @param string    $from_unit    Unit to convert from.
 *                                Defaults to ''.
 *                                Options: 'in', 'mm', 'cm', 'm'.
 * @return float
 */
function wc_get_dimension( $dimension, $to_unit, $from_unit = '' ) {

	$to_unit = strtolower( $to_unit );

	if ( empty( $from_unit ) ) {
		$from_unit = strtolower( get_option( 'woocommerce_dimension_unit' ) );
	}

	// Unify all units to cm first.
	if ( $from_unit !== $to_unit ) {
		switch ( $from_unit ) {
			case 'in':
				$dimension *= 2.54;
				break;
			case 'm':
				$dimension *= 100;
				break;
			case 'mm':
				$dimension *= 0.1;
				break;
			case 'yd':
				$dimension *= 91.44;
				break;
		}

		// Output desired unit.
		switch ( $to_unit ) {
			case 'in':
				$dimension *= 0.3937;
				break;
			case 'm':
				$dimension *= 0.01;
				break;
			case 'mm':
				$dimension *= 10;
				break;
			case 'yd':
				$dimension *= 0.010936133;
				break;
		}
	}

	return ( $dimension < 0 ) ? 0 : $dimension;
}


/**
 * @link https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/wc-formatting-functions.php#L178
 *
 * Normalise weights, unify to kg then convert to wanted unit value.
 *
 * Usage:
 * wc_get_weight(55, 'kg');
 * wc_get_weight(55, 'kg', 'lbs');
 *
 * @param int|float $weight    Weight.
 * @param string    $to_unit   Unit to convert to.
 *                             Options: 'g', 'kg', 'lbs', 'oz'.
 * @param string    $from_unit Unit to convert from.
 *                             Defaults to ''.
 *                             Options: 'g', 'kg', 'lbs', 'oz'.
 * @return float
 */
function wc_get_weight( $weight, $to_unit, $from_unit = '' ) {
	$weight  = (float) $weight;
	$to_unit = strtolower( $to_unit );

	if ( empty( $from_unit ) ) {
		$from_unit = strtolower( get_option( 'woocommerce_weight_unit' ) );
	}

	// Unify all units to kg first.
	if ( $from_unit !== $to_unit ) {
		switch ( $from_unit ) {
			case 'g':
				$weight *= 0.001;
				break;
			case 'lbs':
				$weight *= 0.453592;
				break;
			case 'oz':
				$weight *= 0.0283495;
				break;
		}

		// Output desired unit.
		switch ( $to_unit ) {
			case 'g':
				$weight *= 1000;
				break;
			case 'lbs':
				$weight *= 2.20462;
				break;
			case 'oz':
				$weight *= 35.274;
				break;
		}
	}

	return ( $weight < 0 ) ? 0 : $weight;
}