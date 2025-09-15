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
		this.customBoxesSelectAll();
		this.customBoxesAdd();
		this.customBoxesRemove();

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
			if( 'wc-box-packer' == this.value ) {

				document.getElementById( 'customBoxes' ).style.display = 'table-row';
				if( document.querySelectorAll( '#customBoxes tbody tr' ).length < 2 ) {
					document.querySelector( '#customBoxes button[name=add]' ).click();
				}

			} else {

				document.querySelectorAll( '#customBoxes [name]' ).forEach( ( $elm ) => {
					if( 'text' == $elm.type ) $elm.removeAttribute( 'required' );
				} );
				document.getElementById( 'customBoxes' ).style.display = 'none';

			}
		} );

	}


	/**
	 * Custom Boxes
	 * Select all boxes checkbox.
	 */
	customBoxesSelectAll() {

		document.querySelector( '#customBoxes [name=customboxes_removeall]' ).addEventListener( 'input', function() {
			document.querySelectorAll( '#customBoxes tbody tr:not(.mimic) [type=checkbox]' ).forEach( ( $elm ) => {
				$elm.checked = this.checked;
			} );
		} );

	}


	/**
	 * Custom Boxes
	 * Add new row.
	 */
	customBoxesAdd() {

		document.querySelector( '#customBoxes button[name=add]' ).addEventListener( 'click', () => {

			const count  = document.querySelectorAll( '#customBoxes tbody tr' ).length - 1;
			const $clone = document.querySelector( '#customBoxes tr.mimic' ).cloneNode( true );

			$clone.classList.remove( 'mimic' );
			$clone.querySelectorAll( '[name]' ).forEach( ( $elm ) => {
				$elm.name = $elm.name.replace( 'mimic', count );
				if( 'text' == $elm.type && -1 == $elm.name.indexOf( '[wm]' ) ) $elm.required = true;
			} );

			document.querySelector( '#customBoxes tbody' ).appendChild( $clone );

		} );

	}


	/**
	 * Custom Boxes
	 * Remove row(s).
	 */
	customBoxesRemove() {

		document.querySelector( '#customBoxes button[name=remove]' ).addEventListener( 'click', () => {

			const $checkedBoxes = document.querySelectorAll( '#customBoxes tbody tr:not(.mimic) [type=checkbox]:is(:checked)' );

			if( ! $checkedBoxes.length ) return;
			if( window.confirm( iqlrss.text.confirm_box_removal.replace( '(x)', `(${$checkedBoxes.length})` ) ) ) {
				document.querySelectorAll( '#customBoxes tbody tr:not(.mimic) [type=checkbox]:is(:checked)' ).forEach( $elm => {
					$elm.closest( 'tr' ).remove();
				} );
			}

			document.querySelectorAll( '#customBoxes [type=checkbox]:is(:checked)' ).forEach( $elm => $elm.checked = false );

		} );

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
			if( -1 == e.target.name.indexOf( 'adjustment_type' ) ) return;

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
			if( -1 != e.target.name.indexOf( 'custombox' ) || e.target.classList.contains( 'iqlrss-numbers-only' ) ) {
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