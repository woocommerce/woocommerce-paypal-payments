/**
 * Internal dependencies
 */
import { expect, PayPalUI, test } from '../../utils';
import { merchants, storeConfigDefault, Pcp, products } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await utils.resetPcpDb();
	await utils.configurePcp( {
		merchant: merchants.usa,
	} );
} );

test( 'PCP-0000 | Settings - Styling - Default UI', async ( {
	utils,
	pcpStyling,
	product,
	cart,
	classicCart,
	checkout,
	classicCheckout,
}, testInfo ) => {
	const snapshotName = testInfo.title;
	const locations: Pcp.Admin.Styling.Location[] = [
		'Cart',
		'Classic Checkout',
		'Express Checkout',
		'Mini Cart',
		'Product Page',
	];

	await pcpStyling.visit();
	await expect( pcpStyling.configContainer() ).toBeVisible();
	await expect( pcpStyling.locationSelectbox() ).toBeVisible();

	for ( const location of locations ) {
		await pcpStyling.locationSelectbox().selectOption( location );
		await pcpStyling.snapshotStylingConfigurator(
			`${ snapshotName } - ${ location }`
		);
	}

	await utils.fillVisitorsCart( [ products.simple10 ] );

	await product.visit( products.simple10.slug );
	await product.ppui.snapshotClassicPayPalButtons(
		`${ snapshotName } - Frontend - Product`
	);

	await product.minicartContainer().hover();
	await product.ppui.snapshotMinicartPayPalButtons(
		`${ snapshotName } - Frontend - Minicart`
	);

	await cart.visit();
	await cart.ppui.snapshotBlockPayPalButtons(
		`${ snapshotName } - Frontend - Cart`
	);

	await classicCart.visit();
	await classicCart.ppui.snapshotClassicPayPalButtons(
		`${ snapshotName } - Frontend - Classic Cart`
	);

	await checkout.visit();
	await checkout.ppui.snapshotBlockPayPalButtons(
		`${ snapshotName } - Frontend - Checkout`
	);

	await classicCheckout.visit();
	await classicCheckout.paymentOption( 'PayPal' ).click();
	await classicCheckout.ppui.snapshotClassicPayPalButtons(
		`${ snapshotName } - Frontend - Classic checkout`
	);
} );
