// Tab panel IDs
export const TAB_IDS = {
	OVERVIEW: 'tab-panel-0-overview',
	PAYMENT_METHODS: 'tab-panel-0-payment-methods',
	SETTINGS: 'tab-panel-0-settings',
	STYLING: 'tab-panel-0-styling',
	PAY_LATER_MESSAGING: 'tab-panel-0-pay-later-messaging',
};

/**
 * Select a tab by simulating a click event and scroll to specified element,
 * accounting for navigation container height
 *
 * TODO: Once the TabPanel gets migrated to Tabs (TabPanel v2) we need to remove this in favor of programmatic tab switching: https://github.com/WordPress/gutenberg/issues/52997
 *
 * @param {string} tabId        - The ID of the tab to select
 * @param {string} [scrollToId] - Optional ID of the element to scroll to
 * @return {Promise}           - Resolves when tab switch and scroll are complete
 */
export const selectTab = ( tabId, scrollToId ) => {
	return new Promise( ( resolve ) => {
		const tab = document.getElementById( tabId );
		if ( tab ) {
			tab.click();
			setTimeout( () => {
				const scrollTarget = scrollToId
					? document.getElementById( scrollToId )
					: document.getElementById( 'ppcp-settings-container' );

				if ( scrollTarget ) {
					const navContainer = document.querySelector(
						'.ppcp-r-navigation-container'
					);
					const navHeight = navContainer
						? navContainer.offsetHeight
						: 0;

					// Get the current scroll position and element's position relative to viewport
					const rect = scrollTarget.getBoundingClientRect();

					// Calculate the final position with offset
					const scrollPosition =
						rect.top + window.scrollY - ( navHeight + 55 );

					window.scrollTo( {
						top: scrollPosition,
						behavior: 'smooth',
					} );

					// Resolve after scroll animation
					setTimeout( resolve, 300 );
				} else {
					console.error(
						`Failed to scroll: Element with ID "${
							scrollToId || 'ppcp-settings-container'
						}" not found`
					);
					resolve();
				}
			}, 100 );
		} else {
			console.error(
				`Failed to select tab: Tab with ID "${ tabId }" not found`
			);
			resolve();
		}
	} );
};
