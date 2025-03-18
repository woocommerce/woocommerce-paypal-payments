/**
 * Internal dependencies
 */
import { expect, PayPalUI, test } from '../../utils';
import { merchants, storeConfigDefault, Pcp, products } from '../../resources';

/**
 * - Asserts PayPal buttons classic container is visible.
 * - Compares actual PayPal buttons container screenshot to expected.
 *
 * @param ppui
 * @param snapshotName
 */
const snapshotPayPalButtonsClassicContainer = async (
	ppui: PayPalUI,
	snapshotName: string
) => {
	await expect( ppui.payPalButtonsClassicContainer() ).toBeVisible();
	await ppui.page.waitForTimeout( 500 );
	expect(
		await ppui
			.payPalButtonsClassicContainer()
			.screenshot( { animations: 'disabled' } )
	).toMatchSnapshot( `${ snapshotName }.png` );
};

/**
 * - Asserts Minicart PayPal buttons container is visible.
 * - Compares actual PayPal buttons container screenshot to expected.
 *
 * @param ppui
 * @param snapshotName
 */
const snapshotPayPalButtonsMinicartContainer = async (
	ppui: PayPalUI,
	snapshotName: string
) => {
	await expect( ppui.miniCartButtonContainer() ).toBeVisible();
	await ppui.page.waitForTimeout( 500 );
	expect(
		await ppui
			.miniCartButtonContainer()
			.screenshot( { animations: 'disabled' } )
	).toMatchSnapshot( `${ snapshotName }.png` );
};

/**
 * - Asserts PayPal buttons block container is visible.
 * - Compares actual PayPal buttons container screenshot to expected.
 *
 * @param ppui
 * @param snapshotName
 */
const snapshotPayPalButtonsBlockContainer = async (
	ppui: PayPalUI,
	snapshotName: string
) => {
	await expect( ppui.payPalButtonsBlockContainer() ).toBeVisible();
	await ppui.page.waitForTimeout( 500 );
	expect(
		await ppui
			.payPalButtonsBlockContainer()
			.screenshot( { animations: 'disabled' } )
	).toMatchSnapshot( `${ snapshotName }.png` );
};

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
	await snapshotPayPalButtonsClassicContainer(
		product.ppui,
		`${ snapshotName } - Frontend - Product`
	);

	await product.minicartContainer().hover();
	await snapshotPayPalButtonsMinicartContainer(
		product.ppui,
		`${ snapshotName } - Frontend - Minicart`
	);

	await cart.visit();
	await snapshotPayPalButtonsBlockContainer(
		cart.ppui,
		`${ snapshotName } - Frontend - Cart`
	);

	await classicCart.visit();
	await snapshotPayPalButtonsClassicContainer(
		classicCart.ppui,
		`${ snapshotName } - Frontend - Classic Cart`
	);

	await checkout.visit();
	await snapshotPayPalButtonsBlockContainer(
		checkout.ppui,
		`${ snapshotName } - Frontend - Checkout`
	);

	await classicCheckout.visit();
	await classicCheckout.paymentOption( 'PayPal' ).click();
	await snapshotPayPalButtonsClassicContainer(
		classicCheckout.ppui,
		`${ snapshotName } - Frontend - Classic checkout`
	);
} );
