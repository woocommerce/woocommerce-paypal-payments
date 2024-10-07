/* global ppcpBlocksEditorPaypalGooglepay */

import GooglepayButton from './GooglepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class GooglepayManagerBlockEditor {
	constructor( buttonConfig, ppcpConfig ) {
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.googlePayConfig = null;
		this.transactionInfo = null;
		this.contextHandler = null;
	}

	async init() {
		try {
			// Gets GooglePay configuration of the PayPal merchant.
			this.googlePayConfig = await ppcpBlocksEditorPaypalGooglepay
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
				throw new Error( 'ContextHandler is not initialized' );
			}
			return null;
		} catch ( error ) {
			console.error( 'Error fetching transaction info:', error );
			throw error;
		}
	}
}

export default GooglepayManagerBlockEditor;
