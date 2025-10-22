export let ModuleMap = new Map();

/**
 * Returns an object property or default value.
 *
 * @param {Object} obj
 * @param {String} prop
 * @param {Mixed} ifnull
 *
 * @return {Mixed} obj[prop] | ifnull
 */
export function getProp( obj, prop, ifnull ) {

	if( prop.includes( '.' ) ) {

		[...prop.split( '.' )].forEach( ( p, i ) =>{
			obj = Object.hasOwn( obj, prop ) ? obj[ prop ] : obj;
		} );

	}

	return Object.hasOwn( obj, prop ) ? obj[ prop ] : ifnull;

}


/**
 * Test if a given value is empty.
 *
 * @param {Mixed} val
 *
 * @return {Boolean}
 */
export function notEmpty( val ) {

	if( 'undefined' === typeof val ) return false;
	if( 'object' === typeof val ) return Boolean( Object.keys( val ).length );

	return Boolean( ( val ) );

}


/**
 * All the necessary information to create an element.
 *
 * @param {String} tagname
 * @param {Object} atts - {
 * 	css|style: {Object} - CSS Specific key value pairs.
 *  appendTo: {DOMObject} - Append the created element to this element.
 * }
 *
 * @return {DOMObject} $elm
 */
export function createElement( tagname, atts ) {

	atts = atts ?? {}
	let $elm = document.createElement( tagname );

	if( atts ) {
		Object.keys( atts ).forEach( key => {

			if( ['css', 'style'].includes( key ) ) {
				return css( $elm, atts[key] );
			} else if( 'appendTo' == key ) {
				return atts[ key ].append( $elm );
			}

			$elm.setAttribute( key, atts[ key ] );
		} );
	}

	return $elm;

}


/**
 * Applies CSS to the element style tag.
 *
 * @param {DOMObject} $elm
 * @param {Object} atts
 *
 * @return {DOMObject} $elm
 */
export function css( $elm, atts ) {

	let style = '';
	Object.keys( atts ).forEach( key => {
		style += `${key}: ${atts[key]};`;
	} );

	$elm.style.cssText = style;

	return $elm;

}