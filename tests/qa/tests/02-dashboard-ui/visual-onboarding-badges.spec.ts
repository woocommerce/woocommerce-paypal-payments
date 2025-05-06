/**
 * Internal dependencies
 */
import { test, expect, saveTestResultsToFile, getTestResultsFromFile } from '../../utils';
import { storeConfigDefault } from '../../resources';
import { badgeTestsData } from './_test-data';

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
			test( `(${ testKey }) Settings - ${ country } - ${ currency } - Onboarding - Badge values`, async ( {
				wooCommerceApi,
				pcpOnboarding,
			}, testInfo ) => {
				await wooCommerceApi.updateGeneralSettings( {
					woocommerce_default_country: wooCommerceCountryCode,
					woocommerce_currency: currency,
				} );
				await pcpOnboarding.visit();
				await pcpOnboarding.gotoInitialOnboardingPage();
				await pcpOnboarding.badgeContainer().waitFor( { state: 'visible' } );
				await pcpOnboarding.closeAdvancedOptions();
				await pcpOnboarding.page.waitForLoadState( 'load' );
				await pcpOnboarding.snapshotLocator(
					pcpOnboarding.welcomeDocsContainer(),
					`${ testInfo.title } - PayPal Settings`,
					{
						timeout: 3000,
					}
				);

				await pcpOnboarding.activatePayPalPaymentsButton().click();
				if( country !== 'Germany' ) {
					await pcpOnboarding.businessRadio().click();
					await pcpOnboarding.continueButton().click();
				}
				await pcpOnboarding.virtualCheckbox().check();
				await pcpOnboarding.continueButton().click();
				await pcpOnboarding.disableOptionalPaymentMethodsRadio().click();
				await pcpOnboarding.page.waitForLoadState( 'load' );
				await pcpOnboarding.snapshotLocator(
					pcpOnboarding.checkoutAlternativeOptionsContainer(),
					`${ testInfo.title } - Choose checkout options`,
					{
						timeout: 3000,
					}
				);
			} );
		}
		
		test.afterEach( async ( {}, testInfo ) => {
			saveTestResultsToFile(
				testInfo.title,
				testInfo.status,
				TEST_RESULTS_FILE
			);
		} );
	} );

	test( `${ testKey } | Settings - ${ country } - Onboarding - Badge values`, async () => {
		getTestResultsFromFile( testKey, TEST_RESULTS_FILE );
	} );
}
