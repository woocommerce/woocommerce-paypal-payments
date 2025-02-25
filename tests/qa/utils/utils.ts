/**
 * External dependencies
 */
import {
	WooCommerceApi,
	RequestUtils,
	Plugins,
	WooCommerceUtils,
	restLogin,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	PcpOnboarding,
	PcpOverview,
	PcpSettings
} from './admin';
import {
	PayForOrder,
	Checkout,
	ClassicCheckout,
	OrderReceived,
	CustomerAccount,
	CustomerPaymentMethods,
} from './frontend';
import {
	subscriptionsPlugin,
	wpDebuggingPlugin,
	pcpPlugin,
	Pcp,
} from '../resources';
import { getCustomerStorageStateName } from './helpers';

export class Utils {
	plugins: Plugins;
	wooCommerceUtils: WooCommerceUtils;
	requestUtils: RequestUtils;
	wooCommerceApi: WooCommerceApi;
	visitorWooCommerceApi: WooCommerceApi;
	pcpOnboarding: PcpOnboarding;
	pcpOverview: PcpOverview;
	pcpSettings: PcpSettings;
	payForOrder: PayForOrder;
	checkout: Checkout;
	classicCheckout: ClassicCheckout;
	orderReceived: OrderReceived;
	customerAccount: CustomerAccount;
	customerPaymentMethods: CustomerPaymentMethods;

	constructor( {
		plugins,
		wooCommerceUtils,
		requestUtils,
		wooCommerceApi,
		pcpOnboarding,
		pcpOverview,
		pcpSettings,
		payForOrder,
		checkout,
		classicCheckout,
		orderReceived,
		customerAccount,
		customerPaymentMethods,
		visitorWooCommerceApi,
	} ) {
		this.plugins = plugins;
		this.wooCommerceUtils = wooCommerceUtils;
		this.requestUtils = requestUtils;
		this.wooCommerceApi = wooCommerceApi;
		this.pcpOnboarding = pcpOnboarding;
		this.pcpOverview = pcpOverview;
		this.pcpSettings = pcpSettings;
		this.payForOrder = payForOrder;
		this.checkout = checkout;
		this.classicCheckout = classicCheckout;
		this.orderReceived = orderReceived;
		this.customerAccount = customerAccount;
		this.customerPaymentMethods = customerPaymentMethods;
		this.visitorWooCommerceApi = visitorWooCommerceApi;
	}

	restoreCustomer = async ( customer: WooCommerce.CreateCustomer ) => {
		await this.wooCommerceUtils.deleteCustomer( customer );
		await this.wooCommerceUtils.createCustomer( customer );
		const storageStateName = getCustomerStorageStateName( customer );
		const storageStatePath = `${ process.env.STORAGE_STATE_PATH }/${ storageStateName }.json`;
		await restLogin( {
			baseURL: process.env.WP_BASE_URL,
			httpCredentials: {
				username: process.env.WP_BASIC_AUTH_USER,
				password: process.env.WP_BASIC_AUTH_PASS,
			},
			storageStatePath,
			user: {
				username: customer.username,
				password: customer.password,
			},
		} );
	};

	payForApiOrder = async (
		orderId: number,
		orderKey: string,
		order: WooCommerce.ShopOrder
	) => {
		await this.payForOrder.visit( orderId, orderKey );
		await this.payForOrder.ppui.makeClassicPayment( {
			merchant: order.merchant,
			payment: order.payment,
		} );
		return await this.wooCommerceApi.getOrderByIdAndStatus(
			orderId,
			'processing'
		);
	};

	/**
	 * Pays for order on checkout page
	 *
	 * @param products
	 */
	fillVisitorsCart = async ( products: WooCommerce.CreateProduct[] ) => {
		const cartProducts = await this.wooCommerceUtils.createCartProducts(
			products
		);
		await this.visitorWooCommerceApi.clearCart();
		await this.visitorWooCommerceApi.addProductsToCart( cartProducts );
	};

	/**
	 * Pays for order on checkout page
	 *
	 * @param shopOrder
	 */
	completeOrderOnCheckout = async ( shopOrder: WooCommerce.ShopOrder ) => {
		await this.fillVisitorsCart( shopOrder.products );

		await this.checkout.makeOrder( shopOrder );
		const orderId = await this.orderReceived.getOrderNumber();
		return await this.wooCommerceApi.getOrderByIdAndStatus(
			orderId,
			'processing'
		);
	};

	/**
	 * Pays for order on classic checkout page
	 *
	 * @param shopOrder
	 */
	completeOrderOnClassicCheckout = async (
		shopOrder: WooCommerce.ShopOrder
	) => {
		await this.fillVisitorsCart( shopOrder.products );
		await this.classicCheckout.makeOrder( shopOrder );
		const orderId = await this.orderReceived.getOrderNumber();
		return await this.wooCommerceApi.getOrderByIdAndStatus(
			orderId,
			'processing'
		);
	};

	/**
	 * Connects provided merchant
	 * @param merchant 
	 */
	connectMerchant = async ( merchant: Pcp.Merchant ) => {
		await this.pcpOnboarding.visit();
		await this.pcpOnboarding.gotoInitialOnboardingPage();
		await this.pcpOnboarding.openAdvancedOptions();
		await this.pcpOnboarding.enableSandboxMode();
		await this.pcpOnboarding.enableManuallyConnect();
		await this.pcpOnboarding.sandboxClientIdInput().fill( merchant.client_id );
		await this.pcpOnboarding.sandboxSecretKeyInput().fill( merchant.client_secret );
		// TODO: investigate
		await this.pcpOnboarding.page.waitForTimeout( 500 ); // unfortunately required to make use of the secret key
		await this.pcpOnboarding.connectAccountButton().click();
		await this.pcpOverview.assertUrl();
	};

	disconnectMerchant = async (
		options: { resetDb: boolean; } = { resetDb: false }
	) => {
		const { resetDb } = options;
		await this.pcpSettings.visit();
		await this.pcpSettings.disconnectButton().click();
		if( resetDb ) {
			await this.pcpSettings.startOverToggle().check();
		}
		await this.pcpSettings.disconnectButton().click();
		await this.pcpOnboarding.page.waitForLoadState( 'networkidle' );
		// TODO: report bug for following assertion:
		// await this.pcpOnboarding.assertUrl(); // Bug: the URL isn't changed
	};

	/**
	 * Enable PayPal funding source
	 *
	 * @param method
	 */
	pcpPaymentMethodIsEnabled = async ( method ) => {
		switch ( method ) {
			case 'PayPal':
				break;

			case 'PayLater':
				break;

			case 'Venmo':
				break;

			case 'ACDC':
				break;

			case 'OXXO':
				break;

			case 'DebitOrCreditCard':
				break;

			case 'StandardCardButton':
				break;

			case 'PayUponInvoice':
				break;
		}
	};

	/**
	 * Configures store according to the data provided
	 *
	 * @param {Object} data see /resources/woocommerce-config.ts
	 */
	configureStore = async ( data ) => {
		if ( data.wpDebugging === true ) {
			await this.requestUtils.activatePlugin( wpDebuggingPlugin.slug );
		}

		if ( data.wpDebugging === false ) {
			await this.requestUtils.deactivatePlugin( wpDebuggingPlugin.slug );
		}

		if ( data.subscription === true ) {
			await this.requestUtils.activatePlugin( subscriptionsPlugin.slug );
		}

		if ( data.subscription === false ) {
			await this.requestUtils.deactivatePlugin(
				subscriptionsPlugin.slug
			);
		}

		if ( data.classicPages === true ) {
			await this.wooCommerceUtils.activateClassicCartPage();
			await this.wooCommerceUtils.activateClassicCheckoutPage();
		}

		if ( data.classicPages === false ) {
			await this.wooCommerceUtils.activateBlockCartPage();
			await this.wooCommerceUtils.activateBlockCheckoutPage();
		}

		if ( data.settings?.general ) {
			await this.wooCommerceApi.updateGeneralSettings(
				data.settings.general
			);
		}

		if ( data.taxes ) {
			await this.wooCommerceUtils.setTaxes( data.taxes );
		}

		if ( data.customer ) {
			await this.restoreCustomer( data.customer );
		}
	};

	installAndActivatePcp = async () => {
		if (
			! ( await this.requestUtils.isPluginInstalled( pcpPlugin.slug ) )
		) {
			await this.plugins.installPluginFromFile( pcpPlugin.zipFilePath );
		}
		await this.requestUtils.activatePlugin( pcpPlugin.slug );
	}

	configurePcp = async ( data: Pcp.Admin.Config ) => {
		if ( data.merchant ) {
			if ( data.disconnectMerchant ) {
				await this.disconnectMerchant();
				return;
			}

			await this.connectMerchant( data.merchant );
		}
	};
}
