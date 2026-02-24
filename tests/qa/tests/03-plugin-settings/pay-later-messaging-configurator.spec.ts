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
 * Asserts Pay Later Messaging container is visible on the frontend.
 *
 * @param payPalUi
 * @param assertContext - description for the Assert message
 */
const assertPlmContainerVisible = async (
	payPalUi: PayPalUi | PayPalUiClassic,
	assertContext: string
) => {
	await expect(
		payPalUi.payLaterMessageContainer(),
		`Assert Pay Later Messaging container is visible - ${ assertContext }`
	).toBeVisible();
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
		test.fixme(
			`(PCP-0001) PLM - Product page${ summarizeSettings( settings ) }`,
			async ( { pcpPayLaterMessaging, product }, testInfo ) => {
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
				await expect(
					pcpPayLaterMessaging.configContainer(),
					`Assert PLM configurator is visible after save for Product page`
				).toBeVisible();

				await product.visit( products.simple100.slug );
				await assertPlmContainerVisible(
					product.payPalUi,
					'Product page frontend'
				);
			}
		);
	}

	const cartPlm = payLaterMessagingData.checkoutLocationSettings.Cart;

	for ( const settings of cartPlm.settings ) {
		test.fixme(
			`(PCP-0002) PLM - Cart${ summarizeSettings( settings ) }`,
			async (
				{ utils, pcpPayLaterMessaging, cart, classicCart },
				testInfo
			) => {
				const snapshotName = testInfo.title;
				const { location } = cartPlm;
				await utils.fillVisitorsCart( [ products.simple100 ] );

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
				await expect(
					pcpPayLaterMessaging.configContainer(),
					'Assert PLM configurator is visible after save for Cart'
				).toBeVisible();
				// Block cart
				await cart.visit();
				await assertPlmContainerVisible(
					cart.payPalUi,
					'Block cart frontend'
				);
				// Classic cart
				await classicCart.visit();
				await assertPlmContainerVisible(
					classicCart.payPalUi,
					'Classic cart frontend'
				);
			}
		);
	}

	const checkoutPlm = payLaterMessagingData.checkoutLocationSettings.Checkout;

	for ( const settings of checkoutPlm.settings ) {
		test.fixme(
			`(PCP-0003) PLM - Checkout${ summarizeSettings( settings ) }`,
			async (
				{ utils, pcpPayLaterMessaging, checkout, classicCheckout },
				testInfo
			) => {
				const snapshotName = testInfo.title;
				const { location } = checkoutPlm;
				await utils.fillVisitorsCart( [ products.simple100 ] );

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
				await expect(
					pcpPayLaterMessaging.configContainer(),
					'Assert PLM configurator is visible after save for Checkout'
				).toBeVisible();
				// Block checkout
				await checkout.visit();
				await assertPlmContainerVisible(
					checkout.payPalUi,
					'Block checkout frontend'
				);
				// Classic checkout
				await classicCheckout.visit();
				await assertPlmContainerVisible(
					classicCheckout.payPalUi,
					'Classic checkout frontend'
				);
			}
		);
	}

	const homePlm = payLaterMessagingData.bannerLocationSettings.Home;

	for ( const settings of homePlm.settings ) {
		test.fixme(
			`(PCP-0004) PLM - Home${ summarizeSettings( settings ) }`,
			async ( { pcpPayLaterMessaging, payPalUiClassic }, testInfo ) => {
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
				await expect(
					pcpPayLaterMessaging.configContainer(),
					'Assert PLM configurator is visible after save for Home'
				).toBeVisible();

				await payPalUiClassic.page.goto( '/' );
				await assertPlmContainerVisible(
					payPalUiClassic,
					'Home page frontend'
				);
			}
		);
	}

	const shopPlm = payLaterMessagingData.bannerLocationSettings.Shop;

	for ( const settings of shopPlm.settings ) {
		test.fixme(
			`(PCP-0005) PLM - Shop${ summarizeSettings( settings ) }`,
			async ( { pcpPayLaterMessaging, shop }, testInfo ) => {
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
				await expect(
					pcpPayLaterMessaging.configContainer(),
					'Assert PLM configurator is visible after save for Shop'
				).toBeVisible();

				await shop.visit();
				await assertPlmContainerVisible(
					shop.payPalUi,
					'Shop page frontend'
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

test.fixme(
	'PCP-0001 | Pay Later Messaging - Customize on Product page',
	async () => {
		getTestResultsFromFile( 'PCP-0001', TEST_RESULTS_FILE );
	}
);

test.fixme(
	'PCP-0002 | Pay Later Messaging - Customize on Cart (block and classic)',
	async () => {
		getTestResultsFromFile( 'PCP-0002', TEST_RESULTS_FILE );
	}
);

test.fixme(
	'PCP-0003 | Pay Later Messaging - Customize on Checkout (block and classic)',
	async () => {
		getTestResultsFromFile( 'PCP-0003', TEST_RESULTS_FILE );
	}
);

test.fixme(
	'PCP-0004 | Pay Later Messaging - Customize on Home page',
	async () => {
		getTestResultsFromFile( 'PCP-0004', TEST_RESULTS_FILE );
	}
);

test.fixme(
	'PCP-0005 | Pay Later Messaging - Customize on Shop page',
	async () => {
		getTestResultsFromFile( 'PCP-0005', TEST_RESULTS_FILE );
	}
);
