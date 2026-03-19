/**
 * Internal dependencies
 */
import {
	pcpConfigUsa,
	standardCardButton,
	storeConfigUsa
} from '../../resources';
import { test } from '../../utils';
import {
	standardCardButtonClassicCheckoutIntentAuthorized
} from './_test-data/standard-card-button';
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
		await utils.pcpPaymentMethodIsEnabled( standardCardButton.method )
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCheckout(
		standardCardButtonClassicCheckoutIntentAuthorized
	);
} );
