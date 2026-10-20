import AxoManager from './AxoManager';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';
import { log } from './Helper/Debug';
import { fastlaneSdkV6Config } from '@ppcp-sdk-v6/utils/config';

( function ( { axoConfig, ppcpConfig } ) {
	const namespace = 'ppcpPaypalClassicAxo';
	const bootstrap = () => {
		new AxoManager( namespace, axoConfig, ppcpConfig );
	};

	/**
	 * Whether the v6 SDK owns this page and can supply Fastlane.
	 *
	 * The v6 stack loads its own script and fetches its own client token, and
	 * Connection/Fastlane.js takes the instance from there, so none of the v5
	 * loading below applies. v5's own config global is absent on those pages
	 * (the smart button is replaced by DisabledSmartButton), which is why the
	 * guard on it is skipped too.
	 *
	 * @return {boolean} True when Fastlane comes from the v6 SDK.
	 */
	const usesSdkV6 = () => Boolean( fastlaneSdkV6Config() );

	document.addEventListener( 'DOMContentLoaded', async () => {
		if ( usesSdkV6() ) {
			bootstrap();
			return;
		}

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
