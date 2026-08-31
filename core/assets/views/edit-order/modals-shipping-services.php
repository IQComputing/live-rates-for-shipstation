<?php
/**
 * Edit WooCommerce Order Metabox: ShipStation Label Management
 * Allow the user to view the packages and rates.
 * 
 * @var WC_Order_Item_Shipping $order_item
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 */

if( ! defined( 'ABSPATH' ) ) {
	return;
}

$boxes = $order_item->get_meta( 'boxes' );

?>

<button type="button" class="button button-primary button-small" data-iqlrss-modal="packedItemsModal"><?php esc_html_e( 'Packed Items', 'live-rates-for-shipstation' ); ?></button>
<dialog id="packedItemsModal" class="iqlrss-modal">
	<h3 class="iqlrss-modal-title"><?php esc_html_e( 'Packed Order Items', 'live-rates-for-shipstation' ); ?></h3>
	<button type="button"><span class="screen-reader-text"><?php esc_html_e( 'Close Modal Window', 'live-rates-for-shipstation' ); ?></span><i class="dashicons dashicons-no"></i></button>
	<div class="iqlrss-modal-content">
		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Box', 'live-rates-for-shipstation' ); ?></th>
					<th><?php esc_html_e( 'Box Data', 'live-rates-for-shipstation' ); ?></th>
					<th><?php esc_html_e( 'Products', 'live-rates-for-shipstation' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach( $boxes as $box ) : ?>
					<tr>
						<th><?php 
							print( esc_html( $box['nickname'] ) );
							printf( '<small>%sx%sx%s (%s) | %s (%s)</small>',
								esc_html( $box['dimensions']['length'] ),
								esc_html( $box['dimensions']['width'] ),
								esc_html( $box['dimensions']['height'] ),
								esc_html( $box['dimensions']['unit'] ),
								esc_html( $box['weight']['value'] ),
								esc_html( $box['weight']['unit'] ),
							);
						?></th>
						<td>
							<ul class="listnone">
								<li><?php printf( '<strong>%s:</strong> %s', esc_html__( 'Box Price', 'live-rates-for-shipstation' ), wc_price( $box['price'] ) ); ?></li>
								<li><?php printf( '<strong>%s:</strong> %s', esc_html__( 'Box Weight', 'live-rates-for-shipstation' ), esc_html( $box['box_weight'] ) ); ?></li>
								<li><?php printf( '<strong>%s:</strong> %s', esc_html__( 'Box Max Weight', 'live-rates-for-shipstation' ), esc_html( $box['box_max_weight'] ) ); ?></li>
								<li><?php printf( '<strong>%s:</strong> %s', esc_html__( 'Box Max Volume', 'live-rates-for-shipstation' ), esc_html( $box['box_max_volume'] ) . '%' ); ?></li>
							</ul>
						</td>
						<td>
							<ul><?php
								foreach( $box['packed'] as $product_data ) {
									$data_arr = explode( '|', $product_data );
									printf( '<li><a href="%s" target="_blank">%s</a></li>',
										esc_url( get_edit_post_link( $data_arr[0] ) ),
										esc_html( $data_arr[1] )
									);
								}
							?></ul>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</dialog>