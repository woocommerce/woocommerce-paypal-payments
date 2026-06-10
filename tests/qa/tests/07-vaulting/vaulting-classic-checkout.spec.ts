/**
 * Internal dependencies
 */
import { test } from '../../utils';
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

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
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
