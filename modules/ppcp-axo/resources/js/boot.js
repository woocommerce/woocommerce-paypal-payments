import AxoManager from './AxoManager';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import { log } from './Helper/Debug';

( function ( { axoConfig, ppcpConfig, jQuery } ) {
	const namespace = 'ppcpPaypalClassicAxo';
	const bootstrap = () => {
		new AxoManager( namespace, axoConfig, ppcpConfig );
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		if ( typeof PayPalCommerceGateway === 'undefined' ) {
			console.error( 'AXO could not be configured.' );
			return;
		}

		// Load PayPal
		loadPayPalScript( namespace, ppcpConfig )
			.then( () => {
				bootstrap();
			} )
			.catch( ( error ) => {
				log( `Failed to load PayPal script: ${ error }`, 'error' );
			} );
	} );
} )( {
	axoConfig: window.wc_ppcp_axo,
	ppcpConfig: window.PayPalCommerceGateway,
	jQuery: window.jQuery,
} );
