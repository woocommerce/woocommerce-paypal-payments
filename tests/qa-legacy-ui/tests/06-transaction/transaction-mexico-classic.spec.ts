/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { transactionsOnClassicCheckoutOxxo } from './_test-scenarios';
import {
	taxSettings,
} from '../../resources';
import {
	oxxoClassicCheckoutMexico,
	oxxoClassicCheckoutMexicoExcludingTax,
} from './_test-data/oxxo';

transactionsOnClassicCheckoutOxxo( oxxoClassicCheckoutMexico );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );
	transactionsOnClassicCheckoutOxxo( oxxoClassicCheckoutMexicoExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );
