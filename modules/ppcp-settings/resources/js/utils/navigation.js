import { addQueryArgs } from '@wordpress/url';

const getLocation = () => window.location;

const pushHistory = ( path ) => window.history.pushState( { path }, '', path );

/**
 * Get the current path from the browser.
 *
 * @return {string} Current path.
 */
export const getPath = () => getLocation().pathname;

/**
 * Get the current query string, parsed into an object, from history.
 *
 * @return {Object} Current query object, defaults to empty object.
 */
export const getQuery = () =>
	Object.fromEntries( new URLSearchParams( getLocation().search ) );

/**
 * Updates the query parameters of the current page.
 *
 * @param {Object}  query           Object of params to be updated.
 * @param {boolean} [replace=false] Whether to add the query vars (false) or replace previous query vars with the new details (true).
 * @throws {TypeError} If the query is not an object.
 */
export const updateQueryString = ( query, replace = false ) => {
	const newQuery = replace ? query : { ...getQuery(), ...query };
	return pushHistory( getNewPath( newQuery ) );
};

/**
 * Return a URL with set query parameters.
 *
 * @param {Object} query    Object of params to be updated.
 * @param {string} basePath Optional. Define the path for the new URL.
 * @return {string} Updated URL merging query params into existing params.
 */
export const getNewPath = ( query, basePath = getPath() ) =>
	addQueryArgs( basePath, query );

/**
 * Filter an object to only include specified keys.
 *
 * @param {Object}   obj         The object to filter.
 * @param {string[]} allowedKeys An array of allowed key names.
 * @return {Object} A new object with only the allowed keys.
 */
export const filterObjectKeys = ( obj, allowedKeys ) => {
	return Object.keys( obj ).reduce( ( acc, key ) => {
		if ( allowedKeys.includes( key ) ) {
			acc[ key ] = obj[ key ];
		}
		return acc;
	}, {} );
};

/**
 * Clean the browser URL by removing unsupported query parameters.
 *
 * @param {string[]} supportedArgs An array of supported query parameter names.
 * @return {boolean} Returns true if the URL was modified (cleaned), false if nothing changed.
 */
export const cleanUrlQueryParams = ( supportedArgs ) => {
	const currentQuery = getQuery();
	const cleanedQuery = filterObjectKeys( currentQuery, supportedArgs );

	const isUrlClean =
		Object.keys( cleanedQuery ).length ===
		Object.keys( currentQuery ).length;

	if ( isUrlClean ) {
		return false;
	}

	updateQueryString( cleanedQuery, true );
	return true;
};
