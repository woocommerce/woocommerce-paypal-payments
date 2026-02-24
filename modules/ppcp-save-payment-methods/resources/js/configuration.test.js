/* global describe, test, expect, jest, beforeEach, afterEach */
import '@testing-library/jest-dom';

jest.mock(
	'@ppcp-button/Helper/CheckoutMethodState',
	() => ( {
		getCurrentPaymentMethod: jest.fn(),
		PaymentMethods: {
			PAYPAL: 'ppcp-gateway',
			CARDS: 'ppcp-credit-card-gateway',
		},
	} )
);

import {
	buttonConfiguration,
	cardFieldsConfiguration,
	addPaymentMethodConfiguration,
} from './configuration';
import { getCurrentPaymentMethod } from '@ppcp-button/Helper/CheckoutMethodState';

describe( 'Configuration', () => {
	let mockErrorHandler;
	let originalFetch;
	let mockConfig;

	beforeEach( () => {
		jest.clearAllMocks();

		mockErrorHandler = {
			message: jest.fn(),
			clear: jest.fn(),
		};

		mockConfig = {
			client_id: 'test-client-id',
			merchant_id: 'test-merchant-id',
			error_message: 'Payment failed. Please try again.',
			verification_method: 'SCA_WHEN_REQUIRED',
			payment_methods_page:
				'https://example.com/my-account/payment-methods',
			view_subscriptions_page:
				'https://example.com/my-account/subscriptions',
			is_subscription_change_payment_page: false,
			ajax: {
				create_setup_token: {
					endpoint: '/api/setup-token',
					nonce: 'setup-nonce',
				},
				create_payment_token: {
					endpoint: '/api/payment-token',
					nonce: 'payment-nonce',
				},
				subscription_change_payment_method: {
					endpoint: '/api/subscription-change',
					nonce: 'subscription-nonce',
				},
				create_payment_token_for_guest: {
					endpoint: '/api/guest-token',
					nonce: 'guest-nonce',
				},
			},
		};

		// Mock fetch
		originalFetch = global.fetch;
		global.fetch = jest.fn();

		// Mock getCurrentPaymentMethod
		getCurrentPaymentMethod.mockReturnValue( 'ppcp-gateway' );

		// Setup DOM
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		document.body.innerHTML = '';
	} );

	describe( 'buttonConfiguration', () => {
		test( 'should make correct API calls for subscription payment change', async () => {
			const config = {
				...mockConfig,
				is_subscription_change_payment_page: true,
				subscription_id_to_change_payment: '789',
			};

			// Mock vault setup token creation
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( { data: { id: 'vault-token-123' } } ),
			} );

			// Mock payment token creation
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					success: true,
					data: 'wc-token-456',
				} ),
			} );

			// Mock subscription change success
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( { success: true } ),
			} );

			const buttonConfig = buttonConfiguration(
				config,
				mockErrorHandler
			);

			const vaultToken = await buttonConfig.createVaultSetupToken();
			expect( vaultToken ).toBe( 'vault-token-123' );
			expect( global.fetch.mock.calls.length ).toBe( 1 );

			await buttonConfig.onApprove( { vaultSetupToken: vaultToken } );

			// Verify all 3 API calls were made
			expect( global.fetch.mock.calls.length ).toBe( 3 );

			// Verify subscription change endpoint was called with correct data
			const subscriptionChangeCall = global.fetch.mock.calls[ 2 ];
			expect( subscriptionChangeCall[ 0 ] ).toBe(
				'/api/subscription-change'
			);
			const subscriptionChangeBody = JSON.parse(
				subscriptionChangeCall[ 1 ].body
			);
			expect( subscriptionChangeBody.subscription_id ).toBe( '789' );
			expect( subscriptionChangeBody.wc_payment_token_id ).toBe(
				'wc-token-456'
			);
			// jsdom doesn't support navigation, so it logs an error
			expect( console ).toHaveErrored();
		} );

		test( 'should not call subscription change API when update fails', async () => {
			const config = {
				...mockConfig,
				is_subscription_change_payment_page: true,
				subscription_id_to_change_payment: '789',
			};

			// Mock payment token creation
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					success: true,
					data: 'wc-token-456',
				} ),
			} );

			// Mock subscription change failure
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( { success: false } ),
			} );

			const buttonConfig = buttonConfiguration(
				config,
				mockErrorHandler
			);
			const result = await buttonConfig.onApprove( {
				vaultSetupToken: 'vault-token',
			} );

			// Verify 2 API calls were made
			expect( global.fetch.mock.calls.length ).toBe( 2 );
			expect( result ).toBeUndefined();
		} );

		test( 'should call payment token API for non-subscription flow', async () => {
			const config = {
				...mockConfig,
				is_subscription_change_payment_page: false,
			};

			// Mock payment token creation success
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					success: true,
					data: 'wc-token-123',
				} ),
			} );

			const buttonConfig = buttonConfiguration(
				config,
				mockErrorHandler
			);
			await buttonConfig.onApprove( { vaultSetupToken: 'vault-token' } );

			// Verify payment token API was called
			expect( global.fetch.mock.calls.length ).toBe( 1 );
			const requestBody = JSON.parse(
				global.fetch.mock.calls[ 0 ][ 1 ].body
			);
			expect( requestBody.vault_setup_token ).toBe( 'vault-token' );
			// jsdom doesn't support navigation, so it logs an error
			expect( console ).toHaveErrored();
		} );

		test( 'should display error when vault token creation fails', async () => {
			// Mock API failure
			global.fetch.mockRejectedValueOnce( new Error( 'Network error' ) );

			const buttonConfig = buttonConfiguration(
				mockConfig,
				mockErrorHandler
			);
			const result = await buttonConfig.createVaultSetupToken();

			expect( result ).toBeUndefined();
			expect( mockErrorHandler.message ).toHaveBeenCalledWith(
				'Payment failed. Please try again.'
			);
			expect( console ).toHaveErrored();
		} );

		test( 'should handle HTTP error responses', async () => {
			// Mock HTTP error
			global.fetch.mockResolvedValueOnce( {
				ok: false,
				status: 500,
			} );

			const buttonConfig = buttonConfiguration(
				mockConfig,
				mockErrorHandler
			);
			const result = await buttonConfig.createVaultSetupToken();

			expect( result ).toBeUndefined();
			expect( mockErrorHandler.message ).toHaveBeenCalled();
			expect( console ).toHaveErrored();
		} );

		test( 'onError should call error handler with message', () => {
			const buttonConfig = buttonConfiguration(
				mockConfig,
				mockErrorHandler
			);
			buttonConfig.onError( new Error( 'PayPal SDK error' ) );

			expect( mockErrorHandler.message ).toHaveBeenCalledWith(
				'Payment failed. Please try again.'
			);
			expect( console ).toHaveErrored();
		} );

		test( 'createVaultSetupToken returns token ID on success', async () => {
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( { data: { id: 'setup-token-123' } } ),
			} );

			const buttonConfig = buttonConfiguration(
				mockConfig,
				mockErrorHandler
			);
			const tokenId = await buttonConfig.createVaultSetupToken();

			expect( tokenId ).toBe( 'setup-token-123' );
		} );
	} );

	describe( 'cardFieldsConfiguration', () => {
		test( 'should submit checkout form when context is checkout', async () => {
			document.body.innerHTML =
				'<button id="place_order">Place Order</button>';
			const placeOrderButton = document.querySelector( '#place_order' );
			const clickSpy = jest.fn();
			placeOrderButton.click = clickSpy;

			const config = {
				...mockConfig,
				context: 'checkout',
			};

			// Mock payment token creation
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					success: true,
					data: 'wc-token-123',
				} ),
			} );

			const cardConfig = cardFieldsConfiguration(
				config,
				mockErrorHandler
			);
			await cardConfig.onApprove( { vaultSetupToken: 'vault-token' } );

			expect( clickSpy ).toHaveBeenCalled();
		} );

		test( 'should include is_free_trial_cart flag in API request', async () => {
			const config = {
				...mockConfig,
				is_free_trial_cart: true,
			};

			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( {
					success: true,
					data: 'token',
				} ),
			} );

			const cardConfig = cardFieldsConfiguration(
				config,
				mockErrorHandler
			);
			await cardConfig.onApprove( { vaultSetupToken: 'vault-token' } );

			const requestBody = JSON.parse(
				global.fetch.mock.calls[ 0 ][ 1 ].body
			);
			expect( requestBody.is_free_trial_cart ).toBe( true );
			// jsdom doesn't support navigation, so it logs an error
			expect( console ).toHaveErrored();
		} );

		test( 'should include verification_method in request', async () => {
			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( { data: { id: 'token-123' } } ),
			} );

			const cardConfig = cardFieldsConfiguration(
				mockConfig,
				mockErrorHandler
			);
			await cardConfig.createVaultSetupToken();

			const requestBody = JSON.parse(
				global.fetch.mock.calls[ 0 ][ 1 ].body
			);
			expect( requestBody.verification_method ).toBe(
				'SCA_WHEN_REQUIRED'
			);
			expect( requestBody.payment_method ).toBe(
				'ppcp-credit-card-gateway'
			);
		} );
	} );

	describe( 'addPaymentMethodConfiguration', () => {
		test( 'should submit checkout form for guest approval', async () => {
			document.body.innerHTML =
				'<button id="place_order">Place Order</button>';
			const placeOrderButton = document.querySelector( '#place_order' );
			const clickSpy = jest.fn();
			placeOrderButton.click = clickSpy;

			global.fetch.mockResolvedValueOnce( {
				ok: true,
				json: async () => ( { success: true } ),
			} );

			const guestConfig = addPaymentMethodConfiguration( mockConfig );
			await guestConfig.onApprove( { vaultSetupToken: 'vault-token' } );

			expect( clickSpy ).toHaveBeenCalled();
		} );
	} );
} );
