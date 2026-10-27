import { TokenRefreshHandler, startTokenRefresh } from './tokenRefresh';

const REFRESH_IN = 240;
const RETRY_IN = 15;
const REFRESH_MS = REFRESH_IN * 1000;
const RETRY_MS = RETRY_IN * 1000;

const tokenData = ( overrides = {} ) => ( {
	client_token: 'TOKEN',
	refresh_in: REFRESH_IN,
	retry_in: RETRY_IN,
	...overrides,
} );

const setVisibility = ( state ) =>
	Object.defineProperty( document, 'visibilityState', {
		value: state,
		configurable: true,
	} );

const tick = ( ms ) => jest.advanceTimersByTimeAsync( ms );

let visibilityListeners;
let sdkInstance;

beforeEach( () => {
	jest.useFakeTimers();
	setVisibility( 'visible' );
	delete window.__ppcpV6TokenRefresh;
	sdkInstance = { updateClientToken: jest.fn() };

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
		test( 'updates the instance with the fetched token after refresh_in', async () => {
			const fetchTokenData = jest
				.fn()
				.mockResolvedValue( tokenData( { client_token: 'NEW' } ) );
			handler.start( sdkInstance, fetchTokenData, tokenData() );

			await tick( REFRESH_MS - 1 );
			expect( sdkInstance.updateClientToken ).not.toHaveBeenCalled();

			await tick( 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledWith(
				'NEW'
			);
		} );

		test( 'plans the next refresh from the refresh_in of the new token', async () => {
			const fetchTokenData = jest
				.fn()
				.mockResolvedValueOnce(
					tokenData( { client_token: 'T2', refresh_in: 60 } )
				)
				.mockResolvedValueOnce( tokenData( { client_token: 'T3' } ) );
			handler.start( sdkInstance, fetchTokenData, tokenData() );
			await tick( REFRESH_MS );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 1 );

			await tick( 60000 - 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 1 );

			await tick( 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 2 );
			expect( sdkInstance.updateClientToken ).toHaveBeenLastCalledWith(
				'T3'
			);
		} );

		test( 'plans no refresh when the token data gives no refresh_in', async () => {
			const fetchTokenData = jest.fn().mockResolvedValue( tokenData() );
			handler.start( sdkInstance, fetchTokenData, {} );

			await tick( 24 * 60 * 60 * 1000 );

			expect( sdkInstance.updateClientToken ).not.toHaveBeenCalled();
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
			const fetchTokenData = jest.fn().mockResolvedValue( tokenData() );
			handler.start( sdkInstance, fetchTokenData, tokenData() );

			// Moves the clock without firing the timer, like a sleeping device.
			jest.setSystemTime( Date.now() + elapsed );
			setVisibility( state );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			await tick( 0 );

			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes(
				expectedCalls
			);
		} );

		test( 'fetches only once when several triggers overlap', async () => {
			let release;
			const fetchTokenData = jest.fn().mockReturnValue(
				new Promise( ( resolve ) => {
					release = resolve;
				} )
			);
			handler.start( sdkInstance, fetchTokenData, tokenData() );

			jest.setSystemTime( Date.now() + REFRESH_MS );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			release( tokenData() );
			await tick( 0 );

			expect( fetchTokenData ).toHaveBeenCalledTimes( 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'failure paths', () => {
		// Required here, not imported: the helper registers hooks on load, and an import would silence the whole file.
		require( '@ppcp-test/helpers/silenceConsole' );

		test( 'tries again after retry_in when the fetch rejects', async () => {
			const fetchTokenData = jest
				.fn()
				.mockRejectedValueOnce( new Error( 'token endpoint down' ) )
				.mockResolvedValueOnce( tokenData( { client_token: 'NEW' } ) );
			handler.start( sdkInstance, fetchTokenData, tokenData() );

			await tick( REFRESH_MS );
			expect( sdkInstance.updateClientToken ).not.toHaveBeenCalled();

			await tick( RETRY_MS - 1 );
			expect( sdkInstance.updateClientToken ).not.toHaveBeenCalled();

			await tick( 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledWith(
				'NEW'
			);
		} );

		test( 'stops refreshing when updateClientToken throws', async () => {
			sdkInstance.updateClientToken.mockImplementation( () => {
				throw new Error( 'invalid JWT' );
			} );
			const fetchTokenData = jest.fn().mockResolvedValue( tokenData() );
			handler.start( sdkInstance, fetchTokenData, tokenData() );

			await tick( REFRESH_MS );
			expect( fetchTokenData ).toHaveBeenCalledTimes( 1 );

			await tick( RETRY_MS );
			await tick( REFRESH_MS * 10 );
			expect( fetchTokenData ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'does not refresh on a later visibilitychange after updateClientToken throws', async () => {
			sdkInstance.updateClientToken.mockImplementation( () => {
				throw new Error( 'invalid JWT' );
			} );
			const fetchTokenData = jest.fn().mockResolvedValue( tokenData() );
			handler.start( sdkInstance, fetchTokenData, tokenData() );
			await tick( REFRESH_MS );
			expect( fetchTokenData ).toHaveBeenCalledTimes( 1 );

			jest.setSystemTime( Date.now() + REFRESH_MS * 10 );
			setVisibility( 'visible' );
			document.dispatchEvent( new Event( 'visibilitychange' ) );
			await tick( 0 );

			expect( fetchTokenData ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'keeps retrying after retry_in on repeated failures', async () => {
			const fetchTokenData = jest
				.fn()
				.mockRejectedValueOnce( new Error( 'down' ) )
				.mockRejectedValueOnce( new Error( 'down' ) )
				.mockResolvedValueOnce( tokenData( { client_token: 'NEW' } ) );
			handler.start( sdkInstance, fetchTokenData, tokenData() );

			await tick( REFRESH_MS );
			await tick( RETRY_MS );
			expect( sdkInstance.updateClientToken ).not.toHaveBeenCalled();

			await tick( RETRY_MS );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledWith(
				'NEW'
			);
		} );

		test( 'uses the retry_in of the latest response after a successful refresh', async () => {
			const fetchTokenData = jest
				.fn()
				.mockResolvedValueOnce( tokenData( { retry_in: 5 } ) )
				.mockRejectedValueOnce( new Error( 'down' ) )
				.mockResolvedValueOnce( tokenData() );
			handler.start( sdkInstance, fetchTokenData, tokenData() );
			await tick( REFRESH_MS );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 1 );

			await tick( REFRESH_MS );
			await tick( 5000 - 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 1 );

			await tick( 1 );
			expect( sdkInstance.updateClientToken ).toHaveBeenCalledTimes( 2 );
		} );
	} );
} );

describe( 'startTokenRefresh()', () => {
	test( 'refreshes the instance through the page-wide handler', async () => {
		startTokenRefresh(
			sdkInstance,
			jest.fn().mockResolvedValue( tokenData( { client_token: 'NEW' } ) ),
			tokenData()
		);

		await tick( REFRESH_MS );

		expect( sdkInstance.updateClientToken ).toHaveBeenCalledWith( 'NEW' );
		expect( window.__ppcpV6TokenRefresh ).toBeInstanceOf(
			TokenRefreshHandler
		);
	} );
} );
