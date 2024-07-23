import AxoManager from './AxoManager';
import { loadPaypalScript } from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading';

( function ( { axoConfig, ppcpConfig, jQuery } ) {
	const bootstrap = () => {
		new AxoManager( axoConfig, ppcpConfig );
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		if ( ! typeof PayPalCommerceGateway ) {
			console.error( 'AXO could not be configured.' );
			return;
		}

		// Load PayPal
		loadPaypalScript( ppcpConfig, () => {
			bootstrap();
		} );
	} );
} )( {
	axoConfig: window.wc_ppcp_axo,
	ppcpConfig: window.PayPalCommerceGateway,
	jQuery: window.jQuery,
} );
