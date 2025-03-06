/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault, products } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await utils.resetPcpDb();
	await utils.configurePcp( {
		merchant: merchants.usa,
	} );
} );

test( 'PCP-0000 | Settings - Pay Later Messaging - Default UI', async ( {
	utils,
	ppui,
	pcpPayLaterMessaging,
	shop,
	product,
	cart,
	classicCart,
	checkout,
	classicCheckout,
}, testInfo ) => {
	const snapshotName = testInfo.title;
	await utils.fillVisitorsCart( [ products.simple10 ] );

	await pcpPayLaterMessaging.visit();
	await pcpPayLaterMessaging.waitForLoadingMaskRemoved();
	await pcpPayLaterMessaging.snapshotPlmConfigurator(
		`${ snapshotName } - Initial view`
	);

	await pcpPayLaterMessaging.expandAccordionSection( 'Product page' );
	await pcpPayLaterMessaging.snapshotPlmConfigurator(
		`${ snapshotName } - Product page config`
	);

	await pcpPayLaterMessaging.expandAccordionSection( 'Cart' );
	await pcpPayLaterMessaging.snapshotPlmConfigurator(
		`${ snapshotName } - Cart config`
	);

	await pcpPayLaterMessaging.expandAccordionSection( 'Checkout' );
	await pcpPayLaterMessaging.snapshotPlmConfigurator(
		`${ snapshotName } - Checkout config`
	);

	await pcpPayLaterMessaging.expandAccordionSection( 'Home' );
	await pcpPayLaterMessaging.snapshotPlmConfigurator(
		`${ snapshotName } - Home config`
	);

	await pcpPayLaterMessaging.expandAccordionSection( 'Shop' );
	await pcpPayLaterMessaging.snapshotPlmConfigurator(
		`${ snapshotName } - Shop config`
	);

	await product.visit( products.simple10.slug );
	await expect( product.ppui.payLaterMessageContainer() ).toBeVisible();

	await cart.visit();
	await expect( cart.ppui.payLaterMessageContainer() ).toBeVisible();

	await classicCart.visit();
	await expect( classicCart.ppui.payLaterMessageContainer() ).toBeVisible();

	await checkout.visit();
	await expect( checkout.ppui.payLaterMessageContainer() ).toBeVisible();

	await classicCheckout.visit();
	await expect(
		classicCheckout.ppui.payLaterMessageContainer()
	).toBeVisible();

	await shop.visit();
	await expect( shop.ppui.payLaterMessageContainer() ).not.toBeVisible();

	await ppui.page.goto( '/' ); // home page
	await expect( ppui.payLaterMessageContainer() ).not.toBeVisible();
} );
