const mockLoadAppleSdk = jest.fn();
jest.mock( '../utils/scriptLoaders', () => ( {
	loadAppleSdk: ( ...args ) => mockLoadAppleSdk( ...args ),
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

const mockResolveWalletTotal = jest.fn();
jest.mock( './walletTotal', () => ( {
	resolveWalletTotal: ( ...args ) => mockResolveWalletTotal( ...args ),
} ) );

const mockPayWithWallet = jest.fn();
jest.mock( './walletPayment', () => ( {
	payWithWallet: ( ...args ) => mockPayWithWallet( ...args ),
} ) );

const mockApplePayPayer = jest.fn();
const mockApplePayShippingAddress = jest.fn();
jest.mock( './walletContacts', () => ( {
	applePayPayer: ( ...args ) => mockApplePayPayer( ...args ),
	applePayShippingAddress: ( ...args ) =>
		mockApplePayShippingAddress( ...args ),
} ) );

const mockBuildApplePayRequest = jest.fn();
jest.mock( './applePayRequest', () => ( {
	APPLE_PAY_VERSION: 4,
	buildApplePayRequest: ( ...args ) => mockBuildApplePayRequest( ...args ),
} ) );

const mockWatchSheetTotal = jest.fn();
jest.mock( './applePaySheetTotal', () => ( {
	watchSheetTotal: ( ...args ) => mockWatchSheetTotal( ...args ),
} ) );

const mockRecordDomainValidation = jest.fn();
jest.mock( './applePayValidation', () => ( {
	recordDomainValidation: ( ...args ) =>
		mockRecordDomainValidation( ...args ),
} ) );

const mockRevealGateway = jest.fn();
const mockSyncGatewayVisibility = jest.fn();
jest.mock( './gatewayPlacement', () => ( {
	revealGateway: ( ...args ) => mockRevealGateway( ...args ),
	syncGatewayVisibility: ( ...args ) => mockSyncGatewayVisibility( ...args ),
} ) );

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

import { renderApplePay } from './applePay';

/**
 * Drains pending microtasks.
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

const baseConfig = ( overrides = {} ) => ( {
	apple_pay: {
		sdk_url: 'https://applepay.cdn-apple.com/jssdk.js',
		display_name: 'My Shop',
		styles: {
			product: {
				color: 'black',
				type: 'buy',
				language: 'en',
				borderRadius: '4px',
			},
			cart: {
				color: 'white',
				type: 'pay',
				language: 'de',
				borderRadius: '8px',
			},
		},
	},
	currency: 'USD',
	button_height: '48px',
	wrapper: '#express-wrapper',
	...overrides,
} );

const applePayConfig = ( overrides = {} ) => ( {
	merchantCountry: 'US',
	merchantCapabilities: [ 'supports3DS' ],
	supportedNetworks: [ 'visa' ],
	...overrides,
} );

function makeSession( overrides = {} ) {
	return {
		config: jest.fn().mockResolvedValue( applePayConfig() ),
		formatConfigForPaymentRequest: jest
			.fn()
			.mockReturnValue( 'FORMATTED_CONFIG' ),
		validateMerchant: jest
			.fn()
			.mockResolvedValue( { merchantSession: 'MERCHANT_SESSION' } ),
		...overrides,
	};
}

/**
 * Renders the button with the given inputs and returns them for reuse.
 *
 * @param {Object} [overrides] - Overrides for wrapper/config/context/session/gateway.
 * @return {Promise<Object>} The inputs the button was rendered with.
 */
async function render( overrides = {} ) {
	const args = {
		wrapper: document.createElement( 'div' ),
		config: baseConfig(),
		context: 'product',
		session: makeSession(),
		...overrides,
	};

	await renderApplePay( args );

	return args;
}

/**
 * Renders, clicks the button, and returns the ApplePaySession it constructed.
 *
 * @param {Object} [overrides] - Overrides forwarded to render().
 * @return {Promise<Object>} The render inputs plus the constructed session.
 */
async function clickAndGetSession( overrides ) {
	const args = await render( overrides );
	args.wrapper.querySelector( 'apple-pay-button' ).click();

	const instances = ApplePaySessionMock.mock.instances;

	return {
		...args,
		appleSession: instances[ instances.length - 1 ],
	};
}

const gateway = { id: 'ppcp-applepay', wrapper: '#gateway-row' };

const paymentEvent = {
	payment: {
		token: { paymentData: 'TOKEN' },
		billingContact: { givenName: 'Jane', familyName: 'Doe' },
		shippingContact: { emailAddress: 'jane@example.com' },
	},
};

let ApplePaySessionMock;
let mockJQueryTrigger;

beforeEach( () => {
	jest.clearAllMocks();
	mockHasJQuery.mockReturnValue( true );
	mockLoadAppleSdk.mockResolvedValue( undefined );
	mockBuildApplePayRequest.mockReturnValue( 'APPLE_REQUEST' );
	mockWatchSheetTotal.mockReturnValue( { get: jest.fn( () => '12.34' ) } );
	mockApplePayPayer.mockReturnValue( 'PAYER_SENTINEL' );
	mockApplePayShippingAddress.mockReturnValue( 'SHIP_SENTINEL' );
	mockResolveWalletTotal.mockResolvedValue( {
		purchaseUnits: [ { amount: '12.34' } ],
	} );
	mockPayWithWallet.mockResolvedValue( undefined );

	ApplePaySessionMock = jest.fn( function () {
		this.begin = jest.fn();
		this.completeMerchantValidation = jest.fn();
		this.completePayment = jest.fn();
		this.abort = jest.fn();
	} );
	ApplePaySessionMock.canMakePayments = jest.fn( () => true );
	ApplePaySessionMock.STATUS_SUCCESS = 'STATUS_SUCCESS';
	ApplePaySessionMock.STATUS_FAILURE = 'STATUS_FAILURE';
	window.ApplePaySession = ApplePaySessionMock;

	mockJQueryTrigger = jest.fn();
	global.jQuery = jest.fn( () => ( { trigger: mockJQueryTrigger } ) );
} );

afterEach( () => {
	delete window.ApplePaySession;
	delete global.jQuery;
} );

describe( 'renderApplePay()', () => {
	test( 'leaves the wrapper untouched and loads no SDK when the device is ineligible', async () => {
		ApplePaySessionMock.canMakePayments = jest.fn( () => false );
		const session = makeSession();

		const { wrapper } = await render( { session } );

		expect( wrapper.childElementCount ).toBe( 0 );
		expect( mockLoadAppleSdk ).not.toHaveBeenCalled();
		expect( session.config ).not.toHaveBeenCalled();
	} );

	test( 'treats a canMakePayments throw as ineligible rather than propagating', async () => {
		ApplePaySessionMock.canMakePayments = jest.fn( () => {
			throw new Error( 'not supported' );
		} );

		const { wrapper } = await render();

		expect( wrapper.childElementCount ).toBe( 0 );
	} );

	test( 'appends the button container before loadAppleSdk resolves', () => {
		// Pins the load-bearing order: boot.js's emptiness check must see a
		// child synchronously, before any await, so it never double-renders.
		const wrapper = document.createElement( 'div' );
		mockLoadAppleSdk.mockReturnValue( new Promise( () => {} ) );

		renderApplePay( {
			wrapper,
			config: baseConfig(),
			context: 'product',
			session: makeSession(),
		} );

		expect( wrapper.childElementCount ).toBe( 1 );
	} );

	test( 'asks the session to translate the resolved config for the payment request', async () => {
		const config = applePayConfig( { merchantCountry: 'DE' } );
		const session = makeSession( {
			config: jest.fn().mockResolvedValue( config ),
		} );

		await render( { session } );

		expect( session.formatConfigForPaymentRequest ).toHaveBeenCalledWith(
			config
		);
	} );

	test( 'renders nothing when the config explicitly sets isEligible to false', async () => {
		const session = makeSession( {
			config: jest
				.fn()
				.mockResolvedValue( applePayConfig( { isEligible: false } ) ),
		} );

		const { wrapper } = await render( { session } );

		expect( wrapper.childElementCount ).toBe( 0 );
	} );

	test( 'renders the button when the config has no isEligible field', async () => {
		const session = makeSession( {
			config: jest.fn().mockResolvedValue( applePayConfig() ),
		} );

		const { wrapper } = await render( { session } );

		expect( wrapper.querySelector( 'apple-pay-button' ) ).not.toBeNull();
	} );
} );

describe( 'as its own payment-method row (gateway set)', () => {
	test( 'reveals and syncs the row once eligible', async () => {
		await render( { gateway } );

		expect( mockRevealGateway ).toHaveBeenCalledWith( 'ppcp-applepay' );
		expect( mockSyncGatewayVisibility ).toHaveBeenCalledWith( {
			methodId: 'ppcp-applepay',
			wrapperSelector: '#gateway-row',
			expressSelector: '#express-wrapper',
		} );
	} );

	test( 'never reveals or syncs on the express path', async () => {
		await render();

		expect( mockRevealGateway ).not.toHaveBeenCalled();
		expect( mockSyncGatewayVisibility ).not.toHaveBeenCalled();
	} );

	test( 'never reveals or syncs a row the config vetoes', async () => {
		const session = makeSession( {
			config: jest
				.fn()
				.mockResolvedValue( applePayConfig( { isEligible: false } ) ),
		} );

		await render( { gateway, session } );

		expect( mockRevealGateway ).not.toHaveBeenCalled();
		expect( mockSyncGatewayVisibility ).not.toHaveBeenCalled();
	} );
} );

describe( 'the rendered <apple-pay-button>', () => {
	test.each( [
		[
			'product',
			{
				color: 'black',
				type: 'buy',
				language: 'en',
				borderRadius: '4px',
			},
		],
		[
			'cart',
			{
				color: 'white',
				type: 'pay',
				language: 'de',
				borderRadius: '8px',
			},
		],
	] )(
		'maps the %s context styles onto its attributes and border-radius property',
		async ( context, styles ) => {
			const { wrapper } = await render( { context } );
			const button = wrapper.querySelector( 'apple-pay-button' );

			expect( button.getAttribute( 'buttonstyle' ) ).toBe( styles.color );
			expect( button.getAttribute( 'type' ) ).toBe( styles.type );
			expect( button.getAttribute( 'locale' ) ).toBe( styles.language );
			expect(
				button.style.getPropertyValue(
					'--apple-pay-button-border-radius'
				)
			).toBe( styles.borderRadius );
		}
	);

	test( 'falls back to black/pay/en when the styles are empty strings', async () => {
		// Settings leave these empty when unset; Apple renders nothing for
		// an empty buttonstyle/type/locale.
		const config = baseConfig();
		config.apple_pay.styles.product = {
			color: '',
			type: '',
			language: '',
			borderRadius: '4px',
		};

		const { wrapper } = await render( { config, context: 'product' } );
		const button = wrapper.querySelector( 'apple-pay-button' );

		expect( button.getAttribute( 'buttonstyle' ) ).toBe( 'black' );
		expect( button.getAttribute( 'type' ) ).toBe( 'pay' );
		expect( button.getAttribute( 'locale' ) ).toBe( 'en' );
	} );

	test( 'sizes itself from the shared button height, not a per-context one', async () => {
		const config = baseConfig( { button_height: '55px' } );

		const { wrapper } = await render( { config } );
		const button = wrapper.querySelector( 'apple-pay-button' );

		expect(
			button.style.getPropertyValue( '--apple-pay-button-height' )
		).toBe( '55px' );
		expect( button.style.height ).toBe( '55px' );
	} );

	test( 'prevents the default click behavior', async () => {
		const { wrapper } = await render();
		const button = wrapper.querySelector( 'apple-pay-button' );
		const event = new MouseEvent( 'click', {
			cancelable: true,
			bubbles: true,
		} );

		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );
} );

describe( 'a click on the rendered button', () => {
	test( 'constructs the ApplePaySession and calls begin() synchronously inside the click handler', async () => {
		// No CI runner is an Apple device, so this is the only guard against a
		// refactor silently breaking Safari, which refuses to present the sheet
		// unless begin() runs in the same task as the click.
		const { wrapper } = await render();

		wrapper.querySelector( 'apple-pay-button' ).click();

		expect( ApplePaySessionMock ).toHaveBeenCalledTimes( 1 );
		const appleSession = ApplePaySessionMock.mock.instances[ 0 ];
		expect( appleSession.begin ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'builds the request from the version, the formatted config and the resolved total', async () => {
		const config = baseConfig();
		const sheetTotal = { get: jest.fn( () => '42.00' ) };
		mockWatchSheetTotal.mockReturnValue( sheetTotal );

		const { wrapper } = await render( { config, context: 'checkout' } );
		wrapper.querySelector( 'apple-pay-button' ).click();

		expect( mockBuildApplePayRequest ).toHaveBeenCalledWith(
			'FORMATTED_CONFIG',
			{
				currencyCode: config.currency,
				total: '42.00',
				displayName: config.apple_pay.display_name,
				context: 'checkout',
			}
		);
		expect( ApplePaySessionMock ).toHaveBeenCalledWith(
			4,
			'APPLE_REQUEST'
		);
	} );

	test( 'refuses to open a sheet when no total has resolved yet', async () => {
		// The guard must not latch on this refusal — a second tap, once the
		// total has resolved, still opens a sheet.
		const sheetTotal = { get: jest.fn() };
		sheetTotal.get.mockReturnValueOnce( '' ).mockReturnValue( '12.34' );
		mockWatchSheetTotal.mockReturnValue( sheetTotal );

		const { wrapper } = await render();
		const button = wrapper.querySelector( 'apple-pay-button' );

		button.click();

		expect( ApplePaySessionMock ).not.toHaveBeenCalled();
		expect( mockHandleError ).toHaveBeenCalledTimes( 1 );

		button.click();

		expect( ApplePaySessionMock ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'drops a second tap while a sheet is already open', async () => {
		const { wrapper } = await render();
		const button = wrapper.querySelector( 'apple-pay-button' );

		button.click();
		button.click();

		expect( ApplePaySessionMock ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'onvalidatemerchant', () => {
	test( 'validates the merchant, completes it, and records the domain as validated', async () => {
		const { config, session, appleSession } = await clickAndGetSession();

		appleSession.onvalidatemerchant( {
			validationURL: 'https://apple.com/validate',
		} );
		await flushPromises();

		expect( session.validateMerchant ).toHaveBeenCalledWith( {
			displayName: config.apple_pay.display_name,
			validationUrl: 'https://apple.com/validate',
		} );
		expect( appleSession.completeMerchantValidation ).toHaveBeenCalledWith(
			'MERCHANT_SESSION'
		);
		expect( mockRecordDomainValidation ).toHaveBeenCalledWith(
			config,
			true
		);
	} );

	test( 'aborts the sheet, records failed validation, reports the error, and releases the guard', async () => {
		const session = makeSession( {
			validateMerchant: jest
				.fn()
				.mockRejectedValue( new Error( 'unregistered domain' ) ),
		} );
		const { config, appleSession, wrapper } = await clickAndGetSession( {
			session,
		} );

		appleSession.onvalidatemerchant( {
			validationURL: 'https://apple.com/validate',
		} );
		await flushPromises();

		expect( appleSession.abort ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordDomainValidation ).toHaveBeenCalledWith(
			config,
			false
		);
		expect( mockHandleError ).toHaveBeenCalledTimes( 1 );

		wrapper.querySelector( 'apple-pay-button' ).click();
		expect( ApplePaySessionMock ).toHaveBeenCalledTimes( 2 );
	} );
} );

describe( 'onpaymentauthorized', () => {
	test( 'blocks the spinner synchronously, then pays with the resolved units and mapped contact', async () => {
		const config = baseConfig();
		const { session, appleSession } = await clickAndGetSession( {
			config,
		} );

		appleSession.onpaymentauthorized( paymentEvent );

		// No await yet: the spinner must already be blocked before the
		// sheet-closing await, or an idle page shows in between.
		expect( mockSpinnerBlock ).toHaveBeenCalledTimes( 1 );

		await flushPromises();

		expect( mockResolveWalletTotal ).toHaveBeenCalledWith(
			config,
			'product'
		);
		expect( mockApplePayPayer ).toHaveBeenCalledWith(
			paymentEvent.payment
		);
		expect( mockApplePayShippingAddress ).toHaveBeenCalledWith(
			paymentEvent.payment
		);
		expect( mockPayWithWallet ).toHaveBeenCalledWith( {
			config,
			context: 'product',
			session,
			fundingSource: 'apple_pay',
			paymentMethod: undefined,
			purchaseUnits: [ { amount: '12.34' } ],
			confirmData: {
				token: paymentEvent.payment.token,
				billingContact: paymentEvent.payment.billingContact,
				shippingContact: paymentEvent.payment.shippingContact,
			},
			contact: {
				payer: 'PAYER_SENTINEL',
				shippingAddress: 'SHIP_SENTINEL',
			},
		} );
		expect( appleSession.completePayment ).toHaveBeenCalledWith(
			'STATUS_SUCCESS'
		);
	} );

	test( 'pays with the configured gateway id when Apple Pay is its own row', async () => {
		const { appleSession } = await clickAndGetSession( { gateway } );

		appleSession.onpaymentauthorized( paymentEvent );
		await flushPromises();

		expect( mockPayWithWallet ).toHaveBeenCalledWith(
			expect.objectContaining( { paymentMethod: 'ppcp-applepay' } )
		);
	} );

	test( 'leaves the spinner blocked after success, since the redirect has already started', async () => {
		const { appleSession } = await clickAndGetSession();

		appleSession.onpaymentauthorized( paymentEvent );
		await flushPromises();

		expect( mockSpinnerUnblock ).not.toHaveBeenCalled();
	} );

	test( 'recovers the page and releases the guard when the payment throws', async () => {
		mockPayWithWallet.mockRejectedValueOnce( new Error( 'declined' ) );
		const { appleSession, wrapper } = await clickAndGetSession();

		appleSession.onpaymentauthorized( paymentEvent );
		await flushPromises();

		expect( appleSession.completePayment ).toHaveBeenCalledWith(
			'STATUS_FAILURE'
		);
		expect( mockSpinnerUnblock ).toHaveBeenCalledTimes( 1 );
		expect( mockJQueryTrigger ).toHaveBeenCalledWith(
			'wc_fragment_refresh'
		);
		expect( mockHandleError ).toHaveBeenCalledTimes( 1 );

		wrapper.querySelector( 'apple-pay-button' ).click();
		expect( ApplePaySessionMock ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'still pays without a spinner when jQuery is absent', async () => {
		mockHasJQuery.mockReturnValue( false );
		const { appleSession } = await clickAndGetSession();

		appleSession.onpaymentauthorized( paymentEvent );
		await flushPromises();

		expect( mockPayWithWallet ).toHaveBeenCalled();
		expect( mockSpinnerBlock ).not.toHaveBeenCalled();
	} );
} );

describe( 'oncancel', () => {
	test( 'releases the guard and refreshes the mini-cart on the product page', async () => {
		const { appleSession, wrapper } = await clickAndGetSession( {
			context: 'product',
		} );

		appleSession.oncancel();

		expect( mockSpinnerUnblock ).toHaveBeenCalledTimes( 1 );
		expect( mockJQueryTrigger ).toHaveBeenCalledWith(
			'wc_fragment_refresh'
		);
		expect( mockHandleError ).not.toHaveBeenCalled();

		wrapper.querySelector( 'apple-pay-button' ).click();
		expect( ApplePaySessionMock ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'does not refresh the cart outside the product context', async () => {
		const { appleSession } = await clickAndGetSession( {
			context: 'checkout',
		} );

		appleSession.oncancel();

		expect( mockJQueryTrigger ).not.toHaveBeenCalled();
	} );

	test( 'skips refreshing without jQuery, and does not throw with no spinner', async () => {
		mockHasJQuery.mockReturnValue( false );
		const { appleSession } = await clickAndGetSession( {
			context: 'product',
		} );

		expect( () => appleSession.oncancel() ).not.toThrow();
		expect( mockJQueryTrigger ).not.toHaveBeenCalled();
	} );
} );
