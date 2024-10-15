import PreviewButtonManager from '../../../../ppcp-button/resources/js/modules/Preview/PreviewButtonManager';
import GooglePayPreviewButton from './GooglePayPreviewButton';

/**
 * Manages all GooglePay preview buttons on this page.
 */
export default class GooglePayPreviewButtonManager extends PreviewButtonManager {
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
	 * @return {Promise<{}>}
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
	 * @return {GooglePayPreviewButton}
	 */
	createButtonInstance( wrapperId ) {
		return new GooglePayPreviewButton( {
			selector: wrapperId,
			apiConfig: this.apiConfig,
			methodName: this.methodName,
		} );
	}
}
