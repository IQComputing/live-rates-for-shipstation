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

		add_filter( 'woocommerce_order_item_display_meta_key',		  array( $this, 'labelify_meta_keys' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_metadata' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta',			  array( $this, 'hide_metadata_from_admin_order' ) );

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
		);

		return ( isset( $matches[ $display ] ) ) ? $matches[ $display ] : $display;

	}


	/**
	 * Append metadata and format it.
	 * 
	 * @param Array $formatted
	 * @param WC_Order_Item $order_item
	 * 
	 * @return Array $formatted
	 */
	public function format_order_metadata( $formatted, $order_item ) {

		if( ! is_a( $order_item, 'WC_Order_Item_Shipping' ) || 'iqlrss_shipstation' !== $order_item->get_method_id() ) {
			return $formatted;
		}

		ob_start();
			include \IQLRSS\Driver::get_asset_path( 'views/edit-order/modals-shipping-services.php' );
		$boxes_modal = ob_get_clean();

		$data = (object)array(
			'key'			=> '',
			'value'			=> '',
			'display_key'	=> 'View',
			'display_value' => $boxes_modal,
		);

		return array_merge( $formatted, array( $data ) );

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