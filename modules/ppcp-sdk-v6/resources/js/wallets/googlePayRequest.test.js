import {
	buildPaymentDataRequest,
	buildReadyToPayRequest,
} from './googlePayRequest';

const sessionConfig = ( overrides = {} ) => ( {
	allowedPaymentMethods: [ { type: 'CARD' } ],
	apiVersion: 2,
	merchantInfo: { merchantName: 'WooShop' },
	tokenizationSpecification: { type: 'PAYMENT_GATEWAY' },
	...overrides,
} );

describe( 'buildReadyToPayRequest()', () => {
	test( 'hardcodes apiVersion 2 and apiVersionMinor 0 regardless of the session config', () => {
		const request = buildReadyToPayRequest(
			sessionConfig( { apiVersion: 99 } )
		);

		expect( request.apiVersion ).toBe( 2 );
		expect( request.apiVersionMinor ).toBe( 0 );
	} );

	test( 'carries the allowedPaymentMethods over from the session config', () => {
		const allowedPaymentMethods = [ { type: 'CARD' }, { type: 'PAYPAL' } ];

		const request = buildReadyToPayRequest(
			sessionConfig( { allowedPaymentMethods } )
		);

		expect( request.allowedPaymentMethods ).toBe( allowedPaymentMethods );
	} );

	test( 'omits merchantInfo and transactionInfo, unlike buildPaymentDataRequest', () => {
		const request = buildReadyToPayRequest( sessionConfig() );

		expect( request ).not.toHaveProperty( 'merchantInfo' );
		expect( request ).not.toHaveProperty( 'transactionInfo' );
	} );
} );

describe( 'buildPaymentDataRequest()', () => {
	const transaction = ( overrides = {} ) => ( {
		countryCode: 'US',
		currencyCode: 'USD',
		total: '12.34',
		...overrides,
	} );

	test( 'hardcodes apiVersion 2 and apiVersionMinor 0 regardless of the session config', () => {
		const request = buildPaymentDataRequest(
			sessionConfig( { apiVersion: 99 } ),
			transaction()
		);

		expect( request.apiVersion ).toBe( 2 );
		expect( request.apiVersionMinor ).toBe( 0 );
	} );

	test( 'builds transactionInfo from the transaction with totalPriceStatus FINAL', () => {
		const request = buildPaymentDataRequest(
			sessionConfig(),
			transaction( {
				countryCode: 'DE',
				currencyCode: 'EUR',
				total: '9.99',
			} )
		);

		expect( request.transactionInfo ).toEqual( {
			countryCode: 'DE',
			currencyCode: 'EUR',
			totalPriceStatus: 'FINAL',
			totalPrice: '9.99',
		} );
	} );

	test( 'passes a 3-decimal total straight through without rounding or reformatting', () => {
		const request = buildPaymentDataRequest(
			sessionConfig(),
			transaction( { total: '12.345' } )
		);

		expect( request.transactionInfo.totalPrice ).toBe( '12.345' );
	} );

	test( 'always requires email regardless of whether shipping is requested', () => {
		expect(
			buildPaymentDataRequest( sessionConfig(), transaction() )
				.emailRequired
		).toBe( true );
		expect(
			buildPaymentDataRequest(
				sessionConfig(),
				transaction( { requiresShipping: true } )
			).emailRequired
		).toBe( true );
	} );

	test( 'does not include the shipping callback intents and flags when requiresShipping is omitted', () => {
		const request = buildPaymentDataRequest(
			sessionConfig(),
			transaction()
		);

		expect( request ).not.toHaveProperty( 'callbackIntents' );
		expect( request ).not.toHaveProperty( 'shippingAddressRequired' );
		expect( request ).not.toHaveProperty( 'shippingOptionRequired' );
	} );

	test( 'requests the shipping address and option callbacks when shipping is requested', () => {
		const request = buildPaymentDataRequest(
			sessionConfig(),
			transaction( { requiresShipping: true } )
		);

		expect( request.callbackIntents ).toEqual( [
			'SHIPPING_ADDRESS',
			'SHIPPING_OPTION',
		] );
		expect( request.shippingAddressRequired ).toBe( true );
		expect( request.shippingOptionRequired ).toBe( true );
	} );

	test( 'always requires a phone number when shipping is requested', () => {
		const request = buildPaymentDataRequest(
			sessionConfig(),
			transaction( { requiresShipping: true } )
		);

		expect( request.shippingAddressParameters ).toEqual(
			expect.objectContaining( { phoneNumberRequired: true } )
		);
	} );

	test( 'restricts the shippable countries only when the store supplies a non-empty list', () => {
		const restricted = buildPaymentDataRequest(
			sessionConfig(),
			transaction( { requiresShipping: true, countries: [ 'US', 'CA' ] } )
		);
		const unrestricted = buildPaymentDataRequest(
			sessionConfig(),
			transaction( { requiresShipping: true, countries: [] } )
		);

		expect( restricted.shippingAddressParameters.allowedCountryCodes ).toEqual(
			[ 'US', 'CA' ]
		);
		expect(
			unrestricted.shippingAddressParameters
		).not.toHaveProperty( 'allowedCountryCodes' );
	} );

	test( 'omits the entire shipping block, including shippingAddressParameters, when shipping is not requested', () => {
		const request = buildPaymentDataRequest(
			sessionConfig(),
			transaction( { countries: [ 'US' ] } )
		);

		expect( request ).not.toHaveProperty( 'shippingAddressParameters' );
		expect( request ).not.toHaveProperty( 'callbackIntents' );
	} );

	test( 'requests a full billing address on the card payment method, keeping its other parameters', () => {
		const allowedPaymentMethods = [
			{
				type: 'CARD',
				parameters: { allowedCardNetworks: [ 'VISA', 'MASTERCARD' ] },
			},
		];

		const request = buildPaymentDataRequest(
			sessionConfig( { allowedPaymentMethods } ),
			transaction()
		);

		expect( request.allowedPaymentMethods[ 0 ] ).toEqual( {
			type: 'CARD',
			parameters: {
				allowedCardNetworks: [ 'VISA', 'MASTERCARD' ],
				billingAddressRequired: true,
				billingAddressParameters: { format: 'FULL' },
			},
		} );
	} );

	test( 'leaves non-card payment methods untouched', () => {
		const allowedPaymentMethods = [
			{ type: 'PAYPAL', parameters: { purchase_context: {} } },
		];

		const request = buildPaymentDataRequest(
			sessionConfig( { allowedPaymentMethods } ),
			transaction()
		);

		expect( request.allowedPaymentMethods[ 0 ] ).toEqual(
			allowedPaymentMethods[ 0 ]
		);
	} );

	test( 'does not mutate the session config allowedPaymentMethods, since the same object also renders the button', () => {
		const allowedPaymentMethods = [
			{ type: 'CARD', parameters: { allowedCardNetworks: [ 'VISA' ] } },
		];
		const config = sessionConfig( { allowedPaymentMethods } );

		buildPaymentDataRequest( config, transaction() );

		expect( config.allowedPaymentMethods ).toEqual( [
			{ type: 'CARD', parameters: { allowedCardNetworks: [ 'VISA' ] } },
		] );
	} );
} );
