import { scrollAndHighlight } from '../utils/scrollAndHighlight';

/**
 * Navigate to the WooCommerce "Payments" settings tab, i.e. exit the settings app.
 */
const goToWooCommercePaymentsTab = () => {
	window.location.href = window.ppcpSettings.wcPaymentsTabUrl;
};

/**
 * Navigate to the main settings page, or to a defined tab (panel).
 * Always initiates a browser navigation - if the user already is on the defined settings page,
 * this function acts as a page-reload.
 *
 * @param {?string} [panel=null] Which settings tab to display.
 */
const goToPluginSettings = ( panel = null ) => {
	let url = window.ppcpSettings.pluginSettingsUrl;

	if ( panel ) {
		url += '&panel=' + panel;
	}

	window.location.href = url;
};

/**
 * Check URL for highlight parameter and scroll to the element if present.
 *
 * @return {boolean} Whether a highlight parameter was found and processed
 */
const handleHighlightFromUrl = () => {
	const urlParams = new URLSearchParams( window.location.search );
	const elementId = urlParams.get( 'highlight' );

	if ( elementId ) {
		setTimeout( () => {
			scrollAndHighlight( elementId );

			// Clean up the URL by removing the highlight parameter.
			urlParams.delete( 'highlight' );
			const newUrl =
				window.location.pathname +
				( urlParams.toString() ? '?' + urlParams.toString() : '' ) +
				window.location.hash;

			window.history.replaceState( {}, document.title, newUrl );
		}, 100 );
		return true;
	}

	return false;
};

export const useNavigation = () => {
	return {
		goToWooCommercePaymentsTab,
		goToPluginSettings,
		handleHighlightFromUrl,
	};
};
