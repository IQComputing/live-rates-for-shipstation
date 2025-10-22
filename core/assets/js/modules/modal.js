/**
 * Manage a Modal
 */
export class Modal {

    /**
     * Modal properties.
     *
     * @var Object
     */
    #data = {
        id: 'iqcModalWindow',
        active: 0,
        domBtn: null,
        domModal: null,
        domContent: null,
    };


    /**
	 * Setup events.
     *
     * @param DOMObject $elm - Element to trigger the Modal.
	 */
    constructor( $btn ) {

        if( ! $btn ) return;

        this.#data.domBtn = $btn;
        if( ! this.#data.domBtn || ! this.#data.domBtn.length ) {
            this.#data.domBtn.addButtonListener( 'click', () => this.open() );
        }

    }


    /**
     * Open the Modal.
     */
    open() {

        const $modelWrapper = this.setup();

    }


    /**
     * Setup Modal options.
     */
    setup() {

        let $modelWrapper = document.getElementById( '#' + this.#data.id );
        if( ! $modelWrapper ) {

            

        }

    }

}