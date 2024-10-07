/* global paypal */

import buttonModuleWatcher from '../../../ppcp-button/resources/js/modules/ButtonModuleWatcher';
import ApplePayButton from './ApplepayButton';
import ContextHandlerFactory from './Context/ContextHandlerFactory';

class ApplePayManager {
	constructor( buttonConfig, ppcpConfig ) {
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.ApplePayConfig = null;
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
				this.contextHandler
			);

			this.buttons.push( button );

			// Ensure ApplePayConfig is loaded before proceeding.
			await this.init();

			button.configure( this.ApplePayConfig );
			button.init();
		} );
	}

	async init() {
		try {
			if ( ! this.ApplePayConfig ) {
				this.ApplePayConfig = await paypal.Applepay().config();

				if ( ! this.ApplePayConfig ) {
					console.error( 'No ApplePayConfig received during init' );
				}
			}
		} catch ( error ) {
			console.error( 'Error during initialization:', error );
		}
	}

	reinit() {
		for ( const button of this.buttons ) {
			button.reinit();
		}
	}
}

export default ApplePayManager;
