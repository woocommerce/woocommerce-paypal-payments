const mockSelect = jest.fn();
const mockSubscribe = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	select: ( ...args ) => mockSelect( ...args ),
	subscribe: ( ...args ) => mockSubscribe( ...args ),
} ) );

import { blockSheetTotal } from './blockSheetTotal';

const BUSY_FLAGS = [
	'isApplyingCoupon',
	'isRemovingCoupon',
	'hasPendingItemsOperations',
	'isShippingRateBeingSelected',
	'isCustomerDataUpdating',
];

/**
 * Installs a cart store stand-in; update() changes its state and notifies.
 *
 * @param {Object} initial - State overrides.
 * @return {Object} The state controls.
 */
function installCartStore( initial = {} ) {
	const state = {
		resolved: true,
		totals: { total_price: '1050', currency_minor_unit: 2 },
		busy: {},
		...initial,
	};
	let listener;

	const cart = {
		hasFinishedResolution: () => state.resolved,
		getCartTotals: () => state.totals,
	};
	BUSY_FLAGS.forEach( ( flag ) => {
		cart[ flag ] = () => !! state.busy[ flag ];
	} );

	mockSelect.mockReturnValue( cart );
	mockSubscribe.mockImplementation( ( callback ) => {
		listener = callback;
		return jest.fn();
	} );

	return {
		update( changes ) {
			Object.assign( state, changes );
			listener();
		},
	};
}

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'blockSheetTotal', () => {
	describe( 'get()', () => {
		test( 'returns the store total as a decimal', () => {
			installCartStore();

			expect( blockSheetTotal.get() ).toBe( '10.50' );
		} );

		test( 'honors the currency exponent of the store total', () => {
			installCartStore( {
				totals: { total_price: '1050', currency_minor_unit: 3 },
			} );

			expect( blockSheetTotal.get() ).toBe( '1.050' );
		} );

		test( 'returns an empty string before the cart store has resolved', () => {
			installCartStore( { resolved: false } );

			expect( blockSheetTotal.get() ).toBe( '' );
		} );

		test( 'returns an empty string when the cart store is not registered', () => {
			mockSelect.mockReturnValue( undefined );

			expect( blockSheetTotal.get() ).toBe( '' );
		} );

		test.each( BUSY_FLAGS )(
			'returns an empty string while %s is set',
			( flag ) => {
				installCartStore( { busy: { [ flag ]: true } } );

				expect( blockSheetTotal.get() ).toBe( '' );
			}
		);
	} );

	describe( 'subscribe()', () => {
		test( 'calls back with the new total when it changes', () => {
			const store = installCartStore();
			const onChange = jest.fn();
			blockSheetTotal.subscribe( onChange );

			store.update( {
				totals: { total_price: '2000', currency_minor_unit: 2 },
			} );

			expect( onChange ).toHaveBeenCalledTimes( 1 );
			expect( onChange ).toHaveBeenCalledWith( '20.00' );
		} );

		test( 'calls back with an empty string when a cart request starts, then with the total when it ends', () => {
			const store = installCartStore();
			const onChange = jest.fn();
			blockSheetTotal.subscribe( onChange );

			store.update( { busy: { isApplyingCoupon: true } } );
			store.update( {
				busy: {},
				totals: { total_price: '900', currency_minor_unit: 2 },
			} );

			expect( onChange.mock.calls ).toEqual( [ [ '' ], [ '9.00' ] ] );
		} );

		test( 'stays silent when a store update leaves the total unchanged', () => {
			const store = installCartStore();
			const onChange = jest.fn();
			blockSheetTotal.subscribe( onChange );

			store.update( {} );
			store.update( {} );

			expect( onChange ).not.toHaveBeenCalled();
		} );

		test( 'returns the unsubscribe function of the store subscription', () => {
			installCartStore();
			const unsubscribeStore = jest.fn();
			mockSubscribe.mockReturnValue( unsubscribeStore );

			const unsubscribe = blockSheetTotal.subscribe( jest.fn() );
			unsubscribe();

			expect( unsubscribeStore ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
