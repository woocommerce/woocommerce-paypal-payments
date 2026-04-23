/**
 * Internal dependencies
 */
import { test } from '../../utils';
/**
 * External dependencies
 */
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
	test.setTimeout( 2 * 60 * 1000 );
	await utils.configureStore( {
		...storeConfigMexico,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigMexico );
	await utils.pcpPaymentMethodIsEnabled( oxxo.method );
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
