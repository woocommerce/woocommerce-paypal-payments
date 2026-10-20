import { createCardButtonElements } from './cardButtonElement';

describe( 'createCardButtonElements()', () => {
	test( 'builds a detached container/button pair', () => {
		const { container, button } = createCardButtonElements( {}, null );

		expect( container.tagName ).toBe( 'PAYPAL-BASIC-CARD-CONTAINER' );
		expect( button.tagName ).toBe( 'PAYPAL-BASIC-CARD-BUTTON' );
		expect( container.contains( button ) ).toBe( true );
		expect( container.isConnected ).toBe( false );
	} );

	test( 'does not set buyerCountry when none is given', () => {
		const { button } = createCardButtonElements( {}, undefined );

		expect( button.buyerCountry ).toBeUndefined();
	} );

	test( 'sets buyerCountry as a JS property on the button', () => {
		const { button } = createCardButtonElements( {}, 'DE' );

		expect( button.buyerCountry ).toBe( 'DE' );
	} );

	test( 'applies the border radius as the paypal button border-radius custom property', () => {
		const { button } = createCardButtonElements(
			{ borderRadius: '4px' },
			null
		);

		expect(
			button.style.getPropertyValue( '--paypal-button-border-radius' )
		).toBe( '4px' );
	} );

	test( 'applies height to the button only', () => {
		const { container, button } = createCardButtonElements(
			{ height: '55px' },
			null
		);

		expect( button.style.height ).toBe( '55px' );
		expect( container.style.height ).toBe( '' );
	} );

	test( 'applies width to both the container and the button', () => {
		const { container, button } = createCardButtonElements(
			{ width: '225px' },
			null
		);

		expect( container.style.width ).toBe( '225px' );
		expect( button.style.width ).toBe( '225px' );
	} );

	test( 'applies no styles when none are configured', () => {
		const { container, button } = createCardButtonElements(
			undefined,
			null
		);

		expect( button.style.length ).toBe( 0 );
		expect( container.style.length ).toBe( 0 );
	} );
} );
