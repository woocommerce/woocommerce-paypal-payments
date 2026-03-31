/**
 * External dependencies
 */
import { updateDotenv } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test as setup } from '..';
import {
	shopSettings,
	shippingZones,
	taxSettings,
	products,
	coupons,
	customers,
	disableNoncePlugin,
	subscriptionsPlugin,
	disableWcSetupWizard,
	disableWebhookVerificationPlugin,
} from '../../resources';

const country = process.env.WC_DEFAULT_COUNTRY || 'usa';

export const setupWooCommerce = async () => {
	// In CI wp-env is used and following setup is already done by wp-env, so skip it in CI to save time
	if ( ! process.env.CI ) {
		setup( 'Setup Permalinks', async ( { requestUtils } ) => {
			await requestUtils.setPermalinks( '/%postname%/' );
		} );

		setup(
			'Setup Disable Nonce plugin (active)',
			async ( { requestUtils, plugins } ) => {
				if (
					! ( await requestUtils.isPluginInstalled(
						disableNoncePlugin.slug
					) )
				) {
					await plugins.installPluginFromFile(
						disableNoncePlugin.zipFilePath
					);
				}
				await requestUtils.activatePlugin( disableNoncePlugin.slug );
			}
		);

		setup(
			'Setup Disable Webhook Verification plugin (inactive)',
			async ( { plugins, requestUtils } ) => {
				const plugin = disableWebhookVerificationPlugin;
				if ( ! ( await requestUtils.isPluginInstalled( plugin.slug ) ) ) {
					await plugins.installPluginFromFile( plugin.zipFilePath );
				}
				await requestUtils.deactivatePlugin( plugin.slug );
			}
		);

		setup(
			'Setup Disable WooCommerce Setup Wizard Plugin (active)',
			async ( { requestUtils, plugins } ) => {
				if (
					! ( await requestUtils.isPluginInstalled(
						disableWcSetupWizard.slug
					) )
				) {
					await plugins.installPluginFromFile(
						disableWcSetupWizard.zipFilePath
					);
				}
				await requestUtils.activatePlugin( disableWcSetupWizard.slug );
			}
		);

		setup( 'Setup WooCommerce plugin (active)', async ( { requestUtils } ) => {
			if ( ! ( await requestUtils.isPluginInstalled( 'woocommerce' ) ) ) {
				await requestUtils.installPlugin( 'woocommerce' );
			}
			await requestUtils.activatePlugin( 'woocommerce' );
		} );

		setup(
			'Setup WC Subscriptions plugin (inactive)',
			async ( { requestUtils, plugins } ) => {
				if (
					! ( await requestUtils.isPluginInstalled(
						subscriptionsPlugin.slug
					) )
				) {
					await plugins.installPluginFromFile(
						subscriptionsPlugin.zipFilePath
					);
				}
				await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
			}
		);

		setup( 'Setup theme', async ( { requestUtils } ) => {
			const slug = 'storefront';
			if ( ! ( await requestUtils.isThemeInstalled( slug ) ) ) {
				await requestUtils.installTheme( slug );
			}
			await requestUtils.activateTheme( slug );
		} );

		setup(
			'Setup WooCommerce Live site visibility',
			async ( { wooCommerceUtils } ) => {
				await wooCommerceUtils.setSiteVisibility();
			}
		);
	}

	setup( 'Setup WooCommerce API keys', async ( { wooCommerceUtils } ) => {
		if ( ! ( await wooCommerceUtils.apiKeysExist() ) ) {
			const apiKeys = await wooCommerceUtils.createApiKeys();
			if ( ! process.env.CI ) {
				await updateDotenv( './.env', apiKeys );
			}
			for ( const [ key, value ] of Object.entries( apiKeys ) ) {
				process.env[ key ] = value;
			}
		}
	} );

	setup( 'Setup WooCommerce email settings', async ( { wooCommerceApi } ) => {
		const emailIds = [
			'email_new_order',
			'email_cancelled_order',
			'email_failed_order',
			'email_customer_failed_order',
			'email_customer_on_hold_order',
			'email_customer_processing_order',
			'email_customer_completed_order',
			'email_customer_refunded_order',
			'email_customer_note',
			'email_customer_reset_password',
			'email_customer_new_account',
			'email_customer_pos_refunded_order',
		];
		for ( const id of emailIds ) {
			await wooCommerceApi.updateEmailSubSettings( id, { enabled: 'no' } );
		}
	} );

	setup( 'Setup WooCommerce general settings', async ( { wooCommerceApi } ) => {
		await wooCommerceApi.updateGeneralSettings(
			shopSettings[ country ].general
		);
	} );

	setup( 'Setup WooCommerce shipping', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.configureShippingZone( shippingZones.worldwide );
	} );

	setup( 'Setup WooCommerce taxes (included)', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );

	setup( 'Setup Registered Customer', async ( { utils } ) => {
		await utils.restoreCustomer( customers[ country ] );
	} );

	setup( 'Setup Delete Previous Orders', async ( { wooCommerceApi } ) => {
		await wooCommerceApi.deleteAllOrders();
	} );

	setup( 'Setup coupons', async ( { wooCommerceUtils } ) => {
		// create test coupons
		const couponItems = {};
		const couponEntries = Object.entries( coupons );
		await Promise.all(
			couponEntries.map( async ( [ key, coupon ] ) => {
				const createdCoupon = await wooCommerceUtils.createCoupon( coupon );
				couponItems[ coupon.code ] = { id: createdCoupon.id };
			} )
		);
		// store created coupons as CART_ITEMS env var
		process.env.COUPONS = JSON.stringify( couponItems );
	} );

	setup( 'Setup products', async ( { wooCommerceUtils } ) => {
		// create test products
		const cartItems = {};
		const productEntries = Object.entries( products );
		await Promise.all(
			productEntries.map( async ( [ key, product ] ) => {
				// check if not subscription product - requires Supscriptions plugin
				if ( product.type !== 'subscription' ) {
					const createdProduct = await wooCommerceUtils.createProduct(
						product
					);
					cartItems[ product.slug ] = { id: createdProduct.id };
				}
			} )
		);
		// store created products as CART_ITEMS env var
		process.env.PRODUCTS = JSON.stringify( cartItems );
	} );

	setup( 'Setup Block and Classic pages', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.publishBlockCartPage();
		await wooCommerceUtils.publishBlockCheckoutPage();
		await wooCommerceUtils.publishClassicCartPage();
		await wooCommerceUtils.publishClassicCheckoutPage();
	} );
};
