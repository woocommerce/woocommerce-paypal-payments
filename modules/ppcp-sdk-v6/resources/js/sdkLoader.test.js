const mockPostJson = jest.fn();
jest.mock( './utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockLoadScript = jest.fn();
jest.mock( './utils/scriptLoaders', () => ( {
	loadScript: ( ...args ) => mockLoadScript( ...args ),
} ) );

// loadSdkV6() memoizes on module-level state, so each test needs a fresh module instance.
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
	] )( 'requests %s', async ( label, overrides, expectedComponents ) => {
		await loadSdkV6( baseConfig( overrides ), 'checkout' );

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( { components: expectedComponents } )
		);
	} );

	test( 'does not request card-fields or googlepay-payments when both are explicitly disabled', async () => {
		await loadSdkV6(
			baseConfig( {
				card_fields: { enabled: false },
				google_pay: { enabled: false },
			} ),
			'checkout'
		);

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( {
				components: [ 'paypal-payments', 'venmo-payments' ],
			} )
		);
	} );
} );
