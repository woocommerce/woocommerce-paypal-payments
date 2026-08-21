const mockLoadSdkV6 = jest.fn();
jest.mock( '../sdkLoader', () => ( {
	loadSdkV6: ( ...args ) => mockLoadSdkV6( ...args ),
} ) );

const mockCreateCardOrder = jest.fn();
const mockApproveCardOrder = jest.fn();
jest.mock( '../endpointsAdapter', () => ( {
	createCardOrder: ( ...args ) => mockCreateCardOrder( ...args ),
	approveCardOrder: ( ...args ) => mockApproveCardOrder( ...args ),
} ) );

const mockCardFieldStyles = jest.fn();
jest.mock( '../cardFields/cardFieldStyles', () => ( {
	cardFieldStyles: ( ...args ) => mockCardFieldStyles( ...args ),
} ) );

const mockCardFieldContainer = jest.fn( () => null );
jest.mock( './V6CardFieldContainer', () => ( {
	V6CardFieldContainer: ( props ) => mockCardFieldContainer( props ),
} ) );

const mockCreateCardSetupToken = jest.fn();
const mockExchangeSetupToken = jest.fn();
jest.mock( '../sessions/freeTrialSave', () => ( {
	createCardSetupToken: ( ...args ) => mockCreateCardSetupToken( ...args ),
	exchangeSetupToken: ( ...args ) => mockExchangeSetupToken( ...args ),
} ) );

import {
	render,
	waitFor,
	act,
	screen,
	fireEvent,
} from '@testing-library/react';
import '@testing-library/jest-dom';
import { createElement } from '@wordpress/element';
import { V6CardFieldsComponent } from './V6CardFieldsComponent';

function cardConfig( overrides = {} ) {
	return {
		page_context: 'checkout-block',
		card_fields: {
			enabled: true,
			payment_method: 'ppcp-credit-card-gateway',
			funding_source: 'card',
			title: 'Debit & Credit Cards',
			name_field: true,
			fields: {},
		},
		ajax: {
			create_order: { endpoint: '/co', nonce: 'n-co' },
			approve_order: { endpoint: '/ao', nonce: 'n-ao' },
		},
		...overrides,
	};
}

let onPaymentSetup;
let paymentSetupCb;
let eventRegistration;
let session;
const emitResponse = {
	responseTypes: { SUCCESS: 'success', ERROR: 'error' },
};

function renderComponent( overrides = {} ) {
	return render(
		createElement( V6CardFieldsComponent, {
			config: cardConfig(),
			eventRegistration,
			emitResponse,
			activePaymentMethod: 'ppcp-credit-card-gateway',
			...overrides,
		} )
	);
}

// onPaymentSetup is registered on mount, before the session (which loads
// asynchronously) lands in sessionRef. Waiting only for onPaymentSetup lets
// paymentSetupCb() run ahead of the session, so submitCardPayment sees
// sessionRef.current still null. The mocked V6CardFieldContainer only
// receives the real session once it is set in state, so waiting for that
// call proves sessionRef is actually populated.
async function waitForSessionReady() {
	await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );
	await waitFor( () =>
		expect( mockCardFieldContainer ).toHaveBeenCalledWith(
			expect.objectContaining( { session } )
		)
	);
}

beforeEach( () => {
	paymentSetupCb = null;
	onPaymentSetup = jest.fn( ( cb ) => {
		paymentSetupCb = cb;
		return () => {};
	} );
	eventRegistration = { onPaymentSetup };

	session = {
		createCardFieldsComponent: jest.fn( () =>
			document.createElement( 'div' )
		),
		submit: jest.fn(),
	};

	mockLoadSdkV6.mockReset().mockResolvedValue( {
		createCardFieldsOneTimePaymentSession: () => session,
		createCardFieldsSavePaymentSession: () => session,
	} );
	mockCreateCardOrder.mockReset();
	mockApproveCardOrder.mockReset().mockResolvedValue( undefined );
	mockCardFieldStyles.mockReset().mockReturnValue( { color: 'rgb(0,0,0)' } );
	mockCardFieldContainer.mockClear();
	mockCreateCardSetupToken.mockReset();
	mockExchangeSetupToken.mockReset();
} );

describe( 'V6CardFieldsComponent', () => {
	test( 'subscribes to onPaymentSetup when the active payment method matches', async () => {
		renderComponent();

		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );
	} );

	test( 'does not subscribe to onPaymentSetup when a different payment method is active', async () => {
		renderComponent( { activePaymentMethod: 'ppcp-gateway-paypal' } );

		await waitFor( () =>
			expect( mockCardFieldContainer ).toHaveBeenCalled()
		);
		expect( onPaymentSetup ).not.toHaveBeenCalled();
	} );

	test( 'a successful submission creates the order, submits the session, approves it, and reports success', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
			'',
			false
		);
		expect( session.submit ).toHaveBeenCalledWith( 'ORDER1' );
		expect( mockApproveCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'ORDER1'
		);
		expect( result ).toEqual( { type: 'success' } );
	} );

	test( 'passes savePaymentMethod true to createCardOrder when the buyer opts to save the card', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent( { shouldSavePayment: true } );
		await waitForSessionReady();

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
			'',
			true
		);
	} );

	test( 'forces savePaymentMethod true when the cart has subscriptions, even if the buyer did not opt in', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent( {
			shouldSavePayment: false,
			config: cardConfig( {
				card_fields: {
					...cardConfig().card_fields,
					has_subscriptions: true,
				},
			} ),
		} );
		await waitForSessionReady();

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			expect.objectContaining( {
				card_fields: expect.objectContaining( {
					has_subscriptions: true,
				} ),
			} ),
			'checkout-block',
			'',
			true
		);
	} );

	test( 'passes savePaymentMethod false when the buyer does not opt in and there are no subscriptions', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent( { shouldSavePayment: false } );
		await waitForSessionReady();

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
			'',
			false
		);
	} );

	test( 'reports an error and skips approval when 3D Secure is canceled', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'canceled' } );

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result.type ).toBe( 'error' );
		expect( result.message ).toBeTruthy();
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
	} );

	test( 'reports an error and skips approval when the card payment fails', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'failed' } );

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result.type ).toBe( 'error' );
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
	} );

	test( 'reports the thrown error message when creating the order fails', async () => {
		mockCreateCardOrder.mockRejectedValueOnce(
			new Error( 'nonce expired' )
		);

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result ).toEqual( { type: 'error', message: 'nonce expired' } );
	} );

	test( 'renders the cardholder name as a plain input, not a V6CardFieldContainer, when card_fields.name_field is enabled', async () => {
		renderComponent();

		await waitFor( () =>
			expect( mockCardFieldContainer ).toHaveBeenCalled()
		);

		expect(
			screen.getByPlaceholderText( 'Cardholder name (optional)' )
		).toBeInTheDocument();

		const types = mockCardFieldContainer.mock.calls.map(
			( call ) => call[ 0 ].type
		);
		expect( types ).not.toContain( 'name' );
		expect( types ).toEqual(
			expect.arrayContaining( [ 'number', 'expiry', 'cvv' ] )
		);
		expect( mockCardFieldContainer ).toHaveBeenCalledTimes( 3 );
	} );

	test( 'does not render the name input when card_fields.name_field is disabled', async () => {
		renderComponent( {
			config: cardConfig( {
				card_fields: {
					...cardConfig().card_fields,
					name_field: false,
				},
			} ),
		} );

		await waitFor( () =>
			expect( mockCardFieldContainer ).toHaveBeenCalled()
		);

		expect(
			screen.queryByPlaceholderText( 'Cardholder name (optional)' )
		).not.toBeInTheDocument();

		const types = mockCardFieldContainer.mock.calls.map(
			( call ) => call[ 0 ].type
		);
		expect( types ).not.toContain( 'name' );
		expect( types ).toContain( 'number' );
	} );

	test( 'forwards the typed cardholder name to createCardOrder on submit', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent();
		await waitForSessionReady();

		const nameInput = screen.getByPlaceholderText(
			'Cardholder name (optional)'
		);
		fireEvent.change( nameInput, { target: { value: 'Jane Doe' } } );

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
			'Jane Doe',
			false
		);
	} );

	test( 'submits the session with the billing address derived from the Blocks billing prop', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent( {
			billing: {
				billingAddress: { postcode: '90001', country: 'US' },
			},
		} );
		await waitForSessionReady();

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( session.submit ).toHaveBeenCalledWith( 'ORDER1', {
			billingAddress: { postalCode: '90001', countryCode: 'US' },
		} );
	} );

	test( 'submits the session with no options when the Blocks billing prop has no postcode', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent( { billing: { billingAddress: {} } } );
		await waitForSessionReady();

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( session.submit ).toHaveBeenCalledWith( 'ORDER1' );
	} );

	describe( 'free-trial ($0 subscription) cart', () => {
		function freeTrialConfig( overrides = {} ) {
			return cardConfig( { is_free_trial_cart: true, ...overrides } );
		}

		test( 'creates the card save session instead of the one-time session', async () => {
			mockLoadSdkV6.mockResolvedValueOnce( {
				createCardFieldsOneTimePaymentSession: jest.fn(
					() => new Error( 'must not be called' )
				),
				createCardFieldsSavePaymentSession: () => session,
			} );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			expect( mockCardFieldContainer ).toHaveBeenCalledWith(
				expect.objectContaining( { session } )
			);
		} );

		test( 'a successful save exchanges the setup token instead of creating and approving an order', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( mockCreateCardSetupToken ).toHaveBeenCalledWith(
				freeTrialConfig()
			);
			expect( session.submit ).toHaveBeenCalledWith( 'SETUP1' );
			expect( mockExchangeSetupToken ).toHaveBeenCalledWith(
				freeTrialConfig(),
				'SETUP1',
				freeTrialConfig().card_fields.payment_method
			);
			expect( mockCreateCardOrder ).not.toHaveBeenCalled();
			expect( mockApproveCardOrder ).not.toHaveBeenCalled();
			expect( result ).toEqual( { type: 'success' } );
		} );

		test( 'reports an error and skips the exchange when 3D Secure is canceled', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'canceled' } );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result.type ).toBe( 'error' );
			expect( result.message ).toBeTruthy();
			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
		} );

		test( 'reports an error and skips the exchange when the save session fails', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'failed' } );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result.type ).toBe( 'error' );
			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
		} );

		test( 'reports the thrown error message when the token exchange fails', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'succeeded' } );
			mockExchangeSetupToken.mockRejectedValueOnce(
				new Error( 'exchange failed' )
			);

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result ).toEqual( {
				type: 'error',
				message: 'exchange failed',
			} );
		} );
	} );
} );
