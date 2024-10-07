import { loadCustomScript } from '@paypal/paypal-js';
import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';
import ApplePayManager from './ApplepayManager';
import { setupButtonEvents } from '../../../ppcp-button/resources/js/modules/Helper/ButtonRefreshHelper';

( function ( { buttonConfig, ppcpConfig } ) {
	const bootstrap = function () {
		if ( ! buttonConfig || ! ppcpConfig ) {
			return;
		}

		const manager = new ApplePayManager( buttonConfig, ppcpConfig );

		setupButtonEvents( function () {
			manager.reinit();
		} );
	};


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

		const isMiniCart = ppcpConfig.mini_cart_buttons_enabled;
		const isButton =
			null !== document.getElementById( buttonConfig.button.wrapper );

		// If button wrapper is not present then there is no need to load the scripts.
		// minicart loads later?
		if ( ! isMiniCart && ! isButton ) {
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
		loadPaypalScript( ppcpConfig, () => {
			paypalLoaded = true;
			tryToBoot();
		} );
	} );
} )( {
	buttonConfig: window.wc_ppcp_applepay,
	ppcpConfig: window.PayPalCommerceGateway,
} );
