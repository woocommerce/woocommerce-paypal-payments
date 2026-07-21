// Tab panel IDs
export const TAB_IDS = {
	OVERVIEW: 'tab-panel-0-overview',
	PAYMENT_METHODS: 'tab-panel-0-payment-methods',
	SETTINGS: 'tab-panel-0-settings',
	STYLING: 'tab-panel-0-styling',
	PAY_LATER_MESSAGING: 'tab-panel-0-pay-later-messaging',
};

import { useCallback } from '@wordpress/element';
import { useScrollTo } from '@ppcp-settings/hooks/useScrollHighlight';

/**
 * Select a tab by simulating a click event and scroll to specified element,
 * accounting for navigation container height
 *
 * TODO: Once the TabPanel gets migrated to Tabs (TabPanel v2) we need to remove this in favor of programmatic tab switching: https://github.com/WordPress/gutenberg/issues/52997
 *
 * @return {Function} selectTab(tabId, scrollToId?, highlight?) function
 */
export const useSelectTab = () => {
	const scrollTo = useScrollTo();

	return useCallback(
		( tabId, scrollToId, highlight = false ) => {
			return new Promise( ( resolve ) => {
				const tab = document.getElementById( tabId );
				if ( tab ) {
					tab.click();
					setTimeout( () => {
						const targetId =
							scrollToId || 'ppcp-settings-container';
						scrollTo( targetId, highlight ).then( resolve );
					}, 100 );
				} else {
					resolve();
				}
			} );
		},
		[ scrollTo ]
	);
};
