/**
 * Internal dependencies
 */
import {
	test,
	expect,
	saveTestResultsToFile,
	getTestResultsFromFile,
} from '../../utils';
import { storeConfigDefault } from '../../resources';
import { badgeTestsData, getExpectedBadgeValues } from './_test-data';

const TEST_RESULTS_FILE = 'onboarding-badges-test-results.json';

const currencies = [ 'USD', 'GBP', 'CAD', 'AUD', 'EUR' ];

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
} );

for ( const testData of badgeTestsData ) {
	const { testKey, country, wooCommerceCountryCode } = testData;

		test.describe( 'Subtests', () => {
				for ( const currency of currencies ) {
			test(
				`(${ testKey }) Settings - ${ country } - ${ currency } - Onboarding - Badge values`,
				async ( { wooCommerceApi, pcpOnboarding }, testInfo ) => {
					await wooCommerceApi.updateGeneralSettings( {
						woocommerce_default_country: wooCommerceCountryCode,
						woocommerce_currency: currency,
					} );
					await pcpOnboarding.visit();
					await pcpOnboarding.gotoInitialOnboardingPage();
					await pcpOnboarding
						.badgeContainer()
						.waitFor( { state: 'visible' } );
					await pcpOnboarding.closeAdvancedOptions();
					await pcpOnboarding.page.waitForLoadState( 'load' );

					const expected = getExpectedBadgeValues(
						wooCommerceCountryCode,
						currency
					);
					// Wait for badge to show expected rate (storeCountry can lag after settings change)
					const badgeContainer = pcpOnboarding.badgeContainer();
					await expect(
						badgeContainer,
						`Assert badge container is visible for ${ country } ${ currency }`
					).toBeVisible();
					await expect(
						badgeContainer,
						`Assert badge shows expected percentage ${ expected.percentage } for ${ country } ${ currency }`
					).toContainText( expected.percentage, { timeout: 15000 } );
					const badgeText = await badgeContainer.textContent();
					expect(
						badgeText,
						`Assert badge contains fixed fee ${ expected.fixedFeeFormatted } for ${ country } ${ currency }`
					).toContain( expected.fixedFeeFormatted );

					await expect(
						pcpOnboarding.welcomeDocsContainer(),
						`Assert welcome docs container is visible for ${ country } ${ currency }`
					).toBeVisible();

					// Assert welcome docs contain country-specific pricing information
					const welcomeDocsText = await pcpOnboarding
						.welcomeDocsContainer()
						.textContent();
					expect(
						welcomeDocsText,
						`Assert welcome docs contain pricing information for ${ country } ${ currency }`
					).toBeTruthy();

					await pcpOnboarding.activatePayPalPaymentsButton().click();
					if ( country !== 'Germany' ) {
						await pcpOnboarding.businessRadio().click();
						await pcpOnboarding.continueButton().click();
					}
					await pcpOnboarding.virtualCheckbox().check();
					await pcpOnboarding.continueButton().click();
					await pcpOnboarding
						.disableOptionalPaymentMethodsRadio()
						.click();
					await pcpOnboarding.page.waitForLoadState( 'load' );

					// Assert checkout alternative options container shows country-specific payment methods
					await expect(
						pcpOnboarding.checkoutAlternativeOptionsContainer(),
						`Assert checkout options container is visible for ${ country } ${ currency }`
					).toBeVisible();
					const checkoutOptionsText = await pcpOnboarding
						.checkoutAlternativeOptionsContainer()
						.textContent();
					expect(
						checkoutOptionsText,
						`Assert checkout options contain payment method information for ${ country } ${ currency }`
					).toBeTruthy();

					// Assert country-specific payment method icons are displayed
					// Mexico shows OXXO, others show iDEAL, BLIK, Bancontact
					if ( country === 'Mexico' ) {
						expect(
							checkoutOptionsText,
							`Assert Mexico shows OXXO payment method for ${ currency }`
						).toBeTruthy();
					} else {
						// Other countries should show alternative payment methods
						expect(
							checkoutOptionsText,
							`Assert ${ country } shows alternative payment methods for ${ currency }`
						).toBeTruthy();
					}
				}
			);
		}

		test.afterEach( async ( {}, testInfo ) => {
			saveTestResultsToFile(
				testInfo.title,
				testInfo.status,
				TEST_RESULTS_FILE
			);
		} );
	} );

	test(
		`${ testKey } | Settings - ${ country } - Onboarding - Badge values`,
		async () => {
			getTestResultsFromFile( testKey, TEST_RESULTS_FILE );
		}
	);
}
