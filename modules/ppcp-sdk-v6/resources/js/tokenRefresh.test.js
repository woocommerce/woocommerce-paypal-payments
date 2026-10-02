import {
	TokenRefreshHandler,
	startTokenRefresh,
	onSdkInstanceChange,
	setSdkBusy,
	releasingSdkBusy,
} from './tokenRefresh';

const REFRESH_IN = 240;
const RETRY_IN = 15;
const REFRESH_MS = REFRESH_IN * 1000;
const RETRY_MS = RETRY_IN * 1000;

const sdk2 = { name: 'sdk2' };
const sdk3 = { name: 'sdk3' };

const tokenData = ( overrides = {} ) => ( {
	refresh_in: REFRESH_IN,
	retry_in: RETRY_IN,
	...overrides,
} );

const renewed = ( sdkInstance, overrides = {} ) => ( {
	sdkInstance,
	tokenData: tokenData( overrides ),
} );

const setVisibility = ( state ) =>
	Object.defineProperty( document, 'visibilityState', {
		value: state,
		configurable: true,
	} );

const tick = ( ms ) => jest.advanceTimersByTimeAsync( ms );

let visibilityListeners;

beforeEach( () => {
	jest.useFakeTimers();
	setVisibility( 'visible' );
	delete window.__ppcpV6TokenRefresh;

	// The handler never removes its visibilitychange listener, so listeners of
	// earlier tests would fire refreshes in later ones.
	visibilityListeners = [];
	const originalAddEventListener = document.addEventListener.bind( document );
	jest.spyOn( document, 'addEventListener' ).mockImplementation(
		( type, listener, ...rest ) => {
			if ( type === 'visibilitychange' ) {
				visibilityListeners.push( listener );
			}
			originalAddEventListener( type, listener, ...rest );
		}
	);
} );

afterEach( () => {
	visibilityListeners.forEach( ( listener ) =>
		document.removeEventListener( 'visibilitychange', listener )
	);
	jest.restoreAllMocks();
	jest.useRealTimers();
	delete document.visibilityState;
} );

describe( 'TokenRefreshHandler', () => {
	let handler;

	beforeEach( () => {
		handler = new TokenRefreshHandler();
	} );

	describe( 'start()', () => {
		test( 'notifies subscribers with the renewed instance after refresh_in', async () => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			await tick( REFRESH_MS - 1 );
			expect( listener ).not.toHaveBeenCalled();

			await tick( 1 );
			expect( listener ).toHaveBeenCalledWith( sdk2 );
		} );

		test( 'plans the next refresh from the refresh_in of the renewed token', async () => {
			const renewInstance = jest
				.fn()
				.mockResolvedValueOnce( renewed( sdk2, { refresh_in: 60 } ) )
				.mockResolvedValueOnce( renewed( sdk3 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );
			await tick( REFRESH_MS );
			expect( listener ).toHaveBeenCalledTimes( 1 );

			await tick( 60000 - 1 );
			expect( listener ).toHaveBeenCalledTimes( 1 );

			await tick( 1 );
			expect( listener ).toHaveBeenCalledTimes( 2 );
			expect( listener ).toHaveBeenLastCalledWith( sdk3 );
		} );

		test( 'plans no refresh when the token data gives no refresh_in', async () => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			handler.start( renewInstance, {} );

			await tick( 24 * 60 * 60 * 1000 );

			expect( renewInstance ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'visibility', () => {
		test.each( [
			{
				name: 'refreshes at once when the tab becomes visible after the planned time',
				state: 'visible',
				elapsed: REFRESH_MS,
				expectedCalls: 1,
			},
			{
				name: 'does not refresh when the tab becomes visible before the planned time',
				state: 'visible',
				elapsed: 1000,
				expectedCalls: 0,
			},
			{
				name: 'does not refresh when the tab becomes hidden after the planned time',
				state: 'hidden',
				elapsed: REFRESH_MS,
				expectedCalls: 0,
			},
		] )( '$name', async ( { state, elapsed, expectedCalls } ) => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			// Moves the clock without firing the timer, like a sleeping device.
			jest.setSystemTime( Date.now() + elapsed );
			setVisibility( state );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			await tick( 0 );

			expect( listener ).toHaveBeenCalledTimes( expectedCalls );
		} );

		test( 'creates only one new instance when several triggers overlap', async () => {
			let release;
			const renewInstance = jest.fn().mockReturnValue(
				new Promise( ( resolve ) => {
					release = resolve;
				} )
			);
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			jest.setSystemTime( Date.now() + REFRESH_MS );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			release( renewed( sdk2 ) );
			await tick( 0 );

			expect( listener ).toHaveBeenCalledTimes( 1 );
			expect( renewInstance ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'subscribe()', () => {
		test( 'stops notifying a subscriber after the returned function ran', async () => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			const removed = jest.fn();
			const kept = jest.fn();
			const unsubscribe = handler.subscribe( removed );
			handler.subscribe( kept );
			handler.start( renewInstance, tokenData() );

			unsubscribe();
			await tick( REFRESH_MS );

			expect( removed ).not.toHaveBeenCalled();
			expect( kept ).toHaveBeenCalledWith( sdk2 );
		} );
	} );

	describe( 'setBusy()', () => {
		test( 'holds the renewed instance until busy ends, then notifies once with it', async () => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			handler.setBusy( true );
			await tick( REFRESH_MS );
			expect( listener ).not.toHaveBeenCalled();

			handler.setBusy( false );
			expect( listener ).toHaveBeenCalledTimes( 1 );
			expect( listener ).toHaveBeenCalledWith( sdk2 );

			handler.setBusy( false );
			expect( listener ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'notifies at once when the refresh completes after busy ended', async () => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			handler.setBusy( true );
			handler.setBusy( false );
			await tick( REFRESH_MS );

			expect( listener ).toHaveBeenCalledWith( sdk2 );
		} );

		test( 'notifies nobody when busy ends without a refresh in between', () => {
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( jest.fn(), tokenData() );

			handler.setBusy( true );
			handler.setBusy( false );

			expect( listener ).not.toHaveBeenCalled();
		} );

		test( 'keeps only the latest renewed instance when several refreshes finish while busy', async () => {
			const renewInstance = jest
				.fn()
				.mockResolvedValueOnce( renewed( sdk2 ) )
				.mockResolvedValueOnce( renewed( sdk3 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			handler.setBusy( true );
			await tick( REFRESH_MS );
			await tick( REFRESH_MS );
			handler.setBusy( false );

			expect( listener ).toHaveBeenCalledTimes( 1 );
			expect( listener ).toHaveBeenCalledWith( sdk3 );
		} );
	} );

	describe( 'failure paths', () => {
		// Required here, not imported: the helper registers hooks on load, and an import would silence the whole file.
		require( '@ppcp-test/helpers/silenceConsole' );

		test( 'notifies nobody after a failed refresh and tries again after retry_in', async () => {
			const renewInstance = jest
				.fn()
				.mockRejectedValueOnce( new Error( 'token endpoint down' ) )
				.mockResolvedValueOnce( renewed( sdk2 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			await tick( REFRESH_MS );
			expect( listener ).not.toHaveBeenCalled();

			await tick( RETRY_MS - 1 );
			expect( listener ).not.toHaveBeenCalled();

			await tick( 1 );
			expect( listener ).toHaveBeenCalledWith( sdk2 );
		} );

		test( 'keeps retrying after retry_in on repeated failures', async () => {
			const renewInstance = jest
				.fn()
				.mockRejectedValueOnce( new Error( 'down' ) )
				.mockRejectedValueOnce( new Error( 'down' ) )
				.mockResolvedValueOnce( renewed( sdk2 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );

			await tick( REFRESH_MS );
			await tick( RETRY_MS );
			expect( listener ).not.toHaveBeenCalled();

			await tick( RETRY_MS );
			expect( listener ).toHaveBeenCalledWith( sdk2 );
		} );

		test( 'uses the retry_in of the latest response after a successful refresh', async () => {
			const renewInstance = jest
				.fn()
				.mockResolvedValueOnce( renewed( sdk2, { retry_in: 5 } ) )
				.mockRejectedValueOnce( new Error( 'down' ) )
				.mockResolvedValueOnce( renewed( sdk3 ) );
			const listener = jest.fn();
			handler.subscribe( listener );
			handler.start( renewInstance, tokenData() );
			await tick( REFRESH_MS );
			expect( listener ).toHaveBeenCalledTimes( 1 );

			await tick( REFRESH_MS );
			await tick( 5000 - 1 );
			expect( listener ).toHaveBeenCalledTimes( 1 );

			await tick( 1 );
			expect( listener ).toHaveBeenCalledTimes( 2 );
		} );

		test( 'still notifies the other subscribers when one subscriber throws', async () => {
			const renewInstance = jest.fn().mockResolvedValue( renewed( sdk2 ) );
			const after = jest.fn();
			handler.subscribe( () => {
				throw new Error( 'subscriber failed' );
			} );
			handler.subscribe( after );
			handler.start( renewInstance, tokenData() );

			await tick( REFRESH_MS );

			expect( after ).toHaveBeenCalledWith( sdk2 );
		} );
	} );
} );

describe( 'page-wide handler', () => {
	test( 'delivers the renewed instance to subscribers of onSdkInstanceChange()', async () => {
		const listener = jest.fn();
		onSdkInstanceChange( listener );
		startTokenRefresh(
			jest.fn().mockResolvedValue( renewed( sdk2 ) ),
			tokenData()
		);

		await tick( REFRESH_MS );

		expect( listener ).toHaveBeenCalledWith( sdk2 );
	} );

	test( 'stops notifying a subscriber after the function from onSdkInstanceChange() ran', async () => {
		const removed = jest.fn();
		const kept = jest.fn();
		const unsubscribe = onSdkInstanceChange( removed );
		onSdkInstanceChange( kept );
		startTokenRefresh(
			jest.fn().mockResolvedValue( renewed( sdk2 ) ),
			tokenData()
		);

		unsubscribe();
		await tick( REFRESH_MS );

		expect( removed ).not.toHaveBeenCalled();
		expect( kept ).toHaveBeenCalledWith( sdk2 );
	} );

	test( 'holds the renewed instance while setSdkBusy( true ) is in effect', async () => {
		const listener = jest.fn();
		onSdkInstanceChange( listener );
		startTokenRefresh(
			jest.fn().mockResolvedValue( renewed( sdk2 ) ),
			tokenData()
		);

		setSdkBusy( true );
		await tick( REFRESH_MS );
		expect( listener ).not.toHaveBeenCalled();

		setSdkBusy( false );
		expect( listener ).toHaveBeenCalledWith( sdk2 );
	} );

} );

describe( 'releasingSdkBusy()', () => {
	async function holdRenewedInstance() {
		const listener = jest.fn();
		onSdkInstanceChange( listener );
		startTokenRefresh(
			jest.fn().mockResolvedValue( renewed( sdk2 ) ),
			tokenData()
		);

		setSdkBusy( true );
		await tick( REFRESH_MS );

		return listener;
	}

	test( 'hands the new instance to subscribers only after the callback resolved, and keeps its result', async () => {
		const listener = await holdRenewedInstance();
		let finish;
		const { onApprove } = releasingSdkBusy( {
			onApprove: () =>
				new Promise( ( resolve ) => {
					finish = resolve;
				} ),
		} );

		const result = onApprove();
		await Promise.resolve();
		expect( listener ).not.toHaveBeenCalled();

		finish( 'callback result' );

		await expect( result ).resolves.toBe( 'callback result' );
		expect( listener ).toHaveBeenCalledTimes( 1 );
		expect( listener ).toHaveBeenCalledWith( sdk2 );
	} );

	test( 'hands the new instance to subscribers after the callback threw, and rejects with the same error', async () => {
		const listener = await holdRenewedInstance();
		const failure = new Error( 'callback failed' );

		const { onError } = releasingSdkBusy( {
			onError: () => {
				throw failure;
			},
		} );

		await expect( onError() ).rejects.toBe( failure );
		expect( listener ).toHaveBeenCalledWith( sdk2 );
	} );

	test.each( [ 'onApprove', 'onCancel', 'onError' ] )(
		'releases busy after %s and passes its arguments on',
		async ( name ) => {
			const listener = await holdRenewedInstance();
			const callback = jest.fn().mockResolvedValue( 'ok' );
			const config = releasingSdkBusy( { [ name ]: callback } );

			await expect( config[ name ]( 'a', 'b' ) ).resolves.toBe( 'ok' );
			expect( callback ).toHaveBeenCalledWith( 'a', 'b' );
			expect( listener ).toHaveBeenCalledWith( sdk2 );
		}
	);

	test( 'returns the same config, leaves other keys untouched and adds no missing callback', () => {
		const onShippingAddressChange = jest.fn();
		const config = { onShippingAddressChange, onApprove: jest.fn() };

		const result = releasingSdkBusy( config );

		expect( result ).toBe( config );
		expect( result.onShippingAddressChange ).toBe( onShippingAddressChange );
		expect( result ).not.toHaveProperty( 'onCancel' );
		expect( result ).not.toHaveProperty( 'onError' );
	} );
} );
