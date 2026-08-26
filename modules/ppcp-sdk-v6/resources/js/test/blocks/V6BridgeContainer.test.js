import '@ppcp-test/helpers/silenceConsole';

const mockRenderWalletInto = jest.fn();
jest.mock( '../../methods/renderMethods', () => ( {
	renderMethodInto: ( ...args ) => mockRenderWalletInto( ...args ),
} ) );

import { render, waitFor, act } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { V6BridgeContainer } from '../../blocks/V6BridgeContainer';

/**
 * Drains pending microtasks.
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

const baseProps = ( overrides = {} ) => ( {
	method: 'applepay',
	config: { fake: 'config' },
	context: 'product',
	session: { fake: 'session' },
	overrides: {},
	...overrides,
} );

function renderContainer( overrides = {} ) {
	return render( createElement( V6BridgeContainer, baseProps( overrides ) ) );
}

/**
 * Reads the overrides object the bridge received on a given render call.
 *
 * @param {number} [callIndex] - Which renderMethodInto() call to read; the
 *                              latest one by default.
 * @return {Object} The overrides the container built for that call.
 */
function bridgeOverrides( callIndex = -1 ) {
	const calls = mockRenderWalletInto.mock.calls;
	const call = callIndex === -1 ? calls.at( -1 ) : calls[ callIndex ];
	return call[ 1 ].overrides;
}

beforeEach( () => {
	mockRenderWalletInto.mockReset().mockResolvedValue( undefined );
} );

describe( 'V6BridgeContainer', () => {
	test( 'calls renderMethodInto with the method, config, context, session and its own wrapper element', () => {
		const { container } = renderContainer();

		expect( mockRenderWalletInto ).toHaveBeenCalledWith(
			'applepay',
			expect.objectContaining( {
				wrapper: container.firstElementChild,
				config: { fake: 'config' },
				context: 'product',
				session: { fake: 'session' },
			} )
		);
	} );

	test( 'never renders without a session', () => {
		renderContainer( { session: null } );

		expect( mockRenderWalletInto ).not.toHaveBeenCalled();
	} );

	test( 'calls overrides.onRenderFailed, and never overrides.onUnavailable, when the render rejects', async () => {
		const error = new Error( 'render failed' );
		mockRenderWalletInto.mockRejectedValueOnce( error );
		const onRenderFailed = jest.fn();
		const onUnavailable = jest.fn();

		renderContainer( { overrides: { onRenderFailed, onUnavailable } } );

		// The rejection's .catch() lands on a later microtask than the
		// synchronous render; flushing it inside act() keeps that arrival
		// tracked, instead of surfacing as an unwrapped update.
		await act( async () => {
			await flushPromises();
		} );

		expect( onRenderFailed ).toHaveBeenCalledWith( error );
		expect( onUnavailable ).not.toHaveBeenCalled();
	} );

	describe( 'overrides.isObsolete()', () => {
		test( 'answers false while mounted and true once the component unmounts', async () => {
			mockRenderWalletInto.mockReturnValue( new Promise( () => {} ) );
			const { unmount } = renderContainer();

			await waitFor( () =>
				expect( mockRenderWalletInto ).toHaveBeenCalled()
			);
			const overrides = bridgeOverrides();
			expect( overrides.isObsolete() ).toBe( false );

			unmount();

			expect( overrides.isObsolete() ).toBe( true );
		} );

		test( 'answers true for a render superseded by a rebuildKey change, while the new render answers false', async () => {
			mockRenderWalletInto.mockReturnValue( new Promise( () => {} ) );
			const { rerender } = renderContainer( { rebuildKey: 'a' } );

			await waitFor( () =>
				expect( mockRenderWalletInto ).toHaveBeenCalledTimes( 1 )
			);
			const staleOverrides = bridgeOverrides();

			rerender(
				createElement(
					V6BridgeContainer,
					baseProps( { rebuildKey: 'b' } )
				)
			);

			await waitFor( () =>
				expect( mockRenderWalletInto ).toHaveBeenCalledTimes( 2 )
			);

			expect( staleOverrides.isObsolete() ).toBe( true );
			expect( bridgeOverrides().isObsolete() ).toBe( false );
		} );
	} );

	test( 'swallows overrides.onUnavailable arriving from a render that already unmounted', async () => {
		mockRenderWalletInto.mockReturnValue( new Promise( () => {} ) );
		const onUnavailable = jest.fn();
		const { unmount } = renderContainer( {
			overrides: { onUnavailable },
		} );

		await waitFor( () =>
			expect( mockRenderWalletInto ).toHaveBeenCalled()
		);
		const wrappedOnUnavailable = bridgeOverrides().onUnavailable;

		unmount();
		wrappedOnUnavailable();

		expect( onUnavailable ).not.toHaveBeenCalled();
	} );
} );
