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

import SingleProductHandler from './SingleProductHandler';
import SimulateCart from '@ppcp-button/Helper/SimulateCart';
import SingleProductActionHandler from '@ppcp-button/ActionHandler/SingleProductActionHandler';

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

		handler = new SingleProductHandler( {}, ppcpConfig );
	} );

	describe( 'validateContext()', () => {
		test( 'returns true by default', () => {
			expect( handler.validateContext() ).toBe( true );
		} );
	} );

	describe( 'transactionInfo()', () => {
		describe( 'simulate_cart.enabled guard', () => {
			test( 'rejects immediately when simulate_cart is disabled', async () => {
				const disabledHandler = new SingleProductHandler(
					{},
					{ ...ppcpConfig, simulate_cart: { enabled: false } }
				);

				await expect( disabledHandler.transactionInfo() ).rejects.toThrow(
					'Cart simulation is disabled.'
				);
				expect( mockSimulate ).not.toHaveBeenCalled();
			} );

			test( 'proceeds when simulate_cart key is absent (defaults to enabled)', async () => {
				const { simulate_cart: _, ...configWithoutKey } = ppcpConfig;
				const defaultHandler = new SingleProductHandler(
					{},
					configWithoutKey
				);
				mockSimulate.mockResolvedValue( {} );

				await defaultHandler.transactionInfo();

				expect( mockSimulate ).toHaveBeenCalled();
			} );
		} );

		test( 'calls simulate_cart with products when enabled', async () => {
			mockSimulate.mockImplementation( ( onResolve ) => {
				return Promise.resolve(
					onResolve( {
						total: 45,
						country_code: 'US',
						currency_code: 'USD',
					} )
				);
			} );

			await handler.transactionInfo();

			expect( mockSimulate ).toHaveBeenCalledWith(
				expect.any( Function ),
				mockProducts
			);
		} );

		test( 'maps simulation response to Apple Pay transaction info format', async () => {
			mockSimulate.mockImplementation( ( onResolve ) => {
				return Promise.resolve(
					onResolve( {
						total: 45,
						country_code: 'US',
						currency_code: 'USD',
					} )
				);
			} );

			const result = await handler.transactionInfo();

			expect( result ).toEqual( {
				countryCode: 'US',
				currencyCode: 'USD',
				totalPriceStatus: 'FINAL',
				totalPrice: 45,
			} );
		} );

		test( 'propagates rejection from simulate_cart', async () => {
			const serverError = { message: 'Server error.' };
			mockSimulate.mockRejectedValue( serverError );

			await expect( handler.transactionInfo() ).rejects.toEqual(
				serverError
			);
		} );

		test( 'passes subscription products when subscriptions are enabled', async () => {
			const mockSubscriptionProducts = [ { id: 155, quantity: 1 } ];
			mockGetSubscriptionProducts.mockReturnValue( mockSubscriptionProducts );
			global.PayPalCommerceGateway = {
				data_client_id: {
					has_subscriptions: true,
					paypal_subscriptions_enabled: true,
				},
			};
			mockSimulate.mockImplementation( ( onResolve ) => {
				return Promise.resolve(
					onResolve( { total: 0, country_code: 'US', currency_code: 'USD' } )
				);
			} );

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
