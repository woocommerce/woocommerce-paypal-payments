import buttonModuleWatcher from '@ppcp-button/ButtonModuleWatcher';
import GooglepayButton from './GooglepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class GooglepayManager {
	constructor(
		namespace,
		buttonConfig,
		ppcpConfig,
		buttonAttributes = {},
		onClick = null
	) {
		this.namespace = namespace;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.buttonAttributes = buttonAttributes;
		this.onClick = onClick;

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
				this.contextHandler,
				this.buttonAttributes,
				this.onClick
			);

			this.buttons.push( button );

			const initButton = () => {
				button.configure(
					this.googlePayConfig,
					this.transactionInfo,
					this.buttonAttributes
				);
				button.init();
			};

			// Initialize button only if googlePayConfig is already fetched.
			if ( this.googlePayConfig ) {
				initButton();
			} else {
				await this.init();

				if ( this.googlePayConfig ) {
					initButton();
				}
			}
		} );
	}

	async init() {
		try {
			if ( ! this.googlePayConfig ) {
				// Gets GooglePay configuration of the PayPal merchant.
				this.googlePayConfig = await window[ this.namespace ]
					.Googlepay()
					.config();
			}

			if ( ! this.transactionInfo ) {
				try {
					this.transactionInfo = await this.fetchTransactionInfo();
				} catch ( error ) {
					console.debug( 'Failed to fetch transaction info:', error );
				}
			}

			if ( ! this.googlePayConfig ) {
				console.error( 'No GooglePayConfig received during init' );
			} else {
				for ( const button of this.buttons ) {
					button.configure(
						this.googlePayConfig,
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
		if ( ! this.contextHandler ) {
			throw new Error( 'ContextHandler is not initialized' );
		}
		return await this.contextHandler.transactionInfo();
	}

	reinit() {
		for ( const button of this.buttons ) {
			button.reinit();
		}
	}
}

export default GooglepayManager;
