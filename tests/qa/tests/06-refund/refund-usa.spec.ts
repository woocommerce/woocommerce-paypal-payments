/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	gateways,
	customers,
} from '../../resources';
import { testRefund } from './_test-scenarios';
import {
	refundPayPalFromCheckout,
	refundPayPalFromPayByLink,
	refundAcdcFromCheckout,
	refundAcdcFromPayByLink,
} from './_test-data';

const { payPal, acdc } = gateways;

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		customer: customers.usa,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
	await pcpApi.updatePcpPaymentMethods( {
		[ payPal.id ]: { id: payPal.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
	} );
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of refundPayPalFromCheckout ) {
	testRefund( testOrder );
}

for ( const testOrder of refundAcdcFromCheckout ) {
	testRefund( testOrder );
}

for ( const testOrder of refundPayPalFromPayByLink ) {
	testRefund( testOrder );
}

for ( const testOrder of refundAcdcFromPayByLink ) {
	testRefund( testOrder );
}
