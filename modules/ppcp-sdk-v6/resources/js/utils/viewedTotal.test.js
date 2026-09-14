import jQuery from 'jquery';

// Declared outside the factory so their identity survives jest.resetModules
// equivalents: the mocks are shared across tests via jest.clearAllMocks().
const mockSimulateCart = jest.fn();
const mockFetchCartTotal = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	simulateCart: ( ...args ) => mockSimulateCart( ...args ),
	fetchCartTotal: ( ...args ) => mockFetchCartTotal( ...args ),
	// Not stubbed: it only reads the DOM the fixtures below build, and the
	// selector pair it uses must live in one place.
	productForm: jest.requireActual( '../endpointsAdapter' ).productForm,
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( './api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

import { watchViewedTotal, resetViewedTotals } from './viewedTotal';

// Mirrors REFRESH_DEBOUNCE_MS in the source.
const DEBOUNCE_MS = 250;

const config = ( overrides = {} ) => ( {
	amount: '5.00',
	...overrides,
} );

// Microtask chains inside the module (await simulateCart/fetchCartTotal,
// then await resolve()) resolve over several ticks; real Promise resolution
// is unaffected by jest's fake timers, so this works either way.
//
// The debounce timeout itself must be advanced with the async variant
// (`await jest.advanceTimersByTimeAsync()`), not the sync one: the sync
// `tick()` fires the setTimeout callback and returns without draining the
// microtask queue, so the refresh() call it kicks off would still be
// unstarted when the very next synchronous test statement runs.
const flushPromises = async () => {
	await Promise.resolve();
	await Promise.resolve();
	await Promise.resolve();
};

function renderProductForm() {
	document.body.innerHTML =
		'<form><input name="add-to-cart" value="1" /></form>';
	return document.querySelector( 'form' );
}

beforeEach( () => {
	resetViewedTotals();
	jest.clearAllMocks();
	jest.useFakeTimers();
	mockHasJQuery.mockReturnValue( true );
	document.body.innerHTML = '';
	global.jQuery = jQuery;
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'watchViewedTotal()', () => {
	describe( 'resolving through the right endpoint', () => {
		test( "resolves the 'product' context via simulateCart, never touching fetchCartTotal", async () => {
			mockSimulateCart.mockResolvedValueOnce( { total: '15.00' } );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			expect( watcher.get() ).toBe( '15.00' );
			expect( mockFetchCartTotal ).not.toHaveBeenCalled();
		} );

		test.each( [ 'cart', 'checkout', 'mini-cart' ] )(
			"resolves the '%s' context via fetchCartTotal, never touching simulateCart",
			async ( context ) => {
				mockFetchCartTotal.mockResolvedValueOnce( '20.00' );

				const watcher = watchViewedTotal( config(), context );
				await flushPromises();

				expect( watcher.get() ).toBe( '20.00' );
				expect( mockSimulateCart ).not.toHaveBeenCalled();
			}
		);
	} );

	test( 'get() returns the config.amount seed before the first resolve settles, then the resolved total once it does', async () => {
		let resolveSimulate;
		mockSimulateCart.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveSimulate = resolve;
			} )
		);

		const watcher = watchViewedTotal(
			config( { amount: '5.00' } ),
			'product'
		);
		expect( watcher.get() ).toBe( '5.00' );

		resolveSimulate( { total: '15.00' } );
		await flushPromises();

		expect( watcher.get() ).toBe( '15.00' );
	} );

	describe( 'subscribe()', () => {
		test( 'notifies a subscriber with the new total when it changes', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '25.00' } );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			const notify = jest.fn();
			watcher.subscribe( notify );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( notify ).toHaveBeenCalledWith( '25.00' );
		} );

		test( 'is not notified when a refresh resolves the same total again', async () => {
			const form = renderProductForm();
			mockSimulateCart.mockResolvedValue( { total: '10.00' } );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			const notify = jest.fn();
			watcher.subscribe( notify );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( notify ).not.toHaveBeenCalled();
		} );

		test( 'stops notifying once the returned unsubscribe function has been called', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '25.00' } )
				.mockResolvedValueOnce( { total: '30.00' } );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			const notify = jest.fn();
			const unsubscribe = watcher.subscribe( notify );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();
			expect( notify ).toHaveBeenCalledTimes( 1 );

			unsubscribe();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( notify ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'per-context isolation', () => {
		test( "two different contexts resolve independently, and each watcher's get() reports its own total", async () => {
			mockSimulateCart.mockResolvedValueOnce( { total: '15.00' } );
			mockFetchCartTotal.mockResolvedValueOnce( '99.00' );

			const productWatcher = watchViewedTotal( config(), 'product' );
			const miniCartWatcher = watchViewedTotal( config(), 'mini-cart' );
			await flushPromises();

			expect( productWatcher.get() ).toBe( '15.00' );
			expect( miniCartWatcher.get() ).toBe( '99.00' );
		} );

		// This is the behaviour change item 1 in the task exists to cover: two
		// surfaces (Apple Pay, Pay Later messaging) watching the same context
		// must not report different totals for the same page.
		test( 'two watchers on the same context share the total after a refresh', async () => {
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '20.00' } );

			const firstWatcher = watchViewedTotal( config(), 'product' );
			await flushPromises();
			const secondWatcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			expect( firstWatcher.get() ).toBe( '20.00' );
			expect( secondWatcher.get() ).toBe( '20.00' );
		} );
	} );

	describe( 'debouncing product-form changes', () => {
		test( 'coalesces a burst of change events into a single refresh, priced after the debounce window elapses', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '25.00' } );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();
			expect( watcher.get() ).toBe( '10.00' );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( 100 );
			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( 100 );

			expect( mockSimulateCart ).toHaveBeenCalledTimes( 1 );

			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( mockSimulateCart ).toHaveBeenCalledTimes( 2 );
			expect( watcher.get() ).toBe( '25.00' );
		} );
	} );

	describe( 'out-of-order refreshes', () => {
		test( 'discards an earlier refresh that resolves after a later one, so the later value survives', async () => {
			const form = renderProductForm();
			let resolveFirst;
			let resolveSecond;
			const first = new Promise( ( resolve ) => {
				resolveFirst = resolve;
			} );
			const second = new Promise( ( resolve ) => {
				resolveSecond = resolve;
			} );

			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockReturnValueOnce( first )
				.mockReturnValueOnce( second );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();
			expect( watcher.get() ).toBe( '10.00' );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );

			resolveSecond( { total: '30.00' } );
			await flushPromises();
			expect( watcher.get() ).toBe( '30.00' );

			resolveFirst( { total: '20.00' } );
			await flushPromises();
			expect( watcher.get() ).toBe( '30.00' );
		} );
	} );

	describe( 'refresh failures', () => {
		test( 'a rejected refresh leaves the previous total intact', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '15.00' } )
				.mockRejectedValueOnce( new Error( 'network down' ) );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( watcher.get() ).toBe( '15.00' );
		} );

		test( 'a refresh that resolves an empty total leaves the previous total intact', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '15.00' } )
				.mockResolvedValueOnce( { total: '' } );

			const watcher = watchViewedTotal( config(), 'product' );
			await flushPromises();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( watcher.get() ).toBe( '15.00' );
		} );
	} );

	describe( 'listening across repeated calls', () => {
		test( 'attaches the product-form listener once, however many times watchViewedTotal is called for the page', async () => {
			const form = renderProductForm();
			mockSimulateCart.mockResolvedValue( { total: '10.00' } );

			watchViewedTotal( config(), 'product' );
			await flushPromises();
			watchViewedTotal( config(), 'product' );
			await flushPromises();

			mockSimulateCart.mockClear();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( mockSimulateCart ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
