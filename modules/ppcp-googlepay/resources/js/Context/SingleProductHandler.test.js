/* global describe, test, expect, jest, beforeEach */

jest.mock( '@ppcp-button/ActionHandler/SingleProductActionHandler' );
jest.mock( '@ppcp-button/Helper/SimulateCart', () => {
	const { isSimulateCartEnabled } = jest.requireActual(
		'@ppcp-button/Helper/SimulateCart'
	);
	return { __esModule: true, default: jest.fn(), isSimulateCartEnabled };
} );
jest.mock( '@ppcp-button/ErrorHandler', () => jest.fn() );
jest.mock( '@ppcp-button/Helper/UpdateCart', () => jest.fn() );
jest.mock( '@ppcp-googlepay/Helper/TransactionInfo' );

import SingleProductHandler from './SingleProductHandler';
import SimulateCart from '@ppcp-button/Helper/SimulateCart';
import SingleProductActionHandler from '@ppcp-button/ActionHandler/SingleProductActionHandler';
import TransactionInfo from '@ppcp-googlepay/Helper/TransactionInfo';

describe( 'SingleProductHandler', () => {
	let handler;
	let mockSimulate;
	let mockGetProducts;
	let mockGetSubscriptionProducts;
	const mockProducts = [ { id: 64, quantity: 1 } ];

	const ppcpConfig = {
		labels: { error: { generic: 'An error occurred.' } },
		simulate_cart: {
			enabled: true,
		},
		ajax: {
			simulate_cart: {
				endpoint: '/ppcp/simulate-cart',
				nonce: 'test-nonce',
			},
		},
	};

	beforeEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';

		global.PayPalCommerceGateway = {
			data_client_id: {
				has_subscriptions: false,
				paypal_subscriptions_enabled: false,
			},
		};

		mockGetProducts = jest.fn().mockReturnValue( mockProducts );
		mockGetSubscriptionProducts = jest.fn().mockReturnValue( [] );
		SingleProductActionHandler.mockImplementation( () => ( {
			getProducts: mockGetProducts,
			getSubscriptionProducts: mockGetSubscriptionProducts,
		} ) );

		mockSimulate = jest.fn();
		SimulateCart.mockImplementation( () => ( {
			simulate: mockSimulate,
		} ) );

		handler = new SingleProductHandler( {}, ppcpConfig, null );
	} );

	describe( 'validateContext()', () => {
		test( 'returns true when no subscription product is present', () => {
			expect( handler.validateContext() ).toBe( true );
		} );

		test( 'returns false when a subscription product is present', () => {
			const subscriptionHandler = new SingleProductHandler(
				{},
				{
					...ppcpConfig,
					locations_with_subscription_product: { product: true },
				},
				null
			);

			expect( subscriptionHandler.validateContext() ).toBe( false );
		} );
	} );

	describe( 'transactionInfo()', () => {
		describe( 'simulate_cart.enabled guard', () => {
			test( 'rejects immediately when simulate_cart is disabled', async () => {
				const disabledHandler = new SingleProductHandler(
					{},
					{ ...ppcpConfig, simulate_cart: { enabled: false } },
					null
				);

				await expect(
					disabledHandler.transactionInfo()
				).rejects.toThrow( 'Cart simulation is disabled.' );
				expect( mockSimulate ).not.toHaveBeenCalled();
			} );

			test( 'proceeds when simulate_cart key is absent (defaults to enabled)', async () => {
				const { simulate_cart: _, ...configWithoutKey } = ppcpConfig;
				const defaultHandler = new SingleProductHandler(
					{},
					configWithoutKey,
					null
				);
				document.body.innerHTML = `<form class="cart"></form>`;
				mockSimulate.mockResolvedValue( {} );

				await defaultHandler.transactionInfo();

				expect( mockSimulate ).toHaveBeenCalled();
			} );
		} );

		describe( 'variation_id guard', () => {
			// Falling back rather than rejecting is what lets the button render at
			// all: the manager skips a button it has no transaction info for, and
			// nothing re-runs that init when a variation is later chosen, so a
			// rejection here removed Google Pay from the product for good.
			// ProductButtonGate keeps the rendered button disabled, and
			// onButtonClick() re-reads this method before opening the sheet.
			test.each( [
				[ 'empty', '' ],
				[ '"0"', '0' ],
			] )(
				'falls back to the cart totals, without simulating, when variation_id is %s',
				async ( _label, value ) => {
					document.body.innerHTML = `
					<form class="cart">
						<input type="hidden" name="variation_id" value="${ value }" />
					</form>
				`;

					const fallback = Symbol( 'cart transaction info' );
					const baseTransactionInfo = jest
						.spyOn(
							Object.getPrototypeOf(
								Object.getPrototypeOf( handler )
							),
							'transactionInfo'
						)
						.mockResolvedValue( fallback );

					await expect( handler.transactionInfo() ).resolves.toBe(
						fallback
					);
					expect( baseTransactionInfo ).toHaveBeenCalled();
					expect( mockSimulate ).not.toHaveBeenCalled();

					baseTransactionInfo.mockRestore();
				}
			);

			test( 'calls simulate_cart when variation_id is set', async () => {
				document.body.innerHTML = `
					<form class="cart">
						<input type="hidden" name="variation_id" value="87" />
					</form>
				`;
				mockSimulate.mockResolvedValue( {} );

				await handler.transactionInfo();

				expect( mockSimulate ).toHaveBeenCalledWith(
					expect.any( Function ),
					mockProducts
				);
			} );

			test( 'calls simulate_cart when no variation_id input exists (non-variable product)', async () => {
				document.body.innerHTML = `<form class="cart"></form>`;
				mockSimulate.mockResolvedValue( {} );

				await handler.transactionInfo();

				expect( mockSimulate ).toHaveBeenCalled();
			} );

			test( 'calls simulate_cart when no form.cart exists', async () => {
				mockSimulate.mockResolvedValue( {} );

				await handler.transactionInfo();

				expect( mockSimulate ).toHaveBeenCalled();
			} );
		} );

		describe( 'simulate_cart integration', () => {
			beforeEach( () => {
				document.body.innerHTML = `
					<form class="cart">
						<input type="hidden" name="variation_id" value="87" />
					</form>
				`;
			} );

			test( 'resolves with TransactionInfo built from response data', async () => {
				const mockTransactionInstance = { totalPrice: '45.00' };
				TransactionInfo.mockImplementation(
					() => mockTransactionInstance
				);

				mockSimulate.mockImplementation( ( onResolve ) =>
					Promise.resolve(
						onResolve( {
							total: 45,
							shipping_fee: 5,
							currency_code: 'USD',
							country_code: 'US',
						} )
					)
				);

				const result = await handler.transactionInfo();

				expect( TransactionInfo ).toHaveBeenCalledWith(
					45,
					5,
					'USD',
					'US'
				);
				expect( result ).toBe( mockTransactionInstance );
			} );

			test( 'propagates rejection from simulate_cart', async () => {
				const serverError = {
					message: 'Error adding products to cart.',
				};
				mockSimulate.mockRejectedValue( serverError );

				await expect( handler.transactionInfo() ).rejects.toEqual(
					serverError
				);
			} );

			test( 'passes subscription products to simulate_cart when subscriptions are enabled', async () => {
				const mockSubscriptionProducts = [ { id: 155, quantity: 1 } ];
				mockGetSubscriptionProducts.mockReturnValue(
					mockSubscriptionProducts
				);
				global.PayPalCommerceGateway = {
					data_client_id: {
						has_subscriptions: true,
						paypal_subscriptions_enabled: true,
					},
				};
				mockSimulate.mockResolvedValue( {} );

				await handler.transactionInfo();

				expect( mockGetSubscriptionProducts ).toHaveBeenCalled();
				expect( mockGetProducts ).not.toHaveBeenCalled();
				expect( mockSimulate ).toHaveBeenCalledWith(
					expect.any( Function ),
					mockSubscriptionProducts
				);
			} );
		} );
	} );
} );
