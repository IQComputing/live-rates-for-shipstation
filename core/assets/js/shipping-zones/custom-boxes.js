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
        domList: null
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

        this.#data.domList = document.getElementById( 'iqlrssCustomBoxes' );
        this.#data.domItemClone = document.getElementById( 'iqlrssCustomBoxes' ).querySelector( 'clone' );

        if( this.#data.domList.children ) {
            [...this.#data.domList.children].forEach( ( $boxItem ) => {
                const json = $boxItem.querySelector( '[type="hidden"]' ).value;
                if( ! json ) return;
                this.#data.boxes.push( json );
            } );
        }

    }


    /**
     * Add a Custom Box to the list.
     *
     * @param {Object} box
     *
     * @return {Integer}
     */
    addCustomBox( box ) {

        const boxString = JSON.stringify( box );

    }



    /**------------------------------------------------------------------------------------------------ **/
	/** :: Modals :: **/
	/**------------------------------------------------------------------------------------------------ **/
    /**
     * Manage custom box modals.
     */
    modals() {

        const $modals = document.querySelectorAll( '[data-iqlrss-modal]' );
        if( ! $modals ) return;

        $modals.forEach( ( $btn ) => {

            /**
             * Setup Modal.
             */
            $btn.addEventListener( 'click', ( e ) => {

                e.preventDefault();

                util.loadModule( 'modal', e.target, {
                    'modal': e.target.dataset.iqlrssModal,
                } ).then( ( Modal ) => {
                    $btn.modal = Modal;
                } );

            } );


            /**
             * Animate Modal opening.
             */
            $btn.addEventListener( 'modal-open', ( e ) => {

                const Module = e.detail.modal;

                Module.domModal().animate( {
                    opacity: [ 0, 1 ],
                    transform: [ 'scale(0.85)', 'scale(1.05)', 'scale(1)' ],
                }, {
                    duration: 300,
                    easing: 'ease-in-out',
                } );

                Module.domModal().querySelector( 'input:first-of-type' ).focus();

            } );


            /**
             * Prevent closing the modal...
             * IF the user may have modified the modal in some way (typing, changing, etc.).
             * AND the user clicks outside the modal. This does not apply to clicking the [x].
             */
            $btn.addEventListener( 'modal-close', ( e ) => {

                const Module = e.detail.modal;
                if( ! Module.wasModified() ) return;

                /* Prevent close on confirm cancel. */
                if( 'click-outer' == e.detail.context && ! window.confirm( iqlrss.text.confirm_modal_closure ) ) e.preventDefault();

            } );

        } );

        this.setupModalFieldEvents();

    }


    /**
     * Setup Modal Field Events
     * Specifically toggle displays.
     */
    setupModalFieldEvents() {

        document.querySelectorAll( '#customBoxesFormModal [class*="field-switch"] [type="checkbox"]' ).forEach( ( $checkbox ) => {
            $checkbox.addEventListener( 'change', ( e ) => {
                e.target.value = e.target.checked;
                document.querySelectorAll( `#customBoxesFormModal [data-enabledby="${e.target.id}"]` ).forEach( ( $field ) => {
                    this.toggleFieldVisibility( $field, e.target.checked )
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

        const errorColor = '#d63638';


        /**
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
         * Animate Modal border to denote error/success.
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

            let $input = $fieldWrap.querySelector( 'input' );

            /* Skip fields that are not active. */
            if( 'enabledby' in $fieldWrap.dataset && ! $fieldWrap.classList.contains( 'enabled' ) ) return;

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
        let boxString   = ( jsonBox.length ) ? JSON.stringify( jsonBox ) : '';

        /* Error - Something went wrong, denote it to User */
        if( ! jsonBox.length || ! nickname in jsonBox || ! boxString.length ) {
            modalLighting( errorColor );
            return this.modalToast( 'error', iqlrss.text.error_custombox_json );
        }

        if( ! jsonBox.custombox_id && this.addCustomBox( jsonBox ) ) {
            modalLighting( successColor );
            return this.modalToast( 'success', iqlrss.text.success_custombox_added );
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
            opacity: [0, 1],
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
            transform: 'translateY( 10px )'
        }, {
            duration: 300,
            easing: 'ease-out',
        } ).onfinish = () => $toast.remove();

    }


    /**
     * Toggle Field Visiblity
     *
     * @param {DOMObject} $elm
     * @param {Boolean} visible
     */
    toggleFieldVisibility( $elm, visible ) {

        if( visible ) {

            util.css( $elm, { 'display': 'block' } );
            $elm.animate( {
                opacity: [ 0, 1 ],
                transform: [ 'scale(0.85)', 'scale(1)' ],
            }, {
                duration: 300,
                easing: 'ease-in',
            } ).onfinish = () => 'enabledby' in $elm.dataset && $elm.classList.add( 'enabled' );

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

}