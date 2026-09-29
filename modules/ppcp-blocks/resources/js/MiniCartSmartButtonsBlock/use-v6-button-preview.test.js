import { renderHook, act } from '@testing-library/react';

const mockLoadEditorButtons = jest.fn();
jest.mock( '@ppcp-sdk-v6/blocks/editorButtonPreview', () => ( {
	loadEditorButtons: ( ...args ) => mockLoadEditorButtons( ...args ),
} ) );

const mockCreatePreviewButton = jest.fn();
jest.mock( '@ppcp-sdk-v6/components/buttonRenderer', () => ( {
	createPreviewButton: ( ...args ) => mockCreatePreviewButton( ...args ),
} ) );

import { useV6ButtonPreview } from './use-v6-button-preview';

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
		locale: 'en_US',
	},
	pageType: 'cart',
	style: {
		colorClass: 'paypal-blue',
		borderRadius: '8px',
		height: '35px',
	},
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
	MockResizeObserver.instances = [];
	window.ResizeObserver = MockResizeObserver;
	mockCreatePreviewButton.mockImplementation( ( doc ) =>
		doc.createElement( 'paypal-button' )
	);
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

describe( 'useV6ButtonPreview()', () => {
	test( 'appends a button element to the container once the editor buttons load', async () => {
		mockLoadEditorButtons.mockResolvedValue();
		const { result } = renderHook( () =>
			useV6ButtonPreview( baseProps() )
		);

		const container = attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		expect( container.querySelector( 'paypal-button' ) ).not.toBeNull();
		expect( mockLoadEditorButtons ).toHaveBeenCalledWith( window, {
			sdkUrl: 'https://example.test/v6-sdk.js',
			clientId: 'client-id',
			locale: 'en_US',
			pageType: 'cart',
		} );
		expect( mockCreatePreviewButton ).toHaveBeenCalledWith( document, {
			method: 'paypal',
			styles: {
				colorClass: 'paypal-blue',
				borderRadius: '8px',
				height: '35px',
			},
		} );
	} );

	test( 'marks loaded once the resize observer reports a non-zero height', async () => {
		mockLoadEditorButtons.mockResolvedValue();
		const { result } = renderHook( () =>
			useV6ButtonPreview( baseProps() )
		);

		const container = attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		expect( result.current.loaded ).toBe( false );

		const element = container.querySelector( 'paypal-button' );
		Object.defineProperty( element, 'offsetHeight', { value: 40 } );
		const observer = MockResizeObserver.instances[ 0 ];
		act( () => observer.callback() );

		expect( result.current.loaded ).toBe( true );
		expect( observer.disconnect ).toHaveBeenCalled();
	} );

	test( 'does not mark loaded while the observed element still has zero height', async () => {
		mockLoadEditorButtons.mockResolvedValue();
		const { result } = renderHook( () =>
			useV6ButtonPreview( baseProps() )
		);

		attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		const observer = MockResizeObserver.instances[ 0 ];
		act( () => observer.callback() );

		expect( result.current.loaded ).toBe( false );
	} );

	test( 'marks failed without logging when the button method has no element', async () => {
		mockLoadEditorButtons.mockResolvedValue();
		mockCreatePreviewButton.mockReturnValue( null );
		const { result } = renderHook( () =>
			useV6ButtonPreview( baseProps() )
		);

		attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );

		expect( result.current.failed ).toBe( true );
		expect( result.current.loaded ).toBe( false );
		expect( console.error ).not.toHaveBeenCalled();
	} );

	test( 'marks failed and logs when loading the editor buttons rejects', async () => {
		mockLoadEditorButtons.mockRejectedValue( new Error( 'sdk failed' ) );
		const { result } = renderHook( () =>
			useV6ButtonPreview( baseProps() )
		);

		attachContainer( result );
		await act( async () => {
			await Promise.resolve();
			await Promise.resolve();
		} );

		expect( result.current.failed ).toBe( true );
		expect( console.error ).toHaveBeenCalled();
	} );

	test( 'removes the button element and disconnects the observer on cleanup', async () => {
		mockLoadEditorButtons.mockResolvedValue();
		const { result, unmount } = renderHook( () =>
			useV6ButtonPreview( baseProps() )
		);

		const container = attachContainer( result );
		await act( async () => {
			await Promise.resolve();
		} );
		const element = container.querySelector( 'paypal-button' );
		const observer = MockResizeObserver.instances[ 0 ];

		unmount();

		expect( element.isConnected ).toBe( false );
		expect( observer.disconnect ).toHaveBeenCalled();
	} );
} );
