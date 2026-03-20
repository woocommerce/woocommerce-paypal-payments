/**
 * Internal dependencies
 */
import { test } from '../../utils';
import {
	products,
} from '../../resources';

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
