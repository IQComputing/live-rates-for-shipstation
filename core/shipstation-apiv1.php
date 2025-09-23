<?php
/**
 * ShipStation API Helper
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

		$trans_key 	= $this->prefix_key( 'stores' );
		$stores 	= get_transient( $trans_key );
		$siteurl 	= get_bloginfo( 'siteurl' );

		if( $this->skip_cache || empty( $stores ) ) {

			$stores = $this->make_request( 'get', 'stores', array( 'showInactive' => false ) );
			if( ! is_wp_error( $stores ) && ! empty( $stores ) ) {

				// @todo Filter specific order data. No need to save everything.
				set_transient( $trans_key, $stores, $this->cache_time );
				
				// Attempt to discern current store and store it.
				$curr_store = array();
				foreach( $stores as $store ) {
					if( 'WooCommerce' != 'marketplaceName' ) continue;
					if( false === strpos( untrailingslashit( $store['integrationUrl'] ), untrailingslashit( $siteurl ) ) ) continue;
					$curr_store = $store;
					break;
				}

				// @todo Filter specific order data. No need to save everything.
				// @todo Save as a ShipStation option, skip if it exists.
				// \IQLRSS\Driver::get_ss_opt( 'store', '' );

			}

		}

		return $stores;

	}


	/**
	 * Return data for multiple orders.
	 * Manage orders cache as well.
	 *
	 * @link https://www.shipstation.com/docs/api/orders/list-orders
	 *
	 * @param Array $args - Array of URL query args. See link above for details.
	 * @param Boolean $skip_cache
	 *
	 * @return Array|WP_Error
	 */
	public function get_orders( $args = array() ) {

		$trans_key = $this->prefix_key( 'orders' );
		$orders = get_transient( $trans_key );

		if( $this->skip_cache || empty( $orders ) ) {

			$orders = $this->make_request( 'get', 'orders', $args );
			if( ! is_wp_error( $orders ) && ! empty( $orders ) ) {

				// @todo Filter specific order data. No need to save everything.

				set_transient( $trans_key, $orders, $this->cache_time );
			}

		}

		return $orders;

	}


	/**
	 * Retrieve data for a specific order.
	 * Manage orders cache as well.
	 *
	 * @link https://www.shipstation.com/docs/api/orders/get-order
	 *
	 * @param Integer $order_id - WooCommerce Order ID. 
	 *
	 * @return Array|WP_Error
	 */
	public function get_order( $order_id ) {

		$trans_key = $this->prefix_key( 'orders' );
		$orders = get_transient( $trans_key );

		if( empty( $orders['orders'][ $order_id ] ) ) {

			$order = $this->make_request( 'get', sprintf( 'orders/%d', $order_id ) );
			if( ! is_wp_error( $order ) && ! empty( $order ) ) {
				// @todo Relate to WooCommerce order somehow. WC_Order Metadata?
				// @todo Filter specific order data. No need to save everything.
			}

		}

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
			return $this->log( new \WP_Error( 400, esc_html__( 'ShipStation [v1] API Key and Secret required.', 'live-rates-for-shipstation' ) ), 'warning' );
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
			if( 'post' == $method ) {
				$req_args['body'] = wp_json_encode( $args );
			} else if( 'get' == $method ) {
				$endpoint_url = add_query_arg( $args, $endpoint_url );
			}
		}

		$request = call_user_func( $callback, esc_url( $endpoint_url ), $req_args );
		$code = wp_remote_retrieve_response_code( $request );
		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		// Return Early - API encountered an error.
		if( is_wp_error( $request ) ) {
			return $this->log( $request );
		} else if( 200 != $code || ! is_array( $body ) ) {

			$message = '';
			if( isset( $body['Message'] ) ) {
				$message = $body['Message'];
			} else {
				$message = esc_html__( 'Error encountered during [v1] request.', 'live-rates-for-shipstation' );
			}

			if( isset( $body['ModelState'] ) ) {

				foreach( $body['ModelState'] as $field => $msg_arr ) {
					$message .= sprintf( ' ( %s - %s ) |', $field, implode( ', ', $msg_arr ) );
				}

				$message = rtrim( $message, ' |' );
			}

			return $this->log( new \WP_Error( $code, $message ) );
		}

		// Log API Request Result
		/* translators: %s is the API endpoint (example: carriers/rates). */
		$this->log( sprintf( esc_html__( 'ShipStation [v1] API Request to %s', 'live-rates-for-shipstation' ), $endpoint ), 'info', array(
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
	public function get_carrier( $carrier_code ) {
		return $this->log( new \WP_Error( 400, esc_html__( 'Live Rates for ShipStation v1 API Class does not support this endpoint. Use the v2 API Class: \IQLRSS\Core\Shipstation_Api', 'live-rates-for-shipstation' ) ) );
	}


	/**
	 * Disable v1 API for known v2 requests (that this plugin supports).
	 *
	 * @return WP_Error
	 */
	public function get_carriers( $carrier_code = '' ) {
		return $this->log( new \WP_Error( 400, esc_html__( 'Live Rates for ShipStation v1 API Class does not support this endpoint. Use the v2 API Class: \IQLRSS\Core\Shipstation_Api', 'live-rates-for-shipstation' ) ) );
	}


	/**
	 * Disable v1 API for known v2 requests (that this plugin supports).
	 *
	 * @return WP_Error
	 */
	public function get_shipping_estimates( $est_opts ) {
		return $this->log( new \WP_Error( 400, esc_html__( 'Live Rates for ShipStation v1 API Class does not support this endpoint. Use the v2 API Class: \IQLRSS\Core\Shipstation_Api', 'live-rates-for-shipstation' ) ) );
	}

}