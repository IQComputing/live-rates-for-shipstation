<?php
/**
 * Ensure the Shipping Calculator works as expected.
 *
 * :: Setup & Helpers
 * :: Mock Shipping Calculator
 */
namespace IQLRSS\Tests\Unit;
use PHPUnit\Framework as PHPUnit;

class ShippingCalculator extends PHPUnit\TestCase {

	/* Shipping Calculator Object */
	protected $calc;

	/**
	 * Ensure we can get.
	 */
	public function test_getBasic() {
		$this->assertSame( 'bar', $this->calc->get( 'foo', 'notFoo' ) );
	}

	/**
	 * Ensure we can get nested.
	 */
	public function test_getNested() {
		$this->assertSame( 'US', $this->calc->get( 'to.country', 'notUS' ) );
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
		$this->assertTrue( array_key_exists( 'data', $cart ) );
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
}