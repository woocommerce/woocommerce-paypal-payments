import AxoManager from './AxoManager';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';
import { log } from './Helper/Debug';

( function ( { axoConfig, ppcpConfig } ) {
	const namespace = 'ppcpPaypalClassicAxo';
	const bootstrap = () => {
		new AxoManager( namespace, axoConfig, ppcpConfig );
	};

	document.addEventListener( 'DOMContentLoaded', async () => {
		if ( typeof PayPalCommerceGateway === 'undefined' ) {
			console.error( 'AXO could not be configured.' );
			return;
		}

		const res = await fetch(
			axoConfig.ajax.axo_script_attributes.endpoint,
			{
				method: 'POST',
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: axoConfig.ajax.axo_script_attributes.nonce,
				} ),
			}
		);

		const json = await res.json();
		if ( ! json.success ) {
			throw new Error( json.data.message );
		}

		loadPayPalScript( namespace, {
			...ppcpConfig,
			script_attributes: {
				...ppcpConfig.script_attributes,
				'data-sdk-client-token': json.data.sdk_client_token,
			},
		} )
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
} );
