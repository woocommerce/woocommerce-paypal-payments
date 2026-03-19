/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	pcpConfigUsa,
	standardCardButton,
	storeConfigUsa,
	taxSettings
} from '../../resources';
import {
	standardCardButtonClassicCheckout,
	standardCardButtonClassicCheckoutExcludingTax
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
        		...pcpConfigUsa.standardPayments,
				disableAlternativePaymentMethods: [ 'Venmo' ],
			}
		} );
		await utils.pcpPaymentMethodIsEnabled( standardCardButton.method )
		await utils.updatePcpPlugin();
	} );

	transactionsOnClassicCheckout( standardCardButtonClassicCheckout );

	test.describe( 'Excluding Tax', () => {
		test.beforeAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.excluding );
		} );

		transactionsOnClassicCheckout(
			standardCardButtonClassicCheckoutExcludingTax
		);

		test.afterAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.including );
		} );
	} );
} );
