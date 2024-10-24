import { loadCustomScript } from '@paypal/paypal-js';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import ApplePayManager from './ApplepayManager';
import { setupButtonEvents } from '../../../ppcp-button/resources/js/modules/Helper/ButtonRefreshHelper';

( function ( { buttonConfig, ppcpConfig } ) {
	const namespace = 'ppcpPaypalApplepay';

	function bootstrapPayButton() {
		if ( ! buttonConfig || ! ppcpConfig ) {
			return;
		}

		const manager = new ApplePayManager(
			namespace,
			buttonConfig,
			ppcpConfig
		);

		setupButtonEvents( function () {
			manager.reinit();
		} );
	}

	function bootstrap() {
		bootstrapPayButton();
		// Other Apple Pay bootstrapping could happen here.
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		if ( ! buttonConfig || ! ppcpConfig ) {
			/*
			 * No PayPal buttons present on this page, but maybe a bootstrap module needs to be
			 * initialized. Skip loading the SDK or gateway configuration, and directly initialize
			 * the module.
			 */
			bootstrap();

			return;
		}

		const usedInMiniCart = ppcpConfig.mini_cart_buttons_enabled;
		const pageHasButton =
			null !== document.getElementById( buttonConfig.button.wrapper );

		// If button wrapper is not present then there is no need to load the scripts.
		// minicart loads later?
		if ( ! usedInMiniCart && ! pageHasButton ) {
			return;
		}

		let bootstrapped = false;
		let paypalLoaded = false;
		let applePayLoaded = false;

		const tryToBoot = () => {
			if ( ! bootstrapped && paypalLoaded && applePayLoaded ) {
				bootstrapped = true;
				bootstrap();
			}
		};

		// Load ApplePay SDK
		loadCustomScript( { url: buttonConfig.sdk_url } ).then( () => {
			applePayLoaded = true;
			tryToBoot();
		} );

		// Load PayPal
		loadPayPalScript( namespace, ppcpConfig )
			.then( () => {
				paypalLoaded = true;
				tryToBoot();
			} )
			.catch( ( error ) => {
				console.error( 'Failed to load PayPal script: ', error );
			} );
	} );
} )( {
	buttonConfig: window.wc_ppcp_applepay,
	ppcpConfig: window.PayPalCommerceGateway,
} );
