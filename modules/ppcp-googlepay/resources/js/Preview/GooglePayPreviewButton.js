import GooglepayButton from '../GooglepayButton';
import PreviewButton from '../../../../ppcp-button/resources/js/modules/Preview/PreviewButton';

/**
 * A single GooglePay preview button instance.
 */
export default class GooglePayPreviewButton extends PreviewButton {
	/**
	 * @type {?PaymentButton}
	 */
	#button = null;

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

	createButton( buttonConfig ) {
		if ( ! this.#button ) {
			this.#button = new GooglepayButton(
				'preview',
				null,
				buttonConfig,
				this.ppcpConfig
			);
		}

		this.#button.configure( this.apiConfig, null );
		this.#button.applyButtonStyles( buttonConfig, this.ppcpConfig );
		this.#button.reinit();
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
