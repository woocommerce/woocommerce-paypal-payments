const mockUpdateCustomerAddress = jest.fn();
const mockSelectShippingRate = jest.fn();
const mockFetchCart = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	updateCustomerAddress: ( ...args ) => mockUpdateCustomerAddress( ...args ),
	selectShippingRate: ( ...args ) => mockSelectShippingRate( ...args ),
	fetchCart: ( ...args ) => mockFetchCart( ...args ),
} ) );

const mockQuoteFromCart = jest.fn();
jest.mock( './shippingQuote', () => ( {
	quoteFromCart: ( ...args ) => mockQuoteFromCart( ...args ),
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
	const address = { country: 'US', state: 'CA', postcode: '94105', city: 'SF' };
	const cartAfterAddress = { totals: {} };
	const cartAfterRate = { totals: {}, rate: true };
	const priced = { total: '10.00', options: [ { id: 'flat_rate:1' } ] };

	test( 'current() is null before any quote has resolved', () => {
		expect( createShippingController( { config: config() } ).current() ).toBeNull();
	} );

	test( 'writes the address when it carries a country, and quotes off the recalculated cart', async () => {
		mockUpdateCustomerAddress.mockResolvedValue( cartAfterAddress );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );
		const result = await controller.quote( { address } );

		expect( mockUpdateCustomerAddress ).toHaveBeenCalledWith(
			config(),
			address
		);
		expect( mockFetchCart ).not.toHaveBeenCalled();
		expect( mockSelectShippingRate ).not.toHaveBeenCalled();
		expect( mockQuoteFromCart ).toHaveBeenCalledWith( cartAfterAddress );
		expect( result ).toEqual( priced );
		expect( controller.current() ).toEqual( priced );
	} );

	test( 'reads the cart instead of writing when the address carries no country, as when the sheet opens with nothing selected yet', async () => {
		const cart = { totals: {} };
		mockFetchCart.mockResolvedValue( cart );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );
		const result = await controller.quote( {
			address: { country: '', state: '', postcode: '', city: '' },
		} );

		expect( mockFetchCart ).toHaveBeenCalledWith( config() );
		expect( mockUpdateCustomerAddress ).not.toHaveBeenCalled();
		expect( mockQuoteFromCart ).toHaveBeenCalledWith( cart );
		expect( result ).toEqual( priced );
	} );

	test( 'still selects the rate after reading the cart when a country-less selection carries one', async () => {
		const cart = { totals: {} };
		mockFetchCart.mockResolvedValue( cart );
		mockSelectShippingRate.mockResolvedValue( cartAfterRate );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );
		const result = await controller.quote( {
			address: { country: '' },
			rateId: 'flat_rate:1',
		} );

		expect( mockFetchCart ).toHaveBeenCalledWith( config() );
		expect( mockUpdateCustomerAddress ).not.toHaveBeenCalled();
		expect( mockSelectShippingRate ).toHaveBeenCalledWith(
			config(),
			'flat_rate:1'
		);
		expect( mockQuoteFromCart ).toHaveBeenCalledWith( cartAfterRate );
		expect( result ).toEqual( priced );
	} );

	test( 'selects the rate after the address, and quotes off the rate response', async () => {
		mockUpdateCustomerAddress.mockResolvedValue( cartAfterAddress );
		mockSelectShippingRate.mockResolvedValue( cartAfterRate );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );
		const result = await controller.quote( {
			address,
			rateId: 'flat_rate:1',
		} );

		expect( mockUpdateCustomerAddress ).toHaveBeenCalledWith(
			config(),
			address
		);
		expect( mockSelectShippingRate ).toHaveBeenCalledWith(
			config(),
			'flat_rate:1'
		);
		expect( mockQuoteFromCart ).toHaveBeenCalledWith( cartAfterRate );
		expect( result ).toEqual( priced );
	} );

	test( 'a rejected selection rejects, but does not poison later selections', async () => {
		mockUpdateCustomerAddress
			.mockRejectedValueOnce( new Error( 'Store API down' ) )
			.mockResolvedValueOnce( cartAfterAddress );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );

		await expect( controller.quote( { address } ) ).rejects.toThrow(
			'Store API down'
		);

		const second = await controller.quote( { address } );

		expect( second ).toEqual( priced );
		expect( mockUpdateCustomerAddress ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'writes only the last of several selections made before any of them settles', async () => {
		mockUpdateCustomerAddress.mockResolvedValue( cartAfterAddress );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );

		const first = controller.quote( { address: { country: 'US' } } );
		const second = controller.quote( { address: { country: 'DE' } } );
		const third = controller.quote( { address: { country: 'FR' } } );

		await Promise.allSettled( [ first, second, third ] );

		expect( mockUpdateCustomerAddress ).toHaveBeenCalledTimes( 1 );
		expect( mockUpdateCustomerAddress ).toHaveBeenCalledWith(
			config(),
			{ country: 'FR' }
		);
		await expect( third ).resolves.toEqual( priced );
	} );

	test( 'a superseded selection rejects with the generic message when nothing has been priced yet', async () => {
		mockUpdateCustomerAddress.mockResolvedValue( cartAfterAddress );
		mockQuoteFromCart.mockReturnValue( priced );

		const controller = createShippingController( { config: config() } );

		const first = controller.quote( { address: { country: 'US' } } );
		const second = controller.quote( { address: { country: 'FR' } } );

		await expect( first ).rejects.toThrow(
			'Shipping could not be priced.'
		);
		await expect( second ).resolves.toEqual( priced );
	} );
} );
