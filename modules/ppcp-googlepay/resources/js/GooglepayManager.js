/* global paypal */

import buttonModuleWatcher from '../../../ppcp-button/resources/js/modules/ButtonModuleWatcher';
import GooglepayButton from './GooglepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class GooglepayManager {
	constructor( buttonConfig, ppcpConfig ) {
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.googlePayConfig = null;
		this.transactionInfo = null;
		this.contextHandler = null;
		this.buttons = [];

		this.onContextBootstrap = this.onContextBootstrap.bind( this );
		buttonModuleWatcher.watchContextBootstrap( this.onContextBootstrap );
	}

	async onContextBootstrap( bootstrap ) {
		this.contextHandler = ContextHandlerFactory.create(
			bootstrap.context,
			this.buttonConfig,
			this.ppcpConfig,
			bootstrap.handler
		);

		const button = GooglepayButton.createButton(
			bootstrap.context,
			bootstrap.handler,
			this.buttonConfig,
			this.ppcpConfig,
			this.contextHandler
		);

		this.buttons.push( button );

		// Ensure googlePayConfig and transactionInfo are loaded.
		await this.init();

		button.configure( this.googlePayConfig, this.transactionInfo );
		button.init();
	}

	async init() {
		try {
			if ( ! this.googlePayConfig ) {
				// Gets GooglePay configuration of the PayPal merchant.
				this.googlePayConfig = await paypal.Googlepay().config();

				if ( ! this.googlePayConfig ) {
					console.error( 'No GooglePayConfig received during init' );
				}
			}

			if ( ! this.transactionInfo ) {
				this.transactionInfo = await this.fetchTransactionInfo();

				if ( ! this.transactionInfo ) {
					console.error( 'No transactionInfo found during init' );
				}
			}
		} catch ( error ) {
			console.error( 'Error during initialization:', error );
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

	reinit() {
		for ( const button of this.buttons ) {
			button.reinit();
		}
	}
}

export default GooglepayManager;
