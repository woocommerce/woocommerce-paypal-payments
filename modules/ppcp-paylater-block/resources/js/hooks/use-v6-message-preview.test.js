import { renderHook, act } from '@testing-library/react';

const mockLoadEditorMessages = jest.fn();
jest.mock( '@ppcp-sdk-v6/messages/editorPreview', () => ( {
	loadEditorMessages: ( ...args ) => mockLoadEditorMessages( ...args ),
} ) );

import { useV6MessagePreview } from './use-v6-message-preview';

class MockResizeObserver {
	constructor( callback ) {
		this.callback = callback;
		this.observe = jest.fn();
		this.disconnect = jest.fn();
		MockResizeObserver.instances.push( this );
	}
}
MockResizeObserver.instances = [];

const baseProps = ( overrides = {} ) => ( {
	sdkV6: {
		sdkUrl: 'https://example.test/v6-sdk.js',
		clientId: 'client-id',
		currency: 'USD',
		locale: 'en_US',
	},
	amount: '50.00',
	pageType: 'cart',
	style: {
		logoType: 'WORDMARK',
		logoPosition: 'LEFT',
		textColor: 'BLACK',
		fontSize: '',
	},
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
	MockResizeObserver.instances = [];
	window.ResizeObserver = MockResizeObserver;
	jest.spyOn( console, 'error' ).mockImplementation( () => {} );
} );

afterEach( () => {
	console.error.mockRestore();
} );

function attachContainer( result ) {
	const node = document.createElement( 'div' );
	act( () => result.current.containerRef( node ) );
	return node;
}

describe( 'useV6MessagePreview()', () => {
	test( 'appends a message element to the container once the editor messages load', async () => {
		mockLoadEditorMessages.mockResolvedValue();
		const { result } = renderHook( () =>
			useV6MessagePreview( baseProps() )
		);

		const container = attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		expect( container.querySelector( 'paypal-message' ) ).not.toBeNull();
		expect( mockLoadEditorMessages ).toHaveBeenCalledWith( window, {
			sdkUrl: 'https://example.test/v6-sdk.js',
			clientId: 'client-id',
			currency: 'USD',
			locale: 'en_US',
			pageType: 'cart',
		} );
	} );

	test( 'marks loaded once the resize observer reports a non-zero height', async () => {
		mockLoadEditorMessages.mockResolvedValue();
		const { result } = renderHook( () =>
			useV6MessagePreview( baseProps() )
		);

		const container = attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		expect( result.current.loaded ).toBe( false );

		const element = container.querySelector( 'paypal-message' );
		Object.defineProperty( element, 'offsetHeight', { value: 40 } );
		const observer = MockResizeObserver.instances[ 0 ];
		act( () => observer.callback() );

		expect( result.current.loaded ).toBe( true );
		expect( observer.disconnect ).toHaveBeenCalled();
	} );

	test( 'does not mark loaded while the observed element still has zero height', async () => {
		mockLoadEditorMessages.mockResolvedValue();
		const { result } = renderHook( () =>
			useV6MessagePreview( baseProps() )
		);

		attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		const observer = MockResizeObserver.instances[ 0 ];
		act( () => observer.callback() );

		expect( result.current.loaded ).toBe( false );
	} );

	test( 'marks failed and logs when loading the editor messages rejects', async () => {
		mockLoadEditorMessages.mockRejectedValue( new Error( 'sdk failed' ) );
		const { result } = renderHook( () =>
			useV6MessagePreview( baseProps() )
		);

		attachContainer( result );
		await act( async () => {
			await Promise.resolve();
			await Promise.resolve();
		} );

		expect( result.current.failed ).toBe( true );
		expect( console.error ).toHaveBeenCalled();
	} );

	test( 'removes the message element and disconnects the observer on cleanup', async () => {
		mockLoadEditorMessages.mockResolvedValue();
		const { result, unmount } = renderHook( () =>
			useV6MessagePreview( baseProps() )
		);

		const container = attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );
		const element = container.querySelector( 'paypal-message' );
		const observer = MockResizeObserver.instances[ 0 ];

		unmount();

		expect( element.isConnected ).toBe( false );
		expect( observer.disconnect ).toHaveBeenCalled();
	} );
} );
