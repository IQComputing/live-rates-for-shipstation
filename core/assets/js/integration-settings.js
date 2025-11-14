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
	 * Setup events.
	 */
	constructor() {

		new apiVerificationButton( document.querySelector( '[name*=iqlrss_api_key]' ), 'v2' );
		new apiVerificationButton( document.querySelector( '[name*=iqlrss_apiv1_key]' ), 'v1' );

		this.apiClearCache();
		this.setupPriceAdjustments();
		this.setupSingleLowest();

	}


	/**
	 * Clear the API cache.
	 */
	apiClearCache() {

		if( ! ( iqlrss.api_verified || iqlrss.apiv1_verified ) ) {
			return;
		}

		let $button = document.createElement( 'button' );
			$button.innerText = iqlrss.text.button_api_clearcache;
			$button.type = 'button';
			$button.id = 'iqlrssClearCacheButton';
			$button.classList.add( 'button-secondary' );

		$button.addEventListener( 'click', () => {

			if( $button.classList.contains( 'working' ) ) return false;

			$button.classList.remove( 'complete' );
			$button.classList.add( 'working' );

			fetch( iqlrss.rest.settings, {
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

		document.querySelector( '[name*=iqlrss_api_key]' ).closest( 'tr' ).querySelector( 'fieldset' ).appendChild( $button );

	}


	/**
	 * Only allow numbers for the Price Adjustment input.
	 */
	setupPriceAdjustments() {

		const $adjustmentSelect = document.querySelector( 'select[name*=global_adjustment_type]' );
		const $adjustmentInput  = document.querySelector( '[type=text][name*=global_adjustment' );

		/* Select Change - Show Input Row */
		$adjustmentSelect.addEventListener( 'change', ( e ) => {
			$adjustmentInput.value = '';
			rowMakeVisible( $adjustmentInput.closest( 'tr' ), ( e.target.value ) )
		} );

		/* Input Update - Only FloatString */
		$adjustmentInput.addEventListener( 'input', ( e ) => {
			e.target.value = e.target.value.replace( /(\..*?)\./g, '$1' ).replace( /[^0-9.]/g, '' );
		} );

	}


	/**
	 * Show / Hide the Single Lowest label
	 */
	setupSingleLowest() {

		const $lowestcb 	= document.querySelector( '[type=checkbox][name*=return_lowest' );
		const $lowestLabel 	= document.querySelector( '[type=text][name*=return_lowest_label' );

		/**
		 * Event: Change
		 * Toggle the Lowest Rate Label row visibility.
		 */
		$lowestcb.addEventListener( 'change', () => {
			rowMakeVisible( $lowestLabel.closest( 'tr' ), $lowestcb.checked );
		} );

		/* Eh, just trigger it */
		if( $lowestcb.checked && 'none' == $lowestLabel.closest( 'tr' ).style.display ) {
			$lowestcb.dispatchEvent( new Event( 'change' ) );
		}

	}

}


/**
 * API Button Class
 * Manage the API button per API
 */
class apiVerificationButton {

	/**
	 * API Input.
	 *
	 * @var {DOMObject}
	 */
	#apiInput;


	/**
	 * API Type.
	 *
	 * @var {String}
	 */
	#type;


	/**
	 * Verification Button.
	 *
	 * @var {String}
	 */
	#button;


	/**
	 * Setup events.
	 *
	 * @param {DOMObject} $parentInput
	 * @param {String} type - v1|v2
	 */
	constructor( $parentInput, type ) {

		if( ! $parentInput || $parentInput.length ) {
			return;
		}

		this.#apiInput = $parentInput;
		this.#type = type;

		/* Settings Setup */
		this.apiButtonSetup();
		this.apiInputChange();
		this.verificationRequiredCheck();

	}


	/**
	 * Add API Buttons to the API Row for verification purposes.
	 */
	apiButtonSetup() {

		const $apiRow = this.#apiInput.closest( 'tr' );
		if( ! $apiRow ) return null;

		$apiRow.classList.add( 'iqlrss-api-row' );

		let $button = document.createElement( 'button' );
			$button.innerText = iqlrss.text.button_api_verify;
			$button.type = 'button';
			$button.classList.add( 'button-primary' );

			if( 'v1' == this.#type ) {
				$button.innerText += ` [${this.#type}]`;
			}

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
				rowClearError( $apiRow );

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
						$apiRow.querySelector( 'fieldset > input:first-of-type' ).insertAdjacentElement( 'afterend', $ico );
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

		$apiRow.querySelector( 'fieldset > input:first-of-type' ).insertAdjacentElement( 'afterend', $button );
		this.#button = $button;

	}


	/**
	 * Try to make an API request to ensure the REST key is valid.
	 *
	 * @param {DOMObject} $apiRow - Table row where the button lives.
	 *
	 * @return {Promise} - Boolean of success
	 */
	async apiButtonVerifyFetch( $apiRow ) {

		let body = {
			'action': 'verify',
			'key'	: this.#apiInput.value,
			'type'	: this.#type,
		}

		/* Set secret if dealing with v1 API */
		if( 'v1' == this.#type ) {
			body.secret = document.querySelector( '[name*=iqlrss_apiv1_secret]' ).value;
		}

		return await fetch( iqlrss.rest.settings, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': iqlrss.rest.nonce,
			},
			body: JSON.stringify( body ),
		} ).then( response => response.json() )
		.then( ( json ) => {

			/* Error- slidedown */
			if( ! json.success ) {
				if( 'v1' == this.#type ){ iqlrss.apiv1_verified = false; } else { iqlrss.api_verified = false };
				rowAddError( $apiRow, ( json.data.length && 'string' == typeof json.data ) ? json.data : iqlrss.text.error_rest_generic );
				return false;
			}

			/* Denote success and show fields - fadein */
			document.querySelectorAll( '[name*=iqlrss]' ).forEach( ( $elm ) => {

				const $row = $elm.closest( 'tr' );
				if( ! $row || 'none' != $row.style.display ) return;

				/* Skip the Return Lowest Label if related isn't checked */
				if( $elm.name.includes( 'global_adjustment' ) && '' == document.querySelector( 'select[name*=global_adjustment_type]' ).value ) {
					return;
				}

				/* Skip the Return Lowest Label if related isn't checked */
				if( $elm.name.includes( 'return_lowest_label' ) && ! document.querySelector( '[type=checkbox][name*=return_lowest]' ).checked ) {
					return;
				}

				/**
				 * @jquery
				 * Reinitialize selectWoo with carriers pulled async.
				 */
				if( $elm.name.includes( 'carriers' ) && ! $elm.querySelector( 'option:not([value=""])' ) && 'undefined' !== typeof jQuery ) {

					const selectwoo_args = jQuery( $elm ).data( 'select2' ).options.options || {};
					if( 'undefined' !== typeof jQuery.fn.selectWoo ) {
						fetch( iqlrss.rest.settings, {
							method: 'POST',
							headers: {
								'Content-Type'	: 'application/json',
								'X-WP-Nonce'	: iqlrss.rest.nonce,
							},
							body: JSON.stringify( {
								'action': 'get_carriers'
							} )
						} ).then( response => response.json() )
						.then( ( json ) => {

							if( ! json.success || ! 'carriers' in json.data ) return;

							$elm.innerHTML = '';
							Object.entries( json.data.carriers ).forEach( ( [k, v] ) => {
								let $option = document.createElement( 'option' );
								$option.value = k;
								$option.innerText = v;
								$elm.appendChild( $option );
							} );
							jQuery( $elm ).selectWoo( 'destroy' );
							jQuery( $elm ).selectWoo( selectwoo_args );
						} );
					}

				}

				rowMakeVisible( $row, true );
			} );

			/* Trigger the return lowest checkbox - this may display it's connected label input. */
			document.querySelector( '[type=checkbox][name*=return_lowest' ).dispatchEvent( new Event( 'change' ) );
			if( 'v1' == this.#type ){ iqlrss.apiv1_verified = true; } else { iqlrss.api_verified = true };
			return true;

		} );

	}


	/**
	 * Show / Hide the Verify API button depending if the
	 * input value exists or not.
	 */
	apiInputChange() {

		/* Initial animation */
		if( this.#apiInput.value && this.#button ) {
			this.#button.animate( { opacity: 1 }, 300 );
		}

		this.#apiInput.addEventListener( 'input', ( e ) => {

			if( ! this.#button ) return;

			if( e.target.value ) {
				this.#button.animate( { opacity: 1 }, { duration: 300, fill: 'forwards' } )
			} else {
				this.#button.animate( { opacity: 0 }, { duration: 300, fill: 'forwards' } );
				rowClearError( this.#apiInput.closest( 'tr' ) );
			}

		} );

	}


	/**
	 * Ensure that the user verifies their API Keys.
	 */
	verificationRequiredCheck() {

		if( ! this.#button ) return;

		const $settingsForm = document.getElementById( 'mainform' );
		const $apiRow 		= this.#apiInput.closest( 'tr' );

		$settingsForm.addEventListener( 'submit', ( e ) => {

			rowClearError( $apiRow );
			if( ! this.#apiInput.value ) {
				return true;
			}

			if( 'v1' == this.#type && iqlrss.apiv1_verified ) {
				return true
			} else if( 'v2' == this.#type && iqlrss.api_verified ) {
				return true;
			}

			e.preventDefault();
			e.stopImmediatePropagation();

			this.#button.animate( { opacity: 1 }, { duration: 300, fill: 'forwards' } )
			rowAddError( $apiRow, iqlrss.text.error_verification_required );

			const $wooSave = document.querySelector( '.woocommerce-save-button' );
			if( $wooSave && $wooSave.classList.contains( 'is-busy' ) ) {
				$wooSave.classList.remove( 'is-busy' );
			}

			return false;

		} );

	}

}


/**
 * Toggle row visibility
 *
 * @param {DOMObject} $row
 * @param {Boolean} visible
 */
function rowMakeVisible( $row, visible ) {

	if( visible ) {

		if( null !== $row.offsetParent ) return;

		$row.style = 'opacity:0';
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
		} ).onfinish = () => $row.style = 'display:none;';

	}

}


/**
 * Add settings row error
 * SlideDown
 *
 * @param {DOMObject} $row
 * @param {String} message
 */
function rowAddError( $row, message ) {

	let $err = document.createElement( 'p' );
		$err.classList.add( 'description', 'iqcss-err' );
		$err.innerText = message;

	$row.querySelector( 'fieldset' ).appendChild( $err );
	const errHeight = $err.getBoundingClientRect().height;
	$err.remove();

	$err.style = 'height:0px;opacity:0;overflow:hidden;';
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
function rowClearError( $row ) {

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