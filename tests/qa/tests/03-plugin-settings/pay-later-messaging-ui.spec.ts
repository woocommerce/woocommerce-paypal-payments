/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault, products } from '../../resources';

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
	'PCP-0000 | Settings - Pay Later Messaging - Default UI',
	async (
		{
			utils,
			payPalUi,
			pcpPayLaterMessaging,
			shop,
			product,
			cart,
			classicCart,
			checkout,
			classicCheckout,
		},
		testInfo
	) => {
		const snapshotName = testInfo.title;
		await utils.fillVisitorsCart( [ products.simple100 ] );

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.waitForLoadingMaskRemoved();
		await pcpPayLaterMessaging.snapshotPlmConfigurator(
			`${ snapshotName } - Initial view`
		);

		await pcpPayLaterMessaging.expandAccordionSection( 'Product page' );
		await pcpPayLaterMessaging.snapshotPlmConfigurator(
			`${ snapshotName } - Product page config`
		);

		await pcpPayLaterMessaging.expandAccordionSection( 'Cart' );
		await pcpPayLaterMessaging.snapshotPlmConfigurator(
			`${ snapshotName } - Cart config`
		);

		await pcpPayLaterMessaging.expandAccordionSection( 'Checkout' );
		await pcpPayLaterMessaging.snapshotPlmConfigurator(
			`${ snapshotName } - Checkout config`
		);

		await pcpPayLaterMessaging.expandAccordionSection( 'Home' );
		await pcpPayLaterMessaging.snapshotPlmConfigurator(
			`${ snapshotName } - Home config`
		);

		await pcpPayLaterMessaging.expandAccordionSection( 'Shop' );
		await pcpPayLaterMessaging.snapshotPlmConfigurator(
			`${ snapshotName } - Shop config`
		);

		await product.visit( products.simple100.slug );
		await expect(
			product.payPalUi.payLaterMessageContainer()
		).toBeVisible();

		await cart.visit();
		await expect( cart.payPalUi.payLaterMessageContainer() ).toBeVisible();

		await classicCart.visit();
		await expect(
			classicCart.payPalUi.payLaterMessageContainer()
		).toBeVisible();

		await checkout.visit();
		await expect(
			checkout.payPalUi.payLaterMessageContainer()
		).toBeVisible();

		await classicCheckout.visit();
		await expect(
			classicCheckout.payPalUi.payLaterMessageContainer()
		).toBeVisible();

		await shop.visit();
		await expect(
			shop.payPalUi.payLaterMessageContainer()
		).not.toBeVisible();

		await payPalUi.page.goto( '/' ); // home page
		await expect( payPalUi.payLaterMessageContainer() ).not.toBeVisible();
	}
);

test.fixme(
	'PCP-0000 | Settings - Pay Later Messaging - Disabled on all pages',
	async ( {
		utils,
		payPalUi,
		pcpPayLaterMessaging,
		shop,
		product,
		cart,
		classicCart,
		checkout,
		classicCheckout,
	} ) => {
		await utils.fillVisitorsCart( [ products.simple100 ] );

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.disableMessagingForLocation(
			'Product page'
		);
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Cart' );
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Checkout' );
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Home' );
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Shop' );
		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();

		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Product page'
				)
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Cart'
				)
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Checkout'
				)
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Home'
				)
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Shop'
				)
			)
			.toBeFalsy();

		await product.visit( products.simple100.slug );
		await expect
			.soft( product.payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();

		await cart.visit();
		await expect
			.soft( cart.payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();

		await classicCart.visit();
		await expect
			.soft( classicCart.payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();

		await checkout.visit();
		await expect
			.soft( checkout.payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();

		await classicCheckout.visit();
		await expect
			.soft( classicCheckout.payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();

		await shop.visit();
		await expect
			.soft( shop.payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();

		await payPalUi.page.goto( '/' ); // home page
		await expect
			.soft( payPalUi.payLaterMessageContainer() )
			.not.toBeVisible();
	}
);
