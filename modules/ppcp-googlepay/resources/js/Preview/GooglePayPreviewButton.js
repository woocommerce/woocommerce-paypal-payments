import GooglepayButton from '../GooglepayButton';
import PreviewButton from '../../../../ppcp-button/resources/js/modules/Preview/PreviewButton';

/**
 * A single GooglePay preview button instance.
 */
export default class GooglePayPreviewButton extends PreviewButton {
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
