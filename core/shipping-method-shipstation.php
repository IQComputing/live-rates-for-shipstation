<?php
/**
 * ShipStation Live Shipping Rates Method
 *
 * :: Actual Shipping Calculations
 * :: Helper Methods
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
} else if( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

class Shipping_Method_Shipstation extends \WC_Shipping_Method  {

	/**
	 * Plugin prefix used to namespace data keys.
	 *
	 * @var String
	 */
	protected $plugin_prefix;


	/**
	 * Array of expected dimension keys (width, height, length, weight)
	 *
	 * @var Array
	 */
	protected $dimension_keys = array(
		'width'		=> 'width',
		'height'	=> 'height',
		'length'	=> 'length',
		'weight'	=> 'weight',
	);


	/**
	 * Array of global carriers
	 * There are the carriers saved in Integration settings.
	 *
	 * @var Array
	 */
	protected $carriers = array();


	/**
	 * ShipStation API Helper Class
	 *
	 * @var Object
	 */
	protected $shipStationApi = null;


	/**
	 * WooCommerce Logger
	 *
	 * @var WC_Logger
	 */
	protected $logger = null;


	/**
	 * Setup shipping class
	 *
	 * @param Integer $instance_id - Shipping method instance ID. A new instance ID is assigned per instance created in a shipping zone.
	 *
	 * @return void
	 */
	public function __construct( $instance_id = 0 ) {

		$this->plugin_prefix 		= \IQLRSS\Driver::get( 'slug' );
		$this->shipStationApi 		= new Shipstation_Api( true );
		$this->id 					= \IQLRSS\Driver::plugin_prefix( 'shipstation' );
		$this->instance_id 			= absint( $instance_id );
		$this->method_title 		= esc_html__( 'Live Rates for ShipStation', 'live-rates-for-shipstation' );
		$this->method_description 	= esc_html__( 'Get live shipping rates from all ShipStation supported carriers.', 'live-rates-for-shipstation' );
		$this->supports 			= array( 'instance-settings' );

		$this->carriers = \IQLRSS\Driver::get_ss_opt( 'carriers', array(), true );
		$saved_key = \IQLRSS\Driver::get_ss_opt( 'api_key_valid', false, true );

		// Only show in Shipping Zones if API Key is invalid.
		if( ! empty( $saved_key ) && ! empty( $this->carriers ) ) {
			$this->supports[] = 'shipping-zones';
		}

		$this->init_instance_form_fields();
		$this->init_instance_options();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'http_request_timeout', array( $this, 'increase_request_timeout' ) );

	}


	/**
	 * Clear cache whenever settings are updated.
	 *
	 * @return Boolean
	 */
	public function process_admin_options() {

		( new \IQLRSS\Core\Settings_Shipstation() )->clear_cache();
		return parent::process_admin_options();

	}


	/**
	 * Increase the HTTP Request Timeout
	 * Sometimes ShipStation takes awhile to responde with rates.
	 * Presumably, the more services enabled, the longer it takes.
	 *
	 * @param Integer $timeout
	 *
	 * @return Integer $timeout
	 */
	public function increase_request_timeout( $timeout ) {
		return ( $timeout < 20 ) ? 20 : $timeout;
	}


	/**
	 * Setup the instance title.
	 *
	 * @return void
	 */
	protected function init_instance_options() {
		$this->title = $this->get_option( 'title', $this->method_title );
	}


	/**
	 * Setup the WooCommerce known form fields.
	 *
	 * @return void
	 */
	protected function init_instance_form_fields() {

		$this->instance_form_fields = array(
			'title' => array(
				'title'			=> esc_html__( 'Title', 'live-rates-for-shipstation' ),
				'type'			=> 'text',
				'description'	=> esc_html__( 'This controls the title which the user sees during checkout.', 'live-rates-for-shipstation' ),
				'default'		=> esc_html__( 'ShipStation Rates', 'live-rates-for-shipstation' ),
				'desc_tip'		=> true,
			),
			'packing' => array(
				'title'			=> esc_html__( 'Product Packing', 'live-rates-for-shipstation' ),
				'type'			=> 'select',
				'class'			=> 'custom-boxes-control',
				'options'		=> array(
					'individual'	=> esc_html__( 'Pack items individually', 'live-rates-for-shipstation' ),
					'wc-box-packer'	=> esc_html__( 'Pack items using Custom Packing Boxes', 'live-rates-for-shipstation' ),
				),
				'description'	=> esc_html__( 'Individually can be more costly. Custom packing boxes will automatically fit as many products in set dimensions lowering shipping costs.', 'live-rates-for-shipstation' ),
			),
			'customboxes' => array(
				'type' => 'customboxes', // See self::generate_customboxes_html()
			),
			'services' => array(
				'type' => 'services', // See self::generate_services_html()
			),
		);

	}


	/**
	 * Automatic dynamic method inherited from parent.
	 * Generate HTML for service fields.
	 *
	 * @return String - HTML
	 */
	public function generate_services_html() {

		$prefix 		= $this->plugin_prefix;
		$settings 		= get_option( 'woocommerce_shipstation_settings' );
		$saved_services = $this->get_option( 'services', array() );
		$saved_carriers = \IQLRSS\Driver::get_ss_opt( 'carriers', array(), true );
		$shipStationAPI	= $this->shipStationApi;

		if( ! empty( $saved_services ) ) {

			$sorted_services = array();
			foreach( $saved_services as $k => $s ) {

				// Skip any old carrier services.
				if( ! in_array( $k, $saved_carriers ) ) {
					unset( $saved_services[ $k ] );
					continue;

				// Skip any services not enabled.
				} else if( ! isset( $s['enabled'] ) ) {
					continue;
				}

				$sorted_services[ $k ] = $s;
				unset( $saved_services[ $k ] );
			}
			$saved_services = array_merge( $sorted_services, $saved_services );
		}

		ob_start();
			include 'views/services-table.php';
		return ob_get_clean();

	}


	/**
	 * Automatic dynamic method inherited from parent.
	 * Generate HTML for custom boxes fields.
	 *
	 * @return String - HTML
	 */
	public function generate_customboxes_html() {

		$prefix 		= $this->plugin_prefix;
		$show_custom 	= ( 'wc-box-packer' == $this->get_option( 'packing', 'individual' ) );
		$saved_boxes 	= $this->get_option( 'customboxes', array() );

		ob_start();
			include 'views/customboxes-table.php';
		return ob_get_clean();

	}


	/**
	 * Validate service field.
	 *
	 * @param mixed $key - Field key.
	 */
	public function validate_services_field() {

		if( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$prefix = $this->plugin_prefix;
		$nonce  = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

		if( ! wp_verify_nonce( $nonce, 'woocommerce-settings' ) ) {
			return;
		} else if( ! isset( $_POST[ $prefix ] ) || ! is_array( $_POST[ $prefix ] ) ) {
			return;
		}

		// Input sanitized during processing.
		$posted_services = wp_unslash( $_POST[ $prefix ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Group by Carriers then Services
		$services = array();
		foreach( $posted_services as $carrier_code => $carrier_services ) {
			foreach( $carrier_services as $service_code => $service_arr ) {

				// Skip non-enabled and non-renamed services.
				if( ! isset( $service_arr['enabled'] ) && empty( $service_arr['nickname'] ) ) continue;

				$carrier_code = sanitize_text_field( $carrier_code );
				$service_code = sanitize_text_field( $service_code );
				$services[ $carrier_code ][ $service_code ] = array_filter( array(

					// User Input
					'enabled'		=> boolval( ( isset( $service_arr['enabled'] ) ) ),
					'nickname'		=> sanitize_text_field( $service_arr['nickname'] ),

					// Metadata
					'service_name'	=> sanitize_text_field( $service_arr['service_name'] ),
					'service_code'	=> sanitize_text_field( $service_code ),
					'carrier_name'	=> sanitize_text_field( $service_arr['carrier_name'] ),
					'carrier_code'	=> sanitize_text_field( $carrier_code ),
				) );

				// Allow 0 value user input.
				if( $service_arr['adjustment'] >= 0 ) {
					$services[ $carrier_code ][ $service_code ]['adjustment'] = floatval( $service_arr['adjustment'] );
				}

			}
		}

		return $services;

	}


	/**
	 * Validate customboxes field.
	 *
	 * @param mixed $key - Field key.
	 */
	public function validate_customboxes_field() {

		if( ! isset( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		if( ! wp_verify_nonce( $nonce, 'woocommerce-settings' ) ) {
			return;
		} else if( ! isset( $_POST['custombox'] ) || ! is_array( $_POST['custombox'] ) ) {
			return;
		}

		// Input sanitized during processing.
		$posted_boxes = wp_unslash( $_POST['custombox'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$boxes = array();
		foreach( $posted_boxes as $box_arr ) {

			$vals = array_filter( $box_arr, 'is_numeric' );
			if( count( $vals ) < 7 ) continue;

			$boxes[] = array(
				'outer' => array(
					'length'	=> floatval( $box_arr['ol'] ),
					'width'		=> floatval( $box_arr['ow'] ),
					'height'	=> floatval( $box_arr['oh'] ),
				),
				'inner' => array(
					'length'	=> floatval( $box_arr['il'] ),
					'width'		=> floatval( $box_arr['iw'] ),
					'height'	=> floatval( $box_arr['ih'] ),
				),
				'weight'	=> floatval( $box_arr['w'] ),
				'weight_max'=> floatval( $box_arr['wm'] ),
			);

		}

		return $boxes;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Actual Shipping Calculations :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Calculate shipping costs
	 *
	 * @param Array $package
	 *
	 * @return void
	 */
	public function calculate_shipping( $packages = array() ) {

		// Return Early - Empty Packages
		if( empty( $packages ) || ! isset( $packages['contents'] ) ) {
			return;

		// Return Early - No Destination to work with. Postcode is kinda required.
		} else if( ! isset( $packages['destination'] ) || empty( $packages['destination']['postcode'] ) ) {
			return;
		}

		$saved_services = $this->get_option( 'services', array() );
		if( empty( $saved_services ) ) {
			return;
		}

		$saved_carriers = array_keys( $saved_services );
		if( ! empty( $saved_carriers ) && ! empty( $this->carriers ) ) {
			$saved_carriers = array_values( array_intersect( $saved_carriers, $this->carriers ) );
		}

		$global_upcharge = floatval( \IQLRSS\Driver::get_ss_opt( 'global_adjustment', 0, true ) );
		$packing_type 	= $this->get_option( 'packing', 'individual' );
		$request = array(
			'from_country_code'	 => WC()->countries->get_base_country(),
			'from_postal_code'	 => WC()->countries->get_base_postcode(),
			'from_city_locality' => WC()->countries->get_base_city(),
			'from_state_province'=> WC()->countries->get_base_state(),

			'to_country_code'	=> $packages['destination']['country'],
			'to_postal_code'	=> $packages['destination']['postcode'],
			'to_city_locality'	=> $packages['destination']['city'],
			'to_state_province'	=> $packages['destination']['state'],

			'address_residential_indicator' => 'unknown',
		);

		// Individual Packaging
		if( 'individual' == $packing_type ) {
			$item_requests = $this->get_individual_requests( $packages['contents'] );

		// WC Boxed Packaging
		} else {
			$item_requests = $this->get_custombox_requests( $packages['contents'] );
		}

		// Rates groups shipping estimates by service ID.
		$rates = array();

		/**
		 * This has to be done per package as the other rates endpoint
		 * requires the customers address1 for verification and really
		 * it's not much faster.
		 *
		 * WC()->session->set( '', '' );
		 */
		foreach( $item_requests as $item_id => $req ) {

			// Create the API request combining the package (weight, dimensions), general request data, and the carrier info.
			$api_request = array_merge(
				$req,		// Package (weight, dimensions)
				$request,	// General info like to/from address
				array(		// Saved carrier ids
					'carrier_ids' => $saved_carriers,
				)
			);

			// Check Cache
			$available_rates = $this->cache_get_package_rates( $api_request );
			if( empty( $available_rates ) ) {

				// Ping the ShipStation API to get rates per Carrier.
				$available_rates = $this->shipStationApi->get_shipping_estimates( $api_request );

				// Continue - Something went wrong, should be logged on the API side.
				if( is_wp_error( $available_rates ) || empty( $available_rates ) ) {
					continue;
				}

				// Cache request
				$this->cache_package_rates( $api_request, $available_rates );

			}

			// Loop the found rates and setup the WooCommerce rates array for each.
			foreach( $available_rates as $shiprate ) {

				if( ! isset( $saved_services[ $shiprate['carrier_code'] ][ $shiprate['code'] ] ) ) {
					continue;
				}

				$service_arr = $saved_services[ $shiprate['carrier_code'] ][ $shiprate['code'] ];
				$cost = $shiprate['cost'];

				// Apply service upcharge
				if( isset( $service_arr['adjustment'] ) && $service_arr['adjustment'] > 0 ) {

					$adjustment = floatval( $saved_services[ $shiprate['carrier_code'] ]['adjustment'] );
					$cost += ( $adjustment > 0 ) ? ( $cost * ( $adjustment / 100 ) ) : 0;

				} else if( ! empty( $global_upcharge ) ) {
					$cost += ( $cost * ( $global_upcharge / 100 ) );
				}

				// Maybe apply per item.
				$cost = ( 'individual' == $packing_type ) ? ( $cost * $packages['contents'][ $item_id ]['quantity'] ) : $cost;

				// Create the WooCommerce rate Array.
				$rate = array(
					'id'		=> $shiprate['code'],
					'label'		=> sprintf( '%s (%s)', $shiprate['name'], $shiprate['carrier_name'] ),
					'package'	=> $packages,
					'meta_data' => array(
						'dimensions'	=> $req['dimensions'],
						'weight'	 	=> $req['weight'],
						'carrier_code'	=> $shiprate['carrier_code'],
					),
				);

				if( isset( $rates[ $shiprate['code'] ] ) ) {
					$rates[ $shiprate['code'] ]['cost'][] = $cost;
				} else {
					$rates[ $shiprate['code'] ] = array_merge( $rate, array(
						'cost' => array( $cost ),
					) );
				}

			}

		}

		$single_lowest 			= \IQLRSS\Driver::get_ss_opt( 'return_lowest', 'no', true );
		$single_lowest_label 	= \IQLRSS\Driver::get_ss_opt( 'return_lowest_label', '', true );

		// Add all shipping rates, let the user decide.
		if( 'no' == $single_lowest || empty( $single_lowest ) ) {

			foreach( $rates as $rate_arr ) {
				$this->add_rate( $rate_arr );
			}

		// Find the single lowest shipping rate
		} else if( 'yes' == $single_lowest ) {

			$lowest = 0;
			$lowest_carrier = array_key_first( $rates );
			foreach( $rates as $carrier_code => $rate_arr ) {

				$total = array_sum( $rate_arr['cost'] );
				if( 0 == $lowest || $total < $lowest ) {
					$lowest = $total;
					$lowest_carrier = $carrier_code;
				}
			}

			if( ! empty( $single_lowest_label ) ) {
				$rates[ $lowest_carrier ]['label'] = $single_lowest_label;
			}

			$this->add_rate( $rates[ $lowest_carrier ] );

		}

	}


	/**
	 * Return an array of API requests which would be for individual products.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	protected function get_individual_requests( $items ) {

		$item_requests = array();
		foreach( $items as $item_id => $item ) {

			// Continue - No shipping needed for product.
			if( ! $item['data']->needs_shipping() ) {
				continue;
			}

			$request = array();
			$physicals = array_filter( array(
				'weight'	=> $item['data']->get_weight(),
				'length'	=> $item['data']->get_length(),
				'width'		=> $item['data']->get_width(),
				'height'	=> $item['data']->get_height(),
			) );

			// Return Early - Product missing one of the 4 key dimensions.
			if( count( $physicals ) < 4 ) {
				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$item['product_id'],
					implode( ', ', array_diff_key( $this->dimension_keys, $physicals ) )
				) );
				return array();
			}

			$request['weight'] = array(
				'value' => (float)max( 0.5, round( wc_get_weight( $physicals['weight'], 'lbs' ), 2 ) ),
				'unit'	=> 'pound',
			);

			// Unset weight and sort dimensions
			unset( $physicals['weight'] );
			sort( $physicals );

			$request['dimensions'] = array(
				'unit'		=> 'inch',
				'length'	=> max( 1, round( wc_get_dimension( $physicals[2], 'in' ), 2 ) ),
				'width'		=> max( 1, round( wc_get_dimension( $physicals[1], 'in' ), 2 ) ),
				'height'	=> max( 1, round( wc_get_dimension( $physicals[0], 'in' ), 2 ) ),
			);

			$item_requests[ $item_id ] = $request;

		}

		return $item_requests;

	}


	/**
	 * Return an array of API requests for custom packed boxes.
	 * Shoutout to Mike Jolly & Co.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	protected function get_custombox_requests( $items ) {

		if( ! class_exists( '\IQRLSS\WC_Box_Packer\WC_Boxpack' ) ) {
			include_once 'wc-box-packer/class-wc-boxpack.php';
		}

		$item_requests = array();
		$wc_boxpack = new WC_Box_Packer\WC_Boxpack();
		$boxes = $this->get_option( 'customboxes', array() );

		if( empty( $boxes ) ) {
			$this->log( esc_html__( 'Custom Boxes selected, but no boxes found. Items packed individually', 'live-rates-for-shipstation' ), 'warning' );
		}

		// Setup the WC_Boxpack boxes based on user submitted custom boxes.
		foreach( $boxes as $box ) {

			$custombox = $wc_boxpack->add_box( $box['outer']['length'], $box['outer']['width'], $box['outer']['height'], $box['weight'] );
			$custombox->set_inner_dimensions( $box['inner']['length'], $box['inner']['width'], $box['inner']['height'] );
			if( $box['weight_max'] ) $custombox->set_max_weight( $box['weight_max'] );

		}

		// Loop the items, grabs their dimensions, and assocaite them with WC_Boxpack for future packing.
		foreach( $items as $item_id => $item ) {

			// Continue - No shipping needed for product.
			if( ! $item['data']->needs_shipping() ) {
				continue;
			}

			$data = array();
			$physicals = array_filter( array(
				'weight'	=> $item['data']->get_weight(),
				'length'	=> $item['data']->get_length(),
				'width'		=> $item['data']->get_width(),
				'height'	=> $item['data']->get_height(),
			) );

			// Return Early - Product missing one of the 4 key dimensions.
			if( count( $physicals ) < 4 ) {
				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$item['product_id'],
					implode( ', ', array_diff_key( $this->dimension_keys, $physicals ) )
				) );
				return array();
			}

			$data['weight'] = (float)max( 0.5, round( wc_get_weight( $physicals['weight'], 'lbs' ), 2 ) );

			// Unset weight and sort dimensions
			unset( $physicals['weight'] );
			sort( $physicals );

			$data = array(
				'length'	=> max( 1, round( wc_get_dimension( $physicals[2], 'in' ), 2 ) ),
				'width'		=> max( 1, round( wc_get_dimension( $physicals[1], 'in' ), 2 ) ),
				'height'	=> max( 1, round( wc_get_dimension( $physicals[0], 'in' ), 2 ) ),
			) + $data;

			for( $i = 0; $i < $item['quantity']; $i++ ) {
				$wc_boxpack->add_item(
					$data['length'],
					$data['width'],
					$data['height'],
					$data['weight'],
					$item['data']->get_price(),
				);
			}
		}

		// Pack it up, missions over.
		$wc_boxpack->pack();
		$wc_box_packages = $wc_boxpack->get_packages();
		$box_log = array();

		// Delivery!
		foreach( $wc_box_packages as $key => $package ) {

			$item_requests[] = array(
				'weight' => array(
					'value' => $package->weight,
					'unit'	=> 'pound',
				),
				'dimensions' => array(
					'unit'		=> 'inch',
					'length'	=> $package->length,
					'width'		=> $package->width,
					'height'	=> $package->height,
				),
			);

			$box_log[] = array(
				'box_dimensions' => sprintf( '%s x %s x %s x %s x %s (LxWxHxWeightxVolume)', $package->length, $package->width, $package->height, $package->weight, $package->volume ),
				'item_count'	 => count( $package->packed ),
				'max_volume'	 => floatval( $package->width * $package->height * $package->length ),
			);

		}

		if( ! empty( $box_log ) ) {
			$this->log( esc_html__( 'Custom Boxes Packed', 'live-rates-for-shipstation' ), 'info', $box_log );
		}

		return $item_requests;

	}


	/**
	 * Generate a cache key.
	 *
	 * @param Array $arr
	 * @param Array $kintersect
	 *
	 * @return String
	 */
	protected function cache_key_gen( $arr, $kintersect ) {
		return md5( maybe_serialize( array_intersect_key( $arr, $kintersect ) ) );
	}


	/**
	 * Cache the estimate based on weight, dimensions, and zip.
	 * This will prevent future API calls for the same data.
	 *
	 * @param Array $request - The ShipStation API request array.
	 * @param Array $rates - The returned rates from ShipStation.
	 *
	 * @return void
	 */
	protected function cache_package_rates( $request, $rates ) {

		if( empty( WC()->session ) ) {
			return array();
		}

		$session = WC()->session->get( $this->plugin_prefix, array() );
		$cache = ( isset( $session['api'] ) ) ? $session['api'] : array();
		$cleartime = get_transient( \IQLRSS\Driver::plugin_prefix( 'wcs_timeout' ), 0 );

		// IF the cache has been cleared, invalidate any old caches.
		if( isset( $session['apicached'] ) && $cleartime > $session['apicached'] ) {
			$cache = array();

		// Limit cache to 10.
		} else if( count( $cache ) > 9 ) {
			$cache = array_slice( $cache, 0, 9, true );
		}

		$key = $this->cache_key_gen( $request, array(
			'from_postal_code'	=> '',
			'to_postal_code'	=> '',
			'dimensions'		=> array(),
			'weight'			=> array(),
		) );

		$cache[ $key ] = $rates;
		$session['api'] = $cache;
		$session['apicached'] = time();

		WC()->session->set( $this->plugin_prefix, $session );

	}


	/**
	 * Return cached package rates based on request data.
	 *
	 * @param Array $request - The ShipStation API request array.
	 *
	 * @return Array
	 */
	protected function cache_get_package_rates( $request ) {

		if( empty( WC()->session ) ) {
			return array();
		}

		$session = WC()->session->get( $this->plugin_prefix, array() );
		if( ! isset( $session['api'] ) || empty( $session['api'] ) ) {
			return array();
		}

		// IF the cache has been cleared, invalidate any old caches.
		$cleartime = get_transient( \IQLRSS\Driver::plugin_prefix( 'wcs_timeout' ) );
		if( isset( $session['apicached'] ) && $cleartime > $session['apicached'] ) {
			return array();
		}

		$cache = $session['api'];
		$key = $this->cache_key_gen( $request, array(
			'from_postal_code'	=> '',
			'to_postal_code'	=> '',
			'dimensions'		=> array(),
			'weight'			=> array(),
		) );

		return ( isset( $cache[ $key ] ) && ! empty( $cache[ $key ] ) ) ? $cache[ $key ] : array();

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Log error in WooCommerce
	 * Passthru method - log what's given and give it back.
	 * Could make a good Trait
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