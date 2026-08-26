import { buildApplePayRequest } from './applePayRequest';

const applePayConfig = ( overrides = {} ) => ( {
	countryCode: 'US',
	merchantCapabilities: [ 'supports3DS' ],
	supportedNetworks: [ 'visa', 'masterCard' ],
	...overrides,
} );

const transaction = ( overrides = {} ) => ( {
	currencyCode: 'USD',
	total: '12.34',
	displayName: 'WooShop',
	...overrides,
} );

describe( 'buildApplePayRequest()', () => {
	describe( 'resolving countryCode', () => {
		test( "prefers PayPal's own config over the plugin setting", () => {
			const request = buildApplePayRequest(
				applePayConfig( { countryCode: 'US' } ),
				transaction( { countryCode: 'DE' } )
			);

			expect( request.countryCode ).toBe( 'US' );
		} );

		test( 'falls back to the passed-in countryCode when the config has none', () => {
			// Regression: the config does not guarantee a country and the plugin
			// setting can be empty. An undefined countryCode makes the
			// ApplePaySession constructor throw before any sheet opens.
			const request = buildApplePayRequest(
				applePayConfig( { countryCode: undefined } ),
				transaction( { countryCode: 'DE' } )
			);

			expect( request.countryCode ).toBe( 'DE' );
		} );
	} );

	test( 'passes merchantCapabilities and supportedNetworks through from the raw config, untouched', () => {
		// Regression: session.formatConfigForPaymentRequest() used to run over
		// this config first and lowercase merchantCapabilities, which makes
		// Apple's case-sensitive enum throw. The raw config must survive as-is.
		const request = buildApplePayRequest(
			applePayConfig( {
				merchantCapabilities: [ 'supports3DS', 'supportsCredit' ],
				supportedNetworks: [ 'amex' ],
			} ),
			transaction()
		);

		expect( request.merchantCapabilities ).toEqual( [
			'supports3DS',
			'supportsCredit',
		] );
		expect( request.supportedNetworks ).toEqual( [ 'amex' ] );
	} );

	test.each( [ '9.99', '12.345' ] )(
		'builds a final total labelled with the shop display name, passing %s through without rounding',
		( total ) => {
			const request = buildApplePayRequest(
				applePayConfig(),
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
		const request = buildApplePayRequest( applePayConfig(), transaction() );

		expect( request.requiredBillingContactFields ).toEqual( [
			'postalAddress',
		] );
	} );

	test.each( [
		[ false, [ 'email', 'phone' ] ],
		[ true, [ 'postalAddress', 'email', 'phone' ] ],
	] )(
		'when requiresShipping is %s, requires shipping fields %j',
		( requiresShipping, expectedFields ) => {
			const request = buildApplePayRequest(
				applePayConfig(),
				transaction( { requiresShipping } )
			);

			expect( request.requiredShippingContactFields ).toEqual(
				expectedFields
			);
		}
	);

	test( 'omits shippingType and shippingMethods when the sheet does not collect shipping', () => {
		const request = buildApplePayRequest( applePayConfig(), transaction() );

		expect( request ).not.toHaveProperty( 'shippingType' );
		expect( request ).not.toHaveProperty( 'shippingMethods' );
	} );

	test( 'sets shippingType and an empty shippingMethods list when the sheet collects shipping, since the rates depend on an address not given yet', () => {
		const request = buildApplePayRequest(
			applePayConfig(),
			transaction( { requiresShipping: true } )
		);

		expect( request.shippingType ).toBe( 'shipping' );
		expect( request.shippingMethods ).toEqual( [] );
	} );
} );

