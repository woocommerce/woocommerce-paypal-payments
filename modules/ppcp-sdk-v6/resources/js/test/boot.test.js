const mockHasJQuery = jest.fn();
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

const mockSetErrorLabels = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	setErrorLabels: ( ...args ) => mockSetErrorLabels( ...args ),
} ) );

const mockSetVisible = jest.fn();
jest.mock( '@ppcp-button/Helper/Hiding', () => ( {
	setVisible: ( ...args ) => mockSetVisible( ...args ),
} ) );

const mockLoadSdkV6 = jest.fn();
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

const mockCheckEligibility = jest.fn();
jest.mock( '../eligibility', () => ( {
	checkEligibility: ( ...args ) => mockCheckEligibility( ...args ),
} ) );

const mockCreateSession = jest.fn();
jest.mock( '../sessions/createSession', () => ( {
	createSession: ( ...args ) => mockCreateSession( ...args ),
	SUPPORTED_METHODS: [ 'paypal', 'venmo', 'paylater' ],
} ) );

const mockRenderButtons = jest.fn();
jest.mock( '../components/buttonRenderer', () => ( {
	renderButtons: ( ...args ) => mockRenderButtons( ...args ),
} ) );

const mockRenderWallets = jest.fn();
jest.mock( '../wallets/renderWallets', () => ( {
	renderWallets: ( ...args ) => mockRenderWallets( ...args ),
} ) );

const mockIsWalletEnabled = jest.fn();
jest.mock( '../wallets/walletRegistry', () => ( {
	isWalletEnabled: ( ...args ) => mockIsWalletEnabled( ...args ),
	WALLET_METHODS: [],
} ) );

const mockCreateOrder = jest.fn();
const mockFetchCartTotal = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	createOrder: ( ...args ) => mockCreateOrder( ...args ),
	fetchCartTotal: ( ...args ) => mockFetchCartTotal( ...args ),
} ) );

const mockInitCardFields = jest.fn();
jest.mock( '../cardFields/renderer', () => ( {
	initCardFields: ( ...args ) => mockInitCardFields( ...args ),
} ) );

const mockInitMessages = jest.fn();
const mockRenderMessages = jest.fn();
const mockUpdateMessagesAmount = jest.fn();
jest.mock( '../messages/renderer', () => ( {
	initMessages: ( ...args ) => mockInitMessages( ...args ),
	renderMessages: ( ...args ) => mockRenderMessages( ...args ),
	updateMessagesAmount: ( ...args ) => mockUpdateMessagesAmount( ...args ),
} ) );

const mockWatchViewedTotal = jest.fn();
jest.mock( '../utils/viewedTotal', () => ( {
	watchViewedTotal: ( ...args ) => mockWatchViewedTotal( ...args ),
} ) );

const WRAPPER_SELECTOR = '#ppcp-button-checkout';
const MINI_CART_WRAPPER_SELECTOR = '#ppcp-mini-cart-button';

const baseConfig = ( overrides = {} ) => ( {
	labels: {},
	page_context: 'checkout',
	wrapper: WRAPPER_SELECTOR,
	mini_cart_wrapper: MINI_CART_WRAPPER_SELECTOR,
	button_styles: {},
	amount: '100.00',
	currency: 'USD',
	buyer_country: 'US',
	ajax: {},
	...overrides,
} );

function buildDom() {
	document.body.innerHTML = `
		<div id="${ WRAPPER_SELECTOR.slice( 1 ) }"></div>
		<div id="${ MINI_CART_WRAPPER_SELECTOR.slice( 1 ) }"></div>
	`;
}

/**
 * A jQuery stand-in that records handlers per event name (splitting
 * space-separated event strings the way real jQuery does) and lets tests
 * fire one event by name.
 */
function createFakeJQuery() {
	const handlers = {};

	const fakeJQuery = () => ( {
		on: ( eventNames, handler ) => {
			eventNames
				.split( /\s+/ )
				.forEach( ( name ) => {
					handlers[ name ] = handlers[ name ] || [];
					handlers[ name ].push( handler );
				} );
		},
	} );

	fakeJQuery.trigger = ( name, ...args ) => {
		( handlers[ name ] || [] ).forEach( ( handler ) =>
			handler( { type: name }, ...args )
		);
	};

	return fakeJQuery;
}

/**
 * Sets the module's global config, then imports it fresh so its top-level
 * IIFE runs against the config and DOM this test just set up.
 */
function boot( config ) {
	window.wc_ppcp_sdk_v6 = config;
	jest.isolateModules( () => {
		require( '../boot.js' );
	} );
}

/**
 * Advances only far enough to settle already-scheduled microtasks and any
 * timer already due, without running pending debounce timeouts.
 *
 * @return {Promise<void>}
 */
const flush = () => jest.advanceTimersByTimeAsync( 0 );

beforeEach( () => {
	jest.useFakeTimers();
	jest.clearAllMocks();

	mockHasJQuery.mockReturnValue( true );
	global.jQuery = createFakeJQuery();

	mockLoadSdkV6.mockResolvedValue( {} );
	mockCheckEligibility.mockResolvedValue( {
		paypal: true,
		venmo: false,
		paylater: false,
		payLaterDetails: null,
	} );
	mockCreateSession.mockReturnValue( {} );
	mockRenderWallets.mockResolvedValue();
	mockInitCardFields.mockResolvedValue();
	mockInitMessages.mockResolvedValue( 0 );
	mockRenderMessages.mockResolvedValue( 0 );
	mockFetchCartTotal.mockResolvedValue( '120.00' );
	mockWatchViewedTotal.mockReturnValue( {
		get: () => '',
		subscribe: () => () => {},
	} );
} );

afterEach( () => {
	jest.useRealTimers();
	document.body.innerHTML = '';
	delete window.wc_ppcp_sdk_v6;
	delete global.jQuery;
} );

describe( 'boot', () => {
	describe( 'eligibility refresh on cart/checkout events', () => {
		/**
		 * Regression test for the classic-checkout staleness bug: WooCommerce's
		 * cart.js fires updated_cart_totals and its checkout.js fires
		 * updated_checkout, and the two never overlap. Before updated_checkout
		 * was added to this binding, a coupon or shipping change on classic
		 * checkout never re-checked eligibility, so the Pay Later button's
		 * eligibility stayed frozen at the page-load amount for the whole
		 * checkout session.
		 */
		test( 'an updated_checkout event re-checks eligibility after the debounce elapses', async () => {
			buildDom();
			boot( baseConfig() );
			await flush();
			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 2 );
		} );

		test( 'nothing happens before the 300ms debounce elapses', async () => {
			buildDom();
			boot( baseConfig() );
			await flush();
			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 299 );

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'a burst of several updated_checkout events coalesces into one pass', async () => {
			buildDom();
			boot( baseConfig() );
			await flush();
			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 100 );
			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 100 );
			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 2 );
		} );

		test.each( [
			[ 'updated_cart_totals' ],
			[ 'added_to_cart' ],
			[ 'removed_from_cart' ],
		] )( '%s still triggers an eligibility pass', async ( eventName ) => {
			buildDom();
			boot( baseConfig() );
			await flush();
			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			global.jQuery.trigger( eventName );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 2 );
		} );

		test( 'one updated_checkout event results in exactly one fetchCartTotal call, not a duplicate from a separate handler', async () => {
			buildDom();
			boot( baseConfig() );
			await flush();
			mockFetchCartTotal.mockClear();

			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockFetchCartTotal ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'updateMessagesAmount is called with the fetched total on checkout, since messages there price the cart', async () => {
			buildDom();
			boot( baseConfig( { page_context: 'checkout' } ) );
			await flush();
			mockUpdateMessagesAmount.mockClear();

			global.jQuery.trigger( 'updated_cart_totals' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockUpdateMessagesAmount ).toHaveBeenCalledWith( '120.00' );
		} );

		test( 'updateMessagesAmount is not called from the cart-refresh path on a product page, since a product page prices via the watcher instead', async () => {
			buildDom();
			boot( baseConfig( { page_context: 'product' } ) );
			await flush();
			mockUpdateMessagesAmount.mockClear();

			global.jQuery.trigger( 'updated_cart_totals' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockUpdateMessagesAmount ).not.toHaveBeenCalled();
		} );

		test( 'a rejected pass is logged and does not prevent a later pass from running', async () => {
			buildDom();
			boot( baseConfig() );
			await flush();
			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			mockFetchCartTotal.mockRejectedValueOnce( new Error( 'network failure' ) );
			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( console ).toHaveErrored();
			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 2 );
		} );
	} );

	describe( 'DOM-replacing update events', () => {
		test.each( [
			[ 'updated_checkout' ],
			[ 'wc_fragments_loaded' ],
			[ 'wc_fragments_refreshed' ],
		] )( '%s re-renders messages', async ( eventName ) => {
			buildDom();
			boot( baseConfig() );
			await flush();
			mockRenderMessages.mockClear();

			global.jQuery.trigger( eventName );
			await flush();

			expect( mockRenderMessages ).toHaveBeenCalled();
		} );
	} );

	describe( 'product-page total watcher', () => {
		test( 'subscribes to the shared watcher on a product page and forwards its pushes to the message amount', async () => {
			let notify;
			mockWatchViewedTotal.mockReturnValue( {
				get: () => '',
				subscribe: ( callback ) => {
					notify = callback;
					return () => {};
				},
			} );

			buildDom();
			boot( baseConfig( { page_context: 'product' } ) );
			await flush();

			expect( mockWatchViewedTotal ).toHaveBeenCalledWith(
				expect.objectContaining( { page_context: 'product' } ),
				'product'
			);

			notify( '42.00' );

			expect( mockUpdateMessagesAmount ).toHaveBeenCalledWith( '42.00' );
		} );

		test( 'never subscribes to the watcher off a product page', async () => {
			buildDom();
			boot( baseConfig( { page_context: 'checkout' } ) );
			await flush();

			expect( mockWatchViewedTotal ).not.toHaveBeenCalled();
		} );
	} );
} );
