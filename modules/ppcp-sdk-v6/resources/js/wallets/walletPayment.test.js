jest.mock( '../endpointsAdapter', () => ( {
	createOrder: jest.fn(),
	approveOrder: jest.fn(),
} ) );

import { createOrder, approveOrder } from '../endpointsAdapter';
import { payWithWallet } from './walletPayment';

const config = { ajax: {} };

const makeSession = ( confirmResult = { status: 'APPROVED' } ) => ( {
	confirmOrder: jest.fn().mockResolvedValue( confirmResult ),
	initiatePayerAction: jest.fn().mockResolvedValue( undefined ),
} );

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

describe( 'payWithWallet()', () => {
	test( 'confirms with the created order id, then approves it', async () => {
		const session = makeSession();
		const args = payArgs( { session } );

		await payWithWallet( args );

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

			await payWithWallet( args );

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

			await payWithWallet( args );

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

			await payWithWallet( args );

			expect( session.initiatePayerAction ).not.toHaveBeenCalled();
			expect( approveOrder ).toHaveBeenCalled();
		}
	);

	test.each( [
		[ 'a flat status', { status: 'PAYER_ACTION_REQUIRED' } ],
		[
			'an Apple Pay payload nested under approveApplePayPayment',
			{ approveApplePayPayment: { status: 'PAYER_ACTION_REQUIRED' } },
		],
	] )(
		'runs initiatePayerAction before approveOrder on PAYER_ACTION_REQUIRED as %s',
		async ( _label, confirmResult ) => {
			const session = makeSession( confirmResult );
			const args = payArgs( { session } );

			await payWithWallet( args );

			expect( session.initiatePayerAction ).toHaveBeenCalledWith( {
				orderId: 'ORDER1',
			} );
			expect(
				session.initiatePayerAction.mock.invocationCallOrder[ 0 ]
			).toBeLessThan( approveOrder.mock.invocationCallOrder[ 0 ] );
		}
	);

	test.each( [
		[ 'an unrecognized status', { status: 'DECLINED' } ],
		[ 'a null result', null ],
	] )(
		'rejects and never approves on %s',
		async ( _label, confirmResult ) => {
			const session = makeSession( confirmResult );
			const args = payArgs( { session } );

			await expect( payWithWallet( args ) ).rejects.toThrow(
				'Wallet payment was not approved.'
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

		await expect( payWithWallet( args ) ).rejects.toThrow(
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

		await expect( payWithWallet( args ) ).rejects.toThrow(
			'payer action failed'
		);
		expect( approveOrder ).not.toHaveBeenCalled();
	} );
} );
