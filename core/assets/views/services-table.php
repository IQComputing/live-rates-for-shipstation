<?php
/**
 * Display a table as a WooCommerce WC_Settings callback `generate_services_html()`
 * `services` being the field key / name.
 *
 * Generate a table based on saved carriers and their rates.
 * Pull in new rates based on selected carriers.
 *
 * @param \IQLRSS\Core\Shipping_Method_Shipstation $this
 * @param \IQLRSS\Core\Api\Shipstation $shipStationAPI
 * @param String $prefix - Plugin prefix
 * @param Array $saved_services - Saved Zone Services
 * @param Array $saved_carriers - Saved ShipStation Carriers
 */

if( ! defined( 'ABSPATH' ) ) {
	return;
}

$api_key = \IQLRSS\Driver::get_ss_opt( 'api_key', '' );
$global_adjustment = \IQLRSS\Driver::get_ss_opt( 'global_adjustment', '0' );
$global_adjustment_type = \IQLRSS\Driver::get_ss_opt( 'global_adjustment_type', '' );
$global_adjustment_type = ( empty( $global_adjustment_type ) && ! empty( $global_adjustment ) ) ? 'percentage' : $global_adjustment_type;

?>

<tr valign="top" id="carrierServices">
	<th scope="row" class="titledesc no-padleft"><?php esc_html_e( 'Services', 'live-rates-for-shipstation' ); ?></th>
	<td class="forminp">
		<p style="font-style:italic;"><?php esc_html_e( 'The more selected services, the longer the ShipStation API may take to respond.', 'live-rates-for-shipstation' ); ?></p>
		<p style="margin:0 0 1.1em;font-style:italic;"><?php esc_html_e( 'If using ShipStation Carriers, be sure to activate any service(s) in ShipStation as well as enabling them below:', 'live-rates-for-shipstation' ); ?></p>
		<table class="widefat nottoofat">
			<thead>
				<tr>
					<th style="width: 50px;"><?php esc_html_e( 'Enabled', 'live-rates-for-shipstation' ); ?></th>
					<th><?php esc_html_e( 'Name', 'live-rates-for-shipstation' ); ?></th>
					<th><?php esc_html_e( 'Price Adjustment', 'live-rates-for-shipstation' ); ?></th>
					<th><?php esc_html_e( 'Carrier', 'live-rates-for-shipstation' ); ?></th>
				</tr>
			</thead>
			<tbody><?php

				if( empty( $api_key ) || ! \IQLRSS\Driver::get_ss_opt( 'api_key_valid', false ) ) {
					print( '<tr><th colspan="4">' );
						echo wp_kses( sprintf(

							/* translators: %1$s is the opening html anchor tag linking to ShipStation settings page. %2$s is the closing html anchor tag. */
							__( 'Please visit WooCommerce Integration > %1$sShipStation settings screen%2$s to validate your ShipStation API Key.', 'live-rates-for-shipstation' ),
							sprintf( '<a href="%s" target="_blank">', esc_url( add_query_arg( array(
								'page'		=> 'wc-settings',
								'tab'		=> 'integration',
								'section'	=> 'shipstation',
							), admin_url( 'admin.php' ) ) ) ),
							'</a>',
						), array( 'a' => array( 'href' => array() ) ) );
					print( '</th></tr>' );
				}

				// Saved Services first.
				foreach( $saved_services as $carrier_id => $carrier_arr ) {
					foreach( $carrier_arr as $service_code => $service_arr ) {

						$attr_name = sprintf( '%s[%s][%s]', $prefix, $carrier_id, $service_arr['service_code'] );
						$saved_atts = array(
							'enabled'			=> ( isset( $service_arr['enabled'] ) ) ? $service_arr['enabled'] : false,
							'nickname'			=> ( isset( $service_arr['nickname'] ) ) ? $service_arr['nickname'] : '',
							'adjustment_type'	=> ( isset( $service_arr['adjustment_type'] ) ) ? $service_arr['adjustment_type'] : $global_adjustment_type,
							'adjustment'		=> ( isset( $service_arr['adjustment'] ) ) ? $service_arr['adjustment'] : '',
						);

						print( '<tr>' );

							// Service Checkbox and Metadata
							print( '<td style="width: 50px;">' );
								printf( '<input type="checkbox" name="%s"%s>',
									esc_attr( $attr_name . '[enabled]' ),
									checked( $saved_atts['enabled'], true, false ),
								);

								// Metadata
								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[service_name]' ),
									esc_attr( $service_arr['service_name'] )
								);
								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[carrier_id]' ),
									esc_attr( $carrier_id )
								);

								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[carrier_code]' ),
									esc_attr( $service_arr['carrier_code'] )
								);
								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[carrier_name]' ),
									esc_attr( $service_arr['carrier_name'] )
								);
							print( '</td>' );

							// Service Nickname
							printf( '<td><input type="text" name="%s" value="%s" placeholder="%s"></td>',
								esc_attr( $attr_name . '[nickname]' ),
								esc_attr( $saved_atts['nickname'] ),
								esc_attr( $service_arr['service_name'] ),
							);

							// Service Price Adjustment
							print( '<td><div class="iqrlsserviceprice-flex">' );

								printf( '<div><select name="%s" style="width:100%%;">', esc_attr( $attr_name . '[adjustment_type]' ) );
									foreach( static::get_adjustment_types( true ) as $slug => $label ) {
										printf( '<option value="%s"%s>%s</option>',
											esc_attr( $slug ),
											selected( $saved_atts['adjustment_type'], $slug, false ),
											esc_html( $label )
										);
									}
								print( '</select></div>' );

								printf( '<div><input type="text" name="%s" value="%s" placeholder="%s" style="max-width:60px;" class="%s"></div>',
									esc_attr( $attr_name . '[adjustment]' ),
									esc_attr( $saved_atts['adjustment'] ),
									esc_attr( $global_adjustment ),
									esc_attr( 'iqlrss-numbers-only' . ( ( '' == $saved_atts['adjustment_type'] ) ? ' iqlrss-hide' : '' ) ),
								);
							print( '</div></td>' );

							// Carrier Name
							printf( '<td><strong>%s</strong></td>', esc_html( $service_arr['carrier_name'] ) );

						print( '</tr>' );

						// Set a processed flag for the next array which is not reorganized.
						$saved_services[ $carrier_id ][ $service_code ]['processed'] = true;

					}
				}

				// Remaining Services next.
				foreach( $saved_carriers as $carrier_id ) {

					$response = $shipStationAPI->get_carrier( $carrier_id );
					if( is_wp_error( $response ) ) {
						printf( '<tr><td colspan="4" class="iqcss-err">%s - %s</td></tr>',
							esc_html( $response->get_error_code() ),
							wp_kses_post( $response->get_error_message() )
						);
						continue;
					}

					foreach( $response['services'] as $service_arr ) {

						$service_arr = ( ! is_array( $service_arr ) ) ? (array)$service_arr : $service_arr;
						if( isset( $saved_services[ $carrier_id ][ $service_arr['service_code'] ]['processed'] ) ) continue;

						print( '<tr>' );

							$attr_name = sprintf( '%s[%s][%s]', $prefix, $carrier_id, $service_arr['service_code'] );

							// Service Checkbox and Metadata
							print( '<td style="width: 50px;">' );
								printf( '<input type="checkbox" name="%s">', esc_attr( $attr_name . '[enabled]' ) );

								// Metadata
								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[service_name]' ),
									esc_attr( $service_arr['name'] )
								);
								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[carrier_id]' ),
									esc_attr( $carrier_id )
								);

								if( isset( $response['carrier']['carrier_code'] ) ) {
									printf( '<input type="hidden" name="%s" value="%s">',
										esc_attr( $attr_name . '[carrier_code]' ),
										esc_attr( $response['carrier']['carrier_code'] )
									);
								}
								printf( '<input type="hidden" name="%s" value="%s">',
									esc_attr( $attr_name . '[carrier_name]' ),
									esc_attr( $response['carrier']['name'] )
								);
							print( '</td>' );

							// Service Name
							printf( '<td><input type="text" name="%s" value="" placeholder="%s"></td>',
								esc_attr( $attr_name . '[nickname]' ),
								esc_attr( $service_arr['name'] ),
							);

							// Service Price Adjustment
							print( '<td><div class="iqrlsserviceprice-flex">' );

								printf( '<div><select name="%s" style="width:100%%;">', esc_attr( $attr_name . '[adjustment_type]' ) );
									foreach( static::get_adjustment_types( true ) as $slug => $label ) {
										printf( '<option value="%s"%s>%s</option>',
											esc_attr( $slug ),
											selected( $global_adjustment_type, $slug, false ),
											esc_html( $label )
										);
									}
								print( '</select></div>' );

								printf( '<div><input type="text" name="%s" value="" placeholder="%s" style="max-width:60px;" class="%s"></div>',
									esc_attr( $attr_name . '[adjustment]' ),
									esc_attr( $global_adjustment ),
									esc_attr( 'iqlrss-numbers-only' . ( ( '' == $global_adjustment_type ) ? ' iqlrss-hide' : '' ) )
								);
							print( '</div></td>' );

							// Carrier Name
							printf( '<td><strong>%s</strong></td>', esc_html( $response['carrier']['name'] ) );

						print( '</tr>' );

					}

				}

			?></tbody>
		</table>
	</td>
</tr>