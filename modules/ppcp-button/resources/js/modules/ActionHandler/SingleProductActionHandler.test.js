/* global describe, test, expect, beforeEach, afterEach, jest */
import SingleProductActionHandler from './SingleProductActionHandler';

describe( 'SingleProductActionHandler subscriptionsConfiguration()', () => {
	let originalFetch;
	let formElement;

	const baseConfig = ( overrides = {} ) => ( {
		ajax: {
			change_cart: { endpoint: '/change-cart', nonce: 'cc-nonce' },
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

	const buildHandler = ( config = baseConfig() ) => {
		return new SingleProductActionHandler( config, null, formElement, {} );
	};

	const mockFetchResponses = ( changeCartResponse, approveResponse ) => {
		global.fetch = jest
			.fn()
			.mockResolvedValueOnce( {
				json: async () => changeCartResponse,
			} )
			.mockResolvedValueOnce( {
				json: async () => approveResponse,
			} );
	};

	beforeEach( () => {
		originalFetch = global.fetch;

		document.body.innerHTML =
			'<form class="cart"><input name="add-to-cart" value="42" /></form>';
		formElement = document.querySelector( 'form.cart' );
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		document.body.innerHTML = '';
	} );

	test( 'populates the cart before sending the approval, since the endpoint builds the WC order from the emptied-then-repopulated cart', async () => {
		mockFetchResponses( { success: true }, { success: true, data: {} } );
		const handler = buildHandler();
		const { onApprove } = handler.subscriptionsConfiguration( 'plan-1' );

		await onApprove( { orderID: 'o1', subscriptionID: 's1' } );

		const [ firstCallUrl ] = global.fetch.mock.calls[ 0 ];
		const [ secondCallUrl ] = global.fetch.mock.calls[ 1 ];
		expect( firstCallUrl ).toBe( '/change-cart' );
		expect( secondCallUrl ).toBe( '/approve-subscription' );

		// jsdom cannot perform the real navigation triggered by the success
		// path's `location.href` assignment and logs its own console.error.
		expect( console ).toHaveErrored();
	} );

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
				mockFetchResponses(
					{ success: true },
					{ success: true, data: {} }
				);
				const handler = buildHandler( baseConfig( configOverrides ) );
				const { onApprove } =
					handler.subscriptionsConfiguration( 'plan-1' );

				await onApprove( {
					orderID: 'o1',
					subscriptionID: 's1',
					paymentSource,
				} );

				const [ , approveOptions ] = global.fetch.mock.calls[ 1 ];
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

	describe( 'approvalRedirectUrl()', () => {
		test( 'returns the order received URL when the approve endpoint created and paid the order itself', () => {
			const handler = buildHandler();

			const url = handler.approvalRedirectUrl( {
				success: true,
				data: {
					order_received_url: 'https://example.com/order-received/12',
				},
			} );

			expect( url ).toBe( 'https://example.com/order-received/12' );
		} );

		test.each( [
			[ 'no data key', { success: true } ],
			[ 'empty data object', { success: true, data: {} } ],
		] )(
			'falls back to the configured redirect when Pay Now is off (%s)',
			( _label, response ) => {
				const handler = buildHandler();

				expect( handler.approvalRedirectUrl( response ) ).toBe(
					'https://example.com/checkout'
				);
			}
		);
	} );

	test( 'throws with the endpoint message when populating the cart fails, without calling the approval endpoint', async () => {
		global.fetch = jest.fn().mockResolvedValueOnce( {
			json: async () => ( {
				success: false,
				data: { message: 'Product is out of stock' },
			} ),
		} );
		const handler = buildHandler();
		const { onApprove } = handler.subscriptionsConfiguration( 'plan-1' );

		await expect(
			onApprove( { orderID: 'o1', subscriptionID: 's1' } )
		).rejects.toThrow( 'Product is out of stock' );
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );

		// The source deliberately logs the failed cart response before throwing.
		expect( console ).toHaveLogged();
	} );

	test( 'createSubscription passes the plan id and custom id through to actions.subscription.create', () => {
		const handler = buildHandler();
		const { createSubscription } =
			handler.subscriptionsConfiguration( 'plan-1' );
		const create = jest.fn();

		createSubscription( {}, { subscription: { create } } );

		expect( create ).toHaveBeenCalledWith( {
			plan_id: 'plan-1',
			custom_id: 'custom-1',
		} );
	} );
} );
