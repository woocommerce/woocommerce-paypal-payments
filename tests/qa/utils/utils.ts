/**
 * External dependencies
 */
import {
	WooCommerceApi,
	RequestUtils,
	Plugins,
	WooCommerceUtils,
	restLogin,
	WpCli,
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
	pcpPlugin,
	ShopOrder,
	ShopConfig,
} from '../resources';
import { getCustomerStorageStateName } from './helpers/';
import urls from './urls';

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
	cli: WpCli;

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
		cli,
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
		this.cli = cli;
	}

	/**
	 * (Re)creates registered customer and refreshes his storage state.
	 * May be required for testing subscriptions/vaulting.
	 *
	 * @param customer
	 */
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

	/**
	 * Pays for order created via API (dashboard order).
	 * May be used for testing refunds/vaulting/subscriptions.
	 *
	 * @param orderId
	 * @param orderKey
	 * @param order
	 */
	payForApiOrder = async (
		orderId: number,
		orderKey: string,
		order: ShopOrder
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
	completeOrderOnCheckout = async ( shopOrder: ShopOrder ) => {
		const { payment, products, merchant } = shopOrder;
		await this.fillVisitorsCart( products );
		await this.checkout.visit();
		await this.checkout.completeCheckoutDetails( shopOrder );
		await this.checkout.payPalUi.makePayment( { merchant, payment } );
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
	completeOrderOnClassicCheckout = async ( shopOrder: ShopOrder ) => {
		const { payment, products, merchant } = shopOrder;
		await this.fillVisitorsCart( products );
		await this.classicCheckout.visit();
		await this.classicCheckout.completeCheckoutDetails( shopOrder );
		await this.classicCheckout.payPalUi.makePayment( {
			merchant,
			payment,
		} );
		const orderId = await this.orderReceived.getOrderNumber();
		return await this.wooCommerceApi.getOrderByIdAndStatus(
			orderId,
			'processing'
		);
	};

	/**
	 * Checks if the product type is "subscription", connected to PayPal
	 *
	 * @param product
	 */
	isPayPalSubscriptionProduct = (
		product: WooCommerce.CreateProduct
	): boolean => {
		if ( product.type !== 'subscription' ) {
			return false;
		}

		const payPalMeta = product.meta_data.find(
			( meta ) => meta.key === '_ppcp_enable_subscription_product'
		);

		if ( ! payPalMeta ) {
			return false;
		}

		return payPalMeta.value === 'yes';
	};

	/**
	 * Connects existing Subscription product to PayPal plan
	 *
	 * @param subscriptionId
	 */
	connectPayPalSubscriptionProduct = async ( subscriptionId: number ) => {
		const url = urls.admin.post.edit + subscriptionId;
		const wpnonce = await this.requestUtils.getPageNonce( url );
		const wcsnonce = await this.requestUtils.getRegexMatchValueOnPage(
			url,
			/<input[^>]*id="_wcsnonce"[^>]*value="([^"]*)"/
		);
		const formData = {
			_wpnonce: wpnonce,
			_wcsnonce: wcsnonce,
			_ppcp_enable_subscription_product: 'yes',
			_ppcp_subscription_plan_name: 'test',
			post_ID: subscriptionId,
			action: 'editpost',
		};
		const response = await this.requestUtils.submitPageForm(
			url,
			formData
		);
		return response.ok();
	};

	/**
	 * Configures store according to the data provided:
	 *
	 * wpDebugging: Is WP Debugging plugin activated
	 * subscription: Is WC Subscriptions plugin activated
	 * classicPages: Are classic cart and checkout pages set in WC > Settings > Advanced
	 * settings: WooCommerce settings by tabs (general, advanced, etc.)
	 * taxes: Tax settings in WC > Settings > General tab and Taxes > Tax rates tab
	 * customer: Registered customer to be created
	 *
	 * @param { ShopConfig } data see also /resources/woocommerce-config.ts
	 */
	configureStore = async ( data: ShopConfig ) => {
		const {
			enableWpDebugging,
			enableSubscriptionsPlugin,
			enableClassicPages,
			settings,
			taxes,
			customer,
			products,
		}: ShopConfig = data;

		if ( enableSubscriptionsPlugin === true ) {
			await this.requestUtils.activatePlugin( subscriptionsPlugin.slug );
		}

		if ( enableSubscriptionsPlugin === false ) {
			await this.requestUtils.deactivatePlugin(
				subscriptionsPlugin.slug
			);
		}

		if ( enableWpDebugging === true ) {
			await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
		}

		if ( enableWpDebugging === false ) {
			await this.cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } );
		}

		if ( enableClassicPages === true ) {
			await this.wooCommerceUtils.activateClassicCartPage();
			await this.wooCommerceUtils.activateClassicCheckoutPage();
		}

		if ( enableClassicPages === false ) {
			await this.wooCommerceUtils.activateBlockCartPage();
			await this.wooCommerceUtils.activateBlockCheckoutPage();
		}

		if ( settings?.general ) {
			await this.wooCommerceApi.updateGeneralSettings( settings.general );
		}

		if ( taxes ) {
			await this.wooCommerceUtils.setTaxes( taxes );
		}

		if ( customer ) {
			await this.restoreCustomer( customer );
		}

		if ( products ) {
			// create test products
			const cartItems = {};
			await Promise.all(
				products.map( async ( product ) => {
					const createdProduct =
						await this.wooCommerceUtils.createProduct( product );
					if ( this.isPayPalSubscriptionProduct( product ) ) {
						await this.connectPayPalSubscriptionProduct(
							createdProduct.id
						);
					}
					// Create cart items { id: 123 }
					cartItems[ product.slug ] = { id: createdProduct.id };
				} )
			);

			// Parse existing PRODUCTS, if any
			const existingProducts = process.env.PRODUCTS
				? JSON.parse( process.env.PRODUCTS )
				: {};

			// Merge created products with existing and store back as JSON string
			process.env.PRODUCTS = JSON.stringify( {
				...existingProducts,
				...cartItems,
			} );
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
