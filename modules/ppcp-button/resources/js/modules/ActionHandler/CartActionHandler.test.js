/* global describe, test, expect, beforeEach, afterEach, jest */
import CartActionHandler from './CartActionHandler';

describe( 'CartActionHandler subscriptionsConfiguration()', () => {
	let originalFetch;

	const baseConfig = ( overrides = {} ) => ( {
		ajax: {
			approve_subscription: {
				endpoint: '/approve-subscription',
				nonce: 'as-nonce',
			},
		},
		vaultingEnabled: false,
		redirect: 'https://example.com/checkout',
		subscription_custom_id: 'custom-1',
		...overrides,
	} );

	const buildErrorHandler = () => ( {
		clear: jest.fn(),
		message: jest.fn(),
		genericError: jest.fn(),
	} );

	const buildHandler = ( config = baseConfig(), errorHandler = buildErrorHandler() ) => {
		return new CartActionHandler( config, errorHandler );
	};

	const mockFetchResponse = ( approveResponse ) => {
		global.fetch = jest.fn().mockResolvedValueOnce( {
			json: async () => approveResponse,
		} );
	};

	beforeEach( () => {
		originalFetch = global.fetch;
	} );

	afterEach( () => {
		global.fetch = originalFetch;
	} );

	test( 'rejects and reports the endpoint message when the subscription approval fails', async () => {
		mockFetchResponse( {
			success: false,
			data: { message: 'Something went wrong' },
		} );
		const errorHandler = buildErrorHandler();
		const handler = buildHandler( baseConfig(), errorHandler );
		const { onApprove } = handler.subscriptionsConfiguration( 'P-PLAN-ID' );

		const result = onApprove( { orderID: 'o1', subscriptionID: 's1' } );
		expect( typeof result.then ).toBe( 'function' );

		await expect( result ).rejects.toThrow( 'Something went wrong' );
		expect( errorHandler.clear ).toHaveBeenCalled();
		expect( errorHandler.message ).toHaveBeenCalledWith(
			'Something went wrong'
		);
		expect( errorHandler.genericError ).not.toHaveBeenCalled();
	} );

	test.each( [
		[ 'empty data object', { success: false, data: {} } ],
		[ 'no data key', { success: false } ],
	] )(
		'rejects with a generic error when a failed response carries no message (%s)',
		async ( _label, response ) => {
			mockFetchResponse( response );
			const errorHandler = buildErrorHandler();
			const handler = buildHandler( baseConfig(), errorHandler );
			const { onApprove } =
				handler.subscriptionsConfiguration( 'P-PLAN-ID' );

			await expect(
				onApprove( { orderID: 'o1', subscriptionID: 's1' } )
			).rejects.toThrow();

			expect( errorHandler.clear ).toHaveBeenCalled();
			expect( errorHandler.genericError ).toHaveBeenCalled();
			expect( errorHandler.message ).not.toHaveBeenCalled();
		}
	);

	test( 'navigates to the order received URL when the approve endpoint created and paid the order itself', async () => {
		mockFetchResponse( {
			success: true,
			data: { order_received_url: 'https://example.com/order-received/12' },
		} );
		const errorHandler = buildErrorHandler();
		const handler = buildHandler( baseConfig(), errorHandler );
		const { onApprove } = handler.subscriptionsConfiguration( 'P-PLAN-ID' );

		await onApprove( { orderID: 'o1', subscriptionID: 's1' } );

		expect( errorHandler.clear ).not.toHaveBeenCalled();
		expect( errorHandler.message ).not.toHaveBeenCalled();
		expect( errorHandler.genericError ).not.toHaveBeenCalled();

		// jsdom cannot perform the real navigation triggered by the success
		// path's `location.href` assignment and logs its own console.error.
		expect( console ).toHaveErrored();
	} );

	test.each( [
		[ 'no data key', { success: true } ],
		[ 'empty data object', { success: true, data: {} } ],
	] )(
		'falls back to the configured redirect when the endpoint returns no order received URL (%s)',
		async ( _label, response ) => {
			mockFetchResponse( response );
			const handler = buildHandler();
			const { onApprove } =
				handler.subscriptionsConfiguration( 'P-PLAN-ID' );

			await onApprove( { orderID: 'o1', subscriptionID: 's1' } );

			// jsdom cannot perform the real navigation triggered by the success
			// path's `location.href` assignment.
			expect( console ).toHaveErrored();
		}
	);

	describe.each( [
		[
			'vaulting disabled, non-venmo payment source',
			{ vaultingEnabled: false },
			'card',
			true,
		],
		[
			'vaulting enabled, non-venmo payment source',
			{ vaultingEnabled: true },
			'card',
			true,
		],
		[
			'vaulting disabled, venmo payment source',
			{ vaultingEnabled: false },
			'venmo',
			true,
		],
		[
			'vaulting enabled, venmo payment source',
			{ vaultingEnabled: true },
			'venmo',
			false,
		],
	] )(
		'%s',
		( _label, configOverrides, paymentSource, expectedShouldCreate ) => {
			test( `sends should_create_wc_order = ${ expectedShouldCreate }`, async () => {
				mockFetchResponse( { success: true, data: {} } );
				const handler = buildHandler( baseConfig( configOverrides ) );
				const { onApprove } =
					handler.subscriptionsConfiguration( 'P-PLAN-ID' );

				await onApprove( {
					orderID: 'o1',
					subscriptionID: 's1',
					paymentSource,
				} );

				const [ , approveOptions ] = global.fetch.mock.calls[ 0 ];
				const body = JSON.parse( approveOptions.body );
				expect( body.should_create_wc_order ).toBe(
					expectedShouldCreate
				);

				// jsdom cannot perform the real navigation triggered by the
				// success path's `location.href` assignment.
				expect( console ).toHaveErrored();
			} );
		}
	);

	test( 'sends the endpoint, nonce, order id and subscription id in the approval request', async () => {
		mockFetchResponse( { success: true, data: {} } );
		const handler = buildHandler(
			baseConfig( {
				ajax: {
					approve_subscription: {
						endpoint: '/approve-subscription',
						nonce: 'as-nonce',
					},
				},
			} )
		);
		const { onApprove } = handler.subscriptionsConfiguration( 'P-PLAN-ID' );

		await onApprove( { orderID: 'order-42', subscriptionID: 'sub-7' } );

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/approve-subscription' );

		const body = JSON.parse( options.body );
		expect( body.nonce ).toBe( 'as-nonce' );
		expect( body.order_id ).toBe( 'order-42' );
		expect( body.subscription_id ).toBe( 'sub-7' );

		// jsdom cannot perform the real navigation triggered by the success
		// path's `location.href` assignment.
		expect( console ).toHaveErrored();
	} );

	test( 'createSubscription passes the plan id and custom id through to actions.subscription.create', () => {
		const handler = buildHandler();
		const { createSubscription } =
			handler.subscriptionsConfiguration( 'P-PLAN-ID' );
		const create = jest.fn();

		createSubscription( {}, { subscription: { create } } );

		expect( create ).toHaveBeenCalledWith( {
			plan_id: 'P-PLAN-ID',
			custom_id: 'custom-1',
		} );
	} );
} );
