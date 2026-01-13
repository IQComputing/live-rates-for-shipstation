<?php
/**
 * ShipStation Live Shipping Rates Method
 *
 * @todo Consider moving Shipping Calculations into it's own class.
 *
 * @link https://www.fedex.com/en-us/shipping/one-rate.html
 * @link https://www.usps.com/ship/priority-mail.htm#flatrate
 * @link https://www.ups.com/worldshiphelp/WSA/ENG/AppHelp/mergedProjects/CORE/Codes/Package_Type_Codes.htm
 *
 * :: Action Hooks
 * :: Filter Hooks
 * :: Shipping Zone
 * :: Shipping Calculations
 * :: Helper Methods
 */
namespace IQLRSS\Core;
use \IQLRSS\Core\Traits;

if( ! defined( 'ABSPATH' ) ) {
	return;
} else if( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

class Shipping_Method_Shipstation extends \WC_Shipping_Method  {

	/**
	 * Inherit logger traits
	 */
	use Traits\Logger;


	/**
	 * Plugin prefix used to namespace data keys.
	 *
	 * @var String
	 */
	protected $plugin_prefix;


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
		add_action( 'admin_footer', array( $this, 'hide_zone_setting_fields' ) );

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
	 * Hide Shipping Zone setting fields
	 * 1). Since they're row options we rarely have markup control over.
	 * 2). Since modules load JS a bit later.
	 *
	 * @return void
	 */
	public function hide_zone_setting_fields() {

		?><script type="text/javascript">

			/* Hide onebox when not set */
			if( document.getElementById( 'woocommerce_iqlrss_shipstation_packing' ) ) { ( function() {
				if( 'onebox' != document.getElementById( 'woocommerce_iqlrss_shipstation_packing' ).value ) {
					document.getElementById( 'woocommerce_iqlrss_shipstation_packing' ).closest( 'tr' ).nextElementSibling.style.display = 'none';
				}
			} )(); }
		</script><?php

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
	 * Sometimes ShipStation takes awhile to respond with rates.
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
									$new_display .= sprintf( ' | %s: %s', ucwords( str_replace( array( '-', '_' ), ' ', $o_slug ) ), wc_price( $o_amount ) );
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

						/* translators: %1$d is box/package count (1,2,3). */
						$box_name = sprintf( esc_html__( 'Package %1$d', 'live-rates-for-shipstation' ), $i + 1 );
						if( ! empty( $box_arr['nickname'] ) ) {
							$box_name = $box_arr['nickname'];
						}

						$names = esc_html__( 'Product', 'live-rates-for-shipstation' );
						if( isset( $box_arr['_name'] ) ) {
							$names = $this->format_shipitem_name( $box_arr['_name'] );
						} else if( ! empty( $box_arr['packed'] ) ) {
							$names = array_map( function( $name ) {
								return $this->format_shipitem_name( $name );
							}, $box_arr['packed'] );
						}
						$display_arr[] = sprintf( '%s ( %s ) [ %s %s ( %s x %s x %s %s ) ]',
							$box_name,
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

		$warehouses = array(
			'' => esc_html__( 'Website Store Address', 'live-rates-for-shipstation' ),
		);

		if( ! empty( \IQLRSS\Driver::get_ss_opt( 'api_key' ) ) ) {

			$og_warehouse = \IQLRSS\Driver::get_ss_opt( 'global_warehouse' );
			$api = new Api\Shipstation();

			// Grab Warehouse options
			$api_warehouses = $api->get_warehouses();
			if( is_a( $api_warehouses, 'WP_Error' ) ) {
				$warehouses = array( '' => $api_warehouses->get_error_message() );
			} else if( is_array( $api_warehouses ) && ! empty( $api_warehouses ) ) {
				$warehouses = array_merge( $warehouses, array_combine(
					array_keys( $api_warehouses ),
					array_column( $api_warehouses, 'name' ),
				) );
			}

			// A little switcharoo?
			if( ! empty( $og_warehouse ) && isset( $warehouses[ $og_warehouse ] ) ) {
				$warehouses[''] = $warehouses[ $og_warehouse ];
				unset( $warehouses[ $og_warehouse ] );
				$warehouses['_woo_default'] = esc_html__( 'Website Store Address', 'live-rates-for-shipstation' );
			}
		}

		$settings = array(
			'title' => array(
				'title'			=> esc_html__( 'Title', 'live-rates-for-shipstation' ),
				'type'			=> 'text',
				'description'	=> esc_html__( 'This controls the title which the user sees during checkout.', 'live-rates-for-shipstation' ),
				'default'		=> esc_html__( 'ShipStation Rates', 'live-rates-for-shipstation' ),
				'desc_tip'		=> true,
			),
			'warehouse' => array(
				'title'			=> esc_html__( 'Shipping Location', 'live-rates-for-shipstation' ),
				'type'			=> 'select',
				'options'		=> $warehouses,
				'description'	=> esc_html__( 'Select to ship from a different location than the default.', 'live-rates-for-shipstation' ),
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


		/**
		 * Allow filtering the Shipping Zone settings
		 *
		 * @hook filter
		 *
		 * @param Array $settings
		 * @param \IQLRSS\Core\Shipping_Method_Shipstation $this
		 *
		 * @return Array $settings
		 */
		$settings = apply_filters( 'iqlrss/zone/settings', $settings, $this );
		$this->instance_form_fields = $settings;

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
		$packages		= $this->get_package_options();

		ob_start();
			include \IQLRSS\Driver::get_asset_path( 'views/shipping-zone/customboxes-table.php' );
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
					'preset' => ( isset( $json['preset'] ) ) ? sanitize_text_field( $json['preset'] ) : '',
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
					'carrier_code' => ( isset( $json['carrier_code'] ) ) ? sanitize_text_field( $json['carrier_code'] ) : '',
				);

			}
		}

		usort( $boxes, function( $arrA, $arrB ) {
			return strcasecmp( $arrA['nickname'], $arrB['nickname'] );
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
			include \IQLRSS\Driver::get_asset_path( 'views/shipping-zone/services-table.php' );
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
	 * @param Array $packages
	 *
	 * @return void
	 */
	public function calculate_shipping( $packages = array() ) {

		if( empty( $packages ) || empty( $packages['contents'] ) ) {
			return;
		}

		// Try to pull from cache. This may set $this->rates
		// Return Early - We have cached rates to work with!
		$this->check_packages_rate_cache( $packages );
		if( ! empty( $this->rates ) ) {
			return;

		// Return Early - No Destination to work with. Postcode is kinda required.
		} else if( ! isset( $packages['destination'] ) || empty( $packages['destination']['postcode'] ) ) {
			return;
		}

		// Grab the calculator to be filtered.
		$calculator = new Utility\Shipping_Calculator( $packages, array(
			'method' => $this,
		) );


		/**
		 * Allow overriding the Shipping Calculator object.
		 * Must inherit IQLRSS\Core\Utility\Shipping_Calculator
		 *
		 * @hook filter
		 *
		 * @param \IQLRSS\Core\Utility\Shipping_Calculator $calculator
		 * @param Array $packages - The cart contents. See $packages['contents'] for items.
		 * @param \IQLRSS\Core\Shipping_Method_Shipstation $this
		 *
		 * @return Array $settings
		 */
		$maybe_calc = apply_filters( 'iqlrss/shipping/calculator_object', $calculator, $packages, $this );
		if( is_object( $maybe_calc ) && $maybe_calc !== $calculator ) {

			// Override calculator object and log it's change.
			if( is_subclass_of( $maybe_calc, '\IQLRSS\Core\Utility\Shipping_Calculator' ) ) {

				$calculator = $maybe_calc;
				$this->log( sprintf( '%s [%s]',
					esc_html__( 'Shipping Calculations Object overridden.', 'live-rates-for-shipstation' ),
					get_class( $maybe_calc )
				), 'notice' );

			// Something went wrong, log that too.
			} else {

				$this->log( sprintf( '%s [%s]',
					esc_html__( 'Shipping Calculations Object override failed. Class does not inherit "\IQLRSS\Core\Utility\Shipping_Calculator".', 'live-rates-for-shipstation' ),
					get_class( $maybe_calc )
				) );

			}
		}

		// Get and Add Rates - EZPZ
		if( $rates = $calculator->get_rates() ) {
			foreach( $rates as $rate ) {
				$this->add_rate( $rate );
			}
		}

		$cachehash = $this->generate_packages_cache_key( $packages );
		if( empty( $cachehash ) ) return;

		// Cache packages to prevent multiple requests.
		WC()->session->set( $this->plugin_prefix . '_packages', array_merge(
			WC()->session->get( $this->plugin_prefix . '_packages', array() ),
			array( 'method_hash' => $cachehash ),
			array( 'method_cache_time' => time() ),
		) );

	}


	/**
	 * Set the rates based on cached packages.
	 *
	 * Attempt to pull from the WC() Session cache to prevent multiple calculations
	 * requests, which could unnecessarily ping the API or add duplicate logs.
	 * This issue is common when dealing with WP Blocks/Gutenberg Editor.
	 *
	 * @param Array $packages - Packages in use.
	 *
	 * @return void
	 */
	protected function check_packages_rate_cache( $packages ) {

		$session 	= WC()->session->get( $this->plugin_prefix . '_packages', array() );
		$cleartime 	= get_transient( \IQLRSS\Driver::plugin_prefix( 'wcs_timeout' ) );
		$cachehash 	= $this->generate_packages_cache_key( $packages );

		// Return Early - Cache cleared or 30 minuites has passed (invalidate cache).
		if( isset( $session['method_cache_time'] ) && ( $cleartime > $session['method_cache_time'] || $session['method_cache_time'] < ( time() - ( 30 * 60 ) ) ) ) {
			return;

		// Return Early- Cart has changed.
		} else if( ! isset( $session['method_hash'] ) || empty( $cachehash ) || $session['method_hash'] != $cachehash ) {
			return;
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

	}


	/**
	 * Generate a hash key based off of the given packages.
	 *
	 * @param Array $packages
	 *
	 * @return String $hash
	 */
	protected function generate_packages_cache_key( $packages ) {

		$keys = array();
		foreach( $packages['contents'] as $key => $package ) {
			$keys[] = array(
				$key,
				$package['quantity'],
				$package['line_total'],
			);
		}

		if( empty( $keys ) ) return '';
		return md5( wp_json_encode( $keys ) ) . \WC_Cache_Helper::get_transient_version( 'shipping' );

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Map known packages.
	 * @see assets/json
	 *
	 * @param String $key
	 *
	 * @return String
	 */
	public function get_package_label( $key ) {

		$labels = array(
			// UPS
			'flat_rate_envelope'	=> esc_html__( 'USPS Flat Rate Envelope', 'live-rates-for-shipstation' ),
			'flat_rate_legal_envelope'	=> esc_html__( 'USPS Flat Rate Legal Envelope', 'live-rates-for-shipstation' ),
			'flat_rate_padded_envelope'	=> esc_html__( 'USPS Flat Rate Padded Envelope', 'live-rates-for-shipstation' ),
			'large_envelope_or_flat'=> esc_html__( 'USPS Large Envelope or Flat', 'live-rates-for-shipstation' ),
			'large_flat_rate_box'	=> esc_html__( 'USPS Large Flat Rate Box', 'live-rates-for-shipstation' ),
			'medium_flat_rate_box'	=> esc_html__( 'USPS Medium Flat Rate Box', 'live-rates-for-shipstation' ),
			'small_flat_rate_box'	=> esc_html__( 'USPS Small Flat Rate Box', 'live-rates-for-shipstation' ),
			'regional_rate_box_a'	=> esc_html__( 'USPS Regional Rate Box A', 'live-rates-for-shipstation' ),
			'regional_rate_box_b'	=> esc_html__( 'USPS Regional Rate Box B', 'live-rates-for-shipstation' ),

			// USPS
			'ups_10_kg_box'			=> esc_html__( 'UPS 10kg (22lbs) Box', 'live-rates-for-shipstation' ),
			'ups_25_kg_box'			=> esc_html__( 'UPS 25kg (55lbs) Box', 'live-rates-for-shipstation' ),
			'ups__express_box_large'=> esc_html__( 'UPS Express Box - Large', 'live-rates-for-shipstation' ), // Why does this have an extra underscore? Ask ShipStation.
			'ups_express_box_medium'=> esc_html__( 'UPS Express Box - Medium', 'live-rates-for-shipstation' ),
			'ups_express_box_small'	=> esc_html__( 'UPS Express Box - Small', 'live-rates-for-shipstation' ),
			'ups_tube'				=> esc_html__( 'UPS Tube', 'live-rates-for-shipstation' ),
			'ups_express_pak'		=> esc_html__( 'UPS Express Pak', 'live-rates-for-shipstation' ),
			'ups_letter'			=> esc_html__( 'UPS Letter', 'live-rates-for-shipstation' ),

			// FedEx
			'fedex_10kg_box'	=> esc_html__( 'FedEx 10kg (22lbs) Box', 'live-rates-for-shipstation' ),
			'fedex_25kg_box'	=> esc_html__( 'FedEx 25kg (55lbs) Box', 'live-rates-for-shipstation' ),
			'fedex_extra_large_box' => esc_html__( 'FedEx Extra Large Box', 'live-rates-for-shipstation' ),
			'fedex_large_box'	=> esc_html__( 'FedEx Large Box', 'live-rates-for-shipstation' ),
			'fedex_medium_box'	=> esc_html__( 'FedEx Medium Box', 'live-rates-for-shipstation' ),
			'fedex_small_box'	=> esc_html__( 'FedEx Small Box', 'live-rates-for-shipstation' ),
			'fedex_tube'		=> esc_html__( 'FedEx Tube', 'live-rates-for-shipstation' ),
			'fedex_envelope'	=> esc_html__( 'FedEx Envelope', 'live-rates-for-shipstation' ),
			'fedex_pak'			=> esc_html__( 'FedEx Padded Pak', 'live-rates-for-shipstation' ),
		);

		return ( isset( $labels [ $key ] ) ) ? $labels[ $key ] : esc_html__( 'Unknown Package', 'live-rates-for-shipstation' );

	}


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
	 * Convert a WooCommerce unit to a ShipStation unit.
	 *
	 * @param String $unit
	 *
	 * @return String $new_unit
	 */
	public function convert_unit_term( $unit ) {
		return $this->shipStationApi->convert_unit_term( $unit );
	}


	/**
	 * Return an array of package options.
	 *
	 * @return Array
	 */
	protected function get_package_options() {

		$packages = wp_cache_get( 'packages', $this->plugin_prefix );
		if( ! empty( $packages ) ) {
			return $packages;
		}

		$global_carriers= $this->shipStationApi->get_carriers();
		$carrier_codes	= wp_list_pluck( $global_carriers, 'carrier_code' );
		$carrier_codes	= array_intersect_key( $carrier_codes, array_flip( $this->carriers ) );

		$data = array(
			'usps' => array(
				'label'		=> esc_html__( 'USPS', 'live-rates-for-shipstation' ),
				'packages'	=> json_decode( file_get_contents( \IQLRSS\Driver::get_asset_path( 'json/usps-packages.json' ) ), true ),
			),
			'ups'	=> array(
				'label'		=> esc_html__( 'UPS', 'live-rates-for-shipstation' ),
				'packages'	=> json_decode( file_get_contents( \IQLRSS\Driver::get_asset_path( 'json/ups-packages.json' ) ), true ),
			),
			'fedex' => array(
				'label'		=> esc_html__( 'FedEx', 'live-rates-for-shipstation' ),
				'packages'	=> json_decode( file_get_contents( \IQLRSS\Driver::get_asset_path( 'json/fedex-packages.json' ) ), true ),
			),
		);

		// Append Translated Labels
		$carrier_packages = array();
		foreach( $data as $carrier_code => &$carriers ) {

			// Match carrier slug with known carrier code.
			$carrier_found = array_filter( $carrier_codes, fn( $c ) => $c === $carrier_code );
			if( empty( $carrier_found ) ) {
				$carrier_found = array_filter( $carrier_codes, fn( $c ) => false !== strpos( $c, $carrier_code . '_' ) );
			}

			// Skip - Carrier may not be set.
			if( empty( $carrier_found ) ) continue;

			$codes = wp_list_pluck( $carriers['packages'], 'code' );
			$dupes = array_count_values( $codes );

			foreach( $carriers['packages'] as &$package ) {

				$package['carrier_code'] = $carrier_code;
				$package['label'] = $this->get_package_label( $package['code'] );

				if( $dupes[ $package['code'] ] > 1 ) {
					$package['label'] .= sprintf( ' (%s x %s x %s)', $package['length'], $package['width'], $package['height'] );
				}
			}

			usort( $carriers['packages'], fn( $pa, $pb ) => strcmp( $pa['label'], $pb['label'] ) );
			$carrier_packages[ $carrier_code ] = $carriers;

		}

		$data = array( '' => esc_html__( '-- Select Package Preset --', 'live-rates-for-shipstation' ) ) + $carrier_packages;


		/**
		 * Allow hooking into Custom Package presets for 3rd party management.
		 *
		 * @hook filter
		 *
		 * @param Array $data - Array( Array(
		 * 		'label' => 'Optional Optgroup Name',
		 * 		'packages' => Array(
		 * 			'label'  => '',
		 * 			'code'   => '',
		 * 			'length' => 0,
		 * 			'width'  => 0,
		 * 			'height' => 0,
		 * 			'weight_max' => 0,
		 * 			'carrier_code' => '',
		 * 		)
		 * ) )
		 * @param \IQLRSS\Core\Shipping_Method_Shipstation $this
		 *
		 * @return Array $data
		 */
		$packages = apply_filters( 'iqlrss/zone/package_presets', $data, $this );

		// Maybe reset if what we're given is not what we expect.
		if( ! is_array( $packages ) ) $packages = $data;

		// Cache results to avoid multiple file reads per request.
		if( ! empty( $packages ) ) {
			wp_cache_add( 'packages', $packages, $this->plugin_prefix );

		// Maybe provide a default options / text when empty.
		} else {
			$packages = array( '' => esc_html__( 'No package presets.', 'live-rates-for-shipstation' ) );
		}

		return $packages;

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
	protected function format_shipitem_name( $shipitem_name, $link = false, $context = 'edit' ) {

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

}