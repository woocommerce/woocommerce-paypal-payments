/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';
import {
	taxSettings
} from '../../resources';
import {
	debitOrCreditCardClassicCheckout,
	debitOrCreditCardClassicCheckoutExcludingTax
} from './_test-data/debit-or-credit-card';

transactionsOnClassicCheckout( debitOrCreditCardClassicCheckout );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnClassicCheckout(
		debitOrCreditCardClassicCheckoutExcludingTax
	);

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );
