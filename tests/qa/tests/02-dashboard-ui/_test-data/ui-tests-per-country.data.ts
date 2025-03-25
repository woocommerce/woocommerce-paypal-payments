/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';

export const badgeTestsData = [
	{
		testKey: 'PCP-4319',
		wooCommerceCountryCode: 'US:SC',
		country: 'United States',
	},
	{
		testKey: 'PCP-4320',
		wooCommerceCountryCode: 'UK',
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
		testKey: 'PCP-4326',
		wooCommerceCountryCode: 'ES:GR',
		country: 'Spain',
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
