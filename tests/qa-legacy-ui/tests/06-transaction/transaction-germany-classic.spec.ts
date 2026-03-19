/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	payUponInvoice,
	pcpConfigGermany,
	storeConfigGermany,
	taxSettings,
} from '../../resources';
import {
	payUponInvoiceClassicCheckoutGermany,
	payUponInvoiceClassicCheckoutGermanyExcludingTax,
} from './_test-data/pay-upon-invoice';
import { transactionsOnClassicCheckout } from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {

	test.beforeAll( async ( { utils } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigGermany,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigGermany );
		await utils.pcpPaymentMethodIsEnabled( payUponInvoice.method );
		await utils.updatePcpPlugin();
		// await new Promise( ( resolve ) => setTimeout( resolve, 60_000 ) );
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
} );
