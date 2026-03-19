/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { transactionsOnClassicCheckoutOxxo } from './_test-scenarios';
import {
	oxxo,
	pcpConfigMexico,
	storeConfigMexico,
	taxSettings,
} from '../../resources';
import {
	oxxoClassicCheckoutMexico,
	oxxoClassicCheckoutMexicoExcludingTax,
} from './_test-data/oxxo';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {

	test.beforeAll( async ( { utils } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( {
			...storeConfigMexico,
			classicPages: true,
		} );
		await utils.configurePcp( pcpConfigMexico );
		await utils.pcpPaymentMethodIsEnabled( oxxo.method );
		await utils.updatePcpPlugin();
		// await new Promise( ( resolve ) => setTimeout( resolve, 60_000 ) );
	} );

	transactionsOnClassicCheckoutOxxo( oxxoClassicCheckoutMexico );

	test.describe( 'Excluding Tax', () => {
		test.beforeAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.excluding );
		} );
		transactionsOnClassicCheckoutOxxo( oxxoClassicCheckoutMexicoExcludingTax );

		test.afterAll( async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.including );
		} );
	} );
} );
