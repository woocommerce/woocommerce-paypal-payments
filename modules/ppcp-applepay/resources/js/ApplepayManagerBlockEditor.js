/* global paypal */

import ApplePayButton from './ApplepayButton';

class ApplePayManagerBlockEditor {
	constructor( buttonConfig, ppcpConfig ) {
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.applePayConfig = null;
	}

	init() {
		( async () => {
			await this.config();
		} )();
	}

	async config() {
		try {
			this.applePayConfig = await paypal.Applepay().config();

			const button = new ApplePayButton(
				this.ppcpConfig.context,
				null,
				this.buttonConfig,
				this.ppcpConfig
			);

			button.init( this.applePayConfig );
		} catch ( error ) {
			console.error( 'Failed to initialize Apple Pay:', error );
		}
	}
}

export default ApplePayManagerBlockEditor;
