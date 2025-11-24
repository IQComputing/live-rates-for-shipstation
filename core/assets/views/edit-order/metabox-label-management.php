<?php
/**
 * Edit WooCommerce Order Metabox: ShipStation Label Management
 * Allow the user to manage the shipping packages and necessary fields to
 * send to ShipStation for label creation.
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 */

if( ! defined( 'ABSPATH' ) ) {
	return;
}

?>

<button class="button button-primary" data-iqlrss-modal="shipstationLabelModal"><?php esc_html_e( 'Create Shipping Label', 'live-rates-for-shipstation' ); ?></button>

<dialog id="shipstationLabelModal" class="iqlrss-modal">
	<h3 class="iqlrss-modal-title --tab"><?php esc_html_e( 'ShipStation Shipping Label', 'live-rates-for-shipstation' ); ?></h3>
	<button type="button"><span class="screen-reader-text"><?php esc_html_e( 'Close Shipping Label Modal', 'live-rates-for-shipstation' ); ?></span><i class="dashicons dashicons-no"></i></button>
	<div class="iqlrss-modal-content">
		<p>Here</p>
	</div>
</dialog>