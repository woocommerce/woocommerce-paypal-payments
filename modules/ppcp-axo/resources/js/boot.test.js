const mockAxoManager = jest.fn();
jest.mock( './AxoManager', () => mockAxoManager );

const mockLoadPayPalScript = jest.fn();
jest.mock( '@ppcp-button/Helper/PayPalScriptLoading', () => ( {
	loadPayPalScript: ( ...args ) => mockLoadPayPalScript( ...args ),
} ) );

jest.mock( './Helper/Debug', () => ( {
	log: jest.fn(),
} ) );

// boot.js reads its config off window globals as an IIFE at import time and
// registers one DOMContentLoaded listener, so each test needs a fresh module
// instance and to dispatch the event itself. jest.resetModules() does not
// detach listeners though, so without cleanup every previous test's handler
// would still be attached to `document` and would re-fire on this dispatch,
// running with this test's config while closing over the old one. Capturing
// and removing the listener this call adds keeps exactly one handler alive
// per dispatch.
function boot() {
	jest.resetModules();
	mockAxoManager.mockClear();
	mockLoadPayPalScript.mockClear();

	const addEventListenerSpy = jest.spyOn( document, 'addEventListener' );
	require( './boot' );
	const [ , handler ] = addEventListenerSpy.mock.calls[ 0 ];
	addEventListenerSpy.mockRestore();

	document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
	document.removeEventListener( 'DOMContentLoaded', handler );
}

beforeEach( () => {
	delete window.wc_ppcp_sdk_v6;
	delete window.wc_ppcp_axo;
	delete window.PayPalCommerceGateway;
	delete global.fetch;
} );

describe( 'boot', () => {
	test( 'constructs AxoManager immediately when the v6 SDK owns Fastlane, without fetching the v5 script attributes', async () => {
		window.wc_ppcp_sdk_v6 = { fastlane: { enabled: true } };
		window.wc_ppcp_axo = { ajax: {} };
		global.fetch = jest.fn();

		boot();
		await Promise.resolve();

		expect( mockAxoManager ).toHaveBeenCalledWith(
			'ppcpPaypalClassicAxo',
			window.wc_ppcp_axo,
			window.PayPalCommerceGateway
		);
		expect( global.fetch ).not.toHaveBeenCalled();
		expect( mockLoadPayPalScript ).not.toHaveBeenCalled();
	} );

	test( 'still fetches script attributes and loads the v5 script when the v6 SDK does not own Fastlane', async () => {
		window.PayPalCommerceGateway = { script_attributes: {} };
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

		boot();
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

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
		expect( mockAxoManager ).toHaveBeenCalledWith(
			'ppcpPaypalClassicAxo',
			window.wc_ppcp_axo,
			window.PayPalCommerceGateway
		);
	} );
} );
