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

		add_action( 'admin_enqueue_scripts',					array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_cart_totals_after_order_total',array( $this, 'display_cart_weight' ) );

	}


	/**
	 * Enqueue admin assets - CSS/JS
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {

		$screen = get_current_screen();
		$screen_id = ( is_a( $screen, 'WP_Screen' ) ) ? $screen->id : '';

		if( 'woocommerce_page_wc-settings' !== $screen_id || ! isset( $_GET, $_GET['instance_id'] ) ) {
			return;
		}

		// Styles
		wp_enqueue_style( \IQLRSS\Driver::plugin_prefix( 'admin', '-' ), \IQLRSS\Driver::get_asset_url( 'admin.css' ), array(), \IQLRSS\Driver::get( 'version', '1.0.0' ) );

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
			WC()->cart->get_cart_contents_weight()
		);

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
					'options'		=> $this->get_carrier_options(),
					'description'	=> $carrier_desc,
					'desc_tip'		=> esc_html__( 'Services from selected carriers will appear when settings up Shipping Zones.', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'cart_weight' ) ] = array(
					'title'			=> esc_html__( 'Display Cart Weight', 'live-rates-for-shipstation' ),
					'label'			=> esc_html__( 'Show total cart weight on the cart page.', 'live-rates-for-shipstation' ),
					'type'			=> 'checkbox',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'return_lowest' ) ] = array(
					'title'			=> esc_html__( 'Single Lowest Rate', 'live-rates-for-shipstation' ),
					'label'			=> esc_html__( 'Enable lowest shipping rate', 'live-rates-for-shipstation' ),
					'type'			=> 'checkbox',
					'description'	=> esc_html__( 'Only show the lowest shipping rate from all enabled carrier rates.', 'live-rates-for-shipstation' ),
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'return_lowest_label' ) ] = array(
					'title'			=> esc_html__( 'Single Lowest Label', 'live-rates-for-shipstation' ),
					'type'			=> 'text',
					'description'	=> esc_html__( 'Overrides the shipping rate nickname.', 'live-rates-for-shipstation' ),
				);

			}

		}

		return $appended_fields;

	}



	/**------------------------------------------------------------------------------------------------ **/
	/** :: Helper Methods :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Return an array of Carriers keyed by their carrier code.
	 *
	 * @return Array $carriers
	 */
	protected function get_carrier_options() {

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

	}

}