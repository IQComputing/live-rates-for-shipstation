import * as util from '../modules/utility.js';

/**
 * Manage the custom boxes functionality on Shipping Zones.
 *
 * @global {Object} iqlrss - Localized object of saved values.
 */
export class CustomBoxes {

    /**
     * Custom boxes and DOM elements.
     */
    #data = {
        modal: null,
        domRow: null,
        domList: null,
        editBox: -1,
    }


    /**
     * Setup the various functionality.
     */
    constructor() {

        this.#data.domRow = document.getElementById( 'customBoxesRow' );
        this.#data.domList = this.#data.domRow.querySelector( 'tbody' );
        this.#data.domItemClone = this.#data.domList.lastElementChild;

        this.setupListEvents();
        this.setupModalEvents();
        this.setupModalFieldEvents();

    }


    /**
     * Setup listeners for removing custom boxes and
     * setting box active status.
     */
    setupListEvents() {

        /* Remove Custom Boxes */
        document.getElementById( 'customBoxRemove' ).addEventListener( 'click', () => {

            const $checkedBoxes = this.#data.domList.querySelectorAll( 'tr td:first-child input:checked' );
            if( ! $checkedBoxes.length ) return;

            const confirm = iqlrss.text.confirm_box_removal.replace( '(x)', `(${$checkedBoxes.length})` );
            if( window.confirm( confirm ) ) {
                $checkedBoxes.forEach( ( $input ) => {
                    $input.closest( 'tr' ).remove();
                } );
            }
        } );

        /* Manage custom box active status. */
        this.#data.domList.addEventListener( 'change', ( e ) => {
            if( ! e.target.matches( 'input[name="box_active"]' ) ) return;

            const $jsonElm   = e.target.closest( 'tr' ).querySelector( 'input[name*="[json]"]' );
            const jsonString = $jsonElm.value;
            if( util.isEmpty( jsonString ) ) return;

            let json = JSON.parse( jsonString );
            if( ! util.isEmpty( json ) ) json.active = e.target.checked;
            $jsonElm.value = JSON.stringify( json );

        } );

    }


    /**
     * Manage custom box modals.
     *
     * Opens the modal and slots the data into it's related fields.
     * Closes the modal on [x] click or background click.
     */
    setupModalEvents() {

        const $modal = document.getElementById( 'customBoxesFormModal' );
        if( ! $modal ) {
            return;
        }


        /**
         * Dynamic open modal event.
         */
        this.#data.domRow.addEventListener( 'click', ( e ) => {

            if( ! ( 'iqlrssModal' in e.target.dataset ) ) return;
            e.preventDefault();
            util.loadModule( 'modal', e.target, { 'modal': e.target.dataset.iqlrssModal } );

        } );


        /**
         * Animate Modal opening.
         */
        $modal.addEventListener( 'modal-open', ( e ) => {

            /* Set modal every time it opens. */
            this.#data.modal = e.detail.modal;

            let data  = {};
            let index = -1;

            if( null !== e.detail.targetClicked.previousElementSibling && 'INPUT' == e.detail.targetClicked.previousElementSibling.tagName ) {
                data  = JSON.parse( e.detail.targetClicked.previousElementSibling.value );
                index = [ ...e.detail.targetClicked.closest( 'tbody' ).children ].indexOf( e.detail.targetClicked.closest( 'tr' ) );
            }

            /**
             * Set required fields and maybe data as well.
             */
            this.#data.editBox = index;
            $modal.querySelectorAll( '.iqlrss-field' ).forEach( ( $fieldWrap ) => {

                /* Set required attribute */
                const $input = $fieldWrap.querySelector( 'input' );
                if( $fieldWrap.classList.contains( '--required' ) ) {
                    $input.setAttribute( 'required', '' );
                }

                /* Associate data. */
                if( ! util.isEmpty( data ) ) {

                    const data_key = $input.name.replace( 'box_', '' );
                    if( data_key in data ) {
                        $input.value = data[ data_key ];
                    } else if( data_key.includes( 'inner' ) || [ 'length', 'width', 'height' ].includes( data_key ) ) {

                        if( data_key.includes( 'toggle' ) ) {
                            $input.checked = ( ! Object.values( data.inner ).every( v => 0 == v ) );
                            $input.dispatchEvent( new Event( 'change' ) );
                        } else {
                            $input.value = data[ ( data_key.includes( 'inner' ) ) ? 'inner' : 'outer' ][ ( data_key.includes( 'inner' ) ) ? data_key.replace( '_inner', '' ) : data_key ];
                            $input.value = ( '0' == $input.value ) ? '' : $input.value;
                        }

                    }
                }

            } );

            const $wpWrap = document.querySelector( '.wrap' );
            if( $wpWrap ) {
                const widthDiff = ( document.body.getBoundingClientRect().width - $wpWrap.querySelector( 'form' ).getBoundingClientRect().width );
                $modal.style.left  = ( widthDiff - 20 ) + 'px';
            }

            /* Animate the opening */
            $modal.animate( {
                opacity: [ 0, 1 ],
                transform: [ 'scale(0.85)', 'scale(1.05)', 'scale(1)' ],
            }, {
                duration: 300,
                easing: 'ease-in-out',
            } ).onfinish = () => $modal.querySelector( 'input' ).focus();

        } );


        /**
         * Prevent closing the modal...
         * IF the user may have modified the modal in some way (typing, changing, etc.).
         * AND the user clicks outside the modal. This does not apply to clicking the [x].
         */
        $modal.addEventListener( 'modal-close', ( e ) => {

            const Module = e.detail.modal;

            /* Set required fields. */
            $modal.querySelectorAll( '.iqlrss-field.--required' ).forEach( ( $fieldWrap ) => {
                $fieldWrap.querySelector( 'input' ).removeAttribute( 'required', '' );
                if( $fieldWrap.querySelector( '.iqlrss-errortext' ) ) $fieldWrap.querySelector( '.iqlrss-errortext' ).remove();
            } );

            /* Prevent close on confirm cancel. */
            if( Module.wasModified() && 'click-outer' == e.detail.context && ! window.confirm( iqlrss.text.confirm_modal_closure ) ) {
                return e.preventDefault();
            }

            this.modalReset();
            this.#data.editBox = -1;

        } );


        /**
         * Ensure modal required fields do not prevent main form from saving.
         */
        document.querySelector( 'button[name="save"]' ).addEventListener( 'click', function() {
            $modal.querySelectorAll( '[required]' ).forEach( $elm => $elm.setAttribute( 'disabled', 'disabled' ) );
        } );

    }


    /**
     * Setup Modal Field Events
     *
     * Handles field visiblity toggles.
     * Sets up the data save.
     */
    setupModalFieldEvents() {

        /**
         * @func
         * Toggle Field Visiblity
         *
         * @param {DOMObject} $elm
         * @param {Boolean} visible
         */
        const toggleFieldVisibility = ( $elm, visible ) => {

            if( visible ) {

                util.css( $elm, { 'display': 'block' } );
                $elm.animate( {
                    opacity: [ 0, 1 ],
                    transform: [ 'scale(0.85)', 'scale(1)' ],
                }, {
                    duration: 300,
                    easing: 'ease-in',
                } ).onfinish = () => ( ( 'enabledby' in $elm.dataset ) && $elm.classList.add( 'enabled' ) );

            } else {

                $elm.animate( {
                    opacity: [ 1, 0 ],
                    transform: [ 'scale(1)', 'scale(0.85)' ],
                }, {
                    duration: 300,
                    easing: 'ease-out',
                } ).onfinish = () => {

                    util.css( $elm, {} );
                    if( 'enabledby' in $elm.dataset ) $elm.classList.remove( 'enabled' );

                };

            }

        }

        document.querySelectorAll( '#customBoxesFormModal [class*="field-switch"] [type="checkbox"]' ).forEach( ( $checkbox ) => {
            $checkbox.addEventListener( 'change', ( e ) => {
                e.target.value = e.target.checked; /* Necessary for "easier" JSON mapping */
                document.querySelectorAll( `#customBoxesFormModal [data-enabledby="${e.target.id}"]` ).forEach( ( $field ) => {
                    toggleFieldVisibility( $field, e.target.checked )
                } );
            } );
        } );

        document.getElementById( 'saveCustomBox' ).addEventListener( 'click', ( e ) => this.processModalSave( e ) );

    }


    /**
     * Save the modal data.
     *
     * Invalidate fields.
     * Add / Update Custom Box line items.
     */
    processModalSave() {

        const $modal        = document.getElementById( 'customBoxesFormModal' );
        const box_index     = this.#data.editBox;
        const modalData     = new FormData();
        const errorColor    = '#d63638';
        const successColor  = '#248A3D';

        if( ! $modal.open ) return;


        /**
         * @func
         * Map fields to expected JSON format.
         *
         * @param {FormData} fd
         */
        const mapJson = ( fd ) => {
            return {
                nickname: fd.get( 'nickname' ),
                outer: {
                    length: fd.get( 'box_length' ),
                    width : fd.get( 'box_width' ),
                    height: fd.get( 'box_height' ),
                },
                inner: {
                    length: ( ! ['0','false'].includes( fd.get( 'box_inner_toggle' ) ) ) ? fd.get( 'box_length_inner' ) : '0',
                    width : ( ! ['0','false'].includes( fd.get( 'box_inner_toggle' ) ) ) ? fd.get( 'box_width_inner' )  : '0',
                    height: ( ! ['0','false'].includes( fd.get( 'box_inner_toggle' ) ) ) ? fd.get( 'box_height_inner' ) : '0',
                },
                weight: fd.get( 'box_weight' ),
                price: fd.get( 'box_price' ),
                weight_max: fd.get( 'box_weight_max' ),
                active: ( box_index >= 0 ) ? this.#data.domList.querySelector( `tr:nth-child(${box_index + 1}) td:last-child input` ).checked : 1
            };
        }


        /**
         * @func
         * Add/Update a Custom Box to/in the.
         *
         * @param {Object} box
         *
         * @return {Integer}
         */
        const manageCustomBox = ( box ) => {

            let $clone = this.#data.domItemClone.cloneNode( true );
                $clone.classList.remove( 'clone' );

            $clone.querySelector( '[name*="[json]"]' ).value = JSON.stringify( box );
            $clone.querySelector( '[name*="[json]"] + a' ).innerText = box.nickname;

            /* Dimensions */
            $clone.querySelector( '[data-assoc="box_dimensions"]' ).innerText = [
                box.outer.length,
                box.outer.width,
                box.outer.height,
            ].join( 'x' );

            /* Inner Dimensions */
            if( 'inner' in box && ! Object.values( box.inner ).every( v => 0 == v ) ) {
                $clone.querySelector( '[data-assoc="box_dimensions"]' ).innerText += ' (' + [
                    box.inner.length,
                    box.inner.width,
                    box.inner.height,
                ].join( 'x' ) + ')';
            }

            /* Price */
            if( box.price ) {
                $clone.querySelector( '[data-assoc="box_price"]' ).innerHTML = iqlrss.store.currency_symbol + Number( box.price ).toFixed( 2 );
            }

            /* Keep checkbox state */
            $clone.querySelector( '[name="box_active"]' ).checked = box.active;

            // Update
            if( box_index >= 0 ) {
                this.#data.domList.replaceChild( $clone, this.#data.domList.children[ box_index ] );
                return box_index;
            }

            // Prepend
            this.#data.domList.prepend( $clone );
            this.#data.domRow.dataset.count = this.#data.domList.children.length - 1; /* Minus the clone. */

        }


        /**
         * @func
         * Add error description to element.
         *
         * @param {DOMObject} $wrap
         */
        const invalidateField = ( $wrap ) => {

            if( $wrap.querySelector( '.iqlrss-errortext' ) ) return;

            let $error = util.createElement( 'p', {
                'class': 'description iqlrss-errortext',
            } );
            $error.innerText = iqlrss.text.error_field_required;
            $wrap.appendChild( $error );

            const errHeight = $error.getBoundingClientRect().height;

            $error.remove();
            util.css( $error, {
                'height'    : 0,
                'opacity'   : 0,
                'overflow'  : 'hidden',
            } );
            $wrap.appendChild( $error );

            $error.animate( {
                height: [ errHeight + 'px' ],
                opacity: [ 1 ]
            }, {
                duration: 300
            } ).onfinish = () => util.css( $error, {} )

        }


        /**
         * @func
         * Animate Modal border to denote error/success.
         *
         * @param {String} color
         */
        const modalLighting = ( color ) => {

            $modal.animate( { borderColor: [ color ], }, {
                duration: 1000,
                easing: 'ease-in-out',
                direction: 'alternate',
                iterations: 2
            } );

        }


        /* Clear any toasts */
        $modal.querySelectorAll( '.modal-toast' ).forEach( ( $t ) => this.modalToastRemove( $t ) );

        /* Validate Required Fields */
        $modal.querySelectorAll( '.iqlrss-field' ).forEach( ( $fieldWrap ) => {

            const $input = $fieldWrap.querySelector( 'input' );

            /* Skip - Field not active, clear their error texts. */
            if( ( 'enabledby' in $fieldWrap.dataset ) && ! $fieldWrap.classList.contains( 'enabled' ) ) {
                if( $fieldWrap.querySelector( '.iqlrss-errortext' ) ) $fieldWrap.querySelector( '.iqlrss-errortext' ).remove();
                return;

            /* Error - Required but empty. */
            } else if( $fieldWrap.classList.contains( '--required' ) && util.isEmpty( $input.value ) ) {
                return invalidateField( $fieldWrap );
            }

            if( $fieldWrap.querySelector( '.iqlrss-errortext' ) ) $fieldWrap.querySelector( '.iqlrss-errortext' ).remove();
            modalData.append( $input.name, $input.value );

        } );

        /* Return Early - Errors! */
        if( $modal.querySelector( '.iqlrss-errortext' ) ) {
            return modalLighting( errorColor );
        }

        /* Map known JSON fields */
        const jsonBox = mapJson( modalData );
        const box_string = JSON.stringify( jsonBox );

        /* Error - Something went wrong, denote it to User */
        if( util.isEmpty( jsonBox ) || ! ( 'nickname' in jsonBox ) || ! box_string.length ) {
            modalLighting( errorColor );
            return this.modalToast( 'error', iqlrss.text.error_custombox_json );
        }

        /* Success! */
        manageCustomBox( jsonBox );
        modalLighting( successColor );
        this.modalReset();

        /* Box added successfully! */
        if( -1 == box_index ) {
            $modal.querySelector( 'input' ).focus();
            return this.modalToast( 'success', iqlrss.text.success_custombox_added );
        }

        this.modalReset();
        $modal.close();

        this.#data.domList.children[ box_index ].animate( { backgroundColor: [ successColor + '40' ], }, {
            duration: 600,
            easing: 'ease-in-out',
            direction: 'alternate',
            iterations: 2
        } );

    }


    /**
     * Reset the modal fields to their default states.
     */
    modalReset() {

        const $modal = document.getElementById( 'customBoxesFormModal' );
        $modal.querySelectorAll( '.iqlrss-field' ).forEach( ( $fieldWrap ) => {

            let $field      = null;
            const $input    = $fieldWrap.querySelector( 'input' );
            const $select   = $fieldWrap.querySelector( 'select' );

            $field          = ( $input ) ? $input : $select;
            $field.value    = '';
            $field.checked  = false;

            if( $fieldWrap.classList.contains( 'enabled' ) ) {
                util.css( $fieldWrap, { display: 'none' } );
            }

            $field.dispatchEvent( new Event( 'input' ) );
            $field.dispatchEvent( new Event( 'change' ) );

        } );

        /* Clear any toasts */
        $modal.querySelectorAll( '.modal-toast' ).forEach( ( $t ) => this.modalToastRemove( $t ) );

        /* Reset Modal State */
        if( null !== this.#data.modal ) {
            this.#data.modal.wasModified( false );
        }

    }


    /**
     * Delicious Modal Toast.
     *
     * @param {String} type - error|success
     * @param {String} msg
     */
    modalToast( type, msg ) {

        const $modal = document.getElementById( 'customBoxesFormModal' );
        if( ! $modal.open ) return;

        let $toasty = util.createElement( 'div', {
            'class': [ 'modal-toast', 'toast-' + type ].join( ' ' ),
        } );
        $toasty.innerText = msg;

        /* Grab Toast Height */
        $modal.prepend( $toasty );
        const height = $toasty.getBoundingClientRect().height;
        $toasty.remove();

        /* Animate Toast In */
        $modal.prepend( $toasty );
        $toasty.animate( {
            height: [ 0, height + 'px' ],
            opacity: [ 0, 1 ],
            transform: [ 'translateY(10px)', 'translateY(0)' ],
        }, {
            duration: 300,
            easing: 'ease-in',
            fill: 'forwards',

        /* Set timer on Toast Display */
        } ).onfinish = () => {

            let $timer = util.createElement( 'div', {
                'class': 'modal-timer',
            } );

            $toasty.append( $timer );
            const $anim = $timer.animate( { width: '100%' }, {
                duration: 5000,
                fill: 'forwards',
            } );
            $anim.onfinish = () => this.modalToastRemove( $toasty );

            /* Pause Toast Timer on Mouse Enter */
            $toasty.addEventListener( 'mouseenter', () => $anim.pause() );
            $toasty.addEventListener( 'mouseleave', () => $anim.play() );

            /* Remove Toast on Click */
            $toasty.addEventListener( 'click', () => this.modalToastRemove( $toasty ) );

        }

    }


    /**
     * Removes a Modal Toast
     *
     * @param {DOMObject} $toast
     */
    modalToastRemove( $toast ) {

        $toast.animate( {
            opacity: 0,
            height: 0
        }, {
            duration: 300,
            easing: 'ease-out',
        } ).onfinish = () => $toast.remove();

    }

}