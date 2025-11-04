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
 * @param Array $data
 */
function iqlrssPrintCustomBoxItem( $data ) {

	static $count = -1;

	if( 'clone' == $data ) {
		$data = array(
			'index'		=> -1,
			'classes'	=> array( 'iqlrss-flex', 'clone' ),
		);
	}

	$box_arr = array_merge( array(
		'index'		=> $count,
		'classes'	=> array( 'iqlrss-flex' ),
		'nickname'	=> esc_html__( 'Clone Item', 'live-rates-for-shipstation' )
	), $data );

	$item_html = sprintf( '<li class="%s">', esc_attr( implode( ' ', $box_arr['classes']  ) ) );

		// Removal Checkbox
		$item_html .= '<div>';
			$item_html .= sprintf( '<label for="removeCustomBox_%1$d"><input type="checkbox" name="custombox[%1$d][remove]" id="removeCustomBox_%1$d"><span class="screen-reader-text">%s</span></label>',
				esc_attr( $data['index'] ),
				esc_html__( 'Checkbox denotes removal of the custom box.', 'live-rates-for-shipstation' )
			);
		$item_html .= '</div>';

		// Friendly Name + JSON Data
		$item_html .= '<div>';
			$item_html .= sprintf( '<input type="hidden" name="custombox[%d][json]" value="%s">',
				$data['index'],
				esc_attr( isset( $box_arr['json'] ) ? wp_json_encode( $box_arr['json'] ) : '' )
			);
			$item_html .= sprintf( '<a href="#" data-iqlrss-modal="customBoxesFormModal" data-assoc="nickname">%s</a>',
				$box_arr['nickname'],
			);
		$item_html .= '</div>';

		// Package / Dimensions
		$item_html .= '<div><div data-assoc="box_dimensions"></div></div>';

		// Price
		$item_html .= '<div><div data-assoc="box_price"></div></div>';

		// Warehouse?
		$item_html .= '<div><div data-assoc="box_warehouse"></div></div>';

		// Enabler Switch
		$item_html .= '<div>';
			$item_html .= '<label class="iqlrss-field-switch">';
				$item_html .= sprintf( '<input type="checkbox" name="box_active" value="1" aria-label="%s">', esc_attr__( 'Toggle Custom Box Active State', 'live-rates-for-shipstation' ) );
					$item_html .= '<span>';
						$item_html .= sprintf( '<span class="text-active" aria-hidden="true">%s</span>', esc_html__( 'Active', 'live-rates-for-shipstation' ) );
						$item_html .= sprintf( '<span class="text-inactive" aria-hidden="true">%s</span>', esc_html__( 'Inactive', 'live-rates-for-shipstation' ) ); // Hadouken!
						$item_html .= '<span></span>';
					$item_html .= '</span>';
				$item_html .= '</label>';
		$item_html .= '</div>';

	$item_html .= '</li>';
	print( $item_html );

	++$count;

}

?>

<tr valign="top" id="customBoxesRow" style="display:<?php echo ( $show_custom ) ? 'table-row' : 'none'; ?>;" data-count="<?php echo esc_attr( count( $saved_boxes ) ); ?>">
	<th scope="row" class="titledesc no-padleft"><label for="customBoxesSearch"><?php esc_html_e( 'Custom Packing Boxes', 'live-rates-for-shipstation' ); ?></label></th>
	<td class="forminp">
		<div id="customBoxActions">
			<button type="button" class="button-primary" data-iqlrss-modal="customBoxesFormModal"><?php esc_html_e( 'Add New Custom Box', 'live-rates-for-shipstation' ); ?></button>
			<button type="button" id="customBoxRemove" class="button-secondary"><?php esc_html_e( 'Remove Selected Boxes', 'live-rates-for-shipstation' ); ?></button>
		</div>
		<div id="customBoxSearchWrap">
			<p><?php esc_html_e( 'The search field below will automatically filter results as you type.', 'live-rates-for-shipstation' ); ?></p>
        	<input type="search" id="customBoxesSearch" placeholder="<?php esc_attr_e( 'Search Custom Boxes ...', 'live-rates-for-shipstation' ); ?>">
			<button type="button"><?php esc_html_e( 'Clear Search', 'live-rates-for-shipstation' ); ?></button>
		</div>
		<ul id="iqlrssCustomBoxes"><?php

			// The Real Ones.
			foreach( $saved_boxes as $idx => $box_arr ) {
				iqlrssPrintCustomBoxItem( array_merge( $box_arr, array(
					'index' => $idx,
				) ) );
			}

			// The Clone.
			iqlrssPrintCustomBoxItem( 'clone' );

		?></ul>
		<dialog id="customBoxesFormModal" class="iqlrss-modal">
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
						<input type="text" name="box_maxweight" id="boxMaxWeight" inputmode="decimal" class="iqlrss-numbers-only">
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
						<p class="description"><?php esc_html_e( 'This price will be added ontop of the returned shipment rates.', 'live-rates-for-shipstation' ); ?></p>
					</div>

			</div>

			<button type="button" id="saveCustomBox" class="button-primary">Save Custom Box</button>
		</dialog>
    </td>
</tr>