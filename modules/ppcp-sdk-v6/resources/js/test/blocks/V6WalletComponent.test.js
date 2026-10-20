const mockLoadSdkV6 = jest.fn();
jest.mock( '../../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

const mockCreateSession = jest.fn();
jest.mock( '../../sessions/createSession', () => ( {
	createSession: ( ...args ) => mockCreateSession( ...args ),
} ) );

const mockWalletContainer = jest.fn( () => null );
jest.mock( '../../blocks/V6BridgeContainer', () => ( {
	V6BridgeContainer: ( props ) => mockWalletContainer( props ),
} ) );

import { render, waitFor, act } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { V6WalletComponent } from '../../blocks/V6WalletComponent';

const config = ( overrides = {} ) => ( {
	page_context: 'checkout-block',
	amount: '50.00',
	shipping: { in_context: { 'checkout-block': true } },
	...overrides,
} );

const baseProps = ( overrides = {} ) => ( {
	config: config(),
	method: 'applepay',
	onClick: jest.fn(),
	onClose: jest.fn(),
	onError: jest.fn(),
	billing: undefined,
	shippingData: { needsShipping: true },
	...overrides,
} );

function renderComponent( overrides = {} ) {
	return render( createElement( V6WalletComponent, baseProps( overrides ) ) );
}

/**
 * Reads the props of the most recent V6BridgeContainer render.
 *
 * @return {Object} The latest props.
 */
function latestContainerProps() {
	return mockWalletContainer.mock.calls.at( -1 )[ 0 ];
}

beforeEach( () => {
	mockLoadSdkV6.mockReset().mockResolvedValue( { sdk: true } );
	mockCreateSession.mockReset().mockReturnValue( { fake: 'session' } );
	mockWalletContainer.mockClear();
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'V6WalletComponent', () => {
	test( 'renders nothing before the SDK session exists', () => {
		mockLoadSdkV6.mockReturnValue( new Promise( () => {} ) );

		renderComponent();

		expect( mockWalletContainer ).not.toHaveBeenCalled();
	} );

	test( 'renders the wallet container once the session is created', async () => {
		renderComponent();

		await waitFor( () => expect( mockWalletContainer ).toHaveBeenCalled() );

		const props = latestContainerProps();
		expect( props.method ).toBe( 'applepay' );
		expect( props.session ).toEqual( { fake: 'session' } );
	} );

	test( 'returns null after the bridge reports the wallet unavailable', async () => {
		renderComponent();
		await waitFor( () => expect( mockWalletContainer ).toHaveBeenCalled() );

		const { overrides } = latestContainerProps();
		const callsBeforeUnavailable = mockWalletContainer.mock.calls.length;

		act( () => {
			overrides.onUnavailable();
		} );

		// Returning null builds no fresh V6BridgeContainer element.
		expect( mockWalletContainer.mock.calls.length ).toBe(
			callsBeforeUnavailable
		);
	} );

	test.each( [
		[ true, true, true ],
		[ true, false, false ],
		[ false, true, false ],
		[ false, false, false ],
	] )(
		'requiresShipping is PHP=%s AND live shippingData.needsShipping=%s => %s',
		async ( phpAnswer, needsShipping, expected ) => {
			renderComponent( {
				config: config( {
					shipping: { in_context: { 'checkout-block': phpAnswer } },
				} ),
				shippingData: { needsShipping },
			} );

			await waitFor( () =>
				expect( mockWalletContainer ).toHaveBeenCalled()
			);

			const { overrides } = latestContainerProps();
			expect( overrides.requiresShipping ).toBe( expected );
		}
	);

	test( "passes the block's buttonAttributes height and borderRadius to the bridge", async () => {
		renderComponent( {
			buttonAttributes: { height: '48', borderRadius: '8' },
		} );

		await waitFor( () => expect( mockWalletContainer ).toHaveBeenCalled() );

		const { overrides } = latestContainerProps();
		expect( overrides.height ).toBe( '48px' );
		expect( overrides.borderRadius ).toBe( '8' );
	} );

	test( 'calls onClose when the sheet closes without an order', async () => {
		const onClose = jest.fn();
		renderComponent( { onClose } );
		await waitFor( () => expect( mockWalletContainer ).toHaveBeenCalled() );

		const { overrides } = latestContainerProps();

		act( () => {
			overrides.onSheetClosed();
		} );

		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'calls onError and onClose when the session reports an error', async () => {
		const onError = jest.fn();
		const onClose = jest.fn();
		renderComponent( { onError, onClose } );
		await waitFor( () => expect( mockCreateSession ).toHaveBeenCalled() );

		const handlers = mockCreateSession.mock.calls.at( -1 )[ 4 ];

		act( () => {
			handlers.onError( new Error( 'sdk session failed' ) );
		} );

		expect( onError ).toHaveBeenCalledWith( 'sdk session failed' );
		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'late props reaching the session through the callbacks ref', () => {
		test( 'onError routes through the latest onError/onClose after a re-render with new prop identities', async () => {
			const onErrorFirst = jest.fn();
			const onCloseFirst = jest.fn();
			// Reusing the same `config` reference across the rerender keeps the
			// SDK-loading effect from refiring; a fresh object would resolve
			// setSdk() on a later, un-awaited microtask.
			const props = baseProps( {
				onError: onErrorFirst,
				onClose: onCloseFirst,
			} );
			const { rerender } = render(
				createElement( V6WalletComponent, props )
			);
			await waitFor( () =>
				expect( mockCreateSession ).toHaveBeenCalled()
			);

			const onErrorLatest = jest.fn();
			const onCloseLatest = jest.fn();
			rerender(
				createElement( V6WalletComponent, {
					...props,
					onError: onErrorLatest,
					onClose: onCloseLatest,
				} )
			);

			const handlers = mockCreateSession.mock.calls.at( -1 )[ 4 ];

			act( () => {
				handlers.onError( new Error( 'later failure' ) );
			} );

			expect( onErrorLatest ).toHaveBeenCalledWith( 'later failure' );
			expect( onCloseLatest ).toHaveBeenCalledTimes( 1 );
			expect( onErrorFirst ).not.toHaveBeenCalled();
			expect( onCloseFirst ).not.toHaveBeenCalled();
		} );

		test( 'onCancel routes through the latest onClose after a re-render with new prop identities', async () => {
			const onCloseFirst = jest.fn();
			const props = baseProps( { onClose: onCloseFirst } );
			const { rerender } = render(
				createElement( V6WalletComponent, props )
			);
			await waitFor( () =>
				expect( mockCreateSession ).toHaveBeenCalled()
			);

			const onCloseLatest = jest.fn();
			rerender(
				createElement( V6WalletComponent, {
					...props,
					onClose: onCloseLatest,
				} )
			);

			const handlers = mockCreateSession.mock.calls.at( -1 )[ 4 ];

			act( () => {
				handlers.onCancel();
			} );

			expect( onCloseLatest ).toHaveBeenCalledTimes( 1 );
			expect( onCloseFirst ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'rebuildKey stability while a payment is in flight', () => {
		test( 'leaves the rebuildKey untouched by a shippingData change while paying, then applies it once the sheet closes', async () => {
			// Same `config` reference reused below, for the reason noted above.
			const props = baseProps( {
				shippingData: { needsShipping: true },
			} );
			const { rerender } = render(
				createElement( V6WalletComponent, props )
			);
			await waitFor( () =>
				expect( mockWalletContainer ).toHaveBeenCalled()
			);

			const rebuildKeyBefore = latestContainerProps().rebuildKey;

			act( () => {
				latestContainerProps().overrides.onClick();
			} );

			act( () => {
				rerender(
					createElement( V6WalletComponent, {
						...props,
						shippingData: { needsShipping: false },
					} )
				);
			} );

			expect( latestContainerProps().rebuildKey ).toBe(
				rebuildKeyBefore
			);

			act( () => {
				latestContainerProps().overrides.onSheetClosed();
			} );

			expect( latestContainerProps().rebuildKey ).not.toBe(
				rebuildKeyBefore
			);
		} );
	} );

	describe( 'sheetContacts', () => {
		test( 'reads the billing and shipping address live, reflecting a change made after mount', async () => {
			// Same `config` reference reused below, for the reason noted above.
			const props = baseProps( {
				billing: { billingAddress: { country: 'US', city: 'SF' } },
			} );
			const { rerender } = render(
				createElement( V6WalletComponent, props )
			);
			await waitFor( () =>
				expect( mockWalletContainer ).toHaveBeenCalled()
			);

			const { overrides } = latestContainerProps();
			expect( overrides.sheetContacts.get().billing.locality ).toBe(
				'SF'
			);

			rerender(
				createElement( V6WalletComponent, {
					...props,
					billing: { billingAddress: { country: 'US', city: 'LA' } },
				} )
			);

			expect( overrides.sheetContacts.get().billing.locality ).toBe(
				'LA'
			);
		} );
	} );

	describe( 'onRenderFailed', () => {
		test( 'does not hide the button, and retries with a growing rebuildKey until MAX_RENDER_ATTEMPTS is reached', async () => {
			renderComponent();
			await waitFor( () =>
				expect( mockWalletContainer ).toHaveBeenCalled()
			);

			// Enabled only once the initial SDK/session promises have settled:
			// waitFor()'s own polling relies on real timers.
			jest.useFakeTimers();

			const rebuildKeyAfterMount = latestContainerProps().rebuildKey;

			// First failure: retried after RETRY_DELAY_MS * 1.
			act( () => {
				latestContainerProps().overrides.onRenderFailed(
					new Error( 'render failed' )
				);
			} );
			act( () => {
				jest.advanceTimersByTime( 1000 );
			} );

			expect( mockWalletContainer ).toHaveBeenCalled();
			const rebuildKeyAfterFirstRetry = latestContainerProps().rebuildKey;
			expect( rebuildKeyAfterFirstRetry ).not.toBe(
				rebuildKeyAfterMount
			);

			// Second failure: retried after RETRY_DELAY_MS * 2.
			act( () => {
				latestContainerProps().overrides.onRenderFailed(
					new Error( 'render failed again' )
				);
			} );
			act( () => {
				jest.advanceTimersByTime( 2000 );
			} );

			const rebuildKeyAfterSecondRetry =
				latestContainerProps().rebuildKey;
			expect( rebuildKeyAfterSecondRetry ).not.toBe(
				rebuildKeyAfterFirstRetry
			);

			// Third failure: gives up immediately, no further retry scheduled.
			const callsBeforeGivingUp = mockWalletContainer.mock.calls.length;
			act( () => {
				latestContainerProps().overrides.onRenderFailed(
					new Error( 'render failed a third time' )
				);
			} );

			expect( mockWalletContainer.mock.calls.length ).toBe(
				callsBeforeGivingUp
			);
		} );
	} );
} );
