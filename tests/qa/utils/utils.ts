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
	PayForOrder,
	Checkout,
	ClassicCheckout,
	OrderReceived,
} from './frontend';
import {
	subscriptionsPlugin,
	wpDebuggingPlugin,
	pcpPlugin,
} from '../resources';
import { getCustomerStorageStateName } from './helpers';

export class Utils {
	requestUtils: RequestUtils;
	wooCommerceApi: WooCommerceApi;
	visitorWooCommerceApi: WooCommerceApi;
	wooCommerceUtils: WooCommerceUtils;
	plugins: Plugins;
	payForOrder: PayForOrder;
	checkout: Checkout;
	classicCheckout: ClassicCheckout;
	orderReceived: OrderReceived;

	constructor( {
		requestUtils,
		wooCommerceApi,
		visitorWooCommerceApi,
		wooCommerceUtils,
		plugins,
		payForOrder,
		checkout,
		classicCheckout,
		orderReceived,
	} ) {
		this.requestUtils = requestUtils;
		this.wooCommerceApi = wooCommerceApi;
		this.visitorWooCommerceApi = visitorWooCommerceApi;
		this.wooCommerceUtils = wooCommerceUtils;
		this.plugins = plugins;
		this.payForOrder = payForOrder;
		this.checkout = checkout;
		this.classicCheckout = classicCheckout;
		this.orderReceived = orderReceived;
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
		await this.payForOrder.payPalUi.makePayment( {
			merchant: order.merchant,
			payment: order.payment,
		} );
		return await this.wooCommerceApi.getOrderByIdAndStatus(
			orderId,
			'processing'
		);
	};

	/**
	 * Fills cart with array of products.
	 *
	 * - Creates WooCommerce.CartProduct from WooCommerce.CreateProduct (or gets CartProduct from process.env).
	 * - Clears cart.
	 * - Adds provided products.
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

	/**
	 * Installs and activates PCP plugin
	 */
	installAndActivatePcp = async () => {
		if (
			! ( await this.requestUtils.isPluginInstalled( pcpPlugin.slug ) )
		) {
			await this.plugins.installPluginFromFile( pcpPlugin.zipFilePath );
		}
		await this.requestUtils.activatePlugin( pcpPlugin.slug );
	};
}
