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
let onSdkInstanceChange;
let visibilityListeners;

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
	delete window.__ppcpV6TokenRefresh;

	// The token refresh never removes its visibilitychange listener, so listeners
	// of earlier tests would fire refreshes in later ones.
	visibilityListeners = [];
	const originalAddEventListener = document.addEventListener.bind( document );
	jest.spyOn( document, 'addEventListener' ).mockImplementation(
		( type, listener, ...rest ) => {
			if ( type === 'visibilitychange' ) {
				visibilityListeners.push( listener );
			}
			originalAddEventListener( type, listener, ...rest );
		}
	);

	( { loadSdkV6 } = require( './sdkLoader' ) );
	( { onSdkInstanceChange } = require( './tokenRefresh' ) );
	window.paypal = { createInstance: jest.fn().mockResolvedValue( {} ) };
} );

afterEach( () => {
	visibilityListeners.forEach( ( listener ) =>
		document.removeEventListener( 'visibilitychange', listener )
	);
	jest.restoreAllMocks();
	delete window.paypal;
} );

describe( 'loadSdkV6', () => {
	test.each( [
		[
			'no optional components enabled',
			{},
			[ 'paypal-payments' ],
		],
		[
			'card fields enabled',
			{ card_fields: { enabled: true } },
			[ 'paypal-payments', 'card-fields' ],
		],
		[
			'google pay enabled',
			{ google_pay: { enabled: true } },
			[ 'paypal-payments', 'googlepay-payments' ],
		],
		[
			'apple pay enabled',
			{ apple_pay: { enabled: true } },
			[ 'paypal-payments', 'applepay-payments' ],
		],
		[
			'card fields and google pay both enabled',
			{ card_fields: { enabled: true }, google_pay: { enabled: true } },
			[
				'paypal-payments',
				'card-fields',
				'googlepay-payments',
			],
		],
		[
			'the card button rendering its classic row',
			{ card_button: { row: true } },
			[ 'paypal-payments', 'paypal-guest-payments' ],
		],
		[
			'fastlane enabled',
			{ fastlane: { enabled: true } },
			[ 'paypal-payments', 'fastlane' ],
		],
	] )( 'requests %s', async ( label, overrides, expectedComponents ) => {
		await loadSdkV6( baseConfig( overrides ), 'checkout' );

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( { components: expectedComponents } )
		);
	} );

	test.each( [
		[ 'venmo is on for one context only', { checkout: true, cart: false }, true ],
		[ 'venmo is on for every context', { product: true, cart: true }, true ],
		[ 'venmo is off for every context', { checkout: false, cart: false }, false ],
		[ 'venmo_button is an empty map', {}, false ],
		[ 'venmo_button is missing', undefined, false ],
	] )(
		'requests venmo-payments only when at least one context enables it: %s',
		async ( label, venmoButton, expected ) => {
			await loadSdkV6(
				baseConfig( { venmo_button: venmoButton } ),
				'checkout'
			);

			const { components } =
				window.paypal.createInstance.mock.calls[ 0 ][ 0 ];
			expect( components.includes( 'venmo-payments' ) ).toBe( expected );
		}
	);

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
		const { components } =
			window.paypal.createInstance.mock.calls[ 0 ][ 0 ];
		expect( [ ...components ].sort() ).toEqual(
			[
				'paypal-payments',
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
				components: [ 'paypal-payments' ],
			} )
		);
	} );

	test( 'does not request paypal-guest-payments when the card button has no classic row', async () => {
		await loadSdkV6(
			baseConfig( { card_button: { row: false } } ),
			'checkout'
		);

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( {
				components: [ 'paypal-payments' ],
			} )
		);
	} );

	test( 'requests paypal-messages only when config.messages.enabled is true', async () => {
		await loadSdkV6(
			baseConfig( { messages: { enabled: true } } ),
			'product'
		);

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( {
				components: expect.arrayContaining( [ 'paypal-messages' ] ),
			} )
		);
	} );

	test( 'omits paypal-messages when config.messages.enabled is false', async () => {
		await loadSdkV6(
			baseConfig( { messages: { enabled: false } } ),
			'product'
		);

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( {
				components: expect.not.arrayContaining( [ 'paypal-messages' ] ),
			} )
		);
	} );

	test( 'requests card-fields regardless of the messages setting', async () => {
		await loadSdkV6(
			baseConfig( {
				card_fields: { enabled: true },
				messages: { enabled: true },
			} ),
			'checkout'
		);

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( {
				components: expect.arrayContaining( [
					'card-fields',
					'paypal-messages',
				] ),
			} )
		);
	} );

	test( 'shares one instance across concurrent callers: one createInstance call and one client-token fetch', async () => {
		const config = baseConfig();

		const first = loadSdkV6( config, 'cart' );
		const second = loadSdkV6( config, 'checkout' );

		const [ firstSdk, secondSdk ] = await Promise.all( [ first, second ] );

		expect( firstSdk ).toBe( secondSdk );
		expect( window.paypal.createInstance ).toHaveBeenCalledTimes( 1 );
		expect( mockPostJson ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'uses the page type of the first caller for the shared instance', async () => {
		const config = baseConfig();

		const first = loadSdkV6( config, 'cart' );
		const second = loadSdkV6( config, 'checkout' );
		await Promise.all( [ first, second ] );

		expect( window.paypal.createInstance ).toHaveBeenCalledWith(
			expect.objectContaining( { pageType: 'cart' } )
		);
	} );

	test( 'passes a stable clientMetadataId across two loadSdkV6 calls on the same page', async () => {
		await loadSdkV6( baseConfig(), 'checkout' );
		const firstId =
			window.paypal.createInstance.mock.calls[ 0 ][ 0 ].clientMetadataId;

		// Resetting only the instance cache forces a second createInstance call
		// while leaving the page-level metadata id cache intact.
		delete window.__ppcpV6InstancePromise;
		window.paypal.createInstance.mockClear();

		await loadSdkV6( baseConfig(), 'checkout' );
		const secondId =
			window.paypal.createInstance.mock.calls[ 0 ][ 0 ].clientMetadataId;

		expect( firstId ).toEqual( expect.any( String ) );
		expect( secondId ).toBe( firstId );
	} );
} );

describe( 'client token refresh wiring', () => {
	const REFRESH_IN = 240;
	const REFRESH_MS = REFRESH_IN * 1000;

	const tokenResponse = ( token ) => ( {
		client_token: token,
		refresh_in: REFRESH_IN,
		retry_in: 15,
	} );

	const sdk1 = { name: 'sdk1' };
	const sdk2 = { name: 'sdk2' };

	const tick = ( ms ) => jest.advanceTimersByTimeAsync( ms );

	beforeEach( () => {
		jest.useFakeTimers();
		window.paypal.createInstance
			.mockReset()
			.mockResolvedValueOnce( sdk1 )
			.mockResolvedValueOnce( sdk2 );
		mockPostJson
			.mockReset()
			.mockResolvedValueOnce( tokenResponse( 'T1' ) )
			.mockResolvedValueOnce( tokenResponse( 'T2' ) );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'creates a new instance with the new token and the other options unchanged', async () => {
		await loadSdkV6(
			baseConfig( { card_fields: { enabled: true } } ),
			'checkout'
		);
		const { clientToken, ...firstOptions } =
			window.paypal.createInstance.mock.calls[ 0 ][ 0 ];

		await tick( REFRESH_MS );

		expect( clientToken ).toBe( 'T1' );
		expect( window.paypal.createInstance ).toHaveBeenCalledTimes( 2 );
		expect( window.paypal.createInstance ).toHaveBeenLastCalledWith( {
			...firstOptions,
			clientToken: 'T2',
		} );
	} );

	test( 'resolves later loadSdkV6 calls to the new instance', async () => {
		await expect( loadSdkV6( baseConfig(), 'checkout' ) ).resolves.toBe(
			sdk1
		);

		await tick( REFRESH_MS );

		await expect( loadSdkV6( baseConfig(), 'checkout' ) ).resolves.toBe(
			sdk2
		);
	} );

	test( 'hands the new instance to onSdkInstanceChange() subscribers', async () => {
		const listener = jest.fn();
		onSdkInstanceChange( listener );
		await loadSdkV6( baseConfig(), 'checkout' );

		await tick( REFRESH_MS - 1 );
		expect( listener ).not.toHaveBeenCalled();

		await tick( 1 );
		expect( listener ).toHaveBeenCalledWith( sdk2 );
	} );
} );
