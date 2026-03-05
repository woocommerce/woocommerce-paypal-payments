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

test(
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
		} ) => {
		await utils.fillVisitorsCart( [ products.simple100 ] );

		await pcpPayLaterMessaging.visit();
		await pcpPayLaterMessaging.waitForLoadingMaskRemoved();
		await expect(
			pcpPayLaterMessaging.configContainer(),
			'Assert PLM configurator initial view is visible'
		).toBeVisible();

		await pcpPayLaterMessaging.expandAccordionSection( 'Product page' );
		await expect(
			pcpPayLaterMessaging.configContainer(),
			'Assert PLM Product page config is visible'
		).toBeVisible();

		await pcpPayLaterMessaging.expandAccordionSection( 'Cart' );
		await expect(
			pcpPayLaterMessaging.configContainer(),
			'Assert PLM Cart config is visible'
		).toBeVisible();

		await pcpPayLaterMessaging.expandAccordionSection( 'Checkout' );
		await expect(
			pcpPayLaterMessaging.configContainer(),
			'Assert PLM Checkout config is visible'
		).toBeVisible();

		await pcpPayLaterMessaging.expandAccordionSection( 'Home' );
		await expect(
			pcpPayLaterMessaging.configContainer(),
			'Assert PLM Home config is visible'
		).toBeVisible();

		await pcpPayLaterMessaging.expandAccordionSection( 'Shop' );
		await expect(
			pcpPayLaterMessaging.configContainer(),
			'Assert PLM Shop config is visible'
		).toBeVisible();

		await product.visit( products.simple100.slug );
		await expect(
			product.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on product page'
		).toBeVisible();

		await cart.visit();
		await expect(
			cart.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on cart'
		).toBeVisible();

		await classicCart.visit();
		await expect(
			classicCart.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on classic cart'
		).toBeVisible();

		await checkout.visit();
		await expect(
			checkout.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on checkout'
		).toBeVisible();

		await classicCheckout.visit();
		await expect(
			classicCheckout.payPalUi.payLaterMessageContainer(),
			'Assert PLM is visible on classic checkout'
		).toBeVisible();

		await shop.visit();
		await expect(
			shop.payPalUi.payLaterMessageContainer(),
			'Assert PLM container is not visible on shop (disabled by default)'
		).not.toBeVisible();

		await payPalUi.page.goto( '/' ); // home page
		await expect(
			payPalUi.payLaterMessageContainer(),
			'Assert PLM container is not visible on home (disabled by default)'
		).not.toBeVisible();
	}
);

test(
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
		await pcpPayLaterMessaging.waitForLoadingMaskRemoved();

		await pcpPayLaterMessaging.disableMessagingForLocation(
			'Product page'
		);
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Cart' );
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Checkout' );
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Home' );
		await pcpPayLaterMessaging.disableMessagingForLocation( 'Shop' );

		await pcpPayLaterMessaging.saveChanges();
		await pcpPayLaterMessaging.page.reload();
		await pcpPayLaterMessaging.waitForLoadingMaskRemoved();

		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Product page'
				),
				'Assert Product page messaging is disabled'
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Cart'
				),
				'Assert Cart messaging is disabled'
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Checkout'
				),
				'Assert Checkout messaging is disabled'
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Home'
				),
				'Assert Home messaging is disabled'
			)
			.toBeFalsy();
		expect
			.soft(
				await pcpPayLaterMessaging.isMessagingForLocationEnabled(
					'Shop'
				),
				'Assert Shop messaging is disabled'
			)
			.toBeFalsy();

		await product.visit( products.simple100.slug );
		await expect
			.soft(
				product.payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on product when disabled'
			)
			.not.toBeVisible();

		await cart.visit();
		await expect
			.soft(
				cart.payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on cart when disabled'
			)
			.not.toBeVisible();

		await classicCart.visit();
		await expect
			.soft(
				classicCart.payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on classic cart when disabled'
			)
			.not.toBeVisible();

		await checkout.visit();
		await expect
			.soft(
				checkout.payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on checkout when disabled'
			)
			.not.toBeVisible();

		await classicCheckout.visit();
		await expect
			.soft(
				classicCheckout.payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on classic checkout when disabled'
			)
			.not.toBeVisible();

		await shop.visit();
		await expect
			.soft(
				shop.payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on shop when disabled'
			)
			.not.toBeVisible();

		await payPalUi.page.goto( '/' ); // home page
		await expect
			.soft(
				payPalUi.payLaterMessageContainer(),
				'Assert PLM container is not visible on home when disabled'
			)
			.not.toBeVisible();
	}
);
