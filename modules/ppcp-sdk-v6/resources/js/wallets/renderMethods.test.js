jest.mock( './googlePay', () => ( {
	renderGooglePay: jest.fn(),
} ) );

jest.mock( './applePay', () => ( {
	renderApplePay: jest.fn(),
} ) );

import { renderGooglePay } from './googlePay';
import { renderApplePay } from './applePay';
import { renderMethods } from './renderMethods';

const args = ( overrides = {} ) => ( {
	wrapper: {},
	config: { google_pay: { styles: { product: {} } } },
	context: 'product',
	sessions: { googlepay: {} },
	...overrides,
} );

beforeEach( () => {
	jest.clearAllMocks();
	document.body.innerHTML = '';
} );

const walletCases = [
	{
		name: 'Google Pay',
		configKey: 'google_pay',
		sessionKey: 'googlepay',
		render: renderGooglePay,
		gatewayId: 'ppcp-googlepay',
		rowSelector: '#gateway-row',
	},
	{
		name: 'Apple Pay',
		configKey: 'apple_pay',
		sessionKey: 'applepay',
		render: renderApplePay,
		gatewayId: 'ppcp-applepay',
		rowSelector: '#gateway-row',
	},
];

describe.each( walletCases )(
	'$name',
	( { configKey, sessionKey, render, gatewayId, rowSelector } ) => {
		function expressArgs( overrides = {} ) {
			return args( {
				config: { [ configKey ]: { styles: { product: {} } } },
				sessions: { [ sessionKey ]: {} },
				...overrides,
			} );
		}

		function gatewayArgs( overrides = {} ) {
			return args( {
				context: 'checkout',
				config: {
					[ configKey ]: {
						styles: { checkout: {}, cart: {} },
						gateway: { id: gatewayId, wrapper: rowSelector },
					},
				},
				sessions: { [ sessionKey ]: {} },
				...overrides,
			} );
		}

		test( 'renders with the method, wrapper, config, context, session and no gateway on the express path', async () => {
			const wallets = expressArgs();

			await renderMethods( wallets );

			expect( render ).toHaveBeenCalledWith( {
				method: sessionKey,
				wrapper: wallets.wrapper,
				config: wallets.config,
				context: wallets.context,
				session: wallets.sessions[ sessionKey ],
				gateway: undefined,
			} );
		} );

		test.each( [
			[
				'no styles entry for this context',
				expressArgs( { config: { [ configKey ]: { styles: {} } } } ),
			],
			// Pins the ?. chain: without it a missing wallet key throws.
			[
				'the wallet is absent from config',
				expressArgs( { config: {} } ),
			],
			[ 'the session is missing', expressArgs( { sessions: {} } ) ],
		] )( 'does not render when %s', async ( _label, wallets ) => {
			await renderMethods( wallets );

			expect( render ).not.toHaveBeenCalled();
		} );

		test( 'renders into the gateway wrapper instead of the express one, passing the gateway through', async () => {
			document.body.innerHTML = `<div id="${ rowSelector.slice(
				1
			) }"></div>`;
			const wallets = gatewayArgs();

			await renderMethods( wallets );

			expect( render ).toHaveBeenCalledWith(
				expect.objectContaining( {
					wrapper: document.querySelector( rowSelector ),
					gateway: wallets.config[ configKey ].gateway,
				} )
			);
		} );

		test( 'does not render, and does not touch the express wrapper, when the gateway container is not in the DOM', async () => {
			const wallets = gatewayArgs();

			await renderMethods( wallets );

			expect( render ).not.toHaveBeenCalled();
		} );

		test(
			'does not render on a non-checkout context, even when the gateway ' +
				'container exists, so the mini-cart target does not render a ' +
				'second button into the shared row',
			async () => {
				document.body.innerHTML = `<div id="${ rowSelector.slice(
					1
				) }"></div>`;
				const wallets = gatewayArgs( { context: 'cart' } );

				await renderMethods( wallets );

				expect( render ).not.toHaveBeenCalled();
			}
		);

		test( 'does not render when the gateway container already has a child from an earlier render pass', async () => {
			document.body.innerHTML = `<div id="${ rowSelector.slice(
				1
			) }"><span></span></div>`;
			const wallets = gatewayArgs();

			await renderMethods( wallets );

			expect( render ).not.toHaveBeenCalled();
		} );

		test(
			'does not render on the cart target a wallet enabled only for the ' +
				"mini-cart, even though PHP's `enabled` flag is true for both contexts",
			async () => {
				const wallets = args( {
					context: 'cart',
					config: { [ configKey ]: { styles: { 'mini-cart': {} } } },
					sessions: { [ sessionKey ]: {} },
				} );

				await renderMethods( wallets );

				expect( render ).not.toHaveBeenCalled();
			}
		);

		test( 'renders on the mini-cart target a wallet enabled only for the mini-cart', async () => {
			const wallets = args( {
				context: 'mini-cart',
				config: { [ configKey ]: { styles: { 'mini-cart': {} } } },
				sessions: { [ sessionKey ]: {} },
			} );

			await renderMethods( wallets );

			expect( render ).toHaveBeenCalled();
		} );
	}
);

describe( 'renderMethods() across wallets', () => {
	test( 'renders both wallets in a single pass, each into its own gateway container', async () => {
		document.body.innerHTML =
			'<div id="googlepay-row"></div><div id="applepay-row"></div>';
		const wallets = args( {
			context: 'checkout',
			config: {
				google_pay: {
					styles: { checkout: {} },
					gateway: {
						id: 'ppcp-googlepay',
						wrapper: '#googlepay-row',
					},
				},
				apple_pay: {
					styles: { checkout: {} },
					gateway: { id: 'ppcp-applepay', wrapper: '#applepay-row' },
				},
			},
			sessions: { googlepay: {}, applepay: {} },
		} );

		await renderMethods( wallets );

		expect( renderGooglePay ).toHaveBeenCalledWith(
			expect.objectContaining( {
				wrapper: document.querySelector( '#googlepay-row' ),
			} )
		);
		expect( renderApplePay ).toHaveBeenCalledWith(
			expect.objectContaining( {
				wrapper: document.querySelector( '#applepay-row' ),
			} )
		);
	} );

	test( 'renders only the wallet configured for this context when the other has no styles entry for it', async () => {
		const wallets = args( {
			config: {
				google_pay: { styles: { product: {} } },
				apple_pay: { styles: {} },
			},
			sessions: { googlepay: {}, applepay: {} },
		} );

		await renderMethods( wallets );

		expect( renderGooglePay ).toHaveBeenCalled();
		expect( renderApplePay ).not.toHaveBeenCalled();
	} );

	// Deliberate: renderAll() logs this, and buttons are already rendered by
	// then, so the rejection is left to propagate here.
	test( 'still calls renderApplePay when renderGooglePay rejects, since both are started before either is awaited', async () => {
		renderGooglePay.mockRejectedValueOnce( new Error( 'boom' ) );
		const wallets = args( {
			config: {
				google_pay: { styles: { product: {} } },
				apple_pay: { styles: { product: {} } },
			},
			sessions: { googlepay: {}, applepay: {} },
		} );

		await expect( renderMethods( wallets ) ).rejects.toThrow( 'boom' );

		expect( renderApplePay ).toHaveBeenCalled();
	} );
} );
