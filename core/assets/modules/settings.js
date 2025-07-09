/**
 * WooCommerce ShipStation Settings Page
 *
 * Not really meant to be used as an object but more for
 * encapsulation and organization.
 */
export class shipStationSettings {

	/**
	 * Setup events.
	 */
	constructor() {

		/* Missing API Key field? */
		if( ! document.querySelector( '[name*=iqlrss_api_key]' ) ) return;

		/* Settings Setup */
		this.apiButtonSetup();
		this.singleLowestSetup();

	}


	/**
	 * Add API Buttons to the API Row for verification purposes.
	 *
	 * @note this method may be doing a bit too much.
	 */
	apiButtonSetup() {

		const $apiRow = document.querySelector( '[name*=iqlrss_api_key]' ).closest( 'tr' );
		if( ! $apiRow ) return;

		/* Class to denote our API Row. */
		$apiRow.classList.add( 'iqlrss-api-row' );

		let $button = document.createElement( 'button' );
			$button.innerText = iqlrss.text.button_api_verify;
			$button.type = 'button';
			$button.classList.add( 'iqlrss-api-verify', 'button-primary' );

			/**
			 * Event: Click
			 * Hide any previous errors and try to get response from ShipStation REST API.
			 */
			$button.addEventListener( 'click', () => {

				if( ! document.querySelector( '[name*=iqlrss_api_key]' ).value ) return;
				if( $button.classList.contains( 'active' ) ) return;

				/* Button doing work! */
				$button.classList.add( 'active' );

				/* Remove previous errors */
				this.rowClearError( $apiRow );

				/* Make API Request */
				this.apiButtonFetch( $apiRow ).then( ( success ) => {

					$button.classList.remove( 'active' );

					/* Return - API Error */
					if( ! success ) return false;

					/* Remove button and show validated check icon */
					$button.animate( {
						opacity: [ 0 ]
					}, {
						duration: 300
					} ).onfinish = () =>  {

						$button.remove();

						/* Success check-circle dashicon animate in */
						const $ico = document.createElement( 'span' )
							$ico.classList.add( 'dashicons', 'dashicons-yes-alt', 'iqlrss-success' );
						$apiRow.querySelector( 'fieldset' ).appendChild( $ico );
						setTimeout( () => {
							$ico.animate( {
								color: [ 'green', 'limegreen', 'green' ],
								transform: [ 'scale(1)', 'scale(1.2)', 'scale(1)' ],
							}, {
								duration: 600,
								easing: 'ease-in-out',
							} );
						}, 300 );

					}

				} );

			} );

		$apiRow.querySelector( 'fieldset' ).appendChild( $button );
		$button.style.right = '-' + ( $button.getBoundingClientRect().width + 8 ) + 'px';

	}


	/**
	 * Try to make an API request to ensure the REST key is valid.
	 *
	 * @param {DOMObject} $apiRow - Table row where the button lives.
	 *
	 * @return {Promise} - Boolean of success
	 */
	async apiButtonFetch( $apiRow ) {

		return await fetch( iqlrss.rest.apiverify, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': iqlrss.rest.nonce,
			},
			body: JSON.stringify( {
				'key': document.querySelector( '[name*=iqlrss_api_key]' ).value,
			} ),
		} ).then( response => response.json() )
		.then( ( json ) => {

			/* Error- slidedown */
			if( ! json.success ) {
				this.rowAddError( $apiRow, ( json.data.length ) ? json.data[0].message : iqlrss.text.error_rest_generic );
				return false;
			}

			/* Denote success and show fields - fadein */
			document.querySelectorAll( '[name*=iqlrss]' ).forEach( ( $elm ) => {

				const $row = $elm.closest( 'tr' );
				if( ! $row || 'none' != $row.style.display ) return;

				/* Skip the Return Lowest Label if related isn't checked */
				if( -1 != $elm.name.indexOf( 'return_lowest_label' ) && ! document.querySelectorAll( '[type=checkbox][name*=return_lowest_label]' ).checked ) {
					return;
				}

				this.rowMakeVisible( $row, true );
			} );

			/* Trigger the return lowest checkbox - this may display it's connected label input. */
			document.querySelector( '[type=checkbox][name*=return_lowest' ).dispatchEvent( new Event( 'change' ) );
			return true;

		} );

	}


	/**
	 * Show / Hide the Single Lowest label
	 */
	singleLowestSetup() {

		const $lowestcb 	= document.querySelector( '[type=checkbox][name*=return_lowest' );
		const $lowestLabel 	= document.querySelector( '[type=text][name*=return_lowest_label' );

		/**
		 * Event: Change
		 * Toggle the Lowest Rate Label row visibility.
		 */
		$lowestcb.addEventListener( 'change', () => {
			this.rowMakeVisible( $lowestLabel.closest( 'tr' ), $lowestcb.checked );
		} );

		/* Eh, just trigger it */
		if( 'none' != $lowestcb.closest( 'tr' ).style.display ) {
			$lowestcb.dispatchEvent( new Event( 'change' ) );
		}

	}


	/**
	 * Toggle row visibility
	 *
	 * @param {DOMObject} $row
	 * @param {Boolean} visible
	 */
	rowMakeVisible( $row, visible ) {

		if( visible ) {

			$row.setAttribute( 'style', 'opacity:0' );
			$row.animate( {
				opacity: [ 1 ]
			}, {
				duration: 300
			} ).onfinish = () => $row.removeAttribute( 'style' );

		} else {

			$row.animate( {
				opacity: [ 0 ]
			}, {
				duration: 300
			} ).onfinish = () => $row.setAttribute( 'style', 'display:none;' );

		}

	}


	/**
	 * Add settings row error
	 * SlideDown
	 *
	 * @param {DOMObject} $row
	 * @param {String} message
	 */
	rowAddError( $row, message ) {

		let $err = document.createElement( 'p' );
			$err.classList.add( 'description', 'iqcss-err' );
			$err.innerText = message;

		$row.querySelector( 'fieldset' ).appendChild( $err );
		const errHeight = $err.getBoundingClientRect().height;
		$err.remove();

		$err.setAttribute( 'style', 'height:0px;opacity:0;overflow:hidden;' );
		$row.querySelector( 'fieldset' ).appendChild( $err );

		$err.animate( {
			height: [ errHeight + 'px' ],
			opacity: [ 1 ]
		}, {
			duration: 300
		} ).onfinish = () => $err.removeAttribute( 'style' );

	}


	/**
	 * Clear settings row errors.
	 * SlideUp
	 *
	 * @param {DOMObject} $row
	 */
	rowClearError( $row ) {

		$row.querySelectorAll( '.description.iqcss-err' ).forEach( ( $err ) => {
			$err.style.overflow = 'hidden';
			$err.animate( {
				height: [ $err.getBoundingClientRect().height + 'px', '0px' ],
				opacity: [ 1, 0 ]
			}, {
				duration: 300
			} ).onfinish = () => $err.remove();
		} );

	}

}