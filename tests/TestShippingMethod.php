<?php
/**
 * Ensure the shipping method works as expected.
 */
namespace IQLRSS\Tests;
use PHPUnit\Framework as PHPUnit;

class TestShippingMethod extends PHPUnit\TestCase {

	/**
	 * Core Shipping Method
	 *
	 * @var \IQLRSS\Core\Shipping_Method_Shipstation
	 */
	protected $coreShip;


	/**
	 * Setup the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		$this->coreShip = new \IQLRSS\Core\Shipping_Method_Shipstation();

	}

}