const mockApproveOrder = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	approveOrder: ( ...args ) => mockApproveOrder( ...args ),
} ) );

const mockAddressChange = jest.fn();
const mockOptionsChange = jest.fn();
jest.mock( './shippingHandler', () => ( {
	handleShippingAddressChange: ( ...args ) => mockAddressChange( ...args ),
	handleShippingOptionsChange: ( ...args ) => mockOptionsChange( ...args ),
} ) );

jest.mock( '../utils/api', () => ( { hasJQuery: () => false } ) );
jest.mock( '../utils/errorHandler', () => ( {
	handleError: jest.fn(),
	handleWarning: jest.fn(),
} ) );

import { createSession, SUPPORTED_METHODS } from './createSession';

// Captures the session config and requested factory so the built handlers can be inspected.
function fakeSdk() {
	const capture = {};
	const recordFactoryCall = ( factory ) => ( config ) => {
		capture.factory = factory;
		capture.config = config;
		return { session: true };
	};
	return {
		capture,
		createPayPalOneTimePaymentSession: recordFactoryCall(
			'createPayPalOneTimePaymentSession'
		),
		createVenmoOneTimePaymentSession: recordFactoryCall(
			'createVenmoOneTimePaymentSession'
		),
		createPayLaterOneTimePaymentSession: recordFactoryCall(
			'createPayLaterOneTimePaymentSession'
		),
		createGooglePayOneTimePaymentSession: recordFactoryCall(
			'createGooglePayOneTimePaymentSession'
		),
		createApplePayOneTimePaymentSession: recordFactoryCall(
			'createApplePayOneTimePaymentSession'
		),
		createPayPalGuestOneTimePaymentSession: recordFactoryCall(
			'createPayPalGuestOneTimePaymentSession'
		),
	};
}

beforeEach( () => {
	mockApproveOrder.mockReset();
	mockAddressChange.mockReset();
	mockOptionsChange.mockReset();
} );

describe( 'SUPPORTED_METHODS', () => {
	test( 'lists the methods a session factory exists for, driving the boot.js redraw check', () => {
		expect( SUPPORTED_METHODS ).toEqual( [
			'paypal',
			'venmo',
			'paylater',
			'googlepay',
			'applepay',
			'card',
		] );
	} );
} );

describe( 'createSession', () => {
	test( 'default onApprove routes to the classic approveOrder flow, passing no paymentMethod for paypal', async () => {
		const sdk = fakeSdk();
		const config = { shipping: {} };

		createSession( sdk, 'paypal', config, 'cart' );
		await sdk.capture.config.onApprove( { orderId: 'ORDER1' } );

		expect( mockApproveOrder ).toHaveBeenCalledWith(
			config,
			'cart',
			'paypal',
			'ORDER1',
			{},
			undefined
		);
	} );

	test( 'a provided onApprove replaces the default', async () => {
		const sdk = fakeSdk();
		const onApprove = jest.fn();

		createSession( sdk, 'paypal', { shipping: {} }, 'checkout-block', {
			onApprove,
		} );
		await sdk.capture.config.onApprove( { orderId: 'ORDER2' } );

		expect( onApprove ).toHaveBeenCalledWith( { orderId: 'ORDER2' } );
		expect( mockApproveOrder ).not.toHaveBeenCalled();
	} );

	test( 'provided shipping handlers attach even when the classic condition is false', () => {
		const sdk = fakeSdk();
		const onShippingAddressChange = jest.fn();
		const onShippingOptionsChange = jest.fn();

		// shipping.handle_in_paypal is absent, so the classic path would attach nothing.
		createSession( sdk, 'paypal', { shipping: {} }, 'checkout-block', {
			onShippingAddressChange,
			onShippingOptionsChange,
		} );

		expect( sdk.capture.config.onShippingAddressChange ).toBe(
			onShippingAddressChange
		);
		expect( sdk.capture.config.onShippingOptionsChange ).toBe(
			onShippingOptionsChange
		);
	} );

	test( 'no shipping handlers when neither an override nor the classic condition applies', () => {
		const sdk = fakeSdk();

		createSession( sdk, 'paypal', { shipping: {} }, 'cart' );

		expect( sdk.capture.config.onShippingAddressChange ).toBeUndefined();
		expect( sdk.capture.config.onShippingOptionsChange ).toBeUndefined();
	} );

	test( 'classic default attaches the fetch-based shipping handlers when enabled', async () => {
		const sdk = fakeSdk();
		const config = {
			shipping: { handle_in_paypal: true, need_shipping: true },
		};

		createSession( sdk, 'paypal', config, 'cart' );
		await sdk.capture.config.onShippingAddressChange( { any: true } );

		expect( mockAddressChange ).toHaveBeenCalledWith(
			{ any: true },
			config
		);
	} );

	describe.each( [
		[ 'googlepay', 'createGooglePayOneTimePaymentSession' ],
		[ 'applepay', 'createApplePayOneTimePaymentSession' ],
	] )( '%s', ( method, factoryName ) => {
		test( `is created through ${ factoryName }`, () => {
			const sdk = fakeSdk();

			createSession( sdk, method, { shipping: {} }, 'checkout' );

			expect( sdk.capture.factory ).toBe( factoryName );
		} );

		test( 'the session config has no onApprove, but keeps onCancel and onError', () => {
			const sdk = fakeSdk();

			createSession( sdk, method, { shipping: {} }, 'checkout' );

			expect( sdk.capture.config.onApprove ).toBeUndefined();
			expect( sdk.capture.config.onCancel ).toEqual(
				expect.any( Function )
			);
			expect( sdk.capture.config.onError ).toEqual(
				expect.any( Function )
			);
		} );

		test( 'gets neither in-sheet shipping handler even when classic shipping-in-PayPal is enabled', () => {
			const sdk = fakeSdk();
			const config = {
				shipping: { handle_in_paypal: true, need_shipping: true },
			};

			createSession( sdk, method, config, 'product' );

			expect(
				sdk.capture.config.onShippingAddressChange
			).toBeUndefined();
			expect(
				sdk.capture.config.onShippingOptionsChange
			).toBeUndefined();
		} );
	} );

	describe( 'card', () => {
		test( 'is created through createPayPalGuestOneTimePaymentSession', () => {
			const sdk = fakeSdk();

			createSession( sdk, 'card', { shipping: {} }, 'checkout' );

			expect( sdk.capture.factory ).toBe(
				'createPayPalGuestOneTimePaymentSession'
			);
		} );

		test( 'gets onWarn and onComplete, unlike paypal and venmo', () => {
			const sdk = fakeSdk();

			createSession(
				sdk,
				'card',
				{ shipping: {}, card_button: { payment_method: 'x' } },
				'checkout'
			);

			expect( sdk.capture.config.onWarn ).toEqual( expect.any( Function ) );
			expect( sdk.capture.config.onComplete ).toEqual(
				expect.any( Function )
			);
		} );

		test.each( [ 'paypal', 'venmo' ] )(
			'%s gets neither onWarn nor onComplete',
			( method ) => {
				const sdk = fakeSdk();

				createSession( sdk, method, { shipping: {} }, 'checkout' );

				expect( sdk.capture.config.onWarn ).toBeUndefined();
				expect( sdk.capture.config.onComplete ).toBeUndefined();
			}
		);

		test( "onApprove forwards config.card_button.payment_method as approveOrder's paymentMethod", async () => {
			const sdk = fakeSdk();
			const config = {
				shipping: {},
				card_button: { payment_method: 'ppcp-card-button-gateway' },
			};

			createSession( sdk, 'card', config, 'checkout' );
			await sdk.capture.config.onApprove( { orderId: 'ORDER1' } );

			expect( mockApproveOrder ).toHaveBeenCalledWith(
				config,
				'checkout',
				'card',
				'ORDER1',
				{},
				'ppcp-card-button-gateway'
			);
		} );

		test( "paypal's onApprove leaves approveOrder's paymentMethod undefined", async () => {
			const sdk = fakeSdk();
			const config = { shipping: {}, card_button: { payment_method: 'x' } };

			createSession( sdk, 'paypal', config, 'checkout' );
			await sdk.capture.config.onApprove( { orderId: 'ORDER2' } );

			expect( mockApproveOrder ).toHaveBeenCalledWith(
				config,
				'checkout',
				'paypal',
				'ORDER2',
				{},
				undefined
			);
		} );
	} );
} );
