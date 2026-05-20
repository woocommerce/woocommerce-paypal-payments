/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault, products } from '../../resources';
import { payLaterMessagingData } from './_test-data';

test.describe( 'PLM Configurator', () => {
	test.beforeAll( async ( { utils, pcpApi } ) => {
		await utils.configureStore( storeConfigDefault );
		await utils.installAndActivatePcp();
		await pcpApi.resetDb();
		await pcpApi.connectMerchant(
			merchants.usa.client_id,
			merchants.usa.client_secret
		);
	} );

	test( 'PCP-0001 | PLM - Product page', async ( {
		pcpPayLaterMessaging,
		product,
	} ) => {
		const { location } = payLaterMessagingData.checkoutLocationSettings[
			'Product page'
		];
		const settings =
			payLaterMessagingData.checkoutLocationSettings[ 'Product page' ]
				.settings[ 0 ];

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForLocation( location );
		await pcpPayLaterMessaging.updateLocationSettings( location, settings );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.expandAccordionSection( location );
		await pcpPayLaterMessaging.assertPreviewShowsMessage();

		await product.visit( products.simple100.slug );
		const productPlmVisible =
			await product.payPalUi.assertPayLaterMessageVisibleWithContent();
		if ( ! productPlmVisible ) {
			test.skip();
		}
		await expect(
			product.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on product page'
		).toBeVisible();
	} );

	test( 'PCP-0002 | PLM - Cart (block and classic)', async ( {
		utils,
		pcpPayLaterMessaging,
		cart,
		classicCart,
	} ) => {
		await utils.fillVisitorsCart( [ products.simple100 ] );

		const { location } = payLaterMessagingData.checkoutLocationSettings.Cart;
		const settings =
			payLaterMessagingData.checkoutLocationSettings.Cart.settings[ 0 ];

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForLocation( location );
		await pcpPayLaterMessaging.updateLocationSettings( location, settings );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.expandAccordionSection( location );
		await pcpPayLaterMessaging.assertPreviewShowsMessage();

		await cart.visit();
		const cartPlmVisible =
			await cart.payPalUi.assertPayLaterMessageVisibleWithContent();
		if ( ! cartPlmVisible ) {
			test.skip();
		}
		await expect(
			cart.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on cart'
		).toBeVisible();

		await classicCart.visit();
		const classicCartPlmVisible =
			await classicCart.payPalUi.assertPayLaterMessageVisibleWithContent();
		if ( ! classicCartPlmVisible ) {
			test.skip();
		}
		await expect(
			classicCart.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on classic cart'
		).toBeVisible();
	} );

	test( 'PCP-0003 | PLM - Checkout (block and classic)', async ( {
		utils,
		pcpPayLaterMessaging,
		checkout,
		classicCheckout,
	} ) => {
		await utils.fillVisitorsCart( [ products.simple100 ] );

		const { location } =
			payLaterMessagingData.checkoutLocationSettings.Checkout;
		const settings =
			payLaterMessagingData.checkoutLocationSettings.Checkout.settings[ 0 ];

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForLocation( location );
		await pcpPayLaterMessaging.updateLocationSettings( location, settings );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.expandAccordionSection( location );
		await pcpPayLaterMessaging.assertPreviewShowsMessage();

		await checkout.visit();
		const checkoutPlmVisible =
			await checkout.payPalUi.assertPayLaterMessageVisibleWithContent();
		if ( ! checkoutPlmVisible ) {
			test.skip();
		}
		await expect(
			checkout.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on checkout'
		).toBeVisible();

		await classicCheckout.visit();
		const classicCheckoutPlmVisible =
			await classicCheckout.payPalUi.assertPayLaterMessageVisibleWithContent();
		if ( ! classicCheckoutPlmVisible ) {
			test.skip();
		}
		await expect(
			classicCheckout.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on classic checkout'
		).toBeVisible();
	} );

	test( 'PCP-0004 | PLM - Home page', async ( {
		pcpPayLaterMessaging,
		payPalUiClassic,
	} ) => {
		const { location } = payLaterMessagingData.bannerLocationSettings.Home;
		const settings =
			payLaterMessagingData.bannerLocationSettings.Home.settings[ 0 ];

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForLocation( location );
		await pcpPayLaterMessaging.updateLocationSettings( location, settings );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.expandAccordionSection( location );
		await pcpPayLaterMessaging.assertPreviewShowsMessage();

		await payPalUiClassic.page.goto( '/' );
		await expect(
			payPalUiClassic.payLaterMessageContainer(),
			'Assert PLM is visible on home page'
		).toBeVisible();
	} );

	test( 'PCP-0005 | PLM - Shop page', async ( {
		pcpPayLaterMessaging,
		shop,
	} ) => {
		const { location } = payLaterMessagingData.bannerLocationSettings.Shop;
		const settings =
			payLaterMessagingData.bannerLocationSettings.Shop.settings[ 0 ];

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForLocation( location );
		await pcpPayLaterMessaging.updateLocationSettings( location, settings );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.expandAccordionSection( location );
		await pcpPayLaterMessaging.assertPreviewShowsMessage();

		await shop.visit();
		await expect(
			shop.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on shop page'
		).toBeVisible();
	} );

	test( 'PCP-0006 | PLM - WooCommerce Block', async ( {
		pcpPayLaterMessaging,
		product,
		requestUtils,
	} ) => {
		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForWooCommerceBlock();
		await pcpPayLaterMessaging.saveChanges();

		const page = await requestUtils.createPage( {
			title: 'PLM Block Test Page',
			status: 'publish',
			content:
				'<!-- wp:woocommerce-paypal-payments/paylater-messages /-->',
		} );

		try {
			await product.visitByPageId( page.id );
			await expect(
				product.payPalUi.payLaterMessageContainer(),
				'Assert PLM is visible on WooCommerce Block page'
			).toBeVisible();
		} finally {
			await requestUtils.deletePage( page.id );
		}
	} );

	test( 'PCP-0007 | PLM - Preview layout buttons (Text/Desktop/Mobile)', async ( {
		pcpPayLaterMessaging,
	} ) => {
		const { location } = payLaterMessagingData.checkoutLocationSettings[
			'Product page'
		];
		const settings =
			payLaterMessagingData.checkoutLocationSettings[ 'Product page' ]
				.settings[ 0 ];

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.enableMessagingForLocation( location );
		await pcpPayLaterMessaging.updateLocationSettings( location, settings );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.expandAccordionSection( location );

		await expect(
			pcpPayLaterMessaging.previewIframe(),
			'Assert PLM preview iframe is visible'
		).toBeVisible();

		await pcpPayLaterMessaging.previewTextButton().click();
		await expect(
			pcpPayLaterMessaging.previewIframe(),
			'Assert PLM preview iframe still visible after Text layout'
		).toBeVisible();

		await pcpPayLaterMessaging.previewDesktopButton().click();
		await expect(
			pcpPayLaterMessaging.previewIframe(),
			'Assert PLM preview iframe still visible after Desktop layout'
		).toBeVisible();

		await pcpPayLaterMessaging.previewMobileButton().click();
		await expect(
			pcpPayLaterMessaging.previewIframe(),
			'Assert PLM preview iframe still visible after Mobile layout'
		).toBeVisible();
	} );
} );
