jest.mock( '@ppcp-axo/Helper/Debug', () => ( {
	log: jest.fn(),
} ) );

const mockLoadPayPalScript = jest.fn();
jest.mock( '@ppcp-button/Helper/PayPalScriptLoading', () => ( {
	loadPayPalScript: ( ...args ) => mockLoadPayPalScript( ...args ),
} ) );

import { renderHook, waitFor } from '@testing-library/react';
import { dispatch } from '@wordpress/data';
import usePayPalScript from './usePayPalScript';
import { STORE_NAME } from '@ppcp-axo-block/stores/axoStore';

beforeEach( () => {
	jest.clearAllMocks();
	dispatch( STORE_NAME ).setIsPayPalLoaded( false );
	delete window.wc_ppcp_sdk_v6;
	delete window.wc_ppcp_axo;
	delete global.fetch;
} );

describe( 'usePayPalScript', () => {
	test( 'reports the script as loaded without fetching when the v6 SDK owns Fastlane', async () => {
		window.wc_ppcp_sdk_v6 = { fastlane: { enabled: true } };
		global.fetch = jest.fn();

		const { result } = renderHook( () =>
			usePayPalScript( 'ppcpPaypalClassicAxo', {}, true )
		);

		await waitFor( () => expect( result.current ).toBe( true ) );

		expect( global.fetch ).not.toHaveBeenCalled();
		expect( mockLoadPayPalScript ).not.toHaveBeenCalled();
	} );

	test( 'fetches script attributes and loads the v5 script when the v6 SDK does not own Fastlane', async () => {
		window.wc_ppcp_axo = {
			ajax: {
				axo_script_attributes: {
					endpoint: '/axo-script-attributes',
					nonce: 'abc',
				},
			},
		};
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => ( {
				success: true,
				data: { sdk_client_token: 'TOKEN' },
			} ),
		} );
		mockLoadPayPalScript.mockResolvedValue( undefined );

		const { result } = renderHook( () =>
			usePayPalScript( 'ppcpPaypalClassicAxo', {}, true )
		);

		await waitFor( () => expect( result.current ).toBe( true ) );

		expect( global.fetch ).toHaveBeenCalledWith(
			'/axo-script-attributes',
			expect.objectContaining( { method: 'POST' } )
		);
		expect( mockLoadPayPalScript ).toHaveBeenCalledWith(
			'ppcpPaypalClassicAxo',
			expect.objectContaining( {
				script_attributes: expect.objectContaining( {
					'data-sdk-client-token': 'TOKEN',
				} ),
			} )
		);
	} );

	test( 'does not fetch when the gateway config has not loaded yet', () => {
		const { result } = renderHook( () =>
			usePayPalScript( 'ppcpPaypalClassicAxo', {}, false )
		);

		expect( result.current ).toBe( false );
		expect( global.fetch ).toBeUndefined();
	} );
} );
