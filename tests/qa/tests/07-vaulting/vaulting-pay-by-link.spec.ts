/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { vaultingPayByLink } from './_test-data';
import { testVaultingPayByLink } from './_test-scenarios';

const {
	savePaymentMethodData,
	acdcAdditionalCardData,
	vaultedPaymentMethodData,
} = vaultingPayByLink;

const {
	testSavePaymentMethod,
	testAcdcAdditionalCard,
	testVaultedPaymentMethod,
} = testVaultingPayByLink;

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: false } );
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
