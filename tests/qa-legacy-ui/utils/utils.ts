/**
 * External dependencies
 */
import {
	WooCommerceApi,
	RequestUtils,
	Plugins,
	WooCommerceUtils,
	Login,
	restLogin,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	Connection,
	StandardPayments,
	PayLater,
	AdvancedCardProcessing,
	StandardCardButton,
	OXXO,
	PayUponInvoice,
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
	disableNoncePlugin,
	wpDebuggingPlugin,
	pcpPlugin,
	PcpMerchant,
	PcpConfig,
} from '../resources';
import { getCustomerStorageStateName } from './helpers';

export class Utils {
	plugins: Plugins;
	wooCommerceUtils: WooCommerceUtils;
	requestUtils: RequestUtils;
	wooCommerceApi: WooCommerceApi;
	visitorWooCommerceApi: WooCommerceApi;
	connection: Connection;
	standardPayments: StandardPayments;
	payLater: PayLater;
	advancedCardProcessing: AdvancedCardProcessing;
	standardCardButton: StandardCardButton;
	oxxo: OXXO;
	payUponInvoice: PayUponInvoice;
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
		connection,
		standardPayments,
		payLater,
		advancedCardProcessing,
		standardCardButton,
		oxxo,
		payUponInvoice,
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
		this.connection = connection;
		this.standardPayments = standardPayments;
		this.payLater = payLater;
		this.oxxo = oxxo;
		this.payUponInvoice = payUponInvoice;
		this.advancedCardProcessing = advancedCardProcessing;
		this.standardCardButton = standardCardButton;
		this.payForOrder = payForOrder;
		this.checkout = checkout;
		this.classicCheckout = classicCheckout;
		this.orderReceived = orderReceived;
		this.customerAccount = customerAccount;
		this.customerPaymentMethods = customerPaymentMethods;
		this.visitorWooCommerceApi = visitorWooCommerceApi;
	}

	activateWpDebuggingPlugin = async () => {
		await this.requestUtils.activatePlugin( wpDebuggingPlugin.slug );
	};

	deactivateWpDebuggingPlugin = async () => {
		await this.requestUtils.deactivatePlugin( wpDebuggingPlugin.slug );
	};

	activateWcSubscriptionsPlugin = async () => {
		await this.requestUtils.activatePlugin( subscriptionsPlugin.slug );
	};

	deactivateWcSubscriptionsPlugin = async () => {
		await this.requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
	};

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
		await this.payForOrder.ppui.makeClassicPayment( order );
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

	connectMerchant = async (
		merchant: PcpMerchant,
		options = {
			enablePayUponInvoice: false,
		}
	) => {
		await this.connection.visit();
		// check if merchant with expected email is not connected
		const isExpectedMerchantConnected =
			await this.connection.isMerchantConnected( merchant );
		if ( ! isExpectedMerchantConnected ) {
			await this.connection.disconnectMerchant();
			await this.connection.connectMerchant( merchant, options );
		}
	};

	disconnectMerchant = async () => {
		await this.connection.visit();
		await this.connection.disconnectMerchant();
	};

	clearPcpDb = async ( data: PcpMerchant ) => {
		await this.connection.visit();
		if ( ! ( await this.connection.isMerchantConnected() ) ) {
			await this.connection.connectMerchant( data );
		}
		await this.connection.clearDB();
	};

	/**
	 * Enable PayPal funding source
	 *
	 * @param method
	 */
	pcpPaymentMethodIsEnabled = async ( method ) => {
		switch ( method ) {
			case 'PayPal':
				// Is enabled by default within Standard Payments
				break;

			case 'PayLater':
				await this.activateWpDebuggingPlugin();
				await this.standardPayments.setup( { vaulting: false } );
				await this.payLater.setup( { enableGateway: true } );
				break;

			case 'Venmo':
				await this.activateWpDebuggingPlugin();
				break;

			case 'ACDC':
				await this.advancedCardProcessing.setup( {
					enableGateway: true,
					threeDSecure:
						'No 3D Secure (transaction will be denied if 3D Secure is required)',
				} );
				break;

			case 'ACDC3DS':
				await this.advancedCardProcessing.setup( {
					enableGateway: true,
					threeDSecure: 'Always trigger 3D Secure',
				} );
				break;

			case 'OXXO':
				await this.oxxo.setup( { enableGateway: true } );
				break;

			case 'DebitOrCreditCard':
				await this.standardPayments.visit();
				await this.standardPayments.enableAlternativePaymentMethods( [
					'Credit or debit cards',
				] );
				await this.standardPayments
					.standardCardButtonCheckbox()
					.uncheck();
				await this.standardPayments.saveChanges();
				await this.advancedCardProcessing.setup( {
					enableGateway: false,
				} );
				break;

			case 'StandardCardButton':
				await this.standardPayments.setup( {
					standardCardButton: true,
				} );
				await this.advancedCardProcessing.setup( {
					enableGateway: false,
				} );
				await this.standardCardButton.setup( { enableGateway: true } );
				break;

			case 'PayUponInvoice':
				// Is activated before merchant connection
				await this.payUponInvoice.setup( { enableGateway: true } );
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
			await this.activateWpDebuggingPlugin();
		}

		if ( data.wpDebugging === false ) {
			await this.deactivateWpDebuggingPlugin();
		}

		if ( data.subscription === true ) {
			await this.activateWcSubscriptionsPlugin();
		}

		if ( data.subscription === false ) {
			await this.deactivateWcSubscriptionsPlugin();
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

	configurePcp = async ( data: PcpConfig ) => {
		if (
			! ( await this.requestUtils.isPluginInstalled( pcpPlugin.slug ) )
		) {
			await this.plugins.installPluginFromFile( pcpPlugin.zipFilePath );
		}
		await this.requestUtils.activatePlugin( pcpPlugin.slug );

		if ( data.merchant ) {
			if ( data.clearPCPDB ) {
				await this.clearPcpDb( data.merchant );
			}

			if ( data.merchantIsDisconnected ) {
				await this.disconnectMerchant();
				return;
			}

			await this.connectMerchant( data.merchant, {
				enablePayUponInvoice: data.enablePayUponInvoice || false,
			} );
		}

		if ( data.standardPayments ) {
			await this.standardPayments.setup( data.standardPayments );
		}

		if ( data.payLater ) {
			await this.payLater.setup( data.payLater );
		}

		if ( data.advancedCardProcessing ) {
			await this.advancedCardProcessing.setup(
				data.advancedCardProcessing
			);
		}

		if ( data.standardCardButton ) {
			await this.standardCardButton.setup( data.standardCardButton );
		}

		if ( data.oxxo ) {
			await this.oxxo.setup( data.oxxo );
		}

		if ( data.payUponInvoice ) {
			await this.payUponInvoice.setup( data.payUponInvoice );
		}
	};
}
