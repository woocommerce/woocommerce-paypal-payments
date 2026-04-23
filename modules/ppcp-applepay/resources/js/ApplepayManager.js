import buttonModuleWatcher from '@ppcp-button/ButtonModuleWatcher';
import ApplePayButton from './ApplepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class ApplePayManager {
	constructor( namespace, buttonConfig, ppcpConfig, buttonAttributes = {} ) {
		this.namespace = namespace;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.buttonAttributes = buttonAttributes;
		this.applePayConfig = null;
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

			const button = ApplePayButton.createButton(
				bootstrap.context,
				bootstrap.handler,
				buttonConfig,
				ppcpConfig,
				this.contextHandler,
				this.buttonAttributes
			);

			this.buttons.push( button );
			const initButton = () => {
				button.configure(
					this.applePayConfig,
					this.transactionInfo,
					this.buttonAttributes
				);
				button.init();
			};

			// Initialize button only if applePayConfig and transactionInfo are already fetched.
			if ( this.applePayConfig && this.transactionInfo ) {
				initButton();
			} else {
				// Ensure ApplePayConfig is loaded before proceeding.
				await this.init();

				if ( this.applePayConfig && this.transactionInfo ) {
					initButton();
				}
			}
		} );
	}

	async init() {
		try {
			if ( ! this.applePayConfig ) {
				// Gets ApplePay configuration of the PayPal merchant.
				this.applePayConfig = await window[ this.namespace ]
					.Applepay()
					.config();
			}

			if ( ! this.transactionInfo ) {
				this.transactionInfo = await this.fetchTransactionInfo();
			}

			if ( ! this.applePayConfig ) {
				console.error( 'No ApplePayConfig received during init' );
			} else if ( ! this.transactionInfo ) {
				console.error( 'No transactionInfo found during init' );
			} else {
				for ( const button of this.buttons ) {
					button.configure(
						this.applePayConfig,
						this.transactionInfo,
						this.buttonAttributes
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

export default ApplePayManager;
