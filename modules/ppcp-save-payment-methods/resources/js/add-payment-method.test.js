/* global describe, test, expect, jest, beforeEach, afterEach */
import '@testing-library/jest-dom';
import { fireEvent, waitFor } from '@testing-library/dom';

jest.mock(
	'@ppcp-button/Helper/CheckoutMethodState',
	() => ( {
		getCurrentPaymentMethod: jest.fn(),
		ORDER_BUTTON_SELECTOR: '#place_order',
		PaymentMethods: {
			PAYPAL: 'ppcp-gateway',
			CARDS: 'ppcp-credit-card-gateway',
		},
	} )
);

jest.mock(
	'@ppcp-button/Helper/PayPalScriptLoading',
	() => ( {
		loadPayPalScript: jest.fn(),
	} )
);

jest.mock( '@ppcp-button/ErrorHandler', () => {
	return jest.fn().mockImplementation( () => ( {
		message: jest.fn(),
		clear: jest.fn(),
	} ) );
} );

jest.mock( './configuration', () => ( {
	buttonConfiguration: jest.fn( () => ( {
		createVaultSetupToken: jest.fn(),
		onApprove: jest.fn(),
		onError: jest.fn(),
	} ) ),
	cardFieldsConfiguration: jest.fn( () => ( {
		createVaultSetupToken: jest.fn(),
		onApprove: jest.fn(),
		onError: jest.fn(),
	} ) ),
} ) );

jest.mock( '@ppcp-card-fields/Render', () => ( {
	renderFields: jest.fn(),
} ) );

jest.mock( '@ppcp-button/Helper/Hiding', () => ( {
	setVisible: jest.fn(),
	setVisibleByClass: jest.fn(),
} ) );

import {
	handlePaymentMethodChange,
	setupPaymentMethodListeners,
	initializeScript,
} from './add-payment-method';

import { getCurrentPaymentMethod } from '@ppcp-button/Helper/CheckoutMethodState';
import { loadPayPalScript } from '@ppcp-button/Helper/PayPalScriptLoading';
import ErrorHandler from '@ppcp-button/ErrorHandler';
import { buttonConfiguration, cardFieldsConfiguration } from './configuration';
import { renderFields } from '@ppcp-card-fields/Render';
import {
	setVisible,
	setVisibleByClass,
} from '@ppcp-button/Helper/Hiding';

describe( 'add-payment-method', () => {
	let mockConfig;

	beforeEach( () => {
		jest.clearAllMocks();

		mockConfig = {
			is_subscription_change_payment_page: false,
			client_id: 'test-client-id',
			merchant_id: 'test-merchant-id',
			id_token: 'test-id-token',
			labels: {
				error: {
					generic: 'Generic error message',
				},
			},
			error_message: 'Payment processing failed',
			user: {
				is_logged: true,
			},
		};

		document.body.innerHTML = '';
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'handlePaymentMethodChange', () => {
		test( 'should show PayPal button and hide order button when PayPal is selected', () => {
			getCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			document.body.innerHTML =
				'<div id="ppc-button-ppcp-gateway-save-payment-method"></div>';

			handlePaymentMethodChange( mockConfig );

			expect( setVisibleByClass ).toHaveBeenCalledTimes( 1 );
			expect( setVisible ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'should always show order button on subscription change page when PayPal button missing', () => {
			getCurrentPaymentMethod.mockReturnValue(
				'ppcp-credit-card-gateway'
			);
			const config = {
				...mockConfig,
				is_subscription_change_payment_page: true,
			};

			handlePaymentMethodChange( config );

			expect( setVisibleByClass ).toHaveBeenCalledTimes( 1 );
			expect( setVisible ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'setupPaymentMethodListeners', () => {
		test( 'should call handlePaymentMethodChange immediately on setup', () => {
			getCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );
			setupPaymentMethodListeners( mockConfig );

			expect( setVisibleByClass ).toHaveBeenCalled();
		} );
	} );

	describe( 'initializeScript - subscription change page', () => {
		test( 'should auto-check and disable save to account checkbox on subscription change page', async () => {
			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
			<input type="checkbox" id="wc-ppcp-credit-card-gateway-new-payment-method" />
		`;

			const config = {
				...mockConfig,
				is_subscription_change_payment_page: true,
			};

			// Mock PayPal script loading to throw error (we don't care about PayPal loading for this test)
			loadPayPalScript.mockRejectedValue(
				new Error( 'Intentional error for test' )
			);

			// Suppress expected console.error
			jest.spyOn( console, 'error' ).mockImplementation( () => {} );

			await initializeScript( config );

			const checkbox = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			expect( checkbox.checked ).toBe( true );
			expect( checkbox.disabled ).toBe( true );

			console.error.mockRestore();
		} );
	} );

	describe( 'initializeScript - PayPal button', () => {
		test( 'should load PayPal script with correct configuration', async () => {
			const mockPaypal = {
				Buttons: jest.fn().mockReturnValue( {
					render: jest.fn().mockResolvedValue( undefined ),
				} ),
				CardFields: jest.fn().mockReturnValue( {
					isEligible: jest.fn().mockReturnValue( false ),
				} ),
			};

			loadPayPalScript.mockResolvedValue( mockPaypal );

			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
		`;

			await initializeScript( mockConfig );

			expect( loadPayPalScript ).toHaveBeenCalledWith(
				'ppcp-add-payment-method',
				{
					url_params: {
						'client-id': 'test-client-id',
						'merchant-id': 'test-merchant-id',
						components: 'buttons,card-fields',
					},
					save_payment_methods: {
						id_token: 'test-id-token',
					},
					user: {
						is_logged: true,
					},
				}
			);
		} );
	} );

	describe( 'initializeScript - card fields', () => {
		let mockPayPalButtons;
		let mockCardFields;
		let mockPaypal;

		beforeEach( () => {
			mockPayPalButtons = {
				render: jest.fn().mockResolvedValue( undefined ),
			};

			mockCardFields = {
				isEligible: jest.fn(),
				submit: jest.fn(),
			};

			mockPaypal = {
				Buttons: jest.fn().mockReturnValue( mockPayPalButtons ),
				CardFields: jest.fn().mockReturnValue( mockCardFields ),
			};

			loadPayPalScript.mockResolvedValue( mockPaypal );

			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
		`;
		} );

		test( 'should render card fields when eligible', async () => {
			mockCardFields.isEligible.mockReturnValue( true );

			await initializeScript( mockConfig );

			expect( cardFieldsConfiguration ).toHaveBeenCalledWith(
				mockConfig,
				expect.any( Object )
			);
			expect( mockPaypal.CardFields ).toHaveBeenCalled();
			expect( renderFields ).toHaveBeenCalledWith( mockCardFields );
		} );

		test( 'should submit card fields when place order clicked with new card selected', async () => {
			mockCardFields.isEligible.mockReturnValue( true );
			mockCardFields.submit.mockResolvedValue( undefined );
			getCurrentPaymentMethod.mockReturnValue(
				'ppcp-credit-card-gateway'
			);

			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
			<button id="place_order">Place Order</button>
			<input type="radio" name="wc-ppcp-credit-card-gateway-payment-token" value="new" checked />
		`;

			await initializeScript( mockConfig );

			const placeOrderButton = document.querySelector( '#place_order' );
			fireEvent.click( placeOrderButton );

			await waitFor( () => {
				expect( mockCardFields.submit ).toHaveBeenCalled();
			} );

			expect( placeOrderButton.disabled ).toBe( true );
		} );

		test( 'should NOT submit card fields when saved card token is selected', async () => {
			mockCardFields.isEligible.mockReturnValue( true );
			getCurrentPaymentMethod.mockReturnValue(
				'ppcp-credit-card-gateway'
			);

			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
			<button id="place_order">Place Order</button>
			<input type="radio" name="wc-ppcp-credit-card-gateway-payment-token" value="123" checked />
		`;

			await initializeScript( mockConfig );

			const placeOrderButton = document.querySelector( '#place_order' );
			fireEvent.click( placeOrderButton );

			expect( mockCardFields.submit ).not.toHaveBeenCalled();
			expect( placeOrderButton.disabled ).toBe( false );
		} );

		test( 'should re-enable place order button after card fields submission error', async () => {
			mockCardFields.isEligible.mockReturnValue( true );
			mockCardFields.submit.mockRejectedValue(
				new Error( 'Card submission failed' )
			);
			getCurrentPaymentMethod.mockReturnValue(
				'ppcp-credit-card-gateway'
			);

			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
			<button id="place_order">Place Order</button>
			<input type="radio" name="wc-ppcp-credit-card-gateway-payment-token" value="new" checked />
		`;

			// Suppress expected console.error
			jest.spyOn( console, 'error' ).mockImplementation( () => {} );

			const mockErrorHandler = {
				message: jest.fn(),
				clear: jest.fn(),
			};
			ErrorHandler.mockImplementation( () => mockErrorHandler );

			await initializeScript( mockConfig );

			const placeOrderButton = document.querySelector( '#place_order' );
			fireEvent.click( placeOrderButton );

			await waitFor( () => {
				expect( placeOrderButton.disabled ).toBe( false );
			} );

			expect( mockErrorHandler.message ).toHaveBeenCalledWith(
				'Payment processing failed'
			);

			console.error.mockRestore();
		} );
	} );

	describe( 'error handling', () => {
		test( 'should display error message when PayPal script loading fails', async () => {
			const mockErrorHandler = {
				message: jest.fn(),
				clear: jest.fn(),
			};

			ErrorHandler.mockImplementation( () => mockErrorHandler );

			document.body.innerHTML = `
			<div class="woocommerce-notices-wrapper"></div>
		`;

			loadPayPalScript.mockRejectedValue(
				new Error( 'Script loading failed' )
			);

			// Suppress expected console.error
			jest.spyOn( console, 'error' ).mockImplementation( () => {} );

			await initializeScript( mockConfig );

			expect( mockErrorHandler.message ).toHaveBeenCalledWith(
				'Generic error message'
			);

			console.error.mockRestore();
		} );
	} );
} );
