/**
 * Internal dependencies
 */
import { test } from '../../utils';
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

test.beforeAll( async ( { utils, wooCommerceApi } ) => {
	await utils.configureStore( { enableClassicPages: false } );
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
