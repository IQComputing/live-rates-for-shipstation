<?php
/**
 * Ensure the shipping method works as expected.
 */
namespace IQLRSS\Tests\WordPress;
use PHPUnit\Framework as PHPUnit;

class ShippingMethod extends PHPUnit\TestCase {

	/**
	 * Core Shipping Method
	 *
	 * @var \IQLRSS\Core\Shipping_Method_Shipstation
	 */
	protected $coreMethod;


	/**
	 * Setup the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		$this->coreMethod = new \IQLRSS\Core\Shipping_Method_Shipstation();

	}

}