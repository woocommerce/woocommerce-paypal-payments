import { buildApplePayRequest } from './applePayRequest';

const sessionConfig = ( overrides = {} ) => ( {
	merchantCountry: 'US',
	merchantCapabilities: [ 'supports3DS' ],
	supportedNetworks: [ 'visa', 'masterCard' ],
	...overrides,
} );

const transaction = ( overrides = {} ) => ( {
	currencyCode: 'USD',
	total: '12.34',
	displayName: 'WooShop',
	context: 'checkout',
	...overrides,
} );

describe( 'buildApplePayRequest()', () => {
	test( 'maps merchantCountry from the session config to countryCode', () => {
		const request = buildApplePayRequest(
			sessionConfig( { merchantCountry: 'DE' } ),
			transaction()
		);

		expect( request.countryCode ).toBe( 'DE' );
	} );

	test( 'passes merchantCapabilities and supportedNetworks through from the session config', () => {
		const request = buildApplePayRequest(
			sessionConfig( {
				merchantCapabilities: [ 'supportsCredit' ],
				supportedNetworks: [ 'amex' ],
			} ),
			transaction()
		);

		expect( request.merchantCapabilities ).toEqual( [ 'supportsCredit' ] );
		expect( request.supportedNetworks ).toEqual( [ 'amex' ] );
	} );

	test.each( [ '9.99', '12.345' ] )(
		'builds a final total labelled with the shop display name, passing %s through without rounding',
		( total ) => {
			const request = buildApplePayRequest(
				sessionConfig(),
				transaction( { total, displayName: 'Acme Store' } )
			);

			expect( request.total ).toEqual( {
				label: 'Acme Store',
				type: 'final',
				amount: total,
			} );
		}
	);

	test( 'always requires only the postal address for billing', () => {
		const request = buildApplePayRequest( sessionConfig(), transaction() );

		expect( request.requiredBillingContactFields ).toEqual( [
			'postalAddress',
		] );
	} );

	test.each( [
		[ 'checkout', [ 'email', 'phone' ] ],
		[ 'product', [ 'postalAddress', 'email', 'phone' ] ],
		[ 'cart', [ 'postalAddress', 'email', 'phone' ] ],
		[ 'mini-cart', [ 'postalAddress', 'email', 'phone' ] ],
		[ '', [ 'postalAddress', 'email', 'phone' ] ],
	] )(
		// 'checkout' already has the address from the WC form, so it skips
		// postalAddress; every other (express) context needs it as the only source.
		'for context %s requires shipping fields %j',
		( context, expectedFields ) => {
			const request = buildApplePayRequest(
				sessionConfig(),
				transaction( { context } )
			);

			expect( request.requiredShippingContactFields ).toEqual(
				expectedFields
			);
		}
	);

	test( 'omits shippingType and shippingMethods when the sheet does not collect shipping', () => {
		const request = buildApplePayRequest( sessionConfig(), transaction() );

		expect( request ).not.toHaveProperty( 'shippingType' );
		expect( request ).not.toHaveProperty( 'shippingMethods' );
	} );

	test( 'sets shippingType and an empty shippingMethods list when the sheet collects shipping, since the rates depend on an address not given yet', () => {
		const request = buildApplePayRequest(
			sessionConfig(),
			transaction( { requiresShipping: true } )
		);

		expect( request.shippingType ).toBe( 'shipping' );
		expect( request.shippingMethods ).toEqual( [] );
	} );
} );
