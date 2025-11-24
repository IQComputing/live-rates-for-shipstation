import * as util from '../modules/utility.js';

/**
 * WooCommerce Edit Order Settings
 *
 * Not really meant to be used as an object but more for
 * encapsulation and organization.
 *
 *
 *
 * @global {Object} iqlrss - Localized object of saved values.
 */
export class editOrderSettings {

    /**
	 * Setup events.
	 */
	constructor() {

		this.createShippingLabel();

	}


	/**
	 * Create Shipping Label
	 */
	createShippingLabel() {

		document.querySelector( '[data-iqlrss-modal="shipstationLabelModal"]' ).addEventListener( 'click', ( e ) => {
			e.stopImmediatePropagation();
			e.preventDefault();
			util.loadModule( 'modal', e.target, { 'modal': e.target.dataset.iqlrssModal } );
		} );

	}

}