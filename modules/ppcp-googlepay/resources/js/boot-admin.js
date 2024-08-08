import PreviewButtonManager from '../../../ppcp-button/resources/js/modules/Renderer/PreviewButtonManager';
import GooglePayPreviewButton from './GooglepayPreviewButton';

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
	if ( ! GooglePayPreviewButtonManager.instance ) {
		GooglePayPreviewButtonManager.instance =
			new GooglePayPreviewButtonManager();
	}

	return GooglePayPreviewButtonManager.instance;
};

/**
 * Manages all GooglePay preview buttons on this page.
 */
class GooglePayPreviewButtonManager extends PreviewButtonManager {
	constructor() {
		const args = {
			methodName: 'GooglePay',
			buttonConfig: window.wc_ppcp_googlepay_admin,
		};

		super( args );
	}

	/**
	 * Responsible for fetching and returning the PayPal configuration object for this payment
	 * method.
	 *
	 * @param {{}} payPal - The PayPal SDK object provided by WidgetBuilder.
	 * @return {Promise<{}>} Promise that resolves when API configuration is available.
	 */
	async fetchConfig( payPal ) {
		const apiMethod = payPal?.Googlepay()?.config;

		if ( ! apiMethod ) {
			this.error(
				'configuration object cannot be retrieved from PayPal'
			);
			return {};
		}

		try {
			return await apiMethod();
		} catch ( error ) {
			if ( error.message.includes( 'Not Eligible' ) ) {
				this.apiError = 'Not Eligible';
			}
			return null;
		}
	}

	/**
	 * This method is responsible for creating a new PreviewButton instance and returning it.
	 *
	 * @param {string} wrapperId - CSS ID of the wrapper element.
	 * @return {GooglePayPreviewButton} The new preview button instance.
	 */
	createButtonInstance( wrapperId ) {
		return new GooglePayPreviewButton( {
			selector: wrapperId,
			apiConfig: this.apiConfig,
		} );
	}
}

// Initialize the preview button manager.
buttonManager();
