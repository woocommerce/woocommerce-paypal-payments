import PreviewButton from '../../../ppcp-button/resources/js/modules/Renderer/PreviewButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';
import GooglepayButton from './GooglepayButton';

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

	createNewWrapper() {
		const element = super.createNewWrapper();
		element.addClass( 'ppcp-button-googlepay' );

		return element;
	}

	createButton( buttonConfig ) {
		const contextHandler = ContextHandlerFactory.create(
			'preview',
			buttonConfig,
			this.ppcpConfig,
			null
		);

		/* Intentionally using `new` keyword, instead of the `.createButton()` factory,
		 * as the factory is designed to only create a single button per context, while a single
		 * page can contain multiple instances of a preview button.
		 */
		const button = new GooglepayButton(
			'preview',
			null,
			buttonConfig,
			this.ppcpConfig,
			contextHandler
		);

		button.configure( this.apiConfig, null );
		button.init();
	}

	/**
	 * Merge form details into the config object for preview.
	 * Mutates the previewConfig object; no return value.
	 *
	 * @param {Object} buttonConfig
	 * @param {Object} ppcpConfig
	 */
	dynamicPreviewConfig( buttonConfig, ppcpConfig ) {
		// Merge the current form-values into the preview-button configuration.
		if ( ppcpConfig.button && buttonConfig.button ) {
			Object.assign( buttonConfig.button.style, ppcpConfig.button.style );
		}
	}
}
