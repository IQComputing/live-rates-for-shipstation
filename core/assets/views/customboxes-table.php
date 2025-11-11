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


/**
 * Print a Custom Box List Item
 *
 * @param Array $box - Actual box data
 */
function iqlrssPrintCustomBoxItem( $box ) {

	static $boxNum = 1;

	/**
	 * The following are fallbacks for previous versions that had different a Custom Box implementation.
	 */
	$box_arr = array_merge( array(

		/* translators: %1$d is the an arbitrary box ID. Usually this is set by the user and won't ever be seen. */
		'nickname' 	=> sprintf( esc_html__( 'Box %1$d', 'live-rates-for-shipstation' ), $boxNum ),
		'outer'		=> '',
		'inner'		=> '',
		'price'		=> '',
		'active'	=> 1,
	), (array)$box );

	$item_html = sprintf( '<tr>' );

		// Removal Checkbox
		$item_html .= '<td>';
			$item_html .= sprintf( '<label><input type="checkbox"><span class="screen-reader-text">%s</span></label>',
				esc_html__( 'Marks custom box for removal.', 'live-rates-for-shipstation' )
			);
		$item_html .= '</td>';

		// Friendly Name + JSON Data
		$item_html .= '<td>';
			$item_html .= sprintf( '<input type="hidden" name="custombox[][json]" value="%s">',
				( ! isset( $data['clone'] ) ) ? esc_attr( wp_json_encode( $box_arr ) ) : ''
			);
			$item_html .= sprintf( '<a href="#" data-iqlrss-modal="customBoxesFormModal" data-assoc="nickname">%s</a>',
				$box_arr['nickname'],
			);
		$item_html .= '</td>';

		// Package / Dimensions
		$item_html .= sprintf( '<td data-assoc="box_dimensions">%s%s</td>',
			( is_array( $box_arr['outer'] ) ) ? implode( 'x', $box_arr['outer'] ) : '',
			( is_array( $box_arr['inner'] ) && ! empty( array_filter( $box_arr['inner'] ) ) ) ? ' (' . implode( 'x', $box_arr['inner'] ) . ')' : ''
		);

		// Price
		$item_html .= sprintf( '<td data-assoc="box_price">%s</td>', wc_price( $box_arr['price'] ) );

		// Warehouse?
		$item_html .= sprintf( '<td data-assoc="box_warehouse">%s</td>', ( isset( $box_arr['warehouse'] ) ) ? $box_arr['warehouse'] : '' );

		// Enabler Switch
		$item_html .= '<td>';
			$item_html .= '<label class="iqlrss-field-switch">';
				$item_html .= sprintf( '<input type="checkbox" name="box_active" value="1" aria-label="%s"%s>',
					esc_attr__( 'Toggle Custom Box Active State', 'live-rates-for-shipstation' ),
					checked( true, $box_arr['active'], false )
				);
					$item_html .= '<span>';
						$item_html .= sprintf( '<span class="text-active" aria-hidden="true">%s</span>', esc_html__( 'Active', 'live-rates-for-shipstation' ) );
						$item_html .= sprintf( '<span class="text-inactive" aria-hidden="true">%s</span>', esc_html__( 'Inactive', 'live-rates-for-shipstation' ) ); // Hadouken!
						$item_html .= '<span></span>';
					$item_html .= '</span>';
				$item_html .= '</label>';
		$item_html .= '</td>';

	$item_html .= '</tr>';
	print( $item_html );

	++$boxNum;

}

?>

<tr valign="top" id="customBoxesRow" style="display:<?php echo ( $show_custom ) ? 'table-row' : 'none'; ?>;" data-count="<?php echo esc_attr( count( $saved_boxes ) ); ?>">
	<th scope="row" class="titledesc no-padleft"><label for="customBoxesSearch"><?php esc_html_e( 'Custom Packing Boxes', 'live-rates-for-shipstation' ); ?></label></th>
	<td class="forminp">
		<div id="customBoxActions">
			<button type="button" class="button-primary" data-iqlrss-modal="customBoxesFormModal"><?php esc_html_e( 'Add New Custom Box', 'live-rates-for-shipstation' ); ?></button>
			<button type="button" id="customBoxRemove" class="button-secondary"><?php esc_html_e( 'Remove Selected Boxes', 'live-rates-for-shipstation' ); ?></button>
		</div>
		<table id="iqlrssCustomBoxes"><tbody><?php

			// The Real Ones.
			foreach( $saved_boxes as $idx => $box_arr ) {
				iqlrssPrintCustomBoxItem( $box_arr );
			}

			// The Clone.
			iqlrssPrintCustomBoxItem( array() );

		?><tbody></table>
		<dialog id="customBoxesFormModal" class="iqlrss-modal" data-index="-1">
			<h3 class="iqlrss-modal-title --tab"><?php esc_html_e( 'Custom Box', 'live-rates-for-shipstation' ); ?></h3>
			<button type="button"><span class="screen-reader-text"><?php esc_html_e( 'Close Custom Box Modal', 'live-rates-for-shipstation' ); ?></span><i class="dashicons dashicons-no"></i></button>
			<div class="iqlrss-modal-content">

				<div class="iqlrss-flex --flexwrap --cols3">
					<div class="iqlrss-field --required">
						<label for="boxNickname"><?php esc_html_e( 'Nickname', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="nickname" id="boxNickname">
						<p class="description"><?php esc_html_e( 'The nickname is for your identification only.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field">
						<label for="boxWeight"><?php esc_html_e( 'Box Weight', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_weight" id="boxWeight" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The weight of the empty box in lbs.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field">
						<label for="boxMaxWeight"><?php esc_html_e( 'Max Packing Weight', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_weight_max" id="boxMaxWeight" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'Max weight the box can hold in lbs.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field --required">
						<label for="boxLength"><?php esc_html_e( 'Box Length', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_length" id="boxLength" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The length of the box in inches.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field --required">
						<label for="boxWidth"><?php esc_html_e( 'Box Width', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_width" id="boxWidth" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The width of the box in inches.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field --required">
						<label for="boxHeight"><?php esc_html_e( 'Box Height', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_height" id="boxHeight" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The height of the box in inches.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field --complex iqlrss-flex --flexwrap --valign-center">
						<label for="boxInnerToggle"><?php esc_html_e( 'Set Box Inner Dimensions?', 'live-rates-for-shipstation' ); ?></label>
						<label class="iqlrss-field-switch">
							<input type="checkbox" id="boxInnerToggle" name="box_inner_toggle" value="0" aria-label="<?php esc_attr_e( 'Toggle Inner Dimensions', 'live-rates-for-shipstation' ); ?>">
							<span>
								<span class="text-active" aria-hidden="true"><?php esc_html_e( 'Yes', 'live-rates-for-shipstation' ); ?></span>
								<span class="text-inactive" aria-hidden="true"><?php esc_html_e( 'No', 'live-rates-for-shipstation' ); ?></span>
								<span></span>
							</span>
						</label>
					</div>

					<div class="iqlrss-field --required --w22" data-enabledby="boxInnerToggle">
						<label for="boxInnerLength"><?php esc_html_e( 'Box Inner Length', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_length_inner" id="boxInnerLength" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The box\'s inner length in inches.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field --required --w22" data-enabledby="boxInnerToggle">
						<label for="boxInnerWidth"><?php esc_html_e( 'Box Inner Width', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_width_inner" id="boxInnerWidth" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The box\'s inner width in inches.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field --required --w22" data-enabledby="boxInnerToggle">
						<label for="boxInnerHeight"><?php esc_html_e( 'Box Inner Height', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_height_inner" id="boxInnerHeight" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'The box\'s inner height in inches.', 'live-rates-for-shipstation' ); ?></p>
					</div>

					<div class="iqlrss-field">
						<label for="boxPrice"><?php esc_html_e( 'Box Price', 'live-rates-for-shipstation' ); ?></label>
						<input type="text" name="box_price" id="boxPrice" inputmode="decimal" class="iqlrss-numbers-only">
						<p class="description"><?php esc_html_e( 'This box unit price will be added to the returned shipment rates.', 'live-rates-for-shipstation' ); ?></p>
					</div>
				</div>

				<button type="button" id="saveCustomBox" class="button-primary">Save Custom Box</button>
			</div>
		</dialog>
    </td>
</tr>