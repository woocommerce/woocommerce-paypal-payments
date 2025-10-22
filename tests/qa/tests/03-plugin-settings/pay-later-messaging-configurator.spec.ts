/**
 * Internal dependencies
 */
import {
	expect,
	getTestResultsFromFile,
	PayPalUi,
	PayPalUiClassic,
	PcpPayLaterMessaging,
	saveTestResultsToFile,
	test,
} from '../../utils';
import { merchants, storeConfigDefault, products, Pcp } from '../../resources';
import { payLaterMessagingData } from './_test-data';

const TEST_RESULTS_FILE = 'plm-test-results.json';

/**
 * Adds settings values to test name.
 *
 * @param  settings
 * @param  settings.logoType
 * @param  settings.textColor
 * @param  settings.logoPosition
 * @param  settings.textSize
 * @param  settings.bannerColor
 * @param  settings.bannerSize
 * @return { string } Example: (PCP-000) PLM - Product page - Full Logo - Black / Gray logo - Left - Small
 */
const summarizeSettings = ( settings: {
	logoType?: Pcp.Admin.Plm.LogoType;
	textColor?: Pcp.Admin.Plm.TextColor;
	logoPosition?: Pcp.Admin.Plm.LogoPosition;
	textSize?: Pcp.Admin.Plm.TextSize;
	bannerColor?: Pcp.Admin.Plm.BannerColor;
	bannerSize?: Pcp.Admin.Plm.BannerSize;
} ): string => {
	const {
		logoType,
		textColor,
		logoPosition,
		textSize,
		bannerColor,
		bannerSize,
	} = settings;
	let title = '';

	if ( logoType ) {
		title += ` - ${ logoType }`;
	}

	if ( textColor ) {
		title += ` - ${ textColor }`;
	}

	if ( logoPosition && logoType === 'Full Logo' ) {
		title += ` - ${ logoPosition }`;
	}

	if ( textSize ) {
		title += ` - ${ textSize }`;
	}

	if ( bannerColor ) {
		title += ` - ${ bannerColor }`;
	}

	if ( bannerSize ) {
		title += ` - ${ bannerSize }`;
	}

	return title;
};

/**
 * Takes percy snapshot for each preview variant (text, desktop, mobile + light/dark):
 * - Switches previews
 * - Switches Light/Dark modes
 * - Takes snapshot
 *
 * @param pcpPayLaterMessaging
 * @param snapshotName
 */
const takePreviewSnapshots = async (
	pcpPayLaterMessaging: PcpPayLaterMessaging,
	snapshotName: string
) => {
	for ( const layout of [ 'Text', 'Desktop', 'Mobile' ] ) {
		if ( layout === 'Text' ) {
			await pcpPayLaterMessaging.previewTextButton().click();
		}
		if ( layout === 'Desktop' ) {
			await pcpPayLaterMessaging.previewDesktopButton().click();
		}
		if ( layout === 'Mobile' ) {
			await pcpPayLaterMessaging.previewMobileButton().click();
		}
		for ( const togglePosition of [ 'Dark', 'Light' ] ) {
			const isDark = togglePosition === 'Dark';
			const previewSnapshotName = `${ snapshotName } - ${ layout } - Preview - ${ togglePosition }`;
			await pcpPayLaterMessaging.setDarkModeToggleState( isDark );
			await pcpPayLaterMessaging.snapshotPlmConfigurator(
				previewSnapshotName
			);
		}
		break;
	}
};

/**
 * - Asserts Pay Later Messaging container is visible.
 * - Compares actual PLM container screenshot to expected.
 *
 * @param payPalUi
 * @param snapshotName
 */
const snapshotPlmContainer = async (
	payPalUi: PayPalUi | PayPalUiClassic,
	snapshotName: string
) => {
	await expect( payPalUi.payLaterMessageContainer() ).toBeVisible();
	await payPalUi.page.waitForTimeout( 500 );
	expect(
		await payPalUi
			.payLaterMessageContainer()
			.screenshot( { animations: 'disabled' } )
	).toMatchSnapshot( `${ snapshotName }.png` );
};

test.describe( 'Subtests', () => {
	test.beforeAll( async ( { utils, pcpApi } ) => {
		await utils.configureStore( storeConfigDefault );
		await utils.installAndActivatePcp();
		await pcpApi.resetDb();
		await pcpApi.connectMerchant(
			merchants.usa.client_id,
			merchants.usa.client_secret
		);
	} );

	const productPlm =
		payLaterMessagingData.checkoutLocationSettings[ 'Product page' ];

	for ( const settings of productPlm.settings ) {
		test.fixme( `(PCP-0001) PLM - Product page${ summarizeSettings(
			settings
		) }`, async ( { pcpPayLaterMessaging, product }, testInfo ) => {
			const snapshotName = testInfo.title;
			const { location } = productPlm;
			await pcpPayLaterMessaging.visit();
			await pcpPayLaterMessaging.enableMessagingForLocation( location );
			await pcpPayLaterMessaging.updateLocationSettings(
				location,
				settings
			);
			// await takePreviewSnapshots( pcpPayLaterMessaging, snapshotName ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.saveChanges();
			await pcpPayLaterMessaging.page.reload();
			await pcpPayLaterMessaging.expandAccordionSection( location );
			// await pcpPayLaterMessaging.assertLocationSettings( settings ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.snapshotPlmConfigurator(
				`${ snapshotName } - After save`
			);

			await product.visit( products.simple10.slug );
			await snapshotPlmContainer(
				product.payPalUi,
				`${ snapshotName } - Frontend`
			);
		} );
	}

	const cartPlm = payLaterMessagingData.checkoutLocationSettings.Cart;

	for ( const settings of cartPlm.settings ) {
		test.fixme( `(PCP-0002) PLM - Cart${ summarizeSettings(
			settings
		) }`, async ( {
			utils,
			pcpPayLaterMessaging,
			cart,
			classicCart,
		}, testInfo ) => {
			const snapshotName = testInfo.title;
			const { location } = cartPlm;
			await utils.fillVisitorsCart( [ products.simple10 ] );

			await pcpPayLaterMessaging.visit();
			await pcpPayLaterMessaging.enableMessagingForLocation( location );
			await pcpPayLaterMessaging.updateLocationSettings(
				location,
				settings
			);
			// await takePreviewSnapshots( pcpPayLaterMessaging, snapshotName ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.saveChanges();
			await pcpPayLaterMessaging.page.reload();
			await pcpPayLaterMessaging.expandAccordionSection( location );
			// await pcpPayLaterMessaging.assertLocationSettings( settings ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.snapshotPlmConfigurator(
				`${ snapshotName } - After save`
			);
			// Block cart
			await cart.visit();
			await snapshotPlmContainer(
				cart.payPalUi,
				`${ snapshotName } - Frontend - Block cart`
			);
			// Classic cart
			await classicCart.visit();
			await snapshotPlmContainer(
				classicCart.payPalUi,
				`${ snapshotName } - Frontend - Classic cart`
			);
		} );
	}

	const checkoutPlm = payLaterMessagingData.checkoutLocationSettings.Checkout;

	for ( const settings of checkoutPlm.settings ) {
		test.fixme( `(PCP-0003) PLM - Checkout${ summarizeSettings(
			settings
		) }`, async ( {
			utils,
			pcpPayLaterMessaging,
			checkout,
			classicCheckout,
		}, testInfo ) => {
			const snapshotName = testInfo.title;
			const { location } = checkoutPlm;
			await utils.fillVisitorsCart( [ products.simple10 ] );

			await pcpPayLaterMessaging.visit();
			await pcpPayLaterMessaging.enableMessagingForLocation( location );
			await pcpPayLaterMessaging.updateLocationSettings(
				location,
				settings
			);
			// await takePreviewSnapshots( pcpPayLaterMessaging, snapshotName ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.saveChanges();
			await pcpPayLaterMessaging.page.reload();
			await pcpPayLaterMessaging.expandAccordionSection( location );
			// await pcpPayLaterMessaging.assertLocationSettings( settings ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.snapshotPlmConfigurator(
				`${ snapshotName } - After save`
			);
			// Block checkout
			await checkout.visit();
			await snapshotPlmContainer(
				checkout.payPalUi,
				`${ snapshotName } - Frontend - Block checkout`
			);
			// Classic checkout
			await classicCheckout.visit();
			await snapshotPlmContainer(
				classicCheckout.payPalUi,
				`${ snapshotName } - Frontend - Classic checkout`
			);
		} );
	}

	const homePlm = payLaterMessagingData.bannerLocationSettings.Home;

	for ( const settings of homePlm.settings ) {
		test.fixme( `(PCP-0004) PLM - Home${ summarizeSettings(
			settings
		) }`, async ( { pcpPayLaterMessaging, payPalUiClassic }, testInfo ) => {
			test.setTimeout( 10 * 60 * 1000 );
			const snapshotName = testInfo.title;
			const { location } = homePlm;
			await pcpPayLaterMessaging.visit();
			await pcpPayLaterMessaging.enableMessagingForLocation( location );
			await pcpPayLaterMessaging.updateLocationSettings(
				location,
				settings
			);
			// await takePreviewSnapshots( pcpPayLaterMessaging, snapshotName ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.saveChanges();
			await pcpPayLaterMessaging.page.reload();
			await pcpPayLaterMessaging.expandAccordionSection( location );
			// await pcpPayLaterMessaging.assertLocationSettings( settings ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.snapshotPlmConfigurator(
				`${ snapshotName } - After save`
			);

			await payPalUiClassic.page.goto( '/' );
			await snapshotPlmContainer(
				payPalUiClassic,
				`${ snapshotName } - Frontend`
			);
		} );
	}

	const shopPlm = payLaterMessagingData.bannerLocationSettings.Shop;

	for ( const settings of shopPlm.settings ) {
		test.fixme( `(PCP-0005) PLM - Shop${ summarizeSettings(
			settings
		) }`, async ( { pcpPayLaterMessaging, shop }, testInfo ) => {
			const snapshotName = testInfo.title;
			const { location } = shopPlm;
			await pcpPayLaterMessaging.visit();
			await pcpPayLaterMessaging.enableMessagingForLocation( location );
			await pcpPayLaterMessaging.updateLocationSettings(
				location,
				settings
			);
			// await takePreviewSnapshots( pcpPayLaterMessaging, snapshotName ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.saveChanges();
			await pcpPayLaterMessaging.page.reload();
			await pcpPayLaterMessaging.expandAccordionSection( location );
			// await pcpPayLaterMessaging.assertLocationSettings( settings ); // TODO: uncomment when fixed
			await pcpPayLaterMessaging.snapshotPlmConfigurator(
				`${ snapshotName } - After save`
			);

			await shop.visit();
			await snapshotPlmContainer(
				shop.payPalUi,
				`${ snapshotName } - Frontend`
			);
		} );
	}

	test.afterEach( async ( {}, testInfo ) => {
		saveTestResultsToFile(
			testInfo.title,
			testInfo.status,
			TEST_RESULTS_FILE
		);
	} );
} );

test.fixme( 'PCP-0001 | Pay Later Messaging - Customize on Product page', async () => {
	getTestResultsFromFile( 'PCP-0001', TEST_RESULTS_FILE );
} );

test.fixme( 'PCP-0002 | Pay Later Messaging - Customize on Cart (block and classic)', async () => {
	getTestResultsFromFile( 'PCP-0002', TEST_RESULTS_FILE );
} );

test.fixme( 'PCP-0003 | Pay Later Messaging - Customize on Checkout (block and classic)', async () => {
	getTestResultsFromFile( 'PCP-0003', TEST_RESULTS_FILE );
} );

test.fixme( 'PCP-0004 | Pay Later Messaging - Customize on Home page', async () => {
	getTestResultsFromFile( 'PCP-0004', TEST_RESULTS_FILE );
} );

test.fixme( 'PCP-0005 | Pay Later Messaging - Customize on Shop page', async () => {
	getTestResultsFromFile( 'PCP-0005', TEST_RESULTS_FILE );
} );
