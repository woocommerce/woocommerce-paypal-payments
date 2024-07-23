export const toCamelCase = ( str ) => {
	return str.replace( /([-_]\w)/g, function ( match ) {
		return match[ 1 ].toUpperCase();
	} );
};

export const keysToCamelCase = ( obj ) => {
	const output = {};
	for ( const key in obj ) {
		if ( Object.prototype.hasOwnProperty.call( obj, key ) ) {
			output[ toCamelCase( key ) ] = obj[ key ];
		}
	}
	return output;
};

export const strAddWord = ( str, word, separator = ',' ) => {
	const arr = str.split( separator );
	if ( ! arr.includes( word ) ) {
		arr.push( word );
	}
	return arr.join( separator );
};

export const strRemoveWord = ( str, word, separator = ',' ) => {
	const arr = str.split( separator );
	const index = arr.indexOf( word );
	if ( index !== -1 ) {
		arr.splice( index, 1 );
	}
	return arr.join( separator );
};

export const throttle = ( func, limit ) => {
	let inThrottle, lastArgs, lastContext;

	function execute() {
		inThrottle = true;
		func.apply( this, arguments );
		setTimeout( () => {
			inThrottle = false;
			if ( lastArgs ) {
				const nextArgs = lastArgs;
				const nextContext = lastContext;
				lastArgs = lastContext = null;
				execute.apply( nextContext, nextArgs );
			}
		}, limit );
	}

	return function () {
		if ( ! inThrottle ) {
			execute.apply( this, arguments );
		} else {
			lastArgs = arguments;
			lastContext = this;
		}
	};
};

const Utils = {
	toCamelCase,
	keysToCamelCase,
	strAddWord,
	strRemoveWord,
	throttle,
};

export default Utils;
