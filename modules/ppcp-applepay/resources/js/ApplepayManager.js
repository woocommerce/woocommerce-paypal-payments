import buttonModuleWatcher from '../../../ppcp-button/resources/js/modules/ButtonModuleWatcher';
import ApplePayButton from './ApplepayButton';

class ApplePayManager {
	constructor( namespace, buttonConfig, ppcpConfig ) {
		this.namespace = namespace;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.ApplePayConfig = null;
		this.buttons = [];

		buttonModuleWatcher.watchContextBootstrap( ( bootstrap ) => {
			const button = new ApplePayButton(
				bootstrap.context,
				bootstrap.handler,
				buttonConfig,
				ppcpConfig
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
		this.ApplePayConfig = await window[ this.namespace ]
			.Applepay()
			.config();

		return this.ApplePayConfig;
	}
}

export default ApplePayManager;
