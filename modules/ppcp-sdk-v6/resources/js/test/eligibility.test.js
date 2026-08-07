import { checkEligibility, checkVaultEligibility } from '../eligibility';

function sdkWith( { eligible = [], details = null, detailsThrows = false } ) {
	return {
		findEligibleMethods: jest.fn().mockResolvedValue( {
			isEligible: ( method ) => eligible.includes( method ),
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
} );

describe( 'checkVaultEligibility', () => {
	test( 'queries the VAULT_WITHOUT_PAYMENT flow and reports paypal eligibility', async () => {
		const sdk = sdkWith( { eligible: [ 'paypal' ] } );

		const result = await checkVaultEligibility( sdk, {
			currencyCode: 'USD',
		} );

		expect( sdk.findEligibleMethods ).toHaveBeenCalledWith( {
			currencyCode: 'USD',
			paymentFlow: 'VAULT_WITHOUT_PAYMENT',
		} );
		expect( result ).toEqual( { paypal: true } );
	} );

	test( 'reports paypal as ineligible when the SDK says so', async () => {
		const sdk = sdkWith( { eligible: [] } );

		const result = await checkVaultEligibility( sdk, {
			currencyCode: 'EUR',
		} );

		expect( result ).toEqual( { paypal: false } );
	} );
} );
