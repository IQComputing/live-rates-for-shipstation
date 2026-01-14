<?php
/**
 * An absolute mockery of the ShipStation API.
 *
 * @todo Maybe even add in some Faker?
 */
namespace IQLRSS\Tests\Mockeries;

class ShipStationApi extends \IQLRSS\Core\Api\Shipstation_Api {

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