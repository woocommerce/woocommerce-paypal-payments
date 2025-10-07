/**
 * External dependencies
 */
import fs from 'fs';
import { APIRequestContext, Page } from '@playwright/test';
import {
	test as base,
	expect,
	WooCommerceApi,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { PayPalAPI } from './paypal-api';
import { PayPalUI } from './frontend/paypal-ui';
import { Utils } from './utils';

// PCP tabs
import {
	Connection,
	StandardPayments,
	PayLater,
	AdvancedCardProcessing,
	StandardCardButton,
	OXXO,
	PayUponInvoice,
	WooCommerceOrderEdit,
	WooCommerceSubscriptionEdit,
} from './admin';

// WooCommerce front end
import {
	Shop,
	Product,
	Cart,
	Checkout,
	ClassicCart,
	ClassicCheckout,
	PayForOrder,
	OrderReceived,
	CustomerAccount,
	CustomerPaymentMethods,
	CustomerSubscriptions,
	ClassicPayForOrder,
} from './frontend';

type BaseExtend = {
	ppapi: PayPalAPI;
	ppui: PayPalUI;
	visitorPage: Page;
	visitorRequest: APIRequestContext;
	visitorWooCommerceApi: WooCommerceApi;

	// PCP tabs
	connection: Connection;
	standardPayments: StandardPayments;
	payLater: PayLater;
	advancedCardProcessing: AdvancedCardProcessing;
	standardCardButton: StandardCardButton;
	oxxo: OXXO;
	payUponInvoice: PayUponInvoice;

	// WooCommerce dashboard
	wooCommerceOrderEdit: WooCommerceOrderEdit;
	wooCommerceSubscriptionEdit: WooCommerceSubscriptionEdit;

	// WooCommerce Guest front end
	shop: Shop;
	product: Product;
	cart: Cart;
	checkout: Checkout;
	classicCart: ClassicCart;
	classicCheckout: ClassicCheckout;
	classicPayForOrder: ClassicPayForOrder;
	payForOrder: PayForOrder;
	orderReceived: OrderReceived;
	customerAccount: CustomerAccount;
	customerPaymentMethods: CustomerPaymentMethods;
	customerSubscriptions: CustomerSubscriptions;

	// Utils & preconditions
	utils: Utils;
};

const test = base.extend< BaseExtend >( {
	ppapi: async ( { request }, use ) => {
		await use( new PayPalAPI( { request } ) );
	},
	visitorPage: async ( { browser }, use, testInfo ) => {
		// check if visitor is specified in test otherwise use guest
		const storageStateName =
			testInfo.annotations?.find( ( el ) => el.type === 'visitor' )
				?.description || 'guest';
		const storageStatePath = `${ process.env.STORAGE_STATE_PATH }/${ storageStateName }.json`;
		// apply current visitor's storage state to the context
		const context = await browser.newContext( {
			storageState: fs.existsSync( storageStatePath )
				? storageStatePath
				: undefined,
		} );
		const page = await context.newPage();
		await use( page );
		await page.close();
		await context.close();
	},
	visitorRequest: async ( { visitorPage }, use ) => {
		const request = visitorPage.request;
		await use( request );
	},
	visitorWooCommerceApi: async ( { visitorRequest }, use ) => {
		await use( new WooCommerceApi( { request: visitorRequest } ) );
	},
	ppui: async ( { visitorPage, ppapi }, use ) => {
		await use( new PayPalUI( { page: visitorPage, ppapi } ) );
	},

	// PCP tabs
	connection: async ( { page }, use ) => {
		await use( new Connection( { page } ) );
	},
	standardPayments: async ( { page }, use ) => {
		await use( new StandardPayments( { page } ) );
	},
	payLater: async ( { page }, use ) => {
		await use( new PayLater( { page } ) );
	},
	advancedCardProcessing: async ( { page }, use ) => {
		await use( new AdvancedCardProcessing( { page } ) );
	},
	standardCardButton: async ( { page }, use ) => {
		await use( new StandardCardButton( { page } ) );
	},
	oxxo: async ( { page }, use ) => {
		await use( new OXXO( { page } ) );
	},
	payUponInvoice: async ( { page }, use ) => {
		await use( new PayUponInvoice( { page } ) );
	},

	// WooCommerce dashboard
	wooCommerceOrderEdit: async ( { page }, use ) => {
		await use( new WooCommerceOrderEdit( { page } ) );
	},
	wooCommerceSubscriptionEdit: async ( { page }, use ) => {
		await use( new WooCommerceSubscriptionEdit( { page } ) );
	},

	// WooCommerce front end
	shop: async ( { visitorPage, ppui }, use ) => {
		await use( new Shop( { page: visitorPage, ppui } ) );
	},
	product: async ( { visitorPage, ppui }, use ) => {
		await use( new Product( { page: visitorPage, ppui } ) );
	},
	cart: async ( { visitorPage, ppui }, use ) => {
		await use( new Cart( { page: visitorPage, ppui } ) );
	},
	checkout: async ( { visitorPage, ppui }, use ) => {
		await use( new Checkout( { page: visitorPage, ppui } ) );
	},
	classicCart: async ( { visitorPage, ppui }, use ) => {
		await use( new ClassicCart( { page: visitorPage, ppui } ) );
	},
	classicCheckout: async ( { visitorPage, ppui }, use ) => {
		await use( new ClassicCheckout( { page: visitorPage, ppui } ) );
	},
	classicPayForOrder: async ( { visitorPage, ppui }, use ) => {
		await use( new ClassicPayForOrder( { page: visitorPage, ppui } ) );
	},
	payForOrder: async ( { visitorPage, ppui }, use ) => {
		await use( new PayForOrder( { page: visitorPage, ppui } ) );
	},
	orderReceived: async ( { visitorPage, ppui }, use ) => {
		await use( new OrderReceived( { page: visitorPage, ppui } ) );
	},
	customerAccount: async ( { visitorPage, ppui }, use ) => {
		await use( new CustomerAccount( { page: visitorPage, ppui } ) );
	},
	customerPaymentMethods: async ( { visitorPage, ppui }, use ) => {
		await use( new CustomerPaymentMethods( { page: visitorPage, ppui } ) );
	},
	customerSubscriptions: async ( { visitorPage, ppui }, use ) => {
		await use( new CustomerSubscriptions( { page: visitorPage, ppui } ) );
	},

	// Utils & preconditions
	utils: async (
		{
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
		},
		use
	) => {
		await use(
			new Utils( {
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
			} )
		);
	},
} );

export { test, expect };
