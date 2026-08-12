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

import { render, waitFor, act } from '@testing-library/react';
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
	} );
	mockCreateCardOrder.mockReset();
	mockApproveCardOrder.mockReset().mockResolvedValue( undefined );
	mockCardFieldStyles.mockReset().mockReturnValue( { color: 'rgb(0,0,0)' } );
	mockCardFieldContainer.mockClear();
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
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
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
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
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
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

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
			true
		);
	} );

	test( 'passes savePaymentMethod false when the buyer does not opt in and there are no subscriptions', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'succeeded' } );

		renderComponent( { shouldSavePayment: false } );
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			cardConfig(),
			'checkout-block',
			false
		);
	} );

	test( 'reports an error and skips approval when 3D Secure is canceled', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'canceled' } );

		renderComponent();
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

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
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

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
		await waitFor( () => expect( onPaymentSetup ).toHaveBeenCalled() );

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result ).toEqual( { type: 'error', message: 'nonce expired' } );
	} );

	test( 'renders the name field container when card_fields.name_field is enabled', async () => {
		renderComponent();

		await waitFor( () =>
			expect( mockCardFieldContainer ).toHaveBeenCalled()
		);

		const types = mockCardFieldContainer.mock.calls.map(
			( call ) => call[ 0 ].type
		);
		expect( types ).toContain( 'name' );
		expect( types ).toContain( 'number' );
		expect( types ).toContain( 'expiry' );
		expect( types ).toContain( 'cvv' );
	} );

	test( 'does not render the name field container when card_fields.name_field is disabled', async () => {
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

		const types = mockCardFieldContainer.mock.calls.map(
			( call ) => call[ 0 ].type
		);
		expect( types ).not.toContain( 'name' );
		expect( types ).toContain( 'number' );
	} );
} );
