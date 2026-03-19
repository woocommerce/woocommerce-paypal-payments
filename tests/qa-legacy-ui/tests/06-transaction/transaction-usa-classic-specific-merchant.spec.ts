/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	payPal,
	pcpConfigUsa,
	storeConfigUsa
} from '../../resources';
import {
	payPalClassicCheckoutSpecificMerchant,
	specificMerchant
} from './_test-data/paypal';
import {
	transactionsOnClassicCheckout
} from './_test-scenarios';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigUsa,
			classicPages: true,
		} );
		await utils.configurePcp( {
			...pcpConfigUsa,
			merchant: specificMerchant,
			standardPayments: {
				disableAlternativePaymentMethods: [ 'Venmo' ],
			}
		} );
		await utils.pcpPaymentMethodIsEnabled( payPal.method );
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCheckout( payPalClassicCheckoutSpecificMerchant );
} );
