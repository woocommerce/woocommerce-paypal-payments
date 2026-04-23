/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';

export const defaultUiTestData = [
	{
		testSummary:
			'PCP-4311 | Settings - Croatia - Onboarding initial page - Default UI @Critical',
		wooCommerceGeneralSettings: shopSettings.croatia.general,
	},
	{
		testSummary:
			'PCP-4309 | Settings - Germany - Onboarding initial page - Default UI @Critical',
		wooCommerceGeneralSettings: shopSettings.germany.general,
	},
	{
		testSummary:
			'PCP-4310 | Settings - UK - Onboarding initial page - Default UI @Critical',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testSummary:
			'PCP-4308 | Settings - US - Onboarding initial page - Default UI @Critical',
		wooCommerceGeneralSettings: shopSettings.usa.general,
	},
	{
		testSummary:
			'PCP-4733 | Settings - Mexico - Onboarding initial page - Default UI @Critical',
		wooCommerceGeneralSettings: shopSettings.mexico.general,
	},
];

export const onboardingCheckoutComparison = [
	{
		testKey: 'PCP-4366 @Critical',
		country: 'US',
		wooCommerceGeneralSettings: shopSettings.usa.general,
	},
	{
		testKey: 'PCP-4367 @Critical',
		country: 'UK',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testKey: 'PCP-4368 @Critical',
		country: 'Canada',
		wooCommerceGeneralSettings: shopSettings.canada.general,
	},
	{
		testKey: 'PCP-4369 @Critical',
		country: 'Australia',
		wooCommerceGeneralSettings: shopSettings.australia.general,
	},
	{
		testKey: 'PCP-4370 @Critical',
		country: 'France',
		wooCommerceGeneralSettings: shopSettings.france.general,
	},
	{
		testKey: 'PCP-4371 @Critical',
		country: 'Italy',
		wooCommerceGeneralSettings: shopSettings.italy.general,
	},
	{
		testKey: 'PCP-4372 @Critical',
		country: 'Germany',
		wooCommerceGeneralSettings: shopSettings.germany.general,
	},
	{
		testKey: 'PCP-4373 @Critical',
		country: 'Spain',
		wooCommerceGeneralSettings: shopSettings.spain.general,
	},
];

export const onboardingSubscriptionComparisonByOPM = [
	{
		testSummary:
			'PCP-4357 | Subscription - Settings - US - Onboarding - Connect with business account, all product types, card payment enabled @Critical',
		// subscriptionsEnabled: true,
		optionalPaymentsEnabled: true,
	},
	{
		testSummary:
			'PCP-4360 | Subscription - Settings - US - Onboarding - Connect with business account, all product types, card payment disabled @Critical',
		// subscriptionsEnabled: true,
		optionalPaymentsEnabled: false,
	},
];
