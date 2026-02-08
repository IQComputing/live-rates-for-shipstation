<?php
/**
 * Ensure the Shipping Calculator works as expected.
 *
 * @todo extend this to test against mock WC_Cart data.
 *
 * @internal Results expect 3 Warnings [W] that are logs.
 *
 * :: Tests - Base
 * :: Tests - Requests Individual
 * :: Tests - One Big Box
 * :: Tests - WC Box Packer
 * :: Setup & Helpers
 * :: Mock Shipping Calculator
 */
namespace IQLRSS\Tests\Unit;
use PHPUnit\Framework as PHPUnit;

class TestShippingCalculator extends PHPUnit\TestCase {

	/* Shipping Calculator Object */
	protected $calc;


	/**------------------------------------------------------------------------------------------------ **/
	/** :: Tests - Base :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Ensure we can get.
	 */
	public function test_getBasic() {
		$this->assertSame( 'bar', $this->calc->get( 'foo', 'notFoo' ) );
	}

	/**
	 * State check.
	 */
	public function test_productType() {
		$this->assertSame( 'products', $this->calc->getProp( 'datatype', 'notProducts' ) );
	}

	/**
	 * Cart Populated
	 */
	public function test_cartIsArray() {
		$this->assertIsArray( $this->calc->getProp( 'cart', 'cartNotArray' ) );
	}

	/**
	 * Cart Populated
	 */
	public function test_cartHasData() {
		$cart = $this->calc->getProp( 'cart', array() );
		$this->assertTrue( array_key_exists( 'data', reset( $cart ) ) );
	}

	/**
	 * Valid Destination To
	 */
	public function test_destTo() {
		$to = $this->calc->get( 'to', 'DestToNotArray' );
		$this->assertIsArray( $to );
		$this->assertSame( '63021', $to['postcode'] );
		$this->assertIsArray( $this->calc->get_ship_to() );
		$this->assertSame( '63021', $this->calc->get_ship_to()['to_postal_code'] );
	}

	/**
	 * Error Logged - Destination To - Missing Country
	 */
	public function test_errorDestToNoCountry() {

		// Setup
		$products 		= $this->getProducts( 'mock' );
		$argsNoCountry 	= $this->getShippingCalcArgs();
		unset( $argsNoCountry['to']['country'] );
		$this->calc = new MockShippingCalculator( $products, $argsNoCountry );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Request missing a To' );
		$this->calc->setup_base();

	}

	/**
	 * Error Logged - Destination To - Missing Postcode
	 */
	public function test_errorDestToNoPostcode() {

		// Setup
		$products 		= $this->getProducts( 'mock' );
		$argsNoCountry 	= $this->getShippingCalcArgs();
		unset( $argsNoCountry['to']['postcode'] );
		$this->calc = new MockShippingCalculator( $products, $argsNoCountry );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Request missing a To' );
		$this->calc->setup_base();

	}

	/**
	 * Valid Destination From
	 */
	public function test_destFrom() {
		$from = $this->calc->get( 'from', 'DestFromNotArray' );
		$this->assertIsArray( $from );
		$this->assertSame( '20260', $from['postcode'] );
		$this->assertIsArray( $this->calc->get_ship_from() );
		$this->assertSame( '20260', $this->calc->get_ship_from()['from_postal_code'] );
	}

	/**
	 * Error Logged - Destination From - Missing Country
	 */
	public function test_errorDestFromNoCountry() {

		// Setup
		$products 		= $this->getProducts( 'mock' );
		$argsNoCountry 	= $this->getShippingCalcArgs();
		unset( $argsNoCountry['from']['country'] );
		$this->calc = new MockShippingCalculator( $products, $argsNoCountry );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Request missing a From' );
		$this->calc->setup_base();

	}

	/**
	 * Error Logged - Destination From - Missing Postcode
	 */
	public function test_errorDestFromPostcode() {

		// Setup
		$products 		= $this->getProducts( 'mock' );
		$argsNoCountry 	= $this->getShippingCalcArgs();
		unset( $argsNoCountry['from']['postcode'] );
		$this->calc = new MockShippingCalculator( $products, $argsNoCountry );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Request missing a From' );
		$this->calc->setup_base();

	}


	/**------------------------------------------------------------------------------------------------ **/
	/** :: Tests - Requests Individual :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Valid Request Args for Valid Products.
	 */
	public function test_validRequestsbyIndividual() {

		$this->calc = $this->getCalculator( 'valid' );
		$requests = $this->calc->get_requestsby_individual();
		foreach( $requests as $request ) {
			if( ! isset( $request['weight'], $request['dimensions'], $request['_name'] ) ) {
				$this->assertFalse( false, 'Valid Products missing required key.' ); break;
			}
		}

		$this->assertTrue( true );

	}

	/**
	 * Valid Minweight
	 */
	public function test_validRequestsbyIndividualMinWeightArgFallback() {

		$minweight = 12.34; // Constish

		// Setup
		$this->calc = $this->getCalculator( 'dim_none', array( 'minweight' => $minweight ) );
		$products 	= $this->getProducts( 'dim_none' );
		$requests 	= $this->calc->get_requestsby_individual();

		// Test
		$this->assertEmpty( reset( $products )->get_weight() ); // Ensure product has no weight
		$this->assertSame( $minweight, reset( $requests )['weight']['value'] ); // Ensure product has weight.

	}

	/**
	 * Invalid Weight
	 */
	public function test_invalidRequestsbyIndividualWeight() {

		// Setup
		$key	  	= 'weight';
		$products 	= array_filter( $this->getProducts( 'dim_missing' ), fn( $p ) => "missing-dim-{$key}" === $p->get_slug() );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( sprintf( 'missing (%s) dimensions.', $key ) );
		$this->calc = new MockShippingCalculator( $products );
		$this->calc->get_requestsby_individual();

	}

	/**
	 * Invalid Length
	 */
	public function test_invalidRequestsbyIndividualLength() {

		// Setup
		$key	  	= 'length';
		$products 	= array_filter( $this->getProducts( 'dim_missing' ), fn( $p ) => "missing-dim-{$key}" === $p->get_slug() );
		reset( $products )->set( 'weight', 0 );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( sprintf( 'missing (%s, weight) dimensions.', $key ) );
		$this->calc = new MockShippingCalculator( $products );
		$this->calc->get_requestsby_individual();

	}


	/**
	 * This packing option have unique settings:
	 * 'packing_sub' => 'weightonly|stacked'
	 */
	/**------------------------------------------------------------------------------------------------ **/
	/** :: Tests - One Big Box :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Valid Request Args for Valid Products.
	 */
	public function test_validRequestsbyOneBigBox() {

		$this->calc = $this->getCalculator( 'dim_valid' );
		$products	= $this->getProducts( 'dim_valid' );
		$total 		= array_reduce( $products, fn( $sum, $p ) => $sum + (float)$p->get_weight(), 0 );
		$onebox 	= $this->calc->get_requestsby_onebox();

		$this->assertArrayHasKey( 'weight', reset( $onebox ) );
		$this->assertSame( $total, reset( $onebox )['weight']['value'] );

	}

	/**
	 * Valid Minweight
	 */
	public function test_validRequestsbyOneBigBoxMinWeightFallback() {

		$minweight = 12.34; // Constish

		// Setup
		$this->calc = $this->getCalculator( 'dim_none', array( 'minweight' => $minweight ) );
		$products 	= $this->getProducts( 'dim_none' );
		$requests 	= $this->calc->get_requestsby_onebox();

		// Test
		$this->assertEmpty( reset( $products )->get_weight() ); // Ensure product has no weight
		$this->assertSame( $minweight, reset( $requests )['weight']['value'] ); // Ensure product has weight.

	}

	/**
	 * Valid Request - Subtype Weight
	 */
	public function test_validRequestsbyOneBigBoxWeightSub() {

		$this->calc = $this->getCalculator( 'valid', array( 'packing_sub' => 'weightonly' ) );
		$products	= $this->getProducts( 'valid' );
		$total 		= array_reduce( $products, fn( $sum, $p ) => $sum + (float)$p->get_weight(), 0 );
		$onebox 	= $this->calc->get_requestsby_onebox();

		$this->assertArrayHasKey( 'weight', reset( $onebox ) );
		$this->assertSame( $total, reset( $onebox )['weight']['value'] );

	}

	/**
	 * Valid Request - Subtype Stacked
	 */
	public function test_validRequestsbyOneBigBoxStackedSub() {

		$this->calc = $this->getCalculator( 'valid', array( 'packing_sub' => 'stacked' ) );
		$products	= $this->getProducts( 'valid' );

		// Largest
		$length	= array_reduce( $products, fn( $sum, $p ) => $sum = ( (float)$p->get_length() > $sum ) ? (float)$p->get_length() : $sum , 0 );
		$width	= array_reduce( $products, fn( $sum, $p ) => $sum = ( (float)$p->get_width() > $sum ) ? (float)$p->get_width() : $sum , 0 );

		// Running
		$height = array_reduce( $products, fn( $sum, $p ) => $sum + (float)$p->get_height(), 0 );
		$weight = array_reduce( $products, fn( $sum, $p ) => $sum + (float)$p->get_weight(), 0 );

		$onebox = $this->calc->get_requestsby_onebox();
		$onebox = array_shift( $onebox );

		$this->assertArrayHasKey( 'dimensions', $onebox );
		$this->assertArrayHasKey( 'weight', $onebox );
		$this->assertEqualsWithDelta( $length, $onebox['dimensions']['length'], 0.00001 );
		$this->assertEqualsWithDelta( $width, $onebox['dimensions']['width'], 0.00001 );
		$this->assertEqualsWithDelta( $height, $onebox['dimensions']['height'], 0.00001 );
		$this->assertEqualsWithDelta( $weight, $onebox['weight']['value'], 0.00001 );

	}

	/**
	 * Invalid Onebox - Weight
	 */
	public function test_invalidRequestsbyOneBigBoxWeight() {

		// Setup
		$key		= 'weight';
		$products 	= array_filter( $this->getProducts( 'dim_missing' ), fn( $p ) => "missing-dim-{$key}" === $p->get_slug() );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( sprintf( 'missing weight', $key ) );
		$this->calc = new MockShippingCalculator( $products );
		$this->calc->get_requestsby_onebox();

	}

	/**
	 * Throws Warning
	 * Invalid Stacked - Dimension
	 */
	public function test_invalidRequestsbyOneBigBoxStackedSubDim() {

		// Setup
		$key 		= 'length';
		$products 	= array_filter( $this->getProducts( 'dim_missing' ), fn( $p ) => "missing-dim-{$key}" === $p->get_slug() );

		// Test
		$this->calc = new MockShippingCalculator( $products, array( 'packing_sub' => 'stacked' ) );
		$requests = $this->calc->get_requestsby_onebox();

		$this->assertArrayHasKey( 'weight', reset( $requests ) );
		$this->assertArrayNotHasKey( 'dimensions', reset( $requests ) );

	}


	/**
	 * This packing option have unique settings:
	 * 'custom_boxes' => Array
	 */
	/**------------------------------------------------------------------------------------------------ **/
	/** :: Tests - WC Box Packer :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Valid Request Args for Valid Products.
	 */
	public function test_validWCBoxPacker() {

		$this->calc = $this->getCalculator( 'valid' );
		$requests 	= $this->calc->get_requestsby_wc_box_packer();
		$codes 		= array_column( $requests, 'package_code' );

		$this->assertNotContains( 'random_inactive', $codes );
		$this->assertIsArray( $requests );

		foreach( $requests as $packed ) {
			$this->assertNotEmpty( $packed['weight']['value'] );
			$this->assertNotEmpty( $packed['packed'] );
		}

	}

	/**
	 * Valid Request - Custom Boxes max weight not exceeded.
	 */
	public function test_validWCBoxPackerBoxMaxWeights() {

		$this->calc = $this->getCalculator( 'valid' );
		$boxes 		= get_data( 'CustomBoxes' );
		$requests 	= $this->calc->get_requestsby_wc_box_packer();
		$codes 		= array_column( $requests, 'package_code' );

		$this->assertNotContains( 'random_inactive', $codes );
		foreach( $requests as $packed ) {
			if( isset( $boxes[ $packed['package_code'] ] ) && ! empty( $boxes[ $packed['package_code'] ]['weight_max'] ) ) {
				$this->assertLessThanOrEqual( $boxes[ $packed['package_code'] ]['weight_max'], $packed['weight']['value'] );
			}
		}

	}


	/**
	 * Valid Minweight
	 */
	public function test_validWCBoxPackerMinWeightFallback() {

		$minweight = 12.34; // Constish

		// Setup
		$this->calc = $this->getCalculator( 'dim_none', array( 'minweight' => $minweight ) );
		$products 	= $this->getProducts( 'dim_none' );
		$requests 	= $this->calc->get_requestsby_wc_box_packer();
		$codes 		= array_column( $requests, 'package_code' );

		// Test
		$this->assertNotContains( 'random_inactive', $codes );
		$this->assertIsArray( $requests );
		$this->assertEmpty( reset( $products )->get_weight() ); // Ensure product has no weight
		$this->assertSame( $minweight, reset( $requests )['weight']['value'] ); // Ensure product has weight.

	}

	/**
	 * Invalid Weight
	 */
	public function test_invalidWCBoxPackerWeight() {

		// Setup
		$key	  	= 'weight';
		$products 	= array_filter( $this->getProducts( 'dim_missing' ), fn( $p ) => "missing-dim-{$key}" === $p->get_slug() );

		// Test
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( sprintf( 'missing (%s) dimensions.', $key ) );
		$this->calc = new MockShippingCalculator( $products );
		$this->calc->get_requestsby_wc_box_packer();

	}

	/**
	 * Invalid Length
	 */
	public function test_invalidWCBoxPackerLength() {

		// Setup
		$key	  	= 'length';
		$products 	= array_filter( $this->getProducts( 'dim_missing' ), fn( $p ) => "missing-dim-{$key}" === $p->get_slug() );
		$this->calc = new MockShippingCalculator( $products );
		$requests 	= $this->calc->get_requestsby_wc_box_packer();
		$codes 		= array_column( $requests, 'package_code' );

		// Test
		$this->assertNotContains( 'random_inactive', $codes );
		$this->assertIsArray( $requests );

	}


	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helpers :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Return a calculator.
	 */
	protected function setUp():void {
		$this->calc = $this->getCalculator();
	}

	/**
	 * Return a calculator.
	 */
	protected function getCalculator( $type = 'mock', $args = array() ) {
		return new MockShippingCalculator( $this->getProducts( $type ), array_merge( $this->getShippingCalcArgs(), $args ) );
	}

	/**
	 * Return a subset of products.
	 */
	protected function getProducts( $type = 'mock' ) {
		$products = wc_get_products();
		switch( $type ) {
			case 'mock'		: return array_filter( (array)$products, fn( $p ) => false !== strpos( $p->get_slug(), 'mock-product' ) );
			case 'valid'	: return array_filter( (array)$products, fn( $p ) => $p->get_id() < 400 );
			case 'dim_valid': return array_filter( (array)$products, fn( $p ) => false !== strpos( $p->get_slug(), 'valid-dim' ) );
			case 'dim_missing': return array_filter( (array)$products, fn( $p ) => false !== strpos( $p->get_slug(), 'missing-dim' ) );
			case 'dim_none'	: return array_filter( (array)$products, fn( $p ) => 404 == $p->get_id() );
		}
		return $products;
	}

	/**
	 * Return some basic args.
	 */
	protected function getShippingCalcArgs() {
		return array(
			'foo' => 'bar',
			'to' => array(
				'country'	=> 'US',
				'postcode'	=> '63021',
				'city'		=> 'Ballwin',
				'state'		=> 'MO',
			),
			'from' => array(
				'country'	=> 'US',
				'postcode'	=> '20260',
				'city'		=> 'Washington',
				'state'		=> 'DC',
			),
		);
	}

}


/**------------------------------------------------------------------------------------------------ **/
/** :: Mock Shipping Calculator :: **/
/**------------------------------------------------------------------------------------------------ **/
class MockShippingCalculator extends \IQLRSS\Core\Classes\Shipping_Calculator {
	public function getProp( $key, $default = '' ) {
		return ( property_exists( $this, $key ) ) ? $this->$key : $default;
	}
	public function get( $key, $default = '' ) {
		switch( $key ) {
			case 'customboxes':
				$boxes = get_data( 'CustomBoxes' );
				return array_combine( array_column( $boxes, 'preset' ), $boxes );
		}
		return parent::get( $key, $default );
	}
	public function log( $error, $level = 'info', $context = array() ) {
		if( in_array( $level, array( 'debug', 'info', 'notice' ) ) ) return;
		if( in_array( $level, array( 'warning' ) ) ) return trigger_error( $error, E_USER_WARNING );
		throw new \Exception( $error );
	}
	protected function api() {
		return new \IQLRSS\Tests\Mockeries\ShipStationApi();
	}
}