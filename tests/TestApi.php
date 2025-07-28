<?php
/**
 * Test the ShipStation API.
 *
 * @todo Update api cache / trans tests. 
 *
 * :: No API Key
 * :: Utility Tests
 * :: Mock API Tests
 */
namespace IQLRSS\Tests;
use PHPUnit\Framework as PHPUnit;

class TestApi extends PHPUnit\TestCase {

	/**
	 * API Url
	 *
	 * @var String
	 */
	public $api_url = 'https://api.shipstation.com';


	/**
	 * Mock ShipStation API
	 *
	 * @var \IQLRSS\Tests\MockShipStationApi
	 */
	protected $api;


	/**
	 * Setup the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {

		$this->api = new MockShipStationApi();

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
		$this->assertEquals( 400, $result->get_error_code() );

		$this->api->set_defaults();

	}


	/**
	 * Ensure that the API returns a 400 WP_Error when no API Key is found.
	 */
	public function test_apiGetCarriersNoKey() {

		$this->api->key = '';
		$result = $this->api->get_carriers();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 400, $result->get_error_code() );

		$this->api->set_defaults();

	}


	/**
	 * Ensure that the API returns a 400 WP_Error when no API Key is found.
	 */
	public function test_apiShippingEstimates() {

		$this->api->key = '';
		$result = $this->api->get_shipping_estimates( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 400, $result->get_error_code() );

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
	 * Ensure that array key intersection works as expected.
	 */
	public function test_getIntersection() {

		$given = array(
			'foo' => 'bar',

			'expected1' => 'value',
			'expected2' => 'value',
			'expected3' => 'value',
		);

		$expected = array(
			'expected1' => 'value',
			'expected2' => 'value',
			'expected3' => 'value',
		);

		$this->assertSame( $expected, $this->api->get_intersection( $given, array_keys( $expected ) ) );

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

		$coreApi = new \IQLRSS\Core\Shipstation_Api();
		$mirrorMock = new \ReflectionMethod( $coreApi, 'prefix_key' );
		$mirrorMock->setAccessible( true );
		$this->assertSame( 'iqlrss_foo', $mirrorMock->invoke( $coreApi, 'foo' ) );
		$this->assertSame( 'iqlrss-foo', $mirrorMock->invoke( $coreApi, 'foo', '-' ) );

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

}


/**
 * Create a Mockery of the core plugin ShipStation API.
 */
class MockShipStationApi extends \IQLRSS\Core\Shipstation_Api {

	/**
	 * The API Key
	 *
	 * @var String
	 */
	protected $key;


	/**
	 * Override the construct to define our own setup.
	 */
	public function __construct() {
		$this->set_defaults();
	}


	/**
	 * Setup mock default properties.
	 *
	 * @return void
	 */
	public function set_defaults() {

		$this->prefix = 'mockiqlrss';
		$this->key = 'mockiqlrss';
		$this->skip_cache = true;
		$this->cache_time = 1;

	}


	/**
	 * Override variables, even private vars, because it's a test.
	 *
	 * @param String $prop
	 * @param Mixed $val
	 *
	 * @return Mixed
	 */
	public function __get( $prop ) {
		return ( isset( $this->{$prop} ) ) ? $this->{$prop} : false;
	}


	/**
	 * Override variables, even private vars, because it's a test.
	 *
	 * @param String $prop
	 * @param Mixed $val
	 *
	 * @return void
	 */
	public function __set( $prop, $val ) {
		$this->{$prop} = $val;
	}


	/**
	 * Allow calling protected methods.
	 *
	 * @param String $method
	 * @param Array $args
	 *
	 * @return Mixed
	 */
	public function __call( $method, $args ) {
		return call_user_func( array( $this, $method ), ...$args );
	}


	/**
	 * Return static data when making API request.
	 *
	 * @return Array
	 */
	public function make_request( $method, $endpoint, $args = array() ) {

		if( empty( $this->key ) ) {
			return parent::make_request( $method, $endpoint, $args );
		}


		switch( $endpoint ) {

			case 'carriers':
				return array( 'carriers' => array( array(

					// Faux API values that should be removed by the API method.
					'foo' => 'bar',

					// Expected API Values
					'carrier_id'	=> 'se-shipstation',
					'carrier_code'	=> 'shipstation',
					'account_number'=> '123',
					'nickname'		=> 'ShipStation',
					'friendly_name'	=> 'Ship Station',
					'services'		=> array( array(

						// Faux API values that should be removed by the API method.
						'foo' => 'bar',

						// Expected API Values
						'carrier_id'	=> 'se-shipstation',
						'carrier_code'	=> 'shipstation',
						'service_code'	=> 'shipstation_service',
						'name'			=> 'ShipStation Service',
						'domestic'		=> '0',
						'international'	=> '0',
						'is_multi_package_supported' => '0',
					) ),
					'packages' => array( array(

						// Faux API values that should be removed by the API method.
						'foo' => 'bar',

						// Expected API values
						'package_id' => 'se-shipstation-package',
						'package_code' => 'shipstation_package',
						'name' => 'shipstation_package',
						'dimensions' => array(
							'unit' => 'inch',
							'length'	=> 1,
							'width'		=> 2,
							'height'	=> 3,
						),
						'description' => 'ShipStation Package',
					) )
				) ) );
			break;

			case '':
			break;
		}

	}

}