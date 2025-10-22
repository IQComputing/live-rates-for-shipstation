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

<tr valign="top" id="customBoxesRow" style="display:<?php echo ( $show_custom ) ? 'table-row' : 'none'; ?>;">
	<th scope="row" class="titledesc no-padleft"><label for="customBoxesSearch"><?php esc_html_e( 'Custom Packing Boxes', 'live-rates-for-shipstation' ); ?></label></th>
	<td class="forminp">
		<div id="customBoxActions">
			<button type="button" class="iqc-modal button-primary" data-modalid="customBoxesFormModal"><?php esc_html_e( 'Add New Custom Box', 'live-rates-for-shipstation' ); ?></button>
			<button type="button" id="customBoxRemove" class="button-secondary"><?php esc_html_e( 'Remove Selected Boxes', 'live-rates-for-shipstation' ); ?></button>
		</div>
		<div id="customBoxSearchWrap">
			<p><?php esc_html_e( 'The search field below will automatically filter results as you type.', 'live-rates-for-shipstation' ); ?></p>
        	<input type="search" id="customBoxesSearch" placeholder="<?php esc_attr_e( 'Search Custom Boxes ...', 'live-rates-for-shipstation' ); ?>">
			<button type="button"><?php esc_html_e( 'Clear Search', 'live-rates-for-shipstation' ); ?></button>
		</div>
		<ul id="iqlrssCustomBoxes"><?php

			if( empty( $saved_boxes ) ) {
				printf( '<li class="emptylistitem"><span>%s - </span><a href="#" class="iqc-modal" data-modalid="customBoxesFormModal">%s</a></li>',
					esc_html__( 'No custom boxes found', 'live-rates-for-shipstation' ),
					esc_html__( 'Add New Custom Box', 'live-rates-for-shipstation' ),
				);
			} else {
				foreach( $saved_boxes as $idx => $box_arr ) {

					$item_html = '<li class="iqlrss-flexrow">';

						// Removal Checkbox
						$item_html .= '<div>';
							$item_html .= sprintf( '<label for="removeCustomBox_%1$d"><input type="checkbox" name="custombox[%1$d][remove]" id="removeCustomBox_%1$d"><span class="screen-reader-text">%s</span></label>',
								 esc_attr( $idx ),
								 esc_html__( 'Checkbox denotes removal of the custom box.', 'live-rates-for-shipstation' )
							);
						$item_html .= '</div>';

						// Friendly Name + JSON Data
						$item_html .= '<div>';
							$item_html .= sprintf( '<input type="hidden" name="custombox[%d][json]" value="%s">',
								$idx,
								esc_attr( wp_json_encode( $box_arr ) )
							);
							$item_html .= sprintf( '<a href="#" class="iqc-modal" data-modal="customBoxesFormModal">%s</a>',
								$box_arr['nickname'],
							);
						$item_html .= '</div>';

						// Edit Link
						$item_html .= '<div>';
							$item_html .= sprintf( '<a href="#" class="iqc-modal" data-modal="customBoxesFormModal">%s</a>',
								esc_html__( 'Edit Box', 'live-rates-for-shipstation' ),
							);
						$item_html .= '</div>';

					$item_html .= '</li>';

					print( $item_html );

				}
			}

		?></ul>
		<div id="customBoxesFormModal" class="iqlrss-hidden">
			<div class="iqlrss-flexgrid">

				<div class="iqlrss-field field-required">
					<label for="boxNickname"><?php esc_html_e( 'Nickname', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="nickname" id="boxNickname" />
					<p class="description"><?php esc_html_e( 'The nickname is for your identification only.', 'live-rates-for-shipstation' ); ?></p>
				</div>

				<div class="iqlrss-field">
					<label for="boxWeight"><?php esc_html_e( 'Box Weight', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_weight" id="boxWeight" inputmode="decimal" class="iqlrss-numbers-only" />
					<p class="description"><?php esc_html_e( 'The weight of the empty box.', 'live-rates-for-shipstation' ); ?></p>
				</div>

				<div class="iqlrss-field">
					<label for="boxMaxWeight"><?php esc_html_e( 'Max Packing Weight', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_maxweight" id="boxMaxWeight" inputmode="decimal" class="iqlrss-numbers-only" />
					<p class="description"><?php esc_html_e( 'Max weight the box can hold.', 'live-rates-for-shipstation' ); ?></p>
				</div>

				<div class="iqlrss-field">
					<label for="boxLength"><?php esc_html_e( 'Box Length', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_length" id="boxLength" inputmode="decimal" class="iqlrss-numbers-only" />
				</div>

				<div class="iqlrss-field">
					<label for="boxWidth"><?php esc_html_e( 'Box Width', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_width" id="boxWidth" inputmode="decimal" class="iqlrss-numbers-only" />
				</div>

				<div class="iqlrss-field">
					<label for="boxHeight"><?php esc_html_e( 'Box Height', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_height" id="boxHeight" inputmode="decimal" class="iqlrss-numbers-only" />
				</div>

				<div class="iqlrss-field -w25 -noninput">
					<label for="boxInnerDim"><span><?php esc_html_e( 'Separate Inner Dimensions?', 'live-rates-for-shipstation' ); ?></span>
					<input type="checkbox" id="boxInnerDim" /></label>
				</div>

				<div class="iqlrss-field -w25">
					<label for="boxInnerLength"><?php esc_html_e( 'Box Inner Length', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_length_inner" id="boxInnerLength" inputmode="decimal" class="iqlrss-numbers-only" />
				</div>

				<div class="iqlrss-field -w25">
					<label for="boxInnerWidth"><?php esc_html_e( 'Box Inner Width', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_width_inner" id="boxInnerWidth" inputmode="decimal" class="iqlrss-numbers-only" />
				</div>

				<div class="iqlrss-field -w25">
					<label for="boxInnerHeight"><?php esc_html_e( 'Box Inner Height', 'live-rates-for-shipstation' ); ?></label>
					<input type="text" name="box_height_inner" id="boxInnerHeight" inputmode="decimal" class="iqlrss-numbers-only" />
				</div>

			</div>
		</div>
    </td>
</tr>