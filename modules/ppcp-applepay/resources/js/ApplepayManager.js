import buttonModuleWatcher from '../../../ppcp-button/resources/js/modules/ButtonModuleWatcher';
import ApplePayButton from './ApplepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class ApplePayManager {
	#namespace = '';
	#buttonConfig = null;
	#ppcpConfig = null;
	#applePayConfig = null;
	#contextHandler = null;
	#transactionInfo = null;
	#buttons = [];

	constructor( namespace, buttonConfig, ppcpConfig ) {
		this.#namespace = namespace;
		this.#buttonConfig = buttonConfig;
		this.#ppcpConfig = ppcpConfig;

		this.onContextBootstrap = this.onContextBootstrap.bind( this );
		buttonModuleWatcher.watchContextBootstrap( this.onContextBootstrap );
	}

	async onContextBootstrap( bootstrap ) {
		this.#contextHandler = ContextHandlerFactory.create(
			bootstrap.context,
			this.#buttonConfig,
			this.#ppcpConfig,
			bootstrap.handler
		);

		const button = ApplePayButton.createButton(
			bootstrap.context,
			bootstrap.handler,
			this.#buttonConfig,
			this.#ppcpConfig,
			this.#contextHandler
		);

		this.#buttons.push( button );

		// Ensure ApplePayConfig is loaded before proceeding.
		await this.init();

		button.configure( this.#applePayConfig, this.#transactionInfo );
		button.init();
	}

	async init() {
		try {
			if ( ! this.#applePayConfig ) {
				this.#applePayConfig = await window[ this.#namespace ]
					.Applepay()
					.config();

				if ( ! this.#applePayConfig ) {
					console.error( 'No ApplePayConfig received during init' );
				}
			}

			if ( ! this.#transactionInfo ) {
				this.#transactionInfo = await this.fetchTransactionInfo();

				if ( ! this.#applePayConfig ) {
					console.error( 'No transactionInfo found during init' );
				}
			}
		} catch ( error ) {
			console.error( 'Error during initialization:', error );
		}
	}

	async fetchTransactionInfo() {
		try {
			if ( ! this.#contextHandler ) {
				throw new Error( 'ContextHandler is not initialized' );
			}
			return await this.#contextHandler.transactionInfo();
		} catch ( error ) {
			console.error( 'Error fetching transaction info:', error );
			throw error;
		}
	}

	reinit() {
		for ( const button of this.#buttons ) {
			button.reinit();
		}
	}
}

export default ApplePayManager;
