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
const mockHostedFieldTextStyles = jest.fn();
jest.mock( '../cardFields/cardFieldStyles', () => ( {
	cardFieldStyles: ( ...args ) => mockCardFieldStyles( ...args ),
	hostedFieldTextStyles: ( ...args ) => mockHostedFieldTextStyles( ...args ),
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
const { V6CardFieldContainer: ActualV6CardFieldContainer } = jest.requireActual(
	'./V6CardFieldContainer'
);

// The component detects floating-label support by querying for this markup.
function appendFloatingLabelMarker() {
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'wc-block-components-text-input';
	wrapper.appendChild( document.createElement( 'input' ) );
	document.body.appendChild( wrapper );
	return wrapper;
}

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
let onCheckoutValidation;
let checkoutValidationCb;
let onCheckoutFail;
let checkoutFailCb;
let hasValidationErrors;
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

async function waitForCheckoutValidationReady() {
	await waitFor( () => expect( checkoutValidationCb ).not.toBeNull() );
}

async function waitForCheckoutFailReady() {
	await waitFor( () => expect( checkoutFailCb ).not.toBeNull() );
}

beforeEach( () => {
	paymentSetupCb = null;
	onPaymentSetup = jest.fn( ( cb ) => {
		paymentSetupCb = cb;
		return () => {};
	} );
	checkoutValidationCb = null;
	onCheckoutValidation = jest.fn( ( cb ) => {
		checkoutValidationCb = cb;
		return () => {};
	} );
	checkoutFailCb = null;
	onCheckoutFail = jest.fn( ( cb ) => {
		checkoutFailCb = cb;
		return () => {};
	} );
	eventRegistration = {
		onPaymentSetup,
		onCheckoutValidation,
		onCheckoutFail,
	};

	hasValidationErrors = false;
	global.wp = {
		data: {
			select: ( store ) => {
				if ( store === 'wc/store/validation' ) {
					return {
						hasValidationErrors: () => hasValidationErrors,
					};
				}

				return {};
			},
		},
	};

	session = {
		createCardFieldsComponent: jest.fn( () =>
			document.createElement( 'div' )
		),
		submit: jest.fn(),
		on: jest.fn(),
	};

	mockLoadSdkV6.mockReset().mockResolvedValue( {
		createCardFieldsOneTimePaymentSession: () => session,
		createCardFieldsSavePaymentSession: () => session,
	} );
	mockCreateCardOrder.mockReset();
	mockApproveCardOrder.mockReset().mockResolvedValue( undefined );
	mockCardFieldStyles
		.mockReset()
		.mockReturnValue( { color: 'rgb(0,0,0)', height: '40px' } );
	mockHostedFieldTextStyles
		.mockReset()
		.mockReturnValue( { color: 'rgb(0,0,0)' } );
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

	test( 'does not subscribe to onCheckoutValidation when a different payment method is active', async () => {
		renderComponent( { activePaymentMethod: 'ppcp-gateway-paypal' } );

		await waitFor( () =>
			expect( mockCardFieldContainer ).toHaveBeenCalled()
		);
		expect( onCheckoutValidation ).not.toHaveBeenCalled();
	} );

	test( 'renders and still subscribes to onPaymentSetup when the Blocks registry supplies no onCheckoutValidation', async () => {
		renderComponent( { eventRegistration: { onPaymentSetup } } );

		await waitForSessionReady();

		expect( onPaymentSetup ).toHaveBeenCalled();
	} );

	test( 'the root container and the expiry/CVV row carry the class hooks the stylesheet spacing depends on', async () => {
		renderComponent();
		await waitForSessionReady();

		expect(
			document.querySelector( '.ppcp-sdk-v6-card-fields' )
		).toBeInTheDocument();
		expect(
			document.querySelector( '.ppcp-sdk-v6-card-fields__row' )
		).toBeInTheDocument();
	} );

	test( 'a pending checkout validation error blocks the card submission before an order is created', async () => {
		renderComponent();
		await waitForSessionReady();

		hasValidationErrors = true;

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).not.toHaveBeenCalled();
		expect( session.submit ).not.toHaveBeenCalled();
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
		expect( result.type ).toBe( 'error' );
	} );

	describe( 'onCheckoutValidation observer', () => {
		test( 'returns a bare error response when the form is invalid', async () => {
			renderComponent();
			await waitForCheckoutValidationReady();

			hasValidationErrors = true;

			expect( checkoutValidationCb() ).toEqual( { type: 'error' } );
		} );

		test( 'returns true when the form is valid', async () => {
			renderComponent();
			await waitForCheckoutValidationReady();

			expect( checkoutValidationCb() ).toBe( true );
		} );

		test( 'a form that passes validation and then turns invalid before payment setup still blocks order creation', async () => {
			renderComponent();
			await waitForSessionReady();
			await waitForCheckoutValidationReady();

			expect( checkoutValidationCb() ).toBe( true );

			hasValidationErrors = true;

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( mockCreateCardOrder ).not.toHaveBeenCalled();
			expect( result.type ).toBe( 'error' );
		} );
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
				has_subscriptions: true,
			} ),
		} );
		await waitForSessionReady();

		await act( async () => {
			await paymentSetupCb();
		} );

		expect( mockCreateCardOrder ).toHaveBeenCalledWith(
			expect.objectContaining( {
				has_subscriptions: true,
			} ),
			'checkout-block',
			'',
			true
		);
	} );

	test( 'renders a checked, disabled locked save-option checkbox when the cart has subscriptions and vaulting is enabled', async () => {
		renderComponent( {
			config: cardConfig( {
				has_subscriptions: true,
				card_fields: {
					...cardConfig().card_fields,
					is_vaulting_enabled: true,
				},
			} ),
		} );
		await waitForSessionReady();

		const checkbox = document.getElementById(
			'ppcp-sdk-v6-save-payment-method'
		);
		expect( checkbox ).toBeInTheDocument();
		expect( checkbox ).toBeChecked();
		expect( checkbox ).toBeDisabled();
	} );

	test.each( [
		[
			'the cart has no subscriptions',
			{ has_subscriptions: false, is_vaulting_enabled: true },
		],
		[
			'vaulting is disabled',
			{ has_subscriptions: true, is_vaulting_enabled: false },
		],
	] )(
		'does not render the locked save-option checkbox when %s',
		async ( _label, { has_subscriptions, is_vaulting_enabled } ) => {
			renderComponent( {
				config: cardConfig( {
					has_subscriptions,
					card_fields: {
						...cardConfig().card_fields,
						is_vaulting_enabled,
					},
				} ),
			} );
			await waitForSessionReady();

			expect(
				document.getElementById( 'ppcp-sdk-v6-save-payment-method' )
			).not.toBeInTheDocument();
		}
	);

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

	test( 'reports the "authentication not completed" message and skips approval when 3D Secure is canceled', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'canceled' } );

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result ).toEqual( {
			type: 'error',
			message: 'Card authentication was not completed. Please try again.',
		} );
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
	} );

	test( 'reports the friendly decline message and skips approval when the card payment does not succeed', async () => {
		mockCreateCardOrder.mockResolvedValueOnce( { orderId: 'ORDER1' } );
		session.submit.mockResolvedValueOnce( { state: 'failed' } );

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result ).toEqual( {
			type: 'error',
			message:
				'This card could not be authorized. Please try a different payment method.',
		} );
		expect( mockApproveCardOrder ).not.toHaveBeenCalled();
	} );

	test( 'shows the friendly decline message instead of leaking a thrown error that is not marked user-facing', async () => {
		mockCreateCardOrder.mockRejectedValueOnce(
			new Error( 'PayPal SDK internal failure XYZ' )
		);

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result ).toEqual( {
			type: 'error',
			message:
				'This card could not be authorized. Please try a different payment method.',
		} );
	} );

	test( 'shows a thrown error verbatim when it is marked user-facing', async () => {
		const userFacing = new Error( 'Your card was declined by the bank.' );
		userFacing.isUserFacing = true;
		mockCreateCardOrder.mockRejectedValueOnce( userFacing );

		renderComponent();
		await waitForSessionReady();

		let result;
		await act( async () => {
			result = await paymentSetupCb();
		} );

		expect( result ).toEqual( {
			type: 'error',
			message: 'Your card was declined by the bank.',
		} );
	} );

	test( "passes the reference input's height to each V6CardFieldContainer via its own height prop, not inside style", async () => {
		renderComponent();

		await waitFor( () =>
			expect( mockCardFieldContainer ).toHaveBeenCalled()
		);

		for ( const call of mockCardFieldContainer.mock.calls ) {
			expect( call[ 0 ].height ).toBe( '40px' );
			expect( call[ 0 ].style ).not.toHaveProperty( 'height' );
		}
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

	test( 'keeps the plain placeholder and passes floatingLabel false to each field when no Blocks text-input markup is on the page', async () => {
		renderComponent();
		await waitForSessionReady();

		[ 'number', 'expiry', 'cvv' ].forEach( ( type ) => {
			const call = mockCardFieldContainer.mock.calls.find(
				( c ) => c[ 0 ].type === type
			);
			expect( call[ 0 ].floatingLabel ).toBe( false );
		} );

		expect(
			screen.getByPlaceholderText( 'Cardholder name (optional)' )
		).toBeInTheDocument();
		expect(
			document.querySelector( 'label[for="ppcp-sdk-v6-card-name"]' )
		).not.toBeInTheDocument();
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
			return cardConfig( {
				cart_needs_vaulting: true,
				is_free_trial_cart: true,
				...overrides,
			} );
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

		test( 'a pending checkout validation error blocks the card save before a setup token is created', async () => {
			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			hasValidationErrors = true;

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( mockCreateCardSetupToken ).not.toHaveBeenCalled();
			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
			expect( result.type ).toBe( 'error' );
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
				'SETUP1'
			);
			expect( mockCreateCardOrder ).not.toHaveBeenCalled();
			expect( mockApproveCardOrder ).not.toHaveBeenCalled();
			expect( result ).toEqual( { type: 'success' } );
		} );

		test( 'reports the "authentication not completed" message and skips the exchange when 3D Secure is canceled', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'canceled' } );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result ).toEqual( {
				type: 'error',
				message:
					'Card authentication was not completed. Please try again.',
			} );
			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
		} );

		test( 'reports the friendly decline message and skips the exchange when the save session does not succeed', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'failed' } );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result ).toEqual( {
				type: 'error',
				message:
					'This card could not be authorized. Please try a different card.',
			} );
			expect( mockExchangeSetupToken ).not.toHaveBeenCalled();
		} );

		test( 'shows the friendly save-decline message instead of leaking a thrown error that is not marked user-facing', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'succeeded' } );
			mockExchangeSetupToken.mockRejectedValueOnce(
				new Error( 'internal exchange error' )
			);

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result ).toEqual( {
				type: 'error',
				message:
					'This card could not be authorized. Please try a different card.',
			} );
		} );

		test( 'shows a thrown error verbatim when it is marked user-facing', async () => {
			mockCreateCardSetupToken.mockResolvedValueOnce( 'SETUP1' );
			session.submit.mockResolvedValueOnce( { state: 'succeeded' } );
			const userFacing = new Error( 'This card is already saved.' );
			userFacing.isUserFacing = true;
			mockExchangeSetupToken.mockRejectedValueOnce( userFacing );

			renderComponent( { config: freeTrialConfig() } );
			await waitForSessionReady();

			let result;
			await act( async () => {
				result = await paymentSetupCb();
			} );

			expect( result ).toEqual( {
				type: 'error',
				message: 'This card is already saved.',
			} );
		} );
	} );

	describe( 'onCheckoutFail observer', () => {
		test( 'surfaces the gateway decline message from processingResponse when present', async () => {
			renderComponent();
			await waitForCheckoutFailReady();

			const result = checkoutFailCb( {
				processingResponse: {
					paymentDetails: {
						errorMessage: 'Card declined during capture.',
					},
				},
			} );

			expect( result ).toEqual( {
				type: 'error',
				message: 'Card declined during capture.',
			} );
		} );

		test( 'returns true, leaving the default checkout error, when no gateway error message is present', async () => {
			renderComponent();
			await waitForCheckoutFailReady();

			expect(
				checkoutFailCb( { processingResponse: {} } )
			).toBe( true );
		} );
	} );

	describe( 'floating-label mode (Blocks text-input markup present)', () => {
		let marker;

		beforeEach( () => {
			marker = appendFloatingLabelMarker();
		} );

		afterEach( () => {
			marker.remove();
		} );

		function lastCallFor( type ) {
			const calls = mockCardFieldContainer.mock.calls.filter(
				( call ) => call[ 0 ].type === type
			);
			return calls[ calls.length - 1 ][ 0 ];
		}

		test( 'passes floatingLabel true to each V6CardFieldContainer', async () => {
			renderComponent();
			await waitForSessionReady();

			expect( lastCallFor( 'number' ).floatingLabel ).toBe( true );
			expect( lastCallFor( 'expiry' ).floatingLabel ).toBe( true );
			expect( lastCallFor( 'cvv' ).floatingLabel ).toBe( true );
		} );

		test( 'subscribes to focus, blur, empty and notempty on the card session', async () => {
			renderComponent();
			await waitForSessionReady();

			await waitFor( () =>
				expect(
					session.on.mock.calls.map( ( call ) => call[ 0 ] )
				).toEqual(
					expect.arrayContaining( [
						'focus',
						'blur',
						'empty',
						'notempty',
					] )
				)
			);
		} );

		describe( 'is-active derivation from the session event payload', () => {
			function fieldState( overrides ) {
				return {
					isEmpty: true,
					isFocused: false,
					isPotentiallyValid: true,
					isValid: false,
					...overrides,
				};
			}

			test.each( [
				[
					'a focused but still empty field',
					fieldState( { isFocused: true, isEmpty: true } ),
					true,
				],
				[
					'a blurred field that already holds a value',
					fieldState( { isFocused: false, isEmpty: false } ),
					true,
				],
				[
					'a blurred, empty field',
					fieldState( { isFocused: false, isEmpty: true } ),
					false,
				],
			] )(
				'marks the wrapper active=%p for %s',
				async ( _label, numberState, expectedActive ) => {
					renderComponent();
					await waitForSessionReady();

					await waitFor( () =>
						expect( session.on ).toHaveBeenCalled()
					);
					const handler = session.on.mock.calls[ 0 ][ 1 ];

					act( () => {
						handler( {
							data: {
								cards: [],
								emittedBy: 'number',
								number: numberState,
								expiry: fieldState(),
								cvv: fieldState(),
							},
							instanceId: 'instance-1',
							sender: 'number',
						} );
					} );

					expect( lastCallFor( 'number' ).isActive ).toBe(
						expectedActive
					);
				}
			);
		} );

		describe( 'cardholder name field', () => {
			test( 'the label is associated with the input via for/id', async () => {
				renderComponent();
				await waitForSessionReady();

				const input = screen.getByLabelText(
					'Cardholder name (optional)'
				);
				expect( input ).toHaveAttribute(
					'id',
					'ppcp-sdk-v6-card-name'
				);
			} );

			test( 'the wrapper carries the Blocks text-input class the stylesheet margin rule targets', async () => {
				renderComponent();
				await waitForSessionReady();

				const input = screen.getByLabelText(
					'Cardholder name (optional)'
				);
				const wrapper = input.closest( 'div' );

				expect( wrapper ).toHaveClass(
					'wc-block-components-text-input'
				);
			} );

			test( 'the wrapper becomes active on focus and stays active once the field holds a value', async () => {
				renderComponent();
				await waitForSessionReady();

				const input = screen.getByLabelText(
					'Cardholder name (optional)'
				);
				const wrapper = input.closest( 'div' );

				expect( wrapper ).not.toHaveClass( 'is-active' );

				fireEvent.focus( input );
				expect( wrapper ).toHaveClass( 'is-active' );

				fireEvent.blur( input );
				expect( wrapper ).not.toHaveClass( 'is-active' );

				fireEvent.change( input, {
					target: { value: 'Jane Doe' },
				} );
				fireEvent.blur( input );
				expect( wrapper ).toHaveClass( 'is-active' );
			} );
		} );
	} );
} );

describe( 'V6CardFieldContainer', () => {
	function containerSession( fieldElement ) {
		return {
			createCardFieldsComponent: jest.fn(
				() => fieldElement || document.createElement( 'div' )
			),
		};
	}

	test( 'in fallback mode, the SDK receives a placeholder, not an aria-label, and no label element is appended', () => {
		const session = containerSession();

		const { container } = render(
			createElement( ActualV6CardFieldContainer, {
				session,
				type: 'number',
				style: {},
				label: 'Card number',
			} )
		);

		expect( session.createCardFieldsComponent ).toHaveBeenCalledWith(
			expect.objectContaining( { placeholder: 'Card number' } )
		);
		expect(
			session.createCardFieldsComponent.mock.calls[ 0 ][ 0 ]
		).not.toHaveProperty( 'ariaLabel' );
		expect( container.firstChild ).not.toHaveClass(
			'wc-block-components-text-input'
		);
		expect( container.querySelector( 'label' ) ).not.toBeInTheDocument();
	} );

	test( 'in floating-label mode, the SDK receives an aria-label, the wrapper gets the Blocks class, and an aria-hidden label is appended after the field', () => {
		const field = document.createElement( 'div' );
		const session = containerSession( field );

		const { container } = render(
			createElement( ActualV6CardFieldContainer, {
				session,
				type: 'number',
				style: {},
				label: 'Card number',
				floatingLabel: true,
			} )
		);

		expect( session.createCardFieldsComponent ).toHaveBeenCalledWith(
			expect.objectContaining( { ariaLabel: 'Card number' } )
		);
		expect(
			session.createCardFieldsComponent.mock.calls[ 0 ][ 0 ]
		).not.toHaveProperty( 'placeholder' );

		const wrapper = container.firstChild;
		expect( wrapper ).toHaveClass( 'wc-block-components-text-input' );

		expect( wrapper.children[ 0 ] ).toBe( field );

		const label = wrapper.querySelector( 'label' );
		expect( label ).toHaveTextContent( 'Card number' );
		expect( label ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( label ).toHaveClass( 'ppcp-sdk-v6-card-field__label' );
		expect( wrapper.lastChild ).toBe( label );
	} );

	test.each( [
		[ true, true ],
		[ false, false ],
	] )(
		'the wrapper is-active class follows the isActive prop (%p) in floating-label mode',
		( isActive, expectActive ) => {
			const session = containerSession();

			const { container } = render(
				createElement( ActualV6CardFieldContainer, {
					session,
					type: 'number',
					style: {},
					floatingLabel: true,
					isActive,
				} )
			);

			if ( expectActive ) {
				expect( container.firstChild ).toHaveClass( 'is-active' );
			} else {
				expect( container.firstChild ).not.toHaveClass(
					'is-active'
				);
			}
		}
	);
} );
