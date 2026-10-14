import { onBlockCartTotalChange } from './blockCartTotal';

/**
 * A wp.data stand-in; update() changes the cart store state and notifies.
 *
 * @param {Object} initial - Initial store state overrides.
 */
function installWpData( initial = {} ) {
	const state = { resolved: false, totalPrice: '0', store: true, ...initial };
	let listener;

	window.wp = {
		data: {
			subscribe: ( callback ) => {
				listener = callback;
			},
			select: () =>
				state.store
					? {
							hasFinishedResolution: () => state.resolved,
							getCartTotals: () => ( {
								total_price: state.totalPrice,
							} ),
					  }
					: undefined,
		},
	};

	return {
		update( changes ) {
			Object.assign( state, changes );
			listener();
		},
	};
}

describe( 'onBlockCartTotalChange()', () => {
	let callback;

	beforeEach( () => {
		callback = jest.fn();
	} );

	afterEach( () => {
		delete window.wp;
	} );

	test( 'does nothing when wp.data is not available', () => {
		delete window.wp;

		expect( () => onBlockCartTotalChange( callback ) ).not.toThrow();
	} );

	test( 'reports a total change after the cart has loaded', () => {
		const store = installWpData();
		onBlockCartTotalChange( callback );

		store.update( { resolved: true, totalPrice: '1000' } );
		store.update( { totalPrice: '0' } );

		expect( callback ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores the default totals read before the cart has loaded', () => {
		const store = installWpData();
		onBlockCartTotalChange( callback );

		store.update( { totalPrice: '0' } );
		store.update( { totalPrice: '500' } );
		store.update( { resolved: true, totalPrice: '1000' } );

		expect( callback ).not.toHaveBeenCalled();
	} );

	test( 'does not report updates that leave the total unchanged', () => {
		const store = installWpData( { resolved: true, totalPrice: '1000' } );
		onBlockCartTotalChange( callback );

		store.update( {} );
		store.update( {} );

		expect( callback ).not.toHaveBeenCalled();
	} );

	test( 'tolerates the cart store not being registered yet', () => {
		const store = installWpData( { store: false } );
		onBlockCartTotalChange( callback );

		expect( () => store.update( {} ) ).not.toThrow();

		store.update( { store: true, resolved: true, totalPrice: '1000' } );
		store.update( { totalPrice: '0' } );

		expect( callback ).toHaveBeenCalledTimes( 1 );
	} );
} );
