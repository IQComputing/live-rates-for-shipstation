/**
 * ShipStation Integration Module Settings.
 * @import shipStationSettings
 */
if( document.getElementById( 'woocommerce_shipstation_iqlrss_api_key' ) ) {
	import( './integration-settings.js' ).then( ( Module ) => {
		new Module.shipStationSettings();
	} );
}


/**
 * Shipping Zone Module Settings.
 * @import shippingZoneSettings
 */
if( document.getElementById( 'woocommerce_iqlrss_shipstation_title' ) ) {

	// @todo Remove
	// document.querySelector( '[name="woocommerce_iqlrss_shipstation_packing"]' ).value = 'wc-box-packer';
	// document.querySelector( '[name="woocommerce_iqlrss_shipstation_packing"]' ).dispatchEvent( new Event( 'change' ) );

	import( './shipping-zones/_main.js' ).then( ( Module ) => {

		new Module.shippingZoneSettings();

		// @todo Remove
		// setTimeout( () => {
		// 	document.querySelector( '[data-iqlrss-modal]' ).dispatchEvent( new Event( 'click', { bubbles: true } ) );
		// }, 500 );

	} );
}