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
	} );
} );
