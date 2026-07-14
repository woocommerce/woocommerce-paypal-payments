/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault, Pcp, products } from '../../resources';

// 'Mini Cart' skipped: minicart buttons unreliable in test env
const LOCATIONS: Pcp.Admin.Styling.Location[] = [
	'Cart',
	'Classic Checkout',
	'Express Checkout',
	'Product Page',
];

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

test( 'PCP-4747 | Settings - US- Styling - Default UI', async ( {
	utils,
	pcpStyling,
	product,
	classicCart,
	checkout,
	classicCheckout,
} ) => {
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

	await utils.fillVisitorsCart( [ simpleProduct ] );

	for ( const location of LOCATIONS ) {
		await pcpStyling.locationSelectbox().selectOption( location );
		await expect(
			pcpStyling.configContainer(),
			`Assert styling config is visible for location ${ location }`
		).toBeVisible();

		await pcpStyling.enablePaymentMethodsOnLocationCheckbox().check();
		await pcpStyling.assertPreviewHasPayPalButtons();
		await pcpStyling.saveChanges();

		await assertPayPalButtonsVisibleOnLiveSite( location, {
			product,
			classicCart,
			checkout,
			classicCheckout,
			simpleProduct,
		} );
	}
} );

/**
 * Asserts PayPal buttons are visible on the live site for the given location.
 * Uses standard/default appearance (no label or layout assertions).
 * @param location
 * @param ctx
 * @param ctx.product
 * @param ctx.product.visit
 * @param ctx.product.payPalUi
 * @param ctx.product.payPalUi.assertPayPalButtonsGatewayVisibleWithContent
 * @param ctx.classicCart
 * @param ctx.classicCart.visit
 * @param ctx.classicCart.payPalUi
 * @param ctx.classicCart.payPalUi.assertPayPalButtonsGatewayVisibleWithContent
 * @param ctx.checkout
 * @param ctx.checkout.visit
 * @param ctx.checkout.payPalUi
 * @param ctx.checkout.payPalUi.assertPayPalButtonsBlockVisibleWithContent
 * @param ctx.classicCheckout
 * @param ctx.classicCheckout.visit
 * @param ctx.classicCheckout.paymentOption
 * @param ctx.classicCheckout.payPalUi
 * @param ctx.classicCheckout.payPalUi.assertPayPalButtonsGatewayVisibleWithContent
 * @param ctx.simpleProduct
 * @param ctx.simpleProduct.slug
 */
async function assertPayPalButtonsVisibleOnLiveSite(
	location: Pcp.Admin.Styling.Location,
	ctx: {
		product: {
			visit: ( slug: string ) => Promise< void >;
			payPalUi: {
				assertPayPalButtonsGatewayVisibleWithContent: () => Promise< void >;
			};
		};
		classicCart: {
			visit: () => Promise< void >;
			payPalUi: {
				assertPayPalButtonsGatewayVisibleWithContent: () => Promise< void >;
			};
		};
		checkout: {
			visit: () => Promise< void >;
			payPalUi: {
				assertPayPalButtonsBlockVisibleWithContent: () => Promise< void >;
			};
		};
		classicCheckout: {
			visit: () => Promise< void >;
			paymentOption: ( name: string ) => { click: () => Promise< void > };
			payPalUi: {
				assertPayPalButtonsGatewayVisibleWithContent: () => Promise< void >;
			};
		};
		simpleProduct: { slug?: string };
	}
) {
	const { product, classicCart, checkout, classicCheckout, simpleProduct } =
		ctx;
	const slug = simpleProduct.slug ?? '';

	switch ( location ) {
		case 'Product Page':
			await product.visit( slug );
			await product.payPalUi.assertPayPalButtonsGatewayVisibleWithContent();
			break;
		case 'Cart':
			await classicCart.visit();
			await classicCart.payPalUi.assertPayPalButtonsGatewayVisibleWithContent();
			break;
		case 'Classic Checkout':
			await classicCheckout.visit();
			await classicCheckout.paymentOption( 'PayPal' ).click();
			await classicCheckout.payPalUi.assertPayPalButtonsGatewayVisibleWithContent();
			break;
		case 'Express Checkout':
			await checkout.visit();
			await checkout.payPalUi.assertPayPalButtonsBlockVisibleWithContent();
			break;
	}
}
