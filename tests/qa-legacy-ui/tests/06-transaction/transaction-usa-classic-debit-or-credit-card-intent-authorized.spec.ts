/**
 * Internal dependencies
 */
import {
	debitOrCreditCard,
	pcpConfigUsa,
	storeConfigUsa
} from '../../resources';
import { test } from '../../utils';
import {
	debitOrCreditCardClassicCheckoutIntentAuthorized
} from './_test-data/debit-or-credit-card';
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
			standardPayments: {
				disableAlternativePaymentMethods: [ 'Venmo' ],
				intent: 'Authorize',
			}
		} );
		await utils.pcpPaymentMethodIsEnabled( debitOrCreditCard.method );
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCheckout(
		debitOrCreditCardClassicCheckoutIntentAuthorized
	);
} );
