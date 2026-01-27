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
		add_action( 'admin_footer',								array( $this, 'localize_script_vars' ), 3 );
		add_action( 'admin_enqueue_scripts',					array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_cart_totals_after_order_total',array( $this, 'display_cart_weight' ) ) ;
		add_action( 'woocommerce_update_option',				array( $this, 'clear_cache_on_update' ) );

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
			\IQLRSS\Driver::get_asset_url( 'css/admin.css' ),
			array(),
			\IQLRSS\Driver::get( 'version', '1.0.0' )
		);

		// JS
		wp_register_script_module(
			\IQLRSS\Driver::plugin_prefix( 'admin', '-' ),
			\IQLRSS\Driver::get_asset_url( 'js/admin.js' ),
			array(),
			\IQLRSS\Driver::get( 'version', '1.0.0' )
		);

	}


	/**
	 * Localize script variables as needed.
	 * JS Modules do not allow localization.
	 * Hide unauthenticated API settings.
	 *
	 * @return void
	 */
	public function localize_script_vars() {

		if( ! $this->maybe_enqueue( 'admin' ) ) {
			return;
		}

		$data = array(
			'api_verified'	=> \IQLRSS\Driver::get_ss_opt( 'api_key_valid', false ),
			'global_adjustment_type' => \IQLRSS\Driver::get_ss_opt( 'global_adjustment_type', '' ),
			'store' => array(
				'currency_symbol' => get_woocommerce_currency_symbol( get_woocommerce_currency() ),
			),
			'rest' => array(
				'nonce'		=> wp_create_nonce( 'wp_rest' ),
				'settings'=> get_rest_url( null, sprintf( '/%s/v1/settings',
					\IQLRSS\Driver::get( 'slug' )
				) ),
			),
			'text' => array(
				'button_api_verify'		=> esc_html__( 'Verify API', 'live-rates-for-shipstation' ),
				'button_api_clearcache'	=> esc_html__( 'Clear API Cache', 'live-rates-for-shipstation' ),
				'confirm_box_removal'	=> esc_html__( 'Please confirm you would like to completely remove (x) custom boxes.', 'live-rates-for-shipstation' ),
				'confirm_modal_closure'	=> esc_html__( 'Changes you made may not be saved. Close modal window?', 'live-rates-for-shipstation' ),
				'error_field_required'	=> esc_html__( 'This field is required, please enter a value.', 'live-rates-for-shipstation' ),
				'error_custombox_json'	=> esc_html__( 'Something went wrong while saving your data. Please try again.', 'live-rates-for-shipstation' ),
				'error_rest_generic'	=> esc_html__( 'Something went wrong with the REST Request. Please resave permalinks and try again.', 'live-rates-for-shipstation' ),
				'error_verification_required'		=> esc_html__( 'Please click the Verify API button to ensure a connection exists.', 'live-rates-for-shipstation' ),
				'success_custombox_added'			=> esc_html__( 'The Custom Box has been added to the list successfully!', 'live-rates-for-shipstation' ),
				'desc_global_adjustment_percentage' => esc_html__( 'Example: IF UPS Ground is $7.25 and you input 15% ($1.08), the final shipping rate the customer sees is: $8.33', 'live-rates-for-shipstation' ),
				'desc_global_adjustment_flatrate'	=> esc_html__( 'Example: IF UPS Ground is $5.50 and you input $2.37, the final shipping rate the customer sees is: $7.87', 'live-rates-for-shipstation' ),
			),
		);

		?><script type="text/javascript">

			/* JS Localization */
			const iqlrss = JSON.parse( '<?php echo wp_json_encode( $data ); ?>' );

			/* Early setting field JS */
			if( document.getElementById( 'woocommerce_shipstation_iqlrss_api_key' ) ) { ( function() {

				/* Hide an element, ezpz */
				const fnHide = ( $el ) => $el.closest( 'tr' ).style.display = 'none';

				<?php
					/**
					 * Modules load too late to effectively immediately hide elements.
					 * This runs on the ShipStation settings page to hide additional
					 * settings whenever the API is unauthenticated.
					 */
					if( ! $data['api_verified'] ) :
				?>

					document.querySelectorAll( '[name*=iqlrss]' ).forEach( ( $elm ) => {
						if( $elm.name.includes( 'api_key' ) ) return;
						if( $elm.name.includes( 'cart_weight' ) ) return;
						fnHide( $elm );
					} );

				<?php else : ?>

					document.querySelectorAll( '[name*=iqlrss]' ).forEach( ( $elm ) => {

						if( 'checkbox' == $elm.type && $elm.name.includes( 'return_lowest' ) && ! $elm.checked ) {
							fnHide( document.querySelector( '[name*=return_lowest_label]' ) );
						}

						if( $elm.name.includes( 'global_adjustment_type' ) && '' == $elm.value ) {
							fnHide( document.querySelector( '[type=text][name*=global_adjustment]' ) );
						}
					} );

				<?php endif; ?>

			} )(); }

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

		$show_weight = \IQLRSS\Driver::get_ss_opt( 'cart_weight', 'no' );
		if( 'no' == $show_weight ) return;

		printf( '<tr><th>%s</th><td>%s lbs</td></tr>',
			esc_html__( 'Total Weight', 'live-rates-for-shipstation' ),
			esc_html( WC()->cart->get_cart_contents_weight() )
		);

	}


	/**
	 * Clear the API cache.
	 *
	 * @return void
	 */
	public function clear_cache() {

		global $wpdb;


		/**
		 * The API Class creates various transients to cache carrier services.
		 * These transients are not tracked but generated based on the responses carrier codes.
		 * All these transients are prefixed with our plugins unique string slug.
		 * The first WHERE ensures only `_transient_` and the 2nd ensures only our plugins transients.
		 */
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s AND option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
			$wpdb->options,
			$wpdb->esc_like( '_transient_' ) . '%',
			'%' . $wpdb->esc_like( '_' . \IQLRSS\Driver::get( 'slug' ) . '_' ) . '%'
		) );

		// Set transient to clear any WC_Session caches if they are found.
		$expires = absint( apply_filters( 'wc_session_expiration', DAY_IN_SECONDS * 2 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		set_transient( \IQLRSS\Driver::plugin_prefix( 'wcs_timeout' ), time(), $expires );

	}


	/**
	 * Clear the cache whenever the Integration settings have been updated.
	 *
	 * @param Array $args
	 *
	 * @return void
	 */
	public function clear_cache_on_update( $args ) {

		if( ! isset( $args['id'] ) || false === strpos( $args['id'], 'shipstation' ) ) {
			return;
		}

		\IQLRSS\Driver::clear_cache();

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
		add_filter( 'woocommerce_settings_api_sanitized_fields_shipstation',array( $this, 'save_shipstation_integration_settings' ) );
		add_filter( 'woocommerce_shipstation_export_get_order',				array( $this, 'export_shipstation_shipping_method' ) );

		add_filter( 'plugin_action_links_live-rates-for-shipstation/live-rates-for-shipstation.php', array( $this, 'plugin_settings_link' ) );

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

		$carriers = array(
			'' => esc_html__( 'ShipStation carriers may still be loading...', 'live-rates-for-shipstation' ),
		);
		$warehouses = array(
			'' => '(' . esc_html__( 'Website Store Address', 'live-rates-for-shipstation' ) . ')',
		);
		$appended_fields = array();

		if( ! empty( \IQLRSS\Driver::get_ss_opt( 'api_key' ) ) ) {

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

			// Grab Carrier options
			$carriers = array();
			$api_carriers = $api->get_carriers();
			if( is_a( $api_carriers, 'WP_Error' ) ) {
				$carriers[''] = $api_carriers->get_error_message();
			} else if( is_array( $api_carriers ) && ! empty( $api_carriers ) ) {
				foreach( $api_carriers as $carrier ) {
					$carriers[ $carrier['carrier_id'] ] = $carrier['name'];
				}
			}

		}

		// Backwards compatibility for v1.0.3 when only percentage was supported by default.
		$global_adjustment = \IQLRSS\Driver::get_ss_opt( 'global_adjustment', '0' );
		$adjustment_type_default = ( empty( $global_adjustment_type ) && ! empty( $global_adjustment ) ) ? 'percentage' : '';

		foreach( $fields as $key => $field ) {

			$appended_fields[ $key ] = $field;

			// Append Live Rate Carriers after Shipped Status select.
			if( 'auth_key' == $key ) {

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'api_key' ) ] = array(
					'title'			=> esc_html__( 'ShipStation REST API Key', 'live-rates-for-shipstation' ),
					'type'			=> 'password',
					'description'	=> esc_html__( 'ShipStation Account > Settings > Account > API Settings', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'carriers' ) ] = array(
					'title'			=> esc_html__( 'Shipping Carriers', 'live-rates-for-shipstation' ),
					'type'			=> 'multiselect',
					'class'			=> 'chosen_select',
					'options'		=> $carriers,
					'description'	=> ( function() {
						if( ! empty( \IQLRSS\Driver::get_ss_opt( 'api_key' ) ) ) {
							return esc_html__( 'Select which ShipStation carriers you would like to see live shipping rates from.', 'live-rates-for-shipstation' );
						}
						return esc_html__( 'Please set and verify your ShipStation API key. Then, click the Save button at the bottom of this page.', 'live-rates-for-shipstation' );
					} )(),
					'desc_tip'		=> esc_html__( 'Services from selected carriers will be available when setting up Shipping Zones.', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'global_warehouse' ) ] = array(
					'title'			=> esc_html__( 'Shipping From', 'live-rates-for-shipstation' ),
					'type'			=> 'select',
					'options'		=> $warehouses,
					'description'	=> ( function() {
						if( ! empty( \IQLRSS\Driver::get_ss_opt( 'api_key' ) ) ) {
							return esc_html__( 'Select to ship from a different location than what is set as your WooCommerce website default location.', 'live-rates-for-shipstation' );
						}
						return esc_html__( 'Please set and verify your ShipStation API key. Then, click the Save button at the bottom of this page.', 'live-rates-for-shipstation' );
					} )(),
					'desc_tip'		=> esc_html__( 'This can be overridden per Shipping Zone.', 'live-rates-for-shipstation' ),
					'default'		=> '',
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'global_adjustment_type' ) ] = array(
					'title'			=> esc_html__( 'Shipping Price Adjustment', 'live-rates-for-shipstation' ),
					'type'			=> 'select',
					'options'		=> \IQLRSS\Core\Shipping_Method_Shipstation::get_adjustment_types( true ),
					'description'	=> esc_html__( 'This adjustment is added on top of the returned shipping rates to help you cover shipping costs. Can be overridden per zone, per service.', 'live-rates-for-shipstation' ),
					'default'		=> $adjustment_type_default,
				);

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'global_adjustment' ) ] = array(
					'title'			=> esc_html__( 'Global Price Adjustment', 'live-rates-for-shipstation' ),
					'type'			=> 'text',
					'placeholder'	=> '0',
					'description'	=> esc_html__( 'Optional global ShipStation rate adjustment.', 'live-rates-for-shipstation' ),
					'default'		=> '',
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

				$appended_fields[ \IQLRSS\Driver::plugin_prefix( 'cart_weight' ) ] = array(
					'title'			=> esc_html__( 'Display Cart Weight', 'live-rates-for-shipstation' ),
					'label'			=> esc_html__( 'Show total cart weight on the cart page.', 'live-rates-for-shipstation' ),
					'type'			=> 'checkbox',
					'default'		=> 0,
				);

			}

		}

		return $appended_fields;

	}


	/**
	 * Modify the saved settings after WooCommerce has sanitized them.
	 * Not much we need to do here, WooCommerce does most the heavy lifting.
	 *
	 * @param Array $settings
	 *
	 * @return Array $settings
	 */
	public function save_shipstation_integration_settings( $settings ) {

		// No API Key? Invalid!
		$api_key_key = \IQLRSS\Driver::plugin_prefix( 'api_key' );
		if( ! isset( $settings[ $api_key_key ] ) || empty( $settings[ $api_key_key ] ) ) {

			$settings[ \IQLRSS\Driver::plugin_prefix( 'api_key_valid' ) ] = false;
			if( isset( $settings[ \IQLRSS\Driver::plugin_prefix( 'api_key_vt' ) ] ) ) {
				unset( $settings[ \IQLRSS\Driver::plugin_prefix( 'api_key_vt' ) ] );
			}

			\IQLRSS\Driver::clear_cache();
		}

		return $settings;

	}


	/**
	 * Update the shipping method name to be the Service.
	 * Usually not needed, but if the user saved a nickname?
	 * This will make it easier to understand on ShipStation.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Order $order
	 */
	public function export_shipstation_shipping_method( $order ) {

		if( ! is_a( $order, 'WC_Order' ) ) {
			return $order;
		}

		$methods = $order->get_shipping_methods();
		$plugin_method_id = \IQLRSS\Driver::plugin_prefix( 'shipstation' );

		foreach( $methods as $method ) {

			// Not our shipping method.
			if( $method->get_method_id() != $plugin_method_id ) continue;

			$service_name = (string)$method->get_meta( 'service', true );
			$method->set_props( array(
				'name' => trim( explode( '(', $service_name )[0] ),
			) );
			$method->apply_changes(); // Temporarily apply changes. This does not update the database.

		}

		return $order;

	}


	/**
	 * Add link to plugin settings
	 *
	 * @param Array $links
	 *
	 * @return Array $links
	 */
	public function plugin_settings_link( $links ) {

		return array_merge( array(
			sprintf( '<a href="%s">%s</a>',
				add_query_arg( array(
					'page'	  => 'wc-settings',
					'tab'	  => 'integration',
					'section' => 'shipstation',
				), admin_url( 'admin.php' ) ),
				esc_html__( 'Settings', 'live-rates-for-shipstation' ),
			)
		), $links );

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
			$enqueue = ( $enqueue || ( isset( $_GET, $_GET['section'] ) && 'shipstation' == $_GET['section'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Edit Order page
			$enqueue = ( $enqueue || ( isset( $_GET, $_GET['page'], $_GET['id'] ) && 'wc-orders' == $_GET['page'] && ! empty( $_GET['id'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Overprotective WooCommerce settings page check
			$enqueue = ( $enqueue && in_array( $screen_id, array( 'woocommerce_page_wc-orders', 'woocommerce_page_wc-settings' ) ) );
		}
		return $enqueue;

	}

}