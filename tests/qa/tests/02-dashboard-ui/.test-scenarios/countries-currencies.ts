/**
 * External dependencies
 */
import { shopSettings } from '@inpsyde/playwright-utils/build';
import { PercyConfig } from '@inpsyde/playwright-utils/build/@types/visual/percy';
/**
 * Internal dependencies
 */



const percyConfig: PercyConfig = {
    scope: '#ppcp-settings-container',
    httpCredentials: {
        username: process.env.WP_BASIC_AUTH_USER,
        password: process.env.WP_BASIC_AUTH_PASSWORD,
    },
};

export async function badgeValuesPerCountry({ wooCommerceApi, pcpOnboarding, percy }, testInfo, country, label, currencies) {
    for (const currency of currencies) {

        const response = await wooCommerceApi.updateGeneralSettings({
            woocommerce_default_country: country,
            woocommerce_currency: currency,
        });

        console.log(response);

        await pcpOnboarding.visit();
        await pcpOnboarding.gotoInitialOnboardingPage();
        await pcpOnboarding.page.waitForSelector('span.ppcp-r-title-badge.ppcp-r-title-badge--info');
        await pcpOnboarding.closeAdvancedOptions();
        await pcpOnboarding.page.waitForLoadState('load');
        await percy.takeSnapshot(`${testInfo.title} - PayPal Settings - ${label} - ${currency}`, percyConfig);

        await pcpOnboarding.activatePayPalPaymentsButton().click();
        await pcpOnboarding.businessRadio().click();
        await pcpOnboarding.continueButton().click();
        await pcpOnboarding.virtualCheckbox().check();
        await pcpOnboarding.continueButton().click();
        await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
        await pcpOnboarding.page.waitForLoadState('load');
        await percy.takeSnapshot(`${testInfo.title} - Choose checkout options - ${label} - ${currency}`, percyConfig);
    }
}