// errorHandler pulls in the @ppcp-button webpack alias, which jest cannot
// resolve; the button click path is not exercised here.
jest.mock( '../utils/errorHandler', () => ( { handleError: jest.fn() } ) );

import {
	createMethodButton,
	createPreviewButton,
	renderButtons,
} from '../components/buttonRenderer';

const noop = () => {};

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'a click on a rendered button', () => {
	test.each( [ 'paypal', 'venmo', 'paylater' ] )(
		'a %s button starts the session',
		async ( method ) => {
			const session = { start: jest.fn().mockResolvedValue( undefined ) };
			const button = createMethodButton( {
				method,
				styles: {},
				session,
				createOrderFn: noop,
				payLaterDetails: { productCode: 'PAYLATER' },
			} );

			button.click();
			await flush();

			expect( session.start ).toHaveBeenCalledTimes( 1 );
		}
	);

	test( 'does not start the session when the onClick hook throws', async () => {
		const session = { start: jest.fn() };
		const button = createMethodButton( {
			method: 'paypal',
			styles: {},
			session,
			createOrderFn: noop,
			onClick: () => {
				throw new Error( 'hook failed' );
			},
		} );

		button.click();
		await flush();

		expect( session.start ).not.toHaveBeenCalled();
	} );
} );

describe( 'createMethodButton', () => {
	test( 'sets pay later product details as properties before returning', () => {
		const button = createMethodButton( {
			method: 'paylater',
			styles: {},
			session: {},
			createOrderFn: noop,
			payLaterDetails: { productCode: 'PAYLATER', countryCode: 'US' },
		} );

		expect( button.tagName.toLowerCase() ).toBe(
			'paypal-pay-later-button'
		);
		expect( button.productCode ).toBe( 'PAYLATER' );
		expect( button.countryCode ).toBe( 'US' );
		// Configured but not yet inserted into the DOM.
		expect( button.isConnected ).toBe( false );
	} );

	test( 'applies colour, height and border radius to the element', () => {
		const button = createMethodButton( {
			method: 'paypal',
			styles: {
				colorClass: 'paypal-blue',
				height: '48px',
				borderRadius: '8px',
			},
			session: {},
			createOrderFn: noop,
		} );

		expect( button.className ).toBe( 'paypal-blue' );
		expect( button.style.height ).toBe( '48px' );
		expect(
			button.style.getPropertyValue( '--paypal-button-border-radius' )
		).toBe( '8px' );
	} );

	test( 'sets the border radius on the property each element actually reads', () => {
		// The Venmo element ignores --paypal-button-border-radius, so a single
		// shared property leaves Venmo square while the others round.
		const venmo = createMethodButton( {
			method: 'venmo',
			styles: { borderRadius: '30px' },
			session: {},
			createOrderFn: noop,
		} );

		expect(
			venmo.style.getPropertyValue( '--venmo-button-border-radius' )
		).toBe( '30px' );
		expect(
			venmo.style.getPropertyValue( '--paypal-button-border-radius' )
		).toBe( '' );

		const payLater = createMethodButton( {
			method: 'paylater',
			styles: { borderRadius: '30px' },
			session: {},
			createOrderFn: noop,
			payLaterDetails: { productCode: 'PAYLATER' },
		} );

		expect(
			payLater.style.getPropertyValue( '--paypal-button-border-radius' )
		).toBe( '30px' );
	} );

	test.each( [
		[ 'paypal', 'buynow' ],
		[ 'paypal', 'checkout' ],
		[ 'venmo', 'buynow' ],
		[ 'venmo', 'checkout' ],
	] )( 'sets the %s button type to %s from styles.type', ( method, type ) => {
		const button = createMethodButton( {
			method,
			styles: { type },
			session: {},
			createOrderFn: noop,
		} );

		expect( button.getAttribute( 'type' ) ).toBe( type );
	} );

	test.each( [
		[ 'paypal', 'an empty string', { type: '' } ],
		[ 'paypal', 'absent', {} ],
		[ 'venmo', 'an empty string', { type: '' } ],
		[ 'venmo', 'absent', {} ],
	] )(
		'leaves the %s button without a type attribute when styles.type is %s',
		( method, _, styles ) => {
			const button = createMethodButton( {
				method,
				styles,
				session: {},
				createOrderFn: noop,
			} );

			expect( button.hasAttribute( 'type' ) ).toBe( false );
		}
	);

	test( 'does not set a type on the pay later button even when styles.type is set', () => {
		const button = createMethodButton( {
			method: 'paylater',
			styles: { type: 'checkout' },
			session: {},
			createOrderFn: noop,
			payLaterDetails: { productCode: 'PAYLATER' },
		} );

		expect( button.hasAttribute( 'type' ) ).toBe( false );
	} );

	test( 'returns null for pay later without product details', () => {
		const button = createMethodButton( {
			method: 'paylater',
			styles: {},
			session: {},
			createOrderFn: noop,
			payLaterDetails: null,
		} );

		expect( button ).toBeNull();
	} );

	test( 'returns null for an unknown method', () => {
		const button = createMethodButton( {
			method: 'nope',
			styles: {},
			session: {},
			createOrderFn: noop,
		} );

		expect( button ).toBeNull();
	} );
} );

describe( 'createPreviewButton', () => {
	test( 'returns null for an unknown method', () => {
		const button = createPreviewButton( document, {
			method: 'nope',
			styles: {},
		} );

		expect( button ).toBeNull();
	} );

	test.each( [
		[ 'paypal', 'paypal-button', 'buynow' ],
		[ 'venmo', 'venmo-button', 'buynow' ],
		[ 'paylater', 'paypal-pay-later-button', null ],
	] )(
		'creates a %s element (%s) with type attribute %s when styles.type is buynow',
		( method, tagName, expectedType ) => {
			const button = createPreviewButton( document, {
				method,
				styles: { type: 'buynow' },
			} );

			expect( button.tagName.toLowerCase() ).toBe( tagName );
			expect( button.getAttribute( 'type' ) ).toBe( expectedType );
		}
	);

	test.each( [
		[ 'an empty string', { type: '' } ],
		[ 'absent', {} ],
	] )(
		'leaves the type attribute off when styles.type is %s',
		( _, styles ) => {
			const button = createPreviewButton( document, {
				method: 'paypal',
				styles,
			} );

			expect( button.hasAttribute( 'type' ) ).toBe( false );
		}
	);

	test( 'applies colour, height and border radius to the element', () => {
		const button = createPreviewButton( document, {
			method: 'paypal',
			styles: {
				colorClass: 'paypal-blue',
				height: '35px',
				borderRadius: '8px',
			},
		} );

		expect( button.className ).toBe( 'paypal-blue' );
		expect( button.style.height ).toBe( '35px' );
		expect(
			button.style.getPropertyValue( '--paypal-button-border-radius' )
		).toBe( '8px' );
	} );

	test( 'sets the border radius on the property the Venmo element actually reads', () => {
		const venmo = createPreviewButton( document, {
			method: 'venmo',
			styles: { borderRadius: '30px' },
		} );

		expect(
			venmo.style.getPropertyValue( '--venmo-button-border-radius' )
		).toBe( '30px' );
		expect(
			venmo.style.getPropertyValue( '--paypal-button-border-radius' )
		).toBe( '' );
	} );

	test( 'creates the element in the given document, not the top-level one', () => {
		const fakeElement = {
			setAttribute: jest.fn(),
			style: { setProperty: jest.fn() },
		};
		const fakeDoc = {
			createElement: jest.fn( () => fakeElement ),
		};

		const button = createPreviewButton( fakeDoc, {
			method: 'paypal',
			styles: { colorClass: 'paypal-blue', type: 'pay' },
		} );

		expect( fakeDoc.createElement ).toHaveBeenCalledWith( 'paypal-button' );
		expect( button ).toBe( fakeElement );
		expect( button.setAttribute ).toHaveBeenCalledWith( 'type', 'pay' );
		expect( button.className ).toBe( 'paypal-blue' );
	} );
} );

describe( 'renderButtons', () => {
	test( 'the card button is never bundled with the PayPal express buttons, even with a card session present', () => {
		const wrapper = document.createElement( 'div' );
		document.body.appendChild( wrapper );

		const rendered = renderButtons( {
			wrapper,
			sessions: { paypal: {}, card: {} },
			styles: {},
			createOrderForFunding: () => noop,
		} );

		expect( wrapper.querySelector( 'paypal-basic-card-button' ) ).toBeNull();
		expect( rendered.some( ( el ) => el.tagName === 'PAYPAL-BUTTON' ) ).toBe(
			true
		);

		document.body.removeChild( wrapper );
	} );

	test( 'passes styles.type to the paypal button but not to the pay later button', () => {
		const wrapper = document.createElement( 'div' );
		document.body.appendChild( wrapper );

		renderButtons( {
			wrapper,
			sessions: { paypal: {}, paylater: {} },
			styles: { type: 'checkout' },
			createOrderForFunding: () => noop,
			payLaterDetails: { productCode: 'PAYLATER' },
			payLaterEnabled: true,
		} );

		expect(
			wrapper.querySelector( 'paypal-button' ).getAttribute( 'type' )
		).toBe( 'checkout' );
		expect(
			wrapper
				.querySelector( 'paypal-pay-later-button' )
				.hasAttribute( 'type' )
		).toBe( false );

		document.body.removeChild( wrapper );
	} );

	test( 'renders nothing at all into the express wrapper when card is the only session', () => {
		const wrapper = document.createElement( 'div' );
		document.body.appendChild( wrapper );

		const rendered = renderButtons( {
			wrapper,
			sessions: { card: {} },
			styles: {},
			createOrderForFunding: () => noop,
		} );

		expect( rendered ).toHaveLength( 0 );
		expect( wrapper.childElementCount ).toBe( 0 );

		document.body.removeChild( wrapper );
	} );

	test( 'skips the pay later button when payLaterEnabled is not set, even with a valid session and product details', () => {
		const wrapper = document.createElement( 'div' );
		document.body.appendChild( wrapper );

		const rendered = renderButtons( {
			wrapper,
			sessions: { paypal: {}, paylater: {} },
			styles: {},
			createOrderForFunding: () => noop,
			payLaterDetails: { productCode: 'PAYLATER' },
		} );

		expect(
			wrapper.querySelector( 'paypal-pay-later-button' )
		).toBeNull();
		expect(
			rendered.some( ( el ) => el.tagName === 'PAYPAL-PAY-LATER-BUTTON' )
		).toBe( false );

		document.body.removeChild( wrapper );
	} );

	test( 'renders the pay later button when payLaterEnabled is true and product details are present', () => {
		const wrapper = document.createElement( 'div' );
		document.body.appendChild( wrapper );

		const rendered = renderButtons( {
			wrapper,
			sessions: { paypal: {}, paylater: {} },
			styles: {},
			createOrderForFunding: () => noop,
			payLaterDetails: { productCode: 'PAYLATER' },
			payLaterEnabled: true,
		} );

		expect(
			wrapper.querySelector( 'paypal-pay-later-button' )
		).not.toBeNull();
		expect(
			rendered.some( ( el ) => el.tagName === 'PAYPAL-PAY-LATER-BUTTON' )
		).toBe( true );

		document.body.removeChild( wrapper );
	} );
} );
