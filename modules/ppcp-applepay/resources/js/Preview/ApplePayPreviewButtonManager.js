import PreviewButtonManager from '../../../../ppcp-button/resources/js/modules/Preview/PreviewButtonManager';
import ApplePayPreviewButton from './ApplePayPreviewButton';

/**
 * Manages all Apple Pay preview buttons on this page.
 */
export default class ApplePayPreviewButtonManager extends PreviewButtonManager {
	constructor() {
		const args = {
			methodName: 'ApplePay',
			buttonConfig: window.wc_ppcp_applepay_admin,
		};

		super( args );
	}

	/**
	 * Responsible for fetching and returning the PayPal configuration object for this payment
	 * method.
	 *
	 * @param {{}} payPal - The PayPal SDK object provided by WidgetBuilder.
	 * @return {Promise<{}>}
	 */
	async fetchConfig( payPal ) {
		const apiMethod = payPal?.Applepay()?.config;

		if ( ! apiMethod ) {
			this.error(
				'configuration object cannot be retrieved from PayPal'
			);
			return {};
		}

		return await apiMethod();
	}

	/**
	 * This method is responsible for creating a new PreviewButton instance and returning it.
	 *
	 * @param {string} wrapperId - CSS ID of the wrapper element.
	 * @return {ApplePayPreviewButton}
	 */
	createButtonInstance( wrapperId ) {
		return new ApplePayPreviewButton( {
			selector: wrapperId,
			apiConfig: this.apiConfig,
			methodName: this.methodName,
		} );
	}
}
