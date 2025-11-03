import * as util from '../modules/utility.js';

/**
 * Manage the custom boxes functionality on Shipping Zones.
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
        domRow: null,
        domList: null,
    }


    /**
     * Setup the various functionality.
     */
    constructor() {

        this.setupCustomBoxes();
        this.modals();

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
     */
    modals() {

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

            const Module    = e.detail.modal;
            const $wpWrap   = document.querySelector( '.wrap' );

            if( $wpWrap ) {
                const widthDiff = ( document.body.getBoundingClientRect().width - $wpWrap.querySelector( 'form' ).getBoundingClientRect().width );
                Module.domModal().style.left  = ( widthDiff - 20 ) + 'px';
            }

            Module.domModal().animate( {
                opacity: [ 0, 1 ],
                transform: [ 'scale(0.85)', 'scale(1.05)', 'scale(1)' ],
            }, {
                duration: 300,
                easing: 'ease-in-out',
            } ).onfinish = () => Module.domModal().querySelector( 'input' ).focus();

        } );


        /**
         * Prevent closing the modal...
         * IF the user may have modified the modal in some way (typing, changing, etc.).
         * AND the user clicks outside the modal. This does not apply to clicking the [x].
         */
        $modal.addEventListener( 'modal-close', ( e ) => {

            const Module = e.detail.modal;
            if( ! Module.wasModified() ) return;

            /* Prevent close on confirm cancel. */
            if( 'click-outer' == e.detail.context && ! window.confirm( iqlrss.text.confirm_modal_closure ) ) return e.preventDefault();

        } );


        /**
         * Clear form elements whenever form closes / is reset.
         */
        $modal.addEventListener( 'modal-reset', ( e ) => this.modalReset() );


        /**
         * Setup the events for fields within the modal itself.
         */
        this.setupModalFieldEvents();

    }


    /**
     * Setup Modal Field Events
     * Specifically toggle displays.
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
     * This may create a Custom Box or save the data to an existing
     * depending on the context ofc.
     *
     * @param {Event} e
     */
    processModalSave( e ) {

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

            this.#data.boxes.push( box );
            this.#data.domList.appendChild( $clone );
            this.#data.domRow.dataset.count = this.#data.domList.children.length - 1; // Minus the clone.

            return box.id;

        }


        /* Clear any toasts */
        $modal.querySelectorAll( '.modal-toast' ).forEach( ( $t ) => this.modalToastRemove( $t ) );

        /* Validate Required Fields */
        $modal.querySelectorAll( '.iqlrss-field' ).forEach( ( $fieldWrap ) => {

            let $field = null;

            const $input = $fieldWrap.querySelector( 'input' );
            const $select = $fieldWrap.querySelector( 'select' );

            $field = ( $input ) ? $input : $select;

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
            return this.modalToast( 'success', iqlrss.text.success_custombox_added );
        }

    }


    /**
     * Reset the modal fields to their default states.
     */
    modalReset( e ) {

        const $modal = document.getElementById( 'customBoxesFormModal' );
        $modal.querySelectorAll( '.iqlrss-field' ).forEach( ( $fieldWrap ) => {

            let $field      = null;
            const $input    = $fieldWrap.querySelector( 'input' );
            const $select   = $fieldWrap.querySelector( 'select' );
            $field          = ( $input ) ? $input : $select;

            $field.value = '';
            $field.dispatchEvent( new Event( 'input' ) );
            $field.dispatchEvent( new Event( 'change' ) );

        } );

        /* Clear any toasts */
        $modal.querySelectorAll( '.modal-toast' ).forEach( ( $t ) => this.modalToastRemove( $t ) );

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