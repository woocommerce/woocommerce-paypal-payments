import { checkEligibility, checkVaultEligibility } from './eligibility';

function sdkWith( {
	eligible = [],
	details = null,
	detailsThrows = false,
	isEligibleThrowsFor = [],
} ) {
	return {
		findEligibleMethods: jest.fn().mockResolvedValue( {
			isEligible: ( method ) => {
				if ( isEligibleThrowsFor.includes( method ) ) {
					throw new Error( 'component not loaded' );
				}
				return eligible.includes( method );
			},
			getDetails: () => {
				if ( detailsThrows ) {
					throw new Error( 'unsupported region' );
				}
				return details;
			},
		} ),
	};
}

describe( 'checkEligibility', () => {
	test( 'passes currency, country and amount to the SDK', async () => {
		const sdk = sdkWith( { eligible: [ 'paypal' ] } );

		await checkEligibility( sdk, {
			currencyCode: 'USD',
			countryCode: 'US',
			amount: '110.00',
		} );

		expect( sdk.findEligibleMethods ).toHaveBeenCalledWith( {
			currencyCode: 'USD',
			countryCode: 'US',
			amount: '110.00',
		} );
	} );

	test( 'omits empty optional params', async () => {
		const sdk = sdkWith( { eligible: [] } );

		await checkEligibility( sdk, { currencyCode: 'EUR' } );

		expect( sdk.findEligibleMethods ).toHaveBeenCalledWith( {
			currencyCode: 'EUR',
		} );
	} );

	test( 'reports each method and pay later details', async () => {
		const sdk = sdkWith( {
			eligible: [ 'paypal', 'paylater' ],
			details: { productCode: 'PAY_LATER_LONG_TERM' },
		} );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.paypal ).toBe( true );
		expect( result.venmo ).toBe( false );
		expect( result.paylater ).toBe( true );
		expect( result.payLaterDetails ).toEqual( {
			productCode: 'PAY_LATER_LONG_TERM',
		} );
	} );

	test( 'survives getDetails failures', async () => {
		const sdk = sdkWith( {
			eligible: [ 'paylater' ],
			detailsThrows: true,
		} );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.paylater ).toBe( true );
		expect( result.payLaterDetails ).toBeNull();
	} );

	test( 'reports googlepay eligibility', async () => {
		const sdk = sdkWith( { eligible: [ 'googlepay' ] } );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.googlepay ).toBe( true );
	} );

	test( 'resolves with googlepay false, instead of rejecting, when isEligible throws for googlepay', async () => {
		const sdk = sdkWith( { isEligibleThrowsFor: [ 'googlepay' ] } );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.googlepay ).toBe( false );
	} );

	test( 'reports applepay eligibility', async () => {
		const sdk = sdkWith( { eligible: [ 'applepay' ] } );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.applepay ).toBe( true );
	} );

	test( 'resolves with applepay false, instead of rejecting, when isEligible throws for applepay', async () => {
		const sdk = sdkWith( { isEligibleThrowsFor: [ 'applepay' ] } );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.applepay ).toBe( false );
	} );

	test( 'reports card eligibility', async () => {
		const sdk = sdkWith( { eligible: [ 'card' ] } );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.card ).toBe( true );
	} );

	test( 'resolves with card false, instead of rejecting, when isEligible throws for card', async () => {
		const sdk = sdkWith( { isEligibleThrowsFor: [ 'card' ] } );

		const result = await checkEligibility( sdk, { currencyCode: 'USD' } );

		expect( result.card ).toBe( false );
	} );
} );

describe( 'checkVaultEligibility', () => {
	test( 'queries the vault-without-payment flow for the given currency', async () => {
		const sdk = sdkWith( { eligible: [ 'paypal' ] } );

		await checkVaultEligibility( sdk, { currencyCode: 'USD' } );

		expect( sdk.findEligibleMethods ).toHaveBeenCalledWith( {
			currencyCode: 'USD',
			paymentFlow: 'VAULT_WITHOUT_PAYMENT',
		} );
	} );

	test( 'maps paypal and advanced_cards eligibility onto paypal and card', async () => {
		const sdk = sdkWith( { eligible: [ 'paypal', 'advanced_cards' ] } );

		const result = await checkVaultEligibility( sdk, {
			currencyCode: 'EUR',
		} );

		expect( result ).toEqual( { paypal: true, card: true } );
	} );

	test( 'reports methods as ineligible when the SDK excludes them', async () => {
		const sdk = sdkWith( { eligible: [] } );

		const result = await checkVaultEligibility( sdk, {
			currencyCode: 'EUR',
		} );

		expect( result ).toEqual( { paypal: false, card: false } );
	} );
} );
