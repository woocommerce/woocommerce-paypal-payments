import jQuery from 'jquery';
import CheckoutActionHandler from './CheckoutActionHandler';
import ErrorHandler from '../ErrorHandler';

describe.each( [
	[ 'checkout', '<form class="checkout"></form>', 'form.checkout' ],
	[ 'pay-now', '<form id="order_review"></form>', 'form#order_review' ],
] )( 'Checkout createOrder (%s)', ( context, markup, selector ) => {
	let originalFetch;
	let originalJQuery;
	let originalScrollToNotices;
	let errorHandler;
	let createOrder;
	let spinner;

	const respondWith = ( response ) => {
		global.fetch = jest.fn().mockResolvedValue( {
			json: async () => response,
		} );
	};

	beforeEach( () => {
		originalFetch = global.fetch;
		originalJQuery = global.jQuery;
		originalScrollToNotices = jQuery.scroll_to_notices;
		global.jQuery = jQuery;
		jQuery.scroll_to_notices = jest.fn();
		document.body.innerHTML = markup;

		spinner = { unblock: jest.fn() };
		errorHandler = new ErrorHandler(
			'Payment could not be processed.',
			document.querySelector( selector )
		);
		const handler = new CheckoutActionHandler(
			{
				context,
				bn_codes: {},
				ajax: {
					create_order: {
						endpoint: '/create-order',
						nonce: 'test-nonce',
					},
				},
			},
			errorHandler,
			spinner
		);
		createOrder = handler.configuration().createOrder;
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		global.jQuery = originalJQuery;
		jQuery.scroll_to_notices = originalScrollToNotices;
		document.body.innerHTML = '';
	} );

	test( 'clears corrected validation notices after a successful retry and preserves the resume order', async () => {
		respondWith( {
			success: false,
			messages:
				'<ul class="woocommerce-error"><li>Billing email is invalid.</li><li>Billing ZIP is invalid.</li></ul>',
			data: {},
		} );
		await expect( createOrder() ).rejects.toMatchObject( {
			type: 'create-order-error',
		} );
		const errors = document.querySelector( '.woocommerce-error' );
		expect( errors.textContent ).toBe(
			'Billing email is invalid.Billing ZIP is invalid.'
		);

		respondWith( {
			success: true,
			data: { id: 'paypal-order', custom_id: 'resume-order' },
		} );
		await expect( createOrder() ).resolves.toBe( 'paypal-order' );

		expect( document.querySelector( '.woocommerce-error' ) ).toBeNull();
		expect(
			document.querySelector(
				`${ selector } input[name="ppcp-resume-order"]`
			).value
		).toBe( 'resume-order' );
	} );

	test( 'replaces old validation errors with fresh errors when the retry fails', async () => {
		errorHandler.message( 'Previous validation error.' );
		respondWith( {
			success: false,
			messages:
				'<ul class="woocommerce-error"><li>Billing email is still invalid.</li></ul>',
			data: {},
		} );

		await expect( createOrder() ).rejects.toMatchObject( {
			type: 'create-order-error',
		} );

		const errors = document.querySelector( '.woocommerce-error' );
		expect( errors.textContent ).toBe( 'Billing email is still invalid.' );
		expect( spinner.unblock ).toHaveBeenCalled();
		expect(
			document.querySelector( 'input[name="ppcp-resume-order"]' )
		).toBeNull();
	} );

	test( 'keeps a new plugin error visible and emits checkout_error on failure', async () => {
		const onCheckoutError = jest.fn();
		jQuery( document.body ).one( 'checkout_error', onCheckoutError );
		respondWith( {
			success: false,
			data: { message: 'The payment service is unavailable.' },
		} );

		await expect( createOrder() ).rejects.toMatchObject( {
			type: 'create-order-error',
		} );

		const errors = document.querySelector( '.woocommerce-error' );
		expect( errors.textContent ).toBe(
			'The payment service is unavailable.'
		);
		expect( onCheckoutError ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does not clear existing notices before the request succeeds', async () => {
		errorHandler.message( 'Previous validation error.' );
		global.fetch = jest
			.fn()
			.mockRejectedValue( new Error( 'Network error' ) );

		await expect( createOrder() ).rejects.toThrow( 'Network error' );

		const errors = document.querySelector( '.woocommerce-error' );
		expect( errors.textContent ).toBe( 'Previous validation error.' );
	} );
} );
