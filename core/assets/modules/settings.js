/**
 * WooCommerce ShipStation Settings Page
 *
 * Not really meant to be used as an object but more for
 * encapsulation and organization.
 *
 * @global {Object} iqlrss - Localized object of saved values.
 */
export class shipStationSettings {

	/**
	 * API Input.
	 *
	 * @var {DOMObject}
	 */
	#apiInput;


	/**
	 * Setup events.
	 */
	constructor() {

		/* Missing API Key field? */
		if( ! document.querySelector( '[name*=iqlrss_api_key]' ) ) return;

		/* Set instance APIInput */
		this.#apiInput = document.querySelector( '[name*=iqlrss_api_key]' );

		/* Settings Setup */
		const $button = this.apiButtonSetup();
		this.apiInputChange( $button );
		this.verificationRequiredCheck( $button );

		this.apiClearCache();
		this.priceAdjustmentNumbersOnly();
		this.singleLowestSetup();

	}


	/**
	 * Add API Buttons to the API Row for verification purposes.
	 *
	 * @note this method may be doing a bit too much.
	 *
	 * @return {DOMObject} $button - The created verification button.
	 */
	apiButtonSetup() {

		const $apiRow = this.#apiInput.closest( 'tr' );
		if( ! $apiRow ) return null;

		/* Class to denote our API Row. */
		$apiRow.classList.add( 'iqlrss-api-row' );

		let $button = document.createElement( 'button' );
			$button.innerText = iqlrss.text.button_api_verify;
			$button.type = 'button';
			$button.id = 'iqlrssVerifyButton';
			$button.classList.add( 'button-primary' );

			/**
			 * Event: Click
			 * Hide any previous errors and try to get response from ShipStation REST API.
			 */
			$button.addEventListener( 'click', () => {

				if( ! this.#apiInput.value ) return;
				if( $button.classList.contains( 'active' ) ) return;

				/* Button doing work! */
				$button.classList.add( 'active' );

				/* Remove previous errors */
				this.rowClearError( $apiRow );

				/* Make API Request */
				this.apiButtonVerifyFetch( $apiRow ).then( ( success ) => {

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

		if( ! this.#apiInput.value ) {
			$button.style.opacity = 0;
		}

		$apiRow.querySelector( 'fieldset' ).appendChild( $button );

		return $button;

	}


	/**
	 * Try to make an API request to ensure the REST key is valid.
	 *
	 * @param {DOMObject} $apiRow - Table row where the button lives.
	 *
	 * @return {Promise} - Boolean of success
	 */
	async apiButtonVerifyFetch( $apiRow ) {

		return await fetch( iqlrss.rest.apiactions, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': iqlrss.rest.nonce,
			},
			body: JSON.stringify( {
				'action': 'verify',
				'key': this.#apiInput.value,
			} ),
		} ).then( response => response.json() )
		.then( ( json ) => {

			/* Error- slidedown */
			if( ! json.success ) {
				iqlrss.api_verified = false;
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
			iqlrss.api_verified = true;
			return true;

		} );

	}


	/**
	 * Show / Hide the Verify API button depending if the
	 * input value exists or not.
	 *
	 * @param {DOMObject} $button - The API verification button
	 */
	apiInputChange( $button ) {

		/* Initial animation */
		if( this.#apiInput.value && $button ) {
			$button.animate( { opacity: 1 }, 300 );
		}

		this.#apiInput.addEventListener( 'input', ( e ) => {

			if( ! $button ) return;

			if( e.target.value ) {
				$button.animate( { opacity: 1 }, { duration: 300, fill: 'forwards' } )
			} else {
				$button.animate( { opacity: 0 }, { duration: 300, fill: 'forwards' } );
				this.rowClearError( document.querySelector( '.iqlrss-api-row' )  );
			}

		} );

	}


	/**
	 * Ensure that the user verifies their REST API Key.
	 *
	 * @param {DOMObject} $button - The API verification button
	 */
	verificationRequiredCheck( $button ) {

		if( ! $button ) return;

		const $settingsForm = document.getElementById( 'mainform' );
		const $apiRow 		= document.querySelector( '.iqlrss-api-row' );

		$settingsForm.addEventListener( 'submit', ( e ) => {

			this.rowClearError( $apiRow );
			if( iqlrss.api_verified ) return true;

			if( this.#apiInput.value ) {

				e.preventDefault();
				e.stopImmediatePropagation();

				$button.animate( { opacity: 1 }, { duration: 300, fill: 'forwards' } )
				this.rowAddError( $apiRow, iqlrss.text.error_verification_required );

				const $wooSave = document.querySelector( '.woocommerce-save-button' );
				if( $wooSave && $wooSave.classList.contains( 'is-busy' ) ) {
					$wooSave.classList.remove( 'is-busy' );
				}

				return false;
			}

		} );

	}


	/**
	 * Clear the API cache.
	 */
	apiClearCache() {

		const $apiRow = this.#apiInput.closest( 'tr' );
		if( ! $apiRow ) return null;

		let $button = document.createElement( 'button' );
			$button.innerText = iqlrss.text.button_api_clearcache;
			$button.type = 'button';
			$button.id = 'iqlrssClearCacheButton';
			$button.classList.add( 'button-secondary' );

		$button.addEventListener( 'click', () => {

			if( $button.classList.contains( 'working' ) ) return false;

			$button.classList.remove( 'complete' );
			$button.classList.add( 'working' );

			fetch( iqlrss.rest.apiactions, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': iqlrss.rest.nonce,
				},
				body: JSON.stringify( {
					'action': 'clearcache'
				} ),
			} ).then( response => response.json() )
			.then( ( json ) => {
				$button.classList.remove( 'working' );
				$button.classList.add( 'complete' );
				setTimeout( () => {
					$button.classList.remove( 'complete' );
				}, 3000 );
			} );

		} );

		$apiRow.querySelector( 'fieldset' ).appendChild( $button );

	}


	/**
	 * Only allow numbers for the Price Adjustment input.
	 */
	priceAdjustmentNumbersOnly() {

		const $adjustmentInput = document.querySelector( '[type=text][name*=global_adjustment' );
		$adjustmentInput.addEventListener( 'input', ( e ) => {
			e.target.value = e.target.value.replace( /[^0-9.]/g, '' );
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