const mockHostedFieldTextStyles = jest.fn( () => ( {
	color: 'rgb(0, 0, 0)',
} ) );
jest.mock( '../cardFields/cardFieldStyles', () => ( {
	hostedFieldTextStyles: ( field ) => mockHostedFieldTextStyles( field ),
} ) );

const mockHide = jest.fn();
jest.mock(
	'@ppcp-button/Helper/Hiding',
	() => ( { hide: ( ...args ) => mockHide( ...args ) } ),
	{ virtual: true }
);

const mockGetCurrentPaymentMethod = jest.fn();
jest.mock( '@ppcp-button/Helper/CheckoutMethodState', () => ( {
	getCurrentPaymentMethod: () => mockGetCurrentPaymentMethod(),
} ) );

const mockLoadSdkV6 = jest.fn();
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

const mockPostJson = jest.fn();
jest.mock( '../utils/api', () => ( {
	postJson: ( ...args ) => mockPostJson( ...args ),
} ) );

const mockHandleError = jest.fn();
jest.mock( '../utils/errorHandler', () => ( {
	handleError: ( ...args ) => mockHandleError( ...args ),
} ) );

const mockNavigationAssign = jest.fn();
jest.mock( '../utils/navigation', () => ( {
	navigation: { assign: ( ...args ) => mockNavigationAssign( ...args ) },
} ) );

import { initCardSaveFields } from '../cardFields/saveRenderer';

/**
 * Drains all pending microtasks (unlike chained `await Promise.resolve()`,
 * this doesn't require knowing exactly how many promise hops the code
 * under test awaits internally).
 *
 * @return {Promise<void>}
 */
const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

/**
 * Builds the add-payment-method page DOM this module expects: the three
 * (number/expiry/cvv) WC input fields plus the place-order submit button.
 */
function buildAddPaymentMethodDom() {
	document.body.innerHTML = `
		<form>
			<div class="input-wrapper"><input id="ppcp-credit-card-gateway-card-number" /></div>
			<div class="input-wrapper"><input id="ppcp-credit-card-gateway-card-expiry" /></div>
			<div class="input-wrapper"><input id="ppcp-credit-card-gateway-card-cvc" /></div>
			<button id="place_order" type="button">Add payment method</button>
		</form>
	`;
}

const baseConfig = ( overrides = {} ) => ( {
	card_fields: {
		enabled: true,
		payment_method: 'ppcp-credit-card-gateway',
		fields: {
			number: '#ppcp-credit-card-gateway-card-number',
			expiry: '#ppcp-credit-card-gateway-card-expiry',
			cvv: '#ppcp-credit-card-gateway-card-cvc',
			name: null,
		},
	},
	ajax: {
		create_setup_token: { endpoint: '/cst', nonce: 'n-cst' },
		create_payment_token: { endpoint: '/cpt', nonce: 'n-cpt' },
	},
	verification_method: 'SCA_WHEN_REQUIRED',
	payment_methods_page: '/my-account/payment-methods/',
	...overrides,
} );

function makeCardSession( { state = 'succeeded' } = {} ) {
	return {
		createCardFieldsComponent: jest.fn( () =>
			document.createElement( 'div' )
		),
		submit: jest.fn().mockResolvedValue( { state } ),
	};
}

beforeEach( () => {
	jest.clearAllMocks();
	mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-credit-card-gateway' );
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'initCardSaveFields', () => {
	test( 'does nothing when card fields are disabled', () => {
		buildAddPaymentMethodDom();
		initCardSaveFields( baseConfig( { card_fields: { enabled: false } } ) );

		expect( mockLoadSdkV6 ).not.toHaveBeenCalled();
	} );

	test( 'a successful submission creates the setup token, submits the card session, exchanges it, and redirects', async () => {
		buildAddPaymentMethodDom();
		const cardSession = makeCardSession( { state: 'succeeded' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsSavePaymentSession: () => cardSession,
		} );
		mockPostJson
			.mockResolvedValueOnce( { id: 'SETUP1' } )
			.mockResolvedValueOnce( {} );

		initCardSaveFields( baseConfig() );
		await flushPromises();

		const placeOrder = document.querySelector( '#place_order' );
		placeOrder.click();
		await flushPromises();

		expect( mockPostJson ).toHaveBeenNthCalledWith(
			1,
			baseConfig().ajax.create_setup_token,
			{
				payment_method: 'ppcp-credit-card-gateway',
				verification_method: 'SCA_WHEN_REQUIRED',
			}
		);
		expect( cardSession.submit ).toHaveBeenCalledWith( 'SETUP1' );
		expect( mockPostJson ).toHaveBeenNthCalledWith(
			2,
			baseConfig().ajax.create_payment_token,
			{ vault_setup_token: 'SETUP1' }
		);
		expect( mockNavigationAssign ).toHaveBeenCalledWith(
			'/my-account/payment-methods/'
		);
		expect( mockHandleError ).not.toHaveBeenCalled();
	} );

	test( 'a canceled 3DS challenge is silent and does not exchange the token or redirect', async () => {
		buildAddPaymentMethodDom();
		const cardSession = makeCardSession( { state: 'canceled' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsSavePaymentSession: () => cardSession,
		} );
		mockPostJson.mockResolvedValueOnce( { id: 'SETUP1' } );

		initCardSaveFields( baseConfig() );
		await flushPromises();

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		expect( mockPostJson ).toHaveBeenCalledTimes( 1 );
		expect( mockNavigationAssign ).not.toHaveBeenCalled();
		expect( mockHandleError ).not.toHaveBeenCalled();
	} );

	test( 'a failed card session submit surfaces the error and does not redirect', async () => {
		buildAddPaymentMethodDom();
		const cardSession = makeCardSession( { state: 'failed' } );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsSavePaymentSession: () => cardSession,
		} );
		mockPostJson.mockResolvedValueOnce( { id: 'SETUP1' } );

		initCardSaveFields( baseConfig() );
		await flushPromises();

		document.querySelector( '#place_order' ).click();
		await flushPromises();

		expect( mockHandleError ).toHaveBeenCalled();
		expect( mockNavigationAssign ).not.toHaveBeenCalled();
	} );

	test( 'lets the native click through untouched when a different gateway is selected', async () => {
		buildAddPaymentMethodDom();
		mockGetCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
		mockLoadSdkV6.mockResolvedValue( {
			createCardFieldsSavePaymentSession: () => makeCardSession(),
		} );

		initCardSaveFields( baseConfig() );
		await flushPromises();

		const event = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		document.querySelector( '#place_order' ).dispatchEvent( event );
		await flushPromises();

		expect( event.defaultPrevented ).toBe( false );
		expect( mockPostJson ).not.toHaveBeenCalledWith(
			baseConfig().ajax.create_setup_token,
			expect.anything()
		);
	} );
} );
