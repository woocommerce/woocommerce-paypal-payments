/**
 * External dependencies
 */
import { execFileSync  } from 'node:child_process';
import { WpCliEnvType } from '@inpsyde/playwright-utils/build/@types/wp-cli';
import {
	WooCommerceApi,
	RequestUtils,
	Plugins,
	WooCommerceUtils,
	restLogin,
	guestStorageState,
	expect,
	WpCli,
	updateDotenv,
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
	shopSettings,
	shippingZones,
	taxSettings,
	products,
	coupons,
	customers,
	disableNoncePlugin,
	disableWcSetupWizard,
	subscriptionsPlugin,
	pcpPlugin,
	PcpMerchant,
	PcpConfig,
	pcpPluginUpdate,
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
		let start, end;
		switch ( method ) {
			case 'PayPal':
				// Is enabled by default within Standard Payments
				break;

			case 'PayLater':
				start = Date.now();
				await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
				await this.standardPayments.setup( { vaulting: false } );
				await this.payLater.setup( { enableGateway: true } );
				end = Date.now();
				console.log( `Pay Later configured in ${ ( end - start ) / 1000 } seconds` );

				break;

			case 'Venmo':
				start = Date.now();
				await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
				await this.standardPayments.setup( {
					enableAlternativePaymentMethods: [ 'Venmo' ],
				} );
				end = Date.now();
				console.log( `Venmo configured in ${ ( end - start ) / 1000 } seconds` );
				break;

			case 'ACDC':
				start = Date.now();
				await this.advancedCardProcessing.setup( {
					enableGateway: true,
					threeDSecure:
						'No 3D Secure (transaction will be denied if 3D Secure is required)',
				} );
				end = Date.now();
				console.log( `ACDC configured in ${ ( end - start ) / 1000 } seconds` );

				break;

			case 'ACDC3DS':
				start = Date.now();
				await this.advancedCardProcessing.setup( {
					enableGateway: true,
					threeDSecure: 'Always trigger 3D Secure',
				} );
				end = Date.now();
				console.log( `ACDC 3D Secure configured in ${ ( end - start ) / 1000 } seconds` );
				break;

			case 'OXXO':
				start = Date.now();
				await this.oxxo.setup( { enableGateway: true } );
				end = Date.now();
				console.log( `OXXO configured in ${ ( end - start ) / 1000 } seconds` );
				break;

			case 'DebitOrCreditCard':
				await this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } );
				await this.standardPayments.setup( {
					enableAlternativePaymentMethods: [
						'Credit or debit cards',
					],
					standardCardButton: false,
				} );
				await this.advancedCardProcessing.setup( {
					enableGateway: false,
				} );
				end = Date.now();
				console.log( `Debit or Credit Card configured in ${ ( end - start ) / 1000 } seconds` );
				break;

			case 'StandardCardButton':
				start = Date.now();
				await this.standardPayments.setup( {
					standardCardButton: true,
				} );
				await this.advancedCardProcessing.setup( {
					enableGateway: false,
				} );
				await this.standardCardButton.setup( { enableGateway: true } );
				end = Date.now();
				console.log( `Standard Card Button configured in ${ ( end - start ) / 1000 } seconds` );
				break;

			case 'PayUponInvoice':
				start = Date.now();
				// Is activated before merchant connection
				await this.payUponInvoice.setup( { enableGateway: true } );
				end = Date.now();
				console.log( `Pay Upon Invoice configured in ${ ( end - start ) / 1000 } seconds` );
				break;
		}
	};

	/**
	 * Reset the WordPress environment to a clean state.
	 * Supports 'localhost' (PowerShell/XAMPP) and 'ssh' env types.
	 */
	async resetEnvironment(): Promise< void > {
		let start, end;
		start = Date.now();

		const envType = process.env.WPCLI_ENV_TYPE as WpCliEnvType;

		let command: string;
		let args: string[];

		if ( envType === 'localhost' ) {
			const psCommand = [
				'$env:PATH += ";C:\\xampp\\mysql\\bin"',
				`cd ${ process.env.WPCLI_PATH }`,
				'wp db reset --yes',
				'wp config create --dbname=geniuscourse --dbuser=root --dbpass="" --dbhost=localhost --skip-check --force',
				`wp core install --url="${ process.env.WP_BASE_URL }" --title="Test Site" --admin_user="admin" --admin_password="password" --admin_email="test@test.com"`,
				'wp plugin delete --all',
				'wp theme delete --all',
				'wp plugin install woocommerce --activate',
				'wp cache flush',
			].join( '; ' );

			command = 'powershell';
			args = [ '-NoProfile', '-Command', psCommand ];
		} else if ( envType === 'ssh' ) {
			const WP_VERSION = process.env.WP_VERSION ?? '6.9';
			const WP_TYPE = process.env.WP_TYPE ?? 'single';
			const remoteCmd = `$HOME/bin/reset-wp.sh --wp-version=${ WP_VERSION } --wp-type=${ WP_TYPE }`;

			command = 'ssh';
			args = [
				`${ process.env.SSH_LOGIN }@${ process.env.SSH_HOST }`,
				'-p', process.env.SSH_PORT!,
				'-o', 'StrictHostKeyChecking=no',
				remoteCmd,
			];
		} else {
			throw new Error( `Unsupported WPCLI_ENV_TYPE: ${ envType }` );
		}

		console.log( `Executing: ${ command } ${ args.join( ' ' ) }` );

		execFileSync( command, args, {
			stdio: 'inherit',
			timeout: 60_000,
		} );
		end = Date.now();
		console.log( `Environment reset completed in ${ ( end - start ) / 1000 } seconds` );
	}

	/**
	 * Create admin and guest storage states.
	 */
	async createStorageStates(): Promise< void > {
		let start, end;
		start = Date.now();
		await restLogin( {
			baseURL: process.env.WP_BASE_URL!,
			storageStatePath: process.env.STORAGE_STATE_PATH_ADMIN,
			httpCredentials: {
				username: process.env.WP_BASIC_AUTH_USER,
				password: process.env.WP_BASIC_AUTH_PASS,
			},
			user: {
				username: process.env.WP_USERNAME,
				password: process.env.WP_PASSWORD,
			},
		} );
		end = Date.now();
		console.log( `Admin storage state created in ${ ( end - start ) / 1000 } seconds` );

		start = Date.now();
		await guestStorageState( {
			baseURL: process.env.WP_BASE_URL!,
			httpCredentials: {
				username: process.env.WP_BASIC_AUTH_USER,
				password: process.env.WP_BASIC_AUTH_PASS,
			},
			storageStatePath: `${ process.env.STORAGE_STATE_PATH }/guest.json`,
		} );
		end = Date.now();
		console.log( `Guest storage state created in ${ ( end - start ) / 1000 } seconds` );
	}

	/**
	 * Configures store according to the data provided
	 *
	 * @param {Object} data see /resources/woocommerce-config.ts
	 */
	configureStore = async ( data ) => {
	const start = Date.now();
	const tasks: Promise< unknown >[] = [];

	// CLI operation — independent
	if ( data.wpDebugging === true ) {
		tasks.push( this.cli.setWpConst( { WP_DEBUG: true, SCRIPT_DEBUG: true } ) );
	}
	if ( data.wpDebugging === false ) {
		tasks.push( this.cli.setWpConst( { WP_DEBUG: false, SCRIPT_DEBUG: false } ) );
	}

	// Plugin activate/deactivate — API-based, independent
	if ( data.subscription === true ) {
		tasks.push( this.activateWcSubscriptionsPlugin() );
	}
	if ( data.subscription === false ) {
		tasks.push( this.deactivateWcSubscriptionsPlugin() );
	}

	// Pages — sequential internally if page-based, but parallel with other tasks
	if ( data.classicPages === true ) {
		tasks.push( ( async () => {
			await this.wooCommerceUtils.activateClassicCartPage();
			await this.wooCommerceUtils.activateClassicCheckoutPage();
		} )() );
	}
	if ( data.classicPages === false ) {
		tasks.push( ( async () => {
			await this.wooCommerceUtils.activateBlockCartPage();
			await this.wooCommerceUtils.activateBlockCheckoutPage();
		} )() );
	}

	// API calls — independent
	if ( data.settings?.general ) {
		tasks.push( this.wooCommerceApi.updateGeneralSettings( data.settings.general ) );
	}
	if ( data.taxes ) {
		tasks.push( this.wooCommerceUtils.setTaxes( data.taxes ) );
	}
	if ( data.customer ) {
		tasks.push( this.restoreCustomer( data.customer ) );
	}

	await Promise.all( tasks );
	console.log( `Store configured in ${ ( Date.now() - start ) / 1000 }s` );
};

	updatePcpPlugin = async () => {
		let start, end;
		start = Date.now();
		console.log( `Updating PCP plugin from ${ pcpPluginUpdate.zipFilePath }....` );
		await this.plugins.installPluginFromFile( pcpPluginUpdate.zipFilePath );
		end = Date.now();
		console.log( `PCP Plugin updated in ${ ( end - start ) / 1000 } seconds` );
	}

	configurePcp = async ( data: PcpConfig ) => {
		let start, end;
		if (
			! ( await this.requestUtils.isPluginInstalled( pcpPlugin.slug ) )
		) {
			start = Date.now();
			console.log( `Installing PCP plugin from ${ pcpPlugin.zipFilePath }....` );
			await this.plugins.installPluginFromFile( pcpPlugin.zipFilePath );
			end = Date.now();
			console.log( `PCP Plugin installed in ${ ( end - start ) / 1000 } seconds` );
		}
		start = Date.now();
		console.log( `Activating PCP plugin...` );
		await this.requestUtils.activatePlugin( pcpPlugin.slug );
		end = Date.now();
		console.log( `PCP plugin activated in ${ ( end - start ) / 1000 } seconds` );

		if ( data.merchant ) {
			if ( data.clearPCPDB ) {
				// Make sure merchant is connected to clear PCP DB
				start = Date.now();
				await this.disconnectMerchant();
				await this.connectMerchant( data.merchant, {
					enablePayUponInvoice: !! data.enablePayUponInvoice,
				} );
				await this.clearPcpDb();
				end = Date.now();
				console.log( `PCP DB cleared in ${ ( end - start ) / 1000 } seconds` );
			}

			if ( data.merchantIsDisconnected ) {
				await this.disconnectMerchant();
				return;
			}

			
			start = Date.now();
			await this.disconnectMerchant();
			end = Date.now();
			console.log( `Merchant disconnected in ${ ( end - start ) / 1000 } seconds` );

			start = Date.now();
			await this.connectMerchant( data.merchant, {
				enablePayUponInvoice: !! data.enablePayUponInvoice,
			} );
			end = Date.now();
			console.log( `Merchant connected in ${ ( end - start ) / 1000 } seconds` );
		}

		if ( data.standardPayments ) {
			start = Date.now();
			await this.standardPayments.setup( data.standardPayments );
			end = Date.now();
			console.log( `Standard payments configured in ${ ( end - start ) / 1000 } seconds` );
		}

		if ( data.payLater ) {
			start = Date.now();
			await this.payLater.setup( data.payLater );
			end = Date.now();
			console.log( `Pay Later configured in ${ ( end - start ) / 1000 } seconds` );
		}

		if ( data.advancedCardProcessing ) {
			start = Date.now();
			await this.advancedCardProcessing.setup(
				data.advancedCardProcessing
			);
			end = Date.now();
			console.log( `Advanced Card Processing configured in ${ ( end - start ) / 1000 } seconds` );
		}

		if ( data.standardCardButton ) {
			start = Date.now();
			await this.standardCardButton.setup( data.standardCardButton );
			end = Date.now();
			console.log( `Standard Card Button configured in ${ ( end - start ) / 1000 } seconds` );
		}

		if ( data.oxxo ) {
			start = Date.now();
			await this.oxxo.setup( data.oxxo );
			end = Date.now();
			console.log( `OXXO configured in ${ ( end - start ) / 1000 } seconds` );
		}

		if ( data.payUponInvoice ) {
			start = Date.now();
			await this.payUponInvoice.setup( data.payUponInvoice );
			end = Date.now();
			console.log( `Pay Upon Invoice configured in ${ ( end - start ) / 1000 } seconds` );
		}
	};

	/**
	 * Run full WooCommerce store setup (equivalent to the setup project).
	 * Requires fixture instances since these are API/page-based operations.
	 */
	async setupStore(): Promise< void > {
		let start;

		// Phase 1: Plugins + Theme (sequential — shares browser page)
		start = Date.now();
		await this.requestUtils.setPermalinks( '/%postname%/' );
		await this.installAndActivatePlugin( disableNoncePlugin );
		await this.installAndActivatePlugin( disableWcSetupWizard );

		if ( ! ( await this.requestUtils.isPluginInstalled( 'woocommerce' ) ) ) {
			await this.requestUtils.installPlugin( 'woocommerce' );
		}
		await this.requestUtils.activatePlugin( 'woocommerce' );

		if ( ! ( await this.requestUtils.isPluginInstalled( subscriptionsPlugin.slug ) ) ) {
			await this.plugins.installPluginFromFile( subscriptionsPlugin.zipFilePath );
		}
		await this.requestUtils.deactivatePlugin( subscriptionsPlugin.slug );

		const themeSlug = 'storefront';
		if ( ! ( await this.requestUtils.isThemeInstalled( themeSlug ) ) ) {
			await this.requestUtils.installTheme( themeSlug );
		}
		await this.requestUtils.activateTheme( themeSlug );
		console.log( `Phase 1 (permalinks, plugins, theme) in ${ ( Date.now() - start ) / 1000 }s` );

		// Phase 2: API keys + site visibility (parallel, both need WC active)
		start = Date.now();
		await Promise.all( [
			this.wooCommerceUtils.setSiteVisibility(),
			( async () => {
				if ( ! ( await this.wooCommerceUtils.apiKeysExist() ) ) {
					const apiKeys = await this.wooCommerceUtils.createApiKeys();
					if ( ! process.env.CI ) {
						await updateDotenv( './.env', apiKeys );
					}
					for ( const [ key, value ] of Object.entries( apiKeys ) ) {
						process.env[ key ] = value;
					}
				}
			} )(),
		] );
		console.log( `Phase 2 (API keys + visibility) in ${ ( Date.now() - start ) / 1000 }s` );

		// Phase 3: Everything else (parallel — all API-based, independent of each other)
		start = Date.now();
		const couponItems = {};
		const cartItems = {};

		await Promise.all( [
			// Pages (sequential internally — may share page context)
			( async () => {
				await this.wooCommerceUtils.publishBlockCartPage();
				await this.wooCommerceUtils.publishBlockCheckoutPage();
				await this.wooCommerceUtils.publishClassicCartPage();
				await this.wooCommerceUtils.publishClassicCheckoutPage();
			} )(),
			// Emails
			Promise.all(
				[
					'email_new_order', 'email_cancelled_order', 'email_failed_order',
					'email_customer_on_hold_order', 'email_customer_processing_order',
					'email_customer_completed_order', 'email_customer_refunded_order',
					'email_customer_note', 'email_customer_reset_password',
					'email_customer_new_account',
				].map( ( type ) =>
					this.wooCommerceApi.updateEmailSubSettings( type, { enabled: 'no' } )
				)
			),
			// Shop config
			( async () => {
				await this.wooCommerceApi.updateGeneralSettings( shopSettings.germany.general );
				await this.wooCommerceUtils.configureShippingZone( shippingZones.worldwide );
				await this.wooCommerceUtils.setTaxes( taxSettings.including );
			} )(),
			// Orders
			this.wooCommerceApi.deleteAllOrders(),
			// Coupons
			( async () => {
				await Promise.all(
					Object.entries( coupons ).map( async ( [ _key, coupon ] ) => {
						const created = await this.wooCommerceUtils.createCoupon( coupon );
						couponItems[ coupon.code ] = { id: created.id };
					} )
				);
				process.env.COUPONS = JSON.stringify( couponItems );
			} )(),
			// Products
			( async () => {
				await Promise.all(
					Object.entries( products ).map( async ( [ _key, product ] ) => {
						if ( ! product.slug.includes( 'subscription' ) ) {
							const created = await this.wooCommerceUtils.createProduct( product );
							cartItems[ product.slug ] = { id: created.id };
						}
					} )
				);
				process.env.PRODUCTS = JSON.stringify( cartItems );
			} )(),
		] );
		console.log( `Phase 3 (pages, emails, config, data) in ${ ( Date.now() - start ) / 1000 }s` );
	}

	/**
	 * Helper: install plugin from file if not installed, then activate.
	 */
	private async installAndActivatePlugin( plugin ): Promise< void > {
		if ( ! ( await this.requestUtils.isPluginInstalled( plugin.slug ) ) ) {
			await this.plugins.installPluginFromFile( plugin.zipFilePath );
		}
		await this.requestUtils.activatePlugin( plugin.slug );
	}
}
