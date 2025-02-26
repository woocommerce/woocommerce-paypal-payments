/**
 * Scroll to a specific element and highlight it
 *
 * @param {string}  elementId        - ID of the element to scroll to
 * @param {boolean} [highlight=true] - Whether to highlight the element
 * @return {Promise} - Resolves when scroll and highlight are complete
 */
export const scrollAndHighlight = ( elementId, highlight = true ) => {
	return new Promise( ( resolve ) => {
		const scrollTarget = document.getElementById( elementId );

		if ( scrollTarget ) {
			const navContainer = document.querySelector(
				'.ppcp-r-navigation-container'
			);
			const navHeight = navContainer ? navContainer.offsetHeight : 0;

			// Get the current scroll position and element's position relative to viewport
			const rect = scrollTarget.getBoundingClientRect();

			// Calculate the final position with offset
			const scrollPosition =
				rect.top + window.scrollY - ( navHeight + 55 );

			window.scrollTo( {
				top: scrollPosition,
				behavior: 'smooth',
			} );

			// Add highlight if requested
			if ( highlight ) {
				scrollTarget.classList.add( 'ppcp-highlight' );

				// Remove highlight after animation
				setTimeout( () => {
					scrollTarget.classList.remove( 'ppcp-highlight' );
				}, 2000 );
			}

			// Resolve after scroll animation
			setTimeout( resolve, 300 );
		} else {
			console.error(
				`Failed to scroll: Element with ID "${ elementId }" not found`
			);
			resolve();
		}
	} );
};
