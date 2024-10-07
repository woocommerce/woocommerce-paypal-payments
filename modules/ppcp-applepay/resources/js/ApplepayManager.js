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

		buttonModuleWatcher.watchContextBootstrap( ( bootstrap ) => {
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

			if ( this.ApplePayConfig ) {
				button.init( this.ApplePayConfig );
			}
		} );
	}

	init() {
		( async () => {
			await this.config();
			for ( const button of this.buttons ) {
				button.init( this.ApplePayConfig );
			}
		} )();
	}

	reinit() {
		for ( const button of this.buttons ) {
			button.reinit();
		}
	}

	/**
	 * Gets Apple Pay configuration of the PayPal merchant.
	 */
	async config() {
		this.ApplePayConfig = await paypal.Applepay().config();
		return this.ApplePayConfig;
	}
}

export default ApplePayManager;
