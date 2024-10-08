import GooglepayButton from './GooglepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class GooglepayManagerBlockEditor {
	constructor( namespace, buttonConfig, ppcpConfig ) {
		this.namespace = namespace;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.googlePayConfig = null;
		this.transactionInfo = null;
		this.contextHandler = null;

		/*
		 * On the front-end, the init method is called when a new button context was detected
		 * via `buttonModuleWatcher`. In the block editor, we do not need to wait for the
		 * context, but can initialize the button in the next event loop.
		 */
		setTimeout( () => this.init() );
	}

	async init() {
		try {
			// Gets GooglePay configuration of the PayPal merchant.
			this.googlePayConfig = await window[ this.namespace ]
				.Googlepay()
				.config();

			this.contextHandler = ContextHandlerFactory.create(
				this.ppcpConfig.context,
				this.buttonConfig,
				this.ppcpConfig,
				null
			);

			// Fetch transaction information.
			this.transactionInfo = await this.fetchTransactionInfo();

			const button = GooglepayButton.createButton(
				this.ppcpConfig.context,
				null,
				this.buttonConfig,
				this.ppcpConfig,
				this.contextHandler
			);

			button.configure( this.googlePayConfig, this.transactionInfo );
			button.init();
		} catch ( error ) {
			console.error( 'Failed to initialize Google Pay:', error );
		}
	}

	async fetchTransactionInfo() {
		try {
			if ( ! this.contextHandler ) {
				throw new Error( 'ContextHandler is not initialized' );
			}
			return await this.contextHandler.transactionInfo();
		} catch ( error ) {
			console.error( 'Error fetching transaction info:', error );
			throw error;
		}
	}
}

export default GooglepayManagerBlockEditor;
