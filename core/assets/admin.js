/**
 * Load ShipStation Module Settings.
 */
if( document.getElementById( 'woocommerce_shipstation_iqlrss_api_key' ) ) {
	import( './modules/settings.js' ).then( ( Module ) => {
		new Module.shipStationSettings();
	} );
}