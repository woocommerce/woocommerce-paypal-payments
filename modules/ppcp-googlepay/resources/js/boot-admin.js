import GooglepayButton from './GooglepayButton';
import PreviewButton from '../../../ppcp-button/resources/js/modules/Renderer/PreviewButton';
import PreviewButtonManager from '../../../ppcp-button/resources/js/modules/Renderer/PreviewButtonManager';

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
		} );
	}
}

/**
 * A single GooglePay preview button instance.
 */
class GooglePayPreviewButton extends PreviewButton {
	constructor( args ) {
		super( args );

		this.selector = `${ args.selector }GooglePay`;
		this.defaultAttributes = {
			button: {
				style: {
					type: 'pay',
					color: 'black',
					language: 'en',
				},
			},
		};
	}

	createNewWrapper() {
		const element = super.createNewWrapper();
		element.addClass( 'ppcp-button-googlepay' );

		return element;
	}

	createButton( buttonConfig ) {
		const button = new GooglepayButton(
			'preview',
			null,
			buttonConfig,
			this.ppcpConfig
		);

		button.init( this.apiConfig );
	}

	/**
	 * Merge form details into the config object for preview.
	 * Mutates the previewConfig object; no return value.
	 * @param buttonConfig
	 * @param ppcpConfig
	 */
	dynamicPreviewConfig( buttonConfig, ppcpConfig ) {
		// Merge the current form-values into the preview-button configuration.
		if ( ppcpConfig.button && buttonConfig.button ) {
			Object.assign( buttonConfig.button.style, ppcpConfig.button.style );
		}
	}
}

// Initialize the preview button manager.
buttonManager();
