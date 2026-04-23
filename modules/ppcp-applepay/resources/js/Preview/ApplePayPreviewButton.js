import ApplepayButton from '@ppcp-applepay/ApplepayButton';
import PreviewButton from '@ppcp-button/Preview/PreviewButton';

/**
 * A single Apple Pay preview button instance.
 */
export default class ApplePayPreviewButton extends PreviewButton {
	/**
	 * @type {?PaymentButton}
	 */
	#button = null;

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

	createButton( buttonConfig ) {
		if ( ! this.#button ) {
			this.#button = new ApplepayButton(
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
