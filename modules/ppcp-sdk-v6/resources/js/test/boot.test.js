import '@ppcp-test/helpers/silenceConsole';

const mockHasJQuery = jest.fn();
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

const mockSetErrorLabels = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	setErrorLabels: ( ...args ) => mockSetErrorLabels( ...args ),
} ) );

const mockPlaceExpressButtons = jest.fn();
const mockSetExpressButtonsFailed = jest.fn();
jest.mock( '../methods/gatewayPlacement', () => ( {
	placeExpressButtons: ( ...args ) => mockPlaceExpressButtons( ...args ),
	setExpressButtonsFailed: ( ...args ) =>
		mockSetExpressButtonsFailed( ...args ),
} ) );

const mockLoadSdkV6 = jest.fn();
const mockOnSdkInstanceChange = jest.fn();
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );
jest.mock( '../tokenRefresh', () => ( {
	onSdkInstanceChange: ( ...args ) => mockOnSdkInstanceChange( ...args ),
} ) );

const mockInitCardButton = jest.fn();
jest.mock( '../cardButton/renderCardButton', () => ( {
	initCardButton: ( ...args ) => mockInitCardButton( ...args ),
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
jest.mock( '../methods/renderMethods', () => ( {
	renderMethods: ( ...args ) => mockRenderWallets( ...args ),
} ) );

const mockIsWalletEnabled = jest.fn();
const mockMethodConfig = jest.fn();
jest.mock( '../methods/methodRegistry', () => ( {
	isMethodEnabled: ( ...args ) => mockIsWalletEnabled( ...args ),
	methodConfig: ( ...args ) => mockMethodConfig( ...args ),
	MERCHANT_PRESENTED_METHODS: [ 'googlepay' ],
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

const mockInitProductButtonGate = jest.fn();
jest.mock( '../utils/productButtonGate', () => ( {
	initProductButtonGate: ( ...args ) => mockInitProductButtonGate( ...args ),
} ) );

const mockWatchProductAmount = jest.fn();
jest.mock( '../messages/productAmount', () => ( {
	watchProductAmount: ( ...args ) => mockWatchProductAmount( ...args ),
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

let instanceChangeCallback;

beforeEach( () => {
	jest.useFakeTimers();
	jest.clearAllMocks();
	mockRenderButtons.mockReset();

	mockHasJQuery.mockReturnValue( true );
	global.jQuery = createFakeJQuery();

	mockLoadSdkV6.mockResolvedValue( {} );
	mockOnSdkInstanceChange.mockImplementation( ( callback ) => {
		instanceChangeCallback = callback;
		return () => {};
	} );
	mockInitCardButton.mockResolvedValue();
	mockMethodConfig.mockReturnValue( undefined );
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

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 1 );

			global.jQuery.trigger( 'updated_checkout' );
			await jest.advanceTimersByTimeAsync( 300 );

			expect( mockCheckEligibility ).toHaveBeenCalledTimes( 2 );
		} );
	} );

	describe( 'Venmo button visibility', () => {
		beforeEach( () => {
			mockCheckEligibility.mockResolvedValue( {
				paypal: true,
				venmo: true,
				paylater: false,
				payLaterDetails: null,
			} );
		} );

		const sessionMethods = () =>
			mockRenderButtons.mock.calls[ 0 ][ 0 ].sessions;

		test( 'creates a Venmo session for an eligible buyer when the context flag is true', async () => {
			buildDom();
			boot( baseConfig( { venmo_button: { checkout: true } } ) );
			await flush();

			expect( Object.keys( sessionMethods() ) ).toContain( 'venmo' );
		} );

		test.each( [
			[ 'the flag for the context is false', { checkout: false } ],
			[ 'only another context has the flag', { cart: true } ],
			[ 'venmo_button is missing', undefined ],
		] )(
			'creates no Venmo session for an eligible buyer when %s',
			async ( label, venmoButton ) => {
				buildDom();
				boot( baseConfig( { venmo_button: venmoButton } ) );
				await flush();

				const methods = Object.keys( sessionMethods() );
				expect( methods ).not.toContain( 'venmo' );
				expect( methods ).toContain( 'paypal' );
			}
		);
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

	describe( 'after the SDK instance changed', () => {
		const oldSdk = { name: 'old' };
		const newSdk = { name: 'new' };
		const GATEWAY_WRAPPER_SELECTOR = '#ppcp-googlepay-gateway';
		const CARD_WRAPPER_SELECTOR = '#ppcp-card-button';

		// Every wrapper holds a button of the old instance, and a render pass
		// skips a wrapper that is not empty.
		function buildDomWithButtons() {
			document.body.innerHTML = [
				WRAPPER_SELECTOR,
				MINI_CART_WRAPPER_SELECTOR,
				GATEWAY_WRAPPER_SELECTOR,
				CARD_WRAPPER_SELECTOR,
			]
				.map(
					( selector ) =>
						`<div id="${ selector.slice(
							1
						) }"><button></button></div>`
				)
				.join( '' );
		}

		async function bootAndChangeInstance( overrides = {} ) {
			let currentSdk = oldSdk;
			mockLoadSdkV6.mockImplementation( async () => currentSdk );
			mockCreateSession.mockImplementation( ( sdk ) => ( { sdk } ) );
			mockMethodConfig.mockReturnValue( {
				gateway: { wrapper: GATEWAY_WRAPPER_SELECTOR },
			} );

			buildDomWithButtons();
			boot(
				baseConfig( {
					card_button: { row: true, wrapper: CARD_WRAPPER_SELECTOR },
					...overrides,
				} )
			);
			await flush();
			mockRenderButtons.mockClear();
			mockInitCardButton.mockClear();

			currentSdk = newSdk;
			instanceChangeCallback( newSdk );
			await flush();
		}

		test( 'draws the page and mini-cart buttons again with sessions from the new instance', async () => {
			await bootAndChangeInstance();

			const wrapperIds = mockRenderButtons.mock.calls.map(
				( [ options ] ) => options.wrapper.id
			);
			expect( wrapperIds.sort() ).toEqual(
				[
					WRAPPER_SELECTOR.slice( 1 ),
					MINI_CART_WRAPPER_SELECTOR.slice( 1 ),
				].sort()
			);
			mockRenderButtons.mock.calls.forEach( ( [ options ] ) => {
				expect( options.sessions ).toEqual( { paypal: { sdk: newSdk } } );
			} );
		} );

		test( 'clears the wallet gateway row so the wallet renders again', async () => {
			await bootAndChangeInstance();

			expect(
				document.querySelector( GATEWAY_WRAPPER_SELECTOR ).childElementCount
			).toBe( 0 );
			expect( mockRenderWallets ).toHaveBeenCalledWith(
				expect.objectContaining( { sessions: { paypal: { sdk: newSdk } } } )
			);
		} );

		test( 'clears the card button wrapper and renders it again with sessions from the new instance', async () => {
			await bootAndChangeInstance();

			expect(
				document.querySelector( CARD_WRAPPER_SELECTOR ).childElementCount
			).toBe( 0 );
			expect( mockInitCardButton ).toHaveBeenCalledTimes( 1 );

			const ensureSessions = mockInitCardButton.mock.calls[ 0 ][ 1 ];
			const { map } = await ensureSessions( 'checkout' );
			expect( map ).toEqual( { paypal: { sdk: newSdk } } );
		} );

		test( 'leaves a card button wrapper alone when the page has no card button row', async () => {
			await bootAndChangeInstance( { card_button: { row: false } } );

			expect(
				document.querySelector( CARD_WRAPPER_SELECTOR ).childElementCount
			).toBe( 1 );
		} );
	} );

	describe( 'express button placement', () => {
		const drawButton = ( { wrapper } ) => {
			wrapper.appendChild( document.createElement( 'paypal-button' ) );
		};

		test( 'places the express buttons once on the initial render, with the config', async () => {
			const config = baseConfig();
			buildDom();
			boot( config );
			await flush();

			expect( mockPlaceExpressButtons ).toHaveBeenCalledTimes( 1 );
			expect( mockPlaceExpressButtons ).toHaveBeenCalledWith( config );
		} );

		test( 'reports a failure when the SDK fails to load', async () => {
			mockLoadSdkV6.mockRejectedValueOnce( new Error( 'sdk failed' ) );
			mockRenderButtons.mockImplementation( drawButton );

			buildDom();
			boot( baseConfig() );
			await flush();

			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledTimes( 1 );
			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledWith( true );
		} );

		test( 'reports a failure when the buttons draw nothing', async () => {
			buildDom();
			boot( baseConfig() );
			await flush();

			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledTimes( 1 );
			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledWith( true );
		} );

		test( 'reports success when a button is drawn', async () => {
			mockRenderButtons.mockImplementation( drawButton );

			buildDom();
			boot( baseConfig() );
			await flush();

			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledTimes( 1 );
			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledWith( false );
		} );

		test( 'reports success when a button is drawn but a wallet then fails to render', async () => {
			mockRenderButtons.mockImplementation( drawButton );
			mockRenderWallets.mockRejectedValue( new Error( 'wallet failed' ) );

			buildDom();
			boot( baseConfig() );
			await flush();

			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledTimes( 1 );
			expect( mockSetExpressButtonsFailed ).toHaveBeenCalledWith( false );
		} );

		test( 'never reports for the mini-cart target when there is no page context', async () => {
			mockRenderButtons.mockImplementation( drawButton );

			buildDom();
			boot( baseConfig( { page_context: '' } ) );
			await flush();

			expect( mockRenderButtons ).toHaveBeenCalledTimes( 1 );
			expect( mockSetExpressButtonsFailed ).not.toHaveBeenCalled();
		} );

		test( 'does not report when the page wrapper is already populated', async () => {
			buildDom();
			document
				.querySelector( WRAPPER_SELECTOR )
				.appendChild( document.createElement( 'paypal-button' ) );
			boot( baseConfig() );
			await flush();

			expect( mockSetExpressButtonsFailed ).not.toHaveBeenCalled();
		} );

		test( 'does not report when the page wrapper is absent', async () => {
			document.body.innerHTML = `<div id="${ MINI_CART_WRAPPER_SELECTOR.slice(
				1
			) }"></div>`;
			boot( baseConfig() );
			await flush();

			expect( mockSetExpressButtonsFailed ).not.toHaveBeenCalled();
		} );

		test( 'a DOM-replacing event retries after a failed pass and reports success once a button is drawn', async () => {
			mockLoadSdkV6.mockRejectedValueOnce( new Error( 'sdk failed' ) );
			mockRenderButtons.mockImplementation( drawButton );

			buildDom();
			boot( baseConfig() );
			await flush();
			expect( mockSetExpressButtonsFailed ).toHaveBeenLastCalledWith(
				true
			);

			global.jQuery.trigger( 'updated_checkout' );
			await flush();

			expect( mockSetExpressButtonsFailed ).toHaveBeenLastCalledWith(
				false
			);
		} );
	} );

	describe( 'product-page total watcher', () => {
		test( 'subscribes to the shared watcher on a product page opted into cart simulation, forwarding its pushes to the message amount', async () => {
			let notify;
			mockWatchViewedTotal.mockReturnValue( {
				get: () => '',
				subscribe: ( callback ) => {
					notify = callback;
					return () => {};
				},
			} );

			buildDom();
			boot(
				baseConfig( {
					page_context: 'product',
					messages: { use_cart_simulation: true },
				} )
			);
			await flush();

			expect( mockWatchViewedTotal ).toHaveBeenCalledWith(
				expect.objectContaining( { page_context: 'product' } ),
				'product'
			);
			expect( mockInitProductButtonGate ).toHaveBeenCalledWith(
				expect.objectContaining( { page_context: 'product' } )
			);

			notify( '42.00' );

			expect( mockUpdateMessagesAmount ).toHaveBeenCalledWith( '42.00' );
		} );

		test( 'prices locally on a product page when cart simulation is not requested, issuing no simulate-cart watch', async () => {
			buildDom();
			boot(
				baseConfig( {
					page_context: 'product',
					messages: { use_cart_simulation: false },
				} )
			);
			await flush();

			expect( mockWatchProductAmount ).toHaveBeenCalledWith(
				expect.objectContaining( { page_context: 'product' } ),
				expect.any( Function )
			);
			expect( mockWatchViewedTotal ).not.toHaveBeenCalled();

			const onChange = mockWatchProductAmount.mock.calls[ 0 ][ 1 ];
			onChange( '77.00' );

			expect( mockUpdateMessagesAmount ).toHaveBeenCalledWith( '77.00' );
		} );

		test( 'neither watcher runs off a product page', async () => {
			buildDom();
			boot( baseConfig( { page_context: 'checkout' } ) );
			await flush();

			expect( mockWatchProductAmount ).not.toHaveBeenCalled();
			expect( mockWatchViewedTotal ).not.toHaveBeenCalled();
		} );
	} );
} );
