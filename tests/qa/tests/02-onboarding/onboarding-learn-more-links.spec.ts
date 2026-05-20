/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { learnMoreLinksByCountry } from './_test-data';
import { storeConfigDefault } from '../../resources';

test.describe( () => {
	test.beforeAll( async ( { utils, pcpApi } ) => {
		await utils.configureStore( storeConfigDefault );
		await utils.installAndActivatePcp();
		await pcpApi.resetDb();
	} );

	const countryKeys = Object.keys( learnMoreLinksByCountry );

	for ( const countryKey of countryKeys ) {
		const { testTitle, wooCommerceCountryCode, links } =
			learnMoreLinksByCountry[ countryKey ];

		test( testTitle, async ( { pcpOnboarding, wooCommerceApi } ) => {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_default_country: wooCommerceCountryCode,
				woocommerce_currency: 'USD',
			} );

			await pcpOnboarding.visit();
			await pcpOnboarding.gotoInitialOnboardingPage();
			await pcpOnboarding.page.waitForLoadState( 'load' );
			await pcpOnboarding.onboardingContentContainer().waitFor( {
				state: 'visible',
				timeout: 10000,
			} );
			// Wait for at least one learn-more link to be present (React has rendered)
			await pcpOnboarding.page
				.locator( 'a[href^="https://www.paypal.com/"]' )
				.first()
				.waitFor( { state: 'visible', timeout: 10000 } );

			for ( const { url } of links ) {
				const link = pcpOnboarding.page.locator( `a[href="${ url }"]` );
				await expect(
					link,
					`Assert "Learn more" / fee link is visible: ${ url }`
				).toBeVisible();

				// Validate link navigates to PayPal (PayPal may redirect e.g. checkout/installments → installment-payments)
				const browser = pcpOnboarding.page.context().browser();
				if ( browser ) {
					const newContext = await browser.newContext();
					const newPage = await newContext.newPage();
					try {
						await newPage.goto( url, { waitUntil: 'domcontentloaded', timeout: 15000 } );
						const finalUrl = newPage.url();
						expect(
							finalUrl,
							`Assert link leads to PayPal: ${ url }`
						).toMatch( /^https:\/\/www\.paypal\.com\// );
					} finally {
						await newPage.close();
						await newContext.close();
					}
				}
			}
		} );
	}
} );
