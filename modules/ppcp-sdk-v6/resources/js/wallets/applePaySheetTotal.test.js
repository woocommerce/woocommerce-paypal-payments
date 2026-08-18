import jQuery from 'jquery';

// Declared outside the factory so their identity survives jest.resetModules():
// the module under test is re-required fresh in every beforeEach, but it must
// keep closing over these same mock functions for the tests to observe its calls.
const mockSimulateCart = jest.fn();
const mockFetchCartTotal = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	simulateCart: ( ...args ) => mockSimulateCart( ...args ),
	fetchCartTotal: ( ...args ) => mockFetchCartTotal( ...args ),
} ) );

const mockHasJQuery = jest.fn( () => true );
jest.mock( '../utils/api', () => ( {
	hasJQuery: () => mockHasJQuery(),
} ) );

// Mirrors REFRESH_DEBOUNCE_MS in the source.
const DEBOUNCE_MS = 250;

// The module keeps its listener-attached flag and "current watcher" pointer
// at module scope, so each test needs a fresh module instance.
let watchSheetTotal;

const config = ( overrides = {} ) => ( {
	amount: '5.00',
	...overrides,
} );

// Microtask chains inside the module (await simulateCart/fetchCartTotal,
// then await resolveSheetTotal) resolve over several ticks; real Promise
// resolution is unaffected by jest's fake timers, so this works either way.
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
	jest.resetModules();
	jest.resetAllMocks();
	jest.useFakeTimers();
	mockHasJQuery.mockReturnValue( true );
	document.body.innerHTML = '';
	global.jQuery = jQuery;
	( { watchSheetTotal } = require( './applePaySheetTotal' ) );
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'watchSheetTotal()', () => {
	test.each( [
		[ '10.00', '10.00' ],
		[ undefined, '' ],
	] )(
		'seeds the total from config.amount %j synchronously, before any lookup resolves',
		( configuredAmount, expectedSeed ) => {
			mockSimulateCart.mockReturnValue( new Promise( () => {} ) );

			const watcher = watchSheetTotal(
				config( { amount: configuredAmount } ),
				'product'
			);

			expect( watcher.get() ).toBe( expectedSeed );
		}
	);

	test( 'resolves the total via simulateCart on the product context, never touching fetchCartTotal', async () => {
		mockSimulateCart.mockResolvedValueOnce( { total: '15.00' } );

		const watcher = watchSheetTotal( config(), 'product' );
		await flushPromises();

		expect( watcher.get() ).toBe( '15.00' );
		expect( mockFetchCartTotal ).not.toHaveBeenCalled();
	} );

	test( 'resolves the total via fetchCartTotal on a non-product context, never touching simulateCart', async () => {
		mockFetchCartTotal.mockResolvedValueOnce( '20.00' );

		const watcher = watchSheetTotal( config(), 'checkout' );
		await flushPromises();

		expect( watcher.get() ).toBe( '20.00' );
		expect( mockSimulateCart ).not.toHaveBeenCalled();
	} );

	test( 'does not throw when the page has no product form', async () => {
		mockSimulateCart.mockResolvedValueOnce( { total: '' } );

		expect( () => watchSheetTotal( config(), 'product' ) ).not.toThrow();
		await flushPromises();
	} );

	describe( 'refresh failures', () => {
		test( 'keeps the previous total when a later refresh resolves empty, instead of overwriting it with the failed lookup', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '15.00' } )
				.mockResolvedValueOnce( { total: '' } );

			const watcher = watchSheetTotal( config(), 'product' );
			await flushPromises();
			expect( watcher.get() ).toBe( '15.00' );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( watcher.get() ).toBe( '15.00' );
		} );

		test( 'keeps the previous total when a later refresh resolves without a total property, instead of the nullish result overwriting it', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '15.00' } )
				.mockResolvedValueOnce( {} );

			const watcher = watchSheetTotal( config(), 'product' );
			await flushPromises();
			expect( watcher.get() ).toBe( '15.00' );

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( watcher.get() ).toBe( '15.00' );
		} );

		test( 'keeps the previous total when simulateCart rejects', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '15.00' } )
				.mockRejectedValueOnce( new Error( 'network down' ) );

			const watcher = watchSheetTotal( config(), 'product' );
			await flushPromises();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( watcher.get() ).toBe( '15.00' );
		} );
	} );

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

		const watcher = watchSheetTotal( config(), 'product' );
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

	describe( 'debouncing product-form changes', () => {
		test( 'coalesces a burst of change events into a single refresh, priced after the debounce window elapses', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '25.00' } );

			const watcher = watchSheetTotal( config(), 'product' );
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

		test( 'refreshes when jQuery reports found_variation on a variable product form', async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '30.00' } );

			const watcher = watchSheetTotal( config(), 'product' );
			await flushPromises();

			jQuery( form ).trigger( 'found_variation' );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( watcher.get() ).toBe( '30.00' );
		} );

		test( 'ignores jQuery variation events when jQuery is unavailable', async () => {
			mockHasJQuery.mockReturnValue( false );
			const form = renderProductForm();
			mockSimulateCart.mockResolvedValue( { total: '10.00' } );

			const watcher = watchSheetTotal( config(), 'product' );
			await flushPromises();
			expect( watcher.get() ).toBe( '10.00' );

			jQuery( form ).trigger( 'found_variation' );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( mockSimulateCart ).toHaveBeenCalledTimes( 1 );
			expect( watcher.get() ).toBe( '10.00' );
		} );
	} );

	describe( 'listening across repeated render passes', () => {
		test( 'does not fire the refresh twice on a single change after a re-render, as a stacked listener would', async () => {
			const form = renderProductForm();
			mockSimulateCart.mockResolvedValue( { total: '10.00' } );

			watchSheetTotal( config(), 'product' );
			await flushPromises();
			watchSheetTotal( config(), 'product' );
			await flushPromises();

			mockSimulateCart.mockClear();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( mockSimulateCart ).toHaveBeenCalledTimes( 1 );
		} );

		test( "a form change refreshes whichever watcher is current, leaving an earlier render's watcher untouched", async () => {
			const form = renderProductForm();
			mockSimulateCart
				.mockResolvedValueOnce( { total: '10.00' } )
				.mockResolvedValueOnce( { total: '20.00' } )
				.mockResolvedValueOnce( { total: '40.00' } );

			const firstWatcher = watchSheetTotal( config(), 'product' );
			await flushPromises();
			const secondWatcher = watchSheetTotal( config(), 'product' );
			await flushPromises();

			form.dispatchEvent( new Event( 'change' ) );
			await jest.advanceTimersByTimeAsync( DEBOUNCE_MS );
			await flushPromises();

			expect( secondWatcher.get() ).toBe( '40.00' );
			expect( firstWatcher.get() ).toBe( '10.00' );
		} );
	} );
} );
