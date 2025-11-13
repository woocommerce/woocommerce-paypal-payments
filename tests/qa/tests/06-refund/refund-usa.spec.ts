/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	merchants,
	storeConfigUsa,
	gateways,
	taxSettings,
	customers,
} from '../../resources';
import { testRefund } from './_test-scenarios';
import {
	refundPayPalFromCheckout,
	refundPayPalFromPayByLink,
} from './_test-data/paypal/refund-paypal.data';

const { payPal, venmo, acdc, fastlane } = gateways;

test.beforeAll( async ( { utils, pcpApi } ) => {
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
		[ venmo.id ]: { id: venmo.id, enabled: true },
		[ acdc.id ]: { id: acdc.id, enabled: true },
		[ fastlane.id ]: { id: fastlane.id, enabled: false },
	} );
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	await wooCommerceApi.deleteAllOrders();
} );

for( const testOrder of refundPayPalFromCheckout ) {
	testRefund( testOrder );
}

for( const testOrder of refundPayPalFromCheckout ) {
	testRefund( testOrder );
}
