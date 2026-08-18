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
} );
