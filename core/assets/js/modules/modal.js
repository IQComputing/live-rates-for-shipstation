/**
 * Manage a Modal
 */
export class modal {

    /**
     * Modal properties.
     *
     * @var Object
     */
    #data = {
        id: '',
        domBtn: null,
        domModal: null,
        domBtnClose: null,
        domContent: null,
        modified: false,
    };


    /**
	 * Setup events.
     *
     * @param {DOMObject} $btn - Element to trigger the Modal.
	 */
    constructor( $btn, args ) {

        if( args && ( 'modal' in args ) && document.getElementById( args.modal ) ) {

            this.#data.id = args.modal;
            this.#data.domModal = document.getElementById( args.modal );
            this.#data.domBtnClose = this.#data.domModal.querySelector( ':scope > button' );

        } else if( ( 'modal' in $btn.dataset ) && document.getElementById( $btn.dataset.modal ) ) {

            this.#data.id = $btn.dataset.modal;
            this.#data.domModal = document.getElementById( $btn.dataset.modal );
            this.#data.domBtnClose = this.#data.domModal.querySelector( ':scope > button' );

        } else {
            return;
        }

        this.#data.domBtn = $btn;
        this.#data.domBtn.addEventListener( 'click', () => this.open() );

        this.setupModalEvents();
        this.open();

    }


    /**
     * Setup modal events.
     *
     * @link https://stackoverflow.com/a/26984690/800452
     */
    setupModalEvents() {

        if( this.#data.domModal.classList.contains( 'ready' ) ) return;

        const $titleClassElm    = this.#data.domModal.querySelector( '[class*="-modal-title"]' );
        const titleClass        = ( $titleClassElm ) ? [ ...$titleClassElm.classList ].filter( ( cn ) => cn.includes( '-modal-title' ) ) : '';
        const $contentClassElm  = this.#data.domModal.querySelector( '[class*="-modal-content"]' );
        const contentClass      = ( $contentClassElm ) ? [ ...$contentClassElm.classList ].filter( ( cn ) => cn.includes( '-modal-content' ) ) : '';

        /* Track if the modal was "changed" to try and prevent accidental closures */
        this.#data.domModal.addEventListener( 'change', () => {
            if( ! this.#data.domModal.open ) return;
            this.#data.modified = true;
        } );
        this.#data.domModal.addEventListener( 'input', () => {
            if( ! this.#data.domModal.open ) return;
            this.#data.modified = true
        } );

        /* Close when [x] is clicked. */
        this.#data.domBtnClose.addEventListener( 'click', () => this.close() );

        /* Close when the user clicks outside the main modal window. */
        this.#data.domModal.addEventListener( 'pointerdown', ( e ) => {

            const rect = this.#data.domModal.getBoundingClientRect();
            const isInDialog = ( rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width );

            /* Return Early - In Dialog or Right Click */
            if( isInDialog || 2 == e.button ) return;

            /* Return Early - Might be close button click */
            if( e.target.closest( 'button' ) === this.#data.domBtnClose ) return;

            /* Maybe Return Early - If the closest click is within the modal content. */
            if( contentClass && e.target.closest( '.' + contentClass ) ) return;

            /* Maybe Return Early - If the title is clicked - The title can sometimes be themed outside the modal window. */
            if( titleClass && e.target.closest( '.' + titleClass ) ) return;

            /* Ok, now let's close the modal. */
            this.close( { 'context': 'click-outer' } );

        } );

        this.#data.domModal.classList.add( 'ready' );
        this.dispatch( 'modal-ready' );

    }


    /**
     * Open the Modal.
     */
    open() {

        if( this.#data.domModal.open ) return;

        const EventResults = this.dispatch( 'modal-open', {
            'targetClicked': this.#data.domBtn,
        } );

        if( ! EventResults.defaultPrevented ) {
            this.#data.domModal.showModal();
        }

    }


    /**
     * Close the Modal.
     *
     * @param {Object} detail
     */
    close( detail ) {

        if( ! this.#data.domModal.open ) return;

        detail = detail || {};
        const EventResults = this.dispatch( 'modal-close', {
            ...{ 'context': 'click-close' },
            ...detail
        } );

        if( ! EventResults.defaultPrevented ) {
            this.#data.domModal.close();
        }

    }


    /**
	 * Dispatch an event.
	 *
	 * @param {String} event
	 * @param {Object} detail
	 * @param {options} detail
	 */
	dispatch( e, detail, options ) {

        detail  = detail || {};
        detail  = {
            ...detail,
            ...{ 'modal': this }
        };

        options = options || {};
        options = { ...{
            bubbles     : true,
            cancelable  : true,
        }, ...options, ...{
            detail: detail,
        } };

        const Event = new CustomEvent( e, options );
		this.#data.domModal.dispatchEvent( Event );
        return Event;

	}


    /**
     * Return a boolean to denote if the modal was probably modified.
     *
     * @param {Boolean} was - modified?
     *
     * @return {Boolean} this.#data.modified
     */
    wasModified( was ) {
        if( typeof was === 'undefined' ) return this.#data.modified;
        this.#data.modified = Boolean( was );
    }

}