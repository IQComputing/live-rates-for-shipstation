<?php
/**
 * Manage the Edit Order admin page.
 */
namespace IQLRSS\Core;

if( ! defined( 'ABSPATH' ) ) {
	return;
}

Class Admin_Edit_Order {

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

        add_action( 'add_meta_boxes', array( $this, 'wc_order_metaboxes' ) );

    }


	/**
	 * Add metaboxes onto the WooCommerce Edit Order page.
	 *
	 * @link https://stackoverflow.com/a/78262549/800452
	 * LoicTheAztec - What a legend!
	 *
	 * @return void
	 */
	public function wc_order_metaboxes() {

		$screen_id = 'shop_order';
		if( function_exists( 'wc_get_page_screen_id' ) ) {
			$screen_id = wc_get_page_screen_id( 'shop-order' );
		}

		add_meta_box(
			\IQLRSS\Driver::plugin_prefix( 'label-management', '-' ),
			esc_html__( 'ShipStation Shipping Label', 'live-rates-for-shipstation' ),
			array( $this, 'metabox_label_management' ),
			$screen_id,
			'side',
			'high'
		);

	}


	/**
	 * Render the ShipStation Label Management metabox.
	 *
	 * @param WC_Post $post - The current post object.
	 *
	 * @return void
	 */
	public function metabox_label_management( $post ) {

		$api = new Api\Shipstation();
		include \IQLRSS\Driver::get_asset_path( 'views/edit-order/metabox-label-management.php' );

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

    }

}