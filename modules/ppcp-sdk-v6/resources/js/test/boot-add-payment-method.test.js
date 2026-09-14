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
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
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

beforeEach( () => {
	jest.clearAllMocks();
	mockLoadSdkV6.mockResolvedValue( {} );
	mockCheckVaultEligibility.mockResolvedValue( { paypal: true, card: true } );
	mockCreateSavePayPalSession.mockReturnValue( {} );
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
} );
