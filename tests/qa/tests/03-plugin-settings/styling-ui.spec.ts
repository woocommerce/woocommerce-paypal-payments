/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import {
	merchants,
	storeConfigDefault,
	Pcp,
	products,
} from '../../resources';

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

test(
	'PCP-0000 | Settings - Styling - Default UI',
	async ( {
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
	}
);

/**
 * Asserts PayPal buttons are visible on the live site for the given location.
 * Uses standard/default appearance (no label or layout assertions).
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
