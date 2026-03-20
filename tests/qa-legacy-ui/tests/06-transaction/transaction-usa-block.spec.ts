/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	transactionsOnCart,
	transactionsOnCheckout,
	transactionsOnPayByLink,
	transactionsOnProduct,
} from './_test-scenarios';
import {
	taxSettings
} from '../../resources';
import { acdcPayByLink } from './_test-data/acdc';
import {
	payLaterCart,
	payLaterCartExcludingTax,
	payLaterCheckout,
	payLaterCheckoutExcludingTax,
	payLaterProduct
} from './_test-data/pay-later';
import {
	payPalCart,
	payPalCartExcludingTax,
	payPalCheckout,
	payPalCheckoutExcludingTax,
	payPalPayByLink,
	payPalPayByLinkDebugging,
	payPalProduct
} from './_test-data/paypal';

transactionsOnCart( payPalCart );
transactionsOnCart( payLaterCart );

transactionsOnCheckout( payPalCheckout );
transactionsOnCheckout( payLaterCheckout );

transactionsOnProduct( payPalProduct );
transactionsOnProduct( payLaterProduct );

transactionsOnPayByLink( payPalPayByLink );
transactionsOnPayByLink( acdcPayByLink );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnCart( payPalCartExcludingTax );
	transactionsOnCart( payLaterCartExcludingTax );

	transactionsOnCheckout( payPalCheckoutExcludingTax );
	transactionsOnCheckout( payLaterCheckoutExcludingTax );

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );

test.describe( 'WP Debugging', () => {
	test.beforeAll(
		async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } )
	);

	transactionsOnPayByLink( payPalPayByLinkDebugging );

	test.beforeAll(
		async ( { cli } ) => await cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } )
	);
} );
