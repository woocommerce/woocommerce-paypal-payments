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

		buttonModuleWatcher.watchContextBootstrap( async ( bootstrap ) => {
			this.contextHandler = ContextHandlerFactory.create(
				bootstrap.context,
				buttonConfig,
				ppcpConfig,
				bootstrap.handler
			);

			const button = GooglepayButton.createButton(
				bootstrap.context,
				bootstrap.handler,
				buttonConfig,
				ppcpConfig,
				this.contextHandler
			);

			this.buttons.push( button );

			const initButton = () => {
				button.configure( this.googlePayConfig, this.transactionInfo );
				button.init();
			};

			// Initialize button only if googlePayConfig and transactionInfo are already fetched.
			if ( this.googlePayConfig && this.transactionInfo ) {
				initButton();
			} else {
				await this.init();

				if ( this.googlePayConfig && this.transactionInfo ) {
					initButton();
				}
			}
		} );
	}

	async init() {
		try {
			if ( ! this.googlePayConfig ) {
				// Gets GooglePay configuration of the PayPal merchant.
				this.googlePayConfig = await paypal.Googlepay().config();
			}

			if ( ! this.transactionInfo ) {
				this.transactionInfo = await this.fetchTransactionInfo();
			}

			if ( ! this.googlePayConfig ) {
				console.error( 'No GooglePayConfig received during init' );
			} else if ( ! this.transactionInfo ) {
				console.error( 'No transactionInfo found during init' );
			} else {
				for ( const button of this.buttons ) {
					button.configure(
						this.googlePayConfig,
						this.transactionInfo
					);
					button.init();
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
