/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';

export const badgeTestsData = [
	{
		testKey: 'PCP-4319',
		wooCommerceCountryCode: 'US:SC',
		countryCode: 'US',
	},
	{
		testKey: 'PCP-4320',
		wooCommerceCountryCode: 'GB',
		countryCode: 'UK',
	},
	{
		testKey: 'PCP-4321',
		wooCommerceCountryCode: 'CA:AB',
		countryCode: 'CA',
	},
	{
		testKey: 'PCP-4322',
		wooCommerceCountryCode: 'AU:NSW',
		countryCode: 'AU',
	},
	{
		testKey: 'PCP-4323',
		wooCommerceCountryCode: 'FR',
		countryCode: 'FR',
	},
	{
		testKey: 'PCP-4324',
		wooCommerceCountryCode: 'IT:GE',
		countryCode: 'IT',
	},
	{
		testKey: 'PCP-4326',
		wooCommerceCountryCode: 'ES:GR',
		countryCode: 'ES',
	},
	{
		testKey: 'PCP-4325',
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
