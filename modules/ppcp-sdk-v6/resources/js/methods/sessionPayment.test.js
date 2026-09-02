jest.mock( '../endpointsAdapter', () => ( {
	createOrder: jest.fn(),
	approveOrder: jest.fn(),
} ) );

jest.mock( '../utils/diagnostics', () => ( {
	logError: jest.fn(),
} ) );

import { createOrder, approveOrder } from '../endpointsAdapter';
import { logError } from '../utils/diagnostics';
import { payWithSession } from './sessionPayment';

const config = { ajax: {} };

/**
 * Builds a wallet session double.
 *
 * @param {Object}  [confirmResult] - What confirmOrder() resolves to.
 * @param {Object}  [args]          - Session capabilities.
 * @param {boolean} [args.capable]  - Whether the session offers
 *                                  initiatePayerAction, as the shipped
 *                                  applepay-payments bundle does not.
 * @return {Object} The session double.
 */
const makeSession = (
	confirmResult = { status: 'APPROVED' },
	{ capable = true } = {}
) => {
	const session = {
		confirmOrder: jest.fn().mockResolvedValue( confirmResult ),
	};

	if ( capable ) {
		session.initiatePayerAction = jest.fn().mockResolvedValue( undefined );
	}

	return session;
};

const payArgs = ( overrides = {} ) => ( {
	config,
	context: 'product',
	session: makeSession(),
	fundingSource: 'googlepay',
	purchaseUnits: [ { amount: { value: '10.00' } } ],
	confirmData: { paymentMethodData: { type: 'CARD' } },
	contact: { payer: { email_address: 'a@b.com' } },
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
	createOrder.mockResolvedValue( { orderId: 'ORDER1' } );
} );

describe( 'payWithSession()', () => {
	test( 'confirms with the created order id, then approves it', async () => {
		const session = makeSession();
		const args = payArgs( { session } );

		await payWithSession( args );

		expect( session.confirmOrder ).toHaveBeenCalledWith( {
			orderId: 'ORDER1',
			...args.confirmData,
		} );
		expect( approveOrder ).toHaveBeenCalledWith(
			args.config,
			args.context,
			args.fundingSource,
			'ORDER1',
			args.contact,
			undefined
		);
	} );

	test.each( [
		[ 'resolved units', [ { amount: { value: '5.00' } } ] ],
		[ 'an explicit empty array', [] ],
	] )(
		'forwards %s to createOrder verbatim',
		async ( _label, purchaseUnits ) => {
			const args = payArgs( { purchaseUnits } );

			await payWithSession( args );

			expect( createOrder ).toHaveBeenCalledWith(
				args.config,
				args.context,
				args.fundingSource,
				purchaseUnits,
				undefined
			);
		}
	);

	test(
		'forwards a supplied paymentMethod to createOrder and ' +
			'approveOrder',
		async () => {
			const args = payArgs( { paymentMethod: 'ppcp-googlepay' } );

			await payWithSession( args );

			expect( createOrder ).toHaveBeenCalledWith(
				args.config,
				args.context,
				args.fundingSource,
				args.purchaseUnits,
				'ppcp-googlepay'
			);
			expect( approveOrder ).toHaveBeenCalledWith(
				args.config,
				args.context,
				args.fundingSource,
				'ORDER1',
				args.contact,
				'ppcp-googlepay'
			);
		}
	);

	test.each( [
		[ 'status APPROVED', { status: 'APPROVED' } ],
		[ 'state succeeded', { state: 'succeeded' } ],
		[
			'an Apple Pay payload nested under approveApplePayPayment',
			{ approveApplePayPayment: { status: 'APPROVED' } },
		],
	] )(
		'approves on %s without calling initiatePayerAction',
		async ( _label, confirmResult ) => {
			const session = makeSession( confirmResult );
			const args = payArgs( { session } );

			await payWithSession( args );

			expect( session.initiatePayerAction ).not.toHaveBeenCalled();
			expect( approveOrder ).toHaveBeenCalled();
		}
	);

	test( 'runs initiatePayerAction before approveOrder on PAYER_ACTION_REQUIRED when the session supports it', async () => {
		const session = makeSession( { status: 'PAYER_ACTION_REQUIRED' } );
		const args = payArgs( { session } );

		await payWithSession( args );

		expect( session.initiatePayerAction ).toHaveBeenCalledWith( {
			orderId: 'ORDER1',
		} );
		expect(
			session.initiatePayerAction.mock.invocationCallOrder[ 0 ]
		).toBeLessThan( approveOrder.mock.invocationCallOrder[ 0 ] );
		expect( logError ).not.toHaveBeenCalled();
	} );

	test( 'reports and throws without approving on PAYER_ACTION_REQUIRED when the session cannot service it, as the shipped Apple Pay bundle cannot', async () => {
		const session = makeSession(
			{ approveApplePayPayment: { status: 'PAYER_ACTION_REQUIRED' } },
			{ capable: false }
		);
		const args = payArgs( { session } );

		await expect( payWithSession( args ) ).rejects.toThrow(
			'This wallet cannot complete the required payer action.'
		);

		expect( logError ).toHaveBeenCalledWith(
			args.config,
			'payer-action-unsupported',
			{
				funding_source: args.fundingSource,
				order_id: 'ORDER1',
				status: 'PAYER_ACTION_REQUIRED',
			}
		);
		expect( approveOrder ).not.toHaveBeenCalled();
	} );

	test.each( [
		[ 'an unrecognized status', { status: 'DECLINED' } ],
		[ 'a null result', null ],
	] )(
		'reports confirm-order-not-approved and rejects without approving on %s',
		async ( _label, confirmResult ) => {
			const session = makeSession( confirmResult );
			const args = payArgs( { session } );

			await expect( payWithSession( args ) ).rejects.toThrow(
				'Wallet payment was not approved.'
			);

			expect( logError ).toHaveBeenCalledWith(
				args.config,
				'confirm-order-not-approved',
				{
					funding_source: args.fundingSource,
					order_id: 'ORDER1',
					status: confirmResult?.status,
					result: confirmResult,
				}
			);
			expect( approveOrder ).not.toHaveBeenCalled();
		}
	);

	test( 'propagates a confirmOrder rejection and never approves', async () => {
		const session = makeSession();
		session.confirmOrder.mockRejectedValueOnce(
			new Error( 'confirm failed' )
		);
		const args = payArgs( { session } );

		await expect( payWithSession( args ) ).rejects.toThrow(
			'confirm failed'
		);
		expect( approveOrder ).not.toHaveBeenCalled();
	} );

	test( 'propagates an initiatePayerAction rejection and never approves', async () => {
		const session = makeSession( { status: 'PAYER_ACTION_REQUIRED' } );
		session.initiatePayerAction.mockRejectedValueOnce(
			new Error( 'payer action failed' )
		);
		const args = payArgs( { session } );

		await expect( payWithSession( args ) ).rejects.toThrow(
			'payer action failed'
		);
		expect( approveOrder ).not.toHaveBeenCalled();
	} );
} );
