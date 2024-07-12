/**
 * @param  str
 * @return {string}
 */
export const toSnakeCase = ( str ) => {
	return str
		.replace( /[\w]([A-Z])/g, function ( m ) {
			return m[ 0 ] + '_' + m[ 1 ];
		} )
		.toLowerCase();
};

/**
 * @param  obj
 * @return {{}}
 */
export const convertKeysToSnakeCase = ( obj ) => {
	const newObj = {};
	Object.keys( obj ).forEach( ( key ) => {
		const newKey = toSnakeCase( key );
		newObj[ newKey ] = obj[ key ];
	} );
	return newObj;
};
