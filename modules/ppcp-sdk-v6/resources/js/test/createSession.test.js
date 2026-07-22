const mockApproveOrder = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	approveOrder: ( ...args ) => mockApproveOrder( ...args ),
} ) );

const mockAddressChange = jest.fn();
const mockOptionsChange = jest.fn();
jest.mock( '../sessions/shippingHandler', () => ( {
	handleShippingAddressChange: ( ...args ) => mockAddressChange( ...args ),
	handleShippingOptionsChange: ( ...args ) => mockOptionsChange( ...args ),
} ) );

jest.mock( '../utils/api', () => ( { hasJQuery: () => false } ) );
jest.mock( '../utils/errorHandler', () => ( { handleError: jest.fn() } ) );

import { createSession } from '../sessions/createSession';

// Captures the session config passed to the SDK factory so the built
// handlers can be inspected.
function fakeSdk() {
	const capture = {};
	return {
		capture,
		createPayPalOneTimePaymentSession: ( config ) => {
			capture.config = config;
			return { session: true };
		},
	};
}

beforeEach( () => {
	mockApproveOrder.mockReset();
	mockAddressChange.mockReset();
	mockOptionsChange.mockReset();
} );

describe( 'createSession', () => {
	test( 'default onApprove routes to the classic approveOrder flow', async () => {
		const sdk = fakeSdk();
		const config = { shipping: {} };

		createSession( sdk, 'paypal', config, 'cart' );
		await sdk.capture.config.onApprove( { orderId: 'ORDER1' } );

		expect( mockApproveOrder ).toHaveBeenCalledWith(
			config,
			'cart',
			'paypal',
			'ORDER1'
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

		// shipping.handle_in_paypal is absent, so the classic path would
		// attach nothing.
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
} );
