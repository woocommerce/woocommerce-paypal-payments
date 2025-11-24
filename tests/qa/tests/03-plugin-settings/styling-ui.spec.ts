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
		},
		testInfo
	) => {
		const snapshotName = testInfo.title;
		const locations: Pcp.Admin.Styling.Location[] = [
			'Cart',
			'Classic Checkout',
			'Express Checkout',
			'Mini Cart',
			'Product Page',
		];
		const simpleProduct = products.simple100;

		await pcpStyling.visit();
		await expect( pcpStyling.configContainer() ).toBeVisible();
		await expect( pcpStyling.locationSelectbox() ).toBeVisible();

		for ( const location of locations ) {
			await pcpStyling.locationSelectbox().selectOption( location );
			await pcpStyling.snapshotStylingConfigurator(
				`${ snapshotName } - ${ location }`
			);
		}

		await utils.fillVisitorsCart( [ simpleProduct ] );

		await product.visit( simpleProduct.slug );
		await product.payPalUi.snapshotClassicPayPalButtons(
			`${ snapshotName } - Frontend - Product`
		);

		await product.minicartContainer().hover();
		await product.payPalUi.snapshotMinicartPayPalButtons(
			`${ snapshotName } - Frontend - Minicart`
		);

		await cart.visit();
		await cart.payPalUi.snapshotBlockPayPalButtons(
			`${ snapshotName } - Frontend - Cart`
		);

		await classicCart.visit();
		await classicCart.payPalUi.snapshotClassicPayPalButtons(
			`${ snapshotName } - Frontend - Classic Cart`
		);

		await checkout.visit();
		await checkout.payPalUi.snapshotBlockPayPalButtons(
			`${ snapshotName } - Frontend - Checkout`
		);

		await classicCheckout.visit();
		await classicCheckout.paymentOption( 'PayPal' ).click();
		await classicCheckout.payPalUi.snapshotClassicPayPalButtons(
			`${ snapshotName } - Frontend - Classic checkout`
		);
	}
);
