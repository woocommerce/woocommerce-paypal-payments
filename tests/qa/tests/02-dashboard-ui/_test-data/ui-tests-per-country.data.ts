/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';

export const badgeTestsData = [
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'US:SC',
		countryCode: 'US',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'GB',
		countryCode: 'UK',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'CA:AB',
		countryCode: 'CA',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'AU:NSW',
		countryCode: 'AU',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'FR',
		countryCode: 'FR',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'IT:GE',
		countryCode: 'IT',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'ES:GR',
		countryCode: 'ES',
	},
	{
		testKey: 'PCP-0000',
		wooCommerceCountryCode: 'DE:DE-BE',
		countryCode: 'DE',
	},
];


export const defaultUiTestData = [
	{
		testSummary:
			'PCP-4311 | Settings - Croatia - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.croatia.general,
	},
	{
		testSummary:
			'PCP-4309 | Settings - Germany - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.germany.general,
	},
	{
		testSummary:
			'PCP-4310 | Settings - UK - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testSummary:
			'PCP-4308 | Settings - US - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.usa.general,
	},
];
