/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import {
	storeConfigClassic,
	pcpConfigDefault,
	products,
} from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.resetEnvironment();
	await utils.createStorageStates();
} );

test.describe( () => {

	test.beforeAll( async ( { utils, payLater } ) => {
		test.setTimeout( 5 * 60_000 );
		await utils.setupStore();
		await utils.configureStore( storeConfigClassic );
		await utils.configurePcp( pcpConfigDefault );
		await payLater.visit();
		const enableGatewayCheckbox = payLater.enableGatewayCheckbox();
		await expect(
			enableGatewayCheckbox,
			'Assert enable Gateway checkbox is visible.'
		).toBeVisible();
		await enableGatewayCheckbox.check();
		await payLater.removeItemsFromSelectBox( 'Pay Later Button Locations', [
			'Cart',
			'Classic Cart',
			'Express Checkout',
			'Classic Checkout',
			'Single Product',
		] );
		await payLater.saveChanges();
		await utils.updatePcpPlugin();
		// await new Promise( ( resolve ) => setTimeout( resolve, 60_000 ) );
	} );

	test( 'PCP-2047 | Frontend UI - Classic cart - Pay Later - Disabled Pay Later button is NOT displayed @Critical', async ( {
		utils,
		classicCart,
		classicCheckout,
		checkout,
		cart,
		product,
	} ) => {
		await utils.fillVisitorsCart( [ products.simple10 ] );

		await product.visit( products.simple10.slug );
		await product.ppui.assertPayLaterButtonVisibility( false );

		await cart.visit();
		await cart.ppui.assertPayLaterButtonVisibility( false );

		await classicCart.visit();
		await classicCart.ppui.assertPayLaterButtonVisibility( false );

		await checkout.visit();
		await checkout.ppui.assertPayLaterButtonVisibility( false );

		await classicCheckout.visit();
		await classicCheckout.ppui.assertPayLaterButtonVisibility( false );
	} );
} );
