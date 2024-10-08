import ApplePayButton from './ApplepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class ApplePayManagerBlockEditor {
	#namespace = '';
	#buttonConfig = null;
	#ppcpConfig = null;
	#applePayConfig = null;
	#contextHandler = null;

	constructor( namespace, buttonConfig, ppcpConfig ) {
		this.#namespace = namespace;
		this.#buttonConfig = buttonConfig;
		this.#ppcpConfig = ppcpConfig;

		/*
		 * On the front-end, the init method is called when a new button context was detected
		 * via `buttonModuleWatcher`. In the block editor, we do not need to wait for the
		 * context, but can initialize the button in the next event loop.
		 */
		setTimeout( () => this.init() );
	}

	async init() {
		try {
			this.#applePayConfig = await window[ this.#namespace ]
				.Applepay()
				.config();

			this.#contextHandler = ContextHandlerFactory.create(
				this.#ppcpConfig.context,
				this.#buttonConfig,
				this.#ppcpConfig,
				null
			);

			const button = ApplePayButton.createButton(
				this.#ppcpConfig.context,
				null,
				this.#buttonConfig,
				this.#ppcpConfig,
				this.#contextHandler
			);

			button.configure( this.#applePayConfig );
			button.init();
		} catch ( error ) {
			console.error( 'Failed to initialize Apple Pay:', error );
		}
	}
}

export default ApplePayManagerBlockEditor;
