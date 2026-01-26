/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { merchants, storeConfigUsa } from '../../resources';
import { vaultingClassicCheckout } from './_test-data';
import { testVaultingClassicCheckout } from './_test-scenarios';

const {
	savePaymentMethodData,
	acdcAdditionalCardData,
	vaultedPaymentMethodData,
} = vaultingClassicCheckout;

const {
	testSavePaymentMethod,
	testAcdcAdditionalCard,
	testVaultedPaymentMethod,
} = testVaultingClassicCheckout;

test.beforeAll( async ( { utils, pcpApi, wooCommerceApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		enableClassicPages: true,
	} );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret,
		{
			isCasualSeller: false,
			areOptionalPaymentMethodsEnabled: true,
		}
	);
	await pcpApi.updatePcpSettings( {
		savePaypalAndVenmo: true,
		saveCardDetails: true,
	} );
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of savePaymentMethodData ) {
	testSavePaymentMethod( testOrder );
}

for ( const testOrder of acdcAdditionalCardData ) {
	testAcdcAdditionalCard( testOrder );
}

for ( const testOrder of vaultedPaymentMethodData ) {
	testVaultedPaymentMethod( testOrder );
}
