jest.mock( '../endpointsAdapter', () => ( {
	changeCart: jest.fn(),
	fetchCartTotal: jest.fn(),
} ) );

import { changeCart, fetchCartTotal } from '../endpointsAdapter';
import { resolveWalletTotal } from './walletTotal';

const config = ( overrides = {} ) => ( {
	amount: '10.00',
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'resolveWalletTotal()', () => {
	describe( 'on the product context', () => {
		test.each( [
			[
				'first unit',
				[
					{ amount: { value: '19.99' } },
					{ amount: { value: '5.00' } },
				],
				'19.99',
			],
			[
				'3-decimal unit',
				[ { amount: { value: '12.345' } } ],
				'12.345',
			],
			[ 'empty units', [], '' ],
		] )(
			'resolves the total and purchase units for %s',
			async ( _label, purchaseUnits, total ) => {
				changeCart.mockResolvedValueOnce( purchaseUnits );

				const result = await resolveWalletTotal( config(), 'product' );

				expect( result ).toEqual( { total, purchaseUnits } );
			}
		);

		test( 'calls changeCart exactly once and never fetchCartTotal, so the shopper cart is not rebuilt twice', async () => {
			changeCart.mockResolvedValueOnce( [
				{ amount: { value: '19.99' } },
			] );

			await resolveWalletTotal( config(), 'product' );

			expect( changeCart ).toHaveBeenCalledTimes( 1 );
			expect( fetchCartTotal ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'on a non-product context (cart, checkout, or unset)', () => {
		test.each( [
			[ 'a normal value', '19.99', '19.99' ],
			[ 'a 3-decimal value', '12.345', '12.345' ],
			[ 'an empty string, falling back to config.amount', '', '10.00' ],
		] )(
			'returns %s with empty purchase units',
			async ( _label, fetched, total ) => {
				fetchCartTotal.mockResolvedValueOnce( fetched );

				const result = await resolveWalletTotal( config(), 'cart' );

				expect( result ).toEqual( { total, purchaseUnits: [] } );
			}
		);

		test( 'calls fetchCartTotal and never changeCart, so the shopper cart is not rebuilt on a non-product page', async () => {
			fetchCartTotal.mockResolvedValueOnce( '19.99' );

			await resolveWalletTotal( config(), 'cart' );

			expect( fetchCartTotal ).toHaveBeenCalledTimes( 1 );
			expect( changeCart ).not.toHaveBeenCalled();
		} );
	} );
} );
