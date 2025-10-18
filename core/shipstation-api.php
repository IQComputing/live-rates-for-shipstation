<?php
/**
 * ShipStation API Helper
 *
 * @link https://docs.shipstation.com/openapi
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
	 * Skip cache check
	 *
	 * @var Boolean
	 */
	public $skip_cache = false;


	/**
	 * Seconds to hold cache.
	 * Defaults to 1 week.
	 *
	 * @var Integer
	 */
	protected $cache_time;


	/**
	 * Key prefix
	 *
	 * @var String
	 */
	protected $prefix;


	/**
	 * WooCommerce Logger
	 *
	 * @var WC_Logger
	 */
	protected $logger = null;


	/**
	 * The API Key
	 *
	 * @var String
	 */
	private $key;


	/**
	 * Setup API
	 *
	 * @param Boolean $skip_cache
	 */
	public function __construct( $skip_cache = false ) {

		$this->prefix 	= \IQLRSS\Driver::get( 'slug' );
		$this->key 		= \IQLRSS\Driver::get_ss_opt( 'api_key', '' );
		$this->skip_cache = (boolean)$skip_cache;
		$this->cache_time = defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : 604800;

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

		// No carriers cached - prime cache
		if( empty( $carriers ) || $this->skip_cache ) {
			$carriers = $this->get_carriers();
		}

		// Return Early - Carrierror! Skip log since that should be called in get_carriers()
		if( is_wp_error( $carriers ) ) {
			return $carriers;

		// Return Early - Something went wrong getting carriers.
		} else if( ! isset( $carriers[ $carrier_code ] ) ) {
			return $this->log( new \WP_Error( 404, esc_html__( 'Could not find carrier information.', 'live-rates-for-shipstation' ) ) );
		}

		$service_key = sprintf( '%s_%s_services', $trans_key, $carrier_code );
		$services = get_transient( $service_key );

		$package_key = sprintf( '%s_%s_packages', $trans_key, $carrier_code );
		$packages = get_transient( $package_key );

		return array(
			'carrier'	=> $carriers[ $carrier_code ],
			'services'	=> ( ! empty( $services ) ) ? $services : array(),
			'packages'	=> ( ! empty( $packages ) ) ? $packages : array(),
		);

	}


	/**
	 * Return an array of carriers.
	 * While getting carriers, set services and packages.
	 *
	 * @link https://docs.shipstation.com/openapi/carriers
	 *
	 * @param String $carrier_code
	 * @param Array $unused - Only used in [v1] but here for compatibility purposes. May be used in the future?
	 *
	 * @return Array|WP_Error
	 */
	public function get_carriers( $carrier_code = '', $unused = array() ) {

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

		if( empty( $data['carriers'] ) || $this->skip_cache ) {

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
			foreach( $body['carriers'] as $carrier_data ) {

				$carrier = array_intersect_key( $carrier_data, array_flip( array(
					'carrier_id',
					'carrier_code',
					'account_number',
					'nickname',
					'friendly_name',
				) ) );

				$carrier['is_shipstation'] 	= ( ! empty( $carrier_data['primary'] ) );
				$carrier['name'] 			= $carrier['friendly_name'];

				// Denote Manual Connected Carrier.
				if( ! $carrier['is_shipstation'] ) {
					$carrier['name'] .= ' ' . esc_html__( '(Manual)', 'live-rates-for-shipstation' );
				}

				$data['carriers'][ $carrier['carrier_id'] ] = $carrier;

				if( isset( $carrier_data['services'] ) ) {
					foreach( $carrier_data['services'] as $service ) {
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

				if( isset( $carrier_data['packages'] ) ) {
					$data['packages'][ $carrier['carrier_id'] ] = $carrier_data['packages'];
				}
			}

			// Cache Carriers
			if( ! empty( $data['carriers'] ) ) {
				set_transient( $trans_key, $data['carriers'], $this->cache_time );
			}

			// Cache Services individually
			if( ! empty( $data['services'] ) ) {
				foreach( $data['services'] as $carrier_id => $service_arr ) {
					$service_key = sprintf( '%s_%s_services', $trans_key, $carrier_id );
					set_transient( $service_key, $service_arr, $this->cache_time );
				}
			}

			// Cache Packages individually
			if( ! empty( $data['packages'] ) ) {
				foreach( $data['packages'] as $carrier_id => $package_arr ) {
					$package_key = sprintf( '%s_%s_packages', $trans_key, $carrier_id );
					set_transient( $package_key, $package_arr, $this->cache_time );
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
	 * @todo Look into `delivery_days` field. UPS has, is it carrier consistent?
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
			if( ! empty( $rate['package_type'] ) && 'package' != $rate['package_type'] ) continue;

			$est = array(
				'name'					=> $rate['service_type'],
				'code'					=> $rate['service_code'],
				'cost'					=> $rate['shipping_amount']['amount'],
				'currency'				=> $rate['shipping_amount']['currency'],
				'carrier_id'			=> $rate['carrier_id'],
				'carrier_code'			=> $rate['carrier_code'],
				'carrier_nickname'		=> $rate['carrier_nickname'],
				'carrier_friendly_name'	=> $rate['carrier_friendly_name'],
				'carrier_name'			=> $rate['carrier_friendly_name'],
				'other_costs'			=> array(),
			);

			// If other amount has a value, return it to the estimate.
			if( isset( $rate['other_amount'], $rate['other_amount']['amount'] ) && ! empty( $rate['other_amount']['amount'] ) ) {
				$est['other_costs']['other'] = $rate['other_amount'];
			}

			$data[] = $est;

		}

		return $data;

	}


	/**
	 * Create a new Shipment
	 *
	 * @param Array $args
	 *
	 * @return Array $data
	 */
	public function create_shipments( $args ) {

		$body = $this->make_request( 'post', 'shipments', array( 'shipments' => $args ) );

		// Return Early - API Request error - see logs.
		if( is_wp_error( $body ) ) {
			return $body;
		}

		/**
		 * API returns no errors but also doesn't do anything in ShipStation.
		 */
		$data = $body;

		return $data;

	}



	/**
	 * Create Shipments from given WC_Orders.
	 *
	 * @param Array $wc_orders - Array of WC_Order objects.
	 *
	 * @return Array|WP_Error
	 */
	public function create_shipments_from_wc_orders( $wc_orders ) {

		$data = array();
		if( empty( $wc_orders ) ) {
			return $data;
		}

		$shipments = array();
		foreach( $wc_orders as $wc_order ) {

			// Skip - Not WC_Order
			if( ! is_a( $wc_order, 'WC_Order' ) ) continue;

			$shipstation_order_arr = $wc_order->get_meta( '_shipstation_order', true );

			// Skip - No ShipStation Order data to work with.
			if( empty( $shipstation_order_arr ) ) continue;

			$order_items     = $wc_order->get_items();
			$order_item_ship = $wc_order->get_items( 'shipping' );
			$order_item_ship = ( ! empty( $order_item_ship ) ) ? $order_item_ship[ array_key_first( $order_item_ship ) ] : null;

			$shipment = array(
				'validate_address'  => 'no_validation',
				'carrier_id'        => $order_item_ship->get_meta( "_{$this->prefix}_carrier_id", true ),
				'store_id'          => \IQLRSS\Driver::get_ss_opt( 'store_id' ),
				'shipping_paid'     => array(
					'currency'  => $wc_order->get_currency(),
					'amount'    => $wc_order->get_shipping_total(),
				),
				'ship_from' => array(
					'name'      => get_option( 'woocommerce_email_from_name' ),
					'phone'     => '000-000-0000', // Phone Number is required.
					'email'     => get_option( 'woocommerce_email_from_address' ),
					'company'   => get_bloginfo( 'name' ),
					'address_line1' => WC()->countries->get_base_address(),
					'address_line2' => WC()->countries->get_base_address_2(),
					'city_locality' => WC()->countries->get_base_city(),
					'state_province'=> WC()->countries->get_base_state(),
					'postal_code'   => WC()->countries->get_base_postcode(),
					'country_code'  => WC()->countries->get_base_country(),
					'address_residential_indicator' => 'unknown',
				),
				'ship_to' => array(
					'name'      => $wc_order->get_formatted_shipping_full_name(),
					'phone'     => ( ! empty( $wc_order->get_shipping_phone() ) ) ? $wc_order->get_shipping_phone() : '000-000-0000',
					'email'     => $wc_order->get_billing_email(),
					'company'   => $wc_order->get_shipping_company(),
					'address_line1' => $wc_order->get_shipping_address_1(),
					'address_line2' => $wc_order->get_shipping_address_2(),
					'city_locality' => $wc_order->get_shipping_city(),
					'state_province'=> $wc_order->get_shipping_state(),
					'postal_code'   => $wc_order->get_shipping_postcode(),
					'country_code'  => $wc_order->get_shipping_country(),
					'address_residential_indicator' => 'unknown',
				),
				'items'     => array(),
				'packages'  => array(),
			);

			$shipment['items'] = array();
			foreach( $shipstation_order_arr['items'] as $ship_item ) {

				// Skip any items that don't exist in our orders
				if( ! isset( $order_items[ $ship_item['lineItemKey'] ] ) ) continue;

				$wc_order_item = $order_items[ $ship_item['lineItemKey'] ];
				$shipment['items'][] = array(
					'external_order_id'     => $wc_order->get_id(),
					'external_order_item_id'=> $ship_item['lineItemKey'],
					'order_source_code'     => 'woocommerce',
					'name'                  => $ship_item['name'],
					'sku'                   => $ship_item['sku'],
					'quantity'              => $ship_item['quantity'],
					'image_url'             => $ship_item['imageUrl'],
					'unit_price'            => $wc_order_item->get_product()->get_price(),
					'weight'                => array(
						'value' => $wc_order_item->get_product()->get_weight(),
						'unit'  => $this->convert_unit_term( get_option( 'woocommerce_weight_unit', 'lbs' ) ),
					),
				);

				$shipment['packages'][] = array(
					'package_code'  => 'package',
					'package_name'  => 'Foo Bar',
					'weight'        => array(
						'value' => $wc_order_item->get_product()->get_weight(),
						'unit'  => $this->convert_unit_term( get_option( 'woocommerce_weight_unit', 'lbs' ) ),
					),
					'dimensions' => array(
						'length'	=> round( wc_get_dimension( $wc_order_item->get_product()->get_length(), get_option( 'woocommerce_dimension_unit', 'in' ) ), 2 ),
						'width'		=> round( wc_get_dimension( $wc_order_item->get_product()->get_width(), get_option( 'woocommerce_dimension_unit', 'in' ) ), 2 ),
						'height'	=> round( wc_get_dimension( $wc_order_item->get_product()->get_height(), get_option( 'woocommerce_dimension_unit', 'in' ) ), 2 ),
						'unit'		=> $this->convert_unit_term( get_option( 'woocommerce_dimension_unit', 'in' ) ),
					),
					'products' => array( array(
						'description'   => $wc_order_item->get_product()->get_name(),
						'sku'           => $ship_item['sku'],
						'quantity'      => $ship_item['quantity'],
						'product_url'   => get_permalink( $wc_order_item->get_product()->get_id() ),
						'value'         => array(
							'currency'  => $wc_order->get_currency(),
							'amount'    => $wc_order_item->get_product()->get_price(),
						),
						'weight' => array(
							'value' => $wc_order_item->get_product()->get_weight(),
							'unit'  => $this->convert_unit_term( get_option( 'woocommerce_weight_unit', 'lbs' ) ),
						),
						'unit_of_measure' => get_option( 'woocommerce_dimension_unit', 'in' ),
					) ),
				);
			}

			$shipments[] = $shipment;

		}

		return $this->create_shipments( $shipments );

	}


	/**
	 * Make an API Request
	 *
	 * @param String $method
	 * @param String $endpoint
	 * @param Array $args
	 *
	 * @return Array|WP_Error $response
	 */
	public function make_request( $method, $endpoint, $args = array() ) {

		// Return Early - No API Key found.
		if( empty( $this->key ) ) {
			return $this->log( new \WP_Error( 401, esc_html__( 'No ShipStation REST API Key found.', 'live-rates-for-shipstation' ) ), 'warning' );
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
			if( 'post' == $method ) {
				$req_args['body'] = wp_json_encode( $args );
			} else if( 'get' == $method ) {
				$endpoint_url = add_query_arg( $args, $endpoint_url );
			}
		}

		$request = call_user_func( $callback, esc_url( $endpoint_url ), $req_args );
		$code = wp_remote_retrieve_response_code( $request );
		$body = wp_remote_retrieve_body( $request );

		if( is_string( $body ) ) {
			$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body ); // da BOM
			$json = json_decode( $body, true );
			$body = ( ! empty( $json ) ) ? $json : $body;
		}

		// Return Early - API encountered an error.
		if( is_wp_error( $request ) ) {
			return $this->log( $request );
		} else if( 200 != $code || ! is_array( $body ) ) {

			$err_code = $code;
			$err_msg  = esc_html__( 'Error encountered during request.', 'live-rates-for-shipstation' );

			if( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {

				$error 	  = array_shift( $body['errors'] );
				$err_code = ( isset( $error['error_code'] ) && 'unspecified' != $error['error_code'] ) ? $error['error_code'] : $err_code;
				$err_msg  = ( isset( $error['message'] ) ) ? $error['message'] : $err_msg;
				$err_type = ( isset( $error['error_type'] ) ) ? $error['error_type'] : 'ghosts';

				// Add a bit more context.
				if( 'unauthorized' == $err_code ) {
					$err_msg .= ' ' . esc_html__( '(API Key may be invalid)', 'live-rates-for-shipstation' );
				}

				// Add even more context.
				if( 'account_status' == $err_type ) {
					$err_msg .= sprintf( '<a href="%s" target="_blank" rel="nofollow noopener" class="button button-primary button-small">%s&nbsp;&nbsp;<span class="dashicons dashicons-external" aria-hidden="true"></span></a>',
						'https://www.dpbolvw.net/click-101532691-11646582',
						esc_html__( 'Sign up for a ShipStation Account', 'live-rates-for-shipstation' ),
					);
				}
			}

			return $this->log( new \WP_Error( $err_code, $err_msg ), 'error', array(
				'args'		=> $args,
				'code'		=> $err_code,
				'response'	=> $body,
			) );
		}

		// Log API Request Result
		/* translators: %s is the API endpoint (example: carriers/rates). */
		$this->log( sprintf( esc_html__( 'ShipStation API Request to %s', 'live-rates-for-shipstation' ), $endpoint ), 'info', array(
			'args'		=> $args,
			'code'		=> $code,
			'response'	=> $body,
		) );

		return $body;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Convert a WooCommerce unit term to a
	 * ShipStation unit term.
	 *
	 * @param String $unit
	 *
	 * @return String $term
	 */
	public function convert_unit_term( $unit ) {

		$known = array(
			'kg'	=> 'kilogram',
			'g'		=> 'gram',
			'lbs'	=> 'pound',
			'oz'	=> 'ounce',
			'cm'	=> 'centimeter',
			'in'	=> 'inch',
		);

		return ( isset( $known[ $unit ] ) ) ? $known[ $unit ] : $unit;

	}


	/**
	 * Return a ShipStation API Endpoint URL
	 *
	 * @param String $endpoint
	 *
	 * @return String $url
	 */
	protected function get_endpoint_url( $endpoint ) {

		return sprintf( '%s/v2/%s/',
			'https://api.shipstation.com',
			$endpoint
		);

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

		if( ! \IQLRSS\Driver::get_ss_opt( 'logging_enabled', 0, true ) ) {
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

			/**
			 * The WC_Logger does not handle double quotes well.
			 * This will conver double quotes to faux: " -> ''
			 */
			array_walk_recursive( $context, function( &$val ) {
				$val = ( is_string( $val ) ) ? str_replace( '"', "''", $val ) : $val;
			} );

			$this->logger->log( $level, $error_msg, array_merge( $context, array( 'source' => 'live-rates-for-shipstation' ) ) );

		}

		return $error;

	}

}