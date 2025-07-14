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
					if( 'text' == $elm.getAttribute( 'type' ) ) $elm.removeAttribute( 'required' );
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
				$elm.setAttribute( 'name', $elm.getAttribute( 'name' ).replace( 'mimic', count ) );
				if( 'text' == $elm.getAttribute( 'type' ) && -1 == $elm.getAttribute( 'name' ).indexOf( '[wm]' ) ) $elm.setAttribute( 'required', true );
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
	 * Only allow numbers in inputs.
	 */
	inputsNumbersOnly() {

		document.addEventListener( 'input', ( e ) => {
			if( 'INPUT' !== e.target.tagName ) return;
			if( false !== e.target.getAttribute( 'name' ).indexOf( 'custombox' ) || e.target.classList.contains( 'iqlrss-numbers-only' ) ) {
				e.target.value = e.target.value.replace( /[^0-9.]/g, '' );
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