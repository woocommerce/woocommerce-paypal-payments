const mockApproveSubscription = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	approveSubscription: ( ...args ) => mockApproveSubscription( ...args ),
} ) );

const mockRefreshCartUi = jest.fn();
jest.mock( '../utils/cartUi', () => ( {
	refreshCartUi: ( ...args ) => mockRefreshCartUi( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

jest.mock( '../tokenRefresh', () => ( {
	releasingSdkBusy: ( sessionConfig ) => sessionConfig,
} ) );

import { createSubscriptionSession } from './subscriptionSession';

const config = { ajax: {} };

function setup( context = 'cart', handlers ) {
	const session = { orderId: 'SESSION-ORDER' };
	const capture = {};
	const sdk = {
		createPayPalSubscriptionPaymentSession: jest.fn( ( callbacks ) => {
			capture.callbacks = callbacks;
			return session;
		} ),
	};

	const result = createSubscriptionSession( sdk, config, context, handlers );

	return { result, session, sdk, callbacks: capture.callbacks, context };
}

beforeEach( () => {
	mockApproveSubscription.mockReset();
	mockRefreshCartUi.mockReset();
	mockHandleError.mockReset();
} );

describe( 'createSubscriptionSession', () => {
	test( 'returns the session created by the SDK subscription factory', () => {
		const { result, session } = setup();

		expect( result ).toBe( session );
	} );

	describe( 'onApprove', () => {
		test( 'approves the subscription with the approval data ids', async () => {
			const { callbacks } = setup( 'checkout' );

			await callbacks.onApprove( {
				subscriptionId: 'I-SUB',
				orderId: 'DATA-ORDER',
			} );

			expect( mockApproveSubscription ).toHaveBeenCalledWith(
				config,
				'checkout',
				'I-SUB',
				'DATA-ORDER'
			);
		} );

		test( 'falls back to the session order id when the approval data has none', async () => {
			const { callbacks } = setup( 'product' );

			await callbacks.onApprove( { subscriptionId: 'I-SUB' } );

			expect( mockApproveSubscription ).toHaveBeenCalledWith(
				config,
				'product',
				'I-SUB',
				'SESSION-ORDER'
			);
		} );

		test( 'reports a failed approval through the error handler instead of rejecting', async () => {
			const error = new Error( 'approve failed' );
			mockApproveSubscription.mockRejectedValueOnce( error );
			const { callbacks } = setup();

			await expect(
				callbacks.onApprove( { subscriptionId: 'I-SUB' } )
			).resolves.toBeUndefined();

			expect( mockHandleError ).toHaveBeenCalledWith( error );
		} );

		test( 'reports no error when the approval succeeds', async () => {
			const { callbacks } = setup();

			await callbacks.onApprove( { subscriptionId: 'I-SUB' } );

			expect( mockHandleError ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'onCancel', () => {
		test( 'refreshes the cart UI for the context without raising an error', () => {
			const { callbacks } = setup( 'cart' );

			callbacks.onCancel();

			expect( mockRefreshCartUi ).toHaveBeenCalledWith( 'cart' );
			expect( mockHandleError ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'onError', () => {
		test( 'refreshes the cart UI and reports the error', () => {
			const error = new Error( 'sdk error' );
			const { callbacks } = setup( 'checkout' );

			callbacks.onError( error );

			expect( mockRefreshCartUi ).toHaveBeenCalledWith( 'checkout' );
			expect( mockHandleError ).toHaveBeenCalledWith( error );
		} );
	} );

	describe( 'handler overrides', () => {
		test( 'onApprove replaces the default approval and receives the approval data ids', async () => {
			const onApprove = jest.fn();
			const { callbacks } = setup( 'cart', { onApprove } );

			await callbacks.onApprove( {
				subscriptionId: 'I-SUB',
				orderId: 'DATA-ORDER',
			} );

			expect( onApprove ).toHaveBeenCalledWith( 'I-SUB', 'DATA-ORDER' );
			expect( mockApproveSubscription ).not.toHaveBeenCalled();
		} );

		test( 'onApprove still receives the session order id when the approval data has none', async () => {
			const onApprove = jest.fn();
			const { callbacks } = setup( 'cart', { onApprove } );

			await callbacks.onApprove( { subscriptionId: 'I-SUB' } );

			expect( onApprove ).toHaveBeenCalledWith(
				'I-SUB',
				'SESSION-ORDER'
			);
		} );

		test( 'onApprove failures are left to the override, not reported by the session', async () => {
			const error = new Error( 'override failed' );
			const { callbacks } = setup( 'cart', {
				onApprove: jest.fn().mockRejectedValue( error ),
			} );

			await expect(
				callbacks.onApprove( { subscriptionId: 'I-SUB' } )
			).rejects.toBe( error );
			expect( mockHandleError ).not.toHaveBeenCalled();
		} );

		test( 'onCancel replaces the default cancel handling', () => {
			const onCancel = jest.fn();
			const { callbacks } = setup( 'cart', { onCancel } );

			callbacks.onCancel();

			expect( onCancel ).toHaveBeenCalled();
			expect( mockRefreshCartUi ).not.toHaveBeenCalled();
		} );

		test( 'onError replaces the default error handling', () => {
			const error = new Error( 'sdk error' );
			const onError = jest.fn();
			const { callbacks } = setup( 'cart', { onError } );

			callbacks.onError( error );

			expect( onError ).toHaveBeenCalledWith( error );
			expect( mockRefreshCartUi ).not.toHaveBeenCalled();
			expect( mockHandleError ).not.toHaveBeenCalled();
		} );

		test( 'a partial override leaves the other callbacks on their defaults', () => {
			const { callbacks } = setup( 'cart', { onApprove: jest.fn() } );

			callbacks.onCancel();

			expect( mockRefreshCartUi ).toHaveBeenCalledWith( 'cart' );
		} );
	} );
} );
