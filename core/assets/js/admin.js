/**
 * ShipStation Integration Module Settings.
 * @import shipStationSettings
 */
if( document.getElementById( 'woocommerce_shipstation_iqlrss_api_key' ) ) {
	import( './modules/integration-settings.js' ).then( ( Module ) => {
		new Module.shipStationSettings();
	} );
}


/**
 * Shipping Zone Module Settings.
 * @import shippingZoneSettings
 */
if( document.getElementById( 'woocommerce_iqlrss_shipstation_title' ) ) {
	import( './shipping-zones/_main.js' ).then( ( Module ) => {
		new Module.shippingZoneSettings();
	} );
}