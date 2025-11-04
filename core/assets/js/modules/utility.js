const ModuleMap = new Map();


/**
 * Return a module for a given element
 * Loads the module if it doesn't exist yet.
 *
 * @param {String} slug 	- Module slug to load.
 * @param {DOMObject} $elm 	- The element associated with the module.
 * @param {Object} pargs 	- Arguments to pass to the module.
 *
 * @return {Object} Module - The module object.
 */
export function loadModule( slug, $elm, pargs ) {

	let Modules = ModuleMap.get( slug );
	if( Modules ) {
		let obj = Modules.get( $elm );
		if( obj ) return Promise.resolve( obj );
	}

	return import( `./${slug}.js` ).then( ( Module ) => {

		const margs = { ...$elm.dataset, ...pargs };
		const obj = new Module[ slug ]( $elm, margs );

		Modules = Modules || new WeakMap();
		Modules.set( $elm, obj );
		ModuleMap.set( slug, Modules );

		return obj

	} );

}


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
export function isEmpty( val ) {

	if( 'undefined' === typeof val || null === val ) return true;
	if( 'object' === typeof val ) return ! Boolean( Object.keys( val ).length );

	return ! Boolean( ( val ) );

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

	atts = atts || {}
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


/**
 * Convert a FormData object to JSON.
 *
 * @link https://stackoverflow.com/a/46774073/800452
 *
 * @param {FormData} formData
 */
export function formDataToJSON( formData ) {

	let json = {};
	formData.forEach( ( v, k ) => {

		if( ! Reflect.has( json, k ) ) { return json[ k ] = v;}
		if( ! Array.isArray( json[ k ] ) ) json[ k ] = [ json[ k ] ];
		json[ k ].push( v );

	} );

	return json;

}