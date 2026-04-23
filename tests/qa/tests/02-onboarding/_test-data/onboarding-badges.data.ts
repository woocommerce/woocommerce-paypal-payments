/**
 * Expected onboarding badge values (checkout percentage + fixed fee per currency).
 * Must stay in sync with modules/ppcp-settings/resources/js/utils/countryPriceInfo.js
 * (checkout rate and fixedFee by currency). Used to assert badge text in onboarding-badges.spec.ts.
 */
export const expectedBadgeByCountry: Record<
	string,
	{ checkout: number; fixedFee: Record<string, number> }
> = {
	US: {
		checkout: 3.49,
		fixedFee: { USD: 0.49, GBP: 0.39, CAD: 0.59, AUD: 0.59, EUR: 0.39 },
	},
	GB: {
		checkout: 2.9,
		fixedFee: { GBP: 0.3, USD: 0.3, CAD: 0.3, AUD: 0.3, EUR: 0.35 },
	},
	CA: {
		checkout: 2.9,
		fixedFee: { CAD: 0.3, USD: 0.3, GBP: 0.2, AUD: 0.3, EUR: 0.35 },
	},
	AU: {
		checkout: 2.6,
		fixedFee: { AUD: 0.3, USD: 0.3, GBP: 0.2, CAD: 0.3, EUR: 0.35 },
	},
	FR: {
		checkout: 2.9,
		fixedFee: { EUR: 0.35, USD: 0.3, GBP: 0.3, CAD: 0.3, AUD: 0.3 },
	},
	IT: {
		checkout: 3.4,
		fixedFee: { EUR: 0.35, USD: 0.3, GBP: 0.3, CAD: 0.3, AUD: 0.3 },
	},
	DE: {
		checkout: 2.99,
		fixedFee: { EUR: 0.39, USD: 0.49, GBP: 0.29, CAD: 0.59, AUD: 0.59 },
	},
	ES: {
		checkout: 2.9,
		fixedFee: { EUR: 0.35, USD: 0.3, GBP: 0.3, CAD: 0.3, AUD: 0.3 },
	},
};

/** WooCommerce country code (or base) -> country key in expectedBadgeByCountry */
const wooCommerceToBadgeCountry: Record<string, string> = {
	US: 'US',
	'US:SC': 'US',
	GB: 'GB',
	UK: 'GB',
	CA: 'CA',
	'CA:AB': 'CA',
	AU: 'AU',
	'AU:NSW': 'AU',
	FR: 'FR',
	IT: 'IT',
	'IT:GE': 'IT',
	DE: 'DE',
	'DE:DE-BE': 'DE',
	ES: 'ES',
	'ES:GR': 'ES',
};

/**
 * Returns expected badge percentage and formatted fixed fee for a country/currency.
 * Format matches formatPrice in ppcp-settings (prefix + amount + suffix).
 */
export function getExpectedBadgeValues(
	wooCommerceCountryCode: string,
	currency: string
): { percentage: string; fixedFeeFormatted: string } {
	const countryKey =
		wooCommerceToBadgeCountry[ wooCommerceCountryCode ] ??
		wooCommerceCountryCode.split( ':' )[ 0 ];
	const data = expectedBadgeByCountry[ countryKey ];
	if ( ! data ) {
		throw new Error(
			`No expected badge data for country ${ wooCommerceCountryCode } (key ${ countryKey })`
		);
	}
	const fixedAmount = data.fixedFee[ currency ];
	if ( fixedAmount === undefined ) {
		throw new Error(
			`No fixed fee for ${ countryKey } / ${ currency }`
		);
	}
	const percentage = data.checkout.toFixed( 2 );
	const amountStr = fixedAmount.toFixed( 2 );
	const currencyFormats: Record<string, string> = {
		USD: `$${ amountStr } USD`,
		CAD: `$${ amountStr } CAD`,
		AUD: `$${ amountStr } AUD`,
		EUR: `€${ amountStr }`,
		GBP: `£${ amountStr }`,
	};
	return {
		percentage: `${ percentage }%`,
		fixedFeeFormatted: currencyFormats[ currency ] ?? amountStr,
	};
}

export const badgeTestsData = [
	{
		testKey: 'PCP-4319',
		wooCommerceCountryCode: 'US:SC',
		country: 'United States',
	},
	{
		testKey: 'PCP-4320',
		wooCommerceCountryCode: 'GB',
		country: 'United Kingdom',
	},
	{
		testKey: 'PCP-4321',
		wooCommerceCountryCode: 'CA:AB',
		country: 'Canada',
	},
	{
		testKey: 'PCP-4322',
		wooCommerceCountryCode: 'AU:NSW',
		country: 'Australia',
	},
	{
		testKey: 'PCP-4323',
		wooCommerceCountryCode: 'FR',
		country: 'France',
	},
	{
		testKey: 'PCP-4324',
		wooCommerceCountryCode: 'IT:GE',
		country: 'Italy',
	},
	{
		testKey: 'PCP-4325',
		wooCommerceCountryCode: 'DE:DE-BE',
		country: 'Germany',
	},
	{
		testKey: 'PCP-4326',
		wooCommerceCountryCode: 'ES:GR',
		country: 'Spain',
	},
];
