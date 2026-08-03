// errorHandler pulls in the @ppcp-button webpack alias, which jest cannot
// resolve; the button click path is not exercised here.
jest.mock( '../utils/errorHandler', () => ( { handleError: jest.fn() } ) );

import { createMethodButton } from '../components/buttonRenderer';

describe( 'createMethodButton', () => {
	const noop = () => {};

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
