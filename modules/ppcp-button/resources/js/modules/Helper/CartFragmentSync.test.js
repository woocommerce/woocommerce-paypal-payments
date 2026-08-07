/* global describe, test, expect, beforeEach, afterEach, jest */
import { initCartFragmentSync } from './CartFragmentSync';

describe( 'initCartFragmentSync', () => {
	let subscribedCallback;
	let cartData;
	let triggerSpy;
	let storeAvailable;

	const makeCart = () => ( {
		getCartData: () => cartData,
		getItemsCount: () => cartData?.itemsCount ?? null,
	} );

	const setup = () => {
		subscribedCallback = null;
		storeAvailable = true;

		global.wp = {
			data: {
				select: jest.fn( ( store ) =>
					store === 'wc/store/cart' && storeAvailable
						? makeCart()
						: null
				),
				subscribe: jest.fn( ( cb ) => {
					subscribedCallback = cb;
				} ),
			},
		};

		triggerSpy = jest.fn();
		global.jQuery = jest.fn( () => ( { trigger: triggerSpy } ) );
	};

	beforeEach( () => {
		jest.useFakeTimers();
		delete window.ppcpCartFragmentSyncActive;
		cartData = { itemsCount: 2, totals: { total_price: '2000' } };
		setup();
	} );

	afterEach( () => {
		jest.useRealTimers();
		delete global.wp;
		delete global.jQuery;
		delete window.ppcpCartFragmentSyncActive;
	} );

	// Fires one store notification and flushes the debounced refresh.
	const fireStoreUpdate = () => {
		subscribedCallback();
		jest.advanceTimersByTime( 300 );
	};

	// The first notification only seeds the baseline; call this before asserting
	// on subsequent changes.
	const seed = () => fireStoreUpdate();

	test( 'is a no-op when wp is undefined', () => {
		delete global.wp;
		expect( () => initCartFragmentSync() ).not.toThrow();
	} );

	test( 'never refreshes while the cart store is unavailable', () => {
		storeAvailable = false;
		initCartFragmentSync();

		// Subscription is registered even though the store is not ready yet.
		expect( global.wp.data.subscribe ).toHaveBeenCalled();

		fireStoreUpdate();
		fireStoreUpdate();
		expect( triggerSpy ).not.toHaveBeenCalled();
	} );

	test( 'does not refresh on the initial (seeding) notification', () => {
		initCartFragmentSync();
		seed();
		expect( triggerSpy ).not.toHaveBeenCalled();
	} );

	test( 'triggers wc_fragment_refresh when the item count changes', () => {
		initCartFragmentSync();
		seed();

		cartData = { itemsCount: 0, totals: { total_price: '0' } };
		fireStoreUpdate();

		expect( triggerSpy ).toHaveBeenCalledTimes( 1 );
		expect( triggerSpy ).toHaveBeenCalledWith( 'wc_fragment_refresh' );
	} );

	test( 'triggers on total-only changes (e.g. coupon) without count change', () => {
		initCartFragmentSync();
		seed();

		cartData = { itemsCount: 2, totals: { total_price: '1500' } };
		fireStoreUpdate();

		expect( triggerSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does not trigger when the cart signature is unchanged', () => {
		initCartFragmentSync();
		seed();
		fireStoreUpdate();
		expect( triggerSpy ).not.toHaveBeenCalled();
	} );

	test( 'seeds lazily on the first read after the store becomes available', () => {
		storeAvailable = false;
		initCartFragmentSync();

		// Notifications before the store is ready neither seed nor refresh.
		fireStoreUpdate();

		// Store becomes available: first ready read seeds, no refresh.
		storeAvailable = true;
		fireStoreUpdate();
		expect( triggerSpy ).not.toHaveBeenCalled();

		// A subsequent change now refreshes.
		cartData = { itemsCount: 0, totals: { total_price: '0' } };
		fireStoreUpdate();
		expect( triggerSpy ).toHaveBeenCalledTimes( 1 );
	} );
} );
