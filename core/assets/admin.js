/**
 * Load ShipStation Module Settings.
 */
if( document.getElementById( 'woocommerce_shipstation_iqlrss_api_key' ) ) {
	import( './modules/settings.js' ).then( ( Module ) => {
		new Module.shipStationSettings();
	} );
}


/**
 * Load Shipping Zone Module Settings.
 */
if( document.getElementById( 'woocommerce_iqlrss_shipstation_title' ) ) {
	import( './modules/shipping-zone.js' ).then( ( Module ) => {
		new Module.shippingZoneSettings();
	} );
}