jest.mock( './googlePay', () => ( {
	renderGooglePay: jest.fn(),
} ) );

import { renderGooglePay } from './googlePay';
import { renderWallets } from './renderWallets';

const args = ( overrides = {} ) => ( {
	wrapper: {},
	config: { google_pay: { enabled: true } },
	context: 'product',
	sessions: { googlepay: {} },
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
	document.body.innerHTML = '';
} );

describe( 'renderWallets()', () => {
	test( 'renders Google Pay with the wrapper, config, context, ' +
		'session and no gateway on the express path', async () => {
		const wallets = args();

		await renderWallets( wallets );

		expect( renderGooglePay ).toHaveBeenCalledWith( {
			wrapper: wallets.wrapper,
			config: wallets.config,
			context: wallets.context,
			session: wallets.sessions.googlepay,
			gateway: undefined,
		} );
	} );

	test.each( [
		[ 'google_pay.enabled is false', args( {
			config: { google_pay: { enabled: false } },
		} ) ],
		// Pins the ?. chain: without it a missing google_pay throws.
		[ 'google_pay is absent from config', args( { config: {} } ) ],
		[ 'sessions.googlepay is missing', args( { sessions: {} } ) ],
	] )( 'does not render when %s', async ( _label, wallets ) => {
		await renderWallets( wallets );

		expect( renderGooglePay ).not.toHaveBeenCalled();
	} );

	// Deliberate: renderAll() logs this, and buttons are already
	// rendered by then, so the rejection is left to propagate here.
	test( 'propagates a renderGooglePay rejection', async () => {
		renderGooglePay.mockRejectedValueOnce( new Error( 'boom' ) );

		await expect( renderWallets( args() ) ).rejects.toThrow( 'boom' );
	} );

	describe( 'as its own payment-method row (gateway set)', () => {
		function gatewayArgs( overrides = {} ) {
			return args( {
				context: 'checkout',
				config: {
					google_pay: {
						enabled: true,
						gateway: {
							id: 'ppcp-googlepay',
							wrapper: '#gateway-row',
						},
					},
				},
				...overrides,
			} );
		}

		test( 'renders into the gateway wrapper instead of the ' +
			'express one, passing the gateway through', async () => {
			document.body.innerHTML = '<div id="gateway-row"></div>';
			const wallets = gatewayArgs();

			await renderWallets( wallets );

			expect( renderGooglePay ).toHaveBeenCalledWith(
				expect.objectContaining( {
					wrapper: document.querySelector( '#gateway-row' ),
					gateway: wallets.config.google_pay.gateway,
				} )
			);
		} );

		test( 'does not render, and does not touch the express wrapper, ' +
			'when the gateway container is not in the DOM', async () => {
			const wallets = gatewayArgs();

			await renderWallets( wallets );

			expect( renderGooglePay ).not.toHaveBeenCalled();
		} );

		test( 'does not render on a non-checkout context, even when the ' +
			'gateway container exists, so the mini-cart target does not ' +
			'render a second button into the shared row', async () => {
			document.body.innerHTML = '<div id="gateway-row"></div>';
			const wallets = gatewayArgs( { context: 'cart' } );

			await renderWallets( wallets );

			expect( renderGooglePay ).not.toHaveBeenCalled();
		} );

		test( 'does not render when the gateway container already has ' +
			'a child from an earlier render pass', async () => {
			document.body.innerHTML =
				'<div id="gateway-row"><span></span></div>';
			const wallets = gatewayArgs();

			await renderWallets( wallets );

			expect( renderGooglePay ).not.toHaveBeenCalled();
		} );
	} );
} );
