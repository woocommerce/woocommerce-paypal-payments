const mockPostJson = jest.fn();
jest.mock( './utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockLoadScript = jest.fn();
jest.mock( './utils/scriptLoaders', () => ( {
	loadScript: ( ...args ) => mockLoadScript( ...args ),
} ) );

// loadSdkV6() memoizes on window-level state (shared across webpack bundles),
// which survives jest.resetModules(), so each test also needs its own window keys.
let loadSdkV6;

function baseConfig( overrides = {} ) {
	return {
		sdk_url: 'https://example.test/sdk.js',
		ajax: { client_token: { endpoint: '/token', nonce: 'n' } },
		locale: 'en_US',
		...overrides,
	};
}

beforeEach( () => {
	jest.resetModules();
	mockPostJson.mockReset();
	mockLoadScript.mockReset();
	mockLoadScript.mockResolvedValue( undefined );
	mockPostJson.mockResolvedValue( { client_token: 'TOKEN' } );
	delete window.__ppcpV6InstancePromise;
	delete window.__ppcpV6ScriptPromises;
	delete window.__ppcpV6ClientMetadataId;
	( { loadSdkV6 } = require( './sdkLoader' ) );
	window.paypal = { createInstance: jest.fn().mockResolvedValue( {} ) };
} );

afterEach( () => {
	delete window.paypal;
} );

describe( 'loadSdkV6', () => {
	test.each( [
		[
			'no optional components enabled',
			{},
			[ 'paypal-payments', 'venmo-payments' ],
		],
		[
			'card fields enabled',
			{ card_fields: { enabled: true } },
			[ 'paypal-payments', 'venmo-payments', 'card-fields' ],
		],
		[
			'google pay enabled',
			{ google_pay: { enabled: true } },
			[ 'paypal-payments', 'venmo-payments', 'googlepay-payments' ],
		],
		[
			'apple pay enabled',
			{ apple_pay: { enabled: true } },
			[ 'paypal-payments', 'venmo-payments', 'applepay-payments' ],
		],
		[
			'card fields and google pay both enabled',
			{ card_fields: { enabled: true }, google_pay: { enabled: true } },
			[
				'paypal-payments',
				'venmo-payments',
				'card-fields',
				'googlepay-payments',
			],
		],
		[
			'fastlane enabled',
			{ fastlane: { enabled: true } },
			[ 'paypal-payments', 'venmo-payments', 'fastlane' ],
		],
	] )( 'requests %s', async ( label, overrides, expectedComponents ) => {
		await loadSdkV6( baseConfig( overrides ), 'checkout' );

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( { components: expectedComponents } )
		);
	} );

	test( 'requests fastlane, card fields and apple pay all enabled', async () => {
		await loadSdkV6(
			baseConfig( {
				fastlane: { enabled: true },
				card_fields: { enabled: true },
				apple_pay: { enabled: true },
			} ),
			'checkout'
		);

		// components is a set of names passed to createInstance; push order in
		// the source carries no meaning, so compare contents, not order.
		const { components } = window.paypal.createInstance.mock.calls[ 0 ][ 0 ];
		expect( [ ...components ].sort() ).toEqual(
			[
				'paypal-payments',
				'venmo-payments',
				'card-fields',
				'applepay-payments',
				'fastlane',
			].sort()
		);
	} );

	test( 'does not request card-fields, googlepay-payments or fastlane when all are explicitly disabled', async () => {
		await loadSdkV6(
			baseConfig( {
				card_fields: { enabled: false },
				google_pay: { enabled: false },
				fastlane: { enabled: false },
			} ),
			'checkout'
		);

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( {
				components: [ 'paypal-payments', 'venmo-payments' ],
			} )
		);
	} );

	test( 'passes a stable clientMetadataId across two loadSdkV6 calls on the same page', async () => {
		await loadSdkV6( baseConfig(), 'checkout' );
		const firstId =
			window.paypal.createInstance.mock.calls[ 0 ][ 0 ]
				.clientMetadataId;

		// Resetting only the instance cache forces a second createInstance call
		// while leaving the page-level metadata id cache intact.
		delete window.__ppcpV6InstancePromise;
		window.paypal.createInstance.mockClear();

		await loadSdkV6( baseConfig(), 'checkout' );
		const secondId =
			window.paypal.createInstance.mock.calls[ 0 ][ 0 ]
				.clientMetadataId;

		expect( firstId ).toEqual( expect.any( String ) );
		expect( secondId ).toBe( firstId );
	} );
} );
