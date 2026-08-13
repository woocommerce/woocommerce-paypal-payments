const mockLoadGoogleSdk = jest.fn();
jest.mock( '../utils/scriptLoaders', () => ( {
	loadGoogleSdk: ( ...args ) => mockLoadGoogleSdk( ...args ),
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

const mockGooglePayPayer = jest.fn();
const mockGooglePayShippingAddress = jest.fn();
jest.mock( './walletContacts', () => ( {
	googlePayPayer: ( ...args ) => mockGooglePayPayer( ...args ),
	googlePayShippingAddress: ( ...args ) =>
		mockGooglePayShippingAddress( ...args ),
} ) );

const mockBuildReadyToPayRequest = jest.fn();
const mockBuildPaymentDataRequest = jest.fn();
jest.mock( './googlePayRequest', () => ( {
	buildReadyToPayRequest: ( ...args ) =>
		mockBuildReadyToPayRequest( ...args ),
	buildPaymentDataRequest: ( ...args ) =>
		mockBuildPaymentDataRequest( ...args ),
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

import { renderGooglePay } from './googlePay';

/**
 * Drains pending microtasks.
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

const sessionConfig = {
	allowedPaymentMethods: [
		{ type: 'CARD', id: 'first' },
		{ type: 'CARD', id: 'second' },
	],
	merchantInfo: { merchantId: 'M1' },
};

const baseConfig = ( overrides = {} ) => ( {
	google_pay: {
		sdk_url: 'https://pay.google.com/gp/p/js/pay.js',
		environment: 'TEST',
		styles: {
			product: {
				color: 'black',
				type: 'buy',
				language: 'en',
				borderRadius: 4,
			},
			cart: {
				color: 'white',
				type: 'pay',
				language: 'de',
				borderRadius: 8,
			},
		},
	},
	buyer_country: 'US',
	currency: 'USD',
	...overrides,
} );

function makeSession() {
	return {
		getGooglePayConfig: jest.fn().mockResolvedValue( { raw: true } ),
		formatConfigForPaymentRequest: jest.fn( () => sessionConfig ),
	};
}

let paymentsClientOptions;
let mockIsReadyToPay;
let mockCreateButton;
let mockLoadPaymentData;
let createButtonOptions;
let mockPaymentsClient;

/**
 * Installs a fake window.google.payments.api.PaymentsClient that records
 * its constructor options and the options passed to createButton().
 */
function installPaymentsClient() {
	mockIsReadyToPay = jest.fn().mockResolvedValue( { result: true } );
	mockCreateButton = jest.fn( ( options ) => {
		createButtonOptions = options;
		return document.createElement( 'div' );
	} );
	mockLoadPaymentData = jest.fn();
	paymentsClientOptions = undefined;
	createButtonOptions = undefined;

	mockPaymentsClient = jest.fn( function ( options ) {
		paymentsClientOptions = options;
		this.isReadyToPay = mockIsReadyToPay;
		this.createButton = mockCreateButton;
		this.loadPaymentData = mockLoadPaymentData;
	} );

	window.google = {
		payments: { api: { PaymentsClient: mockPaymentsClient } },
	};
}

/**
 * Renders the button with the given inputs and returns them for reuse.
 *
 * @param {Object} [overrides] - Overrides for wrapper/config/context/session.
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

	await renderGooglePay( args );

	return args;
}

beforeEach( () => {
	jest.clearAllMocks();
	document.body.innerHTML = '';
	mockHasJQuery.mockReturnValue( true );
	mockLoadGoogleSdk.mockResolvedValue( undefined );
	mockBuildReadyToPayRequest.mockReturnValue( 'READY_REQUEST' );
	mockBuildPaymentDataRequest.mockReturnValue( 'PAYMENT_DATA_REQUEST' );
	installPaymentsClient();
} );

describe( 'renderGooglePay()', () => {
	test( 'appends the button container before loadGoogleSdk resolves', () => {
		// Pins the load-bearing order: boot.js's emptiness check must see a
		// child synchronously, before any await, so it never double-renders.
		const wrapper = document.createElement( 'div' );
		mockLoadGoogleSdk.mockReturnValue( new Promise( () => {} ) );

		renderGooglePay( {
			wrapper,
			config: baseConfig(),
			context: 'product',
			session: makeSession(),
		} );

		expect( wrapper.childElementCount ).toBe( 1 );
	} );

	test( 'constructs PaymentsClient with the config environment ' +
		'and no paymentDataCallbacks', async () => {
		const config = baseConfig();
		config.google_pay.environment = 'PRODUCTION';

		await render( { config } );

		expect( paymentsClientOptions ).toEqual( {
			environment: 'PRODUCTION',
		} );
	} );

	test( 'checks readiness with the built request, built from ' +
		'the formatted session config', async () => {
		await render();

		expect( mockBuildReadyToPayRequest ).toHaveBeenCalledWith(
			sessionConfig
		);
		expect( mockIsReadyToPay ).toHaveBeenCalledWith( 'READY_REQUEST' );
	} );

	test( 'renders no button and leaves the wrapper empty when ' +
		'isReadyToPay resolves false', async () => {
		mockIsReadyToPay.mockResolvedValue( { result: false } );

		const { wrapper } = await render();

		expect( mockCreateButton ).not.toHaveBeenCalled();
		expect( wrapper.childElementCount ).toBe( 0 );
	} );

	test.each( [
		[
			'product',
			{ color: 'black', type: 'buy', language: 'en', borderRadius: 4 },
		],
		[
			'cart',
			{ color: 'white', type: 'pay', language: 'de', borderRadius: 8 },
		],
	] )(
		'maps the %s context styles onto createButton',
		async ( context, styles ) => {
			await render( { context } );

			expect( createButtonOptions ).toEqual(
				expect.objectContaining( {
					buttonColor: styles.color,
					buttonType: styles.type,
					buttonLocale: styles.language,
					buttonRadius: styles.borderRadius,
					buttonSizeMode: 'fill',
					allowedPaymentMethods: [
						sessionConfig.allowedPaymentMethods[ 0 ],
					],
				} )
			);
		}
	);
} );

describe( 'a click on the rendered button', () => {
	const purchaseUnits = [ { amount: { value: '12.34' } } ];
	const paymentData = {
		paymentMethodData: { type: 'CARD', tokenizationData: {} },
		email: 'buyer@example.com',
		shippingAddress: { name: 'Jane' },
	};

	beforeEach( () => {
		mockResolveWalletTotal.mockResolvedValue( {
			total: '12.34',
			purchaseUnits,
		} );
		mockLoadPaymentData.mockResolvedValue( paymentData );
		mockGooglePayPayer.mockReturnValue( 'PAYER_SENTINEL' );
		mockGooglePayShippingAddress.mockReturnValue( 'SHIP_SENTINEL' );
	} );

	test( 'resolves the total, then opens the sheet with the ' +
		'built payment data request', async () => {
		const config = baseConfig();
		await render( { config, context: 'cart' } );

		await createButtonOptions.onClick();

		expect( mockResolveWalletTotal ).toHaveBeenCalledWith(
			config,
			'cart'
		);
		expect( mockBuildPaymentDataRequest ).toHaveBeenCalledWith(
			sessionConfig,
			{
				countryCode: config.buyer_country,
				currencyCode: config.currency,
				total: '12.34',
			}
		);
		expect( mockLoadPaymentData ).toHaveBeenCalledWith(
			'PAYMENT_DATA_REQUEST'
		);
	} );

	test( 'pays with the resolved units, confirm data and ' +
		'mapped contact', async () => {
		const config = baseConfig();
		const { session } = await render( { config, context: 'product' } );

		await createButtonOptions.onClick();

		expect( mockGooglePayPayer ).toHaveBeenCalledWith( paymentData );
		expect( mockGooglePayShippingAddress ).toHaveBeenCalledWith(
			paymentData
		);
		expect( mockPayWithWallet ).toHaveBeenCalledWith( {
			config,
			context: 'product',
			session,
			fundingSource: 'googlepay',
			purchaseUnits,
			confirmData: {
				paymentMethodData: paymentData.paymentMethodData,
			},
			contact: {
				payer: 'PAYER_SENTINEL',
				shippingAddress: 'SHIP_SENTINEL',
			},
		} );
	} );

	test( 'never opens the sheet when resolving the total fails, ' +
		'and reports the error', async () => {
		mockResolveWalletTotal.mockRejectedValueOnce(
			new Error( 'total failed' )
		);
		await render();

		await createButtonOptions.onClick();

		expect( mockLoadPaymentData ).not.toHaveBeenCalled();
		expect( mockHandleError ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'stays silent when the sheet is canceled by the buyer', async () => {
		mockLoadPaymentData.mockRejectedValueOnce( {
			statusCode: 'CANCELED',
		} );
		await render();

		await createButtonOptions.onClick();

		expect( mockHandleError ).not.toHaveBeenCalled();
		expect( mockPayWithWallet ).not.toHaveBeenCalled();
	} );

	test( 'reports a sheet failure that is not a cancelation', async () => {
		mockLoadPaymentData.mockRejectedValueOnce(
			new Error( 'sheet failed' )
		);
		await render();

		await createButtonOptions.onClick();

		expect( mockHandleError ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'a successful payment', false ],
		[ 'a failed payment', true ],
	] )( 'unblocks the spinner after %s', async ( _label, fails ) => {
		if ( fails ) {
			mockPayWithWallet.mockRejectedValueOnce( new Error( 'x' ) );
		}
		await render();

		await createButtonOptions.onClick();

		expect( mockSpinnerUnblock ).toHaveBeenCalled();
	} );

	test( 'drops a second click while the first is in flight, ' +
		'then allows one after it settles', async () => {
		// The in-flight guard must reset in `finally`, not latch permanently.
		let resolveLoad;
		mockLoadPaymentData.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveLoad = resolve;
				} )
		);
		await render();

		const firstClick = createButtonOptions.onClick();
		createButtonOptions.onClick();
		await flushPromises();

		expect( mockLoadPaymentData ).toHaveBeenCalledTimes( 1 );

		resolveLoad( paymentData );
		await firstClick;

		mockLoadPaymentData.mockResolvedValueOnce( paymentData );
		await createButtonOptions.onClick();

		expect( mockLoadPaymentData ).toHaveBeenCalledTimes( 2 );
	} );
} );
