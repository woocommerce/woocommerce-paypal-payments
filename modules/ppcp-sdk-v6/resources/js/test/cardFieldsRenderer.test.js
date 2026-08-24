const mockCardFieldStyles = jest.fn( () => ( { color: 'rgb(0, 0, 0)' } ) );
jest.mock( '../cardFields/cardFieldStyles', () => ( {
	cardFieldStyles: ( field ) => mockCardFieldStyles( field ),
} ) );

const mockHide = jest.fn();
jest.mock(
	'@ppcp-button/Helper/Hiding',
	() => ( { hide: ( ...args ) => mockHide( ...args ) } ),
	{ virtual: true }
);

const mockSpinnerBlock = jest.fn();
const mockSpinnerUnblock = jest.fn();
jest.mock(
	'@ppcp-button/Helper/Spinner',
	() => ( {
		__esModule: true,
		default: {
			fullPage: () => ( {
				block: mockSpinnerBlock,
				unblock: mockSpinnerUnblock,
			} ),
		},
	} ),
	{ virtual: true }
);

const mockLoadSdkV6 = jest.fn();
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

const mockCreateCardOrder = jest.fn();
const mockApproveCardOrder = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	createCardOrder: ( ...args ) => mockCreateCardOrder( ...args ),
	approveCardOrder: ( ...args ) => mockApproveCardOrder( ...args ),
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

const mockCreateCardSetupToken = jest.fn();
const mockExchangeSetupToken = jest.fn();
jest.mock( '../sessions/freeTrialSave', () => ( {
	createCardSetupToken: ( ...args ) => mockCreateCardSetupToken( ...args ),
	exchangeSetupToken: ( ...args ) => mockExchangeSetupToken( ...args ),
} ) );

import { initCardFields } from '../cardFields/renderer';

/**
 * Drains all pending microtasks (unlike chained `await Promise.resolve()`,
 * this doesn't require knowing exactly how many promise hops the code
 * under test awaits internally).
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

/**
 * Builds the classic-checkout DOM this module expects: the card gateway
 * radio plus the three (number/expiry/cvv) WC input fields it replaces.
 *
 * @param {string} selectedGateway - The initially checked payment_method value.
 */
function buildCheckoutDom( selectedGateway = 'ppcp-credit-card-gateway' ) {
	document.body.innerHTML = `
		<form class="checkout">
			<input type="radio" name="payment_method" value="ppcp-gateway" ${
				selectedGateway === 'ppcp-gateway' ? 'checked' : ''
			} />
			<input type="radio" name="payment_method" value="ppcp-credit-card-gateway" ${
				selectedGateway === 'ppcp-credit-card-gateway' ? 'checked' : ''
			} />
			<div class="input-wrapper"><input id="ppcp-credit-card-gateway-card-number" /></div>
			<div class="input-wrapper"><input id="ppcp-credit-card-gateway-card-expiry" /></div>
			<div class="input-wrapper"><input id="ppcp-credit-card-gateway-card-cvc" /></div>
			<button id="place_order" type="button">Place order</button>
		</form>
	`;
}

const baseConfig = ( overrides = {} ) => ( {
	card_fields: {
		enabled: true,
		payment_method: 'ppcp-credit-card-gateway',
		funding_source: 'card',
		fields: {
			number: '#ppcp-credit-card-gateway-card-number',
			expiry: '#ppcp-credit-card-gateway-card-expiry',
			cvv: '#ppcp-credit-card-gateway-card-cvc',
			name: null,
		},
	},
	...overrides,
} );

function makeCardSession( { state = 'succeeded' } = {} ) {
	return {
		createCardFieldsComponent: jest.fn( () =>
			document.createElement( 'div' )
		),
		submit: jest.fn().mockResolvedValue( { state, data: {} } ),
	};
}

let bodyHandlers = {};

/**
 * Simulates jQuery(document.body).trigger(event) for handlers registered
 * through the mock below.
 *
 * @param {string} event - The event name.
 */
function triggerBodyEvent( event ) {
	( bodyHandlers[ event ] || [] ).forEach( ( handler ) => handler() );
}

beforeEach( () => {
	jest.clearAllMocks();
	mockHasJQuery.mockReturnValue( true );
	mockCreateCardSetupToken.mockReset();
	mockExchangeSetupToken.mockReset();
	bodyHandlers = {};
	global.jQuery = jest.fn( () => ( {
		on: ( event, handler ) => {
			bodyHandlers[ event ] = bodyHandlers[ event ] || [];
			bodyHandlers[ event ].push( handler );
		},
	} ) );
} );

afterEach( () => {
	delete global.jQuery;
	document.body.innerHTML = '';
} );

describe( 'initCardFields', () => {
	test( 'does nothing when card fields are disabled', async () => {
		buildCheckoutDom();
		await initCardFields(
			baseConfig( { card_fields: { enabled: false } } )
		);

		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test( 'does nothing when the expected WC input fields are missing', async () => {
		document.body.innerHTML = '<form class="checkout"></form>';
		await initCardFields( baseConfig() );

		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test( 'mounts number/expiry/cvv into the existing WC inputs and hides them, when the card gateway is preselected', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const cardSession = makeCardSession();
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );

		await initCardFields( baseConfig() );
		// ensureCardSession() is fired-and-forgotten from the preselected check; flush microtasks.
		await flushPromises();

		expect( cardSession.createCardFieldsComponent ).toHaveBeenCalledTimes(
			3
		);
		expect( mockHide ).toHaveBeenCalledTimes( 3 );
		// The v6 SDK rejects the option object without a valid `type`
		// (number|expiry|cvv); a `field` key is silently wrong at runtime.
		for ( const type of [ 'number', 'expiry', 'cvv' ] ) {
			expect(
				cardSession.createCardFieldsComponent
			).toHaveBeenCalledWith( expect.objectContaining( { type } ) );
		}
	} );

	test( 'never mounts the name field even when fields.name points to a WC input, since v6 has no name field component', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<input id="ppcp-credit-card-gateway-card-name" value="Jane Doe" />'
		);
		const cardSession = makeCardSession();
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );

		await initCardFields(
			baseConfig( {
				card_fields: {
					...baseConfig().card_fields,
					fields: {
						...baseConfig().card_fields.fields,
						name: '#ppcp-credit-card-gateway-card-name',
					},
				},
			} )
		);
		await flushPromises();

		expect(
			cardSession.createCardFieldsComponent
		).toHaveBeenCalledTimes( 3 );
		expect(
			cardSession.createCardFieldsComponent
		).not.toHaveBeenCalledWith(
			expect.objectContaining( { type: 'name' } )
		);
		const nameInput = document.querySelector(
			'#ppcp-credit-card-gateway-card-name'
		);
		expect( nameInput.hidden ).toBe( false );
	} );

	test( "sizes the mounted field to the original input's own box, since style.input only styles what is inside it", async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const numberInput = document.querySelector(
			'#ppcp-credit-card-gateway-card-number'
		);
		numberInput.style.width = '300px';
		numberInput.style.height = '45px';

		const cardSession = makeCardSession();
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );

		await initCardFields( baseConfig() );
		await flushPromises();

		expect( numberInput.nextSibling.style.width ).toBe( '300px' );
		expect( numberInput.nextSibling.style.height ).toBe( '45px' );
	} );

	test( 're-attaches to the fresh #payment DOM after WC replaces it on updated_checkout', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const firstSession = makeCardSession();
		const secondSession = makeCardSession();
		mockLoadSdkV6
			.mockResolvedValueOnce( {
				createCardFieldsOneTimePaymentSession: () => firstSession,
			} )
			.mockResolvedValueOnce( {
				createCardFieldsOneTimePaymentSession: () => secondSession,
			} );

		await initCardFields( baseConfig() );
		await flushPromises();

		expect( firstSession.createCardFieldsComponent ).toHaveBeenCalledTimes(
			3
		);

		// WC's update_checkout AJAX replaces the whole #payment box
		// (inputs + #place_order) with brand new nodes on every call.
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		triggerBodyEvent( 'updated_checkout' );
		await flushPromises();

		expect( secondSession.createCardFieldsComponent ).toHaveBeenCalledTimes(
			3
		);

		const newPlaceOrder = document.querySelector( '#place_order' );
		let nativeSubmits = 0;
		newPlaceOrder.addEventListener( 'click', () => nativeSubmits++ );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		newPlaceOrder.click();
		await flushPromises();

		// The new button must be intercepted (not a stale, unbound node).
		expect( mockCreateCardOrder ).toHaveBeenCalled();
		expect( secondSession.submit ).toHaveBeenCalledWith( 'CARDORDER1' );
		expect( nativeSubmits ).toBe( 1 );
	} );

	test( 'does not re-bind when updated_checkout fires without actually replacing the DOM', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const cardSession = makeCardSession();
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );

		const placeOrder = document.querySelector( '#place_order' );
		const addEventListenerSpy = jest.spyOn(
			placeOrder,
			'addEventListener'
		);

		await initCardFields( baseConfig() );
		await flushPromises();
		expect( addEventListenerSpy ).toHaveBeenCalledTimes( 1 );
		cardSession.createCardFieldsComponent.mockClear();

		// Same #place_order node as before (DOM wasn't actually replaced):
		// attach() must no-op, not remount fields or double-bind a second
		// click listener onto it.
		triggerBodyEvent( 'updated_checkout' );
		await flushPromises();

		expect( cardSession.createCardFieldsComponent ).not.toHaveBeenCalled();
		expect( addEventListenerSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'keeps the mounted session usable after an updated_checkout that changed nothing', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const firstSession = makeCardSession();
		const secondSession = makeCardSession();
		mockLoadSdkV6
			.mockResolvedValueOnce( {
				createCardFieldsOneTimePaymentSession: () => firstSession,
			} )
			.mockResolvedValueOnce( {
				createCardFieldsOneTimePaymentSession: () => secondSession,
			} );

		await initCardFields( baseConfig() );
		await flushPromises();
		expect( firstSession.createCardFieldsComponent ).toHaveBeenCalledTimes(
			3
		);

		// WC skips replacing an unchanged fragment, so the mounted fields and
		// #place_order survive and the session must survive with them.
		triggerBodyEvent( 'updated_checkout' );
		await flushPromises();

		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		// The submitting session must be the one holding the buyer's fields.
		expect( firstSession.submit ).toHaveBeenCalledWith( 'CARDORDER1' );
		expect( secondSession.submit ).not.toHaveBeenCalled();
	} );

	test( 'lets the native click through untouched when a saved payment token is selected', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<input type="radio" name="wc-ppcp-credit-card-gateway-payment-token" value="123" checked />'
		);
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => makeCardSession(),
		} );

		await initCardFields( baseConfig() );

		const event = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		document.querySelector( '#place_order' ).dispatchEvent( event );
		await Promise.resolve();

		expect( event.defaultPrevented ).toBe( false );
		expect( mockCreateCardOrder ).not.toHaveBeenCalled();
	} );

	test( 'lets the native click through untouched when a different gateway is selected', async () => {
		buildCheckoutDom( 'ppcp-gateway' );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => makeCardSession(),
		} );

		await initCardFields( baseConfig() );

		const event = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		document.querySelector( '#place_order' ).dispatchEvent( event );
		await Promise.resolve();

		expect( event.defaultPrevented ).toBe( false );
		expect( mockCreateCardOrder ).not.toHaveBeenCalled();
	} );

	test( 'a new-card submission creates the order, submits the card session, approves it, then re-clicks place_order', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const cardSession = makeCardSession( { state: 'succeeded' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		await initCardFields( baseConfig() );
		await flushPromises();

		const placeOrder = document.querySelector( '#place_order' );
		let nativeSubmits = 0;
		placeOrder.addEventListener( 'click', () => nativeSubmits++ );

		placeOrder.click();
		// Flush the async submit chain (createCardOrder -> submit -> approveCardOrder).
		await flushPromises();

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			baseConfig(),
			'checkout',
			'',
			false
		);
		expect( cardSession.submit ).toHaveBeenCalledWith( 'CARDORDER1' );
		expect( mockApproveCardOrder ).toHaveBeenCalledWith(
			expect.anything(),
			'CARDORDER1'
		);
		// One native submit from our own re-click; the guard must have let
		// that second click through instead of re-intercepting it.
		expect( nativeSubmits ).toBe( 1 );
		expect( mockHandleError ).not.toHaveBeenCalled();
	} );

	test( 'forwards the cardholder name from the plain WC input to createCardOrder', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<input id="ppcp-credit-card-gateway-card-name" value="Jane Doe" />'
		);
		const cardSession = makeCardSession( { state: 'succeeded' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		const config = baseConfig( {
			card_fields: {
				...baseConfig().card_fields,
				fields: {
					...baseConfig().card_fields.fields,
					name: '#ppcp-credit-card-gateway-card-name',
				},
			},
		} );

		await initCardFields( config );
		await flushPromises();

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			config,
			'checkout',
			'Jane Doe',
			false
		);
	} );

	test( 'submits the card session with the billing address when the checkout form has a postcode', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<input id="billing_postcode" value="90001" /><input id="billing_country" value="US" />'
		);
		const cardSession = makeCardSession( { state: 'succeeded' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		await initCardFields( baseConfig() );
		await flushPromises();

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		expect( cardSession.submit ).toHaveBeenCalledWith( 'CARDORDER1', {
			billingAddress: { postalCode: '90001', countryCode: 'US' },
		} );
	} );

	test( 'passes save_payment_method true to createCardOrder when the buyer checks the save-card box', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" checked />'
		);
		const cardSession = makeCardSession( { state: 'succeeded' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		await initCardFields( baseConfig() );
		await flushPromises();

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			baseConfig(),
			'checkout',
			'',
			true
		);
	} );

	test( 'passes save_payment_method false to createCardOrder when the save-card box is unchecked', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		document.body.insertAdjacentHTML(
			'beforeend',
			'<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" />'
		);
		const cardSession = makeCardSession( { state: 'succeeded' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );
		mockApproveCardOrder.mockResolvedValue( undefined );

		await initCardFields( baseConfig() );
		await flushPromises();

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			baseConfig(),
			'checkout',
			'',
			false
		);
	} );

	test( 'a failed card session submit surfaces the error and does not click place_order again', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const cardSession = makeCardSession( { state: 'failed' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );

		await initCardFields( baseConfig() );
		await flushPromises();

		const placeOrder = document.querySelector( '#place_order' );
		let nativeSubmits = 0;
		placeOrder.addEventListener( 'click', () => nativeSubmits++ );

		placeOrder.click();
		await flushPromises();

		expect( mockHandleError ).toHaveBeenCalled();
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
		expect( nativeSubmits ).toBe( 0 );
	} );

	test( 'a canceled 3DS challenge is silent (no error) and does not click place_order again', async () => {
		buildCheckoutDom( 'ppcp-credit-card-gateway' );
		const cardSession = makeCardSession( { state: 'canceled' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsOneTimePaymentSession: () => cardSession,
		} );
		mockCreateCardOrder.mockResolvedValue( { orderId: 'CARDORDER1' } );

		await initCardFields( baseConfig() );
		await flushPromises();

		const placeOrder = document.querySelector( '#place_order' );
		let nativeSubmits = 0;
		placeOrder.addEventListener( 'click', () => nativeSubmits++ );

		placeOrder.click();
		await flushPromises();

		expect( mockHandleError ).not.toHaveBeenCalled();
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
		expect( nativeSubmits ).toBe( 0 );
	} );

	describe( 'free-trial ($0 subscription) cart', () => {
		function freeTrialConfig( overrides = {} ) {
			return baseConfig( { is_free_trial_cart: true, ...overrides } );
		}

		test( 'mounts the fields from the card save session instead of the one-time session', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			const cardSession = makeCardSession();
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsSavePaymentSession: () => cardSession,
			} );

			await initCardFields( freeTrialConfig() );
			await flushPromises();

			expect( cardSession.createCardFieldsComponent ).toHaveBeenCalledTimes(
				3
			);
		} );

		test( 'a new-card submission creates a setup token, confirms it through the save session, exchanges it, then re-clicks place_order', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			const cardSession = makeCardSession( { state: 'succeeded' } );
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsSavePaymentSession: () => cardSession,
			} );
			mockCreateCardSetupToken.mockResolvedValue( 'SETUP1' );
			mockExchangeSetupToken.mockResolvedValue( undefined );

			await initCardFields( freeTrialConfig() );
			await flushPromises();

			const placeOrder = document.querySelector( '#place_order' );
			let nativeSubmits = 0;
			placeOrder.addEventListener( 'click', () => nativeSubmits++ );

			placeOrder.click();
			await flushPromises();

			expect( mockCreateCardSetupToken ).toHaveBeenCalledWith(
				freeTrialConfig()
			);
			expect( cardSession.submit ).toHaveBeenCalledWith( 'SETUP1' );
			expect( mockExchangeSetupToken ).toHaveBeenCalledWith(
				freeTrialConfig(),
				'SETUP1'
			);
			expect( mockCreateCardOrder ).not.toHaveBeenCalled();
			expect( mockApproveCardOrder ).not.toHaveBeenCalled();
			expect( nativeSubmits ).toBe( 1 );
			expect( mockHandleError ).not.toHaveBeenCalled();
		} );

		test( 'a canceled 3DS challenge on the save session is silent and does not re-click place_order', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			const cardSession = makeCardSession( { state: 'canceled' } );
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsSavePaymentSession: () => cardSession,
			} );
			mockCreateCardSetupToken.mockResolvedValue( 'SETUP1' );

			await initCardFields( freeTrialConfig() );
			await flushPromises();

			const placeOrder = document.querySelector( '#place_order' );
			let nativeSubmits = 0;
			placeOrder.addEventListener( 'click', () => nativeSubmits++ );

			placeOrder.click();
			await flushPromises();

			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
			expect( mockHandleError ).not.toHaveBeenCalled();
			expect( nativeSubmits ).toBe( 0 );
		} );

		test( 'a failed save session surfaces the error and does not re-click place_order', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			const cardSession = makeCardSession( { state: 'failed' } );
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsSavePaymentSession: () => cardSession,
			} );
			mockCreateCardSetupToken.mockResolvedValue( 'SETUP1' );

			await initCardFields( freeTrialConfig() );
			await flushPromises();

			const placeOrder = document.querySelector( '#place_order' );
			let nativeSubmits = 0;
			placeOrder.addEventListener( 'click', () => nativeSubmits++ );

			placeOrder.click();
			await flushPromises();

			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
			expect( mockHandleError ).toHaveBeenCalled();
			expect( nativeSubmits ).toBe( 0 );
		} );
	} );

	describe( 'subscription cart (force-save the tokenization checkbox)', () => {
		function subscriptionConfig( overrides = {} ) {
			return baseConfig( {
				card_fields: {
					...baseConfig().card_fields,
					has_subscriptions: true,
					is_vaulting_enabled: true,
					...overrides,
				},
			} );
		}

		test( 'checks and disables the save-to-account checkbox on attach when the cart has a subscription and vaulting is enabled', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			document.body.insertAdjacentHTML(
				'beforeend',
				'<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" />'
			);
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsOneTimePaymentSession: () =>
					makeCardSession(),
			} );

			await initCardFields( subscriptionConfig() );
			await flushPromises();

			const checkbox = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			expect( checkbox.checked ).toBe( true );
			expect( checkbox.disabled ).toBe( true );
		} );

		test( 'leaves the save-to-account checkbox untouched when the cart has no subscription', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			document.body.insertAdjacentHTML(
				'beforeend',
				'<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" />'
			);
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsOneTimePaymentSession: () =>
					makeCardSession(),
			} );

			await initCardFields(
				subscriptionConfig( { has_subscriptions: false } )
			);
			await flushPromises();

			const checkbox = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			expect( checkbox.checked ).toBe( false );
			expect( checkbox.disabled ).toBe( false );
		} );

		test( 'leaves the save-to-account checkbox untouched when vaulting is disabled', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			document.body.insertAdjacentHTML(
				'beforeend',
				'<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" />'
			);
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsOneTimePaymentSession: () =>
					makeCardSession(),
			} );

			await initCardFields(
				subscriptionConfig( { is_vaulting_enabled: false } )
			);
			await flushPromises();

			const checkbox = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			expect( checkbox.checked ).toBe( false );
			expect( checkbox.disabled ).toBe( false );
		} );

		test( 'is a no-op when the checkbox is absent from the DOM (vaulting off server-side)', async () => {
			buildCheckoutDom( 'ppcp-credit-card-gateway' );
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsOneTimePaymentSession: () =>
					makeCardSession(),
			} );

			await expect(
				initCardFields( subscriptionConfig() )
			).resolves.not.toThrow();
			await flushPromises();

			expect(
				document.querySelector(
					'#wc-ppcp-credit-card-gateway-new-payment-method'
				)
			).toBeNull();
		} );

		test( 'force-checks the checkbox again when payment_method_selected fires with the card gateway now selected', async () => {
			// The checkbox is only in the DOM once the card gateway's fields
			// show, which selecting the method is what does; simulate that
			// by starting on a different gateway with no checkbox present.
			buildCheckoutDom( 'ppcp-gateway' );
			mockLoadSdkV6.mockResolvedValue( {
				createCardFieldsOneTimePaymentSession: () =>
					makeCardSession(),
			} );

			await initCardFields( subscriptionConfig() );
			await flushPromises();

			document.querySelector(
				'input[value="ppcp-gateway"]'
			).checked = false;
			document.querySelector(
				'input[value="ppcp-credit-card-gateway"]'
			).checked = true;
			document.body.insertAdjacentHTML(
				'beforeend',
				'<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" />'
			);

			triggerBodyEvent( 'payment_method_selected' );
			await flushPromises();

			const checkbox = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			expect( checkbox.checked ).toBe( true );
			expect( checkbox.disabled ).toBe( true );
		} );
	} );
} );
