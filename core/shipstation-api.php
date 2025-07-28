<?php
/**
 * ShipStation API Helper
 *
 * :: API Requests
 * :: Helper Methods
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

class Shipstation_Api  {

	/**
	 * API Endpoint
	 *
	 * @var String
	 */
	protected $apiurl = 'https://api.shipstation.com'; // No trailing slash.


	/**
	 * WooCommerce Logger
	 *
	 * @var WC_Logger
	 */
	protected $logger = null;


	/**
	 * Key prefix
	 *
	 * @var String
	 */
	protected $prefix;


	/**
	 * Skip cache check
	 *
	 * @var Boolean
	 */
	protected $skip_cache = false;


	/**
	 * The API Key
	 *
	 * @var String
	 */
	private $key = '';


	/**
	 * Setup API
	 *
	 * @param Boolean $skip_cache
	 */
	public function __construct( $skip_cache = false ) {

		$this->prefix 	= \IQLRSS\Driver::get( 'slug' );
		$this->key 		= \IQLRSS\Driver::get_ss_opt( 'api_key', '', true );
		$this->skip_cache = (boolean)$skip_cache;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: API Requests :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Return a single carrier and all their info.
	 * This includes services and packages.
	 *
	 * @param String $carrier_code
	 *
	 * @return Array|WP_Error
	 */
	public function get_carrier( $carrier_code ) {

		$trans_key = $this->prefix_key( 'carriers' );
		$carriers = get_transient( $trans_key );
		$carrier = array();

		// No carriers cached - prime cache
		if( empty( $carriers ) || $this->skip_cache ) {
			$carriers = $this->get_carriers();
		}

		// Return Early - Carrierror!
		if( is_wp_error( $carriers ) ) {
			return $this->log( $carriers );

		// Return Early - Something went wrong getting carriers.
		} else if( ! isset( $carriers[ $carrier_code ] ) ) {
			return $this->log( new \WP_Error( 400, esc_html__( 'Could not find carrier information.', 'live-rates-for-shipstation' ) ) );
		}

		$service_key = sprintf( '%s_%s_services', $trans_key, $carrier_code );
		$services = get_transient( $service_key );

		$package_key = sprintf( '%s_%s_packages', $trans_key, $carrier_code );
		$packages = get_transient( $package_key );

		return array_merge( $carrier, array(
			'carrier'	=> $carriers[ $carrier_code ],
			'services'	=> ( ! empty( $services ) ) ? $services : array(),
			'packages'	=> ( ! empty( $packages ) ) ? $packages : array(),
		) );

	}


	/**
	 * Return an array of carriers.
	 * While getting carriers, set services and packages.
	 *
	 * @param String $carrier_code
	 *
	 * @return Array|WP_Error
	 */
	public function get_carriers( $carrier_code = '' ) {

		if( ! empty( $carrier_code ) ) {
			return $this->get_carrier( $carrier_code );
		}

		$trans_key = $this->prefix_key( 'carriers' );
		$carriers = get_transient( $trans_key );
		$data = array(
			'carriers' => ( ! empty( $carriers ) && ! $this->skip_cache ) ? $carriers : array(),
			'services' => array(),
			'packages' => array(),
		);

		if( empty( $data['carriers'] ) ) {

			$body = $this->make_request( 'get', 'carriers' );

			// Return Early - API Request error - see logs.
			if( is_wp_error( $body ) ) {
				return $body;
			}

			// Return Early - No carriers to work with.
			if( empty( $body['carriers'] ) ) {
				return array();
			}

			// We don't need all carrier data
			foreach( $body['carriers'] as $carrier ) {

				$data['carriers'][ $carrier['carrier_id'] ] = array_intersect_key( $carrier, array_flip( array(
					'carrier_id',
					'carrier_code',
					'account_number',
					'nickname',
					'friendly_name',
				) ) );

				$data['carriers'][ $carrier['carrier_id'] ]['is_shipstation'] 	= ( ! empty( $carrier['nickname'] ) && is_numeric( $carrier['nickname'] ) );
				$data['carriers'][ $carrier['carrier_id'] ]['name'] 			= $data['carriers'][ $carrier['carrier_id'] ]['friendly_name'];

				// Denote Manual Connected Carrier.
				if( ! $data['carriers'][ $carrier['carrier_id'] ]['is_shipstation'] ) {
					$data['carriers'][ $carrier['carrier_id'] ]['name'] .= ' ' . esc_html__( '(Manual)', 'live-rates-for-shipstation' );
				}

				if( isset( $carrier['services'] ) ) {
					foreach( $carrier['services'] as $service ) {
						$data['services'][ $carrier['carrier_id'] ][] = array_intersect_key( $service, array_flip( array(
							'carrier_id',
							'carrier_code',
							'service_code',
							'name',
							'domestic',
							'international',
							'is_multi_package_supported',
						) ) );
					}
				}

				if( isset( $carrier['packages'] ) ) {
					$data['packages'][ $carrier['carrier_id'] ] = $carrier['packages'];
				}
			}

			// Cache Carriers
			if( ! empty( $data['carriers'] ) ) {
				set_transient( $trans_key, $data['carriers'], MONTH_IN_SECONDS );
			}

			// Cache Services individually
			if( ! empty( $data['services'] ) ) {
				foreach( $data['services'] as $carrier_id => $service_arr ) {
					$service_key = sprintf( '%s_%s_services', $trans_key, $carrier_id );
					set_transient( $service_key, $service_arr, MONTH_IN_SECONDS );
				}
			}

			// Cache Packages individually
			if( ! empty( $data['packages'] ) ) {
				foreach( $data['packages'] as $carrier_id => $package_arr ) {
					$package_key = sprintf( '%s_%s_packages', $trans_key, $carrier_id );
					set_transient( $package_key, $package_arr, MONTH_IN_SECONDS );
				}
			}
		}

		return $data['carriers'];

	}


	/**
	 * Return an array of avaialble rates by a carrier.
	 *
	 * @note ShipStation does have a /rates/ endpoint, but it requires the customers address_line1
	 * In addition, it really is not much faster than the rates/estimate endpoint.
	 *
	 * @param Array $est_opts
	 *
	 * @return Array|WP_Error
	 */
	public function get_shipping_estimates( $est_opts ) {

		$body = $this->make_request( 'post', 'rates/estimate', $est_opts );

		// Return Early - API Request error - see logs.
		if( is_wp_error( $body ) ) {
			return $body;
		}

		$data = array();
		foreach( $body as $rate ) {

			// Sometimes rates come with error messages - skip them.
			if( ! empty( $rate['error_messages'] ) ) continue;

			// Sometimes rates can be cost $0, which isn't right - skip them.
			if( $rate['shipping_amount']['amount'] <= 0 ) continue;

			$est = array(
				'name'					=> $rate['service_type'],
				'code'					=> $rate['service_code'],
				'cost'					=> $rate['shipping_amount']['amount'],
				'currency'				=> $rate['shipping_amount']['currency'],
				'carrier_code'			=> $rate['carrier_id'],
				'carrier_nickname'		=> $rate['carrier_nickname'],
				'carrier_friendly_name'	=> $rate['carrier_friendly_name'],
				'carrier_name'			=> $rate['carrier_friendly_name'],
			);

			// Denote Manual Connected Carrier.
			if( ! empty( $est['carrier_nickname'] ) && ! is_numeric( $est['carrier_nickname'] ) ) {
				$est['carrier_name'] .= ' ' . esc_html__( '(Manual)', 'live-rates-for-shipstation' );
			}

			$data[] = $est;

		}

		return $data;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Make an API Request
	 *
	 * @param String $method
	 * @param String $endpoint
	 * @param Array $args
	 *
	 * @return Array|WP_Error $response
	 */
	protected function make_request( $method, $endpoint, $args = array() ) {

		// Return Early - No API Key found.
		if( empty( $this->key ) ) {
			return $this->log( new \WP_Error( 400, esc_html__( 'No ShipStation REST API Key found.', 'live-rates-for-shipstation' ) ), 'warning' );
		}

		$endpoint_url = $this->get_endpoint_url( $endpoint );
		$callback = ( 'post' == $method ) ? 'wp_remote_post' : 'wp_remote_get';
		$req_args = array(
			'headers' => array(
				'API-Key' => $this->key,
				'Content-Type' => 'application/json',
			),
		);

		if( ! empty( $args ) && is_array( $args ) ) {
			$req_args['body'] = wp_json_encode( $args );
		}

		$request = call_user_func( $callback, esc_url( $endpoint_url ), $req_args );
		$code = wp_remote_retrieve_response_code( $request );
		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		// Return Early - API encountered an error.
		if( is_wp_error( $request ) ) {
			return $this->log( $request );
		} else if( 200 != $code || ! is_array( $body ) ) {

			$err_code = 400;
			$err_msg = esc_html__( 'Error encountered during request.', 'live-rates-for-shipstation' );

			if( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
				$error = array_shift( $body['errors'] );
				$err_code = ( isset( $error['error_code'] ) && 'unspecified' != $error['error_code'] ) ? $error['error_code'] : $err_code;
				$err_msg = ( isset( $error['message'] ) ) ? $error['message'] : $err_msg;

				// Add a bit more context.
				if( 'unauthorized' == $err_code ) {
					$err_msg .= ' ' . esc_html__( '(API Key may be invalid)', 'live-rates-for-shipstation' );
				}

			}

			return $this->log( new \WP_Error( $err_code, $err_msg ) );
		}

		// Log API Request Result
		/* translators: %s is the API endpoint (example: carriers/rates). */
		$this->log( sprintf( esc_html__( 'ShipStation API Request to %s', 'live-rates-for-shipstation' ), $endpoint ), 'info', array(
			'args'		=> $args,
			'code'		=> $code,
			'reponse'	=> $body,
		) );

		return $body;

	}


	/**
	 * Return a ShipStation API Endpoint URL
	 *
	 * @param String $endpoint
	 *
	 * @return String $url
	 */
	protected function get_endpoint_url( $endpoint ) {
		return sprintf( '%s/v2/%s/', $this->apiurl, $endpoint );
	}


	/**
	 * Prefix a string with the plugin slug.
	 *
	 * @param String $str
	 * @param String $sep
	 *
	 * @return String
	 */
	protected function prefix_key( $key, $sep = '_' ) {

		return sprintf( '%s%s%s',
			$this->prefix, // Plugin slug
			preg_replace( '/[^-_]/', '', $sep ),
			$key
		);

	}


	/**
	 * Log error in WooCommerce
	 * Passthru method - log what's given and give it back.
	 *
	 * @param Mixed $error 		- String or WP_Error
	 * @param String $level 	- WooCommerce level (debug|info|notice|warning|error|critical|alert|emergency)
	 * @param Array $context
	 *
	 * @return Mixed - Return the error back.
	 */
	protected function log( $error, $level = 'debug', $context = array() ) {

		if( ! \IQLRSS\Driver::get_ss_opt( 'logging_enabled', false ) ) {
			return $error;
		}

		if( is_wp_error( $error ) ) {
			$error_msg = sprintf( '(%s) %s', $error->get_error_code(), $error->get_error_message() );
		} else {
			$error_msg = $error;
		}

		if( class_exists( '\WC_Logger' ) ) {

			if( null === $this->logger ) {
				$this->logger = \wc_get_logger();
			}

			$this->logger->log( $level, $error_msg, array_merge( $context, array( 'source' => 'live-rates-for-shipstation' ) ) );

		}

		return $error;

	}

}