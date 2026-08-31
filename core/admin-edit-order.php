<?php
/**
 * Manage the Edit Order admin page.
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

class Admin_Edit_Order {

	/**
	 * Initialize controller
	 *
	 * @return void
	 */
	public static function initialize() {

		if( ! is_admin() ) return;

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
	private function action_hooks() {}



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Filter Hooks :: **/
	/**------------------------------------------------------------------------------------------------ **/
	/**
	 * Add any necessary filter hooks
	 *
	 * @return void
	 */
	private function filter_hooks() {

		add_filter( 'woocommerce_order_item_display_meta_key',	array( $this, 'labelify_meta_keys' ) );
		add_filter( 'woocommerce_order_item_display_meta_value',array( $this, 'format_meta_values' ), 10, 2 );
		// add_filter( 'woocommerce_hidden_order_itemmeta',		array( $this, 'hide_metadata_from_admin_order' ) );

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

		error_log( print_r( $wc_meta->key, 1 ) );

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

		$prefix = \IQLRSS\Driver::get( 'slug' );
		return array_merge( $meta_keys, array(
			"_{$prefix}_carrier_id",
			"_{$prefix}_carrier_code",
			"_{$prefix}_service_code",
		) );

	}

}