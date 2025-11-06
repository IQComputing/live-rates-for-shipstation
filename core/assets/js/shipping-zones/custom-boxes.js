import * as util from '../modules/utility.js';

/**
 * Manage the custom boxes functionality on Shipping Zones.
 * :: Custom Box Management
 * :: Modals
 *
 * @global {Object} iqlrss - Localized object of saved values.
 */
export class CustomBoxes {

    /**
     * Custom boxes and DOM elements.
     */
    #data = {
        boxes: [],
        modal: null,
        domRow: null,
        domList: null,
    }


    /**
     * Setup the various functionality.
     */
    constructor() {

        this.setupCustomBoxes();
        this.setupModalEvents();
        this.setupModalFieldEvents();

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Custom Box Management :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Make note of the current set of Custom Boxes.
     */
    setupCustomBoxes() {

        this.#data.domRow = document.getElementById( 'customBoxesRow' );
        this.#data.domList = document.getElementById( 'iqlrssCustomBoxes' );
        this.#data.domItemClone = this.#data.domList.querySelector( '.clone' );

        if( this.#data.domList.children ) {
            [...this.#data.domList.children].forEach( ( $boxItem ) => {
                const json = $boxItem.querySelector( '[type="hidden"]' ).value;
                if( ! json ) return;
                this.#data.boxes.push( json );
            } );
        }

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Modals :: **/
	/**------------------------------------------------------------------------------------------------ **/
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

            let data = {};
            if( null !== e.detail.targetClicked.previousElementSibling && 'INPUT' == e.detail.targetClicked.previousElementSibling.tagName ) {
                data = JSON.parse( e.detail.targetClicked.previousElementSibling.value );
            }

            /**
             * Set required fields and maybe data as well.
             */
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
                            $input.checked = ( ! util.isEmpty( data.inner.length ) );
                            $input.dispatchEvent( new Event( 'change' ) );
                        } else {
                            $input.value = data[ ( data_key.includes( 'inner' ) ) ? 'inner' : 'outer' ][ ( data_key.includes( 'inner' ) ) ? data_key.replace( '_inner', '' ) : data_key ];
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
            } );

            /* Prevent close on confirm cancel. */
            if( Module.wasModified() && 'click-outer' == e.detail.context && ! window.confirm( iqlrss.text.confirm_modal_closure ) ) {
                return e.preventDefault();
            }

            this.modalReset();

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
                e.target.value = e.target.checked;
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

        let data     = new FormData();
        const $modal = document.getElementById( 'customBoxesFormModal' );
        if( ! $modal.open ) return;

        const errorColor    = '#d63638';
        const successColor  = '#248A3D';


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


        /**
         * @func
         * Add a Custom Box to the list.
         *
         * @param {Object} box
         *
         * @return {Integer}
         */
        const addCustomBox = ( box ) => {

            box.id = ( 'id' in box ) ? box.id : -1;
            if( -1 == box.id ) {
                box.id = ( this.#data.boxes.length ) ? Math.max( ...this.#data.boxes.map( obj => obj.id ) ) + 1 : 1;
            }

            const box_string = JSON.stringify( box );
            let $clone = this.#data.domItemClone.cloneNode( true );
                $clone.classList.remove( 'clone' );
                $clone.innerHTML = $clone.innerHTML.replaceAll( '[-1]', `[${box.id}]` );

            $clone.querySelector( '[name*="[json]"]' ).value = box_string;
            $clone.querySelector( '[name*="[json]"] + a' ).innerText = box.nickname;

            /* Dimensions */
            $clone.querySelector( '[data-assoc="box_dimensions"]' ).innerText = [
                box.box_length ?? 0,
                box.box_width ?? 0,
                box.box_height ?? 0,
            ].join( 'x' );

            /* Inner Dimensions */
            if( box.box_inner_length ) {
                $clone.querySelector( '[data-assoc="box_dimensions"]' ).innerText += ' (' + [
                    box.box_inner_length ?? 0,
                    box.box_width ?? 0,
                    box.box_height ?? 0,
                ].join( 'x' ) + ')';
            }

            /* Price */
            if( box.box_price ) {
                $clone.querySelector( '[data-assoc="box_price"]' ).innerHTML = iqlrss.store.currency_symbol + Number( box.box_price ).toFixed( 2 );
            }

            $clone.querySelector( '[name="box_active"]' ).checked = true;

            this.#data.boxes.push( box );
            this.#data.domList.appendChild( $clone );
            this.#data.domRow.dataset.count = this.#data.domList.children.length - 1; /* Minus the clone. */

            return box.id;

        }


        /* Clear any toasts */
        $modal.querySelectorAll( '.modal-toast' ).forEach( ( $t ) => this.modalToastRemove( $t ) );

        /* Validate Required Fields */
        $modal.querySelectorAll( '.iqlrss-field' ).forEach( ( $fieldWrap ) => {

            const $input = $fieldWrap.querySelector( 'input' );

            /* Skip fields that are not active. */
            if( ( 'enabledby' in $fieldWrap.dataset ) && ! $fieldWrap.classList.contains( 'enabled' ) ) return;

            if( $fieldWrap.classList.contains( '--required' ) && util.isEmpty( $input.value ) ) {
                return invalidateField( $fieldWrap );
            } else if( $fieldWrap.querySelector( '.iqlrss-errortext' ) ) {
                $fieldWrap.querySelector( '.iqlrss-errortext' ).remove();
            }

            data.append( $input.name, $input.value );

        } );

        /* Return Early - Errors! */
        if( $modal.querySelector( '.iqlrss-errortext' ) ) {
            return modalLighting( errorColor );
        }

        let jsonBox     = util.formDataToJSON( data );
        let box_string  = ( ! util.isEmpty( jsonBox ) ) ? JSON.stringify( jsonBox ) : '';

        /* Error - Something went wrong, denote it to User */
        if( util.isEmpty( jsonBox ) || ! ( 'nickname' in jsonBox ) || ! box_string.length ) {
            modalLighting( errorColor );
            return this.modalToast( 'error', iqlrss.text.error_custombox_json );
        }

        if( ! ( 'id' in jsonBox ) && addCustomBox( jsonBox ) ) {
            modalLighting( successColor );
            this.modalReset();
            $modal.querySelector( 'input' ).focus();
            return this.modalToast( 'success', iqlrss.text.success_custombox_added );
        }

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