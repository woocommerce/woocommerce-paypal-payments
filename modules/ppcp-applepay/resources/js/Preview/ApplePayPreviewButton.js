import ApplepayButton from '../ApplepayButton';
import PreviewButton from '../../../../ppcp-button/resources/js/modules/Preview/PreviewButton';

/**
 * A single Apple Pay preview button instance.
 */
export default class ApplePayPreviewButton extends PreviewButton {
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
