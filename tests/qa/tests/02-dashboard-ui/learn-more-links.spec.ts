/**
 * External dependencies
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { learnMoreLinksByCountry } from './.test-data/learn-more-links.data';

test.describe( () => {
	const countries = Object.keys( learnMoreLinksByCountry );

	for ( const country of countries ) {
		test( `Settings -  ${ country } - Onboarding - Links Learn more and link for fees in footer`, async ( {
			pcpOnboarding,
			wooCommerceApi,
		} ) => {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_default_country: country,
				woocommerce_currency: 'USD'
			} );

			await pcpOnboarding.visit();
			await pcpOnboarding.page.waitForLoadState( 'networkidle' );
			const pageLinks = await pcpOnboarding.getLearnMoreLinks();

			const expectedLinks = learnMoreLinksByCountry[ country ];

			expect( pageLinks.map( ( l ) => l.url ).sort() ).toEqual(
				expectedLinks.map( ( l ) => l.url ).sort()
			);

			for ( const { url, title } of expectedLinks ) {
				const newPage = await pcpOnboarding.clickLearnMoreLink( url );

				// Assertions
				expect( newPage.url() ).toBe( url );
				const pageTitle = await pcpOnboarding.getPageTitle( newPage );
				expect( pageTitle ).toContain( title );

				await newPage.close();
			}
		} );
	}
} );
