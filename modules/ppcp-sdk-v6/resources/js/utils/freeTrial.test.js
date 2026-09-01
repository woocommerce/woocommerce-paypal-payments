import { isFreeTrialCart } from './freeTrial';

/**
 * A cart that holds a subscription billed by vaulting, i.e. the flag the
 * server sets independently of the total.
 *
 * @param {Object} overrides - Config overrides.
 * @return {Object} The wc_ppcp_sdk_v6-shaped config.
 */
const vaultingCart = ( overrides = {} ) => ( {
	cart_needs_vaulting: true,
	is_free_trial_cart: false,
	...overrides,
} );

describe( 'isFreeTrialCart()', () => {
	test.each( [ true, false ] )(
		'returns false when cart_needs_vaulting is explicitly false, regardless of amount or is_free_trial_cart (is_free_trial_cart: %s)',
		( isFreeTrialCartFlag ) => {
			const config = {
				cart_needs_vaulting: false,
				is_free_trial_cart: isFreeTrialCartFlag,
			};

			expect( isFreeTrialCart( config, '0.00' ) ).toBe( false );
			expect( isFreeTrialCart( config, '100.00' ) ).toBe( false );
			expect( isFreeTrialCart( config ) ).toBe( false );
		}
	);

	test.each( [
		[ { is_free_trial_cart: true }, '0.00' ],
		[ { is_free_trial_cart: true }, '100.00' ],
		[ { is_free_trial_cart: true }, undefined ],
		[ { is_free_trial_cart: false }, '0.00' ],
		[ undefined, '0.00' ],
	] )(
		'returns false when cart_needs_vaulting is absent or the config itself is missing, whatever the amount',
		( config, amount ) => {
			expect( isFreeTrialCart( config, amount ) ).toBe( false );
		}
	);

	test.each( [
		[ null, true ],
		[ undefined, true ],
		[ '', true ],
		[ null, false ],
		[ undefined, false ],
		[ '', false ],
	] )(
		'falls back to is_free_trial_cart when amount is %p and the flag is %s',
		( amount, isFreeTrialCartFlag ) => {
			const config = vaultingCart( {
				is_free_trial_cart: isFreeTrialCartFlag,
			} );

			expect( isFreeTrialCart( config, amount ) ).toBe(
				isFreeTrialCartFlag
			);
		}
	);

	test.each( [
		[ '0.00', true ],
		[ '0', true ],
		[ 0, true ],
		[ '-1.00', true ],
		[ '100.00', false ],
		[ '0.01', false ],
		[ 100, false ],
	] )(
		'a vaulting cart with a live amount of %p resolves to %s regardless of is_free_trial_cart',
		( amount, expected ) => {
			expect(
				isFreeTrialCart( vaultingCart( { is_free_trial_cart: true } ), amount )
			).toBe( expected );
			expect(
				isFreeTrialCart(
					vaultingCart( { is_free_trial_cart: false } ),
					amount
				)
			).toBe( expected );
		}
	);

	test( 'a live amount of $0 is a free trial even when the server said it was not', () => {
		const config = vaultingCart( { is_free_trial_cart: false } );

		expect( isFreeTrialCart( config, '0.00' ) ).toBe( true );
	} );

	test( 'a live amount above $0 is not a free trial even when the server said it was', () => {
		const config = vaultingCart( { is_free_trial_cart: true } );

		expect( isFreeTrialCart( config, '49.00' ) ).toBe( false );
	} );

	test( 'an unparseable amount falls back to is_free_trial_cart', () => {
		const config = vaultingCart( { is_free_trial_cart: true } );

		expect( isFreeTrialCart( config, 'not-a-number' ) ).toBe( true );
	} );
} );
