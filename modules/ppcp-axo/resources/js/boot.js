import AxoManager from './AxoManager';
import UnifiedScriptLoader from '../../../ppcp-button/resources/js/modules/Helper/UnifiedScriptLoader';

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
		UnifiedScriptLoader.loadPayPalScript( namespace, ppcpConfig )
			.then( () => {
				console.log( 'PayPal script loaded successfully' );
				bootstrap();
			} )
			.catch( ( error ) => {
				console.error( 'Failed to load PayPal script:', error );
			} );
	} );
} )( {
	axoConfig: window.wc_ppcp_axo,
	ppcpConfig: window.PayPalCommerceGateway,
	jQuery: window.jQuery,
} );
