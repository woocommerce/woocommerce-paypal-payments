const mockGetCurrentPaymentMethod = jest.fn();
jest.mock( '@ppcp-button/Helper/CheckoutMethodState', () => ( {
	getCurrentPaymentMethod: () => mockGetCurrentPaymentMethod(),
	ORDER_BUTTON_SELECTOR: '#place_order',
	PaymentMethods: {
		PAYPAL: 'ppcp-gateway',
		CARDS: 'ppcp-credit-card-gateway',
	},
} ) );

const mockSetVisible = jest.fn();
const mockSetVisibleByClass = jest.fn();
jest.mock( '@ppcp-button/Helper/Hiding', () => ( {
	setVisible: ( ...args ) => mockSetVisible( ...args ),
	setVisibleByClass: ( ...args ) => mockSetVisibleByClass( ...args ),
} ) );

const mockLoadSdkV6 = jest.fn();
const mockSetSdkBusy = jest.fn();
let mockSdkListeners = [];
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );
jest.mock( '../tokenRefresh', () => ( {
	setSdkBusy: ( ...args ) => mockSetSdkBusy( ...args ),
	onSdkInstanceChange: ( listener ) => mockSdkListeners.push( listener ),
} ) );

const mockCheckVaultEligibility = jest.fn();
jest.mock( '../eligibility', () => ( {
	checkVaultEligibility: ( ...args ) => mockCheckVaultEligibility( ...args ),
} ) );

const mockCreateSavePayPalSession = jest.fn();
jest.mock( '../sessions/createSaveSession', () => ( {
	createSavePayPalSession: ( ...args ) =>
		mockCreateSavePayPalSession( ...args ),
} ) );

const mockInitCardSaveFields = jest.fn();
jest.mock( '../cardFields/saveRenderer', () => ( {
	initCardSaveFields: ( ...args ) => mockInitCardSaveFields( ...args ),
} ) );

const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockHandleError = jest.fn();
const mockSetErrorLabels = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
	setErrorLabels: ( ...args ) => mockSetErrorLabels( ...args ),
} ) );

/**
 * Drains all pending microtasks so the module's chained `init().catch().finally()`
 * promise settles before assertions run.
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

const WRAPPER_SELECTOR = '#ppcp-add-payment-method-paypal-button';

const baseConfig = ( overrides = {} ) => ( {
	labels: {},
	button: { wrapper: WRAPPER_SELECTOR, color_class: '' },
	card_fields: { enabled: true },
	currency: 'USD',
	ajax: {
		create_setup_token: { endpoint: '/cst', nonce: 'n-cst' },
	},
	...overrides,
} );

/**
 * Builds the add-payment-method page DOM: the native submit button and
 * (optionally) the PayPal button wrapper.
 */
function buildDom( { hasWrapper = true } = {} ) {
	document.body.innerHTML = `
		<button id="place_order" type="button">Place order</button>
		${
			hasWrapper
				? `<div id="${ WRAPPER_SELECTOR.slice( 1 ) }"></div>`
				: ''
		}
	`;
}

/**
 * Sets the module's global config, then imports it fresh so its top-level
 * IIFE runs against the config and DOM this test just set up.
 */
function boot( config ) {
	window.wc_ppcp_sdk_v6_save = config;
	jest.isolateModules( () => {
		require( '../boot-add-payment-method.js' );
	} );
}

const isSdkBusy = () => mockSetSdkBusy.mock.calls.at( -1 )?.[ 0 ] === true;

const swapSdk = ( sdkInstance ) =>
	mockSdkListeners.forEach( ( listener ) => listener( sdkInstance ) );

/**
 * An SDK instance whose save session is the given stub.
 *
 * @param {Object} session - The session the instance creates.
 */
const sdkWithSession = ( session ) => ( { session } );

beforeEach( () => {
	jest.clearAllMocks();
	mockSdkListeners = [];
	mockLoadSdkV6.mockResolvedValue( {} );
	mockCheckVaultEligibility.mockResolvedValue( { paypal: true, card: true } );
	mockCreateSavePayPalSession.mockReturnValue( {} );
	mockPostJson.mockResolvedValue( { id: 'SETUP1' } );
} );

afterEach( () => {
	document.body.innerHTML = '';
	delete window.wc_ppcp_sdk_v6_save;
} );

describe( 'boot-add-payment-method', () => {
	test( 'PayPal selected with a rendered PayPal button hides the native submit and shows the wrapper', async () => {
		buildDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

		boot( baseConfig() );
		await flushPromises();

		expect(
			document.querySelector( `${ WRAPPER_SELECTOR } paypal-button` )
		).not.toBeNull();
		expect( mockSetVisibleByClass ).toHaveBeenCalledWith(
			'#place_order',
			false,
			'ppcp-hidden'
		);
		expect( mockSetVisible ).toHaveBeenCalledWith(
			WRAPPER_SELECTOR,
			true
		);
	} );

	test( 'the card method selected keeps the native submit visible and hides the wrapper', async () => {
		buildDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-credit-card-gateway' );

		boot( baseConfig() );
		await flushPromises();

		expect( mockSetVisibleByClass ).toHaveBeenCalledWith(
			'#place_order',
			true,
			'ppcp-hidden'
		);
		expect( mockSetVisible ).toHaveBeenCalledWith(
			WRAPPER_SELECTOR,
			false
		);
	} );

	test( 'PayPal ineligible renders no button and leaves the native submit visible even with PayPal selected', async () => {
		buildDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
		mockCheckVaultEligibility.mockResolvedValue( {
			paypal: false,
			card: true,
		} );

		boot( baseConfig() );
		await flushPromises();

		expect(
			document.querySelector( `${ WRAPPER_SELECTOR } paypal-button` )
		).toBeNull();
		expect( mockSetVisibleByClass ).toHaveBeenCalledWith(
			'#place_order',
			true,
			'ppcp-hidden'
		);
		expect( mockSetVisible ).toHaveBeenCalledWith(
			WRAPPER_SELECTOR,
			false
		);
	} );

	test( 'card saving initializes even when the PayPal wrapper is missing from the page', async () => {
		buildDom( { hasWrapper: false } );
		mockGetCurrentPaymentMethod.mockReturnValue(
			'ppcp-credit-card-gateway'
		);
		mockCheckVaultEligibility.mockResolvedValue( {
			paypal: true,
			card: true,
		} );

		boot( baseConfig( { card_fields: { enabled: true } } ) );
		await flushPromises();

		expect( mockInitCardSaveFields ).toHaveBeenCalledWith(
			expect.objectContaining( { card_fields: { enabled: true } } )
		);
	} );

	test( 'a rejected init() (e.g. SDK load failure) is reported and still leaves the native submit visible', async () => {
		buildDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
		const loadError = new Error( 'sdk load failed' );
		mockLoadSdkV6.mockRejectedValue( loadError );

		boot( baseConfig() );
		await flushPromises();

		expect( mockHandleError ).toHaveBeenCalledWith( loadError );
		expect( mockSetVisibleByClass ).toHaveBeenCalledWith(
			'#place_order',
			true,
			'ppcp-hidden'
		);
		expect( mockSetVisible ).toHaveBeenCalledWith(
			WRAPPER_SELECTOR,
			false
		);
	} );

	describe( 'after the SDK instance changes', () => {
		test( 'replaces the PayPal button with one that starts a session of the new instance', async () => {
			buildDom();
			const oldSession = { start: jest.fn() };
			const newSession = { start: jest.fn() };
			mockLoadSdkV6.mockResolvedValue( sdkWithSession( oldSession ) );
			mockCreateSavePayPalSession.mockImplementation(
				( sdkInstance ) => sdkInstance.session
			);

			boot( baseConfig() );
			await flushPromises();
			swapSdk( sdkWithSession( newSession ) );

			const buttons = document.querySelectorAll(
				`${ WRAPPER_SELECTOR } paypal-button`
			);
			expect( buttons ).toHaveLength( 1 );

			buttons[ 0 ].click();

			expect( newSession.start ).toHaveBeenCalledTimes( 1 );
			expect( oldSession.start ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'a click on the PayPal button', () => {
		test( 'holds the SDK busy while the session runs', async () => {
			buildDom();
			mockCreateSavePayPalSession.mockReturnValue( {
				start: jest.fn( () => new Promise( () => {} ) ),
			} );

			boot( baseConfig() );
			await flushPromises();
			document.querySelector( 'paypal-button' ).click();
			await flushPromises();

			expect( isSdkBusy() ).toBe( true );
		} );

		test( 'releases the SDK and reports the error when the session fails to start', async () => {
			buildDom();
			const startError = new Error( 'popup blocked' );
			mockCreateSavePayPalSession.mockReturnValue( {
				start: jest.fn().mockRejectedValue( startError ),
			} );

			boot( baseConfig() );
			await flushPromises();
			document.querySelector( 'paypal-button' ).click();
			await flushPromises();

			expect( isSdkBusy() ).toBe( false );
			expect( mockHandleError ).toHaveBeenCalledWith( startError );
		} );
	} );
} );
