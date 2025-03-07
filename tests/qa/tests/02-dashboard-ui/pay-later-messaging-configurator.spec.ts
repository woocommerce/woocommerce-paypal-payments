/**
 * Internal dependencies
 */
import {
	expect,
	getTestResultsFromFile,
	PayPalUI,
	PcpPayLaterMessaging,
	saveTestResultsToFile,
	test,
} from '../../utils';
import { merchants, storeConfigDefault, products, Pcp } from '../../resources';
import { payLaterMessagingData } from './_test-data/pay-later-messaging.data';

const TEST_RESULTS_FILE = 'plm-test-results.json';

/**
 * Adds settings values to test name.
 *
 * @param  baseName
 * @param  settings
 * @param  settings.logoType
 * @param  settings.textColor
 * @param  settings.logoPosition
 * @param  settings.textSize
 * @param  settings.bannerColor
 * @param  settings.bannerSize
 * @return { string } Example: (PCP-000) PLM - Product page - Full Logo - Black / Gray logo - Left - Small
 */
const buildTestName = (
	baseName: string,
	settings: {
		logoType?: Pcp.Admin.Plm.LogoType;
		textColor?: Pcp.Admin.Plm.TextColor;
		logoPosition?: Pcp.Admin.Plm.LogoPosition;
		textSize?: Pcp.Admin.Plm.TextSize;
		bannerColor?: Pcp.Admin.Plm.BannerColor;
		bannerSize?: Pcp.Admin.Plm.BannerSize;
	}
): string => {
	const {
		logoType,
		textColor,
		logoPosition,
		textSize,
		bannerColor,
		bannerSize,
	} = settings;
	let snapshotName = baseName;

	if ( logoType ) {
		snapshotName += ` - ${ logoType }`;
	}

	if ( textColor ) {
		snapshotName += ` - ${ textColor }`;
	}

	if ( logoPosition && logoType === 'Full Logo' ) {
		snapshotName += ` - ${ logoPosition }`;
	}

	if ( textSize ) {
		snapshotName += ` - ${ textSize }`;
	}

	if ( bannerColor ) {
		snapshotName += ` - ${ bannerColor }`;
	}

	if ( bannerSize ) {
		snapshotName += ` - ${ bannerSize }`;
	}

	return snapshotName;
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
 * @param ppui
 * @param snapshotName
 */
const snapshotPlmContainer = async ( ppui: PayPalUI, snapshotName: string ) => {
	await expect( ppui.payLaterMessageContainer() ).toBeVisible();
	await ppui.page.waitForTimeout( 500 );
	expect(
		await ppui
			.payLaterMessageContainer()
			.screenshot( { animations: 'disabled' } )
	).toMatchSnapshot( snapshotName );
};

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await utils.resetPcpDb();
	await utils.configurePcp( {
		merchant: merchants.usa,
	} );
} );

test.describe( () => {
	const productPlm =
		payLaterMessagingData.checkoutLocationSettings[ 'Product page' ];

	for ( const settings of productPlm.settings ) {
		test(
			buildTestName( `(PCP-0001) PLM - Product page`, settings ),
			async ( { pcpPayLaterMessaging, product, ppui }, testInfo ) => {
				const snapshotName = testInfo.title;
				const { location } = productPlm;
				await pcpPayLaterMessaging.visit();
				await pcpPayLaterMessaging.enableMessagingForLocation(
					location
				);
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
					`${ snapshotName } - After save.png`
				);

				await product.visit( products.simple10.slug );
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend.png`
				);
			}
		);
	}

	const cartPlm = payLaterMessagingData.checkoutLocationSettings.Cart;

	for ( const settings of cartPlm.settings ) {
		test(
			buildTestName( `(PCP-0002) PLM - Cart`, settings ),
			async (
				{ utils, pcpPayLaterMessaging, cart, classicCart, ppui },
				testInfo
			) => {
				const snapshotName = testInfo.title;
				const { location } = cartPlm;
				await utils.fillVisitorsCart( [ products.simple10 ] );

				await pcpPayLaterMessaging.visit();
				await pcpPayLaterMessaging.enableMessagingForLocation(
					location
				);
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
					`${ snapshotName } - After save.png`
				);
				// Block cart
				await cart.visit();
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend - Block cart.png`
				);
				// Classic cart
				await classicCart.visit();
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend - Classic cart.png`
				);
			}
		);
	}

	const checkoutPlm = payLaterMessagingData.checkoutLocationSettings.Checkout;

	for ( const settings of checkoutPlm.settings ) {
		test(
			buildTestName( `(PCP-0003) PLM - Checkout`, settings ),
			async (
				{
					utils,
					pcpPayLaterMessaging,
					checkout,
					classicCheckout,
					ppui,
				},
				testInfo
			) => {
				const snapshotName = testInfo.title;
				const { location } = checkoutPlm;
				await utils.fillVisitorsCart( [ products.simple10 ] );

				await pcpPayLaterMessaging.visit();
				await pcpPayLaterMessaging.enableMessagingForLocation(
					location
				);
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
					`${ snapshotName } - After save.png`
				);
				// Block checkout
				await checkout.visit();
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend - Block checkout.png`
				);
				// Classic checkout
				await classicCheckout.visit();
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend - Classic checkout.png`
				);
			}
		);
	}

	const homePlm = payLaterMessagingData.bannerLocationSettings.Home;

	for ( const settings of homePlm.settings ) {
		test(
			buildTestName( `(PCP-0004) PLM - Home`, settings ),
			async (
				{
					pcpPayLaterMessaging,
					ppui, // PayPal UI
				},
				testInfo
			) => {
				test.setTimeout( 10 * 60 * 1000 );
				const snapshotName = testInfo.title;
				const { location } = homePlm;
				await pcpPayLaterMessaging.visit();
				await pcpPayLaterMessaging.enableMessagingForLocation(
					location
				);
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
					`${ snapshotName } - After save.png`
				);

				await ppui.page.goto( '/' );
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend.png`
				);
			}
		);
	}

	const shopPlm = payLaterMessagingData.bannerLocationSettings.Shop;

	for ( const settings of shopPlm.settings ) {
		test(
			buildTestName( `(PCP-0005) PLM - Shop`, settings ),
			async ( { pcpPayLaterMessaging, shop, ppui }, testInfo ) => {
				const snapshotName = testInfo.title;
				const { location } = shopPlm;
				await pcpPayLaterMessaging.visit();
				await pcpPayLaterMessaging.enableMessagingForLocation(
					location
				);
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
					`${ snapshotName } - After save.png`
				);

				await shop.visit();
				await snapshotPlmContainer(
					ppui,
					`${ snapshotName } - Frontend.png`
				);
			}
		);
	}

	test.afterEach( async ( {}, testInfo ) => {
		saveTestResultsToFile(
			testInfo.title,
			testInfo.status,
			TEST_RESULTS_FILE
		);
	} );
} );

test( 'PCP-0001 | Pay Later Messaging - Customize on Product page', async () => {
	getTestResultsFromFile( 'PCP-0001', TEST_RESULTS_FILE );
} );

test( 'PCP-0002 | Pay Later Messaging - Customize on Cart (block and classic)', async () => {
	getTestResultsFromFile( 'PCP-0002', TEST_RESULTS_FILE );
} );

test( 'PCP-0003 | Pay Later Messaging - Customize on Checkout (block and classic', async () => {
	getTestResultsFromFile( 'PCP-0003', TEST_RESULTS_FILE );
} );

test( 'PCP-0004 | Pay Later Messaging - Customize on Home page', async () => {
	getTestResultsFromFile( 'PCP-0004', TEST_RESULTS_FILE );
} );

test( 'PCP-0005 | Pay Later Messaging - Customize on Shop page', async () => {
	getTestResultsFromFile( 'PCP-0005', TEST_RESULTS_FILE );
} );
