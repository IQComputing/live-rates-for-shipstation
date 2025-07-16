<?php
/**
 * Display a table as a WooCommerce WC_Settings callback `generate_customboxes_html()`
 * `customboxes` being the field key / name.
 *
 * Generate a dynamic table where the user can create, update, and delete custom boxes
 * with dimensions and weights. These boxes will be used for automated product packing.
 *
 * @param \IQLRSS\Core\Shipping_Method_Shipstation $this
 * @param String $prefix - Plugin prefix.
 * @param Boolean $show_custom - Whether to show the customBoxes table.
 * @param Array $saved_boxes - Saved user entered custom boxes.
 */

if( ! defined( 'ABSPATH' ) ) {
	return;
}

?>

<tr valign="top" id="customBoxes" style="display:<?php echo ( $show_custom ) ? 'table-row' : 'none'; ?>;">
	<th scope="row" class="titledesc no-padleft"><?php esc_html_e( 'Custom Packing Boxes', 'live-rates-for-shipstation' ); ?></th>
	<td class="forminp">
		<table class="widefat nottoofat">
			<thead>
				<th style="width: 50px;"><input type="checkbox" name="customboxes_removeall"></th>
				<th><?php esc_html_e( 'Outer Length', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Outer Width', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Outer Height', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Inner Length', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Inner Width', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Inner Height', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Weight of Box', 'live-rates-for-shipstation' ); ?>*</th>
				<th><?php esc_html_e( 'Max Weight', 'live-rates-for-shipstation' ); ?></th>
			</thead>
			<tbody><?php
				foreach( $saved_boxes as $idx => $box_arr ) {

					print( '<tr>' );
						printf( '<td><input type="checkbox" name="custombox[%d][remove]" /></td>', esc_attr( $idx ) );
						printf( '<td><label><input type="text" name="custombox[%d][ol]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['outer']['length'] ),
							esc_html__( '(IN)', 'live-rates-for-shipstation' ),
							esc_html__( 'Outer Length in Inches', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][ow]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['outer']['width'] ),
							esc_html__( '(IN)', 'live-rates-for-shipstation' ),
							esc_html__( 'Outer Width in Inches (IN)', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][oh]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['outer']['height'] ),
							esc_html__( '(IN)', 'live-rates-for-shipstation' ),
							esc_html__( 'Outer Height in Inches (IN)', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][il]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['inner']['length'] ),
							esc_html__( '(IN)', 'live-rates-for-shipstation' ),
							esc_html__( 'Inner Length in Inches (IN)', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][iw]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['inner']['width'] ),
							esc_html__( '(IN)', 'live-rates-for-shipstation' ),
							esc_html__( 'Inner Width in Inches (IN)', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][ih]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['inner']['height'] ),
							esc_html__( '(IN)', 'live-rates-for-shipstation' ),
							esc_html__( 'Inner Height in Inches (IN)', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][w]" value="%s" required /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( $box_arr['weight'] ),
							esc_html__( '(LBS)', 'live-rates-for-shipstation' ),
							esc_html__( 'Weight of Empty Box in Pounds (LBS)', 'live-rates-for-shipstation' )
						);
						printf( '<td><label><input type="text" name="custombox[%d][wm]" value="%s" /><strong>%s</strong><span class="screen-reader-text">%s</span></label></td>',
							esc_attr( $idx ),
							esc_attr( ( ! empty( $box_arr['weight_max'] ) ) ? $box_arr['weight_max'] : '' ),
							esc_html__( '(LBS)', 'live-rates-for-shipstation' ),
							esc_html__( 'Max Weight Box Can Safely Hold In Pounds (LBS)', 'live-rates-for-shipstation' )
						);
					print( '</tr>' );

				}
			?>

				<tr class="mimic">
					<td><input type="checkbox" name="custombox[mimic][remove]" /></td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][ol]" /><strong><?php esc_html_e( '(IN)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Outer Length in Inches', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][ow]" /><strong><?php esc_html_e( '(IN)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Outer Width in Inches (IN)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][oh]" /><strong><?php esc_html_e( '(IN)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Outer Height in Inches (IN)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][il]" /><strong><?php esc_html_e( '(IN)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Inner Length in Inches (IN)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][iw]" /><strong><?php esc_html_e( '(IN)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Inner Width in Inches (IN)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][ih]" /><strong><?php esc_html_e( '(IN)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Inner Height in Inches (IN)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][w]" /><strong><?php esc_html_e( '(LBS)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Weight of Empty Box in Pounds (LBS)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
					<td>
						<label>
							<input type="text" name="custombox[mimic][wm]" /><strong><?php esc_html_e( '(LBS)', 'live-rates-for-shipstation' ); ?></strong>
							<span class="screen-reader-text"><?php esc_html_e( 'Max Weight Box Can Safely Hold In Pounds (LBS)', 'live-rates-for-shipstation' ); ?></span>
						</label>
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="3">
						<div class="boxactions">
							<button type="button" name="add" class="button button-primary"><?php esc_html_e( 'New Custom Box', 'live-rates-for-shipstation' ); ?></button>
							<button type="button" name="remove" class="button button-secondary"><?php esc_html_e( 'Remove Selected Box(es)', 'live-rates-for-shipstation' ); ?></button>
						</div>
					</th>
					<th colspan="6"><p><?php esc_html_e( 'Products will automatically be packed into these boxes based on product dimensions, box dimensions, and volume. Products which do not fit into any of these boxes will be packaged individually based on their dimensions. Shipping costs will be based around these box dimensions.', 'live-rates-for-shipstation' ); ?></p></th>
				</tr>
			</tfoot>
		</table>
	</td>
</tr>