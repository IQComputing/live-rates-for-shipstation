/**
 * WooCommerce Shipping Zone Settings
 *
 * Not really meant to be used as an object but more for
 * encapsulation and organization.
 *
 * @global {Object} iqlrss - Localized object of saved values.
 */
export class shippingZoneSettings {

	/**
	 * Setup events.
	 */
	constructor() {

		this.customBoxesVisibility();
		this.setupPriceAdjustments()
		this.inputsNumbersOnly();
		this.wooAccommodations();

	}


	/**
	 * Custom Boxes
	 * Manage table visibility.
	 */
	customBoxesVisibility() {

		document.querySelector( '.custom-boxes-control' ).addEventListener( 'change', function() {

			/* Display Custom Boxes */
			if( 'wc-box-packer' == this.value ) {

				import( './custom-boxes.js' ).then( ( Module ) => {
					new Module.CustomBoxes();
					document.getElementById( 'customBoxesRow' ).classList.add( 'ready' );
				} );
				document.getElementById( 'customBoxesRow' ).style.display = 'table-row';

			/* Don't */
			} else {
				document.getElementById( 'customBoxesRow' ).style.display = 'none';
			}

		} );
		document.querySelector( '.custom-boxes-control' ).dispatchEvent( new Event( 'change' ) );

	}


	/**
	 * Price Adjustments
	 * Manage the show/hide functionality.
	 */
	setupPriceAdjustments() {

		/**
		 * Adjustment Type Change
		 * Show / Hide Price Input
		 */
		document.addEventListener( 'change', ( e ) => {

			if( 'SELECT' != e.target.tagName ) return;
			if( ! e.target.name.includes( 'adjustment_type' ) ) return;

			const $adjustmentSelect = e.target;
			const $adjustmentInput  = $adjustmentSelect.closest( 'td' ).querySelector( 'input' );

			if( '' == $adjustmentSelect.value ) {

				$adjustmentInput.animate( {
					opacity: 0
				}, {
					duration: 300,
					fill: 'forwards',
				} ).onfinish = () => {
					$adjustmentInput.value = '';
					$adjustmentInput.classList.add( 'iqlrss-hide' );
				};

			} else if( null === $adjustmentInput.offsetParent ) {

				$adjustmentInput.classList.remove( 'iqlrss-hide' );
				$adjustmentInput.animate( {
					opacity: [0, 1]
				}, {
					duration: 300,
					fill: 'forwards',
				} ).onfinish = () => {
					$adjustmentInput.value = '';
				};

			} else {
				$adjustmentInput.value = ( $adjustmentSelect.value != iqlrss.global_adjustment_type ) ? '0' : '';
			}

		} );

	}


	/**
	 * Only allow numbers in inputs.
	 */
	inputsNumbersOnly() {

		/**
		 * All Custom Packing Box inputs.
		 * Any numbers-only classes
		 */
		document.addEventListener( 'input', ( e ) => {
			if( 'INPUT' !== e.target.tagName ) return;
			if( e.target.name.includes( 'custombox' ) || e.target.classList.contains( 'iqlrss-numbers-only' ) ) {
				e.target.value = e.target.value.replace( /(\..*?)\./g, '$1' ).replace( /[^0-9.]/g, '' );
			}
		} );

	}


	/**
	 * WooCommerce
	 * Remove button class when save error occurs.
	 */
	wooAccommodations() {

		document.querySelector( 'button[name="save"]' ).addEventListener( 'click', function() {
			if( ! document.getElementById( 'mainform' ).checkValidity() ) this.classList.remove( 'is-busy' );
		} );

	}

}