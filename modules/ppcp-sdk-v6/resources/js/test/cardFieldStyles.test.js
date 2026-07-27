import { cardFieldStyles } from '../cardFields/cardFieldStyles';

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'cardFieldStyles', () => {
	test( 'camelCases a supported kebab-case CSS property', () => {
		const field = document.createElement( 'input' );
		field.style.setProperty( 'font-size', '16px' );
		document.body.appendChild( field );

		expect( cardFieldStyles( field ) ).toEqual(
			expect.objectContaining( { fontSize: '16px' } )
		);
	} );

	test( 'drops properties the v6 SDK does not support (e.g. vendor-prefixed ones)', () => {
		const field = document.createElement( 'input' );
		field.style.setProperty( '-webkit-tap-highlight-color', 'red' );
		field.style.setProperty( 'margin', '4px' );
		document.body.appendChild( field );

		const styles = cardFieldStyles( field );

		expect( styles ).not.toHaveProperty( 'webkitTapHighlightColor' );
		expect( styles ).not.toHaveProperty( 'margin' );
	} );
} );
