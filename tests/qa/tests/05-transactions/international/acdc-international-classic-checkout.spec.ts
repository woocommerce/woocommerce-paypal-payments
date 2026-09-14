/**
 * Internal dependencies
 */
import { test } from '../../../utils';
import { shopSettings, gateways, orders, payments } from '../../../resources';
import { transactionsOnClassicCheckout } from '../_test-scenarios';
import {
	acdcInternationalCountries,
	guests,
} from '../_test-data/acdc/acdc-international.data';

const { acdc } = gateways;

for ( const { key, label, merchant } of acdcInternationalCountries ) {
	test.describe( label, () => {
		test.beforeAll( async ( { utils, pcpApi } ) => {
			await utils.configureStore( {
				enableClassicPages: true,
				settings: shopSettings[ key ],
			} );
			await utils.installAndActivatePcp();
			await pcpApi.resetDb();
			await pcpApi.connectMerchant(
				merchant.client_id,
				merchant.client_secret
			);
			await pcpApi.updatePcpPaymentMethods( {
				[ acdc.id ]: { id: acdc.id, enabled: true },
			} );
		} );

		transactionsOnClassicCheckout( {
			title: `Transaction - Classic checkout - ACDC - ${ label } - Default order`,
			...orders.default,
			payment: payments.acdcVisa,
			customer: guests[ key ],
			merchant,
			currency: shopSettings[ key ].general.woocommerce_currency,
		} );
	} );
}
