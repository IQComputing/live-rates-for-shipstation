<?php
/**
 * Ensure the Shipping Calculator works as expected.
 */
namespace IQLRSS\Tests\Unit;
use PHPUnit\Framework as PHPUnit;

class ShippingCalculator extends PHPUnit\TestCase {

	/**
	 * Setup the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {}

	/**
	 * Ensure that the prefix is mock and non-mock.
	 */
	public function test_options() {
		$this->assertSame( 0, \IQLRSS\Driver::get_ss_opt( 'logging_enabled', 0, true ) );
	}

}