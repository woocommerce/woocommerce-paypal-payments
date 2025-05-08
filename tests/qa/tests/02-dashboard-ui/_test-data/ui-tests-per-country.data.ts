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
      testSummary:
        'PCP-4366 | Settings - US - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.usa.general,
    },
    {
      testSummary:
        'PCP-4367 | Settings - UK - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.uk.general,
    },
    {
      testSummary:
        'PCP-4368 | Settings - Canada - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.canada.general,
    },
    {
      testSummary:
        'PCP-4369 | Settings - Australia - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.australia.general,
    },
    {
      testSummary:
        'PCP-4370 | Settings - France - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.france.general,
    },
    {
      testSummary:
        'PCP-4371 | Settings - Italy - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.italy.general,
    },
    {
      testSummary:
        'PCP-4372 | Settings - Germany - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.germany.general,
    },
    {
      testSummary:
        'PCP-4373 | Settings - Spain - Onboarding - Compare initial onboarding page (right part) with expanded checkout screen',
      wooCommerceGeneralSettings: shopSettings.spain.general,
    },
  ];