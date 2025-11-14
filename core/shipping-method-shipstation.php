<?php
/**
 * ShipStation Live Shipping Rates Method
 *
 * :: Action Hooks
 * :: Filter Hooks
 * :: Shipping Zone
 * :: Shipping Calculations
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
	 * Array of store specific settings.
	 *
	 * @var Array
	 */
	protected $store_data = array(
		'weight_unit'	=> '',
		'dim_unit'		=> '', // Dimension
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
		$this->shipStationApi 		= new Api\Shipstation();
		$this->id 					= \IQLRSS\Driver::plugin_prefix( 'shipstation' );
		$this->instance_id 			= absint( $instance_id );
		$this->method_title 		= esc_html__( 'Live Rates for ShipStation', 'live-rates-for-shipstation' );
		$this->method_description 	= esc_html__( 'Get live shipping rates from all ShipStation supported carriers.', 'live-rates-for-shipstation' );
		$this->supports 			= array( 'instance-settings' );

		$this->carriers = \IQLRSS\Driver::get_ss_opt( 'carriers', array() );
		$saved_key 		= \IQLRSS\Driver::get_ss_opt( 'api_key_valid', false ); // v2 key.

		// Only show in Shipping Zones if API Key is invalid.
		if( ! empty( $saved_key ) && ! empty( $this->carriers ) ) {
			$this->supports[] = 'shipping-zones';
		}

		// Set the store unit term and associate it with ShipStations term.
		$this->store_data = array(
			'weight_unit' 	=> get_option( 'woocommerce_weight_unit', $this->store_data['weight_unit'] ),
			'dim_unit'		=> get_option( 'woocommerce_dimension_unit', $this->store_data['dim_unit'] ),
		);

		/**
		 * Init shipping methods.
		 */
		$this->init_instance_form_fields();
		$this->init_instance_options();

		/**
		 * These hooks should/will only run whenever the Shipping Method is in active use.
		 * Frontend and Admin.
		 */
		$this->action_hooks();
		$this->filter_hooks();

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Action Hooks :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Add any necessary action hooks
	 *
	 * @return void
	 */
	private function action_hooks() {
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
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



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Filter Hooks :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Add any necessary filter hooks
	 *
	 * @return void
	 */
	private function filter_hooks() {

		add_filter( 'http_request_timeout',						array( $this, 'increase_request_timeout' ) );
		add_filter( 'woocommerce_order_item_display_meta_key',	array( $this, 'labelify_meta_keys' ) );
		add_filter( 'woocommerce_order_item_display_meta_value',array( $this, 'format_meta_values' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta',		array( $this, 'hide_metadata_from_admin_order' ) );

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
	 * Edit Order Screen
	 * Display Order Item Metadata, but labelify the $dispaly Key
	 *
	 * @param String $display
	 *
	 * @return String $display
	 */
	public function labelify_meta_keys( $display ) {

		$matches = array(
			'carrier'	=> esc_html__( 'Carrier', 'live-rates-for-shipstation' ),
			'service'	=> esc_html__( 'Service', 'live-rates-for-shipstation' ),
			'rates'		=> esc_html__( 'Rates', 'live-rates-for-shipstation' ),
			'boxes'		=> esc_html__( 'Packages', 'live-rates-for-shipstation' ),
		);

		return ( isset( $matches[ $display ] ) ) ? $matches[ $display ] : $display;

	}


	/**
	 * Edit Order Screen
	 * Display Order Item Metadata, but labelify the $dispaly Key
	 *
	 * @param String $display
	 * @param WC_Meta_Data $wc_meta
	 * @param WC_Order $wc_order
	 *
	 * @return String $display
	 */
	public function format_meta_values( $display, $wc_meta ) {

		if( ! empty( $display ) ) {
			switch( $wc_meta->key ) {

				// Rates
				case 'rates':
					$value = json_decode( $display, true );

					$display_arr = array();
					foreach( $value as $i => $rate_arr ) {

						/* translators: %1$d is box/package count (1,2,3). */
						$name = sprintf( esc_html__( 'Package %1$d', 'live-rates-for-shipstation' ), $i + 1 );
						if( ! empty( $rate_arr['_name'] ) ) {
							$name = $this->format_shipitem_name( $rate_arr['_name'] );
						}

						if( isset( $rate_arr['adjustment'] ) ) {

							if( ! empty( $rate_arr['qty'] ) ) {

								$new_display = sprintf( '%s [ %s &times; ( %s + %s',
									$name,
									$rate_arr['qty'],
									wc_price( $rate_arr['rate'] ),
									wc_price( $rate_arr['adjustment']['cost'] ),
								);

							} else {

								$new_display = sprintf( '%s [ ( %s + %s',
									$name,
									wc_price( $rate_arr['rate'] ),
									wc_price( $rate_arr['adjustment']['cost'] ),
								);

							}

							if( 'percentage' == $rate_arr['adjustment']['type'] ) {
								$new_display .= sprintf( ' | %s', $rate_arr['adjustment']['rate'] . '%' );
							}

							// Add any other charges
							if( isset( $rate_arr['other_costs'] ) ) {
								foreach( $rate_arr['other_costs'] as $o_slug => $o_amount ) {
									$new_display .= sprintf( ' | %s: %s', ucwords( $o_slug ), wc_price( $o_amount ) );
								}
							}

							$new_display .= sprintf( ' ) %s ]',
								( $rate_arr['adjustment']['global'] ) ? esc_html__( 'Global', 'live-rates-for-shipstation' ) : esc_html__( 'Service', 'live-rates-for-shipstation' )
							);

							$display_arr[] = $new_display;

						} else {

							$new_display = '';
							if( ! empty( $rate_arr['qty'] ) ) {

								$new_display = sprintf( '%s [ %s x %s',
									$name,
									$rate_arr['qty'],
									wc_price( $rate_arr['rate'] ),
								);

							} else {

								$new_display = sprintf( '%s [ %s',
									$name,
									wc_price( $rate_arr['rate'] ),
								);
							}

							// Add any other charges
							if( isset( $rate_arr['other_costs'] ) ) {
								foreach( $rate_arr['other_costs'] as $o_slug => $o_amount ) {
									$new_display .= sprintf( ' | %s: %s', ucwords( $o_slug ), wc_price( $o_amount ) );
								}
							}

							$new_display .= ' ]';
							$display_arr[] = $new_display;

						}

					}

					$display = implode( ',&nbsp;&nbsp;', $display_arr );

					break;

				// Boxes
				case 'boxes':
					$value = json_decode( $display, true );

					$display_arr = array();
					foreach( $value as $i => $box_arr ) {

						$names = esc_html__( 'Product', 'live-rates-for-shipstation' );
						if( isset( $box_arr['_name'] ) ) {
							$names = $this->format_shipitem_name( $box_arr['_name'] );
						} else if( ! empty( $box_arr['packed'] ) ) {
							$names = array_map( function( $name ) {
								return $this->format_shipitem_name( $name );
							}, $box_arr['packed'] );
						}

						$display_arr[] = sprintf( '%s ( %s ) [ %s %s ( %s x %s x %s %s ) ]',

							/* translators: %1$d is box/package count (1,2,3). */
							sprintf( esc_html__( 'Package %1$d', 'live-rates-for-shipstation' ), $i + 1 ),
							implode( ', ', (array)$names ),
							$box_arr['weight']['value'],
							$box_arr['weight']['unit'],
							$box_arr['dimensions']['length'],
							$box_arr['dimensions']['width'],
							$box_arr['dimensions']['height'],
							$box_arr['dimensions']['unit'],
						);

					}

					$display = implode( ',&nbsp;&nbsp;', $display_arr );

					break;
			}
		}

		return $display;

	}


	/**
	 * Hide certain metadata from the Admin Order screen.
	 * Otherwise, it formats it as label value pairs.
	 *
	 * @param Arary $meta_keys
	 *
	 * @return Array $meta_keys
	 */
	public function hide_metadata_from_admin_order( $meta_keys ) {
		return array_merge( $meta_keys, array(
			"_{$this->plugin_prefix}_carrier_id",
			"_{$this->plugin_prefix}_carrier_code",
			"_{$this->plugin_prefix}_service_code",
		) );
	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Shipping Zone :: **/
	/**------------------------------------------------------------------------------------------------ **/
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
			'minweight' => array(
				'title'			=> esc_html__( 'Product Weight Fallback', 'live-rates-for-shipstation' ),
				'type'			=> 'text',
				'description'	=> esc_html__( 'This value will be used if both weight and dimensions are missing from any given product. ShipStation at minimum needs a product weight to retrieve rates.', 'live-rates-for-shipstation' ),
			),
			'packing' => array(
				'title'			=> esc_html__( 'Product Packing', 'live-rates-for-shipstation' ),
				'type'			=> 'select',
				'class'			=> 'custom-boxes-control',
				'options'		=> array(
					'individual'	=> esc_html__( 'Pack items individually', 'live-rates-for-shipstation' ),
					'wc-box-packer'	=> esc_html__( 'Pack items using Custom Packing Boxes', 'live-rates-for-shipstation' ),
					'onebox'		=> esc_html__( 'Pack items into one package derived from products', 'live-rates-for-shipstation' ),
				),
				'description'	=> esc_html__( 'Individually can be more costly. Custom packing boxes will automatically fit as many products in set dimensions lowering shipping costs.', 'live-rates-for-shipstation' ),
			),
			'packing_sub' => array(
				'title'			=> esc_html__( 'Package Dimensions', 'live-rates-for-shipstation' ),
				'type'			=> 'select',
				'options'		=> array(
					'weightonly'	=> esc_html__( 'Total weight', 'live-rates-for-shipstation' ),
					'stacked'		=> esc_html__( 'Stacked vertically', 'live-rates-for-shipstation' ),
				),
				'description'	=> esc_html__( 'Stacked vertically - sums product heights and weights, takes largest of other dimensions. Weight only sums product weights and retrieves rates using the total.', 'live-rates-for-shipstation' ),
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
	 * Generate HTML for custom boxes fields.
	 *
	 * @return String - HTML
	 */
	public function generate_customboxes_html() {

		$prefix 		= $this->plugin_prefix;
		$show_custom 	= ( 'wc-box-packer' == $this->get_option( 'packing', 'individual' ) );
		$saved_boxes 	= $this->get_option( 'customboxes', array() );

		ob_start();
			include 'assets/views/customboxes-table.php';
		return ob_get_clean();

	}


	/**
	 * Validate customboxes.
	 *
	 * @return Array $boxes
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

			if( isset( $box_arr['json'] ) ) {

				$json = json_decode( $box_arr['json'], true );
				if( empty( $json['outer'] ) ) continue;

				$boxes[] = array(
					'active' => absint( $json['active'] ),
					'nickname' => sanitize_text_field( $json['nickname'] ),
					'outer' => array(
						'length'	=> floatval( $json['outer']['length'] ),
						'width'		=> floatval( $json['outer']['width'] ),
						'height'	=> floatval( $json['outer']['height'] ),
					),
					'inner' => array(
						'length'	=> floatval( $json['inner']['length'] ),
						'width'		=> floatval( $json['inner']['width'] ),
						'height'	=> floatval( $json['inner']['height'] ),
					),
					'weight'	=> floatval( $json['weight'] ),
					'weight_max'=> floatval( $json['weight_max'] ),
					'price'		=> floatval( $json['price'] ),
				);

				// Next!
				continue;
			}

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

		usort( $boxes, function( $arrA, $arrB ) {
			if( isset( $arrA['nickname'] ) && $arrB['nickname'] ) {
				return strcasecmp( $arrA['nickname'], $arrB['nickname'] );
			}
		} );

		return $boxes;

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
		$saved_carriers = \IQLRSS\Driver::get_ss_opt( 'carriers', array() );
		$shipStationAPI	= $this->shipStationApi;

		if( ! empty( $saved_services ) ) {

			$sorted_services = array();

			// See $this->validate_services_field()
			foreach( $saved_services as $carrier_id => $carrier_services ) {

				// Skip any old carrier services.
				if( ! in_array( $carrier_id, $saved_carriers ) ) {
					unset( $saved_services[ $carrier_id ] );
					continue;
				}

				// Skip any services which are not enabled.
				foreach( $carrier_services as $service_code => $service_arr ) {
					if( ! isset( $service_arr['enabled'] ) ) {
						unset( $carrier_services[ $service_code ] );
					}
				}

				$sorted_services[ $carrier_id ] = $carrier_services;
				unset( $saved_services[ $carrier_id ] );
			}

			$saved_services = array_merge( $sorted_services, $saved_services );
		}

		ob_start();
			include 'assets/views/services-table.php';
		return ob_get_clean();

	}


	/**
	 * Validate service field.
	 *
	 * @return Array $services
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

		// Global adjustment
		$global_adjustment 		= \IQLRSS\Driver::get_ss_opt( 'global_adjustment', '' );
		$global_adjustment_type = \IQLRSS\Driver::get_ss_opt( 'global_adjustment_type', '' );

		// Group by Carriers then Services
		$services = array();

		foreach( $posted_services as $carrier_id => $carrier_services ) {
			foreach( $carrier_services as $service_code => $service_arr ) {

				$carrier_id 	= sanitize_text_field( $carrier_id );
				$service_code 	= sanitize_text_field( $service_code );
				$data = array_filter( array(

					// User Input
					'enabled'		=> boolval( ( isset( $service_arr['enabled'] ) ) ),
					'nickname'		=> sanitize_text_field( $service_arr['nickname'] ),

					// Metadata
					'service_name'	=> sanitize_text_field( $service_arr['service_name'] ),
					'service_code'	=> sanitize_text_field( $service_code ),
					'carrier_name'	=> sanitize_text_field( $service_arr['carrier_name'] ),
					'carrier_code'	=> ( isset( $service_arr['carrier_code'] ) ) ? sanitize_text_field( $service_arr['carrier_code'] ) : '',
					'carrier_id'	=> ( isset( $service_arr['carrier_id'] ) ) ? sanitize_text_field( $service_arr['carrier_id'] ) : $carrier_id,
				) );

				// The above removes empty values.
				// Price Adjustments
				$data['adjustment']		= ( $service_arr['adjustment'] ) ? floatval( $service_arr['adjustment'] ) : '';
				$data['adjustment_type']= $service_arr['adjustment_type'];

				// Maybe unset if we don't need the data.
				if( $data['adjustment_type'] == $global_adjustment_type ) {

					// equal or equal empty -> 0 == ''
					if( $data['adjustment'] == $global_adjustment || '' == $data['adjustment'] ) {
						unset( $data['adjustment'] );
						unset( $data['adjustment_type'] );
					}
				}

				/**
				 * We don't want to array_filter() since
				 * Global Adjust could be populated, and
				 * Service is set to '' (No Adjustment).
				 */
				$services[ $carrier_id ][ $service_code ] = $data;

			}
		}

		return $services;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Shipping Calculations :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Calculate shipping costs
	 *
	 * @param Array $package
	 *
	 * @return void
	 */
	public function calculate_shipping( $packages = array() ) {

		if( empty( $packages ) || empty( $packages['contents'] ) ) {
			return;
		}

		// Try to pull from cache. This may set $this->rates
		// Return Early - We have cached rates to work with!
		// @todo - Re-enable
		// $packages_hash = $this->check_packages_rate_cache( $packages );
		if( ! empty( $this->rates ) ) {
			return;

		// Return Early - No Destination to work with. Postcode is kinda required.
		} else if( ! isset( $packages['destination'] ) || empty( $packages['destination']['postcode'] ) ) {
			return;
		}

		$enabled_services = $this->get_enabled_services();
		if( empty( $enabled_services ) ) {
			return;
		}

		$saved_carriers = array_keys( $enabled_services );
		if( ! empty( $saved_carriers ) && ! empty( $this->carriers ) ) {
			$saved_carriers = array_values( array_intersect( $saved_carriers, $this->carriers ) );
		}

		$global_adjustment 		= floatval( \IQLRSS\Driver::get_ss_opt( 'global_adjustment', 0 ) );
		$global_adjustment_type = \IQLRSS\Driver::get_ss_opt( 'global_adjustment_type','' );
		$global_adjustment_type = ( empty( $global_adjustment_type ) && ! empty( $global_adjustment ) ) ? 'percentage' : $global_adjustment_type;

		$packing_type = $this->get_option( 'packing', 'individual' );
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

		// Pack items
		$item_requests = call_user_func( array( $this, sprintf( 'group_requestsby_%s', str_replace( '-', '_', $packing_type ) ) ), $packages['contents'] );

		/**
		 * This has to be done per package as the other rates endpoint
		 * requires the customers address1 for verification and really
		 * it's not much faster.
		 * Group rates.
		 */
		$rates = array();
		foreach( $item_requests as $item_id => $req ) {

			// Create the API request combining the package (weight, dimensions), general request data, and the carrier info.
			$api_request = array_merge(
				$req,		// Package (weight, dimensions)
				$request,	// General info like to/from address
				array(		// Saved carrier ids
					'carrier_ids' => $saved_carriers,
				)
			);

			// Ping the ShipStation API to get rates per Carrier.
			// Continue - Something went wrong, should be logged on the API side.
			$available_rates = $this->shipStationApi->get_shipping_estimates( $api_request );

			if( is_wp_error( $available_rates ) || empty( $available_rates ) ) {
				continue;
			}

			// Loop the found rates and setup the WooCommerce rates array for each.
			foreach( $available_rates as $shiprate ) {

				if( ! isset( $enabled_services[ $shiprate['carrier_id'] ][ $shiprate['code'] ] ) ) {
					continue;
				}

				$service_arr = $enabled_services[ $shiprate['carrier_id'] ][ $shiprate['code'] ];
				$cost = floatval( $shiprate['cost'] );
				$ratemeta = array(
					'_name'=> ( isset( $req['_name'] ) ) ? $req['_name'] : '', // Item product name.
					'rate' => $cost,
				);

				// Apply service upcharge
				if( isset( $service_arr['adjustment'] ) ) {

					/**
					 * Adjustment type could be '' to skip global adjustment.
					 * Defaults to percentage for v1.03 backwards compatibility.
					 */
					$adjustment 	 = floatval( $service_arr['adjustment'] );
					$adjustment_type = ( isset( $service_arr['adjustment_type'] ) ) ? $service_arr['adjustment_type'] : 'percentage';

					if( ! empty( $adjustment_type ) && $adjustment > 0 ) {

						$adjustment_cost = ( 'percentage' == $adjustment_type ) ? ( $cost * ( floatval( $adjustment ) / 100 ) ) : floatval( $adjustment );
						$ratemeta['adjustment'] = array(
							'type' => $adjustment_type,
							'rate' => $adjustment,
							'cost' => $adjustment_cost,
							'global'=> false,
						);
						$cost += $adjustment_cost;

					}

				} else if( ! empty( $global_adjustment_type ) && $global_adjustment > 0 ) {

					$adjustment_cost = ( 'percentage' == $global_adjustment_type ) ? ( $cost * ( floatval( $global_adjustment ) / 100 ) ) : floatval( $global_adjustment );
					$ratemeta['adjustment'] = array(
						'type' => $global_adjustment_type,
						'rate' => $global_adjustment,
						'cost' => $adjustment_cost,
						'global'=> true,
					);
					$cost += $adjustment_cost;

				}

				// Loop and add any other shipment amounts.
				if( ! empty( $shiprate['other_costs'] ) ) {

					$ratemeta['other_costs'] = array();
					foreach( $shiprate['other_costs'] as $slug => $cost_arr ) {

						if( empty( $cost_arr['amount'] ) ) continue;
						$cost += floatval( $cost_arr['amount'] );
						$ratemeta['other_costs'][ $slug ] = $cost_arr['amount'];

					}
				}

				// Maybe a package price
				if( 'wc-box-packer' == $packing_type && ! empty( $req['price'] ) ) {
					$cost += floatval( $req['price'] );
					$ratemeta['other_costs']['box_price'] = $req['price'];
				}

				// Maybe apply per item.
				if( 'individual' == $packing_type ) {
					$cost *= $packages['contents'][ $item_id ]['quantity'];
					$ratemeta['qty'] = $packages['contents'][ $item_id ]['quantity'];
				}

				// Set rate or append the estimated item ship cost.
				if( ! isset( $rates[ $shiprate['code'] ] ) ) {

					$rates[ $shiprate['code'] ] = array(
						'id'		=> $shiprate['code'],
						'label'		=> ( ! empty( $service_arr['nickname'] ) ) ? $service_arr['nickname'] : $shiprate['name'],
						'package'	=> $packages,
						'cost'		=> array( $cost ),
						'meta_data' => array(
							'carrier'	=> $shiprate['carrier_name'],
							'service'	=> $shiprate['name'],
							'rates'		=> array(),
							'boxes'		=> array(),

							// Private metadata fields must be excluded via filter way above.
							"_{$this->plugin_prefix}_carrier_id"	=> $shiprate['carrier_id'],
							"_{$this->plugin_prefix}_carrier_code"	=> $shiprate['carrier_code'],
							"_{$this->plugin_prefix}_service_code"	=> $shiprate['code'],
						),
					);

				} else {
					$rates[ $shiprate['code'] ]['cost'][] = $cost;
				}

				// Merge item rates
				$rates[ $shiprate['code'] ]['meta_data']['rates'] = array_merge(
					$rates[ $shiprate['code'] ]['meta_data']['rates'],
					array( $ratemeta ),
				);

				// Merge item boxes
				$rates[ $shiprate['code'] ]['meta_data']['boxes'] = array_merge(
					$rates[ $shiprate['code'] ]['meta_data']['boxes'],
					array( $req ),
				);

			}

		}

		$single_lowest 		 = \IQLRSS\Driver::get_ss_opt( 'return_lowest', 'no' );
		$single_lowest_label = \IQLRSS\Driver::get_ss_opt( 'return_lowest_label', '' );

		// Add all shipping rates, let the user decide.
		if( 'no' == $single_lowest || empty( $single_lowest ) ) {

			foreach( $rates as $rate_arr ) {

				// Skip incomplete rate requests
				if( count( $item_requests ) != count( $rate_arr['cost'] ) ) {
					continue;
				}

				// WooCommerce skips serialized data when outputting order item meta, this is a workaround.
				// See hooks above for formatting.
				$rate_arr['meta_data']['rates'] = json_encode( $rate_arr['meta_data']['rates'] );
				$rate_arr['meta_data']['boxes'] = json_encode( $rate_arr['meta_data']['boxes'] );

				$this->add_rate( $rate_arr );
			}

		// Find the single lowest shipping rate
		} else if( 'yes' == $single_lowest ) {

			$lowest = 0;
			$lowest_service = array_key_first( $rates );
			foreach( $rates as $service_id => $rate_arr ) {

				$total = array_sum( $rate_arr['cost'] );
				if( 0 == $lowest || $total < $lowest ) {
					$lowest = $total;
					$lowest_service = $service_id;
				}
			}

			if( ! empty( $single_lowest_label ) ) {
				$rates[ $lowest_service ]['label'] = $single_lowest_label;
			}

			// WooCommerce skips serialized data when outputting order item meta, this is a workaround.
			// See hooks above for formatting.
			$rates[ $lowest_service ]['meta_data']['rates'] = json_encode( $rates[ $lowest_service ]['meta_data']['rates'] );
			$rates[ $lowest_service ]['meta_data']['boxes'] = json_encode( $rates[ $lowest_service ]['meta_data']['boxes'] );

			$this->add_rate( $rates[ $lowest_service ] );

		}

		// Add a cache key to check against.
		// @todo - Re-enable
		// WC()->session->set( $this->plugin_prefix, array_merge(
		// 	WC()->session->get( $this->plugin_prefix, array() ),
		// 	array( 'method_hash' => $packages_hash ),
		// 	array( 'method_cache_time' => time() ),
		// ) );

	}


	/**
	 * Return an array of API requests which would be for individual products.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	protected function group_requestsby_individual( $items ) {

		$item_requests 	= array();
		$default_weight = $this->get_option( 'minweight', '' );

		foreach( $items as $item_id => $item ) {

			// Continue - No shipping needed for product.
			if( ! $item['data']->needs_shipping() ) {
				continue;
			}

			$request = array(
				'_name' => sprintf( '%s|%s',
					$item['data']->get_id(),
					$item['data']->get_name(),
				),
				'weight' => ( ! empty( $item['data']->get_weight() ) ) ? $item['data']->get_weight() : $default_weight,
			);
			$physicals = array_filter( array(
				'length'	=> $item['data']->get_length(),
				'width'		=> $item['data']->get_width(),
				'height'	=> $item['data']->get_height(),
			) );

			// Return Early - Product missing one of the 4 key dimensions.
			if( count( $physicals ) < 3 || empty( $request['weight'] ) ) {
				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions. Weight is a minimum requirement. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$item['product_id'],
					implode( ', ', array_diff_key( array(
						'length'	=> 'length',
						'width'		=> 'width',
						'height'	=> 'height',
						'weight'	=> 'weight',
					), $physicals + array( 'weight' => $request['weight'] ) ) )
				) );

				return array();
			}

			// Set rate request dimensions.
			sort( $physicals );
			if( 3 == count( $physicals ) ) {
				$request['dimensions'] = array(
					'length'	=> round( wc_get_dimension( $physicals[2], $this->store_data['dim_unit'] ), 2 ),
					'width'		=> round( wc_get_dimension( $physicals[1], $this->store_data['dim_unit'] ), 2 ),
					'height'	=> round( wc_get_dimension( $physicals[0], $this->store_data['dim_unit'] ), 2 ),
					'unit'		=> $this->shipStationApi->convert_unit_term( $this->store_data['dim_unit'] ),
				);
			}

			// Set rate request weight.
			if( ! empty( $request['weight'] ) ) {
				$request['weight'] = array(
					'value' => (float)round( wc_get_weight( $request['weight'], $this->store_data['weight_unit'] ), 2 ),
					'unit'	=> $this->shipStationApi->convert_unit_term( $this->store_data['weight_unit'] ),
				);
			}

			$item_requests[ $item_id ] = $request;

		}

		return $item_requests;

	}


	/**
	 * One Big Box
	 * Group all the products by weight and get rates by total weight.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	protected function group_requestsby_onebox( $items ) {

		$default_weight = $this->get_option( 'minweight', 0 );
		$subtype 		= $this->get_option( 'packing_sub', 'weightonly' );
		$dimensions = array(
			'running' => array_combine( array( 'length', 'width', 'height', 'weight' ), array_fill( 0, 4, 0 ) ),
			'largest' => array_combine( array( 'length', 'width', 'height', 'weight' ), array_fill( 0, 4, 0 ) ),
		);

		foreach( $items as $item_id => $item ) {

			// Continue - No shipping needed for product.
			if( ! $item['data']->needs_shipping() ) {
				continue;
			}

			$request = array(
				'weight' => ( ! empty( $item['data']->get_weight() ) ) ? $item['data']->get_weight() : $default_weight,
			);
			$physicals = array_filter( array(
				'length'	=> $item['data']->get_length(),
				'width'		=> $item['data']->get_width(),
				'height'	=> $item['data']->get_height(),
			) );

			// Return Early - Missing minimum requirement: weight.
			if( empty( $request['weight'] ) ) {

				$this->log( sprintf(

					/* translators: %1$d is the Product ID. */
					esc_html__( 'Product ID #%1$d missing weight. Shipping Zone weight fallback could not be used. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$item['product_id']
				) );

				return array();

			} else if( 'weightonly' != $subtype && count( $physicals ) < 3 ) {

				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$item['product_id'],
					implode( ', ', array_diff_key( array(
						'length'	=> 'length',
						'width'		=> 'width',
						'height'	=> 'height',
					), $physicals ) )
				) );

			}

			$dimensions['running']['weight'] = $dimensions['running']['weight'] + ( floatval( $request['weight'] ) * $item['quantity'] );
			$dimensions['running']['height'] = $dimensions['running']['height'] + ( floatval( $item['data']->get_height() ) * $item['quantity'] );
			$dimensions['largest'] = array(
				'length'	=> ( $dimensions['largest']['length'] < $item['data']->get_length() ) ? $item['data']->get_length() : $dimensions['largest']['length'],
				'width'		=> ( $dimensions['largest']['width'] < $item['data']->get_width() )   ? $item['data']->get_width()  : $dimensions['largest']['width'],
				'height'	=> ( $dimensions['largest']['height'] < $item['data']->get_height() ) ? $item['data']->get_height() : $dimensions['largest']['height'],
				'weight'	=> ( $dimensions['largest']['weight'] < $request['weight'] )		  ? $request['weight']			: $dimensions['largest']['weight'],
			);

		}

		// Return Early - Rates by total weight.
		if( 'weightonly' == $subtype ) {

			return array( array(
				'weight' => array(
					'value' => (float)round( wc_get_weight( $dimensions['running']['weight'], $this->store_data['weight_unit'] ), 2 ),
					'unit'	=> $this->shipStationApi->convert_unit_term( $this->store_data['weight_unit'] ),
				),
			) );

		}

		// Default - Stacked Verticially
		return array( array(
			'weight' => array(
				'value' => (float)round( wc_get_weight( $dimensions['running']['weight'], $this->store_data['weight_unit'] ), 2 ),
				'unit'	=> $this->shipStationApi->convert_unit_term( $this->store_data['weight_unit'] ),
			),
			'dimensions' => array(
				'unit'		=> $this->shipStationApi->convert_unit_term( $this->store_data['dim_unit'] ),

				// Largest
				'length'	=> round( wc_get_dimension( $dimensions['largest']['length'], $this->store_data['dim_unit'] ), 2 ),
				'width'		=> round( wc_get_dimension( $dimensions['largest']['width'], $this->store_data['dim_unit'] ), 2 ),

				// Running
				'height'	=> round( wc_get_dimension( $dimensions['running']['height'], $this->store_data['dim_unit'] ), 2 ),
			),
		) );

	}


	/**
	 * Return an array of API requests for custom packed boxes.
	 * Shoutout to Mike Jolly & Co.
	 *
	 * @param Array $items
	 *
	 * @return Array $requests
	 */
	protected function group_requestsby_wc_box_packer( $items ) {

		if( ! class_exists( '\IQRLSS\WC_Box_Packer\WC_Boxpack' ) ) {
			include_once 'wc-box-packer/class-wc-boxpack.php';
		}

		$item_requests 	= array();
		$wc_boxpack 	= new WC_Box_Packer\WC_Boxpack();
		$boxes 			= $this->get_option( 'customboxes', array() );
		$default_weight = $this->get_option( 'minweight', '' );

		if( empty( $boxes ) ) {
			$this->log( esc_html__( 'Custom Boxes selected, but no boxes found. Items packed individually', 'live-rates-for-shipstation' ), 'warning' );
		}

		// Setup the WC_Boxpack boxes based on user submitted custom boxes.
		foreach( $boxes as $box ) {
			if( empty( $box['active'] ) ) continue;
			$wc_boxpack->add_box( $box );
		}

		// Loop the items, grabs their dimensions, and assocaite them with WC_Boxpack for future packing.
		foreach( $items as $item_id => $item ) {
			if( ! $item['data']->needs_shipping() ) continue;

			$weight	= ( ! empty( $item['data']->get_weight() ) ) ? $item['data']->get_weight() : $default_weight;
			$data	= array(
				'weight' => (float)round( wc_get_weight( $weight, $this->store_data['weight_unit'] ), 2 ),
			);
			$physicals = array_filter( array(
				'length'	=> $item['data']->get_length(),
				'width'		=> $item['data']->get_width(),
				'height'	=> $item['data']->get_height(),
			) );

			// Return Early - Product missing one of the 4 key dimensions.
			if( count( $physicals ) < 3 && empty( $data['weight'] ) ) {
				$this->log( sprintf(

					/* translators: %1$d is the Product ID. %2$s is the Product Dimensions separated by a comma. */
					esc_html__( 'Product ID #%1$d missing (%2$s) dimensions and no weight found. Shipping calculations terminated.', 'live-rates-for-shipstation' ),
					$item['product_id'],
					implode( ', ', array_diff_key( array(
						'width'		=> 'width',
						'height'	=> 'height',
						'length'	=> 'length',
					), $physicals ) )
				) );
				return array();
			}

			sort( $physicals );
			$data = array(
				'length'	=> round( wc_get_dimension( $physicals[2], $this->store_data['dim_unit'] ), 2 ),
				'width'		=> round( wc_get_dimension( $physicals[1], $this->store_data['dim_unit'] ), 2 ),
				'height'	=> round( wc_get_dimension( $physicals[0], $this->store_data['dim_unit'] ), 2 ),
				'weight'	=> round( wc_get_weight( $data['weight'], $this->store_data['weight_unit'] ), 2 ),
			);

			// Pack Products
			for( $i = 0; $i < $item['quantity']; $i++ ) {
				$wc_boxpack->add_item(
					$data['length'],
					$data['width'],
					$data['height'],
					$data['weight'],
					$item['data']->get_price(),
					array(
						'_name' => sprintf( '%s|%s',
							$item['data']->get_id(),
							$item['data']->get_name(),
						),
					),
				);
			}
		}

		// Pack it up, missions over.
		$wc_boxpack->pack();
		$wc_box_packages = $wc_boxpack->get_packages();
		$box_log = array();

		// Delivery!
		foreach( $wc_box_packages as $key => $package ) {

			$packed_items = ( is_array( $package->packed ) ) ? array_map( function( $item ) { return $item->meta['_name']; }, $package->packed ) : array();
			$item_requests[] = array(
				'weight' => array(
					'value' => $package->weight,
					'unit'	=> $this->shipStationApi->convert_unit_term( $this->store_data['weight_unit'] ),
				),
				'dimensions' => array(
					'length'	=> $package->length,
					'width'		=> $package->width,
					'height'	=> $package->height,
					'unit'		=> $this->shipStationApi->convert_unit_term( $this->store_data['dim_unit'] ),
				),
				'packed' => $packed_items,
				'price'	 => ( ! empty( $package->data ) ) ? $package->data['price'] : 0,
			);

			$box_log[] = array(
				'is_packed'		 => boolval( empty( $package->unpacked ) ),
				'item_count'	 => count( $package->packed ),
				'items'			 => $packed_items,
				'box_dimensions' => sprintf( '%s x %s x %s | %s | %s', $package->length, $package->width, $package->height, $package->weight, $package->volume ),
				'box_dim_key'	 => sprintf( '%s x %s x %s | %s | %s',
					esc_html__( 'Length', 'live-rates-for-shipstation' ),
					esc_html__( 'Width', 'live-rates-for-shipstation' ),
					esc_html__( 'Height', 'live-rates-for-shipstation' ),
					esc_html__( 'Weight', 'live-rates-for-shipstation' ),
					esc_html__( 'Volume', 'live-rates-for-shipstation' ),
				),
				'max_volume' => floatval( $package->width * $package->height * $package->length ),
				'data' => ( ! empty( $package->data ) ) ? $package->data : array(),
			);

		}

		if( ! empty( $box_log ) ) {
			$this->log( esc_html__( 'Custom Boxes Packed', 'live-rates-for-shipstation' ), 'info', $box_log );
		}

		return $item_requests;

	}


	/**
	 * Attempt to pull from the WC() Session cache to prevent multiple caclulation
	 * requests, which could unnecessarily ping the API or add duplicate logs.
	 * This issue is common when dealing with WP Blocks/Gutenberg Editor.
	 *
	 * @param Array $packages - Packages in use.
	 *
	 * @return String $hash - hash key neded to reset cache.
	 */
	protected function check_packages_rate_cache( $packages ) {

		$session 	= WC()->session->get( $this->plugin_prefix, array() );
		$cleartime 	= get_transient( \IQLRSS\Driver::plugin_prefix( 'wcs_timeout' ) );

		$keys = array();
		foreach( $packages['contents'] as $key => $package ) {
			$keys[] = array(
				$key,
				$package['quantity'],
				$package['line_total'],
			);
		}
		$hash = md5( wp_json_encode( $keys ) ) . \WC_Cache_Helper::get_transient_version( 'shipping' );

		// Return Early - Cache cleared or 30 minuites has passed (invalidate cache).
		if( isset( $session['method_cache_time'] ) && ( $cleartime > $session['method_cache_time'] || $session['method_cache_time'] < ( time() - ( 30 * 60 ) ) ) ) {
			return $hash;

		// Return Early- Cart has changed.
		} else if( ! isset( $session['method_hash'] ) || $session['method_hash'] != $hash ) {
			return $hash;
		}

		// Try to populate Rates.
		$size = count( $packages );
		for( $i = 0; $i < $size; $i++ ) {

			$cache = WC()->session->get( 'shipping_for_package_' . $i, false );
			if( empty( $cache ) || ! is_array( $cache ) ) {
				break;
			}
			$this->rates = array_merge( $cache['rates'], $this->rates );

		}

		return $hash;

	}


	/**
	 * Generate a hash key based off of the given packages.
	 *
	 * @param Array $packages
	 *
	 * @return String $hash
	 */
	protected function generate_packages_cache_key( $packages ) {

		// Maybe skip if cache was cleared.
		$session 	= WC()->session->get( $this->plugin_prefix, array() );
		$cleartime 	= get_transient( \IQLRSS\Driver::plugin_prefix( 'wcs_timeout' ) );
		if( isset( $session['method_cache_time'] ) && $cleartime > $session['method_cache_time'] ) {
			return '';
		}

		$keys = array();
		foreach( $packages['contents'] as $key => $package ) {
			$keys[] = array(
				$key,
				$package['quantity'],
				$package['line_total'],
			);
		}

		$hash = md5( wp_json_encode( $keys ) ) . \WC_Cache_Helper::get_transient_version( 'shipping' );
		return ( ! empty( $keys ) ) ? $hash : '';

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Return an array of Price Adjustment Type options.
	 *
	 * @return Array
	 */
	public static function get_adjustment_types( $include_empty = false ) {

		$types = array(
			'flatrate'	 => esc_html__( 'Flat Rate', 'live-rates-for-shipstation' ),
			'percentage' => esc_html__( 'Percentage', 'live-rates-for-shipstation' ),
		);

		return ( false == $include_empty ) ? $types : array_merge( array(
			'' => esc_html__( 'No Adjustments', 'live-rates-for-shipstation' ),
		), $types );

	}


	/**
	 * Return an m-array of enabled services grouped by carrier key.
	 *
	 * @return Array
	 */
	public function get_enabled_services() {

		$enabled = array();
		$saved_services = $this->get_option( 'services', array() );
		if( empty( $saved_services ) ) return $enabled;

		foreach( $saved_services as $c => $sa ) {
			foreach( $sa as $sk => $s ) {
				if( ! isset( $s['enabled'] ) || ! $s['enabled'] ) continue;
				$enabled[ $c ][ $sk ] = $s;
			}
		}

		return $enabled;

	}


	/**
	 * Format a stringified product name.
	 * ex. 213|Shirt|optional|meta|data
	 *
	 * @param String $shipitem_name
	 * @param Boolean $link
	 * @param String $context - edit|view
	 *
	 * @return String $name
	 */
	public function format_shipitem_name( $shipitem_name, $link = false, $context = 'edit' ) {

		$name = mb_strimwidth( $shipitem_name, 0, 47, '...' );
		$name_arr = explode( '|', $shipitem_name );

		if( count( $name_arr ) >= 2 ) {

			$name = mb_strimwidth( $name_arr[1], 0, 47, '...' );
			if( $link ) {
				$name = sprintf( '<a href="%s" target="_blank" title="%s">%s</a>',
					( 'edit' == $context ) ? get_edit_post_link( $name_arr[0] ) : get_permalink( $name_arr[0] ),
					esc_attr( $name_arr[1] ),
					$name
				);
			}

		}

		return $name;

	}


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

			$this->logger->log( $level, $error_msg, array_merge( $context, array( 'source' => 'live-rates-for-shipstation' ) ) );

		}

		return $error;

	}

}