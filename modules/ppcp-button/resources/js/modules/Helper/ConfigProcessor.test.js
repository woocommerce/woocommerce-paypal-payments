/* global describe, test, expect */
import { getUserIdToken } from './ConfigProcessor';

const baseConfig = ( overrides = {} ) => ( {
	user: { is_logged: true },
	save_payment_methods: { id_token: 'TOKEN123' },
	vault_component: { is_eligible: false },
	context: 'product',
	...overrides,
} );

describe( 'getUserIdToken', () => {
	test( 'returns null when the user is not logged in', () => {
		expect( getUserIdToken( baseConfig( { user: { is_logged: false } } ) ) ).toBeNull();
	} );

	test( 'returns null when there is no id_token', () => {
		expect(
			getUserIdToken( baseConfig( { save_payment_methods: { id_token: '' } } ) )
		).toBeNull();
		expect( getUserIdToken( baseConfig( { save_payment_methods: undefined } ) ) ).toBeNull();
	} );

	test( 'returns the token when the vault component is not eligible (any context)', () => {
		[ 'product', 'cart', 'cart-block', 'mini-cart', 'checkout', 'checkout-block', 'pay-now' ].forEach(
			( context ) => {
				expect( getUserIdToken( baseConfig( { context } ) ) ).toBe( 'TOKEN123' );
			}
		);
	} );

	test( 'omits the token on checkout-family contexts when the vault component is eligible', () => {
		[ 'checkout', 'checkout-block', 'pay-now' ].forEach( ( context ) => {
			expect(
				getUserIdToken(
					baseConfig( { context, vault_component: { is_eligible: true } } )
				)
			).toBeNull();
		} );
	} );

	test( 'keeps the token on express contexts even when the vault component is eligible', () => {
		[ 'product', 'cart', 'cart-block', 'mini-cart' ].forEach( ( context ) => {
			expect(
				getUserIdToken(
					baseConfig( { context, vault_component: { is_eligible: true } } )
				)
			).toBe( 'TOKEN123' );
		} );
	} );
} );
