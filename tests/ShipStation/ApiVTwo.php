<?php
/**
 * ShipStation v2 Tests
 *
 * @todo Update api cache / trans tests.
 * @todo Consider the helpfulness of the "No API Key" tests. Just one test should suffice?
 *
 * :: Mock API Tests
 * :: No API Key
 * :: Utility Tests
 */
namespace IQLRSS\Tests\ShipStation;
use PHPUnit\Framework as PHPUnit;

class ApiVTwo extends PHPUnit\TestCase {

	/**
	 * API Url
	 *
	 * @var String
	 */
	public $api_url = 'https://api.shipstation.com';


	/**
	 * Mock ShipStation API
	 *
	 * @var \IQLRSS\Tests\Mockeries\MockShipStationApi
	 */
	protected $api;


	/**
	 * Setup the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		$this->api = make_mockery( 'ShipStationApi' );

	}



	/* -------------------------------------------------------------------------------- **
	 * :: Mock API Tests ::
	** -------------------------------------------------------------------------------- */
	/**
	 * Ensure get_carriers() sets the proper transients.
	 */
	public function test_getCarrier() {

		$data = $this->api->get_carrier( 'se-shipstation' );
		$this->assertTrue( ! empty( $data['carrier'] ), 'Carrier empty.' );

	}


	/**
	 * Ensure get_carriers() sets the proper transients.
	 */
	public function test_getCarriers() {

		$data = $this->api->get_carriers();
		$this->assertTrue( ! empty( $data ), 'Carriers empty.' );
		$this->assertTrue( ! empty( $data['se-shipstation'] ), 'Carriers Carrier empty.' );

	}



	/* -------------------------------------------------------------------------------- **
	 * :: No API Key ::
	** -------------------------------------------------------------------------------- */
	/**
	 * Ensure that the API returns a 400 WP_Error when no API Key is found.
	 */
	public function test_apiGetCarrierNoKey() {

		$this->api->key = '';
		$result = $this->api->get_carrier( 'foo' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 401, $result->get_error_code() );

		$this->api->set_defaults();

	}


	/**
	 * Ensure that the API returns a 400 WP_Error when no API Key is found.
	 */
	public function test_apiGetCarriersNoKey() {

		$this->api->key = '';
		$result = $this->api->get_carriers();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 401, $result->get_error_code() );

		$this->api->set_defaults();

	}


	/**
	 * Ensure that the API returns a 400 WP_Error when no API Key is found.
	 */
	public function test_apiShippingEstimates() {

		$this->api->key = '';
		$result = $this->api->get_shipping_estimates( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 401, $result->get_error_code() );

		$this->api->set_defaults();

	}



	/* -------------------------------------------------------------------------------- **
	 * :: Utility Tests ::
	** -------------------------------------------------------------------------------- */
	/**
	 * Ensure unit conversions convert.
	 */
	public function test_converUnitTerm() {

		$terms = array(
			'kg'	=> 'kilogram',
			'g'		=> 'gram',
			'lbs'	=> 'pound',
			'oz'	=> 'ounce',
			'cm'	=> 'centimeter',
			'in'	=> 'inch',
		);

		foreach( $terms as $unit => $expected ) {
			$this->assertSame( $expected, $this->api->convert_unit_term( $unit ) );
		}

	}


	/**
	 * Ensure the Endpoint URL is what we know it currently to be.
	 */
	public function test_getEndpointUrl() {
		$this->assertStringContainsString( $this->api_url, $this->api->get_endpoint_url( 'foo' ) );
	}


	/**
	 * Ensure that the prefix is mock and non-mock.
	 */
	public function test_prefixKey() {

		$this->assertSame( 'mockiqlrss_foo', $this->api->prefix_key( 'foo' ) );
		$this->assertSame( 'mockiqlrss-foo', $this->api->prefix_key( 'foo', '-' ) );

		$coreApi = new \IQLRSS\Core\Api\Shipstation();
		$mirrorMock = new \ReflectionMethod( $coreApi, 'prefix_key' );
		$this->assertSame( 'iqlrss_foo', $mirrorMock->invoke( $coreApi, 'foo' ) );
		$this->assertSame( 'iqlrss-foo', $mirrorMock->invoke( $coreApi, 'foo', '-' ) );

	}

}