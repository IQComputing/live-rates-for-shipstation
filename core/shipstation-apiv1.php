<?php
/**
 * ShipStation API Helper
 * 
 * orderNumber 	- Associated with WC_Order ID.'
 * orderId		- ShipStation Unique
 * orderKey 	- ShipStation Unique
 *
 * :: API Requests
 * :: Helper Methods
 * :: v2 API Request Overrides - Returns WP Errors to show invalid request.
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

class Shipstation_Apiv1 extends Shipstation_Api  {

	/**
	 * The API Key
	 *
	 * @var String
	 */
	private $key;


	/**
	 * The API Secret
	 * Shhhhh.
	 *
	 * @var String
	 */
	private $secret;


	/**
	 * Setup API
	 *
	 * @param Boolean $skip_cache
	 */
	public function __construct( $skip_cache = false ) {

		$this->prefix 	= \IQLRSS\Driver::get( 'slug' );
		$this->key 		= \IQLRSS\Driver::get_ss_opt( 'apiv1_key', '' );
		$this->secret 	= \IQLRSS\Driver::get_ss_opt( 'apiv1_secret', '' );
		$this->skip_cache = (boolean)$skip_cache;
		$this->cache_time = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 604800;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: API Requests :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Return ShipStation connected store data.
	 *
	 * @link https://www.shipstation.com/docs/api/stores/list
	 *
	 * @return Array|WP_Error
	 */
	public function get_stores() {

		$trans_key 	= $this->prefix_key( 'v1stores' );
		$stores 	= get_transient( $trans_key );
		$siteurl 	= get_bloginfo( 'siteurl' );

		if( empty( $stores ) || $this->skip_cache ) {

			$body = $this->make_request( 'get', 'stores', array( 'showInactive' => false ) );
			$stores = array();
			$curr_store_id = 0;

			// Return Early - API Request Error - Probably bad API keys.
			if( is_wp_error( $body ) ) {
				return $body;

			// Return Early - No stores found?
			} else if( empty( $body ) ) {
				return array();
			}

			foreach( $body as $store ) {

				// Skip anything that isn't WooCommerce
				if( 'WooCommerce' != $store['marketplaceName'] ) continue;

				// Save the currently connected store separately.
				if( false !== strpos( untrailingslashit( $store['integrationUrl'] ), untrailingslashit( $siteurl ) ) ) {
					$curr_store_id = $store['storeId'];
				}

				$stores[ $store['storeId'] ] = array_intersect_key( $store, array_flip( array(
					'storeId',
					'storeName',
					'marketplaceId',
					'marketplaceName',
					'refreshDate',
					'lastRefreshAttempt',
				) ) );

			}

			// Save store data
			if( ! empty( $stores ) ) {
				set_transient( $trans_key, $stores, $this->cache_time );
			}

			// Save current store as option.
			if( ! empty( $curr_store_id ) ) {
				\IQLRSS\Driver::set_ss_opt( 'store_id', $curr_store_id );
			}

		}

		return $stores;

	}


	/**
	 * Return a single carrier and all their info.
	 * This includes services and packages.
	 *
	 * @param String $carrier_code
	 *
	 * @return Array|WP_Error
	 */
	public function get_carrier( $carrier_code ) {

		$trans_key = $this->prefix_key( 'v1carriers' );
		$carriers = get_transient( $trans_key );

		// No carriers cached - prime cache
		if( empty( $carriers ) || $this->skip_cache ) {
			$carriers = $this->get_carriers( '', array( 'services' => true, 'packages' => true ) );
		}

		// Return Early - Carrierror! Skip log since that should be called in get_carriers()
		if( is_wp_error( $carriers ) ) {
			return $carriers;

		// Return Early - Something went wrong getting carriers.
		} else if( ! isset( $carriers[ $carrier_code ] ) ) {
			return $this->log( new \WP_Error( 404, esc_html__( '[v1] Could not find carrier information.', 'live-rates-for-shipstation' ) ) );
		}

		return array(
			'carrier'	=> $carriers[ $carrier_code ],
			'services'	=> $this->get_services( $carriers[ $carrier_code ]['carrier_code'] ),
			'packages'	=> $this->get_packages( $carriers[ $carrier_code ]['carrier_code'] ),
		);

	}


	/**
	 * Return an array of carriers.
	 * While getting carriers, set services and packages.
	 *
	 * @link https://www.shipstation.com/docs/api/carriers/list
	 *
	 * @param String $carrier_code
	 * @param Array $args - Array(
	 * 		'services' => false - Additional API requests to retrieve services.
	 * 		'packages' => false - Additional API requests to retrieve packages.
	 * )
	 *
	 * @return Array|WP_Error
	 */
	public function get_carriers( $carrier_code = '', $args = array() ) {

		if( ! empty( $carrier_code ) ) {
			return $this->get_carrier( $carrier_code );
		}

		$trans_key = $this->prefix_key( 'v1carriers' );
		$carriers = get_transient( $trans_key );
		$carriers = array();
		$data = array(
			'carriers' => ( ! empty( $carriers ) && ! $this->skip_cache ) ? $carriers : array(),
			'services' => array(),
			'packages' => array(),
		);

		if( empty( $data['carriers'] ) || $this->skip_cache ) {

			$body = $this->make_request( 'get', 'carriers' );

			// Return Early - API Request error - see logs.
			if( is_wp_error( $body ) ) {
				return $body;
			}

			// Return Early - No carriers to work with.
			if( empty( $body ) ) {
				return array();
			}

			// We don't need all carrier data
			foreach( $body as $carrier_data ) {

				// Align with v2 key names for ease.
				$carrier['name'] 			= $carrier_data['name'];
				$carrier['friendly_name'] 	= $carrier_data['name'];
				$carrier['carrier_code'] 	= $carrier_data['code'];
				$carrier['account_number'] 	= $carrier_data['accountNumber'];
				$carrier['carrier_id'] 		= $carrier_data['shippingProviderId'];
				$carrier['nickname']	 	= $carrier_data['nickname'];
				$carrier['is_shipstation'] 	= ( ! empty( $carrier_data['primary'] ) );

				// Denote Manual Connected Carrier.
				if( ! $carrier['is_shipstation'] ) {
					$carrier['name'] .= ' ' . esc_html__( '(Manual)', 'live-rates-for-shipstation' );
				}

				$data['carriers'][ $carrier['carrier_id'] ] = $carrier;

			}

			// Cache Carriers
			if( ! empty( $data['carriers'] ) ) {
				set_transient( $trans_key, $data['carriers'], $this->cache_time );
			}

			// Maybe add carrier services
			if( ! empty( $data['carriers'] ) && $args['services'] ) {
				foreach( $data['carriers'] as $carrier_id => $carrier_arr ) {

					$services = $this->get_services( $carrier_arr['carrier_code'] );
					if( is_wp_error( $services ) || empty( $services ) ) continue;
					$data['services'][ $carrier_id ] = $services;

				}
			}

			// Maybe add carrier packages
			if( ! empty( $data['carriers'] ) && $args['packages'] ) {
				foreach( $data['carriers'] as $carrier_id => $carrier_arr ) {

					$packages = $this->get_packages( $carrier_arr['carrier_code'] );
					if( is_wp_error( $packages ) || empty( $packages ) ) continue;
					$data['packages'][ $carrier_id ] = $packages;

				}
			}

		}

		return $data['carriers'];

	}


	/**
	 * Return an array of services for a specific carrier.
	 * 
	 * @param String $carrier_code
	 * 
	 * @return Array|WP_Error
	 */
	public function get_services( $carrier_code ) {

		$trans_key 	= sprintf( '%s_%s_services', $this->prefix_key( 'v1carriers' ), $carrier_code );
		$services 	= get_transient( $trans_key );

		if( empty( $services ) || $this->skip_cache ) {

			$body = $this->make_request( 'get', 'carriers/listservices', array( 'carrierCode' => $carrier_code ) );
			$services = array();

			// Return Early - API Request Error - Probably bad API keys.
			if( is_wp_error( $body ) ) {
				return $body;

			// Return Early - No stores found?
			} else if( empty( $body ) ) {
				return array();
			}

			// Setup carriers to associate carrier_id
			$carriers = $this->get_carriers();
			if( ! is_wp_error( $carriers ) && ! empty( $carriers ) ) {
				$carrier_assoc = array_combine(
					wp_list_pluck( $carriers, 'carrier_code' ),
					array_keys( $carriers ),
				);
			}

			foreach( $body as $service_data ) {

				$service['carrier_code'] 	= $service_data['carrierCode'];
				$service['service_code'] 	= $service_data['code'];
				$service['name'] 			= $service_data['name'];
				$service['domestic'] 		= $service_data['domestic'];
				$service['international'] 	= $service_data['international'];
				$service['is_multi_package_supported'] = 1;
				$service['carrier_id']		= ( isset( $carrier_assoc[ $service['carrier_code'] ] ) ) ? $carrier_assoc[ $service['carrier_code'] ] : '';

				$services[ $service['service_code'] ] = $service;

			}

			// Save store data
			if( ! empty( $services ) ) {
				set_transient( $trans_key, $services, $this->cache_time );
			}

		}

		return $services;

	}


	/**
	 * Return an array of packages for a specific carrier.
	 * 
	 * @param String $carrier_code
	 * 
	 * @return Array|WP_Error
	 */
	public function get_packages( $carrier_code ) {

		$trans_key 	= sprintf( '%s_%s_packages', $this->prefix_key( 'v1carriers' ), $carrier_code );
		$packages 	= get_transient( $trans_key );

		if( empty( $packages ) || $this->skip_cache ) {

			$body = $this->make_request( 'get', 'carriers/listpackages', array( 'carrierCode' => $carrier_code ) );
			$packages = array();

			// Return Early - API Request Error - Probably bad API keys.
			if( is_wp_error( $body ) ) {
				return $body;

			// Return Early - No packages found?
			} else if( empty( $body ) ) {
				return array();
			}

			// Setup carriers to associate carrier_id
			$carriers = $this->get_carriers();
			if( ! is_wp_error( $carriers ) && ! empty( $carriers ) ) {
				$carrier_assoc = array_combine(
					wp_list_pluck( $carriers, 'carrier_code' ),
					array_keys( $carriers ),
				);
			}

			foreach( $body as $package_data ) {

				$package['carrier_code'] 	= $package_data['carrierCode'];
				$package['package_code'] 	= $package_data['code'];
				$package['name'] 			= $package_data['name'];
				$package['domestic'] 		= $package_data['domestic'];
				$package['international'] 	= $package_data['international'];
				$package['carrier_id']		= ( isset( $carrier_assoc[ $package['carrier_code'] ] ) ) ? $carrier_assoc[ $package['carrier_code'] ] : '';

				$packages[ $package['package_code'] ] = $package;

			}

			// Save store data
			if( ! empty( $packages ) ) {
				set_transient( $trans_key, $packages, $this->cache_time );
			}

		}

		return $packages;

	}


	/**
	 * Retrieve data for a specific order.
	 * Try to pull from the WC_Order metadata.
	 * Otherwise, query orders with store ID and cache.
	 *
	 * @link https://www.shipstation.com/docs/api/orders/get-order
	 *
	 * @param Integer $order_id - WooCommerce Order ID. 
	 *
	 * @return Array|WP_Error
	 */
	public function get_order( $order_id ) {

		$order = array();

		if( ! $this->skip_cache ) {

			$wc_order = wc_get_order( $order_id );
			$order = $wc_order->get_meta( '_shipstation_order', true );

		}

		if( empty( $order ) || $this->skip_cache ) {

			$orders = $this->get_orders( array( 'orderNumber' => $order_id ) );
			if( is_wp_error( $orders ) ) {
				return $orders;
			} else if( ! empty( $orders ) ) {
				$order = array_shift( $orders );
			}

		}

		return $order;

	}


	/**
	 * Return data for multiple orders.
	 * Cache the data onto the WooCommerce order as metadata.
	 * If WC_Order cannot be found, do not cache data.
	 * 
	 * Cache the found order IDs as based on query hash.
	 *
	 * @link https://www.shipstation.com/docs/api/orders/list-orders
	 *
	 * @param Array $args - Array of URL query args. See link above for details.
	 *
	 * @return Array|WP_Error
	 */
	public function get_orders( $args = array() ) {

		// Set the transient key to a hash of the arguments.
		// The transient should hold an array of WC_Order IDs associated with the query.
		$trans_key = $this->prefix_key( 'v1orders' );
		if( ! empty( $args ) ) {
			ksort( $args );
			$trans_key .= sprintf( '_%s', md5( serialize( $args ) ) );
		}

		$orders = array();
		$order_ids = get_transient( $trans_key );

		// Maybe pull from WC_Order
		if( is_array( $order_ids ) && ! empty( $order_ids ) && ! $this->skip_cache ) {

			$wc_orders = wc_get_orders( array(
				'include' => array_map( 'absint', $order_ids ),
			) );

			foreach( $wc_orders as $wc_order ) {
				$orders[ $wc_order->get_id() ] = $wc_order->get_meta_data( '_shipstation_order', true );
			}

		}

		// Pull from API and associate with known WC_Orders.
		if( empty( $orders ) || $this->skip_cache ) {

			$body = $this->make_request( 'get', 'orders', array_merge( array(
				'storeId' => \IQLRSS\Driver::get_ss_opt( 'store_id' ),
			), $args ) );

			// Return Early - API Request error - see logs.
			if( is_wp_error( $body ) ) {
				return $body;
			}

			// Return Early - No orders to work with.
			if( empty( $body['orders'] ) ) {
				return array();
			}

			// Prime order cache.
			wc_get_orders( array(
				'include' => array_map( 'absint', wp_list_pluck( $body['orders'], 'orderNumber' ) ),
			) );

			foreach( $body['orders'] as $order_arr ) {

				// Skip any orders that are not found in WooCommerce.
				$wc_order = wc_get_order( $order_arr['orderNumber'] );
				if( ! is_a( $wc_order, 'WC_Order' ) ) continue;

				$wc_order->update_meta_data( '_shipstation_order', $order_arr );
				$wc_order->update_meta_data( '_shipstation_order_fetched', time() );
				$wc_order->save_meta_data();

				$orders[ $wc_order->get_id() ] =  $order_arr;

			}

			// Only cache results with multiple orders.
			if( count( $orders ) > 1 ) {
				set_transient( $trans_key, array_keys( $orders ), HOUR_IN_SECONDS );
			}

		}

		return $orders;

	}


	/**
	 * Update multiple orders.
	 * This actually requires the entire Order entity from ShipStation.
	 * WC_Order _shipstation_order meta for entity.
	 * WC_Order _shipstation_order_fetched for timestamp to determine freshness.
	 *
	 * @link https://www.shipstation.com/docs/api/orders/create-update-multiple-orders
	 *
	 * @param Array $order_arr - Multidimensional array of orders to update. 
	 *
	 * @return Array|WP_Error
	 */
	public function update_orders( $order_arr ) {

		$orders = array_filter( (array)$order_arr, function( $order ) {
			return ( is_array( $order ) && isset( $order['orderNumber'], $order['orderKey'] ) );
		} );

		// Return Early - Skip the log but o
		if( empty( $orders ) ) {
			return $this->log( new \WP_Error( 400, esc_html__( '[v1] Empty Orders. Data may be missing orderNumber or orderKey.', 'live-rates-for-shipstation' ) ), 'warning', array(
				'orders' => $order_arr,
			) );
		}

		$body = $this->make_request( 'post', 'orders/createorders', $orders );

		// Return Early - API Request error - see logs.
		if( is_wp_error( $body ) ) {
			return $body;
		}

		// Return Early - Something unknown went wrong.
		if( empty( $body['results'] ) ) {
			return $this->log( new \WP_Error( 400, esc_html__( '[v1] ShipStation orders tried to update, but API returned no results.', 'live-rates-for-shipstation' ) ), 'warning' );
		}

		// Track Successes
		$success = array_filter( $body['results'], function( $orderish ) {
			return ( isset( $orderish['success'] ) && $orderish['success'] );
		} );
		if( ! empty( $success ) ) {
			$success = array_combine( wp_list_pluck( $success, 'orderNumber' ), $success );
		}

		// Track Errors
		$errors = array_filter( $body['results'], function( $orderish ) {
			return ( isset( $orderish['errorMessage'] ) && ! empty( $orderish['errorMessage'] ) );
		} );
		if( ! empty( $errors ) ) {
			$errors = array_combine( wp_list_pluck( $errors, 'orderNumber' ), $errors );
		}

		if( ! empty( $errors ) ) {
			foreach( $errors as $order_id => $err_arr ) {
				$this->log( sprintf( '[v1] Order Update Error: %d | %s', $order_id, $err_arr['errorMessage'] ), 'error', array( 'result' => $err_arr ) );
			}
		}

		// Denote completed orders.
		return array(
			'success'	=> $success,
			'error'		=> $errors,
		);

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
		if( empty( $this->key ) || empty( $this->secret ) ) {
			return $this->log( new \WP_Error( 400, esc_html__( '[v1] ShipStation API Key and Secret required.', 'live-rates-for-shipstation' ) ), 'warning' );
		}

		$endpoint_url = $this->get_endpoint_url( $endpoint );
		$callback = ( 'post' == $method ) ? 'wp_remote_post' : 'wp_remote_get';
		$req_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( sprintf( '%s:%s', $this->key, $this->secret ) ),
				'Content-Type' => 'application/json',
			),
		);

		if( ! empty( $args ) && is_array( $args ) ) {
			if( 'get' == $method ) {
				$req_args['body'] = $args;
			} else if( 'post' == $method ) {
				$req_args['body'] = wp_json_encode( $args );
			}
		}

		$request = call_user_func( $callback, esc_url( $endpoint_url ), $req_args );
		$code = wp_remote_retrieve_response_code( $request );
		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		// Return Early - API encountered an error.
		if( is_wp_error( $request ) ) {

			// The API doesn't seem to return a proper error if the API keys are invalid.
			if( false !== strpos( $request->get_error_message(), 'cURL error 28' ) ) {
				$request = new \WP_Error( 401, esc_html__( '[v1] Operation Timeout (API Key or Secret may be invalid)', 'live-rates-for-shipstation' ) );
			}

			return $this->log( $request, 'error' );
			
		} else if( 200 != $code || ! is_array( $body ) ) {

			$message = '';
			if( isset( $body['Message'] ) ) {
				$message = '[v1] ' . $body['Message'];
			} else {
				$message = esc_html__( '[v1] Error encountered during request.', 'live-rates-for-shipstation' );
			}

			if( isset( $body['ModelState'] ) ) {

				foreach( $body['ModelState'] as $field => $msg_arr ) {
					$message .= sprintf( ' ( %s - %s ) |', $field, implode( ', ', $msg_arr ) );
				}

				$message = rtrim( $message, ' |' );
			}

			return $this->log( new \WP_Error( $code, $message ), 'error', array(
				'body' => $body,
			) );

		}

		// Log API Request Result
		/* translators: %s is the API endpoint (example: carriers/rates). */
		$this->log( sprintf( esc_html__( '[v1] ShipStation API Request to %s', 'live-rates-for-shipstation' ), $endpoint ), 'info', array(
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

		return sprintf( '%s/%s',
			'https://ssapi.shipstation.com',
			$endpoint
		);

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: v2 API Request Overrides :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Disable v1 API for known v2 requests (that this plugin supports).
	 *
	 * @return WP_Error
	 */
	public function get_shipping_estimates( $est_opts ) {
		return $this->log( new \WP_Error( 400, esc_html__( 'Live Rates for ShipStation v1 API Class does not support this endpoint. Use the v2 API Class: \IQLRSS\Core\Shipstation_Api', 'live-rates-for-shipstation' ) ) );
	}

}