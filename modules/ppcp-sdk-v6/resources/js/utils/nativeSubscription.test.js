import {
	isNativeSubscription,
	subscriptionButtonAllowed,
	needsSubscriptionComponent,
} from './nativeSubscription';

const configWith = ( nativeSubscription ) => ( {
	native_subscription: nativeSubscription,
} );

describe( 'nativeSubscription', () => {
	describe( 'isNativeSubscription()', () => {
		test.each( [
			[ 'allowed', { checkout: true }, 'checkout', true ],
			[
				'present with a false value',
				{ checkout: false },
				'checkout',
				true,
			],
			[ 'absent from the map', { cart: true }, 'checkout', false ],
			[ 'an empty map', {}, 'checkout', false ],
			[ 'an array', [], 'checkout', false ],
			[ 'undefined', undefined, 'checkout', false ],
			[ 'null', null, 'checkout', false ],
			[ 'a legacy page-wide boolean', true, 'checkout', false ],
			[
				'only an inherited key',
				Object.create( { checkout: true } ),
				'checkout',
				false,
			],
		] )( 'for %s returns %s', ( label, map, context, expected ) => {
			expect( isNativeSubscription( configWith( map ), context ) ).toBe(
				expected
			);
		} );

		test.each( [
			[ 'undefined', undefined ],
			[ 'null', null ],
			[ 'an empty object', {} ],
		] )( 'returns false for %s config', ( label, config ) => {
			expect( isNativeSubscription( config, 'checkout' ) ).toBe( false );
		} );
	} );

	describe( 'subscriptionButtonAllowed()', () => {
		test.each( [
			[ 'true for the context', { checkout: true }, true ],
			[ 'false for the context', { checkout: false }, false ],
			[ 'true only for another context', { cart: true }, false ],
			[ 'a truthy non-boolean value', { checkout: 1 }, false ],
			[ 'an empty map', {}, false ],
			[ 'an array', [], false ],
			[ 'undefined', undefined, false ],
		] )( 'with %s returns %s', ( label, map, expected ) => {
			expect(
				subscriptionButtonAllowed( configWith( map ), 'checkout' )
			).toBe( expected );
		} );

		test.each( [
			[ 'undefined', undefined ],
			[ 'null', null ],
		] )( 'returns false for %s config', ( label, config ) => {
			expect( subscriptionButtonAllowed( config, 'checkout' ) ).toBe(
				false
			);
		} );
	} );

	describe( 'needsSubscriptionComponent()', () => {
		test.each( [
			[ 'one context allows the button', { checkout: true }, true ],
			[
				'one of several contexts allows the button',
				{ checkout: false, 'mini-cart': true },
				true,
			],
			[
				'every context blocks the button',
				{ checkout: false, cart: false },
				false,
			],
			[ 'a truthy non-boolean value', { checkout: 1 }, false ],
			[ 'an empty map', {}, false ],
			[ 'an array', [], false ],
			[ 'undefined', undefined, false ],
			[ 'a legacy page-wide boolean', true, false ],
		] )( 'when %s returns %s', ( label, map, expected ) => {
			expect( needsSubscriptionComponent( configWith( map ) ) ).toBe(
				expected
			);
		} );

		test.each( [
			[ 'undefined', undefined ],
			[ 'null', null ],
		] )( 'returns false for %s config', ( label, config ) => {
			expect( needsSubscriptionComponent( config ) ).toBe( false );
		} );
	} );
} );
