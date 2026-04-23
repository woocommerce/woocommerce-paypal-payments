/**
 * Waits for a DOM element using setTimeout polling
 * @param {string} selector - CSS selector for the element
 * @param {number} timeout  - Maximum time to wait in milliseconds (default: 3000)
 * @param {number} interval - Polling interval in milliseconds (default: 100)
 * @return {Promise<Element>} - Resolves with the element or rejects if timeout
 */
export function waitForElement( selector, timeout = 3000, interval = 100 ) {
	return new Promise( ( resolve, reject ) => {
		const timeoutId = setTimeout( () => {
			clearInterval( intervalId );
			reject( `Element "${ selector }" not found within ${ timeout }ms` );
		}, timeout );

		const element = document.querySelector( selector );
		if ( element ) {
			clearTimeout( timeoutId );
			resolve( element );
			return;
		}

		const intervalId = setInterval( () => {
			const el = document.querySelector( selector );

			if ( el ) {
				clearTimeout( timeoutId );
				clearInterval( intervalId );
				resolve( el );
			}
		}, interval );
	} );
}
