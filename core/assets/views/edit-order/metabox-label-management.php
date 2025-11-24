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

<button class="button button-primary"><?php esc_html_e( 'Create Shipment Label', 'live-rates-for-shipstation' ); ?></button>