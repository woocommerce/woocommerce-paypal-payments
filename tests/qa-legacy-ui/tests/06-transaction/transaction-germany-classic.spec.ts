/**
 * Internal dependencies
 */
import { test } from '../../utils';
/**
 * External dependencies
 */
import {
	payUponInvoice,
	pcpConfigGermany,
	storeConfigClassic,
	storeConfigGermany,
	taxSettings,
} from '../../resources';
import {
	payUponInvoiceClassicCheckoutGermany,
	payUponInvoiceClassicCheckoutGermanyExcludingTax,
} from './_test-data/pay-upon-invoice';
import { transactionsOnClassicCheckout } from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	test.setTimeout( 2 * 60 * 1000 );
	await utils.configureStore( {
		...storeConfigGermany,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigGermany );
	await utils.pcpPaymentMethodIsEnabled( payUponInvoice.method );
} );

transactionsOnClassicCheckout( payUponInvoiceClassicCheckoutGermany );

test.describe( 'Excluding Tax', () => {
	test.beforeAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.excluding );
	} );

	transactionsOnClassicCheckout(
		payUponInvoiceClassicCheckoutGermanyExcludingTax
	);

	test.afterAll( async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );
} );
