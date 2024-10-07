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
	}

	init() {
		( async () => {
			await this.config();
		} )();
	}

	async config() {
		try {
			// Gets GooglePay configuration of the PayPal merchant.
			this.googlePayConfig = await window[ this.namespace ]
				.Googlepay()
				.config();

			// Fetch transaction information.
			this.transactionInfo = await this.fetchTransactionInfo();

			const button = new GooglepayButton(
				this.ppcpConfig.context,
				null,
				this.buttonConfig,
				this.ppcpConfig,
				this.contextHandler
			);

			button.init( this.googlePayConfig, this.transactionInfo );
		} catch ( error ) {
			console.error( 'Failed to initialize Google Pay:', error );
		}
	}

	async fetchTransactionInfo() {
		try {
			if ( ! this.contextHandler ) {
				this.contextHandler = ContextHandlerFactory.create(
					this.ppcpConfig.context,
					this.buttonConfig,
					this.ppcpConfig,
					null
				);
			}
			return null;
		} catch ( error ) {
			console.error( 'Error fetching transaction info:', error );
			throw error;
		}
	}
}

export default GooglepayManagerBlockEditor;
