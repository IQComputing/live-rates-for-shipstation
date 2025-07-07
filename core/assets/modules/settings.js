/**
 * WooCommerce ShipStation Settings Page
 */
export class shipStationSettings {

	constructor() {

		/* Missing API Key field? */
		if( ! document.querySelector( '[name*=iqlrss_api_key]' ) ) return;

		/* API is valid - don't hide fields */
		if( document.body.classList.contains( 'iqrlss-api-v' ) ) return;

		/* Hide fields until API is validated. */
		const $elms = document.querySelectorAll( '[name*=iqlrss]' );
		if( $elms ) {
			$elms.forEach( ( $elm ) => {
				if( $elm.getAttribute( 'name' ).includes( 'api_key' ) ) return;
				if( $elm.getAttribute( 'name' ).includes( 'cart_weight' ) ) return;
				$elm.closest( 'tr' ).style.display = 'none';
			} );
		}

		/* Add API Buttons */
		this.addApiButtons()

	}


	/**
	 * Add API Buttons to the API Row for verification purposes.
	 */
	addApiButtons() {

		const $apiRow = document.querySelector( '[name*=iqlrss_api_key]' ).closest( 'tr' );
		if( ! $apiRow ) return;

		/* Class to denote our API Row. */
		$apiRow.classList.add( 'iqlrss-api-row' );

		let $button = document.createElement( 'button' );
			$button.innerText = iqlrss.text.button_api_verify;
			$button.type = 'button';
			$button.classList.add( 'iqlrss-api-verify', 'button-primary' );

			/* Try to get response from API using their API Key */
			$button.addEventListener( 'click', () => {

				const apiVal = document.querySelector( '[name*=iqlrss_api_key]' ).value;
				if( ! apiVal ) return;

				/* Button doing work! */
				$button.classList.add( 'active' );

				/* Remove previous errors */
				$apiRow.querySelectorAll( '.description.error' ).forEach( function() {
					this.remove();
				} );

				fetch( iqlrss.rest.apiverify, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': iqlrss.rest.nonce,
					},
					body: JSON.stringify( {
						'key': apiVal,
					} ),
				} ).then( response => response.json() )
				.then( ( json ) => {

					/* Error */
					if( ! json.success ) {

						let $err = document.createElement( 'p' );
							$err.classList.add( 'description', 'error' );
							$err.innerText = ( json.data.length ) ? json.data[0].message : iqlrss.text.error_rest_generic;
						$apiRow.querySelector( 'fieldset' ).appendChild( $err );
						return;

					}

					/* Denote success and show fields */

				} );


			} );

		$apiRow.querySelector( 'fieldset' ).appendChild( $button );
		$button.style.right = '-' + ( $button.getBoundingClientRect().width + 8 ) + 'px';

	}

}