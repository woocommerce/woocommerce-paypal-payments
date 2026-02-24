/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigDefault,
	products,
	pcpConfigDefault,
} from '../../resources';

test.describe( 'Pay Later', async () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( {
			...storeConfigDefault,
			wpDebugging: true,
		} );
		await utils.configurePcp( pcpConfigDefault );
	} );

	test( 'PCP-1092 | Pay Later - Enable Pay Later @Critical', async ( {
		payLater,
	} ) => {
		await payLater.visit();
		await payLater.enableGatewayCheckbox().uncheck();
		await payLater.saveChanges();
		await expect( payLater.enableGatewayCheckbox() ).not.toBeChecked();

		await payLater.enableGatewayCheckbox().check();
		await payLater.saveChanges();
		await expect( payLater.enableGatewayCheckbox() ).toBeChecked();
	} );

	test.describe( 'Display on frontend', () => {
		test.beforeAll( async ( { payLater, standardPayments } ) => {
			await payLater.setup( { enableGateway: true } );
			await standardPayments.visit();
			await standardPayments.disableAlternativePaymentMethods( [
				'Venmo',
			] );
			await standardPayments.saveChanges();
		} );

		test.beforeEach( async ( { utils } ) => {
			await utils.fillVisitorsCart( [ products.simple10 ] );
		} );

		test( 'PCP-1094 | Pay Later - Product - Enabled Pay Later button is displayed @Critical', async ( {
			product,
		} ) => {
			await product.visit( products.simple10.slug );
			await product.ppui.assertPayPalButtonVisibility( true );
			await product.ppui.assertPayLaterButtonVisibility( true );
		} );

		test( 'PCP-1095 | Pay Later - Classic cart - Enabled Pay Later button is displayed @Critical', async ( {
			classicCart,
		} ) => {
			await classicCart.visit();
			await classicCart.ppui.assertPayPalButtonVisibility( true );
			await classicCart.ppui.assertPayLaterButtonVisibility( true );
		} );

		test( 'PCP-1093 | Pay Later - Classic checkout - Enabled Pay Later button is displayed @Critical', async ( {
			classicCheckout,
		} ) => {
			await classicCheckout.visit();
			await classicCheckout.ppui.assertPayPalButtonVisibility( true );
			await classicCheckout.ppui.assertPayLaterButtonVisibility( true );
		} );
	} );
} );
