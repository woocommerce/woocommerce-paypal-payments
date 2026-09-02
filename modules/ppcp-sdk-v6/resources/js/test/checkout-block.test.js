import { loadSdkV6 } from '../sdkLoader';
import { checkEligibility } from '../eligibility';

const mockRegisterExpressPaymentMethod = jest.fn();
const mockRegisterPaymentMethod = jest.fn();
// virtual: webpack resolves this to the wc.wcBlocksRegistry global, so there is
// no package under node_modules for Jest to find.
jest.mock(
	'@woocommerce/blocks-registry',
	() => ( {
		registerExpressPaymentMethod: ( ...args ) =>
			mockRegisterExpressPaymentMethod( ...args ),
		registerPaymentMethod: ( ...args ) =>
			mockRegisterPaymentMethod( ...args ),
	} ),
	{ virtual: true }
);

jest.mock( '../sdkLoader', () => ( { loadSdkV6: jest.fn() } ) );
jest.mock( '../eligibility', () => ( { checkEligibility: jest.fn() } ) );
jest.mock( '../utils/errorHandler', () => ( { setErrorLabels: jest.fn() } ) );
jest.mock( '../blocks/V6ExpressComponent', () => ( {
	V6ExpressComponent: () => null,
} ) );
jest.mock( '../blocks/V6WalletComponent', () => ( {
	V6WalletComponent: () => null,
} ) );
jest.mock( '../blocks/V6ContinuationComponent', () => ( {
	V6ContinuationComponent: () => null,
} ) );
jest.mock( '../blocks/V6CardFieldsComponent', () => ( {
	V6CardFieldsComponent: () => null,
} ) );
jest.mock( '../blocks/V6EditorPreview', () => ( {
	V6EditorPreview: () => null,
} ) );
jest.mock( '@ppcp-blocks/Components/paypal-saved-token', () => ( {
	PayPalSavedToken: () => null,
} ) );
jest.mock( '../wallets/applePay', () => ( {
	isDeviceEligible: jest.fn( () => true ),
} ) );
jest.mock( '../messages/renderer', () => ( {
	initMessages: jest.fn( () => Promise.resolve() ),
	updateMessagesAmount: jest.fn(),
} ) );
jest.mock( '../messages/cartTotalWatcher', () => ( {
	watchBlockCartTotal: jest.fn(),
} ) );

/**
 * The wc_ppcp_sdk_v6 config shape checkout-block.js reads for a normal
 * (non-continuation) checkout, with both wallets enabled and styled so the
 * express registration loop reaches every funding source.
 */
const baseConfig = ( overrides = {} ) => ( {
	id: 'ppcp-gateway',
	page_context: 'checkout',
	supported_features: [ 'products', 'subscriptions' ],
	pay_later_button: { checkout: true },
	google_pay: {
		enabled: true,
		styles: { checkout: {} },
		supported_features: [ 'products' ],
	},
	apple_pay: {
		enabled: true,
		styles: { checkout: {} },
		supported_features: [ 'products' ],
	},
	...overrides,
} );

/**
 * Sets the wcSettings global the module reads at import time, then imports it
 * fresh so its module-scope registration runs against this test's config.
 *
 * @param {Object} config - The 'ppcp-sdk-v6' payment method data.
 */
function loadCheckoutBlock( config ) {
	window.wc = {
		wcSettings: {
			getSetting: ( key ) =>
				key === 'paymentMethodData' ? { 'ppcp-sdk-v6': config } : undefined,
		},
	};
	jest.isolateModules( () => {
		require( '../checkout-block.js' );
	} );
}

/**
 * The registerExpressPaymentMethod call for one registered name.
 *
 * @param {string} name - The registration name to find.
 * @return {Object} The args object passed to registerExpressPaymentMethod.
 */
function expressCallFor( name ) {
	const call = mockRegisterExpressPaymentMethod.mock.calls.find(
		( [ args ] ) => args.name === name
	);
	return call[ 0 ];
}

/**
 * The registerPaymentMethod calls for one registered name.
 *
 * @param {string} name - The registration name to find.
 * @return {Object[]} The args objects passed to registerPaymentMethod.
 */
function regularCallsFor( name ) {
	return mockRegisterPaymentMethod.mock.calls
		.filter( ( [ args ] ) => args.name === name )
		.map( ( [ args ] ) => args );
}

/**
 * The single registerPaymentMethod call for one registered name.
 *
 * @param {string} name - The registration name to find.
 * @return {Object} The args object passed to registerPaymentMethod.
 */
function regularCallFor( name ) {
	return regularCallsFor( name )[ 0 ];
}

beforeEach( () => {
	jest.clearAllMocks();
} );

afterEach( () => {
	delete window.wc;
} );

describe( 'checkout-block', () => {
	describe( 'express method registration', () => {
		test.each( [
			[ 'ppcp-gateway-paypal', [ 'products', 'subscriptions' ] ],
			[ 'ppcp-gateway-venmo', [ 'products', 'subscriptions' ] ],
			[ 'ppcp-gateway-paylater', [ 'products', 'subscriptions' ] ],
			[ 'ppcp-googlepay', [ 'products' ] ],
			[ 'ppcp-applepay', [ 'products' ] ],
		] )(
			'%s declares ppcp_continuation alongside its own supported features',
			( name, ownFeatures ) => {
				loadCheckoutBlock( baseConfig() );

				const { supports } = expressCallFor( name );

				expect( supports.features ).toEqual( [
					...ownFeatures,
					'ppcp_continuation',
				] );
			}
		);

		/**
		 * Regression test: WooCommerce Blocks withdraws any payment method whose
		 * supports.features misses a cart requirement. The plugin's Store API
		 * requirement flips to ['ppcp_continuation'] the moment the buyer
		 * approves in the express sheet, i.e. mid-flow for the very method that
		 * is submitting the checkout. Without the feature here that method is
		 * withdrawn along with the rest, activePaymentMethod clears, and the
		 * checkout POST goes out with no payment_method.
		 */
		test( 'ppcp_continuation is present even when the gateway declares no supported_features of its own', () => {
			loadCheckoutBlock( baseConfig( { supported_features: undefined } ) );

			const { supports } = expressCallFor( 'ppcp-gateway-paypal' );

			expect( supports.features ).toEqual( [
				'products',
				'ppcp_continuation',
			] );
		} );

		test( 'a wallet with no supported_features of its own still gets ppcp_continuation', () => {
			loadCheckoutBlock(
				baseConfig( {
					google_pay: {
						enabled: true,
						styles: { checkout: {} },
						supported_features: undefined,
					},
				} )
			);

			const { supports } = expressCallFor( 'ppcp-googlepay' );

			expect( supports.features ).toEqual( [
				'products',
				'ppcp_continuation',
			] );
		} );

		test( 'ppcp-gateway-paypal processes through the gateway id the server supplied', () => {
			loadCheckoutBlock( baseConfig( { id: 'ppcp-gateway-custom' } ) );

			expect( expressCallFor( 'ppcp-gateway-paypal' ).gatewayId ).toBe(
				'ppcp-gateway-custom'
			);
		} );
	} );

	describe( 'canMakePayment on a subscription cart (cart_needs_vaulting)', () => {
		const eligibleForEveryMethod = {
			paypal: true,
			venmo: true,
			paylater: true,
			googlepay: true,
			applepay: true,
		};

		beforeEach( () => {
			loadSdkV6.mockResolvedValue( {} );
			checkEligibility.mockResolvedValue( eligibleForEveryMethod );
		} );

		test( 'a cart that became $0 after page load is a free trial even though the server said it was not', async () => {
			loadCheckoutBlock(
				baseConfig( {
					cart_needs_vaulting: true,
					is_free_trial_cart: false,
				} )
			);
			const cartTotals = { total_price: '0', currency_minor_unit: 2 };

			const results = await Promise.all(
				[
					'ppcp-gateway-paypal',
					'ppcp-gateway-venmo',
					'ppcp-gateway-paylater',
					'ppcp-googlepay',
					'ppcp-applepay',
				].map( ( name ) =>
					expressCallFor( name ).canMakePayment( { cartTotals } )
				)
			);

			expect( results ).toEqual( [ true, false, false, false, false ] );
		} );

		test( 'a cart that rose above $0 after page load is not a free trial even though the server said it was', async () => {
			loadCheckoutBlock(
				baseConfig( {
					cart_needs_vaulting: true,
					is_free_trial_cart: true,
				} )
			);
			const cartTotals = {
				total_price: '4900',
				currency_minor_unit: 2,
			};

			const results = await Promise.all(
				[
					'ppcp-gateway-paypal',
					'ppcp-gateway-venmo',
					'ppcp-gateway-paylater',
					'ppcp-googlepay',
					'ppcp-applepay',
				].map( ( name ) =>
					expressCallFor( name ).canMakePayment( { cartTotals } )
				)
			);

			expect( results ).toEqual( [ true, true, true, true, true ] );
		} );

		test( 'a non-subscription cart at $0 is unaffected: eligibility alone decides', async () => {
			loadCheckoutBlock(
				baseConfig( {
					cart_needs_vaulting: false,
					is_free_trial_cart: false,
				} )
			);
			const cartTotals = { total_price: '0', currency_minor_unit: 2 };

			const results = await Promise.all(
				[
					'ppcp-gateway-paypal',
					'ppcp-gateway-venmo',
					'ppcp-gateway-paylater',
					'ppcp-googlepay',
					'ppcp-applepay',
				].map( ( name ) =>
					expressCallFor( name ).canMakePayment( { cartTotals } )
				)
			);

			expect( results ).toEqual( [ true, true, true, true, true ] );
		} );
	} );

	describe( 'regular ppcp-gateway registration', () => {
		describe( 'when the place-order row is enabled', () => {
			test( 'registers ppcp-gateway as a regular payment method even with no vault component', () => {
				loadCheckoutBlock(
					baseConfig( {
						place_order: { enabled: true, text: 'Complete order' },
					} )
				);

				expect( regularCallFor( 'ppcp-gateway' ) ).toBeDefined();
			} );

			test( 'uses place_order.text as the placeOrderButtonLabel', () => {
				loadCheckoutBlock(
					baseConfig( {
						place_order: { enabled: true, text: 'Complete order' },
					} )
				);

				expect(
					regularCallFor( 'ppcp-gateway' ).placeOrderButtonLabel
				).toBe( 'Complete order' );
			} );

			test( 'does not show saved cards when the vault component is not eligible', () => {
				loadCheckoutBlock(
					baseConfig( {
						place_order: { enabled: true, text: 'Complete order' },
					} )
				);

				expect(
					regularCallFor( 'ppcp-gateway' ).supports.showSavedCards
				).toBe( false );
			} );

			test.each( [
				{
					name: 'a subscription cart is allowed even at a $0 live total',
					hasSubscriptions: true,
					totalPrice: '0',
					expected: true,
				},
				{
					name: 'a non-subscription cart at a $0 live total is not allowed',
					hasSubscriptions: false,
					totalPrice: '0',
					expected: false,
				},
				{
					name: 'a non-subscription cart with a positive live total is allowed',
					hasSubscriptions: false,
					totalPrice: '4900',
					expected: true,
				},
			] )( '$name', ( { hasSubscriptions, totalPrice, expected } ) => {
				loadCheckoutBlock(
					baseConfig( {
						place_order: { enabled: true, text: 'Complete order' },
						has_subscriptions: hasSubscriptions,
						amount: '10.00',
					} )
				);

				const { canMakePayment } = regularCallFor( 'ppcp-gateway' );

				expect(
					canMakePayment( {
						cartTotals: {
							total_price: totalPrice,
							currency_minor_unit: 2,
						},
					} )
				).toBe( expected );
			} );
		} );

		describe( 'when only the saved-PayPal vault row is eligible', () => {
			test( 'registers ppcp-gateway with a savedTokenComponent, no placeOrderButtonLabel, and unconditional canMakePayment', () => {
				loadCheckoutBlock(
					baseConfig( {
						vault_component: { is_eligible: true },
					} )
				);

				const call = regularCallFor( 'ppcp-gateway' );

				expect( call.supports.showSavedCards ).toBe( true );
				expect( call.savedTokenComponent ).toBeDefined();
				expect( call ).not.toHaveProperty( 'placeOrderButtonLabel' );
				expect(
					call.canMakePayment( {
						cartTotals: {
							total_price: '0',
							currency_minor_unit: 2,
						},
					} )
				).toBe( true );
			} );
		} );

		test( 'registers the regular row under the gateway id the server supplied, not the literal ppcp-gateway', () => {
			loadCheckoutBlock(
				baseConfig( {
					id: 'ppcp-gateway-custom',
					place_order: { enabled: true, text: 'Complete order' },
				} )
			);

			expect( regularCallFor( 'ppcp-gateway' ) ).toBeUndefined();
			expect( regularCallFor( 'ppcp-gateway-custom' ) ).toBeDefined();
		} );

		test( 'does not register ppcp-gateway when neither the place-order row nor vault eligibility apply', () => {
			loadCheckoutBlock( baseConfig() );

			expect( regularCallFor( 'ppcp-gateway' ) ).toBeUndefined();
		} );

		test( 'continuation mode registers ppcp-gateway once, keeping the continuation shape', () => {
			loadCheckoutBlock(
				baseConfig( {
					continuation: { funding_source: 'paypal' },
					place_order: { enabled: true, text: 'Complete order' },
					vault_component: { is_eligible: true },
				} )
			);

			const calls = regularCallsFor( 'ppcp-gateway' );

			expect( calls ).toHaveLength( 1 );
			expect( calls[ 0 ].supports.features ).toContain(
				'ppcp_continuation'
			);
		} );
	} );
} );
