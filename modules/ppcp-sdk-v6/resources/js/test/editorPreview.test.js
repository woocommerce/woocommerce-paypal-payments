const mockLoadScript = jest.fn( () => Promise.resolve() );
jest.mock( '../utils/scriptLoaders', () => ( {
	loadScript: ( ...args ) => mockLoadScript( ...args ),
} ) );

import { loadEditorMessages } from '../messages/editorPreview';

function fakeWindow( { createInstance } = {} ) {
	return {
		paypal: createInstance ? { createInstance } : undefined,
	};
}

const baseOptions = ( overrides = {} ) => ( {
	sdkUrl: 'https://example.test/v6-sdk.js',
	clientId: 'client-id',
	currency: 'USD',
	locale: 'en_US',
	pageType: 'cart',
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'loadEditorMessages()', () => {
	test( 'loads the script into the target window and creates the messages component', async () => {
		const createPayPalMessages = jest.fn();
		const createInstance = jest.fn( () =>
			Promise.resolve( { createPayPalMessages } )
		);
		const targetWindow = fakeWindow( { createInstance } );

		await loadEditorMessages( targetWindow, baseOptions() );

		expect( mockLoadScript ).toHaveBeenCalledWith(
			'https://example.test/v6-sdk.js',
			targetWindow
		);
		expect( createInstance ).toHaveBeenCalledWith( {
			clientId: 'client-id',
			components: [ 'paypal-messages' ],
			pageType: 'cart',
			locale: 'en_US',
		} );
		expect( createPayPalMessages ).toHaveBeenCalledWith( {
			currencyCode: 'USD',
		} );
	} );

	test( 'rejects when the SDK global is missing after the script loads', async () => {
		const targetWindow = fakeWindow();

		await expect(
			loadEditorMessages( targetWindow, baseOptions() )
		).rejects.toThrow(
			'PayPal SDK v6 global not found after script load.'
		);
	} );

	test( 'memoizes the promise per target window, loading the script once', async () => {
		const createInstance = jest.fn( () =>
			Promise.resolve( { createPayPalMessages: jest.fn() } )
		);
		const targetWindow = fakeWindow( { createInstance } );

		const first = loadEditorMessages( targetWindow, baseOptions() );
		const second = loadEditorMessages( targetWindow, baseOptions() );
		await Promise.all( [ first, second ] );

		expect( first ).toBe( second );
		expect( mockLoadScript ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'loads independently for two different target windows', async () => {
		const createInstance = jest.fn( () =>
			Promise.resolve( { createPayPalMessages: jest.fn() } )
		);
		const windowA = fakeWindow( { createInstance } );
		const windowB = fakeWindow( { createInstance } );

		await loadEditorMessages( windowA, baseOptions() );
		await loadEditorMessages( windowB, baseOptions() );

		expect( mockLoadScript ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'clears the memoized promise on failure so a retry can succeed', async () => {
		const targetWindow = fakeWindow();

		await expect(
			loadEditorMessages( targetWindow, baseOptions() )
		).rejects.toThrow();

		const createPayPalMessages = jest.fn();
		targetWindow.paypal = {
			createInstance: jest.fn( () =>
				Promise.resolve( { createPayPalMessages } )
			),
		};

		await loadEditorMessages( targetWindow, baseOptions() );

		expect( createPayPalMessages ).toHaveBeenCalledWith( {
			currencyCode: 'USD',
		} );
	} );
} );
