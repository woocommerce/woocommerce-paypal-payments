import { createOrder, onApprove } from './card-fields-config';

const baseScriptData = ( overrides = {} ) => ( {
	ajax: {
		create_order: {
			endpoint: 'https://example.com/create-order',
			nonce: 'create-order-nonce',
		},
		approve_order: {
			endpoint: 'https://example.com/approve-order',
			nonce: 'approve-order-nonce',
		},
	},
	context: 'checkout-block',
	...overrides,
} );

const mockScriptData = ( overrides = {} ) => {
	global.wc = {
		wcSettings: {
			getSetting: jest.fn( () => ( {
				scriptData: baseScriptData( overrides ),
			} ) ),
		},
	};
};

describe( 'card-fields-config', () => {
	let originalFetch;

	beforeEach( () => {
		originalFetch = global.fetch;
		global.fetch = jest.fn();
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		jest.restoreAllMocks();
		delete global.wc;
		localStorage.clear();
	} );

	describe( 'createOrder()', () => {
		beforeEach( () => {
			mockScriptData();
			global.fetch.mockResolvedValueOnce( {
				json: async () => ( { data: { id: 'PAYPAL-ORDER-ID' } } ),
			} );
		} );

		test( 'resolves to the PayPal order id from the response', async () => {
			await expect( createOrder( true ) ).resolves.toBe(
				'PAYPAL-ORDER-ID'
			);
		} );

		test( 'sends nonce, context and the credit card payment method', async () => {
			await createOrder( true );

			const body = JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );
			expect( body ).toMatchObject( {
				nonce: 'create-order-nonce',
				context: 'checkout-block',
				payment_method: 'ppcp-credit-card-gateway',
			} );
		} );

		test.each( [
			[ true, true ],
			[ false, false ],
		] )(
			'createOrder( %s ) puts save_payment_method: %s in the request body',
			async ( input, expected ) => {
				await createOrder( input );

				const body = JSON.parse(
					global.fetch.mock.calls[ 0 ][ 1 ].body
				);
				expect( body.save_payment_method ).toBe( expected );
			}
		);

		test( 'defaults save_payment_method to false when called without an argument', async () => {
			await createOrder();

			const body = JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );
			expect( body.save_payment_method ).toBe( false );
		} );

		test( 'does not read the buyer intent from localStorage', async () => {
			const getItemSpy = jest.spyOn( Storage.prototype, 'getItem' );

			await createOrder( true );

			expect( getItemSpy ).not.toHaveBeenCalled();
		} );

		test( 'ignores a stale ppcp-save-card-payment value left in localStorage', async () => {
			localStorage.setItem( 'ppcp-save-card-payment', 'false' );

			await createOrder( true );

			const body = JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );
			expect( body.save_payment_method ).toBe( true );
		} );
	} );

	describe( 'onApprove()', () => {
		beforeEach( () => {
			mockScriptData();
			global.fetch.mockResolvedValueOnce( {
				json: async () => ( { success: true } ),
			} );
		} );

		test( 'posts the order id and nonce to the approve endpoint', async () => {
			await onApprove( { orderID: 'PAYPAL-ORDER-ID' } );

			expect( global.fetch ).toHaveBeenCalledWith(
				'https://example.com/approve-order',
				expect.objectContaining( {
					method: 'POST',
				} )
			);
			const body = JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );
			expect( body ).toEqual( {
				order_id: 'PAYPAL-ORDER-ID',
				nonce: 'approve-order-nonce',
			} );
		} );

		test( 'does not clear the buyer intent stored for a retried submit', async () => {
			localStorage.setItem( 'ppcp-save-card-payment', 'true' );
			const removeItemSpy = jest.spyOn( Storage.prototype, 'removeItem' );

			await onApprove( { orderID: 'PAYPAL-ORDER-ID' } );

			expect( removeItemSpy ).not.toHaveBeenCalled();
			expect( localStorage.getItem( 'ppcp-save-card-payment' ) ).toBe(
				'true'
			);
		} );
	} );
} );
