const mockQuoteWalletShipping = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	quoteWalletShipping: ( ...args ) => mockQuoteWalletShipping( ...args ),
} ) );

const mockQuoteFromResponse = jest.fn();
jest.mock( './shippingQuote', () => ( {
	quoteFromResponse: ( ...args ) => mockQuoteFromResponse( ...args ),
} ) );

import {
	createShippingController,
	walletShippingCountries,
	walletShippingRequired,
} from './walletShipping';

const config = ( overrides = {} ) => ( {
	shipping: {
		in_context: { checkout: true, product: false },
		countries: [ 'US', 'DE' ],
	},
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'walletShippingRequired()', () => {
	test.each( [
		[ 'checkout', true ],
		[ 'product', false ],
		[ 'cart', false ],
	] )( 'reports %s as required: %s', ( context, expected ) => {
		expect( walletShippingRequired( config(), context ) ).toBe( expected );
	} );

	test( 'is false when the config carries no shipping section at all', () => {
		expect( walletShippingRequired( {}, 'checkout' ) ).toBe( false );
	} );
} );

describe( 'walletShippingCountries()', () => {
	test( 'returns the configured countries', () => {
		expect( walletShippingCountries( config() ) ).toEqual( [
			'US',
			'DE',
		] );
	} );

	test( 'returns an empty list when the store does not restrict countries', () => {
		expect(
			walletShippingCountries( config( { shipping: {} } ) )
		).toEqual( [] );
	} );

	test( 'returns an empty list when the config carries no shipping section at all', () => {
		expect( walletShippingCountries( {} ) ).toEqual( [] );
	} );
} );

describe( 'createShippingController()', () => {
	const address = {
		country: 'US',
		state: 'CA',
		postcode: '94105',
		city: 'SF',
	};
	const response = { total: '10.00' };
	const priced = { total: '10.00', selectedId: null, options: [] };

	describe( 'quote()', () => {
		test( 'current() is null before any quote has resolved', () => {
			expect(
				createShippingController( { config: config() } ).current()
			).toBeNull();
		} );

		test( 'prices the address and rate in a single request and keeps the result as the current answer', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse.mockReturnValue( priced );

			const controller = createShippingController( { config: config() } );
			const result = await controller.quote( {
				address,
				rateId: 'flat_rate:1',
			} );

			expect( mockQuoteWalletShipping ).toHaveBeenCalledTimes( 1 );
			expect( mockQuoteWalletShipping ).toHaveBeenCalledWith( config(), {
				address,
				rateId: 'flat_rate:1',
				billingAddress: null,
					expectedTotal: null,
			} );
			expect( mockQuoteFromResponse ).toHaveBeenCalledWith( response );
			expect( result ).toEqual( priced );
			expect( controller.current() ).toEqual( priced );
		} );

		test( 'defaults rateId to null when the selection carries none', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse.mockReturnValue( priced );

			const controller = createShippingController( { config: config() } );
			await controller.quote( { address } );

			expect( mockQuoteWalletShipping ).toHaveBeenCalledWith( config(), {
				address,
				rateId: null,
				billingAddress: null,
					expectedTotal: null,
			} );
		} );

		test( 'a rejected selection rejects, but does not poison later selections', async () => {
			mockQuoteWalletShipping
				.mockRejectedValueOnce( new Error( 'Endpoint down' ) )
				.mockResolvedValueOnce( response );
			mockQuoteFromResponse.mockReturnValue( priced );

			const controller = createShippingController( { config: config() } );

			await expect( controller.quote( { address } ) ).rejects.toThrow(
				'Endpoint down'
			);

			const second = await controller.quote( { address } );

			expect( second ).toEqual( priced );
			expect( mockQuoteWalletShipping ).toHaveBeenCalledTimes( 2 );
		} );

		test( 'writes only the last of several selections made before any of them settles', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse.mockReturnValue( priced );

			const controller = createShippingController( { config: config() } );

			const first = controller.quote( {
				address: { ...address, country: 'US' },
			} );
			const second = controller.quote( {
				address: { ...address, country: 'DE' },
			} );
			const third = controller.quote( {
				address: { ...address, country: 'FR' },
			} );

			await Promise.allSettled( [ first, second, third ] );

			expect( mockQuoteWalletShipping ).toHaveBeenCalledTimes( 1 );
			expect( mockQuoteWalletShipping ).toHaveBeenCalledWith(
				config(),
				expect.objectContaining( {
					address: expect.objectContaining( { country: 'FR' } ),
				} )
			);
			await expect( third ).resolves.toEqual( priced );
		} );

		test( 'a superseded selection rejects with the generic message when nothing has been priced yet', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse.mockReturnValue( priced );

			const controller = createShippingController( { config: config() } );

			const first = controller.quote( {
				address: { ...address, country: 'US' },
			} );
			const second = controller.quote( {
				address: { ...address, country: 'FR' },
			} );

			await expect( first ).rejects.toThrow(
				'Shipping could not be priced.'
			);
			await expect( second ).resolves.toEqual( priced );
		} );
	} );

	describe( 'commit()', () => {
		test( 'resolves with the committed quote', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:1' } )
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:1' } );

			const controller = createShippingController( { config: config() } );
			await controller.quote( { address, rateId: 'flat_rate:1' } );

			const committed = await controller.commit( address );

			expect( committed ).toEqual( {
				total: '10.00',
				selectedId: 'flat_rate:1',
			} );
		} );

		test( "quotes with the complete address, the previous quote's selected rate, and the previously displayed total as expectedTotal", async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:9' } )
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:9' } );

			const controller = createShippingController( { config: config() } );
			await controller.quote( { address, rateId: 'flat_rate:9' } );

			await controller.commit( address );

			expect( mockQuoteWalletShipping ).toHaveBeenLastCalledWith(
				config(),
				{
					address,
					rateId: 'flat_rate:9',
					billingAddress: address,
					expectedTotal: '10.00',
				}
			);
		} );

		test( 'sends the shipping address as the billing address when the given one carries no country', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:1' } )
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:1' } );
			const billingAddressWithNoCountry = { state: 'NY' };

			const controller = createShippingController( { config: config() } );
			await controller.quote( { address, rateId: 'flat_rate:1' } );

			await controller.commit( address, billingAddressWithNoCountry );

			expect( mockQuoteWalletShipping ).toHaveBeenLastCalledWith(
				config(),
				expect.objectContaining( { billingAddress: address } )
			);
		} );

		test( 'forwards the billing address to the quote when authorization revealed one', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:1' } )
				.mockReturnValueOnce( { total: '10.00', selectedId: 'flat_rate:1' } );
			const billingAddress = { ...address, state: 'NY' };

			const controller = createShippingController( { config: config() } );
			await controller.quote( { address, rateId: 'flat_rate:1' } );

			await controller.commit( address, billingAddress );

			expect( mockQuoteWalletShipping ).toHaveBeenLastCalledWith(
				config(),
				expect.objectContaining( { billingAddress } )
			);
		} );

		test( 'propagates a rejection from the endpoint, so a total higher than the sheet displayed never charges the shopper', async () => {
			mockQuoteWalletShipping
				.mockResolvedValueOnce( response )
				.mockRejectedValueOnce(
					new Error( 'Shipping price changed after authorization' )
				);
			mockQuoteFromResponse.mockReturnValueOnce( {
				total: '10.00',
				selectedId: 'flat_rate:1',
			} );

			const controller = createShippingController( { config: config() } );
			await controller.quote( { address, rateId: 'flat_rate:1' } );

			await expect( controller.commit( address ) ).rejects.toThrow(
				'Shipping price changed after authorization'
			);
		} );

		test( 'does not throw when there is no previous quote to compare against', async () => {
			mockQuoteWalletShipping.mockResolvedValue( response );
			mockQuoteFromResponse.mockReturnValue( {
				total: '10.00',
				selectedId: null,
			} );

			const controller = createShippingController( { config: config() } );

			await expect( controller.commit( address ) ).resolves.toEqual( {
				total: '10.00',
				selectedId: null,
			} );
		} );
	} );
} );
