/**
 * External dependencies
 */
import {
	WooCommerceApi,
	RequestUtils,
	Plugins,
	WooCommerceUtils,
	restLogin,
	expect,
	WpCli,
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
	pcpPlugin,
	PcpMerchant,
	PcpConfig,
} from '../resources';
import { generateRandomString, getCustomerStorageStateName } from './helpers';
import urls from './urls';
import { PcpApi } from './pcp-api';

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
	cli: WpCli;
	pcpApi: PcpApi;

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
		cli,
		pcpApi,
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
		this.cli = cli;
		this.pcpApi = pcpApi;
	}

	activateWcSubscriptionsPlugin = async () => {
		await this.requestUtils.activatePlugin( subscriptionsPlugin.slug );
	};

	deactivateWcSubscriptionsPlugin = async () => {
		await this.requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
	};

	restoreCustomer = async ( customer: WooCommerce.CreateCustomer ) => {
		await this.wooCommerceUtils.deleteCustomer( customer );
		if ( customer.username ) {
			const user = await this.requestUtils.getUserByName(
				customer.username
			);
			if ( user.length ) {
				await this.requestUtils.deleteUser( user[ 0 ].id );
			}
		}
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

	/**
	 * Onboard with Pay Upon Invoice (PUI)
	 * Only for German merchant
	 *
	 */
	onboardWithPui = async () => {
		const nonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/"update_signup_links_nonce":"([^"]+)"/
		);

		const response = await this.requestUtils.request.post(
			'/?wc-ajax=ppc-update-signup-links',
			{
				data: {
					nonce,
					settings: { 'ppcp-onboarding-pui': true },
				},
			}
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};

	/**
	 * Connects merchant via form post request
	 *
	 * @param merchant
	 * @param options
	 */
	connectMerchant = async (
		merchant: PcpMerchant,
		options = {
			enablePayUponInvoice: false,
		}
	) => {
		const ppcpNonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/<input type="hidden" name="ppcp-nonce" value="([^"]+)">/
		);

		const wpnonce = await this.requestUtils.getPageNonce(
			urls.pcp.connection
		);

		const formData = {
			_wpnonce: wpnonce,
			'ppcp-nonce': ppcpNonce,
			'ppcp[sandbox_on]': '1',
			'ppcp[merchant_email_production]': '',
			'ppcp[merchant_id_production]': '',
			'ppcp[client_id_production]': '',
			'ppcp[client_secret_production]': '',
			'ppcp[merchant_email_sandbox]': merchant.email,
			'ppcp[merchant_id_sandbox]': merchant.account_id,
			'ppcp[client_id_sandbox]': merchant.client_id,
			'ppcp[client_secret_sandbox]': merchant.client_secret,
			'ppcp[soft_descriptor]': '',
			'ppcp[prefix]': `${ generateRandomString( 10 ) }-`,
			'ppcp[stay_updated]': '1',
			'ppcp[subtotal_mismatch_behavior]': 'extra_line',
			'ppcp[subtotal_mismatch_line_name]': '',
			save: 'Save changes',
		};

		if ( options.enablePayUponInvoice === true ) {
			formData.ppcp_onboarding_dcc = 'basic';
			await this.onboardWithPui();
		}

		const response = await this.requestUtils.submitPageForm(
			urls.pcp.connection,
			formData
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};

	/**
	 * Disconnects merchant via form post request
	 *
	 */
	disconnectMerchant = async () => {
		const ppcpNonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/<input type="hidden" name="ppcp-nonce" value="([^"]+)">/
		);
		const wpnonce = await this.requestUtils.getPageNonce(
			urls.pcp.connection
		);
		const formData = {
			_wpnonce: wpnonce,
			'ppcp-nonce': ppcpNonce,
			'ppcp[merchant_email_production]': '',
			'ppcp[merchant_id_production]': '',
			'ppcp[client_id_production]': '',
			'ppcp[client_secret_production]': '',
			'ppcp[merchant_email_sandbox]': '',
			'ppcp[merchant_id_sandbox]': '',
			'ppcp[client_id_sandbox]': '',
			'ppcp[client_secret_sandbox]': '',
			'ppcp[soft_descriptor]': '',
			'ppcp[prefix]': '',
			'ppcp[stay_updated]': '1',
			'ppcp[subtotal_mismatch_behavior]': 'extra_line',
			'ppcp[subtotal_mismatch_line_name]': '',
			save: 'Save changes',
		};
		const response = await this.requestUtils.submitPageForm(
			urls.pcp.connection,
			formData
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
	};

	/**
	 * Clear PCP DB via request
	 *
	 */
	clearPcpDb = async () => {
		const nonce = await this.requestUtils.getRegexMatchValueOnPage(
			urls.pcp.connection,
			/"clearDb":\{[^}]*"nonce":"([^"]+)"/
		);

		const response = await this.requestUtils.request.post(
			'/?wc-ajax=ppcp-clear-db',
			{ data: { nonce } }
		);
		const result = response.ok();
		await expect( result ).toBeTruthy();
		return result;
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
				await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
				await this.standardPayments.setup( { vaulting: false } );
				await this.payLater.setup( { enableGateway: true } );
				break;

			case 'Venmo':
				await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
				await this.standardPayments.setup( {
					enableAlternativePaymentMethods: [ 'Venmo' ],
				} );
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
				await this.standardPayments.setup( {
					enableAlternativePaymentMethods: [
						'Credit or debit cards',
					],
					standardCardButton: false,
				} );
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
			await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
		}

		if ( data.wpDebugging === false ) {
			await this.cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } );
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
				// Make sure merchant is connected to clear PCP DB
				await this.disconnectMerchant();
				await this.connectMerchant( data.merchant, {
					enablePayUponInvoice: !! data.enablePayUponInvoice,
				} );
				await this.clearPcpDb();
			}

			if ( data.merchantIsDisconnected ) {
				await this.disconnectMerchant();
				return;
			}

			await this.disconnectMerchant();
			await this.connectMerchant( data.merchant, {
				enablePayUponInvoice: !! data.enablePayUponInvoice,
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
