/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';

export const defaultUiTestData = [
	{
		testSummary:
			'PCP-4311 | Settings - Croatia - Onboarding initial page - Default UI',
		wooCommerceGeneralSettings: shopSettings.croatia.general,
	},
	{
		testSummary:
			'PCP-4309 | Settings - Germany - Onboarding initial page - Default UI',
		wooCommerceGeneralSettings: shopSettings.germany.general,
	},
	{
		testSummary:
			'PCP-4310 | Settings - UK - Onboarding initial page - Default UI',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testSummary:
			'PCP-4308 | Settings - US - Onboarding initial page - Default UI',
		wooCommerceGeneralSettings: shopSettings.usa.general,
	},
];

export const onboardingCheckoutComparison = [
	{
		testKey: 'PCP-4366',
		country: 'US',
		wooCommerceGeneralSettings: shopSettings.usa.general,
	},
	{
		testKey: 'PCP-4367',
		country: 'UK',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testKey: 'PCP-4368',
		country: 'Canada',
		wooCommerceGeneralSettings: shopSettings.canada.general,
	},
	{
		testKey: 'PCP-4369',
		country: 'Australia',
		wooCommerceGeneralSettings: shopSettings.australia.general,
	},
	{
		testKey: 'PCP-4370',
		country: 'France',
		wooCommerceGeneralSettings: shopSettings.france.general,
	},
	{
		testKey: 'PCP-4371',
		country: 'Italy',
		wooCommerceGeneralSettings: shopSettings.italy.general,
	},
	{
		testKey: 'PCP-4372',
		country: 'Germany',
		wooCommerceGeneralSettings: shopSettings.germany.general,
	},
	{
		testKey: 'PCP-4373',
		country: 'Spain',
		wooCommerceGeneralSettings: shopSettings.spain.general,
	},
];
