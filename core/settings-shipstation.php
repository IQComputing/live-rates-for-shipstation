<?php
/**
 * ShipStation Settings Controller
 * Integrate into the ShipStation Settings.
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

Class Settings_Shipstation {

	/**
	 * Initialize controller
	 *
	 * @return void
	 */
	public static function initialize() {

		$class = new self();
		$class->action_hooks();
		$class->filter_hooks();

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

		add_action( 'admin_enqueue_scripts',					array( $this, 'register_admin_assets' ), 3 );

		add_action( 'admin_head',								array( $this, 'localize_script_vars' ) );
		add_action( 'admin_enqueue_scripts',					array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_cart_totals_after_order_total',array( $this, 'display_cart_weight' ) ) ;
		add_action( 'rest_api_init',							array( $this, 'api_verification_endpoint' ) );

	}


	/**
	 * Register assets to enqueue.
	 *
	 * @return void
	 */
	public function register_admin_assets() {

		// CSS
		wp_register_style(
			\IQLRSS\Driver::plugin_prefix( 'admin', '-' ),
			\IQLRSS\Driver::get_asset_url( 'admin.css' ),
			array(),
			\IQLRSS\Driver::get( 'version', '1.0.0' )
		);

		// JS
		wp_register_script_module(
			\IQLRSS\Driver::plugin_prefix( 'admin', '-' ),
			\IQLRSS\Driver::get_asset_url( 'admin.js' ),
			array( 'jquery' ),
			\IQLRSS\Driver::get( 'version', '1.0.0' )
		);


	}


	/**
	 * Localize script variables as needed.
	 * JS Modules do not allow localization.
	 *
	 * @return void
	 */
	public function localize_script_vars() {

		if( ! $this->maybe_enqueue( 'admin' ) ) {
			return;
		}

		$data = array(
			'api_verified' => false,
			'rest' => array(
				'nonce'		=> wp_create_nonce( 'wp_rest' ),
				'apiverify'	=> get_rest_url( null, sprintf( '/%s/v1/apiverify',
					\IQLRSS\Driver::get( 'slug' )
				) ),
			),
			'text' => array(
				'button_api_verify'		=> esc_html__( 'Verify API', 'live-rates-for-shipstation' ),
				'error_rest_generic'	=> esc_html__( 'Something went wrong with the REST Request.', 'live-rates-for-shipstation' ),
			),
		);

		?><script type="text/javascript">
			var iqlrss = JSON.parse( '<?php echo json_encode( $data ); ?>' );
		</script><?php

	}


	/**
	 * Enqueue admin assets - CSS/JS
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {

		if( ! $this->maybe_enqueue( 'admin' ) ) {
			return;
		}

		wp_enqueue_style( \IQLRSS\Driver::plugin_prefix( 'admin', '-' ) );
		wp_enqueue_script_module( \IQLRSS\Driver::plugin_prefix( 'admin', '-' ) );

	}


	/**
	 * Cart Weight display.
	 *
	 * @return void
	 */
	public function display_cart_weight() {

		$show_weight = \IQLRSS\Driver::get_ss_opt( 'cart_weight', 'no', true );
		if( 'no' == $show_weight ) return;

		printf( '<tr><th>%s</th><td>%s lbs</td></tr>',
			esc_html__( 'Total Weight', 'live-rates-for-shipstation' ),
			esc_html( WC()->cart->get_cart_contents_weight() )
		);

	}


	/**
	 * REST Endpoint to validate the users API Key.
	 *
	 * IF the Endpoint needs expanded, move to separate controller file.
	 *
	 * @return void
	 */
	public function api_verification_endpoint() {

		$prefix = \IQLRSS\Driver::get( 'slug' );

		// Handle ajax requests
		register_rest_route( "{$prefix}/v1", 'apiverify', array(
			'methods' => array( 'POST' ),
			'permission_callback' => fn() => is_user_logged_in(),
			'callback' => function( $request ) {

				$params = $request->get_params();

				// Error - Missing API Key.
				if( empty( $params['key'] ) ) {
					wp_send_json_error( esc_html__( 'API Key not found.', 'live-rates-for-shipstation' ), 400 );
				}

				$apikeys = array(
					'old' => '',
					'new' => sanitize_text_field( $params['key'] ),
				);
				$shipstation_opt_slug 	= 'woocommerce_shipstation_settings';
				$api_settings_key 		= \IQLRSS\Driver::plugin_prefix( 'api_key' );
				$settings = get_option( $shipstation_opt_slug, array() );

				// Save the old key in case we need to revert.
				if( ! empty( $settings[ $api_settings_key ] ) ) {
					$apikeys['old'] = $settings[ $api_settings_key ];
				}

				// The API pulls the API Key directly from the ShipStation Settings on init.
				$settings[ $api_settings_key ] = $apikeys['new'];
				update_option( $shipstation_opt_slug, $settings );

				$shipStationAPI = new Shipstation_Api( \IQLRSS\Driver::get( 'slug' ), true );
				$carriers = $shipStationAPI->get_carriers();

				// Error - Something went wrong, the API should let us know.
				if( is_wp_error( $carriers ) ) {

					// Revert to old key
					if( ! empty( $apikeys['old'] ) ) {
						$settings = get_option( $shipstation_opt_slug, array() );
						$settings[ $api_settings_key ] = $apikeys['old'];
						update_option( $shipstation_opt_slug, $settings );
					}

					wp_send_json_error( $carriers );
				}

				wp_send_json_success();

			}
		) );

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

		add_filter( 'woocommerce_shipping_methods',							array ($this, 'append_shipstation_method' ) );
		add_filter( 'woocommerce_settings_api_form_fields_shipstation',		array( $this, 'append_shipstation_integration_settings' ) );

	}


	/**
	 * Append the ShipStation Shipping Method
	 *
	 * @param Array $methods
	 *
	 * @return Array $methods
	 */
	public function append_shipstation_method( $methods ) {
		return array_merge( $methods, array(
			\IQLRSS\Driver::plugin_prefix( 'shipstation' ) => 'IQLRSS\\Core\\Shipping_Method_Shipstation',
		) );
	}


	/**
	 * Append ShipStation Integration Settings
	 * This will allow the user to select which carriers to receive rates from.
	 *
	 * @param Array $fields
	 *
	 * @return Array $fields
	 */
	public function append_shipstation_integration_settings( $fields ) {

		$carrier_desc = esc_html__( 'Select which ShipStation carriers you would like to see live shipping rates from.', 'live-rates-for-shipstation' );

		if( ! isset( $settings[ \IQLRSS\Driver::get_ss_opt( 'api_key', '', true ) ] ) ) {
			$carrier_desc = esc_html__( 'Please enter your ShipStation REST API Key first.', 'live-rates-for-shipstation' );
		}

		$appended_fields = array();
		foreach( $fields as $key => $field ) {

			$appended_fields[ $key ] = $field;

			// Append Live Rate Carriers after Shipped Status select.
			if( 'auth_key' == $key ) {

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'api_key' ) ] = array(
					'title'			=> esc_html__( 'ShipStation REST API Key', 'live-rates-for-shipstation' ),
					'type'			=> 'password',
					'description'	=> esc_html__( 'ShipStation REST v2 API Key - Settings > Account > API Settings', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'carriers' ) ] = array(
					'title'			=> esc_html__( 'Shipping Carriers', 'live-rates-for-shipstation' ),
					'type'			=> 'multiselect',
					'class'			=> 'chosen_select',
					'options'		=> ( function() { // Closure since it's only used once.

						$carriers = array();
						$shipStationAPI = new Shipstation_Api( \IQLRSS\Driver::get( 'slug' ), true );
						$response = $shipStationAPI->get_carriers();

						if( ! is_a( $response, 'WP_Error' ) ) {
							foreach( $response as $carrier ) {

								$name = $carrier['friendly_name'];
								if( ! $carrier['is_shipstation'] ) {
									$name .= esc_html__( ' (Personal)', 'live-rates-for-shipstation' );
								}

								$carriers[ $carrier['carrier_id'] ] = $name;
							}
						}

						return $carriers;

					} )(),
					'description'	=> $carrier_desc,
					'desc_tip'		=> esc_html__( 'Services from selected carriers will appear when settings up Shipping Zones.', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'cart_weight' ) ] = array(
					'title'			=> esc_html__( 'Display Cart Weight', 'live-rates-for-shipstation' ),
					'label'			=> esc_html__( 'Show total cart weight on the cart page.', 'live-rates-for-shipstation' ),
					'type'			=> 'checkbox',
					'default'		=> 0,
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'return_lowest' ) ] = array(
					'title'			=> esc_html__( 'Single Lowest Rate', 'live-rates-for-shipstation' ),
					'label'			=> esc_html__( 'Enable lowest shipping rate', 'live-rates-for-shipstation' ),
					'type'			=> 'checkbox',
					'description'	=> esc_html__( 'Only show the lowest shipping rate from all enabled carrier rates.', 'live-rates-for-shipstation' ),
					'default'		=> 0,
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'return_lowest_label' ) ] = array(
					'title'			=> esc_html__( 'Single Lowest Label', 'live-rates-for-shipstation' ),
					'type'			=> 'text',
					'description'	=> esc_html__( 'Overrides the shipping rate nickname.', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

			}

		}

		return $appended_fields;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Maybe enqueue JS modules as needed.
	 *
	 * @param String $slug
	 *
	 * @return Boolean $enqueue
	 */
	protected function maybe_enqueue( $slug = '' ) {

		$screen = get_current_screen();
		$screen_id = ( is_a( $screen, 'WP_Screen' ) ) ? $screen->id : '';

		$enqueue = false;
		if( 'admin' == $slug ) {

			// Shipping Zone settings page
			$enqueue = ( isset( $_GET, $_GET['instance_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Integration > ShipStation settings page
			$enqueue = ( isset( $_GET, $_GET['section'] ) && 'shipstation' == $_GET['section'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Overprotective WooCommerce settings page check
			$enqueue = ( $enqueue && 'woocommerce_page_wc-settings' == $screen_id );
		}
		return $enqueue;

	}

}