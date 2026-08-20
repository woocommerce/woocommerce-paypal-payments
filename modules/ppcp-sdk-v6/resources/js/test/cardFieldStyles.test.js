import {
	cardFieldStyles,
	hostedFieldTextStyles,
} from '../cardFields/cardFieldStyles';

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

	test.each( [
		[ 'background', 'rgb(255, 0, 0)' ],
		[ 'border', '1px solid rgb(0, 0, 0)' ],
		[ 'border-radius', '4px' ],
		[ 'box-shadow', 'rgb(0, 0, 0) 0px 0px 4px' ],
		[ 'height', '40px' ],
	] )(
		'keeps the box property %s, unlike hostedFieldTextStyles()',
		( cssProperty, value ) => {
			const field = document.createElement( 'input' );
			field.style.setProperty( cssProperty, value );
			document.body.appendChild( field );

			expect( Object.keys( cardFieldStyles( field ) ) ).toEqual(
				expect.arrayContaining( [
					cssProperty.replace( /-([a-z])/g, ( match, letter ) =>
						letter.toUpperCase()
					),
				] )
			);
		}
	);
} );

describe( 'hostedFieldTextStyles', () => {
	test.each( [
		'background',
		'border',
		'borderRadius',
		'boxShadow',
		'height',
	] )(
		'excludes the box property %s, which would paint a frame the SDK iframe never asked for',
		( camelProperty ) => {
			const field = document.createElement( 'input' );
			field.style.setProperty( 'font-size', '16px' );
			document.body.appendChild( field );

			expect( hostedFieldTextStyles( field ) ).not.toHaveProperty(
				camelProperty
			);
		}
	);

	test( 'keeps text properties such as font size and color', () => {
		const field = document.createElement( 'input' );
		field.style.setProperty( 'font-size', '16px' );
		field.style.setProperty( 'color', 'rgb(1, 2, 3)' );
		document.body.appendChild( field );

		expect( hostedFieldTextStyles( field ) ).toEqual(
			expect.objectContaining( {
				fontSize: '16px',
				color: 'rgb(1, 2, 3)',
			} )
		);
	} );
} );
