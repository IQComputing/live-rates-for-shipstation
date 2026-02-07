<?php
/**
 * Ensure the Shipping Calculator works as expected.
 *
 * @todo extend this to test against mock WC_Cart data.
 *
 * :: Tests - Base
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
	protected function getCalculator( $type = 'mock' ) {
		if( 'mock' === $type ) return new MockShippingCalculator( $this->getProducts( $type ), $this->getShippingCalcArgs() );
		return new \IQLRSS\Core\Classes\Shipping_Calculator( $this->getProducts( $type ), $this->getShippingCalcArgs() );
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
			case 'dim_none'	: return array_filter( (array)$products, fn( $p ) => 400 == $p->get_id() );
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
	public function log( $error, $level = 'info', $context = array() ) {
		throw new \Exception( $error );
	}
	protected function api() {
		return new \IQLRSS\Tests\Mockeries\ShipStationApi();
	}
}