import ApplepayButton from './ApplepayButton';
import PreviewButton from '../../../ppcp-button/resources/js/modules/Renderer/PreviewButton';
import PreviewButtonManager from '../../../ppcp-button/resources/js/modules/Renderer/PreviewButtonManager';

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
	if ( ! ApplePayPreviewButtonManager.instance ) {
		ApplePayPreviewButtonManager.instance =
			new ApplePayPreviewButtonManager();
	}

	return ApplePayPreviewButtonManager.instance;
};

/**
 * Manages all Apple Pay preview buttons on this page.
 */
class ApplePayPreviewButtonManager extends PreviewButtonManager {
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
		} );
	}
}

/**
 * A single Apple Pay preview button instance.
 */
class ApplePayPreviewButton extends PreviewButton {
	constructor( args ) {
		super( args );

		this.selector = `${ args.selector }ApplePay`;
		this.defaultAttributes = {
			button: {
				type: 'pay',
				color: 'black',
				lang: 'en',
			},
		};
	}

	createNewWrapper() {
		const element = super.createNewWrapper();
		element.addClass( 'ppcp-button-applepay' );

		return element;
	}

	createButton( buttonConfig ) {
		const button = new ApplepayButton(
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
		// The Apple Pay button expects the "wrapper" to be an ID without `#` prefix!
		buttonConfig.button.wrapper = buttonConfig.button.wrapper.replace(
			/^#/,
			''
		);

		// Merge the current form-values into the preview-button configuration.
		if ( ppcpConfig.button ) {
			buttonConfig.button.type = ppcpConfig.button.style.type;
			buttonConfig.button.color = ppcpConfig.button.style.color;
			buttonConfig.button.lang =
				ppcpConfig.button.style?.lang ||
				ppcpConfig.button.style.language;
		}
	}
}

// Initialize the preview button manager.
buttonManager();
