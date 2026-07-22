import { paypalSubscriptionButtonAllowed } from './Subscription';

const baseData = ( overrides = {} ) => ( {
	locations_with_subscription_product: { cart: true },
	user: { is_logged: true },
	context: 'cart',
	is_free_trial_cart: false,
	can_save_vault_token: false,
	subscription_product_allowed: false,
	data_client_id: {
		has_subscriptions: false,
		paypal_subscriptions_enabled: false,
	},
	...overrides,
} );

describe( 'paypalSubscriptionButtonAllowed', () => {
	it( 'allows a cart without subscription products', () => {
		const data = baseData( {
			locations_with_subscription_product: { cart: false },
		} );
		expect( paypalSubscriptionButtonAllowed( data ) ).toBe( true );
	} );

	it( 'hides for a guest with a free trial on the block cart', () => {
		const data = baseData( {
			user: { is_logged: false },
			context: 'cart-block',
			is_free_trial_cart: true,
			subscription_button_allowed: true,
		} );
		expect( paypalSubscriptionButtonAllowed( data ) ).toBe( false );
	} );

	it( 'prefers the server subscription_button_allowed flag when present', () => {
		expect(
			paypalSubscriptionButtonAllowed(
				baseData( { subscription_button_allowed: false } )
			)
		).toBe( false );
		expect(
			paypalSubscriptionButtonAllowed(
				baseData( { subscription_button_allowed: true } )
			)
		).toBe( true );
	} );

	it( 'falls back to hide in vaulting mode when vaulting is disabled', () => {
		const data = baseData( { can_save_vault_token: false } );
		expect( paypalSubscriptionButtonAllowed( data ) ).toBe( false );
	} );

	it( 'falls back to show in vaulting mode when vaulting is enabled', () => {
		const data = baseData( { can_save_vault_token: true } );
		expect( paypalSubscriptionButtonAllowed( data ) ).toBe( true );
	} );

	it( 'falls back to hide in subscriptions mode when the product is not allowed', () => {
		const data = baseData( {
			data_client_id: {
				has_subscriptions: true,
				paypal_subscriptions_enabled: true,
			},
			subscription_product_allowed: false,
		} );
		expect( paypalSubscriptionButtonAllowed( data ) ).toBe( false );
	} );

	it( 'falls back to show in subscriptions mode when the product is allowed', () => {
		const data = baseData( {
			data_client_id: {
				has_subscriptions: true,
				paypal_subscriptions_enabled: true,
			},
			subscription_product_allowed: true,
		} );
		expect( paypalSubscriptionButtonAllowed( data ) ).toBe( true );
	} );
} );
