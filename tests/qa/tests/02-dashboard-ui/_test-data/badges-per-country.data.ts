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

export const initialOnboardingScreenData = [
	{
		testSummary:
			'PCP-0000 | Settings - UK - Onboarding - Default UI (acdc, paylater) - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testSummary:
			'PCP-0000 | Settings - Croatia - Onboarding - Default UI (only BCDC) - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.croatia.general,
	},
];

export const defaultUITestData = [
	{
		testSummary:
			'PCP-0000 | Settings - Croatia - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.croatia.general,
	},
	{
		testSummary:
			'PCP-0000 | Settings - Germany - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.germany.general,
	},
	{
		testSummary:
			'PCP-0000 | Settings - UK - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.uk.general,
	},
	{
		testSummary:
			'PCP-0000 | Settings - US - Onboarding initial page - Default UI @percy',
		wooCommerceGeneralSettings: shopSettings.usa.general,
	},
];
