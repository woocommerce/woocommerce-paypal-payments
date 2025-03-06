/**
 * External dependencies
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { learnMoreLinksByCountry } from './_test-data/learn-more-links.data';

test.describe( () => {
	const countries = Object.keys( learnMoreLinksByCountry );

	for ( const country of countries ) {
		test(
			learnMoreLinksByCountry[ country ].testTitle,
			async ( { pcpOnboarding, wooCommerceApi } ) => {
				const expectedLinks = learnMoreLinksByCountry[ country ].links;

				await wooCommerceApi.updateGeneralSettings( {
					woocommerce_default_country: country,
					woocommerce_currency: 'USD',
				} );

				await pcpOnboarding.visit();
				await pcpOnboarding.page.waitForLoadState( 'networkidle' );
				for ( const { url, title } of expectedLinks ) {
					const link = await pcpOnboarding.page.locator(
						`a[href="${ url }"]`
					);
					await expect.soft( link ).toBeVisible();

					if ( await link.isVisible() ) {
						const newContext = await pcpOnboarding.page
							.context()
							.browser()
							?.newContext();
						const newPage = await newContext?.newPage();
						await newPage?.goto( url );

						expect.soft( newPage.url() ).toBe( url );
						expect.soft( await newPage.title() ).toContain( title );

						await newPage.close();
						await newContext.close();
					}
				}
			}
		);
	}
} );
