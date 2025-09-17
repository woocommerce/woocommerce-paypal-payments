/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { merchants, storeConfigUsa } from '../../resources';
import { vaultingCheckout } from './_test-data';
import { testVaultingCheckout } from './_test-scenarios';

const {
	savePaymentMethodData,
	acdcAdditionalCardData,
	vaultedPaymentMethodData,
} = vaultingCheckout;

const {
	testSavePaymentMethod,
	testAcdcAdditionalCard,
	testVaultedPaymentMethod,
} = testVaultingCheckout;

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: false,
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
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testData of savePaymentMethodData ) {
	testSavePaymentMethod( testData );
}

for ( const testData of acdcAdditionalCardData ) {
	testAcdcAdditionalCard( testData );
}

for ( const testData of vaultedPaymentMethodData ) {
	testVaultedPaymentMethod( testData );
}
