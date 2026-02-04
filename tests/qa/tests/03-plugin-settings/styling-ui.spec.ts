/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault, Pcp, products } from '../../resources';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

	test.fixme(
		'PCP-0000 | Settings - Styling - Default UI',
		async (
			{
				utils,
				pcpStyling,
				product,
				cart,
				classicCart,
				checkout,
				classicCheckout,
			}
		) => {
		const locations: Pcp.Admin.Styling.Location[] = [
			'Cart',
			'Classic Checkout',
			'Express Checkout',
			'Mini Cart',
			'Product Page',
		];
		const simpleProduct = products.simple100;

		await pcpStyling.visit();
		await expect(
			pcpStyling.configContainer(),
			'Assert styling config container is visible'
		).toBeVisible();
		await expect(
			pcpStyling.locationSelectbox(),
			'Assert styling location selectbox is visible'
		).toBeVisible();

		for ( const location of locations ) {
			await pcpStyling.locationSelectbox().selectOption( location );
			await expect(
				pcpStyling.configContainer(),
				`Assert styling config is visible for location ${ location }`
			).toBeVisible();
		}

		await utils.fillVisitorsCart( [ simpleProduct ] );

		await product.visit( simpleProduct.slug );
		await expect(
			product.payPalUi.payPalButtonsBlockContainer(),
			'Assert PayPal buttons are visible on product page'
		).toBeVisible();

		await product.minicartContainer().hover();
		await expect(
			product.payPalUi.miniCartButtonContainer(),
			'Assert PayPal minicart buttons are visible'
		).toBeVisible();

		await cart.visit();
		await expect(
			cart.payPalUi.payPalButtonsBlockContainer(),
			'Assert PayPal buttons are visible on cart'
		).toBeVisible();

		await classicCart.visit();
		await expect(
			classicCart.payPalUi.payPalButtonsBlockContainer(),
			'Assert PayPal buttons are visible on classic cart'
		).toBeVisible();

		await checkout.visit();
		await expect(
			checkout.payPalUi.payPalButtonsBlockContainer(),
			'Assert PayPal buttons are visible on checkout'
		).toBeVisible();

		await classicCheckout.visit();
		await classicCheckout.paymentOption( 'PayPal' ).click();
		await expect(
			classicCheckout.payPalUi.payPalButtonsBlockContainer(),
			'Assert PayPal buttons are visible on classic checkout'
		).toBeVisible();
	}
);
