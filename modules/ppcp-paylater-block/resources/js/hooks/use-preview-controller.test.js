import { renderHook, act } from '@testing-library/react';
import {
	usePreviewController,
	RETRY_DELAY_MS,
	MAX_RETRIES,
} from './use-preview-controller';

class MockMutationObserver {
	constructor( callback ) {
		this.callback = callback;
		this.observe = jest.fn();
		this.disconnect = jest.fn();
		MockMutationObserver.instances.push( this );
	}
}
MockMutationObserver.instances = [];

function buildNode( { withIframe = false, offsetHeight = 0 } = {} ) {
	const node = document.createElement( 'div' );
	if ( withIframe ) {
		appendIframe( node, offsetHeight );
	}
	return node;
}

function appendIframe( node, offsetHeight ) {
	const iframe = document.createElement( 'iframe' );
	Object.defineProperty( iframe, 'offsetHeight', {
		value: offsetHeight,
		configurable: true,
	} );
	node.appendChild( iframe );
	return iframe;
}

beforeEach( () => {
	MockMutationObserver.instances = [];
	global.MutationObserver = jest.fn(
		( callback ) => new MockMutationObserver( callback )
	);
	jest.useFakeTimers();
} );

afterEach( () => {
	jest.useRealTimers();
	jest.clearAllMocks();
} );

describe( 'usePreviewController()', () => {
	describe( 'containerRef', () => {
		test( 'marks loaded immediately when the attached node already contains a rendered iframe', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook( () =>
				usePreviewController( false, setLoaded )
			);
			const node = buildNode( { withIframe: true, offsetHeight: 31 } );

			act( () => result.current.containerRef( node ) );

			expect( setLoaded ).toHaveBeenCalledWith( true );
			expect( global.MutationObserver ).not.toHaveBeenCalled();
		} );

		test( 'creates and observes a MutationObserver when no rendered iframe is present yet', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook( () =>
				usePreviewController( false, setLoaded )
			);
			const node = buildNode();

			act( () => result.current.containerRef( node ) );

			expect( setLoaded ).not.toHaveBeenCalled();
			const observer = MockMutationObserver.instances[ 0 ];
			expect( observer.observe ).toHaveBeenCalledWith( node, {
				childList: true,
				subtree: true,
				attributes: true,
			} );
		} );

		test( 'marks loaded and disconnects once a mutation reveals a rendered iframe', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook( () =>
				usePreviewController( false, setLoaded )
			);
			const node = buildNode();

			act( () => result.current.containerRef( node ) );
			const observer = MockMutationObserver.instances[ 0 ];

			appendIframe( node, 31 );
			act( () => observer.callback() );

			expect( setLoaded ).toHaveBeenCalledWith( true );
			expect( observer.disconnect ).toHaveBeenCalled();
		} );

		test( 'does not mark loaded when the only iframe present has zero height', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook( () =>
				usePreviewController( false, setLoaded )
			);
			const node = buildNode( { withIframe: true, offsetHeight: 0 } );

			act( () => result.current.containerRef( node ) );

			expect( setLoaded ).not.toHaveBeenCalled();
		} );

		test( 'disconnects the observer when the ref is detached with null', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook( () =>
				usePreviewController( false, setLoaded )
			);
			const node = buildNode();

			act( () => result.current.containerRef( node ) );
			const observer = MockMutationObserver.instances[ 0 ];

			act( () => result.current.containerRef( null ) );

			expect( observer.disconnect ).toHaveBeenCalled();
		} );
	} );

	describe( 'renderKey', () => {
		test( 'bumps every RETRY_DELAY_MS while not loaded, up to MAX_RETRIES', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook( () =>
				usePreviewController( false, setLoaded )
			);

			expect( result.current.renderKey ).toBe( 0 );

			for ( let attempt = 1; attempt <= MAX_RETRIES; attempt++ ) {
				act( () => jest.advanceTimersByTime( RETRY_DELAY_MS ) );
				expect( result.current.renderKey ).toBe( attempt );
			}

			act( () => jest.advanceTimersByTime( RETRY_DELAY_MS ) );
			expect( result.current.renderKey ).toBe( MAX_RETRIES );
		} );

		test( 'does not schedule a retry once loaded is true', () => {
			const setLoaded = jest.fn();
			const { result } = renderHook(
				( { loaded } ) => usePreviewController( loaded, setLoaded ),
				{ initialProps: { loaded: true } }
			);

			act( () =>
				jest.advanceTimersByTime( RETRY_DELAY_MS * MAX_RETRIES )
			);

			expect( result.current.renderKey ).toBe( 0 );
		} );
	} );
} );
