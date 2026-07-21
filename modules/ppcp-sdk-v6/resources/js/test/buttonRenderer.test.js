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
