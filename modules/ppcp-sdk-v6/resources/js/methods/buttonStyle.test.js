import { buttonHeight } from './buttonStyle';

const config = ( overrides = {} ) => ( {
	...overrides,
} );

describe( 'buttonHeight()', () => {
	test( 'returns the per-context height even when a different flat button_height is also set', () => {
		const result = buttonHeight(
			config( {
				button_height: '48px',
				button_styles: { 'mini-cart': { height: '35px' } },
			} ),
			'mini-cart'
		);

		expect( result ).toBe( '35px' );
	} );

	test( 'falls back to the flat button_height when the context has no entry in button_styles', () => {
		const result = buttonHeight(
			config( {
				button_height: '48px',
				button_styles: { cart: { height: '40px' } },
			} ),
			'checkout'
		);

		expect( result ).toBe( '48px' );
	} );

	test( 'falls back to the flat button_height when button_styles is absent entirely', () => {
		const result = buttonHeight(
			config( { button_height: '48px' } ),
			'mini-cart'
		);

		expect( result ).toBe( '48px' );
	} );

	test( 'returns undefined when neither button_styles nor button_height is set', () => {
		const result = buttonHeight( config(), 'mini-cart' );

		expect( result ).toBeUndefined();
	} );

	test( 'resolves two different contexts in the same config to their own heights', () => {
		const conf = config( {
			button_height: '48px',
			button_styles: {
				'mini-cart': { height: '35px' },
				product: { height: '55px' },
			},
		} );

		expect( buttonHeight( conf, 'mini-cart' ) ).toBe( '35px' );
		expect( buttonHeight( conf, 'product' ) ).toBe( '55px' );
	} );
} );
