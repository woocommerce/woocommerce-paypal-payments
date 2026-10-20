const mockGetCartTotals = jest.fn();
const mockSelect = jest.fn( () => ( { getCartTotals: mockGetCartTotals } ) );
let subscriber;
const mockUnsubscribe = jest.fn();
const mockSubscribe = jest.fn( ( callback ) => {
	subscriber = callback;
	return mockUnsubscribe;
} );

jest.mock( '@wordpress/data', () => ( {
	select: ( ...args ) => mockSelect( ...args ),
	subscribe: ( ...args ) => mockSubscribe( ...args ),
} ) );

import { watchBlockCartTotal } from '../messages/cartTotalWatcher';

beforeEach( () => {
	jest.clearAllMocks();
	mockSelect.mockImplementation( () => ( {
		getCartTotals: mockGetCartTotals,
	} ) );
	subscriber = undefined;
} );

describe( 'watchBlockCartTotal()', () => {
	test( 'fires the callback with the decimal total when it changes', () => {
		mockGetCartTotals
			.mockReturnValueOnce( { total_price: '1000', currency_minor_unit: 2 } )
			.mockReturnValueOnce( { total_price: '1500', currency_minor_unit: 2 } );

		const onChange = jest.fn();
		watchBlockCartTotal( onChange );

		subscriber();

		expect( onChange ).toHaveBeenCalledWith( '15.00' );
	} );

	test( 'does not fire when the store notifies but the total is unchanged', () => {
		mockGetCartTotals.mockReturnValue( {
			total_price: '1000',
			currency_minor_unit: 2,
		} );

		const onChange = jest.fn();
		watchBlockCartTotal( onChange );

		subscriber();
		subscriber();

		expect( onChange ).not.toHaveBeenCalled();
	} );

	test( 'no-ops when the wc/store/cart store is unavailable', () => {
		mockSelect.mockReturnValue( undefined );

		const onChange = jest.fn();
		expect( () => watchBlockCartTotal( onChange ) ).not.toThrow();

		subscriber();

		expect( onChange ).not.toHaveBeenCalled();
	} );

	test( 'no-ops when the store returns no totals', () => {
		mockGetCartTotals.mockReturnValue( undefined );

		const onChange = jest.fn();
		watchBlockCartTotal( onChange );

		subscriber();

		expect( onChange ).not.toHaveBeenCalled();
	} );

	test( 'returns the subscribe unsubscribe function', () => {
		mockGetCartTotals.mockReturnValue( {
			total_price: '1000',
			currency_minor_unit: 2,
		} );

		const unsubscribe = watchBlockCartTotal( jest.fn() );

		expect( unsubscribe ).toBe( mockUnsubscribe );
	} );
} );
