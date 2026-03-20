/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';
import { taxSettings } from '../../resources';
import {
	standardCardButtonClassicCheckout,
	standardCardButtonClassicCheckoutExcludingTax
} from './_test-data/standard-card-button';

transactionsOnClassicCheckout( standardCardButtonClassicCheckout );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	test.beforeEach( async ( {}, testInfo ) => {
		testInfo.setTimeout( 3 * 60_000 );
	} );
	
	transactionsOnClassicCheckout(
		standardCardButtonClassicCheckoutExcludingTax
	);

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );
