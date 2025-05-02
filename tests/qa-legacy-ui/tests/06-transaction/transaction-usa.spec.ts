/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	pcpConfigUsa,
	storeConfigUsa,
	taxSettings,
	venmo,
} from '../../resources';
import {
	transactionsOnClassicCart,
	transactionsOnClassicCheckout,
	transactionsOnClassicProduct,
} from './_test-scenarios';
import {
	venmoClassicCartUsa,
	venmoClassicCheckoutUsa,
	venmoClassicProductUsa,
	venmoClassicCheckoutUsaExcludingTax,
} from './_test-data/venmo';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( {
		...storeConfigUsa,
		classicPages: true,
	} );
	await utils.configurePcp( pcpConfigUsa );
	await utils.pcpPaymentMethodIsEnabled( venmo.method );
} );

transactionsOnClassicCart( venmoClassicCartUsa );
transactionsOnClassicCheckout( venmoClassicCheckoutUsa );
transactionsOnClassicProduct( venmoClassicProductUsa );
