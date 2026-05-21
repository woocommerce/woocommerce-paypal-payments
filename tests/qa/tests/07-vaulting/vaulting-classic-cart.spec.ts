/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { vaultingClassicCart } from './_test-data';
import { testVaultingClassicCart } from './_test-scenarios';

const { savePaymentMethodData, vaultedPaymentMethodData } = vaultingClassicCart;

const { testSavePaymentMethod, testVaultedPaymentMethod } =
	testVaultingClassicCart;

test.beforeAll( async ( { utils, wooCommerceApi } ) => {
	await utils.configureStore( { enableClassicPages: true } );
	await wooCommerceApi.deleteAllOrders();
} );

for ( const testOrder of savePaymentMethodData ) {
	testSavePaymentMethod( testOrder );
}

for ( const testOrder of vaultedPaymentMethodData ) {
	testVaultedPaymentMethod( testOrder );
}
