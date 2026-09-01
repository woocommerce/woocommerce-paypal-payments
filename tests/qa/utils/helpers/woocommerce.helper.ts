/**
 * External dependencies
 */
import {
	updateDotenv,
	WooCommerceApi,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test as setup, expect } from '..';
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
	negative12FeePlugin,
	pcpSdkVersionFlag,
} from '../../resources';
import { sdkVersion } from './sdk-version.helper';

const country = process.env.WC_DEFAULT_COUNTRY || 'usa';

const installPluginResolveActiveState = async ( {
	requestUtils,
	plugins,
	slug,
	zipFilePath,
	isActive = true,
	forceReinstall = false,
} ) => {
	if ( forceReinstall || ! ( await requestUtils.isPluginInstalled( slug ) ) ) {
		await plugins.installPluginFromFile( zipFilePath );
	}
	if ( isActive ) {
		await requestUtils.activatePlugin( slug );
	} else {
		await requestUtils.deactivatePlugin( slug );
	}
};

export const setupWooCommerce = async () => {
	// Unlike the setup below, this is a plain REST call (requestUtils.activatePlugin),
	// not something wp-env's own bootstrap provisions - it must run in every
	// environment, CI included, or the SDK version under test silently stays
	// whatever the site's active_plugins state happened to already be.
	//
	// The flag plugin stays active on both v5 and v6 runs now and is switched via its own
	// REST endpoint (POST /pcp-qa/v1/sdk-v6) instead of WP activation state. Some hosting
	// environments hardcode PCP_SDK_V6_ENABLED=1 server-side; the old activate-to-force-v6/
	// deactivate-to-fall-back design had no way to override that — deactivating only removed
	// the override, it never forced v5. forceReinstall keeps a stale copy of this plugin
	// (from before this endpoint existed) from silently sticking around on an environment
	// where it was installed previously.
	setup(
		`Setup PCP SDK Version Flag (${ sdkVersion() })`,
		async ( { requestUtils, plugins } ) => {
			await installPluginResolveActiveState( {
				requestUtils,
				plugins,
				...pcpSdkVersionFlag,
				isActive: true,
				forceReinstall: true,
			} );
			await requestUtils.rest( {
				method: 'POST',
				path: '/pcp-qa/v1/sdk-v6',
				data: { enabled: sdkVersion() === 'v6' },
			} );
		}
	);

	// In CI wp-env is used and following setup is already done by wp-env, so skip it in CI to save time
	if ( ! process.env.CI ) {
		setup( 'Setup Permalinks', async ( { requestUtils } ) => {
			await requestUtils.setPermalinks( '/%postname%/' );
		} );

		setup(
			'Setup Disable Nonce plugin (active)',
			async ( { requestUtils, plugins } ) => {
				await installPluginResolveActiveState( {
					requestUtils,
					plugins,
					...disableNoncePlugin,
				} );
			}
		);

		setup(
			'Setup Disable Webhook Verification plugin (active)',
			async ( { plugins, requestUtils } ) => {
				await installPluginResolveActiveState( {
					requestUtils,
					plugins,
					...disableWebhookVerificationPlugin,
				} );
			}
		);

		setup(
			'Setup Disable WooCommerce Setup Wizard Plugin (active)',
			async ( { requestUtils, plugins } ) => {
				await installPluginResolveActiveState( {
					requestUtils,
					plugins,
					...disableWcSetupWizard,
				} );
			}
		);

		setup(
			'Setup WooCommerce plugin (active)',
			async ( { requestUtils } ) => {
				if (
					! ( await requestUtils.isPluginInstalled( 'woocommerce' ) )
				) {
					await requestUtils.installPlugin( 'woocommerce' );
				}
				await requestUtils.activatePlugin( 'woocommerce' );
			}
		);

		setup(
			'Setup WC Subscriptions plugin (inactive)',
			async ( { requestUtils, plugins } ) => {
				await installPluginResolveActiveState( {
					requestUtils,
					plugins,
					...subscriptionsPlugin,
					isActive: false,
				} );
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

	// Installed (inactive) in every environment — specs activate it themselves
	// via requestUtils.activatePlugin/deactivatePlugin in their own beforeAll/afterAll.
	setup(
		'Setup Negative 12 Fee plugin (inactive)',
		async ( { requestUtils, plugins } ) => {
			await installPluginResolveActiveState( {
				requestUtils,
				plugins,
				...negative12FeePlugin,
				isActive: false,
			} );
		}
	);

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
			await wooCommerceApi.updateEmailSubSettings( id, {
				enabled: 'no',
			} );
		}
	} );

	setup(
		'Setup WooCommerce general settings',
		async ( { wooCommerceApi } ) => {
			await wooCommerceApi.updateGeneralSettings(
				shopSettings[ country ].general
			);
		}
	);

	setup( 'Setup WooCommerce shipping', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.configureShippingZone( shippingZones.worldwide );
	} );

	setup(
		'Setup WooCommerce taxes (included)',
		async ( { wooCommerceUtils } ) => {
			await wooCommerceUtils.setTaxes( taxSettings.including );
		}
	);

	setup( 'Setup Registered Customer', async ( { utils } ) => {
		await utils.restoreCustomer( customers[ country ] );
	} );

	setup( 'Setup coupons', async ( { wooCommerceUtils } ) => {
		// create test coupons
		const couponItems = {};
		const couponEntries = Object.entries( coupons );
		await Promise.all(
			couponEntries.map( async ( [ , coupon ] ) => {
				const createdCoupon =
					await wooCommerceUtils.createCoupon( coupon );
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
			productEntries.map( async ( [ , product ] ) => {
				// check if not subscription product - requires Supscriptions plugin
				if ( product.type !== 'subscription' ) {
					const createdProduct =
						await wooCommerceUtils.createProduct( product );
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

export const waitForOrderStatus = async (
	wooCommerceApi: WooCommerceApi,
	orderId: number,
	{
		expectedStatus = 'processing',
		timeout = 60_000,
	}: { expectedStatus?: string; timeout?: number } = {}
) => {
	let order: WooCommerce.Order;

	await expect
		.poll(
			async () => {
				order = await wooCommerceApi.getOrder( orderId );
				return order.status;
			},
			{
				message: `Assert order #${ orderId } status is "${ expectedStatus }"`,
				timeout,
				intervals: [ 1_000, 2_500, 5_000, 10_000 ],
			}
		)
		.toEqual( expectedStatus );

	return order;
};
